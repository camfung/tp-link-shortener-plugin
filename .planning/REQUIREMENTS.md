# Requirements: Traffic Portal v2.2 — TerrWallet Integration

**Defined:** 2026-03-10
**Core Value:** Users can track their link usage costs and account balance at a glance — daily stats with a chart and detailed table showing clicks, QR scans, costs, wallet top-ups, and running balance.

## v2.2 Requirements

### Wallet Client

- [ ] **WCLI-01**: Plugin fetches wallet credit transactions from the TerrWallet API for the current user
- [ ] **WCLI-02**: TerrWallet API credentials (WC consumer key/secret) are configured via wp-config.php constants or direct PHP calls
- [ ] **WCLI-03**: Wallet client handles pagination to retrieve all transactions within the requested date range
- [ ] **WCLI-04**: Wallet client uses direct PHP calls or rest_do_request() to avoid loopback HTTP issues on same-server API

### Data Merge

- [ ] **MERGE-01**: Wallet credit transactions are merged with usage data by date into a unified daily dataset
- [ ] **MERGE-02**: Multiple wallet transactions on the same day are aggregated into a single daily total with combined descriptions
- [ ] **MERGE-03**: Days with only wallet transactions (no usage activity) appear as rows with zero hits/cost
- [ ] **MERGE-04**: Date formats are normalized between APIs (usage: YYYY-MM-DD, wallet: YYYY-MM-DD HH:MM:SS)

### Graceful Degradation

- [ ] **GRACE-01**: Dashboard displays usage data normally if TerrWallet API is unavailable or errors — Other Services column shows empty
- [ ] **GRACE-02**: If TerrWallet plugin is deactivated, dashboard continues to function without errors

### Dashboard UI

- [ ] **UI-01**: Usage dashboard table includes an "Other Services" column showing wallet credit amounts (+$X.XX format)
- [ ] **UI-02**: Other Services amounts display a tooltip on hover showing the transaction description
- [ ] **UI-03**: Summary strip includes an Other Services total card for the selected period
- [ ] **UI-04**: Existing AJAX handler (tp_get_usage_summary) returns merged data — no additional AJAX call needed

### Testing

- [ ] **TEST-01**: Integration tests verify wallet client fetches and parses real TerrWallet API data (uid 125)
- [ ] **TEST-02**: Unit tests verify merge adapter handles: both sources, usage-only days, wallet-only days, multiple transactions per day
- [ ] **TEST-03**: E2E tests verify Other Services column appears with real wallet data after deployment

## v2.0 Requirements (Validated)

### Shortcode & Page

- ✓ **PAGE-01**: User sees a standalone billing dashboard — v2.0 Phase 5
- ✓ **PAGE-02**: Unauthenticated user sees login prompt — v2.0 Phase 5
- ✓ **PAGE-03**: Dashboard shows loading skeleton — v2.0 Phase 5

### Data & API

- ✓ **DATA-01**: Dashboard fetches daily activity data via AJAX proxy — v2.0 Phase 5
- ✓ **DATA-02**: User ID determined server-side — v2.0 Phase 5
- ✓ **DATA-03**: API responses cached via transients — v2.0 Phase 5
- ✓ **DATA-04**: Date range defaults to last 30 days — v2.0 Phase 6
- ✓ **DATA-05**: Custom start/end date selection — v2.0 Phase 8
- ✓ **DATA-06**: Preset date buttons (7d, 30d, 90d) — v2.0 Phase 8
- ✓ **DATA-07**: Mock clicks/QR split — v2.0 Phase 6

### Stats Table

- ✓ **TABLE-01**: Daily stats table with date, clicks, QR, hits, cost, balance — v2.0 Phase 6
- ✓ **TABLE-02**: Currency formatting ($X.XX) — v2.0 Phase 6
- ✓ **TABLE-03**: Running balance without floating-point drift — v2.0 Phase 6
- ✓ **TABLE-04**: Empty state message — v2.0 Phase 6
- ✓ **TABLE-05**: End date cannot exceed today — v2.0 Phase 8

### Chart

- ✓ **CHART-01**: Stacked area chart (clicks/QR) — v2.0 Phase 7
- ✓ **CHART-02**: Data point markers — v2.0 Phase 7
- ✓ **CHART-03**: Proper chart destroy/recreate — v2.0 Phase 7
- ✓ **CHART-04**: CSS flex resize fix — v2.0 Phase 7
- ✓ **CHART-05**: Estimated label — v2.0 Phase 7

### Summary Stats

- ✓ **STATS-01**: Summary strip (hits, cost, balance) — v2.0 Phase 6

### API Doc

- ✓ **DOC-01**: API requirements document — v2.0 Phase 8

## Future Requirements

### Extended Analytics
- **EXT-01**: Second table for domains, tpKeys, semaphores info
- **EXT-02**: CSV/export functionality for usage data
- **EXT-03**: Real clicks/QR split from by-source API (replacing mock)

### Dashboard Caching (deferred from v2.1)
- **CACHE-01**: Browser-side cache for link data with mutation invalidation
- **CACHE-02**: Server-side cache (transients) for link preview thumbnails
- **CACHE-03**: Cache invalidation on link create/edit/delete

## Out of Scope

| Feature | Reason |
|---------|--------|
| Wallet debit transactions | Debits represent usage costs already tracked by hitCost — would double-count |
| Link management features | Separate dashboard (tp_client_links) |
| Real-time data refresh | Daily granularity doesn't benefit from live updates |
| Mobile responsiveness | Separate milestone (v1.0) |
| Dashboard caching | Deferred to future milestone |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| WCLI-01 | Phase 9 | Pending |
| WCLI-02 | Phase 9 | Pending |
| WCLI-03 | Phase 9 | Pending |
| WCLI-04 | Phase 9 | Pending |
| MERGE-01 | Phase 10 | Pending |
| MERGE-02 | Phase 10 | Pending |
| MERGE-03 | Phase 10 | Pending |
| MERGE-04 | Phase 10 | Pending |
| GRACE-01 | Phase 11 | Pending |
| GRACE-02 | Phase 11 | Pending |
| UI-01 | Phase 12 | Pending |
| UI-02 | Phase 12 | Pending |
| UI-03 | Phase 12 | Pending |
| UI-04 | Phase 11 | Pending |
| TEST-01 | Phase 13 | Pending |
| TEST-02 | Phase 13 | Pending |
| TEST-03 | Phase 13 | Pending |

**Coverage:**
- v2.2 requirements: 17 total
- Mapped to phases: 17/17
- Unmapped: 0

---
*Requirements defined: 2026-03-10*
*Last updated: 2026-03-10 after roadmap creation*
