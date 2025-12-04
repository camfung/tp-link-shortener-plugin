<?php

declare(strict_types=1);

namespace SnapCapture\Http;

/**
 * Mock HTTP Client
 *
 * Mock implementation of HTTP client for testing
 *
 * @package SnapCapture\Http
 */
class MockHttpClient implements HttpClientInterface
{
    private array $responses = [];
    private int $responseIndex = 0;
    private array $requests = [];

    /**
     * Add a mocked response
     *
     * @param HttpResponse $response Response to return
     * @return void
     */
    public function addResponse(HttpResponse $response): void
    {
        $this->responses[] = $response;
    }

    /**
     * Add multiple mocked responses
     *
     * @param HttpResponse[] $responses Responses to return in sequence
     * @return void
     */
    public function addResponses(array $responses): void
    {
        foreach ($responses as $response) {
            $this->addResponse($response);
        }
    }

    /**
     * Send an HTTP request (returns mocked response)
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array $options Request options
     * @return HttpResponse Mocked response
     * @throws \RuntimeException If no more mocked responses available
     */
    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        // Record the request
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
        ];

        // Return the next mocked response
        if ($this->responseIndex >= count($this->responses)) {
            throw new \RuntimeException('No more mocked responses available');
        }

        $response = $this->responses[$this->responseIndex];
        $this->responseIndex++;

        return $response;
    }

    /**
     * Get all recorded requests
     *
     * @return array Array of recorded requests
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Get the last recorded request
     *
     * @return array|null Last request or null if no requests
     */
    public function getLastRequest(): ?array
    {
        return end($this->requests) ?: null;
    }

    /**
     * Reset the mock client state
     *
     * @return void
     */
    public function reset(): void
    {
        $this->responses = [];
        $this->responseIndex = 0;
        $this->requests = [];
    }

    /**
     * Get count of requests made
     *
     * @return int
     */
    public function getRequestCount(): int
    {
        return count($this->requests);
    }
}
