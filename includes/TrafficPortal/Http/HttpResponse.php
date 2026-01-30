<?php

declare(strict_types=1);

namespace TrafficPortal\Http;

/**
 * HTTP Response wrapper
 */
class HttpResponse
{
    private int $statusCode;
    private array $headers;
    private string $body;

    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getJsonBody(): ?array
    {
        $data = json_decode($this->body, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
