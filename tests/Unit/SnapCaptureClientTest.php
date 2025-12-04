<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SnapCapture\SnapCaptureClient;
use SnapCapture\DTO\ScreenshotRequest;
use SnapCapture\DTO\ScreenshotResponse;
use SnapCapture\Exception\AuthenticationException;
use SnapCapture\Exception\ValidationException;
use SnapCapture\Exception\ApiException;
use SnapCapture\Http\MockHttpClient;
use SnapCapture\Http\HttpResponse;

/**
 * Unit tests for SnapCaptureClient using mock HTTP client
 */
class SnapCaptureClientTest extends TestCase
{
    private MockHttpClient $mockHttpClient;
    private SnapCaptureClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHttpClient = new MockHttpClient();
        $this->client = new SnapCaptureClient('test-api-key', $this->mockHttpClient);
    }

    public function testCaptureScreenshotBinary(): void
    {
        // Mock binary JPEG response
        $imageData = 'fake-jpeg-binary-data';
        $this->mockHttpClient->addResponse(
            new HttpResponse(200, [
                'content-type' => 'image/jpeg',
                'x-cache-hit' => 'false',
                'x-response-time' => '1234ms',
            ], $imageData)
        );

        $request = ScreenshotRequest::desktop('https://example.com');
        $response = $this->client->captureScreenshot($request);

        $this->assertInstanceOf(ScreenshotResponse::class, $response);
        $this->assertEquals($imageData, $response->getImageData());
        $this->assertEquals('image/jpeg', $response->getContentType());
        $this->assertFalse($response->isCached());
        $this->assertEquals(1234, $response->getResponseTimeMs());

        // Verify request was made correctly
        $lastRequest = $this->mockHttpClient->getLastRequest();
        $this->assertEquals('POST', $lastRequest['method']);
        $this->assertStringContainsString('/screenshot', $lastRequest['url']);
        $this->assertStringNotContainsString('json=true', $lastRequest['url']);
    }

    public function testCaptureScreenshotJson(): void
    {
        // Mock JSON response with base64 image
        $imageData = 'fake-jpeg-binary-data';
        $base64Data = base64_encode($imageData);
        $jsonResponse = json_encode([
            'screenshot_base64' => $base64Data,
            'cached' => true,
            'response_time_ms' => 567,
        ]);

        $this->mockHttpClient->addResponse(
            new HttpResponse(200, [
                'content-type' => 'application/json',
            ], $jsonResponse)
        );

        $request = ScreenshotRequest::desktop('https://example.com');
        $response = $this->client->captureScreenshot($request, true);

        $this->assertInstanceOf(ScreenshotResponse::class, $response);
        $this->assertEquals($imageData, $response->getImageData());
        $this->assertTrue($response->isCached());
        $this->assertEquals(567, $response->getResponseTimeMs());

        // Verify request URL includes json=true
        $lastRequest = $this->mockHttpClient->getLastRequest();
        $this->assertStringContainsString('json=true', $lastRequest['url']);
    }

    public function testCaptureScreenshotPng(): void
    {
        // Mock binary PNG response
        $imageData = 'fake-png-binary-data';
        $this->mockHttpClient->addResponse(
            new HttpResponse(200, [
                'content-type' => 'image/png',
                'x-cache-hit' => 'true',
            ], $imageData)
        );

        $request = new ScreenshotRequest(
            'https://example.com',
            'png',
            100,
            ['width' => 1280, 'height' => 720]
        );
        $response = $this->client->captureScreenshot($request);

        $this->assertEquals('image/png', $response->getContentType());
        $this->assertTrue($response->isCached());
    }

    public function testCaptureScreenshotMobile(): void
    {
        // Mock response
        $this->mockHttpClient->addResponse(
            new HttpResponse(200, [
                'content-type' => 'image/jpeg',
                'x-cache-hit' => 'false',
            ], 'mobile-screenshot-data')
        );

        $request = ScreenshotRequest::mobile('https://example.com');
        $response = $this->client->captureScreenshot($request);

        $this->assertInstanceOf(ScreenshotResponse::class, $response);

        // Verify request body
        $lastRequest = $this->mockHttpClient->getLastRequest();
        $body = json_decode($lastRequest['options']['body'], true);

        $this->assertTrue($body['mobile']);
        $this->assertEquals(375, $body['viewport']['width']);
        $this->assertEquals(667, $body['viewport']['height']);
    }

    public function testCaptureScreenshotFullPage(): void
    {
        // Mock response
        $this->mockHttpClient->addResponse(
            new HttpResponse(200, [
                'content-type' => 'image/jpeg',
                'x-cache-hit' => 'false',
            ], 'fullpage-screenshot-data')
        );

        $request = ScreenshotRequest::fullPage('https://example.com');
        $response = $this->client->captureScreenshot($request);

        $this->assertInstanceOf(ScreenshotResponse::class, $response);

        // Verify request body
        $lastRequest = $this->mockHttpClient->getLastRequest();
        $body = json_decode($lastRequest['options']['body'], true);

        $this->assertTrue($body['fullPage']);
    }

    public function testAuthenticationError(): void
    {
        // Mock 401 response
        $errorResponse = json_encode([
            'error' => 'Unauthorized',
            'message' => 'Invalid API key',
        ]);

        $this->mockHttpClient->addResponse(
            new HttpResponse(401, ['content-type' => 'application/json'], $errorResponse)
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $request = ScreenshotRequest::desktop('https://example.com');
        $this->client->captureScreenshot($request);
    }

    public function testValidationError(): void
    {
        // Mock 400 response
        $errorResponse = json_encode([
            'error' => 'Validation Error',
            'message' => 'Invalid URL format',
        ]);

        $this->mockHttpClient->addResponse(
            new HttpResponse(400, ['content-type' => 'application/json'], $errorResponse)
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid URL format');

        $request = ScreenshotRequest::desktop('invalid-url');
        $this->client->captureScreenshot($request);
    }

    public function testServerError(): void
    {
        // Mock 500 response
        $errorResponse = json_encode([
            'error' => 'Internal Server Error',
            'message' => 'Something went wrong',
        ]);

        $this->mockHttpClient->addResponse(
            new HttpResponse(500, ['content-type' => 'application/json'], $errorResponse)
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Server error');

        $request = ScreenshotRequest::desktop('https://example.com');
        $this->client->captureScreenshot($request);
    }

    public function testPingSuccess(): void
    {
        // Mock ping response
        $pingResponse = json_encode([
            'status' => 'ok',
            'timestamp' => '2025-12-03T12:00:00Z',
            'uptime' => 123456,
        ]);

        $this->mockHttpClient->addResponse(
            new HttpResponse(200, ['content-type' => 'application/json'], $pingResponse)
        );

        $response = $this->client->ping();

        $this->assertIsArray($response);
        $this->assertEquals('ok', $response['status']);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('uptime', $response);

        // Verify request
        $lastRequest = $this->mockHttpClient->getLastRequest();
        $this->assertEquals('GET', $lastRequest['method']);
        $this->assertStringContainsString('/ping', $lastRequest['url']);
    }

    public function testPingFailure(): void
    {
        // Mock failed ping with valid JSON
        $errorResponse = json_encode([
            'status' => 'error',
            'message' => 'Service unavailable',
        ]);

        $this->mockHttpClient->addResponse(
            new HttpResponse(503, ['content-type' => 'application/json'], $errorResponse)
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Ping failed with HTTP 503');

        $this->client->ping();
    }

    public function testRequestHeadersIncludeApiKey(): void
    {
        // Mock response
        $this->mockHttpClient->addResponse(
            new HttpResponse(200, [
                'content-type' => 'image/jpeg',
            ], 'screenshot-data')
        );

        $request = ScreenshotRequest::desktop('https://example.com');
        $this->client->captureScreenshot($request);

        // Verify headers
        $lastRequest = $this->mockHttpClient->getLastRequest();
        $headers = $lastRequest['options']['headers'];

        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertContains('X-RapidAPI-Key: test-api-key', $headers);
        $this->assertContains('X-RapidAPI-Host: snapcapture1.p.rapidapi.com', $headers);
    }

    public function testInvalidJsonResponse(): void
    {
        // Mock invalid JSON response
        $this->mockHttpClient->addResponse(
            new HttpResponse(200, [
                'content-type' => 'application/json',
            ], 'invalid-json{')
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $request = ScreenshotRequest::desktop('https://example.com');
        $this->client->captureScreenshot($request, true);
    }

    public function testMissingBase64InJsonResponse(): void
    {
        // Mock JSON response without screenshot_base64
        $jsonResponse = json_encode([
            'cached' => false,
        ]);

        $this->mockHttpClient->addResponse(
            new HttpResponse(200, [
                'content-type' => 'application/json',
            ], $jsonResponse)
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Missing screenshot_base64');

        $request = ScreenshotRequest::desktop('https://example.com');
        $this->client->captureScreenshot($request, true);
    }

    public function testGetApiEndpoint(): void
    {
        $endpoint = $this->client->getApiEndpoint();
        $this->assertEquals('https://snapcapture1.p.rapidapi.com', $endpoint);
    }

    public function testGetHttpClient(): void
    {
        $httpClient = $this->client->getHttpClient();
        $this->assertSame($this->mockHttpClient, $httpClient);
    }
}
