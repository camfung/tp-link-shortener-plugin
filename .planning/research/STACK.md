# Stack Research: TerrWallet API Integration

**Domain:** WordPress plugin -- WooCommerce Wallet API client for usage dashboard
**Researched:** 2026-03-10
**Confidence:** HIGH

## Context: What Already Exists (Do Not Re-add or Change)

These technologies are already loaded and working. The TerrWallet integration builds on top of them.

| Technology | Version | Role in TerrWallet Feature |
|------------|---------|---------------------------|
| jQuery | WP-bundled | AJAX calls in `usage-dashboard.js` -- will receive merged data |
| Bootstrap 5 | 5.3.0 | Table layout already handles extra columns |
| Chart.js | 4.4.1 | No change -- chart renders usage data only |
| `includes/autoload.php` | Current | PSR-4 autoloader -- will register new `TerrWallet\\` namespace |
| `includes/class-tp-api-handler.php` | Current | AJAX handler -- `ajax_get_usage_summary()` will be extended to merge wallet data |
| `includes/TrafficPortal/Http/CurlHttpClient.php` | Current | cURL HTTP client -- **reusable** for WC REST API calls |
| `includes/TrafficPortal/Http/HttpClientInterface.php` | Current | HTTP interface -- **reusable** for wallet client DI |
| `assets/js/usage-dashboard.js` | Current | Renders table rows -- will add "Other Services" column from merged data |
| `templates/usage-dashboard-template.php` | Current | HTML template -- will add `<th>` for Other Services column |
| WordPress `wp-config.php` constants | Current | Credential pattern (`API_KEY`, `SNAPCAPTURE_API_KEY`) -- same pattern for WC keys |

---

## Recommended Stack Additions

### Zero new libraries. One new PHP namespace using existing HTTP infrastructure.

---

### 1. TerrWallet API Client: New `TerrWallet\` Namespace

**What:** A new PHP API client under `includes/TerrWallet/` following the exact same architecture as the existing `TrafficPortal\` and `SnapCapture\` namespaces. Uses the existing `CurlHttpClient` (or a copy under the TerrWallet namespace for isolation).

**Why follow existing patterns instead of WooCommerce's official PHP client (`automattic/woocommerce`)**:

| Approach | Pros | Cons | Verdict |
|----------|------|------|---------|
| **New namespace + existing CurlHttpClient** | Zero dependencies, matches codebase conventions, DI via HttpClientInterface for testing | Must implement WC auth manually (trivial -- 2 lines) | **Best fit** -- consistent with TrafficPortal, SnapCapture, ShortCode patterns |
| **`automattic/woocommerce` Composer package** | Official client, handles OAuth 1.0a | Adds Composer dependency, different patterns from rest of codebase, overkill for 1 GET endpoint | Overengineered -- we only call GET /wallet/ with Basic Auth |
| **Raw cURL in API handler** | No new files | No testability, no DI, violates existing clean architecture patterns | Regression from established patterns |

**Decision: New `TerrWallet\` namespace** because:
1. The plugin already has 3 API client namespaces (`TrafficPortal`, `SnapCapture`, `ShortCode`) all following the same pattern: Client class + DTOs + Exceptions + Http layer
2. The WC REST API only needs HTTP Basic Auth over HTTPS (consumer_key as username, consumer_secret as password) -- this is a single `Authorization: Basic base64(ck_xxx:cs_xxx)` header
3. We only need ONE endpoint: `GET /wp-json/wc/v3/wallet/?email=xxx`
4. The `HttpClientInterface` + `MockHttpClient` pattern gives us test isolation for free

**Confidence: HIGH** -- directly inspected all 3 existing client namespaces in the codebase. The pattern is consistent and well-established.

---

### 2. WooCommerce REST API Authentication: HTTP Basic Auth

**What:** Authenticate to `https://trafficportal.dev/wp-json/wc/v3/wallet/` using HTTP Basic Auth with WooCommerce consumer key and consumer secret.

**Why HTTP Basic Auth (not OAuth 1.0a, not query string params)**:

| Method | When to Use | Our Case | Verdict |
|--------|-------------|----------|---------|
| **HTTP Basic Auth** | Server-to-server over HTTPS | Yes -- PHP backend to HTTPS endpoint | **Use this** |
| **OAuth 1.0a** | HTTP (non-SSL) connections | Target is HTTPS | Unnecessary complexity |
| **Query string params** | When server doesn't parse Authorization header | We control both ends | Less secure (keys in logs) |

**Decision: HTTP Basic Auth** because:
1. The target endpoint (`trafficportal.dev`) is HTTPS -- Basic Auth is secure over TLS
2. WooCommerce REST API v3 docs state: "Use HTTP Basic Auth by providing the REST API Consumer Key as the username and the REST API Consumer Secret as the password"
3. It is a single header: `Authorization: Basic base64_encode($consumer_key . ':' . $consumer_secret)`
4. The existing `CurlHttpClient` already supports arbitrary headers via the `headers` option

**Implementation in the client:**

```php
// In TerrWalletClient constructor or method
$authHeader = 'Basic ' . base64_encode($consumerKey . ':' . $consumerSecret);

$response = $this->httpClient->request('GET', $url, [
    'headers' => [
        'Authorization' => $authHeader,
        'Content-Type'  => 'application/json',
    ],
    'timeout' => 15,
]);
```

**Credential storage:** Follow the existing `wp-config.php` constant pattern.

```php
// In wp-config.php (already the pattern for API_KEY and SNAPCAPTURE_API_KEY)
define('WC_WALLET_CONSUMER_KEY', 'ck_xxxxxxxxxxxxxxxxxxxx');
define('WC_WALLET_CONSUMER_SECRET', 'cs_xxxxxxxxxxxxxxxxxxxx');
```

```php
// In TP_Link_Shortener or TP_API_Handler -- same pattern as get_api_key()
public static function get_wc_consumer_key(): string {
    return defined('WC_WALLET_CONSUMER_KEY') ? WC_WALLET_CONSUMER_KEY : '';
}

public static function get_wc_consumer_secret(): string {
    return defined('WC_WALLET_CONSUMER_SECRET') ? WC_WALLET_CONSUMER_SECRET : '';
}
```

**Confidence: HIGH** -- WooCommerce REST API authentication documented at [woocommerce.github.io/woocommerce-rest-api-docs](https://woocommerce.github.io/woocommerce-rest-api-docs/). HTTP Basic Auth is the standard method for HTTPS server-to-server calls.

---

### 3. User Email Resolution: WordPress `get_userdata()`

**What:** The TerrWallet API requires an `email` parameter, but the existing usage dashboard identifies users by WordPress user ID (via `TP_Link_Shortener::get_user_id()`). Need to map user ID to email.

**Why `get_userdata()` (not `wp_get_current_user()`, not storing email separately)**:

| Approach | Pros | Cons | Verdict |
|----------|------|------|---------|
| **`get_userdata($uid)->user_email`** | Works for any user ID, standard WP function, cached by WP object cache | Requires valid user ID | **Best fit** -- we already have the user ID from `get_user_id()` |
| **`wp_get_current_user()->user_email`** | Simpler | Only works for the current logged-in user | Would work but `get_userdata()` is more explicit and testable |
| **Store email in plugin settings** | Avoids DB lookup | Redundant -- WordPress already stores it, gets stale if user changes email | Overengineered |

**Decision: Use `get_userdata()`** because:
1. The user ID is already available server-side via `TP_Link_Shortener::get_user_id()`
2. `get_userdata()` is cached by WordPress -- no extra DB query on repeated calls within a request
3. It follows the existing security pattern: user identity is determined server-side, never from client POST data

```php
$uid = TP_Link_Shortener::get_user_id();
$user = get_userdata($uid);
if (!$user) {
    // Handle invalid user
}
$email = $user->user_email;
```

**Confidence: HIGH** -- standard WordPress function, used extensively in WordPress core.

---

### 4. Data Merging: Server-Side PHP Adapter

**What:** Merge TerrWallet transactions with Traffic Portal usage data by date, server-side in PHP, before sending to the browser.

**Why server-side merging (not client-side JavaScript merging)**:

| Approach | Pros | Cons | Verdict |
|----------|------|------|---------|
| **Server-side PHP merge** | Single AJAX call from browser, simpler JS, credentials never exposed to client, can validate/sanitize all data | Slightly more PHP code | **Best fit** -- follows existing proxy pattern |
| **Client-side JS merge** | Less PHP code | Two AJAX calls from browser, WC credentials must be proxied anyway, more complex JS, race conditions | Violates existing single-AJAX-call pattern |
| **Separate AJAX endpoint for wallet** | Separation of concerns | Two round trips, client must handle partial failures, merge timing issues | Overengineered for tooltip data |

**Decision: Server-side merge** because:
1. The existing `ajax_get_usage_summary()` already fetches Traffic Portal data and returns it -- extending it to also fetch wallet data keeps the single-request pattern
2. Both API calls happen in PHP where credentials are safe
3. The merge logic (group wallet credits by date, sum amounts, collect descriptions) is simpler in PHP with associative arrays than in jQuery

**Merge strategy:**

```php
// 1. Fetch usage data (existing)
$usage = $this->client->getUserActivitySummary($uid, $startDate, $endDate);

// 2. Fetch wallet transactions (new)
$walletClient = $this->get_wallet_client();
$transactions = $walletClient->getTransactions($email, $perPage, $page);

// 3. Group wallet credits by date
$walletByDate = [];
foreach ($transactions as $txn) {
    if ($txn->getType() !== 'credit') continue;
    $date = substr($txn->getDate(), 0, 10); // YYYY-MM-DD
    if (!isset($walletByDate[$date])) {
        $walletByDate[$date] = ['amount' => 0.0, 'details' => []];
    }
    $walletByDate[$date]['amount'] += $txn->getAmount();
    $walletByDate[$date]['details'][] = $txn->getDetails();
}

// 4. Merge into usage days
foreach ($days as &$day) {
    $date = $day['date'];
    $day['otherServices'] = $walletByDate[$date]['amount'] ?? 0;
    $day['otherServicesDetails'] = $walletByDate[$date]['details'] ?? [];
}
```

**Confidence: HIGH** -- follows the established pattern in `ajax_get_usage_summary()` where all data shaping happens server-side.

---

## What NOT to Add

| Avoid | Why | What to Use Instead |
|-------|-----|---------------------|
| **`automattic/woocommerce` Composer package** | Adds a Composer dependency for a single GET endpoint. The package is designed for stores building full WC integrations (orders, products, customers). We need exactly one endpoint. The package also uses Guzzle/cURL internally -- we already have CurlHttpClient. | Custom `TerrWalletClient` class with HTTP Basic Auth header (3 lines of auth code) |
| **Guzzle HTTP client** | The codebase uses raw cURL via `CurlHttpClient`. Adding Guzzle introduces a Composer dependency, PSR-7/PSR-18 interfaces, and a different HTTP abstraction than the rest of the plugin. | Existing `CurlHttpClient` implementing `HttpClientInterface` |
| **WordPress `wp_remote_get()`** | The codebase consistently uses its own `CurlHttpClient` + `HttpClientInterface` pattern for all 3 existing API clients. Mixing in `wp_remote_get()` would create an inconsistent pattern and lose the `MockHttpClient` testing capability. | `CurlHttpClient` via `HttpClientInterface` (existing pattern) |
| **OAuth 1.0a implementation** | OAuth 1.0a is only needed for WooCommerce REST API over plain HTTP. The target endpoint (`trafficportal.dev`) uses HTTPS. OAuth 1.0a requires nonce generation, timestamp, signature base string, HMAC-SHA1/SHA256 -- massive complexity for no benefit. | HTTP Basic Auth (consumer_key:consumer_secret as base64) |
| **Separate AJAX endpoint for wallet data** | Would require two AJAX calls from the browser, client-side data merging in jQuery, handling of partial failures (usage succeeds but wallet fails), and race conditions. | Extend existing `ajax_get_usage_summary()` to merge wallet data server-side before returning |
| **New JavaScript file for wallet** | No client-side wallet logic is needed. The merged data arrives in the same AJAX response, just with extra fields (`otherServices`, `otherServicesDetails`). The existing `usage-dashboard.js` renders it. | Add column rendering to existing `usage-dashboard.js` |
| **Redis/Memcached for wallet response caching** | Premature optimization. Wallet transactions are fetched per-request alongside usage data. If caching is needed later, it belongs in the v2.1 caching milestone using the transients pattern already researched. | No caching for v2.2 -- add in future milestone if needed |
| **Database table for wallet transaction storage** | We are a read-through proxy, not a data warehouse. Storing wallet transactions locally creates sync issues, stale data, and migration complexity. | Fetch from API on each request (same pattern as usage data) |
| **npm packages or build tools** | The plugin uses no build step. JS is vanilla jQuery. Adding a build step for tooltip rendering or data formatting would break the existing development workflow. | Vanilla JS/jQuery in existing `usage-dashboard.js` |

---

## New Files to Create

### Following existing namespace patterns exactly:

| File | Purpose | Pattern Source |
|------|---------|---------------|
| `includes/TerrWallet/TerrWalletClient.php` | API client class | `SnapCapture/SnapCaptureClient.php` |
| `includes/TerrWallet/DTO/WalletTransaction.php` | Transaction data object | `TrafficPortal/DTO/MapItem.php` |
| `includes/TerrWallet/DTO/WalletBalance.php` | Balance response object (optional, future use) | `SnapCapture/DTO/ScreenshotResponse.php` |
| `includes/TerrWallet/Exception/TerrWalletException.php` | Base exception | `SnapCapture/Exception/SnapCaptureException.php` |
| `includes/TerrWallet/Exception/ApiException.php` | API error exception | `TrafficPortal/Exception/ApiException.php` |
| `includes/TerrWallet/Exception/AuthenticationException.php` | Auth failure exception | `TrafficPortal/Exception/AuthenticationException.php` |
| `includes/TerrWallet/Exception/NetworkException.php` | Network error exception | `TrafficPortal/Exception/NetworkException.php` |
| `includes/TerrWallet/Http/CurlHttpClient.php` | cURL client (copy or reuse) | `TrafficPortal/Http/CurlHttpClient.php` |
| `includes/TerrWallet/Http/HttpClientInterface.php` | HTTP interface (copy or reuse) | `TrafficPortal/Http/HttpClientInterface.php` |
| `includes/TerrWallet/Http/HttpResponse.php` | HTTP response DTO (copy or reuse) | `TrafficPortal/Http/HttpResponse.php` |
| `includes/TerrWallet/Http/MockHttpClient.php` | Test double | `TrafficPortal/Http/MockHttpClient.php` |

**Note on Http layer duplication:** Each existing namespace (`TrafficPortal`, `SnapCapture`, `ShortCode`) has its own copy of `CurlHttpClient`, `HttpClientInterface`, `HttpResponse`, and `MockHttpClient`. These are nearly identical. While a shared Http namespace would reduce duplication, it would break the established pattern. Follow the existing convention for consistency -- refactoring to shared Http can be a separate cleanup task.

### Files to Modify

| File | Change | Scope |
|------|--------|-------|
| `includes/autoload.php` | Add PSR-4 autoloader for `TerrWallet\\` namespace | ~15 lines (copy existing block) |
| `includes/class-tp-api-handler.php` | Add `$wallet_client` property, `init_wallet_client()`, extend `ajax_get_usage_summary()` to merge wallet data | ~60 lines |
| `includes/class-tp-link-shortener.php` | Add `get_wc_consumer_key()` and `get_wc_consumer_secret()` static methods | ~12 lines |
| `assets/js/usage-dashboard.js` | Add "Other Services" column rendering with tooltip | ~30 lines |
| `templates/usage-dashboard-template.php` | Add `<th>` for Other Services column header | ~3 lines |
| `assets/css/usage-dashboard.css` | Tooltip styles for transaction details (if not using Bootstrap tooltips) | ~10 lines |

---

## Credential Configuration

### wp-config.php Constants (Required)

```php
// WooCommerce REST API credentials for TerrWallet
// Generate at: WordPress Admin > WooCommerce > Settings > Advanced > REST API
define('WC_WALLET_CONSUMER_KEY', 'ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('WC_WALLET_CONSUMER_SECRET', 'cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// Optional: Override wallet API base URL (defaults to trafficportal.dev)
// define('WC_WALLET_API_URL', 'https://trafficportal.dev/wp-json/wc/v3/wallet');
```

**Key format:** Consumer keys are 40-character hex strings prefixed with `ck_` (key) and `cs_` (secret), totaling 43 characters each.

**Permissions:** The WC REST API key needs **Read** permissions only (we only call GET endpoints).

---

## API Contract

### Request

```
GET https://trafficportal.dev/wp-json/wc/v3/wallet/?email={user_email}&per_page={n}&page={p}
Authorization: Basic base64(ck_xxx:cs_xxx)
Content-Type: application/json
```

### Response (200 OK)

```json
[
    {
        "transaction_id": "123",
        "user_id": "45",
        "date": "2026-03-10 14:30:00",
        "type": "credit",
        "amount": "25.00",
        "balance": "125.00",
        "details": "Wallet top-up via PayPal",
        "currency": "USD",
        "blog_id": "1"
    }
]
```

### Fields We Use

| Field | Type (from API) | Use In Dashboard | Notes |
|-------|----------------|------------------|-------|
| `date` | string (datetime) | Group by date (first 10 chars = YYYY-MM-DD) | Must parse and truncate to date |
| `type` | string ("credit"/"debit") | Filter to credits only | Per PROJECT.md: "only credits (top-ups) shown" |
| `amount` | string (decimal) | Sum per date for "Other Services" column | Cast to float |
| `details` | string | Tooltip text listing transaction descriptions | Sanitize for HTML output |
| `transaction_id` | string | Not displayed, but useful for logging/debugging | |
| `currency` | string | Validate matches expected currency | |

### Fields We Ignore

| Field | Why |
|-------|-----|
| `user_id` | We already know the user from WP session |
| `balance` | Not relevant to daily breakdown display |
| `blog_id` | Single-site context |

---

## Version Compatibility

| Component | Version | Notes |
|-----------|---------|-------|
| WooCommerce REST API | v3 (`/wc/v3/`) | Stable since WooCommerce 3.5+ (2018). Not deprecated. |
| TerrWallet (woo-wallet) API | v3 | Extends WC REST API v3 with `/wallet/` endpoint |
| PHP `base64_encode()` | All PHP versions | Used for Basic Auth header |
| WordPress `get_userdata()` | WP 2.0+ | Stable, cached by object cache |
| `CurlHttpClient` pattern | Existing in codebase | No version concern -- already proven |
| `spl_autoload_register()` | PHP 5.1+ | Already used in `autoload.php` |

---

## Installation

```bash
# No npm packages to install.
# No Composer packages to install.
# No build step required.
# No database migrations.
#
# The TerrWallet integration uses:
#   - Existing CurlHttpClient pattern (no new HTTP library)
#   - HTTP Basic Auth (built-in PHP base64_encode)
#   - WordPress get_userdata() (WP core)
#   - PSR-4 autoloader already in autoload.php (just add new namespace block)
#
# Required configuration in wp-config.php:
#   define('WC_WALLET_CONSUMER_KEY', 'ck_xxxx...');
#   define('WC_WALLET_CONSUMER_SECRET', 'cs_xxxx...');
#
# Generate WC API keys at:
#   WordPress Admin > WooCommerce > Settings > Advanced > REST API > Add Key
#   - Description: "Traffic Portal Wallet Integration"
#   - User: (admin user)
#   - Permissions: Read
```

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| WC API credentials missing from wp-config.php | Medium (deployment) | Wallet data silently absent, usage dashboard shows $0.00 for Other Services | Check credentials at client init, log warning if missing, gracefully degrade (show usage data without wallet column) |
| TerrWallet API slow or unavailable | Low | Usage dashboard load time increases or fails | Set 10s timeout for wallet call. If wallet fails, return usage data without wallet merge (graceful degradation). Log error for admin. |
| User email mismatch between WP and TerrWallet | Low | Empty wallet transactions returned | Verify email exists in API response. Log discrepancy. |
| WC API pagination -- more transactions than per_page | Medium | Missing transactions for date range | Request a high per_page (100) or implement pagination loop. Date filtering is not native to the API, so must fetch enough transactions to cover the date range. |
| Amount string parsing (API returns string "25.00" not float) | Certain | Must cast | Use `(float)` cast in DTO, same as existing `hitCost` handling |
| Datetime format includes time ("2026-03-10 14:30:00") | Certain | Must extract date portion for grouping | Use `substr($date, 0, 10)` to get YYYY-MM-DD |

---

## Sources

- [TerrWallet (woo-wallet) API V3 Documentation](https://github.com/malsubrata/woo-wallet/wiki/API-V3) -- Endpoint definitions, parameters, response format (HIGH confidence -- official plugin wiki)
- [WooCommerce REST API Documentation -- Authentication](https://woocommerce.github.io/woocommerce-rest-api-docs/#authentication) -- HTTP Basic Auth, OAuth 1.0a, query string methods (HIGH confidence -- official WooCommerce docs)
- [WooCommerce REST API Developer Docs](https://developer.woocommerce.com/docs/apis/rest-api/) -- REST API overview, key generation (HIGH confidence -- official WooCommerce developer portal)
- `includes/TrafficPortal/TrafficPortalApiClient.php` (this repo) -- `getUserActivitySummary()` at line 777, HttpClientInterface DI pattern, auth header pattern (HIGH confidence -- direct code inspection)
- `includes/SnapCapture/SnapCaptureClient.php` (this repo) -- Clean architecture pattern: Client + DTO + Exception + Http namespaces (HIGH confidence -- direct code inspection)
- `includes/autoload.php` (this repo) -- PSR-4 autoloader registration pattern for `TrafficPortal\`, `SnapCapture\`, `ShortCode\` namespaces (HIGH confidence -- direct code inspection)
- `includes/class-tp-api-handler.php` (this repo) -- `ajax_get_usage_summary()` at line 1573, AJAX proxy pattern, credential initialization pattern (HIGH confidence -- direct code inspection)
- `includes/class-tp-link-shortener.php` (this repo) -- `get_api_key()` at line 99, `get_user_id()` at line 123, wp-config.php constant pattern (HIGH confidence -- direct code inspection)
- `assets/js/usage-dashboard.js` (this repo) -- `loadData()` at line 582, single AJAX call pattern, response rendering (HIGH confidence -- direct code inspection)

---
*Stack research for: TerrWallet API Integration -- v2.2 milestone*
*Researched: 2026-03-10*
