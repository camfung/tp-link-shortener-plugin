# Phase 9: Wallet Client - Research

**Researched:** 2026-03-10
**Domain:** TerrWallet (woo-wallet) PHP integration within WordPress plugin
**Confidence:** HIGH

## Summary

Phase 9 requires building a PHP client that fetches wallet credit transactions from the TerrWallet (woo-wallet) plugin for a given user. The critical discovery is that **direct PHP function calls are the primary approach** -- the woo-wallet plugin exposes `get_wallet_transactions()` as a global function that queries the `{prefix}_woo_wallet_transactions` database table directly. This function supports `user_id`, `after`/`before` date filtering, `where` clauses for type filtering (credit/debit), pagination via `limit`, and ordering -- everything this phase needs.

The REST API (`/wp-json/wc/v3/wallet/`) exists but requires `manage_woocommerce` capability and uses email-based lookup. Using `rest_do_request()` would bypass HTTP but NOT the permission callback, meaning regular users could not access their own wallet data through REST. Direct PHP calls avoid this entirely and are simpler.

**Primary recommendation:** Use `get_wallet_transactions()` with `after`/`before` date params and `where` type=credit filter. Detect plugin availability via `function_exists('get_wallet_transactions')`. Fall back to WC REST API with consumer key/secret auth ONLY for cron/CLI contexts where the function is unavailable.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Use `rest_do_request()` for internal WordPress REST dispatch -- no HTTP loopback, no network overhead, runs in-process
- Primary auth: current logged-in user's WordPress session (for dashboard page loads)
- Fallback auth: WC API keys from wp-config.php constants (for cron/CLI contexts where no user session exists)
- Client must support both authenticated page loads and cron/CLI contexts

### Claude's Discretion
- Transaction parsing: which fields to extract, how to identify credits vs debits, date range handling
- Error handling: exception types, retry behavior, missing plugin detection
- Credential config: wp-config.php constant naming convention, validation on missing creds

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| woo-wallet (TerrWallet) | 1.5.x | Wallet transactions source | Already installed on target WordPress site |
| WordPress REST API | Core | Internal request dispatch via `rest_do_request()` | Built into WordPress, no dependencies |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WooCommerce REST API | v3 | Wallet endpoint `/wc/v3/wallet/` | Cron/CLI fallback only |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Direct PHP `get_wallet_transactions()` | REST API via `rest_do_request()` | REST requires `manage_woocommerce` cap -- regular users blocked; direct PHP has no permission overhead |
| `rest_do_request()` | `wp_remote_get()` | HTTP loopback causes timeouts on some hosts; `rest_do_request()` runs in-process |

**Installation:**
No new packages needed. Zero new dependencies (per project constraint).

## Architecture Patterns

### Recommended Project Structure
```
includes/
  TerrWallet/                        # New namespace (follows existing pattern)
    TerrWalletClient.php             # Main client class
    DTO/
      WalletTransaction.php          # Parsed transaction value object
    Exception/
      TerrWalletException.php        # Base exception (typed, catchable)
      TerrWalletNotInstalledException.php  # Plugin not active
      TerrWalletApiException.php     # REST API errors (cron/CLI path)
```

### Pattern 1: Namespace + Autoloader (follows existing pattern)
**What:** Add `TerrWallet\\` namespace to the existing PSR-4 autoloader in `includes/autoload.php`
**When to use:** Always -- this is how the project loads TrafficPortal, ShortCode, and SnapCapture namespaces
**Example:**
```php
// Source: includes/autoload.php (existing pattern)
spl_autoload_register(function ($class) use ($includes_path) {
    $prefix = 'TerrWallet\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $includes_path . '/TerrWallet/' . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
```

### Pattern 2: Dual-Mode Client (Direct PHP + REST Fallback)
**What:** Client tries direct PHP first (`get_wallet_transactions()`), falls back to WC REST API with consumer key/secret auth for cron/CLI contexts
**When to use:** Always -- locked decision from CONTEXT.md

```php
// Primary path: direct PHP call (page load context)
public function getTransactions(int $userId, string $after, string $before): array {
    if (function_exists('get_wallet_transactions')) {
        return $this->fetchViaDirect($userId, $after, $before);
    }
    // Fallback: REST API with WC consumer key/secret
    return $this->fetchViaRest($userId, $after, $before);
}
```

### Pattern 3: rest_do_request() for Internal REST Dispatch
**What:** Use WordPress internal request dispatch instead of HTTP
**When to use:** Cron/CLI fallback when `get_wallet_transactions()` is not available

```php
// Source: WordPress REST API internal dispatch pattern
$request = new \WP_REST_Request('GET', '/wc/v3/wallet');
$request->set_query_params([
    'email'    => $userEmail,
    'per_page' => 100,
    'page'     => $page,
]);
$response = rest_do_request($request);

if ($response->is_error()) {
    $error = $response->as_error();
    throw new TerrWalletApiException($error->get_error_message());
}
$data = $response->get_data();
```

**CRITICAL NOTE:** `rest_do_request()` does NOT run authentication middleware, but it DOES run the permission callback. The woo-wallet permission callback requires `manage_woocommerce` capability. For cron/CLI, you must either:
- Set the current user to an admin before the request
- Or use the `woo_wallet_rest_check_permissions` filter to allow access

### Anti-Patterns to Avoid
- **Using wp_remote_get() for same-server API:** Causes HTTP loopback issues, timeouts, and adds unnecessary network overhead
- **Storing WC API credentials in wp_options:** Exposes secrets via WordPress admin; use wp-config.php constants
- **Querying the woo_wallet_transactions table directly with $wpdb:** Bypasses plugin filters and future schema changes; use `get_wallet_transactions()` which already handles all SQL building safely

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Wallet transaction queries | Custom SQL against `woo_wallet_transactions` table | `get_wallet_transactions()` function | Handles SQL injection prevention, caching, multisite, filters |
| Date range filtering | Custom WHERE clause building | `after`/`before` params in `get_wallet_transactions()` | Built-in, handles edge cases |
| Type filtering (credit/debit) | Manual array_filter after fetch | `where` param: `[['key' => 'type', 'value' => 'credit']]` | Filters at DB level, not in PHP |
| Pagination | Manual OFFSET/LIMIT SQL | `limit` param: `"offset,count"` format | Already implemented safely |
| WooCommerce auth for REST | Custom OAuth1 signing | WC consumer key/secret via query params | Standard WC auth mechanism |

**Key insight:** The `get_wallet_transactions()` function is remarkably full-featured. It supports filtering, pagination, ordering, metadata joins, date ranges, and caching. Building any of this from scratch would be error-prone and fragile against plugin updates.

## Common Pitfalls

### Pitfall 1: REST Permission Callback Blocks Regular Users
**What goes wrong:** `rest_do_request()` dispatches the request in-process but the woo-wallet permission callback checks `current_user_can('manage_woocommerce')`. Regular users get a 403.
**Why it happens:** `rest_do_request()` skips HTTP auth but NOT permission callbacks.
**How to avoid:** Use direct PHP `get_wallet_transactions()` as the primary path. Only use REST for cron/CLI where you control the user context.
**Warning signs:** 403 errors in AJAX responses; wallet data works for admins but not regular users.

### Pitfall 2: v3 API Requires Email, Not User ID
**What goes wrong:** The WC v3 wallet endpoint (`/wc/v3/wallet/`) requires an `email` parameter, not a user ID. The v2 endpoint uses user ID in the URL path.
**Why it happens:** API version difference. v2 uses `/wc/v2/wallet/{id}`, v3 uses `/wc/v3/wallet?email=...`.
**How to avoid:** When using REST fallback, resolve user ID to email via `get_userdata($userId)->user_email`.
**Warning signs:** "email is required" validation errors from the API.

### Pitfall 3: Date Format Mismatch
**What goes wrong:** The `after`/`before` params in `get_wallet_transactions()` expect MySQL datetime format (`YYYY-MM-DD HH:MM:SS`), while the usage API uses `YYYY-MM-DD`.
**Why it happens:** Wallet stores timestamps with time component; usage API uses date-only.
**How to avoid:** Append ` 00:00:00` to `after` and ` 23:59:59` to `before` when passing date-only strings. Or pass date-only and rely on MySQL BETWEEN behavior (which works for date-only strings against datetime columns).
**Warning signs:** Missing transactions at date boundaries.

### Pitfall 4: Plugin Detection Timing
**What goes wrong:** Checking `function_exists('get_wallet_transactions')` too early in WordPress boot (before woo-wallet loads).
**Why it happens:** Plugin load order varies; woo-wallet may load after your plugin.
**How to avoid:** Check at call time (inside the method), not at construction time. The function check happens during an AJAX request or page render, long after all plugins have loaded.
**Warning signs:** Function reported as non-existent even when woo-wallet is active.

### Pitfall 5: Transaction `details` Field is HTML
**What goes wrong:** The `details` field in wallet transactions may contain HTML markup (links, formatting from WooCommerce order references).
**Why it happens:** woo-wallet stores rich text descriptions from various sources (manual credits, order refunds, etc.).
**How to avoid:** Strip HTML tags with `wp_strip_all_tags()` before returning to the frontend, or sanitize with `wp_kses()` if some formatting is desired.
**Warning signs:** Raw HTML appearing in tooltip text on the dashboard.

### Pitfall 6: No Native Date Filtering in REST API
**What goes wrong:** The v3 REST endpoint does not expose `after`/`before` parameters. It only accepts `email`, `per_page`, and `page`.
**Why it happens:** The REST controller passes a minimal args array to `get_wallet_transactions()` without date params.
**How to avoid:** For the REST fallback path, fetch all transactions and filter in PHP, or use the `woo_wallet_rest_api_get_items_args` filter to inject date params.
**Warning signs:** REST path returning far more transactions than expected, slow responses.

## Code Examples

Verified patterns from official woo-wallet source code:

### Fetching Credit Transactions for a User Within a Date Range (Direct PHP)
```php
// Source: woo-wallet/includes/helper/woo-wallet-util.php
$transactions = get_wallet_transactions([
    'user_id' => 125,
    'where'   => [
        [
            'key'      => 'type',
            'value'    => 'credit',
            'operator' => '=',
        ],
    ],
    'after'    => '2026-01-01',
    'before'   => '2026-03-10 23:59:59',
    'order_by' => 'date',
    'order'    => 'ASC',
    'limit'    => '',  // No limit = all results
]);
// Returns array of objects with: transaction_id, user_id, type, amount, date, details, balance, currency, blog_id, deleted
```

### Transaction Object Fields
```php
// Source: woo-wallet database schema and get_wallet_transactions()
// Each transaction object contains:
// - transaction_id (int)    -- unique ID
// - user_id (int)           -- WordPress user ID
// - type (string)           -- 'credit' or 'debit'
// - amount (float)          -- transaction amount
// - date (string)           -- MySQL datetime 'YYYY-MM-DD HH:MM:SS'
// - details (string)        -- description text (may contain HTML)
// - balance (float)         -- wallet balance after transaction
// - currency (string)       -- currency code
// - blog_id (int)           -- multisite blog ID
// - deleted (int)           -- soft delete flag (0 or 1)

// With 'fields' => 'all_with_meta', additional metadata is attached
```

### Plugin Detection Pattern
```php
// Check if woo-wallet is active and functions available
if (!function_exists('get_wallet_transactions')) {
    throw new TerrWalletNotInstalledException(
        'TerrWallet (woo-wallet) plugin is not installed or activated.'
    );
}
```

### REST Fallback with WC Consumer Key/Secret
```php
// Source: WooCommerce REST API auth docs
// For cron/CLI contexts, authenticate with WC API keys
$request = new \WP_REST_Request('GET', '/wc/v3/wallet');
$request->set_query_params([
    'email'              => get_userdata($userId)->user_email,
    'per_page'           => 100,
    'page'               => 1,
    'consumer_key'       => TP_WC_CONSUMER_KEY,
    'consumer_secret'    => TP_WC_CONSUMER_SECRET,
]);
$response = rest_do_request($request);
```

### wp-config.php Constants Pattern
```php
// In wp-config.php:
define('TP_WC_CONSUMER_KEY', 'ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TP_WC_CONSUMER_SECRET', 'cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// In client code:
if (!defined('TP_WC_CONSUMER_KEY') || !defined('TP_WC_CONSUMER_SECRET')) {
    throw new TerrWalletException(
        'WC API credentials not configured. Add TP_WC_CONSUMER_KEY and TP_WC_CONSUMER_SECRET to wp-config.php.'
    );
}
```

### Exception Hierarchy (follows existing project pattern)
```php
// Base exception -- callers catch this for any wallet error
namespace TerrWallet\Exception;
class TerrWalletException extends \Exception {}

// Plugin not installed/active
class TerrWalletNotInstalledException extends TerrWalletException {}

// REST API errors (cron/CLI fallback path)
class TerrWalletApiException extends TerrWalletException {}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| REST v2 `/wc/v2/wallet/{id}` | REST v3 `/wc/v3/wallet?email=` | woo-wallet 1.3.23 | v3 uses email param instead of path param |
| No date filtering in PHP function | `after`/`before` params in `get_wallet_transactions()` | Recent versions | Enables server-side date range queries |
| HTTP loopback for same-site REST | `rest_do_request()` internal dispatch | WordPress core (long-standing) | No network overhead, avoids loopback issues |

**Deprecated/outdated:**
- v2 REST API (`/wc/v2/wallet/{id}`): Still works but v3 is the current version
- `wp_remote_get()` for same-server calls: Anti-pattern; use `rest_do_request()` or direct PHP

## Open Questions

1. **Does `rest_do_request()` pass consumer_key/secret to WC auth layer?**
   - What we know: `rest_do_request()` skips HTTP auth but runs permission callbacks. WC auth via query params may not work because WC authentication happens in `WC_REST_Authentication::authenticate()` which hooks into `determine_current_user` filter -- this runs for HTTP requests but may not trigger for internal dispatch.
   - What's unclear: Whether WC recognizes consumer_key/secret in query params during `rest_do_request()` internal dispatch.
   - Recommendation: Rely on direct PHP calls as primary. For cron/CLI, set the current user to an admin via `wp_set_current_user()` before calling `rest_do_request()`, or simply use `get_wallet_transactions()` directly since cron/CLI contexts still have access to the function if woo-wallet is active.

2. **Transaction `details` field content format**
   - What we know: The field stores text descriptions, possibly with HTML from WooCommerce order references.
   - What's unclear: Exact content format across different transaction sources (manual top-up, order refund, cashback, etc.).
   - Recommendation: Always sanitize with `wp_strip_all_tags()` and truncate to a reasonable length for tooltip display.

3. **Timezone of wallet `date` field**
   - What we know: `get_wallet_transactions()` uses `current_time('mysql', 1)` for the `before` default (UTC). The blocker in STATE.md mentions timezone handling as a product decision.
   - What's unclear: Whether wallet dates are stored in UTC or site timezone.
   - Recommendation: For Phase 9 (client only, no merge), parse dates as-is into `Y-m-d` format. Phase 10 (data merge) will handle timezone normalization.

## Sources

### Primary (HIGH confidence)
- [woo-wallet GitHub source: v3 REST controller](https://github.com/malsubrata/woo-wallet/blob/master/includes/api/Controllers/Version3/class-wc-rest-wallet-controller.php) - Full endpoint code reviewed
- [woo-wallet GitHub source: v2 REST controller](https://github.com/malsubrata/woo-wallet/blob/master/includes/api/class-wc-rest-woo-wallet-controller.php) - Full endpoint code reviewed
- [woo-wallet GitHub source: get_wallet_transactions()](https://github.com/malsubrata/woo-wallet/blob/master/includes/helper/woo-wallet-util.php) - Function signature, query builder, date filtering verified
- [woo-wallet GitHub source: API class](https://github.com/malsubrata/woo-wallet/blob/master/includes/class-woo-wallet-api.php) - Route registration verified
- Existing codebase: `includes/autoload.php`, `includes/TrafficPortal/`, `includes/class-tp-api-handler.php` - Established patterns

### Secondary (MEDIUM confidence)
- [WordPress REST API Handbook: Internal Requests](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) - `rest_do_request()` authentication behavior
- [WP Scholar: Internal WP REST API Calls](https://wpscholar.com/blog/internal-wp-rest-api-calls/) - Verified rest_do_request does not run auth
- [woo-wallet GitHub Wiki: API V3](https://github.com/malsubrata/woo-wallet/wiki/API-V3) - Endpoint documentation

### Tertiary (LOW confidence)
- WC consumer key/secret behavior with `rest_do_request()` internal dispatch -- needs validation in integration test

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Verified from woo-wallet source code on GitHub
- Architecture: HIGH - Follows existing plugin patterns exactly; direct PHP API fully documented
- Pitfalls: HIGH - Permission callback behavior verified from source; date format confirmed from SQL query builder
- REST fallback auth: LOW - Consumer key/secret via rest_do_request() unverified; may need wp_set_current_user() workaround

**Research date:** 2026-03-10
**Valid until:** 2026-04-10 (stable -- woo-wallet API changes infrequently)
