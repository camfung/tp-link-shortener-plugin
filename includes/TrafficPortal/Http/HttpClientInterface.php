<?php

declare(strict_types=1);

namespace TrafficPortal\Http;

use TrafficPortal\Exception\NetworkException;

interface HttpClientInterface
{
    /**
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $url Endpoint URL
     * @param array $options Supported options: headers (array), body (string), timeout (int)
     * @throws NetworkException On transport errors
     */
    public function request(string $method, string $url, array $options = []): HttpResponse;
}
