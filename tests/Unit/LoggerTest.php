<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SnapCapture\Logger;

/**
 * Unit tests for Logger
 */
class LoggerTest extends TestCase
{
    private string $testLogFile;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary log file for testing
        $this->testLogFile = sys_get_temp_dir() . '/test-snapcapture-' . uniqid() . '.log';
        $this->logger = new Logger($this->testLogFile, true, Logger::LEVEL_DEBUG);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test log file
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
    }

    public function testLoggerCreatesLogFile(): void
    {
        $this->assertFileExists($this->testLogFile);
    }

    public function testDebugLogging(): void
    {
        $this->logger->debug('Test debug message', ['key' => 'value']);

        $contents = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('[DEBUG]', $contents);
        $this->assertStringContainsString('Test debug message', $contents);
        $this->assertStringContainsString('"key":"value"', $contents);
    }

    public function testInfoLogging(): void
    {
        $this->logger->info('Test info message');

        $contents = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('[INFO]', $contents);
        $this->assertStringContainsString('Test info message', $contents);
    }

    public function testWarningLogging(): void
    {
        $this->logger->warning('Test warning message');

        $contents = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('[WARNING]', $contents);
        $this->assertStringContainsString('Test warning message', $contents);
    }

    public function testErrorLogging(): void
    {
        $this->logger->error('Test error message', ['error_code' => 500]);

        $contents = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('[ERROR]', $contents);
        $this->assertStringContainsString('Test error message', $contents);
        $this->assertStringContainsString('"error_code":500', $contents);
    }

    public function testLogLevelFiltering(): void
    {
        // Set log level to INFO (should skip DEBUG messages)
        $logger = new Logger($this->testLogFile, true, Logger::LEVEL_INFO);

        $logger->debug('Debug message - should not appear');
        $logger->info('Info message - should appear');

        $contents = file_get_contents($this->testLogFile);
        $this->assertStringNotContainsString('Debug message', $contents);
        $this->assertStringContainsString('Info message', $contents);
    }

    public function testLoggerCanBeDisabled(): void
    {
        $this->logger->setEnabled(false);
        $this->logger->info('This should not be logged');

        $contents = file_get_contents($this->testLogFile);
        $this->assertStringNotContainsString('This should not be logged', $contents);
    }

    public function testClearLog(): void
    {
        $this->logger->info('Message before clear');
        $this->logger->clear();

        $contents = file_get_contents($this->testLogFile);
        $this->assertEmpty($contents);
    }

    public function testTail(): void
    {
        // Write multiple lines
        for ($i = 1; $i <= 10; $i++) {
            $this->logger->info("Message $i");
        }

        // Get last 5 lines
        $lines = $this->logger->tail(5);
        $this->assertCount(5, $lines);
        $this->assertStringContainsString('Message 10', $lines[4]);
        $this->assertStringContainsString('Message 6', $lines[0]);
    }

    public function testGetLogFile(): void
    {
        $this->assertEquals($this->testLogFile, $this->logger->getLogFile());
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->logger->isEnabled());

        $this->logger->setEnabled(false);
        $this->assertFalse($this->logger->isEnabled());
    }

    public function testGetLogLevel(): void
    {
        $this->assertEquals(Logger::LEVEL_DEBUG, $this->logger->getLogLevel());
    }

    public function testSetLogLevel(): void
    {
        $this->logger->setLogLevel(Logger::LEVEL_ERROR);
        $this->assertEquals(Logger::LEVEL_ERROR, $this->logger->getLogLevel());
    }

    public function testTimestampFormat(): void
    {
        $this->logger->info('Test message');

        $contents = file_get_contents($this->testLogFile);
        // Check for timestamp format [YYYY-MM-DD HH:MM:SS]
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $contents);
    }
}
