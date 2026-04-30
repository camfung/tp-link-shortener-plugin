<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Global-namespace WP stubs (shared with SideloadPreviewTest pattern).
// All WP globals must live in the `namespace {}` (global) block.
// ---------------------------------------------------------------------------
namespace {

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

    if (!class_exists('TP_Link_Shortener')) {
        class TP_Link_Shortener
        {
            public static function get_user_id(): int    { return 42; }
            public static function get_domain(): string  { return 'dev.trfc.link'; }
            public static function is_premium_only(): bool { return false; }
            public static function get_api_endpoint(): string { return 'https://api.example.com/dev'; }
            public static function get_api_key(): string { return 'test-key'; }
        }
    }

    // ----- WP function stubs -----
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
        function sanitize_text_field(string $str): string { return trim($str); }
    }
    if (!function_exists('esc_url_raw')) {
        function esc_url_raw(string $url): string { return $url; }
    }
    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer(string $action, string $query_arg = ''): bool { return true; }
    }

    // Capture wp_send_json_* calls so tests can inspect the response.
    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success($data = null): void {
            $GLOBALS['_tp_test_last_json'] = ['success' => true, 'data' => $data];
        }
    }
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data = null, int $status = 200): void {
            $GLOBALS['_tp_test_last_json'] = ['success' => false, 'data' => $data, 'status' => $status];
        }
    }
    if (!function_exists('is_user_logged_in')) {
        function is_user_logged_in(): bool { return $GLOBALS['_tp_test_logged_in'] ?? true; }
    }
    if (!function_exists('get_current_user_id')) {
        function get_current_user_id(): int { return $GLOBALS['_tp_test_user_id'] ?? 42; }
    }
    if (!function_exists('__')) {
        function __(string $text, string $domain = 'default'): string { return $text; }
    }
    if (!function_exists('esc_html__')) {
        function esc_html__(string $text, string $domain = 'default'): string { return htmlspecialchars($text); }
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
    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir(): array {
            return $GLOBALS['_tp_test_uploads_dir'] ?? [
                'basedir' => sys_get_temp_dir(),
                'baseurl' => 'http://example.com/wp-content/uploads',
            ];
        }
    }

    // ----- Extended wpdb stub that handles all queries needed by T009 -----
    if (!isset($GLOBALS['wpdb'])) {
        $GLOBALS['wpdb'] = new class {
            public string $prefix        = 'wp_';
            public array  $last_replace  = [];
            public int    $replace_count = 0;
            // Rows returned by get_results (keyed by query substring for routing)
            public array  $query_results = [];
            // Track insert calls to wp_tp_link_history
            public array  $insert_calls  = [];
            public int    $insert_count  = 0;
            // Track update calls
            public array  $update_calls  = [];
            public int    $update_count  = 0;
            // Simulate query() for DELETE/cache-bust
            public int    $query_count   = 0;

            public function prepare(string $sql, ...$args): string {
                // Simple substitution for tests
                $i = 0;
                return preg_replace_callback('/%[dsfF]/', function($m) use ($args, &$i) {
                    return "'" . ($args[$i++] ?? '') . "'";
                }, $sql);
            }

            /** @return array<int,array<string,mixed>> */
            public function get_results(string $sql, $output = OBJECT): array {
                foreach ($this->query_results as $key => $rows) {
                    if (stripos($sql, $key) !== false) {
                        return $rows;
                    }
                }
                return [];
            }

            /** @return mixed */
            public function get_var(string $sql) { return null; }

            public function replace(string $table, array $data, array $formats): int|false {
                $this->last_replace  = ['table' => $table, 'data' => $data];
                $this->replace_count++;
                return 1;
            }

            public function insert(string $table, array $data, array $formats = []): int|false {
                $this->insert_calls[] = ['table' => $table, 'data' => $data];
                $this->insert_count++;
                return 1;
            }

            public function update(string $table, array $data, array $where, array $dataFormats = [], array $whereFormats = []): int|false {
                $this->update_calls[] = ['table' => $table, 'data' => $data, 'where' => $where];
                $this->update_count++;
                return 1;
            }

            public function query(string $sql): int|false {
                $this->query_count++;
                return 0;
            }

            public function get_charset_collate(): string {
                return "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            }
        };
    }

    // DB constants
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
    if (!defined('TP_DEBUG_LOG')) {
        define('TP_DEBUG_LOG', false);
    }
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    // Load the class under test
    if (!class_exists('TP_API_Handler')) {
        require_once dirname(__DIR__, 2) . '/includes/class-tp-api-handler.php';
    }

} // end global namespace block

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------
namespace Tests\Unit {

use PHPUnit\Framework\TestCase;

/**
 * T009 — Server-side diff detection + conditional regenerate (QR / preview)
 *
 * Tests for the ajax_update_link() method in TP_API_Handler.
 *
 * AC covered:
 *   AC1 - Destination changed → DB updated, old preview file unlinked, sideload called once, QR NOT regenerated
 *   AC2 - tpKey changed → DB updated, QR flag set, sideload NOT called
 *   AC3 - Notes only changed → DB updated, no QR, no sideload
 *   AC4 - No fields changed → no DB write, no history row, response signals 'no_changes'
 *   AC5a - DB update fails → no regen calls happen, error returned
 *   AC5b - DB succeeds, SnapCapture fails → response carries preview_pending: true
 *   AC5c - History row carries {destination: {from, to}} shape (T002 wiring)
 */
class SaveActionServerDiffTest extends TestCase
{
    private static string $uploadsBase;
    private static string $previewsDir;

    // History table name used by tests
    private string $historyTable;
    // Previews table name
    private string $previewsTable;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $tempDir = sys_get_temp_dir() . '/tp_save_diff_test_' . md5(microtime());
        self::$uploadsBase = $tempDir . '/uploads';
        self::$previewsDir = self::$uploadsBase . '/tp-link-previews';
        mkdir(self::$previewsDir, 0755, true);

        $GLOBALS['_tp_test_uploads_dir'] = [
            'basedir' => self::$uploadsBase,
            'baseurl' => 'http://example.com/wp-content/uploads',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reset shared globals — support both stub variants:
        //   - PreviewUrlEnrichmentTest stubs write to _tp_test_json_success / _tp_test_json_error
        //   - SaveActionServerDiffTest stubs write to _tp_test_last_json
        // We normalise to _tp_test_last_json in getLastJsonResponse().
        $GLOBALS['_tp_test_last_json']      = null;
        $GLOBALS['_tp_test_json_success']   = null;
        $GLOBALS['_tp_test_json_error']     = null;
        // Support both is_user_logged_in() stub variants (different test files define different globals)
        $GLOBALS['_tp_test_user_logged_in'] = true;
        $GLOBALS['_tp_test_logged_in']    = true;
        $GLOBALS['_tp_test_user_id']      = 42;

        // Reset wpdb tracking
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->last_replace  = [];
        $wpdb->replace_count = 0;
        $wpdb->insert_calls  = [];
        $wpdb->insert_count  = 0;
        $wpdb->update_calls  = [];
        $wpdb->update_count  = 0;
        $wpdb->query_count   = 0;
        $wpdb->query_results = [];

        $this->historyTable  = $GLOBALS['wpdb']->prefix . 'tp_link_history';
        $this->previewsTable = TP_LINK_PREVIEWS_TABLE;

        // Clean up preview files from prior tests
        foreach (glob(self::$previewsDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
    }

    // =========================================================================
    // AC1 — Destination changed
    // =========================================================================

    /**
     * @test
     * should update DB, unlink old preview file, call sideload once, NOT regenerate QR
     * when only destination changes
     */
    public function testDestinationChangedTriggersPreviewRegenerateNotQr(): void
    {
        $mid       = 101;
        $oldDest   = 'https://old.example.com';
        $newDest   = 'https://new.example.com';
        $tpKey     = 'mylink';
        $domain    = 'dev.trfc.link';

        // Create a fake cached preview file on disk — use .jpg so we can distinguish
        // "old file deleted" from "new file created" (sideload will produce .png).
        $oldPreviewFile = self::$previewsDir . "/{$mid}.jpg";
        file_put_contents($oldPreviewFile, 'OLD_PREVIEW_DATA');
        $this->assertFileExists($oldPreviewFile, 'Pre-condition: old preview file must exist');

        // Seed wpdb with history (read_link_state will query wp_tp_link_history)
        // and with previews table row (for local_path lookup).
        // local_path references the .jpg so delete_cached_preview_file() unlinks it.
        $GLOBALS['wpdb']->query_results = [
            // read_link_state query hits history table
            'tp_link_history' => [
                [
                    'action'  => 'created',
                    'changes' => json_encode([
                        'destination' => $oldDest,
                        'tpKey'       => $tpKey,
                        'domain'      => $domain,
                    ]),
                ],
            ],
            // local_path lookup hits previews table — points at the old .jpg file
            'tp_link_previews' => [
                [
                    'local_path' => "tp-link-previews/{$mid}.jpg",
                ],
            ],
        ];

        // Set up _POST
        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $newDest,
            'tpKey'       => $tpKey,
            'notes'       => '',
        ];

        // SnapCapture mock returns PNG so new file is .png (different from old .jpg)
        $handler = $this->makeHandlerWithMockClient(
            updateSuccess: true,
            snapcaptureSuccess: true,
            imageData: 'FAKE_PNG_DATA',
            contentType: 'image/png'
        );

        $handler->ajax_update_link();

        // Old .jpg preview file must be deleted
        $this->assertFileDoesNotExist(
            $oldPreviewFile,
            'Old preview file must be unlinked when destination changes'
        );

        // sideload_preview writes a new .png file for the new destination
        $this->assertFileExists(
            self::$previewsDir . "/{$mid}.png",
            'New preview file must be written after sideload'
        );

        // DB update (via updateMaskedRecord) must have been called
        $this->assertCount(1, $this->getMockClientCalls('update'), 'updateMaskedRecord must be called once');

        // History row must be inserted
        $this->assertSame(1, $GLOBALS['wpdb']->insert_count, 'History row must be written');
        $historyInsert = $GLOBALS['wpdb']->insert_calls[0];
        $this->assertSame($this->historyTable, $historyInsert['table']);
        $this->assertSame('updated', $historyInsert['data']['action']);

        // Diff in history must reference destination
        $diff = json_decode($historyInsert['data']['changes'], true);
        $this->assertArrayHasKey('destination', $diff, 'Diff must include destination');
        $this->assertSame($oldDest, $diff['destination']['from']);
        $this->assertSame($newDest, $diff['destination']['to']);

        // Response must report regenerated: ['preview'], NOT 'qr'
        $json = $this->getLastJsonResponse();
        $this->assertTrue($json['success'], 'Response must be success');
        $responseData = $json['data'];
        $regenerated = $responseData['regenerated'] ?? [];
        $this->assertContains('preview', $regenerated, 'regenerated must include preview');
        $this->assertNotContains('qr', $regenerated, 'regenerated must NOT include qr when only destination changed');
    }

    // =========================================================================
    // AC2 — tpKey changed
    // =========================================================================

    /**
     * @test
     * should update DB and flag QR regeneration, NOT call sideload when only tpKey changes
     */
    public function testTpKeyChangedFlagsQrRegenerateNotPreview(): void
    {
        $mid     = 202;
        $oldKey  = 'oldlink';
        $newKey  = 'newlink';
        $dest    = 'https://destination.example.com';
        $domain  = 'dev.trfc.link';

        $GLOBALS['wpdb']->query_results = [
            'tp_link_history' => [
                [
                    'action'  => 'created',
                    'changes' => json_encode([
                        'destination' => $dest,
                        'tpKey'       => $oldKey,
                        'domain'      => $domain,
                    ]),
                ],
            ],
        ];

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $dest,
            'tpKey'       => $newKey,
            'notes'       => '',
        ];

        $handler = $this->makeHandlerWithMockClient(updateSuccess: true);
        $handler->ajax_update_link();

        // No preview file should have been written
        $this->assertFileDoesNotExist(
            self::$previewsDir . "/{$mid}.png",
            'No preview file must be written when only tpKey changes'
        );

        // DB update must have been called
        $this->assertCount(1, $this->getMockClientCalls('update'), 'updateMaskedRecord must be called once');

        // History row written
        $this->assertSame(1, $GLOBALS['wpdb']->insert_count, 'History row must be written');

        // Response must report regenerated: ['qr'], NOT 'preview'
        $json = $this->getLastJsonResponse();
        $this->assertTrue($json['success'], 'Response must be success');
        $responseData = $json['data'];
        $regenerated = $responseData['regenerated'] ?? [];
        $this->assertContains('qr', $regenerated, 'regenerated must include qr when tpKey changes');
        $this->assertNotContains('preview', $regenerated, 'regenerated must NOT include preview when only tpKey changes');
    }

    // =========================================================================
    // AC3 — Notes only changed
    // =========================================================================

    /**
     * @test
     * should update DB without regenerating QR or preview when only notes changes
     */
    public function testNotesOnlyChangedNoRegen(): void
    {
        $mid   = 303;
        $dest  = 'https://dest.example.com';
        $tpKey = 'stablekey';
        $domain = 'dev.trfc.link';

        $GLOBALS['wpdb']->query_results = [
            'tp_link_history' => [
                [
                    'action'  => 'created',
                    'changes' => json_encode([
                        'destination' => $dest,
                        'tpKey'       => $tpKey,
                        'domain'      => $domain,
                        'notes'       => '',
                    ]),
                ],
            ],
        ];

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $dest,
            'tpKey'       => $tpKey,
            'notes'       => 'new notes added',
        ];

        $handler = $this->makeHandlerWithMockClient(updateSuccess: true);
        $handler->ajax_update_link();

        // No preview file written
        $this->assertFileDoesNotExist(
            self::$previewsDir . "/{$mid}.png",
            'No preview file must be written when only notes changes'
        );

        // DB update called
        $this->assertCount(1, $this->getMockClientCalls('update'), 'updateMaskedRecord must be called once');

        // History row written
        $this->assertSame(1, $GLOBALS['wpdb']->insert_count, 'History row must be written');

        // Response must report no regen
        $json = $this->getLastJsonResponse();
        $this->assertTrue($json['success'], 'Response must be success');
        $responseData = $json['data'];
        $regenerated = $responseData['regenerated'] ?? [];
        $this->assertEmpty($regenerated, 'regenerated must be empty when only notes changed');
    }

    // =========================================================================
    // AC4 — No-op save
    // =========================================================================

    /**
     * @test
     * should return status no_changes without DB write or history row when nothing changed
     */
    public function testNoOpSaveReturnsNoChangesWithNoDbWrite(): void
    {
        $mid   = 404;
        $dest  = 'https://same.example.com';
        $tpKey = 'samekey';
        $domain = 'dev.trfc.link';

        $GLOBALS['wpdb']->query_results = [
            'tp_link_history' => [
                [
                    'action'  => 'created',
                    'changes' => json_encode([
                        'destination' => $dest,
                        'tpKey'       => $tpKey,
                        'domain'      => $domain,
                        'notes'       => '',
                    ]),
                ],
            ],
        ];

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $dest,
            'tpKey'       => $tpKey,
            'notes'       => '',    // same as before
        ];

        $handler = $this->makeHandlerWithMockClient(updateSuccess: true);
        $handler->ajax_update_link();

        // No DB update (updateMaskedRecord) called
        $this->assertEmpty(
            $this->getMockClientCalls('update'),
            'updateMaskedRecord must NOT be called on no-op save'
        );

        // No history row
        $this->assertSame(0, $GLOBALS['wpdb']->insert_count, 'No history row must be written on no-op');

        // Response signals no_changes
        $json = $this->getLastJsonResponse();
        $this->assertTrue($json['success'], 'no-op response must be wp_send_json_success');
        $responseData = $json['data'];
        $this->assertSame('no_changes', $responseData['status'], 'Response status must be no_changes');
    }

    // =========================================================================
    // AC5a — DB update fails → no regen, error returned
    // =========================================================================

    /**
     * @test
     * should return error and NOT call sideload or QR regen when DB update fails
     */
    public function testDbUpdateFailurePreventsRegen(): void
    {
        $mid   = 505;
        $dest  = 'https://new-dest.example.com';
        $tpKey = 'mykey';
        $domain = 'dev.trfc.link';

        $GLOBALS['wpdb']->query_results = [
            'tp_link_history' => [
                [
                    'action'  => 'created',
                    'changes' => json_encode([
                        'destination' => 'https://old-dest.example.com',
                        'tpKey'       => $tpKey,
                        'domain'      => $domain,
                        'notes'       => '',
                    ]),
                ],
            ],
        ];

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $dest,
            'tpKey'       => $tpKey,
            'notes'       => '',
        ];

        // DB update will fail (success = false)
        $handler = $this->makeHandlerWithMockClient(updateSuccess: false);
        $handler->ajax_update_link();

        // No preview file written
        $this->assertFileDoesNotExist(
            self::$previewsDir . "/{$mid}.png",
            'No preview file must be written when DB update fails'
        );

        // Error response
        $json = $this->getLastJsonResponse();
        $this->assertFalse($json['success'], 'Response must be error when DB update fails');
    }

    // =========================================================================
    // AC5b — DB succeeds, SnapCapture fails → preview_pending in response
    // =========================================================================

    /**
     * @test
     * should return preview_pending flag when DB succeeds but SnapCapture fails
     */
    public function testSnapCaptureFailureCarriesPreviewPendingFlag(): void
    {
        $mid     = 606;
        $oldDest = 'https://old.example.com';
        $newDest = 'https://new.example.com';
        $tpKey   = 'mykey';
        $domain  = 'dev.trfc.link';

        $GLOBALS['wpdb']->query_results = [
            'tp_link_history' => [
                [
                    'action'  => 'created',
                    'changes' => json_encode([
                        'destination' => $oldDest,
                        'tpKey'       => $tpKey,
                        'domain'      => $domain,
                        'notes'       => '',
                    ]),
                ],
            ],
            'tp_link_previews' => [
                [
                    'local_path' => "tp-link-previews/{$mid}.png",
                ],
            ],
        ];

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $newDest,
            'tpKey'       => $tpKey,
            'notes'       => '',
        ];

        // DB update succeeds, SnapCapture fails
        $handler = $this->makeHandlerWithMockClient(
            updateSuccess: true,
            snapcaptureSuccess: false
        );

        $handler->ajax_update_link();

        // DB update was still called
        $this->assertCount(1, $this->getMockClientCalls('update'), 'updateMaskedRecord must be called');

        // Response must be success (DB write succeeded) with preview_pending flag
        $json = $this->getLastJsonResponse();
        $this->assertTrue($json['success'], 'Response must be success (DB write is authoritative)');
        $responseData = $json['data'];
        $failures = $responseData['failures'] ?? [];
        $this->assertContains('preview_pending', $failures, 'failures must include preview_pending when sideload fails');
    }

    // =========================================================================
    // AC5c — History row carries correct diff shape (T002 wiring)
    // =========================================================================

    /**
     * @test
     * should write history row with diff shape {destination: {from: old, to: new}} on destination change
     */
    public function testHistoryRowDiffShapeOnDestinationChange(): void
    {
        $mid     = 707;
        $oldDest = 'https://original.example.com';
        $newDest = 'https://updated.example.com';
        $tpKey   = 'histkey';
        $domain  = 'dev.trfc.link';

        $GLOBALS['wpdb']->query_results = [
            'tp_link_history' => [
                [
                    'action'  => 'created',
                    'changes' => json_encode([
                        'destination' => $oldDest,
                        'tpKey'       => $tpKey,
                        'domain'      => $domain,
                        'notes'       => '',
                    ]),
                ],
            ],
            'tp_link_previews' => [
                ['local_path' => "tp-link-previews/{$mid}.png"],
            ],
        ];

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $newDest,
            'tpKey'       => $tpKey,
            'notes'       => '',
        ];

        $handler = $this->makeHandlerWithMockClient(
            updateSuccess: true,
            snapcaptureSuccess: true,
            imageData: 'FAKEDATA',
            contentType: 'image/png'
        );

        $handler->ajax_update_link();

        // Find the history insert
        $historyInserts = array_filter(
            $GLOBALS['wpdb']->insert_calls,
            fn(array $call) => str_contains($call['table'], 'tp_link_history')
        );

        $this->assertCount(1, $historyInserts, 'Exactly one history row must be written');
        $insert = array_values($historyInserts)[0];
        $diff   = json_decode($insert['data']['changes'], true);

        $this->assertArrayHasKey('destination', $diff, 'Diff must include destination key');
        $this->assertSame($oldDest, $diff['destination']['from'], 'from must be the old destination');
        $this->assertSame($newDest, $diff['destination']['to'],   'to must be the new destination');
    }

    // =========================================================================
    // M2 — buildCreatedPayload used by ajax_create_link
    //       First edit after creation: destination-only change must NOT regen QR.
    //       First edit after creation: tpKey-only change must NOT regen preview.
    // =========================================================================

    /**
     * @test
     * should NOT include qr in regenerated when only destination changed on first edit
     * after a created row whose payload came from buildCreatedPayload (includes domain+notes)
     */
    public function testFirstEditDestinationOnlyDoesNotRegenQr(): void
    {
        $mid     = 901;
        $oldDest = 'https://original.example.com';
        $newDest = 'https://changed.example.com';
        $tpKey   = 'stablekey';
        $domain  = 'dev.trfc.link';

        // Seed a created row with the shape buildCreatedPayload now produces (includes domain)
        $GLOBALS['wpdb']->query_results = [
            'tp_link_history' => [
                [
                    'action'  => 'created',
                    'changes' => json_encode([
                        'destination' => $oldDest,
                        'tpKey'       => $tpKey,
                        'domain'      => $domain,
                    ]),
                ],
            ],
        ];

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $newDest,   // changed
            'tpKey'       => $tpKey,     // unchanged
            'notes'       => '',
        ];

        $handler = $this->makeHandlerWithMockClient(
            updateSuccess: true,
            snapcaptureSuccess: true,
            imageData: 'FAKEDATA',
            contentType: 'image/png'
        );
        $handler->ajax_update_link();

        $json = $this->getLastJsonResponse();
        $this->assertTrue($json['success'], 'Response must be success');
        $regenerated = $json['data']['regenerated'] ?? [];
        $this->assertContains('preview', $regenerated, 'preview must be in regenerated when destination changed');
        $this->assertNotContains('qr', $regenerated, 'qr must NOT be in regenerated when only destination changed');
    }

    /**
     * @test
     * should NOT include preview in regenerated when only tpKey changed on first edit
     */
    public function testFirstEditTpKeyOnlyDoesNotRegenPreview(): void
    {
        $mid    = 902;
        $dest   = 'https://stable.example.com';
        $oldKey = 'oldkey';
        $newKey = 'newkey';

        // Seed created row with the full buildCreatedPayload shape (includes domain)
        $GLOBALS['wpdb']->query_results = [
            'tp_link_history' => [
                [
                    'action'  => 'created',
                    'changes' => json_encode([
                        'destination' => $dest,
                        'tpKey'       => $oldKey,
                        'domain'      => 'dev.trfc.link',
                    ]),
                ],
            ],
        ];

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $dest,      // unchanged
            'tpKey'       => $newKey,    // changed
            'notes'       => '',
        ];

        $handler = $this->makeHandlerWithMockClient(updateSuccess: true);
        $handler->ajax_update_link();

        $json = $this->getLastJsonResponse();
        $this->assertTrue($json['success'], 'Response must be success');
        $regenerated = $json['data']['regenerated'] ?? [];
        $this->assertContains('qr', $regenerated, 'qr must be in regenerated when tpKey changed');
        $this->assertNotContains('preview', $regenerated, 'preview must NOT be in regenerated when only tpKey changed');
    }

    // =========================================================================
    // S3 — Unauthenticated path: login_required error
    // =========================================================================

    /**
     * @test
     * should return login_required error with 401 status when user is not logged in
     */
    public function testUnauthenticatedRequestReturnsLoginRequired(): void
    {
        // Both globals cover variant stub signatures across test files.
        // PreviewUrlEnrichmentTest defines is_user_logged_in() reading _tp_test_user_logged_in;
        // SaveActionServerDiffTest defines the same reading _tp_test_logged_in.
        // Whichever was registered first, both are set so the check returns false.
        $GLOBALS['_tp_test_logged_in']      = false;
        $GLOBALS['_tp_test_user_logged_in'] = false;

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => '101',
            'destination' => 'https://example.com',
            'tpKey'       => 'somekey',
            'notes'       => '',
        ];

        $handler = $this->makeHandlerWithMockClient(updateSuccess: true);
        $handler->ajax_update_link();

        $json = $this->getLastJsonResponse();
        $this->assertFalse($json['success'], 'Response must be error when not logged in');
        // The error data contains 'code' => 'login_required'.
        // HTTP status (401) is in $json['status'] when the SaveActionServerDiffTest stub is active;
        // when PreviewUrlEnrichmentTest's stub is first (no status key), we check only code.
        $this->assertSame('login_required', $json['data']['code'], 'Error code must be login_required');
    }

    // =========================================================================
    // S4 — No history rows: null state → full regeneration
    // =========================================================================

    /**
     * @test
     * should regenerate both preview and qr when no history rows exist for mid
     */
    public function testNoHistoryRowsTriggersFullRegeneration(): void
    {
        $mid  = 999;
        $dest = 'https://new.example.com';
        $key  = 'somekey';

        // Zero history rows — link pre-dates history table
        $GLOBALS['wpdb']->query_results = [
            'tp_link_history' => [],
        ];

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $dest,
            'tpKey'       => $key,
            'notes'       => '',
        ];

        $handler = $this->makeHandlerWithMockClient(
            updateSuccess: true,
            snapcaptureSuccess: true,
            imageData: 'FAKEDATA',
            contentType: 'image/png'
        );
        $handler->ajax_update_link();

        $json = $this->getLastJsonResponse();
        $this->assertTrue($json['success'], 'Response must be success');
        $regenerated = $json['data']['regenerated'] ?? [];
        $this->assertContains('preview', $regenerated, 'preview must be in regenerated when no history exists');
        $this->assertContains('qr', $regenerated, 'qr must be in regenerated when no history exists');
    }

    // =========================================================================
    // S5 — Missing required POST parameters
    // =========================================================================

    /**
     * @test
     * should return error when mid is missing (zero)
     */
    public function testMissingMidReturnsError(): void
    {
        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => '0',
            'destination' => 'https://example.com',
            'tpKey'       => 'somekey',
            'notes'       => '',
        ];

        $handler = $this->makeHandlerWithMockClient(updateSuccess: true);
        $handler->ajax_update_link();

        $json = $this->getLastJsonResponse();
        $this->assertFalse($json['success'], 'Response must be error when mid is 0');
    }

    /**
     * @test
     * should return error when destination is empty
     */
    public function testMissingDestinationReturnsError(): void
    {
        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => '101',
            'destination' => '',
            'tpKey'       => 'somekey',
            'notes'       => '',
        ];

        $handler = $this->makeHandlerWithMockClient(updateSuccess: true);
        $handler->ajax_update_link();

        $json = $this->getLastJsonResponse();
        $this->assertFalse($json['success'], 'Response must be error when destination is empty');
    }

    /**
     * @test
     * should return error when tpKey is empty
     */
    public function testMissingTpKeyReturnsError(): void
    {
        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => '101',
            'destination' => 'https://example.com',
            'tpKey'       => '',
            'notes'       => '',
        ];

        $handler = $this->makeHandlerWithMockClient(updateSuccess: true);
        $handler->ajax_update_link();

        $json = $this->getLastJsonResponse();
        $this->assertFalse($json['success'], 'Response must be error when tpKey is empty');
    }

    // =========================================================================
    // Mutation guard — flip 'destination' check to confirm AC1 branching
    // =========================================================================

    /**
     * @test
     * should NOT call sideload when destination did not change (mutation guard)
     */
    public function testSideloadNotCalledWhenDestinationUnchanged(): void
    {
        $mid    = 808;
        $dest   = 'https://same-dest.example.com';
        $oldKey = 'samekey';
        $newKey = 'newkey2';
        $domain = 'dev.trfc.link';

        $GLOBALS['wpdb']->query_results = [
            'tp_link_history' => [
                [
                    'action'  => 'created',
                    'changes' => json_encode([
                        'destination' => $dest,
                        'tpKey'       => $oldKey,
                        'domain'      => $domain,
                        'notes'       => '',
                    ]),
                ],
            ],
        ];

        $_POST = [
            'nonce'       => 'test-nonce',
            'mid'         => (string) $mid,
            'destination' => $dest,    // unchanged
            'tpKey'       => $newKey,  // changed — should trigger QR but not preview
            'notes'       => '',
        ];

        $handler = $this->makeHandlerWithMockClient(updateSuccess: true);
        $handler->ajax_update_link();

        // No preview file written
        $this->assertFileDoesNotExist(
            self::$previewsDir . "/{$mid}.png",
            'Preview must NOT be regenerated when destination did not change'
        );

        // QR must be flagged
        $json = $this->getLastJsonResponse();
        $responseData = $json['data'];
        $regenerated = $responseData['regenerated'] ?? [];
        $this->assertContains('qr', $regenerated, 'QR must be flagged when tpKey changed');
        $this->assertNotContains('preview', $regenerated);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a TP_API_Handler with a mock TrafficPortalApiClient and optional mock SnapCapture.
     */
    private function makeHandlerWithMockClient(
        bool $updateSuccess = true,
        bool $snapcaptureSuccess = true,
        string $imageData    = 'FAKEIMAGE',
        string $contentType  = 'image/png'
    ): \TP_API_Handler {
        $handler = new \TP_API_Handler();

        // ---- Mock TrafficPortalApiClient ----
        $mockTpClient = $this->createMock(\TrafficPortal\TrafficPortalApiClient::class);

        // Track updateMaskedRecord calls in a list
        if (!isset($GLOBALS['_tp_mock_client_calls'])) {
            $GLOBALS['_tp_mock_client_calls'] = ['update' => []];
        }
        $GLOBALS['_tp_mock_client_calls'] = ['update' => []];

        $mockTpClient
            ->method('updateMaskedRecord')
            ->willReturnCallback(function($mid, $data) use ($updateSuccess) {
                $GLOBALS['_tp_mock_client_calls']['update'][] = ['mid' => $mid, 'data' => $data];
                return ['success' => $updateSuccess, 'data' => []];
            });

        $handler->set_client($mockTpClient);

        // ---- Mock SnapCaptureClient (if snapcaptureSuccess) ----
        if ($snapcaptureSuccess) {
            $response = new \SnapCapture\DTO\ScreenshotResponse(
                $imageData,
                false,
                null,
                $contentType
            );
            $mockSnap = $this->createMock(\SnapCapture\SnapCaptureClient::class);
            $mockSnap->method('captureScreenshot')->willReturn($response);
        } else {
            $mockSnap = $this->createMock(\SnapCapture\SnapCaptureClient::class);
            $mockSnap
                ->method('captureScreenshot')
                ->willThrowException(new \SnapCapture\Exception\NetworkException('timeout'));
        }
        $handler->set_snapcapture_client($mockSnap);

        return $handler;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getMockClientCalls(string $type): array
    {
        return $GLOBALS['_tp_mock_client_calls'][$type] ?? [];
    }

    /**
     * Normalise the last JSON response regardless of which stub variant is active.
     *
     * - PreviewUrlEnrichmentTest stubs write success to $GLOBALS['_tp_test_json_success']
     *   and error to $GLOBALS['_tp_test_json_error'].
     * - SaveActionServerDiffTest stubs write to $GLOBALS['_tp_test_last_json'].
     *
     * Returns ['success' => bool, 'data' => mixed] or null if nothing was set.
     *
     * @return array{success: bool, data: mixed}|null
     */
    private function getLastJsonResponse(): ?array
    {
        // Direct form (our stub, if it wins)
        if ($GLOBALS['_tp_test_last_json'] !== null) {
            return $GLOBALS['_tp_test_last_json'];
        }

        // PreviewUrlEnrichmentTest stub variant
        if ($GLOBALS['_tp_test_json_success'] !== null) {
            return ['success' => true, 'data' => $GLOBALS['_tp_test_json_success']];
        }
        if ($GLOBALS['_tp_test_json_error'] !== null) {
            return ['success' => false, 'data' => $GLOBALS['_tp_test_json_error']];
        }

        return null;
    }
}

} // end namespace Tests\Unit
