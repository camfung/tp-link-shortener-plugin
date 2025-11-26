<?php
/**
 * API Handler - Wrapper for Traffic Portal API Client
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use TrafficPortal\TrafficPortalApiClient;
use TrafficPortal\DTO\CreateMapRequest;
use TrafficPortal\Exception\AuthenticationException;
use TrafficPortal\Exception\ValidationException;
use TrafficPortal\Exception\NetworkException;
use TrafficPortal\Exception\RateLimitException;
use TrafficPortal\Exception\ApiException;
use SnapCapture\SnapCaptureClient;
use SnapCapture\DTO\ScreenshotRequest;

class TP_API_Handler {

    /**
     * API Client instance
     */
    private $client;

    /**
     * SnapCapture Client instance
     */
    private $screenshotClient;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_client();
        $this->register_ajax_handlers();
    }

    /**
     * Initialize API client
     */
    private function init_client() {
        $api_endpoint = TP_Link_Shortener::get_api_endpoint();
        $api_key = TP_Link_Shortener::get_api_key();

        if (empty($api_key)) {
            error_log('TP Link Shortener: API_KEY not defined in wp-config.php');
        }

        $this->client = new TrafficPortalApiClient(
            $api_endpoint,
            $api_key,
            30
        );

        // Initialize SnapCapture client with verbose debugging
        error_log('[SNAPCAPTURE DEBUG] Starting SnapCapture client initialization');
        error_log('[SNAPCAPTURE DEBUG] Checking for SNAPCAPTURE_API_KEY constant: ' . (defined('SNAPCAPTURE_API_KEY') ? 'DEFINED' : 'NOT DEFINED'));

        $snapcapture_key = TP_Link_Shortener::get_snapcapture_api_key();
        error_log('[SNAPCAPTURE DEBUG] Retrieved API key: ' . (empty($snapcapture_key) ? 'EMPTY' : 'Present (length: ' . strlen($snapcapture_key) . ')'));
        error_log('[SNAPCAPTURE DEBUG] API key first 10 chars: ' . (empty($snapcapture_key) ? 'N/A' : substr($snapcapture_key, 0, 10) . '...'));

        if (!empty($snapcapture_key)) {
            try {
                error_log('[SNAPCAPTURE DEBUG] Attempting to create SnapCaptureClient instance');
                $this->screenshotClient = new SnapCaptureClient($snapcapture_key);
                error_log('[SNAPCAPTURE DEBUG] SnapCaptureClient created successfully');
                error_log('[SNAPCAPTURE DEBUG] Client class: ' . get_class($this->screenshotClient));
            } catch (Exception $e) {
                error_log('[SNAPCAPTURE DEBUG] Failed to create SnapCaptureClient: ' . $e->getMessage());
                error_log('[SNAPCAPTURE DEBUG] Exception class: ' . get_class($e));
                error_log('[SNAPCAPTURE DEBUG] Stack trace: ' . $e->getTraceAsString());
                $this->screenshotClient = null;
            }
        } else {
            error_log('[SNAPCAPTURE DEBUG] API key is empty, client NOT initialized');
        }

        error_log('[SNAPCAPTURE DEBUG] Final client state: ' . ($this->screenshotClient ? 'INITIALIZED' : 'NULL'));
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // For logged-in users
        add_action('wp_ajax_tp_create_link', array($this, 'ajax_create_link'));
        add_action('wp_ajax_tp_validate_key', array($this, 'ajax_validate_key'));
        add_action('wp_ajax_tp_validate_url', array($this, 'ajax_validate_url'));
        add_action('wp_ajax_tp_capture_screenshot', array($this, 'ajax_capture_screenshot'));

        // For non-logged-in users
        add_action('wp_ajax_nopriv_tp_create_link', array($this, 'ajax_create_link'));
        add_action('wp_ajax_nopriv_tp_validate_key', array($this, 'ajax_validate_key'));
        add_action('wp_ajax_nopriv_tp_validate_url', array($this, 'ajax_validate_url'));
        add_action('wp_ajax_nopriv_tp_capture_screenshot', array($this, 'ajax_capture_screenshot'));
    }

    /**
     * AJAX handler for creating short links
     */
    public function ajax_create_link() {
        error_log('TP Link Shortener: ajax_create_link called');

        // Verify nonce
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        // Get POST data
        $destination = isset($_POST['destination']) ? sanitize_url($_POST['destination']) : '';
        $custom_key = isset($_POST['custom_key']) ? sanitize_text_field($_POST['custom_key']) : '';
        $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

        error_log('TP Link Shortener: Initial POST data - destination: ' . $destination . ', custom_key: ' . $custom_key . ', uid: ' . $uid);

        // If user is not logged in, set uid to -1
        if (!is_user_logged_in()) {
            error_log('TP Link Shortener: User not logged in, setting uid to -1');
            $uid = -1;
        } elseif ($uid <= 0) {
            $uid = TP_Link_Shortener::get_user_id();
            error_log('TP Link Shortener: User logged in, using configured uid: ' . $uid);
        } else {
            error_log('TP Link Shortener: Using provided uid: ' . $uid);
        }

        // Validate destination
        if (empty($destination) || !filter_var($destination, FILTER_VALIDATE_URL)) {
            error_log('TP Link Shortener: Invalid destination URL: ' . $destination);
            wp_send_json_error(array(
                'message' => __('Please enter a valid URL', 'tp-link-shortener')
            ));
        }

        // Check if custom key is allowed
        if (!empty($custom_key) && TP_Link_Shortener::is_premium_only()) {
            error_log('TP Link Shortener: Premium-only mode enabled, checking user status');
            // Check if user is premium
            if (!$this->is_user_premium()) {
                error_log('TP Link Shortener: User is not premium, rejecting custom key');
                wp_send_json_error(array(
                    'message' => __('Custom shortcodes are only available for premium members', 'tp-link-shortener')
                ));
            }
        }

        // Generate random key if custom key not provided
        if (empty($custom_key)) {
            $custom_key = $this->generate_random_key();
            error_log('TP Link Shortener: Generated random key: ' . $custom_key);
        } else {
            error_log('TP Link Shortener: Using custom key: ' . $custom_key);
        }

        // Create the short link
        error_log('TP Link Shortener: Creating short link - destination: ' . $destination . ', key: ' . $custom_key . ', uid: ' . $uid);
        $result = $this->create_short_link($destination, $custom_key, $uid);

        if ($result['success']) {
            error_log('TP Link Shortener: Link created successfully: ' . json_encode($result['data']));
            wp_send_json_success($result['data']);
        } else {
            error_log('TP Link Shortener: Link creation failed: ' . $result['message']);

            // Prepare error data
            $error_data = array(
                'message' => $result['message']
            );

            // Add error type if present (for rate limit errors)
            if (isset($result['error_type'])) {
                $error_data['error_type'] = $result['error_type'];
            }

            // Add HTTP code if present
            if (isset($result['http_code'])) {
                $error_data['http_code'] = $result['http_code'];
            }

            wp_send_json_error($error_data);
        }
    }

    /**
     * Create short link via API
     */
    private function create_short_link(string $destination, string $key, int $uid): array {
        try {
            error_log('TP Link Shortener: Building CreateMapRequest with uid=' . $uid . ', key=' . $key . ', domain=' . TP_Link_Shortener::get_domain());

            $request = new CreateMapRequest(
                uid: $uid,
                tpKey: $key,
                domain: TP_Link_Shortener::get_domain(),
                destination: $destination,
                status: 'active',
                type: 'redirect',
                isSet: 0,
                tags: 'wordpress,plugin',
                notes: 'Created via WordPress plugin',
                settings: '{}',
                cacheContent: 0
            );

            error_log('TP Link Shortener: Sending request to API: ' . json_encode($request->toArray()));
            $response = $this->client->createMaskedRecord($request);
            error_log('TP Link Shortener: Received API response');

            if ($response->isSuccess()) {
                $domain = TP_Link_Shortener::get_domain();
                $short_url = 'https://' . $domain . '/' . $key;

                error_log('TP Link Shortener: API response successful - mid: ' . $response->getMid());

                return array(
                    'success' => true,
                    'data' => array(
                        'short_url' => $short_url,
                        'key' => $key,
                        'domain' => $domain,
                        'destination' => $destination,
                        'mid' => $response->getMid(),
                        'message' => $response->getMessage(),
                    )
                );
            }

            error_log('TP Link Shortener: API response unsuccessful');
            return array(
                'success' => false,
                'message' => __('Failed to create link', 'tp-link-shortener')
            );

        } catch (AuthenticationException $e) {
            error_log('TP Link Shortener Auth Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Authentication failed. Please check plugin configuration.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (RateLimitException $e) {
            error_log('TP Link Shortener Rate Limit Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'rate_limit',
                'http_code' => 429,
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (ValidationException $e) {
            // Key might be taken
            if (strpos($e->getMessage(), 'invalid') !== false) {
                return array(
                    'success' => false,
                    'message' => __('This shortcode is already taken. Please try another.', 'tp-link-shortener'),
                    'debug_error' => $e->getMessage() // DEBUG: Remove in production
                );
            }

            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'debug_error' => $e->getMessage() + "test" // DEBUG: Remove in production
            );

        } catch (NetworkException $e) {
            error_log('TP Link Shortener Network Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Network error. Please try again later.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (ApiException $e) {
            error_log('TP Link Shortener API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('API error. Please try again.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (Exception $e) {
            error_log('TP Link Shortener Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An unexpected error occurred. Please try again.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );
        }
    }

    /**
     * Generate random shortcode
     */
    private function generate_random_key(int $length = 8): string {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $key = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $key .= $characters[random_int(0, $max)];
        }

        return $key;
    }

    /**
     * AJAX handler for validating stored keys
     */
    public function ajax_validate_key() {
        error_log('TP Link Shortener: ajax_validate_key called');

        // Verify nonce
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        // Get POST data
        $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $destination = isset($_POST['destination']) ? sanitize_url($_POST['destination']) : '';
        $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

        if ($uid <= 0) {
            $uid = TP_Link_Shortener::get_user_id();
        }

        error_log('TP Link Shortener: Validating key: ' . $key . ', destination: ' . $destination . ', uid: ' . $uid);

        // Validate inputs
        if (empty($key)) {
            error_log('TP Link Shortener: Key validation failed - empty key');
            wp_send_json_error(array(
                'message' => __('Key is required', 'tp-link-shortener')
            ));
        }

        // Validate the key against the API
        $result = $this->validate_key($key, $destination, $uid);

        if ($result['success']) {
            error_log('TP Link Shortener: Key validation successful: ' . json_encode($result['data']));
            wp_send_json_success($result['data']);
        } else {
            error_log('TP Link Shortener: Key validation failed: ' . $result['message']);
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }

    /**
     * Validate key against API
     */
    private function validate_key(string $key, string $destination, int $uid): array {
        try {
            $record = $this->client->getMaskedRecord($key, $uid);

            // Key not found
            if ($record === null) {
                return array(
                    'success' => true,
                    'data' => array(
                        'status' => 'unavailable',
                        'message' => __('This key is no longer available.', 'tp-link-shortener')
                    )
                );
            }

            // Extract record data
            $recordStatus = $record['data']['status'] ?? 'unknown';
            $recordDestination = $record['data']['destination'] ?? '';

            // Check if destination matches (if provided)
            $destinationMatches = empty($destination) || $recordDestination === $destination;

            // Determine status
            if ($recordStatus === 'intro') {
                return array(
                    'success' => true,
                    'data' => array(
                        'status' => 'intro',
                        'destination' => $recordDestination,
                        'destination_matches' => $destinationMatches,
                        'message' => __('Your trial key is active!', 'tp-link-shortener')
                    )
                );
            } elseif ($recordStatus === 'expired') {
                return array(
                    'success' => true,
                    'data' => array(
                        'status' => 'expired',
                        'destination' => $recordDestination,
                        'destination_matches' => $destinationMatches,
                        'message' => __('This key has expired.', 'tp-link-shortener')
                    )
                );
            } elseif ($recordStatus === 'active') {
                return array(
                    'success' => true,
                    'data' => array(
                        'status' => 'active',
                        'destination' => $recordDestination,
                        'destination_matches' => $destinationMatches,
                        'message' => __('This key is active.', 'tp-link-shortener')
                    )
                );
            } else {
                return array(
                    'success' => true,
                    'data' => array(
                        'status' => 'unavailable',
                        'message' => __('This key is not available.', 'tp-link-shortener')
                    )
                );
            }

        } catch (AuthenticationException $e) {
            error_log('TP Link Shortener Auth Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Authentication failed.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (NetworkException $e) {
            error_log('TP Link Shortener Network Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Network error. Please try again later.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (ApiException $e) {
            error_log('TP Link Shortener API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('API error. Please try again.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (Exception $e) {
            error_log('TP Link Shortener Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An unexpected error occurred.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );
        }
    }

    /**
     * Check if current user is premium
     * TODO: Implement actual premium check based on your membership system
     */
    private function is_user_premium(): bool {
        // For now, return true if user is logged in
        // You can integrate with your membership plugin here
        return is_user_logged_in();
    }

    /**
     * AJAX handler for URL validation proxy (CORS bypass)
     * This endpoint proxies HEAD requests to validate URLs
     */
    public function ajax_validate_url() {
        // Get the URL to validate
        $url = isset($_GET['url']) ? esc_url_raw($_GET['url']) : '';

        if (empty($url)) {
            wp_send_json_error(array(
                'message' => 'URL parameter is required'
            ), 400);
            return;
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array(
                'message' => 'Invalid URL format'
            ), 400);
            return;
        }

        // Only allow http and https protocols
        $parsed_url = parse_url($url);
        if (!isset($parsed_url['scheme']) || !in_array($parsed_url['scheme'], array('http', 'https'))) {
            wp_send_json_error(array(
                'message' => 'Only HTTP and HTTPS protocols are allowed'
            ), 400);
            return;
        }

        // Make HEAD request using WordPress HTTP API
        $response = wp_remote_head($url, array(
            'timeout' => 10,
            'redirection' => 0, // Don't follow redirects automatically
            'sslverify' => true,
            'user-agent' => 'TP-Link-Shortener-Validator/1.0'
        ));

        // Check for errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();

            // Return error response in format expected by URLValidator
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(array(
                'ok' => false,
                'status' => 0,
                'headers' => array(),
                'error' => $error_message
            ));
            wp_die();
        }

        // Get response data
        $status_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);

        // Convert headers to simple key-value array
        $headers_array = array();
        if (is_object($headers)) {
            $headers_array = $headers->getAll();
        } elseif (is_array($headers)) {
            $headers_array = $headers;
        }

        // Return response in format expected by URLValidator
        header('Content-Type: application/json');
        echo json_encode(array(
            'ok' => $status_code >= 200 && $status_code < 400,
            'status' => $status_code,
            'headers' => $headers_array
        ));
        wp_die();
    }

    /**
     * AJAX handler for capturing website screenshots
     */
    public function ajax_capture_screenshot() {
        error_log('[SCREENSHOT] ========== AJAX SCREENSHOT REQUEST STARTED ==========');
        error_log('[SCREENSHOT] Timestamp: ' . date('Y-m-d H:i:s'));
        error_log('[SCREENSHOT] Request URI: ' . $_SERVER['REQUEST_URI']);
        error_log('[SCREENSHOT] HTTP Method: ' . $_SERVER['REQUEST_METHOD']);

        // Collect debug info
        $debug_info = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => '1.0.0',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'api_key_defined' => defined('SNAPCAPTURE_API_KEY'),
            'api_key_length' => defined('SNAPCAPTURE_API_KEY') ? strlen(SNAPCAPTURE_API_KEY) : 0,
            'api_key_prefix' => defined('SNAPCAPTURE_API_KEY') ? substr(SNAPCAPTURE_API_KEY, 0, 10) . '...' : 'N/A',
            'client_initialized' => !is_null($this->screenshotClient),
            'client_class' => $this->screenshotClient ? get_class($this->screenshotClient) : 'null',
        );

        error_log('[SCREENSHOT] Debug Info: ' . json_encode($debug_info, JSON_PRETTY_PRINT));

        // Verify nonce
        try {
            check_ajax_referer('tp_link_shortener_nonce', 'nonce');
            error_log('[SCREENSHOT] ✓ Nonce verified successfully');
            $debug_info['nonce_verified'] = true;
        } catch (Exception $e) {
            error_log('[SCREENSHOT] ✗ Nonce verification failed: ' . $e->getMessage());
            $debug_info['nonce_verified'] = false;
            $debug_info['nonce_error'] = $e->getMessage();
            wp_send_json_error(array(
                'message' => __('Security check failed', 'tp-link-shortener'),
                'debug' => $debug_info
            ));
            return;
        }

        // Check if screenshot client is initialized
        error_log('[SCREENSHOT] Checking screenshot client initialization...');
        if (!$this->screenshotClient) {
            error_log('[SCREENSHOT] ✗ Screenshot client NOT initialized');
            error_log('[SCREENSHOT] API Key Status:');
            error_log('[SCREENSHOT]   - Constant defined: ' . (defined('SNAPCAPTURE_API_KEY') ? 'YES' : 'NO'));
            error_log('[SCREENSHOT]   - Retrieved value: ' . (TP_Link_Shortener::get_snapcapture_api_key() ? 'HAS VALUE' : 'EMPTY'));

            $debug_info['error'] = 'Screenshot client is NULL';
            $debug_info['possible_causes'] = array(
                'SNAPCAPTURE_API_KEY constant not defined in wp-config.php',
                'API key is empty or whitespace only',
                'Exception thrown during SnapCaptureClient construction',
                'Class SnapCaptureClient not found or autoload failed'
            );
            $debug_info['solution'] = 'Add define(\'SNAPCAPTURE_API_KEY\', \'your-key-here\'); to wp-config.php';

            wp_send_json_error(array(
                'message' => __('Screenshot service not configured', 'tp-link-shortener'),
                'debug' => $debug_info
            ));
            return;
        }
        error_log('[SCREENSHOT] ✓ Screenshot client IS initialized');
        error_log('[SCREENSHOT] Client class: ' . get_class($this->screenshotClient));

        // Get URL from POST data
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        error_log('[SCREENSHOT] Target URL: ' . $url);
        $debug_info['target_url'] = $url;

        if (empty($url)) {
            error_log('[SCREENSHOT] ✗ URL is empty or invalid');
            $debug_info['error'] = 'URL is required but was empty';
            wp_send_json_error(array(
                'message' => __('URL is required', 'tp-link-shortener'),
                'debug' => $debug_info
            ));
            return;
        }

        try {
            error_log('[SCREENSHOT] Creating screenshot request...');
            $debug_info['request_format'] = 'jpeg';
            $debug_info['request_quality'] = 75;
            $debug_info['request_type'] = 'desktop';

            // Create screenshot request
            $request = ScreenshotRequest::desktop($url, 'jpeg', 75);
            error_log('[SCREENSHOT] ✓ Screenshot request object created');
            error_log('[SCREENSHOT] Request class: ' . get_class($request));

            // Capture screenshot
            error_log('[SCREENSHOT] Calling SnapCapture API...');
            $api_call_start = microtime(true);
            $response = $this->screenshotClient->captureScreenshot($request);
            $api_call_duration = (microtime(true) - $api_call_start) * 1000;

            error_log('[SCREENSHOT] ✓ SnapCapture API response received');
            error_log('[SCREENSHOT] Response class: ' . get_class($response));
            error_log('[SCREENSHOT] Response cached: ' . ($response->isCached() ? 'YES' : 'NO'));
            error_log('[SCREENSHOT] Response time: ' . $response->getResponseTimeMs() . 'ms');
            error_log('[SCREENSHOT] Total call duration: ' . round($api_call_duration, 2) . 'ms');

            $debug_info['api_call_success'] = true;
            $debug_info['response_cached'] = $response->isCached();
            $debug_info['response_time_ms'] = $response->getResponseTimeMs();
            $debug_info['total_duration_ms'] = round($api_call_duration, 2);
            $debug_info['image_data_length'] = strlen($response->getBase64());

            // Return base64 encoded image
            error_log('[SCREENSHOT] ✓ Sending success response to client');
            error_log('[SCREENSHOT] ========== AJAX SCREENSHOT REQUEST COMPLETED ==========');

            wp_send_json_success(array(
                'image' => $response->getBase64(),
                'cached' => $response->isCached(),
                'response_time' => $response->getResponseTimeMs(),
                'data_uri' => $response->getDataUri(),
                'debug' => $debug_info
            ));

        } catch (\SnapCapture\Exception\AuthenticationException $e) {
            error_log('[SCREENSHOT] ✗ Authentication Error: ' . $e->getMessage());
            error_log('[SCREENSHOT] Exception class: ' . get_class($e));
            error_log('[SCREENSHOT] Stack trace: ' . $e->getTraceAsString());

            $debug_info['exception_type'] = 'AuthenticationException';
            $debug_info['exception_message'] = $e->getMessage();
            $debug_info['exception_code'] = $e->getCode();
            $debug_info['exception_file'] = $e->getFile() . ':' . $e->getLine();
            $debug_info['stack_trace'] = $e->getTraceAsString();

            wp_send_json_error(array(
                'message' => __('Screenshot authentication failed', 'tp-link-shortener'),
                'debug' => $debug_info
            ));

        } catch (\SnapCapture\Exception\NetworkException $e) {
            error_log('[SCREENSHOT] ✗ Network Error: ' . $e->getMessage());
            error_log('[SCREENSHOT] Exception class: ' . get_class($e));
            error_log('[SCREENSHOT] Stack trace: ' . $e->getTraceAsString());

            $debug_info['exception_type'] = 'NetworkException';
            $debug_info['exception_message'] = $e->getMessage();
            $debug_info['exception_code'] = $e->getCode();
            $debug_info['exception_file'] = $e->getFile() . ':' . $e->getLine();
            $debug_info['stack_trace'] = $e->getTraceAsString();

            wp_send_json_error(array(
                'message' => __('Network error while capturing screenshot', 'tp-link-shortener'),
                'debug' => $debug_info
            ));

        } catch (\SnapCapture\Exception\ApiException $e) {
            error_log('[SCREENSHOT] ✗ API Error: ' . $e->getMessage());
            error_log('[SCREENSHOT] Exception class: ' . get_class($e));
            error_log('[SCREENSHOT] Stack trace: ' . $e->getTraceAsString());

            $debug_info['exception_type'] = 'ApiException';
            $debug_info['exception_message'] = $e->getMessage();
            $debug_info['exception_code'] = $e->getCode();
            $debug_info['exception_file'] = $e->getFile() . ':' . $e->getLine();
            $debug_info['stack_trace'] = $e->getTraceAsString();

            wp_send_json_error(array(
                'message' => __('Failed to capture screenshot', 'tp-link-shortener'),
                'debug' => $debug_info
            ));

        } catch (Exception $e) {
            error_log('[SCREENSHOT] ✗ Unexpected Error: ' . $e->getMessage());
            error_log('[SCREENSHOT] Exception class: ' . get_class($e));
            error_log('[SCREENSHOT] Stack trace: ' . $e->getTraceAsString());

            $debug_info['exception_type'] = get_class($e);
            $debug_info['exception_message'] = $e->getMessage();
            $debug_info['exception_code'] = $e->getCode();
            $debug_info['exception_file'] = $e->getFile() . ':' . $e->getLine();
            $debug_info['stack_trace'] = $e->getTraceAsString();

            wp_send_json_error(array(
                'message' => __('An error occurred while capturing screenshot', 'tp-link-shortener'),
                'debug' => $debug_info
            ));
        }
    }
}
