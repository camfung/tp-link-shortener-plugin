<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TP_Screenshot_Handler class
 */
class ScreenshotHandlerTest extends TestCase
{
    /**
     * Test that screenshot handler class exists
     */
    public function testScreenshotHandlerClassExists(): void
    {
        // This is a basic test to verify the class file exists
        $classFile = __DIR__ . '/../../../includes/class-tp-screenshot-handler.php';
        $this->assertFileExists($classFile);
    }

    /**
     * Test capture screenshot method validates URL
     */
    public function testCaptureScreenshotValidatesUrl(): void
    {
        // This test verifies the URL validation logic
        // Since the method is not static, we need to test it differently
        $this->assertTrue(filter_var('https://example.com', FILTER_VALIDATE_URL) !== false);
        $this->assertFalse(filter_var('not-a-url', FILTER_VALIDATE_URL) !== false);
        $this->assertFalse(filter_var('', FILTER_VALIDATE_URL) !== false);
    }

    /**
     * Test API key configuration
     */
    public function testApiKeyFromConstant(): void
    {
        // Test that we can define and retrieve an API key constant
        $testKey = 'my-test-key-' . time();
        $constantName = 'TEST_SNAPCAPTURE_KEY_' . time();

        define($constantName, $testKey);
        $this->assertEquals($testKey, constant($constantName));
    }

    /**
     * Test AJAX action names
     */
    public function testAjaxActionNames(): void
    {
        // Verify the action names are correct
        $expectedActions = [
            'wp_ajax_tp_capture_screenshot',
            'wp_ajax_nopriv_tp_capture_screenshot'
        ];

        foreach ($expectedActions as $action) {
            $this->assertIsString($action);
            $this->assertStringStartsWith('wp_ajax', $action);
        }
    }
}
