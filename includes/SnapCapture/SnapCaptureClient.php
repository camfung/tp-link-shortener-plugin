<?php

declare(strict_types=1);

namespace SnapCapture;

use SnapCapture\DTO\ScreenshotRequest;
use SnapCapture\DTO\ScreenshotResponse;
use SnapCapture\Exception\ApiException;
use SnapCapture\Exception\AuthenticationException;
use SnapCapture\Exception\NetworkException;
use SnapCapture\Exception\ValidationException;
use SnapCapture\Http\HttpClientInterface;
use SnapCapture\Http\CurlHttpClient;

/**
 * SnapCapture API Client
 *
 * Client for interacting with the SnapCapture API to capture website screenshots
 *
 * @package SnapCapture
 */
class SnapCaptureClient
{
    private const API_ENDPOINT = 'https://snapcapture1.p.rapidapi.com';
    private const API_HOST = 'snapcapture1.p.rapidapi.com';

    private string $apiKey;
    private HttpClientInterface $httpClient;

    /**
     * Constructor
     *
     * @param string $apiKey RapidAPI key for authentication
     * @param HttpClientInterface|null $httpClient HTTP client (defaults to CurlHttpClient)
     * @param int $timeout Request timeout in seconds (default: 30)
     */
    public function __construct(
        string $apiKey,
        ?HttpClientInterface $httpClient = null,
        int $timeout = 30
    ) {
        $this->apiKey = $apiKey;
        $this->httpClient = $httpClient ?? new CurlHttpClient($timeout);
    }

    /**
     * Capture a screenshot
     *
     * @param ScreenshotRequest $request Screenshot request parameters
     * @param bool $returnJson Whether to request JSON response with base64 data
     * @return ScreenshotResponse Screenshot response
     * @throws AuthenticationException If authentication fails
     * @throws ValidationException If validation fails
     * @throws NetworkException If network error occurs
     * @throws ApiException For other API errors
     */
    public function captureScreenshot(
        ScreenshotRequest $request,
        bool $returnJson = false
    ): ScreenshotResponse {
        $url = self::API_ENDPOINT . '/screenshot';
        if ($returnJson) {
            $url .= '?json=true';
        }

        $payload = $request->toArray();

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type: application/json',
                'X-RapidAPI-Key: ' . $this->apiKey,
                'X-RapidAPI-Host: ' . self::API_HOST,
            ],
            'body' => json_encode($payload),
        ]);

        $httpCode = $response->getStatusCode();
        $headers = $response->getHeaders();
        $body = $response->getBody();

        // Handle HTTP errors
        if ($httpCode >= 400) {
            $this->handleHttpErrors($httpCode, $body);
        }

        // Parse response based on format
        if ($returnJson) {
            return $this->parseJsonResponse($body, $headers);
        } else {
            return $this->parseBinaryResponse($body, $headers, $request->getFormat());
        }
    }

    /**
     * Ping the API to check health
     *
     * @return array Health check response
     * @throws NetworkException If network error occurs
     * @throws ApiException For other API errors
     */
    public function ping(): array
    {
        $url = self::API_ENDPOINT . '/ping';

        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 10,
        ]);

        $httpCode = $response->getStatusCode();
        $body = $response->getBody();

        // Decode JSON response
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(
                sprintf('Invalid JSON response: %s', json_last_error_msg()),
                $httpCode
            );
        }

        if ($httpCode !== 200) {
            throw new ApiException(
                sprintf('Ping failed with HTTP %d', $httpCode),
                $httpCode
            );
        }

        return $data;
    }

    /**
     * Parse JSON response
     *
     * @param string $body Response body
     * @param array $headers Response headers
     * @return ScreenshotResponse
     * @throws ApiException If JSON is invalid
     */
    private function parseJsonResponse(string $body, array $headers): ScreenshotResponse
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(
                sprintf('Invalid JSON response: %s', json_last_error_msg())
            );
        }

        if (!isset($data['screenshot_base64'])) {
            throw new ApiException('Missing screenshot_base64 in response');
        }

        $imageData = base64_decode($data['screenshot_base64']);
        if ($imageData === false) {
            throw new ApiException('Failed to decode base64 image data');
        }

        $cached = $data['cached'] ?? ($headers['x-cache-hit'] === 'true');
        $responseTimeMs = $data['response_time_ms'] ?? null;
        $contentType = $headers['content-type'] ?? 'image/jpeg';

        return new ScreenshotResponse($imageData, $cached, $responseTimeMs, $contentType);
    }

    /**
     * Parse binary response
     *
     * @param string $body Response body
     * @param array $headers Response headers
     * @param string $format Expected format
     * @return ScreenshotResponse
     */
    private function parseBinaryResponse(
        string $body,
        array $headers,
        string $format
    ): ScreenshotResponse {
        $cached = ($headers['x-cache-hit'] ?? 'false') === 'true';
        $contentType = $headers['content-type'] ?? 'image/' . $format;

        // Parse response time if available
        $responseTimeMs = null;
        if (isset($headers['x-response-time'])) {
            $responseTimeMs = (int) rtrim($headers['x-response-time'], 'ms');
        }

        return new ScreenshotResponse($body, $cached, $responseTimeMs, $contentType);
    }

    /**
     * Handle HTTP error responses
     *
     * @param int $httpCode HTTP status code
     * @param string $body Response body
     * @throws AuthenticationException If authentication fails
     * @throws ValidationException If validation fails
     * @throws ApiException For other API errors
     */
    private function handleHttpErrors(int $httpCode, string $body): void
    {
        // Try to parse JSON error message
        $data = json_decode($body, true);
        $message = 'Unknown error';

        if (json_last_error() === JSON_ERROR_NONE && isset($data['error'])) {
            $message = $data['error'];
            if (isset($data['message'])) {
                $message .= ': ' . $data['message'];
            }
        } else {
            $message = $body;
        }

        switch ($httpCode) {
            case 401:
            case 403:
                throw new AuthenticationException($message, $httpCode);

            case 400:
                throw new ValidationException($message, $httpCode);

            case 500:
            case 502:
            case 503:
                throw new ApiException(
                    sprintf('Server error: %s', $message),
                    $httpCode
                );

            default:
                throw new ApiException(
                    sprintf('API error (HTTP %d): %s', $httpCode, $message),
                    $httpCode
                );
        }
    }

    /**
     * Get the API endpoint
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        return self::API_ENDPOINT;
    }

    /**
     * Get the HTTP client
     *
     * @return HttpClientInterface
     */
    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }
}
