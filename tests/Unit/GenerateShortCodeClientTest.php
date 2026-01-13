<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShortCode\GenerateShortCodeClient;
use ShortCode\DTO\GenerateShortCodeRequest;
use ShortCode\DTO\GenerateShortCodeResponse;
use ShortCode\Exception\ApiException;
use ShortCode\Exception\ValidationException;
use ShortCode\Exception\NetworkException;
use ShortCode\Http\MockHttpClient;
use ShortCode\Http\HttpResponse;

class GenerateShortCodeClientTest extends TestCase
{
    private MockHttpClient $httpClient;
    private GenerateShortCodeClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new MockHttpClient();
        $this->client = new GenerateShortCodeClient(
            'https://example.com/generate-short-code',
            $this->httpClient
        );
    }

    public function testGenerateShortCodeSuccess(): void
    {
        $responseBody = json_encode([
            'message' => 'Short code generated successfully',
            'source' => [
                'short_code' => 'pythontut',
                'method' => 'gemini-ai',
                'original_code' => 'pythontut',
                'was_modified' => false,
                'url' => 'https://docs.python.org/3/tutorial/index.html',
            ],
            'success' => true,
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $request = new GenerateShortCodeRequest(
            'https://docs.python.org/3/tutorial/index.html',
            'trfc.link'
        );

        $result = $this->client->generateShortCode($request);

        $this->assertInstanceOf(GenerateShortCodeResponse::class, $result);
        $this->assertSame('pythontut', $result->getShortCode());
        $this->assertSame('gemini-ai', $result->getMethod());
        $this->assertSame('pythontut', $result->getOriginalCode());
        $this->assertFalse($result->wasModified());
        $this->assertSame('https://docs.python.org/3/tutorial/index.html', $result->getUrl());

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertSame('POST', $lastRequest['method']);
        $this->assertSame('https://example.com/generate-short-code', $lastRequest['url']);

        $payload = json_decode($lastRequest['options']['body'], true);
        $this->assertSame('trfc.link', $payload['domain']);
    }

    public function testGenerateShortCodeWithoutDomainOmitsField(): void
    {
        $responseBody = json_encode([
            'message' => 'Short code generated successfully',
            'source' => [
                'short_code' => 'example',
                'method' => 'gemini-ai',
                'original_code' => 'example',
                'was_modified' => false,
                'url' => 'https://example.com',
            ],
            'success' => true,
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $request = new GenerateShortCodeRequest('https://example.com');
        $this->client->generateShortCode($request);

        $payload = json_decode($this->httpClient->getLastRequest()['options']['body'], true);
        $this->assertArrayNotHasKey('domain', $payload);
    }

    public function testInvalidRequestUrlThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('URL must start with http:// or https://');

        $request = new GenerateShortCodeRequest('not-a-url');
        $this->client->generateShortCode($request);
    }

    public function testApiValidationError(): void
    {
        $responseBody = json_encode([
            'message' => 'Invalid URL format',
            'success' => false,
        ]);

        $this->httpClient->addResponse(new HttpResponse(400, [], $responseBody));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid URL format');

        $request = new GenerateShortCodeRequest('https://example.com');
        $this->client->generateShortCode($request);
    }

    public function testApiServerError(): void
    {
        $responseBody = json_encode([
            'message' => 'Server error occurred',
            'success' => false,
        ]);

        $this->httpClient->addResponse(new HttpResponse(500, [], $responseBody));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Server error occurred');

        $request = new GenerateShortCodeRequest('https://example.com');
        $this->client->generateShortCode($request);
    }

    public function testNetworkErrorIsWrapped(): void
    {
        $this->httpClient->throwNext(new NetworkException('Network down'));

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Network down');

        $request = new GenerateShortCodeRequest('https://example.com');
        $this->client->generateShortCode($request);
    }

    public function testMissingSourceFieldsTriggersApiException(): void
    {
        $responseBody = json_encode([
            'message' => 'Short code generated successfully',
            'source' => [
                'short_code' => 'abc123',
                // Missing required fields: method and was_modified
            ],
            'success' => true,
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Response missing expected source fields');

        $request = new GenerateShortCodeRequest('https://example.com');
        $this->client->generateShortCode($request);
    }

    public function testFastEndpointResponse(): void
    {
        $responseBody = json_encode([
            'message' => 'Short code generated successfully',
            'source' => [
                'short_code' => 'pythontut',
                'method' => 'rule-based',
                'was_modified' => false,
                'candidates' => ['pythontut', 'python3', 'tutorial'],
            ],
            'success' => true,
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $request = new GenerateShortCodeRequest('https://docs.python.org/3/tutorial/index.html');
        $result = $this->client->generateShortCode($request);

        $this->assertSame('pythontut', $result->getShortCode());
        $this->assertSame('rule-based', $result->getMethod());
        $this->assertFalse($result->wasModified());
        $this->assertNotNull($result->getCandidates());
        $this->assertSame(['pythontut', 'python3', 'tutorial'], $result->getCandidates());
        $this->assertNull($result->getOriginalCode());
        $this->assertNull($result->getUrl());
    }

    public function testSmartEndpointResponse(): void
    {
        $responseBody = json_encode([
            'message' => 'Short code generated successfully',
            'source' => [
                'short_code' => 'pythontut',
                'method' => 'nlp-comprehend',
                'was_modified' => false,
                'candidates' => ['pythontut', 'python3'],
                'key_phrases' => ['python tutorial', 'documentation'],
                'entities' => ['Python', 'tutorial'],
            ],
            'success' => true,
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $request = new GenerateShortCodeRequest('https://docs.python.org/3/tutorial/index.html');
        $result = $this->client->generateShortCode($request);

        $this->assertSame('pythontut', $result->getShortCode());
        $this->assertSame('nlp-comprehend', $result->getMethod());
        $this->assertFalse($result->wasModified());
        $this->assertNotNull($result->getCandidates());
        $this->assertNotNull($result->getKeyPhrases());
        $this->assertNotNull($result->getEntities());
        $this->assertSame(['pythontut', 'python3'], $result->getCandidates());
        $this->assertSame(['python tutorial', 'documentation'], $result->getKeyPhrases());
        $this->assertSame(['Python', 'tutorial'], $result->getEntities());
    }
}
