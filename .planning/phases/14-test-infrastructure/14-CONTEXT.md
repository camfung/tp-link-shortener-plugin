# Phase 14: Test Infrastructure - Context

**Gathered:** 2026-03-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Install new test dependencies (httpx, pytest-asyncio, pytest-xdist), add pytest markers and fixtures, establish RUN_ID isolation pattern and cleanup strategy so stress and regression tests can run without interfering with existing tests or polluting the dev environment.

</domain>

<decisions>
## Implementation Decisions

### Cleanup strategy
- No automatic cleanup — cleanup is manual only via a standalone CLI script
- Script uses API/AJAX calls (admin-ajax.php) to delete links — no Playwright needed
- Two modes: pass a specific RUN_ID to delete only that run's links, or `--all-stress` flag to delete all links matching the stress prefix pattern
- Cleanup script also deletes the corresponding `stress_data_{RUN_ID}.json` file alongside the links — no orphaned artifacts

### Data file handling
- Data files stored in `tests/e2e/data/` directory (git-ignored)
- Each stress run creates a timestamped file: `stress_data_{RUN_ID}.json` — preserves history across runs
- Per-link data contains: keyword, URL, MID (essentials only — no extra metadata)

### Parallel execution
- pytest-xdist parallelism used only for usage generation tests (httpx I/O-bound traffic)
- Playwright UI tests and regression tests stay sequential — no parallel risk
- Worker count: auto-detect (matches CPU cores via pytest-xdist `auto` mode)
- Existing e2e tests completely isolated from xdist — default `pytest` runs them sequentially as before
- Rate limiting for httpx usage generation is configurable via `STRESS_RATE_LIMIT` env var with a sensible default

### Claude's Discretion
- RUN_ID generation format and length
- Exact pytest marker names and conftest organization
- Default rate limit value for usage generation
- pytest.ini / pyproject.toml configuration approach

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 14-test-infrastructure*
*Context gathered: 2026-03-22*
