<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Global-namespace WP stubs (must be defined before loading the class).
// We re-use the same pattern as SideloadPreviewTest.php.
// ---------------------------------------------------------------------------
namespace {

    // WP_Error class
    if (!class_exists('WP_Error')) {
        class WP_Error
        {
            public function __construct(
                public string $code    = '',
                public string $message = ''
            ) {}
            public function get_error_message(): string { return $this->message; }
        }
    }

    // TP_Link_Shortener stub
    if (!class_exists('TP_Link_Shortener')) {
        class TP_Link_Shortener
        {
            public static function get_user_id(): int    { return 42; }
            public static function get_domain(): string  { return 'dev.trfc.link'; }
            public static function is_premium_only(): bool { return false; }
        }
    }

    // WP functions stubbed globally.
    if (!function_exists('add_action')) {
        function add_action(string $hook, $cb, int $prio = 10, int $args = 1): bool { return true; }
    }
    if (!function_exists('add_filter')) {
        function add_filter(string $hook, $cb, int $prio = 10, int $args = 1): bool { return true; }
    }
    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing): bool { return $thing instanceof WP_Error; }
    }
    if (!function_exists('sanitize_url')) {
        function sanitize_url(string $url): string { return $url; }
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field(string $str): string { return $str; }
    }
    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer(string $action, string $query_arg = ''): bool { return true; }
    }
    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success($data = null): void {
            $GLOBALS['_tp_test_json_success'] = $data;
        }
    }
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data = null, int $status = 200): void {
            $GLOBALS['_tp_test_json_error'] = $data;
        }
    }
    if (!function_exists('__')) {
        function __(string $text, string $domain = 'default'): string { return $text; }
    }
    if (!function_exists('esc_html__')) {
        function esc_html__(string $text, string $domain = 'default'): string {
            return htmlspecialchars($text);
        }
    }
    if (!function_exists('wp_cache_get')) {
        function wp_cache_get(string $key, string $group = '') { return false; }
    }
    if (!function_exists('wp_cache_set')) {
        function wp_cache_set(string $key, $data, string $group = '', int $expire = 0): bool { return true; }
    }
    if (!function_exists('wp_cache_delete')) {
        function wp_cache_delete(string $key, string $group = ''): bool { return true; }
    }
    if (!function_exists('wp_cache_flush_group')) {
        function wp_cache_flush_group(string $group): bool { return true; }
    }
    if (!function_exists('current_time')) {
        function current_time(string $type): string { return date('Y-m-d H:i:s'); }
    }
    if (!function_exists('get_userdata')) {
        function get_userdata(int $uid) { return false; }
    }
    if (!function_exists('dbDelta')) {
        function dbDelta(string $sql): array { return []; }
    }
    if (!function_exists('get_site_url')) {
        function get_site_url(): string { return 'http://example.com'; }
    }
    if (!function_exists('home_url')) {
        function home_url(string $path = ''): string { return 'http://example.com' . $path; }
    }
    if (!function_exists('rest_url')) {
        function rest_url(string $path = ''): string { return 'http://example.com/wp-json/' . $path; }
    }
    if (!function_exists('update_option')) {
        function update_option(string $key, $value): bool { return true; }
    }
    if (!function_exists('get_option')) {
        function get_option(string $key, $default = false) { return $default; }
    }
    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p(string $dir): bool {
            return is_dir($dir) || mkdir($dir, 0755, true);
        }
    }
    if (!function_exists('is_user_logged_in')) {
        function is_user_logged_in(): bool {
            return $GLOBALS['_tp_test_user_logged_in'] ?? true;
        }
    }
    if (!function_exists('get_current_user_id')) {
        function get_current_user_id(): int { return 42; }
    }
    if (!function_exists('filter_var')) {
        // Use native PHP filter_var — no-op stub not needed.
    }

    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir(): array {
            return $GLOBALS['_tp_test_uploads_dir'] ?? [
                'basedir' => sys_get_temp_dir(),
                'baseurl' => 'http://example.com/wp-content/uploads',
            ];
        }
    }

    // $wpdb stub — supports replace(), prepare(), get_results().
    // This stub is shared across all Unit tests in this file.
    // get_results() is programmed per-test via $GLOBALS['_tp_test_wpdb_results'].
    if (!isset($GLOBALS['wpdb'])) {
        $GLOBALS['wpdb'] = new class {
            public string $prefix        = 'wp_';
            public array  $last_replace  = [];
            public int    $replace_count = 0;
            public array  $queries_log   = [];

            public function replace(string $table, array $data, array $formats): int|false {
                $this->last_replace  = ['table' => $table, 'data' => $data];
                $this->replace_count++;
                return 1;
            }

            public function prepare(string $sql, ...$args): string {
                // Very basic placeholder — just embed args for logging.
                $result = $sql;
                foreach ($args as $arg) {
                    if (is_array($arg)) {
                        $result = str_replace('%s', implode(',', array_map(fn($a) => "'$a'", $arg)), $result);
                    } elseif (is_int($arg)) {
                        $pos = strpos($result, '%d');
                        if ($pos !== false) {
                            $result = substr_replace($result, (string) $arg, $pos, 2);
                        }
                    } else {
                        $pos = strpos($result, '%s');
                        if ($pos !== false) {
                            $result = substr_replace($result, "'$arg'", $pos, 2);
                        }
                    }
                }
                return $result;
            }

            public function get_results(string $sql, $output = OBJECT): ?array {
                // Record every query issued.
                $this->queries_log[] = $sql;
                // Return whatever the test has programmed.
                return $GLOBALS['_tp_test_wpdb_results'] ?? [];
            }

            public function get_charset_collate(): string {
                return "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            }
        };
    }

    // DB constants.
    if (!defined('ABSPATH')) {
        define('ABSPATH', sys_get_temp_dir() . '/wp/');
    }
    if (!defined('TP_DB_VERSION')) {
        define('TP_DB_VERSION', '1.1.0');
    }
    if (!defined('TP_LINK_PREVIEWS_TABLE')) {
        define('TP_LINK_PREVIEWS_TABLE', $GLOBALS['wpdb']->prefix . 'tp_link_previews');
    }
    if (!defined('TP_LINK_SHORTENER_PLUGIN_DIR')) {
        define('TP_LINK_SHORTENER_PLUGIN_DIR', sys_get_temp_dir() . '/tp-plugin/');
    }
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }

    // Load the class under test.
    if (!class_exists('TP_API_Handler')) {
        require_once dirname(__DIR__, 2) . '/includes/class-tp-api-handler.php';
    }

} // end global namespace block

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------
namespace Tests\Unit {

use PHPUnit\Framework\TestCase;
use TrafficPortal\DTO\PaginatedMapItemsResponse;
use TrafficPortal\DTO\MapItem;

/**
 * T007 — JOIN preview_url into Client Links list endpoint
 *
 * Tests the enrich_items_with_preview_url() logic inside ajax_get_user_map_items()
 * by driving it through a public helper method exposed for testing.
 *
 * AC covered:
 *   AC1 - Items with a wp_tp_link_previews row (non-empty local_path) → preview_url is local URL
 *   AC2 - URL is baseurl + '/tp-link-previews/' + local_path
 *   AC3 - Items without a previews row → preview_url is null
 *   AC4 - local_path is empty string (soft-fail from T006) → preview_url is null
 *   AC5 - 0 items → no DB query issued, empty source array returned
 *   AC6 - Single batched IN(...) query — not one query per item (no N+1)
 */
class PreviewUrlEnrichmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset state before each test.
        $GLOBALS['wpdb']->queries_log    = [];
        $GLOBALS['wpdb']->last_replace   = [];
        $GLOBALS['wpdb']->replace_count  = 0;
        $GLOBALS['_tp_test_wpdb_results'] = [];
        $GLOBALS['_tp_test_json_success'] = null;
        $GLOBALS['_tp_test_user_logged_in'] = true;

        // Set uploads baseurl for URL construction assertions.
        $GLOBALS['_tp_test_uploads_dir'] = [
            'basedir' => sys_get_temp_dir(),
            'baseurl' => 'http://example.com/wp-content/uploads',
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a TP_API_Handler with a mocked TrafficPortal client that returns
     * the given items array wrapped in a PaginatedMapItemsResponse.
     */
    private function makeHandlerReturningItems(array $rawItems): \TP_API_Handler
    {
        $responseData = [
            'message'       => 'ok',
            'success'       => true,
            'page'          => 1,
            'page_size'     => 50,
            'total_records' => count($rawItems),
            'total_pages'   => 1,
            'source'        => $rawItems,
        ];

        $paginatedResponse = PaginatedMapItemsResponse::fromArray($responseData);

        $mockClient = $this->createMock(\TrafficPortal\TrafficPortalApiClient::class);
        $mockClient
            ->method('getUserMapItems')
            ->willReturn($paginatedResponse);

        $handler = new \TP_API_Handler();
        $handler->set_client($mockClient);

        return $handler;
    }

    /**
     * Simulate the AJAX request by calling ajax_get_user_map_items() and
     * returning whatever was passed to wp_send_json_success().
     */
    private function callAjax(\TP_API_Handler $handler): ?array
    {
        // Set up POST parameters that the handler validates.
        $_POST = [
            'nonce'         => 'test',
            'page'          => '1',
            'page_size'     => '50',
            'include_usage' => 'true',
        ];

        $GLOBALS['_tp_test_json_success'] = null;
        $handler->ajax_get_user_map_items();

        return $GLOBALS['_tp_test_json_success'];
    }

    /**
     * Build a raw link item array (matches what MapItem::fromArray expects).
     */
    private function makeRawItem(int $mid, string $tpKey = ''): array
    {
        return [
            'mid'         => $mid,
            'uid'         => 42,
            'tpKey'       => $tpKey ?: "key{$mid}",
            'domain'      => 'dev.trfc.link',
            'destination' => "https://example.com/{$mid}",
            'status'      => 'active',
            'notes'       => '',
            'created_at'  => '2026-01-01T00:00:00Z',
            'updated_at'  => '2026-01-01T00:00:00Z',
        ];
    }

    // =========================================================================
    // AC1 + AC2 — Item with a previews row and non-empty local_path → URL
    // =========================================================================

    /**
     * @test
     * should return preview_url with local URL when previews row exists with non-empty local_path.
     */
    public function testItemWithPreviewRowGetsUrl(): void
    {
        $handler = $this->makeHandlerReturningItems([
            $this->makeRawItem(101),
        ]);

        // Program the wpdb stub: one row for mid=101 with a local_path.
        $GLOBALS['_tp_test_wpdb_results'] = [
            ['mid' => 101, 'local_path' => 'tp-link-previews/101.png'],
        ];

        $result = $this->callAjax($handler);

        $this->assertNotNull($result, 'wp_send_json_success must be called');
        $this->assertCount(1, $result['source']);

        $item = $result['source'][0];
        $this->assertArrayHasKey('preview_url', $item, 'Each item must carry a preview_url key');
        $this->assertSame(
            'http://example.com/wp-content/uploads/tp-link-previews/101.png',
            $item['preview_url'],
            'preview_url must be baseurl + /tp-link-previews/ + local_path'
        );
    }

    // =========================================================================
    // AC3 — Item without a previews row → null
    // =========================================================================

    /**
     * @test
     * should return preview_url=null for items with no previews row.
     */
    public function testItemWithNoPreviewRowGetsNull(): void
    {
        $handler = $this->makeHandlerReturningItems([
            $this->makeRawItem(202),
        ]);

        // No rows returned from DB.
        $GLOBALS['_tp_test_wpdb_results'] = [];

        $result = $this->callAjax($handler);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('preview_url', $result['source'][0]);
        $this->assertNull(
            $result['source'][0]['preview_url'],
            'preview_url must be null when no previews row exists'
        );
    }

    // =========================================================================
    // AC4 — Row exists but local_path is empty → null
    // =========================================================================

    /**
     * @test
     * should return preview_url=null when previews row exists but local_path is empty (soft-fail).
     */
    public function testItemWithEmptyLocalPathGetsNull(): void
    {
        $handler = $this->makeHandlerReturningItems([
            $this->makeRawItem(303),
        ]);

        // DB row exists but local_path is empty (T006 soft-fail case).
        $GLOBALS['_tp_test_wpdb_results'] = [
            ['mid' => 303, 'local_path' => ''],
        ];

        $result = $this->callAjax($handler);

        $this->assertNotNull($result);
        $this->assertNull(
            $result['source'][0]['preview_url'],
            'preview_url must be null when local_path is empty string'
        );
    }

    // =========================================================================
    // Mixed — 3 items: 2 have previews, 1 does not
    // =========================================================================

    /**
     * @test
     * should populate preview_url for items with rows and null for items without.
     */
    public function testMixedItemsGetCorrectPreviewUrls(): void
    {
        $handler = $this->makeHandlerReturningItems([
            $this->makeRawItem(401),
            $this->makeRawItem(402),
            $this->makeRawItem(403),
        ]);

        // mid=401 has a preview, mid=402 does not, mid=403 has empty local_path.
        $GLOBALS['_tp_test_wpdb_results'] = [
            ['mid' => 401, 'local_path' => 'tp-link-previews/401.png'],
            ['mid' => 403, 'local_path' => ''],
        ];

        $result = $this->callAjax($handler);

        $this->assertNotNull($result);
        $source = $result['source'];
        $this->assertCount(3, $source);

        // Build a mid-keyed map for easy assertions.
        $byMid = [];
        foreach ($source as $item) {
            $byMid[$item['mid']] = $item;
        }

        $this->assertSame(
            'http://example.com/wp-content/uploads/tp-link-previews/401.png',
            $byMid[401]['preview_url'],
            'mid=401 should have a URL'
        );
        $this->assertNull($byMid[402]['preview_url'], 'mid=402 has no row → null');
        $this->assertNull($byMid[403]['preview_url'], 'mid=403 has empty local_path → null');
    }

    // =========================================================================
    // AC5 — Empty list: no DB query, empty source
    // =========================================================================

    /**
     * @test
     * should issue no DB query and return empty source when list has 0 items.
     */
    public function testEmptyListIssuesNoDbQuery(): void
    {
        $handler = $this->makeHandlerReturningItems([]);

        $result = $this->callAjax($handler);

        $this->assertNotNull($result);
        $this->assertEmpty($result['source'], 'source must be empty');

        // No get_results() call should have been made.
        $previewQueries = array_filter(
            $GLOBALS['wpdb']->queries_log,
            fn(string $q) => str_contains($q, 'tp_link_previews')
        );
        $this->assertCount(
            0,
            $previewQueries,
            'No preview table query must be issued when item list is empty'
        );
    }

    // =========================================================================
    // AC6 — Single batched IN(...) query, no N+1
    // =========================================================================

    /**
     * @test
     * should issue exactly one query to wp_tp_link_previews regardless of item count.
     */
    public function testSingleBatchedQueryForMultipleItems(): void
    {
        $handler = $this->makeHandlerReturningItems([
            $this->makeRawItem(501),
            $this->makeRawItem(502),
            $this->makeRawItem(503),
            $this->makeRawItem(504),
            $this->makeRawItem(505),
        ]);

        $GLOBALS['_tp_test_wpdb_results'] = [
            ['mid' => 501, 'local_path' => 'tp-link-previews/501.png'],
            ['mid' => 503, 'local_path' => 'tp-link-previews/503.jpg'],
        ];

        $this->callAjax($handler);

        $previewQueries = array_filter(
            $GLOBALS['wpdb']->queries_log,
            fn(string $q) => str_contains($q, 'tp_link_previews')
        );

        $this->assertCount(
            1,
            $previewQueries,
            'Exactly one batched IN() query must be issued (no N+1)'
        );
    }

    // =========================================================================
    // Existing fields preserved (no regressions)
    // =========================================================================

    /**
     * @test
     * should preserve all existing fields on each item when adding preview_url.
     */
    public function testExistingItemFieldsArePreserved(): void
    {
        $handler = $this->makeHandlerReturningItems([
            $this->makeRawItem(601),
        ]);

        $GLOBALS['_tp_test_wpdb_results'] = [];

        $result = $this->callAjax($handler);

        $item = $result['source'][0];
        $this->assertArrayHasKey('mid',         $item);
        $this->assertArrayHasKey('tpKey',       $item);
        $this->assertArrayHasKey('domain',      $item);
        $this->assertArrayHasKey('destination', $item);
        $this->assertArrayHasKey('status',      $item);
        $this->assertArrayHasKey('preview_url', $item);
        $this->assertSame(601, $item['mid']);
    }
}

} // end namespace Tests\Unit
