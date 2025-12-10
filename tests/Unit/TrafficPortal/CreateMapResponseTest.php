<?php

declare(strict_types=1);

namespace Tests\Unit\TrafficPortal;

use PHPUnit\Framework\TestCase;
use TrafficPortal\DTO\CreateMapResponse;

/**
 * Unit tests for CreateMapResponse DTO
 */
class CreateMapResponseTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $source = [
            'mid' => 14244,
            'tpKey' => 'testlink',
            'domain' => 'dev.trfc.link',
            'destination' => 'https://example.com',
            'status' => 'intro',
            'expires_at' => '2025-12-08 03:31:29',
        ];

        $response = new CreateMapResponse(
            message: 'Record Created',
            success: true,
            source: $source
        );

        $this->assertEquals('Record Created', $response->getMessage());
        $this->assertTrue($response->isSuccess());
        $this->assertEquals($source, $response->getSource());
    }

    public function testConstructorWithNullSource(): void
    {
        $response = new CreateMapResponse(
            message: 'Error occurred',
            success: false,
            source: null
        );

        $this->assertEquals('Error occurred', $response->getMessage());
        $this->assertFalse($response->isSuccess());
        $this->assertNull($response->getSource());
    }

    public function testFromArraySuccess(): void
    {
        $data = [
            'message' => 'Record Created',
            'success' => true,
            'source' => [
                'mid' => 14244,
                'tpKey' => 'mylink',
                'domain' => 'dev.trfc.link',
                'destination' => 'https://example.com',
                'status' => 'active',
                'expires_at' => '2025-12-10 00:00:00',
            ],
        ];

        $response = CreateMapResponse::fromArray($data);

        $this->assertInstanceOf(CreateMapResponse::class, $response);
        $this->assertEquals('Record Created', $response->getMessage());
        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->getSource());
    }

    public function testFromArrayWithMissingFields(): void
    {
        $data = [];
        $response = CreateMapResponse::fromArray($data);

        $this->assertEquals('', $response->getMessage());
        $this->assertFalse($response->isSuccess());
        $this->assertNull($response->getSource());
    }

    public function testGetMid(): void
    {
        $source = [
            'mid' => 12345,
            'tpKey' => 'test',
        ];

        $response = new CreateMapResponse('Success', true, $source);

        $this->assertEquals(12345, $response->getMid());
    }

    public function testGetMidWhenNull(): void
    {
        $response = new CreateMapResponse('Success', true, null);
        $this->assertNull($response->getMid());
    }

    public function testGetMidWhenMissing(): void
    {
        $source = ['tpKey' => 'test'];
        $response = new CreateMapResponse('Success', true, $source);
        $this->assertNull($response->getMid());
    }

    public function testGetTpKey(): void
    {
        $source = [
            'mid' => 14244,
            'tpKey' => 'mytestlink',
        ];

        $response = new CreateMapResponse('Success', true, $source);
        $this->assertEquals('mytestlink', $response->getTpKey());
    }

    public function testGetDomain(): void
    {
        $source = [
            'mid' => 14244,
            'domain' => 'dev.trfc.link',
        ];

        $response = new CreateMapResponse('Success', true, $source);
        $this->assertEquals('dev.trfc.link', $response->getDomain());
    }

    public function testGetDestination(): void
    {
        $source = [
            'mid' => 14244,
            'destination' => 'https://example.com/page',
        ];

        $response = new CreateMapResponse('Success', true, $source);
        $this->assertEquals('https://example.com/page', $response->getDestination());
    }

    public function testGetExpiresAt(): void
    {
        $expiryDate = '2025-12-08 03:31:29';
        $source = [
            'mid' => 14244,
            'expires_at' => $expiryDate,
        ];

        $response = new CreateMapResponse('Success', true, $source);
        $this->assertEquals($expiryDate, $response->getExpiresAt());
    }

    public function testGetExpiresAtWhenNull(): void
    {
        $source = [
            'mid' => 14244,
            'tpKey' => 'permanent-link',
        ];

        $response = new CreateMapResponse('Success', true, $source);
        $this->assertNull($response->getExpiresAt());
    }

    public function testToArray(): void
    {
        $source = [
            'mid' => 14244,
            'tpKey' => 'testlink',
            'expires_at' => '2025-12-10 00:00:00',
        ];

        $response = new CreateMapResponse('Record Created', true, $source);
        $array = $response->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Record Created', $array['message']);
        $this->assertTrue($array['success']);
        $this->assertEquals($source, $array['source']);
    }

    public function testSuccessfulResponseWithExpiry(): void
    {
        $data = [
            'message' => 'Record Created',
            'success' => true,
            'source' => [
                'mid' => 14244,
                'tpKey' => 'anon-link',
                'domain' => 'dev.trfc.link',
                'destination' => 'https://example.com',
                'status' => 'intro',
                'expires_at' => '2025-12-08 03:31:29',
                'created_by_ip' => '192.168.1.100',
                'updated_at' => '2025-12-07 03:31:29',
            ],
        ];

        $response = CreateMapResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(14244, $response->getMid());
        $this->assertEquals('anon-link', $response->getTpKey());
        $this->assertEquals('dev.trfc.link', $response->getDomain());
        $this->assertEquals('https://example.com', $response->getDestination());
        $this->assertEquals('2025-12-08 03:31:29', $response->getExpiresAt());
    }

    public function testSuccessfulResponseWithoutExpiry(): void
    {
        $data = [
            'message' => 'Record Created',
            'success' => true,
            'source' => [
                'mid' => 14245,
                'tpKey' => 'permanent-link',
                'domain' => 'dev.trfc.link',
                'destination' => 'https://example.com',
                'status' => 'active',
            ],
        ];

        $response = CreateMapResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(14245, $response->getMid());
        $this->assertNull($response->getExpiresAt());
    }
}
