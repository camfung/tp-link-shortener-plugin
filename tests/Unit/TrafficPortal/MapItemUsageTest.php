<?php

declare(strict_types=1);

namespace Tests\Unit\TrafficPortal;

use PHPUnit\Framework\TestCase;
use TrafficPortal\DTO\MapItemUsage;

class MapItemUsageTest extends TestCase
{
    public function testConstructorSetsValues(): void
    {
        $usage = new MapItemUsage(150, 45, 105);

        $this->assertEquals(150, $usage->getTotal());
        $this->assertEquals(45, $usage->getQr());
        $this->assertEquals(105, $usage->getRegular());
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'total' => 200,
            'qr' => 80,
            'regular' => 120,
        ];

        $usage = MapItemUsage::fromArray($data);

        $this->assertEquals(200, $usage->getTotal());
        $this->assertEquals(80, $usage->getQr());
        $this->assertEquals(120, $usage->getRegular());
    }

    public function testFromArrayWithNullDataDefaultsToZeros(): void
    {
        $usage = MapItemUsage::fromArray(null);

        $this->assertEquals(0, $usage->getTotal());
        $this->assertEquals(0, $usage->getQr());
        $this->assertEquals(0, $usage->getRegular());
    }

    public function testFromArrayWithPartialDataDefaultsMissing(): void
    {
        $data = ['total' => 50];

        $usage = MapItemUsage::fromArray($data);

        $this->assertEquals(50, $usage->getTotal());
        $this->assertEquals(0, $usage->getQr());
        $this->assertEquals(0, $usage->getRegular());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $usage = new MapItemUsage(100, 30, 70);

        $array = $usage->toArray();

        $this->assertEquals([
            'total' => 100,
            'qr' => 30,
            'regular' => 70,
        ], $array);
    }
}
