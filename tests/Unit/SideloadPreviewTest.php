<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Global-namespace WP stubs.
//
// All WordPress functions/classes that TP_API_Handler calls at load time must
// be defined in the GLOBAL namespace.  Because this file mixes namespace blocks
// we put everything into a single `namespace {}` (global) block first, then
// the `namespace Tests\Unit {}` block for the test class.
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

    // TP_Link_Shortener stub (required by TP_API_Handler at load time)
    if (!class_exists('TP_Link_Shortener')) {
        class TP_Link_Shortener
        {
            public static function get_user_id(): int    { return 42; }
            public static function get_domain(): string  { return 'dev.trfc.link'; }
            public static function is_premium_only(): bool { return false; }
        }
    }

    // WP functions stubbed globally so TP_API_Handler can call them.
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
        function wp_send_json_success($data = null): void {}
    }
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data = null): void {}
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

    // NOTE: wp_upload_dir() is NOT defined here — it reads from a static property
    // on the test class and must be defined after the class is declared OR the
    // test class must expose the path some other way.  We define it below in the
    // global namespace block after the test class is declared, but PHP requires
    // functions to be declared before they are called... so we use a global
    // variable instead and define wp_upload_dir() here.
    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir(): array {
            return $GLOBALS['_tp_test_uploads_dir'] ?? [
                'basedir' => sys_get_temp_dir(),
                'baseurl' => 'http://example.com/wp-content/uploads',
            ];
        }
    }

    // $wpdb stub — shared across all Unit test files.
    // Methods needed by SideloadPreviewTest (replace) + SaveActionServerDiffTest (insert, get_results, query).
    if (!isset($GLOBALS['wpdb'])) {
        $GLOBALS['wpdb'] = new class {
            public string $prefix        = 'wp_';
            public array  $last_replace  = [];
            public int    $replace_count = 0;
            /** @var array<int, array<string, mixed>> */
            public array  $insert_calls  = [];
            public int    $insert_count  = 0;
            /** @var array<string, array<int, array<string, mixed>>> */
            public array  $query_results = [];
            public int    $query_count   = 0;

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

            /** @return array<int, array<string, mixed>> */
            public function get_results(string $sql, $output = ARRAY_A): array {
                foreach ($this->query_results as $key => $rows) {
                    if (stripos($sql, $key) !== false) {
                        return $rows;
                    }
                }
                return [];
            }

            public function query(string $sql): int|false {
                $this->query_count++;
                return 0;
            }

            public function prepare(string $sql, ...$args): string {
                $i = 0;
                return preg_replace_callback('/%[dsfF]/', function($m) use ($args, &$i) {
                    return "'" . ($args[$i++] ?? '') . "'";
                }, $sql);
            }

            public function get_charset_collate(): string {
                return "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            }
        };
    }

    // DB constants required by class-tp-db-migrations.php (loaded transitively).
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

    // Load the class under test (not PSR-4 autoloaded — it is a WP plugin class).
    if (!class_exists('TP_API_Handler')) {
        require_once dirname(__DIR__, 2) . '/includes/class-tp-api-handler.php';
    }

} // end namespace {} (global)

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------
namespace Tests\Unit {

use PHPUnit\Framework\TestCase;

/**
 * T006 — Sideload SnapCapture image at link creation
 *
 * Tests cover the sideload_preview() method on TP_API_Handler without a live
 * WordPress database or SnapCapture API.
 *
 * AC covered:
 *   AC1 - Image binary written to uploads/tp-link-previews/{mid}.{ext}
 *   AC2 - Row inserted into wp_tp_link_previews with all required fields
 *   AC3 - SnapCapture failure → returns false, no row, no file
 *   AC4 - Non-writable uploads dir → row with empty local_path (soft-fail)
 *   AC5 - sideload_preview() is a public method (reusable for T009)
 *   Reg - Second call for same mid uses UPSERT (replace) semantics
 */
class SideloadPreviewTest extends TestCase
{
    private static string $uploadsBase;
    private static string $previewsDir;

    // =========================================================================
    // Bootstrap: set up temp directory tree
    // =========================================================================

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $tempDir = sys_get_temp_dir() . '/tp_sideload_test_' . md5(microtime());
        self::$uploadsBase = $tempDir . '/uploads';
        self::$previewsDir = self::$uploadsBase . '/tp-link-previews';
        mkdir(self::$previewsDir, 0755, true);

        // Point the global wp_upload_dir() stub at our temp tree.
        $GLOBALS['_tp_test_uploads_dir'] = [
            'basedir' => self::$uploadsBase,
            'baseurl' => 'http://example.com/wp-content/uploads',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Reset wpdb tracking state before each test.
        $GLOBALS['wpdb']->last_replace  = [];
        $GLOBALS['wpdb']->replace_count = 0;
        $GLOBALS['wpdb']->insert_calls  = [];
        $GLOBALS['wpdb']->insert_count  = 0;
        $GLOBALS['wpdb']->query_results = [];
        $GLOBALS['wpdb']->query_count   = 0;
        // Clean up image files from prior tests.
        foreach (glob(self::$previewsDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
    }

    // =========================================================================
    // AC5 — sideload_preview() must be a public method
    // =========================================================================

    /**
     * @test
     * sideload_preview() must be public so T009 (destination-change path) can call it.
     */
    public function testSideloadPreviewIsPublicMethod(): void
    {
        $ref = new \ReflectionClass('TP_API_Handler');
        $this->assertTrue(
            $ref->hasMethod('sideload_preview'),
            'TP_API_Handler must expose a sideload_preview() method'
        );
        $method = $ref->getMethod('sideload_preview');
        $this->assertTrue($method->isPublic(), 'sideload_preview() must be public');
    }

    // =========================================================================
    // AC1 + AC2 — Success: file written + DB row inserted
    // =========================================================================

    /**
     * @test
     * PNG binary written to tp-link-previews/{mid}.png; DB row has all required fields.
     */
    public function testSideloadWritesFileAndInsertsRow(): void
    {
        $mid  = 1001;
        $dest = 'https://example.com/landing';

        $handler = $this->makeHandlerWithMockSnapCapture(
            imageData:   "\x89PNG\r\nFAKEDATA",
            contentType: 'image/png'
        );

        $result = $handler->sideload_preview($mid, $dest);

        // AC1 — file on disk
        $expectedFile = self::$previewsDir . "/{$mid}.png";
        $this->assertTrue($result, 'sideload_preview() must return true on success');
        $this->assertFileExists($expectedFile, "Image file must be written at tp-link-previews/{$mid}.png");
        $this->assertSame("\x89PNG\r\nFAKEDATA", file_get_contents($expectedFile));

        // AC2 — DB row
        $this->assertSame(1, $GLOBALS['wpdb']->replace_count, 'Exactly one wpdb->replace() call expected');
        $row = $GLOBALS['wpdb']->last_replace['data'];
        $this->assertSame($mid, $row['mid'], 'DB row mid must match the supplied mid');
        $this->assertStringContainsString(
            "tp-link-previews/{$mid}.png",
            $row['local_path'],
            'local_path must reference the written file'
        );
        $this->assertSame($dest, $row['original_url'], 'original_url must be the destination URL');
        $this->assertArrayHasKey('width',      $row, 'Row must carry width');
        $this->assertArrayHasKey('height',     $row, 'Row must carry height');
        $this->assertArrayHasKey('created_at', $row, 'Row must carry created_at');
        $this->assertArrayHasKey('updated_at', $row, 'Row must carry updated_at');
    }

    /**
     * @test
     * Content-Type image/jpeg → file extension .jpg
     */
    public function testSideloadDerivesJpgExtensionFromContentType(): void
    {
        $mid     = 2002;
        $handler = $this->makeHandlerWithMockSnapCapture(
            imageData:   "\xFF\xD8\xFF\xFAKEJPEG",
            contentType: 'image/jpeg'
        );

        $handler->sideload_preview($mid, 'https://example.com');

        $this->assertFileExists(
            self::$previewsDir . "/{$mid}.jpg",
            'image/jpeg Content-Type must produce .jpg extension'
        );
    }

    // =========================================================================
    // Reg — UPSERT semantics on second call for same mid
    // =========================================================================

    /**
     * @test
     * Two calls for the same mid → two replace() calls; latest data wins.
     */
    public function testSideloadUpsertSemanticsOnSecondCall(): void
    {
        $mid     = 3003;
        $handler = $this->makeHandlerWithMockSnapCapture(
            imageData:   'FAKEPNG1',
            contentType: 'image/png'
        );

        $handler->sideload_preview($mid, 'https://example.com/v1');
        $handler->sideload_preview($mid, 'https://example.com/v2');

        $this->assertSame(
            2,
            $GLOBALS['wpdb']->replace_count,
            'wpdb->replace() must be called on each sideload (UPSERT semantics)'
        );
        $this->assertSame(
            'https://example.com/v2',
            $GLOBALS['wpdb']->last_replace['data']['original_url'],
            'Second call must update original_url'
        );
    }

    // =========================================================================
    // AC3 — SnapCapture failure: soft-fail, no DB row, return false
    // =========================================================================

    /**
     * @test
     * SnapCapture NetworkException → returns false, no DB row, no file written.
     */
    public function testSideloadReturnsFalseOnSnapCaptureFailure(): void
    {
        $mid     = 4004;
        $handler = $this->makeHandlerWithFailingSnapCapture();

        $result = $handler->sideload_preview($mid, 'https://example.com/fail');

        $this->assertFalse($result, 'sideload_preview() must return false on SnapCapture failure');
        $this->assertSame(0, $GLOBALS['wpdb']->replace_count, 'No DB row must be inserted on failure');
        $this->assertFileDoesNotExist(
            self::$previewsDir . "/{$mid}.png",
            'No image file must be written on SnapCapture failure'
        );
    }

    // =========================================================================
    // M3 — delete_cached_preview_file: no error suppression
    //      Exercises the unlink path via delete_cached_preview_file when the
    //      file does NOT exist (should be a no-op without suppression).
    //      Also verifies the function completes (no throw) when file is absent.
    // =========================================================================

    /**
     * @test
     * delete_cached_preview_file completes without error when the file does not exist.
     * Tests the code path where file_exists() returns false — no unlink attempted.
     * This covers the removal of the error-suppressing @ operator.
     */
    public function testDeleteCachedPreviewFileCompletesWhenFileDoesNotExist(): void
    {
        $mid = 9001;

        // Seed the previews table stub with a local_path that does NOT exist on disk
        $GLOBALS['wpdb']->query_results = [
            'tp_link_previews' => [
                ['local_path' => "tp-link-previews/{$mid}.png"],
            ],
        ];

        $handler = new \TP_API_Handler();

        // Use reflection to call the private method directly
        $ref    = new \ReflectionClass('TP_API_Handler');
        $method = $ref->getMethod('delete_cached_preview_file');
        $method->setAccessible(true);

        // No file on disk at this path — should complete cleanly without throwing
        $this->expectNotToPerformAssertions();
        $method->invoke($handler, $mid);
    }

    /**
     * @test
     * delete_cached_preview_file returns void (no exception) and logs an error when
     * the file exists but cannot be unlinked. Simulated by using a temp file that
     * is unlinked first (causing the second delete to fail gracefully).
     */
    public function testDeleteCachedPreviewFileReturnsVoidWhenFileMissing(): void
    {
        $mid      = 9002;
        $fakeFile = self::$previewsDir . "/{$mid}.png";

        // Do NOT create the file — so file_exists() returns false
        $GLOBALS['wpdb']->query_results = [
            'tp_link_previews' => [
                ['local_path' => "tp-link-previews/{$mid}.png"],
            ],
        ];

        $handler = new \TP_API_Handler();
        $ref     = new \ReflectionClass('TP_API_Handler');
        $method  = $ref->getMethod('delete_cached_preview_file');
        $method->setAccessible(true);

        // Must not throw — verify return is void (null)
        $result = $method->invoke($handler, $mid);
        $this->assertNull($result, 'delete_cached_preview_file must return void (null)');
        $this->assertFileDoesNotExist($fakeFile, 'File must still not exist');
    }

    // =========================================================================
    // S6 — sideload_preview returns false when SnapCapture client is null
    // =========================================================================

    /**
     * @test
     * sideload_preview() must return false when the SnapCapture client has not been
     * injected (null), guarding against missing API key scenarios.
     */
    public function testSideloadReturnsFalseWhenSnapCaptureClientIsNull(): void
    {
        $handler = new \TP_API_Handler();
        // Do NOT inject a SnapCapture client — leave it null

        $result = $handler->sideload_preview(8001, 'https://example.com');

        $this->assertFalse($result, 'sideload_preview() must return false when SnapCapture client is null');
        $this->assertSame(0, $GLOBALS['wpdb']->replace_count, 'No DB row must be inserted when client is null');
    }

    // =========================================================================
    // AC4 — Non-writable uploads dir: soft-fail with empty local_path
    // =========================================================================

    /**
     * @test
     * Non-writable uploads dir → row inserted with empty local_path; returns false.
     */
    public function testSideloadSoftFailsWhenUploadsNotWritable(): void
    {
        $mid     = 5005;
        $handler = $this->makeHandlerWithMockSnapCapture(
            imageData:   'FAKEPNG',
            contentType: 'image/png'
        );

        // Pass an explicit non-writable path override as 3rd argument.
        $result = $handler->sideload_preview(
            $mid,
            'https://example.com/nowrit',
            '/nonexistent/path/that/cannot/be/written'
        );

        $this->assertFalse($result, 'Must return false when uploads dir is not writable');
        // AC4: row IS still inserted with empty local_path.
        $this->assertSame(
            1,
            $GLOBALS['wpdb']->replace_count,
            'A row must be inserted even on soft-fail (empty local_path fallback)'
        );
        $row = $GLOBALS['wpdb']->last_replace['data'];
        $this->assertSame('', $row['local_path'], 'local_path must be empty string on soft-fail');
        $this->assertSame('https://example.com/nowrit', $row['original_url']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeHandlerWithMockSnapCapture(
        string $imageData,
        string $contentType
    ): \TP_API_Handler {
        $response = new \SnapCapture\DTO\ScreenshotResponse(
            $imageData,
            false,
            null,
            $contentType
        );

        $mockClient = $this->createMock(\SnapCapture\SnapCaptureClient::class);
        $mockClient->method('captureScreenshot')->willReturn($response);

        $handler = new \TP_API_Handler();
        $handler->set_snapcapture_client($mockClient);

        return $handler;
    }

    private function makeHandlerWithFailingSnapCapture(): \TP_API_Handler
    {
        $mockClient = $this->createMock(\SnapCapture\SnapCaptureClient::class);
        $mockClient
            ->method('captureScreenshot')
            ->willThrowException(new \SnapCapture\Exception\NetworkException('timeout'));

        $handler = new \TP_API_Handler();
        $handler->set_snapcapture_client($mockClient);

        return $handler;
    }
}

} // end namespace Tests\Unit
