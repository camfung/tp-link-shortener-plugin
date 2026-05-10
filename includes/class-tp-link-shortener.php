<?php
/**
 * Main plugin class
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TP_Link_Shortener {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Assets handler
     */
    private $assets;

    /**
     * Shortcode handler
     */
    private $shortcode;

    /**
     * Dashboard shortcode handler
     */
    private $dashboard_shortcode;

    /**
     * Client links shortcode handler
     */
    private $client_links_shortcode;

    /**
     * Usage dashboard shortcode handler
     */
    private $usage_dashboard_shortcode;

    /**
     * Admin settings
     */
    private $admin;

    /**
     * API handler
     */
    private $api_handler;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize components
        $this->assets = new TP_Assets();
        $this->api_handler = new TP_API_Handler();
        $this->shortcode = new TP_Shortcode($this->assets);
        $this->dashboard_shortcode = new TP_Dashboard_Shortcode();
        $this->client_links_shortcode = new TP_Client_Links_Shortcode();
        $this->usage_dashboard_shortcode = new TP_Usage_Dashboard_Shortcode();
        $this->admin = new TP_Admin_Settings();

        // Register hooks
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this->assets, 'enqueue_assets'));
        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        add_action('admin_init', array($this->admin, 'register_settings'));
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'tp-link-shortener',
            false,
            dirname(TP_LINK_SHORTENER_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Get API key from WordPress config
     */
    public static function get_api_key(): string {
        // Try to get from wp-config.php constant
        if (defined('API_KEY')) {
            return API_KEY;
        }

        // Fallback to empty string
        return '';
    }

    /**
     * Get API endpoint
     */
    public static function get_api_endpoint(): string {
        if (defined('TP_API_ENDPOINT')) {
            return TP_API_ENDPOINT;
        }

        return 'https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev';
    }

    /**
     * Get the Traffic Portal uid for API calls.
     *
     * For logged-in users, resolves the WP user ID to a TP uid via
     * the POST /users/ endpoint (idempotent — creates if missing).
     * The TP uid is cached in user meta to avoid repeated API calls.
     */
    public static function get_user_id(): int {
        if (!is_user_logged_in()) {
            return -1;
        }

        $wp_user_id = (int) get_current_user_id();

        // Check cached TP uid in user meta
        $tp_uid = get_user_meta($wp_user_id, 'tp_uid', true);
        if (!empty($tp_uid)) {
            return (int) $tp_uid;
        }

        // No cached uid — resolve via API
        try {
            $api_endpoint = self::get_api_endpoint();
            $api_key = self::get_api_key();

            if (empty($api_key)) {
                error_log('TP Link Shortener: Cannot resolve TP uid — API_KEY not configured');
                return $wp_user_id; // fallback to WP ID
            }

            $client = new \TrafficPortal\TrafficPortalApiClient($api_endpoint, $api_key);
            $user = $client->ensureUser($wp_user_id);

            $tp_uid = (int) $user['uid'];
            update_user_meta($wp_user_id, 'tp_uid', $tp_uid);

            error_log("TP Link Shortener: Resolved WP user {$wp_user_id} -> TP uid {$tp_uid}");
            return $tp_uid;

        } catch (\Exception $e) {
            error_log('TP Link Shortener: ensureUser failed for WP user ' . $wp_user_id . ': ' . $e->getMessage());
            return $wp_user_id; // fallback to WP ID
        }
    }

    /**
     * Get domain for short links
     */
    public static function get_domain(): string {
        return get_option('tp_link_shortener_domain', 'dev.trfc.link');
    }

    /**
     * Check if premium-only mode is enabled
     */
    public static function is_premium_only(): bool {
        return (bool) get_option('tp_link_shortener_premium_only', false);
    }

    /**
     * Check if Gemini-powered short code generation is enabled
     */
    public static function use_gemini_generation(): bool {
        return (bool) get_option('tp_link_shortener_use_gemini', false);
    }

    /**
     * Check if QR code generation is enabled
     */
    public static function is_qr_code_enabled(): bool {
        return (bool) get_option('tp_link_shortener_enable_qr_code', true);
    }

    /**
     * Check if screenshot capture is enabled
     */
    public static function is_screenshot_enabled(): bool {
        return (bool) get_option('tp_link_shortener_enable_screenshot', true);
    }

    /**
     * Check if expiry timer display is enabled
     */
    public static function is_expiry_timer_enabled(): bool {
        return (bool) get_option('tp_link_shortener_enable_expiry_timer', true);
    }

    /**
     * Get usage stats polling interval in milliseconds
     */
    public static function get_usage_polling_interval(): int {
        $seconds = (int) get_option('tp_link_shortener_usage_polling_interval', 1);
        return $seconds * 1000;
    }

    /**
     * Get dashboard page size
     */
    public static function get_dashboard_page_size(): int {
        return (int) get_option('tp_link_shortener_dashboard_page_size', 10);
    }
}
