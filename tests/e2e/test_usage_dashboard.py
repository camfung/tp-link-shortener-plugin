"""
Playwright e2e tests for the [tp_usage_dashboard] shortcode page.

These tests verify the Usage Dashboard:
  - Page loads and renders the skeleton
  - Skeleton transitions to content after AJAX fetch
  - Dashboard container has expected structure
  - Error state with retry button works
  - Date inputs are present with default values

Run:
    pytest tests/e2e/test_usage_dashboard.py -v

NOTE: These tests require the Phase 5 (05-01, 05-02) code to be deployed to
the dev site. They target the tp-ud- prefixed implementation with skeleton/AJAX
pattern. Tests will auto-skip if the old uad- implementation is still active.
"""

import pytest
from playwright.sync_api import Page, expect

from conftest import BASE_URL, USAGE_DASHBOARD_PATH


# -------------------------------------------------------------------
# Deployment detection helper
# -------------------------------------------------------------------
def _check_deployment(page: Page) -> bool:
    """Check if Phase 5 tp-ud- implementation is deployed.

    Returns True if .tp-ud-container is present, False if the old
    .uad-dashboard is still active.
    """
    page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
    page.wait_for_load_state("networkidle")
    return page.locator(".tp-ud-container").count() > 0


def _require_deployment(page: Page):
    """Skip test if Phase 5 code is not deployed to the dev site."""
    if not _check_deployment(page):
        pytest.skip(
            "Phase 5 tp-ud- implementation not deployed. "
            "Deploy commits from 05-01/05-02 and re-run."
        )


# -------------------------------------------------------------------
# Page load & skeleton
# -------------------------------------------------------------------
class TestPageLoad:
    """Verify the page loads with the dashboard skeleton."""

    def test_container_visible(self, page: Page):
        """The main .tp-ud-container should be visible."""
        _require_deployment(page)
        container = page.locator(".tp-ud-container")
        expect(container).to_be_visible()

    def test_skeleton_appears_initially(self, page: Page):
        """The loading skeleton should appear before data loads."""
        # Navigate fresh -- need to catch skeleton before it disappears
        _require_deployment(page)
        page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
        # The skeleton may disappear fast, so check that either:
        #   - skeleton is visible (still loading), OR
        #   - content is visible (already loaded)
        # This validates the template renders the skeleton by default
        page.wait_for_selector(".tp-ud-container", timeout=10_000)
        skeleton = page.locator("#tp-ud-skeleton")
        content = page.locator("#tp-ud-content")
        assert skeleton.is_visible() or content.is_visible(), \
            "Neither skeleton nor content visible on page load"

    def test_content_loads_after_fetch(self, page: Page):
        """After AJAX fetch, either content or error should appear (skeleton hidden)."""
        _require_deployment(page)
        page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
        page.wait_for_selector(".tp-ud-container", timeout=10_000)
        # Wait for skeleton to disappear (max 25s for API timeout)
        page.wait_for_selector("#tp-ud-skeleton", state="hidden", timeout=25_000)
        # Either content or error should be visible
        content = page.locator("#tp-ud-content")
        error = page.locator("#tp-ud-error")
        assert content.is_visible() or error.is_visible(), \
            "Neither content nor error visible after skeleton disappears"

    def test_no_login_form_for_authenticated_user(self, page: Page):
        """Authenticated users should NOT see the login form."""
        _require_deployment(page)
        login_form = page.locator(".tp-ud-login-wrapper")
        assert login_form.count() == 0, \
            "Login form should not appear for authenticated users"


# -------------------------------------------------------------------
# Dashboard structure
# -------------------------------------------------------------------
class TestDashboardStructure:
    """Verify the dashboard has the expected structural elements."""

    def _wait_for_content(self, page: Page):
        """Navigate and wait for skeleton to finish loading."""
        _require_deployment(page)
        page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
        page.wait_for_selector(".tp-ud-container", timeout=10_000)
        page.wait_for_selector("#tp-ud-skeleton", state="hidden", timeout=25_000)

    def test_chart_canvas_present(self, page: Page):
        """The chart canvas element should exist in the DOM."""
        self._wait_for_content(page)
        canvas = page.locator("#tp-ud-chart")
        assert canvas.count() == 1, "Chart canvas should be present"

    def test_date_inputs_present(self, page: Page):
        """Date start and end inputs should be present."""
        self._wait_for_content(page)
        # Look for date inputs in the content area
        date_inputs = page.locator('.tp-ud-content input[type="date"]')
        assert date_inputs.count() >= 2, \
            f"Expected at least 2 date inputs, found {date_inputs.count()}"

    def test_summary_strip_present(self, page: Page):
        """The summary stats strip container should exist."""
        self._wait_for_content(page)
        strip = page.locator("#tp-ud-summary-strip")
        assert strip.count() == 1, "Summary strip should be present"

    def test_table_container_present(self, page: Page):
        """The table container should exist."""
        self._wait_for_content(page)
        table = page.locator("#tp-ud-table-container")
        assert table.count() == 1, "Table container should be present"


# -------------------------------------------------------------------
# AJAX data fetch
# -------------------------------------------------------------------
class TestAjaxDataFetch:
    """Verify the AJAX data pipeline works end-to-end."""

    def _get_nonce(self, page: Page) -> str:
        """Navigate to dashboard and extract the AJAX nonce."""
        _require_deployment(page)
        page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
        page.wait_for_selector(".tp-ud-container", timeout=10_000)
        nonce = page.evaluate(
            "() => typeof tpUsageDashboard !== 'undefined' ? tpUsageDashboard.nonce : null"
        )
        assert nonce is not None, "tpUsageDashboard.nonce should be set"
        return nonce

    def test_ajax_returns_valid_json(self, page: Page):
        """Authenticated AJAX call should return valid JSON with days array."""
        nonce = self._get_nonce(page)

        # Make AJAX call directly
        response = page.request.post(f"{BASE_URL}/wp-admin/admin-ajax.php", form={
            "action": "tp_get_usage_summary",
            "nonce": nonce,
            "start_date": "2025-01-01",
            "end_date": "2025-01-31",
        })

        assert response.status == 200
        body = response.json()
        assert body["success"] is True, f"Expected success=true, got: {body}"
        assert "days" in body["data"], \
            f"Expected 'days' in response data, got: {list(body['data'].keys())}"
        assert isinstance(body["data"]["days"], list), "days should be a list"

    def test_ajax_days_have_expected_fields(self, page: Page):
        """Each day record should have date, totalHits, hitCost, balance."""
        nonce = self._get_nonce(page)

        response = page.request.post(f"{BASE_URL}/wp-admin/admin-ajax.php", form={
            "action": "tp_get_usage_summary",
            "nonce": nonce,
            "start_date": "2024-01-01",
            "end_date": "2025-12-31",
        })

        body = response.json()
        if body["success"] and len(body["data"]["days"]) > 0:
            day = body["data"]["days"][0]
            assert "date" in day, "Day record missing 'date'"
            assert "totalHits" in day, "Day record missing 'totalHits'"
            assert "hitCost" in day, "Day record missing 'hitCost'"
            assert "balance" in day, "Day record missing 'balance'"
            # Type checks
            assert isinstance(day["totalHits"], int), "totalHits should be int"
            assert isinstance(day["hitCost"], (int, float)), "hitCost should be numeric"
            assert isinstance(day["balance"], (int, float)), "balance should be numeric"

    def test_ajax_rejects_invalid_date_format(self, page: Page):
        """AJAX call with invalid date format should return error."""
        nonce = self._get_nonce(page)

        response = page.request.post(f"{BASE_URL}/wp-admin/admin-ajax.php", form={
            "action": "tp_get_usage_summary",
            "nonce": nonce,
            "start_date": "not-a-date",
            "end_date": "also-bad",
        })

        body = response.json()
        assert body["success"] is False, "Should reject invalid date format"

    def test_no_uid_in_ajax_request(self, page: Page):
        """The JS should NOT send a uid parameter -- verify via localized config."""
        _require_deployment(page)
        # Check that tpUsageDashboard does not contain a uid field
        has_uid = page.evaluate("""() => {
            return typeof tpUsageDashboard !== 'undefined' && 'uid' in tpUsageDashboard
        }""")
        assert has_uid is False, "tpUsageDashboard should not contain uid"


# -------------------------------------------------------------------
# Retry behavior
# -------------------------------------------------------------------
class TestRetryBehavior:
    """Verify the retry button works without page reload."""

    def test_retry_button_present_on_error(self, page: Page):
        """If an error occurs, the retry button should be visible."""
        _require_deployment(page)
        page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
        page.wait_for_selector(".tp-ud-container", timeout=10_000)
        # Wait for loading to finish
        page.wait_for_selector("#tp-ud-skeleton", state="hidden", timeout=25_000)

        # Check if error state appeared (may not if API succeeded)
        error = page.locator("#tp-ud-error")
        if error.is_visible():
            retry_btn = page.locator("#tp-ud-retry")
            expect(retry_btn).to_be_visible()
        else:
            # If content loaded fine, we can't test retry naturally
            # Just verify the retry button element exists in DOM
            retry_btn = page.locator("#tp-ud-retry")
            assert retry_btn.count() == 1, "Retry button should exist in DOM"

    def test_retry_does_not_reload_page(self, page: Page):
        """Clicking retry should re-fetch via AJAX, not reload the page."""
        _require_deployment(page)
        page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
        page.wait_for_selector(".tp-ud-container", timeout=10_000)
        page.wait_for_selector("#tp-ud-skeleton", state="hidden", timeout=25_000)

        # Record current URL
        url_before = page.url

        # Force show the error state via JS to test retry
        page.evaluate("""() => {
            document.getElementById('tp-ud-error').style.display = '';
            document.getElementById('tp-ud-content').style.display = 'none';
        }""")

        # Click retry
        page.locator("#tp-ud-retry").click()

        # Wait briefly for AJAX
        page.wait_for_timeout(2000)

        # URL should not have changed (no page reload)
        assert page.url == url_before, \
            f"Page reloaded on retry. Before: {url_before}, After: {page.url}"
