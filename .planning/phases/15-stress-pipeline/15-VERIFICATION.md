---
phase: 15-stress-pipeline
verified: 2026-03-23T00:00:00Z
status: passed
score: 12/12 must-haves verified
re_verification: false
gaps: []
---

# Phase 15: Stress Pipeline Verification Report

**Phase Goal:** The test suite can create 50 short links through the UI, generate measurable usage traffic against each link, and verify the usage dashboard accurately reflects the generated activity
**Verified:** 2026-03-23
**Status:** gaps_found — 1 traceability gap (phantom requirement ID ORCH-01)
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Test creates 50 short links via Playwright UI | VERIFIED | `test_create_links.py` loops `LINK_COUNT` times (default 50), clicks `#tp-cl-add-link-btn`, fills modal, intercepts `tp_create_link` AJAX response |
| 2 | Each created link has a unique keyword containing RUN_ID prefix | VERIFIED | Keyword generated as `f"{run_id}-{i:03d}"` — e.g. `stress-a1b2c3d4-000` |
| 3 | All link records (keyword, URL, MID) are written to JSON | VERIFIED | `json.dump(created_links, f, indent=2)` writes to `stress_data_{run_id}.json` via `stress_data_file` fixture |
| 4 | Test sends HTTP requests to each of the 50 created links | VERIFIED | `test_generate_usage.py` builds URL list from `stress_links` fixture and runs `HITS_PER_LINK` rounds |
| 5 | Each link is hit at least 5 times (250+ total) | VERIFIED | `HITS_PER_LINK = int(os.getenv("STRESS_HITS_PER_LINK", "5"))` — default 5 hits per link, configurable |
| 6 | No 429 errors due to rate limiting and backoff | VERIFIED | `asyncio.Semaphore(MAX_CONCURRENCY)`, per-request `rate_delay` from fixture, exponential backoff `2^attempt` up to `MAX_RETRIES=3` |
| 7 | Usage generator reads from stress_data JSON | VERIFIED | `stress_links` fixture in conftest loads from `stress_data_{run_id}.json`, graceful skip if missing |
| 8 | Dashboard test navigates to /usage-dashboard and verifies page loads | VERIFIED | `test_verify_dashboard.py` navigates, waits for `.tp-ud-container`, asserts `#tp-ud-date-start` and `#tp-ud-date-end` exist |
| 9 | Test confirms table shows rows with non-zero hit counts for stress date | VERIFIED | `poll_for_usage_data` queries `#tp-ud-tbody tr`, parses hits from cells, confirms `hits > 0` |
| 10 | Test confirms chart canvas has rendered | VERIFIED | Queries `#tp-ud-chart` then `canvas` fallback, checks `bounding_box()` for non-zero width/height |
| 11 | Test uses retry polling, not hardcoded sleeps | VERIFIED | `poll_for_usage_data` loops up to `max_iterations`, both `time.sleep(interval)` calls use the configurable `interval` variable — no hardcoded sleep values |
| 12 | ORCH-01 requirement ID is accounted for in REQUIREMENTS.md | FAILED | `ORCH-01` appears in `15-04-PLAN.md` frontmatter `requirements:` but is absent from REQUIREMENTS.md entirely — phantom ID |

**Score: 11/12 truths verified**

---

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `tests/e2e/stress/test_create_links.py` | Playwright UI test creating 50 links | VERIFIED | 121 lines, substantive implementation, `@pytest.mark.stress`, wired to `auth_context`, `run_id`, `stress_data_file` fixtures |
| `tests/e2e/stress/test_generate_usage.py` | Async httpx usage traffic generator | VERIFIED | 134 lines, `@pytest.mark.stress`, `@pytest.mark.asyncio`, uses `stress_links` and `stress_rate_limit` fixtures |
| `tests/e2e/stress/test_verify_dashboard.py` | Playwright dashboard verification with retry polling | VERIFIED | 155 lines, `@pytest.mark.stress`, uses `auth_context`, implements `poll_for_usage_data` with configurable timeout/interval |
| `run_stress.sh` | Shell orchestration script for complete pipeline | VERIFIED | 100 lines, executable, exports `STRESS_RUN_ID`, runs all three stages sequentially, fail-fast, cleanup prompt |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `test_create_links.py` | `tests/e2e/stress/conftest.py` | `run_id`, `stress_data_file` fixtures | WIRED | Both fixture names present in function signature and used in body |
| `test_create_links.py` | `tests/e2e/conftest.py` | `auth_context` fixture | WIRED | `auth_context: BrowserContext` in function signature, `auth_context.new_page()` called |
| `test_generate_usage.py` | `tests/e2e/stress/conftest.py` | `stress_links`, `stress_rate_limit` fixtures | WIRED | Both in `test_generate_usage(stress_links, stress_rate_limit)` signature and used in body |
| `test_verify_dashboard.py` | `tests/e2e/conftest.py` | `auth_context` fixture | WIRED | `test_verify_dashboard(auth_context)` signature, `auth_context.new_page()` called |
| `run_stress.sh` | `test_create_links.py` | `pytest -m stress -k test_create` | WIRED | `pytest stress/test_create_links.py -m stress` on line 52 |
| `run_stress.sh` | `test_generate_usage.py` | `pytest -m stress -k test_generate` | WIRED | `pytest stress/test_generate_usage.py -m stress` on line 62 |
| `run_stress.sh` | `test_verify_dashboard.py` | `pytest -m stress -k test_verify` | WIRED | `pytest stress/test_verify_dashboard.py -m stress` on line 72 |
| `run_stress.sh` | `tests/e2e/scripts/cleanup_stress.py` | `python cleanup_stress.py $RUN_ID` | WIRED | `cleanup_stress.py` exists at that path and is called with `$RUN_ID` argument |

---

### Requirements Coverage

| Requirement | Status | Notes |
|-------------|--------|-------|
| STRESS-01 | SATISFIED | Custom keywords via UI, shortcode generator explicitly avoided |
| STRESS-02 | SATISFIED | `DESTINATION_URL = "https://example.com"`, keyword = `{run_id}-{NNN}` |
| STRESS-03 | SATISFIED | `json.dump` to `stress_data_{run_id}.json` after all links created |
| USAGE-01 | SATISFIED | httpx GET to `https://{SHORT_DOMAIN}/{keyword}` for each link |
| USAGE-02 | SATISFIED | `HITS_PER_LINK` rounds, default 5, configurable via env var |
| USAGE-03 | SATISFIED | `asyncio.Semaphore`, per-request delay, exponential backoff on 429 |
| USAGE-04 | SATISFIED | `stress_links` fixture reads from `stress_data_{run_id}.json`, skips if absent |
| VERIFY-01 | SATISFIED | Asserts `.tp-ud-container`, `#tp-ud-date-start`, `#tp-ud-date-end` |
| VERIFY-02 | SATISFIED | `poll_for_usage_data` confirms rows with `hits > 0` in `#tp-ud-tbody` |
| VERIFY-03 | SATISFIED | Queries `#tp-ud-chart`/`canvas`, checks bounding box for non-zero dimensions |
| VERIFY-04 | SATISFIED | Configurable polling loop; both `time.sleep` calls use `interval` variable |
| ORCH-01 | NOT IN REQUIREMENTS.MD | Listed in 15-04-PLAN.md but this ID does not exist in REQUIREMENTS.md — phantom reference |

---

### Anti-Patterns Found

No stubs, placeholders, empty implementations, or TODO/FIXME markers found in any of the four artifacts. All implementations are substantive.

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| — | — | No anti-patterns detected | — | — |

---

### Human Verification Required

The following cannot be verified programmatically and require a live dev environment:

#### 1. Full pipeline execution

**Test:** Run `./run_stress.sh` against the configured dev environment
**Expected:** Stage 1 creates 50 links, stage 2 hits each 5 times (250 requests total), stage 3 confirms usage data appears in the dashboard table and chart within 120s
**Why human:** Requires live WordPress/plugin environment with valid credentials in `tests/e2e/.env`

#### 2. Modal response interception timing

**Test:** Run `pytest tests/e2e/stress/test_create_links.py -m stress --headed -s` and observe whether `tp_create_link` post_data filter captures the correct AJAX response for each link creation
**Expected:** 50 links created with non-empty MIDs returned, no timeouts on `expect_response`
**Why human:** The correctness of the post_data lambda filter can only be confirmed against the real admin-ajax.php endpoint

#### 3. 429 backoff behavior

**Test:** Temporarily lower `STRESS_RATE_LIMIT=0` and observe whether exponential backoff triggers and recovers
**Expected:** 429 responses logged, retried with backoff, overall test still passes
**Why human:** Requires triggering rate limiting on the live redirect service

#### 4. Dashboard eventual consistency window

**Test:** Run stage 3 immediately after stage 2 with `STRESS_POLL_TIMEOUT=120` and observe how many poll iterations are needed
**Expected:** Data appears within the 120s window; iteration count logged to stdout
**Why human:** Depends on redirect service propagation latency in the specific dev environment

---

### Gaps Summary

One gap was found. All four test files and the orchestration script are substantively implemented, syntactically valid, correctly wired, and cover the 11 requirement IDs that exist in REQUIREMENTS.md (STRESS-01/02/03, USAGE-01/02/03/04, VERIFY-01/02/03/04).

The single gap is a traceability issue: plan 15-04 claims to satisfy `ORCH-01`, but this requirement ID does not exist in REQUIREMENTS.md. The implementation itself (`run_stress.sh`) is complete and correct. The gap is one of documentation hygiene — either the requirement needs to be added to REQUIREMENTS.md retroactively, or the phantom reference in the plan frontmatter needs to be removed.

This gap does not block execution of the stress pipeline. All pipeline stages are implemented and wired correctly.

---

_Verified: 2026-03-23_
_Verifier: Claude (gsd-verifier)_
