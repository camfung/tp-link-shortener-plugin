<?php

declare(strict_types=1);

namespace ShortCode;

use ShortCode\DTO\GenerateShortCodeRequest;
use ShortCode\DTO\GenerateShortCodeResponse;
use ShortCode\Exception\ApiException;
use ShortCode\Exception\ValidationException;
use ShortCode\Exception\NetworkException;
use ShortCode\Http\HttpClientInterface;
use ShortCode\Http\CurlHttpClient;

class GenerateShortCodeClient
{
    private const BASE_URL = 'https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev';
    private const DEFAULT_ENDPOINT = self::BASE_URL . '/generate-short-code';

    public const ENDPOINT_FAST = '/generate-short-code/fast';
    public const ENDPOINT_SMART = '/generate-short-code/smart';
    public const ENDPOINT_AI = '/generate-short-code/ai';
    public const ENDPOINT_LEGACY = '/generate-short-code';

    private string $baseUrl;
    private string $endpointPath;
    private HttpClientInterface $httpClient;
    private int $timeout;

    public function __construct(
        ?string $endpoint = null,
        ?HttpClientInterface $httpClient = null,
        int $timeout = 15
    ) {
        if ($endpoint === null) {
            $this->baseUrl = self::BASE_URL;
            $this->endpointPath = self::ENDPOINT_LEGACY;
        } else {
            $endpoint = rtrim($endpoint, '/');
            $lastSlashPos = strrpos($endpoint, '/generate-short-code');
            if ($lastSlashPos !== false) {
                $this->baseUrl = substr($endpoint, 0, $lastSlashPos);
                $this->endpointPath = substr($endpoint, $lastSlashPos);
            } else {
                $this->baseUrl = $endpoint;
                $this->endpointPath = self::ENDPOINT_LEGACY;
            }
        }

        $this->timeout = $timeout;
        $this->httpClient = $httpClient ?? new CurlHttpClient($timeout);
    }

    /**
     * Set the endpoint type (fast, smart, or ai)
     */
    public function setEndpointType(string $endpointPath): void
    {
        $this->endpointPath = $endpointPath;
    }

    /**
     * Get the full endpoint URL
     */
    private function getFullEndpoint(): string
    {
        return $this->baseUrl . $this->endpointPath;
    }

    public function generateShortCode(GenerateShortCodeRequest $request): GenerateShortCodeResponse
    {
        $this->log_to_file('=== SHORT CODE GENERATION REQUEST START ===');
        $payload = $request->toArray();
        $this->log_to_file('Request payload: ' . json_encode($payload));
        $fullEndpoint = $this->getFullEndpoint();
        $this->log_to_file('Endpoint: ' . $fullEndpoint);

        try {
            $this->log_to_file('Sending POST request to API...');
            $response = $this->httpClient->request('POST', $fullEndpoint, [
                'headers' => [
                    'Content-Type: application/json',
                ],
                'body' => json_encode($payload),
                'timeout' => $this->timeout,
            ]);
            $this->log_to_file('Received response with status code: ' . $response->getStatusCode());
            $this->log_to_file('Response body: ' . $response->getBody());
        } catch (NetworkException $e) {
            $this->log_to_file('EXCEPTION - NetworkException: ' . $e->getMessage());
            $this->log_to_file('=== SHORT CODE GENERATION REQUEST END ===');
            throw $e;
        } catch (\Exception $e) {
            $this->log_to_file('EXCEPTION - ' . get_class($e) . ': ' . $e->getMessage());
            $this->log_to_file('=== SHORT CODE GENERATION REQUEST END ===');
            throw new NetworkException($e->getMessage(), $e->getCode(), $e);
        }

        $parsedResponse = $this->parseResponse($response->getStatusCode(), $response->getBody());
        $this->log_to_file('SUCCESS - Generated short code: ' . $parsedResponse->getShortCode() . ' using method: ' . $parsedResponse->getMethod());
        $this->log_to_file('=== SHORT CODE GENERATION REQUEST END ===');
        return $parsedResponse;
    }

    private function parseResponse(int $statusCode, string $body): GenerateShortCodeResponse
    {
        $this->log_to_file('Parsing response - Status Code: ' . $statusCode);
        $this->log_to_file('Raw response body: ' . $body);

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = sprintf('Invalid JSON response: %s', json_last_error_msg());
            $this->log_to_file('ERROR - JSON parsing failed: ' . $error_msg);
            throw new ApiException($error_msg, $statusCode);
        }

        $this->log_to_file('JSON decoded successfully. Data structure: ' . json_encode($data));

        if ($statusCode >= 400) {
            $message = $data['message'] ?? 'API error';
            $this->log_to_file('ERROR - HTTP error status: ' . $statusCode . ', message: ' . $message);

            if ($statusCode === 400) {
                throw new ValidationException($message, $statusCode);
            }

            throw new ApiException($message, $statusCode);
        }

        $success = $data['success'] ?? false;
        $this->log_to_file('Response success flag: ' . ($success ? 'true' : 'false'));

        if (!$success) {
            $message = $data['message'] ?? 'API indicated failure';
            $this->log_to_file('ERROR - API returned success=false: ' . $message);
            throw new ApiException($message, $statusCode);
        }

        // Check for required fields (all endpoints have these)
        if (!isset($data['source']['short_code']) || !isset($data['source']['method']) || !isset($data['source']['was_modified'])) {
            $this->log_to_file('ERROR - Response missing required source fields');
            throw new ApiException('Response missing expected source fields (short_code, method, was_modified)', $statusCode);
        }

        $source = $data['source'];
        $method = $source['method'];

        $this->log_to_file('Response method: ' . $method);
        $this->log_to_file('short_code: ' . $source['short_code']);
        $this->log_to_file('was_modified: ' . ($source['was_modified'] ? 'true' : 'false'));

        // Extract optional fields based on endpoint type
        $originalCode = $source['original_code'] ?? null;
        $url = $source['url'] ?? null;
        $candidates = $source['candidates'] ?? null;
        $keyPhrases = $source['key_phrases'] ?? null;
        $entities = $source['entities'] ?? null;

        if ($originalCode !== null) {
            $this->log_to_file('original_code: ' . $originalCode);
        }
        if ($url !== null) {
            $this->log_to_file('url: ' . $url);
        }
        if ($candidates !== null) {
            $this->log_to_file('candidates: ' . json_encode($candidates));
        }
        if ($keyPhrases !== null) {
            $this->log_to_file('key_phrases: ' . json_encode($keyPhrases));
        }
        if ($entities !== null) {
            $this->log_to_file('entities: ' . json_encode($entities));
        }

        $this->log_to_file('message: ' . ($data['message'] ?? '(none)'));

        return new GenerateShortCodeResponse(
            $source['short_code'],
            $method,
            (bool)$source['was_modified'],
            $data['message'] ?? '',
            $originalCode,
            $url,
            $candidates,
            $keyPhrases,
            $entities
        );
    }

    public function getEndpoint(): string
    {
        return $this->getFullEndpoint();
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getEndpointPath(): string
    {
        return $this->endpointPath;
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Log to file for debugging
     */
    private function log_to_file($message): void
    {
        if (!defined('WP_CONTENT_DIR')) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/plugins/shortcode-api-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] SHORTCODE CLIENT: $message\n", FILE_APPEND);
    }
}
