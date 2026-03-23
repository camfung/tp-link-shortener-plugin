# Project Research Summary

**Project:** Traffic Portal v2.3 — Stress Test and Bug Regression Suite
**Domain:** Automated test suite — bulk UI stress testing + Jira bug regression
**Researched:** 2026-03-22
**Confidence:** HIGH (stack and architecture), MEDIUM (individual bug details)

## Executive Summary

This milestone adds two distinct test suites to an existing Python/Playwright pytest infrastructure: a three-phase stress test pipeline that creates 50 links via the UI, generates real usage records via bulk HTTP, and verifies the usage dashboard reflects the traffic; and eight independent regression tests covering known Jira bugs. Both suites build on a solid existing foundation — 11 e2e test files, session-scoped auth, page fixtures, and env-driven config already exist and require only minor additions (one new fixture in conftest.py, three new pytest markers).

The recommended approach uses three targeted additions to the existing stack: `pytest-xdist` for parallel test execution, `httpx` for async bulk HTTP usage generation, and `pytest-asyncio` to enable async test functions alongside the existing sync Playwright tests. The stress pipeline must run sequentially (creation → usage generation → dashboard verification) and uses a generated `stress_data.json` file to pass link data between phases. The eight regression tests are fully independent and can be built and executed in any order.

The primary risks are API rate limiting during bulk link creation (mitigated by configurable inter-creation delays and explicit custom keys), test data pollution from 50 real links persisting in the dev environment (mitigated by a deterministic `stress-{run_id}-{i}` naming prefix and teardown cleanup), and flaky assertions from AJAX timing (mitigated by waiting for network responses and loading indicators rather than arbitrary sleeps). The individual Jira bug details are the single significant research gap — each ticket must be read from Jira before its regression test can be written, and TP-94 is flagged as an umbrella ticket that may decompose into multiple sub-tests.

## Key Findings

### Recommended Stack

The existing test infrastructure (pytest, pytest-playwright 0.7.2, Playwright sync API, conftest.py with session-scoped auth) is well-suited and requires no replacement. Three packages are added for the new work. All other tools — locust, aiohttp, selenium, faker, jira — are explicitly out of scope.

**Core technologies:**
- `pytest-xdist >= 3.8.0`: parallel Playwright test execution — Playwright's officially endorsed parallelism approach; start with `-n 4`, not `-n auto`, to avoid overwhelming the target server
- `httpx >= 0.28.1`: async bulk HTTP for usage generation — dual sync/async API, requests-compatible, handles 50-500 requests without aiohttp's complexity; preferred over `requests` (no async) and aiohttp (overkill)
- `pytest-asyncio >= 1.3.0`: async test function support — standard companion to httpx in pytest; co-exists with existing sync Playwright tests without conflict via explicit `@pytest.mark.asyncio` markers

**See:** `.planning/research/STACK.md` for full alternatives analysis and requirements.txt additions.

### Expected Features

**Must have (table stakes):**
- Bulk link creation (50 links via Playwright UI with explicit custom keys) — core stress test requirement; never use auto-generation due to known `/generate-short-code` 500 errors
- Usage generation via HTTP hits (httpx async, `allow_redirects=False`) — produces real click records in the backend; browser navigation is too slow (150+ page loads vs. 15 seconds with HTTP)
- Usage dashboard verification with retry polling — end-to-end confirmation of the pipeline; must handle backend data propagation delay
- Test data cleanup strategy (deterministic `stress-{run_id}-{i}` prefix + teardown) — prevents dev environment pollution across multiple test runs
- Regression tests for TP-22, TP-25, TP-29, TP-34, TP-41, TP-46, TP-71, TP-94 — all eight bugs must be covered; TP-94 is an umbrella ticket requiring sub-task analysis

**Should have (differentiators):**
- Configurable link count via `TP_STRESS_LINK_COUNT` env var (default 50) — low-effort, enables flexible runs
- Screenshot capture on Playwright failure — free debugging value with zero configuration
- CSV/JSON test result export to `tests/e2e/results/` — enables post-hoc analysis

**Defer (v2.4+):**
- Parallel link creation via multiple browser contexts — high complexity, not necessary for 50 links
- API-bypass link creation — defeats the purpose of testing the UI path under load
- Cross-browser stress runs in Firefox/WebKit — no milestone value, Chromium is sufficient

**See:** `.planning/research/FEATURES.md` for full feature dependency graph and file organization recommendation.

### Architecture Approach

New tests live entirely within `tests/e2e/` alongside existing tests. The stress pipeline uses three separate test files bridged by a generated `stress_data.json`. Regression tests live in a new `tests/e2e/regression/` subdirectory — one file per Jira ticket — and inherit all parent fixtures automatically via pytest's conftest chain. A `run_stress.sh` shell script orchestrates the sequential stress phases. The only existing file modified is `conftest.py` (adding one `stress_links` fixture and `STRESS_DATA_FILE` constant, approximately 10 lines). No PHP, JS, template, or PHPUnit files change.

**Major components:**
1. `conftest.py` (modified) — adds `stress_links` fixture that reads `stress_data.json`; includes skip guard if file is missing; also adds pytest markers for `stress` and `regression`
2. `test_stress_link_creation.py` (new) — Playwright creates 50 links with explicit keys, writes `stress_data.json`; includes per-15-link page refresh and configurable inter-creation delay
3. `test_stress_usage_generation.py` (new) — async httpx `AsyncClient` hits each short URL `allow_redirects=False` to generate backend usage records; uses `asyncio.gather` for parallelism
4. `test_stress_dashboard_verify.py` (new) — Playwright polls the usage dashboard with retry logic until hit counts match expected totals
5. `run_stress.sh` (new) — sequential orchestrator for the three stress phases with a configurable `sleep` propagation gap between phase 2 and phase 3
6. `regression/test_tp{nn}.py` (8 new files) — isolated bug reproduction per ticket; each test sets up its own preconditions; behavior-based assertions with `data-testid` attributes

**See:** `.planning/research/ARCHITECTURE.md` for the full build order, data flow diagrams, and working code patterns for each component.

### Critical Pitfalls

1. **API rate limiting kills stress test mid-run** — the `tp_create_link` AJAX handler calls the Traffic Portal Lambda API which enforces per-route throttles; add `time.sleep(1.5)` between link creations (configurable via `TP_STRESS_DELAY`); detect HTTP 429 and apply exponential backoff rather than failing the test outright

2. **Shortcode generation exhaustion** — always provide explicit custom keys (`stress-{run_id}-{i:03d}`); never rely on auto-generation because the `/generate-short-code/{tier}` endpoint already has a documented 500 error rate; the stress test goal is volume, not shortcode generation

3. **Test data pollutes the dev environment** — 50 real links and hundreds of usage records persist after the test; use a unique `RUN_ID` per run in all key names; implement teardown that deletes or disables created links via the API; running the test twice without cleanup doubles the pollution

4. **Browser resource exhaustion during the 50-link loop** — the client links table's DOM grows with each creation; refresh the page every 15 iterations; set `page.set_default_timeout(60_000)` for the stress test only; assert total count once at the end rather than after each individual creation

5. **Dashboard verification before data propagates** — the usage summary API has eventual consistency; wait at least 30 seconds after usage generation; implement retry polling (reload, check totals, wait 10s, up to 12 retries for ~60 seconds max) rather than a hardcoded sleep

**See:** `.planning/research/PITFALLS.md` for the full 13-pitfall catalogue including nonce expiration, AJAX race conditions, UI coupling in regression tests, and parallel run conflicts.

## Implications for Roadmap

Research identifies three natural phases: foundation setup, stress pipeline, and regression suite. The stress pipeline has a strict internal dependency order (creation → generation → verification). The regression suite is fully independent of the stress pipeline and can be developed in parallel with Phases 2a/2b after Phase 1 completes.

### Phase 1: Foundation and Conftest Updates

**Rationale:** All new test files depend on fixtures, markers, and the `RUN_ID` pattern established here. Must come first. Low risk, well-understood.
**Delivers:** Updated `conftest.py` with `stress_links` fixture and `STRESS_DATA_FILE` constant; pytest markers for `stress` and `regression` in `pyproject.toml` or `conftest.py`; `requirements.txt` additions (httpx, pytest-asyncio, pytest-xdist); documentation of test user account requirements (balance/quota)
**Addresses:** Test isolation infrastructure, cleanup strategy scaffolding, parallel run conflict prevention
**Avoids:** Parallel run conflicts (Pitfall 13) by establishing unique `RUN_ID` from the start; nonce expiration (Pitfall 6) by designing the page navigation pattern upfront

### Phase 2: Stress Pipeline

**Rationale:** The three stress files must be built in dependency order — creation first (produces `stress_data.json`), usage generation second (consumes the file), dashboard verification third (depends on backend state). Core deliverable of the milestone.
**Delivers:** `test_stress_link_creation.py`, `test_stress_usage_generation.py`, `test_stress_dashboard_verify.py`, `run_stress.sh`, `.gitignore` entry for `stress_data.json`
**Uses:** httpx with `AsyncClient` and `asyncio.gather` (usage generation), Playwright sync API (creation and verification), `stress_links` fixture from Phase 1
**Implements:** File-based state bridge pattern, HTTP-based usage generation with `allow_redirects=False`, retry-based dashboard polling
**Avoids:** Rate limiting (Pitfall 1), shortcode exhaustion (Pitfall 3), browser resource exhaustion (Pitfall 4), data propagation timing (Pitfall 9), test data pollution (Pitfall 2)

**Internal ordering within Phase 2:**
- Step 2a: `test_stress_link_creation.py` — most complex; UI automation loop with delay, page refresh, custom keys, and file output
- Step 2b: `test_stress_usage_generation.py` — simpler; async HTTP hits; can be validated independently by reading an existing `stress_data.json`
- Step 2c: `test_stress_dashboard_verify.py` — depends on backend state from 2b; build last
- Step 2d: `run_stress.sh` — trivial; write after all three test files are passing

### Phase 3: Jira Bug Regression Suite

**Rationale:** Fully independent of the stress pipeline; can begin immediately after Phase 1. The main constraint within this phase is that each regression test requires reading the actual Jira ticket before implementation begins. TP-94 (umbrella ticket) should be scheduled last because its scope is uncertain.
**Delivers:** `tests/e2e/regression/` directory with `__init__.py`, optional `conftest.py`, and 8 test files (test_tp22.py through test_tp94.py)
**Uses:** Existing Playwright fixtures from parent `conftest.py`; no new dependencies beyond Phase 1 additions
**Implements:** Isolated scenario pattern — each test sets up its own preconditions, tests behavior not DOM structure, references Jira ticket ID in test docstring
**Avoids:** UI coupling (Pitfall 7) by using behavior assertions with `data-testid` over fragile CSS selectors; AJAX race conditions (Pitfall 8) by waiting for network responses; missing preconditions (Pitfall 11) by creating test data inline

**Internal ordering within Phase 3:**
- Begin with whichever bug ticket has the clearest fix description from Jira (most likely to succeed first)
- Build TP-94 last given umbrella ticket risk
- All 8 tests are independent once written; order does not affect the others

### Phase Ordering Rationale

- Phase 1 is the gate because every subsequent file depends on fixtures and markers it establishes
- Phase 2 and Phase 3 can be developed in parallel after Phase 1 completes
- Within Phase 2, the three stress files have a hard sequential dependency via `stress_data.json`
- Within Phase 3, regression tests are all independent of each other — prioritize by Jira ticket clarity or bug severity
- No plugin code changes are required at any phase; all work is purely test-side

### Research Flags

Phases needing deeper research during planning:
- **Phase 3 (all 8 regression tests):** Individual Jira ticket details are entirely unknown. Each test's implementation depends on reading the actual ticket description and reproduction steps. TP-94 is an umbrella ticket that may decompose into 2-5 independent tests. Run a research pass on all 8 tickets before beginning Phase 3 implementation — do not start writing tests from memory or inference.

Phases with standard patterns (skip additional research):
- **Phase 1 (conftest updates):** Fixtures follow exact patterns already in the codebase. Well-documented pytest APIs.
- **Phase 2 (stress pipeline):** All three patterns (file-based state bridge, async HTTP usage generation, retry polling) are fully specified in ARCHITECTURE.md with working code examples ready to implement.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All three packages verified on PyPI with current versions; Playwright official docs endorse pytest-xdist; httpx and pytest-asyncio are standard companions with stable APIs |
| Features | HIGH (stress), LOW (regression) | Stress test feature set derived from direct codebase inspection. Jira bug details unknown until tickets are read — no assumptions made about individual bug behavior. |
| Architecture | HIGH | Derived from direct inspection of 11 existing test files, conftest.py, and class-tp-api-handler.php; all patterns verified against current codebase; component boundaries are explicit and well-defined |
| Pitfalls | HIGH (rate limiting, data pollution, shortcode), MEDIUM (Playwright resource limits) | Rate limit and shortcode pitfalls confirmed from RateLimitException.php and BUG documentation in the repo. Playwright memory behavior is based on general Playwright knowledge, not repo-specific measurement. |

**Overall confidence:** HIGH for stress pipeline, MEDIUM for regression suite (blocked on Jira ticket content)

### Gaps to Address

- **Jira ticket content for TP-22, TP-25, TP-29, TP-34, TP-41, TP-46, TP-71, TP-94:** No research was done on the specifics of these bugs. Must read each ticket before writing its regression test. Handle during Phase 3 kickoff by pulling all 8 ticket details from Jira as the first action.

- **TP-94 umbrella scope:** STATE.md flags TP-94 as an umbrella ticket. It may contain multiple sub-tasks that each warrant an independent test class or file. Resolve during Phase 3 kickoff; if it decomposes into more than 3 sub-tasks, budget additional time for this ticket.

- **Test user wallet balance and quota:** The stress test creates 50 links and generates usage hits that consume real balance and quota. Verify the `TP_TEST_USER` account has an unlimited or well-funded plan before running. Document this in the test README. If the account runs out of balance, link creation fails with billing errors that produce misleading test failures.

- **API rate limit threshold for the dev environment:** The exact throttle limit for `POST /items` on `trafficportal.dev` is unknown. The default `TP_STRESS_DELAY=1.5` seconds is conservative. Validate the actual limit during Phase 2 step 2a development and adjust the default if needed.

- **`allow_redirects=False` behavior for trpl.link:** Confirm that sending a GET to `https://trpl.link/{key}` with `allow_redirects=False` actually creates a usage record in the backend. If the usage record is only written after the full redirect chain completes, the usage generation approach must use `allow_redirects=True` instead. Verify during Phase 2 step 2b.

## Sources

### Primary (HIGH confidence)
- `tests/e2e/conftest.py` — auth pattern, fixture structure, env loading
- `tests/e2e/test_client_links.py` — test class structure, AJAX wait patterns
- `tests/e2e/test_usage_dashboard.py` — deployment detection, selector patterns
- `tests/e2e/test_usage_dashboard_date_filtering.py` — date filtering test patterns
- `includes/TrafficPortal/Exception/RateLimitException.php` — confirms 429 handling exists
- `includes/class-tp-api-handler.php` — AJAX handler flow, rate limit error path
- `docs/BUG-shortcode-generation-failing.md` — confirms `/generate-short-code` returns 500 under load
- `.planning/PROJECT.md` — v2.3 milestone definition, 8 Jira bug IDs
- `.planning/STATE.md` — TP-94 umbrella ticket flag, current milestone scope
- [Playwright Python pytest plugin](https://playwright.dev/python/docs/test-runners) — official parallel testing guidance endorsing pytest-xdist
- [pytest-xdist on PyPI](https://pypi.org/project/pytest-xdist/) — v3.8.0
- [httpx on PyPI](https://pypi.org/project/httpx/) — v0.28.1
- [HTTPX Async Support](https://www.python-httpx.org/async/) — AsyncClient documentation
- [pytest-asyncio on PyPI](https://pypi.org/project/pytest-asyncio/) — v1.3.0

### Secondary (MEDIUM confidence)
- AWS API Gateway default throttling behaviors for Lambda-backed APIs — rate limiting baseline; actual dev environment limits unknown
- Playwright memory management best practices — browser resource exhaustion under sustained load, general knowledge

### Tertiary (LOW confidence)
- Individual Jira bug details for TP-22, TP-25, TP-29, TP-34, TP-41, TP-46, TP-71, TP-94 — not yet read; must be retrieved from Jira before regression test implementation begins

---
*Research completed: 2026-03-22*
*Ready for roadmap: yes*
