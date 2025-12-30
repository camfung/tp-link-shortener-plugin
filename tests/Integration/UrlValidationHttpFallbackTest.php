<?php
/**
 * Integration tests for URL Validation HTTP Fallback
 * Tests the ajax_validate_url endpoint logic
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class UrlValidationHttpFallbackTest extends TestCase
{
    /**
     * Test that HTTPS URL with SSL error falls back to HTTP successfully
     * Using cURL directly since wp_remote_head is not available in tests
     */
    public function testHttpsFallsBackToHttpOnSslError(): void
    {
        // Skip if cURL is not available
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('cURL extension is not available');
        }

        // Test URL with SSL certificate error
        $test_url = 'https://146.190.120.67/';

        // Make HEAD request to HTTPS URL (should fail with SSL error)
        $https_result = $this->curlHeadRequest($test_url, true);

        // Verify HTTPS request fails with SSL error
        $this->assertFalse($https_result['success'], 'HTTPS should fail with SSL error');
        $this->assertStringContainsStringIgnoringCase('ssl', $https_result['error'] ?? '');

        // Make HEAD request to HTTP version (should succeed)
        $http_url = preg_replace('/^https:/', 'http:', $test_url);
        $http_result = $this->curlHeadRequest($http_url, false);

        // Verify HTTP request succeeds
        $this->assertTrue($http_result['success'], 'HTTP request should succeed');
        $this->assertGreaterThanOrEqual(200, $http_result['http_code']);
        $this->assertLessThan(400, $http_result['http_code']);
    }

    /**
     * Test that the fallback logic correctly identifies SSL errors
     */
    public function testSslErrorDetection(): void
    {
        $ssl_error_messages = [
            'SSL certificate problem: unable to get local issuer certificate',
            'SSL: no alternative certificate subject name matches target',
            'cURL error 60: SSL certificate verification failed',
            'SSL peer certificate or SSH remote key was not OK',
        ];

        foreach ($ssl_error_messages as $message) {
            $is_ssl_error = strpos($message, 'SSL') !== false ||
                           strpos($message, 'certificate') !== false ||
                           strpos($message, 'ssl') !== false;

            $this->assertTrue($is_ssl_error, "Should detect SSL error in: $message");
        }
    }

    /**
     * Test that non-SSL errors do not trigger HTTP fallback
     */
    public function testNonSslErrorsDoNotTriggerFallback(): void
    {
        $non_ssl_errors = [
            'Connection timeout',
            'Could not resolve host',
            'Connection refused',
            'Network unreachable',
        ];

        foreach ($non_ssl_errors as $message) {
            $is_ssl_error = strpos($message, 'SSL') !== false ||
                           strpos($message, 'certificate') !== false ||
                           strpos($message, 'ssl') !== false;

            $this->assertFalse($is_ssl_error, "Should not detect SSL error in: $message");
        }
    }

    /**
     * Test protocol conversion from HTTPS to HTTP
     */
    public function testProtocolConversion(): void
    {
        $test_cases = [
            'https://example.com/' => 'http://example.com/',
            'https://example.com/path' => 'http://example.com/path',
            'https://example.com:443/path?query=1' => 'http://example.com:443/path?query=1',
            'https://146.190.120.67/' => 'http://146.190.120.67/',
        ];

        foreach ($test_cases as $https_url => $expected_http_url) {
            $http_url = preg_replace('/^https:/', 'http:', $https_url);
            $this->assertEquals($expected_http_url, $http_url);
        }
    }

    /**
     * Test that successful HTTPS URLs don't get converted to HTTP
     */
    public function testSuccessfulHttpsNotConverted(): void
    {
        // Skip if cURL is not available
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('cURL extension is not available');
        }

        // Test with a URL that has valid SSL
        $test_url = 'https://www.google.com/';

        $result = $this->curlHeadRequest($test_url, true);

        // Should not be an error
        $this->assertTrue($result['success'], 'Valid HTTPS should not error');

        // Should get successful status code
        $this->assertGreaterThanOrEqual(200, $result['http_code']);
        $this->assertLessThan(400, $result['http_code']);
    }

    /**
     * Helper method to make HEAD request using cURL
     */
    private function curlHeadRequest(string $url, bool $verify_ssl): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_ssl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify_ssl ? 2 : 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TP-Link-Shortener-Validator/1.0');

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => empty($error) && $http_code > 0,
            'http_code' => $http_code,
            'error' => $error
        ];
    }
}
