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
    private const DEFAULT_ENDPOINT = 'https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/generate-short-code';

    private string $endpoint;
    private HttpClientInterface $httpClient;
    private int $timeout;

    public function __construct(
        ?string $endpoint = null,
        ?HttpClientInterface $httpClient = null,
        int $timeout = 15
    ) {
        $this->endpoint = rtrim($endpoint ?? self::DEFAULT_ENDPOINT, '/');
        $this->timeout = $timeout;
        $this->httpClient = $httpClient ?? new CurlHttpClient($timeout);
    }

    public function generateShortCode(GenerateShortCodeRequest $request): GenerateShortCodeResponse
    {
        $this->log_to_file('=== GEMINI SHORT CODE REQUEST START ===');
        $payload = $request->toArray();
        $this->log_to_file('Request payload: ' . json_encode($payload));
        $this->log_to_file('Endpoint: ' . $this->endpoint);

        try {
            $this->log_to_file('Sending POST request to Gemini API...');
            $response = $this->httpClient->request('POST', $this->endpoint, [
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
            $this->log_to_file('=== GEMINI SHORT CODE REQUEST END ===');
            throw $e;
        } catch (\Exception $e) {
            $this->log_to_file('EXCEPTION - ' . get_class($e) . ': ' . $e->getMessage());
            $this->log_to_file('=== GEMINI SHORT CODE REQUEST END ===');
            throw new NetworkException($e->getMessage(), $e->getCode(), $e);
        }

        $parsedResponse = $this->parseResponse($response->getStatusCode(), $response->getBody());
        $this->log_to_file('SUCCESS - Generated short code: ' . $parsedResponse->getShortCode());
        $this->log_to_file('=== GEMINI SHORT CODE REQUEST END ===');
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

        // Check for required fields
        $hasShortCode = isset($data['source']['short_code']);
        $hasOriginalCode = isset($data['source']['original_code']);
        $hasWasModified = isset($data['source']['was_modified']);
        $hasUrl = isset($data['source']['url']);

        $this->log_to_file('Field presence check - short_code: ' . ($hasShortCode ? 'YES' : 'NO') .
                          ', original_code: ' . ($hasOriginalCode ? 'YES' : 'NO') .
                          ', was_modified: ' . ($hasWasModified ? 'YES' : 'NO') .
                          ', url: ' . ($hasUrl ? 'YES' : 'NO'));

        if (!($hasShortCode && $hasOriginalCode && $hasWasModified && $hasUrl)) {
            $this->log_to_file('ERROR - Response missing required source fields');
            throw new ApiException('Response missing expected source fields', $statusCode);
        }

        $this->log_to_file('All required fields present. Building response object...');
        $this->log_to_file('short_code: ' . $data['source']['short_code']);
        $this->log_to_file('original_code: ' . $data['source']['original_code']);
        $this->log_to_file('was_modified: ' . ($data['source']['was_modified'] ? 'true' : 'false'));
        $this->log_to_file('url: ' . $data['source']['url']);
        $this->log_to_file('message: ' . ($data['message'] ?? '(none)'));

        return new GenerateShortCodeResponse(
            $data['source']['short_code'],
            $data['source']['original_code'],
            (bool)$data['source']['was_modified'],
            $data['source']['url'],
            $data['message'] ?? ''
        );
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
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

        $log_file = WP_CONTENT_DIR . '/plugins/gemini-shortcode-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] GEMINI CLIENT: $message\n", FILE_APPEND);
    }
}
