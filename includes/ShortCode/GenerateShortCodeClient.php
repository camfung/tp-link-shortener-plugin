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
    private const DEFAULT_BASE_URL = 'https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev';

    private string $baseUrl;
    private HttpClientInterface $httpClient;
    private int $timeout;

    public function __construct(
        ?string $baseUrl = null,
        ?HttpClientInterface $httpClient = null,
        int $timeout = 15
    ) {
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = $timeout;
        $this->httpClient = $httpClient ?? new CurlHttpClient($timeout);
    }

    public function generateShortCode(
        GenerateShortCodeRequest $request,
        GenerationTier $tier = GenerationTier::AI
    ): GenerateShortCodeResponse {
        $endpoint = $this->baseUrl . $tier->getPath();
        $this->log_to_file('=== SHORT CODE REQUEST START ===');
        $payload = $request->toArray();
        $this->log_to_file('Request payload: ' . json_encode($payload));
        $this->log_to_file('Endpoint: ' . $endpoint);
        $this->log_to_file('Tier: ' . $tier->value);

        try {
            $this->log_to_file('Sending POST request...');
            $response = $this->httpClient->request('POST', $endpoint, [
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
            $this->log_to_file('=== SHORT CODE REQUEST END ===');
            throw $e;
        } catch (\Exception $e) {
            $this->log_to_file('EXCEPTION - ' . get_class($e) . ': ' . $e->getMessage());
            $this->log_to_file('=== SHORT CODE REQUEST END ===');
            throw new NetworkException($e->getMessage(), $e->getCode(), $e);
        }

        $parsedResponse = $this->parseResponse($response->getStatusCode(), $response->getBody());
        $this->log_to_file('SUCCESS - Generated short code: ' . $parsedResponse->getShortCode());
        $this->log_to_file('=== SHORT CODE REQUEST END ===');
        return $parsedResponse;
    }

    public function fast(GenerateShortCodeRequest $request): GenerateShortCodeResponse
    {
        return $this->generateShortCode($request, GenerationTier::Fast);
    }

    public function smart(GenerateShortCodeRequest $request): GenerateShortCodeResponse
    {
        return $this->generateShortCode($request, GenerationTier::Smart);
    }

    public function ai(GenerateShortCodeRequest $request): GenerateShortCodeResponse
    {
        return $this->generateShortCode($request, GenerationTier::AI);
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

        $source = $data['source'] ?? null;
        if (!$source || !isset($source['short_code'], $source['was_modified'], $source['method'])) {
            $this->log_to_file('ERROR - Response missing required source fields');
            throw new ApiException('Response missing expected source fields', $statusCode);
        }

        $this->log_to_file('All required fields present. Building response object...');
        $this->log_to_file('short_code: ' . $source['short_code']);
        $this->log_to_file('method: ' . $source['method']);
        $this->log_to_file('was_modified: ' . ($source['was_modified'] ? 'true' : 'false'));

        $method = GenerationMethod::fromString($source['method']);

        return new GenerateShortCodeResponse(
            shortCode: $source['short_code'],
            wasModified: (bool)$source['was_modified'],
            method: $method,
            message: $data['message'] ?? '',
            originalCode: $source['original_code'] ?? null,
            url: $source['url'] ?? null,
            candidates: $source['candidates'] ?? [],
            keyPhrases: $source['key_phrases'] ?? [],
            entities: $source['entities'] ?? []
        );
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    private function log_to_file($message): void
    {
        if (!defined('WP_CONTENT_DIR')) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/plugins/gemini-shortcode-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] SHORTCODE CLIENT: $message\n", FILE_APPEND);
    }
}
