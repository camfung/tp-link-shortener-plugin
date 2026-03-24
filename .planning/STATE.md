# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-22)

**Core value:** Users can track their link usage costs and account balance at a glance -- daily stats with a chart and detailed table showing clicks, QR scans, costs, and running balance.
**Current focus:** Phase 16 - Bug Regression Suite (v2.3 Stress Test and Bug Regression)

## Current Position

Phase: 16 of 16 (Bug Regression Suite)
Plan: 1 of 2 in current phase (16-01 COMPLETE)
Status: 16-01 redirect regression tests complete -- ready for 16-02
Last activity: 2026-03-24 -- Completed 16-01 redirect regression tests

Progress: [#########░] 90% (v2.3 phases: 16-01 of 16-02 complete)

## Performance Metrics

**Velocity:**
- Total plans completed: 6 (v2.3)
- Average duration: 1.7min
- Total execution time: 10min

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 14-test-infrastructure | 1 | 2min | 2min |
| 15-stress-pipeline | 4 | 4min | 1min |
| 16-bug-regression-suite | 1 | 4min | 4min |

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
- [15-04]: Subshell isolation per pytest stage to prevent directory accumulation
- [15-04]: if-not pattern for granular per-stage failure messages
- [16-01]: Assert 'trafficportal' (not 'trafficportal.com') in redirect Location for dev/prod compatibility
- [16-01]: Root path 403 is acceptable behavior for short domain
- [16-01]: Each test file reads SHORT_DOMAIN via os.getenv directly (avoids conftest import path issues)

### Pending Todos

None yet.

### Blockers/Concerns

- TP-94 is an umbrella ticket -- may decompose into multiple sub-tests (resolve during Phase 16 planning)
- Jira ticket details for all 8 bugs must be read before writing regression tests
- Test user account balance/quota must be verified before running stress tests
- API rate limit threshold for dev environment is unknown (default 1.5s delay is conservative)

## Session Continuity

Last session: 2026-03-24
Stopped at: Completed 16-01-PLAN.md (redirect regression tests) -- ready for 16-02
Resume file: None
