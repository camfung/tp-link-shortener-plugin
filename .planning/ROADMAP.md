# Roadmap: Traffic Portal

## Milestones

- [ ] **v1.0 Mobile Responsive** - Phases 1-4 (paused)
- [ ] **v2.0 Usage Dashboard** - Phases 5-8 (current)
- [ ] **v2.2 TerrWallet Integration** - Phases 9-13 (planned)

## Phases

<details>
<summary>v1.0 Mobile Responsive (Phases 1-4) - PAUSED</summary>

- [ ] **Phase 1: CSS Foundation** - Standardize breakpoints, clean up specificity, and convert hover patterns for touch devices
- [ ] **Phase 2: Forms and Modals** - Make link creation/edit forms and all modal dialogs fully usable on phone screens
- [ ] **Phase 3: Table Cards and Controls** - Convert link tables to card layout and make pagination, date pickers, and action buttons touch-friendly
- [ ] **Phase 4: Chart Collapse** - Hide performance chart by default on mobile with expand toggle and summary stats bar

</details>

<details>
<summary>v2.0 Usage Dashboard (Phases 5-8)</summary>

- [ ] **Phase 5: Shortcode Foundation and API Proxy** - Register the shortcode, render the page skeleton, gate on authentication, and wire the AJAX proxy to the external API with caching
- [x] **Phase 6: Stats Table and Summary Strip** - Render the daily stats table with currency formatting, balance precision, mock click/QR split, and summary stats cards using default 30-day data (completed 2026-02-23)
- [ ] **Phase 7: Chart Rendering** - Display the area chart with two series (clicks/QR), data point markers, proper canvas lifecycle, and flex container stability
- [ ] **Phase 8: Date Filtering and API Doc** - Add interactive date range selection with presets and validation, and document the backend API changes needed for real data

</details>

### v2.2 TerrWallet Integration (Phases 9-13)

**Milestone Goal:** Integrate the TerrWallet (WooCommerce Wallet) API into the usage dashboard to show wallet credit transactions as an "Other Services" column alongside daily usage data.

**Phase Numbering:**
- Integer phases (9, 10, 11, 12, 13): Planned milestone work
- Decimal phases (10.1, 10.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 9: Wallet Client** - PHP client that fetches wallet credit transactions via direct PHP calls or rest_do_request(), with pagination and error handling
- [x] **Phase 10: Merge Adapter** - Pure data transformation that aggregates wallet transactions by date and merges them into usage day records via full outer join (completed 2026-03-10)
- [x] **Phase 11: Backend Integration** - Wire wallet client and merge adapter into the existing AJAX handler with non-fatal error handling for graceful degradation (completed 2026-03-10)
- [x] **Phase 12: Dashboard UI** - Add Other Services column to table with tooltip descriptions, and Other Services total card to summary strip (completed 2026-03-10)
- [ ] **Phase 13: E2E Tests and Validation** - Integration tests, unit tests for merge edge cases, and E2E tests verifying the full feature with real wallet data

## Phase Details

<details>
<summary>v1.0 Phase Details (Phases 1-4) - PAUSED</summary>

### Phase 1: CSS Foundation
**Goal**: Standardize breakpoints, clean up specificity, and convert hover patterns for touch devices
**Depends on**: Nothing
**Plans**: TBD

### Phase 2: Forms and Modals
**Goal**: Make link creation/edit forms and all modal dialogs fully usable on phone screens
**Depends on**: Phase 1
**Plans**: TBD

### Phase 3: Table Cards and Controls
**Goal**: Convert link tables to card layout and make pagination, date pickers, and action buttons touch-friendly
**Depends on**: Phase 2
**Plans**: TBD

### Phase 4: Chart Collapse
**Goal**: Hide performance chart by default on mobile with expand toggle and summary stats bar
**Depends on**: Phase 3
**Plans**: TBD

</details>

<details>
<summary>v2.0 Phase Details (Phases 5-8)</summary>

### Phase 5: Shortcode Foundation and API Proxy
**Goal**: A page with `[tp_usage_dashboard]` renders a complete HTML skeleton, blocks unauthenticated users, and can fetch real data from the external API through a secure WordPress AJAX proxy
**Depends on**: Nothing (first phase in v2.0 milestone)
**Requirements**: PAGE-01, PAGE-02, PAGE-03, DATA-01, DATA-02, DATA-03
**Success Criteria** (what must be TRUE):
  1. Visiting a WordPress page containing `[tp_usage_dashboard]` displays a dashboard page with a loading skeleton (chart placeholder, table placeholder, date inputs)
  2. An unauthenticated visitor sees a login prompt instead of the dashboard content
  3. The browser can call `admin-ajax.php?action=tp_get_usage_summary` and receive real API data as JSON -- the user ID is never accepted from client-side parameters
  4. Repeated page loads within 5 minutes return cached data (WordPress transient) without hitting the external API again
**Plans**: 3 plans

Plans:
- [ ] 05-01-PLAN.md -- Page Foundation (shortcode, template, CSS, plugin wiring, unit tests)
- [ ] 05-02-PLAN.md -- Data Pipeline (API client, AJAX handler, JS, unit/integration tests)
- [ ] 05-03-PLAN.md -- E2E Validation (Playwright/Python tests for all Phase 5 success criteria)

### Phase 6: Stats Table and Summary Strip
**Goal**: Users see their daily usage data in a formatted table with accurate currency values and running balance, plus summary stats cards, all loaded with the default 30-day date range
**Depends on**: Phase 5
**Requirements**: TABLE-01, TABLE-02, TABLE-03, TABLE-04, DATA-04, DATA-07, STATS-01
**Success Criteria** (what must be TRUE):
  1. The stats table displays rows with columns: date, clicks (estimated), QR scans (estimated), total hits, cost ($X.XX format), and running balance -- with the click/QR split clearly labeled as estimated
  2. The running balance column shows correct values without floating-point drift, even after 30+ rows of non-round cost values like $0.001
  3. When no usage data exists for the selected range, a clear "No usage data" message appears showing the date range queried
  4. Summary stats cards above the table show total hits, total cost, and current balance for the displayed period
  5. On first load, the dashboard shows the last 30 days of data without the user needing to select any dates
**Plans**: 2 plans

Plans:
- [ ] 06-01-PLAN.md -- Template and CSS
- [ ] 06-02-PLAN.md -- JavaScript rendering

### Phase 7: Chart Rendering
**Goal**: Users see an area chart visualizing their daily clicks and QR scans over time, matching the TP-59 design, with stable rendering across date range changes
**Depends on**: Phase 6
**Requirements**: CHART-01, CHART-02, CHART-03, CHART-04, CHART-05
**Success Criteria** (what must be TRUE):
  1. An area chart displays two stacked series -- yellow for clicks, green for QR scans -- matching the TP-59 design reference colors
  2. Each day on the chart has visible data point markers on the line
  3. Changing the date range re-renders the chart without "Canvas already in use" errors, even after 5+ consecutive changes
  4. Resizing the browser window does not cause the chart to enter an infinite resize loop -- the chart height remains stable
  5. The chart legend or a nearby disclaimer indicates that the click/QR breakdown is estimated
**Plans**: 1 plan

Plans:
- [ ] 07-01-PLAN.md -- Chart wrapper CSS fix + renderChart() function with lifecycle management

### Phase 8: Date Filtering and API Doc
**Goal**: Users can filter their usage data by custom date ranges or quick presets, and the API requirements for real click/QR split data are documented for the backend team
**Depends on**: Phase 7
**Requirements**: DATA-05, DATA-06, TABLE-05, DOC-01
**Success Criteria** (what must be TRUE):
  1. User can select custom start and end dates and click Apply to reload the table and chart with the filtered data
  2. Preset buttons (7d, 30d, 90d) update the date inputs and reload data with one click
  3. The end date input does not allow selecting a date beyond today
  4. An API requirements document exists specifying the backend changes needed for real clicks/QR split, other services data, and wallet transactions
**Plans**: 2 plans

Plans:
- [ ] 08-01-PLAN.md -- Date filtering (preset buttons, Apply handler, max date enforcement, validation)
- [ ] 08-02-PLAN.md -- API requirements document

</details>

### Phase 9: Wallet Client
**Goal**: The plugin can fetch wallet credit transactions for the current user from the TerrWallet API, handling authentication, pagination, and errors -- without any UI changes or modifications to existing code
**Depends on**: Phase 8 (v2.0 must be complete -- the usage dashboard must exist before extending it)
**Requirements**: WCLI-01, WCLI-02, WCLI-03, WCLI-04
**Success Criteria** (what must be TRUE):
  1. A PHP integration test can call the wallet client with uid 125 and receive an array of parsed credit transactions containing date, amount, and description fields
  2. The wallet client retrieves all transactions within a date range, even when results span multiple API pages
  3. WC API credentials are read from wp-config.php constants -- no credentials are stored in the WordPress database or exposed to the browser
  4. When the TerrWallet plugin is not installed, the client throws a typed TerrWalletException that callers can catch non-fatally
**Plans**: 1 plan

Plans:
- [ ] 09-01-PLAN.md -- TerrWallet client (exceptions, DTO, dual-mode fetch, integration test)

### Phase 10: Merge Adapter
**Goal**: Wallet credit transactions can be aggregated by date and merged into usage day records, producing a unified dataset where every day with either usage or wallet activity appears -- independently testable with fixture data and no I/O
**Depends on**: Nothing (can be built in parallel with Phase 9)
**Requirements**: MERGE-01, MERGE-02, MERGE-03, MERGE-04
**Success Criteria** (what must be TRUE):
  1. Given usage days and wallet transactions, the adapter produces a merged array where each day record includes an otherServices field with the summed credit amount and combined descriptions
  2. Multiple wallet transactions on the same day are aggregated into a single daily total -- not mapped one-to-one to separate rows
  3. A day with wallet transactions but zero usage activity appears as a row with 0 hits, $0.00 cost, and the wallet amount in Other Services
  4. Wallet timestamps (YYYY-MM-DD HH:MM:SS) and usage dates (YYYY-MM-DD) are normalized to the same date key before merging -- no mismatches from format differences
**Plans**: 1 plan

Plans:
- [ ] 10-01-PLAN.md -- TDD: UsageMergeAdapter with hash-map full outer join and PHPUnit tests

### Phase 11: Backend Integration
**Goal**: The existing AJAX handler returns merged usage + wallet data in a single response, and wallet failures never break the dashboard -- usage data always displays even if the wallet API is unavailable
**Depends on**: Phase 9, Phase 10
**Requirements**: GRACE-01, GRACE-02, UI-04
**Success Criteria** (what must be TRUE):
  1. Calling admin-ajax.php?action=tp_get_usage_summary returns day records that include otherServices fields with wallet credit amounts -- no additional AJAX call is needed from the browser
  2. If the TerrWallet API returns an error or times out, the AJAX response still returns all usage data normally with null otherServices values -- no error is shown to the user
  3. If the TerrWallet plugin is deactivated, the dashboard loads without any PHP errors or JavaScript failures -- the Other Services column simply shows empty cells
**Plans**: 1 plan

Plans:
- [ ] 11-01-PLAN.md -- Wire wallet client + merge adapter into AJAX handler with graceful degradation

### Phase 12: Dashboard UI
**Goal**: Users can see their wallet credit amounts per day in a dedicated Other Services column with tooltip descriptions, and the summary strip shows the period total
**Depends on**: Phase 11
**Requirements**: UI-01, UI-02, UI-03
**Success Criteria** (what must be TRUE):
  1. The usage dashboard table shows an "Other Services" column displaying wallet credit amounts in +$X.XX format for days with wallet activity, and a dash for days without
  2. Hovering over an Other Services amount displays a Bootstrap tooltip showing the transaction description text
  3. The summary strip includes a fourth card showing the Other Services total for the selected date range
**Plans**: 1 plan

Plans:
- [ ] 12-01-PLAN.md -- Other Services column, tooltips, sorting, and summary card

### Phase 13: E2E Tests and Validation
**Goal**: The full TerrWallet integration is verified end-to-end with real data, covering the wallet client, merge adapter edge cases, and the rendered UI column
**Depends on**: Phase 12
**Requirements**: TEST-01, TEST-02, TEST-03
**Success Criteria** (what must be TRUE):
  1. An integration test confirms the wallet client fetches and correctly parses real TerrWallet API data for uid 125
  2. Unit tests pass for all merge adapter scenarios: both sources present, usage-only days, wallet-only days, and multiple transactions on the same day
  3. An E2E test loads the usage dashboard in a browser and verifies the Other Services column displays real wallet amounts with working tooltips
**Plans**: 1 plan

Plans:
- [ ] 13-01-PLAN.md -- Update existing E2E tests for 5-column layout and create Other Services E2E tests

## Progress

**Execution Order:**
Phases execute in numeric order: 9 -> 10 -> 11 -> 12 -> 13
Note: Phases 9 and 10 have no dependency on each other and can be built in parallel.

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. CSS Foundation | v1.0 | 0/0 | Paused | - |
| 2. Forms and Modals | v1.0 | 0/0 | Paused | - |
| 3. Table Cards and Controls | v1.0 | 0/0 | Paused | - |
| 4. Chart Collapse | v1.0 | 0/0 | Paused | - |
| 5. Shortcode Foundation and API Proxy | v2.0 | 0/2 | Planning | - |
| 6. Stats Table and Summary Strip | v2.0 | Complete | 2026-02-23 | - |
| 7. Chart Rendering | v2.0 | 0/0 | Not started | - |
| 8. Date Filtering and API Doc | v2.0 | 0/0 | Not started | - |
| 9. Wallet Client | v2.2 | 1/1 | Complete | 2026-03-10 |
| 10. Merge Adapter | 1/1 | Complete   | 2026-03-10 | - |
| 11. Backend Integration | v2.2 | Complete    | 2026-03-10 | - |
| 12. Dashboard UI | v2.2 | Complete    | 2026-03-10 | - |
| 13. E2E Tests and Validation | v2.2 | 0/1 | Planning | - |
