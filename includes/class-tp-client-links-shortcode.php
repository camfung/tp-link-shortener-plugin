<?php
/**
 * Client Links Shortcode Handler
 * Link management page with sortable columns, date range filtering,
 * per-link performance chart, and change history tracking.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class TP_Client_Links_Shortcode {

    public function __construct() {
        add_shortcode('tp_client_links', array($this, 'render_shortcode'));
    }

    public function render_shortcode($atts): string {
        if (!is_user_logged_in()) {
            return '';
        }

        $atts = shortcode_atts(array(
            'page_size'    => TP_Link_Shortener::get_dashboard_page_size(),
            'show_search'  => 'true',
            'show_filters' => 'true',
        ), $atts);

        $this->enqueue_assets();

        ob_start();
        include TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/client-links-template.php';
        return ob_get_clean();
    }

    private function enqueue_assets() {
        // Bootstrap CSS
        wp_enqueue_style(
            'tp-bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
            array(),
            '5.3.0'
        );

        // Font Awesome
        wp_enqueue_style(
            'tp-fontawesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            array(),
            '6.4.0'
        );

        // Base frontend CSS for shared styles/variables
        wp_enqueue_style(
            'tp-link-shortener',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/css/frontend.css',
            array('tp-bootstrap'),
            TP_LINK_SHORTENER_VERSION
        );

        // Client links CSS
        wp_enqueue_style(
            'tp-client-links',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/css/client-links.css',
            array('tp-bootstrap', 'tp-link-shortener'),
            TP_LINK_SHORTENER_VERSION
        );

        // Bootstrap JS
        wp_enqueue_script(
            'tp-bootstrap-js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
            array('jquery'),
            '5.3.0',
            true
        );

        // Chart.js
        wp_enqueue_script(
            'tp-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // QRCode.js
        wp_enqueue_script(
            'tp-qrcode',
            'https://cdn.jsdelivr.net/npm/qrcodejs2@0.0.2/qrcode.min.js',
            array(),
            '0.0.2',
            true
        );

        // QR utilities
        wp_enqueue_script(
            'tp-qr-utils',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/js/qr-utils.js',
            array('jquery', 'tp-qrcode'),
            TP_LINK_SHORTENER_VERSION,
            true
        );

        // Client links JS
        wp_enqueue_script(
            'tp-client-links-js',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/js/client-links.js',
            array('jquery', 'tp-bootstrap-js', 'tp-chartjs', 'tp-qrcode', 'tp-qr-utils'),
            TP_LINK_SHORTENER_VERSION,
            true
        );

        // Default date range: last 30 days
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));

        wp_localize_script('tp-client-links-js', 'tpClientLinks', array(
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('tp_link_shortener_nonce'),
            'domain'     => TP_Link_Shortener::get_domain(),
            'isLoggedIn' => is_user_logged_in(),
            'dateRange'  => array(
                'start' => $start_date,
                'end'   => $end_date,
            ),
            'strings' => array(
                'loading'        => __('Loading...', 'tp-link-shortener'),
                'noResults'      => __('No links found.', 'tp-link-shortener'),
                'error'          => __('Error loading links. Please try again.', 'tp-link-shortener'),
                'loginRequired'  => __('Please log in to view your links.', 'tp-link-shortener'),
                'copied'         => __('Copied!', 'tp-link-shortener'),
                'confirmDelete'  => __('Are you sure you want to delete this link?', 'tp-link-shortener'),
                'confirmDisable' => __('Disable this link? It will stop redirecting.', 'tp-link-shortener'),
                'active'         => __('Active', 'tp-link-shortener'),
                'disabled'       => __('Disabled', 'tp-link-shortener'),
                'all'            => __('All', 'tp-link-shortener'),
                'enabled'        => __('Link enabled successfully.', 'tp-link-shortener'),
                'disabledMsg'    => __('Link disabled successfully.', 'tp-link-shortener'),
            ),
        ));
    }
}
