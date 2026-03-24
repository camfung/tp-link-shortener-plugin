# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-22)

**Core value:** Users can track their link usage costs and account balance at a glance -- daily stats with a chart and detailed table showing clicks, QR scans, costs, and running balance.
**Current focus:** Phase 15 - Stress Pipeline (v2.3 Stress Test and Bug Regression)

## Current Position

Phase: 15 of 16 (Stress Pipeline)
Plan: 3 of 4 in current phase (COMPLETE)
Status: 15-03 complete -- ready for 15-04
Last activity: 2026-03-23 -- Completed 15-03 verify dashboard

Progress: [#######░░░] 67% (v2.3 phases: 1.75/3 phases complete)

## Performance Metrics

**Velocity:**
- Total plans completed: 4 (v2.3)
- Average duration: 1.3min
- Total execution time: 5min

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 14-test-infrastructure | 1 | 2min | 2min |
| 15-stress-pipeline | 3 | 3min | 1min |

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- [v2.3 init]: Stress test uses Playwright + Python scripts
- [v2.3 init]: Links use custom keywords (never auto-generation due to 500 errors)
- [v2.3 init]: All 8 Jira bugs get regression tests (TP-46 excluded -- infra/IP issue)
- [v2.3 init]: httpx for async HTTP, pytest-xdist for parallel execution, pytest-asyncio for async tests
- [v2.3 roadmap]: 3 phases -- infrastructure, stress pipeline, regression suite
- [v2.3 roadmap]: Phases 15 and 16 can run in parallel after Phase 14
- [14-01]: Relaxed pytest pin to >=8.0 to resolve pytest-playwright 0.7.x metadata conflict
- [14-01]: Cleanup script uses httpx sync client for CLI simplicity
- [15-01]: expect_response filters by tp_create_link post_data to avoid validation AJAX
- [15-01]: 500ms inter-creation delay for form reset reliability
- [15-02]: follow_redirects=False -- 301/302 alone registers usage, skip destination load
- [15-02]: Realistic User-Agent header to avoid bot filtering on redirect service
- [15-03]: Fixed selector IDs to match actual template (tp-ud-date-start not tp-ud-start-date)
- [15-03]: Open custom date panel before filling date inputs (panel hidden by default)

### Pending Todos

None yet.

### Blockers/Concerns

- TP-94 is an umbrella ticket -- may decompose into multiple sub-tests (resolve during Phase 16 planning)
- Jira ticket details for all 8 bugs must be read before writing regression tests
- Test user account balance/quota must be verified before running stress tests
- API rate limit threshold for dev environment is unknown (default 1.5s delay is conservative)

## Session Continuity

Last session: 2026-03-23
Stopped at: Completed 15-03-PLAN.md (verify dashboard)
Resume file: None
