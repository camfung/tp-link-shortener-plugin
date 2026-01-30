<?php

declare(strict_types=1);

namespace Tests\Unit\TrafficPortal;

use PHPUnit\Framework\TestCase;
use TrafficPortal\DTO\MapItem;
use TrafficPortal\DTO\MapItemUsage;

class MapItemTest extends TestCase
{
    public function testConstructorSetsAllValues(): void
    {
        $usage = new MapItemUsage(150, 45, 105);
        $item = new MapItem(
            14205,
            123,
            'mylink',
            'dev.trfc.link',
            'https://example.com/landing',
            'active',
            'My link notes',
            '2026-01-10T12:00:00Z',
            '2026-01-20T15:30:00Z',
            $usage
        );

        $this->assertEquals(14205, $item->getMid());
        $this->assertEquals(123, $item->getUid());
        $this->assertEquals('mylink', $item->getTpKey());
        $this->assertEquals('dev.trfc.link', $item->getDomain());
        $this->assertEquals('https://example.com/landing', $item->getDestination());
        $this->assertEquals('active', $item->getStatus());
        $this->assertEquals('My link notes', $item->getNotes());
        $this->assertEquals('2026-01-10T12:00:00Z', $item->getCreatedAt());
        $this->assertEquals('2026-01-20T15:30:00Z', $item->getUpdatedAt());
        $this->assertSame($usage, $item->getUsage());
    }

    public function testFromArrayWithCompleteData(): void
    {
        $data = [
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
        ];

        $item = MapItem::fromArray($data);

        $this->assertEquals(14205, $item->getMid());
        $this->assertEquals(123, $item->getUid());
        $this->assertEquals('mylink', $item->getTpKey());
        $this->assertEquals('dev.trfc.link', $item->getDomain());
        $this->assertEquals('https://example.com/landing', $item->getDestination());
        $this->assertEquals('active', $item->getStatus());
        $this->assertEquals('My link', $item->getNotes());
        $this->assertEquals('2026-01-10T12:00:00Z', $item->getCreatedAt());
        $this->assertEquals('2026-01-20T15:30:00Z', $item->getUpdatedAt());
        $this->assertInstanceOf(MapItemUsage::class, $item->getUsage());
        $this->assertEquals(150, $item->getUsage()->getTotal());
    }

    public function testFromArrayWithMissingDataUsesDefaults(): void
    {
        $item = MapItem::fromArray([]);

        $this->assertEquals(0, $item->getMid());
        $this->assertEquals(0, $item->getUid());
        $this->assertEquals('', $item->getTpKey());
        $this->assertEquals('', $item->getDomain());
        $this->assertEquals('', $item->getDestination());
        $this->assertEquals('', $item->getStatus());
        $this->assertEquals('', $item->getNotes());
        $this->assertEquals('', $item->getCreatedAt());
        $this->assertEquals('', $item->getUpdatedAt());
        $this->assertNull($item->getUsage());
    }

    public function testFromArrayWithoutUsage(): void
    {
        $data = [
            'mid' => 14205,
            'uid' => 123,
            'tpKey' => 'mylink',
            'domain' => 'dev.trfc.link',
            'destination' => 'https://example.com',
            'status' => 'active',
            'notes' => '',
            'created_at' => '2026-01-10T12:00:00Z',
            'updated_at' => '2026-01-20T15:30:00Z',
        ];

        $item = MapItem::fromArray($data);

        $this->assertNull($item->getUsage());
    }

    public function testGetShortUrl(): void
    {
        $item = MapItem::fromArray([
            'domain' => 'trfc.link',
            'tpKey' => 'myshortcode',
        ]);

        $this->assertEquals('https://trfc.link/myshortcode', $item->getShortUrl());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $usage = new MapItemUsage(100, 30, 70);
        $item = new MapItem(
            14205,
            123,
            'mylink',
            'dev.trfc.link',
            'https://example.com',
            'active',
            'My notes',
            '2026-01-10T12:00:00Z',
            '2026-01-20T15:30:00Z',
            $usage
        );

        $array = $item->toArray();

        $this->assertEquals(14205, $array['mid']);
        $this->assertEquals(123, $array['uid']);
        $this->assertEquals('mylink', $array['tpKey']);
        $this->assertEquals('dev.trfc.link', $array['domain']);
        $this->assertEquals('https://example.com', $array['destination']);
        $this->assertEquals('active', $array['status']);
        $this->assertEquals('My notes', $array['notes']);
        $this->assertEquals('2026-01-10T12:00:00Z', $array['created_at']);
        $this->assertEquals('2026-01-20T15:30:00Z', $array['updated_at']);
        $this->assertIsArray($array['usage']);
        $this->assertEquals(100, $array['usage']['total']);
    }

    public function testToArrayWithNullUsage(): void
    {
        $item = new MapItem(
            14205,
            123,
            'mylink',
            'dev.trfc.link',
            'https://example.com',
            'active',
            '',
            '2026-01-10T12:00:00Z',
            '2026-01-20T15:30:00Z',
            null
        );

        $array = $item->toArray();

        $this->assertNull($array['usage']);
    }
}
