# Feature Landscape: TerrWallet Integration

**Domain:** Wallet transaction integration into existing usage dashboard
**Researched:** 2026-03-10
**Overall Confidence:** HIGH (TeraWallet API is documented, existing dashboard patterns are well-understood from codebase analysis)

---

## Context

This research covers the **v2.2 TerrWallet Integration milestone** -- adding wallet transaction data from the TeraWallet (WooCommerce Wallet) plugin into the existing `[tp_usage_dashboard]` table as an "Other Services" column.

**Current state (no wallet data):**
- The usage dashboard table has 4 columns: Date, Hits, Cost, Balance
- Data comes from Traffic Portal API via `ajax_get_usage_summary` AJAX handler
- Each row is a daily record: `{ date, totalHits, hitCost, balance }`
- The PHP handler validates/reshapes data in `validate_usage_summary_response()`
- JS renders rows via `renderRows()` and summary cards via `renderSummaryCards()`

**TeraWallet API (confirmed from official GitHub wiki):**
- `GET /wp-json/wc/v3/wallet/?email={email}` -- returns all transactions for a user
- Auth: WooCommerce REST API consumer key/secret (Basic Auth or query params)
- Response: array of `{ transaction_id, user_id, date, type, amount, balance, details, currency, blog_id }`
- Pagination via `per_page` and `page` query params
- `GET /wp-json/wc/v3/wallet/balance/?email={email}` -- returns current balance

**What we are building:**
- An "Other Services" column in the table showing credit transaction amounts
- Tooltip on hover showing the transaction `details` text
- Only `type: "credit"` transactions shown (wallet top-ups, not debits)
- PHP adapter to merge wallet data with existing usage data by date

---

## Table Stakes (Must Have)

Features that are required for the milestone to deliver value. Missing any of these means the integration is incomplete or misleading.

| Feature | Why Expected | Complexity | Dependencies |
|---------|--------------|------------|--------------|
| **PHP client for TeraWallet REST API** | Need a server-side client to call `/wp-json/wc/v3/wallet/` with WooCommerce consumer key/secret auth. Cannot call from browser (CORS + secret exposure). Must use `wp_remote_get()` with Basic Auth header. | MEDIUM | Depends on: WooCommerce consumer key/secret stored in `wp-config.php` or plugin settings. Needs the WordPress user's email to pass as the `email` query param. |
| **AJAX handler for wallet transactions** | Either a new `tp_get_wallet_transactions` AJAX action, or extend the existing `ajax_get_usage_summary` to also fetch wallet data. The browser needs the merged data in a single response to avoid multiple AJAX calls and client-side merge complexity. | MEDIUM | Depends on: PHP client (above). Recommend extending existing handler rather than adding a new one -- one AJAX call returns both usage data and wallet data merged by date. |
| **Date-keyed merge adapter** | Wallet transactions are per-transaction (multiple per day possible), usage data is per-day. Must aggregate wallet credits by date, then merge into the daily records array. Days with only wallet activity (no usage) should still appear as rows. Days with only usage (no wallet) show empty Other Services cell. | MEDIUM | Depends on: both data sources fetched. The merge must happen server-side in PHP to keep JS rendering simple. Output: each day record gains an `otherServices` field (array of `{ amount, description }`). |
| **"Other Services" table column** | New column between Cost and Balance columns showing the sum of credit amounts for that day. Format: `+$X.XX` in green text. If no transactions for a day, show `--` or leave empty. | LOW | Depends on: merged data from adapter. Requires updating `renderRows()` in JS, the `<thead>` in PHP template, column width CSS, and mobile card layout `data-label`. |
| **Tooltip with transaction descriptions** | Hover over the Other Services cell shows the `details` text from the transaction(s). Multiple transactions on the same day should show each description on its own line. Use Bootstrap 5 tooltip (already loaded). | LOW | Depends on: Other Services column rendering. Bootstrap 5 tooltips initialized via `data-bs-toggle="tooltip"`. Multi-line via `data-bs-html="true"` with `<br>` separators. |
| **Filter to credit transactions only** | Only show `type: "credit"` transactions. Debits (charges, purchases) are not "Other Services" -- they represent the user spending wallet balance, which is already tracked in the Cost/Balance columns from the usage API. Showing debits would double-count. | LOW | Depends on: PHP client filtering response. Simple `array_filter` on the API response. |
| **Skeleton and error handling for wallet data** | Wallet API call may fail independently of usage API. Must handle: wallet API timeout, auth failure, empty response. Should not block usage data from rendering -- show usage data normally, show "N/A" or "--" in Other Services column if wallet fetch fails. | MEDIUM | Depends on: error handling in the AJAX handler. Use try/catch around wallet call, set a flag if wallet data unavailable, and pass it to JS so the column renders gracefully. |
| **Column width rebalancing** | Current columns: Date 25%, Hits 30%, Cost 20%, Balance 25%. Adding a 5th column requires rebalancing. Suggested: Date 20%, Hits 25%, Other Services 15%, Cost 15%, Balance 25%. The Other Services column is narrow (just `+$X.XX`). | LOW | Depends on: CSS changes in `usage-dashboard.css`. Must also update mobile card layout breakpoint styles. |
| **Summary card for Other Services total** | Add a 4th summary stat card showing the total Other Services credits for the period. Icon: `fa-hand-holding-dollar`. Label: "Other Services". Secondary: number of transactions. Completes the at-a-glance view. | LOW | Depends on: merged data. Update `renderSummaryCards()` in JS. Use integer-cent accumulation (same pattern as existing `totalCostCents`). |
| **Mobile card layout for new column** | The table converts to card layout below 768px. The new Other Services field needs a `data-label="Other Services"` attribute and appropriate styling in the mobile card view. Tooltip must work on tap (not just hover). | LOW | Depends on: column rendering. Bootstrap 5 tooltips already work on tap on mobile. Just add the `data-label` attribute and verify card layout spacing. |

---

## Differentiators (Above and Beyond)

Features that would make the integration notably polished. Worth building if time allows, but the milestone succeeds without them.

| Feature | Value Proposition | Complexity | Dependencies |
|---------|-------------------|------------|--------------|
| **Expandable row detail for transaction descriptions** | Instead of (or in addition to) tooltips, clicking a row with Other Services data expands an inline detail section showing each transaction's full description, amount, and time. Better for days with many transactions. | MEDIUM | Depends on: basic column rendering. Requires new JS for row expansion, CSS for detail panel. Similar pattern exists in many dashboard frameworks. |
| **Color-coded amount badges** | Show the Other Services amount as a small green badge/pill (`+$10.00`) to visually distinguish it from the cost column (which shows charges). Gives immediate visual signal that this is money coming in, not going out. | LOW | Depends on: basic column rendering. CSS class `.tp-ud-credit-badge` with green background. |
| **Wallet transactions in chart** | Add a third series to the area chart showing Other Services credits as vertical bars (mixed chart: area + bar). Provides visual context for when top-ups happened relative to usage patterns. | HIGH | Depends on: merged data with daily Other Services totals. Chart.js supports mixed chart types but requires careful axis configuration. Risk of visual clutter if transactions are sparse. |
| **Transaction type icons** | Show different icons for different transaction types: shopping cart for purchases (`#1279`), globe for admin console visits, gift for promotional credits. Parse the `details` string to determine type. | LOW | Depends on: basic column rendering. Simple string matching on `details` field. Font Awesome icons already available. |
| **Server-side caching of wallet data** | Cache wallet transactions in a WordPress transient (keyed by user + date range, 5-min TTL). The TeraWallet API paginates, so multiple calls may be needed for users with many transactions. Caching avoids repeated multi-page fetches. | MEDIUM | Depends on: PHP client. Use `set_transient()` / `get_transient()`. Must invalidate when user triggers a wallet action (top-up). |
| **"Other Services" sort column** | Make the Other Services column header sortable, sorting by total credit amount per day. Days with no credits sort as 0. | LOW | Depends on: merged data including `otherServicesTotal` numeric field per day. Add `data-sort="otherServicesTotal"` to the `<th>`. Extend `getSortedData()` in JS. |

---

## Anti-Features (Explicitly Do NOT Build)

Features that seem logical but would add complexity, confusion, or scope creep.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| **Show debit transactions** | Debits represent wallet spending (purchases, usage charges). The Cost column already shows daily charges from the Traffic Portal API. Showing debits in Other Services would double-count costs and confuse users about where money went. The milestone scope explicitly states "only credits." | Filter to `type: "credit"` only. If debit visibility is needed later, it belongs in a dedicated wallet transaction history page, not this column. |
| **Client-side wallet API calls** | Calling `/wp-json/wc/v3/wallet/` from the browser exposes WooCommerce consumer key/secret. Even with read-only keys, this is a security anti-pattern. The existing dashboard already proxies all API calls through WordPress AJAX. | Fetch wallet data server-side in the PHP AJAX handler. Return merged data to the browser in the existing response format. |
| **Separate AJAX call for wallet data** | Making two AJAX calls (one for usage, one for wallet) and merging client-side adds complexity: race conditions, partial failure states, two loading indicators, client-side date merge logic. | Merge server-side in PHP. Return a single unified response from `ajax_get_usage_summary`. JS stays simple -- it renders whatever `days[]` array it receives. |
| **Real-time wallet balance sync** | Polling or WebSocket connection to detect wallet balance changes in real-time. This is a management dashboard, not a trading platform. Users check it occasionally, not continuously. | The balance shown is from the last data fetch. The existing refresh/date-apply flow re-fetches both usage and wallet data. |
| **Wallet top-up button in the dashboard** | Adding a "Top Up Wallet" action button in the usage dashboard. This crosses the boundary between viewing data and taking financial actions. The wallet top-up flow is handled by the WooCommerce storefront (TeraWallet provides its own wallet page). | Keep the dashboard read-only. Link to the WooCommerce wallet page if needed. |
| **Custom date filtering for wallet only** | Separate date pickers for wallet transactions vs usage data. The whole point of the merge is seeing both data streams aligned on the same date axis. | Use the existing date range filter. It applies to both data sources. |
| **Parse transaction details into structured fields** | The `details` field is a free-text string (e.g., "Balance credited for visiting TrafficPortal administrative console. +$1.00"). Parsing it into structured fields (source, action, amount) would be fragile and break when the text format changes. | Show the raw `details` text in the tooltip. It is already human-readable. |

---

## Feature Dependencies

```
WC consumer key config --> PHP TeraWallet client (MEDIUM)
                              |
                              +--> AJAX handler extension (MEDIUM)
                              |       |
                              |       +--> Date-keyed merge adapter (MEDIUM)
                              |               |
                              |               +--> "Other Services" column (LOW)
                              |               |       |
                              |               |       +--> Tooltip descriptions (LOW)
                              |               |       +--> Column width rebalancing (LOW)
                              |               |       +--> Mobile card layout (LOW)
                              |               |       +--> Sort column (LOW, differentiator)
                              |               |
                              |               +--> Summary card (LOW)
                              |
                              +--> Error handling / graceful degradation (MEDIUM)

Expandable row detail (MEDIUM, differentiator) -- independent of tooltip
Wallet data in chart (HIGH, differentiator) -- depends on merge adapter
Server-side caching (MEDIUM, differentiator) -- depends on PHP client
```

**Critical path:** PHP client --> AJAX handler --> Merge adapter --> Column rendering. Everything else branches from the merge adapter output.

**Critical coupling:** The merge adapter and AJAX handler extension are the riskiest pieces. The adapter must handle: (a) days with only usage data, (b) days with only wallet data, (c) days with both, (d) multiple wallet transactions on the same day. Getting this wrong corrupts the entire table.

---

## MVP Recommendation

**Build in this order (each builds on previous):**

1. **PHP TeraWallet client** -- `wp_remote_get()` to `/wp-json/wc/v3/wallet/` with Basic Auth. Filter to credits only. Handle pagination if needed.
2. **Date-keyed merge adapter** -- PHP function that takes usage `days[]` array and wallet transactions array, groups wallet by date, merges into unified `days[]` with `otherServices` field.
3. **AJAX handler extension** -- Extend `ajax_get_usage_summary` to call both APIs, merge, return unified response. Graceful degradation if wallet call fails.
4. **Column rendering** -- Update template `<thead>`, JS `renderRows()`, CSS column widths. Add Bootstrap tooltip for descriptions.
5. **Summary card** -- Add 4th card to `renderSummaryCards()`.
6. **Mobile layout** -- Verify card layout with new column, add `data-label`.

**Defer to future:**
- Chart integration (HIGH complexity, low value for sparse transaction data)
- Server-side caching (good optimization but not blocking for initial release)
- Expandable row detail (tooltip is sufficient for MVP)
- Sort by Other Services (can add later without data changes)

**Rationale:** The MVP gives users the core value -- seeing wallet credits alongside their daily usage data -- with minimal JS changes. The heavy lifting is in PHP (API client + merge adapter). The JS changes are additive (one new column, one new card) and follow existing patterns exactly.

---

## Complexity Estimates

| Feature | Complexity | Effort | Risk |
|---------|-----------|--------|------|
| PHP TeraWallet client | MEDIUM | 2-3 hours | MEDIUM -- WC auth may need debugging |
| Date-keyed merge adapter | MEDIUM | 2-3 hours | MEDIUM -- edge cases (no overlap, multi-transaction days) |
| AJAX handler extension | MEDIUM | 1-2 hours | LOW -- extends existing pattern |
| Error handling / graceful degradation | MEDIUM | 1-2 hours | LOW -- isolated try/catch |
| "Other Services" column + tooltip | LOW | 1-2 hours | LOW -- follows existing renderRows() pattern |
| Column width rebalancing | LOW | 30 min | LOW -- CSS only |
| Summary card | LOW | 30 min | LOW -- follows existing buildStatCard() pattern |
| Mobile card layout | LOW | 30 min | LOW -- add data-label + verify |
| Integration tests | MEDIUM | 2-3 hours | LOW -- test merge logic with known inputs |
| E2E tests | MEDIUM | 2-3 hours | MEDIUM -- needs real or mocked wallet data |

**Total estimated effort:** 14-18 hours for the full MVP.

---

## Data Flow (Reference)

```
Browser                  WordPress (PHP)                  External APIs
-------                  ---------------                  -------------
                                                          Traffic Portal API
loadData() -->  admin-ajax.php                            GET /user-activity-summary/{uid}
   AJAX POST       |                                           |
   action=         +-- ajax_get_usage_summary()                |
   tp_get_usage_       |                                       |
   summary             +-- $this->client->getUserActivitySummary()
                       |       returns: { source: [{ date, totalHits, hitCost, balance }] }
                       |
                       +-- $this->wallet_client->getTransactions($email)
                       |       calls: GET /wp-json/wc/v3/wallet/?email=X
                       |       returns: [{ transaction_id, date, type, amount, details, ... }]
                       |
                       +-- $this->merge_usage_with_wallet($usage_days, $wallet_txns)
                       |       filters credits only
                       |       groups by date
                       |       merges into days[] with otherServices field
                       |
                       +-- wp_send_json_success({ days: [...] })
                               each day now has:
                               { date, totalHits, hitCost, balance,
                                 otherServices: [{ amount, description }],
                                 otherServicesTotal: 10.00 }
```

---

## Sources

- [TeraWallet API V3 Documentation -- GitHub Wiki](https://github.com/malsubrata/woo-wallet/wiki/API-V3) -- HIGH confidence, official plugin docs
- [WooCommerce REST API Authentication -- Official Docs](https://woocommerce.github.io/woocommerce-rest-api-docs/) -- HIGH confidence
- [TeraWallet WordPress Plugin -- WordPress.org](https://wordpress.org/plugins/woo-wallet/) -- HIGH confidence
- [TeraWallet GitHub Repository -- malsubrata/woo-wallet](https://github.com/malsubrata/woo-wallet) -- HIGH confidence
- Codebase analysis: `class-tp-api-handler.php` (1700+ lines, `ajax_get_usage_summary` at line 1573, `validate_usage_summary_response` at line 1647), `usage-dashboard.js` (770 lines, `renderRows` at line 313, `renderSummaryCards` at line 424), `usage-dashboard-template.php` (159 lines), `usage-dashboard.css` (759 lines), `class-tp-usage-dashboard-shortcode.php` (123 lines)
- `docs/API-REQUIREMENTS-V2.md` -- internal document specifying future "Other Services" and wallet data shapes (sections 3 and 4)
- `.planning/PROJECT.md` -- milestone v2.2 scope definition confirming "Other Services" column with credit amounts and tooltip descriptions
