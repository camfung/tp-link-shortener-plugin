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
use TrafficPortal\DTO\PaginatedMapItemsResponse;
use TrafficPortal\Exception\AuthenticationException;
use TrafficPortal\Exception\ValidationException;
use TrafficPortal\Exception\NetworkException;
use TrafficPortal\Exception\RateLimitException;
use TrafficPortal\Exception\PageNotFoundException;
use TrafficPortal\Exception\ApiException;
use SnapCapture\SnapCaptureClient;
use SnapCapture\DTO\ScreenshotRequest;
use ShortCode\GenerateShortCodeClient;
use ShortCode\DTO\GenerateShortCodeRequest;
use ShortCode\GenerationTier;
use ShortCode\Exception\ApiException as ShortCodeApiException;
use ShortCode\Exception\ValidationException as ShortCodeValidationException;
use ShortCode\Exception\NetworkException as ShortCodeNetworkException;

class TP_API_Handler {

    /**
     * API Client instance
     */
    private $client;

    /**
     * SnapCapture Client instance
     */
    private $snapcapture_client;

    /**
     * AI Short Code Client instance
     */
    private $shortcode_client;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_client();
        $this->register_ajax_handlers();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
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

        // Initialize AI shortcode client
        $this->init_shortcode_client();

        // Initialize SnapCapture client
        $this->init_snapcapture_client();
    }

    /**
     * Initialize Gemini-powered short code client
     */
    private function init_shortcode_client() {
        $this->shortcode_client = new GenerateShortCodeClient();
    }

    /**
     * Initialize SnapCapture API client
     */
    private function init_snapcapture_client() {
        $snapcapture_api_key = '';

        // Priority 1: WordPress constant (recommended)
        if (defined('SNAPCAPTURE_API_KEY')) {
            $snapcapture_api_key = SNAPCAPTURE_API_KEY;
            error_log('TP Link Shortener: Using SNAPCAPTURE_API_KEY from WordPress constant');
        }
        // Priority 2: Environment variable
        elseif (getenv('SNAPCAPTURE_API_KEY')) {
            $snapcapture_api_key = getenv('SNAPCAPTURE_API_KEY');
            error_log('TP Link Shortener: Using SNAPCAPTURE_API_KEY from environment variable');
        }
        // Priority 3: .env.snapcapture file (fallback for development)
        else {
            $env_file = TP_LINK_SHORTENER_PLUGIN_DIR . '.env.snapcapture';
            if (file_exists($env_file)) {
                $env = parse_ini_file($env_file);
                if (isset($env['SNAPCAPTURE_API_KEY'])) {
                    $snapcapture_api_key = $env['SNAPCAPTURE_API_KEY'];
                    error_log('TP Link Shortener: Using SNAPCAPTURE_API_KEY from .env.snapcapture file');
                }
            }
        }

        if (empty($snapcapture_api_key)) {
            error_log('TP Link Shortener: SNAPCAPTURE_API_KEY not configured. Add it to wp-config.php: define(\'SNAPCAPTURE_API_KEY\', \'your-api-key\');');
            return;
        }

        // Initialize logger
        $log_file = TP_LINK_SHORTENER_PLUGIN_DIR . 'logs/snapcapture.log';
        $logger = new \SnapCapture\Logger($log_file, true, \SnapCapture\Logger::LEVEL_DEBUG);

        $this->snapcapture_client = new SnapCaptureClient($snapcapture_api_key, null, 30, $logger);
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
        add_action('wp_ajax_tp_search_by_fingerprint', array($this, 'ajax_search_by_fingerprint'));
        add_action('wp_ajax_tp_update_link', array($this, 'ajax_update_link'));
        add_action('wp_ajax_tp_suggest_shortcode', array($this, 'ajax_suggest_shortcode'));
        add_action('wp_ajax_tp_suggest_shortcode_fast', array($this, 'ajax_suggest_shortcode_fast'));
        add_action('wp_ajax_tp_suggest_shortcode_smart', array($this, 'ajax_suggest_shortcode_smart'));
        add_action('wp_ajax_tp_suggest_shortcode_ai', array($this, 'ajax_suggest_shortcode_ai'));

        // Paginated Map Items - logged-in users only
        add_action('wp_ajax_tp_get_user_map_items', array($this, 'ajax_get_user_map_items'));

        // Toggle link status (enable/disable) - logged-in users only
        add_action('wp_ajax_tp_toggle_link_status', array($this, 'ajax_toggle_link_status'));

        // Link change history - logged-in users only
        add_action('wp_ajax_tp_get_link_history', array($this, 'ajax_get_link_history'));

        // Client links endpoints for non-logged-in users (return 401)
        add_action('wp_ajax_nopriv_tp_get_user_map_items', array($this, 'ajax_require_login'));
        add_action('wp_ajax_nopriv_tp_toggle_link_status', array($this, 'ajax_require_login'));
        add_action('wp_ajax_nopriv_tp_get_link_history', array($this, 'ajax_require_login'));

        // For non-logged-in users
        add_action('wp_ajax_nopriv_tp_create_link', array($this, 'ajax_create_link'));
        add_action('wp_ajax_nopriv_tp_validate_key', array($this, 'ajax_validate_key'));
        add_action('wp_ajax_nopriv_tp_validate_url', array($this, 'ajax_validate_url'));
        add_action('wp_ajax_nopriv_tp_capture_screenshot', array($this, 'ajax_capture_screenshot'));
        add_action('wp_ajax_nopriv_tp_search_by_fingerprint', array($this, 'ajax_search_by_fingerprint'));
        add_action('wp_ajax_nopriv_tp_update_link', array($this, 'ajax_update_link'));
        add_action('wp_ajax_nopriv_tp_suggest_shortcode', array($this, 'ajax_suggest_shortcode'));
        add_action('wp_ajax_nopriv_tp_suggest_shortcode_fast', array($this, 'ajax_suggest_shortcode_fast'));
        add_action('wp_ajax_nopriv_tp_suggest_shortcode_smart', array($this, 'ajax_suggest_shortcode_smart'));
        add_action('wp_ajax_nopriv_tp_suggest_shortcode_ai', array($this, 'ajax_suggest_shortcode_ai'));
    }

    /**
     * AJAX handler for creating short links
     */
    public function ajax_create_link() {
        // Log incoming request for debugging
        $this->log_to_file('=== CREATE LINK REQUEST START ===');
        $this->log_to_file('Request received: ' . json_encode($_POST));
        error_log('TP Link Shortener: ajax_create_link called');

        // Verify nonce
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        // Get POST data
        $destination = isset($_POST['destination']) ? sanitize_url($_POST['destination']) : '';
        $custom_key = isset($_POST['custom_key']) ? sanitize_text_field($_POST['custom_key']) : '';
        $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;
        $fingerprint = isset($_POST['fingerprint']) ? sanitize_text_field($_POST['fingerprint']) : null;

        $this->log_to_file('Initial POST data - destination: ' . $destination . ', custom_key: ' . $custom_key . ', uid: ' . $uid . ', fingerprint: ' . ($fingerprint ?: 'null'));
        error_log('TP Link Shortener: Initial POST data - destination: ' . $destination . ', custom_key: ' . $custom_key . ', uid: ' . $uid . ', fingerprint: ' . ($fingerprint ?: 'null'));

        // If user is not logged in, set uid to -1
        if (!is_user_logged_in()) {
            $this->log_to_file('User not logged in, setting uid to -1');
            error_log('TP Link Shortener: User not logged in, setting uid to -1');
            $uid = -1;
        } elseif ($uid <= 0) {
            $uid = TP_Link_Shortener::get_user_id();
            $this->log_to_file('User logged in, using configured uid: ' . $uid);
            error_log('TP Link Shortener: User logged in, using configured uid: ' . $uid);
        } else {
            $this->log_to_file('Using provided uid: ' . $uid);
            error_log('TP Link Shortener: Using provided uid: ' . $uid);
        }

        // Validate destination
        if (empty($destination) || !filter_var($destination, FILTER_VALIDATE_URL)) {
            $this->log_to_file('VALIDATION ERROR - Invalid destination URL: ' . $destination);
            $this->log_to_file('=== CREATE LINK REQUEST END ===');
            error_log('TP Link Shortener: Invalid destination URL: ' . $destination);
            wp_send_json_error(array(
                'message' => __('Please enter a valid URL', 'tp-link-shortener')
            ));
        }

        // Check if custom key is allowed
        if (!empty($custom_key) && TP_Link_Shortener::is_premium_only()) {
            $this->log_to_file('Premium-only mode enabled, checking user status');
            error_log('TP Link Shortener: Premium-only mode enabled, checking user status');
            // Check if user is premium
            if (!$this->is_user_premium()) {
                $this->log_to_file('PERMISSION ERROR - User is not premium, rejecting custom key');
                $this->log_to_file('=== CREATE LINK REQUEST END ===');
                error_log('TP Link Shortener: User is not premium, rejecting custom key');
                wp_send_json_error(array(
                    'message' => __('Custom shortcodes are only available for premium members', 'tp-link-shortener')
                ));
            }
        }

        // Generate random key if custom key not provided
        if (empty($custom_key)) {
            $custom_key = $this->generate_short_code($destination);
            $this->log_to_file('Generated key: ' . $custom_key);
            error_log('TP Link Shortener: Generated key: ' . $custom_key);
        } else {
            $this->log_to_file('Using custom key: ' . $custom_key);
            error_log('TP Link Shortener: Using custom key: ' . $custom_key);
        }

        // Handle missing fingerprint for anonymous users (fingerprinting script blocked/failed)
        if ($uid === -1 && (empty($fingerprint) || strtolower((string) $fingerprint) === 'null')) {
            $this->log_to_file('Fingerprint missing for anonymous request - blocking and prompting user to retry');
            error_log('TP Link Shortener: Fingerprint missing for anonymous request');
            wp_send_json_error(array(
                'message' => __('Unable to create link. Please refresh the page and allow fingerprinting (disable ad/script blockers).', 'tp-link-shortener')
            ));
        }

        // Create the short link
        $this->log_to_file('Creating short link - destination: ' . $destination . ', key: ' . $custom_key . ', uid: ' . $uid . ', fingerprint: ' . ($fingerprint ?: 'null'));
        error_log('TP Link Shortener: Creating short link - destination: ' . $destination . ', key: ' . $custom_key . ', uid: ' . $uid . ', fingerprint: ' . ($fingerprint ?: 'null'));
        $result = $this->create_short_link($destination, $custom_key, $uid, $fingerprint);

        if ($result['success']) {
            $this->log_to_file('SUCCESS - Link created successfully: ' . json_encode($result['data']));
            $this->log_to_file('=== CREATE LINK REQUEST END ===');
            error_log('TP Link Shortener: Link created successfully: ' . json_encode($result['data']));

            // Log history
            $created_mid = isset($result['data']['mid']) ? intval($result['data']['mid']) : 0;
            if ($created_mid) {
                $this->log_link_history($created_mid, $uid, 'created', json_encode(array(
                    'destination' => $destination,
                    'tpKey' => $custom_key,
                )));
            }

            wp_send_json_success($result['data']);
        } else {
            $this->log_to_file('FAILURE - Link creation failed: ' . $result['message']);
            $this->log_to_file('=== CREATE LINK REQUEST END ===');
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
    private function create_short_link(string $destination, string $key, int $uid, ?string $fingerprint = null): array {
        try {
            $this->log_to_file('Building CreateMapRequest with uid=' . $uid . ', key=' . $key . ', domain=' . TP_Link_Shortener::get_domain() . ', fingerprint=' . ($fingerprint ?: 'null'));
            error_log('TP Link Shortener: Building CreateMapRequest with uid=' . $uid . ', key=' . $key . ', domain=' . TP_Link_Shortener::get_domain() . ', fingerprint=' . ($fingerprint ?: 'null'));

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
                cacheContent: 0,
                expiresAt: null,
                fingerprint: $fingerprint
            );

            $this->log_to_file('Sending request to API: ' . json_encode($request->toArray()));
            error_log('TP Link Shortener: Sending request to API: ' . json_encode($request->toArray()));
            $response = $this->client->createMaskedRecord($request);
            $this->log_to_file('Received API response');
            error_log('TP Link Shortener: Received API response');

            if ($response->isSuccess()) {
                $domain = TP_Link_Shortener::get_domain();
                $short_url = 'https://' . $domain . '/' . $key;

                $this->log_to_file('API response successful - mid: ' . $response->getMid());
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
                        'expires_at' => $response->getExpiresAt(),
                    )
                );
            }

            $this->log_to_file('API response unsuccessful');
            error_log('TP Link Shortener: API response unsuccessful');
            return array(
                'success' => false,
                'message' => __('Failed to create link', 'tp-link-shortener')
            );

        } catch (AuthenticationException $e) {
            $this->log_to_file('EXCEPTION - AuthenticationException: ' . $e->getMessage());
            error_log('TP Link Shortener Auth Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Authentication failed. Please check plugin configuration.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (RateLimitException $e) {
            $this->log_to_file('EXCEPTION - RateLimitException: ' . $e->getMessage());
            error_log('TP Link Shortener Rate Limit Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'rate_limit',
                'http_code' => 429,
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (ValidationException $e) {
            $this->log_to_file('EXCEPTION - ValidationException: ' . $e->getMessage());
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
            $this->log_to_file('EXCEPTION - NetworkException: ' . $e->getMessage());
            error_log('TP Link Shortener Network Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Network error. Please try again later.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (ApiException $e) {
            $this->log_to_file('EXCEPTION - ApiException: ' . $e->getMessage());
            error_log('TP Link Shortener API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('API error. Please try again.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage() // DEBUG: Remove in production
            );

        } catch (Exception $e) {
            $this->log_to_file('EXCEPTION - ' . get_class($e) . ': ' . $e->getMessage());
            $this->log_to_file('Trace: ' . $e->getTraceAsString());
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
     * Generate shortcode using Gemini API (when enabled) with random fallback
     */
    private function generate_short_code(string $destination, GenerationTier $tier = null): string {
        $tier = $tier ?? GenerationTier::AI;

        if (TP_Link_Shortener::use_gemini_generation() && $this->shortcode_client instanceof GenerateShortCodeClient) {
            try {
                $request = new GenerateShortCodeRequest($destination, TP_Link_Shortener::get_domain());
                $response = $this->shortcode_client->generateShortCode($request, $tier);
                $shortcode = trim($response->getShortCode());

                if (!empty($shortcode)) {
                    error_log('TP Link Shortener: Gemini generated shortcode (' . $tier->value . '): ' . $shortcode);
                    return $shortcode;
                }
            } catch (ShortCodeValidationException $e) {
                error_log('TP Link Shortener: Gemini validation error (' . $tier->value . '), falling back to random key - ' . $e->getMessage());
            } catch (ShortCodeNetworkException $e) {
                error_log('TP Link Shortener: Gemini network error (' . $tier->value . '), falling back to random key - ' . $e->getMessage());
            } catch (ShortCodeApiException $e) {
                error_log('TP Link Shortener: Gemini API error (' . $tier->value . '), falling back to random key - ' . $e->getMessage());
            } catch (\Exception $e) {
                error_log('TP Link Shortener: Unexpected Gemini error (' . $tier->value . '), falling back to random key - ' . $e->getMessage());
            }
        }

        return $this->generate_random_key();
    }

    /**
     * Generate shortcode and return metadata (candidates, method, etc.)
     */
    private function generate_short_code_result(string $destination, GenerationTier $tier): array {
        $result = array(
            'shortcode' => '',
            'candidates' => array(),
            'method' => '',
            'was_modified' => false,
            'original_code' => null,
            'url' => null,
        );

        if (TP_Link_Shortener::use_gemini_generation() && $this->shortcode_client instanceof GenerateShortCodeClient) {
            try {
                $request = new GenerateShortCodeRequest($destination, TP_Link_Shortener::get_domain());
                $response = $this->shortcode_client->generateShortCode($request, $tier);

                $result['shortcode'] = trim($response->getShortCode());
                $result['candidates'] = $response->getCandidates();
                $result['method'] = $response->getMethod()->value;
                $result['was_modified'] = $response->wasModified();
                $result['original_code'] = $response->getOriginalCode();
                $result['url'] = $response->getUrl();

                return $result;
            } catch (ShortCodeValidationException $e) {
                error_log('TP Link Shortener: Gemini validation error (' . $tier->value . '), falling back to random key - ' . $e->getMessage());
            } catch (ShortCodeNetworkException $e) {
                error_log('TP Link Shortener: Gemini network error (' . $tier->value . '), falling back to random key - ' . $e->getMessage());
            } catch (ShortCodeApiException $e) {
                error_log('TP Link Shortener: Gemini API error (' . $tier->value . '), falling back to random key - ' . $e->getMessage());
            } catch (\Exception $e) {
                error_log('TP Link Shortener: Unexpected Gemini error (' . $tier->value . '), falling back to random key - ' . $e->getMessage());
            }
        }

        // Fallback to random key if Gemini not enabled or failed
        $random = $this->generate_random_key();
        $result['shortcode'] = $random;
        $result['candidates'] = array($random);
        $result['method'] = 'random-fallback';
        $result['was_modified'] = false;
        $result['original_code'] = $random;
        $result['url'] = $destination;

        return $result;
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
     * Implements automatic HTTP fallback for HTTPS URLs with SSL errors
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

        // Check for errors - if HTTPS fails with SSL error, try HTTP fallback
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $is_https = $parsed_url['scheme'] === 'https';

            // Check if this is an SSL-related error
            $is_ssl_error = strpos($error_message, 'SSL') !== false ||
                           strpos($error_message, 'certificate') !== false ||
                           strpos($error_message, 'ssl') !== false;

            // If HTTPS failed with SSL error, try HTTP fallback
            if ($is_https && $is_ssl_error) {
                error_log('TP Link Shortener: HTTPS failed with SSL error, trying HTTP fallback for: ' . $url);

                // Convert to HTTP
                $http_url = preg_replace('/^https:/', 'http:', $url);

                // Try HTTP request
                $http_response = wp_remote_head($http_url, array(
                    'timeout' => 10,
                    'redirection' => 0,
                    'user-agent' => 'TP-Link-Shortener-Validator/1.0'
                ));

                // If HTTP succeeds, return success with protocol update flag
                if (!is_wp_error($http_response)) {
                    $http_status_code = wp_remote_retrieve_response_code($http_response);

                    // Only use HTTP fallback if we get a successful response (2xx or 3xx)
                    if ($http_status_code >= 200 && $http_status_code < 400) {
                        $http_headers = wp_remote_retrieve_headers($http_response);

                        // Convert headers to simple key-value array
                        $headers_array = array();
                        if (is_object($http_headers)) {
                            $headers_array = $http_headers->getAll();
                        } elseif (is_array($http_headers)) {
                            $headers_array = $http_headers;
                        }

                        error_log('TP Link Shortener: HTTP fallback successful, returning updated URL');

                        // Return response with protocol update flag
                        header('Content-Type: application/json');
                        echo json_encode(array(
                            'ok' => true,
                            'status' => $http_status_code,
                            'headers' => $headers_array,
                            'protocol_updated' => true,
                            'updated_url' => $http_url,
                            'original_url' => $url,
                            'reason' => 'HTTPS failed with SSL error, HTTP works'
                        ));
                        wp_die();
                    }
                }

                error_log('TP Link Shortener: HTTP fallback also failed for: ' . $url);
            }

            // Return original error response if no fallback worked
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
     * AJAX handler for capturing screenshots
     */
    public function ajax_capture_screenshot() {
        $start_time = microtime(true);
        error_log('TP Link Shortener: ajax_capture_screenshot called');

        // Verify nonce
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        // Get POST data
        $url = isset($_POST['url']) ? sanitize_url($_POST['url']) : '';

        error_log('TP Link Shortener: Capturing screenshot for URL: ' . $url);
        error_log('TP Link Shortener: Request details - IP: ' . $_SERVER['REMOTE_ADDR'] . ', User Agent: ' . $_SERVER['HTTP_USER_AGENT']);

        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            error_log('TP Link Shortener: Invalid URL for screenshot: ' . $url);
            wp_send_json_error(array(
                'message' => __('Please enter a valid URL', 'tp-link-shortener')
            ));
        }

        // Check if SnapCapture client is initialized
        if (!isset($this->snapcapture_client)) {
            error_log('TP Link Shortener: SnapCapture client not initialized');
            error_log('TP Link Shortener: Check that SNAPCAPTURE_API_KEY is configured');
            wp_send_json_error(array(
                'message' => __('Screenshot service not configured. Please contact administrator.', 'tp-link-shortener')
            ));
        }

        error_log('TP Link Shortener: Starting screenshot capture process...');

        // Capture screenshot
        $result = $this->capture_screenshot($url);

        $elapsed_time = round((microtime(true) - $start_time) * 1000, 2);
        error_log('TP Link Shortener: Total request time: ' . $elapsed_time . 'ms');

        if ($result['success']) {
            error_log('TP Link Shortener: Screenshot captured successfully');
            error_log('TP Link Shortener: Data URI length: ' . strlen($result['data']['data_uri'] ?? ''));
            wp_send_json_success($result['data']);
        } else {
            error_log('TP Link Shortener: Screenshot capture failed: ' . $result['message']);
            error_log('TP Link Shortener: Error details: ' . json_encode($result));
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }

    /**
     * Capture screenshot of URL
     */
    private function capture_screenshot(string $url): array {
        try {
            error_log('TP Link Shortener: Creating screenshot request for: ' . $url);

            // Create desktop screenshot request
            $request = ScreenshotRequest::desktop($url);

            // Capture screenshot (returns base64 by default for easier transmission)
            $response = $this->snapcapture_client->captureScreenshot($request, true);

            error_log('TP Link Shortener: Screenshot captured successfully');

            return array(
                'success' => true,
                'data' => array(
                    'screenshot_base64' => $response->getBase64(),
                    'data_uri' => $response->getDataUri(),
                    'content_type' => $response->getContentType(),
                    'cached' => $response->isCached(),
                    'response_time_ms' => $response->getResponseTimeMs(),
                    'url' => $url
                )
            );

        } catch (\SnapCapture\Exception\AuthenticationException $e) {
            error_log('TP Link Shortener SnapCapture Auth Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Screenshot authentication failed. Please check configuration.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage()
            );

        } catch (\SnapCapture\Exception\ValidationException $e) {
            error_log('TP Link Shortener SnapCapture Validation Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Invalid URL for screenshot capture.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage()
            );

        } catch (\SnapCapture\Exception\NetworkException $e) {
            error_log('TP Link Shortener SnapCapture Network Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Network error while capturing screenshot. Please try again.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage()
            );

        } catch (\SnapCapture\Exception\ApiException $e) {
            error_log('TP Link Shortener SnapCapture API Error: ' . $e->getMessage());

            // Check for rate limit
            if (strpos($e->getMessage(), '429') !== false || strpos($e->getMessage(), 'rate limit') !== false) {
                return array(
                    'success' => false,
                    'message' => __('Screenshot rate limit exceeded. Please try again later.', 'tp-link-shortener'),
                    'debug_error' => $e->getMessage()
                );
            }

            return array(
                'success' => false,
                'message' => __('Screenshot API error. Please try again.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage()
            );

        } catch (Exception $e) {
            error_log('TP Link Shortener Screenshot Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An unexpected error occurred while capturing screenshot.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage()
            );
        }
    }

    /**
     * AJAX handler for searching links by browser fingerprint
     */
    public function ajax_search_by_fingerprint() {
        $this->log_to_file('=== AJAX SEARCH BY FINGERPRINT START ===');
        try {
            $this->log_to_file('Step 1: Verifying nonce');
            // Verify nonce
            check_ajax_referer('tp_link_shortener_nonce', 'nonce');
            $this->log_to_file('Nonce verified successfully');

            $this->log_to_file('Step 2: Getting fingerprint from POST data');
            $this->log_to_file('POST data: ' . json_encode($_POST));

            // Get fingerprint from POST data
            $fingerprint = isset($_POST['fingerprint']) ? sanitize_text_field($_POST['fingerprint']) : '';

            $this->log_to_file('Fingerprint received: ' . $fingerprint);
            $this->log_to_file('Fingerprint length: ' . strlen($fingerprint));

            if (empty($fingerprint)) {
                $this->log_to_file('ERROR: Fingerprint is empty');
                $this->log_to_file('=== AJAX SEARCH BY FINGERPRINT END (NO FINGERPRINT) ===');
                wp_send_json_error(array(
                    'message' => __('Fingerprint is required.', 'tp-link-shortener')
                ));
                return;
            }

            $this->log_to_file('Step 3: Calling API client searchByFingerprint');
            // Search for records by fingerprint
            $result = $this->client->searchByFingerprint($fingerprint, 0, '');

            $this->log_to_file('Step 4: API client returned result');
            $this->log_to_file('Result: ' . json_encode($result->toArray()));

            // Check for records using DTO methods
            $has_records = $result->hasRecords();
            $record_count = $result->getCount();

            $this->log_to_file('Result has source.records: ' . ($has_records ? 'yes' : 'no'));
            $this->log_to_file('Record count: ' . $record_count);

            if ($has_records && $record_count > 0) {
                // Get the most recent record (first in array)
                $latest_record = $result->getFirstRecord();

                $this->log_to_file('Step 5: Found record, returning success');
                $this->log_to_file('Record: ' . json_encode($latest_record->toArray()));
                $this->log_to_file('=== AJAX SEARCH BY FINGERPRINT END (SUCCESS - RECORD FOUND) ===');

                wp_send_json_success(array(
                    'record' => $latest_record->toArray(),
                    'fingerprint' => $result->getFingerprint(),
                    'count' => $result->getCount()
                ));
            } else {
                // No records found
                $this->log_to_file('Step 5: No records found, returning success with null record');
                $this->log_to_file('=== AJAX SEARCH BY FINGERPRINT END (SUCCESS - NO RECORDS) ===');

                wp_send_json_success(array(
                    'record' => null,
                    'fingerprint' => $fingerprint,
                    'count' => 0
                ));
            }

        } catch (Exception $e) {
            $this->log_to_file('EXCEPTION: ' . get_class($e));
            $this->log_to_file('Message: ' . $e->getMessage());
            $this->log_to_file('Trace: ' . $e->getTraceAsString());
            $this->log_to_file('=== AJAX SEARCH BY FINGERPRINT END (EXCEPTION) ===');

            error_log('TP Link Shortener Fingerprint Search Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Failed to search for links.', 'tp-link-shortener'),
                'debug_error' => $e->getMessage()
            ));
        }
    }

    /**
     * Log to file for debugging
     */
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('tp-link-shortener/v1', '/logs', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_get_logs'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args'                => array(
                'log' => array(
                    'default'           => 'debug',
                    'sanitize_callback' => 'sanitize_text_field',
                    'enum'              => array('debug', 'snapcapture', 'wp'),
                ),
                'mode' => array(
                    'default'           => 'tail',
                    'sanitize_callback' => 'sanitize_text_field',
                    'enum'              => array('head', 'tail'),
                ),
                'n' => array(
                    'default'           => 50,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }

    /**
     * Verify logs API key from X-API-Key header
     */
    public function verify_api_key(\WP_REST_Request $request): bool {
        $expected = defined('LOGS_API_KEY') ? LOGS_API_KEY : '';

        if (empty($expected)) {
            return false;
        }

        $key = $request->get_header('X-API-Key');

        return hash_equals($expected, (string) $key);
    }

    /**
     * REST handler: read log files with head/tail and -n
     */
    public function rest_get_logs(\WP_REST_Request $request): \WP_REST_Response {
        $log_name = $request->get_param('log');
        $mode     = $request->get_param('mode');
        $n        = (int) $request->get_param('n');

        if ($n < 1) {
            $n = 50;
        }
        if ($n > 5000) {
            $n = 5000;
        }

        $log_files = array(
            'debug'       => WP_CONTENT_DIR . '/plugins/tp-update-debug.log',
            'snapcapture' => TP_LINK_SHORTENER_PLUGIN_DIR . 'logs/snapcapture.log',
            'wp'          => WP_CONTENT_DIR . '/debug.log',
        );

        if (!isset($log_files[$log_name])) {
            return new \WP_REST_Response(array('error' => 'Invalid log name. Use: debug, snapcapture, wp'), 400);
        }

        $file = $log_files[$log_name];

        if (!file_exists($file) || !is_readable($file)) {
            return new \WP_REST_Response(array('error' => "Log file not found: {$log_name}"), 404);
        }

        $spl = new \SplFileObject($file, 'r');
        $spl->seek(PHP_INT_MAX);
        $total = $spl->key();

        if ($mode === 'head') {
            $start = 0;
        } else {
            $start = max(0, $total - $n);
        }

        $lines = array();
        $spl->seek($start);
        $count = 0;
        while (!$spl->eof() && $count < $n) {
            $line = rtrim($spl->current(), "\r\n");
            if ($line !== '' || !$spl->eof()) {
                $lines[] = $line;
                $count++;
            }
            $spl->next();
        }

        return new \WP_REST_Response(array(
            'log'   => $log_name,
            'file'  => basename($file),
            'mode'  => $mode,
            'n'     => $n,
            'total' => $total,
            'lines' => $lines,
        ), 200);
    }

    private function log_to_file($message) {
        $log_file = WP_CONTENT_DIR . '/plugins/tp-update-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    }

    /**
     * AJAX handler for updating link (anonymous users)
     */
    public function ajax_update_link() {
        try {
            // Log incoming request for debugging
            $this->log_to_file('=== UPDATE LINK REQUEST START ===');
            $this->log_to_file('Request received: ' . json_encode($_POST));
            error_log('TP Update Link - Request received: ' . json_encode($_POST));

            // Verify nonce
            check_ajax_referer('tp_link_shortener_nonce', 'nonce');

            // Get parameters
            $mid = isset($_POST['mid']) ? intval($_POST['mid']) : 0;
            $destination = isset($_POST['destination']) ? esc_url_raw($_POST['destination']) : '';
            $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
            $tpKey = isset($_POST['tpKey']) ? sanitize_text_field($_POST['tpKey']) : '';

            $this->log_to_file('Parsed params: mid=' . $mid . ', destination=' . $destination . ', domain=' . $domain . ', tpKey=' . $tpKey);
            error_log('TP Update Link - Parsed params: mid=' . $mid . ', destination=' . $destination . ', domain=' . $domain . ', tpKey=' . $tpKey);

            if (empty($mid) || empty($destination) || empty($domain) || empty($tpKey)) {
                $error_details = array(
                    'mid_empty' => empty($mid),
                    'destination_empty' => empty($destination),
                    'domain_empty' => empty($domain),
                    'tpKey_empty' => empty($tpKey),
                    'mid_value' => $mid,
                    'destination_value' => $destination,
                    'domain_value' => $domain,
                    'tpKey_value' => $tpKey
                );
                $this->log_to_file('Missing params: ' . json_encode($error_details));
                error_log('TP Update Link - Missing params: ' . json_encode($error_details));
                wp_send_json_error(array(
                    'message' => __('Missing required parameters.', 'tp-link-shortener'),
                    'debug' => $error_details
                ));
                return;
            }

            // Get user ID (-1 for anonymous)
            $user_id = is_user_logged_in() ? get_current_user_id() : -1;
            $this->log_to_file('User ID: ' . $user_id . ' (logged_in: ' . (is_user_logged_in() ? 'yes' : 'no') . ')');
            error_log('TP Update Link - User ID: ' . $user_id);

            // Prepare update data
            $updateData = array(
                'uid' => $user_id,
                'domain' => $domain,
                'destination' => $destination,
                'tpKey' => $tpKey,
                'status' => $user_id === -1 ? 'intro' : 'active',
                'is_set' => 0,
                'tags' => '',
                'notes' => '',
                'settings' => '{}',
            );

            $this->log_to_file('Update data prepared: ' . json_encode($updateData));
            error_log('TP Update Link - Update data prepared: ' . json_encode($updateData));

            // Update the record
            $this->log_to_file('Calling updateMaskedRecord with mid=' . $mid);
            $response = $this->client->updateMaskedRecord($mid, $updateData);

            $this->log_to_file('API Response: ' . json_encode($response));
            error_log('TP Update Link - API Response: ' . json_encode($response));

            if ($response['success']) {
                $this->log_to_file('SUCCESS - Link updated successfully');
                $this->log_to_file('=== UPDATE LINK REQUEST END ===');

                // Log history
                $this->log_link_history($mid, $user_id, 'updated', json_encode(array(
                    'destination' => $destination,
                    'tpKey' => $tpKey,
                    'domain' => $domain,
                )));

                wp_send_json_success(array(
                    'message' => __('Link updated successfully!', 'tp-link-shortener'),
                    'data' => $response
                ));
            } else {
                $this->log_to_file('FAILURE - API returned success=false: ' . json_encode($response));
                $this->log_to_file('=== UPDATE LINK REQUEST END ===');
                error_log('TP Update Link - API returned success=false: ' . json_encode($response));
                wp_send_json_error(array(
                    'message' => __('Failed to update link.', 'tp-link-shortener'),
                    'api_response' => $response,
                    'debug' => array(
                        'mid' => $mid,
                        'update_data' => $updateData
                    )
                ));
            }

        } catch (ValidationException $e) {
            $this->log_to_file('EXCEPTION - ValidationException: ' . $e->getMessage());
            $this->log_to_file('Trace: ' . $e->getTraceAsString());
            $this->log_to_file('=== UPDATE LINK REQUEST END ===');
            error_log('TP Update Link - ValidationException: ' . $e->getMessage());
            error_log('TP Update Link - ValidationException trace: ' . $e->getTraceAsString());
            wp_send_json_error(array(
                'message' => __('Validation error: ', 'tp-link-shortener') . $e->getMessage(),
                'exception_type' => 'ValidationException',
                'trace' => $e->getTraceAsString()
            ));
        } catch (Exception $e) {
            $this->log_to_file('EXCEPTION - ' . get_class($e) . ': ' . $e->getMessage());
            $this->log_to_file('Code: ' . $e->getCode());
            $this->log_to_file('Trace: ' . $e->getTraceAsString());
            $this->log_to_file('=== UPDATE LINK REQUEST END ===');
            error_log('TP Link Shortener Update Error: ' . $e->getMessage());
            error_log('TP Link Shortener Update Error Trace: ' . $e->getTraceAsString());
            wp_send_json_error(array(
                'message' => __('Failed to update link: ', 'tp-link-shortener') . $e->getMessage(),
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }

    /**
     * Get user's IP address
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    /**
     * AJAX handler for suggesting AI-generated shortcode
     * Called after URL validation to get Gemini-powered short code suggestion
     */
    public function ajax_suggest_shortcode() {
        $this->handle_suggest_shortcode(GenerationTier::AI);
    }

    public function ajax_suggest_shortcode_fast() {
        $this->handle_suggest_shortcode(GenerationTier::Fast);
    }

    public function ajax_suggest_shortcode_smart() {
        $this->handle_suggest_shortcode(GenerationTier::Smart);
    }

    public function ajax_suggest_shortcode_ai() {
        $this->handle_suggest_shortcode(GenerationTier::AI);
    }

    private function handle_suggest_shortcode(GenerationTier $tier): void {
        $this->log_to_file('=== SUGGEST SHORTCODE REQUEST START ===');
        $this->log_to_file('Tier: ' . $tier->value);
        error_log('TP Link Shortener: ajax_suggest_shortcode (' . $tier->value . ') called');

        // Verify nonce
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        // Get destination URL
        $destination = isset($_POST['destination']) ? sanitize_url($_POST['destination']) : '';

        $this->log_to_file('Destination URL: ' . $destination);
        error_log('TP Link Shortener: Suggesting shortcode (' . $tier->value . ') for URL: ' . $destination);

        // Validate destination URL
        if (empty($destination) || !filter_var($destination, FILTER_VALIDATE_URL)) {
            $this->log_to_file('ERROR - Invalid destination URL: ' . $destination);
            $this->log_to_file('=== SUGGEST SHORTCODE REQUEST END ===');
            error_log('TP Link Shortener: Invalid destination URL for shortcode suggestion: ' . $destination);
            wp_send_json_error(array(
                'message' => __('Please enter a valid URL', 'tp-link-shortener')
            ));
            return;
        }

        // Check if Gemini generation is enabled
        if (!TP_Link_Shortener::use_gemini_generation()) {
            $this->log_to_file('Gemini generation disabled, returning random key');
            $random_key = $this->generate_random_key();
            $this->log_to_file('Generated random key: ' . $random_key);
            $this->log_to_file('=== SUGGEST SHORTCODE REQUEST END ===');
            wp_send_json_success(array(
                'shortcode' => $random_key,
                'source' => 'random',
                'message' => __('Random shortcode generated', 'tp-link-shortener')
            ));
            return;
        }

        // Generate shortcode using the requested tier
        $this->log_to_file('Gemini generation enabled, calling generate_short_code for tier: ' . $tier->value);
        $result = $this->generate_short_code_result($destination, $tier);
        $suggested_code = $result['shortcode'];

        $this->log_to_file('Suggested shortcode: ' . $suggested_code);
        $this->log_to_file('=== SUGGEST SHORTCODE REQUEST END ===');
        error_log('TP Link Shortener: Suggested shortcode (' . $tier->value . '): ' . $suggested_code);

        wp_send_json_success(array(
            'shortcode' => $suggested_code,
            'source' => $tier->value,
            'candidates' => $result['candidates'],
            'method' => $result['method'],
            'was_modified' => $result['was_modified'],
            'original_code' => $result['original_code'],
            'url' => $result['url'],
            'message' => __('AI-generated shortcode suggestion', 'tp-link-shortener')
        ));
    }

    /**
     * AJAX handler for non-logged-in users hitting protected endpoints
     */
    public function ajax_require_login() {
        $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : 'unknown';
        $this->log_to_file('=== AUTH REJECTED (nopriv) ===');
        $this->log_to_file('Action: ' . $action);
        $this->log_to_file('IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $this->log_to_file('User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
        $this->log_to_file('Referer: ' . ($_SERVER['HTTP_REFERER'] ?? 'none'));
        wp_send_json_error(array(
            'message' => __('You must be logged in to view your links.', 'tp-link-shortener'),
            'code'    => 'login_required',
        ), 401);
    }

    /**
     * AJAX handler for getting paginated user map items
     * Only available to logged-in users
     */
    public function ajax_get_user_map_items() {
        $this->log_to_file('=== GET USER MAP ITEMS REQUEST START ===');
        $this->log_to_file('IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $this->log_to_file('User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
        $this->log_to_file('Referer: ' . ($_SERVER['HTTP_REFERER'] ?? 'none'));
        $this->log_to_file('WP User ID: ' . get_current_user_id());
        $this->log_to_file('is_user_logged_in: ' . (is_user_logged_in() ? 'true' : 'false'));
        $this->log_to_file('Nonce received: ' . (isset($_POST['nonce']) ? 'yes' : 'no'));

        // Verify nonce
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');
        $this->log_to_file('Nonce verified OK');

        // Ensure user is logged in
        if (!is_user_logged_in()) {
            $this->log_to_file('ERROR: User not logged in after nonce check');
            $this->log_to_file('=== GET USER MAP ITEMS REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('You must be logged in to view your links.', 'tp-link-shortener'),
                'code'    => 'login_required',
            ), 401);
            return;
        }

        // Get parameters
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $page_size = isset($_POST['page_size']) ? intval($_POST['page_size']) : 50;
        $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : null;
        $include_usage = isset($_POST['include_usage']) ? filter_var($_POST['include_usage'], FILTER_VALIDATE_BOOLEAN) : true;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : null;

        $this->log_to_file('Parameters: page=' . $page . ', page_size=' . $page_size . ', sort=' . ($sort ?? 'default') . ', include_usage=' . ($include_usage ? 'true' : 'false') . ', status=' . ($status ?? 'all') . ', search=' . ($search ?? 'none'));

        // Validate parameters
        if ($page < 1) {
            wp_send_json_error(array(
                'message' => __('Invalid page. Must be >= 1.', 'tp-link-shortener')
            ), 400);
            return;
        }

        if ($page_size < 1 || $page_size > 200) {
            wp_send_json_error(array(
                'message' => __('Invalid page_size. Must be between 1 and 200.', 'tp-link-shortener')
            ), 400);
            return;
        }

        // Validate sort if provided
        if ($sort !== null) {
            $allowed_sort_fields = array('updated_at', 'created_at', 'tpKey', 'destination', 'clicks');
            $allowed_directions = array('asc', 'desc');
            $sort_parts = explode(':', $sort);

            if (count($sort_parts) !== 2 ||
                !in_array($sort_parts[0], $allowed_sort_fields, true) ||
                !in_array($sort_parts[1], $allowed_directions, true)
            ) {
                wp_send_json_error(array(
                    'message' => __('Invalid sort. Use one of: updated_at, created_at, tpKey with asc/desc.', 'tp-link-shortener')
                ), 400);
                return;
            }
        }

        // Validate search length
        if ($search !== null && strlen($search) > 255) {
            wp_send_json_error(array(
                'message' => __('Search term too long. Maximum 255 characters.', 'tp-link-shortener')
            ), 400);
            return;
        }

        // Get user ID
        $uid = TP_Link_Shortener::get_user_id();
        $this->log_to_file('User ID: ' . $uid);

        try {
            // Call the real API
            $response = $this->client->getUserMapItems($uid, $page, $page_size, $sort, $include_usage, $status, $search);

            $this->log_to_file('API response received: ' . json_encode($response->toArray()));
            $this->log_to_file('=== GET USER MAP ITEMS REQUEST END ===');

            wp_send_json_success($response->toArray());

        } catch (PageNotFoundException $e) {
            $this->log_to_file('Page not found: ' . $e->getMessage());
            $this->log_to_file('=== GET USER MAP ITEMS REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('Page not found. Please try a different page.', 'tp-link-shortener'),
                'error_code' => 404
            ), 404);

        } catch (ValidationException $e) {
            $this->log_to_file('Validation error: ' . $e->getMessage());
            $this->log_to_file('=== GET USER MAP ITEMS REQUEST END ===');
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ), 400);

        } catch (AuthenticationException $e) {
            $this->log_to_file('Authentication error: ' . $e->getMessage());
            $this->log_to_file('=== GET USER MAP ITEMS REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('Authentication failed. Please check configuration.', 'tp-link-shortener')
            ), 401);

        } catch (NetworkException $e) {
            $this->log_to_file('Network error: ' . $e->getMessage());
            $this->log_to_file('=== GET USER MAP ITEMS REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('Network error. Please try again later.', 'tp-link-shortener')
            ), 503);

        } catch (ApiException $e) {
            $this->log_to_file('API error: ' . $e->getMessage());
            $this->log_to_file('=== GET USER MAP ITEMS REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('API error. Please try again.', 'tp-link-shortener')
            ), 500);

        } catch (Exception $e) {
            $this->log_to_file('Unexpected error: ' . $e->getMessage());
            $this->log_to_file('Trace: ' . $e->getTraceAsString());
            $this->log_to_file('=== GET USER MAP ITEMS REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('An unexpected error occurred. Please try again.', 'tp-link-shortener')
            ), 500);
        }
    }

    /**
     * AJAX handler for toggling link status (enable/disable)
     */
    public function ajax_toggle_link_status() {
        $this->log_to_file('=== TOGGLE LINK STATUS REQUEST START ===');
        $this->log_to_file('IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $this->log_to_file('WP User ID: ' . get_current_user_id());
        $this->log_to_file('is_user_logged_in: ' . (is_user_logged_in() ? 'true' : 'false'));

        check_ajax_referer('tp_link_shortener_nonce', 'nonce');
        $this->log_to_file('Nonce verified OK');

        if (!is_user_logged_in()) {
            $this->log_to_file('ERROR: User not logged in');
            $this->log_to_file('=== TOGGLE LINK STATUS REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'tp-link-shortener'),
                'code'    => 'login_required',
            ), 401);
            return;
        }

        $mid = isset($_POST['mid']) ? intval($_POST['mid']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (empty($mid) || !in_array($new_status, array('active', 'disabled'), true)) {
            wp_send_json_error(array(
                'message' => __('Invalid parameters.', 'tp-link-shortener')
            ), 400);
            return;
        }

        $user_id = TP_Link_Shortener::get_user_id();

        try {
            $response = $this->client->updateMaskedRecord($mid, array(
                'uid'    => $user_id,
                'status' => $new_status,
            ));

            if ($response['success']) {
                // Log history
                $this->log_link_history($mid, $user_id, $new_status === 'active' ? 'enabled' : 'disabled', '');

                $this->log_to_file('SUCCESS - Status toggled to ' . $new_status);
                $this->log_to_file('=== TOGGLE LINK STATUS REQUEST END ===');
                wp_send_json_success(array(
                    'message' => $new_status === 'active'
                        ? __('Link enabled.', 'tp-link-shortener')
                        : __('Link disabled.', 'tp-link-shortener'),
                    'status' => $new_status
                ));
            } else {
                $this->log_to_file('FAILURE - API returned success=false');
                $this->log_to_file('=== TOGGLE LINK STATUS REQUEST END ===');
                wp_send_json_error(array(
                    'message' => __('Failed to update status.', 'tp-link-shortener')
                ));
            }
        } catch (Exception $e) {
            $this->log_to_file('EXCEPTION: ' . $e->getMessage());
            $this->log_to_file('=== TOGGLE LINK STATUS REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('Error: ', 'tp-link-shortener') . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX handler for retrieving link change history
     */
    public function ajax_get_link_history() {
        $this->log_to_file('=== GET LINK HISTORY REQUEST START ===');
        $this->log_to_file('IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $this->log_to_file('WP User ID: ' . get_current_user_id());
        $this->log_to_file('is_user_logged_in: ' . (is_user_logged_in() ? 'true' : 'false'));

        check_ajax_referer('tp_link_shortener_nonce', 'nonce');
        $this->log_to_file('Nonce verified OK');

        if (!is_user_logged_in()) {
            $this->log_to_file('ERROR: User not logged in');
            $this->log_to_file('=== GET LINK HISTORY REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'tp-link-shortener'),
                'code'    => 'login_required',
            ), 401);
            return;
        }

        $mid = isset($_POST['mid']) ? intval($_POST['mid']) : 0;
        if (empty($mid)) {
            wp_send_json_error(array('message' => __('Invalid link ID.', 'tp-link-shortener')), 400);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tp_link_history';

        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            wp_send_json_success(array());
            return;
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT action, changes, created_at FROM {$table} WHERE mid = %d ORDER BY created_at DESC LIMIT 50",
            $mid
        ), ARRAY_A);

        wp_send_json_success($results ?: array());
    }

    /**
     * Log a link change to the history table
     */
    private function log_link_history(int $mid, int $uid, string $action, string $changes): void {
        global $wpdb;
        $table = $wpdb->prefix . 'tp_link_history';

        // Create table if it doesn't exist
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset_collate = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                mid BIGINT(20) UNSIGNED NOT NULL,
                uid BIGINT(20) NOT NULL,
                action VARCHAR(50) NOT NULL,
                changes TEXT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_mid (mid),
                KEY idx_uid (uid)
            ) {$charset_collate}");
        }

        $wpdb->insert($table, array(
            'mid'        => $mid,
            'uid'        => $uid,
            'action'     => $action,
            'changes'    => $changes,
            'created_at' => current_time('mysql'),
        ), array('%d', '%d', '%s', '%s', '%s'));
    }
}
