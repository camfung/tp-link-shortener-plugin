<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * T005 — Create wp_tp_link_previews table + activation migration
 *
 * Tests cover the migration SQL and structural conventions without requiring
 * a live WordPress database. They validate:
 *
 *   AC1  - SQL contains all required columns with the correct types
 *   AC2  - TP_DB_Migrations::get_link_previews_table_sql() is idempotent-safe
 *          (delegates to dbDelta — we assert the SQL shape, not DB state)
 *   AC3  - TP_DB_VERSION constant is defined and non-empty
 *   AC4  - TP_LINK_PREVIEWS_TABLE constant ends with 'tp_link_previews'
 *   Reg  - The migration class does NOT use raw CREATE TABLE without dbDelta
 */
class DbMigrationsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Bootstrap: load the migration class (WP stubs prevent fatal errors)
    // -------------------------------------------------------------------------

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Minimal WordPress stubs so we can require the class without a full WP install.
        if (!defined('ABSPATH')) {
            define('ABSPATH', sys_get_temp_dir() . '/wp/');
        }
        if (!function_exists('update_option')) {
            function update_option(string $key, $value): bool { return true; }
        }
        if (!function_exists('get_option')) {
            function get_option(string $key, $default = false) { return $default; }
        }
        if (!function_exists('wp_upload_dir')) {
            function wp_upload_dir(): array {
                return ['basedir' => sys_get_temp_dir() . '/wp-uploads'];
            }
        }
        if (!function_exists('wp_mkdir_p')) {
            function wp_mkdir_p(string $dir): bool {
                return is_dir($dir) || mkdir($dir, 0755, true);
            }
        }
        if (!function_exists('dbDelta')) {
            function dbDelta(string $sql): array { return []; }
        }
        if (!function_exists('version_compare')) {
            // version_compare is a PHP built-in — always available. Listed here for clarity only.
        }

        // Stub global $wpdb so TP_LINK_PREVIEWS_TABLE can be defined at class-load time.
        // NOTE: This stub is the canonical shared wpdb mock for all Unit tests.
        // It must include all methods used across Unit test files (replace, insert,
        // get_results, query, prepare, get_charset_collate).
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new class {
                public string $prefix       = 'wp_';
                public array  $last_replace = [];
                public int    $replace_count = 0;
                public array  $insert_calls  = [];
                public int    $insert_count  = 0;
                public array  $query_results = [];
                public int    $query_count   = 0;
                // Legacy: PreviewUrlEnrichmentTest uses this key
                public array  $queries_log   = [];

                public function replace(string $table, array $data, array $formats): int|false {
                    $this->last_replace   = ['table' => $table, 'data' => $data];
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
                    // SaveActionServerDiffTest routes by table-name substring.
                    foreach ($this->query_results as $key => $rows) {
                        if (stripos($sql, $key) !== false) {
                            return $rows;
                        }
                    }
                    // PreviewUrlEnrichmentTest uses $GLOBALS['_tp_test_wpdb_results'].
                    return $GLOBALS['_tp_test_wpdb_results'] ?? [];
                }

                public function query(string $sql): int|false {
                    $this->query_count++;
                    return 0;
                }

                public function prepare(string $sql, ...$args): string {
                    $i = 0;
                    return preg_replace_callback('/%[dsfF]/', function ($m) use ($args, &$i) {
                        return "'" . ($args[$i++] ?? '') . "'";
                    }, $sql);
                }

                public function get_charset_collate(): string {
                    return "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                }
            };
        }

        // Require the file under test.
        $migrationFile = dirname(__DIR__, 2) . '/includes/class-tp-db-migrations.php';
        if (!class_exists('TP_DB_Migrations')) {
            require_once $migrationFile;
        }
    }

    // -------------------------------------------------------------------------
    // AC3 — TP_DB_VERSION constant is defined and non-empty
    // -------------------------------------------------------------------------

    /**
     * @test
     * TP_DB_VERSION constant must be defined and be a non-empty string
     */
    public function testDbVersionConstantIsDefined(): void
    {
        $this->assertTrue(defined('TP_DB_VERSION'), 'TP_DB_VERSION must be defined in the migrations file');
        $this->assertIsString(TP_DB_VERSION);
        $this->assertNotEmpty(TP_DB_VERSION, 'TP_DB_VERSION must not be an empty string');
    }

    // -------------------------------------------------------------------------
    // AC4 — TP_LINK_PREVIEWS_TABLE constant has correct suffix
    // -------------------------------------------------------------------------

    /**
     * @test
     * TP_LINK_PREVIEWS_TABLE must end with 'tp_link_previews' so table references are consistent
     */
    public function testLinkPreviewsTableConstantHasCorrectSuffix(): void
    {
        $this->assertTrue(defined('TP_LINK_PREVIEWS_TABLE'), 'TP_LINK_PREVIEWS_TABLE must be defined');
        $this->assertStringEndsWith(
            'tp_link_previews',
            TP_LINK_PREVIEWS_TABLE,
            'TP_LINK_PREVIEWS_TABLE must end with tp_link_previews'
        );
    }

    // -------------------------------------------------------------------------
    // AC1 — SQL contains all required columns with correct types
    // -------------------------------------------------------------------------

    /**
     * @test
     * SQL must declare mid as BIGINT UNSIGNED PRIMARY KEY
     */
    public function testSqlContainsMidAsBigintUnsignedPrimaryKey(): void
    {
        $sql = \TP_DB_Migrations::get_link_previews_table_sql('wp_tp_link_previews');

        $this->assertStringContainsString('mid', $sql);
        $this->assertMatchesRegularExpression(
            '/mid\s+BIGINT UNSIGNED/i',
            $sql,
            'mid column must be BIGINT UNSIGNED'
        );
        $this->assertStringContainsString('PRIMARY KEY (mid)', $sql, 'PRIMARY KEY must reference mid');
    }

    /**
     * @test
     * SQL must declare local_path as VARCHAR(255) NOT NULL
     */
    public function testSqlContainsLocalPathVarchar255(): void
    {
        $sql = \TP_DB_Migrations::get_link_previews_table_sql('wp_tp_link_previews');

        $this->assertMatchesRegularExpression(
            '/local_path\s+VARCHAR\(255\)\s+NOT NULL/i',
            $sql,
            'local_path must be VARCHAR(255) NOT NULL'
        );
    }

    /**
     * @test
     * SQL must declare original_url as TEXT
     */
    public function testSqlContainsOriginalUrlText(): void
    {
        $sql = \TP_DB_Migrations::get_link_previews_table_sql('wp_tp_link_previews');

        $this->assertMatchesRegularExpression(
            '/original_url\s+TEXT/i',
            $sql,
            'original_url must be TEXT'
        );
    }

    /**
     * @test
     * SQL must declare width and height as INT UNSIGNED with default 0
     */
    public function testSqlContainsWidthAndHeightIntUnsignedDefault0(): void
    {
        $sql = \TP_DB_Migrations::get_link_previews_table_sql('wp_tp_link_previews');

        $this->assertMatchesRegularExpression(
            '/width\s+INT UNSIGNED/i',
            $sql,
            'width must be INT UNSIGNED'
        );
        $this->assertMatchesRegularExpression(
            '/height\s+INT UNSIGNED/i',
            $sql,
            'height must be INT UNSIGNED'
        );
        // Both columns default to 0
        $widthMatches = preg_match('/width\s+INT UNSIGNED[^,\n]*DEFAULT 0/i', $sql);
        $heightMatches = preg_match('/height\s+INT UNSIGNED[^,\n]*DEFAULT 0/i', $sql);
        $this->assertSame(1, $widthMatches, 'width must DEFAULT 0');
        $this->assertSame(1, $heightMatches, 'height must DEFAULT 0');
    }

    /**
     * @test
     * SQL must declare created_at and updated_at as DATETIME NOT NULL
     */
    public function testSqlContainsCreatedAtAndUpdatedAtDatetimeNotNull(): void
    {
        $sql = \TP_DB_Migrations::get_link_previews_table_sql('wp_tp_link_previews');

        $this->assertMatchesRegularExpression(
            '/created_at\s+DATETIME\s+NOT NULL/i',
            $sql,
            'created_at must be DATETIME NOT NULL'
        );
        $this->assertMatchesRegularExpression(
            '/updated_at\s+DATETIME\s+NOT NULL/i',
            $sql,
            'updated_at must be DATETIME NOT NULL'
        );
    }

    /**
     * @test
     * SQL must embed the caller-supplied table name (respects wpdb prefix)
     */
    public function testSqlContainsSuppliedTableName(): void
    {
        $sql = \TP_DB_Migrations::get_link_previews_table_sql('custom_tp_link_previews');

        $this->assertStringContainsString(
            'custom_tp_link_previews',
            $sql,
            'SQL must contain the table name supplied by the caller'
        );
    }

    // -------------------------------------------------------------------------
    // AC2 (idempotency guard) — regression: no raw CREATE TABLE without dbDelta
    // -------------------------------------------------------------------------

    /**
     * @test
     * The run() method delegates to dbDelta(), not raw mysqli_query().
     *
     * We verify this structurally: the migration source file must not contain
     * a raw `CREATE TABLE` outside of the SQL string prepared for dbDelta().
     * Specifically, the word "dbDelta" must appear in the file.
     */
    public function testMigrationFileUsesDeltaNotRawQuery(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-tp-db-migrations.php');

        $this->assertIsString($source);
        $this->assertStringContainsString(
            'dbDelta',
            $source,
            'Migration file must delegate to dbDelta() for idempotent schema management'
        );
    }

    /**
     * @test
     * Migration source must not call mysqli_query directly (no raw DDL bypass)
     */
    public function testMigrationFileDoesNotCallMysqliQueryDirectly(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-tp-db-migrations.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString(
            'mysqli_query',
            $source,
            'Migration must not use raw mysqli_query; use dbDelta() only'
        );
    }

    // -------------------------------------------------------------------------
    // AC3 variant — maybe_run() guard: version_compare logic is present
    // -------------------------------------------------------------------------

    /**
     * @test
     * The maybe_run() method must exist and the source must contain version_compare
     * so future schema versions are detected
     */
    public function testMaybeRunMethodExistsAndUsesVersionCompare(): void
    {
        $this->assertTrue(
            method_exists('TP_DB_Migrations', 'maybe_run'),
            'TP_DB_Migrations must have a maybe_run() method for the upgrade path'
        );

        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-tp-db-migrations.php');
        $this->assertStringContainsString(
            'version_compare',
            $source,
            'maybe_run() must use version_compare() to detect pending migrations'
        );
    }

    // -------------------------------------------------------------------------
    // AC4 — uploads directory path uses wp_upload_dir() (not a hardcoded path)
    // -------------------------------------------------------------------------

    /**
     * @test
     * The migration source must use wp_upload_dir() and wp_mkdir_p() for the
     * preview images directory — never a hardcoded absolute path
     */
    public function testMigrationUsesWpUploadDirAndWpMkdirP(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-tp-db-migrations.php');

        $this->assertStringContainsString(
            'wp_upload_dir()',
            $source,
            'Must use wp_upload_dir() to locate the uploads base dir'
        );
        $this->assertStringContainsString(
            'wp_mkdir_p',
            $source,
            'Must use wp_mkdir_p() to create the preview images directory'
        );
        $this->assertStringContainsString(
            'tp-link-previews',
            $source,
            'Must reference tp-link-previews as the subdirectory name'
        );
    }
}
