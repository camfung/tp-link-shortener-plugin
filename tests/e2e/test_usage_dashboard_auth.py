"""
Playwright e2e tests for Usage Dashboard authentication and authorization.

These tests verify that:
  - Unauthenticated users see a login form (not the dashboard)
  - Unauthenticated AJAX calls return proper 401 responses
  - The login form redirects back to the dashboard page

Run:
    pytest tests/e2e/test_usage_dashboard_auth.py -v

NOTE: These tests require the Phase 5 (05-01, 05-02) code to be deployed to
the dev site. The AJAX auth tests require the tp_get_usage_summary handler to
be registered. Tests will auto-skip if the implementation is not deployed.
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
# Deployment detection helper
# -------------------------------------------------------------------
def _require_ajax_handler(anon_page: Page):
    """Skip tests if the tp_get_usage_summary AJAX handler is not deployed.

    The handler returns 401 when not authenticated. If the handler is not
    registered, WordPress returns 400 with body '0'.
    """
    response = anon_page.request.post(AJAX_URL, form={
        "action": "tp_get_usage_summary",
        "nonce": "probe",
    })
    if response.status == 400 and response.text() == "0":
        pytest.skip(
            "tp_get_usage_summary AJAX handler not registered. "
            "Deploy commits from 05-02 and re-run."
        )


def _require_auth_gate(anon_page: Page):
    """Skip tests if the tp-ud- auth gate is not deployed.

    The Phase 5 shortcode wraps unauthenticated output in
    .tp-ud-login-wrapper. If neither that nor a standard loginform is
    present with tp-ud markers, the old implementation is still active.
    """
    anon_page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
    anon_page.wait_for_load_state("networkidle")
    has_tp_ud_login = anon_page.locator(".tp-ud-login-wrapper").count() > 0
    has_loginform = anon_page.locator("form#loginform").count() > 0
    # Old uad- implementation shows the dashboard to everyone (no auth gate)
    has_old_uad = anon_page.locator(".uad-dashboard").count() > 0
    if has_old_uad and not has_tp_ud_login:
        pytest.skip(
            "Phase 5 tp-ud- auth gate not deployed. "
            "Old uad- implementation still active. Deploy 05-01 and re-run."
        )
    return has_tp_ud_login or has_loginform


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
        _require_auth_gate(anon_page)

        # Should see a login form
        login_form = anon_page.locator("form#loginform, .tp-ud-login-wrapper form")
        assert login_form.count() >= 1, \
            "Login form should be visible for anonymous users"

    def test_anon_user_does_not_see_dashboard(self, anon_page: Page):
        """Anonymous users should NOT see the dashboard skeleton or content."""
        _require_auth_gate(anon_page)

        skeleton = anon_page.locator("#tp-ud-skeleton")
        content = anon_page.locator("#tp-ud-content")
        assert skeleton.count() == 0, "Skeleton should not appear for anon users"
        assert content.count() == 0, "Content should not appear for anon users"

    def test_login_form_has_redirect_to_current_page(self, anon_page: Page):
        """The login form redirect field should point back to the dashboard page."""
        _require_auth_gate(anon_page)

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
        _require_ajax_handler(anon_page)

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
        _require_ajax_handler(anon_page)

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
        _require_ajax_handler(anon_page)

        response = anon_page.request.post(AJAX_URL, form={
            "action": "tp_get_usage_summary",
            "nonce": "invalid_nonce",
        })

        body = response.json()
        assert body["success"] is False
        # The ajax_require_login handler returns code: login_required
        assert body["data"]["code"] == "login_required", \
            f"Expected login_required code, got: {body['data']}"
