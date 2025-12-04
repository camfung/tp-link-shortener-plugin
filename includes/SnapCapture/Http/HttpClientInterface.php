<?php

declare(strict_types=1);

namespace SnapCapture\Http;

/**
 * HTTP Client Interface
 *
 * Interface for HTTP clients used by SnapCaptureClient
 *
 * @package SnapCapture\Http
 */
interface HttpClientInterface
{
    /**
     * Send an HTTP request
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Request URL
     * @param array $options Request options including headers, body, timeout, etc.
     * @return HttpResponse Response object
     * @throws \Exception On network or HTTP error
     */
    public function request(string $method, string $url, array $options = []): HttpResponse;
}
