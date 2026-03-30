<?php

declare(strict_types=1);

namespace WooWallet\Http;

/**
 * HTTP Client Interface
 *
 * @package WooWallet\Http
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
