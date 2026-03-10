# Architecture Research: TerrWallet Integration

**Domain:** Integrating WooCommerce wallet (TeraWallet) data into existing usage dashboard
**Researched:** 2026-03-10
**Confidence:** HIGH -- based on direct codebase inspection of all existing files, TeraWallet API V3 docs, and WooCommerce REST API authentication docs

---

## Existing Architecture (Current State)

### Usage Dashboard Data Flow

```
Browser                    WordPress                  External APIs
-------                    ---------                  -------------

usage-dashboard.js         admin-ajax.php
    |                          |
    | [1] loadData()           |
    | POST action=             |
    |  tp_get_usage_summary    |
    | { nonce, start_date,     |
    |   end_date }             |
    |------------------------->|
    |                          |
    |                     TP_API_Handler
    |                     ::ajax_get_usage_summary()
    |                          |
    |                     [2] $uid = TP_Link_Shortener::get_user_id()
    |                         (server-side, DATA-02 security)
    |                          |
    |                     [3] $this->client->getUserActivitySummary($uid, $start, $end)
    |                         GET /user-activity-summary/{uid}?start_date=...&end_date=...
    |                          |------------------------------------>  Traffic Portal API
    |                          |<------------------------------------  { source: [{ date, totalHits, hitCost, balance }] }
    |                          |
    |                     [4] validate_usage_summary_response($raw)
    |                         -> { days: [{ date, totalHits, hitCost, balance }] }
    |                          |
    |                     [5] wp_send_json_success($validated)
    |<-------------------------|
    |                          |
    | [6] state.data = response.data.days
    |     renderSummaryCards()
    |     renderChart()
    |     renderTable()
    v
```

### Key Architectural Facts

1. **Single AJAX endpoint:** JS calls `tp_get_usage_summary`, PHP fetches from external API, validates, returns.
2. **Server-side UID:** UID determined server-side via `TP_Link_Shortener::get_user_id()` (returns WP user ID for logged-in users). Never sent from client.
3. **Data shape:** Each day record has `{ date, totalHits, hitCost, balance }`. The JS sorts/paginates this array client-side.
4. **Validation layer:** `validate_usage_summary_response()` strips unexpected fields, type-casts, sanitizes.
5. **Error handling:** Typed exceptions (NetworkException, ApiException) caught and surfaced differently for admins vs regular users.

---

## TerrWallet API (Data Source to Integrate)

### Endpoint Details (from TeraWallet API V3 docs)

**Get Wallet Transactions:**
- URL: `GET /wp-json/wc/v3/wallet/?email={email}`
- Auth: WooCommerce REST API (consumer_key + consumer_secret via HTTP Basic Auth)
- Params: `email` (required), `per_page` (optional), `page` (optional)
- Response: Array of transaction objects:

```json
[
  {
    "transaction_id": 123,
    "user_id": 45,
    "date": "2026-03-09T14:30:00",
    "type": "credit",
    "amount": "25.00",
    "balance": "150.00",
    "details": "Wallet top-up via PayPal",
    "currency": "USD",
    "blog_id": 1
  }
]
```

**Get Wallet Balance:**
- URL: `GET /wp-json/wc/v3/wallet/balance/?email={email}`
- Response: Balance value

### Key Differences from Traffic Portal API

| Aspect | Traffic Portal API | TerrWallet API |
|--------|-------------------|----------------|
| Location | External (AWS Lambda) | Local (same WordPress site) |
| Auth | x-api-key header | WC REST API consumer_key/secret |
| Identifier | UID (int) | Email (string) |
| Data shape | Daily aggregates (date, totalHits, hitCost, balance) | Individual transactions (date, type, amount, details) |
| Date format | `YYYY-MM-DD` | ISO datetime `YYYY-MM-DDTHH:MM:SS` |

---

## Recommended Architecture: Server-Side Merge

### Decision: Merge in PHP, NOT in JavaScript

**Recommendation:** Add a new AJAX handler that fetches wallet data server-side and returns it pre-merged with usage data. Do NOT make two separate AJAX calls from JS and merge client-side.

**Rationale:**

1. **Auth secrets stay server-side.** WC REST API consumer_key/secret must never be exposed to the browser. A server-side merge keeps these credentials in PHP only.

2. **Single loading state.** One AJAX call = one skeleton/loading/error/content state transition. Two parallel AJAX calls from JS require complex coordination (both must succeed, partial failure handling, two loading spinners or one that waits for both).

3. **Consistent data shape.** The adapter can normalize TerrWallet's transaction-level data into daily aggregates before sending to JS. The JS render functions already expect per-day records -- they need no changes to their core rendering logic.

4. **Follows existing pattern.** The current flow is: JS calls one AJAX action, PHP fetches external data, validates, returns. Adding a second data source fits this pattern -- PHP becomes the aggregation layer.

5. **Same-server optimization.** Since TerrWallet runs on the same WordPress instance, PHP can call the REST endpoint via `wp_remote_get()` to localhost, or potentially even call TeraWallet PHP functions directly. Either way, the latency is negligible compared to a cross-origin AJAX call from the browser.

### Data Flow With TerrWallet Integration

```
Browser                    WordPress                  APIs
-------                    ---------                  ----

usage-dashboard.js         admin-ajax.php
    |                          |
    | [1] loadData()           |
    | POST action=             |
    |  tp_get_usage_summary    |
    | { nonce, start_date,     |  (UNCHANGED from current)
    |   end_date }             |
    |------------------------->|
    |                          |
    |                     TP_API_Handler
    |                     ::ajax_get_usage_summary()
    |                          |
    |                     [2] $uid = TP_Link_Shortener::get_user_id()
    |                          |
    |                     [3] Fetch usage data (EXISTING)
    |                         $this->client->getUserActivitySummary(...)
    |                          |------------------------------------>  Traffic Portal API
    |                          |<------------------------------------  { source: [...days] }
    |                          |
    |                     [4] Fetch wallet data (NEW)
    |                         $this->wallet_client->getTransactions($email, ...)
    |                          |---> GET /wp-json/wc/v3/wallet/?email=...
    |                          |<--- [{ transaction_id, date, type, amount, details }]
    |                          |     (local HTTP call, ~50ms)
    |                          |
    |                     [5] Merge wallet into usage (NEW)
    |                         $merged = $this->merge_wallet_data($usage_days, $wallet_txns)
    |                         -> adds 'otherServices' field to matching date records
    |                          |
    |                     [6] wp_send_json_success($merged)
    |<-------------------------|
    |                          |
    | [7] state.data = response.data.days
    |     renderSummaryCards()     (MODIFIED: show wallet total)
    |     renderChart()            (UNCHANGED)
    |     renderTable()            (MODIFIED: render otherServices column)
    v
```

---

## New Components

### Component 1: TerrWalletClient (NEW PHP Class)

**File:** `includes/TerrWallet/TerrWalletClient.php`

**Responsibility:** Fetch wallet transactions from the WooCommerce REST API. Handles authentication, HTTP transport, error handling. Does NOT merge or adapt data -- that happens in the adapter.

```php
namespace TerrWallet;

class TerrWalletClient {
    private string $siteUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private int $timeout;

    public function __construct(
        string $siteUrl,
        string $consumerKey,
        string $consumerSecret,
        int $timeout = 10
    ) { ... }

    /**
     * Get wallet transactions for a user by email.
     * @return array Raw transaction array from API
     * @throws TerrWalletException on HTTP or parse errors
     */
    public function getTransactions(string $email, int $perPage = 100, int $page = 1): array
    {
        $url = $this->siteUrl . '/wp-json/wc/v3/wallet/?' . http_build_query([
            'email'    => $email,
            'per_page' => $perPage,
            'page'     => $page,
        ]);

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(
                    $this->consumerKey . ':' . $this->consumerSecret
                ),
            ],
            'timeout' => $this->timeout,
            'sslverify' => false,  // Same-server, skip SSL verification
        ]);

        // Handle errors, decode JSON, return array
    }

    /**
     * Get current wallet balance for a user.
     */
    public function getBalance(string $email): float { ... }
}
```

**Why `wp_remote_get()` instead of cURL:** The existing `TrafficPortalApiClient` uses cURL because it talks to an external AWS Lambda. For same-server requests, WordPress's `wp_remote_get()` is preferred because: (a) it respects WordPress's HTTP transport configuration, (b) it handles SSL/proxy settings automatically, (c) it is more testable (can be filtered with `pre_http_request`).

**Auth credential storage:** Consumer key/secret should be stored as WordPress constants in `wp-config.php`, following the existing pattern for `API_KEY`:

```php
define('TERRWALLET_CONSUMER_KEY', 'ck_...');
define('TERRWALLET_CONSUMER_SECRET', 'cs_...');
```

### Component 2: TerrWalletAdapter (NEW PHP Class)

**File:** `includes/TerrWallet/TerrWalletAdapter.php`

**Responsibility:** Transform raw wallet transactions into daily aggregates and merge with existing usage day records. This is pure data transformation -- no I/O.

```php
namespace TerrWallet;

class TerrWalletAdapter {

    /**
     * Aggregate wallet transactions by date.
     * Groups credit transactions by date, sums amounts, collects descriptions.
     *
     * Input: raw transaction array from TerrWalletClient
     * Output: associative array keyed by date (YYYY-MM-DD)
     *   [
     *     '2026-03-09' => [
     *       'amount' => 25.00,
     *       'descriptions' => ['Wallet top-up via PayPal']
     *     ]
     *   ]
     */
    public function aggregateByDate(array $transactions): array { ... }

    /**
     * Merge wallet daily aggregates into usage day records.
     * Adds 'otherServices' field to each day record.
     *
     * Input:
     *   $usageDays: [{ date, totalHits, hitCost, balance }]
     *   $walletByDate: output of aggregateByDate()
     * Output:
     *   [{ date, totalHits, hitCost, balance, otherServices: { amount, descriptions } | null }]
     */
    public function mergeIntoUsageDays(array $usageDays, array $walletByDate): array { ... }
}
```

**Why a separate adapter class:** The merge logic is non-trivial (date matching, filtering credits only, aggregating multiple transactions per day, handling timezone differences). Putting this in a dedicated class makes it independently testable with unit tests. The `TP_API_Handler` stays focused on AJAX orchestration.

### Component 3: TerrWalletException (NEW PHP Class)

**File:** `includes/TerrWallet/Exception/TerrWalletException.php`

Follows the existing exception pattern (see `TrafficPortal/Exception/`, `SnapCapture/Exception/`).

### Component 4: No New JS Files

The existing `usage-dashboard.js` is modified, not replaced. No new JavaScript files are needed.

---

## Modified Components

### Modified 1: TP_API_Handler::ajax_get_usage_summary() (PHP)

**Current:** Fetches usage data, validates, returns.
**After:** Fetches usage data AND wallet data, merges, validates, returns.

```php
public function ajax_get_usage_summary(): void {
    // ... existing nonce check, login check, date validation ...

    try {
        // EXISTING: Fetch usage data from Traffic Portal API
        $raw = $this->client->getUserActivitySummary($uid, $start_date, $end_date);
        $validated = $this->validate_usage_summary_response($raw);

        // NEW: Fetch and merge wallet data
        if ($this->wallet_client) {
            $email = $this->get_user_email();
            $wallet_txns = $this->wallet_client->getTransactions($email);
            $wallet_by_date = $this->wallet_adapter->aggregateByDate($wallet_txns);
            $validated['days'] = $this->wallet_adapter->mergeIntoUsageDays(
                $validated['days'],
                $wallet_by_date
            );
        }

        wp_send_json_success($validated);

    } catch (TerrWalletException $e) {
        // NEW: Wallet errors are non-fatal -- return usage data without wallet
        $this->log_to_file('Wallet error (non-fatal): ' . $e->getMessage());
        wp_send_json_success($validated);  // Usage data still sent
    }
    // ... existing catch blocks for NetworkException, ApiException ...
}
```

**Critical design decision: Wallet errors are non-fatal.** If the TerrWallet API fails but Traffic Portal succeeds, the dashboard still shows usage data -- just without the "Other Services" column. This prevents a wallet outage from breaking the entire dashboard.

### Modified 2: TP_API_Handler::__construct() (PHP)

**Current:** Initializes `$this->client`, `$this->snapcapture_client`, `$this->shortcode_client`.
**After:** Also initializes `$this->wallet_client` and `$this->wallet_adapter`.

```php
public function __construct() {
    $this->init_client();
    $this->init_wallet_client();  // NEW
    $this->register_ajax_handlers();
    add_action('rest_api_init', array($this, 'register_rest_routes'));
}

private function init_wallet_client(): void {
    if (!defined('TERRWALLET_CONSUMER_KEY') || !defined('TERRWALLET_CONSUMER_SECRET')) {
        $this->log_to_file('TerrWallet: Consumer key/secret not configured');
        return;  // Wallet features disabled gracefully
    }

    $this->wallet_client = new \TerrWallet\TerrWalletClient(
        home_url(),
        TERRWALLET_CONSUMER_KEY,
        TERRWALLET_CONSUMER_SECRET
    );
    $this->wallet_adapter = new \TerrWallet\TerrWalletAdapter();
}
```

### Modified 3: validate_usage_summary_response() (PHP)

**Current:** Returns `{ days: [{ date, totalHits, hitCost, balance }] }`.
**After:** Returns `{ days: [{ date, totalHits, hitCost, balance, otherServices }] }`.

The `otherServices` field is `null` for days with no wallet activity, or `{ amount: float, descriptions: string[] }` for days with transactions. The validation function passes through this field after type-checking.

### Modified 4: usage-dashboard.js -- renderRows() (JS)

**Current:** Renders 4 columns: Date, Hits, Cost, Balance.
**After:** Renders 5 columns: Date, Hits, Cost, Other Services, Balance.

```javascript
// In renderRows(), add after the Cost <td>:
var otherHtml = '-';
if (day.otherServices && day.otherServices.amount) {
    var tooltipText = day.otherServices.descriptions.join(', ');
    otherHtml = '<span class="tp-ud-other-services" ' +
        'data-bs-toggle="tooltip" data-bs-placement="top" ' +
        'title="' + tooltipText + '">' +
        formatCurrency(day.otherServices.amount) +
    '</span>';
}
'<td class="tp-ud-col-other" data-label="Other Services">' + otherHtml + '</td>' +
```

### Modified 5: usage-dashboard.js -- renderSummaryCards() (JS)

**Current:** Shows 3 cards: Total Hits, Total Cost, Balance.
**After:** Shows 4 cards: Total Hits, Total Cost, Other Services, Balance.

The "Other Services" card aggregates `otherServices.amount` across all days.

### Modified 6: usage-dashboard-template.php (HTML)

**Current:** Table header has 4 `<th>` columns.
**After:** Table header has 5 `<th>` columns with "Other Services" between Cost and Balance.

### Modified 7: usage-dashboard.css (CSS)

Column widths adjusted for the 5th column. Tooltip styles for "Other Services" amounts.

### Modified 8: includes/autoload.php

Add autoloading for `TerrWallet\*` namespace, following the existing pattern for `TrafficPortal\*` and `SnapCapture\*`.

---

## Component Boundaries

| Component | Responsibility | Communicates With |
|-----------|---------------|-------------------|
| `TerrWalletClient` | HTTP transport to WC REST API. Auth, request, parse, throw on error. | WooCommerce REST API (local) |
| `TerrWalletAdapter` | Pure data transformation. Aggregate transactions by date, merge into usage records. | Nothing (receives/returns arrays) |
| `TerrWalletException` | Typed exception for wallet API errors. | Thrown by Client, caught by Handler |
| `TP_API_Handler` (modified) | Orchestrates: fetch usage, fetch wallet, merge, validate, return. Catches wallet errors non-fatally. | Client, Adapter, existing TrafficPortalApiClient |
| `usage-dashboard.js` (modified) | Renders the `otherServices` field in table and summary. Tooltip for descriptions. | Receives merged data via AJAX |
| `usage-dashboard-template.php` (modified) | Adds 5th column header. | Static HTML |

---

## New vs. Modified Files Summary

### New Files

| File | Purpose | Lines (est.) |
|------|---------|-------------|
| `includes/TerrWallet/TerrWalletClient.php` | HTTP client for WC REST API wallet endpoint | ~120 |
| `includes/TerrWallet/TerrWalletAdapter.php` | Data transformation: aggregate + merge | ~80 |
| `includes/TerrWallet/Exception/TerrWalletException.php` | Exception class | ~15 |

### Modified Files

| File | Changes | Scope |
|------|---------|-------|
| `includes/class-tp-api-handler.php` | Add `init_wallet_client()`, modify `ajax_get_usage_summary()` to fetch/merge wallet data, add wallet error handling | ~40 lines added |
| `includes/autoload.php` | Add TerrWallet namespace mapping | ~3 lines |
| `assets/js/usage-dashboard.js` | Add "Other Services" column rendering in `renderRows()`, add wallet sum in `renderSummaryCards()`, Bootstrap tooltip init | ~30 lines added |
| `templates/usage-dashboard-template.php` | Add 5th `<th>` column, update skeleton | ~5 lines |
| `assets/css/usage-dashboard.css` | Column width adjustment, tooltip styling | ~15 lines |

### Files NOT Modified

| File | Why Not |
|------|---------|
| `TrafficPortalApiClient.php` | Wallet is a separate data source; TP client unchanged |
| `class-tp-usage-dashboard-shortcode.php` | No new assets to enqueue (Bootstrap tooltips already loaded) |
| `class-tp-link-shortener.php` | No new top-level components; wallet client is internal to API handler |

---

## Patterns to Follow

### Pattern 1: Non-Fatal Secondary Data Source

The wallet data is secondary to usage data. If the wallet API fails, the dashboard must still work with usage data alone. This means:

- Catch `TerrWalletException` separately from `NetworkException`/`ApiException`
- On wallet failure, log the error but still return `wp_send_json_success($validated)` with usage-only data
- The JS must handle `otherServices` being absent from all records (render "-" or hide column)

```php
// PHP: wallet errors are non-fatal
try {
    $wallet_txns = $this->wallet_client->getTransactions($email);
    $wallet_by_date = $this->wallet_adapter->aggregateByDate($wallet_txns);
    $validated['days'] = $this->wallet_adapter->mergeIntoUsageDays($validated['days'], $wallet_by_date);
} catch (TerrWalletException $e) {
    $this->log_to_file('Wallet error (non-fatal): ' . $e->getMessage());
    // $validated['days'] unchanged -- usage-only data
}
```

### Pattern 2: Adapter Separation (Transform vs. Transport)

The `TerrWalletClient` handles I/O (HTTP requests). The `TerrWalletAdapter` handles data transformation (aggregation, merging). These are separate classes because:

- Client is hard to unit test (needs HTTP mocking). Adapter is trivially testable with plain arrays.
- Client changes if the API changes. Adapter changes if the data model changes. Different reasons to change.
- This matches the existing SnapCapture pattern where `SnapCaptureClient` handles HTTP and DTOs handle data shaping.

### Pattern 3: Credential Storage via WordPress Constants

Follow the existing pattern (`API_KEY`, `SNAPCAPTURE_API_KEY`) -- store WC REST API credentials in `wp-config.php`:

```php
define('TERRWALLET_CONSUMER_KEY', 'ck_...');
define('TERRWALLET_CONSUMER_SECRET', 'cs_...');
```

The `init_wallet_client()` method gracefully degrades if these are not defined (wallet features silently disabled).

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: Two Separate AJAX Calls from JavaScript

**What:** Adding a second AJAX endpoint `tp_get_wallet_data` and calling it from JS alongside `tp_get_usage_summary`, then merging in JS.

**Why bad:** (a) Exposes WC auth complexity to the client layer, (b) requires coordinating two async calls (Promise.all / jQuery.when), (c) two loading states to manage, (d) two error states to handle, (e) the merge logic in JS duplicates what PHP can do more cleanly.

**Instead:** Single AJAX call, server-side merge.

### Anti-Pattern 2: Calling TeraWallet PHP Functions Directly

**What:** Bypassing the REST API and calling TeraWallet's internal PHP functions (e.g., `woo_wallet()->wallet->get_transactions()`).

**Why bad:** (a) Couples this plugin to TeraWallet's internal API which can change without notice, (b) the REST API is the documented, stable interface, (c) direct function calls skip TeraWallet's own validation and access control.

**Instead:** Use the documented REST API via `wp_remote_get()`.

### Anti-Pattern 3: Storing Consumer Key/Secret in Database

**What:** Using `get_option('terrwallet_consumer_key')` or a settings page.

**Why bad:** (a) API secrets in the database are exposed to any admin user, database backups, and SQL injection, (b) the existing codebase stores all API keys as `wp-config.php` constants.

**Instead:** `define()` constants in `wp-config.php`. Consistent with existing `API_KEY` and `SNAPCAPTURE_API_KEY` patterns.

### Anti-Pattern 4: Filtering Transactions Client-Side

**What:** Sending all wallet transactions to JS and filtering by date range in JavaScript.

**Why bad:** (a) Wallet may have hundreds of transactions, (b) unnecessary data transfer, (c) the PHP adapter can filter by date range before merge.

**Instead:** The adapter's `aggregateByDate()` accepts a date range parameter and discards transactions outside the range.

---

## Build Order (Dependency-Driven)

### Phase 1: TerrWalletClient (PHP -- no UI changes)

Build the HTTP client in isolation. Can be tested with integration tests against the real local API.

**Step 1.1:** Create `includes/TerrWallet/TerrWalletClient.php` with `getTransactions()` and `getBalance()`
**Step 1.2:** Create `includes/TerrWallet/Exception/TerrWalletException.php`
**Step 1.3:** Add namespace to `includes/autoload.php`
**Step 1.4:** Add `TERRWALLET_CONSUMER_KEY` / `TERRWALLET_CONSUMER_SECRET` constants to wp-config
**Step 1.5:** Integration test: `TerrWalletClient->getTransactions('user@email.com')` returns valid data

**Dependencies:** None. Can be built without touching existing code.

### Phase 2: TerrWalletAdapter (PHP -- no UI changes)

Build the data transformation layer. Purely unit-testable with mock data.

**Step 2.1:** Create `includes/TerrWallet/TerrWalletAdapter.php` with `aggregateByDate()` and `mergeIntoUsageDays()`
**Step 2.2:** Unit tests with fixture data: verify date matching, credit-only filtering, multi-transaction-per-day aggregation, empty wallet graceful handling

**Dependencies:** None. Can be built in parallel with Phase 1.

### Phase 3: Wire Into AJAX Handler (PHP -- backend integration)

Connect the client and adapter into the existing AJAX flow.

**Step 3.1:** Add `$wallet_client` and `$wallet_adapter` properties to `TP_API_Handler`
**Step 3.2:** Add `init_wallet_client()` method, call from constructor
**Step 3.3:** Modify `ajax_get_usage_summary()` to fetch wallet data and merge
**Step 3.4:** Add non-fatal error handling for wallet failures
**Step 3.5:** Integration test: AJAX call returns merged data with `otherServices` field

**Dependencies:** Phase 1 and Phase 2 complete.

### Phase 4: Dashboard UI (JS/HTML/CSS -- frontend)

Add the "Other Services" column to the dashboard display.

**Step 4.1:** Add 5th `<th>` column to `usage-dashboard-template.php`
**Step 4.2:** Update `renderRows()` in `usage-dashboard.js` to render `otherServices` with tooltip
**Step 4.3:** Update `renderSummaryCards()` to include wallet total
**Step 4.4:** Update `usage-dashboard.css` for column widths and tooltip styling
**Step 4.5:** Initialize Bootstrap tooltips on render
**Step 4.6:** Update skeleton loading template for 5 columns

**Dependencies:** Phase 3 complete (needs merged data from backend).

### Phase 5: E2E Tests

**Step 5.1:** Test with real wallet data on trafficportal.dev
**Step 5.2:** Test wallet API unavailable scenario (dashboard still works)
**Step 5.3:** Test date range filtering includes correct wallet transactions
**Step 5.4:** Test tooltip displays correct descriptions

**Dependencies:** Phase 4 complete.

**Phase ordering rationale:**
- Phases 1 and 2 have zero dependencies and can be built in parallel. They produce independently testable components.
- Phase 3 depends on both 1 and 2 but requires no UI changes -- backend can be tested via direct AJAX calls.
- Phase 4 is UI-only and depends on Phase 3's data shape being finalized.
- This ordering means any phase can be shipped independently without breaking the existing dashboard.

---

## Data Shape: Before and After

### Current Response (from ajax_get_usage_summary)

```json
{
  "success": true,
  "data": {
    "days": [
      {
        "date": "2026-03-09",
        "totalHits": 142,
        "hitCost": 1.42,
        "balance": 48.58
      }
    ]
  }
}
```

### After Integration

```json
{
  "success": true,
  "data": {
    "days": [
      {
        "date": "2026-03-09",
        "totalHits": 142,
        "hitCost": 1.42,
        "balance": 48.58,
        "otherServices": {
          "amount": 25.00,
          "descriptions": ["Wallet top-up via PayPal"]
        }
      },
      {
        "date": "2026-03-08",
        "totalHits": 98,
        "hitCost": 0.98,
        "balance": 50.00,
        "otherServices": null
      }
    ]
  }
}
```

The `otherServices` field is `null` when no wallet transactions exist for that date. JS checks `day.otherServices && day.otherServices.amount` before rendering.

---

## User Identity Mapping

The Traffic Portal API uses `uid` (integer, from `TP_Link_Shortener::get_user_id()`).
The TerrWallet API uses `email` (string, the WP user's email).

Both are resolved server-side:

```php
$uid = TP_Link_Shortener::get_user_id();           // For Traffic Portal
$user = wp_get_current_user();
$email = $user->user_email;                          // For TerrWallet
```

No new identity mapping infrastructure is needed. Both identifiers come from the same WordPress user session.

---

## Scalability Considerations

| Concern | At 10 txns/month | At 100 txns/month | At 1000 txns/month |
|---------|-----------------|-------------------|---------------------|
| API payload size | ~1 KB | ~10 KB | ~100 KB |
| PHP merge time | <1ms | <5ms | <20ms |
| Total AJAX latency | +50ms (local HTTP) | +80ms | +150ms |
| JS rendering impact | None | None | Minimal |

The TerrWallet REST API call is local (same server), so network latency is minimal. The adapter's `aggregateByDate()` is O(n) where n = number of transactions. For 1000+ transactions per month, consider adding `per_page` pagination and date filtering in the API query params to reduce payload size.

---

## Sources

- Direct inspection: `includes/class-tp-api-handler.php` -- `ajax_get_usage_summary()`, `validate_usage_summary_response()`, constructor, AJAX registration (HIGH confidence)
- Direct inspection: `includes/TrafficPortal/TrafficPortalApiClient.php` -- `getUserActivitySummary()` method (HIGH confidence)
- Direct inspection: `assets/js/usage-dashboard.js` -- `loadData()`, `renderRows()`, `renderSummaryCards()`, state management (HIGH confidence)
- Direct inspection: `templates/usage-dashboard-template.php` -- table structure, column headers (HIGH confidence)
- Direct inspection: `includes/class-tp-usage-dashboard-shortcode.php` -- asset enqueuing, localize script (HIGH confidence)
- Direct inspection: `includes/class-tp-link-shortener.php` -- `get_user_id()`, `get_api_endpoint()` patterns (HIGH confidence)
- TeraWallet API V3 docs: https://github.com/malsubrata/woo-wallet/wiki/API-V3 (HIGH confidence)
- WooCommerce REST API authentication: https://woocommerce.github.io/woocommerce-rest-api-docs/#authentication (HIGH confidence)
- WooCommerce REST API docs: https://developer.woocommerce.com/docs/apis/rest-api/ (HIGH confidence)

---

*Architecture research for: TerrWallet Integration (v2.2)*
*Researched: 2026-03-10*
