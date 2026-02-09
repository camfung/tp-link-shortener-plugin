<?php
/**
 * Windowed logging helper and REST API for logs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the log file path for the current 10-minute window.
 *
 * Files are named like: tp-debug-2026-02-08-1430.log
 * Each file covers a 10-minute window (e.g. 14:30–14:39).
 */
function tp_get_log_file_path(): string {
    $now = time();
    $minute = (int) date('i', $now);
    $window_minute = str_pad((string) (floor($minute / 10) * 10), 2, '0', STR_PAD_LEFT);
    $filename = sprintf('tp-debug-%s-%s%s.log', date('Y-m-d', $now), date('H', $now), $window_minute);
    return WP_CONTENT_DIR . '/plugins/' . $filename;
}

class TP_Logs_API {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes(): void {
        register_rest_route('tp-link-shortener/v1', '/logs', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_logs'),
            'permission_callback' => array($this, 'check_api_key'),
            'args'                => array(
                'windows' => array(
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (int) $value >= 1 && (int) $value <= 144;
                    },
                ),
            ),
        ));
    }

    public function check_api_key(\WP_REST_Request $request): bool {
        if (!defined('TP_LOGS_API_KEY')) {
            return false;
        }

        $provided_key = $request->get_header('x-api-key');
        if (empty($provided_key)) {
            return false;
        }

        return hash_equals(TP_LOGS_API_KEY, $provided_key);
    }

    public function get_logs(\WP_REST_Request $request): \WP_REST_Response {
        $num_windows = (int) $request->get_param('windows');
        $now = time();

        $windows = array();
        $total_entries = 0;

        for ($i = 0; $i < $num_windows; $i++) {
            $offset_seconds = $i * 600; // 10 minutes
            $window_time = $now - $offset_seconds;

            $minute = (int) date('i', $window_time);
            $window_minute = str_pad((string) (floor($minute / 10) * 10), 2, '0', STR_PAD_LEFT);
            $window_label = date('Y-m-d', $window_time) . '-' . date('H', $window_time) . $window_minute;
            $filename = sprintf('tp-debug-%s.log', $window_label);
            $filepath = WP_CONTENT_DIR . '/plugins/' . $filename;

            $entries = array();
            if (file_exists($filepath)) {
                $contents = file_get_contents($filepath);
                if ($contents !== false && $contents !== '') {
                    $entries = array_filter(explode("\n", $contents), function ($line) {
                        return $line !== '';
                    });
                    $entries = array_values($entries);
                }
            }

            $total_entries += count($entries);

            $windows[] = array(
                'window'  => $window_label,
                'entries' => $entries,
            );
        }

        // Current window label
        $current_minute = (int) date('i', $now);
        $current_window_minute = str_pad((string) (floor($current_minute / 10) * 10), 2, '0', STR_PAD_LEFT);
        $current_window = date('Y-m-d', $now) . '-' . date('H', $now) . $current_window_minute;

        return new \WP_REST_Response(array(
            'windows'        => $windows,
            'total_entries'  => $total_entries,
            'current_window' => $current_window,
        ), 200);
    }
}
