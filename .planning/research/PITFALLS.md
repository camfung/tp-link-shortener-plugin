# Domain Pitfalls: Stress Testing & Bug Regression Suite (v2.3)

**Domain:** Adding stress tests (50 link creation, bulk usage generation) and Jira bug regression tests to existing WordPress link shortener plugin
**Researched:** 2026-03-22
**Confidence:** HIGH for API throttling and test isolation (based on codebase inspection of existing rate limiting, API handler, and e2e test patterns); MEDIUM for Playwright-specific stress testing pitfalls (based on existing test suite patterns + known Playwright behaviors)

---

## Critical Pitfalls

Mistakes that cause test suites to be unreliable, produce false results, or damage production data.

### Pitfall 1: API Rate Limiting Kills Stress Test Mid-Run

**What goes wrong:**
The stress test creates 50 links in rapid succession via Playwright. Each link creation triggers `ajax_create_link` which calls the Traffic Portal Lambda API (`POST /items`). The API returns HTTP 429 when rate limits are exceeded -- the plugin already handles this via `RateLimitException` (see `includes/TrafficPortal/Exception/RateLimitException.php`). At 50 sequential link creations with no delay, the API will almost certainly start returning 429s after 10-20 requests, causing the stress test to fail partway through and report false negatives.

**Why it happens:**
Developers write the stress test as a simple loop -- "click Add Link, fill form, submit, repeat 50 times" -- without accounting for the API's rate limiting on the `/items` endpoint. The API is hosted on AWS API Gateway which has default throttling of 10,000 requests/second at the account level, but custom per-route limits may be much lower (commonly 5-50 requests/second for write endpoints on dev environments).

**Consequences:**
- Test fails at link 15-25 with "rate limit exceeded" error, never completing the full 50
- Intermittent failures: sometimes passes (low server load), sometimes fails (high load)
- False conclusion that the plugin is broken when the API is just throttling correctly
- If the test retries without backoff, it worsens the throttling window

**Prevention:**
Add a configurable delay between link creations. Start with 1-2 seconds between each creation. Use exponential backoff if a 429 is detected:

```python
import time

LINK_CREATION_DELAY = float(os.getenv("TP_STRESS_DELAY", "1.5"))  # seconds

for i in range(50):
    create_link(page, destination=f"https://example.com/page-{i}", key=f"stress-{i}")
    if i < 49:  # no delay after last one
        time.sleep(LINK_CREATION_DELAY)
```

Also: check the response for 429 status and implement retry logic in the test itself, separate from the plugin's own error handling. The test should detect rate limiting and slow down, not just fail.

**Detection:**
- Test output shows "rate limit exceeded" or error_type "rate_limit" in responses
- Test passes locally but fails in CI (different IP, different rate limit bucket)
- Inconsistent number of links created across runs

**Phase:** Must be addressed in the stress test script design phase. Build the delay/backoff into the test from day one.

---

### Pitfall 2: Test Data Pollutes Production/Staging Environment

**What goes wrong:**
The stress test creates 50 real links on the dev site (`trafficportal.dev`). The usage generation phase then hits each link multiple times, creating real usage records in the API's database. After the test completes, these 50 test links and hundreds of usage records remain in the system permanently. The test user's dashboard now shows 50 junk links mixed with legitimate data, pagination changes, charts look different, and the usage dashboard shows inflated costs/balance.

**Why it happens:**
The existing e2e test suite (see `tests/e2e/conftest.py`) uses a shared authenticated session against the live dev site. There is no test data isolation -- tests read from and write to the same database as manual testing. The existing tests are read-only (they verify UI elements exist) so this was never a problem before. The stress test is the first test that creates significant write data.

**Consequences:**
- Test user's dashboard becomes unusable for manual QA (cluttered with stress test links)
- Usage dashboard shows inflated balance/cost data from test traffic
- Subsequent test runs conflict with previous test data (duplicate keys, wrong total counts)
- No way to distinguish test data from real data after the fact
- Running the stress test twice doubles the pollution

**Prevention:**
Use a deterministic naming convention for test data AND implement cleanup:

```python
STRESS_PREFIX = "stress-test-"  # all test links use this prefix

# Cleanup: after test (or before next run), delete all links with this prefix
# Option A: Use the API directly to delete test links
# Option B: Use a dedicated test user that can be reset
# Option C: Tag test links with metadata and filter them out
```

Practical approach for this project: Since the API has `DELETE /items/{mid}` capability (or status toggle to "disabled"), store the MIDs of created links during the test and delete/disable them in a teardown fixture. At minimum, use a unique prefix per run (e.g., `stress-{timestamp}-{i}`) so old test data is identifiable.

**Detection:**
- Test user's link count keeps growing across test runs
- Dashboard pagination changes unexpectedly between manual QA sessions
- Usage dashboard shows costs that do not match manual testing

**Phase:** Must be designed into the stress test from the start. Cleanup logic in teardown fixture.

---

### Pitfall 3: Shortcode Generation Exhaustion Under Stress

**What goes wrong:**
The plugin calls `/generate-short-code/{tier}` to auto-generate shortcodes when no custom key is provided. This API endpoint already has a known issue -- it returns 500 "Could not find available short code" (documented in `docs/BUG-shortcode-generation-failing.md`). Under stress testing with 50 links, this endpoint will be hammered 50 times. If it is already fragile, it will fail even more under load, and the stress test will produce 50 error results instead of 50 links.

**Why it happens:**
The shortcode generation API has a finite pool of available codes per domain. The `dev.trfc.link` domain may have limited availability. Each call tries to find an unused code, and under rapid sequential calls, the API may be checking the same pool state before previous writes have propagated (race condition in the backend).

**Consequences:**
- Stress test creates 0 links because every shortcode generation fails
- Test appears to show a critical bug when it is actually an API capacity issue
- Wastes test run time waiting for 50 API timeouts

**Prevention:**
For the stress test, always provide explicit custom keys rather than relying on auto-generation:

```python
# BAD: Let the plugin auto-generate (hits fragile /generate-short-code API)
create_link(page, destination=url)

# GOOD: Provide deterministic custom keys
create_link(page, destination=url, custom_key=f"stress-{run_id}-{i:03d}")
```

This also makes cleanup easier (predictable key names) and avoids the known shortcode generation bug entirely. The stress test's goal is to test link creation volume, not shortcode generation.

**Detection:**
- All or most link creations fail with "Could not find available short code"
- Error logs show 500 responses from `/generate-short-code/fast`

**Phase:** Stress test script design. Use explicit keys.

---

### Pitfall 4: Playwright Browser Resource Exhaustion During 50-Link Creation

**What goes wrong:**
Creating 50 links via Playwright means 50 cycles of: open modal, fill form, submit, wait for response, close modal, verify table update. Each cycle keeps the browser context alive and accumulates DOM state. After 30-40 iterations, the browser tab's memory usage grows significantly (the client links table now has 30-40 rows of DOM elements), JavaScript event listeners pile up, and Playwright's page operations slow down or timeout.

**Why it happens:**
The client links dashboard renders all links in a paginated table. As links are created, the table re-renders via AJAX. By link 30, the DOM has grown substantially. Additionally, Playwright's internal state tracking (for `expect()` assertions, network interception, etc.) grows with each action. The default Playwright timeout of 30 seconds may not be enough for later iterations when the page is sluggish.

**Consequences:**
- Test times out at link 35-40 with `TimeoutError: page.click`
- Memory leak causes browser crash, losing all test state
- Intermittent failures that are impossible to reproduce locally (different hardware)

**Prevention:**
1. Increase Playwright timeouts for the stress test specifically (not globally):
```python
page.set_default_timeout(60_000)  # 60s for stress test only
```

2. Consider refreshing the page every 10-15 link creations to clear accumulated DOM:
```python
if i > 0 and i % 15 == 0:
    page.reload()
    page.wait_for_selector(".tp-cl-container", timeout=10_000)
```

3. Do NOT assert on every single creation. Create all 50, then verify the total count once at the end. Per-creation assertions multiply the DOM queries by 50.

4. Set a generous overall test timeout:
```python
@pytest.mark.timeout(600)  # 10 minutes for the full stress test
def test_create_50_links(page):
    ...
```

**Detection:**
- Tests pass for first 20 links, then start timing out
- CI runner kills test due to memory limit
- Local runs succeed but CI fails (CI has less memory)

**Phase:** Stress test script design. Build in page refresh cycles and appropriate timeouts.

---

## Moderate Pitfalls

### Pitfall 5: Usage Generation Hits Wrong Links or Cached Redirects

**What goes wrong:**
The usage generation phase sends HTTP requests to each created short link (e.g., `https://dev.trfc.link/stress-001`) to produce usage records. But if the short links redirect via 301 (permanent redirect), the HTTP client caches the redirect and never actually hits the Traffic Portal tracking endpoint on subsequent requests. Or if the domain is behind Cloudflare, Cloudflare caches the redirect response and the tracking API never sees the hits.

**Prevention:**
Use `requests` with `allow_redirects=False` to ensure each request hits the tracking endpoint without following the redirect. This guarantees the API logs the hit:

```python
import requests

for link_url in created_links:
    for _ in range(hit_count):
        resp = requests.get(link_url, allow_redirects=False)
        assert resp.status_code in (301, 302), f"Expected redirect, got {resp.status_code}"
        time.sleep(0.5)  # avoid overwhelming the tracking endpoint
```

Also: disable Cloudflare caching for the test domain, or use a cache-busting query parameter if the API supports it.

**Detection:**
- Usage dashboard shows 0 hits despite running the usage generation script
- All requests return 200 (following redirect to destination) instead of 301/302

---

### Pitfall 6: Nonce Expiration During Long Stress Test Runs

**What goes wrong:**
WordPress AJAX nonces (`tp_link_shortener_nonce`) expire after 12-24 hours by default. A stress test creating 50 links with delays between each creation can run for several minutes. This is not long enough to expire the nonce. BUT if the test suite runs the stress test after a long setup phase, or if the authenticated session (from `conftest.py`'s `auth_context` with `scope="session"`) was created at the start of a multi-hour test suite run, the nonce embedded in the page's initial HTML may have expired by the time the stress test reaches it.

**Prevention:**
Reload the client links page before starting the stress test to get a fresh nonce:

```python
@pytest.fixture()
def stress_page(page: Page):
    """Navigate to client links page with fresh nonce for stress testing."""
    page.goto(f"{BASE_URL}{CLIENT_LINKS_PATH}")
    page.wait_for_selector(".tp-cl-container", timeout=10_000)
    return page
```

Also: if individual link creation fails with a nonce error, refresh the page and retry once before marking as failure.

**Detection:**
- AJAX responses return `{"success": false}` with no error message (WordPress nonce failure returns generic error)
- First few links create successfully, then all subsequent ones fail

---

### Pitfall 7: Regression Tests Tightly Coupled to Current UI State

**What goes wrong:**
Bug regression tests verify that specific Jira bugs (TP-22, TP-25, TP-29, etc.) are fixed. The temptation is to write these tests against exact CSS selectors, exact text content, or exact DOM structure as it exists today. When the UI is updated in a future milestone (e.g., v3.0 redesign), all 8 regression tests break even though the bugs remain fixed. The test suite becomes a maintenance burden that nobody wants to update.

**Prevention:**
Write regression tests that verify the behavior, not the implementation:

```python
# BAD: Coupled to exact DOM structure
def test_tp22_edit_empty_keyword():
    assert page.locator("#tp-cl-edit-modal .tp-cl-edit-keyword-input").input_value() != ""

# GOOD: Coupled to behavior
def test_tp22_edit_preserves_keyword():
    """TP-22: Editing a link should preserve the existing keyword, not blank it."""
    # Click first link row to open edit
    page.locator("[data-testid='link-row']").first.click()
    # Wait for edit modal
    modal = page.locator("[data-testid='edit-modal']")
    modal.wait_for(state="visible")
    # The keyword field should not be empty
    keyword_input = modal.locator("input[name='keyword'], [data-testid='keyword-input']")
    assert keyword_input.input_value().strip() != "", "TP-22 regression: keyword blanked on edit"
```

Use `data-testid` attributes where possible. Add them to the plugin's HTML if they do not exist. This decouples tests from CSS class names that may change.

**Detection:**
- Multiple regression tests fail after a CSS refactor even though no functionality changed
- Tests use deeply nested CSS selectors (`.tp-cl-container > div:nth-child(3) > table > tbody > tr`)

---

### Pitfall 8: Flaky Tests from Race Conditions in AJAX-Heavy Dashboard

**What goes wrong:**
The client links dashboard loads data via AJAX (`wp_ajax_tp_get_links`). After creating a link, the table refreshes via AJAX. If the test immediately checks the table for the new link before the AJAX response arrives and the DOM updates, the assertion fails intermittently. This is the classic Playwright flakiness problem: the test runs faster than the UI updates.

**Prevention:**
After any action that triggers an AJAX call, wait for the specific result rather than using arbitrary `time.sleep()`:

```python
# BAD: Arbitrary sleep
page.click("#submit-link")
time.sleep(3)
assert page.locator(".tp-cl-link").count() == expected_count

# GOOD: Wait for network + DOM update
with page.expect_response(lambda r: "tp_create_link" in r.url) as response_info:
    page.click("#submit-link")
response = response_info.value
assert response.ok

# Then wait for DOM to reflect the change
page.wait_for_selector(f".tp-cl-link >> text=stress-{i}", timeout=10_000)
```

For the stress test specifically, avoid asserting after every single link creation. Instead, wait for the loading indicator to disappear:

```python
page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)
```

This pattern already exists in the current test suite (see `test_client_links.py` line 73).

**Detection:**
- Test passes 8/10 times locally, fails 3/10 times in CI
- Failures always show "expected count 5, got 4" or similar off-by-one timing issues
- Adding `time.sleep(5)` "fixes" the test (but makes it slow and still occasionally fails)

---

### Pitfall 9: Usage Dashboard Verification Runs Before Data Propagates

**What goes wrong:**
After the stress test creates 50 links and the usage generation script hits each link multiple times, the test immediately navigates to `/usage-dashboard/` to verify the data. But usage data is aggregated asynchronously -- the Traffic Portal API may batch-process click records, or the usage summary API (`/user-activity-summary/{uid}`) may have eventual consistency with a delay of seconds to minutes. The dashboard shows stale or zero data, and the test fails.

**Prevention:**
Add a configurable wait period between usage generation and dashboard verification:

```python
USAGE_PROPAGATION_DELAY = int(os.getenv("TP_USAGE_DELAY", "30"))  # seconds

# After usage generation completes
time.sleep(USAGE_PROPAGATION_DELAY)

# Then verify the dashboard
page.goto(usage_dashboard_url)
```

Also: implement retry logic for the dashboard verification. Check the data, and if it shows zero, wait 10 seconds and refresh, up to 3 retries:

```python
for attempt in range(3):
    page.goto(usage_dashboard_url)
    page.wait_for_selector(".tp-ud-container", timeout=10_000)
    # Check if data has loaded
    if page.locator(".tp-ud-stat-value").first.inner_text() != "0":
        break
    time.sleep(10)
```

**Detection:**
- Usage dashboard test always shows 0 hits even though usage generation completed
- Test passes when run manually (human is slower, data has time to propagate)

---

## Minor Pitfalls

### Pitfall 10: Test User Account Hits Usage/Balance Limits

**What goes wrong:**
The test user (`TP_TEST_USER` from `.env`) may have a wallet balance or usage quota. Creating 50 links and generating hundreds of usage hits consumes real balance. If the test user runs out of balance, link creation fails with a billing error, or the usage dashboard shows a negative balance that triggers UI edge cases not covered by the regression tests.

**Prevention:**
- Use a dedicated test user with no balance constraints (admin or unlimited plan)
- Or: top up the test user's wallet before each stress test run
- Document the test user requirements in the test README

---

### Pitfall 11: Regression Test for TP-22 (Edit Empty Keyword) Depends on Existing Links

**What goes wrong:**
The regression test for TP-22 needs to click a link row to open the edit modal. If the test user has no links (e.g., after a database reset or on a fresh environment), the test fails with "no link rows found" rather than testing the actual bug fix.

**Prevention:**
Each regression test should set up its own preconditions. If TP-22's test needs an existing link, create one as part of the test's setup, then edit it:

```python
def test_tp22_edit_preserves_keyword(client_links_page):
    # Ensure at least one link exists (create if needed)
    ensure_link_exists(client_links_page, destination="https://example.com/tp22-test")
    # Now test the edit flow
    ...
```

---

### Pitfall 12: Hardcoded Selectors Break When Running Against Different Environments

**What goes wrong:**
Tests reference `https://trafficportal.dev` and CSS selectors like `.tp-cl-container` that are specific to the current deployment. If the test needs to run against a local Docker WordPress instance, a staging URL, or a different theme, paths and selectors may differ.

**Prevention:**
The existing `conftest.py` already parameterizes `BASE_URL`, `CLIENT_LINKS_PATH`, etc. via environment variables. Extend this pattern to the stress test. Do not hardcode any URLs in the stress test scripts:

```python
DOMAIN = os.getenv("TP_DOMAIN", "dev.trfc.link")  # for usage generation hits
```

---

### Pitfall 13: Parallel Test Runs Cause Data Conflicts

**What goes wrong:**
If two developers or CI jobs run the stress test simultaneously against the same dev environment with the same test user, they create conflicting link keys (`stress-001` already exists from the other run). One run fails with "shortcode already taken" errors.

**Prevention:**
Include a unique run identifier in all test data:

```python
import uuid
RUN_ID = os.getenv("TP_RUN_ID", uuid.uuid4().hex[:8])

# Link keys become: stress-a1b2c3d4-001
custom_key = f"stress-{RUN_ID}-{i:03d}"
```

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| Stress test script (50 link creation) | API rate limiting (Pitfall 1) | Add configurable delay between creations, exponential backoff on 429 |
| Stress test script (50 link creation) | Shortcode generation failure (Pitfall 3) | Use explicit custom keys, not auto-generation |
| Stress test script (50 link creation) | Browser resource exhaustion (Pitfall 4) | Refresh page every 15 links, increase timeouts |
| Stress test script (50 link creation) | Test data pollution (Pitfall 2) | Deterministic key prefix, teardown cleanup fixture |
| Usage generation script | Wrong redirect handling (Pitfall 5) | Use `allow_redirects=False`, add delay between hits |
| Usage dashboard verification | Data propagation delay (Pitfall 9) | Configurable wait period, retry logic |
| Jira bug regression tests | UI coupling (Pitfall 7) | Use `data-testid` attributes, test behavior not DOM |
| Jira bug regression tests | AJAX race conditions (Pitfall 8) | Wait for network responses, not arbitrary sleep |
| Jira bug regression tests | Missing preconditions (Pitfall 11) | Each test sets up its own data |
| All tests | Parallel run conflicts (Pitfall 13) | Unique run ID in all test data |
| All tests | Nonce expiration (Pitfall 6) | Fresh page load before long-running tests |

---

## Sources

- Codebase inspection: `includes/TrafficPortal/Exception/RateLimitException.php` -- confirms 429 handling exists
- Codebase inspection: `includes/class-tp-api-handler.php` lines 353-361 -- rate limit error flow
- Codebase inspection: `tests/e2e/conftest.py` -- existing session-scoped auth pattern
- Codebase inspection: `tests/e2e/test_client_links.py` -- existing wait patterns for AJAX
- Codebase inspection: `docs/BUG-shortcode-generation-failing.md` -- known shortcode generation 500 errors
- Codebase inspection: `tests/e2e/test_usage_dashboard.py` -- deployment detection pattern
- Playwright documentation: timeout and resource management best practices (HIGH confidence, well-documented)
- AWS API Gateway: default throttling behaviors for Lambda-backed APIs (MEDIUM confidence, varies by configuration)
