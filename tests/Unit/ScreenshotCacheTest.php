<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SnapCapture\Cache\ScreenshotCache;
use SnapCapture\Cache\MockCacheAdapter;
use SnapCapture\DTO\ScreenshotResponse;

/**
 * Unit tests for ScreenshotCache
 *
 * Tests the screenshot caching functionality using a mock cache adapter.
 * This ensures the cache works independently of WordPress.
 */
class ScreenshotCacheTest extends TestCase
{
    private MockCacheAdapter $adapter;
    private ScreenshotCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock cache adapter
        $this->adapter = new MockCacheAdapter();

        // Create screenshot cache with 1 hour expiration
        $this->cache = new ScreenshotCache($this->adapter, 3600);
    }

    public function testCacheStoresAndRetrievesScreenshot(): void
    {
        $url = 'https://example.com';
        $imageData = 'fake-image-data-12345';

        // Create a screenshot response
        $screenshot = new ScreenshotResponse(
            $imageData,
            false,
            250,
            'image/jpeg'
        );

        // Cache the screenshot
        $result = $this->cache->set($url, $screenshot);
        $this->assertTrue($result);

        // Retrieve the screenshot
        $cached = $this->cache->get($url);
        $this->assertInstanceOf(ScreenshotResponse::class, $cached);
        $this->assertEquals($imageData, $cached->getImageData());
        $this->assertEquals('image/jpeg', $cached->getContentType());
        $this->assertTrue($cached->isCached()); // Should be marked as cached
    }

    public function testCacheReturnsNullForNonExistentUrl(): void
    {
        $cached = $this->cache->get('https://nonexistent.com');
        $this->assertNull($cached);
    }

    public function testCacheHasMethodWorks(): void
    {
        $url = 'https://example.com';
        $screenshot = new ScreenshotResponse('data', false, null, 'image/png');

        // Should not exist initially
        $this->assertFalse($this->cache->has($url));

        // Cache the screenshot
        $this->cache->set($url, $screenshot);

        // Should exist now
        $this->assertTrue($this->cache->has($url));
    }

    public function testCacheDeleteRemovesScreenshot(): void
    {
        $url = 'https://example.com';
        $screenshot = new ScreenshotResponse('data', false, null, 'image/jpeg');

        // Cache the screenshot
        $this->cache->set($url, $screenshot);
        $this->assertTrue($this->cache->has($url));

        // Delete it
        $result = $this->cache->delete($url);
        $this->assertTrue($result);
        $this->assertFalse($this->cache->has($url));
    }

    public function testCacheClearRemovesAllScreenshots(): void
    {
        // Cache multiple screenshots
        $urls = [
            'https://example.com',
            'https://example.org',
            'https://example.net',
        ];

        $screenshot = new ScreenshotResponse('data', false, null, 'image/jpeg');

        foreach ($urls as $url) {
            $this->cache->set($url, $screenshot);
        }

        // Verify all are cached
        foreach ($urls as $url) {
            $this->assertTrue($this->cache->has($url));
        }

        // Clear cache
        $result = $this->cache->clear();
        $this->assertTrue($result);

        // Verify all are removed
        foreach ($urls as $url) {
            $this->assertFalse($this->cache->has($url));
        }
    }

    public function testCacheUsesOptionsInKey(): void
    {
        $url = 'https://example.com';
        $screenshot1 = new ScreenshotResponse('desktop-data', false, null, 'image/jpeg');
        $screenshot2 = new ScreenshotResponse('mobile-data', false, null, 'image/jpeg');

        // Cache with different options
        $desktopOptions = ['viewport' => 'desktop'];
        $mobileOptions = ['viewport' => 'mobile'];

        $this->cache->set($url, $screenshot1, $desktopOptions);
        $this->cache->set($url, $screenshot2, $mobileOptions);

        // Retrieve with different options should return different results
        $cachedDesktop = $this->cache->get($url, $desktopOptions);
        $cachedMobile = $this->cache->get($url, $mobileOptions);

        $this->assertEquals('desktop-data', $cachedDesktop->getImageData());
        $this->assertEquals('mobile-data', $cachedMobile->getImageData());
    }

    public function testCachePreservesAllScreenshotData(): void
    {
        $url = 'https://example.com';
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        // Create a screenshot with all properties
        $screenshot = new ScreenshotResponse(
            $imageData,
            true,
            500,
            'image/png'
        );

        // Cache it
        $this->cache->set($url, $screenshot);

        // Retrieve it
        $cached = $this->cache->get($url);

        // Verify all properties are preserved
        $this->assertEquals($imageData, $cached->getImageData());
        $this->assertTrue($cached->isCached());
        $this->assertEquals('image/png', $cached->getContentType());
        // Response time might be preserved in serialized data
    }

    public function testCacheCustomExpiration(): void
    {
        $url = 'https://example.com';
        $screenshot = new ScreenshotResponse('data', false, null, 'image/jpeg');

        // Cache with very short expiration (1 second)
        $this->cache->set($url, $screenshot, [], 1);

        // Should exist immediately
        $this->assertTrue($this->cache->has($url));

        // Wait for expiration
        sleep(2);

        // Should be expired now (mock adapter handles expiration)
        $this->assertFalse($this->cache->has($url));
    }

    public function testCacheHandlesEmptyImageData(): void
    {
        $url = 'https://example.com';
        $screenshot = new ScreenshotResponse('', false, null, 'image/jpeg');

        $this->cache->set($url, $screenshot);
        $cached = $this->cache->get($url);

        $this->assertInstanceOf(ScreenshotResponse::class, $cached);
        $this->assertEquals('', $cached->getImageData());
    }

    public function testCacheGetterMethods(): void
    {
        $this->assertInstanceOf(MockCacheAdapter::class, $this->cache->getAdapter());
        $this->assertEquals(3600, $this->cache->getDefaultExpiration());
    }

    public function testCacheKeyNormalization(): void
    {
        $screenshot = new ScreenshotResponse('data', false, null, 'image/jpeg');

        // Different URL formats that should normalize to the same key
        $url1 = 'https://Example.COM';
        $url2 = 'https://example.com';

        $this->cache->set($url1, $screenshot);

        // Should retrieve with normalized URL
        $cached = $this->cache->get($url2);
        $this->assertInstanceOf(ScreenshotResponse::class, $cached);
    }

    public function testCacheOptionsOrderDoesNotMatter(): void
    {
        $url = 'https://example.com';
        $screenshot = new ScreenshotResponse('data', false, null, 'image/jpeg');

        // Cache with options in one order
        $options1 = ['format' => 'jpeg', 'viewport' => 'desktop'];
        $this->cache->set($url, $screenshot, $options1);

        // Retrieve with options in different order
        $options2 = ['viewport' => 'desktop', 'format' => 'jpeg'];
        $cached = $this->cache->get($url, $options2);

        $this->assertInstanceOf(ScreenshotResponse::class, $cached);
        $this->assertEquals('data', $cached->getImageData());
    }
}
