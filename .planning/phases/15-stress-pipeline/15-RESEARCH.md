# Phase 15: Stress Pipeline - Research

**Researched:** 2026-03-23
**Domain:** Playwright UI automation (batch link creation), async HTTP traffic generation, dashboard verification with retry polling
**Confidence:** HIGH

## Summary

Phase 15 builds on the test infrastructure from Phase 14 to implement a three-stage stress pipeline: (1) create 50 short links via the Playwright UI, (2) generate measurable usage traffic by hitting each short link with httpx, and (3) verify the usage dashboard reflects the generated activity. The pipeline is orchestrated by a shell script (`run_stress.sh`) that runs the three stages sequentially.

The existing codebase provides all the infrastructure needed: the stress conftest in `tests/e2e/stress/conftest.py` already has `run_id`, `stress_data_file`, `stress_links`, and `stress_rate_limit` fixtures. The `tests/e2e/conftest.py` provides `auth_context` and `client_links_page` fixtures for Playwright. The short link URL pattern is `https://{domain}/{tpKey}` where domain is `dev.trfc.link` in dev. The usage API at `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev` does not require an API key for read access.

**Primary recommendation:** Write three test files in `tests/e2e/stress/` (link creation, usage generation, dashboard verification), all marked with `@pytest.mark.stress`, plus a `run_stress.sh` orchestration script. The link creation test must handle the UI flow carefully: fill destination URL, wait for async validation to complete and custom key field to appear, fill custom key, then submit. Usage generation uses httpx async with rate limiting. Dashboard verification uses retry polling with `page.wait_for_function()` or manual polling loop.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| STRESS-01 | Script creates 50 short links via Playwright UI using custom keywords (not shortcode generator) | Architecture Patterns: UI automation flow for link creation modal, custom key field appears after URL validation |
| STRESS-02 | Each link points to a valid URL with unique keyword per RUN_ID | Architecture Patterns: keyword format `{RUN_ID}-{NNN}`, destination `https://example.com` |
| STRESS-03 | Created link data (keyword, URL, MID) persisted to stress_data.json | Architecture Patterns: capture MID from AJAX response via network interception or DOM scraping |
| USAGE-01 | Script sends HTTP requests to each of the 50 created links | Architecture Patterns: httpx async client hitting `https://dev.trfc.link/{keyword}` |
| USAGE-02 | Each link hit multiple times (configurable, default 5+) for measurable volume | Architecture Patterns: configurable hits_per_link with env var override |
| USAGE-03 | Requests use httpx with rate limiting/backoff to avoid 429s | Architecture Patterns: asyncio.Semaphore + per-request delay, exponential backoff on 429 |
| USAGE-04 | Usage generation reads link data from stress_data.json | Architecture Patterns: stress_links fixture from Phase 14 conftest |
| VERIFY-01 | Playwright navigates to /usage-dashboard and verifies page loads with data | Architecture Patterns: existing usage_dashboard_page fixture pattern |
| VERIFY-02 | Test confirms usage table shows records for stress test date range | Architecture Patterns: set date inputs to today's date, check #tp-ud-tbody rows |
| VERIFY-03 | Test confirms chart renders with non-zero data points | Architecture Patterns: check canvas element or chart data attributes |
| VERIFY-04 | Test accounts for eventual consistency with retry/polling | Architecture Patterns: polling loop with configurable timeout and interval |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| pytest | >=8.0 | Test framework | Already installed, provides fixtures and markers |
| pytest-playwright | >=0.7 | Browser automation | Already installed, provides Playwright fixtures |
| httpx | >=0.28 | Async HTTP client for usage generation | Installed in Phase 14, async support with rate limiting |
| pytest-asyncio | >=0.25 | Async test support | Installed in Phase 14, enables async test functions |

### Existing (already available from Phase 14)
| Library | Purpose |
|---------|---------|
| Playwright (Chromium) | Browser engine for UI automation |
| stress conftest fixtures | run_id, stress_data_file, stress_links, stress_rate_limit |
| auth_context fixture | Session-scoped authenticated browser context |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Playwright for link creation | Direct API calls | Requirement STRESS-01 explicitly requires Playwright UI -- no alternative |
| httpx for usage generation | Playwright page.goto() | httpx is faster, lighter, supports async concurrency -- Playwright would be overkill for simple GET requests |
| Manual polling loop | Playwright auto-wait | Auto-wait doesn't handle eventual consistency -- explicit polling with timeout is more reliable and debuggable |

## Architecture Patterns

### Recommended Project Structure
```
tests/e2e/stress/
├── __init__.py                 # Existing (Phase 14)
├── conftest.py                 # Existing (Phase 14) - run_id, stress_links, etc.
├── test_create_links.py        # NEW: 50 link creation via Playwright UI
├── test_generate_usage.py      # NEW: httpx async usage traffic generation
└── test_verify_dashboard.py    # NEW: dashboard verification with retry polling

tests/e2e/scripts/
├── cleanup_stress.py           # Existing (Phase 14)

run_stress.sh                   # NEW: orchestration script at project root
```

### Pattern 1: Link Creation via Playwright UI (STRESS-01, STRESS-02, STRESS-03)
**What:** Automate the Add Link modal to create 50 links sequentially
**When to use:** test_create_links.py

The UI flow for creating a single link:
1. Click `#tp-cl-add-link-btn` to open the Add Link modal
2. Wait for modal `#tp-cl-edit-modal-overlay` to be visible
3. Fill `#tp-destination` with the destination URL (e.g., `https://example.com`)
4. Wait for async URL validation to complete -- the custom key field (`#tp-custom-key-group`) slides down after validation passes
5. Wait for `#tp-custom-key-group` to be visible (slideDown animation)
6. Fill `#tp-custom-key` with the unique keyword (e.g., `stress-a1b2c3d4-001`)
7. Click `#tp-submit-btn` to submit
8. Wait for success response -- capture the MID from the `tp:linkSaved` event or intercept the AJAX response
9. Close the modal / wait for automatic close

**Capturing the MID:** Two approaches:
- **Network interception (recommended):** Use `page.route()` or `page.expect_response()` to capture the AJAX response from `admin-ajax.php` containing `response.data.mid`
- **DOM scraping (fallback):** After creation, the short URL appears in `#tp-short-url-output` -- extract the keyword and look up the MID from the table

**Writing stress_data.json:** After all 50 links are created, write the collected data to `stress_data_{run_id}.json`.

**Confidence:** HIGH -- selectors verified from template and JS source.

### Pattern 2: Usage Generation with httpx Async (USAGE-01, USAGE-02, USAGE-03, USAGE-04)
**What:** Send HTTP GET requests to each short link to generate usage records
**When to use:** test_generate_usage.py

The short URL format is `https://dev.trfc.link/{keyword}`. Each request to this URL triggers a redirect and records a usage event. The domain should be configurable via env var since it may differ between environments.

Key considerations:
- **Rate limiting:** Use `asyncio.Semaphore` to limit concurrency and add a delay between requests
- **429 handling:** If a 429 response is received, back off exponentially
- **Follow redirects:** Set `follow_redirects=False` to avoid loading the destination page (we just need the redirect to register usage)
- **No API key needed:** The short link redirect endpoint is public

**Confidence:** HIGH -- URL pattern confirmed from `class-tp-api-handler.php` line 319: `'https://' . $domain . '/' . $key`.

### Pattern 3: Dashboard Verification with Retry Polling (VERIFY-01 through VERIFY-04)
**What:** Navigate to /usage-dashboard, set date range, poll for data to appear
**When to use:** test_verify_dashboard.py

The usage dashboard loads data via AJAX. After stress test usage generation, there may be eventual consistency delay before data appears. The test must:
1. Navigate to `/usage-dashboard/`
2. Wait for skeleton to disappear
3. Set date range to cover today (when stress test ran)
4. Apply the date filter
5. Poll for table rows to appear with non-zero hit counts
6. Verify chart canvas has rendered

**Confidence:** HIGH -- selectors verified from `templates/usage-dashboard-template.php` and existing test patterns.

### Pattern 4: Orchestration Script (run_stress.sh)
**What:** Shell script that runs the three stress test phases sequentially
**When to use:** Entry point for the complete stress pipeline

### Anti-Patterns to Avoid
- **Creating links via API instead of UI:** STRESS-01 explicitly requires Playwright UI. Do not bypass.
- **Hardcoded sleeps for eventual consistency:** Use retry polling with a configurable timeout and interval, not `time.sleep(60)`.
- **Running all 50 link creations in parallel:** Link creation through the UI is inherently sequential (modal flow). Do not attempt parallel browser contexts.
- **Following redirects in usage generation:** Setting `follow_redirects=True` loads the destination page unnecessarily. Use `follow_redirects=False` -- the 301/302 response is enough to register usage.
- **Using pytest-xdist for link creation or dashboard verification:** Only usage generation benefits from parallelism. Creation and verification are sequential Playwright flows.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| AJAX response capture | DOM scraping after each creation | `page.expect_response()` | Reliable, captures MID directly from server response |
| Rate limiting | Manual sleep counters | asyncio.Semaphore + delay | Standard concurrency pattern, handles backpressure |
| Retry polling | Custom while loops everywhere | Dedicated poll helper function | Reusable, configurable timeout/interval, clear failure messages |
| UUID generation | Custom random strings | `uuid.uuid4().hex[:8]` | Already used by Phase 14 fixtures |
| Date formatting | Manual string building | `datetime.date.today().isoformat()` | Standard library, correct format |

## Common Pitfalls

### Pitfall 1: Custom Key Field Not Visible
**What goes wrong:** Test tries to fill `#tp-custom-key` immediately after filling the destination URL, but the field is hidden (CSS `display: none`)
**Why it happens:** The custom key field slides down only after async URL validation succeeds. The validation makes an AJAX call to `tp_validate_url`.
**How to avoid:** Wait for `#tp-custom-key-group` to become visible: `page.wait_for_selector("#tp-custom-key-group", state="visible", timeout=15_000)`. The 15s timeout accounts for the debounced validation (there's a debounce delay before validation fires).

### Pitfall 2: AJAX Response Interception Timing
**What goes wrong:** `page.expect_response()` misses the AJAX response because it was set up too late
**Why it happens:** The response can arrive before the context manager captures it if there's a race condition
**How to avoid:** Use the `with page.expect_response()` context manager BEFORE triggering the action (clicking submit).

### Pitfall 3: Multiple AJAX Calls on admin-ajax.php
**What goes wrong:** `expect_response("**/admin-ajax.php")` captures a URL validation response instead of the link creation response
**Why it happens:** The form triggers `tp_validate_url` and `tp_validate_key` AJAX calls in addition to `tp_create_link`
**How to avoid:** Filter by checking the response body or use a more specific predicate: `page.expect_response(lambda r: "admin-ajax.php" in r.url and "tp_create_link" in r.request.post_data)`

### Pitfall 4: Usage Data Not Appearing Immediately
**What goes wrong:** Dashboard verification test fails because usage records haven't propagated yet
**Why it happens:** There is eventual consistency between the redirect service recording usage and the dashboard API returning it
**How to avoid:** Use retry polling with reasonable timeout (60-120 seconds) and interval (5-10 seconds). Re-apply date filter on each retry to trigger a fresh AJAX fetch.

### Pitfall 5: Short Link Domain Mismatch
**What goes wrong:** Usage generation sends requests to wrong domain
**Why it happens:** The short link domain (`dev.trfc.link`) is a WordPress option, not in the test `.env`
**How to avoid:** Make the domain configurable via `TP_SHORT_DOMAIN` env var. Default to `dev.trfc.link`.

### Pitfall 6: Form State Not Reset Between Creations
**What goes wrong:** After creating link N, the form is in "update mode" and the next creation attempt calls `tp_update_link` instead of `tp_create_link`
**Why it happens:** After successful creation, `frontend.js` calls `switchToUpdateMode()` -- the form stays in edit mode until explicitly reset
**How to avoid:** After each link creation, close the modal and re-click "Add a link" button. The click handler triggers `tp:resetForm` which calls `switchToCreateMode()`. Wait for the modal to fully close before reopening.

### Pitfall 7: `.env` Missing API Config for Cleanup
**What goes wrong:** Cleanup script fails because `TP_API_ENDPOINT` and `API_KEY` are not in `tests/e2e/.env`
**Why it happens:** The current `.env` only has WordPress auth credentials, not API config
**How to avoid:** Add `TP_API_ENDPOINT` and `API_KEY` to the `.env` file.

## Open Questions

1. **What is the exact rate limit threshold for dev.trfc.link?**
   - Recommendation: Start with 1.5s delay (from Phase 14 fixture default). The test should log and handle 429s gracefully regardless.

2. **Does the redirect endpoint require specific headers to register usage?**
   - Recommendation: Set a realistic User-Agent header on httpx requests. If usage doesn't register, investigate whether the redirect service filters bot traffic.

3. **Are TP_API_ENDPOINT and API_KEY needed in tests/e2e/.env?**
   - Recommendation: Document in run_stress.sh that these must be added for cleanup to work.

4. **How long does usage data take to propagate to the dashboard?**
   - Recommendation: Use a generous polling timeout (120s) with 5-10s intervals.

## Sources

### Primary (HIGH confidence)
- Codebase analysis: `templates/client-links-template.php` -- modal structure, selectors
- Codebase analysis: `templates/shortcode-template.php` -- form fields
- Codebase analysis: `assets/js/frontend.js` -- form submission flow, `tp_create_link` AJAX action
- Codebase analysis: `assets/js/client-links.js` -- modal overlay selectors
- Codebase analysis: `includes/class-tp-api-handler.php` line 319 -- short URL format
- Codebase analysis: `includes/class-tp-link-shortener.php` line 170 -- domain default: `dev.trfc.link`
- Codebase analysis: `templates/usage-dashboard-template.php` -- dashboard selectors
- Codebase analysis: `tests/e2e/stress/conftest.py` -- existing fixtures
- Codebase analysis: `tests/e2e/conftest.py` -- auth_context, client_links_page fixtures

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - all libraries already installed from Phase 14
- Architecture: HIGH - all selectors and AJAX flows verified from source code
- Pitfalls: HIGH - based on direct codebase analysis

**Research date:** 2026-03-23
**Valid until:** 2026-04-23
