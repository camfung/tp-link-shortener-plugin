<?php
/**
 * Database migration helper for the Traffic Portal Link Shortener plugin.
 *
 * Centralises all schema creation / upgrade logic. Wired into both the
 * activation hook (fresh installs) and the plugins_loaded upgrade check
 * (existing installs receiving a plugin update).
 *
 * Usage:
 *   TP_DB_Migrations::run();
 *
 * Idempotent — safe to call on every activation / upgrade; dbDelta() only
 * applies changes that are needed.
 */

declare(strict_types=1);

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Current schema version.  Bump this string whenever a new migration is added
 * so that the upgrade-check path (`maybe_run()`) knows to re-run.
 */
define('TP_DB_VERSION', '1.1.0');

/**
 * Fully-qualified table name for the link-previews cache.
 * Use this constant everywhere you reference the table to avoid drift.
 */
define('TP_LINK_PREVIEWS_TABLE', $GLOBALS['wpdb']->prefix . 'tp_link_previews');

class TP_DB_Migrations
{
    /**
     * Run all migrations unconditionally.
     *
     * Called from the activation hook and, if the stored DB version is behind
     * TP_DB_VERSION, from the plugins_loaded upgrade path.
     */
    public static function run(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        self::create_link_previews_table($wpdb);

        // Abort version-bump if directory creation fails so maybe_run() retries on next request.
        if (!self::create_link_previews_directory()) {
            return;
        }

        update_option('tp_link_shortener_db_version', TP_DB_VERSION);
    }

    /**
     * Run migrations only when the stored DB version is behind the current one.
     *
     * Hook this to `plugins_loaded` so existing installs get the table on the
     * first request after a plugin update (activation hook does not fire on
     * simple file updates).
     */
    public static function maybe_run(): void
    {
        $stored = get_option('tp_link_shortener_db_version', '0');
        if (version_compare((string) $stored, TP_DB_VERSION, '<')) {
            self::run();
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Create / update the wp_tp_link_previews table via dbDelta().
     *
     * dbDelta() requirements (from WP docs):
     *  - Two spaces before the field type in each column definition.
     *  - PRIMARY KEY on its own line (not inline with the column).
     *  - Column list must be comma-terminated on each line.
     */
    /**
     * Return the SQL used to create the link-previews table.
     *
     * Single source of truth — called by both create_link_previews_table() and
     * get_link_previews_table_sql() so the schema never drifts between them.
     *
     * @param string $table_name      Fully-qualified table name (e.g. wp_tp_link_previews)
     * @param string $charset_collate Collation clause from wpdb->get_charset_collate()
     * @return string
     */
    private static function build_link_previews_sql(string $table_name, string $charset_collate): string
    {
        return "CREATE TABLE {$table_name} (
  mid BIGINT UNSIGNED NOT NULL,
  local_path VARCHAR(255) NOT NULL DEFAULT '',
  original_url TEXT,
  width INT UNSIGNED NOT NULL DEFAULT 0,
  height INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (mid)
) {$charset_collate};";
    }

    private static function create_link_previews_table(\wpdb $wpdb): void
    {
        $table          = $wpdb->prefix . 'tp_link_previews';
        $charset_collate = $wpdb->get_charset_collate();

        dbDelta(self::build_link_previews_sql($table, $charset_collate));
    }

    /**
     * Create the uploads sub-directory used to store sideloaded preview images.
     *
     * Uses wp_upload_dir() so the path respects multisite / custom upload-dir
     * configurations, and wp_mkdir_p() which sets standard WP ownership /
     * permissions (matching the rest of the uploads tree).
     */
    private static function create_link_previews_directory(): bool
    {
        $upload_dir    = wp_upload_dir();
        $previews_dir  = $upload_dir['basedir'] . '/tp-link-previews';

        if (file_exists($previews_dir)) {
            return true;
        }

        $created = wp_mkdir_p($previews_dir);
        if (!$created) {
            error_log('TP_DB_Migrations: wp_mkdir_p() failed to create tp-link-previews directory at: ' . $previews_dir);
        }
        return $created;
    }

    /**
     * Return the SQL used to create the link-previews table.
     *
     * Exposed for testing — callers can assert the SQL contains the expected
     * columns without needing a live database.
     */
    public static function get_link_previews_table_sql(string $table_name, string $charset_collate = ''): string
    {
        return self::build_link_previews_sql($table_name, $charset_collate);
    }
}
