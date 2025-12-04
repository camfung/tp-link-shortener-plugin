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

    public function testGenerateShortCodeLive(): void
    {
        $request = new GenerateShortCodeRequest(
            'https://docs.python.org/3/tutorial/index.html',
            'trfc.link'
        );

        $response = $this->client->generateShortCode($request);

        $this->assertInstanceOf(GenerateShortCodeResponse::class, $response);
        $this->assertNotEmpty($response->getShortCode());
        $this->assertSame('https://docs.python.org/3/tutorial/index.html', $response->getUrl());
    }
}
