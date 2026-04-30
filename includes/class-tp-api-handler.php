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
use TerrWallet\TerrWalletClient;
use TerrWallet\UsageMergeAdapter;
use TerrWallet\Exception\TerrWalletException;
use WooWallet\WooWalletClient;
use WooWallet\Exception\WooWalletException;
use WooWallet\Exception\AuthenticationException as WooWalletAuthException;
use WooWallet\Exception\ValidationException as WooWalletValidationException;
use WooWallet\Exception\ApiException as WooWalletApiException;
use TP\History\LinkHistoryDiff;

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
     * WooWallet Client instance
     */
    private ?WooWalletClient $woowallet_client = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->register_ajax_handlers();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('woocommerce_thankyou', array($this, 'render_wallet_topup_return_link'), 20);
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
     * Initialize WooWallet API client
     */
    private function init_woowallet_client() {
        if (!defined('TP_WC_CONSUMER_KEY') || !defined('TP_WC_CONSUMER_SECRET')) {
            error_log('TP Link Shortener: TP_WC_CONSUMER_KEY / TP_WC_CONSUMER_SECRET not defined. WooWallet client disabled.');
            return;
        }

        $this->woowallet_client = new WooWalletClient(
            site_url(),
            TP_WC_CONSUMER_KEY,
            TP_WC_CONSUMER_SECRET
        );
    }

    private function get_client() {
        if ($this->client === null) {
            $this->init_client();
        }
        return $this->client;
    }

    private function get_shortcode_client() {
        if ($this->shortcode_client === null) {
            $this->init_shortcode_client();
        }
        return $this->shortcode_client;
    }

    private function get_snapcapture_client() {
        if ($this->snapcapture_client === null) {
            $this->init_snapcapture_client();
        }
        return $this->snapcapture_client;
    }

    /**
     * Inject a SnapCapture client (used by tests to supply a mock).
     *
     * @param SnapCaptureClient $client
     */
    public function set_snapcapture_client(SnapCaptureClient $client): void {
        $this->snapcapture_client = $client;
    }

    /**
     * Sideload a SnapCapture preview image for a link.
     *
     * Captures a screenshot of $destinationUrl via SnapCapture, writes the
     * binary image to uploads/tp-link-previews/{mid}.{ext}, and inserts /
     * replaces a row in wp_tp_link_previews.
     *
     * On failure (SnapCapture error, network timeout, etc.) the method logs
     * and returns false without throwing — callers must not block the parent
     * link-creation request on sideload failure.
     *
     * On a non-writable uploads directory the method still inserts a row with
     * an empty local_path so the placeholder renderer has a row to detect.
     *
     * @param int         $mid             Link ID (primary key).
     * @param string      $destinationUrl  URL to screenshot.
     * @param string|null $uploadsOverride Override the uploads base dir (for tests).
     * @return bool True on full success (file written + row inserted); false otherwise.
     */
    public function sideload_preview(int $mid, string $destinationUrl, ?string $uploadsOverride = null): bool {
        global $wpdb;

        try {
            $snapcapture = $this->get_snapcapture_client();
            if ($snapcapture === null) {
                error_log("TP Sideload: SnapCapture client not available for mid={$mid}");
                return false;
            }

            // 1. Capture screenshot binary from SnapCapture.
            $request  = ScreenshotRequest::desktop($destinationUrl);
            $response = $snapcapture->captureScreenshot($request, false);

            $imageData   = $response->getImageData();
            $contentType = $response->getContentType();

            // 2. Derive file extension from Content-Type.
            $ext = match (true) {
                str_contains($contentType, 'image/jpeg') => 'jpg',
                str_contains($contentType, 'image/png')  => 'png',
                str_contains($contentType, 'image/gif')  => 'gif',
                str_contains($contentType, 'image/webp') => 'webp',
                default                                   => 'png',
            };

            // 3. Resolve the uploads directory.
            if ($uploadsOverride !== null) {
                $uploadsBase = $uploadsOverride;
            } else {
                $uploadDir   = wp_upload_dir();
                $uploadsBase = $uploadDir['basedir'];
            }
            $previewsDir = $uploadsBase . '/tp-link-previews';
            $filename    = "{$mid}.{$ext}";
            $localPath   = $previewsDir . '/' . $filename;
            $relPath     = "tp-link-previews/{$filename}";

            // 4. Write image bytes to disk (soft-fail: directory errors are caught locally).
            $fileWritten = false;
            try {
                $dirReady = is_writable($previewsDir);
                if (!$dirReady) {
                    $dirReady = @wp_mkdir_p($previewsDir) && is_writable($previewsDir);
                }
                if ($dirReady) {
                    $bytes       = file_put_contents($localPath, $imageData);
                    $fileWritten = ($bytes !== false && $bytes > 0);
                }
            } catch (\Throwable $dirErr) {
                error_log("TP Sideload: dir error for mid={$mid}: " . $dirErr->getMessage());
            }

            if (!$fileWritten) {
                error_log("TP Sideload: uploads dir not writable for mid={$mid}, storing empty local_path");
            }

            // 5. Compute dimensions (only when file is on disk).
            $width  = 0;
            $height = 0;
            if ($fileWritten) {
                $size = @getimagesize($localPath);
                if ($size !== false) {
                    $width  = (int) $size[0];
                    $height = (int) $size[1];
                }
            }

            // 6. Persist row (UPSERT via wpdb->replace — always runs, even on soft-fail).
            $now = current_time('mysql');
            $wpdb->replace(
                TP_LINK_PREVIEWS_TABLE,
                [
                    'mid'          => $mid,
                    'local_path'   => $fileWritten ? $relPath : '',
                    'original_url' => $destinationUrl,
                    'width'        => $width,
                    'height'       => $height,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ],
                ['%d', '%s', '%s', '%d', '%d', '%s', '%s']
            );

            if (!$fileWritten) {
                return false;
            }

            error_log("TP Sideload: preview saved for mid={$mid} at {$relPath}");
            return true;

        } catch (\Throwable $e) {
            // SnapCapture API failure or other fatal error.
            // Do NOT insert a DB row in this case — there is no image data to record.
            error_log("TP Sideload: failed for mid={$mid}: " . $e->getMessage());
            return false;
        }
    }

    private function get_woowallet_client(): ?WooWalletClient {
        if ($this->woowallet_client === null) {
            $this->init_woowallet_client();
        }
        return $this->woowallet_client;
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

        // Link usage by tpKey - logged-in users only
        add_action('wp_ajax_tp_get_link_usage', array($this, 'ajax_get_link_usage'));
        add_action('wp_ajax_nopriv_tp_get_link_usage', array($this, 'ajax_require_login'));

        // Usage summary - logged-in users only
        add_action('wp_ajax_tp_get_usage_summary', array($this, 'ajax_get_usage_summary'));
        add_action('wp_ajax_nopriv_tp_get_usage_summary', array($this, 'ajax_require_login'));

        // WooWallet endpoints - logged-in users only
        add_action('wp_ajax_tp_wallet_balance', array($this, 'ajax_wallet_balance'));
        add_action('wp_ajax_tp_wallet_transactions', array($this, 'ajax_wallet_transactions'));
        add_action('wp_ajax_tp_wallet_credit', array($this, 'ajax_wallet_credit'));
        add_action('wp_ajax_tp_wallet_debit', array($this, 'ajax_wallet_debit'));
        add_action('wp_ajax_tp_wallet_topup_checkout', array($this, 'ajax_wallet_topup_checkout'));
        add_action('wp_ajax_nopriv_tp_wallet_balance', array($this, 'ajax_require_login'));
        add_action('wp_ajax_nopriv_tp_wallet_transactions', array($this, 'ajax_require_login'));
        add_action('wp_ajax_nopriv_tp_wallet_credit', array($this, 'ajax_require_login'));
        add_action('wp_ajax_nopriv_tp_wallet_debit', array($this, 'ajax_require_login'));
        add_action('wp_ajax_nopriv_tp_wallet_topup_checkout', array($this, 'ajax_require_login'));

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
        add_action('wp_ajax_nopriv_tp_update_link', array($this, 'ajax_require_login'));
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
        $uid = TP_Link_Shortener::get_user_id();
        $fingerprint = isset($_POST['fingerprint']) ? sanitize_text_field($_POST['fingerprint']) : null;

        $this->log_to_file('Initial POST data - destination: ' . $destination . ', custom_key: ' . $custom_key . ', uid: ' . $uid . ', fingerprint: ' . ($fingerprint ?: 'null'));
        error_log('TP Link Shortener: Initial POST data - destination: ' . $destination . ', custom_key: ' . $custom_key . ', uid: ' . $uid . ', fingerprint: ' . ($fingerprint ?: 'null'));

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

                // Sideload SnapCapture preview (soft-fail — never blocks link creation).
                $this->sideload_preview($created_mid, $destination);
            }

            $this->invalidate_user_caches($uid);
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
                notes: '',
                settings: '{}',
                cacheContent: 0,
                expiresAt: null,
                fingerprint: $fingerprint
            );

            $this->log_to_file('Sending request to API: ' . json_encode($request->toArray()));
            error_log('TP Link Shortener: Sending request to API: ' . json_encode($request->toArray()));
            $response = $this->get_client()->createMaskedRecord($request);
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
            );

        } catch (RateLimitException $e) {
            $this->log_to_file('EXCEPTION - RateLimitException: ' . $e->getMessage());
            error_log('TP Link Shortener Rate Limit Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'rate_limit',
                'http_code' => 429,
            );

        } catch (ValidationException $e) {
            $this->log_to_file('EXCEPTION - ValidationException: ' . $e->getMessage());
            // Key might be taken
            if (strpos($e->getMessage(), 'invalid') !== false) {
                return array(
                    'success' => false,
                    'message' => __('This shortcode is already taken. Please try another.', 'tp-link-shortener'),
                );
            }

            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );

        } catch (NetworkException $e) {
            $this->log_to_file('EXCEPTION - NetworkException: ' . $e->getMessage());
            error_log('TP Link Shortener Network Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Network error. Please try again later.', 'tp-link-shortener'),
            );

        } catch (ApiException $e) {
            $this->log_to_file('EXCEPTION - ApiException: ' . $e->getMessage());
            error_log('TP Link Shortener API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('API error. Please try again.', 'tp-link-shortener'),
            );

        } catch (Exception $e) {
            $this->log_to_file('EXCEPTION - ' . get_class($e) . ': ' . $e->getMessage());
            $this->log_to_file('Trace: ' . $e->getTraceAsString());
            error_log('TP Link Shortener Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An unexpected error occurred. Please try again.', 'tp-link-shortener'),
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

        if (TP_Link_Shortener::use_gemini_generation() && $this->get_shortcode_client() instanceof GenerateShortCodeClient) {
            try {
                $request = new GenerateShortCodeRequest($destination, TP_Link_Shortener::get_domain());
                $response = $this->get_shortcode_client()->generateShortCode($request, $tier);
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

        if (TP_Link_Shortener::use_gemini_generation() && $this->get_shortcode_client() instanceof GenerateShortCodeClient) {
            try {
                $request = new GenerateShortCodeRequest($destination, TP_Link_Shortener::get_domain());
                $response = $this->get_shortcode_client()->generateShortCode($request, $tier);

                $result['shortcode'] = trim($response->getShortCode());
                $result['candidates'] = $response->getCandidates();
                $result['method'] = $response->getMethod()->value;
                $result['was_modified'] = $response->wasModified();
                $result['original_code'] = $response->getOriginalCode();
                $result['url'] = $response->getUrl();

                return $result;
            } catch (ShortCodeValidationException $e) {
                error_log('TP Link Shortener: Gemini validation error (' . $tier->value . ') - ' . $e->getMessage());
                $result['error'] = $e->getMessage();
            } catch (ShortCodeNetworkException $e) {
                error_log('TP Link Shortener: Gemini network error (' . $tier->value . ') - ' . $e->getMessage());
                $result['error'] = $e->getMessage();
            } catch (ShortCodeApiException $e) {
                error_log('TP Link Shortener: Gemini API error (' . $tier->value . ') - ' . $e->getMessage());
                $result['error'] = $e->getMessage();
            } catch (\Exception $e) {
                error_log('TP Link Shortener: Unexpected Gemini error (' . $tier->value . ') - ' . $e->getMessage());
                $result['error'] = $e->getMessage();
            }
        }

        if (!isset($result['error'])) {
            $result['error'] = 'Shortcode generation is not available';
        }

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
        $uid = TP_Link_Shortener::get_user_id();

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
            $record = $this->get_client()->getMaskedRecord($key, $uid);

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
            );

        } catch (NetworkException $e) {
            error_log('TP Link Shortener Network Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Network error. Please try again later.', 'tp-link-shortener'),
            );

        } catch (ApiException $e) {
            error_log('TP Link Shortener API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('API error. Please try again.', 'tp-link-shortener'),
            );

        } catch (Exception $e) {
            error_log('TP Link Shortener Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An unexpected error occurred.', 'tp-link-shortener'),
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
        // Verify nonce (SECURITY: was missing before)
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

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

        // SECURITY: Block requests to internal/private IP ranges (SSRF protection)
        $host = $parsed_url['host'] ?? '';
        if (empty($host)) {
            wp_send_json_error(array('message' => 'Invalid URL: no host'), 400);
            return;
        }

        if ($this->is_internal_host($host)) {
            wp_send_json_error(array('message' => 'Internal URLs are not allowed'), 403);
            return;
        }

        // Make HEAD request using WordPress HTTP API
        $response = wp_remote_head($url, array(
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'user-agent' => 'TP-Link-Shortener-Validator/1.0'
        ));

        // Check for errors - if HTTPS fails with SSL error, try HTTP fallback
        if (is_wp_error($response)) {
            $is_https = $parsed_url['scheme'] === 'https';
            $error_message = $response->get_error_message();

            // Check if this is an SSL-related error
            $is_ssl_error = strpos($error_message, 'SSL') !== false ||
                           strpos($error_message, 'certificate') !== false ||
                           strpos($error_message, 'ssl') !== false;

            // If HTTPS failed with SSL error, try HTTP fallback
            if ($is_https && $is_ssl_error) {
                $http_url = preg_replace('/^https:/', 'http:', $url);

                $http_response = wp_remote_head($http_url, array(
                    'timeout' => 10,
                    'redirection' => 0,
                    'user-agent' => 'TP-Link-Shortener-Validator/1.0'
                ));

                if (!is_wp_error($http_response)) {
                    $http_status_code = wp_remote_retrieve_response_code($http_response);

                    if ($http_status_code >= 200 && $http_status_code < 400) {
                        wp_send_json_success(array(
                            'ok' => true,
                            'status' => $http_status_code,
                            'protocol_updated' => true,
                            'updated_url' => $http_url,
                            'original_url' => $url,
                            'reason' => 'HTTPS failed with SSL error, HTTP works'
                        ));
                        return;
                    }
                }
            }

            // Return generic error (don't leak internal error details)
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(array(
                'ok' => false,
                'status' => 0,
                'error' => __('Unable to reach URL.', 'tp-link-shortener')
            ));
            wp_die();
        }

        // Get response data
        $status_code = wp_remote_retrieve_response_code($response);

        // Return minimal response (don't leak target server headers)
        header('Content-Type: application/json');
        echo json_encode(array(
            'ok' => $status_code >= 200 && $status_code < 400,
            'status' => $status_code,
        ));
        wp_die();
    }

    /**
     * Check if a hostname resolves to an internal/private IP address (SSRF protection)
     */
    private function is_internal_host(string $host): bool {
        // Block obvious internal hostnames
        $blocked_hosts = array('localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]');
        if (in_array(strtolower($host), $blocked_hosts, true)) {
            return true;
        }

        // Resolve hostname to IP and check against private ranges
        $ip = gethostbyname($host);

        // gethostbyname returns the hostname if resolution fails — treat as blocked
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Block private, reserved, and link-local IP ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        // Block AWS/cloud metadata endpoint (169.254.169.254)
        if (strpos($ip, '169.254.') === 0) {
            return true;
        }

        return false;
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
        if ($this->get_snapcapture_client() === null) {
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
            $response = $this->get_snapcapture_client()->captureScreenshot($request, true);

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
            );

        } catch (\SnapCapture\Exception\ValidationException $e) {
            error_log('TP Link Shortener SnapCapture Validation Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Invalid URL for screenshot capture.', 'tp-link-shortener'),
            );

        } catch (\SnapCapture\Exception\NetworkException $e) {
            error_log('TP Link Shortener SnapCapture Network Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Network error while capturing screenshot. Please try again.', 'tp-link-shortener'),
            );

        } catch (\SnapCapture\Exception\ApiException $e) {
            error_log('TP Link Shortener SnapCapture API Error: ' . $e->getMessage());

            // Check for rate limit
            if (strpos($e->getMessage(), '429') !== false || strpos($e->getMessage(), 'rate limit') !== false) {
                return array(
                    'success' => false,
                    'message' => __('Screenshot rate limit exceeded. Please try again later.', 'tp-link-shortener'),
                );
            }

            return array(
                'success' => false,
                'message' => __('Screenshot API error. Please try again.', 'tp-link-shortener'),
            );

        } catch (Exception $e) {
            error_log('TP Link Shortener Screenshot Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An unexpected error occurred while capturing screenshot.', 'tp-link-shortener'),
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
            $result = $this->get_client()->searchByFingerprint($fingerprint, 0, '');

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
                'offset' => array(
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ),
                'search' => array(
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
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

        $offset = (int) $request->get_param('offset');
        $search = $request->get_param('search');

        if ($mode === 'head') {
            $start = $offset;
        } else {
            $start = max(0, $total - $n - $offset);
        }

        $lines = array();
        $spl->seek($start);
        $count = 0;

        if (!empty($search)) {
            // Search mode: scan up to 100k lines for matches
            $scanned = 0;
            while (!$spl->eof() && $count < $n && $scanned < 100000) {
                $line = rtrim($spl->current(), "\r\n");
                if ($line !== '' && stripos($line, $search) !== false) {
                    $lines[] = $line;
                    $count++;
                }
                $scanned++;
                $spl->next();
            }
        } else {
            while (!$spl->eof() && $count < $n) {
                $line = rtrim($spl->current(), "\r\n");
                if ($line !== '' || !$spl->eof()) {
                    $lines[] = $line;
                    $count++;
                }
                $spl->next();
            }
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
        if (!defined('TP_DEBUG_LOG') || !TP_DEBUG_LOG) return;
        $log_file = WP_CONTENT_DIR . '/plugins/tp-update-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    }

    private function invalidate_user_caches(int $uid): void {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_tp_usage_' . $uid . '_%',
                '_transient_tp_links_' . $uid . '_%'
            )
        );
    }

    /**
     * AJAX handler for updating link (logged-in users only)
     */
    public function ajax_update_link() {
        try {
            $this->log_to_file('=== UPDATE LINK REQUEST START ===');

            // Verify nonce
            check_ajax_referer('tp_link_shortener_nonce', 'nonce');

            // SECURITY: Require login (anonymous updates removed)
            if (!is_user_logged_in()) {
                wp_send_json_error(array(
                    'message' => __('You must be logged in to update links.', 'tp-link-shortener'),
                    'code'    => 'login_required',
                ), 401);
                return;
            }

            // Get parameters
            $mid = isset($_POST['mid']) ? intval($_POST['mid']) : 0;
            $destination = isset($_POST['destination']) ? esc_url_raw($_POST['destination']) : '';
            $tpKey = isset($_POST['tpKey']) ? sanitize_text_field($_POST['tpKey']) : '';

            // SECURITY: Force domain from server config (don't accept from client)
            $domain = TP_Link_Shortener::get_domain();

            $this->log_to_file('Parsed params: mid=' . $mid . ', destination=' . $destination . ', tpKey=' . $tpKey);

            if (empty($mid) || empty($destination) || empty($tpKey)) {
                $this->log_to_file('Missing required params');
                wp_send_json_error(array(
                    'message' => __('Missing required parameters.', 'tp-link-shortener'),
                ));
                return;
            }

            // Get user ID
            $user_id = get_current_user_id();
            $this->log_to_file('User ID: ' . $user_id);

            // Capture the link's current state BEFORE the update for diff logging.
            // Read once here; T009 (F005 no-op detection) can also reuse this value.
            $link_state_before = $this->read_link_state($mid);

            // Prepare update data
            $updateData = array(
                'uid' => $user_id,
                'domain' => $domain,
                'destination' => $destination,
                'tpKey' => $tpKey,
                'status' => 'active',
                'is_set' => 0,
                'tags' => '',
                'notes' => '',
                'settings' => '{}',
            );

            // Update the record
            $this->log_to_file('Calling updateMaskedRecord with mid=' . $mid);
            $response = $this->get_client()->updateMaskedRecord($mid, $updateData);

            $this->log_to_file('API Response received');

            if ($response['success']) {
                $this->log_to_file('SUCCESS - Link updated');
                $this->log_to_file('=== UPDATE LINK REQUEST END ===');

                // Build the after-state from the submitted values
                $link_state_after = [
                    'destination' => $destination,
                    'tpKey'       => $tpKey,
                    'domain'      => $domain,
                    'notes'       => '',
                ];

                // Log history with diff (skipped automatically on no-op)
                $this->log_link_history($mid, $user_id, 'updated', '', $link_state_before, $link_state_after);

                $this->invalidate_user_caches($user_id);
                wp_send_json_success(array(
                    'message' => __('Link updated successfully!', 'tp-link-shortener'),
                    'data' => $response
                ));
            } else {
                $this->log_to_file('FAILURE - API returned success=false');
                $this->log_to_file('=== UPDATE LINK REQUEST END ===');
                wp_send_json_error(array(
                    'message' => __('Failed to update link.', 'tp-link-shortener'),
                ));
            }

        } catch (ValidationException $e) {
            $this->log_to_file('EXCEPTION - ValidationException: ' . $e->getMessage());
            $this->log_to_file('=== UPDATE LINK REQUEST END ===');
            error_log('TP Update Link - ValidationException: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Validation error. Please check your input.', 'tp-link-shortener'),
            ));
        } catch (Exception $e) {
            $this->log_to_file('EXCEPTION - ' . get_class($e) . ': ' . $e->getMessage());
            $this->log_to_file('=== UPDATE LINK REQUEST END ===');
            error_log('TP Link Shortener Update Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An unexpected error occurred. Please try again.', 'tp-link-shortener'),
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

        if (isset($result['error'])) {
            $this->log_to_file('ERROR - Shortcode generation failed: ' . $result['error']);
            $this->log_to_file('=== SUGGEST SHORTCODE REQUEST END ===');
            wp_send_json_error(array(
                'message' => $result['error'],
            ), 500);
            return;
        }

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
            $response = $this->get_client()->getUserMapItems($uid, $page, $page_size, $sort, $include_usage, $status, $search);

            $this->log_to_file('API response received: ' . json_encode($response->toArray()));
            $this->log_to_file('=== GET USER MAP ITEMS REQUEST END ===');

            $result = $response->toArray();
            wp_send_json_success($result);

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
            $response = $this->get_client()->updateMaskedRecord($mid, array(
                'uid'    => $user_id,
                'status' => $new_status,
            ));

            if ($response['success']) {
                // Log history
                $this->log_link_history($mid, $user_id, $new_status === 'active' ? 'enabled' : 'disabled', '');

                $this->log_to_file('SUCCESS - Status toggled to ' . $new_status);
                $this->log_to_file('=== TOGGLE LINK STATUS REQUEST END ===');
                $this->invalidate_user_caches($user_id);
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
            error_log('TP Link Shortener Toggle Status Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An unexpected error occurred. Please try again.', 'tp-link-shortener'),
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

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT action, changes, created_at FROM {$table} WHERE mid = %d ORDER BY created_at DESC LIMIT 50",
            $mid
        ), ARRAY_A);

        wp_send_json_success($results ?: array());
    }

    /**
     * AJAX handler for polling usage (clicks + scans) for a single link by tpKey.
     * Used by the frontend form when a logged-in user has a link open.
     */
    public function ajax_get_link_usage(): void {
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'tp-link-shortener')), 401);
            return;
        }

        $tp_key = isset($_POST['tpKey']) ? sanitize_text_field($_POST['tpKey']) : '';
        if (empty($tp_key)) {
            wp_send_json_error(array('message' => __('tpKey is required.', 'tp-link-shortener')), 400);
            return;
        }

        $uid = TP_Link_Shortener::get_user_id();

        try {
            $response = $this->get_client()->getUserMapItems($uid, 1, 1, null, true, null, $tp_key);
            $items = $response->toArray();
            $source = $items['source'] ?? array();

            if (empty($source)) {
                wp_send_json_error(array('message' => __('Link not found.', 'tp-link-shortener')), 404);
                return;
            }

            $item = $source[0];
            wp_send_json_success(array('usage' => $item['usage'] ?? array('qr' => 0, 'regular' => 0, 'total' => 0)));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Failed to fetch usage.', 'tp-link-shortener')));
        }
    }

    /**
     * AJAX handler for getting usage summary data
     * Only available to logged-in users
     */
    public function ajax_get_usage_summary(): void {
        $this->log_to_file('=== GET USAGE SUMMARY REQUEST START ===');
        $this->log_to_file('IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $this->log_to_file('WP User ID: ' . get_current_user_id());

        // Verify nonce
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');
        $this->log_to_file('Nonce verified OK');

        // Ensure user is logged in
        if (!is_user_logged_in()) {
            $this->log_to_file('ERROR: User not logged in');
            $this->log_to_file('=== GET USAGE SUMMARY REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('You must be logged in.', 'tp-link-shortener'),
            ), 401);
            return;
        }

        // Get UID server-side (DATA-02: NEVER from $_POST)
        $uid = TP_Link_Shortener::get_user_id();
        $this->log_to_file('User ID (server-side): ' . $uid);

        // Sanitize date inputs
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date   = isset($_POST['end_date'])   ? sanitize_text_field($_POST['end_date'])   : '';

        $this->log_to_file('Date range: ' . $start_date . ' to ' . $end_date);

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $this->log_to_file('ERROR: Invalid date format');
            $this->log_to_file('=== GET USAGE SUMMARY REQUEST END ===');
            wp_send_json_error(array(
                'message' => __('Invalid date format.', 'tp-link-shortener'),
            ), 400);
            return;
        }

        $cache_key = 'tp_usage_' . $uid . '_' . md5($start_date . '_' . $end_date);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            wp_send_json_success($cached);
            return;
        }

        try {
            $raw = $this->get_client()->getUserActivitySummary($uid, $start_date, $end_date);

            // Validate and reshape response
            $validated = $this->validate_usage_summary_response($raw);
            $days = $validated['days'];

            // Wallet integration: merge credit transactions into usage days
            $walletClient = null;
            $walletMergeSucceeded = false;
            $wpUserId = get_current_user_id();
            try {
                $walletClient = new TerrWalletClient();
                $transactions = $walletClient->getTransactions($wpUserId, $start_date, $end_date);
                $days = UsageMergeAdapter::merge($days, $transactions);
                $walletMergeSucceeded = true;
                $this->log_to_file('Wallet data merged: ' . count($transactions) . ' transactions');
            } catch (TerrWalletException $e) {
                error_log('TP Link Shortener: Wallet data unavailable: ' . $e->getMessage());
                $this->log_to_file('Wallet data unavailable: ' . $e->getMessage());
                $days = array_map(function($day) {
                    $day['otherServices'] = null;
                    return $day;
                }, $days);
            }

            // Fetch authoritative current wallet balance
            $currentWalletBalance = $this->get_current_wallet_balance();

            // Compute running balances server-side only when wallet data is complete.
            // Without credit history, reverse-computing from current balance would
            // produce confidently wrong values -- fail closed instead.
            if ($currentWalletBalance !== null && $walletMergeSucceeded) {
                // Ensure ascending date order before forward walk
                usort($days, static function (array $a, array $b): int {
                    return strcmp($a['date'], $b['date']);
                });
                $days = $this->compute_running_balances($days, $currentWalletBalance, $uid, $wpUserId, $start_date, $end_date, $walletClient);
            } else {
                // Degraded: set balance to null so frontend shows '--'
                foreach ($days as &$day) {
                    $day['balance'] = null;
                }
                unset($day);
            }

            // Strip apiBalance from outgoing response
            $days = array_map(function($day) {
                unset($day['apiBalance']);
                return $day;
            }, $days);

            $this->log_to_file('Usage summary validated successfully: ' . count($days) . ' days');
            $this->log_to_file('=== GET USAGE SUMMARY REQUEST END ===');

            $response_data = [
                'days' => $days,
                'currentWalletBalance' => $currentWalletBalance,
            ];
            set_transient($cache_key, $response_data, 5 * MINUTE_IN_SECONDS);
            wp_send_json_success($response_data);

        } catch (NetworkException $e) {
            $this->log_to_file('Network error: ' . $e->getMessage());
            $this->log_to_file('=== GET USAGE SUMMARY REQUEST END ===');
            $this->send_usage_proxy_error($e, 'network');

        } catch (ApiException $e) {
            $this->log_to_file('API error: ' . $e->getMessage());
            $this->log_to_file('=== GET USAGE SUMMARY REQUEST END ===');
            $this->send_usage_proxy_error($e, 'api');

        } catch (\Exception $e) {
            $this->log_to_file('Unexpected error: ' . $e->getMessage());
            $this->log_to_file('=== GET USAGE SUMMARY REQUEST END ===');
            $this->send_usage_proxy_error($e, 'unknown');
        }
    }

    /**
     * Validate and reshape the usage summary API response.
     * Strips unexpected fields, checks types, normalizes format.
     * API returns: { message, success, source: [{ date, totalHits, hitCost, balance }] }
     * Returns: { days: [{ date, totalHits, hitCost, apiBalance }] }
     *
     * The API's balance field is a running cumulative usage cost (negative/debit).
     * It is kept as apiBalance internally; the final display balance is computed
     * downstream after wallet credit merging.
     */
    private function validate_usage_summary_response(array $raw): array {
        $source = $raw['source'] ?? [];

        if (!is_array($source)) {
            $source = [];
        }

        $days = [];
        foreach ($source as $record) {
            // Skip non-array records and records missing required fields
            if (!is_array($record) || !isset($record['date']) || !isset($record['totalHits'])) {
                continue;
            }

            // API may return "YYYY-MM-DD" or "YYYY-MM-DD HH:MM:SS" -- normalize to YYYY-MM-DD
            $rawDate = sanitize_text_field((string) $record['date']);
            $date = substr($rawDate, 0, 10);

            $days[] = [
                'date'       => $date,
                'totalHits'  => (int) $record['totalHits'],
                'hitCost'    => abs((float) ($record['hitCost'] ?? 0)),
                'apiBalance' => isset($record['balance']) && is_numeric($record['balance'])
                    ? (float) $record['balance']
                    : null,
                'sources'    => $this->sanitize_sources($record['sources'] ?? []),
            ];
        }

        return ['days' => $days];
    }

    /**
     * Sanitize the `sources` array from a usage-summary record.
     *
     * Expected shape per entry:
     *   { source_name: string, query_param_key: string|null, hits: int }
     *
     * Skips malformed entries. Returns empty array when input is not an array.
     */
    private function sanitize_sources($raw): array {
        if (!is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $entry) {
            if (!is_array($entry) || !isset($entry['hits'])) {
                continue;
            }

            $queryKey = $entry['query_param_key'] ?? null;
            if ($queryKey !== null) {
                $queryKey = sanitize_text_field((string) $queryKey);
            }

            $clean[] = [
                'source_name'     => sanitize_text_field((string) ($entry['source_name'] ?? '')),
                'query_param_key' => $queryKey,
                'hits'            => (int) $entry['hits'],
            ];
        }

        return $clean;
    }

    /**
     * Get the current authoritative wallet balance for the logged-in user.
     *
     * Tries two paths:
     *   1. WooWalletClient REST API (requires TP_WC_CONSUMER_KEY/SECRET)
     *   2. Direct PHP via woo_wallet()->wallet->get_wallet_balance() (requires plugin active)
     *
     * Returns null if both are unavailable.
     */
    private function get_current_wallet_balance(): ?float {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return null;
        }

        // Path 1: WooWallet REST API client
        if ($this->get_woowallet_client()) {
            try {
                $balanceDto = $this->get_woowallet_client()->getBalance($user->user_email);
                return (float) $balanceDto->balance;
            } catch (\Exception $e) {
                error_log('TP Link Shortener: WooWallet REST balance failed: ' . $e->getMessage());
            }
        }

        // Path 2: Direct PHP via user meta (woo-wallet stores balance in usermeta)
        $metaBalance = get_user_meta($user->ID, '_current_woo_wallet_balance', true);
        if ($metaBalance !== '' && is_numeric($metaBalance)) {
            return (float) $metaBalance;
        }

        return null;
    }

    /**
     * Sum wallet credit amounts in integer cents.
     *
     * @param \TerrWallet\DTO\WalletTransaction[] $transactions
     */
    private function sum_wallet_credit_cents(array $transactions): int {
        $cents = 0;
        foreach ($transactions as $tx) {
            $cents += (int) round($tx->amount * 100);
        }
        return $cents;
    }

    /**
     * Sum hitCost values from validated days in integer cents.
     *
     * @param array<int, array{hitCost: float}> $days
     */
    private function sum_usage_cost_cents(array $days): int {
        $cents = 0;
        foreach ($days as $day) {
            $cents += (int) round((float) $day['hitCost'] * 100);
        }
        return $cents;
    }

    /**
     * Compute running balances for merged usage days.
     *
     * Uses the current wallet balance as the anchor, reverses through
     * bridge-period transactions (if end_date < today) to find the
     * opening balance, then walks forward to assign each row's balance.
     *
     * All arithmetic is in integer cents to avoid floating-point drift.
     */
    private function compute_running_balances(
        array $days,
        float $currentWalletBalance,
        int $uid,
        int $wpUserId,
        string $startDate,
        string $endDate,
        ?TerrWalletClient $walletClient
    ): array {
        $today = gmdate('Y-m-d');
        $currentBalanceCents = (int) round($currentWalletBalance * 100);

        // Sum credits and costs within the selected range
        $rangeCreditsCents = 0;
        $rangeCostsCents = 0;
        foreach ($days as $day) {
            $rangeCreditsCents += (int) round((float) (($day['otherServices']['amount'] ?? 0.0)) * 100);
            $rangeCostsCents += (int) round((float) $day['hitCost'] * 100);
        }

        // Compute balance at end of selected range
        $balanceAtEndCents = $currentBalanceCents;

        if ($endDate < $today) {
            // Need bridge-period data between end_date+1 and today.
            // Both sides (usage costs + wallet credits) are required for
            // correct reversal. If either fails, return null balances.
            $bridgeStart = date('Y-m-d', strtotime($endDate . ' +1 day'));
            $bridgeComplete = true;

            $bridgeCostsCents = 0;
            $bridgeCreditsCents = 0;

            try {
                $bridgeUsageRaw = $this->get_client()->getUserActivitySummary($uid, $bridgeStart, $today);
                $bridgeUsage = $this->validate_usage_summary_response($bridgeUsageRaw)['days'];
                $bridgeCostsCents = $this->sum_usage_cost_cents($bridgeUsage);
            } catch (\Exception $e) {
                $this->log_to_file('Bridge usage fetch failed: ' . $e->getMessage());
                $bridgeComplete = false;
            }

            if ($walletClient && $bridgeComplete) {
                try {
                    $bridgeTransactions = $walletClient->getTransactions($wpUserId, $bridgeStart, $today);
                    $bridgeCreditsCents = $this->sum_wallet_credit_cents($bridgeTransactions);
                } catch (\Exception $e) {
                    $this->log_to_file('Bridge wallet fetch failed: ' . $e->getMessage());
                    $bridgeComplete = false;
                }
            } elseif (!$walletClient) {
                $bridgeComplete = false;
            }

            if (!$bridgeComplete) {
                // Cannot reverse from current balance -- return null balances
                foreach ($days as &$day) {
                    $day['balance'] = null;
                }
                unset($day);
                return $days;
            }

            // Reverse from today to end_date
            $balanceAtEndCents = $currentBalanceCents - $bridgeCreditsCents + $bridgeCostsCents;
        }

        // Compute opening balance by reversing the selected range
        $openingBalanceCents = $balanceAtEndCents - $rangeCreditsCents + $rangeCostsCents;

        // Walk forward in ascending date order, assigning running balance
        $runningCents = $openingBalanceCents;
        foreach ($days as &$day) {
            $creditCents = (int) round((float) (($day['otherServices']['amount'] ?? 0.0)) * 100);
            $costCents = (int) round((float) $day['hitCost'] * 100);

            $runningCents += $creditCents;
            $runningCents -= $costCents;

            $day['balance'] = round($runningCents / 100, 2);
        }
        unset($day);

        return $days;
    }

    /**
     * Send usage proxy error response with admin-conditional detail.
     * Generic error for regular users, detailed error for admins.
     */
    private function send_usage_proxy_error(\Exception $e, string $type): void {
        $response = array(
            'message' => __('Unable to load usage data. Please try again.', 'tp-link-shortener'),
        );

        // Admins see the actual error type and detail
        if (current_user_can('manage_options')) {
            $response['error_type']   = $type;
            $response['error_detail'] = $e->getMessage();
        }

        error_log('TP Link Shortener: Usage summary error (' . $type . '): ' . $e->getMessage());

        wp_send_json_error($response);
    }

    /**
     * Log a link change to the history table.
     *
     * For 'updated' actions, pass $before and $after state arrays to produce a
     * field-level diff payload shaped as {"field": {"from": OLD, "to": NEW}}.
     * Only changed fields are stored. When the diff is empty (no-op update),
     * the history row is skipped entirely.
     *
     * For 'created', 'enabled', and 'disabled' actions the $changes string is
     * passed through as-is (backwards-compatible with existing callers).
     *
     * @param int         $mid     Link record ID
     * @param int         $uid     WordPress user ID performing the action
     * @param string      $action  One of 'created', 'updated', 'enabled', 'disabled'
     * @param string      $changes Pre-built JSON string (used for non-updated actions)
     * @param array|null  $before  Previous link state (destination, tpKey, domain, notes)
     * @param array|null  $after   New link state after the update
     */
    private function log_link_history(
        int $mid,
        int $uid,
        string $action,
        string $changes = '',
        ?array $before = null,
        ?array $after = null
    ): void {
        global $wpdb;

        if ($action === 'updated' && $before !== null && $after !== null) {
            $diff = LinkHistoryDiff::compute($before, $after);

            // No-op: nothing changed — skip the history write
            if (empty($diff)) {
                return;
            }

            $changes = (string) json_encode($diff);
        }

        $table = $wpdb->prefix . 'tp_link_history';

        $wpdb->insert($table, array(
            'mid'        => $mid,
            'uid'        => $uid,
            'action'     => $action,
            'changes'    => $changes,
            'created_at' => current_time('mysql'),
        ), array('%d', '%d', '%s', '%s', '%s'));
    }

    /**
     * Read the current state of a link from wp_tp_link_history for diff purposes.
     *
     * Reconstructs the link's current field values by walking history backwards:
     * finds the most recent row for this mid that carries a non-empty changes payload
     * (either a 'created' row or an 'updated' diff row) and extracts the latest
     * known values for destination, tpKey, domain, notes.
     *
     * This is a best-effort read: if no history exists yet (e.g. the link pre-dates
     * the history feature), returns null and the caller falls back to legacy behaviour.
     *
     * @param int $mid Link record ID
     * @return array{destination: string, tpKey: string, domain: string, notes: string}|null
     */
    private function read_link_state(int $mid): ?array {
        global $wpdb;
        $history_table = $wpdb->prefix . 'tp_link_history';

        // Fetch the most recent rows for this mid (limit to avoid scanning all history)
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT action, changes FROM `{$history_table}` WHERE mid = %d ORDER BY created_at DESC LIMIT 20",
                $mid
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return null;
        }

        // Accumulate the latest known values field-by-field by walking rows
        // from most-recent to oldest. A 'to' value in an updated diff row is
        // the current value; in a 'created' row each key is the initial value.
        $state = [];
        $target_fields = ['destination', 'tpKey', 'domain', 'notes'];

        foreach ($rows as $row) {
            $changes_raw = $row['changes'] ?? '';
            if ($changes_raw === '' || $changes_raw === null) {
                continue;
            }
            $changes = json_decode($changes_raw, true);
            if (!is_array($changes)) {
                continue;
            }

            if ($row['action'] === 'updated') {
                // Diff shape: {"field": {"from": OLD, "to": NEW}}
                foreach ($target_fields as $field) {
                    if (!isset($state[$field]) && isset($changes[$field]['to'])) {
                        $state[$field] = (string) $changes[$field]['to'];
                    }
                }
            } elseif ($row['action'] === 'created') {
                // Flat shape: {"destination": "...", "tpKey": "..."}
                foreach ($target_fields as $field) {
                    if (!isset($state[$field]) && isset($changes[$field])) {
                        $state[$field] = (string) $changes[$field];
                    }
                }
            }

            // Stop once we have all target fields
            if (count($state) >= count($target_fields)) {
                break;
            }
        }

        if (empty($state)) {
            return null;
        }

        // Fill any missing fields with empty string
        return [
            'destination' => $state['destination'] ?? '',
            'tpKey'       => $state['tpKey'] ?? '',
            'domain'      => $state['domain'] ?? '',
            'notes'       => $state['notes'] ?? '',
        ];
    }

    // TEMP: Remove after milestone v2.2 complete

    // ─── WooWallet AJAX Handlers ────────────────────────────────────────

    /**
     * Get wallet balance for the current logged-in user.
     */
    public function ajax_wallet_balance() {
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        if (!$this->get_woowallet_client()) {
            wp_send_json_error(array('message' => 'Wallet service is not configured.'), 503);
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            wp_send_json_error(array('message' => 'Not logged in.'), 401);
        }

        try {
            $balance = $this->get_woowallet_client()->getBalance($user->user_email);
            wp_send_json_success(array(
                'balance' => $balance->balance,
                'email'   => $balance->email,
            ));
        } catch (WooWalletAuthException $e) {
            wp_send_json_error(array('message' => 'Authentication failed.'), 401);
        } catch (WooWalletValidationException $e) {
            wp_send_json_error(array('message' => $e->getMessage()), 400);
        } catch (WooWalletException $e) {
            error_log('WooWallet balance error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Could not retrieve wallet balance.'), 500);
        }
    }

    /**
     * Get wallet transactions for the current logged-in user.
     */
    public function ajax_wallet_transactions() {
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        if (!$this->get_woowallet_client()) {
            wp_send_json_error(array('message' => 'Wallet service is not configured.'), 503);
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            wp_send_json_error(array('message' => 'Not logged in.'), 401);
        }

        $per_page = isset($_REQUEST['per_page']) ? absint($_REQUEST['per_page']) : 10;
        $page     = isset($_REQUEST['page']) ? absint($_REQUEST['page']) : 1;

        try {
            $transactions = $this->get_woowallet_client()->getTransactions($user->user_email, $per_page, $page);

            $data = array_map(fn($t) => [
                'transaction_id' => $t->transactionId,
                'user_id'        => $t->userId,
                'date'           => $t->date,
                'type'           => $t->type,
                'amount'         => $t->amount,
                'balance'        => $t->balance,
                'details'        => $t->details,
                'currency'       => $t->currency,
            ], $transactions);

            wp_send_json_success(array(
                'transactions' => $data,
                'page'         => $page,
                'per_page'     => $per_page,
                'has_more'     => count($transactions) >= $per_page,
            ));
        } catch (WooWalletAuthException $e) {
            wp_send_json_error(array('message' => 'Authentication failed.'), 401);
        } catch (WooWalletValidationException $e) {
            wp_send_json_error(array('message' => $e->getMessage()), 400);
        } catch (WooWalletException $e) {
            error_log('WooWallet transactions error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Could not retrieve transactions.'), 500);
        }
    }

    /**
     * Credit the current user's wallet.
     */
    public function ajax_wallet_credit() {
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        if (!$this->get_woowallet_client()) {
            wp_send_json_error(array('message' => 'Wallet service is not configured.'), 503);
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            wp_send_json_error(array('message' => 'Not logged in.'), 401);
        }

        $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0.0;
        $note   = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : null;

        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'Amount must be greater than zero.'), 400);
        }

        try {
            $transaction_id = $this->get_woowallet_client()->credit($user->user_email, $amount, $note);
            wp_send_json_success(array('transaction_id' => $transaction_id));
        } catch (WooWalletAuthException $e) {
            wp_send_json_error(array('message' => 'Authentication failed.'), 401);
        } catch (WooWalletValidationException $e) {
            wp_send_json_error(array('message' => $e->getMessage()), 400);
        } catch (WooWalletException $e) {
            error_log('WooWallet credit error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Could not credit wallet.'), 500);
        }
    }

    /**
     * Debit the current user's wallet.
     */
    public function ajax_wallet_debit() {
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        if (!$this->get_woowallet_client()) {
            wp_send_json_error(array('message' => 'Wallet service is not configured.'), 503);
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            wp_send_json_error(array('message' => 'Not logged in.'), 401);
        }

        $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0.0;
        $note   = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : null;

        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'Amount must be greater than zero.'), 400);
        }

        try {
            $transaction_id = $this->get_woowallet_client()->debit($user->user_email, $amount, $note);
            wp_send_json_success(array('transaction_id' => $transaction_id));
        } catch (WooWalletAuthException $e) {
            wp_send_json_error(array('message' => 'Authentication failed.'), 401);
        } catch (WooWalletValidationException $e) {
            wp_send_json_error(array('message' => $e->getMessage()), 400);
        } catch (WooWalletException $e) {
            error_log('WooWallet debit error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Could not debit wallet.'), 500);
        }
    }

    /**
     * Create a WooCommerce checkout session for wallet top-up.
     * Adds the WooWallet recharge product to the cart and returns the checkout URL.
     */
    public function ajax_wallet_topup_checkout() {
        check_ajax_referer('tp_link_shortener_nonce', 'nonce');

        if (!function_exists('WC') || !function_exists('wc_get_checkout_url')) {
            wp_send_json_error(array(
                'message' => __('WooCommerce checkout is not available.', 'tp-link-shortener'),
                'code'    => 'woocommerce_unavailable',
            ), 503);
        }

        if (!function_exists('get_wallet_rechargeable_product')) {
            wp_send_json_error(array(
                'message' => __('Wallet top-up is not available right now.', 'tp-link-shortener'),
                'code'    => 'wallet_product_unavailable',
            ), 503);
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            wp_send_json_error(array(
                'message' => __('Not logged in.', 'tp-link-shortener'),
                'code'    => 'login_required',
            ), 401);
        }

        $raw_amount = isset($_POST['amount']) ? sanitize_text_field(wp_unslash($_POST['amount'])) : '';
        $amount = $raw_amount !== '' ? round((float) $raw_amount, 2) : 0.0;
        $min_amount = 5.0;
        $max_amount = 500.0;

        if ($amount <= 0) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid top-up amount.', 'tp-link-shortener'),
                'code'    => 'invalid_amount',
            ), 400);
        }

        if ($amount < $min_amount) {
            wp_send_json_error(array(
                'message' => sprintf(__('Minimum top-up amount is $%s.', 'tp-link-shortener'), number_format($min_amount, 2)),
                'code'    => 'amount_too_low',
            ), 400);
        }

        if ($amount > $max_amount) {
            wp_send_json_error(array(
                'message' => sprintf(__('Maximum top-up amount is $%s.', 'tp-link-shortener'), number_format($max_amount, 2)),
                'code'    => 'amount_too_high',
            ), 400);
        }

        $product = get_wallet_rechargeable_product();
        if (!$product || !$product->get_id()) {
            wp_send_json_error(array(
                'message' => __('Wallet recharge product is not configured.', 'tp-link-shortener'),
                'code'    => 'wallet_product_missing',
            ), 503);
        }

        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (!WC()->cart) {
            wp_send_json_error(array(
                'message' => __('Cart is not available.', 'tp-link-shortener'),
                'code'    => 'cart_unavailable',
            ), 503);
        }

        try {
            // v1 intentionally replaces any existing cart contents.
            WC()->cart->empty_cart();

            $cart_item_key = WC()->cart->add_to_cart(
                $product->get_id(),
                1,
                0,
                array(),
                array('recharge_amount' => $amount)
            );

            if (!$cart_item_key) {
                wp_send_json_error(array(
                    'message' => __('Could not add the wallet top-up to the cart.', 'tp-link-shortener'),
                    'code'    => 'add_to_cart_failed',
                ), 500);
            }

            WC()->cart->calculate_totals();

            if (method_exists(WC()->cart, 'set_session')) {
                WC()->cart->set_session();
            }

            if (isset(WC()->session) && WC()->session && method_exists(WC()->session, 'set_customer_session_cookie')) {
                WC()->session->set_customer_session_cookie(true);
            }

            wp_send_json_success(array(
                'checkout_url' => wc_get_checkout_url(),
                'amount'       => number_format($amount, 2, '.', ''),
            ));
        } catch (\Throwable $e) {
            error_log('Wallet top-up checkout error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Could not start checkout. Please try again.', 'tp-link-shortener'),
                'code'    => 'topup_checkout_failed',
            ), 500);
        }
    }

    /**
     * Render a return-to-dashboard banner for wallet recharge orders on the thank-you page.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function render_wallet_topup_return_link($order_id) {
        if (!$order_id || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $is_wallet_order = false;

        if (function_exists('is_wallet_rechargeable_order')) {
            $is_wallet_order = (bool) is_wallet_rechargeable_order($order);
        }

        if (!$is_wallet_order) {
            $recharge_product_id = (int) get_option('_woo_wallet_recharge_product');
            if ($recharge_product_id > 0) {
                foreach ($order->get_items('line_item') as $item) {
                    if ((int) $item->get_product_id() === $recharge_product_id) {
                        $is_wallet_order = true;
                        break;
                    }
                }
            }
        }

        if (!$is_wallet_order) {
            return;
        }

        $dashboard_url = home_url('/usage-dashboard/');

        echo '<style>
            .tp-wallet-return-banner { margin:2rem 0 0; padding:1.25rem 1.5rem; border:1px solid #cfe2ff; border-radius:1rem; background:linear-gradient(180deg,#f6fbff 0%,#fff 100%); box-shadow:0 18px 45px -30px rgba(30,79,159,.35); text-align:center; }
            .tp-wallet-return-banner__title { margin:0 0 .35rem; color:#1c4f9f; font-size:1.05rem; font-weight:700; }
            .tp-wallet-return-banner__text { margin:0 0 1rem; color:#5f6f8c; }
            .tp-wallet-return-banner .button { background:#3c7ae5; border-color:#3c7ae5; color:#fff; border-radius:999px; padding:.8rem 1.4rem; font-weight:700; }
            .tp-wallet-return-banner .button:hover,.tp-wallet-return-banner .button:focus { background:#1c4f9f; border-color:#1c4f9f; color:#fff; }
        </style>';

        echo '<section class="tp-wallet-return-banner">';
        echo '<p class="tp-wallet-return-banner__title">' . esc_html__('Wallet top-up complete', 'tp-link-shortener') . '</p>';
        echo '<p class="tp-wallet-return-banner__text">' . esc_html__('You can return to your usage dashboard to review your updated balance.', 'tp-link-shortener') . '</p>';
        echo '<a class="button wc-forward" href="' . esc_url($dashboard_url) . '">' . esc_html__('Return to Dashboard', 'tp-link-shortener') . '</a>';
        echo '</section>';
    }
}
