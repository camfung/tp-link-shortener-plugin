<?php

declare(strict_types=1);

namespace TrafficPortal\Http;

use TrafficPortal\Exception\NetworkException;

/**
 * cURL-based HTTP client implementation
 */
class CurlHttpClient implements HttpClientInterface
{
    private int $defaultTimeout;

    public function __construct(int $defaultTimeout = 30)
    {
        $this->defaultTimeout = $defaultTimeout;
    }

    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? null;
        $timeout = $options['timeout'] ?? $this->defaultTimeout;

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }
                return $len;
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new NetworkException(
                sprintf('cURL error: %s', $curlError),
                $curlErrno
            );
        }

        if ($response === false) {
            throw new NetworkException('Empty response from API');
        }

        return new HttpResponse($httpCode, $responseHeaders, $response);
    }
}
