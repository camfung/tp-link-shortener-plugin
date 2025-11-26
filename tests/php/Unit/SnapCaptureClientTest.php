<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SnapCapture\SnapCaptureClient;
use SnapCapture\DTO\ScreenshotRequest;
use SnapCapture\DTO\ScreenshotResponse;
use SnapCapture\Exception\ApiException;
use SnapCapture\Exception\AuthenticationException;
use SnapCapture\Exception\NetworkException;
use SnapCapture\Exception\ValidationException;

/**
 * Unit tests for SnapCaptureClient
 *
 * These tests use function mocking to test the client logic without making
 * actual HTTP requests.
 */
class SnapCaptureClientTest extends TestCase
{
    private SnapCaptureClient $client;
    private string $testApiKey = 'test-api-key-12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new SnapCaptureClient($this->testApiKey);
    }

    public function testConstructorSetsProperties(): void
    {
        $client = new SnapCaptureClient('my-key', 60);

        $this->assertEquals('https://snapcapture1.p.rapidapi.com', $client->getApiEndpoint());
        $this->assertEquals(60, $client->getTimeout());
    }

    public function testGetApiEndpoint(): void
    {
        $this->assertEquals('https://snapcapture1.p.rapidapi.com', $this->client->getApiEndpoint());
    }

    public function testGetTimeout(): void
    {
        $this->assertEquals(30, $this->client->getTimeout());
    }

    public function testScreenshotRequestDesktopFactory(): void
    {
        $request = ScreenshotRequest::desktop('https://example.com');

        $this->assertEquals('https://example.com', $request->getUrl());
        $this->assertEquals('jpeg', $request->getFormat());
        $this->assertEquals(80, $request->getQuality());
        $this->assertEquals(['width' => 1920, 'height' => 1080], $request->getViewport());
        $this->assertFalse($request->isFullPage());
        $this->assertFalse($request->isMobile());
    }

    public function testScreenshotRequestMobileFactory(): void
    {
        $request = ScreenshotRequest::mobile('https://example.com');

        $this->assertEquals('https://example.com', $request->getUrl());
        $this->assertEquals('jpeg', $request->getFormat());
        $this->assertEquals(80, $request->getQuality());
        $this->assertEquals(['width' => 375, 'height' => 667], $request->getViewport());
        $this->assertFalse($request->isFullPage());
        $this->assertTrue($request->isMobile());
    }

    public function testScreenshotRequestFullPageFactory(): void
    {
        $request = ScreenshotRequest::fullPage('https://example.com');

        $this->assertEquals('https://example.com', $request->getUrl());
        $this->assertTrue($request->isFullPage());
    }

    public function testScreenshotRequestToArray(): void
    {
        $request = new ScreenshotRequest(
            'https://example.com',
            'png',
            90,
            ['width' => 800, 'height' => 600],
            true,
            false
        );

        $expected = [
            'url' => 'https://example.com',
            'format' => 'png',
            'quality' => 90,
            'viewport' => ['width' => 800, 'height' => 600],
            'fullPage' => true,
            'mobile' => false,
        ];

        $this->assertEquals($expected, $request->toArray());
    }

    public function testScreenshotResponseGetters(): void
    {
        $imageData = 'fake-binary-data';
        $response = new ScreenshotResponse($imageData, true, 123, 'image/jpeg');

        $this->assertEquals($imageData, $response->getImageData());
        $this->assertTrue($response->isCached());
        $this->assertEquals(123, $response->getResponseTimeMs());
        $this->assertEquals('image/jpeg', $response->getContentType());
    }

    public function testScreenshotResponseGetBase64(): void
    {
        $imageData = 'test-data';
        $response = new ScreenshotResponse($imageData);

        $this->assertEquals(base64_encode($imageData), $response->getBase64());
    }

    public function testScreenshotResponseGetDataUri(): void
    {
        $imageData = 'test-data';
        $response = new ScreenshotResponse($imageData, false, null, 'image/png');

        $expected = 'data:image/png;base64,' . base64_encode($imageData);
        $this->assertEquals($expected, $response->getDataUri());
    }

    public function testScreenshotResponseSaveToFile(): void
    {
        $imageData = 'test-image-data';
        $response = new ScreenshotResponse($imageData);
        $tempFile = sys_get_temp_dir() . '/test-screenshot-' . uniqid() . '.jpg';

        try {
            $result = $response->saveToFile($tempFile);

            $this->assertTrue($result);
            $this->assertFileExists($tempFile);
            $this->assertEquals($imageData, file_get_contents($tempFile));
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testScreenshotResponseSaveToFileFailure(): void
    {
        $imageData = 'test-image-data';
        $response = new ScreenshotResponse($imageData);
        $invalidPath = '/invalid/path/that/does/not/exist/screenshot.jpg';

        $result = @$response->saveToFile($invalidPath);

        $this->assertFalse($result);
    }
}
