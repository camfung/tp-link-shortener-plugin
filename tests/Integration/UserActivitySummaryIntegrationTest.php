<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use TrafficPortal\TrafficPortalApiClient;
use TrafficPortal\Exception\AuthenticationException;
use TrafficPortal\Exception\ApiException;
use TrafficPortal\Exception\NetworkException;

/**
 * Integration tests for getUserActivitySummary() API client method.
 *
 * These tests require environment variables:
 * - TP_API_ENDPOINT: The Traffic Portal API endpoint
 * - TP_API_KEY: The API key for authentication
 * - TP_USER_ID: User ID for testing
 *
 * Run with: vendor/bin/phpunit tests/Integration/UserActivitySummaryIntegrationTest.php
 */
class UserActivitySummaryIntegrationTest extends TestCase
{
    private ?TrafficPortalApiClient $client = null;
    private ?string $apiEndpoint = null;
    private ?string $apiKey = null;
    private ?int $userId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $apiEndpoint = getenv('TP_API_ENDPOINT');
        $apiKey = getenv('TP_API_KEY');
        $userIdStr = getenv('TP_USER_ID');

        $this->apiEndpoint = $apiEndpoint !== false ? $apiEndpoint : null;
        $this->apiKey = $apiKey !== false ? $apiKey : null;
        $this->userId = $userIdStr !== false ? (int) $userIdStr : null;

        if (!$this->apiEndpoint || !$this->apiKey) {
            $this->markTestSkipped(
                'Integration tests skipped: Set TP_API_ENDPOINT and TP_API_KEY environment variables to run these tests'
            );
        }

        $this->client = new TrafficPortalApiClient($this->apiEndpoint, $this->apiKey);
    }

    public function testGetUserActivitySummaryReturnsValidShape(): void
    {
        $uid = $this->userId ?? 125;
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));

        $result = $this->client->getUserActivitySummary($uid, $startDate, $endDate);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('source', $result);
        $this->assertIsArray($result['source']);
    }

    public function testGetUserActivitySummarySourceRecordsHaveExpectedFields(): void
    {
        $uid = $this->userId ?? 125;
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-30 days'));

        $result = $this->client->getUserActivitySummary($uid, $startDate, $endDate);

        $this->assertIsArray($result['source']);

        // If there are records, verify each has the expected fields
        foreach ($result['source'] as $record) {
            $this->assertArrayHasKey('date', $record, 'Each record should have a date field');
            $this->assertArrayHasKey('totalHits', $record, 'Each record should have a totalHits field');
            $this->assertArrayHasKey('hitCost', $record, 'Each record should have a hitCost field');
            $this->assertArrayHasKey('balance', $record, 'Each record should have a balance field');

            // Verify types
            $this->assertIsString($record['date']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $record['date'], 'Date should be YYYY-MM-DD format');
            $this->assertIsNumeric($record['totalHits']);
            $this->assertIsNumeric($record['hitCost']);
            $this->assertIsNumeric($record['balance']);
        }
    }

    public function testGetUserActivitySummaryShortDateRange(): void
    {
        $uid = $this->userId ?? 125;
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));

        $result = $this->client->getUserActivitySummary($uid, $startDate, $endDate);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['source']);

        // A 7-day range should return at most 8 records (inclusive of both endpoints)
        $this->assertLessThanOrEqual(8, count($result['source']),
            'A 7-day range should return at most 8 daily records');
    }

    public function testGetUserActivitySummaryMessageFieldExists(): void
    {
        $uid = $this->userId ?? 125;
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));

        $result = $this->client->getUserActivitySummary($uid, $startDate, $endDate);

        $this->assertArrayHasKey('message', $result);
        $this->assertIsString($result['message']);
    }
}
