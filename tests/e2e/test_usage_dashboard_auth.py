"""
Playwright e2e tests for Usage Dashboard authentication and authorization.

These tests verify that:
  - Unauthenticated users see a login form (not the dashboard)
  - Unauthenticated AJAX calls return proper 401 responses
  - The login form redirects back to the dashboard page

Run:
    pytest tests/e2e/test_usage_dashboard_auth.py -v
"""

import os
from pathlib import Path

import pytest
from playwright.sync_api import Page, Browser, BrowserContext, expect

# -------------------------------------------------------------------
# Load .env
# -------------------------------------------------------------------
_env_path = Path(__file__).parent / ".env"
if _env_path.exists():
    for line in _env_path.read_text().splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            key, _, value = line.partition("=")
            os.environ.setdefault(key.strip(), value.strip())

BASE_URL = os.getenv("TP_BASE_URL", "https://trafficportal.dev")
USAGE_DASHBOARD_PATH = os.getenv("TP_USAGE_DASHBOARD_PATH", "/usage-dashboard/")
AJAX_URL = f"{BASE_URL}/wp-admin/admin-ajax.php"


# -------------------------------------------------------------------
# Fixtures -- unauthenticated browser context (no login)
# -------------------------------------------------------------------
@pytest.fixture(scope="module")
def anon_context(browser: Browser):
    """Browser context with no authentication cookies."""
    context = browser.new_context(
        viewport={"width": 1280, "height": 900},
        ignore_https_errors=True,
    )
    yield context
    context.close()


@pytest.fixture()
def anon_page(anon_context: BrowserContext):
    """Fresh page with no auth for each test."""
    pg = anon_context.new_page()
    yield pg
    pg.close()


# -------------------------------------------------------------------
# UI-level auth tests -- verify unauthenticated page behavior
# -------------------------------------------------------------------
class TestUnauthenticatedPageBehavior:
    """Verify that unauthenticated users see the login form."""

    def test_anon_user_sees_login_form(self, anon_page: Page):
        """The shortcode should render wp_login_form() for logged-out users."""
        anon_page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
        anon_page.wait_for_load_state("networkidle")

        # Should see a login form
        login_form = anon_page.locator("form#loginform, .tp-ud-login-wrapper form")
        assert login_form.count() >= 1, \
            "Login form should be visible for anonymous users"

    def test_anon_user_does_not_see_dashboard(self, anon_page: Page):
        """Anonymous users should NOT see the dashboard skeleton or content."""
        anon_page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
        anon_page.wait_for_load_state("networkidle")

        skeleton = anon_page.locator("#tp-ud-skeleton")
        content = anon_page.locator("#tp-ud-content")
        assert skeleton.count() == 0, "Skeleton should not appear for anon users"
        assert content.count() == 0, "Content should not appear for anon users"

    def test_login_form_has_redirect_to_current_page(self, anon_page: Page):
        """The login form redirect field should point back to the dashboard page."""
        anon_page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
        anon_page.wait_for_load_state("networkidle")

        # wp_login_form() generates a hidden redirect_to field
        redirect_input = anon_page.locator('input[name="redirect_to"]')
        if redirect_input.count() > 0:
            redirect_value = redirect_input.input_value()
            assert "/usage-dashboard" in redirect_value.lower() or \
                   USAGE_DASHBOARD_PATH.rstrip("/") in redirect_value, \
                f"redirect_to should point to dashboard page, got: {redirect_value}"


# -------------------------------------------------------------------
# AJAX-level auth tests -- verify server returns correct 401 responses
# -------------------------------------------------------------------
class TestAjaxAuthResponses:
    """Verify that unauthenticated AJAX calls return proper 401 JSON."""

    def test_get_usage_summary_returns_401(self, anon_page: Page):
        """tp_get_usage_summary should return 401 for unauthenticated requests."""
        response = anon_page.request.post(AJAX_URL, form={
            "action": "tp_get_usage_summary",
            "nonce": "invalid_nonce",
            "start_date": "2025-01-01",
            "end_date": "2025-01-31",
        })

        assert response.status == 401, \
            f"Expected 401, got {response.status}: {response.text()}"

        body = response.json()
        assert body["success"] is False

    def test_401_response_is_json(self, anon_page: Page):
        """401 response should be valid JSON, not HTML error page."""
        response = anon_page.request.post(AJAX_URL, form={
            "action": "tp_get_usage_summary",
            "nonce": "invalid_nonce",
        })

        content_type = response.headers.get("content-type", "")
        assert "application/json" in content_type, \
            f"Expected JSON content-type, got {content_type}"

        body = response.json()
        assert "success" in body, "Response missing 'success' field"

    def test_401_has_login_required_code(self, anon_page: Page):
        """401 response should include login_required error code."""
        response = anon_page.request.post(AJAX_URL, form={
            "action": "tp_get_usage_summary",
            "nonce": "invalid_nonce",
        })

        body = response.json()
        assert body["success"] is False
        # The ajax_require_login handler returns code: login_required
        assert body["data"]["code"] == "login_required", \
            f"Expected login_required code, got: {body['data']}"
