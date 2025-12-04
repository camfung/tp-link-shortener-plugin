<?php

declare(strict_types=1);

namespace ShortCode\Http;

use ShortCode\Exception\NetworkException;

class MockHttpClient implements HttpClientInterface
{
    private array $responses = [];
    private ?array $lastRequest = null;
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

        $this->lastRequest = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
        ];

        return array_shift($this->responses);
    }

    public function getLastRequest(): ?array
    {
        return $this->lastRequest;
    }
}
