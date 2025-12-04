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
        $payload = $request->toArray();

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'headers' => [
                    'Content-Type: application/json',
                ],
                'body' => json_encode($payload),
                'timeout' => $this->timeout,
            ]);
        } catch (NetworkException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new NetworkException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->parseResponse($response->getStatusCode(), $response->getBody());
    }

    private function parseResponse(int $statusCode, string $body): GenerateShortCodeResponse
    {
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(sprintf('Invalid JSON response: %s', json_last_error_msg()), $statusCode);
        }

        if ($statusCode >= 400) {
            $message = $data['message'] ?? 'API error';

            if ($statusCode === 400) {
                throw new ValidationException($message, $statusCode);
            }

            throw new ApiException($message, $statusCode);
        }

        if (!($data['success'] ?? false)) {
            $message = $data['message'] ?? 'API indicated failure';
            throw new ApiException($message, $statusCode);
        }

        if (!isset($data['source']['short_code'], $data['source']['original_code'], $data['source']['was_modified'], $data['source']['url'])) {
            throw new ApiException('Response missing expected source fields', $statusCode);
        }

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
}
