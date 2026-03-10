# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-10)

**Core value:** Users can track their link usage costs and account balance at a glance -- daily stats with a chart and detailed table showing clicks, QR scans, costs, wallet top-ups, and running balance.
**Current focus:** Phase 9 - Wallet Client (v2.2 TerrWallet Integration)

## Current Position

Phase: 9 of 13 (Wallet Client)
Plan: 0 of TBD in current phase
Status: Ready to plan
Last activity: 2026-03-10 -- Roadmap created for v2.2 TerrWallet Integration (Phases 9-13)

Progress: [========-----] 62% (8 of 13 phases positioned, 0 of 5 v2.2 phases complete)

## Performance Metrics

**Velocity:**
- Total plans completed: 0 (v2.2 milestone)
- Average duration: -
- Total execution time: -

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| - | - | - | - |

**Recent Trend:**
- Last 5 plans: -
- Trend: -

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

### Pending Todos

None yet.

### Blockers/Concerns

- Phase 9 gate: Must verify `function_exists('woo_wallet')` before committing to direct PHP vs. rest_do_request() approach
- Product decision needed: Timezone handling -- normalize wallet dates to UTC or WordPress site timezone before merge
- API only returns `totalHits`, `hitCost`, `balance` -- no clicks vs QR scans breakdown (unchanged from v2.0)

## Session Continuity

Last session: 2026-03-10
Stopped at: Roadmap created for v2.2 milestone (Phases 9-13)
Resume file: None
