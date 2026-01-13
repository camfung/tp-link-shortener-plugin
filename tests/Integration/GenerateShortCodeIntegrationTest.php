<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ShortCode\GenerateShortCodeClient;
use ShortCode\DTO\GenerateShortCodeRequest;
use ShortCode\DTO\GenerateShortCodeResponse;
use ShortCode\Exception\NetworkException;
use ShortCode\Exception\ApiException;

class GenerateShortCodeIntegrationTest extends TestCase
{
    private ?GenerateShortCodeClient $client = null;
    private string $testUrl = 'https://docs.python.org/3/tutorial/index.html';
    private string $testDomain = 'trfc.link';

    protected function setUp(): void
    {
        parent::setUp();

        $runIntegration = getenv('RUN_SHORTCODE_INTEGRATION');
        if ($runIntegration !== '1') {
            $this->markTestSkipped(
                'Set RUN_SHORTCODE_INTEGRATION=1 to run live Generate Short Code API tests'
            );
        }

        $endpoint = getenv('SHORTCODE_API_ENDPOINT');
        $this->client = new GenerateShortCodeClient($endpoint ?: null);
    }

    public function testGenerateShortCodeFastEndpoint(): void
    {
        $this->client->setEndpointType(GenerateShortCodeClient::ENDPOINT_FAST);

        $request = new GenerateShortCodeRequest($this->testUrl, $this->testDomain);
        $response = $this->client->generateShortCode($request);

        $this->assertInstanceOf(GenerateShortCodeResponse::class, $response);
        $this->assertNotEmpty($response->getShortCode());
        $this->assertSame('rule-based', $response->getMethod());
        $this->assertIsBool($response->wasModified());
        $this->assertNotEmpty($response->getMessage());

        // Fast endpoint should have candidates array
        if ($response->getCandidates() !== null) {
            $this->assertIsArray($response->getCandidates());
        }

        // Fast endpoint should not have these fields
        $this->assertNull($response->getKeyPhrases());
        $this->assertNull($response->getEntities());
    }

    public function testGenerateShortCodeSmartEndpoint(): void
    {
        $this->client->setEndpointType(GenerateShortCodeClient::ENDPOINT_SMART);

        $request = new GenerateShortCodeRequest($this->testUrl, $this->testDomain);
        $response = $this->client->generateShortCode($request);

        $this->assertInstanceOf(GenerateShortCodeResponse::class, $response);
        $this->assertNotEmpty($response->getShortCode());
        $this->assertSame('nlp-comprehend', $response->getMethod());
        $this->assertIsBool($response->wasModified());
        $this->assertNotEmpty($response->getMessage());

        // Smart endpoint may have candidates, key_phrases, and entities
        if ($response->getCandidates() !== null) {
            $this->assertIsArray($response->getCandidates());
        }
        if ($response->getKeyPhrases() !== null) {
            $this->assertIsArray($response->getKeyPhrases());
        }
        if ($response->getEntities() !== null) {
            $this->assertIsArray($response->getEntities());
        }
    }

    public function testGenerateShortCodeAiEndpoint(): void
    {
        $this->client->setEndpointType(GenerateShortCodeClient::ENDPOINT_AI);

        $request = new GenerateShortCodeRequest($this->testUrl, $this->testDomain);
        $response = $this->client->generateShortCode($request);

        $this->assertInstanceOf(GenerateShortCodeResponse::class, $response);
        $this->assertNotEmpty($response->getShortCode());
        $this->assertSame('gemini-ai', $response->getMethod());
        $this->assertIsBool($response->wasModified());
        $this->assertNotEmpty($response->getMessage());

        // AI endpoint should have original_code and url
        $this->assertNotNull($response->getOriginalCode());
        $this->assertNotEmpty($response->getOriginalCode());
        $this->assertNotNull($response->getUrl());
        $this->assertSame($this->testUrl, $response->getUrl());

        // AI endpoint should not have these fields
        $this->assertNull($response->getCandidates());
        $this->assertNull($response->getKeyPhrases());
        $this->assertNull($response->getEntities());
    }

    public function testGenerateShortCodeLegacyEndpoint(): void
    {
        $this->client->setEndpointType(GenerateShortCodeClient::ENDPOINT_LEGACY);

        $request = new GenerateShortCodeRequest($this->testUrl, $this->testDomain);
        $response = $this->client->generateShortCode($request);

        $this->assertInstanceOf(GenerateShortCodeResponse::class, $response);
        $this->assertNotEmpty($response->getShortCode());
        $this->assertSame('gemini-ai', $response->getMethod());
        $this->assertNotNull($response->getUrl());
        $this->assertSame($this->testUrl, $response->getUrl());
    }
}
