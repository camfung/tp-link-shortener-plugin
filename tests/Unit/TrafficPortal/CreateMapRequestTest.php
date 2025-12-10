<?php

declare(strict_types=1);

namespace Tests\Unit\TrafficPortal;

use PHPUnit\Framework\TestCase;
use TrafficPortal\DTO\CreateMapRequest;

/**
 * Unit tests for CreateMapRequest DTO
 */
class CreateMapRequestTest extends TestCase
{
    public function testConstructorWithRequiredParametersOnly(): void
    {
        $request = new CreateMapRequest(
            uid: 125,
            tpKey: 'mylink',
            domain: 'dev.trfc.link',
            destination: 'https://example.com'
        );

        $this->assertEquals(125, $request->getUid());
        $this->assertEquals('mylink', $request->getTpKey());
        $this->assertEquals('dev.trfc.link', $request->getDomain());
        $this->assertEquals('https://example.com', $request->getDestination());

        // Test defaults
        $this->assertEquals('active', $request->getStatus());
        $this->assertEquals('redirect', $request->getType());
        $this->assertEquals(0, $request->getIsSet());
        $this->assertEquals('', $request->getTags());
        $this->assertEquals('', $request->getNotes());
        $this->assertEquals('{}', $request->getSettings());
        $this->assertEquals(0, $request->getCacheContent());
        $this->assertNull($request->getExpiresAt());
    }

    public function testConstructorWithAllParameters(): void
    {
        $request = new CreateMapRequest(
            uid: -1,
            tpKey: 'testlink',
            domain: 'dev.trfc.link',
            destination: 'https://example.com/page',
            status: 'intro',
            type: 'mask',
            isSet: 1,
            tags: 'test,demo',
            notes: 'Test link',
            settings: '{"dark_mode": true}',
            cacheContent: 1,
            expiresAt: '2025-12-10 00:00:00'
        );

        $this->assertEquals(-1, $request->getUid());
        $this->assertEquals('testlink', $request->getTpKey());
        $this->assertEquals('dev.trfc.link', $request->getDomain());
        $this->assertEquals('https://example.com/page', $request->getDestination());
        $this->assertEquals('intro', $request->getStatus());
        $this->assertEquals('mask', $request->getType());
        $this->assertEquals(1, $request->getIsSet());
        $this->assertEquals('test,demo', $request->getTags());
        $this->assertEquals('Test link', $request->getNotes());
        $this->assertEquals('{"dark_mode": true}', $request->getSettings());
        $this->assertEquals(1, $request->getCacheContent());
        $this->assertEquals('2025-12-10 00:00:00', $request->getExpiresAt());
    }

    public function testToArrayWithoutExpiry(): void
    {
        $request = new CreateMapRequest(
            uid: 125,
            tpKey: 'mylink',
            domain: 'dev.trfc.link',
            destination: 'https://example.com',
            status: 'active',
            type: 'redirect',
            isSet: 0,
            tags: 'tag1,tag2',
            notes: 'My notes',
            settings: '{}',
            cacheContent: 0
        );

        $array = $request->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(125, $array['uid']);
        $this->assertEquals('mylink', $array['tpKey']);
        $this->assertEquals('dev.trfc.link', $array['domain']);
        $this->assertEquals('https://example.com', $array['destination']);
        $this->assertEquals('active', $array['status']);
        $this->assertEquals('redirect', $array['type']);
        $this->assertEquals(0, $array['is_set']);
        $this->assertEquals('tag1,tag2', $array['tags']);
        $this->assertEquals('My notes', $array['notes']);
        $this->assertEquals('{}', $array['settings']);
        $this->assertEquals(0, $array['cache_content']);

        // expires_at should not be present when null
        $this->assertArrayNotHasKey('expires_at', $array);
    }

    public function testToArrayWithExpiry(): void
    {
        $expiryDate = '2025-12-10 00:00:00';
        $request = new CreateMapRequest(
            uid: -1,
            tpKey: 'templink',
            domain: 'dev.trfc.link',
            destination: 'https://example.com',
            expiresAt: $expiryDate
        );

        $array = $request->toArray();

        $this->assertArrayHasKey('expires_at', $array);
        $this->assertEquals($expiryDate, $array['expires_at']);
    }

    public function testAnonymousUserWithExpiry(): void
    {
        // Test case for anonymous user (uid=-1) with 24-hour expiry
        $request = new CreateMapRequest(
            uid: -1,
            tpKey: 'anon-link',
            domain: 'dev.trfc.link',
            destination: 'https://example.com',
            status: 'intro',
            expiresAt: '2025-12-08 03:31:29'
        );

        $this->assertEquals(-1, $request->getUid());
        $this->assertEquals('intro', $request->getStatus());
        $this->assertEquals('2025-12-08 03:31:29', $request->getExpiresAt());

        $array = $request->toArray();
        $this->assertArrayHasKey('expires_at', $array);
    }

    public function testLoggedInUserWithoutExpiry(): void
    {
        // Test case for logged-in user (never expires by default)
        $request = new CreateMapRequest(
            uid: 125,
            tpKey: 'permanent-link',
            domain: 'dev.trfc.link',
            destination: 'https://example.com'
        );

        $this->assertEquals(125, $request->getUid());
        $this->assertNull($request->getExpiresAt());

        $array = $request->toArray();
        $this->assertArrayNotHasKey('expires_at', $array);
    }
}
