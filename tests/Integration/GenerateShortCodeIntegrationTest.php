<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ShortCode\GenerateShortCodeClient;
use ShortCode\GenerationTier;
use ShortCode\GenerationMethod;
use ShortCode\DTO\GenerateShortCodeRequest;
use ShortCode\DTO\GenerateShortCodeResponse;
use ShortCode\Exception\NetworkException;
use ShortCode\Exception\ApiException;

class GenerateShortCodeIntegrationTest extends TestCase
{
    private ?GenerateShortCodeClient $client = null;

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

    public function testFastTierLive(): void
    {
        $request = new GenerateShortCodeRequest(
            'https://docs.python.org/3/tutorial/index.html',
            'trfc.link'
        );

        $response = $this->client->fast($request);

        $this->assertInstanceOf(GenerateShortCodeResponse::class, $response);
        $this->assertNotEmpty($response->getShortCode());
        $this->assertSame(GenerationMethod::RuleBased, $response->getMethod());
        $this->assertIsArray($response->getCandidates());
    }

    public function testSmartTierLive(): void
    {
        $request = new GenerateShortCodeRequest(
            'https://docs.python.org/3/tutorial/index.html',
            'trfc.link'
        );

        $response = $this->client->smart($request);

        $this->assertInstanceOf(GenerateShortCodeResponse::class, $response);
        $this->assertNotEmpty($response->getShortCode());
        $this->assertSame(GenerationMethod::NlpComprehend, $response->getMethod());
        $this->assertIsArray($response->getCandidates());
        $this->assertIsArray($response->getKeyPhrases());
        $this->assertIsArray($response->getEntities());
    }

    public function testAITierLive(): void
    {
        $request = new GenerateShortCodeRequest(
            'https://docs.python.org/3/tutorial/index.html',
            'trfc.link'
        );

        $response = $this->client->ai($request);

        $this->assertInstanceOf(GenerateShortCodeResponse::class, $response);
        $this->assertNotEmpty($response->getShortCode());
        $this->assertSame(GenerationMethod::GeminiAI, $response->getMethod());
        $this->assertNotNull($response->getUrl());
    }

    public function testDefaultTierIsAI(): void
    {
        $request = new GenerateShortCodeRequest(
            'https://docs.python.org/3/tutorial/index.html',
            'trfc.link'
        );

        $response = $this->client->generateShortCode($request);

        $this->assertInstanceOf(GenerateShortCodeResponse::class, $response);
        $this->assertSame(GenerationMethod::GeminiAI, $response->getMethod());
    }
}
