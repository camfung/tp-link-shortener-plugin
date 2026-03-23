# Architecture Research

**Domain:** Stress testing and bug regression test suite for WordPress link shortener plugin
**Researched:** 2026-03-22
**Confidence:** HIGH -- based on direct inspection of existing test infrastructure, plugin architecture, and API surface

## Existing Architecture (Context for New Work)

### System Overview

```
                    EXISTING SYSTEM
 ┌──────────────────────────────────────────────────────┐
 │  WordPress (trafficportal.dev)                       │
 │  ┌──────────────────────────────────────────────┐    │
 │  │  Plugin: tp-link-shortener-plugin             │    │
 │  │  ┌──────────────┐  ┌──────────────────────┐  │    │
 │  │  │ Shortcodes   │  │  TP_API_Handler      │  │    │
 │  │  │ [tp_client_  │  │  (AJAX + REST)       │  │    │
 │  │  │  links]      │  │  - create link       │  │    │
 │  │  │ [tp_usage_   │  │  - toggle status     │  │    │
 │  │  │  dashboard]  │  │  - get map items     │  │    │
 │  │  └──────┬───────┘  └──────────┬───────────┘  │    │
 │  └─────────┼─────────────────────┼──────────────┘    │
 └────────────┼─────────────────────┼───────────────────┘
              │ renders HTML/JS     │ wp_ajax_ hooks
              ▼                     ▼
         Browser (user)     External APIs
                            ├── TrafficPortalApiClient (trpl.link)
                            ├── GenerateShortCodeClient
                            ├── SnapCaptureClient
                            └── TerrWalletClient

              EXISTING TEST INFRASTRUCTURE
 ┌──────────────────────────────────────────────────────┐
 │  tests/                                              │
 │  ├── e2e/          Python + Playwright (pytest)      │
 │  │   ├── conftest.py     (auth, fixtures, .env)      │
 │  │   ├── test_client_links*.py                       │
 │  │   └── test_usage_dashboard*.py                    │
 │  ├── Unit/         PHP + PHPUnit                     │
 │  │   └── *Test.php                                   │
 │  ├── Integration/  PHP + PHPUnit (real API calls)    │
 │  │   └── *IntegrationTest.php                        │
 │  └── (JS: vitest via package.json)                   │
 └──────────────────────────────────────────────────────┘
```

### Existing Test Patterns (What New Tests Must Follow)

| Pattern | How It Works | Source |
|---------|-------------|--------|
| Session-scoped auth | `conftest.py` logs in once via WordPress login form, reuses cookies for all tests | `conftest.py:42-63` |
| Page fixtures | `client_links_page` and `usage_dashboard_page` navigate + wait for container selector | `conftest.py:76-92` |
| Env-based config | `TP_BASE_URL`, `TP_TEST_USER`, `TP_TEST_PASS` from `.env` file or env vars | `conftest.py:23-39` |
| Deployment checks | Tests auto-skip if expected DOM elements are missing (version detection) | `test_usage_dashboard.py:28-45` |
| Class-based grouping | Related tests grouped in classes (e.g., `TestPageLoad`, `TestTable`) | `test_client_links.py` |
| Playwright sync API | All tests use synchronous Playwright API (not async) | All existing tests |

### Component Responsibilities

| Component | Responsibility | Technology |
|-----------|----------------|------------|
| `conftest.py` | Session-scoped auth, page fixtures, env loading | pytest + Playwright sync API |
| `TP_API_Handler` | AJAX/REST endpoints proxying to external APIs | PHP, WordPress hooks |
| `TrafficPortalApiClient` | HTTP client for trpl.link API | PHP (custom HTTP) |
| Shortcode classes | Render dashboard HTML + enqueue JS/CSS assets | PHP (WordPress shortcode API) |
| Frontend JS | AJAX calls, DOM manipulation, Chart.js rendering | Vanilla JS + Bootstrap 5 |

---

## New Architecture: Stress Tests + Bug Regression

### What Gets Added

```
 tests/
 ├── e2e/                         (EXISTING directory)
 │   ├── conftest.py              (MODIFY: add stress fixtures + markers)
 │   ├── test_client_links.py     (EXISTING - no change)
 │   ├── test_usage_dashboard*.py (EXISTING - no change)
 │   │
 │   ├── test_stress_link_creation.py    (NEW: create 50 links via UI)
 │   ├── test_stress_usage_generation.py (NEW: HTTP hits to short URLs)
 │   ├── test_stress_dashboard_verify.py (NEW: verify dashboard data)
 │   ├── stress_data.json               (NEW: generated, gitignored)
 │   │
 │   └── regression/                     (NEW: subdirectory)
 │       ├── __init__.py
 │       ├── conftest.py                 (optional regression-specific fixtures)
 │       ├── test_tp22.py
 │       ├── test_tp25.py
 │       ├── test_tp29.py
 │       ├── test_tp34.py
 │       ├── test_tp41.py
 │       ├── test_tp46.py
 │       ├── test_tp71.py
 │       └── test_tp94.py
 │
 ├── e2e/run_stress.sh            (NEW: orchestrator script)
 └── (existing Unit/, Integration/ -- no changes)
```

### Component Boundaries

| Component | Responsibility | New vs Modified | Communicates With |
|-----------|---------------|-----------------|-------------------|
| `conftest.py` | Add stress fixtures, pytest markers, shared helpers | MODIFIED | All test files |
| `test_stress_link_creation.py` | Create 50 short links via Client Links UI | NEW | WP AJAX (`tp_create_link`), conftest auth |
| `test_stress_usage_generation.py` | HTTP GET each short URL to generate usage records | NEW | External redirect service (trpl.link) |
| `test_stress_dashboard_verify.py` | Assert usage dashboard shows correct totals | NEW | Usage Dashboard page, conftest auth |
| `stress_data.json` | Ephemeral data bridge between stress phases | NEW (generated) | Written by creation, read by usage+verify |
| `regression/test_tp*.py` | Per-bug test cases for all 8 Jira tickets | NEW (8 files) | Various plugin pages + AJAX endpoints |
| `run_stress.sh` | Sequential runner for 3 stress phases with wait | NEW | pytest CLI |

---

## Data Flow

### Stress Test Flow

```
1. LINK CREATION (test_stress_link_creation.py)
   ┌─────────────────────────────────────────────────────┐
   │  For i in range(50):                                │
   │    Navigate to Client Links page                    │
   │    Click "Add Link" button                          │
   │    Fill destination URL (https://example.com/N)     │
   │    Submit via modal form --> AJAX tp_create_link     │
   │    Assert link appears in table                     │
   │    Store {tpKey, destination, short_url}             │
   │                                                     │
   │  Write all created links to stress_data.json        │
   └──────────────────────┬──────────────────────────────┘
                          │
                          ▼ outputs stress_data.json

2. USAGE GENERATION (test_stress_usage_generation.py)
   ┌─────────────────────────────────────────────────────┐
   │  Load links from stress_data.json                   │
   │  For each link:                                     │
   │    HTTP GET https://trpl.link/{tpKey}  (N times)    │
   │    Use allow_redirects=False (only need redirect    │
   │    to be logged, not followed)                      │
   │    Use ThreadPoolExecutor for parallelism            │
   │  Record total expected hits                         │
   └──────────────────────┬──────────────────────────────┘
                          │
                          ▼ usage records exist in backend DB

3. DASHBOARD VERIFICATION (test_stress_dashboard_verify.py)
   ┌─────────────────────────────────────────────────────┐
   │  Navigate to /usage-dashboard/                      │
   │  Set date range to include today                    │
   │  Poll with retries until data propagates            │
   │  Assert total hits >= expected count                │
   │  Assert chart renders data points                   │
   │  Assert table rows show today's activity            │
   └─────────────────────────────────────────────────────┘
```

### Bug Regression Flow (Per Bug)

```
   ┌──────────────────────────────────────┐
   │  Setup: reproduce preconditions      │
   │    (navigate to page, create data)   │
   │    ↓                                 │
   │  Action: trigger the bug scenario    │
   │    (click, submit, filter, etc.)     │
   │    ↓                                 │
   │  Assert: bug behavior does NOT occur │
   │  Assert: correct behavior occurs     │
   │    ↓                                 │
   │  Teardown: clean up if needed        │
   └──────────────────────────────────────┘
```

---

## Architectural Patterns

### Pattern 1: File-Based State Between Sequential Stress Phases

**What:** Stress tests have 3 phases (create, generate usage, verify) that must run in order and share data (the list of created links).
**When to use:** When test phases produce output consumed by later phases and run as separate pytest invocations.
**Trade-offs:** Adds coupling between tests but reflects real sequential workflow. File is visible for debugging.

```python
# test_stress_link_creation.py
import json
from pathlib import Path

STRESS_DATA_FILE = Path(__file__).parent / "stress_data.json"

class TestStressLinkCreation:
    """Create 50 short links via the Client Links UI."""

    def test_create_50_links(self, client_links_page: Page):
        created_links = []
        for i in range(50):
            url = f"https://example.com/stress-test-{i}"
            # ... open modal, fill form, submit via UI ...
            tp_key = self._extract_created_key(client_links_page)
            created_links.append({"tpKey": tp_key, "url": url})

        STRESS_DATA_FILE.write_text(json.dumps(created_links, indent=2))
        assert len(created_links) == 50
```

```python
# test_stress_usage_generation.py
class TestStressUsageGeneration:
    def test_hit_all_links(self, stress_links):
        """Hit each created link to generate usage records."""
        for link in stress_links:
            requests.get(f"https://trpl.link/{link['tpKey']}",
                        allow_redirects=False, timeout=10)
```

**Why file-based over pytest session scope:** Stress phases run as separate pytest invocations (via `run_stress.sh`) because phase 2 uses raw HTTP (not Playwright) and phase 3 needs a wait period. Separate invocations cannot share pytest fixtures.

### Pattern 2: HTTP-Based Usage Generation (Not Playwright)

**What:** Usage generation uses Python `requests` or `httpx` for hitting short URLs, not Playwright browser navigation.
**When to use:** When testing backend behavior (usage recording) not frontend rendering.
**Trade-offs:** Much faster (no browser overhead), can run concurrent requests, but does not test browser-based redirect UX.

```python
import requests
from concurrent.futures import ThreadPoolExecutor

def hit_link(url: str, count: int = 3):
    """Hit a short URL to generate usage records."""
    for _ in range(count):
        requests.get(url, allow_redirects=False, timeout=10)

# Parallel for speed: 50 links x 3 hits = 150 requests in ~15 seconds
with ThreadPoolExecutor(max_workers=10) as pool:
    for link in links:
        pool.submit(hit_link, f"https://trpl.link/{link['tpKey']}")
```

**Why `allow_redirects=False`:** We only need the TP backend to log the redirect. Following the redirect to the destination wastes time and bandwidth. The 301/302 response is sufficient to create a `usage_record`.

### Pattern 3: Regression Tests as Isolated Scenarios

**What:** Each Jira bug gets an independent test file and class. No shared state between bugs.
**When to use:** Always for regression tests -- bugs are independent failure modes.
**Trade-offs:** Some setup duplication, but total isolation means one failing test cannot cascade.

```python
# tests/e2e/regression/test_tp22.py
"""Regression test for TP-22: [bug title from Jira]."""

class TestTP22:
    """TP-22: [descriptive bug title]

    Original bug: [what went wrong]
    Fix: [what was changed]
    Regression test: [what this test verifies]
    """

    def test_bug_does_not_reproduce(self, client_links_page: Page):
        # Setup preconditions that triggered the bug
        # Perform the action that caused the bug
        # Assert the correct behavior (not the buggy behavior)
        pass
```

### Pattern 4: Retry-Based Dashboard Verification

**What:** After usage generation, poll the dashboard with retries instead of hardcoded sleep.
**When to use:** Whenever verifying backend-processed data in a UI after triggering backend writes.
**Trade-offs:** More code, but eliminates flaky tests from timing issues.

```python
def test_dashboard_shows_stress_data(self, usage_dashboard_page: Page):
    """Verify dashboard reflects usage from stress test."""
    page = usage_dashboard_page
    expected_hits = 150  # 50 links x 3 hits

    for attempt in range(12):  # max ~60 seconds
        page.reload()
        page.wait_for_selector(".tp-ud-container", timeout=10_000)
        page.wait_for_selector("#tp-ud-skeleton", state="hidden", timeout=25_000)

        total_el = page.locator("#tp-ud-total-hits")
        if total_el.is_visible():
            total = int(total_el.text_content().replace(",", ""))
            if total >= expected_hits:
                break
        time.sleep(5)
    else:
        pytest.fail(f"Dashboard shows {total} hits after 60s, expected >= {expected_hits}")
```

---

## Recommended Project Structure

```
tests/
├── e2e/
│   ├── conftest.py                        # MODIFY: add stress_links fixture, markers
│   ├── .env                               # Credentials (gitignored, existing)
│   ├── stress_data.json                   # GENERATED: by stress creation test
│   ├── run_stress.sh                      # NEW: orchestrates 3 stress phases
│   │
│   ├── test_client_links.py               # existing -- no change
│   ├── test_client_links_auth.py          # existing -- no change
│   ├── test_edit_empty_keyword.py         # existing -- no change
│   ├── test_mobile_responsive.py          # existing -- no change
│   ├── test_uid_server_side.py            # existing -- no change
│   ├── test_usage_dashboard.py            # existing -- no change
│   ├── test_usage_dashboard_*.py          # existing -- no change
│   │
│   ├── test_stress_link_creation.py       # NEW: Phase 1 - create 50 links
│   ├── test_stress_usage_generation.py    # NEW: Phase 2 - HTTP hits
│   ├── test_stress_dashboard_verify.py    # NEW: Phase 3 - verify dashboard
│   │
│   └── regression/                        # NEW: subdirectory for bug tests
│       ├── __init__.py
│       ├── conftest.py                    # Regression-specific fixtures (optional)
│       ├── test_tp22.py
│       ├── test_tp25.py
│       ├── test_tp29.py
│       ├── test_tp34.py
│       ├── test_tp41.py
│       ├── test_tp46.py
│       ├── test_tp71.py
│       └── test_tp94.py
│
├── Unit/          # existing PHP unit tests -- no change
└── Integration/   # existing PHP integration tests -- no change
```

### Structure Rationale

- **Stress tests at `e2e/` root:** They use the same fixtures (auth, page navigation) as existing E2E tests. Same directory keeps fixture inheritance simple.
- **`stress_data.json`:** Ephemeral coupling file between stress phases. Gitignored. Created by creation test, consumed by usage + verification tests. Human-readable for debugging.
- **`regression/` subdirectory:** Separates regression tests from feature E2E tests. Allows running `pytest tests/e2e/regression/ -v` independently.
- **One file per bug:** Enables running a single regression (`pytest tests/e2e/regression/test_tp22.py -v`) and maps 1:1 to Jira tickets for traceability.
- **`run_stress.sh` in `e2e/`:** Keeps orchestration close to the tests it orchestrates.

---

## Conftest Modifications

### New Additions to `conftest.py`

```python
# Add to existing conftest.py
import json

STRESS_DATA_FILE = Path(__file__).parent / "stress_data.json"

@pytest.fixture()
def stress_links():
    """Load the list of links created by the stress creation test."""
    if not STRESS_DATA_FILE.exists():
        pytest.skip("Run test_stress_link_creation.py first to generate stress data")
    return json.loads(STRESS_DATA_FILE.read_text())
```

No other conftest changes needed. The existing `auth_context`, `page`, `client_links_page`, and `usage_dashboard_page` fixtures are sufficient for all new tests.

### Regression Subdirectory Conftest

```python
# tests/e2e/regression/conftest.py
# Inherits all fixtures from parent conftest.py automatically.
# Add regression-specific fixtures here if needed.

import pytest

# Example: fixture that navigates to a specific page state
@pytest.fixture()
def client_links_with_data(client_links_page):
    """Wait for table data to load (not just container)."""
    page = client_links_page
    page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)
    return page
```

---

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| trpl.link (redirect) | HTTP GET with `allow_redirects=False` for usage generation | Each GET creates a `usage_record` in the backend DB |
| WordPress AJAX | Playwright form submission triggers `tp_create_link` | Same auth cookies as existing tests |
| TP API (`/user-activity-summary/{uid}`) | Indirectly via usage dashboard page AJAX | Dashboard verification checks rendered output, not raw API |
| TP API (`/items/user/{uid}`) | Indirectly via client links page AJAX | Stress creation verifies links appear in paginated table |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Stress create --> Usage gen | `stress_data.json` file | Sequential; creation must complete before usage gen |
| Usage gen --> Dashboard verify | Implicit (backend DB state) | Needs retry/polling for data propagation delay |
| All regression tests | Independent (no cross-test state) | Can run in any order |
| `regression/conftest.py` --> parent `conftest.py` | pytest fixture inheritance | Automatic via pytest's conftest chain |

---

## Execution Strategy

### Running Stress Tests (Sequential -- Order Matters)

```bash
#!/bin/bash
# tests/e2e/run_stress.sh
set -e

echo "=== Phase 1: Creating 50 links ==="
pytest tests/e2e/test_stress_link_creation.py -v

echo "=== Phase 2: Generating usage (HTTP hits) ==="
pytest tests/e2e/test_stress_usage_generation.py -v

echo "=== Phase 3: Waiting for backend propagation ==="
sleep 30

echo "=== Phase 3: Verifying dashboard ==="
pytest tests/e2e/test_stress_dashboard_verify.py -v

echo "=== Stress test complete ==="
```

### Running Regression Tests (Independent -- Any Order)

```bash
# All regression tests
pytest tests/e2e/regression/ -v

# Single bug regression
pytest tests/e2e/regression/test_tp22.py -v
```

### Running Everything Except Stress (Default CI/Dev)

```bash
# Exclude stress tests (which create real data and are slow)
pytest tests/e2e/ -m "not stress" --ignore=tests/e2e/test_stress_* -v
```

### Pytest Markers (Add to conftest.py or pyproject.toml)

```ini
# pyproject.toml or pytest.ini
[tool:pytest]
markers =
    stress: Stress tests that create bulk data (run with run_stress.sh)
    regression: Bug regression tests (safe to run anytime)
```

---

## No Plugin Code Changes Required

The stress tests and regression tests are **purely test-side additions**. They exercise existing plugin functionality through:

- The browser (Playwright for UI interactions)
- HTTP (Python `requests` for usage generation)

No PHP changes, no JavaScript changes, no template changes. The only modified file in the existing codebase is `conftest.py` (adding a fixture).

If a bug regression test reveals the fix is not yet in place, that is a separate code fix task -- not part of the test architecture.

---

## Anti-Patterns

### Anti-Pattern 1: Using Playwright for Usage Generation

**What people do:** Navigate Playwright browser to each short URL to generate clicks.
**Why it's wrong:** 50 links x 3+ hits = 150+ browser navigations. Takes 5+ minutes vs 15 seconds with HTTP. Browser follows redirects, loading destination pages unnecessarily.
**Do this instead:** Use Python `requests` with `allow_redirects=False` and `ThreadPoolExecutor` for parallel hits.

### Anti-Pattern 2: Shared Mutable State Between Regression Tests

**What people do:** One regression test creates data that another regression test depends on.
**Why it's wrong:** Tests become order-dependent. Failing test A causes test B to fail for the wrong reason.
**Do this instead:** Each regression test sets up its own preconditions. Use per-test fixtures.

### Anti-Pattern 3: Hardcoded Sleep Instead of Polling

**What people do:** `time.sleep(60)` before dashboard verification.
**Why it's wrong:** Too short = flaky. Too long = slow. Backend processing time varies.
**Do this instead:** Poll the dashboard with retries (reload page, check value, retry after 5s, up to N attempts). Use Playwright's `expect().to_contain_text()` with timeout where possible.

### Anti-Pattern 4: Including Stress Tests in Default Test Runs

**What people do:** No markers or naming convention to exclude stress tests from `pytest tests/e2e/`.
**Why it's wrong:** Stress tests create 50 real links and generate real API traffic. Running on every test pass pollutes the dev environment and takes minutes.
**Do this instead:** Use pytest markers (`@pytest.mark.stress`) and filename convention (`test_stress_*`) to exclude from default runs.

### Anti-Pattern 5: Single Monolithic Regression Test File

**What people do:** Put all 8 bug regressions in one `test_regression.py` file.
**Why it's wrong:** Cannot run individual bug regressions. Harder to trace failures back to specific Jira tickets. File grows unbounded as bugs are added.
**Do this instead:** One file per Jira ticket (`test_tp22.py`). Each file has a docstring linking to the Jira ticket and describing the original bug.

---

## Dependencies and Build Order

```
conftest.py modifications (fixtures + markers)
    │
    ├──► test_stress_link_creation.py
    │         │
    │         ▼ produces stress_data.json
    │    test_stress_usage_generation.py
    │         │
    │         ▼ usage records in backend
    │    test_stress_dashboard_verify.py
    │
    ├──► run_stress.sh (orchestrates above 3)
    │
    └──► regression/ directory
              │
              ├── conftest.py (inherits parent)
              ├── test_tp22.py  ─┐
              ├── test_tp25.py   │
              ├── test_tp29.py   │
              ├── test_tp34.py   ├── all independent, any order
              ├── test_tp41.py   │
              ├── test_tp46.py   │
              ├── test_tp71.py   │
              └── test_tp94.py  ─┘
```

**Recommended build order:**

1. **Conftest updates** -- add `stress_links` fixture, pytest markers. Foundation for everything.
2. **Stress: link creation** -- depends on conftest. Most complex test (UI automation for 50 links).
3. **Stress: usage generation** -- depends on link creation output. Simpler (just HTTP hits).
4. **Stress: dashboard verification** -- depends on usage generation. Verifies end-to-end.
5. **`run_stress.sh`** -- orchestrates phases 2-4. Simple bash script, build after phases are working.
6. **Regression tests** -- independent of stress tests. Can be built in parallel with stress work. Priority by bug severity. Each bug test is independent, so they can be built in any order.

**Phase ordering rationale:**
- Conftest first because all tests depend on it.
- Stress creation before usage generation because it produces the data file.
- Regression tests are fully independent from stress tests and each other -- build in any order based on Jira priority.
- The shell runner is last because it just calls the other tests.

---

## New vs Modified Files Summary

### New Files (9-10 files)

| File | Purpose | Est. Lines |
|------|---------|-----------|
| `tests/e2e/test_stress_link_creation.py` | Create 50 links via Client Links UI | ~120 |
| `tests/e2e/test_stress_usage_generation.py` | HTTP hits to generate usage records | ~60 |
| `tests/e2e/test_stress_dashboard_verify.py` | Verify dashboard shows correct data | ~80 |
| `tests/e2e/run_stress.sh` | Sequential runner for 3 stress phases | ~20 |
| `tests/e2e/regression/__init__.py` | Package marker | 0 |
| `tests/e2e/regression/conftest.py` | Regression-specific fixtures (optional) | ~20 |
| `tests/e2e/regression/test_tp22.py` | Bug regression test | ~40-80 |
| `tests/e2e/regression/test_tp25.py` | Bug regression test | ~40-80 |
| `tests/e2e/regression/test_tp29.py` | Bug regression test | ~40-80 |
| `tests/e2e/regression/test_tp34.py` | Bug regression test | ~40-80 |
| `tests/e2e/regression/test_tp41.py` | Bug regression test | ~40-80 |
| `tests/e2e/regression/test_tp46.py` | Bug regression test | ~40-80 |
| `tests/e2e/regression/test_tp71.py` | Bug regression test | ~40-80 |
| `tests/e2e/regression/test_tp94.py` | Bug regression test (umbrella -- may be larger) | ~60-120 |

### Modified Files (1 file)

| File | Changes | Scope |
|------|---------|-------|
| `tests/e2e/conftest.py` | Add `stress_links` fixture, `STRESS_DATA_FILE` constant | ~10 lines added |

### Files NOT Modified

| File | Why Not |
|------|---------|
| Any PHP in `includes/` | Tests exercise existing functionality, no plugin changes |
| Any JS in `assets/` | UI is tested as-is |
| Any template in `templates/` | No UI changes |
| `phpunit.xml` | PHP test suites unchanged |
| `package.json` | No new JS test dependencies |

---

## Sources

- Direct inspection: `tests/e2e/conftest.py` -- auth pattern, fixtures, env loading (HIGH confidence)
- Direct inspection: `tests/e2e/test_client_links.py` -- test class structure, assertion patterns (HIGH confidence)
- Direct inspection: `tests/e2e/test_usage_dashboard.py` -- deployment check pattern, selector patterns (HIGH confidence)
- Direct inspection: `tests/e2e/test_usage_dashboard_date_filtering.py` -- date filtering test patterns (HIGH confidence)
- Direct inspection: `includes/class-tp-api-handler.php` -- AJAX handlers, link creation flow (HIGH confidence)
- Direct inspection: `phpunit.xml` -- PHP test configuration, env vars (HIGH confidence)
- Project context: `.planning/PROJECT.md` -- 8 Jira bug IDs, stress test requirements (HIGH confidence)
- Project state: `.planning/STATE.md` -- current milestone scope (HIGH confidence)

---
*Architecture research for: v2.3 Stress Test and Bug Regression Suite*
*Researched: 2026-03-22*
