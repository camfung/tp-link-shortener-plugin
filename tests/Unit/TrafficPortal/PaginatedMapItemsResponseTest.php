<?php

declare(strict_types=1);

namespace Tests\Unit\TrafficPortal;

use PHPUnit\Framework\TestCase;
use TrafficPortal\DTO\PaginatedMapItemsResponse;
use TrafficPortal\DTO\MapItem;

class PaginatedMapItemsResponseTest extends TestCase
{
    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 2,
            'page_size' => 50,
            'total_records' => 1234,
            'total_pages' => 25,
            'source' => [
                [
                    'mid' => 14205,
                    'uid' => 123,
                    'tpKey' => 'mylink',
                    'domain' => 'dev.trfc.link',
                    'destination' => 'https://example.com/landing',
                    'status' => 'active',
                    'notes' => 'My link',
                    'created_at' => '2026-01-10T12:00:00Z',
                    'updated_at' => '2026-01-20T15:30:00Z',
                    'usage' => ['total' => 150, 'qr' => 45, 'regular' => 105],
                ],
            ],
        ];

        $response = PaginatedMapItemsResponse::fromArray($data);

        $this->assertEquals('Map items retrieved', $response->getMessage());
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(2, $response->getPage());
        $this->assertEquals(50, $response->getPageSize());
        $this->assertEquals(1234, $response->getTotalRecords());
        $this->assertEquals(25, $response->getTotalPages());
        $this->assertCount(1, $response->getItems());
        $this->assertInstanceOf(MapItem::class, $response->getItems()[0]);
    }

    public function testFromArrayWithEmptySource(): void
    {
        $data = [
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 1,
            'page_size' => 50,
            'total_records' => 0,
            'total_pages' => 0,
            'source' => [],
        ];

        $response = PaginatedMapItemsResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(0, $response->getTotalRecords());
        $this->assertEquals(0, $response->getTotalPages());
        $this->assertEmpty($response->getItems());
        $this->assertFalse($response->hasItems());
    }

    public function testFromArrayWithMissingDataUsesDefaults(): void
    {
        $response = PaginatedMapItemsResponse::fromArray([]);

        $this->assertEquals('', $response->getMessage());
        $this->assertFalse($response->isSuccess());
        $this->assertEquals(1, $response->getPage());
        $this->assertEquals(50, $response->getPageSize());
        $this->assertEquals(0, $response->getTotalRecords());
        $this->assertEquals(0, $response->getTotalPages());
        $this->assertEmpty($response->getItems());
    }

    public function testHasItems(): void
    {
        $withItems = PaginatedMapItemsResponse::fromArray([
            'source' => [['mid' => 1, 'tpKey' => 'test']],
        ]);
        $withoutItems = PaginatedMapItemsResponse::fromArray([
            'source' => [],
        ]);

        $this->assertTrue($withItems->hasItems());
        $this->assertFalse($withoutItems->hasItems());
    }

    public function testHasNextPage(): void
    {
        $hasNext = PaginatedMapItemsResponse::fromArray([
            'page' => 1,
            'total_pages' => 5,
        ]);
        $lastPage = PaginatedMapItemsResponse::fromArray([
            'page' => 5,
            'total_pages' => 5,
        ]);
        $onlyPage = PaginatedMapItemsResponse::fromArray([
            'page' => 1,
            'total_pages' => 1,
        ]);

        $this->assertTrue($hasNext->hasNextPage());
        $this->assertFalse($lastPage->hasNextPage());
        $this->assertFalse($onlyPage->hasNextPage());
    }

    public function testHasPreviousPage(): void
    {
        $hasPrevious = PaginatedMapItemsResponse::fromArray([
            'page' => 3,
            'total_pages' => 5,
        ]);
        $firstPage = PaginatedMapItemsResponse::fromArray([
            'page' => 1,
            'total_pages' => 5,
        ]);

        $this->assertTrue($hasPrevious->hasPreviousPage());
        $this->assertFalse($firstPage->hasPreviousPage());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $data = [
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 2,
            'page_size' => 25,
            'total_records' => 100,
            'total_pages' => 4,
            'source' => [
                [
                    'mid' => 14205,
                    'uid' => 123,
                    'tpKey' => 'mylink',
                    'domain' => 'dev.trfc.link',
                    'destination' => 'https://example.com',
                    'status' => 'active',
                    'notes' => '',
                    'created_at' => '2026-01-10T12:00:00Z',
                    'updated_at' => '2026-01-20T15:30:00Z',
                    'usage' => ['total' => 150, 'qr' => 45, 'regular' => 105],
                ],
            ],
        ];

        $response = PaginatedMapItemsResponse::fromArray($data);
        $array = $response->toArray();

        $this->assertEquals('Map items retrieved', $array['message']);
        $this->assertTrue($array['success']);
        $this->assertEquals(2, $array['page']);
        $this->assertEquals(25, $array['page_size']);
        $this->assertEquals(100, $array['total_records']);
        $this->assertEquals(4, $array['total_pages']);
        $this->assertIsArray($array['source']);
        $this->assertCount(1, $array['source']);
        $this->assertEquals(14205, $array['source'][0]['mid']);
    }

    public function testMultipleItemsAreParsedCorrectly(): void
    {
        $data = [
            'message' => 'Map items retrieved',
            'success' => true,
            'page' => 1,
            'page_size' => 50,
            'total_records' => 3,
            'total_pages' => 1,
            'source' => [
                ['mid' => 1, 'tpKey' => 'link1', 'domain' => 'trfc.link', 'destination' => 'https://example.com/1', 'status' => 'active', 'notes' => '', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
                ['mid' => 2, 'tpKey' => 'link2', 'domain' => 'trfc.link', 'destination' => 'https://example.com/2', 'status' => 'active', 'notes' => '', 'created_at' => '2026-01-02', 'updated_at' => '2026-01-02'],
                ['mid' => 3, 'tpKey' => 'link3', 'domain' => 'trfc.link', 'destination' => 'https://example.com/3', 'status' => 'disabled', 'notes' => '', 'created_at' => '2026-01-03', 'updated_at' => '2026-01-03'],
            ],
        ];

        $response = PaginatedMapItemsResponse::fromArray($data);

        $this->assertCount(3, $response->getItems());
        $this->assertEquals('link1', $response->getItems()[0]->getTpKey());
        $this->assertEquals('link2', $response->getItems()[1]->getTpKey());
        $this->assertEquals('link3', $response->getItems()[2]->getTpKey());
        $this->assertEquals('disabled', $response->getItems()[2]->getStatus());
    }
}
