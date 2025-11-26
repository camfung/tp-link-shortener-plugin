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
 * Set the SNAPCAPTURE_API_KEY environment variable to run these tests.
 *
 * Example:
 * SNAPCAPTURE_API_KEY=your-key-here ./vendor/bin/phpunit --testsuite Integration
 */
class SnapCaptureIntegrationTest extends TestCase
{
    private ?SnapCaptureClient $client = null;
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = getenv('SNAPCAPTURE_API_KEY');

        if (empty($apiKey) || $apiKey === 'test-api-key') {
            $this->markTestSkipped(
                'Integration tests require SNAPCAPTURE_API_KEY environment variable. ' .
                'Set it to your RapidAPI key to run these tests.'
            );
        }

        $this->client = new SnapCaptureClient($apiKey);

        // Create output directory for screenshots
        $this->outputDir = dirname(__DIR__, 3) . '/tests/screenshots';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    public function testPing(): void
    {
        $response = $this->client->ping();

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('ok', $response['status']);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('uptime', $response);
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

        echo "\n\nScreenshot saved to: {$filepath}\n";
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

        echo "\n\nMobile screenshot saved to: {$filepath}\n";
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
        $this->assertStringStartsWith('data:image/', $dataUri);
        $this->assertStringContainsString('base64', $dataUri);
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

    protected function tearDown(): void
    {
        parent::tearDown();

        // Note: Screenshots are intentionally not deleted so they can be verified manually
        // Delete the screenshots directory manually when verification is complete
    }
}
