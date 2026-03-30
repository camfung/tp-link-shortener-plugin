<?php

declare(strict_types=1);

namespace WooWallet\Http;

/**
 * Mock HTTP Client for testing
 *
 * @package WooWallet\Http
 */
class MockHttpClient implements HttpClientInterface
{
    private array $responses = [];
    private int $responseIndex = 0;
    private array $requests = [];

    public function addResponse(HttpResponse $response): void
    {
        $this->responses[] = $response;
    }

    public function addResponses(array $responses): void
    {
        foreach ($responses as $response) {
            $this->addResponse($response);
        }
    }

    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
        ];

        if ($this->responseIndex >= count($this->responses)) {
            throw new \RuntimeException('No more mocked responses available');
        }

        $response = $this->responses[$this->responseIndex];
        $this->responseIndex++;

        return $response;
    }

    public function getRequests(): array
    {
        return $this->requests;
    }

    public function getLastRequest(): ?array
    {
        return end($this->requests) ?: null;
    }

    public function reset(): void
    {
        $this->responses = [];
        $this->responseIndex = 0;
        $this->requests = [];
    }

    public function getRequestCount(): int
    {
        return count($this->requests);
    }
}
