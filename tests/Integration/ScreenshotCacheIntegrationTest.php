<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use SnapCapture\SnapCaptureClient;
use SnapCapture\DTO\ScreenshotRequest;
use SnapCapture\Cache\ScreenshotCache;
use SnapCapture\Cache\MockCacheAdapter;

/**
 * Integration tests for ScreenshotCache with live SnapCapture API
 *
 * These tests make actual HTTP requests to the SnapCapture API and
 * verify that the caching system works correctly.
 *
 * Setup:
 * Create .env.snapcapture file in project root with:
 * SNAPCAPTURE_API_KEY=your-rapidapi-key-here
 *
 * Run with:
 * SNAPCAPTURE_API_KEY=your-key ./vendor/bin/phpunit --testsuite Integration
 */
class ScreenshotCacheIntegrationTest extends TestCase
{
    private ?SnapCaptureClient $client = null;
    private ScreenshotCache $cache;
    private MockCacheAdapter $adapter;
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Try to load API key from .env.snapcapture file
        $envFile = dirname(__DIR__, 2) . '/.env.snapcapture';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            if (isset($env['SNAPCAPTURE_API_KEY'])) {
                putenv('SNAPCAPTURE_API_KEY=' . $env['SNAPCAPTURE_API_KEY']);
            }
        }

        $apiKey = getenv('SNAPCAPTURE_API_KEY');

        if (empty($apiKey) || $apiKey === 'test-api-key') {
            $this->markTestSkipped(
                'Integration tests require SNAPCAPTURE_API_KEY. ' .
                'Create .env.snapcapture in project root with: ' .
                'SNAPCAPTURE_API_KEY=your-rapidapi-key-here'
            );
        }

        $this->client = new SnapCaptureClient($apiKey);

        // Use mock adapter for testing (in real WordPress, use WordPressCacheAdapter)
        $this->adapter = new MockCacheAdapter();
        $this->cache = new ScreenshotCache($this->adapter, 3600); // 1 hour cache

        // Create output directory for screenshots
        $this->outputDir = dirname(__DIR__, 2) . '/tests/screenshots/cache-integration';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    public function testCacheMissCallsApi(): void
    {
        $url = 'https://example.com';
        $options = ['viewport' => 'desktop', 'format' => 'jpeg'];

        // Verify cache is empty
        $this->assertFalse($this->cache->has($url, $options));

        // Make API request
        $request = ScreenshotRequest::desktop($url);
        $apiResponse = $this->client->captureScreenshot($request, true);

        $this->assertNotNull($apiResponse);
        $this->assertNotEmpty($apiResponse->getImageData());

        // Cache the response
        $this->cache->set($url, $apiResponse, $options);

        // Verify it's cached
        $this->assertTrue($this->cache->has($url, $options));

        // Save to file for verification
        $filepath = $this->outputDir . '/cache-miss-example-com.jpg';
        $apiResponse->saveToFile($filepath);
        $this->assertFileExists($filepath);

        echo "\n\nCache Miss Test - Screenshot saved to: {$filepath}\n";
        echo "API Response Time: {$apiResponse->getResponseTimeMs()}ms\n";
        echo "Cached: " . ($apiResponse->isCached() ? 'yes' : 'no') . "\n";
    }

    public function testCacheHitReturnsStoredData(): void
    {
        $url = 'https://example.org';
        $options = ['viewport' => 'desktop', 'format' => 'jpeg'];

        // First request - cache miss
        $request = ScreenshotRequest::desktop($url);
        $firstResponse = $this->client->captureScreenshot($request, true);

        // Cache it
        $this->cache->set($url, $firstResponse, $options);

        // Second request - cache hit
        $cachedResponse = $this->cache->get($url, $options);

        $this->assertNotNull($cachedResponse);
        $this->assertEquals($firstResponse->getImageData(), $cachedResponse->getImageData());
        $this->assertEquals($firstResponse->getContentType(), $cachedResponse->getContentType());
        $this->assertTrue($cachedResponse->isCached()); // Should be marked as cached

        // Save both to verify they're identical
        $firstFile = $this->outputDir . '/cache-test-first.jpg';
        $cachedFile = $this->outputDir . '/cache-test-cached.jpg';

        $firstResponse->saveToFile($firstFile);
        $cachedResponse->saveToFile($cachedFile);

        $this->assertFileExists($firstFile);
        $this->assertFileExists($cachedFile);

        // Verify files are identical
        $this->assertEquals(
            file_get_contents($firstFile),
            file_get_contents($cachedFile),
            'Cached screenshot should be identical to original'
        );

        echo "\n\nCache Hit Test - Screenshots identical\n";
        echo "First response size: " . strlen($firstResponse->getImageData()) . " bytes\n";
        echo "Cached response size: " . strlen($cachedResponse->getImageData()) . " bytes\n";
    }

    public function testCacheIsolatesUrlsWithDifferentOptions(): void
    {
        $url = 'https://example.net';

        // Request with desktop viewport
        $desktopRequest = ScreenshotRequest::desktop($url);
        $desktopResponse = $this->client->captureScreenshot($desktopRequest, true);

        // Cache desktop version
        $desktopOptions = ['viewport' => 'desktop'];
        $this->cache->set($url, $desktopResponse, $desktopOptions);

        // Request with mobile viewport
        $mobileRequest = ScreenshotRequest::mobile($url);
        $mobileResponse = $this->client->captureScreenshot($mobileRequest, true);

        // Cache mobile version
        $mobileOptions = ['viewport' => 'mobile'];
        $this->cache->set($url, $mobileResponse, $mobileOptions);

        // Verify both are cached separately
        $this->assertTrue($this->cache->has($url, $desktopOptions));
        $this->assertTrue($this->cache->has($url, $mobileOptions));

        // Retrieve both
        $cachedDesktop = $this->cache->get($url, $desktopOptions);
        $cachedMobile = $this->cache->get($url, $mobileOptions);

        $this->assertNotNull($cachedDesktop);
        $this->assertNotNull($cachedMobile);

        // They should have different image data (different viewport sizes)
        $this->assertNotEquals(
            $cachedDesktop->getImageData(),
            $cachedMobile->getImageData(),
            'Desktop and mobile screenshots should be different'
        );

        // Save for manual verification
        $cachedDesktop->saveToFile($this->outputDir . '/cache-desktop.jpg');
        $cachedMobile->saveToFile($this->outputDir . '/cache-mobile.jpg');

        echo "\n\nCache Isolation Test\n";
        echo "Desktop size: " . strlen($cachedDesktop->getImageData()) . " bytes\n";
        echo "Mobile size: " . strlen($cachedMobile->getImageData()) . " bytes\n";

        // Add delay to avoid rate limiting
        sleep(2);
    }

    public function testCacheExpiration(): void
    {
        $url = 'https://example.com/expiration-test';
        $options = ['viewport' => 'desktop'];

        // Request screenshot
        $request = ScreenshotRequest::desktop($url);
        $response = $this->client->captureScreenshot($request, true);

        // Cache with very short expiration (2 seconds)
        $this->cache->set($url, $response, $options, 2);
        $this->assertTrue($this->cache->has($url, $options));

        echo "\n\nCache Expiration Test\n";
        echo "Screenshot cached with 2 second expiration\n";

        // Wait for expiration
        echo "Waiting 3 seconds for expiration...\n";
        sleep(3);

        // Should be expired now
        $this->assertFalse(
            $this->cache->has($url, $options),
            'Cache entry should be expired after 3 seconds'
        );

        echo "Cache entry expired successfully\n";
    }

    public function testCacheWithMultipleUrls(): void
    {
        $urls = [
            'https://example.com',
            'https://example.org',
        ];

        $options = ['viewport' => 'desktop'];
        $responses = [];

        // Cache multiple URLs
        foreach ($urls as $index => $url) {
            $request = ScreenshotRequest::desktop($url);
            $response = $this->client->captureScreenshot($request, true);

            $this->cache->set($url, $response, $options);
            $responses[$url] = $response;

            echo "\nCached screenshot for: {$url}\n";

            // Add delay to avoid rate limiting
            if ($index < count($urls) - 1) {
                sleep(2);
            }
        }

        // Verify all are cached
        foreach ($urls as $url) {
            $this->assertTrue($this->cache->has($url, $options));

            $cached = $this->cache->get($url, $options);
            $this->assertNotNull($cached);
            $this->assertEquals($responses[$url]->getImageData(), $cached->getImageData());
        }

        echo "\n\nAll " . count($urls) . " URLs cached successfully\n";
        echo "Cache size: " . $this->adapter->size() . " entries\n";
    }

    public function testCacheClearRemovesAllEntries(): void
    {
        $url1 = 'https://example.com';
        $url2 = 'https://example.org';
        $options = ['viewport' => 'desktop'];

        // Cache two screenshots
        $request1 = ScreenshotRequest::desktop($url1);
        $response1 = $this->client->captureScreenshot($request1, true);
        $this->cache->set($url1, $response1, $options);

        sleep(2); // Avoid rate limiting

        $request2 = ScreenshotRequest::desktop($url2);
        $response2 = $this->client->captureScreenshot($request2, true);
        $this->cache->set($url2, $response2, $options);

        // Verify both are cached
        $this->assertTrue($this->cache->has($url1, $options));
        $this->assertTrue($this->cache->has($url2, $options));

        echo "\n\nCache Clear Test\n";
        echo "Cached 2 screenshots\n";

        // Clear cache
        $result = $this->cache->clear();
        $this->assertTrue($result);

        // Verify both are removed
        $this->assertFalse($this->cache->has($url1, $options));
        $this->assertFalse($this->cache->has($url2, $options));

        echo "Cache cleared successfully\n";
        echo "Cache size after clear: {$this->adapter->size()} entries\n";
    }

    public function testCachePreservesImageQuality(): void
    {
        $url = 'https://example.com';
        $options = ['viewport' => 'desktop'];

        // Get original screenshot
        $request = ScreenshotRequest::desktop($url);
        $originalResponse = $this->client->captureScreenshot($request, true);

        // Cache it
        $this->cache->set($url, $originalResponse, $options);

        // Retrieve from cache
        $cachedResponse = $this->cache->get($url, $options);

        // Save both
        $originalFile = $this->outputDir . '/quality-original.jpg';
        $cachedFile = $this->outputDir . '/quality-cached.jpg';

        $originalResponse->saveToFile($originalFile);
        $cachedResponse->saveToFile($cachedFile);

        // Verify image integrity
        $originalInfo = getimagesize($originalFile);
        $cachedInfo = getimagesize($cachedFile);

        $this->assertNotFalse($originalInfo);
        $this->assertNotFalse($cachedInfo);
        $this->assertEquals($originalInfo[0], $cachedInfo[0], 'Width should match');
        $this->assertEquals($originalInfo[1], $cachedInfo[1], 'Height should match');
        $this->assertEquals($originalInfo[2], $cachedInfo[2], 'Image type should match');

        // Verify binary data is identical
        $this->assertEquals(
            file_get_contents($originalFile),
            file_get_contents($cachedFile),
            'Image data should be identical'
        );

        echo "\n\nImage Quality Test\n";
        echo "Original: {$originalInfo[0]}x{$originalInfo[1]}\n";
        echo "Cached:   {$cachedInfo[0]}x{$cachedInfo[1]}\n";
        echo "Quality preserved: Yes\n";
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        echo "\n\nScreenshots location: {$this->outputDir}\n";
        echo "Screenshots are preserved for manual verification.\n";
    }
}
