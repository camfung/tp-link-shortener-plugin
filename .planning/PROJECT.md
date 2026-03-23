# Traffic Portal

## What This Is

A WordPress plugin (Traffic Portal) that provides link shortening, click tracking, QR code generation, a client links management dashboard, and a billing/usage dashboard. The plugin serves both end-users (creating/managing short links, viewing their usage costs) and administrators (viewing analytics, managing client links).

## Core Value

Users can track their link usage costs and account balance at a glance — daily stats with a chart and detailed table showing clicks, QR scans, costs, and running balance.

## Current Milestone: v2.3 Stress Test and Bug Regression

**Goal:** Validate plugin reliability through stress testing (50 link creation + high-volume usage generation) and build regression tests for all 8 Jira bugs.

**Target features:**
- Python stress test script: Playwright-based creation of 50 short links
- Usage generation: HTTP requests hitting each created link to produce usage records
- Usage dashboard verification: Playwright test validating /usage-dashboard reports correct data after stress test
- Jira bug regression suite: Automated tests covering all 8 TP project bugs (TP-22, TP-25, TP-29, TP-34, TP-41, TP-46, TP-71, TP-94)

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

- [ ] Stress test: create 50 short links via Playwright
- [ ] Usage generation: hit each link multiple times to produce usage records
- [ ] Usage dashboard verification test (/usage-dashboard)
- [ ] Regression tests for all 8 Jira bugs (TP-22, TP-25, TP-29, TP-34, TP-41, TP-46, TP-71, TP-94)

### Out of Scope

- Dashboard caching — deferred to v2.1
- Second table for domains, tpKeys, semaphores — deferred
- Link management features — separate existing dashboard handles this
- Mobile responsiveness — separate milestone (v1.0)
- Wallet debit transactions — only credits (top-ups) shown in Other Services

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
*Last updated: 2026-03-22 after v2.3 milestone initialization*
