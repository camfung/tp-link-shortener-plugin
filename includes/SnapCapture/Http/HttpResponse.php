<?php

declare(strict_types=1);

namespace SnapCapture\Http;

/**
 * HTTP Response
 *
 * Represents an HTTP response
 *
 * @package SnapCapture\Http
 */
class HttpResponse
{
    private int $statusCode;
    private array $headers;
    private string $body;

    /**
     * Constructor
     *
     * @param int $statusCode HTTP status code
     * @param array $headers Response headers (lowercase keys)
     * @param string $body Response body
     */
    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get response headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value
     *
     * @param string $name Header name (case-insensitive)
     * @return string|null Header value or null if not found
     */
    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }

    /**
     * Get response body
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Check if response is successful (2xx status code)
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
