# Feature Landscape: Stress Testing & Bug Regression Suite

**Domain:** Automated testing -- stress testing (bulk link creation + usage generation) and Jira bug regression
**Researched:** 2026-03-22
**Overall Confidence:** HIGH (existing Playwright + pytest infrastructure in the repo, well-understood patterns from 11 existing e2e test files)

---

## Context

This research covers the **v2.3 Stress Test and Bug Regression milestone** -- validating plugin reliability through bulk link creation, usage generation via HTTP traffic, and regression tests for 8 Jira bugs.

**Existing test infrastructure (confirmed from codebase):**
- Python/Playwright e2e tests in `tests/e2e/` (11 test files)
- `conftest.py` with session-scoped auth, `.env`-driven config (BASE_URL, credentials)
- Existing fixtures: `auth_context`, `page`, `client_links_page`, `usage_dashboard_page`
- PHPUnit tests in `tests/Unit/` and `tests/Integration/`
- Vitest for JS unit tests (`vitest.config.js`, `url-validator.test.js`)
- `.env.test` with `TP_API_ENDPOINT` and `API_KEY` for direct API calls

**Target:** 50 links created via Playwright UI, each hit multiple times via HTTP to produce usage records, then verify usage dashboard shows correct data. Plus 8 regression tests for specific Jira bugs.

---

## Table Stakes

Features that are required for this milestone. Missing = milestone incomplete.

| Feature | Why Expected | Complexity | Dependencies |
|---------|--------------|------------|-------------|
| Bulk link creation script (50 links) | Core stress test requirement per PROJECT.md | Med | `client_links_page` fixture, Add Link modal |
| Unique keyword generation per link | Links need unique tpKeys to avoid collisions | Low | Timestamp/UUID-based naming pattern |
| Usage generation via HTTP hits | Must produce real usage records for dashboard verification | Med | Created links must resolve (active status), HTTP client |
| Usage dashboard data verification | Must confirm dashboard correctly reports stress test traffic | Med | `usage_dashboard_page` fixture, AJAX data fetch |
| Test data cleanup strategy | 50 links pollute the test account if not managed | Low | Either dedicated test user or naming convention for manual cleanup |
| Regression test: TP-22 | Bug must be covered to prevent recurrence | Low-Med | Depends on bug specifics (needs Jira lookup) |
| Regression test: TP-25 | Bug must be covered to prevent recurrence | Low-Med | Depends on bug specifics (needs Jira lookup) |
| Regression test: TP-29 | Bug must be covered to prevent recurrence | Low-Med | Depends on bug specifics (needs Jira lookup) |
| Regression test: TP-34 | Bug must be covered to prevent recurrence | Low-Med | Depends on bug specifics (needs Jira lookup) |
| Regression test: TP-41 | Bug must be covered to prevent recurrence | Low-Med | Depends on bug specifics (needs Jira lookup) |
| Regression test: TP-46 | Bug must be covered to prevent recurrence | Low-Med | Depends on bug specifics (needs Jira lookup) |
| Regression test: TP-71 | Bug must be covered to prevent recurrence | Low-Med | Depends on bug specifics (needs Jira lookup) |
| Regression test: TP-94 | Umbrella ticket -- may need sub-task extraction | Med | Sub-task analysis from Jira |

---

## Feature Details

### 1. Bulk Link Creation Script (Stress Test)

**What it does:** Automates creation of 50 short links through the Playwright UI by repeatedly opening the "Add a link" modal, filling the form, and submitting.

**Expected behavior pattern:**
- Login once (reuse `auth_context` session fixture)
- Navigate to client links page
- Loop 50 times:
  1. Click "Add a link" button (`#tp-cl-add-link-btn`)
  2. Fill destination URL (can be a fixed URL like `https://example.com` or varied)
  3. Fill custom keyword with unique value (e.g., `stress-test-001` through `stress-test-050`)
  4. Submit the form
  5. Wait for success snackbar confirmation
  6. Close modal or wait for auto-close
- Collect created link URLs for the usage generation phase
- Assert all 50 links appear in the table (may need pagination check)

**Complexity:** Medium -- the modal interaction is well-understood from existing `TestAddLinkModal` and `TestEditModal` tests. The challenge is reliable iteration without flaky failures on 50 repetitions.

**Key considerations:**
- Rate limiting: The API may throttle rapid creation. Add small delays between creations (0.5-1s).
- Shortcode generation: The `/generate-short-code/{tier}` API has known issues (BUG doc shows 500 errors). Using custom keywords bypasses this.
- Form field IDs: `#tp-custom-key` for keyword, destination URL input, `#tp-submit-btn` for save.
- Success detection: Watch for snackbar with "successfully" text.
- Failure handling: Log which links fail, continue creating remaining links, assert minimum success threshold.

### 2. Usage Generation via HTTP Hits

**What it does:** Sends HTTP GET requests to each of the 50 created short links to generate real click/usage records in the backend.

**Expected behavior pattern:**
- For each created link URL (e.g., `https://dev.trfc.link/stress-test-001`):
  1. Send N HTTP GET requests (follow redirects to confirm link works)
  2. Vary request count per link for realistic distribution (e.g., 1-10 hits each)
  3. Space requests to avoid triggering rate limits or DDoS protection
- Total expected hits: 50 links x ~5 avg hits = ~250 HTTP requests
- Do NOT use Playwright for this -- use `requests` or `httpx` library for speed
- Record expected hit counts per link for later verification

**Complexity:** Medium -- straightforward HTTP calls, but needs to handle redirects, timeouts, and potential rate limiting. Also needs to account for async processing lag (usage records may not appear immediately).

**Key considerations:**
- Redirect behavior: Short links redirect (301/302) to destination. Follow redirects to confirm the link works, but the click is recorded on the initial request.
- Timing: Usage records may take seconds to minutes to appear in the API. Build in a wait period before verification.
- Concurrency: Can use `asyncio`/`httpx.AsyncClient` to parallelize hits, but be careful about overwhelming the server.
- User-Agent: Vary or set a test User-Agent so test traffic is identifiable.

### 3. Usage Dashboard Verification

**What it does:** After stress test traffic is generated, navigates to the usage dashboard and verifies the data is correctly displayed.

**Expected behavior pattern:**
- Navigate to `/usage-dashboard/`
- Wait for skeleton to disappear and content to load
- Set date range to include today (the stress test date)
- Verify:
  1. Summary strip shows non-zero hit count
  2. Table has a row for today's date with hits >= expected count
  3. Chart renders data points for the date range
  4. Hit cost values are non-negative
  5. Balance reflects usage deductions
- Compare displayed data against known generated traffic counts

**Complexity:** Medium -- builds on existing `test_usage_dashboard.py` patterns. The tricky part is timing (data availability) and exact count matching (usage might include pre-existing traffic).

**Key considerations:**
- Data lag: The Traffic Portal API may have processing delays. May need retry logic or generous timeouts.
- Pre-existing data: The test user may have existing usage. Verify incremental increase rather than exact counts.
- Date filtering: Use today's date range to isolate stress test data.

### 4. Jira Bug Regression Tests

**What it does:** Automated tests that verify each of the 8 Jira bugs remains fixed. Each test reproduces the original bug scenario and asserts the correct (fixed) behavior.

**Expected behavior pattern per regression test:**
1. Set up preconditions (navigate to relevant page, create test data if needed)
2. Reproduce the exact steps that triggered the original bug
3. Assert the FIXED behavior (not the buggy behavior)
4. Include the Jira ticket ID in the test name and docstring for traceability

**Bug ticket details -- LOW confidence (need Jira lookup to confirm specifics):**

| Ticket | Likely Area | Test Approach |
|--------|-------------|---------------|
| TP-22 | Unknown -- needs Jira lookup | Playwright e2e or API-level test |
| TP-25 | Unknown -- needs Jira lookup | Playwright e2e or API-level test |
| TP-29 | Unknown -- needs Jira lookup | Playwright e2e or API-level test |
| TP-34 | Unknown -- needs Jira lookup | Playwright e2e or API-level test |
| TP-41 | Unknown -- needs Jira lookup | Playwright e2e or API-level test |
| TP-46 | Unknown -- needs Jira lookup | Playwright e2e or API-level test |
| TP-71 | Unknown -- needs Jira lookup | Playwright e2e or API-level test |
| TP-94 | Umbrella ticket (per STATE.md) | May decompose into multiple tests |

**Note:** TP-94 is flagged as an umbrella ticket in STATE.md. It likely needs sub-task extraction before regression tests can be written. This is a research gap that must be resolved during the implementation phase by reading the actual Jira tickets.

**Existing regression test example:** `test_edit_empty_keyword.py` (for TP-103) shows the established pattern -- reproduce the bug steps, assert correct behavior, reference ticket ID in docstring.

---

## Differentiators

Features that add value beyond the minimum requirements.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Test result reporting with timing data | Shows how long each link creation takes, identifies performance degradation | Low | Log timestamps, compute avg/p95/max |
| Screenshot capture on failure | Enables debugging without re-running -- Playwright supports this natively | Low | `page.screenshot()` in pytest failure hooks |
| Parallel link creation (batched) | Speeds up 50-link creation from ~5min to ~1min using multiple browser contexts | High | Complex state management, may hit API limits |
| CSV/JSON test data export | Persist created link IDs and hit counts for post-hoc analysis | Low | Write to `tests/e2e/results/` directory |
| Configurable link count | Allow N links instead of hardcoded 50 via env var or CLI arg | Low | `TP_STRESS_LINK_COUNT` env var, default 50 |
| Pre-test cleanup | Delete any leftover stress test links before starting | Med | Requires API delete endpoint or UI deletion |
| Link creation via API bypass | Skip UI for speed -- create links directly via Traffic Portal API | Med | Faster but doesn't test UI flow |

---

## Anti-Features

Features to explicitly NOT build.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Load testing / concurrent users | This is stress testing a single user flow, not load testing the infrastructure. Load testing requires different tools (k6, Locust) and is out of scope. | Stick to sequential single-user stress test |
| Automated Jira ticket status updates | Adds coupling to Jira API, fragile, not needed for test suite | Reference ticket IDs in test names/docstrings only |
| Browser-based usage generation | Using Playwright to visit each link 5x is extremely slow and wasteful | Use `requests`/`httpx` for HTTP hits (no browser needed) |
| Cross-browser stress testing | Running 50-link creation in Firefox/WebKit too adds no value for this milestone | Chromium only (already the default in conftest.py) |
| Production environment testing | Stress testing on prod could impact real users and billing | Always target dev environment (`trafficportal.dev`) |
| Retry-until-pass test patterns | Flaky test masking -- retries hide real failures | Fix the root cause of flakiness, use explicit waits |
| Link deletion after test | Deleting stress test links removes evidence for manual verification | Use naming convention (`stress-test-*`) for easy filtering/cleanup later |

---

## Feature Dependencies

```
Authentication (existing) --> Bulk Link Creation --> Usage Generation --> Dashboard Verification
                                |                        |
                                v                        v
                           Link URLs collected     Hit counts recorded
                                                        |
                                                        v
                                                  Dashboard Verification
                                                  (compare expected vs actual)

Jira Bug Research (ticket lookup) --> Individual Regression Tests (independent of stress test)
```

**Critical path:** Link creation MUST complete before usage generation. Usage generation MUST complete (with processing delay) before dashboard verification.

**Independent:** Regression tests for Jira bugs are completely independent of the stress test and can be built in parallel.

---

## MVP Recommendation

**Phase 1 -- Stress Test Pipeline (sequential, must be ordered):**
1. Bulk link creation script (50 links with unique keywords)
2. Usage generation script (HTTP hits to each created link)
3. Usage dashboard verification test

**Phase 2 -- Bug Regression Suite (independent, can be parallelized):**
4. Research all 8 Jira tickets (read ticket details from Jira)
5. Write regression tests for each bug

**Defer:**
- Parallel link creation: High complexity, low necessity for 50 links
- API-bypass link creation: Doesn't test the UI path which is what we want to stress
- Test data export: Nice-to-have, can add later if needed
- Pre-test cleanup: Use naming convention instead

---

## File Organization Recommendation

Based on existing test structure in `tests/e2e/`:

```
tests/e2e/
  conftest.py                           # Existing -- add stress test fixtures
  test_stress_link_creation.py          # 50-link creation test
  test_stress_usage_generation.py       # HTTP hit generation
  test_stress_dashboard_verification.py # Dashboard data validation
  test_regression_tp22.py              # One file per bug ticket
  test_regression_tp25.py
  test_regression_tp29.py
  test_regression_tp34.py
  test_regression_tp41.py
  test_regression_tp46.py
  test_regression_tp71.py
  test_regression_tp94.py              # May have multiple test classes
```

**Alternative:** Group regression tests into a single file `test_regression_bugs.py` with one class per ticket. This is simpler for 8 bugs but makes it harder to run individual ticket tests.

**Recommendation:** One file per ticket because it matches the existing pattern (`test_edit_empty_keyword.py` for TP-103) and allows `pytest -k tp22` filtering.

---

## Sources

- Codebase analysis: `tests/e2e/conftest.py`, `test_client_links.py`, `test_usage_dashboard.py`, `test_edit_empty_keyword.py`
- Project requirements: `.planning/PROJECT.md` (v2.3 milestone definition)
- Project state: `.planning/STATE.md` (TP-94 umbrella ticket note)
- Bug documentation: `docs/BUG-shortcode-generation-failing.md` (shortcode API 500 issues)
- API scripts: `get-links.sh`, `get-usage.sh` (API endpoint patterns)
- Confidence: HIGH for stress test patterns (well-understood from codebase). LOW for individual Jira bug details (tickets not yet read from Jira).
