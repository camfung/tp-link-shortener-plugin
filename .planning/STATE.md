# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-10)

**Core value:** Users can track their link usage costs and account balance at a glance -- daily stats with a chart and detailed table showing clicks, QR scans, costs, wallet top-ups, and running balance.
**Current focus:** Phase 12 - Dashboard UI (v2.2 TerrWallet Integration)

## Current Position

Phase: 12 of 13 (Dashboard UI)
Plan: 1 of 1 in current phase (COMPLETE)
Status: Phase 12 complete -- ready for Phase 13
Last activity: 2026-03-10 -- Completed 12-01 Other Services column, tooltips, and summary card

Progress: [============-] 92% (12 of 13 phases positioned, 4 of 5 v2.2 phases complete)

## Performance Metrics

**Velocity:**
- Total plans completed: 4 (v2.2 milestone)
- Average duration: 3min
- Total execution time: 12min

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 09-wallet-client | 1 | 5min | 5min |
| 10-merge-adapter | 1 | 2min | 2min |
| 11-backend-integration | 1 | 2min | 2min |
| 12-dashboard-ui | 1 | 3min | 3min |

**Recent Trend:**
- Last 5 plans: 09-01 (5min), 10-01 (2min), 11-01 (2min), 12-01 (3min)
- Trend: stable

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- [Milestone]: v1.0 Mobile Responsive paused, pivoting to v2.0 then v2.2
- [v2.2 init]: Server-side merge in ajax_get_usage_summary -- single AJAX call, no separate wallet endpoint
- [v2.2 init]: Direct PHP calls or rest_do_request() preferred over wp_remote_get() to avoid loopback issues
- [v2.2 init]: Credit transactions only -- debits would double-count costs already tracked by hitCost
- [v2.2 init]: Full outer join -- wallet-only days appear with 0 hits/cost
- [v2.2 init]: Zero new dependencies -- new TerrWallet namespace follows existing pattern
- [09-01]: Direct PHP get_wallet_transactions() as primary path -- no permission overhead for regular users
- [09-01]: REST fallback uses rest_do_request() with email lookup and PHP-side date/type filtering
- [09-01]: WalletTransaction DTO sanitizes HTML via wp_strip_all_tags() on details field
- [10-01]: Items array preserves per-transaction detail (amount + description), no transactionId exposed
- [10-01]: Empty/whitespace descriptions stored as-is per CONTEXT.md decision
- [10-01]: strcmp() for YYYY-MM-DD date sorting -- lexicographic comparison is correct
- [11-01]: Catch only TerrWalletException, not generic Exception -- merge adapter bugs bubble up
- [11-01]: Use get_current_user_id() for wallet, not Traffic Portal UID variable
- [11-01]: otherServices set to null (not absent) on failure -- frontend checks null, not field existence
- [12-01]: fa-hand-holding-dollar icon for Other Services summary card
- [12-01]: Bootstrap Tooltip with container: 'body' for proper z-index in table overflow
- [12-01]: Tooltip lifecycle: dispose before DOM removal, init after insertion to prevent memory leaks

### Pending Todos

None yet.

### Blockers/Concerns

- Phase 9 gate: Must verify `function_exists('woo_wallet')` before committing to direct PHP vs. rest_do_request() approach
- Product decision needed: Timezone handling -- normalize wallet dates to UTC or WordPress site timezone before merge
- API only returns `totalHits`, `hitCost`, `balance` -- no clicks vs QR scans breakdown (unchanged from v2.0)

## Session Continuity

Last session: 2026-03-10
Stopped at: Completed 12-01-PLAN.md (Dashboard UI) -- ready for Phase 13 cleanup
Resume file: None
