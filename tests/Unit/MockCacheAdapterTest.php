<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SnapCapture\Cache\MockCacheAdapter;

/**
 * Unit tests for MockCacheAdapter
 *
 * Tests the mock cache adapter to ensure it behaves correctly for testing.
 */
class MockCacheAdapterTest extends TestCase
{
    private MockCacheAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new MockCacheAdapter();
    }

    public function testSetAndGet(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $result = $this->adapter->set($key, $value);
        $this->assertTrue($result);

        $retrieved = $this->adapter->get($key);
        $this->assertEquals($value, $retrieved);
    }

    public function testGetNonExistentKey(): void
    {
        $retrieved = $this->adapter->get('nonexistent');
        $this->assertNull($retrieved);
    }

    public function testHas(): void
    {
        $key = 'test_key';

        $this->assertFalse($this->adapter->has($key));

        $this->adapter->set($key, 'value');
        $this->assertTrue($this->adapter->has($key));
    }

    public function testDelete(): void
    {
        $key = 'test_key';

        $this->adapter->set($key, 'value');
        $this->assertTrue($this->adapter->has($key));

        $result = $this->adapter->delete($key);
        $this->assertTrue($result);
        $this->assertFalse($this->adapter->has($key));
    }

    public function testClear(): void
    {
        $this->adapter->set('key1', 'value1');
        $this->adapter->set('key2', 'value2');
        $this->adapter->set('key3', 'value3');

        $this->assertEquals(3, $this->adapter->size());

        $result = $this->adapter->clear();
        $this->assertTrue($result);
        $this->assertEquals(0, $this->adapter->size());
    }

    public function testExpiration(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        // Set with 1 second expiration
        $this->adapter->set($key, $value, 1);
        $this->assertTrue($this->adapter->has($key));
        $this->assertEquals($value, $this->adapter->get($key));

        // Wait for expiration
        sleep(2);

        // Should be expired now
        $this->assertFalse($this->adapter->has($key));
        $this->assertNull($this->adapter->get($key));
    }

    public function testNoExpiration(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        // Set with no expiration (0)
        $this->adapter->set($key, $value, 0);
        $this->assertTrue($this->adapter->has($key));

        // Even after some time, should still exist
        sleep(1);
        $this->assertTrue($this->adapter->has($key));
        $this->assertEquals($value, $this->adapter->get($key));
    }

    public function testGetKeys(): void
    {
        $this->adapter->set('key1', 'value1');
        $this->adapter->set('key2', 'value2');
        $this->adapter->set('key3', 'value3');

        $keys = $this->adapter->getKeys();
        $this->assertCount(3, $keys);
        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertContains('key3', $keys);
    }

    public function testSize(): void
    {
        $this->assertEquals(0, $this->adapter->size());

        $this->adapter->set('key1', 'value1');
        $this->assertEquals(1, $this->adapter->size());

        $this->adapter->set('key2', 'value2');
        $this->assertEquals(2, $this->adapter->size());

        $this->adapter->delete('key1');
        $this->assertEquals(1, $this->adapter->size());

        $this->adapter->clear();
        $this->assertEquals(0, $this->adapter->size());
    }

    public function testStoreDifferentDataTypes(): void
    {
        // Test string
        $this->adapter->set('string', 'test');
        $this->assertEquals('test', $this->adapter->get('string'));

        // Test integer
        $this->adapter->set('int', 42);
        $this->assertEquals(42, $this->adapter->get('int'));

        // Test array
        $this->adapter->set('array', ['a', 'b', 'c']);
        $this->assertEquals(['a', 'b', 'c'], $this->adapter->get('array'));

        // Test object
        $obj = new \stdClass();
        $obj->prop = 'value';
        $this->adapter->set('object', $obj);
        $retrieved = $this->adapter->get('object');
        $this->assertInstanceOf(\stdClass::class, $retrieved);
        $this->assertEquals('value', $retrieved->prop);
    }

    public function testOverwriteExistingKey(): void
    {
        $key = 'test_key';

        $this->adapter->set($key, 'value1');
        $this->assertEquals('value1', $this->adapter->get($key));

        $this->adapter->set($key, 'value2');
        $this->assertEquals('value2', $this->adapter->get($key));
    }

    public function testExpirationIsUpdatedOnSet(): void
    {
        $key = 'test_key';

        // Set with 2 second expiration
        $this->adapter->set($key, 'value1', 2);
        $this->assertTrue($this->adapter->has($key));

        // Wait 1 second
        sleep(1);

        // Update with no expiration
        $this->adapter->set($key, 'value2', 0);

        // Wait 2 more seconds (would have expired with old expiration)
        sleep(2);

        // Should still exist because we removed expiration
        $this->assertTrue($this->adapter->has($key));
        $this->assertEquals('value2', $this->adapter->get($key));
    }
}
