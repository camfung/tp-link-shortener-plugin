# Phase 14: Test Infrastructure - Research

**Researched:** 2026-03-22
**Domain:** Python test infrastructure (pytest ecosystem, async HTTP, parallel execution)
**Confidence:** HIGH

## Summary

Phase 14 adds three new Python dependencies (httpx, pytest-asyncio, pytest-xdist) to an existing Playwright-based e2e test suite, introduces custom pytest markers to isolate stress/regression tests from the default run, and establishes a RUN_ID-based isolation pattern with a manual cleanup script.

The existing test infrastructure uses a Python venv at `tests/e2e/.venv/` with pytest 9.0.2 and pytest-playwright 0.7.2. There is no `requirements.txt`, no `pytest.ini`, and no `pyproject.toml` — all configuration is implicit. The conftest at `tests/e2e/conftest.py` provides session-scoped auth and page fixtures. The external link API lives at `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev` and uses `x-api-key` auth. Critically, the PHP client has no delete method — only create, update (PUT), and get. The cleanup script will need to either call a DELETE endpoint on the external API directly (if it exists) or disable links via the update endpoint.

**Primary recommendation:** Create a `requirements.txt` for dependency management, add `pytest.ini` for marker registration and default marker expression, put stress fixtures in a separate `tests/e2e/stress/conftest.py`, and write the cleanup script as a standalone Python script in `tests/e2e/scripts/`.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- No automatic cleanup — cleanup is manual only via a standalone CLI script
- Script uses API/AJAX calls (admin-ajax.php) to delete links — no Playwright needed
- Two modes: pass a specific RUN_ID to delete only that run's links, or `--all-stress` flag to delete all links matching the stress prefix pattern
- Cleanup script also deletes the corresponding `stress_data_{RUN_ID}.json` file alongside the links — no orphaned artifacts
- Data files stored in `tests/e2e/data/` directory (git-ignored)
- Each stress run creates a timestamped file: `stress_data_{RUN_ID}.json` — preserves history across runs
- Per-link data contains: keyword, URL, MID (essentials only — no extra metadata)
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

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| INFRA-01 | Test suite installs httpx, pytest-asyncio, and pytest-xdist as new dependencies | Standard Stack section: version-pinned requirements.txt with compatible versions for existing pytest 9.0.2 |
| INFRA-02 | Conftest provides stress data fixture (session-scoped, writes/reads stress_data.json) | Architecture Patterns section: stress_links fixture reads from `tests/e2e/data/stress_data_{RUN_ID}.json`, skips gracefully when file absent |
| INFRA-03 | Pytest markers (@pytest.mark.stress, @pytest.mark.regression_bugs) exclude tests from default run | Architecture Patterns section: pytest.ini addopts with `-m "not stress and not regression_bugs"` and marker registration |
| INFRA-04 | RUN_ID pattern isolates test data per run (unique prefix on link keywords) | Architecture Patterns section: `stress-{8char_hex}-{seq}` format using uuid4 hex prefix |
| INFRA-05 | Cleanup fixture deletes stress-created links after test suite completes | Architecture Patterns section: standalone cleanup script using httpx + admin-ajax.php to delete by MID; also removes data file |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| httpx | 0.28.x | Async HTTP client for usage generation | Modern async Python HTTP client, successor to requests for async use cases |
| pytest-asyncio | 0.25.x | Async test support in pytest | Standard bridge between pytest and asyncio |
| pytest-xdist | 3.5.x | Parallel test execution | Standard pytest parallelism plugin, auto worker count |

### Existing (already installed)
| Library | Version | Purpose |
|---------|---------|---------|
| pytest | 9.0.2 | Test framework |
| pytest-playwright | 0.7.2 | Browser automation tests |
| playwright | 1.58.0 | Browser engine |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| httpx | aiohttp | httpx has simpler API, requests-like interface; aiohttp more mature but lower-level |
| pytest-xdist | manual multiprocessing | xdist handles test distribution, collection, reporting — not worth building |

**Installation:**
```bash
cd tests/e2e
source .venv/bin/activate
pip install httpx pytest-asyncio pytest-xdist
pip freeze > requirements.txt
```

## Architecture Patterns

### Recommended Project Structure
```
tests/e2e/
├── conftest.py              # Existing: auth, page fixtures (UNCHANGED)
├── data/                    # NEW: git-ignored, stress data files
│   └── stress_data_{RUN_ID}.json
├── scripts/                 # NEW: standalone CLI tools
│   └── cleanup_stress.py    # Manual cleanup script
├── stress/                  # NEW: stress test directory
│   ├── conftest.py          # Stress-specific fixtures (RUN_ID, stress_links, rate_limit)
│   ├── test_create_links.py # Phase 15: link creation
│   └── test_usage.py        # Phase 15: usage generation
├── regression/              # NEW: regression test directory
│   ├── conftest.py          # Regression-specific fixtures
│   └── test_tp_*.py         # Phase 16: bug regression tests
├── test_client_links.py     # Existing (unchanged)
├── test_usage_dashboard.py  # Existing (unchanged)
└── ...                      # Other existing test files
```

### Pattern 1: Marker Registration and Default Exclusion (INFRA-03)
**What:** Register custom markers in pytest.ini and set default addopts to exclude stress/regression
**When to use:** Always — this is the core isolation mechanism

```ini
# tests/e2e/pytest.ini
[pytest]
markers =
    stress: Stress tests — run with -m stress
    regression_bugs: Bug regression tests — run with -m regression_bugs
addopts = -m "not stress and not regression_bugs"
```

Running `pytest tests/e2e/` executes only existing tests. Running `pytest tests/e2e/ -m stress` overrides addopts and runs only stress tests. The `-m` flag on the CLI replaces the addopts value for markers.

**Confidence:** HIGH — this is standard pytest behavior documented in the pytest docs.

### Pattern 2: RUN_ID Generation (INFRA-04)
**What:** Generate a unique prefix per test session to namespace link keywords
**When to use:** Every stress test run

**Recommended format:** `stress-{8char_hex}` where the hex is from `uuid.uuid4().hex[:8]`

Example keyword: `stress-a1b2c3d4-001`, `stress-a1b2c3d4-002`

```python
import uuid

def generate_run_id() -> str:
    """Generate a unique RUN_ID for this stress test session."""
    return f"stress-{uuid.uuid4().hex[:8]}"
```

The RUN_ID is session-scoped (one per pytest invocation). Individual link keywords append a sequence number: `{RUN_ID}-{NNN}`.

**Confidence:** HIGH — uuid4 provides sufficient uniqueness for non-overlapping concurrent runs.

### Pattern 3: Stress Data Fixture (INFRA-02)
**What:** Session-scoped fixture that reads/writes stress data JSON
**When to use:** Stress tests that need link data from creation phase

```python
import json
from pathlib import Path

DATA_DIR = Path(__file__).parent.parent / "data"

@pytest.fixture(scope="session")
def stress_links(run_id):
    """Load stress link data for current or previous run."""
    data_file = DATA_DIR / f"stress_data_{run_id}.json"
    if not data_file.exists():
        pytest.skip(f"No stress data file found: {data_file}")
    with open(data_file) as f:
        return json.load(f)
```

The data file schema:
```json
[
    {"keyword": "stress-a1b2c3d4-001", "url": "https://example.com", "mid": 12345},
    {"keyword": "stress-a1b2c3d4-002", "url": "https://example.com", "mid": 12346}
]
```

### Pattern 4: Cleanup Script (INFRA-05)
**What:** Standalone Python CLI script that deletes stress links via WordPress admin-ajax.php
**When to use:** Manual execution after stress tests

The cleanup script needs to:
1. Authenticate with WordPress to get a session cookie and nonce
2. Call admin-ajax.php to delete each link by MID
3. Remove the corresponding `stress_data_{RUN_ID}.json` file

**Critical finding:** The WordPress plugin has NO delete AJAX action registered. The available actions are: `tp_create_link`, `tp_validate_key`, `tp_validate_url`, `tp_update_link`, `tp_toggle_link_status`, `tp_get_user_map_items`, `tp_get_link_history`, `tp_get_usage_summary`.

**Options for cleanup:**
1. **Disable links via `tp_toggle_link_status`** — set status to "disabled" for each MID. Links persist but become inactive. This is safe and uses existing infrastructure.
2. **Call the external API directly** — the API endpoint is `{TP_API_ENDPOINT}/items/{mid}` and may support DELETE. The `x-api-key` is available via `.env.test`.
3. **Add a new wp_ajax delete handler** — would require PHP changes to the plugin (scope creep for this phase).

**Recommendation:** Use option 1 (disable via toggle) as the primary approach. Links are effectively cleaned up (won't appear in active lists, won't generate usage). If the external API supports DELETE, that's a bonus. The cleanup script should use httpx to POST to admin-ajax.php with the session cookies.

**Authentication for the script:** Use requests/httpx to POST to the WordPress login form (same as conftest auth flow), capture cookies, navigate to the client links page to extract the nonce from `tpClientLinks.nonce`, then call admin-ajax.php. However, since this is a Python CLI script (no browser), extracting the nonce is harder — the nonce is embedded in JavaScript by WordPress.

**Simpler approach:** Use the external API directly with `x-api-key`. The `.env.test` file (or a similar config) contains `TP_API_ENDPOINT` and `API_KEY`. The script can call `PUT /items/{mid}` with `{"status": "disabled"}` or `DELETE /items/{mid}` if supported. This avoids the WordPress auth complexity entirely.

**Confidence:** MEDIUM — need to verify if the external API supports DELETE during implementation.

### Pattern 5: Rate Limiting Configuration
**What:** Configurable delay between httpx requests during usage generation
**When to use:** Usage generation tests (Phase 15, but config established here)

```python
import os

STRESS_RATE_LIMIT = float(os.getenv("STRESS_RATE_LIMIT", "1.5"))  # seconds between requests
```

Default 1.5s is conservative (noted in STATE.md as unknown threshold). Configurable via env var.

### Anti-Patterns to Avoid
- **Modifying existing conftest.py with stress fixtures:** Keep stress fixtures in `tests/e2e/stress/conftest.py` — separation of concerns, no risk to existing tests
- **Using pytest-xdist for Playwright tests:** Playwright has its own parallelism and xdist conflicts with browser state. Only use xdist for httpx-based tests
- **Hard-coding RUN_ID:** Always generate fresh. If you need to reuse, pass via env var or CLI arg
- **Auto-cleanup in fixtures:** User explicitly decided NO automatic cleanup. Use standalone script only

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Async HTTP requests | Custom aiohttp wrapper | httpx.AsyncClient | Handles connection pooling, retries, rate limiting |
| Parallel test execution | multiprocessing + custom runner | pytest-xdist | Handles test collection, distribution, reporting |
| Unique ID generation | timestamp + random | uuid.uuid4().hex[:8] | Collision-proof, no coordination needed |
| CLI argument parsing (cleanup script) | Manual sys.argv parsing | argparse | Standard library, handles --help, validation |

**Key insight:** The test infrastructure phase is plumbing — every component has a well-tested standard solution. The risk is in wiring them together correctly, not in any individual component.

## Common Pitfalls

### Pitfall 1: pytest-xdist Runs All Tests in Parallel
**What goes wrong:** Running `pytest -n auto` parallelizes ALL collected tests, including Playwright browser tests
**Why it happens:** xdist distributes any test it collects unless constrained
**How to avoid:** Only use `-n auto` with `-m stress` marker. The `addopts` default excludes stress tests anyway. Usage generation tests in `tests/e2e/stress/` should be the only ones run with `-n`.
**Warning signs:** Flaky Playwright tests, browser crashes, "context already closed" errors

### Pitfall 2: addopts `-m` Conflicts with CLI `-m`
**What goes wrong:** People think CLI `-m stress` is additive to addopts `-m "not stress"`, resulting in zero tests collected
**Why it happens:** Misunderstanding of pytest option precedence
**How to avoid:** CLI `-m` REPLACES the addopts `-m` value. Running `pytest -m stress` correctly selects only stress tests. This is standard pytest behavior.
**Warning signs:** "no tests ran" or "0 items collected"

### Pitfall 3: Session-Scoped Fixtures with xdist
**What goes wrong:** Session-scoped fixtures run once per WORKER, not once per session, when using xdist
**Why it happens:** Each xdist worker is a separate pytest session
**How to avoid:** For stress data, this is actually fine — each worker reads the same JSON file. For RUN_ID, generate it BEFORE pytest runs (pass via env var or use `tmp_path_factory` with `FileLock` from `filelock` package).
**Warning signs:** Multiple RUN_IDs generated in one test run, duplicate link keywords

### Pitfall 4: Nonce Extraction Without Browser
**What goes wrong:** The cleanup script needs a WordPress nonce to call admin-ajax.php, but the nonce is embedded in JavaScript
**Why it happens:** WordPress nonces are generated server-side and injected into `wp_localize_script` output
**How to avoid:** Use the external API directly with `x-api-key` header instead of admin-ajax.php. The API endpoint (`TP_API_ENDPOINT`) and key (`API_KEY`) are already used by `get-links.sh`.
**Warning signs:** 403/401 errors from admin-ajax.php, "invalid nonce" responses

### Pitfall 5: .gitignore Conflicts with Data Directory
**What goes wrong:** The `.gitignore` has `.env*` which could match unintended files, and `tests/e2e/data/` needs explicit ignoring
**Why it happens:** Broad gitignore patterns
**How to avoid:** Add `tests/e2e/data/` to `.gitignore` explicitly. The `.env*` pattern already covers the env files.
**Warning signs:** Stress data JSON files committed to git, or data directory not created

## Code Examples

### pytest.ini Configuration
```ini
# tests/e2e/pytest.ini
[pytest]
markers =
    stress: Stress tests (link creation, usage generation, dashboard verification)
    regression_bugs: Bug regression tests for known Jira issues
addopts = -m "not stress and not regression_bugs"
asyncio_mode = auto
```

### requirements.txt
```
# Existing dependencies
pytest>=9.0,<10.0
pytest-playwright>=0.7,<1.0
pytest-base-url>=2.1,<3.0

# New dependencies for v2.3
httpx>=0.28,<1.0
pytest-asyncio>=0.25,<1.0
pytest-xdist>=3.5,<4.0
```

### Stress Conftest (tests/e2e/stress/conftest.py)
```python
import json
import os
import uuid
from pathlib import Path

import pytest

DATA_DIR = Path(__file__).parent.parent / "data"


def pytest_configure(config):
    """Ensure data directory exists."""
    DATA_DIR.mkdir(exist_ok=True)


@pytest.fixture(scope="session")
def run_id():
    """Unique RUN_ID for this test session. Can be overridden via env var."""
    return os.getenv("STRESS_RUN_ID", f"stress-{uuid.uuid4().hex[:8]}")


@pytest.fixture(scope="session")
def stress_data_file(run_id):
    """Path to the stress data JSON file for this run."""
    return DATA_DIR / f"stress_data_{run_id}.json"


@pytest.fixture(scope="session")
def stress_links(stress_data_file):
    """Load existing stress link data. Skip if file doesn't exist."""
    if not stress_data_file.exists():
        pytest.skip(f"Stress data file not found: {stress_data_file.name}")
    with open(stress_data_file) as f:
        return json.load(f)


@pytest.fixture(scope="session")
def stress_rate_limit():
    """Rate limit delay in seconds between usage generation requests."""
    return float(os.getenv("STRESS_RATE_LIMIT", "1.5"))
```

### Cleanup Script Skeleton (tests/e2e/scripts/cleanup_stress.py)
```python
#!/usr/bin/env python3
"""
Delete stress test links and their data files.

Usage:
    python cleanup_stress.py <RUN_ID>          # Delete links for specific run
    python cleanup_stress.py --all-stress      # Delete all stress-prefixed links
"""
import argparse
import json
import sys
from pathlib import Path

import httpx

DATA_DIR = Path(__file__).parent.parent / "data"


def load_env():
    """Load API config from .env or .env.test."""
    # Read TP_API_ENDPOINT, API_KEY from env files
    ...


def delete_links_for_run(run_id: str, api_endpoint: str, api_key: str):
    """Disable all links from a specific stress run."""
    data_file = DATA_DIR / f"stress_data_{run_id}.json"
    if not data_file.exists():
        print(f"No data file found for {run_id}")
        return

    with open(data_file) as f:
        links = json.load(f)

    client = httpx.Client(headers={"x-api-key": api_key})
    for link in links:
        mid = link["mid"]
        resp = client.put(f"{api_endpoint}/items/{mid}", json={"status": "disabled"})
        print(f"  MID {mid}: {resp.status_code}")

    # Remove data file
    data_file.unlink()
    print(f"Deleted data file: {data_file.name}")
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `@pytest.yield_fixture` | `@pytest.fixture` with yield | pytest 4.0+ | yield_fixture deprecated |
| `asyncio_mode = "strict"` | `asyncio_mode = "auto"` | pytest-asyncio 0.21+ | Auto mode is now standard, detects async tests automatically |
| Manual worker distribution | `pytest-xdist -n auto` | Long-standing | Auto-detects CPU cores |

**Deprecated/outdated:**
- `pytest.ini` `filterwarnings` for asyncio: no longer needed with `asyncio_mode = auto`
- `@pytest.mark.asyncio` on every test: not needed when `asyncio_mode = auto`

## Open Questions

1. **Does the external API support DELETE /items/{mid}?**
   - What we know: The PHP client has create (POST), update (PUT), and get (GET) methods. No DELETE method exists in `TrafficPortalApiClient.php`.
   - What's unclear: Whether the API Gateway/Lambda behind `TP_API_ENDPOINT` accepts DELETE requests
   - Recommendation: Test with a curl DELETE during implementation. If not supported, use PUT with `{"status": "disabled"}` as the cleanup mechanism. Either way, the script structure is the same.

2. **What is the test user's UID for the external API?**
   - What we know: The `.env` has test credentials (email/password) for WordPress auth. The `get-links.sh` script takes a UID argument.
   - What's unclear: The UID value for the test user
   - Recommendation: Add UID to `.env` or determine from existing links. Not blocking for Phase 14 infrastructure — needed in Phase 15 for link creation.

3. **Will pytest-asyncio 0.25.x work with pytest 9.0.2?**
   - What we know: Both are recent versions
   - What's unclear: Exact compatibility
   - Recommendation: Pin versions in requirements.txt, test with `pip install` during implementation. If conflict, use latest compatible version.

## Sources

### Primary (HIGH confidence)
- Codebase analysis: `tests/e2e/conftest.py`, `tests/e2e/.venv/`, existing test files
- Codebase analysis: `includes/class-tp-api-handler.php` (AJAX handlers — no delete action)
- Codebase analysis: `includes/TrafficPortal/TrafficPortalApiClient.php` (API client — no delete method)
- Codebase analysis: `get-links.sh` (external API pattern with x-api-key)

### Secondary (MEDIUM confidence)
- pytest marker documentation (well-established feature, stable behavior)
- pytest-xdist session-scope behavior (known gotcha, widely documented)
- httpx async client patterns (standard library usage)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - established libraries with clear version compatibility
- Architecture: HIGH - patterns derived directly from existing codebase conventions
- Pitfalls: HIGH - based on codebase analysis (no delete handler) and known pytest-xdist behavior

**Research date:** 2026-03-22
**Valid until:** 2026-04-22 (stable domain, slow-moving dependencies)
