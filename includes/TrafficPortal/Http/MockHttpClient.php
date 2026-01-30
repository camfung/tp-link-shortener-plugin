<?php

declare(strict_types=1);

namespace TrafficPortal\Http;

use TrafficPortal\Exception\NetworkException;

/**
 * Mock HTTP client for testing
 */
class MockHttpClient implements HttpClientInterface
{
    private array $responses = [];
    private array $requests = [];
    private ?\Exception $nextException = null;

    public function addResponse(HttpResponse $response): void
    {
        $this->responses[] = $response;
    }

    public function throwNext(\Exception $exception): void
    {
        $this->nextException = $exception;
    }

    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        if ($this->nextException !== null) {
            $exception = $this->nextException;
            $this->nextException = null;
            throw $exception;
        }

        if (empty($this->responses)) {
            throw new NetworkException('No mock responses queued');
        }

        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
        ];

        return array_shift($this->responses);
    }

    public function getLastRequest(): ?array
    {
        return empty($this->requests) ? null : end($this->requests);
    }

    public function getAllRequests(): array
    {
        return $this->requests;
    }

    public function getRequestCount(): int
    {
        return count($this->requests);
    }

    public function clearRequests(): void
    {
        $this->requests = [];
    }

    public function clearResponses(): void
    {
        $this->responses = [];
    }
}
