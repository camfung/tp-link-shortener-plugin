<?php
/**
 * Screenshot Handler - SnapCapture API Integration
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use SnapCapture\SnapCaptureClient;
use SnapCapture\DTO\ScreenshotRequest;
use SnapCapture\Exception\SnapCaptureException;

class TP_Screenshot_Handler {

    /**
     * SnapCapture client instance
     */
    private $client;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_client();
        $this->register_ajax_handlers();
    }

    /**
     * Initialize SnapCapture client
     */
    private function init_client() {
        $api_key = $this->get_api_key();

        if (empty($api_key)) {
            error_log('TP Screenshot Handler: SNAPCAPTURE_API_KEY not defined');
        }

        $this->client = new SnapCaptureClient($api_key, 30);
    }

    /**
     * Get API key from wp-config.php
     */
    private function get_api_key(): string {
        if (defined('SNAPCAPTURE_API_KEY')) {
            return SNAPCAPTURE_API_KEY;
        }
        return '';
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // For logged-in users
        add_action('wp_ajax_tp_capture_screenshot', array($this, 'ajax_capture_screenshot'));

        // For non-logged-in users
        add_action('wp_ajax_nopriv_tp_capture_screenshot', array($this, 'ajax_capture_screenshot'));
    }

    /**
     * AJAX handler for capturing screenshots
     */
    public function ajax_capture_screenshot() {
        // Verify nonce
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        // Get URL from request
        $url = isset($_POST['url']) ? sanitize_url($_POST['url']) : '';

        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array(
                'message' => __('Please provide a valid URL', 'tp-link-shortener')
            ));
        }

        try {
            // Create screenshot request
            $request = ScreenshotRequest::desktop($url);

            // Capture screenshot with JSON response (returns base64)
            $response = $this->client->captureScreenshot($request, true);

            // Return screenshot data in format expected by frontend
            $base64 = $response->getBase64();
            $dataUri = 'data:image/png;base64,' . $base64;

            wp_send_json_success(array(
                'image' => $base64,
                'data_uri' => $dataUri,
                'cached' => $response->isCached(),
                'response_time' => $response->getResponseTimeMs()
            ));

        } catch (SnapCaptureException $e) {
            error_log('TP Screenshot Handler: Screenshot capture failed - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Failed to capture screenshot. Please try again.', 'tp-link-shortener'),
                'error' => $e->getMessage()
            ));
        } catch (Exception $e) {
            error_log('TP Screenshot Handler: Unexpected error - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An unexpected error occurred.', 'tp-link-shortener')
            ));
        }
    }

    /**
     * Capture screenshot for a URL (non-AJAX method)
     *
     * @param string $url The URL to capture
     * @return array|null Screenshot data or null on failure
     */
    public function capture_screenshot(string $url): ?array {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            // Create screenshot request
            $request = ScreenshotRequest::desktop($url);

            // Capture screenshot with JSON response (returns base64)
            $response = $this->client->captureScreenshot($request, true);

            return array(
                'screenshot_base64' => $response->getBase64(),
                'cached' => $response->isCached(),
                'response_time_ms' => $response->getResponseTimeMs()
            );

        } catch (Exception $e) {
            error_log('TP Screenshot Handler: Screenshot capture failed - ' . $e->getMessage());
            return null;
        }
    }
}
