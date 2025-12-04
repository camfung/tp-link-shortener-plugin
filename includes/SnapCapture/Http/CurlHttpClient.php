<?php

declare(strict_types=1);

namespace SnapCapture\Http;

use SnapCapture\Exception\NetworkException;

/**
 * cURL HTTP Client
 *
 * Default HTTP client implementation using cURL
 *
 * @package SnapCapture\Http
 */
class CurlHttpClient implements HttpClientInterface
{
    private int $timeout;
    private int $connectTimeout;

    /**
     * Constructor
     *
     * @param int $timeout Request timeout in seconds (default: 30)
     * @param int $connectTimeout Connection timeout in seconds (default: 10)
     */
    public function __construct(int $timeout = 30, int $connectTimeout = 10)
    {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * Send an HTTP request
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Request URL
     * @param array $options Request options including:
     *   - headers: array of header strings
     *   - body: request body (for POST, PUT, etc.)
     *   - timeout: override default timeout
     * @return HttpResponse Response object
     * @throws NetworkException On network or cURL error
     */
    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        // Initialize cURL
        $ch = curl_init($url);
        if ($ch === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        // Set timeout
        $timeout = $options['timeout'] ?? $this->timeout;

        // Build cURL options
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ];

        // Add headers
        if (isset($options['headers']) && is_array($options['headers'])) {
            $curlOptions[CURLOPT_HTTPHEADER] = $options['headers'];
        }

        // Add body for POST/PUT/PATCH
        if (isset($options['body'])) {
            $curlOptions[CURLOPT_POSTFIELDS] = $options['body'];
        }

        curl_setopt_array($ch, $curlOptions);

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        // Handle cURL errors
        if ($curlErrno !== 0) {
            throw new NetworkException(
                sprintf('cURL error: %s', $curlError),
                $curlErrno
            );
        }

        // Handle empty response
        if ($response === false || $response === '') {
            throw new NetworkException('Empty response from server');
        }

        // Split headers and body
        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Parse headers
        $headers = $this->parseHeaders($headerString);

        return new HttpResponse($httpCode, $headers, $body);
    }

    /**
     * Parse HTTP headers
     *
     * @param string $headerString Raw header string
     * @return array Parsed headers (lowercase keys)
     */
    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        return $headers;
    }
}
