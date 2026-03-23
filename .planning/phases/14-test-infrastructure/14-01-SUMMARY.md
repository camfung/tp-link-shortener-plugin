---
phase: 14-test-infrastructure
plan: 01
subsystem: testing
tags: [pytest, httpx, pytest-asyncio, pytest-xdist, stress-testing, regression-testing]

# Dependency graph
requires: []
provides:
  - pytest markers (stress, regression_bugs) with default exclusion
  - stress test fixtures (run_id, stress_data_file, stress_links, stress_rate_limit)
  - cleanup script for stress link removal via API
  - requirements.txt with httpx, pytest-asyncio, pytest-xdist
affects: [15-stress-pipeline, 16-regression-suite]

# Tech tracking
tech-stack:
  added: [httpx 0.28, pytest-asyncio 0.26, pytest-xdist 3.8]
  patterns: [session-scoped fixtures for test isolation, RUN_ID-based data file naming, marker-based test exclusion]

key-files:
  created:
    - tests/e2e/requirements.txt
    - tests/e2e/pytest.ini
    - tests/e2e/stress/conftest.py
    - tests/e2e/regression/conftest.py
    - tests/e2e/scripts/cleanup_stress.py
  modified:
    - .gitignore

key-decisions:
  - "Relaxed pytest pin to >=8.0 to resolve pytest-playwright 0.7.x metadata conflict"
  - "Cleanup script uses httpx sync client (not async) for CLI simplicity"

patterns-established:
  - "Marker exclusion: addopts = -m 'not stress and not regression_bugs' keeps default runs safe"
  - "RUN_ID isolation: each stress run writes to stress_data_{run_id}.json for independent cleanup"
  - "Data directory gitignored: tests/e2e/data/ never committed"

# Metrics
duration: 2min
completed: 2026-03-23
---

# Phase 14 Plan 01: Test Infrastructure Summary

**Pytest markers, stress fixtures with RUN_ID isolation, and cleanup CLI using httpx for API link removal**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-23T06:41:30Z
- **Completed:** 2026-03-23T06:43:58Z
- **Tasks:** 2
- **Files modified:** 9

## Accomplishments
- Installed httpx, pytest-asyncio, and pytest-xdist alongside existing Playwright dependencies
- Registered stress and regression_bugs markers with default exclusion via addopts
- Created stress conftest with session-scoped fixtures for run isolation and data management
- Built standalone cleanup script with DELETE/PUT fallback and both single-run and all-stress modes

## Task Commits

Each task was committed atomically:

1. **Task 1: Dependencies, markers, and directory structure** - `1376a4d` (feat)
2. **Task 2: Cleanup script and marker verification** - `a83d26b` (feat)

## Files Created/Modified
- `tests/e2e/requirements.txt` - Pinned dependencies for all e2e tests (existing + v2.3)
- `tests/e2e/pytest.ini` - Marker registration and default exclusion config
- `tests/e2e/stress/__init__.py` - Stress test package marker
- `tests/e2e/stress/conftest.py` - Session-scoped fixtures: run_id, stress_data_file, stress_links, stress_rate_limit
- `tests/e2e/regression/__init__.py` - Regression test package marker
- `tests/e2e/regression/conftest.py` - Regression directory docstring (fixtures deferred to Phase 16)
- `tests/e2e/scripts/__init__.py` - Scripts package marker
- `tests/e2e/scripts/cleanup_stress.py` - CLI for manual stress link cleanup via API
- `.gitignore` - Added tests/e2e/data/ exclusion

## Decisions Made
- Relaxed pytest version pin from >=9.0 to >=8.0 to resolve conflict with pytest-playwright 0.7.x metadata (pytest 8.4.2 installed, fully compatible)
- Used httpx sync client in cleanup script for CLI simplicity (async unnecessary for sequential API calls)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Resolved pytest version conflict with pytest-playwright**
- **Found during:** Task 1 (dependency installation)
- **Issue:** pytest-playwright 0.7.x metadata declares pytest<9.0 but works fine with 9.x; pip refuses to resolve with pytest>=9.0 pin
- **Fix:** Relaxed pytest pin to >=8.0,<10.0; pip resolved to pytest 8.4.2
- **Files modified:** tests/e2e/requirements.txt
- **Verification:** pip install -r requirements.txt completes without errors; all imports succeed
- **Committed in:** 1376a4d (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Version pin adjustment necessary for pip resolution. No functional impact -- pytest 8.4.2 is fully compatible with all dependencies.

## Issues Encountered
None beyond the version conflict documented above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 15 (Stress Pipeline) can begin: markers registered, fixtures ready, data directory created
- Phase 16 (Regression Suite) can begin: regression_bugs marker registered, directory structure in place
- Cleanup script ready for manual use after stress test runs

---
*Phase: 14-test-infrastructure*
*Completed: 2026-03-23*
