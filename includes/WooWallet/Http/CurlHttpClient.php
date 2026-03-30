<?php

declare(strict_types=1);

namespace WooWallet\Http;

use WooWallet\Exception\NetworkException;

/**
 * cURL HTTP Client
 *
 * @package WooWallet\Http
 */
class CurlHttpClient implements HttpClientInterface
{
    private int $timeout;
    private int $connectTimeout;

    public function __construct(int $timeout = 30, int $connectTimeout = 10)
    {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        $timeout = $options['timeout'] ?? $this->timeout;

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ];

        if (isset($options['headers']) && is_array($options['headers'])) {
            $curlOptions[CURLOPT_HTTPHEADER] = $options['headers'];
        }

        if (isset($options['body'])) {
            $curlOptions[CURLOPT_POSTFIELDS] = $options['body'];
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new NetworkException(
                sprintf('cURL error: %s', $curlError),
                $curlErrno
            );
        }

        if ($response === false || $response === '') {
            throw new NetworkException('Empty response from server');
        }

        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $headers = $this->parseHeaders($headerString);

        return new HttpResponse($httpCode, $headers, $body);
    }

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
