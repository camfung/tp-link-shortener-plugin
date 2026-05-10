<?php
/**
 * User profile shortcode + WP login -> TP /users sync.
 *
 * Ported from the standalone "TP User Manager" plugin so the Traffic Portal
 * UI plugin owns the [user_profile] shortcode and the WP -> TP user sync.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class TP_User_Profile_Shortcode {

    public function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_login', array($this, 'send_user_data_on_login'), 10, 2);
        add_filter('http_request_args', array($this, 'add_api_token_to_requests'), 10, 2);
    }

    public function register_shortcodes() {
        add_shortcode('user_profile', array($this, 'user_profile_shortcode'));
    }

    public function enqueue_assets() {
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_user_shortcode = has_shortcode($post->post_content, 'user_profile')
            || has_shortcode($post->post_content, 'test_intro_key');

        if (!$has_user_shortcode) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'tp-user-shortcode-js',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/js/tp-user-shortcode.js',
            array('jquery'),
            TP_LINK_SHORTENER_VERSION,
            true
        );
        wp_localize_script('tp-user-shortcode-js', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('user_plugin_nonce'),
        ));

        wp_enqueue_style(
            'tp-user-shortcode-css',
            TP_LINK_SHORTENER_PLUGIN_URL . 'assets/css/tp-user-shortcode.css',
            array(),
            TP_LINK_SHORTENER_VERSION
        );
    }

    public function user_profile_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_avatar' => 'true',
            'avatar_size' => '96',
        ), $atts);

        if (!is_user_logged_in()) {
            return '<p>Please log in to view your profile.</p>';
        }

        $current_user = wp_get_current_user();

        ob_start();
        ?>
        <div class="user-profile-container">
            <?php if ($atts['show_avatar'] === 'true'): ?>
                <div class="user-avatar">
                    <?php echo get_avatar($current_user->ID, (int) $atts['avatar_size']); ?>
                </div>
            <?php endif; ?>

            <div class="user-info">
                <h3>Welcome, <?php echo esc_html($current_user->display_name); ?>!</h3>
                <p><strong>Username:</strong> <?php echo esc_html($current_user->user_login); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($current_user->user_email); ?></p>
                <?php if ($current_user->first_name): ?>
                    <p><strong>First Name:</strong> <?php echo esc_html($current_user->first_name); ?></p>
                <?php endif; ?>
                <?php if ($current_user->last_name): ?>
                    <p><strong>Last Name:</strong> <?php echo esc_html($current_user->last_name); ?></p>
                <?php endif; ?>
                <?php if ($current_user->ID): ?>
                    <p><strong>id:</strong> <?php echo esc_html((string) $current_user->ID); ?></p>
                <?php endif; ?>
                <p><strong>Member Since:</strong> <?php echo esc_html(date('F j, Y', strtotime($current_user->user_registered))); ?></p>
            </div>

            <div class="user-actions">
                <a href="<?php echo esc_url(wp_logout_url()); ?>" class="logout-btn">Logout</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Push the WP user id to the Traffic Portal /users endpoint on login.
     *
     * @param string  $user_login The user's login username.
     * @param WP_User $user       The WP_User object.
     */
    public function send_user_data_on_login($user_login, $user) {
        $user_id = (int) $user->ID;

        $data = array(
            'uid'      => $user_id,
            'wpUserId' => $user_id,
        );

        $api_url = 'https://dev.trfc.link/users';

        $args = array(
            'method'  => 'PUT',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode($data),
            'timeout' => 30,
        );

        $response = wp_remote_request($api_url, $args);

        if (is_wp_error($response)) {
            error_log('TP User Profile: API request failed: ' . $response->get_error_message());
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log("TP User Profile: API request completed. Status: {$response_code}, Response: {$response_body}");
    }

    /**
     * Inject the X-API-Key header on outbound requests to dev.trfc.link.
     */
    public function add_api_token_to_requests($args, $url) {
        if (strpos($url, 'dev.trfc.link') === false) {
            return $args;
        }

        if (!isset($args['headers']) || !is_array($args['headers'])) {
            $args['headers'] = array();
        }

        $args['headers']['X-API-Key'] = $_ENV['API_KEY'] ?? '';

        return $args;
    }
}
