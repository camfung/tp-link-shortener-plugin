# Feature Research

**Domain:** SaaS billing/usage dashboard (WordPress plugin, link shortener service)
**Researched:** 2026-02-22
**Confidence:** HIGH (API is known, design reference exists in TP-59 ticket, competitor patterns are well-established)

---

## Context

This is research for the **v2.0 Usage Dashboard milestone** — a standalone `[tp_usage_dashboard]` shortcode showing users their daily link activity, costs, and running account balance. This is NOT the link management dashboard (already built as `[tp_client_links]`). The research focuses specifically on billing/usage dashboard UX patterns — what users expect from a "here is what you spent and how your balance is changing" view.

**API reality (from API_REFERENCE.md):**
- `GET /user-activity-summary/{uid}` — returns `[{ date, totalHits, hitCost, balance }]` per day
- `GET /user-activity-summary/{uid}/by-source` — returns hits/cost per traffic source per day (QR is a traffic source)
- No dedicated clicks vs QR breakdown at the summary level; QR is identifiable via `by-source` where `source_name === "QR Code"`
- Balance is a running cumulative sum already computed server-side
- Date range via `?start_date=&end_date=` query params supported on all three endpoints

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features that a billing/usage dashboard must have. Missing any of these makes the dashboard feel incomplete or untrustworthy.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| **Summary stats (totals strip)** | Every billing dashboard (AWS Cost Dashboard, Bitly Analytics, Stripe, GoHighLevel Wallet) leads with aggregate numbers at the top. Users want to know "what happened in this period" before drilling into daily detail. Shows: total hits, total cost, current balance. | LOW | Derived from summing `totalHits` and `hitCost` across all records in date range. Balance is the last record's `balance` field. Three stat cards/pills at top of page. |
| **Time series chart** | Charts are expected in every analytics product. The TP-59 design reference specifies an area chart. Bitly's default view is an overtime clicks+scans chart. AWS, Stripe — all lead with a graph. | MEDIUM | Use Chart.js (already loaded). Area chart, yellow=clicks, green=QR. The clicks/QR split requires using `by-source` endpoint or mocking (QR hits from by-source, regular = totalHits - qrHits). |
| **Date range filter (default last 30 days)** | Universal dashboard pattern. AWS defaults to last month. Bitly defaults to 30 days. GoHighLevel defaults to current month. Users expect to be able to change what period they see. | LOW | Two `<input type="date">` inputs with "Apply" button. Default: today minus 30 days to today. Wire to `start_date`/`end_date` query params. |
| **Daily stats table** | The TP-59 ticket design and all comparable billing UIs (AWS Cost Explorer table, Stripe daily breakdown) include a row-per-day table below the chart. This is the drill-down that gives the chart credibility. | LOW | Columns: Date, Clicks, QR Scans, Total Hits, Cost, Balance. Sorted newest-first. |
| **Running balance column in table** | This is the whole point of the billing dashboard from the user's perspective — can they see how their credit is depleting. GoHighLevel, digital wallet SaaS products all show this as a running total. | LOW | API already returns `balance` (cumulative). Render it in the Balance column formatted as currency. Color-code: green if positive, red if negative/zero. |
| **Cost formatted as currency** | `hitCost` comes from the API as a negative float (e.g., `-0.50`). Users expect to see "$0.50" not "-0.5". Every billing product formats costs positively with $ sign. | LOW | Display `Math.abs(hitCost).toFixed(2)` with `$` prefix. Show total period cost the same way. |
| **Loading skeleton / spinner** | Existing plugin uses skeleton loading throughout (`tp-skeleton`). Users expect a visual feedback that data is loading, not a blank page flash. | LOW | Reuse existing skeleton pattern from `dashboard.js` / `client-links.js`. Three skeleton stat cards + skeleton rows in the table. |
| **Empty state** | If user has no usage in the date range, showing nothing is confusing. All existing plugin shortcodes handle empty state explicitly. | LOW | "No activity found for this period." message with a date picker prompt to try a wider range. |
| **Error state** | API can fail. Showing a blank page or JS error when the API is down destroys user trust. | LOW | Reuse existing error state pattern. "Failed to load usage data. Try again." with retry button. |
| **Auth gate (logged-in only)** | The usage dashboard is per-user billing data — completely private. Unauthenticated access must show a login prompt, not an error. This is consistent with both existing shortcodes. | LOW | Reuse existing `tpDashboard.userId` / `tpClientLinks.userId` pattern from PHP shortcode class. |

---

### Differentiators (Competitive Advantage)

Features that would make this dashboard noticeably better than a bare-bones implementation. Not required for v2.0 but worth noting for roadmap.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| **Clicks vs QR split in chart (two area series)** | The TP-59 design reference explicitly shows two separate stacked area series: yellow for regular clicks, green for QR scans. Bitly separates these too. Users who use QR codes want to see which channel is driving traffic. | MEDIUM | Requires calling `by-source` endpoint in parallel with summary endpoint, then subtracting QR hits from totalHits to get regular clicks. Will need mocking if by-source data is sparse. The design reference makes this a required differentiator. |
| **Preset date range quick-select buttons** | AWS, Stripe, Grafana all offer "Last 7d / 30d / 90d" quick buttons next to the date picker. Reduces friction for the most common filter operations. Bitly's metric time period presets (7 days, 14 days, 30 days) are explicitly called out in their analytics docs. | LOW | Three buttons: "7 Days", "30 Days", "90 Days". Active state on the current selection. Clicking sets the date inputs and fetches. One-line JS per button. |
| **Hover tooltips on chart data points** | Chart.js tooltips on hover are already configured in existing chart usage. Showing exact date + clicks + QR + cost on hover is expected in any serious analytics chart. | LOW | Chart.js tooltip configuration already exists in the codebase. Configure `tooltip.callbacks` to show all three values. |
| **Balance color coding** | If balance is positive (credits), green. If negative or near zero, amber/red. This is how GoHighLevel, digital wallet products, and prepaid SaaS show account health at a glance. | LOW | One CSS class swap based on balance value. `.tp-balance-positive` / `.tp-balance-warning` / `.tp-balance-danger`. Thresholds: >0 = green, 0 = amber, <0 = red. |
| **Period totals row at table bottom** | Stripe, AWS, and accounting tools all show a "Total" row at the bottom of period tables. Eliminates the need for users to mentally sum the column. | LOW | Sum `totalHits`, `hitCost` across all rows. Fixed footer row in the table. |
| **"Other Services" charges column (future)** | TP-59 ticket mentions domain renewals, wallet top-ups as additional charge types. Having an "Other" column placeholder now makes it easier to add later without redesigning the table. | LOW | Add column header with placeholder dash values. No data yet but establishes the schema visually. **Only if this doesn't add implementation complexity.** |

---

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem useful but would complicate v2.0 without proportional value.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| **Real-time / auto-refresh** | Usage dashboards feel more "alive" if they update automatically. | For a daily stats dashboard, data only changes once per day (when new hits are logged). Polling every 30s adds API load with zero benefit. Users reading their daily stats are not watching for live changes. | Refresh button in controls bar. Refresh once on page load. That is sufficient for daily granularity. |
| **CSV/PDF export** | Power users always ask for export. | Scope explosion: PDF generation in WordPress is a rabbit hole (DOMPDF, wkhtmltopdf, etc.). CSV is simpler but still a distinct feature requiring server-side work. Neither is in the TP-59 scope. | Defer to v3.0. Note the API's date range filter makes it easy to add later — the data is already structured. |
| **Per-link breakdown in this dashboard** | Users want to see which link drove costs. | The billing dashboard is about daily totals and balance. Per-link breakdown is its own view — the `by-link` API endpoint exists for this. Mixing it in creates a hybrid dashboard with no clear purpose. | The existing `[tp_client_links]` dashboard already shows per-link usage. Cross-link via a "See link details" anchor on each row. |
| **Wallet top-up button** | Users with low balance would naturally want to top up from the dashboard. | Requires payment processing integration (Stripe, WooCommerce, etc.) — completely out of scope. Also, the TeraWallet API is not yet integrated. | Show a "Contact to top up" message or link when balance is low/negative. Mark as v3.0+ feature. |
| **Sortable table columns** | Users might want to sort by cost or clicks. | For a daily stats table (max 90 rows for 90-day range), sorting is rarely needed. Adds implementation complexity. The natural sort is newest-first by date, which is the billing period most users read. | Sort by date descending (newest first) by default. No column sorting needed for v2.0. |
| **Pagination on stats table** | If data spans months, the table gets long. | Even 90 days of data is 90 rows — manageable without pagination. Pagination adds complexity and breaks the "see the whole period" mental model users have for billing statements. | Show all rows for the selected date range (max 90 rows for 90d default). No pagination needed. |
| **Second table for domains/tpKeys/semaphores** | TP-59 ticket originally mentioned this. | This is not billing data — it is link management data. It belongs in the `[tp_client_links]` dashboard. Adding it here mixes concerns and inflates scope. | Explicitly out of scope per PROJECT.md. Link to the client links dashboard if users need domain info. |

---

## Feature Dependencies

```
Summary Stats (totals strip)
    └──requires──> Date Range Filter (determines which records to sum)
                       └──requires──> API call to /user-activity-summary/{uid}

Area Chart (time series)
    └──requires──> Date Range Filter (same API call)
    └──requires(for split)──> /user-activity-summary/{uid}/by-source (QR data)
    └──enhances──> Hover tooltips on data points (Chart.js callback config)

Daily Stats Table
    └──requires──> Date Range Filter (same API call, same data)
    └──enhances──> Running balance column (balance field from API response)
    └──enhances──> Period totals row (summation of table data already in memory)
    └──enhances──> Balance color coding (CSS class on balance value)

Clicks vs QR split (chart differentiator)
    └──requires──> Area Chart (is part of chart config, not standalone)
    └──requires──> /user-activity-summary/{uid}/by-source (parallel API call)
    └──requires(fallback)──> Mock split logic when by-source data is absent

Auth Gate
    └──required by──> All features (nothing renders without uid)

Loading Skeleton
    └──required before──> Summary Stats, Chart, Table (shown while data loads)

Empty State
    └──conflicts with──> Loading Skeleton (mutually exclusive states)
    └──conflicts with──> Error State (mutually exclusive states)

Preset Date Buttons (7d/30d/90d)
    └──enhances──> Date Range Filter (shortcuts the date input interaction)

"Other Services" column
    └──depends on (future)──> Payment Records API and wallet top-up charges
```

### Dependency Notes

- **Area Chart requires Date Range Filter:** The chart shows data for the selected period. Date filter must be implemented and working before chart rendering is meaningful.
- **Clicks vs QR split requires by-source endpoint:** The `GET /user-activity-summary/{uid}` only returns `totalHits`. To show separate QR vs click series, we must also call `/by-source` and extract `source_name === "QR Code"` rows. If by-source returns no data for a user, fall back to totalHits (mock 70/30 split per PROJECT.md decision, or show as single series).
- **Balance color coding enhances the table:** The balance column already needs to exist before adding color coding. Color coding is additive CSS, not a separate feature.
- **Period totals row enhances the table:** Requires the table to be built first. Totals row is computed from the in-memory data already loaded for the table — zero additional API calls.

---

## MVP Definition

### Launch With (v2.0)

Minimum viable product — sufficient to let users see their usage costs and balance, which is the core value proposition from PROJECT.md.

- [x] **Auth gate** — Show login prompt for unauthenticated users. Nothing renders without a user.
- [x] **Date range filter** — Two date inputs defaulting to last 30 days. Apply button fetches data. Required before any data-dependent feature can work.
- [x] **Summary stats strip** — Three stat cards: Total Hits, Total Cost (period), Current Balance. These are the three numbers users care most about at a glance.
- [x] **Area chart** — Time series showing daily activity. Ideally two series (clicks + QR), falling back to single series (totalHits) if by-source data is absent. Matches TP-59 design reference.
- [x] **Daily stats table** — Columns: Date, Clicks, QR Scans, Total Hits, Cost, Balance. Sorted newest-first.
- [x] **Loading skeleton** — Reuse existing plugin skeleton pattern. Prevents blank flash.
- [x] **Empty state** — "No activity for this period" with date picker prompt.
- [x] **Error state** — "Failed to load" with retry button.
- [x] **Cost formatted as currency** — `$0.50` not `-0.5`. Non-negotiable for billing UI.
- [x] **Balance color coding** — Green/amber/red based on balance value. Low-effort, high trust signal.

### Add After Validation (v2.x)

Features to add once the core dashboard is deployed and used.

- [ ] **Preset date buttons (7d/30d/90d)** — Add these quick-select buttons once the date filter is proven working. Reduces friction for return users. One sprint of work.
- [ ] **Period totals row** — Add after table is live. Sum the columns and show a fixed footer row. Eliminates user mental math.
- [ ] **Hover chart tooltips** — Chart.js tooltip config. Add after chart is proven rendering correctly.
- [ ] **Clicks vs QR split (if mocking is reliable)** — If the by-source data is consistently returning QR data, refine the split. If mocking is needed, validate the mock proportion with real users first.

### Future Consideration (v2+)

Features to defer until product-market fit is established for the billing dashboard.

- [ ] **Other Services column** — Domain renewals, wallet top-ups. Requires Payment Records API integration and more data from backend. v3.0+ after API supports it.
- [ ] **Wallet top-up / payment flow** — Full payment integration. Requires Stripe/WooCommerce. Out of scope until revenue model is confirmed.
- [ ] **CSV export** — Useful but not essential. Defer until users request it.
- [ ] **Per-link cost breakdown table** — Requires `by-link` API, separate UI section. Separate milestone.
- [ ] **Domains/tpKeys info table** — Belongs in client-links dashboard, not billing. Explicitly deferred per PROJECT.md.

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Auth gate | HIGH | LOW | P1 |
| Date range filter (30d default) | HIGH | LOW | P1 |
| Summary stats strip | HIGH | LOW | P1 |
| Daily stats table | HIGH | LOW | P1 |
| Cost formatted as currency | HIGH | LOW | P1 |
| Loading skeleton | MEDIUM | LOW | P1 |
| Empty state | MEDIUM | LOW | P1 |
| Error state | MEDIUM | LOW | P1 |
| Area chart (totalHits single series) | HIGH | MEDIUM | P1 |
| Balance color coding | HIGH | LOW | P1 |
| Clicks vs QR split (two-series chart) | MEDIUM | MEDIUM | P2 |
| Hover chart tooltips | MEDIUM | LOW | P2 |
| Preset date buttons (7d/30d/90d) | MEDIUM | LOW | P2 |
| Period totals row in table | MEDIUM | LOW | P2 |
| Other Services column (placeholder) | LOW | LOW | P2 |
| Wallet top-up button | LOW | HIGH | P3 |
| CSV export | LOW | MEDIUM | P3 |
| Per-link cost breakdown | MEDIUM | HIGH | P3 |
| Real-time/auto-refresh | LOW | MEDIUM | P3 |
| Sortable columns | LOW | MEDIUM | P3 |

**Priority key:**
- P1: Must have for v2.0 launch
- P2: Should have, add in v2.x after validation
- P3: Nice to have, future milestone

---

## Competitor Feature Analysis

This dashboard is most analogous to GoHighLevel's SaaS Wallet, AWS Cost Dashboard, and Bitly Analytics — all are "how much did I use and what is my balance" views for a metered/credit-based service.

| Feature | GoHighLevel Wallet | AWS Cost Dashboard | Bitly Analytics | Our Approach |
|---------|--------------------|--------------------|-----------------|--------------|
| Summary stats at top | Yes — balance, usage, depletion rate | Yes — month-to-date cost | Yes — total clicks + scans | Three stat cards: total hits, period cost, current balance |
| Time series chart | Not prominent | Yes — area chart, daily granularity | Yes — area chart, clicks + scans overtime | Area chart, yellow=clicks, green=QR, daily points |
| Per-day table | Yes — transaction history | Yes — by-day cost table | No — link-level not day-level | Daily rows: date, clicks, QR, hits, cost, balance |
| Running balance | Yes — wallet balance per transaction | No — cumulative cost from zero | No — no wallet concept | Balance column in table, color-coded |
| Date range filter | Preset periods | Last 1m, 3m, 6m, custom | 7d, 14d preset | Date inputs + 7d/30d/90d presets |
| Export | Yes — CSV | Yes — CSV, PDF | Yes — download chart | Deferred to v3.0 |
| Top-up / payment | Yes — "Add funds" button | N/A | N/A | Deferred to v3.0 |

---

## API Constraints That Shape Features

These are hard constraints from the actual API (not assumptions) that affect what can be built:

| Constraint | Impact | Decision |
|------------|--------|----------|
| `totalHits` does not separate clicks vs QR at summary level | Chart cannot show two series from summary endpoint alone | Call `/by-source` in parallel; extract `source_name === "QR Code"` for QR series. Regular = totalHits - qrHits. If by-source has no data, show totalHits as single series. |
| `balance` is a running cumulative (already computed) | No need to compute running balance client-side | Display `balance` field directly. The last record's balance = current balance. Use for summary stat card. |
| `hitCost` is negative float | Confusing if shown raw | Display as `$` + `Math.abs(hitCost).toFixed(2)`. Always positive display. |
| No pagination on activity summary API | All dates returned in one response | Simple: no pagination needed in UI. Load everything, render everything. Fine for 30-90 day ranges. |
| Date range is optional; without it, all historical data returns | Default to 30 days to keep response size manageable | Always pass `start_date` and `end_date`. Default = last 30 days. |
| No wallet/payment transaction endpoint yet | Cannot show top-up history | "Other Services" and payment rows are deferred. Note in API requirements doc that this endpoint is needed for v3.0. |

---

## Sources

- **API_REFERENCE.md (project file)** — HIGH confidence. Authoritative source for what the backend actually returns. Directly informs which features are buildable vs require API changes.
- **PROJECT.md (.planning/)** — HIGH confidence. Defines the v2.0 milestone scope, confirmed out-of-scope items, and tech stack constraints.
- **ticketactionplan.md (project file)** — HIGH confidence. Shows the original TP-59 intent (daily stats + wallet) and what was already determined to be out of scope.
- **GoHighLevel SaaS Wallet documentation** — MEDIUM confidence. [https://help.gohighlevel.com/support/solutions/articles/48001207115-saas-wallet-credit-management](https://help.gohighlevel.com/support/solutions/articles/48001207115-saas-wallet-credit-management). Most analogous product for billing+balance dashboard pattern.
- **Bitly Analytics (search results)** — MEDIUM confidence. Analytics feature list (clicks, QR scans, time-series, date presets) verified via search results from support.bitly.com. Area chart with two series (clicks + scans) is confirmed Bitly pattern.
- **AWS Cost Dashboard / AWS Marketplace usage dashboard** — MEDIUM confidence. Confirmed daily granularity, date filtering, running cost table as standard billing dashboard patterns.
- **SaaS billing dashboard UX patterns (WebSearch 2025/2026)** — LOW-MEDIUM confidence. General patterns confirmed across multiple search results (summary stats at top, chart, table, date filter). Not directly verified against official docs.
- **colorwhistle.com SaaS Credits System Guide 2026** — LOW confidence. Secondary source for wallet/credit balance UX expectations.

---

*Feature research for: SaaS billing/usage dashboard (tp-link-shortener-plugin v2.0 Usage Dashboard)*
*Researched: 2026-02-22*
