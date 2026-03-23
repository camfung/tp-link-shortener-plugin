# Technology Stack: Stress Testing & Bug Regression Suite

**Project:** Traffic Portal v2.3 -- Stress Test and Bug Regression
**Researched:** 2026-03-22
**Confidence:** HIGH

## Context: What Already Exists (Do Not Change)

The existing test infrastructure is Python + Playwright with pytest. All tests live in `tests/e2e/` and use:

| Technology | Current Usage | Notes |
|------------|---------------|-------|
| pytest | Test runner | Standard test discovery, fixtures, assertions |
| pytest-playwright | `0.7.2` | Browser automation via `sync_api` |
| Playwright (Python) | Chromium browser | `playwright.sync_api` -- Page, Browser, BrowserContext |
| conftest.py fixtures | `auth_context`, `page`, `client_links_page`, `usage_dashboard_page` | Session-scoped auth, per-test pages |
| .env file loading | Manual parsing in conftest.py | `TP_BASE_URL`, `TP_TEST_USER`, `TP_TEST_PASS` |

**Key pattern:** Tests use `sync_api` (not async). Auth is session-scoped (login once, reuse cookies). Each test gets a fresh page tab.

---

## Recommended Stack Additions

### 1. pytest-xdist -- Parallel Test Execution

| Field | Value |
|-------|-------|
| **Package** | `pytest-xdist` |
| **Version** | `>=3.8.0` |
| **Purpose** | Run Playwright tests in parallel across multiple CPU workers |
| **Why** | Creating 50 links sequentially would take 10+ minutes. With `-n 4` or `-n auto`, stress tests run 3-4x faster. Official Playwright docs recommend pytest-xdist for parallel Python tests. |
| **Confidence** | HIGH -- Playwright official docs endorse this, widely used in production |

**Usage:**
```bash
pytest tests/e2e/test_stress.py -n 4 --dist=loadgroup
```

**Integration concern:** The existing `auth_context` fixture is `scope="session"`. With xdist, each worker gets its own session, meaning each worker logs in independently. This is actually fine -- it provides proper isolation. No conftest changes needed for basic parallelism.

**What NOT to do:** Do not use `-n auto` with more than 4-6 workers for Playwright tests against a live WordPress site. Too many concurrent browser sessions will overwhelm the target server, causing false failures. Start with `-n 4`.

---

### 2. httpx -- Async HTTP Client for Usage Generation

| Field | Value |
|-------|-------|
| **Package** | `httpx` |
| **Version** | `>=0.28.1` |
| **Purpose** | Send bulk HTTP requests to generated short links to produce usage/click records |
| **Why** | Need to hit 50+ short links multiple times each to generate usage data. httpx supports both sync and async modes, has connection pooling, and a requests-compatible API. For 50 links x N hits each, async httpx with `asyncio.gather` handles this in seconds vs minutes with sync requests. |
| **Confidence** | HIGH -- stable, well-documented, verified on PyPI |

**Why httpx over aiohttp:** aiohttp is faster at extreme concurrency (10K+ requests), but httpx is simpler, has a familiar requests-like API, and handles our scale (50-500 requests) without issue. The team already uses Python's sync patterns; httpx's dual sync/async API lowers the learning curve. We do NOT need aiohttp's raw throughput for 50 links x 10 hits = 500 requests.

**Why httpx over stdlib urllib/requests:** `requests` has no async support. We need concurrent HTTP hits without blocking. httpx's `AsyncClient` with `asyncio.gather` handles this natively.

**Usage pattern:**
```python
import asyncio
import httpx

async def generate_usage(short_urls: list[str], hits_per_link: int = 10):
    async with httpx.AsyncClient(follow_redirects=True, timeout=30.0) as client:
        tasks = []
        for url in short_urls:
            for _ in range(hits_per_link):
                tasks.append(client.get(url))
        responses = await asyncio.gather(*tasks, return_exceptions=True)
    return responses
```

---

### 3. pytest-asyncio -- Async Test Support

| Field | Value |
|-------|-------|
| **Package** | `pytest-asyncio` |
| **Version** | `>=1.3.0` |
| **Purpose** | Enable `async def` test functions and async fixtures for httpx usage generation |
| **Why** | The usage generation step uses httpx's AsyncClient. pytest-asyncio lets us write async test functions that await the bulk HTTP calls, and create async fixtures for the httpx client. |
| **Confidence** | HIGH -- standard companion to httpx in pytest, verified on PyPI |

**Usage:**
```python
import pytest

@pytest.mark.asyncio
async def test_usage_generation_produces_records():
    async with httpx.AsyncClient() as client:
        responses = await asyncio.gather(*[client.get(url) for url in short_urls])
    assert all(r.status_code in (200, 301, 302) for r in responses)
```

**Integration note:** pytest-asyncio works alongside sync Playwright tests without conflict. Async tests are marked explicitly with `@pytest.mark.asyncio`. The existing sync tests remain unchanged.

---

## What NOT to Add

| Library | Why Not |
|---------|---------|
| **aiohttp** | Overkill for our scale (~500 requests). httpx covers it with simpler API. |
| **locust / k6 / artillery** | Full load-testing frameworks are too heavy. We need targeted stress tests within pytest, not a separate load-testing tool with its own reporting. |
| **jira (Python library)** | The Jira bug regression tests do NOT need to talk to Jira at runtime. We hardcode the 8 bug ticket IDs (TP-22, TP-25, etc.) and write regression tests that verify the fix. No API calls to Jira needed. |
| **selenium** | Already using Playwright. No reason to add a second browser automation tool. |
| **pytest-parallel** | Less maintained than pytest-xdist, fewer features, smaller community. |
| **requests** | No async support. httpx is a strict superset with async capabilities. |
| **faker / factory_boy** | Test data (link URLs, keywords) can be generated with simple string formatting. No need for a data generation library for 50 items. |

---

## Alternatives Considered

| Category | Recommended | Alternative | Why Not Alternative |
|----------|-------------|-------------|---------------------|
| HTTP client | httpx | aiohttp | httpx simpler API, sufficient for ~500 requests, dual sync/async |
| HTTP client | httpx | requests | No async support, would be sequential and slow |
| Parallel tests | pytest-xdist | pytest-parallel | xdist is the Playwright-endorsed option, better maintained |
| Load testing | pytest + httpx | locust | We want results inside pytest, not a separate dashboard |
| Async testing | pytest-asyncio | anyio/trio | asyncio is standard, no reason to add alternative event loops |

---

## Installation

```bash
# New dependencies for stress testing
pip install httpx>=0.28.1 pytest-asyncio>=1.3.0 pytest-xdist>=3.8.0

# Existing (already installed, do not change)
# pytest, pytest-playwright, playwright
```

**Suggested requirements file** (`tests/e2e/requirements.txt`):
```
pytest>=8.0
pytest-playwright>=0.7.2
pytest-xdist>=3.8.0
pytest-asyncio>=1.3.0
httpx>=0.28.1
```

---

## Integration Points

### Stress Test Flow (Playwright + httpx together)

1. **Phase 1 -- Link Creation (Playwright, parallel via xdist):** Playwright creates 50 short links via the WordPress UI. Each worker handles a batch. Created link URLs are written to a shared file (`tmp/stress_test_links.json`).

2. **Phase 2 -- Usage Generation (httpx async):** An async test reads the links file and fires bulk HTTP requests. This step does NOT need Playwright -- it is pure HTTP.

3. **Phase 3 -- Verification (Playwright):** Playwright navigates to `/usage-dashboard` and verifies the usage data matches expected counts.

**Sequencing:** These phases must run in order. Use pytest markers or separate test files with explicit ordering:
```bash
# Run in sequence
pytest tests/e2e/test_stress_create.py -n 4 && \
pytest tests/e2e/test_stress_usage.py && \
pytest tests/e2e/test_stress_verify.py
```

### Bug Regression Tests (Playwright only)

The 8 Jira bug regression tests are standard Playwright E2E tests. They use the existing `conftest.py` fixtures with no new dependencies. Each test verifies a specific bug fix (TP-22, TP-25, TP-29, TP-34, TP-41, TP-46, TP-71, TP-94).

No stack additions needed for regression tests.

---

## Sources

- [Playwright Python -- Pytest Plugin Reference](https://playwright.dev/python/docs/test-runners) -- Official parallel testing guidance
- [pytest-xdist on PyPI](https://pypi.org/project/pytest-xdist/) -- v3.8.0, July 2025
- [httpx on PyPI](https://pypi.org/project/httpx/) -- v0.28.1, December 2024
- [pytest-asyncio on PyPI](https://pypi.org/project/pytest-asyncio/) -- v1.3.0, November 2025
- [HTTPX Async Support](https://www.python-httpx.org/async/) -- AsyncClient documentation
- [Parallelize Playwright with Xdist](https://testingwithedd.github.io/testing/playwright/2024/07/05/parallelize-playwright-with-xdist.html) -- Practical guide
- [HTTPX vs Requests vs AIOHTTP comparison](https://proxywing.com/blog/httpx-vs-requests-vs-aiohttp-feature-performance-comparison-guide) -- Performance benchmarks
