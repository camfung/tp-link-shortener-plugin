<?php

declare(strict_types=1);

namespace SnapCapture\Cache;

use SnapCapture\DTO\ScreenshotResponse;

/**
 * Screenshot Cache
 *
 * Caches screenshot responses to reduce API calls and improve performance.
 * Uses a URL-based cache key and stores serialized screenshot data.
 *
 * @package SnapCapture\Cache
 */
class ScreenshotCache
{
    private CacheAdapterInterface $adapter;
    private int $defaultExpiration;
    private string $keyPrefix;

    /**
     * Constructor
     *
     * @param CacheAdapterInterface $adapter Cache adapter implementation
     * @param int $defaultExpiration Default cache expiration in seconds (default: 24 hours)
     * @param string $keyPrefix Prefix for cache keys (default: 'screenshot_')
     */
    public function __construct(
        CacheAdapterInterface $adapter,
        int $defaultExpiration = 86400,
        string $keyPrefix = 'screenshot_'
    ) {
        $this->adapter = $adapter;
        $this->defaultExpiration = $defaultExpiration;
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * Get a cached screenshot
     *
     * @param string $url The URL that was screenshotted
     * @param array $options Screenshot options (viewport, format, etc.)
     * @return ScreenshotResponse|null The cached screenshot or null if not found
     */
    public function get(string $url, array $options = []): ?ScreenshotResponse
    {
        $cacheKey = $this->generateCacheKey($url, $options);
        $cachedData = $this->adapter->get($cacheKey);

        if ($cachedData === null) {
            return null;
        }

        // Reconstruct ScreenshotResponse from cached data
        return $this->unserializeScreenshot($cachedData);
    }

    /**
     * Cache a screenshot
     *
     * @param string $url The URL that was screenshotted
     * @param ScreenshotResponse $screenshot The screenshot to cache
     * @param array $options Screenshot options (viewport, format, etc.)
     * @param int|null $expiration Cache expiration in seconds (null = use default)
     * @return bool True on success, false on failure
     */
    public function set(
        string $url,
        ScreenshotResponse $screenshot,
        array $options = [],
        ?int $expiration = null
    ): bool {
        $cacheKey = $this->generateCacheKey($url, $options);
        $cacheData = $this->serializeScreenshot($screenshot);
        $expiration = $expiration ?? $this->defaultExpiration;

        return $this->adapter->set($cacheKey, $cacheData, $expiration);
    }

    /**
     * Check if a screenshot is cached
     *
     * @param string $url The URL to check
     * @param array $options Screenshot options
     * @return bool True if cached, false otherwise
     */
    public function has(string $url, array $options = []): bool
    {
        $cacheKey = $this->generateCacheKey($url, $options);
        return $this->adapter->has($cacheKey);
    }

    /**
     * Delete a cached screenshot
     *
     * @param string $url The URL to delete
     * @param array $options Screenshot options
     * @return bool True on success, false on failure
     */
    public function delete(string $url, array $options = []): bool
    {
        $cacheKey = $this->generateCacheKey($url, $options);
        return $this->adapter->delete($cacheKey);
    }

    /**
     * Clear all cached screenshots
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool
    {
        return $this->adapter->clear();
    }

    /**
     * Generate a cache key for a URL and options
     *
     * The cache key is generated using MD5 hash of the URL and serialized options.
     * This ensures consistent keys for the same URL/options combination while
     * keeping keys short and filesystem-safe.
     *
     * @param string $url The URL
     * @param array $options Screenshot options
     * @return string The cache key
     */
    private function generateCacheKey(string $url, array $options = []): string
    {
        // Normalize URL
        $normalizedUrl = strtolower(trim($url));

        // Sort options to ensure consistent key generation
        ksort($options);

        // Create a unique hash
        $hash = md5($normalizedUrl . serialize($options));

        return $this->keyPrefix . $hash;
    }

    /**
     * Serialize a screenshot for caching
     *
     * @param ScreenshotResponse $screenshot The screenshot to serialize
     * @return array Serialized data
     */
    private function serializeScreenshot(ScreenshotResponse $screenshot): array
    {
        return [
            'image_data' => $screenshot->getImageData(),
            'cached' => $screenshot->isCached(),
            'response_time_ms' => $screenshot->getResponseTimeMs(),
            'content_type' => $screenshot->getContentType(),
            'cached_at' => time(),
        ];
    }

    /**
     * Unserialize a cached screenshot
     *
     * @param array $data Cached data
     * @return ScreenshotResponse|null The screenshot or null if invalid
     */
    private function unserializeScreenshot(array $data): ?ScreenshotResponse
    {
        if (!isset($data['image_data']) || !isset($data['content_type'])) {
            return null;
        }

        return new ScreenshotResponse(
            $data['image_data'],
            true, // Mark as cached since we're retrieving from cache
            $data['response_time_ms'] ?? null,
            $data['content_type']
        );
    }

    /**
     * Get the cache adapter
     *
     * @return CacheAdapterInterface
     */
    public function getAdapter(): CacheAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Get the default expiration time
     *
     * @return int
     */
    public function getDefaultExpiration(): int
    {
        return $this->defaultExpiration;
    }
}
