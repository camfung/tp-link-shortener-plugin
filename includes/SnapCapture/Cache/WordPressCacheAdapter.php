<?php

declare(strict_types=1);

namespace SnapCapture\Cache;

/**
 * WordPress Cache Adapter
 *
 * Implements caching using WordPress Transients API.
 * WordPress transients provide a simple and standardized way of storing
 * cached data in the database with automatic expiration.
 *
 * @package SnapCapture\Cache
 */
class WordPressCacheAdapter implements CacheAdapterInterface
{
    private string $prefix;

    /**
     * Constructor
     *
     * @param string $prefix Prefix for all cache keys (default: 'snapcap_')
     */
    public function __construct(string $prefix = 'snapcap_')
    {
        $this->prefix = $prefix;
    }

    /**
     * Get a value from cache using WordPress Transients
     *
     * @param string $key Cache key
     * @return mixed|null The cached value or null if not found/expired
     */
    public function get(string $key)
    {
        $value = get_transient($this->prefix . $key);

        // WordPress returns false for non-existent or expired transients
        return $value !== false ? $value : null;
    }

    /**
     * Set a value in cache using WordPress Transients
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $expiration Time until expiration in seconds (0 = no expiration)
     * @return bool True on success, false on failure
     */
    public function set(string $key, $value, int $expiration = 0): bool
    {
        // WordPress transients use 0 for no expiration
        return set_transient($this->prefix . $key, $value, $expiration);
    }

    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool
    {
        return delete_transient($this->prefix . $key);
    }

    /**
     * Check if a key exists in cache
     *
     * @param string $key Cache key
     * @return bool True if exists and not expired, false otherwise
     */
    public function has(string $key): bool
    {
        return get_transient($this->prefix . $key) !== false;
    }

    /**
     * Clear all cached values with this prefix
     *
     * Note: This queries the database to find all transients with the prefix.
     * For better performance, consider using WordPress Object Cache with a backend
     * like Redis or Memcached.
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool
    {
        global $wpdb;

        $prefix_like = $wpdb->esc_like('_transient_' . $this->prefix) . '%';
        $timeout_like = $wpdb->esc_like('_transient_timeout_' . $this->prefix) . '%';

        // Delete all transients and their timeout entries
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $prefix_like,
                $timeout_like
            )
        );

        return $deleted !== false;
    }

    /**
     * Get the cache key prefix
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
