<?php
/**
 * Auth Handler - AJAX endpoints for register, login, profile
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TP_Auth_Handler {

    /**
     * Constructor — register AJAX actions
     */
    public function __construct() {
        // Public (nopriv) endpoints
        add_action('wp_ajax_nopriv_tp_register', array($this, 'handle_register'));
        add_action('wp_ajax_nopriv_tp_login', array($this, 'handle_login'));

        // Authenticated endpoints
        add_action('wp_ajax_tp_update_profile', array($this, 'handle_update_profile'));
        add_action('wp_ajax_tp_get_profile', array($this, 'handle_get_profile'));
    }

    /**
     * Handle user registration
     */
    public function handle_register(): void {
        check_ajax_referer('tp_auth_nonce', 'nonce');

        // Rate limit: 5 registrations per hour per IP
        $ip = $this->get_client_ip();
        $rate_key = 'tp_reg_' . md5($ip);
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 5) {
            wp_send_json_error(array('message' => __('Too many registration attempts. Please try again later.', 'tp-link-shortener')));
            return;
        }

        $username = sanitize_user(wp_unslash($_POST['username'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $password = wp_unslash($_POST['password'] ?? '');
        $password_confirm = wp_unslash($_POST['password_confirm'] ?? '');

        // Validate required fields
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(array('message' => __('All required fields must be filled.', 'tp-link-shortener')));
            return;
        }

        if ($password !== $password_confirm) {
            wp_send_json_error(array('message' => __('Passwords do not match.', 'tp-link-shortener')));
            return;
        }

        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => __('Password must be at least 8 characters.', 'tp-link-shortener')));
            return;
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'tp-link-shortener')));
            return;
        }

        if (username_exists($username)) {
            wp_send_json_error(array('message' => __('This username is already taken.', 'tp-link-shortener')));
            return;
        }

        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('This email is already registered.', 'tp-link-shortener')));
            return;
        }

        // Create user
        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'role'       => 'subscriber',
        ));

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
            return;
        }

        // Store UWP custom fields
        $this->save_uwp_meta($user_id, 'register');

        // Increment rate limit
        set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);

        // Auto-login
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        // Determine redirect
        $redirect = $this->get_redirect_url('register');

        wp_send_json_success(array(
            'message'  => __('Registration successful!', 'tp-link-shortener'),
            'redirect' => $redirect,
        ));
    }

    /**
     * Handle user login
     */
    public function handle_login(): void {
        check_ajax_referer('tp_auth_nonce', 'nonce');

        // Rate limit: 10 login attempts per 15 minutes per IP
        $ip = $this->get_client_ip();
        $rate_key = 'tp_login_' . md5($ip);
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 10) {
            wp_send_json_error(array('message' => __('Too many login attempts. Please try again in 15 minutes.', 'tp-link-shortener')));
            return;
        }

        $username = sanitize_user(wp_unslash($_POST['username'] ?? ''));
        $password = wp_unslash($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        if (empty($username) || empty($password)) {
            wp_send_json_error(array('message' => __('Please enter your username and password.', 'tp-link-shortener')));
            return;
        }

        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        );

        $user = wp_signon($creds, is_ssl());

        // Increment rate limit regardless of outcome
        set_transient($rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);

        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => __('Invalid username or password.', 'tp-link-shortener')));
            return;
        }

        wp_set_current_user($user->ID);

        $redirect = $this->get_redirect_url('login');

        wp_send_json_success(array(
            'message'  => __('Login successful!', 'tp-link-shortener'),
            'redirect' => $redirect,
        ));
    }

    /**
     * Handle profile update
     */
    public function handle_update_profile(): void {
        check_ajax_referer('tp_auth_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'tp-link-shortener')));
            return;
        }

        $userdata = array('ID' => $user_id);

        // Update email if changed
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if (!empty($email)) {
            $current_user = get_userdata($user_id);
            if ($email !== $current_user->user_email) {
                if (!is_email($email)) {
                    wp_send_json_error(array('message' => __('Please enter a valid email address.', 'tp-link-shortener')));
                    return;
                }
                $existing = email_exists($email);
                if ($existing && $existing !== $user_id) {
                    wp_send_json_error(array('message' => __('This email is already in use.', 'tp-link-shortener')));
                    return;
                }
                $userdata['user_email'] = $email;
            }
        }

        // Update first/last name
        if (isset($_POST['first_name'])) {
            $userdata['first_name'] = sanitize_text_field(wp_unslash($_POST['first_name']));
        }
        if (isset($_POST['last_name'])) {
            $userdata['last_name'] = sanitize_text_field(wp_unslash($_POST['last_name']));
        }

        // Handle password change
        $new_password = wp_unslash($_POST['new_password'] ?? '');
        if (!empty($new_password)) {
            $current_password = wp_unslash($_POST['current_password'] ?? '');
            $confirm_password = wp_unslash($_POST['confirm_password'] ?? '');

            if (empty($current_password)) {
                wp_send_json_error(array('message' => __('Please enter your current password to set a new one.', 'tp-link-shortener')));
                return;
            }

            $current_user = get_userdata($user_id);
            if (!wp_check_password($current_password, $current_user->user_pass, $user_id)) {
                wp_send_json_error(array('message' => __('Current password is incorrect.', 'tp-link-shortener')));
                return;
            }

            if ($new_password !== $confirm_password) {
                wp_send_json_error(array('message' => __('New passwords do not match.', 'tp-link-shortener')));
                return;
            }

            if (strlen($new_password) < 8) {
                wp_send_json_error(array('message' => __('New password must be at least 8 characters.', 'tp-link-shortener')));
                return;
            }

            $userdata['user_pass'] = $new_password;
        }

        $result = wp_update_user($userdata);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        // Update UWP custom fields
        $this->save_uwp_meta($user_id, 'profile');

        wp_send_json_success(array(
            'message' => __('Profile updated successfully!', 'tp-link-shortener'),
        ));
    }

    /**
     * Handle get profile data
     */
    public function handle_get_profile(): void {
        check_ajax_referer('tp_auth_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'tp-link-shortener')));
            return;
        }

        $user = get_userdata($user_id);

        $data = array(
            'username'   => $user->user_login,
            'email'      => $user->user_email,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
        );

        // Get UWP custom field values
        $fields = $this->get_uwp_form_fields('profile');
        foreach ($fields as $field) {
            $key = $field->htmlvar_name;
            $data['uwp_' . $key] = get_user_meta($user_id, 'uwp_meta_' . $key, true);
        }

        wp_send_json_success($data);
    }

    /**
     * Get UWP form fields filtered by admin settings
     *
     * @param string $form_type 'register' or 'profile'
     * @return array
     */
    public function get_uwp_form_fields(string $form_type): array {
        global $wpdb;

        $table = $wpdb->prefix . 'uwp_form_fields';

        // Check table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return array();
        }

        $option_key = $form_type === 'register'
            ? 'tp_link_shortener_register_fields'
            : 'tp_link_shortener_profile_fields';

        $enabled_fields = get_option($option_key, array());
        if (empty($enabled_fields) || !is_array($enabled_fields)) {
            return array();
        }

        // Query UWP fields table
        $results = $wpdb->get_results(
            "SELECT htmlvar_name, site_title, field_type, is_required, option_values, field_icon, css_class
             FROM {$table}
             WHERE form_type = 'account'
             AND is_active = 1
             ORDER BY sort_order ASC"
        );

        if (empty($results)) {
            return array();
        }

        // Filter by admin-enabled fields
        return array_filter($results, function ($field) use ($enabled_fields) {
            return in_array($field->htmlvar_name, $enabled_fields, true);
        });
    }

    /**
     * Save UWP meta fields from POST data
     */
    private function save_uwp_meta(int $user_id, string $form_type): void {
        $fields = $this->get_uwp_form_fields($form_type);

        foreach ($fields as $field) {
            $key = $field->htmlvar_name;
            $post_key = 'uwp_' . $key;

            if (!isset($_POST[$post_key])) {
                continue;
            }

            $value = wp_unslash($_POST[$post_key]);

            // Sanitize based on field type
            switch ($field->field_type) {
                case 'email':
                    $value = sanitize_email($value);
                    break;
                case 'url':
                    $value = esc_url_raw($value);
                    break;
                case 'textarea':
                    $value = sanitize_textarea_field($value);
                    break;
                case 'number':
                    $value = intval($value);
                    break;
                case 'multiselect':
                    $value = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
                    break;
                default:
                    $value = sanitize_text_field($value);
                    break;
            }

            update_user_meta($user_id, 'uwp_meta_' . $key, $value);
        }
    }

    /**
     * Get redirect URL based on priority: POST param > admin setting > current page
     */
    private function get_redirect_url(string $type): string {
        // Check POST param (from shortcode attribute)
        if (!empty($_POST['redirect_url'])) {
            return esc_url_raw(wp_unslash($_POST['redirect_url']));
        }

        // Check admin setting
        $option_key = 'tp_link_shortener_' . $type . '_redirect';
        $admin_redirect = get_option($option_key, '');
        if (!empty($admin_redirect)) {
            return esc_url_raw($admin_redirect);
        }

        // Fallback to home
        return home_url('/');
    }

    /**
     * Get client IP address
     */
    private function get_client_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return sanitize_text_field($ip);
    }
}
