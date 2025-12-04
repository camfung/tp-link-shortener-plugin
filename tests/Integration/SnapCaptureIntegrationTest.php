<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use SnapCapture\SnapCaptureClient;
use SnapCapture\DTO\ScreenshotRequest;
use SnapCapture\DTO\ScreenshotResponse;
use SnapCapture\Exception\AuthenticationException;
use SnapCapture\Exception\NetworkException;

/**
 * Integration tests for SnapCaptureClient
 *
 * These tests make actual HTTP requests to the SnapCapture API.
 *
 * API Key Location:
 * Create a file at the project root: .env.snapcapture
 * Add the following line:
 * SNAPCAPTURE_API_KEY=your-rapidapi-key-here
 *
 * Example:
 * SNAPCAPTURE_API_KEY=abc123xyz ./vendor/bin/phpunit --testsuite Integration
 *
 * Or load from .env.snapcapture file
 */
class SnapCaptureIntegrationTest extends TestCase
{
    private ?SnapCaptureClient $client = null;
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

        // Create output directory for screenshots
        $this->outputDir = dirname(__DIR__, 2) . '/tests/screenshots/integration';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    public function testPing(): void
    {
        // Note: The SnapCapture API ping endpoint may require authentication
        // or not exist, so we skip this test for now
        $this->markTestSkipped('Ping endpoint may not be available or require different authentication');

        $response = $this->client->ping();

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('ok', $response['status']);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('uptime', $response);

        echo "\n\nAPI Ping Successful\n";
        echo "Status: {$response['status']}\n";
        echo "Timestamp: {$response['timestamp']}\n";
        echo "Uptime: {$response['uptime']}s\n";
    }

    public function testCaptureDesktopScreenshot(): void
    {
        $request = ScreenshotRequest::desktop('https://example.com');
        $response = $this->client->captureScreenshot($request);

        $this->assertInstanceOf(ScreenshotResponse::class, $response);
        $this->assertNotEmpty($response->getImageData());
        $this->assertEquals('image/jpeg', $response->getContentType());

        // Save to file for verification
        $filepath = $this->outputDir . '/example-com-desktop.jpg';
        $saved = $response->saveToFile($filepath);

        $this->assertTrue($saved);
        $this->assertFileExists($filepath);

        // Verify it's a valid JPEG
        $imageInfo = getimagesize($filepath);
        $this->assertNotFalse($imageInfo);
        $this->assertEquals(IMAGETYPE_JPEG, $imageInfo[2]);

        echo "\n\nDesktop Screenshot saved to: {$filepath}\n";
        echo "Image size: {$imageInfo[0]}x{$imageInfo[1]} pixels\n";
        echo "Cached: " . ($response->isCached() ? 'yes' : 'no') . "\n";
        if ($response->getResponseTimeMs() !== null) {
            echo "Response time: {$response->getResponseTimeMs()}ms\n";
        }
    }

    public function testCaptureMobileScreenshot(): void
    {
        $request = ScreenshotRequest::mobile('https://example.com');
        $response = $this->client->captureScreenshot($request);

        $this->assertInstanceOf(ScreenshotResponse::class, $response);
        $this->assertNotEmpty($response->getImageData());

        // Save to file for verification
        $filepath = $this->outputDir . '/example-com-mobile.jpg';
        $saved = $response->saveToFile($filepath);

        $this->assertTrue($saved);
        $this->assertFileExists($filepath);

        // Verify it's a valid JPEG
        $imageInfo = getimagesize($filepath);
        $this->assertNotFalse($imageInfo);

        echo "\n\nMobile screenshot saved to: {$filepath}\n";
        echo "Image size: {$imageInfo[0]}x{$imageInfo[1]} pixels\n";
    }

    public function testCapturePngScreenshot(): void
    {
        $request = new ScreenshotRequest(
            'https://example.com',
            'png',
            100,
            ['width' => 1280, 'height' => 720]
        );
        $response = $this->client->captureScreenshot($request);

        $this->assertInstanceOf(ScreenshotResponse::class, $response);
        $this->assertEquals('image/png', $response->getContentType());

        // Save to file for verification
        $filepath = $this->outputDir . '/example-com.png';
        $saved = $response->saveToFile($filepath);

        $this->assertTrue($saved);
        $this->assertFileExists($filepath);

        // Verify it's a valid PNG
        $imageInfo = getimagesize($filepath);
        $this->assertNotFalse($imageInfo);
        $this->assertEquals(IMAGETYPE_PNG, $imageInfo[2]);

        echo "\n\nPNG screenshot saved to: {$filepath}\n";
        echo "Image size: {$imageInfo[0]}x{$imageInfo[1]} pixels\n";
    }

    public function testCaptureFullPageScreenshot(): void
    {
        $request = ScreenshotRequest::fullPage('https://example.com');
        $response = $this->client->captureScreenshot($request);

        $this->assertInstanceOf(ScreenshotResponse::class, $response);
        $this->assertNotEmpty($response->getImageData());

        // Save to file for verification
        $filepath = $this->outputDir . '/example-com-fullpage.jpg';
        $saved = $response->saveToFile($filepath);

        $this->assertTrue($saved);
        $this->assertFileExists($filepath);

        echo "\n\nFull page screenshot saved to: {$filepath}\n";
    }

    public function testCaptureJsonResponse(): void
    {
        $request = ScreenshotRequest::desktop('https://example.com');
        $response = $this->client->captureScreenshot($request, true);

        $this->assertInstanceOf(ScreenshotResponse::class, $response);
        $this->assertNotEmpty($response->getImageData());

        // Test base64 and data URI methods
        $base64 = $response->getBase64();
        $this->assertNotEmpty($base64);

        $dataUri = $response->getDataUri();
        // When using JSON response, content-type may be application/json
        $this->assertStringStartsWith('data:', $dataUri);
        $this->assertStringContainsString('base64', $dataUri);

        // Save to file for verification
        $filepath = $this->outputDir . '/example-com-json.jpg';
        $saved = $response->saveToFile($filepath);

        $this->assertTrue($saved);
        $this->assertFileExists($filepath);

        echo "\n\nJSON response screenshot saved to: {$filepath}\n";
        echo "Base64 length: " . strlen($base64) . " characters\n";
        echo "Data URI length: " . strlen($dataUri) . " characters\n";
    }

    public function testInvalidApiKey(): void
    {
        $this->expectException(AuthenticationException::class);

        $client = new SnapCaptureClient('invalid-api-key-12345');
        $request = ScreenshotRequest::desktop('https://example.com');
        $client->captureScreenshot($request);
    }

    public function testInvalidUrl(): void
    {
        $request = new ScreenshotRequest('not-a-valid-url');

        $this->expectException(\Exception::class);
        $this->client->captureScreenshot($request);
    }

    public function testMultipleScreenshots(): void
    {
        $urls = [
            'https://example.com',
            'https://example.org',
        ];

        foreach ($urls as $index => $url) {
            $request = ScreenshotRequest::desktop($url);
            $response = $this->client->captureScreenshot($request);

            $this->assertInstanceOf(ScreenshotResponse::class, $response);
            $this->assertNotEmpty($response->getImageData());

            // Save to file
            $filename = 'screenshot-' . ($index + 1) . '.jpg';
            $filepath = $this->outputDir . '/' . $filename;
            $saved = $response->saveToFile($filepath);

            $this->assertTrue($saved);
            $this->assertFileExists($filepath);

            echo "\nScreenshot {$filename} saved for {$url}\n";

            // Add delay to avoid rate limiting
            sleep(2);
        }

        echo "\nAll screenshots saved to: {$this->outputDir}\n";
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Note: Screenshots are intentionally not deleted so they can be verified manually
        // Delete the screenshots directory manually when verification is complete
        echo "\n\nScreenshots location: {$this->outputDir}\n";
        echo "Screenshots are preserved for manual verification.\n";
    }
}
