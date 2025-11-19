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
use TrafficPortal\Exception\ApiException;

class TP_API_Handler {

    /**
     * API Client instance
     */
    private $client;

    /**
     * Log file path
     */
    private $log_file_path = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_client();
        $this->init_logging();
        $this->register_ajax_handlers();
    }

    /**
     * Initialize API client
     */
    private function init_client() {
        $api_endpoint = TP_Link_Shortener::get_api_endpoint();
        $api_key = TP_Link_Shortener::get_api_key();

        if (empty($api_key)) {
            $this->log('API_KEY not defined in wp-config.php');
        }

        $this->client = new TrafficPortalApiClient(
            $api_endpoint,
            $api_key,
            30
        );
    }

    /**
     * Initialize file logging
     */
    private function init_logging() {
        if (!function_exists('wp_upload_dir')) {
            return;
        }

        $upload_dir = wp_upload_dir();

        if (empty($upload_dir['basedir'])) {
            return;
        }

        $log_dir = trailingslashit($upload_dir['basedir']) . 'tp-link-shortener-logs';

        if (!is_dir($log_dir) && !wp_mkdir_p($log_dir)) {
            return;
        }

        $this->log_file_path = trailingslashit($log_dir) . 'tp-link-shortener.log';
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // For logged-in users
        add_action('wp_ajax_tp_create_link', array($this, 'ajax_create_link'));
        add_action('wp_ajax_tp_validate_key', array($this, 'ajax_validate_key'));
        add_action('wp_ajax_tp_validate_url', array($this, 'ajax_validate_url'));

        // For non-logged-in users
        add_action('wp_ajax_nopriv_tp_create_link', array($this, 'ajax_create_link'));
        add_action('wp_ajax_nopriv_tp_validate_key', array($this, 'ajax_validate_key'));
        add_action('wp_ajax_nopriv_tp_validate_url', array($this, 'ajax_validate_url'));
    }

    /**
     * AJAX handler for creating short links
     */
    public function ajax_create_link() {
        $this->log('ajax_create_link called');

        // Verify nonce
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        // Get POST data
        $destination = isset($_POST['destination']) ? sanitize_url($_POST['destination']) : '';
        $custom_key = isset($_POST['custom_key']) ? sanitize_text_field($_POST['custom_key']) : '';
        $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

        $this->log('Initial POST data', array(
            'destination' => $destination,
            'custom_key' => $custom_key,
            'uid' => $uid
        ));

        // If user is not logged in, set uid to -1
        if (!is_user_logged_in()) {
            $this->log('User not logged in, setting uid to -1');
            $uid = -1;
        } elseif ($uid <= 0) {
            $uid = TP_Link_Shortener::get_user_id();
            $this->log('User logged in, using configured uid: ' . $uid);
        } else {
            $this->log('Using provided uid: ' . $uid);
        }

        // Validate destination
        if (empty($destination) || !filter_var($destination, FILTER_VALIDATE_URL)) {
            $this->log('Invalid destination URL: ' . $destination);
            wp_send_json_error(array(
                'message' => __('Please enter a valid URL', 'tp-link-shortener')
            ));
        }

        // Check if custom key is allowed
        if (!empty($custom_key) && TP_Link_Shortener::is_premium_only()) {
            $this->log('Premium-only mode enabled, checking user status');
            // Check if user is premium
            if (!$this->is_user_premium()) {
                $this->log('User is not premium, rejecting custom key');
                wp_send_json_error(array(
                    'message' => __('Custom shortcodes are only available for premium members', 'tp-link-shortener')
                ));
            }
        }

        // Generate random key if custom key not provided
        if (empty($custom_key)) {
            $custom_key = $this->generate_random_key();
            $this->log('Generated random key: ' . $custom_key);
        } else {
            $this->log('Using custom key: ' . $custom_key);
        }

        // Create the short link
        $this->log('Creating short link', array(
            'destination' => $destination,
            'key' => $custom_key,
            'uid' => $uid
        ));
        $result = $this->create_short_link($destination, $custom_key, $uid);

        if ($result['success']) {
            $this->log('Link created successfully', $result['data']);
            wp_send_json_success($result['data']);
        } else {
            $this->log('Link creation failed: ' . $result['message']);
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }

    /**
     * Create short link via API
     */
    private function create_short_link(string $destination, string $key, int $uid): array {
        try {
            $this->log('Building CreateMapRequest', array(
                'uid' => $uid,
                'key' => $key,
                'domain' => TP_Link_Shortener::get_domain()
            ));

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

            $this->log('Sending request to API', $request->toArray());
            $response = $this->client->createMaskedRecord($request);
            $this->log('Received API response');

            if ($response->isSuccess()) {
                $domain = TP_Link_Shortener::get_domain();
                $short_url = 'https://' . $domain . '/' . $key;

                $this->log('API response successful', array('mid' => $response->getMid()));

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

            $this->log('API response unsuccessful');
            return array(
                'success' => false,
                'message' => __('Failed to create link', 'tp-link-shortener')
            );

        } catch (AuthenticationException $e) {
            $this->log('Auth Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Authentication failed. Please check plugin configuration.', 'tp-link-shortener'),
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
            $this->log('Network Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Network error. Please try again later.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (ApiException $e) {
            $this->log('API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('API error. Please try again.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (Exception $e) {
            $this->log('Unexpected Error: ' . $e->getMessage());
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
        $this->log('ajax_validate_key called');

        // Verify nonce
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        // Get POST data
        $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $destination = isset($_POST['destination']) ? sanitize_url($_POST['destination']) : '';
        $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

        if ($uid <= 0) {
            $uid = TP_Link_Shortener::get_user_id();
        }

        $this->log('Validating key request', array(
            'key' => $key,
            'destination' => $destination,
            'uid' => $uid
        ));

        // Validate inputs
        if (empty($key)) {
            $this->log('Key validation failed - empty key');
            wp_send_json_error(array(
                'message' => __('Key is required', 'tp-link-shortener')
            ));
        }

        // Validate the key against the API
        $result = $this->validate_key($key, $destination, $uid);

        if ($result['success']) {
            $this->log('Key validation successful', $result['data']);
            wp_send_json_success($result['data']);
        } else {
            $this->log('Key validation failed: ' . $result['message']);
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
            $this->log('Auth Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Authentication failed.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (NetworkException $e) {
            $this->log('Network Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Network error. Please try again later.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (ApiException $e) {
            $this->log('API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('API error. Please try again.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (Exception $e) {
            $this->log('Unexpected Error: ' . $e->getMessage());
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
     * Write log entries to debug log and file
     */
    private function log(string $message, array $context = array()): void {
        $context_output = '';

        if (!empty($context)) {
            $context_output = ' ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $formatted_message = 'TP Link Shortener: ' . $message . $context_output;
        error_log($formatted_message);
        $this->write_log_file($formatted_message);
    }

    /**
     * Append message to log file
     */
    private function write_log_file(string $message): void {
        if (empty($this->log_file_path)) {
            return;
        }

        $log_dir = dirname($this->log_file_path);

        if (!is_dir($log_dir) && !wp_mkdir_p($log_dir)) {
            return;
        }

        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $entry = sprintf('[%s] %s%s', $timestamp, $message, PHP_EOL);

        file_put_contents($this->log_file_path, $entry, FILE_APPEND | LOCK_EX);
    }
}
