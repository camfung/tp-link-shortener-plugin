<?php

declare(strict_types=1);

namespace TrafficPortal\Exception;

/**
 * Rate Limit Exception
 *
 * Thrown when API rate limits are exceeded (HTTP 429)
 * Specifically used for anonymous user IP limits
 *
 * @package TrafficPortal\Exception
 */
class RateLimitException extends ApiException
{
    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code HTTP status code (default: 429)
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        int $code = 429,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
