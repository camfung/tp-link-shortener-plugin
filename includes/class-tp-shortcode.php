<?php
/**
 * Shortcode Handler
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TP_Shortcode {

    /**
     * Assets handler
     */
    private $assets;

    /**
     * Constructor
     */
    public function __construct(TP_Assets $assets) {
        $this->assets = $assets;
        add_shortcode('tp_link_shortener', array($this, 'render_shortcode'));
    }

    /**
     * Render the shortcode
     */
    public function render_shortcode($atts): string {
        $atts = shortcode_atts(array(
            'domain' => TP_Link_Shortener::get_domain(),
        ), $atts);

        // Enqueue assets
        $this->assets->enqueue_assets();

        // Start output buffering
        ob_start();

        // Include template
        include TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/shortcode-template.php';

        return ob_get_clean();
    }

    /**
     * Get screenshot image URL from data
     *
     * @param array $screenshot_data Array containing screenshot_base64, cached, response_time_ms
     * @return string The image URL or data URI
     */
    public static function get_screenshot_image($screenshot_data): string {
        // Check if we have base64 data
        if (isset($screenshot_data['screenshot_base64']) && !empty($screenshot_data['screenshot_base64'])) {
            // Return as data URI
            return 'data:image/png;base64,' . $screenshot_data['screenshot_base64'];
        }

        // Fallback to Orange-cat.jpg if no screenshot data
        $plugin_dir_url = plugin_dir_url(TP_LINK_SHORTENER_PLUGIN_DIR . 'tp-link-shortener.php');
        return $plugin_dir_url . 'Orange-cat.jpg';
    }

    /**
     * Render screenshot preview image
     *
     * @param array $screenshot_data Array containing screenshot_base64, cached, response_time_ms
     * @return void
     */
    public static function render_screenshot_preview($screenshot_data = null): void {
        // Use default data pointing to Orange-cat.jpg for now
        if ($screenshot_data === null) {
            $screenshot_data = array(
                'screenshot_base64' => '',
                'cached' => false,
                'response_time_ms' => 0
            );
        }

        $image_url = self::get_screenshot_image($screenshot_data);
        $cached_text = isset($screenshot_data['cached']) && $screenshot_data['cached'] ? 'Cached' : 'Fresh';
        $response_time = isset($screenshot_data['response_time_ms']) ? $screenshot_data['response_time_ms'] . 'ms' : 'N/A';

        echo '<div class="tp-screenshot-preview">';
        echo '<img src="' . esc_url($image_url) . '" alt="URL Preview" class="tp-screenshot-img" />';
        echo '<div class="tp-screenshot-meta">';
        echo '<span class="tp-screenshot-badge">' . esc_html($cached_text) . '</span>';
        echo '<span class="tp-screenshot-time">' . esc_html($response_time) . '</span>';
        echo '</div>';
        echo '</div>';
    }
}
