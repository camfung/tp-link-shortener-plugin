# Domain Pitfalls: TerrWallet Integration (v2.2)

**Domain:** Integrating WooCommerce Wallet (TeraWallet) API into existing WordPress plugin usage dashboard
**Researched:** 2026-03-10
**Confidence:** HIGH for architecture/integration pitfalls (based on codebase inspection + WC REST API docs); MEDIUM for TeraWallet-specific behaviors (based on GitHub wiki + community reports)

---

## Critical Pitfalls

Mistakes that cause rewrites, data corruption, or security vulnerabilities.

### Pitfall 1: Loopback HTTP Request to Own Server for WC REST API

**What goes wrong:**
The TerrWallet API lives at `trafficportal.dev/wp-json/wc/v3/wallet/`. The plugin also runs on `trafficportal.dev`. Making an HTTP request (via `wp_remote_get` or cURL) from the server to itself creates a loopback request. On many WordPress hosting environments (especially behind Cloudflare, reverse proxies, or Docker), loopback requests fail with cURL error 28 (timeout), get blocked by bot protection, or deadlock because PHP-FPM has no available workers to serve the request while it is also waiting for the response.

**Why it happens:**
The existing `TrafficPortalApiClient` uses cURL (`CurlHttpClient`) to call an external Lambda API on AWS. Developers naturally reach for the same pattern to call the WC REST API. But the WC REST API is local -- it is served by the same WordPress installation. An HTTP request from the server to itself is fundamentally different from an HTTP request to an external service.

**Consequences:**
- Timeout errors on shared hosting where PHP workers are limited
- 403 errors behind Cloudflare (bot protection blocks server-to-server requests to own domain)
- Doubled server load (each dashboard page load triggers a PHP request that spawns another PHP request)
- SSL certificate verification failures when the server's internal IP does not match the domain's certificate

**Prevention:**
Do NOT make HTTP requests to the local WC REST API. Instead, use WordPress's internal REST API dispatch mechanism:

```php
// BAD: HTTP loopback request
$response = wp_remote_get('https://trafficportal.dev/wp-json/wc/v3/wallet/?email=' . $email, [
    'headers' => ['Authorization' => 'Basic ' . base64_encode($key . ':' . $secret)]
]);

// GOOD: Internal REST API dispatch (no HTTP request, no loopback)
$request = new \WP_REST_Request('GET', '/wc/v3/wallet');
$request->set_query_params(['email' => $email]);
$response = rest_do_request($request);
$data = $response->get_data();
```

Alternatively, use TeraWallet's PHP functions directly if available (e.g., `woo_wallet()->wallet->get_transactions()`), bypassing the REST API entirely.

**Detection:**
- cURL error 28 in error logs when loading the usage dashboard
- Dashboard works on local dev but fails on staging/production
- `wp_remote_get` to own domain in the codebase

**Phase:** Must be addressed in Phase 1 (API client design). Getting this wrong requires a full rewrite of the client layer.

---

### Pitfall 2: Date Format Mismatch Between APIs

**What goes wrong:**
The usage API returns dates as `YYYY-MM-DD` (ISO date, no time component). The TeraWallet API returns dates as `YYYY-MM-DD HH:MM:SS` (MySQL datetime format with time component). When merging by date, a naive string comparison (`"2026-03-10" === "2026-03-10 14:30:22"`) fails. Every wallet transaction appears as an unmatched row in the merged data.

**Why it happens:**
The two APIs are maintained by different teams with different conventions. The usage API aggregates per-day and returns date-only strings. TeraWallet stores individual transactions with full timestamps. The developer writing the merge adapter assumes both APIs return the same date format because the PROJECT.md says "merge by date."

**Consequences:**
- Wallet transactions never align with usage rows -- "Other Services" column is always empty
- Or worse: partial matches on some days, missing on others, creating an inconsistent UI
- Sorting by date produces interleaved garbage if date strings and datetime strings are mixed

**Prevention:**
The adapter that merges the two datasets must normalize dates BEFORE matching:

```php
// Normalize TeraWallet datetime to date-only for matching
$walletDate = substr($transaction['date'], 0, 10); // "2026-03-10 14:30:22" -> "2026-03-10"
```

Or more robustly:

```php
$walletDate = (new \DateTime($transaction['date']))->format('Y-m-d');
```

The merge key is always `Y-m-d`. Multiple wallet transactions on the same day should be summed before merging with the usage row for that date.

**Detection:**
- "Other Services" column shows $0.00 for all rows despite wallet transactions existing
- Unit test that creates wallet transactions and usage data on the same date fails to merge

**Phase:** Must be addressed in Phase 2 (merge adapter). Add unit tests with explicit date format assertions.

---

### Pitfall 3: WC REST API Authentication When Using Internal Dispatch

**What goes wrong:**
If you use `rest_do_request()` for internal dispatch, the request runs in the same PHP context as the AJAX handler. The WC REST API authentication layer (`WC_REST_Authentication`) expects either: (a) Basic Auth headers with consumer key/secret, or (b) an authenticated WordPress user with appropriate capabilities. Internal requests via `rest_do_request()` do not carry HTTP headers, so Basic Auth fails. But if the current user (from the AJAX session) does not have WooCommerce capabilities, the WC endpoint returns 401 Unauthorized.

**Why it happens:**
The AJAX handler runs as the logged-in WordPress user (the customer viewing their dashboard). WooCommerce REST API endpoints require either API key auth or a user with `manage_woocommerce` capability. Regular customers do not have this capability. The developer assumes that since the code runs on the server, authentication is not needed.

**Consequences:**
- 401 Unauthorized errors when fetching wallet data for regular users
- Works fine when tested by admin users, fails for regular customers
- Security review flags the workaround of granting customers `manage_woocommerce` capability

**Prevention:**
Two approaches, in order of preference:

1. **Bypass the REST API entirely** -- use TeraWallet's PHP functions directly:
```php
// Direct PHP call -- no REST API auth needed
$user_id = get_current_user_id();
$transactions = get_wallet_transactions([
    'user_id' => $user_id,
    'per_page' => -1,
]);
```

2. **If REST API is required** -- temporarily elevate to an application-level context:
```php
// Create an internal request with proper auth context
add_filter('woocommerce_rest_check_permissions', function($permission, $context, $object_id, $post_type) {
    // Only allow for our specific internal call
    if (doing_action('wp_ajax_tp_get_usage_summary')) {
        return true;
    }
    return $permission;
}, 10, 4);
```

The first approach (direct PHP) is strongly preferred. It avoids all REST API auth complexity and is faster.

**Detection:**
- Works for admin, fails for customer -- the classic auth pitfall
- 401 errors in AJAX responses when non-admin users load the dashboard

**Phase:** Must be addressed in Phase 1 (API client design). The auth approach determines the entire client architecture.

---

### Pitfall 4: User ID Mismatch Between APIs

**What goes wrong:**
The existing usage API uses a Traffic Portal `uid` (obtained via `TP_Link_Shortener::get_user_id()`, which returns the WordPress user ID). The TeraWallet API requires an email address (`?email={email}`) or a WordPress user ID depending on the endpoint. The internal `API_REFERENCE.md` wallet endpoints use `wpUserId`, but the WC REST API endpoint uses `email`. If the wrong identifier is passed, the wallet data returns empty or belongs to the wrong user.

**Why it happens:**
Three different identifier conventions exist in this system:
- Traffic Portal API: `uid` (which happens to be the WP user ID)
- TeraWallet REST API (wp-json): `email` parameter
- Internal Lambda wallet proxy: `wpUserId` path parameter

The developer may use the `uid` from the existing flow and pass it to an endpoint that expects email, or vice versa.

**Consequences:**
- Empty wallet data (no user found for email/ID)
- Wrong user's wallet data displayed (if ID/email mapping is wrong)
- Security vulnerability: exposing another user's financial data

**Prevention:**
Resolve the email from the WordPress user ID at the PHP layer, never from the frontend:

```php
$wp_user_id = get_current_user_id();
$user = get_userdata($wp_user_id);
$email = $user->user_email;
```

If using TeraWallet PHP functions directly, use the WordPress user ID (which TeraWallet maps internally). If using the REST API, use the email parameter. Never accept the email from the frontend POST data.

**Detection:**
- Wallet data is empty for a user who definitely has transactions
- Different users see the same wallet data

**Phase:** Phase 1 (API client). Server-side user resolution, never trust client-provided identity.

---

## Moderate Pitfalls

### Pitfall 5: Multiple Wallet Transactions Per Day Not Aggregated

**What goes wrong:**
The usage API returns one row per day. TeraWallet may have multiple transactions on the same day (e.g., a top-up at 9am and a charge at 3pm). If the merge adapter maps transactions 1:1 with usage rows, days with multiple transactions create duplicate rows or only show the first/last transaction.

**Prevention:**
The adapter must aggregate wallet transactions by date BEFORE merging with usage data:

```php
$walletByDate = [];
foreach ($transactions as $tx) {
    $date = substr($tx['date'], 0, 10);
    if (!isset($walletByDate[$date])) {
        $walletByDate[$date] = ['total' => 0, 'descriptions' => []];
    }
    if ($tx['type'] === 'credit') {
        $walletByDate[$date]['total'] += (float) $tx['amount'];
        $walletByDate[$date]['descriptions'][] = $tx['details'];
    }
}
```

Then merge: for each usage row, look up `$walletByDate[$usageRow['date']]` and attach the aggregated amount + descriptions.

**Phase:** Phase 2 (merge adapter). Unit test with 3+ transactions on a single day.

---

### Pitfall 6: Wallet Transactions Exist on Dates With No Usage Data

**What goes wrong:**
A user might have wallet top-ups on days with zero link activity. The usage API returns NO row for days with zero hits. The wallet has a credit transaction on that day. If the merge only iterates usage rows and looks up wallet data, the wallet-only days are silently dropped.

**Prevention:**
The merge must be a full outer join by date, not a left join from usage data:

```php
// Collect all dates from both sources
$allDates = array_unique(array_merge(
    array_column($usageDays, 'date'),
    array_keys($walletByDate)
));
sort($allDates);

// Build merged rows for ALL dates
foreach ($allDates as $date) {
    $usage = $usageByDate[$date] ?? ['totalHits' => 0, 'hitCost' => 0, 'balance' => 0];
    $wallet = $walletByDate[$date] ?? ['total' => 0, 'descriptions' => []];
    // ... build merged row ...
}
```

Decide at the product level: should days with ONLY wallet transactions (no usage) appear in the table? If yes, implement full outer join. If no, implement left join from usage and accept that some wallet data is invisible.

**Phase:** Phase 2 (merge adapter). Requires a product decision documented before implementation.

---

### Pitfall 7: Timezone Discrepancy Between APIs

**What goes wrong:**
The usage API stores dates in UTC (confirmed by existing `formatDateISO` using `getUTCFullYear()`). TeraWallet stores transaction dates in the WordPress site timezone (typically set in Settings > General). If the site timezone is `America/Toronto` (UTC-5), a wallet transaction at 11pm Toronto time on March 10 is stored as `2026-03-10 23:00:00` in the database but corresponds to `2026-03-11` in UTC. The merge puts this transaction on March 10 (based on its local timestamp) while the usage data has it affecting March 11 (based on UTC).

**Why it happens:**
WordPress's `current_time('mysql')` returns site-local time. TeraWallet uses this for transaction timestamps. The Lambda-based usage API operates in UTC. One-day-off mismatches appear for transactions near midnight in any non-UTC timezone.

**Prevention:**
Convert TeraWallet timestamps to UTC before extracting the date for merge:

```php
$siteTimezone = wp_timezone();
$utcTimezone = new \DateTimeZone('UTC');

$txDate = new \DateTime($transaction['date'], $siteTimezone);
$txDate->setTimezone($utcTimezone);
$mergeDate = $txDate->format('Y-m-d');
```

Alternatively, if the product decision is "merge by site-local date" (which may be more intuitive for users), convert the usage API dates from UTC to site timezone before merging. Be consistent -- pick one timezone for the merge key and document the decision.

**Detection:**
- Transactions near midnight appear on the "wrong" day in the table
- Test passes in UTC-0 environments, fails in UTC-5

**Phase:** Phase 2 (merge adapter). Add a unit test with a transaction at 11:30pm in a non-UTC timezone.

---

### Pitfall 8: Performance Degradation From Sequential API Calls

**What goes wrong:**
The current `ajax_get_usage_summary()` makes one API call to the external Lambda. Adding the wallet data fetch makes it two sequential operations per dashboard load. If the wallet fetch takes 500ms (REST API dispatch + database query), the dashboard load time increases by 500ms. On slow database servers, this could be 1-2 seconds.

**Why it happens:**
PHP is single-threaded. The AJAX handler must complete both data fetches before returning to the browser. The developer adds the wallet fetch after the usage fetch in the same handler, making them sequential.

**Prevention:**
If using internal `rest_do_request()` or direct PHP calls, both happen in the same PHP process and are inherently sequential. Mitigation options:

1. **Cache wallet data with transients** -- wallet transactions rarely change mid-session:
```php
$cache_key = 'tp_wallet_' . $user_id . '_' . $start_date . '_' . $end_date;
$cached = get_transient($cache_key);
if ($cached !== false) {
    $wallet_data = $cached;
} else {
    $wallet_data = $this->fetch_wallet_transactions($user_id, $start_date, $end_date);
    set_transient($cache_key, $wallet_data, 5 * MINUTE_IN_SECONDS);
}
```

2. **Separate AJAX endpoint** -- fetch wallet data in a parallel AJAX call from the browser:
```javascript
// Fire both requests simultaneously from the browser
$.when(
    $.ajax({ action: 'tp_get_usage_summary', ... }),
    $.ajax({ action: 'tp_get_wallet_transactions', ... })
).then(function(usageResp, walletResp) {
    // Merge client-side
});
```

Option 2 is more complex but faster. Option 1 is simpler and sufficient for v2.2.

**Phase:** Phase 1 (architecture decision). Choose single-endpoint vs dual-endpoint before building.

---

### Pitfall 9: Error Handling When One API Succeeds and the Other Fails

**What goes wrong:**
The usage API call succeeds, but the wallet data fetch fails (TeraWallet plugin deactivated, database error, permission denied). If the AJAX handler treats any failure as a total failure, the entire dashboard shows an error state -- even though 90% of the data (usage stats) is available.

**Why it happens:**
The existing `ajax_get_usage_summary()` has a try/catch that returns an error response on any exception. Adding the wallet fetch inside the same try block means a wallet failure kills the entire response.

**Consequences:**
- Dashboard shows "Error loading usage data" when only the wallet data is unavailable
- Users cannot see their usage stats because of an unrelated wallet plugin issue
- TeraWallet plugin deactivation breaks the usage dashboard entirely

**Prevention:**
Fetch wallet data in a separate try/catch. Return usage data even if wallet fails:

```php
// Always fetch usage data first
$usage = $this->client->getUserActivitySummary($uid, $start_date, $end_date);
$validated = $this->validate_usage_summary_response($usage);

// Attempt wallet data -- failure is non-fatal
$wallet_data = [];
$wallet_error = null;
try {
    $wallet_data = $this->fetch_wallet_transactions($user_id, $start_date, $end_date);
} catch (\Exception $e) {
    $wallet_error = $e->getMessage();
    $this->log_to_file('Wallet fetch failed (non-fatal): ' . $e->getMessage());
}

// Merge whatever we have
$merged = $this->merge_usage_and_wallet($validated['days'], $wallet_data);

wp_send_json_success([
    'days' => $merged,
    'wallet_available' => empty($wallet_error),
    'wallet_error' => $wallet_error,
]);
```

The frontend should render the table with empty "Other Services" cells if wallet data is unavailable, not show an error state.

**Detection:**
- Deactivate TeraWallet plugin -- dashboard should still work with empty Other Services column
- Simulate wallet database timeout -- dashboard should degrade gracefully

**Phase:** Phase 2 (merge adapter) and Phase 3 (frontend). Design the partial-failure response shape early.

---

### Pitfall 10: WC REST API Consumer Key/Secret Storage in wp_options

**What goes wrong:**
If using the WC REST API with HTTP requests (despite Pitfall 1 recommending against it), the consumer key and secret must be stored somewhere. Storing them in `wp_options` as plaintext is a security risk. Storing them in plugin settings UI exposes them to any admin user. Hardcoding them in the plugin source means they are in version control.

**Why it happens:**
WooCommerce generates consumer key/secret pairs for REST API access. The developer needs to store these credentials for the plugin to authenticate. There is no standard WordPress pattern for storing third-party API credentials securely.

**Prevention:**
If HTTP requests to the WC REST API are truly needed (which they should NOT be per Pitfall 1):
- Store credentials in `wp-config.php` as constants: `define('TP_WC_CONSUMER_KEY', 'ck_xxx');`
- Never store in the database or plugin settings
- Never commit to version control
- Use `wp_options` only if the value is encrypted

But the real prevention is to avoid needing WC REST API credentials entirely by using direct PHP function calls (Pitfall 1 and Pitfall 3).

**Phase:** Phase 1 (API client design). If the direct PHP approach is used, this pitfall is eliminated entirely.

---

## Minor Pitfalls

### Pitfall 11: TeraWallet Pagination Not Handled

**What goes wrong:**
The TeraWallet API supports `per_page` and `page` parameters. If a user has more transactions than the default `per_page` (typically 10), only the first page is returned. The adapter silently works with incomplete data, showing only some wallet transactions.

**Prevention:**
Either request all transactions by setting `per_page` to a high number, or implement pagination to fetch all pages:

```php
$page = 1;
$all_transactions = [];
do {
    $response = $this->fetch_wallet_page($email, $page, 100);
    $all_transactions = array_merge($all_transactions, $response);
    $page++;
} while (count($response) === 100);
```

For the date-filtered use case, consider filtering server-side if the API supports it, or filter client-side after fetching all transactions within the date range.

**Phase:** Phase 1 (API client). Test with a user who has 50+ wallet transactions.

---

### Pitfall 12: Wallet Amount Precision (String vs Float)

**What goes wrong:**
The TeraWallet API returns amounts as strings with high precision: `"3.50000000"`. The usage API returns amounts as floats: `0.15`. JavaScript's floating-point arithmetic means `parseFloat("3.50000000") + 0.15` may not equal `3.65` exactly. If the merged data is used for balance calculations or displayed with inconsistent decimal places, users see "$3.50000000" or rounding errors.

**Prevention:**
Convert all amounts to cents (integers) for arithmetic, format to 2 decimal places for display only:

```php
// PHP: normalize wallet amount to float with 2 decimal precision
$amount = round((float) $transaction['amount'], 2);
```

```javascript
// JS: use the existing formatCurrency() which already rounds to cents
formatCurrency(day.otherServices); // Already handles precision via Math.round(value * 100) / 100
```

The existing `formatCurrency()` in `usage-dashboard.js` already uses cent-snapping (`Math.round(value * 100) / 100`), so this is handled if the PHP layer delivers clean floats.

**Phase:** Phase 1 (API client) for PHP normalization, Phase 3 (frontend) already handled by existing `formatCurrency()`.

---

### Pitfall 13: TeraWallet Plugin Not Installed/Active

**What goes wrong:**
The plugin assumes TeraWallet is installed and active. If a WordPress installation uses a different wallet plugin, or has no wallet plugin, calling TeraWallet PHP functions throws a fatal error ("Call to undefined function").

**Prevention:**
Check for TeraWallet availability before attempting to use it:

```php
private function is_terawallet_available(): bool {
    return function_exists('woo_wallet') || class_exists('Woo_Wallet');
}
```

If unavailable, return empty wallet data (not an error). The "Other Services" column simply shows nothing.

**Phase:** Phase 1 (API client). This check must be the first thing the wallet client does.

---

### Pitfall 14: "Other Services" Column Tooltip Rendering With HTML Injection

**What goes wrong:**
Wallet transaction `details` field contains user-provided text (e.g., "Deposit from payment", "Usage charge"). If this text is rendered inside a tooltip without escaping, and a malicious description contains HTML or JavaScript, it creates an XSS vulnerability.

**Prevention:**
Escape all wallet descriptions before rendering in tooltips:

```javascript
// BAD: direct insertion
tooltip.html(day.otherServicesDetails.join('<br>'));

// GOOD: text content only, or escaped HTML
var $tooltip = $('<div>');
day.otherServicesDetails.forEach(function(desc) {
    $tooltip.append($('<div>').text(desc));
});
```

In PHP, sanitize descriptions before sending to the frontend:

```php
$details = sanitize_text_field($transaction['details']);
```

**Phase:** Phase 3 (frontend rendering). Standard WordPress XSS prevention.

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| API Client Design (Phase 1) | Loopback HTTP request to WC REST API (Pitfall 1) | Use direct PHP functions or `rest_do_request()`, never `wp_remote_get` to own server |
| API Client Design (Phase 1) | WC REST API auth for non-admin users (Pitfall 3) | Bypass REST API entirely with direct TeraWallet PHP calls |
| API Client Design (Phase 1) | User ID vs email mismatch (Pitfall 4) | Resolve email server-side from `get_current_user_id()`, never from POST data |
| API Client Design (Phase 1) | TeraWallet not installed (Pitfall 13) | Feature-detect before calling; degrade gracefully |
| API Client Design (Phase 1) | Pagination not handled (Pitfall 11) | Fetch all pages or set high `per_page` limit |
| Merge Adapter (Phase 2) | Date format mismatch (Pitfall 2) | Normalize to `Y-m-d` before merge key comparison |
| Merge Adapter (Phase 2) | Timezone discrepancy (Pitfall 7) | Convert TeraWallet dates to UTC (or decide on site-local) before extracting date |
| Merge Adapter (Phase 2) | Multiple transactions per day (Pitfall 5) | Aggregate by date before merging |
| Merge Adapter (Phase 2) | Wallet-only dates dropped (Pitfall 6) | Full outer join, not left join from usage data |
| Merge Adapter (Phase 2) | One API fails, dashboard dies (Pitfall 9) | Separate try/catch; wallet failure is non-fatal |
| Frontend (Phase 3) | XSS via tooltip descriptions (Pitfall 14) | Escape all user-provided text with `.text()` not `.html()` |
| Frontend (Phase 3) | Amount precision display (Pitfall 12) | Existing `formatCurrency()` handles this; ensure PHP sends clean floats |

---

## Decision Log: Key Architectural Choices That Prevent Pitfalls

| Decision | Prevents Pitfall | Rationale |
|----------|-----------------|-----------|
| Use direct PHP calls, not HTTP requests, for wallet data | 1 (loopback), 3 (auth), 10 (credentials) | Same server, same process. No HTTP overhead, no auth complexity, no credential storage. |
| Normalize all dates to `Y-m-d` before merge | 2 (format mismatch), 7 (timezone) | Single canonical date format for the merge key. |
| Wallet fetch failure is non-fatal | 9 (partial failure) | Usage dashboard works even if TeraWallet is broken, deactivated, or uninstalled. |
| Server-side user identity resolution | 4 (user ID mismatch) | `get_current_user_id()` in PHP, never from frontend. Consistent with existing DATA-02 pattern. |
| Feature-detect TeraWallet before use | 13 (plugin not installed) | Plugin must work on WP installations without TeraWallet. |

---

## Sources

- Codebase inspection: `includes/class-tp-api-handler.php` lines 1573-1670 -- existing `ajax_get_usage_summary()` handler and `validate_usage_summary_response()` (HIGH confidence)
- Codebase inspection: `assets/js/usage-dashboard.js` -- `loadData()`, `renderRows()`, `formatCurrency()`, `formatDateISO()` functions (HIGH confidence)
- Codebase inspection: `includes/TrafficPortal/TrafficPortalApiClient.php` -- cURL-based HTTP client pattern (HIGH confidence)
- Codebase inspection: `API_REFERENCE.md` lines 1812-1896 -- Wallet API endpoint documentation showing `wpUserId` path params and datetime format `"2025-12-03 11:51:47"` (HIGH confidence)
- Codebase inspection: `docs/API-REQUIREMENTS-V2.md` -- usage API response shape `{ date: "YYYY-MM-DD", totalHits, hitCost, balance }` (HIGH confidence)
- [TeraWallet API V3 wiki](https://github.com/malsubrata/woo-wallet/wiki/API-V3) -- REST API endpoints, `email` parameter, `per_page`/`page` pagination, transaction response shape (MEDIUM confidence)
- [WooCommerce REST API Authentication docs](https://woocommerce.github.io/woocommerce-rest-api-docs/) -- consumer key/secret, Basic Auth, query string auth (HIGH confidence)
- [WordPress loopback request issues](https://github.com/docker-library/wordpress/issues/493) -- cURL error 28 on self-requests, Cloudflare blocking (MEDIUM confidence)
- [WordPress REST API loopback failures behind Cloudflare](https://lukapaunovic.com/2025/04/24/fix-wordpress-loopback-and-rest-api-403-errors-behind-cloudflare/) -- 403 errors on server-to-server requests (MEDIUM confidence)
- [WooCommerce REST API auth issue #26847](https://github.com/woocommerce/woocommerce/issues/26847) -- `wp_get_current_user()` conflicts with WC auth (MEDIUM confidence)
- [wp_timezone() reference](https://developer.wordpress.org/reference/functions/wp_timezone/) -- WordPress site timezone resolution (HIGH confidence)
- [WordPress Transients API](https://developer.wordpress.org/apis/transients/) -- caching pattern for wallet data (HIGH confidence)

---

*Pitfalls research for: TerrWallet Integration (v2.2 milestone)*
*Researched: 2026-03-10*
