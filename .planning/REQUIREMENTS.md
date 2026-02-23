# Requirements: Traffic Portal v2.0 — Usage Dashboard

**Defined:** 2026-02-22
**Core Value:** Users can track their link usage costs and account balance at a glance — daily stats with a chart and detailed table.

## v2.0 Requirements

### Shortcode & Page

- [ ] **PAGE-01**: User sees a standalone billing dashboard when visiting a page with `[tp_usage_dashboard]`
- [ ] **PAGE-02**: Unauthenticated user sees a login prompt instead of the dashboard
- [ ] **PAGE-03**: Dashboard shows a loading skeleton while data is being fetched

### Data & API

- [ ] **DATA-01**: Dashboard fetches daily activity data from the external `user-activity-summary` API via WordPress AJAX proxy
- [ ] **DATA-02**: User ID is always determined server-side — never accepted from client-side request parameters
- [ ] **DATA-03**: API responses are cached via WordPress transients (5-minute TTL) to avoid redundant external calls
- [ ] **DATA-04**: Date range filter defaults to last 30 days on first load
- [ ] **DATA-05**: User can select custom start and end dates to filter the data
- [ ] **DATA-06**: Preset date buttons (7d, 30d, 90d) allow quick date range selection
- [ ] **DATA-07**: Clicks and QR scans are split from totalHits using a deterministic mock ratio, clearly labeled as estimated

### Stats Table

- [ ] **TABLE-01**: Daily stats table displays columns: date, clicks, QR scans, total hits, cost, balance
- [ ] **TABLE-02**: All currency values display with exactly 2 decimal places and dollar sign formatting
- [ ] **TABLE-03**: Running balance is calculated without floating-point drift (rounded after each step)
- [ ] **TABLE-04**: When no data exists for the selected range, a clear "No usage data" message is shown with the date range displayed
- [ ] **TABLE-05**: Date range filter end date cannot exceed today

### Chart

- [ ] **CHART-01**: Area chart displays daily clicks and QR scans as two stacked series (yellow/green matching TP-59 design)
- [ ] **CHART-02**: Chart has data point markers on each day
- [ ] **CHART-03**: Chart properly destroys and recreates when date range changes (no "Canvas already in use" errors)
- [ ] **CHART-04**: Chart container uses proper CSS (position: relative, min-width: 0) to prevent flex resize loops
- [ ] **CHART-05**: Mock data split is visually labeled as "estimated" via chart legend or disclaimer

### Summary Stats

- [ ] **STATS-01**: Summary strip above the table shows total hits, total cost, and current balance for the selected period

### API Requirements Doc

- [ ] **DOC-01**: API requirements document specifies needed backend changes for real clicks/QR split, other services, and wallet transactions

## Future Requirements

### Other Services & Wallet
- **WALLET-01**: Other Services column showing one-time charges (domain renewals, wallet top-ups)
- **WALLET-02**: Wallet top-up integration with balance

### Extended Analytics
- **EXT-01**: Second table for domains, tpKeys, semaphores info
- **EXT-02**: CSV/export functionality for usage data
- **EXT-03**: Real clicks/QR split from by-source API (replacing mock)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Link management features | Separate dashboard handles this (tp_client_links) |
| Real-time data refresh | Daily granularity data doesn't benefit from live updates |
| Per-link breakdown in this dashboard | Belongs in the link management dashboard |
| Sortable table columns | Daily chronological data has one natural sort order |
| Table pagination | 30-90 rows is manageable without pagination |
| Mobile responsiveness | Separate milestone (v1.0) handles this |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| PAGE-01 | TBD | Pending |
| PAGE-02 | TBD | Pending |
| PAGE-03 | TBD | Pending |
| DATA-01 | TBD | Pending |
| DATA-02 | TBD | Pending |
| DATA-03 | TBD | Pending |
| DATA-04 | TBD | Pending |
| DATA-05 | TBD | Pending |
| DATA-06 | TBD | Pending |
| DATA-07 | TBD | Pending |
| TABLE-01 | TBD | Pending |
| TABLE-02 | TBD | Pending |
| TABLE-03 | TBD | Pending |
| TABLE-04 | TBD | Pending |
| TABLE-05 | TBD | Pending |
| CHART-01 | TBD | Pending |
| CHART-02 | TBD | Pending |
| CHART-03 | TBD | Pending |
| CHART-04 | TBD | Pending |
| CHART-05 | TBD | Pending |
| STATS-01 | TBD | Pending |
| DOC-01 | TBD | Pending |

**Coverage:**
- v2.0 requirements: 22 total
- Mapped to phases: 0 (pending roadmap)
- Unmapped: 22

---
*Requirements defined: 2026-02-22*
*Last updated: 2026-02-22 after milestone v2.0 initialization*
