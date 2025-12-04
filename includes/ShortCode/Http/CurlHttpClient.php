<?php

declare(strict_types=1);

namespace ShortCode\Http;

use ShortCode\Exception\NetworkException;

class CurlHttpClient implements HttpClientInterface
{
    private int $timeout;

    public function __construct(int $timeout = 30)
    {
        $this->timeout = $timeout;
    }

    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? null;
        $timeout = $options['timeout'] ?? $this->timeout;

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Capture headers in a temporary variable
        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$responseHeaders) {
            $len = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $name = strtolower(trim($parts[0]));
                $value = trim($parts[1]);
                $responseHeaders[$name] = $value;
            }
            return $len;
        });

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new NetworkException(sprintf('cURL error: %s', $curlError), $curlErrno);
        }

        return new HttpResponse($statusCode, $responseHeaders, $responseBody === false ? '' : $responseBody);
    }
}
