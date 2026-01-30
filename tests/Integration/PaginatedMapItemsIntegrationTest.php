<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use TrafficPortal\TrafficPortalApiClient;
use TrafficPortal\DTO\PaginatedMapItemsResponse;
use TrafficPortal\DTO\MapItem;
use TrafficPortal\Exception\ValidationException;
use TrafficPortal\Exception\PageNotFoundException;

/**
 * Integration tests for Paginated Map Items API
 *
 * These tests require environment variables:
 * - TP_API_ENDPOINT: The Traffic Portal API endpoint
 * - TP_API_KEY: The API key for authentication
 * - TP_USER_ID: User ID for testing
 *
 * Run with: vendor/bin/phpunit tests/Integration/PaginatedMapItemsIntegrationTest.php
 */
class PaginatedMapItemsIntegrationTest extends TestCase
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
        $this->userId = $userIdStr !== false ? (int)$userIdStr : null;

        if (!$this->apiEndpoint || !$this->apiKey || !$this->userId) {
            $this->markTestSkipped(
                'Integration tests skipped: Set TP_API_ENDPOINT, TP_API_KEY, and TP_USER_ID environment variables to run these tests'
            );
        }

        $this->client = new TrafficPortalApiClient($this->apiEndpoint, $this->apiKey);
    }

    public function testGetUserMapItemsFirstPage(): void
    {
        $response = $this->client->getUserMapItems($this->userId, page: 1, pageSize: 10);

        $this->assertInstanceOf(PaginatedMapItemsResponse::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(1, $response->getPage());
        $this->assertEquals(10, $response->getPageSize());
        $this->assertGreaterThanOrEqual(0, $response->getTotalRecords());
        $this->assertGreaterThanOrEqual(0, $response->getTotalPages());

        if ($response->hasItems()) {
            $firstItem = $response->getItems()[0];
            $this->assertInstanceOf(MapItem::class, $firstItem);
            $this->assertGreaterThan(0, $firstItem->getMid());
            $this->assertNotEmpty($firstItem->getTpKey());
            $this->assertNotEmpty($firstItem->getDomain());
            $this->assertNotEmpty($firstItem->getDestination());
        }

        echo "\n--- First Page Results ---\n";
        echo "Total Records: {$response->getTotalRecords()}\n";
        echo "Total Pages: {$response->getTotalPages()}\n";
        echo "Items on Page: " . count($response->getItems()) . "\n";
    }

    public function testGetUserMapItemsPagination(): void
    {
        // Get first page
        $page1 = $this->client->getUserMapItems($this->userId, page: 1, pageSize: 5);

        if ($page1->getTotalPages() < 2) {
            $this->markTestSkipped('Not enough records to test pagination');
        }

        // Get second page
        $page2 = $this->client->getUserMapItems($this->userId, page: 2, pageSize: 5);

        $this->assertEquals(2, $page2->getPage());
        $this->assertEquals(5, $page2->getPageSize());
        $this->assertEquals($page1->getTotalRecords(), $page2->getTotalRecords());
        $this->assertEquals($page1->getTotalPages(), $page2->getTotalPages());

        // Verify different items on each page
        if ($page1->hasItems() && $page2->hasItems()) {
            $page1Mids = array_map(fn($item) => $item->getMid(), $page1->getItems());
            $page2Mids = array_map(fn($item) => $item->getMid(), $page2->getItems());

            $this->assertEmpty(
                array_intersect($page1Mids, $page2Mids),
                'Pages should contain different items'
            );
        }

        echo "\n--- Pagination Test ---\n";
        echo "Page 1 items: " . count($page1->getItems()) . "\n";
        echo "Page 2 items: " . count($page2->getItems()) . "\n";
        echo "Has next page: " . ($page1->hasNextPage() ? 'yes' : 'no') . "\n";
        echo "Has previous page: " . ($page2->hasPreviousPage() ? 'yes' : 'no') . "\n";
    }

    public function testGetUserMapItemsSortByUpdatedAtDesc(): void
    {
        $response = $this->client->getUserMapItems(
            $this->userId,
            page: 1,
            pageSize: 10,
            sort: 'updated_at:desc'
        );

        $this->assertTrue($response->isSuccess());

        if (count($response->getItems()) >= 2) {
            $items = $response->getItems();
            for ($i = 0; $i < count($items) - 1; $i++) {
                $currentDate = $items[$i]->getUpdatedAt();
                $nextDate = $items[$i + 1]->getUpdatedAt();
                $this->assertGreaterThanOrEqual(
                    $nextDate,
                    $currentDate,
                    'Items should be sorted by updated_at descending'
                );
            }
        }
    }

    public function testGetUserMapItemsSortByCreatedAtAsc(): void
    {
        $response = $this->client->getUserMapItems(
            $this->userId,
            page: 1,
            pageSize: 10,
            sort: 'created_at:asc'
        );

        $this->assertTrue($response->isSuccess());

        if (count($response->getItems()) >= 2) {
            $items = $response->getItems();
            for ($i = 0; $i < count($items) - 1; $i++) {
                $currentDate = $items[$i]->getCreatedAt();
                $nextDate = $items[$i + 1]->getCreatedAt();
                $this->assertLessThanOrEqual(
                    $nextDate,
                    $currentDate,
                    'Items should be sorted by created_at ascending'
                );
            }
        }
    }

    public function testGetUserMapItemsWithUsageStats(): void
    {
        $response = $this->client->getUserMapItems(
            $this->userId,
            page: 1,
            pageSize: 10,
            includeUsage: true
        );

        $this->assertTrue($response->isSuccess());

        if ($response->hasItems()) {
            $item = $response->getItems()[0];
            $usage = $item->getUsage();

            if ($usage !== null) {
                $this->assertGreaterThanOrEqual(0, $usage->getTotal());
                $this->assertGreaterThanOrEqual(0, $usage->getQr());
                $this->assertGreaterThanOrEqual(0, $usage->getRegular());
                $this->assertEquals(
                    $usage->getQr() + $usage->getRegular(),
                    $usage->getTotal(),
                    'Total should equal qr + regular'
                );

                echo "\n--- Usage Stats ---\n";
                echo "Link: {$item->getTpKey()}\n";
                echo "Total: {$usage->getTotal()}\n";
                echo "QR: {$usage->getQr()}\n";
                echo "Regular: {$usage->getRegular()}\n";
            }
        }
    }

    public function testGetUserMapItemsWithoutUsageStats(): void
    {
        $response = $this->client->getUserMapItems(
            $this->userId,
            page: 1,
            pageSize: 10,
            includeUsage: false
        );

        $this->assertTrue($response->isSuccess());

        if ($response->hasItems()) {
            $item = $response->getItems()[0];
            // When include_usage is false, usage should be null
            $this->assertNull($item->getUsage());
        }
    }

    public function testGetUserMapItemsFilterByActiveStatus(): void
    {
        $response = $this->client->getUserMapItems(
            $this->userId,
            page: 1,
            pageSize: 50,
            status: 'active'
        );

        $this->assertTrue($response->isSuccess());

        foreach ($response->getItems() as $item) {
            $this->assertEquals(
                'active',
                $item->getStatus(),
                'All items should have active status'
            );
        }

        echo "\n--- Active Status Filter ---\n";
        echo "Active items found: " . count($response->getItems()) . "\n";
    }

    public function testGetUserMapItemsSearchByTpKey(): void
    {
        // First get a sample tpKey if available
        $initialResponse = $this->client->getUserMapItems($this->userId, page: 1, pageSize: 1);

        if (!$initialResponse->hasItems()) {
            $this->markTestSkipped('No items available to test search');
        }

        $sampleKey = $initialResponse->getItems()[0]->getTpKey();
        $searchTerm = substr($sampleKey, 0, min(5, strlen($sampleKey)));

        $response = $this->client->getUserMapItems(
            $this->userId,
            page: 1,
            pageSize: 50,
            search: $searchTerm
        );

        $this->assertTrue($response->isSuccess());

        // All results should contain the search term in tpKey or destination
        foreach ($response->getItems() as $item) {
            $matchesTpKey = stripos($item->getTpKey(), $searchTerm) !== false;
            $matchesDestination = stripos($item->getDestination(), $searchTerm) !== false;
            $this->assertTrue(
                $matchesTpKey || $matchesDestination,
                "Item should match search term in tpKey or destination"
            );
        }

        echo "\n--- Search Test ---\n";
        echo "Search term: {$searchTerm}\n";
        echo "Results found: " . count($response->getItems()) . "\n";
    }

    public function testGetUserMapItemsEmptyResultsForUnknownUser(): void
    {
        // Use a user ID that likely has no records
        $response = $this->client->getUserMapItems(999999999, page: 1, pageSize: 10);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(0, $response->getTotalRecords());
        $this->assertEquals(0, $response->getTotalPages());
        $this->assertFalse($response->hasItems());
    }

    public function testGetUserMapItemsPageOutOfRange(): void
    {
        // First get total pages
        $response = $this->client->getUserMapItems($this->userId, page: 1, pageSize: 10);

        if ($response->getTotalPages() === 0) {
            $this->markTestSkipped('No records to test page out of range');
        }

        $this->expectException(PageNotFoundException::class);

        // Request a page way beyond the total
        $this->client->getUserMapItems($this->userId, page: 99999, pageSize: 10);
    }

    public function testGetUserMapItemsMaxPageSize(): void
    {
        $response = $this->client->getUserMapItems($this->userId, page: 1, pageSize: 200);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(200, $response->getPageSize());
        $this->assertLessThanOrEqual(200, count($response->getItems()));
    }

    public function testGetUserMapItemsMinPageSize(): void
    {
        $response = $this->client->getUserMapItems($this->userId, page: 1, pageSize: 1);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(1, $response->getPageSize());
        $this->assertLessThanOrEqual(1, count($response->getItems()));
    }

    public function testGetUserMapItemsNegativeUid(): void
    {
        // Negative UIDs are valid per existing API rules (e.g., for anonymous users)
        $response = $this->client->getUserMapItems(-1, page: 1, pageSize: 10);

        $this->assertTrue($response->isSuccess());
        $this->assertGreaterThanOrEqual(0, $response->getTotalRecords());
    }

    public function testGetUserMapItemsItemStructure(): void
    {
        $response = $this->client->getUserMapItems($this->userId, page: 1, pageSize: 1);

        if (!$response->hasItems()) {
            $this->markTestSkipped('No items available to test structure');
        }

        $item = $response->getItems()[0];

        echo "\n--- Item Structure ---\n";
        echo "MID: {$item->getMid()}\n";
        echo "UID: {$item->getUid()}\n";
        echo "tpKey: {$item->getTpKey()}\n";
        echo "Domain: {$item->getDomain()}\n";
        echo "Destination: {$item->getDestination()}\n";
        echo "Status: {$item->getStatus()}\n";
        echo "Notes: {$item->getNotes()}\n";
        echo "Created At: {$item->getCreatedAt()}\n";
        echo "Updated At: {$item->getUpdatedAt()}\n";
        echo "Short URL: {$item->getShortUrl()}\n";

        $this->assertGreaterThan(0, $item->getMid());
        $this->assertNotEmpty($item->getTpKey());
        $this->assertNotEmpty($item->getDomain());
        $this->assertNotEmpty($item->getDestination());
        $this->assertNotEmpty($item->getStatus());
        $this->assertNotEmpty($item->getCreatedAt());
        $this->assertNotEmpty($item->getUpdatedAt());
        $this->assertStringStartsWith('https://', $item->getShortUrl());
    }

    public function testGetUserMapItemsTimestampFormat(): void
    {
        $response = $this->client->getUserMapItems($this->userId, page: 1, pageSize: 1);

        if (!$response->hasItems()) {
            $this->markTestSkipped('No items available to test timestamp format');
        }

        $item = $response->getItems()[0];

        // Timestamps should be in ISO-8601 format with Z (UTC)
        $createdAt = $item->getCreatedAt();
        $updatedAt = $item->getUpdatedAt();

        // Verify timestamps can be parsed
        $this->assertNotFalse(
            strtotime($createdAt),
            "created_at should be a valid timestamp: {$createdAt}"
        );
        $this->assertNotFalse(
            strtotime($updatedAt),
            "updated_at should be a valid timestamp: {$updatedAt}"
        );
    }
}
