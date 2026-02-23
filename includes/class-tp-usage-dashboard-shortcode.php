<?php
/**
 * Usage Dashboard Shortcode Handler
 * Usage tracking page with chart, summary stats, and daily breakdown table.
 * Gates unauthenticated users behind wp_login_form().
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class TP_Usage_Dashboard_Shortcode {

    public function __construct() {
        add_shortcode('tp_usage_dashboard', array($this, 'render_shortcode'));
    }

    public function render_shortcode($atts): string {
        if (!is_user_logged_in()) {
            $login_form = wp_login_form(array(
                'echo'     => false,
                'redirect' => get_permalink(),
                'remember' => true,
            ));
            return '<div class="tp-ud-login-wrapper">' . $login_form . '</div>';
        }

        $atts = shortcode_atts(array(
            'days' => 30,
        ), $atts);

        $this->enqueue_assets($atts);

        ob_start();
        include TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/usage-dashboard-template.php';
        return ob_get_clean();
    }

    private function enqueue_assets(array $atts): void {
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

        // Usage dashboard CSS
        wp_enqueue_style(
            'tp-usage-dashboard',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/css/usage-dashboard.css',
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

        // Usage dashboard JS
        wp_enqueue_script(
            'tp-usage-dashboard-js',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/js/usage-dashboard.js',
            array('jquery', 'tp-bootstrap-js', 'tp-chartjs'),
            TP_LINK_SHORTENER_VERSION,
            true
        );

        // Default date range based on days attribute
        $days = intval($atts['days']);
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        wp_localize_script('tp-usage-dashboard-js', 'tpUsageDashboard', array(
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('tp_link_shortener_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'isAdmin'    => current_user_can('manage_options'),
            'dateRange'  => array(
                'start' => $start_date,
                'end'   => $end_date,
            ),
            'strings' => array(
                'loading' => __('Loading usage data...', 'tp-link-shortener'),
                'error'   => __('Error loading usage data. Please try again.', 'tp-link-shortener'),
                'noData'  => __('No usage data available for the selected period.', 'tp-link-shortener'),
                'retry'   => __('Retry', 'tp-link-shortener'),
            ),
        ));
    }
}
