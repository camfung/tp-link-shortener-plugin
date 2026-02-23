# Traffic Portal

## What This Is

A WordPress plugin (Traffic Portal) that provides link shortening, click tracking, QR code generation, a client links management dashboard, and a billing/usage dashboard. The plugin serves both end-users (creating/managing short links, viewing their usage costs) and administrators (viewing analytics, managing client links).

## Core Value

Users can track their link usage costs and account balance at a glance — daily stats with a chart and detailed table showing clicks, QR scans, costs, and running balance.

## Current Milestone: v2.0 Usage Dashboard

**Goal:** Build a standalone billing/usage dashboard that shows users their daily link activity stats, costs, and account balance via a chart and table.

**Target features:**
- New `[tp_usage_dashboard]` shortcode on its own page
- Area chart showing daily clicks and QR scans over time
- Stats table with date, clicks, QR scans, total hits, cost, and running balance
- Date range filtering (default: last 30 days)
- Mock data where API falls short (clicks vs QR scans breakdown)
- API requirements document specifying needed backend changes

## Requirements

### Validated

<!-- Existing capabilities confirmed working -->

- ✓ Short link creation with custom codes — existing
- ✓ Click tracking and analytics — existing
- ✓ QR code generation and display — existing
- ✓ Client links dashboard with table, filters, pagination — existing
- ✓ Link editing and status toggling — existing
- ✓ Date range filtering and search — existing
- ✓ Performance chart (clicks + QR scans) — existing
- ✓ User authentication and session management — existing

### Active

- [ ] Usage dashboard shortcode renders a standalone billing page
- [ ] Area chart displays daily clicks and QR scans over time
- [ ] Stats table shows date, clicks, QR scans, total hits, cost, balance
- [ ] Date range filter defaults to last 30 days
- [ ] Dashboard fetches data from user-activity-summary API
- [ ] Mocked clicks/QR scans split where API only returns totalHits
- [ ] API requirements doc specifies needed backend changes

### Out of Scope

- Other Services column (domain renewals, wallet top-ups) — deferred
- Second table for domains, tpKeys, semaphores — deferred
- Link management features — separate existing dashboard handles this
- Mobile responsiveness — separate milestone (v1.0)

## Context

- **Framework:** Bootstrap 5.3.0 (via CDN) + custom CSS with CSS variables design system
- **Icons:** Font Awesome 6.4.0
- **Typography:** Poppins font family
- **Charting:** Chart.js 4.4.1 already loaded in plugin
- **API:** `GET /user-activity-summary/{uid}` returns date, totalHits, hitCost, balance
- **API gap:** No clicks vs QR scans breakdown — needs mocking until API updated
- **Design reference:** Screenshots from TP-59 ticket — area chart (yellow=clicks, green=QR) + stats table
- **Existing patterns:** `[tp_client_links]` shortcode has chart + table pattern to follow
- **WordPress:** Plugin runs inside WordPress themes, styles scoped to avoid conflicts

## Constraints

- **Tech stack**: Must use existing Bootstrap 5 + Chart.js + custom CSS — no new frameworks
- **API**: Current API only returns totalHits (not clicks/QR split) — mock the split for now
- **WordPress**: New shortcode must follow existing plugin patterns (class-tp-*-shortcode.php)
- **Separate page**: This is NOT a tab on the existing dashboard — it's its own shortcode/page

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| New shortcode `[tp_usage_dashboard]` | Billing is conceptually separate from link management | — Pending |
| Mock clicks/QR split | API only returns totalHits; mock split until backend updated | — Pending |
| Skip Other Services for v2.0 | Reduces scope; can add in future milestone | — Pending |
| Skip second table (domains/keys) | Focus on core billing stats first | — Pending |
| Area chart matching TP-59 design | Yellow=clicks, green=QR scans, data point markers | — Pending |

---
*Last updated: 2026-02-22 after v2.0 milestone initialization*
