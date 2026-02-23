# Roadmap: Traffic Portal

## Milestones

- [ ] **v1.0 Mobile Responsive** - Phases 1-4 (paused)
- [ ] **v2.0 Usage Dashboard** - Phases 5-8 (current)

## Phases

<details>
<summary>v1.0 Mobile Responsive (Phases 1-4) - PAUSED</summary>

- [ ] **Phase 1: CSS Foundation** - Standardize breakpoints, clean up specificity, and convert hover patterns for touch devices
- [ ] **Phase 2: Forms and Modals** - Make link creation/edit forms and all modal dialogs fully usable on phone screens
- [ ] **Phase 3: Table Cards and Controls** - Convert link tables to card layout and make pagination, date pickers, and action buttons touch-friendly
- [ ] **Phase 4: Chart Collapse** - Hide performance chart by default on mobile with expand toggle and summary stats bar

</details>

### v2.0 Usage Dashboard (Phases 5-8)

**Milestone Goal:** Build a standalone billing/usage dashboard showing daily link activity stats, costs, and account balance via a chart and table.

**Phase Numbering:**
- Integer phases (5, 6, 7, 8): Planned milestone work
- Decimal phases (6.1, 6.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 5: Shortcode Foundation and API Proxy** - Register the shortcode, render the page skeleton, gate on authentication, and wire the AJAX proxy to the external API with caching
- [ ] **Phase 6: Stats Table and Summary Strip** - Render the daily stats table with currency formatting, balance precision, mock click/QR split, and summary stats cards using default 30-day data
- [ ] **Phase 7: Chart Rendering** - Display the area chart with two series (clicks/QR), data point markers, proper canvas lifecycle, and flex container stability
- [ ] **Phase 8: Date Filtering and API Doc** - Add interactive date range selection with presets and validation, and document the backend API changes needed for real data

## Phase Details

### Phase 5: Shortcode Foundation and API Proxy
**Goal**: A page with `[tp_usage_dashboard]` renders a complete HTML skeleton, blocks unauthenticated users, and can fetch real data from the external API through a secure WordPress AJAX proxy
**Depends on**: Nothing (first phase in v2.0 milestone)
**Requirements**: PAGE-01, PAGE-02, PAGE-03, DATA-01, DATA-02, DATA-03
**Success Criteria** (what must be TRUE):
  1. Visiting a WordPress page containing `[tp_usage_dashboard]` displays a dashboard page with a loading skeleton (chart placeholder, table placeholder, date inputs)
  2. An unauthenticated visitor sees a login prompt instead of the dashboard content
  3. The browser can call `admin-ajax.php?action=tp_get_usage_summary` and receive real API data as JSON -- the user ID is never accepted from client-side parameters
  4. Repeated page loads within 5 minutes return cached data (WordPress transient) without hitting the external API again
**Plans**: TBD

Plans:
- [ ] 05-01: TBD
- [ ] 05-02: TBD

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
**Plans**: TBD

Plans:
- [ ] 06-01: TBD
- [ ] 06-02: TBD

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
**Plans**: TBD

Plans:
- [ ] 07-01: TBD
- [ ] 07-02: TBD

### Phase 8: Date Filtering and API Doc
**Goal**: Users can filter their usage data by custom date ranges or quick presets, and the API requirements for real click/QR split data are documented for the backend team
**Depends on**: Phase 7
**Requirements**: DATA-05, DATA-06, TABLE-05, DOC-01
**Success Criteria** (what must be TRUE):
  1. User can select custom start and end dates and click Apply to reload the table and chart with the filtered data
  2. Preset buttons (7d, 30d, 90d) update the date inputs and reload data with one click
  3. The end date input does not allow selecting a date beyond today
  4. An API requirements document exists specifying the backend changes needed for real clicks/QR split, other services data, and wallet transactions
**Plans**: TBD

Plans:
- [ ] 08-01: TBD
- [ ] 08-02: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 5 -> 6 -> 7 -> 8

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. CSS Foundation | v1.0 | 0/0 | Paused | - |
| 2. Forms and Modals | v1.0 | 0/0 | Paused | - |
| 3. Table Cards and Controls | v1.0 | 0/0 | Paused | - |
| 4. Chart Collapse | v1.0 | 0/0 | Paused | - |
| 5. Shortcode Foundation and API Proxy | v2.0 | 0/0 | Not started | - |
| 6. Stats Table and Summary Strip | v2.0 | 0/0 | Not started | - |
| 7. Chart Rendering | v2.0 | 0/0 | Not started | - |
| 8. Date Filtering and API Doc | v2.0 | 0/0 | Not started | - |
