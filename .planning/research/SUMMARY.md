# Project Research Summary

**Project:** tp-link-shortener-plugin — v2.0 Usage Dashboard (`[tp_usage_dashboard]`)
**Domain:** WordPress plugin billing/usage analytics shortcode
**Researched:** 2026-02-22
**Confidence:** HIGH

## Executive Summary

This feature adds a `[tp_usage_dashboard]` shortcode to the existing WordPress link shortener plugin. The dashboard gives users a daily view of their link activity, costs, and running account balance — a metered-credit billing dashboard analogous to GoHighLevel SaaS Wallet or AWS Cost Dashboard. Research is grounded almost entirely in direct codebase inspection and an authoritative API reference, which gives unusually high confidence: an established four-file pattern (shortcode class, template, JS, CSS) already exists in two sibling shortcodes, and the full technology stack (Chart.js 4.4.1, Bootstrap 5.3.0, jQuery, native date inputs) is already loaded on every shortcode page.

The recommended approach is zero new library dependencies. Every technology needed for the area chart, date filter, AJAX data flow, and stats table is already present in the plugin. The implementation is additive: four new files plus small additions to three existing files. The key architectural decision is to route data through WordPress admin-ajax.php (not call the external API directly from JS), matching the security posture of the existing shortcodes where the user ID is always derived server-side and never accepted from the client.

The primary implementation risk is Chart.js-specific: a documented infinite resize loop in flex containers, silent failure when a date adapter is missing (use category scale instead to avoid this entirely), and memory leaks from not destroying chart instances before re-render. A secondary risk is financial display precision: floating-point drift in the running balance column. Both categories must be addressed in the same phase as chart and table rendering — retrofitting them is significantly harder than building correctly from the start.

---

## Key Findings

### Recommended Stack

The stack is locked by the existing plugin. Zero new libraries are required. Chart.js 4.4.1 (already loaded via `tp-chartjs` handle) supports area charts natively with `type: 'line'` and `fill: 'origin'` — no additional plugin needed. Bootstrap 5.3.0 covers all layout needs (stats cards, table, responsive grid). jQuery provides AJAX and DOM manipulation. `wp_localize_script` passes PHP context (default date range, nonce, user ID) to JS — identical to how `tpClientLinks` works in the existing client-links shortcode.

New files are three (JS, CSS, template) plus one PHP shortcode class, matching the exact pattern of `class-tp-client-links-shortcode.php`. CSS uses the `tp-ud-*` prefix to avoid collisions. JS is a plain IIFE using `var` + jQuery — no ES modules, no build step.

**Core technologies:**
- Chart.js 4.4.1: area chart (`type: 'line'`, `fill: 'origin'`) for time-series daily activity — already loaded, reuse `tp-chartjs` handle
- Bootstrap 5.3.0: stats cards, table, grid layout — already loaded, reuse `tp-bootstrap` handle
- jQuery (WP-bundled): AJAX to admin-ajax.php, DOM updates — consistent with all other shortcodes
- Native `<input type="date">`: date range filter — already the codebase pattern in client-links, no flatpickr needed
- `wp_localize_script`: PHP-to-JS config bridge — identical pattern to `tpClientLinks` global object
- WordPress transients: caching AJAX proxy responses — prevents every page load from hitting the external API live

**What NOT to add:** flatpickr (49 KB for functionality already covered by two native date inputs), Moment.js/Day.js (API date strings need no parsing), DataTables.js (30 rows max needs no library), any Chart.js date adapter (use `type: 'category'` scale with pre-formatted strings instead), or any build tool.

### Expected Features

The `GET /user-activity-summary/{uid}` endpoint returns `[{ date, totalHits, hitCost, balance }]` per day. This hard API constraint shapes what is buildable for v2.0 without additional backend work.

**Must have (table stakes — v2.0):**
- Auth gate — private billing data; show login prompt for unauthenticated users
- Date range filter (last 30 days default) — universal dashboard expectation; two `<input type="date">` + Apply button
- Summary stats strip — three cards: Total Hits, Total Cost (period), Current Balance
- Area chart — daily time series, Chart.js, yellow=clicks, green=QR (or totalHits single series if split data unavailable)
- Daily stats table — Date, Clicks, QR Scans, Total Hits, Cost, Balance; sorted newest-first
- Running balance column — API already returns cumulative `balance`; color-code green/amber/red
- Cost formatted as currency — `$0.50` not `-0.5`; non-negotiable for any billing UI
- Loading skeleton, empty state, error state — all reuse existing plugin patterns

**Should have (competitive — v2.x after validation):**
- Preset date buttons (7d/30d/90d) — low effort, reduces friction significantly for return users
- Hover chart tooltips — Chart.js tooltip config, patterns already in codebase
- Period totals row in table — sum columns, eliminate user mental math
- Clicks vs QR split (two-series chart) — requires `/by-source` parallel API call or clearly labeled mock

**Defer (v3.0+):**
- Wallet top-up/payment flow — requires Stripe/WooCommerce; completely out of scope
- CSV/PDF export — out of scope per PROJECT.md; defer until users explicitly request it
- Per-link cost breakdown — belongs in `[tp_client_links]`, not this billing dashboard
- Real-time auto-refresh — daily data granularity makes polling pointless

**Hard API constraint:** `totalHits` does not split clicks vs QR at the summary level. Two options: (a) show a single area labeled "Total Hits", or (b) call `/by-source` in parallel and subtract QR hits. If mocking the split with a static ratio, a user-facing disclaimer is mandatory — never present fabricated percentages as real data.

### Architecture Approach

The architecture is dictated by the existing codebase pattern. A new `TP_Usage_Dashboard_Shortcode` class follows the identical four-step template method used by `TP_Dashboard_Shortcode` and `TP_Client_Links_Shortcode`: constructor registers the shortcode, `render_shortcode()` gates on `is_user_logged_in()`, `enqueue_assets()` enqueues scripts/styles and calls `wp_localize_script`, and the template is included via output buffer. Data flows from the browser through WordPress admin-ajax.php to a new method on the existing `TrafficPortalApiClient`, then back as `wp_send_json_success`.

The mandated build order (PHP shortcode → HTML template → API client/AJAX handler → JS → CSS) is not negotiable: JS cannot be written without knowing the HTML IDs, and the API method cannot be tested without the AJAX handler. This is the same dependency chain used to build both existing shortcodes.

**Major components:**
1. `class-tp-usage-dashboard-shortcode.php` — shortcode registration, asset enqueue, template include, auth gate
2. `templates/usage-dashboard-template.php` — static HTML skeleton (canvas, table, date inputs, Apply button)
3. `assets/js/usage-dashboard.js` — IIFE with state object, `loadData()`, `renderChart()`, `renderTable()`, `mockSplit()`
4. `assets/css/usage-dashboard.css` — `.tp-ud-*` scoped styles, chart wrapper constraints
5. `TP_API_Handler::ajax_get_usage_summary()` — nonce check, server-side UID, delegate to API client, return JSON
6. `TrafficPortalApiClient::getUserActivitySummary()` — GET request to external API, parse response

**Critical security boundary:** The UID must always be determined server-side via `TP_Link_Shortener::get_user_id()`. Never accept `uid` from `$_POST`. This pattern is already enforced across all existing AJAX handlers (confirmed in commit `e063541`).

### Critical Pitfalls

1. **Chart.js infinite resize loop in flex containers** — Add `min-width: 0` to the flex parent and `position: relative; height: 280px; overflow: hidden` (explicit height, not min-height) to the chart wrapper. Must be in CSS before any chart JS is written; detecting it requires resizing the browser window multiple times.

2. **Missing date adapter causes silent blank chart** — Avoid `type: 'time'` scale entirely. Use `type: 'category'` scale with pre-formatted `YYYY-MM-DD` label strings from the API. This eliminates the adapter requirement without any functionality loss; the chart renders date labels correctly with no extra script.

3. **Chart instance not destroyed before re-render** — Keep `var chart = null` in module scope. Always call `chart.destroy(); chart = null;` before `new Chart()`. The date range filter triggers re-render on every Apply click — this bug surfaces on the first filter change.

4. **Floating-point drift in running balance column** — Use `Math.round((runningBalance + cost) * 10000) / 10000` after each accumulation step. Display with `toFixed(2)`. Testing with round numbers ($1.00/day) hides this bug; test with values like $0.001 per hit across 30+ rows.

5. **Mocked click/QR split presented as real data** — Add a visible user-facing disclaimer if using the 80/20 mock ratio. Alternatively, show a single total-hits area labeled "Total Hits (QR breakdown coming soon)". A constant 20% QR line across all 30 days regardless of actual campaigns is the warning sign.

---

## Implications for Roadmap

Based on research, the implementation has clear sequential dependencies dictated by the existing architecture pattern. The build order (PHP → Template → API → JS → CSS) is the same order used to build both sibling shortcodes and must be followed. Pitfalls 1 through 4 must all be addressed in the same phase as chart and table rendering — they cannot be deferred to a polish phase without significant rework risk.

### Phase 1: Foundation — Shortcode, Template, and API Proxy

**Rationale:** The PHP shortcode class and HTML template define the entire HTML contract. All downstream work (JS, CSS, AJAX) depends on the element IDs and classes established here. The AJAX handler and API client method must exist before any JS can make a real data call. This phase has direct codebase templates to copy from and zero external dependencies.

**Delivers:** A page showing `[tp_usage_dashboard]` renders the static HTML skeleton (chart canvas placeholder, table structure, date inputs, Apply button). The AJAX endpoint `admin-ajax.php?action=tp_get_usage_summary` returns real data from the Traffic Portal API. Auth gate blocks unauthenticated access. WordPress transient cache wraps the API call.

**Features addressed:** Auth gate (P1), HTML skeleton for loading/empty/error states, date range filter inputs (static, wired via `wp_localize_script` default).

**Pitfalls to prevent:** Server-side UID enforcement (never from `$_POST`), nonce + `is_user_logged_in()` double-check on AJAX handler, WordPress transient caching to prevent hammering the external API.

**Research flag:** None — direct codebase templates exist in two sibling shortcodes; copy, rename, adapt.

---

### Phase 2: Data Layer and Stats Table

**Rationale:** Before rendering a chart, establish the data pipeline and validate it with the simpler stats table. The table has no Chart.js complexity and will immediately confirm that the API response shape is correct, cost values are properly formatted, and the running balance calculation is accurate. The floating-point pitfall must be addressed here, not discovered after the chart is built.

**Delivers:** A fully working stats table (Date, Clicks, QR Scans, Total Hits, Cost, Balance) with real API data, currency formatting, balance color-coding (green/amber/red), and the summary stats strip (three cards: Total Hits, Total Cost, Current Balance). The date range Apply button reloads the table.

**Features addressed:** Summary stats strip (P1), daily stats table (P1), cost as currency (P1), running balance column (P1), balance color coding (P1), loading skeleton wired to real state transitions, empty state, error state.

**Pitfalls to prevent:** Floating-point balance drift (round after each accumulation step), date range timezone behavior verification (pass `YYYY-MM-DD` strings directly — confirm API interprets them as expected), table HTML built as single string and injected once (not 30 separate `.append()` calls causing 30 DOM reflows).

**Research flag:** None — straightforward JS table rendering and currency formatting. API shape is known from API_REFERENCE.md.

---

### Phase 3: Chart Rendering

**Rationale:** The area chart is the most complex UI component and contains the majority of the technical pitfalls. Building it after the data layer is validated means chart bugs are isolated to Chart.js behavior, not mixed with data shape issues. All three Chart.js pitfalls (flex resize loop, missing adapter, chart not destroyed) must be addressed in this phase — they are interconnected and cannot be split.

**Delivers:** A working area chart showing daily activity with correct date labels on the X-axis. Yellow line = regular hits, green line = QR hits (mocked 80/20 with visible disclaimer, OR single total-hits series with clear label). Chart re-renders correctly on date range change without memory leaks or canvas errors. X-axis uses `type: 'category'` scale (no date adapter required). CSS chart wrapper uses `position: relative; height: 280px; overflow: hidden` and flex parent has `min-width: 0`.

**Features addressed:** Area chart (P1), color scheme matching design mockup (`#f5a623` yellow, `#22b573` green), clicks vs QR series (with mocked split disclaimer).

**Pitfalls to prevent:** Flex container resize loop (CSS-first fix, established before chart JS), chart not destroyed before re-render (module-scoped chart variable with `.destroy()`), category scale used instead of time scale (eliminates adapter dependency entirely). Mocked split must have visible disclaimer in UI and `// TODO` comment in code.

**Research flag:** Needs explicit QA during implementation. Run the "Looks Done But Isn't" checklist from PITFALLS.md: resize browser window 10 times (chart height must stay constant), change date range 5 times in a row (no canvas errors, no double-stacked datasets), inspect balance column with non-round input values.

---

### Phase 4: Polish and v2.x Features

**Rationale:** Once the core v2.0 functionality is deployed and verified working, add the low-complexity enhancements that reduce friction for return users. These features have zero dependencies on each other and none block the v2.0 launch.

**Delivers:** Preset date buttons (7d/30d/90d) with active state, hover chart tooltips configured via `tooltip.callbacks` to show date + clicks + QR + cost, period totals row in table footer. If the `/by-source` endpoint proves reliable with real user data, upgrade the mocked split to a real two-series chart.

**Features addressed:** Preset date buttons (P2), hover tooltips (P2), period totals row (P2), refined QR/click split (P2 conditional on by-source data quality).

**Research flag:** None — all standard patterns. By-source integration is a single data-source swap in `mockSplit()`.

---

### Phase Ordering Rationale

- PHP shortcode and template must come first because they define the HTML contract (IDs, classes) that JS and CSS depend on — this is the mandatory build order established by both existing sibling shortcodes.
- Data layer (table) before chart because: (a) it validates API shape with simpler rendering code, (b) floating-point pitfall is easier to catch and test in a table, (c) chart debugging is cleaner when the data layer is proven.
- Chart phase is isolated because it contains the majority of technical pitfalls; isolating it prevents chart bugs from being attributed to data issues.
- Polish features go last because they are entirely additive to already-working functionality.

### Research Flags

Phases needing attention during implementation:
- **Phase 3 (Chart Rendering):** Chart.js flex container behavior and instance lifecycle require explicit verification against the PITFALLS.md "Looks Done But Isn't" checklist. The category-scale vs time-scale choice must be confirmed to handle zero-click days correctly (appear as `0`, not gaps in the line).

Phases with standard patterns (no additional research needed):
- **Phase 1 (Foundation):** Direct codebase templates exist in `class-tp-client-links-shortcode.php` and `class-tp-api-handler.php`. Copy, rename, adapt.
- **Phase 2 (Data Layer):** Standard JS table rendering and currency formatting. API shape is fully documented in API_REFERENCE.md.
- **Phase 4 (Polish):** All enhancements are additive to already-proven functionality; Chart.js tooltip config pattern exists in the codebase.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Based on direct codebase inspection — existing handles, versions, and IIFE patterns are known with certainty. No new library additions, eliminating version conflict risk entirely. |
| Features | HIGH | API_REFERENCE.md is authoritative and determines exactly what is buildable. Competitor analysis (GoHighLevel, Bitly, AWS) confirms expected UX patterns. The `totalHits`-only constraint is a hard API fact, not an assumption. |
| Architecture | HIGH | Based on direct inspection of two canonical sibling shortcodes and the codebase ARCHITECTURE.md and CONVENTIONS.md. The four-step template method pattern is well-established and consistent across the plugin. |
| Pitfalls | HIGH | Chart.js pitfalls verified against official GitHub issues (#5805, #9001) and official docs. Financial float precision from multiple authoritative sources (Modern Treasury, Honeybadger). WordPress security from official developer docs and Patchstack. |

**Overall confidence:** HIGH

### Gaps to Address

- **API response envelope shape:** The `getUserActivitySummary()` method assumes the response body is `{ days: [...] }`. The exact key name must be verified against the live API before the PHP method is finalized. This is a one-line fix but cannot be assumed from the reference doc alone.

- **Timezone behavior of date parameters:** API_REFERENCE.md does not specify whether `start_date`/`end_date` parameters are interpreted as UTC or local time. Must be verified empirically by fetching known-date data and comparing row counts with actual activity. The PITFALLS.md checklist item ("fetch today's data and verify the row count matches actual activity for local calendar day") is the verification step.

- **`/by-source` data reliability:** The decision to use a mocked 80/20 click/QR split vs. a real `/by-source` API call depends on whether existing users have QR links tagged with `?qr=1`. This cannot be known from research — requires checking real data in the environment. Safe default: single-series total hits with clear label, upgraded to two-series if by-source proves consistently populated.

- **WordPress transient key strategy:** Cache key `tp_usage_{uid}_{start}_{end}` is assumed. The actual `$uid` value format and max transient key length (172 characters in WordPress) must be verified when implementing the PHP AJAX handler.

---

## Sources

### Primary (HIGH confidence)

- `includes/class-tp-client-links-shortcode.php` (codebase) — canonical shortcode pattern, enqueue order, `wp_localize_script` config
- `includes/class-tp-api-handler.php` (codebase) — AJAX handler nonce/uid/JSON pattern, `register_ajax_handlers()` structure
- `includes/TrafficPortal/TrafficPortalApiClient.php` (codebase) — HTTP client method structure, `handleHttpErrors()` pattern
- `assets/js/client-links.js` (codebase) — IIFE/state/DOM-cache JS pattern, Chart.js bar chart reference config
- `API_REFERENCE.md` (codebase) — authoritative API shape (`/user-activity-summary/{uid}`, response fields: `date`, `totalHits`, `hitCost`, `balance`)
- `.planning/codebase/ARCHITECTURE.md` (codebase) — layer map and data flows
- `.planning/codebase/CONVENTIONS.md` (codebase) — naming rules, file patterns, CSS prefix conventions
- Chart.js official docs (chartjs.org) — `fill: 'origin'`, area chart config, responsive config, `.destroy()` API
- Chart.js GitHub issues #5805 and #9001 — flex container infinite resize loop (known bug, documented CSS fix)
- WordPress Developer Docs — nonces, transients, `wp_localize_script`, `wp_ajax_` action pattern
- Patchstack Academy — broken access control patterns in WordPress plugins
- Git log commit `e063541` — confirmed uid must always be server-side (removed all client-side uid passing)

### Secondary (MEDIUM confidence)

- GoHighLevel SaaS Wallet documentation — billing/balance dashboard UX patterns (running balance, color-coding, date presets)
- Bitly Analytics documentation — clicks + scans time-series chart, 7d/14d/30d date preset patterns
- AWS Cost Dashboard (search results) — daily breakdown table, date range filter, period cost summary pattern
- Modern Treasury / Honeybadger — floating-point currency precision in JavaScript
- flatpickr.js.org — file size confirmation (~49 KB JS); native inputs are superior on mobile per their own docs

### Tertiary (LOW confidence)

- SaaS billing dashboard UX patterns (general web search 2025/2026) — summary stats strip position, chart-first layout
- colorwhistle.com SaaS Credits System Guide 2026 — wallet/credit balance UX expectations

---

*Research completed: 2026-02-22*
*Ready for roadmap: yes*
