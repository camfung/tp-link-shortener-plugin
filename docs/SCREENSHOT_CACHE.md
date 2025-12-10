# Screenshot Caching System

## Overview

The screenshot caching system provides a robust, testable solution for caching screenshots on the PHP server. It uses WordPress Transients API for storage and follows clean architecture principles with dependency injection.

## Architecture

### Components

1. **CacheAdapterInterface** - Defines the contract for cache implementations
2. **WordPressCacheAdapter** - WordPress Transients implementation
3. **MockCacheAdapter** - In-memory implementation for testing
4. **ScreenshotCache** - Main caching logic with URL-based cache keys

### Design Principles

- **Dependency Injection**: Cache adapter is injected, making the system testable
- **Interface Segregation**: Clean interface allows multiple backend implementations
- **Single Responsibility**: Each class has one clear purpose
- **Testability**: Mock adapter enables comprehensive unit testing

## Usage

### Basic Usage

```php
use SnapCapture\Cache\ScreenshotCache;
use SnapCapture\Cache\WordPressCacheAdapter;
use SnapCapture\DTO\ScreenshotResponse;

// Initialize cache with WordPress adapter
$adapter = new WordPressCacheAdapter('snapcap_');
$cache = new ScreenshotCache($adapter, 604800); // 7 day cache

// Check if screenshot is cached
if ($cache->has($url, $options)) {
    $screenshot = $cache->get($url, $options);
} else {
    // Fetch from API
    $screenshot = $client->captureScreenshot($request);

    // Cache it
    $cache->set($url, $screenshot, $options);
}
```

### Configuration

```php
// Custom cache expiration (in seconds)
$cache = new ScreenshotCache($adapter, 3600); // 1 hour

// Custom key prefix
$adapter = new WordPressCacheAdapter('my_prefix_');
```

### Cache Options

The cache key is generated from the URL and options array. Different options create separate cache entries:

```php
// Desktop screenshot
$desktopOptions = ['viewport' => 'desktop', 'format' => 'jpeg'];
$cache->set($url, $screenshot, $desktopOptions);

// Mobile screenshot (separate cache entry)
$mobileOptions = ['viewport' => 'mobile', 'format' => 'jpeg'];
$cache->set($url, $screenshot, $mobileOptions);
```

## Integration with WordPress

### In TP_API_Handler

The caching is automatically integrated in the `capture_screenshot` method:

```php
private function capture_screenshot(string $url): array {
    // Check cache first
    $options = ['viewport' => 'desktop', 'format' => 'jpeg'];
    $cachedScreenshot = $this->screenshot_cache->get($url, $options);

    if ($cachedScreenshot !== null) {
        // Return cached version
        return $cachedScreenshot;
    }

    // Fetch from API and cache
    $response = $this->snapcapture_client->captureScreenshot($request, true);
    $this->screenshot_cache->set($url, $response, $options);

    return $response;
}
```

### WordPress Transients

The `WordPressCacheAdapter` uses WordPress transients:

- Automatically handles expiration
- Stores in WordPress database
- Compatible with object caching plugins (Redis, Memcached)
- Can be cleared manually or via WordPress tools

## Testing

### Unit Tests

```bash
# Run all cache unit tests
./vendor/bin/phpunit --testsuite Unit tests/Unit/ScreenshotCacheTest.php
./vendor/bin/phpunit --testsuite Unit tests/Unit/MockCacheAdapterTest.php
```

Unit tests use `MockCacheAdapter` for fast, isolated testing without WordPress dependencies.

### Integration Tests

```bash
# Run integration tests with live API
./vendor/bin/phpunit tests/Integration/ScreenshotCacheIntegrationTest.php
```

Integration tests verify the caching works with:
- Live SnapCapture API
- Real screenshot data
- Cache expiration
- Multiple URLs and options

## Cache Management

### Clear Cache

```php
// Clear all cached screenshots
$cache->clear();
```

### Delete Specific Entry

```php
// Delete a specific cached screenshot
$cache->delete($url, $options);
```

### Check Cache Status

```php
// Check if a screenshot is cached
if ($cache->has($url, $options)) {
    // Screenshot is cached
}
```

## Performance Benefits

1. **Reduced API Calls**: Screenshots are cached for 7 days by default
2. **Faster Response**: Cached screenshots are served instantly
3. **Lower Costs**: Fewer API requests reduce usage costs
4. **Rate Limit Protection**: Cache prevents hitting API rate limits

## WordPress Transients Location

Cached screenshots are stored in the WordPress options table:

- Option name: `_transient_snapcap_{hash}`
- Timeout name: `_transient_timeout_snapcap_{hash}`
- Hash: MD5 of URL + options

## Monitoring

### Cache Hit Tracking

The API response includes a `server_cached` flag:

```json
{
  "screenshot_base64": "...",
  "cached": false,
  "server_cached": true
}
```

- `cached`: Whether the SnapCapture API served from cache
- `server_cached`: Whether our WordPress cache was used

### WordPress Admin

You can view/delete transients using:
- WordPress Transients Manager plugin
- Direct database queries
- WP-CLI: `wp transient list --search="snapcap_*"`

## Troubleshooting

### Cache Not Working

1. Check WordPress transients are enabled
2. Verify database permissions
3. Check disk space on server
4. Review PHP error logs

### Clear Stuck Cache

```php
// Via code
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_snapcap_%'");

// Via WP-CLI
wp transient delete --all
```

### Memory Issues

If caching large screenshots causes memory issues:

1. Reduce cache expiration time
2. Use Redis/Memcached instead of database
3. Implement image compression

## Future Enhancements

Possible improvements:

1. **Redis/Memcached Adapter**: For better performance at scale
2. **File-based Cache**: Store screenshots as files instead of database
3. **Cache Warming**: Pre-cache common URLs
4. **Smart Expiration**: Different TTLs based on URL patterns
5. **Cache Analytics**: Track hit rates and performance metrics

## API Reference

### CacheAdapterInterface

```php
interface CacheAdapterInterface {
    public function get(string $key);
    public function set(string $key, $value, int $expiration = 0): bool;
    public function delete(string $key): bool;
    public function has(string $key): bool;
    public function clear(): bool;
}
```

### ScreenshotCache

```php
class ScreenshotCache {
    public function __construct(
        CacheAdapterInterface $adapter,
        int $defaultExpiration = 86400,
        string $keyPrefix = 'screenshot_'
    );

    public function get(string $url, array $options = []): ?ScreenshotResponse;
    public function set(string $url, ScreenshotResponse $screenshot,
                       array $options = [], ?int $expiration = null): bool;
    public function has(string $url, array $options = []): bool;
    public function delete(string $url, array $options = []): bool;
    public function clear(): bool;
}
```

## Examples

### Custom Cache Backend

```php
// Create custom adapter (e.g., Redis)
class RedisCacheAdapter implements CacheAdapterInterface {
    private $redis;

    public function get(string $key) {
        return unserialize($this->redis->get($key));
    }

    public function set(string $key, $value, int $expiration = 0): bool {
        return $this->redis->setex($key, $expiration, serialize($value));
    }

    // ... implement other methods
}

// Use it
$adapter = new RedisCacheAdapter($redisConnection);
$cache = new ScreenshotCache($adapter);
```

### Testing with Mock Adapter

```php
use SnapCapture\Cache\MockCacheAdapter;

// Create test cache
$adapter = new MockCacheAdapter();
$cache = new ScreenshotCache($adapter, 3600);

// Test your code
$cache->set($url, $screenshot);
$this->assertTrue($cache->has($url));

// Verify cache state
$this->assertEquals(1, $adapter->size());
$this->assertContains('screenshot_...', $adapter->getKeys());
```
