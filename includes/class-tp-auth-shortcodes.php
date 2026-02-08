<?php
/**
 * Auth Shortcodes — [tp_register], [tp_login], [tp_profile]
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TP_Auth_Shortcodes {

    /**
     * Auth handler instance
     */
    private $auth_handler;

    /**
     * Constructor
     */
    public function __construct(TP_Auth_Handler $auth_handler) {
        $this->auth_handler = $auth_handler;

        add_shortcode('tp_register', array($this, 'render_register'));
        add_shortcode('tp_login', array($this, 'render_login'));
        add_shortcode('tp_profile', array($this, 'render_profile'));
    }

    /**
     * Render registration form
     */
    public function render_register($atts): string {
        $atts = shortcode_atts(array(
            'redirect'     => '',
            'login_url'    => '',
        ), $atts);

        $this->enqueue_auth_assets();

        if (is_user_logged_in()) {
            return '<div class="tp-auth-container"><div class="tp-card tp-auth-card text-center p-4">'
                . '<i class="fas fa-check-circle fa-2x mb-3" style="color: var(--tp-accent);"></i>'
                . '<p class="mb-0">' . esc_html__('You are already logged in.', 'tp-link-shortener') . '</p>'
                . '</div></div>';
        }

        $uwp_fields = $this->auth_handler->get_uwp_form_fields('register');

        ob_start();
        include TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/register-template.php';
        return ob_get_clean();
    }

    /**
     * Render login form
     */
    public function render_login($atts): string {
        $atts = shortcode_atts(array(
            'redirect'     => '',
            'register_url' => '',
        ), $atts);

        $this->enqueue_auth_assets();

        if (is_user_logged_in()) {
            return '<div class="tp-auth-container"><div class="tp-card tp-auth-card text-center p-4">'
                . '<i class="fas fa-check-circle fa-2x mb-3" style="color: var(--tp-accent);"></i>'
                . '<p class="mb-0">' . esc_html__('You are already logged in.', 'tp-link-shortener') . '</p>'
                . '</div></div>';
        }

        ob_start();
        include TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/login-template.php';
        return ob_get_clean();
    }

    /**
     * Render profile edit form
     */
    public function render_profile($atts): string {
        if (!is_user_logged_in()) {
            return '';
        }

        $atts = shortcode_atts(array(
            'redirect' => '',
        ), $atts);

        $this->enqueue_auth_assets();

        $uwp_fields = $this->auth_handler->get_uwp_form_fields('profile');
        $current_user = wp_get_current_user();

        ob_start();
        include TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/profile-template.php';
        return ob_get_clean();
    }

    /**
     * Enqueue auth-specific assets
     */
    private function enqueue_auth_assets(): void {
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

        // Auth CSS
        wp_enqueue_style(
            'tp-auth',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/css/auth.css',
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

        // Auth JS
        wp_enqueue_script(
            'tp-auth-js',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/js/auth.js',
            array('jquery', 'tp-bootstrap-js'),
            TP_LINK_SHORTENER_VERSION,
            true
        );

        // Localize
        wp_localize_script('tp-auth-js', 'tpAuth', array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tp_auth_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'logoutUrl'  => wp_logout_url($this->get_logout_redirect()),
            'strings'  => array(
                'registering'     => __('Creating account...', 'tp-link-shortener'),
                'loggingIn'       => __('Logging in...', 'tp-link-shortener'),
                'saving'          => __('Saving...', 'tp-link-shortener'),
                'loading'         => __('Loading...', 'tp-link-shortener'),
                'registerSuccess' => __('Account created successfully!', 'tp-link-shortener'),
                'loginSuccess'    => __('Login successful!', 'tp-link-shortener'),
                'profileSaved'    => __('Profile updated successfully!', 'tp-link-shortener'),
                'error'           => __('Something went wrong. Please try again.', 'tp-link-shortener'),
                'passwordWeak'    => __('Weak', 'tp-link-shortener'),
                'passwordFair'    => __('Fair', 'tp-link-shortener'),
                'passwordGood'    => __('Good', 'tp-link-shortener'),
                'passwordStrong'  => __('Strong', 'tp-link-shortener'),
                'required'        => __('This field is required.', 'tp-link-shortener'),
                'invalidEmail'    => __('Please enter a valid email address.', 'tp-link-shortener'),
                'passwordShort'   => __('Password must be at least 8 characters.', 'tp-link-shortener'),
                'passwordMismatch' => __('Passwords do not match.', 'tp-link-shortener'),
            ),
        ));
    }

    /**
     * Get logout redirect URL
     */
    private function get_logout_redirect(): string {
        $url = get_option('tp_link_shortener_logout_redirect', '');
        return !empty($url) ? $url : home_url('/');
    }
}
