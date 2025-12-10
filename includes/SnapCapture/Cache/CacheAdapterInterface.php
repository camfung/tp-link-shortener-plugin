<?php

declare(strict_types=1);

namespace SnapCapture\Cache;

/**
 * Cache Adapter Interface
 *
 * Provides an abstraction layer for caching operations.
 * This interface allows the screenshot cache to work with different
 * cache backends (WordPress Transients, Redis, Memcached, etc.)
 * and enables easy mocking for unit tests.
 *
 * @package SnapCapture\Cache
 */
interface CacheAdapterInterface
{
    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @return mixed|null The cached value or null if not found/expired
     */
    public function get(string $key);

    /**
     * Set a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $expiration Time until expiration in seconds (0 = no expiration)
     * @return bool True on success, false on failure
     */
    public function set(string $key, $value, int $expiration = 0): bool;

    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool;

    /**
     * Check if a key exists in cache
     *
     * @param string $key Cache key
     * @return bool True if exists and not expired, false otherwise
     */
    public function has(string $key): bool;

    /**
     * Clear all cached values
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool;
}
