# Project Research Summary

**Project:** TerrWallet Integration (v2.2)
**Domain:** WordPress plugin — WooCommerce Wallet API client for usage dashboard
**Researched:** 2026-03-10
**Confidence:** HIGH

## Executive Summary

The v2.2 TerrWallet Integration adds an "Other Services" column to the existing `[tp_usage_dashboard]` shortcode table, showing wallet credit transaction totals per day alongside the existing Date, Hits, Cost, and Balance columns. The critical architectural insight from research is that the TerrWallet API lives on the same WordPress instance as the plugin, which changes the integration approach entirely: HTTP loopback requests to the local WC REST API are unreliable and should be avoided in favor of either WordPress's internal `rest_do_request()` dispatch or direct TeraWallet PHP function calls. The recommended approach is direct PHP functions (`woo_wallet()->wallet->get_transactions()`) to bypass REST API authentication complexity, eliminate loopback risk, and stay consistent with the existing single-AJAX-call pattern.

The recommended stack adds zero new libraries. One new PHP namespace (`TerrWallet\`) follows the exact pattern of the three existing API namespaces (`TrafficPortal`, `SnapCapture`, `ShortCode`). A client class fetches transactions, a separate adapter aggregates them by date and merges them into the existing usage day records server-side, and the modified JS renders the additional column. The entire data flow remains a single browser AJAX call returning a unified response — the existing `ajax_get_usage_summary` handler is extended rather than duplicated.

The top risk is the loopback HTTP request trap: using `wp_remote_get()` to call the local WC REST API from PHP running on the same server causes cURL error 28 (timeout) behind Cloudflare and reverse proxies, and deadlocks under limited-worker PHP-FPM setups. The second risk is the merge adapter edge cases — date format normalization, timezone handling, multi-transaction-per-day aggregation, and full-outer-join behavior for wallet-only dates all need explicit handling before the column data is trustworthy.

---

## Key Findings

### Recommended Stack

The integration requires no new dependencies. The existing `CurlHttpClient` / `HttpClientInterface` pattern used by `TrafficPortal`, `SnapCapture`, and `ShortCode` namespaces is replicated under a new `TerrWallet\` namespace. WooCommerce consumer key and secret are stored as `wp-config.php` constants following the existing `API_KEY` / `SNAPCAPTURE_API_KEY` pattern. User email is resolved server-side via `get_userdata(get_current_user_id())` and never accepted from the browser.

The key decision against using the `automattic/woocommerce` Composer package (or Guzzle, or `wp_remote_get()` for local calls) is that the codebase deliberately avoids Composer dependencies and the WC REST API requires only a single GET endpoint with HTTP Basic Auth — a 2-line auth header, not a full OAuth implementation.

**Core technologies:**
- `TerrWallet\TerrWalletClient` (new PHP class): HTTP transport or direct PHP access for wallet data — follows existing namespace pattern, zero new dependencies
- `TerrWallet\TerrWalletAdapter` (new PHP class): Pure data transformation (aggregate + merge) — independently unit-testable, no I/O
- `TerrWallet\Exception\TerrWalletException` (new PHP class): Typed exception — caught non-fatally in the handler to preserve usage data on wallet failure
- `WordPress get_userdata()`: User ID to email resolution — standard WP function, object-cached, consistent with existing server-side identity pattern
- WordPress `wp-config.php` constants: Credential storage — consistent with `API_KEY`, `SNAPCAPTURE_API_KEY` pattern already in codebase

**What NOT to add:** `automattic/woocommerce` Composer package (overkill for one GET endpoint), Guzzle (codebase uses raw cURL), `wp_remote_get()` to own server (loopback failure risk), OAuth 1.0a (HTTPS makes Basic Auth sufficient), separate AJAX endpoint (adds two round trips and client-side merge complexity), any new JS file (all changes are additive to existing `usage-dashboard.js`).

**See:** `.planning/research/STACK.md` for full rationale and new/modified file manifest.

### Expected Features

The feature set is tightly scoped: one new table column with a Bootstrap tooltip, one new summary stat card, and the PHP backend to power them. Total estimated effort is 14–18 hours for the full MVP.

**Must have (table stakes):**
- PHP client for TeraWallet wallet API — server-side only, WC credentials never reach the browser
- Date-keyed merge adapter — aggregates credit transactions by date, merges into existing `days[]` array with `otherServices` field
- AJAX handler extension — `ajax_get_usage_summary` extended to fetch and merge wallet data before returning; wallet errors non-fatal
- "Other Services" table column — 5th column between Cost and Balance; `+$X.XX` format with Bootstrap tooltip showing `details` text
- Filter to credit transactions only — debit transactions are not "Other Services" and would double-count costs
- Summary card for Other Services total — 4th stat card added to `renderSummaryCards()`
- Mobile card layout — `data-label="Other Services"` attribute, Bootstrap tooltip verified on tap
- Graceful degradation — wallet fetch failure shows usage data normally with empty Other Services cells; TeraWallet not installed shows the same

**Should have (differentiators, defer if time-constrained):**
- Color-coded credit badge (green `+$X.XX` pill) — visual distinction from Cost column
- Sort by Other Services column — additive JS change, no data model changes needed
- Server-side transient caching — 5-minute TTL keyed by user and date range; reduces repeated multi-page fetches for users with many transactions

**Defer to v2.3+:**
- Wallet transactions overlaid in Chart.js — HIGH complexity, mixed chart type, risk of visual clutter for sparse transaction data
- Expandable row detail panel — tooltip is sufficient for MVP; row expansion is a distinct UI pattern
- Separate wallet transaction history page — out of scope for this dashboard column integration

**See:** `.planning/research/FEATURES.md` for complexity estimates and the full feature dependency tree.

### Architecture Approach

The architecture extends the existing proxy pattern: one browser AJAX call, PHP fetches from both external APIs, merges server-side, returns a single unified response. The two new PHP classes have clean separation of concerns — `TerrWalletClient` handles I/O, `TerrWalletAdapter` handles data transformation — mirroring the existing split between `TrafficPortalApiClient` and the validation/shaping logic in `validate_usage_summary_response()`.

**Major components:**
1. `TerrWalletClient` (new) — transport layer; auth, request, parse, throw on error; no data shaping
2. `TerrWalletAdapter` (new) — `aggregateByDate()` + `mergeIntoUsageDays()`; pure arrays in/out; independently unit-testable
3. `TerrWalletException` (new) — typed exception; caught non-fatally in the handler, preserving usage data on wallet failure
4. `TP_API_Handler::ajax_get_usage_summary()` (modified) — orchestrator; adds wallet fetch and merge after existing usage fetch; separate try/catch for wallet errors
5. `usage-dashboard.js` (modified) — `renderRows()` adds 5th column; `renderSummaryCards()` adds 4th card; Bootstrap tooltip initialized on render
6. `usage-dashboard-template.php` + `usage-dashboard.css` (modified) — 5th `<th>` column header, column width rebalancing

**Data shape change:** Each day record gains `otherServices: { amount: float, descriptions: string[] } | null`. JS checks `day.otherServices && day.otherServices.amount` before rendering — null means no wallet activity that day.

**See:** `.planning/research/ARCHITECTURE.md` for full before/after data shapes, component boundaries, and the five-phase build order.

### Critical Pitfalls

1. **Loopback HTTP request to own server** — Using `wp_remote_get()` to call `trafficportal.dev/wp-json/wc/v3/wallet/` from PHP running on `trafficportal.dev` causes cURL error 28 (timeout) behind Cloudflare and reverse proxies, and deadlocks under limited-worker PHP-FPM. Use `rest_do_request()` or direct TeraWallet PHP functions. This decision must be made in Phase 1 — getting it wrong requires a full client rewrite.

2. **WC REST API auth failure for non-admin users** — If using `rest_do_request()`, the WC endpoint returns 401 for regular customers who lack `manage_woocommerce` capability. Works fine when tested as admin, silently fails for actual users. Prevention: bypass the REST API entirely with direct PHP calls (`woo_wallet()->wallet->get_transactions()`).

3. **Date format mismatch between APIs** — Usage API returns `YYYY-MM-DD`; TeraWallet returns `YYYY-MM-DD HH:MM:SS`. Naive string comparison makes every wallet transaction appear as an unmatched row. Always normalize: `substr($tx['date'], 0, 10)` before using as a merge key.

4. **Merge edge cases in the adapter** — Three requirements that must all be explicitly tested: (a) multiple transactions on the same day must be summed, not mapped 1:1; (b) wallet-only dates with no usage activity require a full outer join, not a left join from usage data; (c) TeraWallet stores datetimes in site timezone while the usage API operates in UTC — transactions near midnight can land on the wrong day without UTC conversion.

5. **Wallet error killing the entire dashboard** — Placing the wallet fetch inside the existing try/catch block means a wallet failure returns an error response even though usage data is available. The wallet fetch must be in its own non-fatal try/catch that logs the error and continues with usage-only data.

**See:** `.planning/research/PITFALLS.md` for the full 14-pitfall catalogue with code-level prevention patterns.

---

## Implications for Roadmap

Research identified a clear dependency ordering. Phases 1 and 2 are independent of each other and produce zero UI changes, allowing parallel development and isolated testing. Each phase can be shipped without breaking the existing dashboard.

### Phase 1: TerrWalletClient — HTTP Transport

**Rationale:** The client is the foundational dependency for everything else. The loopback pitfall (Pitfall 1) and auth pitfall (Pitfall 3) must be resolved before any other work begins — these decisions determine the entire client architecture. Building in isolation (no UI changes) allows integration testing against the real local API before touching the AJAX handler.

**Delivers:** A working, tested PHP class that retrieves wallet transactions for a given user, handles auth, pagination, and errors; a typed exception class; autoloader registration for the new namespace; `wp-config.php` constants documented for deployment.

**Addresses:** PHP TerrWallet client (table stakes), TeraWallet plugin detection, credential storage pattern.

**Avoids:** Pitfall 1 (loopback HTTP), Pitfall 3 (non-admin auth failure), Pitfall 4 (user ID/email mismatch), Pitfall 10 (credentials in database), Pitfall 11 (pagination not handled), Pitfall 13 (plugin not installed).

**Research flag:** NEEDS VALIDATION — the loopback vs. `rest_do_request()` vs. direct PHP call decision requires verifying which TeraWallet PHP functions are accessible in this specific installation. Confirm `function_exists('woo_wallet')` at the start of Phase 1 before committing to an approach.

### Phase 2: TerrWalletAdapter — Data Transformation

**Rationale:** Independently buildable and testable in parallel with Phase 1 — pure PHP array manipulation with no I/O. The merge edge cases are the highest logical complexity in the feature and deserve isolation. Testing the adapter with fixture data before wiring it to live API calls prevents corrupted table data from masking logic bugs.

**Delivers:** A working, unit-tested adapter with `aggregateByDate()` (credit-only filter, date normalization, multi-transaction summing) and `mergeIntoUsageDays()` (full outer join, timezone-aware date keys, null for days with no wallet activity).

**Addresses:** Date-keyed merge adapter (table stakes), full outer join behavior, timezone handling.

**Avoids:** Pitfall 2 (date format mismatch), Pitfall 5 (multi-transaction aggregation), Pitfall 6 (wallet-only dates dropped), Pitfall 7 (timezone discrepancy).

**Research flag:** STANDARD PATTERNS — pure PHP array transformation, no external dependencies, straightforward unit tests with fixture data.

### Phase 3: Backend Integration — Wire Into AJAX Handler

**Rationale:** Depends on both Phase 1 and Phase 2. Connects the client and adapter into the existing AJAX flow via a surgical modification to `ajax_get_usage_summary()`. The non-fatal error handling design is implemented here. The modified response shape (`otherServices` field) is validated before any UI is built.

**Delivers:** The AJAX endpoint returns merged data with `otherServices` field per day; wallet errors are caught non-fatally and usage data is returned without the wallet column; integration tests confirm end-to-end data shape.

**Addresses:** AJAX handler extension (table stakes), error handling and graceful degradation (table stakes).

**Avoids:** Pitfall 9 (wallet error kills entire dashboard).

**Research flag:** STANDARD PATTERNS — surgical extension of existing AJAX handler following established WordPress AJAX proxy pattern.

### Phase 4: Dashboard UI — Column, Tooltip, and Summary Card

**Rationale:** Depends on Phase 3's finalized response shape. All changes are additive to existing JS/HTML/CSS — no rearchitecting of the rendering pipeline. Column rendering, tooltip initialization, summary card, and mobile layout can all be built and tested together since they share the same data source.

**Delivers:** 5-column table with "Other Services" showing `+$X.XX` with Bootstrap tooltip listing transaction descriptions; 4th summary card showing period total; mobile card layout updated; column widths rebalanced; graceful "-" rendering when `otherServices` is null.

**Addresses:** "Other Services" column (table stakes), tooltip (table stakes), summary card (table stakes), mobile layout (table stakes), column width rebalancing (table stakes), XSS prevention in tooltip text.

**Avoids:** Pitfall 12 (amount precision — existing `formatCurrency()` handles this if PHP delivers clean floats), Pitfall 14 (XSS via tooltip — use `.text()` not `.html()` for user-provided description strings).

**Research flag:** STANDARD PATTERNS — follows existing `renderRows()` and `renderSummaryCards()` patterns directly; Bootstrap 5 tooltip already loaded and initialized in the existing codebase.

### Phase 5: E2E Tests and Edge Case Validation

**Rationale:** The integration has edge cases that require real or realistic data: wallet API unavailable, date range spanning a month boundary, user with 100+ transactions requiring pagination, user with wallet-only days, transactions near midnight in a non-UTC timezone.

**Delivers:** E2E test suite covering the nominal path, wallet unavailable degradation, date filtering, pagination edge cases, and tooltip rendering.

**Addresses:** Integration confidence, production deployment readiness.

**Research flag:** STANDARD PATTERNS — follows existing Python pytest E2E test structure in `tests/e2e/`.

### Phase Ordering Rationale

- Phases 1 and 2 have zero dependencies on each other and zero UI changes — they can be built and tested in parallel without modifying any existing code.
- Phase 3 is the first modification to existing code; keeping it after both foundational components are tested limits the blast radius of any integration issues.
- Phase 4 is UI-only; blocking it on Phase 3 ensures the response shape is final before building the renderer.
- This ordering means deploying any phase individually leaves the existing dashboard fully functional with no partial-state breakage.

### Research Flags

Phases needing deeper investigation during planning:
- **Phase 1:** The loopback vs. `rest_do_request()` vs. direct PHP function approach must be validated against the actual TeraWallet installation. Research documents both paths but cannot confirm function availability without a live environment check. This is a binary gate: if `woo_wallet()` is available, use it; otherwise fall back to `rest_do_request()` with the permission filter documented in PITFALLS.md Pitfall 3.

Phases with standard, well-documented patterns (skip additional research):
- **Phase 2:** Pure PHP array manipulation, fully unit-testable offline, no external dependencies.
- **Phase 3:** Surgical extension of existing AJAX handler following the established WordPress AJAX proxy pattern.
- **Phase 4:** Additive JS/HTML/CSS following existing `renderRows()` and `renderSummaryCards()` patterns.
- **Phase 5:** Follows existing pytest E2E test structure in `tests/e2e/`.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All 3 existing API namespaces directly inspected; WC REST API auth documented in official WooCommerce docs; zero new libraries required means no version conflict risk |
| Features | HIGH | TeraWallet API V3 docs confirmed; `ajax_get_usage_summary` and `renderRows` directly inspected; feature set is narrow and well-bounded |
| Architecture | HIGH | All data flow components inspected in codebase; response shapes before/after documented from live code; component boundaries follow existing patterns exactly |
| Pitfalls | HIGH (architecture/integration) / MEDIUM (TeraWallet-specific behaviors) | Loopback/auth/merge pitfalls verified against official WC docs and codebase patterns; TeraWallet-specific API behaviors based on GitHub wiki and community reports |

**Overall confidence:** HIGH

### Gaps to Address

- **Direct PHP access vs. REST API dispatch:** Research recommends direct TeraWallet PHP functions but cannot confirm which functions are exposed in the specific version installed on `trafficportal.dev`. Verify `function_exists('woo_wallet')` and available methods at the start of Phase 1. If direct PHP is unavailable, fall back to `rest_do_request()` with the auth workaround documented in PITFALLS.md Pitfall 3.

- **Full outer join product decision:** PITFALLS.md Pitfall 6 flags that showing wallet-only days (no usage activity) in the table requires a product decision — should a day with a $25 top-up but zero link hits appear as a row? Research documents the full-outer-join implementation but the business rule must be confirmed before Phase 2 implementation begins.

- **Pagination depth:** The WC REST API `per_page` default is typically 10. The Phase 1 client must implement pagination or set a high `per_page` limit. The correct maximum must be confirmed against the installed TeraWallet version's actual API behavior.

- **Timezone handling decision:** The merge adapter can normalize dates to UTC or to the WordPress site timezone before matching. Both are technically correct but produce different user-visible behavior for transactions near midnight. The product decision (UTC or site-local) must be documented before Phase 2 implementation.

---

## Sources

### Primary (HIGH confidence)
- `includes/class-tp-api-handler.php` (this repo) — `ajax_get_usage_summary()` line 1573, `validate_usage_summary_response()` line 1647, AJAX proxy pattern
- `includes/TrafficPortal/TrafficPortalApiClient.php` (this repo) — `getUserActivitySummary()`, `CurlHttpClient` / `HttpClientInterface` pattern
- `includes/SnapCapture/SnapCaptureClient.php` (this repo) — namespace structure: Client + DTO + Exception + Http layers
- `assets/js/usage-dashboard.js` (this repo) — `loadData()`, `renderRows()`, `renderSummaryCards()`, `formatCurrency()`
- `templates/usage-dashboard-template.php` (this repo) — table structure, column headers
- `includes/autoload.php` (this repo) — PSR-4 namespace registration pattern
- `includes/class-tp-link-shortener.php` (this repo) — `get_api_key()`, `get_user_id()`, wp-config constant pattern
- [WooCommerce REST API Authentication](https://woocommerce.github.io/woocommerce-rest-api-docs/#authentication) — HTTP Basic Auth, consumer key/secret
- [WooCommerce REST API Developer Docs](https://developer.woocommerce.com/docs/apis/rest-api/) — REST API overview, key generation
- [WordPress Transients API](https://developer.wordpress.org/apis/transients/) — caching pattern
- [wp_timezone() reference](https://developer.wordpress.org/reference/functions/wp_timezone/) — site timezone resolution

### Secondary (MEDIUM confidence)
- [TeraWallet API V3 Documentation](https://github.com/malsubrata/woo-wallet/wiki/API-V3) — endpoint definitions, parameters, response format
- [TeraWallet WordPress Plugin](https://wordpress.org/plugins/woo-wallet/) — plugin overview
- [TeraWallet GitHub Repository](https://github.com/malsubrata/woo-wallet) — source code reference
- [WordPress loopback request issues](https://github.com/docker-library/wordpress/issues/493) — cURL error 28 on self-requests
- [WordPress REST API loopback failures behind Cloudflare](https://lukapaunovic.com/2025/04/24/fix-wordpress-loopback-and-rest-api-403-errors-behind-cloudflare/) — 403 errors on server-to-server requests
- [WooCommerce REST API auth issue #26847](https://github.com/woocommerce/woocommerce/issues/26847) — `wp_get_current_user()` conflicts with WC auth

---
*Research completed: 2026-03-10*
*Ready for roadmap: yes*
