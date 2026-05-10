<?php
/**
 * Intro Key Handler
 *
 * Ported from the standalone "TP User Manager" plugin. Provides:
 *   - AJAX action `create_introKey` that POSTs an intro item to the TP API.
 *   - `[test_intro_key]` shortcode that renders a test form for the AJAX call.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class TP_Intro_Key_Handler {

    public function __construct() {
        add_action('wp_ajax_create_introKey', array($this, 'create_intro_key'));
        add_action('wp_ajax_nopriv_create_introKey', array($this, 'create_intro_key'));
        add_shortcode('test_intro_key', array($this, 'test_intro_key_shortcode'));
    }

    /**
     * AJAX handler for creating intro keys.
     */
    public function create_intro_key() {
        if (!wp_doing_ajax()) {
            wp_die('Invalid request', 'Error', array('response' => 400));
        }

        $api_base_url = defined('API_BASE_URL') ? API_BASE_URL : '';

        if (empty($api_base_url)) {
            wp_send_json_error('API base URL not configured', 500);
            return;
        }

        $parsed_url = parse_url($api_base_url);
        $domain = $parsed_url['host'] ?? '';

        if (isset($parsed_url['path']) && $parsed_url['path'] !== '/') {
            $domain .= $parsed_url['path'];
        }

        $tp_key  = sanitize_text_field($_REQUEST['tpKey'] ?? '');
        $tp_dest = esc_url_raw($_REQUEST['tpDest'] ?? '');

        if (empty($tp_key) || empty($tp_dest)) {
            wp_send_json_error('Missing required parameters: tpKey or tpDest', 400);
            return;
        }

        $user_id = is_user_logged_in() ? (int) get_current_user_id() : 0;

        $api_data = array(
            'domain'        => $domain,
            'tpKey'         => $tp_key,
            'destination'   => $tp_dest,
            'type'          => '',
            'is_set'        => 0,
            'status'        => 'intro',
            'notes'         => '',
            'tags'          => '',
            'settings'      => '',
            'uid'           => $user_id,
            'tpTkn'         => 'MkmFJGQJlCyAuFWkkIiG',
            'cache_content' => 0,
        );

        $response = wp_remote_post($api_base_url . '/items/', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key'    => $_ENV['API_KEY'] ?? '',
            ),
            'body'    => wp_json_encode($api_data),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'API request failed',
                'error'   => $response->get_error_message(),
            ), 500);
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        $result = array(
            'http_code' => $response_code,
            'data'      => $decoded_response,
        );

        if ($response_code >= 200 && $response_code < 300) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result, $response_code);
        }
    }

    /**
     * Shortcode for testing the intro key API.
     */
    public function test_intro_key_shortcode($atts) {
        wp_enqueue_script('jquery');

        $atts = shortcode_atts(array(
            'tp_key'  => 'camtest1',
            'tp_dest' => 'https://github.com/camfung',
        ), $atts);

        ob_start();
        ?>
        <div id="intro-key-test">
            <h3>Test Create Intro Key</h3>
            <form id="intro-key-form">
                <p>
                    <label for="tpKey">TP Key:</label>
                    <input type="text" id="tpKey" name="tpKey" value="<?php echo esc_attr($atts['tp_key']); ?>" required>
                </p>
                <p>
                    <label for="tpDest">Destination:</label>
                    <input type="url" id="tpDest" name="tpDest" value="<?php echo esc_attr($atts['tp_dest']); ?>" required>
                </p>
                <p>
                    <button type="submit">Test API Call</button>
                </p>
            </form>
            <div id="test-results" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; background: #f9f9f9; display: none;">
                <h4>Results:</h4>
                <pre id="results-content"></pre>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#intro-key-form').on('submit', function(e) {
                e.preventDefault();

                var data = {
                    action: 'create_introKey',
                    tpKey: $('#tpKey').val(),
                    tpDest: $('#tpDest').val(),
                    _ajax_nonce: '<?php echo esc_js(wp_create_nonce('intro_key_nonce')); ?>'
                };

                $('#test-results').show();
                $('#results-content').text('Loading...');

                $.post(ajaxurl, data, function(response) {
                    $('#results-content').text(JSON.stringify(response, null, 2));
                }).fail(function(xhr, status, error) {
                    $('#results-content').text('Error: ' + error + '\n' + xhr.responseText);
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
