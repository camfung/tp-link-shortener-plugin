# Pitfalls Research

**Domain:** Billing/usage dashboard added to existing WordPress link shortener plugin
**Researched:** 2026-02-22
**Confidence:** HIGH (Chart.js issues verified via official GitHub and docs; financial precision from multiple authoritative sources; WordPress security from WordPress Developer Blog and Patchstack)

---

## Critical Pitfalls

### Pitfall 1: Chart.js Canvas Grows Indefinitely in Flex Container

**What goes wrong:**
Chart.js with `responsive: true` and `maintainAspectRatio: false` inside a flex container causes an infinite resize loop. Chart.js reads the container width, resizes the canvas, which grows the flex container, which triggers another Chart.js resize. The area chart grows taller with each resize event and never stabilizes. Observed in production as the chart area doubling in height every time the page is resized or orientation changes.

**Why it happens:**
Flex containers have an implicit `min-width: auto` that prevents flex children from shrinking below their content width. When Chart.js expands the canvas, the container grows to match. Chart.js's ResizeObserver detects the container size change and triggers another resize. This is a documented long-standing bug (GitHub issues #5805 and #9001 in the Chart.js repo, still referenced in 2025 discussions).

**How to avoid:**
The chart wrapper div MUST have `position: relative` and the flex child containing it MUST have `min-width: 0` (or `overflow: hidden`). Without `min-width: 0`, the flex intrinsic sizing prevents the chart from ever shrinking.

```css
/* The flex row containing the chart */
.tp-billing-chart-row {
    display: flex;
    min-width: 0; /* CRITICAL — allows chart to shrink */
}

/* The chart wrapper itself */
.tp-billing-chart-wrapper {
    position: relative;  /* Required by Chart.js */
    min-width: 0;        /* Belt-and-suspenders */
    overflow: hidden;    /* Prevents bleedout */
    height: 280px;       /* Explicit height, not min-height */
}
```

Never rely on `min-height` alone for the chart wrapper — use explicit `height` so Chart.js has a definitive constraint.

**Warning signs:**
- Chart area visually grows taller every time the browser window is resized
- Console shows: `ResizeObserver loop limit exceeded` or `ResizeObserver loop completed with undelivered notifications`
- Chart renders correctly on first load but breaks after a date range change that re-renders the chart

**Phase to address:**
Phase 1 (Chart Foundation) — The wrapper CSS must be established correctly before any chart rendering code is written. Fixing this after the JS is complete requires CSS-only changes, but detecting it requires testing at specific flex container widths.

---

### Pitfall 2: Chart.js Requires a Date Adapter for Time-Scale X-Axis — Silent Failure

**What goes wrong:**
When using `type: 'time'` on the X-axis (needed to render daily data correctly with date labels), Chart.js 3+ throws a silent or cryptic error: `"This method is not implemented: Check that a complete date adapter is provided"`. The chart renders blank or broken with no obvious user-facing error. Developers often waste time debugging chart configuration when the fix is adding one script tag.

**Why it happens:**
In Chart.js v3, date/time handling was separated from the core library. `type: 'time'` requires an external adapter (e.g., `chartjs-adapter-date-fns`, `chartjs-adapter-luxon`, `chartjs-adapter-moment`). This is not loaded by the Chart.js CDN bundle by default. The error message is non-obvious and easy to miss if the browser console is not open.

**How to avoid:**
Include the date adapter CDN script immediately after Chart.js. For this project (no build step, CDN delivery via `wp_enqueue_scripts`):

```php
// In PHP enqueue
wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js', [], '4.x', true);
wp_enqueue_script('chartjs-adapter', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js', ['chartjs'], '3.x', true);
```

The bundle version of `chartjs-adapter-date-fns` includes date-fns, so only one additional script is needed. Alternatively, avoid the time scale entirely and use a `'category'` scale with pre-formatted date strings as labels — this avoids the adapter requirement at the cost of some axis flexibility.

**Warning signs:**
- Chart canvas renders as blank white rectangle
- Console error: `"This method is not implemented"` or `"No scale found with id 'time'"`
- Chart works for `type: 'bar'` or `type: 'line'` with category scale but fails when switching to time scale

**Phase to address:**
Phase 1 (Chart Foundation) — Must be resolved at the time the chart is first rendered. A category scale with formatted date strings is an acceptable alternative if the time scale adapter adds complexity.

---

### Pitfall 3: Chart Instance Not Destroyed Before Re-Render Causes Memory Leak and Double Rendering

**What goes wrong:**
When the date range filter changes and the chart needs to re-render with new data, calling `new Chart(canvas, config)` on a canvas that already has a Chart.js instance causes the error: `"Canvas is already in use. Chart with ID X must be destroyed before the canvas with ID Y can be reused"`. Even without that error (in older Chart.js versions), the old instance persists in memory and both instances receive resize events, causing visual artifacts — two datasets stacked, wrong colors, duplicate tooltips.

**Why it happens:**
Chart.js tracks instances internally by canvas element. If the canvas DOM element is reused without calling `.destroy()` on the previous instance, Chart.js throws or behaves unpredictably. Developers building filter-triggered chart refreshes often forget this because simple pages render charts once and never need to destroy them.

**How to avoid:**
Always keep a reference to the chart instance and destroy it before re-creating:

```javascript
let billingChart = null;

function renderChart(data) {
    const canvas = document.getElementById('tp-billing-chart');

    // Destroy previous instance before re-using canvas
    if (billingChart) {
        billingChart.destroy();
        billingChart = null;
    }

    billingChart = new Chart(canvas, {
        type: 'line',
        // ... config
    });
}
```

Alternatively, use `chart.data.datasets[0].data = newData; chart.update()` to update data in-place without destroying/recreating — this is more performant and avoids the lifecycle issue entirely.

**Warning signs:**
- Console error: `"Canvas is already in use"`
- Changing the date range filter adds a second colored line on top of the existing one
- Memory usage grows each time the date range is changed (visible in Chrome DevTools Memory panel)

**Phase to address:**
Phase 1 (Chart Foundation) — Must be part of the initial chart rendering code. Retrofitting destroy/recreate logic after the fact is easy but discovering the bug requires multiple date range filter changes.

---

### Pitfall 4: Floating-Point Arithmetic Corrupts Running Balance Display

**What goes wrong:**
The running balance column is calculated by cumulatively summing costs across rows: `runningBalance += row.cost`. Because JavaScript uses IEEE 754 floating-point arithmetic, values like `0.1 + 0.2` do not equal `0.3` exactly — they equal `0.30000000000000004`. In a billing context, a running balance that shows `$12.3000000000001` or `$0.0000000000003` instead of `$0.00` destroys user trust and looks like a billing error.

**Why it happens:**
Decimal fractions (like $0.001 cost per click) cannot be represented exactly in binary floating point. Errors are tiny per operation but accumulate across 30+ rows of daily totals. The issue is invisible in unit tests unless the test specifically checks for exact string equality after formatting.

**How to avoid:**
Two options:
1. **Work in integer micro-cents**: Multiply all cost values by 100000 (or whatever the precision requires), sum as integers, divide only at display time.
2. **Round after each addition**: `runningBalance = Math.round((runningBalance + row.cost) * 10000) / 10000` — round to 4 decimal places after each step, not just at display time.
3. **Use `toFixed()` only for display**: Always format displayed values with `Number(value).toFixed(4)` or similar — never display raw float to user.

The preferred approach for this project: round to the API's precision after each step (if API returns 6 decimal places, work at 6 decimal places) and use `toFixed()` at render time only.

**Warning signs:**
- Balance column shows trailing zeros like `$0.000001` when it should show `$0.00`
- Final running balance for a period doesn't match the "total cost" summary card
- Any balance value shows more than 4 decimal places when rendered without explicit formatting

**Phase to address:**
Phase 1 (Data Layer) — Must be addressed when writing the table rendering logic. It will not be caught during development if testing with round numbers like $0.10 or $1.00.

---

### Pitfall 5: Date Range Filter Timezone Mismatch Causes Wrong Day Boundaries

**What goes wrong:**
The date range filter uses HTML `<input type="date">` which returns a date string in `YYYY-MM-DD` format interpreted in the browser's local timezone. The external API likely uses UTC timestamps. When a user in UTC-5 selects "today" (e.g., `2026-02-22`), the API query with `start_date=2026-02-22` may be interpreted as `2026-02-22T00:00:00Z` (UTC midnight) — which is 7pm the previous day in the user's timezone. This means the dashboard shows data for a different day than the user expects, and off-by-one-day errors appear in the stats table.

**Why it happens:**
`new Date('2026-02-22')` in JavaScript parses a date-only string as UTC midnight, not local midnight. API date parameters are almost always UTC. The gap between local browser time and UTC causes the boundary to shift by the user's UTC offset, most severely for users in UTC-12 to UTC+14 ranges.

**How to avoid:**
When building the API query parameters from the date input:
```javascript
// WRONG: new Date(dateInputValue) parses as UTC midnight
// RIGHT: explicitly parse as local date
function localDateToApiParam(dateString) {
    // dateString is "YYYY-MM-DD" from input[type=date]
    // Return as-is for the API — let the API handle timezone context
    // Never convert to a timestamp without knowing the API's expected timezone
    return dateString; // "2026-02-22" passed directly
}
```

Verify with the API documentation whether `start_date` and `end_date` parameters are interpreted as UTC or local time. If UTC: document this prominently and consider displaying a note like "Dates shown in UTC". If local: ensure the API accepts YYYY-MM-DD and doesn't require ISO 8601 timestamps.

**Warning signs:**
- Stats for "today" show data from yesterday (user is in a positive UTC offset)
- Total for a 30-day range shows 29 days or 31 days of data
- The first or last day in the table always shows 0 clicks even when clicks occurred

**Phase to address:**
Phase 1 (Data Layer) — Must be verified against actual API behavior on day 1. Write a test that fetches data for a known date and confirms the count matches expectations.

---

### Pitfall 6: Mocked Clicks/QR Split Ratio Presented as Real Data Without Labeling

**What goes wrong:**
The external API returns only `totalHits` — it does not split clicks from QR scans at the per-day level. If the dashboard shows a chart with two stacked areas ("Clicks" and "QR Scans"), and the split is mocked using a static ratio (e.g., 70% clicks / 30% QR), this will be visually indistinguishable from real data. Users may make business decisions (e.g., "my QR campaign is outperforming expectations") based on fabricated data. If the real split ratio later becomes available from the API, the historical mocked data cannot be reconciled.

**Why it happens:**
Dashboard features are scoped before the underlying data is available. The shortcut of applying a fixed ratio to a known total looks correct on screen and passes visual QA.

**How to avoid:**
Two acceptable approaches:
1. **Show only `totalHits` on the chart** — one area, labeled "Total hits". No split. Label the chart clearly as "Total Clicks (QR breakdown not available)".
2. **Show stacked areas but label them clearly as estimated** — add a visible disclaimer: "QR/Click split is estimated based on historical ratios. Actual split not available at daily granularity."

Never present the mocked split as if it were real API data. Add a `// TODO: Replace with real API split when available` comment in the code and a task card on the board.

**Warning signs:**
- A constant QR percentage (e.g., exactly 30%) across all 30 days regardless of actual campaigns
- No difference in the QR/click ratio even on days when no QR codes were distributed
- Code comments like `// assume 70/30 split` without a visible disclaimer to the user

**Phase to address:**
Phase 2 (Chart Rendering) — Decision must be made at design time before any chart rendering code is written. Changing from mocked split to real split later requires a data model change.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Using a static click/QR split ratio from totalHits | Allows stacked chart without API changes | Historical mocked data cannot be reconciled when real data becomes available; users may trust fabricated percentages | Only with explicit user-facing disclaimer |
| Fetching fresh data from external API on every dashboard load (no transient cache) | Always current | External API latency (100-500ms) on every page visit; if external API is down, dashboard breaks | Never — use 5-15 minute WordPress transient cache for usage data |
| Using `type: 'category'` scale instead of `type: 'time'` for the X-axis | No date adapter required, simpler setup | Cannot handle sparse data (gaps in days with zero hits) gracefully; labels must be pre-generated | Acceptable for MVP if date adapter adds significant complexity |
| Calculating running balance as raw float accumulation without rounding | Simpler code | Floating point drift causes cents-off errors in balance column, especially with many rows | Never for financial data |
| Inline `wp_remote_get()` call inside AJAX handler (no caching) | Direct, simple | Each dashboard load triggers a synchronous HTTP call to the external API, adding 200-500ms to every page load; external API downtime breaks the dashboard | Never in production — always wrap in transient |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| External usage API via WordPress AJAX proxy | Not caching the API response — every dashboard page load triggers a live HTTP request | Cache via `set_transient('tp_usage_{uid}_{start}_{end}', $data, 5 * MINUTE_IN_SECONDS)` with a user-scoped cache key |
| WordPress AJAX nonce + billing data | Using nonce alone as access control ("if nonce passes, return data") | Nonce prevents CSRF but does not authorize. Always pair with `is_user_logged_in()` + ownership check: verify the `uid` in the request matches `get_current_user_id()` |
| Chart.js + date adapter CDN | Registering Chart.js in `wp_enqueue_scripts` but forgetting the adapter | The adapter script must be enqueued as a dependency of the chart initialization script: `wp_enqueue_script('tp-billing-chart', ..., ['chartjs-adapter'], ...)` |
| `input[type=date]` default value for "last 30 days" | Using `new Date()` to compute the start date — result is browser-timezone-dependent | Use `new Date().toISOString().slice(0, 10)` for UTC today or document the timezone assumption clearly |
| Running balance from paginated API data | Calculating running balance only from the current page's rows | Running balance must span the full date range, not just the visible table page. Fetch all rows for the date range, calculate balance from the full set, then paginate the display |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Fetching full 30-day usage dataset on every filter change (no debounce) | Rapid date picker interactions fire multiple concurrent AJAX requests; last response wins but may not be the last request sent | Debounce date range filter changes by 300ms; cancel in-flight requests using an AbortController or a request serial number check | Noticeable immediately when user types into date fields; race condition appears on slow connections |
| Rendering the full stats table (30 rows) via jQuery `.append()` in a loop | Each `.append()` causes a DOM reflow; 30 separate appends = 30 reflows | Build the complete HTML string first, then do a single `.html(tableHtml)` call | With 30+ rows, visible render flash on every filter change |
| Loading Chart.js (~200KB) on all plugin pages | Adds 200KB parse cost to pages that don't show the billing dashboard | Use `wp_enqueue_script()` only on the page that renders the billing shortcode; check `has_shortcode()` or use a specific CSS class check | On shared hosting, noticeable on mobile; wastes bandwidth for all non-billing dashboard page views |
| Re-fetching usage data when only the chart display changes (e.g., toggling between chart types) | Unnecessary API calls for UI-only changes | Separate data fetching from rendering; cache the last API response in a JS variable and re-render from cache for UI-only changes | Any time a display toggle causes an AJAX request |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| AJAX endpoint returns billing data without ownership verification | User A can see User B's costs and usage by sending `uid=B` in the request | On every AJAX call: `$uid = TP_Link_Shortener::get_user_id()` (server-side, not from POST data). Never accept `uid` as a POST parameter for billing data retrieval |
| Nonce verification only (no `is_user_logged_in()`) on billing AJAX handler | A cached page with a valid nonce could allow unauthenticated access in some cache configurations | Always double-check: `check_ajax_referer('...', 'nonce')` AND `if (!is_user_logged_in()) { wp_send_json_error(..., 401); return; }` — both checks, in order |
| Displaying raw API error messages to the user | API errors may expose internal endpoint URLs, authentication tokens, or server structure | Catch all exceptions in the PHP AJAX handler and return only sanitized user-facing messages; log the detailed error server-side |
| No rate limiting on the billing data AJAX endpoint | An authenticated user could hammer the endpoint 100x/second, causing repeated external API calls and potential cost | Add a transient-based rate limit: refuse requests more frequent than once per 30 seconds per user for the same date range |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Showing "Loading..." with no skeleton state for chart and table | Blank white area for 1-3 seconds while API data loads; user doesn't know if page is broken | Show a skeleton chart (grey box with shimmer) and skeleton table rows during load, matching the final layout dimensions |
| Date range defaulting to "today only" instead of "last 30 days" | User sees one row of data and thinks the feature is broken or that they have no history | Default to last 30 days on first load; persist the user's last-used date range in `localStorage` |
| Displaying balance as `$0.000001` (raw float) instead of `$0.00` | Looks like a billing bug; erodes trust | Always format with `toFixed(2)` for dollar display; use `toFixed(4)` if showing cost-per-click precision |
| "No data" empty state with no explanation for the date range | User doesn't know if they have no clicks yet or if the API failed | Distinguish between "zero clicks in this period" (with the date range shown) and "data failed to load" (with a retry button) |
| Date range filter accepting future dates | User can select a range ending in the future; API returns 0 for future days, which looks like a bug | Set `max` attribute on the end date input to today's date: `endDateInput.max = new Date().toISOString().slice(0, 10)` |

---

## "Looks Done But Isn't" Checklist

- [ ] **Running balance column:** Verify the last row's balance equals the sum of all `cost` values — if there is floating-point drift, the totals will not match
- [ ] **Date range "last 30 days":** Verify the default 30-day range produces exactly 30 rows (not 29 or 31) — off-by-one in the date calculation is common
- [ ] **Chart destroy on re-render:** Verify changing the date range 3 times in a row does NOT produce the console error `"Canvas is already in use"` and does NOT stack multiple datasets
- [ ] **Chart adapter loaded:** Verify the time-scale X-axis renders date labels (not numbers or "Invalid Date") — open console and confirm no date adapter errors
- [ ] **Chart in flex container:** Verify the chart does not grow taller when the browser window is resized 5 times rapidly
- [ ] **User data isolation:** Verify that changing `uid` in the browser's DevTools Network tab to a different user ID returns an error, not another user's billing data
- [ ] **Stale data cache:** Verify that after clicking a link 5 times, refreshing the billing dashboard within 1 minute still shows cached (not live) data, and after 15 minutes shows updated data
- [ ] **Zero-click days:** Verify that days with zero clicks appear as `0` in the table (not missing rows) and appear as `0` on the chart (not a gap in the line)
- [ ] **Empty state messaging:** Verify that a brand-new account with no links shows a clear "No usage data yet" message rather than a broken chart or empty table with no explanation
- [ ] **Balance with mocked QR split:** If using a mocked split, verify the user-facing disclaimer is present and the legend or tooltip clearly labels the data as estimated

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Chart grows indefinitely (flex container) | LOW | Add `min-width: 0` to flex parent and `position: relative; height: [explicit]px; overflow: hidden` to chart wrapper — CSS-only change, no JS needed |
| Missing date adapter (blank chart) | LOW | Add one `wp_enqueue_script` call for the adapter; takes 10 minutes to deploy |
| Chart not destroyed before re-render | LOW | Store chart instance in a module-scoped variable; add `if (chart) chart.destroy()` before `new Chart()` — 3 lines of code |
| Floating-point balance drift | LOW-MEDIUM | Add `Math.round(value * 10000) / 10000` after each accumulation step; requires re-testing the entire table with non-round numbers |
| Timezone date mismatch | MEDIUM | Requires verifying the API contract and potentially adjusting how date strings are built and displayed; may require showing a "UTC" label on the dashboard |
| User data not isolated (security) | HIGH | Requires immediate deployment of a server-side uid enforcement fix; audit logs should be checked for unauthorized access before patching |
| Mocked split accepted as real | MEDIUM | Cannot retroactively correct historical mocked data; requires adding a visible disclaimer and a code-level TODO; when real data is available, a migration plan is needed |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Chart flex resize loop | Phase 1: Chart Foundation | Resize browser window 10 times rapidly — chart height stays constant |
| Missing date adapter | Phase 1: Chart Foundation | Open browser console — zero errors when chart renders |
| Chart not destroyed on re-render | Phase 1: Chart Foundation | Change date range 5 times — no `"Canvas is already in use"` errors |
| Floating-point balance drift | Phase 1: Data Layer | Sum of all `cost` column values equals the final `runningBalance` value (test with non-round numbers) |
| Date range timezone mismatch | Phase 1: Data Layer | Fetch "today" data and verify the row count matches actual activity for local calendar day |
| Mocked QR/click split | Phase 2: Chart Rendering | User-facing disclaimer present; code comment with TODO present; single-area fallback available |
| Missing data ownership check | Phase 1: API Proxy | Send a request with a different user's uid — receive 401/403, not data |
| No transient cache on AJAX proxy | Phase 1: API Proxy | Check browser DevTools — second dashboard load within 5 minutes shows same data without triggering external API call |
| Stale mocked data baked into localStorage | Any phase | Clear localStorage — dashboard still loads correctly with API data |
| Balance displayed as raw float | Phase 2: Table Rendering | Inspect every value in the balance column with non-round input data — all show max 2 decimal places |

---

## Sources

- [Chart.js Responsive Canvas Grows Indefinitely - GitHub Issue #5805](https://github.com/chartjs/Chart.js/issues/5805) — HIGH confidence, official repo
- [Chart.js Resizing in Flex Containers - GitHub Issue #9001](https://github.com/chartjs/Chart.js/issues/9001) — HIGH confidence, official repo
- [Chart.js Responsive Configuration - Official Docs](https://www.chartjs.org/docs/latest/configuration/responsive.html) — HIGH confidence
- [Chart.js Time Cartesian Axis - Official Docs](https://www.chartjs.org/docs/latest/axes/cartesian/time.html) — HIGH confidence
- [chartjs-adapter-date-fns - npm](https://www.npmjs.com/package/chartjs-adapter-date-fns) — HIGH confidence
- [Chart.js API - destroy() method](https://www.chartjs.org/docs/latest/developers/api.html) — HIGH confidence
- [Floats Don't Work For Storing Cents - Modern Treasury](https://www.moderntreasury.com/journal/floats-dont-work-for-storing-cents) — HIGH confidence, financial engineering blog
- [Currency Calculations in JavaScript - Honeybadger Developer Blog](https://www.honeybadger.io/blog/currency-money-calculations-in-javascript/) — MEDIUM confidence, verified against multiple sources
- [WordPress Nonces - Official Developer Documentation](https://developer.wordpress.org/apis/security/nonces/) — HIGH confidence
- [Understand and use WordPress nonces properly - WordPress Developer Blog 2023](https://developer.wordpress.org/news/2023/08/understand-and-use-wordpress-nonces-properly/) — HIGH confidence
- [Broken Access Control in WordPress - Patchstack Academy](https://patchstack.com/academy/wordpress/vulnerabilities/broken-access-control/) — HIGH confidence, security-focused WordPress source
- [DatePickers working with Timezones - Medium](https://nezspencer.medium.com/datepickers-working-with-timezones-c0e342904aa4) — MEDIUM confidence, verified against Grafana and OpenSearch issues
- [WordPress Transients API - Official Handbook](https://developer.wordpress.org/apis/transients/) — HIGH confidence
- [How to consume external APIs with wp_remote_get and cache in transients - YourWPweb 2025](https://yourwpweb.com/2025/09/26/how-to-consume-external-apis-with-wp_remote_get-and-cache-in-transients-in-wordpress/) — MEDIUM confidence
- [Bridging Data Gaps in Time-Series Charts - chartjs-plugin-fill-gaps-zero - Medium](https://medium.com/nethive-engineering/bridging-data-gaps-in-time-series-line-charts-e853cffc623d) — MEDIUM confidence

---
*Pitfalls research for: Billing/usage dashboard — WordPress link shortener plugin (v2.0 milestone)*
*Researched: 2026-02-22*
