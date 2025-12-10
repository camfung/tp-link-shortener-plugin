<?php

declare(strict_types=1);

namespace SnapCapture\Cache;

/**
 * Mock Cache Adapter
 *
 * In-memory cache adapter for testing purposes.
 * Stores cache entries in an array with expiration tracking.
 *
 * @package SnapCapture\Cache
 */
class MockCacheAdapter implements CacheAdapterInterface
{
    private array $cache = [];
    private array $expirations = [];

    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @return mixed|null The cached value or null if not found/expired
     */
    public function get(string $key)
    {
        // Check if expired
        if ($this->isExpired($key)) {
            $this->delete($key);
            return null;
        }

        return $this->cache[$key] ?? null;
    }

    /**
     * Set a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $expiration Time until expiration in seconds (0 = no expiration)
     * @return bool True on success, false on failure
     */
    public function set(string $key, $value, int $expiration = 0): bool
    {
        $this->cache[$key] = $value;

        if ($expiration > 0) {
            $this->expirations[$key] = time() + $expiration;
        } else {
            unset($this->expirations[$key]);
        }

        return true;
    }

    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key], $this->expirations[$key]);
        return true;
    }

    /**
     * Check if a key exists in cache
     *
     * @param string $key Cache key
     * @return bool True if exists and not expired, false otherwise
     */
    public function has(string $key): bool
    {
        if ($this->isExpired($key)) {
            $this->delete($key);
            return false;
        }

        return isset($this->cache[$key]);
    }

    /**
     * Clear all cached values
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool
    {
        $this->cache = [];
        $this->expirations = [];
        return true;
    }

    /**
     * Check if a key is expired
     *
     * @param string $key Cache key
     * @return bool True if expired, false otherwise
     */
    private function isExpired(string $key): bool
    {
        if (!isset($this->expirations[$key])) {
            return false;
        }

        return time() > $this->expirations[$key];
    }

    /**
     * Get all cached keys (for testing)
     *
     * @return array
     */
    public function getKeys(): array
    {
        return array_keys($this->cache);
    }

    /**
     * Get cache size (for testing)
     *
     * @return int
     */
    public function size(): int
    {
        return count($this->cache);
    }
}
