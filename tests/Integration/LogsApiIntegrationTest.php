<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Logs REST API endpoint
 *
 * These tests require environment variables:
 * - TP_SITE_URL: The WordPress site URL (e.g., 'https://yoursite.com')
 * - TP_LOGS_API_KEY: The API key matching the TP_LOGS_API_KEY constant in wp-config.php
 *
 * Run with: vendor/bin/phpunit tests/Integration/LogsApiIntegrationTest.php
 */
class LogsApiIntegrationTest extends TestCase
{
    private ?string $siteUrl = null;
    private ?string $apiKey = null;
    private string $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $siteUrl = getenv('TP_SITE_URL');
        $apiKey = getenv('TP_LOGS_API_KEY');

        $this->siteUrl = $siteUrl !== false ? rtrim($siteUrl, '/') : null;
        $this->apiKey = $apiKey !== false ? $apiKey : null;

        if (!$this->siteUrl || !$this->apiKey) {
            $this->markTestSkipped(
                'Integration tests skipped: Set TP_SITE_URL and TP_LOGS_API_KEY environment variables to run these tests'
            );
        }

        $this->endpoint = $this->siteUrl . '/wp-json/tp-link-shortener/v1/logs';
    }

    /**
     * Helper to make GET requests to the logs endpoint
     */
    private function request(array $queryParams = [], array $headers = []): array
    {
        $url = $this->endpoint;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => array_map(
                fn($key, $value) => "{$key}: {$value}",
                array_keys($headers),
                array_values($headers)
            ),
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $this->assertEmpty($error, "cURL error: {$error}");

        return [
            'status' => $httpCode,
            'body' => $body,
            'data' => json_decode($body, true),
        ];
    }

    // -------------------------------------------------------
    // Authentication Tests
    // -------------------------------------------------------

    public function testReturns401WithoutApiKey(): void
    {
        $response = $this->request([], []);

        $this->assertEquals(401, $response['status'], 'Should return 401 without API key');
    }

    public function testReturns401WithInvalidApiKey(): void
    {
        $response = $this->request([], ['x-api-key' => 'invalid-key-' . uniqid()]);

        $this->assertEquals(401, $response['status'], 'Should return 401 with invalid API key');
    }

    public function testReturns401WithEmptyApiKey(): void
    {
        $response = $this->request([], ['x-api-key' => '']);

        $this->assertEquals(401, $response['status'], 'Should return 401 with empty API key');
    }

    // -------------------------------------------------------
    // Successful Request Tests
    // -------------------------------------------------------

    public function testReturns200WithValidApiKey(): void
    {
        $response = $this->request([], ['x-api-key' => $this->apiKey]);

        $this->assertEquals(200, $response['status'], 'Should return 200 with valid API key');
        $this->assertNotNull($response['data'], 'Response should be valid JSON');
    }

    public function testDefaultResponseStructure(): void
    {
        $response = $this->request([], ['x-api-key' => $this->apiKey]);

        $this->assertEquals(200, $response['status']);
        $data = $response['data'];

        // Verify top-level keys
        $this->assertArrayHasKey('windows', $data);
        $this->assertArrayHasKey('total_entries', $data);
        $this->assertArrayHasKey('current_window', $data);

        // Verify types
        $this->assertIsArray($data['windows']);
        $this->assertIsInt($data['total_entries']);
        $this->assertIsString($data['current_window']);

        // Default should return 1 window
        $this->assertCount(1, $data['windows']);
    }

    public function testWindowObjectStructure(): void
    {
        $response = $this->request([], ['x-api-key' => $this->apiKey]);
        $data = $response['data'];

        $this->assertNotEmpty($data['windows']);
        $window = $data['windows'][0];

        // Verify window object keys
        $this->assertArrayHasKey('window', $window);
        $this->assertArrayHasKey('entries', $window);

        // Verify types
        $this->assertIsString($window['window']);
        $this->assertIsArray($window['entries']);
    }

    public function testCurrentWindowLabelFormat(): void
    {
        $response = $this->request([], ['x-api-key' => $this->apiKey]);
        $data = $response['data'];

        // Window label should match YYYY-MM-DD-HHMM format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}-\d{4}$/',
            $data['current_window'],
            'current_window should match YYYY-MM-DD-HHMM format'
        );

        // First window's label should match current_window (default windows=1)
        $this->assertEquals(
            $data['current_window'],
            $data['windows'][0]['window'],
            'First window label should match current_window'
        );
    }

    public function testWindowMinuteIsRoundedToTens(): void
    {
        $response = $this->request([], ['x-api-key' => $this->apiKey]);
        $data = $response['data'];

        // Extract the minute portion (last 2 chars of HHMM)
        $hhmm = substr($data['current_window'], -4);
        $minute = (int) substr($hhmm, 2, 2);

        $this->assertEquals(
            0,
            $minute % 10,
            "Window minute should be a multiple of 10, got: {$minute}"
        );
    }

    // -------------------------------------------------------
    // Windows Parameter Tests
    // -------------------------------------------------------

    public function testMultipleWindows(): void
    {
        $response = $this->request(['windows' => 3], ['x-api-key' => $this->apiKey]);

        $this->assertEquals(200, $response['status']);
        $data = $response['data'];

        $this->assertCount(3, $data['windows'], 'Should return 3 windows');

        // Windows should be ordered most recent first
        // and each window label should be unique
        $labels = array_map(fn($w) => $w['window'], $data['windows']);
        $this->assertCount(3, array_unique($labels), 'All window labels should be unique');
    }

    public function testWindowsAre10MinutesApart(): void
    {
        $response = $this->request(['windows' => 3], ['x-api-key' => $this->apiKey]);
        $data = $response['data'];

        $this->assertCount(3, $data['windows']);

        // Parse timestamps from window labels and verify 10-minute spacing
        $timestamps = [];
        foreach ($data['windows'] as $window) {
            // Parse YYYY-MM-DD-HHMM
            $label = $window['window'];
            $datePart = substr($label, 0, 10);
            $hour = substr($label, 11, 2);
            $minute = substr($label, 13, 2);
            $timestamps[] = strtotime("{$datePart} {$hour}:{$minute}:00");
        }

        // Each window should be 600 seconds (10 min) before the previous
        for ($i = 1; $i < count($timestamps); $i++) {
            $diff = $timestamps[$i - 1] - $timestamps[$i];
            $this->assertEquals(
                600,
                $diff,
                "Window {$i} should be 10 minutes after window " . ($i + 1) . ", got {$diff}s diff"
            );
        }
    }

    public function testSingleWindow(): void
    {
        $response = $this->request(['windows' => 1], ['x-api-key' => $this->apiKey]);

        $this->assertEquals(200, $response['status']);
        $this->assertCount(1, $response['data']['windows']);
    }

    public function testMaxWindows(): void
    {
        $response = $this->request(['windows' => 144], ['x-api-key' => $this->apiKey]);

        $this->assertEquals(200, $response['status']);
        $this->assertCount(144, $response['data']['windows'], 'Should return 144 windows (24 hours)');
    }

    // -------------------------------------------------------
    // Validation Tests
    // -------------------------------------------------------

    public function testInvalidWindowsZero(): void
    {
        $response = $this->request(['windows' => 0], ['x-api-key' => $this->apiKey]);

        $this->assertEquals(400, $response['status'], 'windows=0 should return 400');
    }

    public function testInvalidWindowsNegative(): void
    {
        $response = $this->request(['windows' => -1], ['x-api-key' => $this->apiKey]);

        $this->assertEquals(400, $response['status'], 'Negative windows should return 400');
    }

    public function testInvalidWindowsOver144(): void
    {
        $response = $this->request(['windows' => 145], ['x-api-key' => $this->apiKey]);

        $this->assertEquals(400, $response['status'], 'windows > 144 should return 400');
    }

    public function testInvalidWindowsNonNumeric(): void
    {
        $response = $this->request(['windows' => 'abc'], ['x-api-key' => $this->apiKey]);

        $this->assertEquals(400, $response['status'], 'Non-numeric windows should return 400');
    }

    // -------------------------------------------------------
    // Entry Content Tests
    // -------------------------------------------------------

    public function testTotalEntriesMatchesWindowEntries(): void
    {
        $response = $this->request(['windows' => 6], ['x-api-key' => $this->apiKey]);
        $data = $response['data'];

        $countedEntries = 0;
        foreach ($data['windows'] as $window) {
            $countedEntries += count($window['entries']);
        }

        $this->assertEquals(
            $data['total_entries'],
            $countedEntries,
            'total_entries should equal sum of all window entry counts'
        );
    }

    public function testEntriesAreStrings(): void
    {
        $response = $this->request(['windows' => 6], ['x-api-key' => $this->apiKey]);
        $data = $response['data'];

        foreach ($data['windows'] as $window) {
            foreach ($window['entries'] as $entry) {
                $this->assertIsString($entry, 'Each log entry should be a string');
            }
        }
    }

    public function testEntriesHaveTimestampFormat(): void
    {
        $response = $this->request(['windows' => 6], ['x-api-key' => $this->apiKey]);
        $data = $response['data'];

        foreach ($data['windows'] as $window) {
            foreach ($window['entries'] as $entry) {
                $this->assertMatchesRegularExpression(
                    '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/',
                    $entry,
                    "Entry should start with [YYYY-MM-DD HH:MM:SS] timestamp: {$entry}"
                );
            }
        }
    }

    public function testEmptyWindowsReturnEmptyEntries(): void
    {
        // Request many windows - some in the past should be empty
        $response = $this->request(['windows' => 144], ['x-api-key' => $this->apiKey]);
        $data = $response['data'];

        $emptyWindows = array_filter($data['windows'], fn($w) => empty($w['entries']));

        // We can't guarantee empty windows exist, but we can verify structure is correct
        foreach ($emptyWindows as $window) {
            $this->assertIsArray($window['entries']);
            $this->assertCount(0, $window['entries']);
        }
    }
}
