<?php
/**
 * Dashboard Shortcode Handler
 * POC for displaying user's map items in a paginated table
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TP_Dashboard_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('tp_link_dashboard', array($this, 'render_shortcode'));
    }

    /**
     * Render the dashboard shortcode
     */
    public function render_shortcode($atts): string {
        $atts = shortcode_atts(array(
            'page_size' => 10,
            'show_search' => 'true',
            'show_filters' => 'true',
        ), $atts);

        // Enqueue dashboard assets
        $this->enqueue_assets();

        // Start output buffering
        ob_start();

        // Include template
        include TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/dashboard-template.php';

        return ob_get_clean();
    }

    /**
     * Enqueue dashboard-specific assets
     */
    private function enqueue_assets() {
        // Enqueue Bootstrap CSS
        wp_enqueue_style(
            'tp-bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
            array(),
            '5.3.0'
        );

        // Enqueue Font Awesome
        wp_enqueue_style(
            'tp-fontawesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            array(),
            '6.4.0'
        );

        // Enqueue dashboard CSS
        wp_enqueue_style(
            'tp-dashboard',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/css/dashboard.css',
            array('tp-bootstrap'),
            TP_LINK_SHORTENER_VERSION
        );

        // Enqueue Bootstrap JS
        wp_enqueue_script(
            'tp-bootstrap-js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
            array('jquery'),
            '5.3.0',
            true
        );

        // Enqueue dashboard JS
        wp_enqueue_script(
            'tp-dashboard-js',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/js/dashboard.js',
            array('jquery', 'tp-bootstrap-js'),
            TP_LINK_SHORTENER_VERSION,
            true
        );

        // Localize script with AJAX URL and settings
        wp_localize_script('tp-dashboard-js', 'tpDashboard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tp_link_shortener_nonce'),
            'domain' => TP_Link_Shortener::get_domain(),
            'isLoggedIn' => is_user_logged_in(),
            'strings' => array(
                'loading' => __('Loading...', 'tp-link-shortener'),
                'noResults' => __('No links found.', 'tp-link-shortener'),
                'error' => __('Error loading links. Please try again.', 'tp-link-shortener'),
                'loginRequired' => __('Please log in to view your links.', 'tp-link-shortener'),
                'copied' => __('Copied!', 'tp-link-shortener'),
                'confirmDelete' => __('Are you sure you want to delete this link?', 'tp-link-shortener'),
                'active' => __('Active', 'tp-link-shortener'),
                'disabled' => __('Disabled', 'tp-link-shortener'),
                'all' => __('All', 'tp-link-shortener'),
            ),
        ));
    }
}
