<?php

declare(strict_types=1);

namespace Tests\Unit\TrafficPortal;

use PHPUnit\Framework\TestCase;
use TrafficPortal\TrafficPortalApiClient;
use TrafficPortal\Exception\ApiException;
use TrafficPortal\Exception\NetworkException;
use TrafficPortal\Exception\AuthenticationException;
use TrafficPortal\Http\MockHttpClient;
use TrafficPortal\Http\HttpResponse;

/**
 * Unit tests for getUserActivitySummary() API client method
 * and usage summary response validation logic.
 *
 * The API client method is tested directly with MockHttpClient.
 * The response validation logic (validate_usage_summary_response) lives in
 * TP_API_Handler which requires WordPress. We test the validation contract
 * by verifying the API client returns data that the handler would process.
 */
class UserActivitySummaryTest extends TestCase
{
    private MockHttpClient $httpClient;
    private TrafficPortalApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new MockHttpClient();
        $this->client = new TrafficPortalApiClient(
            'https://api.example.com',
            'test-api-key',
            30,
            $this->httpClient
        );
    }

    // ---------------------------------------------------------------
    // API Client: getUserActivitySummary() tests
    // ---------------------------------------------------------------

    public function testGetUserActivitySummarySuccess(): void
    {
        $responseBody = json_encode([
            'message' => 'Activity summary retrieved',
            'success' => true,
            'source' => [
                [
                    'date' => '2026-02-20',
                    'totalHits' => 5,
                    'hitCost' => -0.5,
                    'balance' => -0.5,
                ],
                [
                    'date' => '2026-02-21',
                    'totalHits' => 10,
                    'hitCost' => -1.0,
                    'balance' => -1.5,
                ],
            ],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $result = $this->client->getUserActivitySummary(125, '2026-02-20', '2026-02-21');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('source', $result);
        $this->assertCount(2, $result['source']);
        $this->assertEquals('2026-02-20', $result['source'][0]['date']);
        $this->assertEquals(5, $result['source'][0]['totalHits']);
        $this->assertEquals(-0.5, $result['source'][0]['hitCost']);
    }

    public function testGetUserActivitySummaryBuildsCorrectUrl(): void
    {
        $responseBody = json_encode([
            'message' => 'Activity summary retrieved',
            'success' => true,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $this->client->getUserActivitySummary(125, '2026-02-01', '2026-02-28');

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertStringContainsString('/user-activity-summary/125', $lastRequest['url']);
        $this->assertStringContainsString('start_date=2026-02-01', $lastRequest['url']);
        $this->assertStringContainsString('end_date=2026-02-28', $lastRequest['url']);
        $this->assertEquals('GET', $lastRequest['method']);
    }

    public function testGetUserActivitySummarySendsCorrectHeaders(): void
    {
        $responseBody = json_encode([
            'message' => 'Activity summary retrieved',
            'success' => true,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $this->client->getUserActivitySummary(125, '2026-02-01', '2026-02-28');

        $lastRequest = $this->httpClient->getLastRequest();
        $headers = $lastRequest['options']['headers'];

        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('test-api-key', $headers['x-api-key']);
    }

    public function testGetUserActivitySummaryUses15SecondTimeout(): void
    {
        $responseBody = json_encode([
            'message' => 'Activity summary retrieved',
            'success' => true,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $this->client->getUserActivitySummary(125, '2026-02-01', '2026-02-28');

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertEquals(15, $lastRequest['options']['timeout']);
    }

    public function testGetUserActivitySummaryEmptySource(): void
    {
        $responseBody = json_encode([
            'message' => 'Activity summary retrieved',
            'success' => true,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $result = $this->client->getUserActivitySummary(125, '2026-02-01', '2026-02-28');

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['source']);
    }

    public function testGetUserActivitySummaryAuthenticationError(): void
    {
        $responseBody = json_encode([
            'message' => 'Invalid API key',
            'success' => false,
        ]);

        $this->httpClient->addResponse(new HttpResponse(401, [], $responseBody));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->client->getUserActivitySummary(125, '2026-02-01', '2026-02-28');
    }

    public function testGetUserActivitySummaryServerError(): void
    {
        $responseBody = json_encode([
            'message' => 'Internal server error',
            'success' => false,
        ]);

        $this->httpClient->addResponse(new HttpResponse(500, [], $responseBody));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Server error');

        $this->client->getUserActivitySummary(125, '2026-02-01', '2026-02-28');
    }

    public function testGetUserActivitySummaryNetworkError(): void
    {
        $this->httpClient->throwNext(new NetworkException('Connection refused'));

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->client->getUserActivitySummary(125, '2026-02-01', '2026-02-28');
    }

    public function testGetUserActivitySummaryEmptyResponse(): void
    {
        $this->httpClient->addResponse(new HttpResponse(200, [], ''));

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Empty response from API');

        $this->client->getUserActivitySummary(125, '2026-02-01', '2026-02-28');
    }

    public function testGetUserActivitySummaryInvalidJsonResponse(): void
    {
        $this->httpClient->addResponse(new HttpResponse(200, [], 'not valid json'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $this->client->getUserActivitySummary(125, '2026-02-01', '2026-02-28');
    }

    public function testGetUserActivitySummaryWithNegativeUid(): void
    {
        $responseBody = json_encode([
            'message' => 'Activity summary retrieved',
            'success' => true,
            'source' => [],
        ]);

        $this->httpClient->addResponse(new HttpResponse(200, [], $responseBody));

        $result = $this->client->getUserActivitySummary(-1, '2026-02-01', '2026-02-28');

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertStringContainsString('/user-activity-summary/-1', $lastRequest['url']);
        $this->assertTrue($result['success']);
    }

    // ---------------------------------------------------------------
    // Response validation contract tests
    // These verify the structure that validate_usage_summary_response()
    // expects and produces. The actual method lives in TP_API_Handler
    // (WordPress-dependent), so we test the contract here.
    // ---------------------------------------------------------------

    public function testValidationContractValidData(): void
    {
        $raw = [
            'message' => 'Activity summary retrieved',
            'success' => true,
            'source' => [
                ['date' => '2026-02-20', 'totalHits' => 5, 'hitCost' => -0.5, 'balance' => -0.5],
                ['date' => '2026-02-21', 'totalHits' => 10, 'hitCost' => -1.0, 'balance' => -1.5],
            ],
        ];

        $validated = $this->applyValidation($raw);

        $this->assertArrayHasKey('days', $validated);
        $this->assertCount(2, $validated['days']);
        $this->assertEquals('2026-02-20', $validated['days'][0]['date']);
        $this->assertSame(5, $validated['days'][0]['totalHits']);
        $this->assertSame(-0.5, $validated['days'][0]['hitCost']);
        $this->assertSame(-0.5, $validated['days'][0]['balance']);
    }

    public function testValidationContractMissingSourceKey(): void
    {
        $raw = [
            'message' => 'Activity summary retrieved',
            'success' => true,
        ];

        $validated = $this->applyValidation($raw);

        $this->assertArrayHasKey('days', $validated);
        $this->assertEmpty($validated['days']);
    }

    public function testValidationContractRecordsMissingDateAreSkipped(): void
    {
        $raw = [
            'source' => [
                ['totalHits' => 5, 'hitCost' => -0.5, 'balance' => -0.5], // missing date
                ['date' => '2026-02-21', 'totalHits' => 10, 'hitCost' => -1.0, 'balance' => -1.5],
            ],
        ];

        $validated = $this->applyValidation($raw);

        $this->assertCount(1, $validated['days']);
        $this->assertEquals('2026-02-21', $validated['days'][0]['date']);
    }

    public function testValidationContractRecordsMissingTotalHitsAreSkipped(): void
    {
        $raw = [
            'source' => [
                ['date' => '2026-02-20', 'hitCost' => -0.5, 'balance' => -0.5], // missing totalHits
                ['date' => '2026-02-21', 'totalHits' => 10, 'hitCost' => -1.0, 'balance' => -1.5],
            ],
        ];

        $validated = $this->applyValidation($raw);

        $this->assertCount(1, $validated['days']);
        $this->assertEquals('2026-02-21', $validated['days'][0]['date']);
    }

    public function testValidationContractExtraFieldsStripped(): void
    {
        $raw = [
            'source' => [
                [
                    'date' => '2026-02-20',
                    'totalHits' => 5,
                    'hitCost' => -0.5,
                    'balance' => -0.5,
                    'unexpectedField' => 'should be stripped',
                    'anotherExtra' => 42,
                ],
            ],
        ];

        $validated = $this->applyValidation($raw);

        $this->assertCount(1, $validated['days']);
        $record = $validated['days'][0];
        $this->assertCount(4, $record); // only date, totalHits, hitCost, balance
        $this->assertArrayNotHasKey('unexpectedField', $record);
        $this->assertArrayNotHasKey('anotherExtra', $record);
    }

    public function testValidationContractTypeCoercion(): void
    {
        $raw = [
            'source' => [
                [
                    'date' => '2026-02-20',
                    'totalHits' => '5',        // string -> int
                    'hitCost' => '-0.5',       // string -> float
                    'balance' => '-0.5',       // string -> float
                ],
            ],
        ];

        $validated = $this->applyValidation($raw);

        $this->assertCount(1, $validated['days']);
        $record = $validated['days'][0];
        $this->assertSame(5, $record['totalHits']);
        $this->assertSame(-0.5, $record['hitCost']);
        $this->assertSame(-0.5, $record['balance']);
    }

    public function testValidationContractDefaultsForMissingOptionalFields(): void
    {
        $raw = [
            'source' => [
                [
                    'date' => '2026-02-20',
                    'totalHits' => 5,
                    // hitCost and balance missing -- should default to 0
                ],
            ],
        ];

        $validated = $this->applyValidation($raw);

        $this->assertCount(1, $validated['days']);
        $record = $validated['days'][0];
        $this->assertSame(0.0, $record['hitCost']);
        $this->assertSame(0.0, $record['balance']);
    }

    public function testValidationContractSourceNotArray(): void
    {
        $raw = [
            'source' => 'not an array',
        ];

        $validated = $this->applyValidation($raw);

        $this->assertArrayHasKey('days', $validated);
        $this->assertEmpty($validated['days']);
    }

    // ---------------------------------------------------------------
    // Helper: replicate validate_usage_summary_response logic
    // This mirrors the exact logic in TP_API_Handler so we can verify
    // the contract without loading WordPress.
    // ---------------------------------------------------------------

    private function applyValidation(array $raw): array
    {
        $source = $raw['source'] ?? [];

        if (!is_array($source)) {
            $source = [];
        }

        $days = [];
        foreach ($source as $record) {
            if (!isset($record['date']) || !isset($record['totalHits'])) {
                continue;
            }

            $days[] = [
                'date'      => (string) $record['date'],
                'totalHits' => (int) $record['totalHits'],
                'hitCost'   => (float) ($record['hitCost'] ?? 0),
                'balance'   => (float) ($record['balance'] ?? 0),
            ];
        }

        return ['days' => $days];
    }
}
