"""
Playwright e2e tests for Client Links authentication error handling.

These tests verify that unauthenticated users see proper error messages
instead of generic failures when accessing the Client Links page.

Run:
    pytest tests/e2e/test_client_links_auth.py -v
"""

import os
import json
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
CLIENT_LINKS_PATH = os.getenv("TP_CLIENT_LINKS_PATH", "/camerons-test-page/")
AJAX_URL = f"{BASE_URL}/wp-admin/admin-ajax.php"


# -------------------------------------------------------------------
# Fixtures — unauthenticated browser context (no login)
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
# AJAX-level auth tests — verify server returns correct 401 responses
# -------------------------------------------------------------------
class TestAjaxAuthResponses:
    """Verify that unauthenticated AJAX calls return proper 401 JSON."""

    def test_get_user_map_items_returns_401(self, anon_page: Page):
        """tp_get_user_map_items should return 401 with login_required code."""
        response = anon_page.request.post(AJAX_URL, form={
            "action": "tp_get_user_map_items",
            "nonce": "invalid_nonce",
            "page": "1",
            "page_size": "10",
        })

        assert response.status == 401, (
            f"Expected 401, got {response.status}: {response.text()}"
        )

        body = response.json()
        assert body["success"] is False
        assert body["data"]["code"] == "login_required"
        assert "logged in" in body["data"]["message"].lower()

    def test_toggle_link_status_returns_401(self, anon_page: Page):
        """tp_toggle_link_status should return 401 with login_required code."""
        response = anon_page.request.post(AJAX_URL, form={
            "action": "tp_toggle_link_status",
            "nonce": "invalid_nonce",
            "mid": "1",
            "status": "active",
        })

        assert response.status == 401, (
            f"Expected 401, got {response.status}: {response.text()}"
        )

        body = response.json()
        assert body["success"] is False
        assert body["data"]["code"] == "login_required"

    def test_get_link_history_returns_401(self, anon_page: Page):
        """tp_get_link_history should return 401 with login_required code."""
        response = anon_page.request.post(AJAX_URL, form={
            "action": "tp_get_link_history",
            "nonce": "invalid_nonce",
            "mid": "1",
        })

        assert response.status == 401, (
            f"Expected 401, got {response.status}: {response.text()}"
        )

        body = response.json()
        assert body["success"] is False
        assert body["data"]["code"] == "login_required"

    def test_401_response_is_json(self, anon_page: Page):
        """All 401 responses should be valid JSON, not HTML error pages."""
        for action in ["tp_get_user_map_items", "tp_toggle_link_status", "tp_get_link_history"]:
            response = anon_page.request.post(AJAX_URL, form={
                "action": action,
                "nonce": "invalid_nonce",
            })

            content_type = response.headers.get("content-type", "")
            assert "application/json" in content_type, (
                f"{action}: Expected JSON content-type, got {content_type}"
            )

            body = response.json()
            assert "success" in body, (
                f"{action}: Response missing 'success' field"
            )


# -------------------------------------------------------------------
# UI-level auth tests — verify the page shows correct error messages
# -------------------------------------------------------------------
class TestUnauthenticatedPageBehavior:
    """Verify UI behavior when an unauthenticated user accesses Client Links."""

    def test_anon_user_sees_no_client_links_ui(self, anon_page: Page):
        """
        The [tp_client_links] shortcode returns empty HTML for
        non-logged-in users. The container should not be present.
        """
        anon_page.goto(f"{BASE_URL}{CLIENT_LINKS_PATH}")
        anon_page.wait_for_load_state("networkidle")

        container = anon_page.locator(".tp-cl-container")
        assert container.count() == 0, (
            "Client Links container should not render for anonymous users"
        )


# -------------------------------------------------------------------
# Expired session tests — simulate session expiry mid-use
# -------------------------------------------------------------------
class TestExpiredSession:
    """
    Simulate a user whose session expires after the page loads.
    The JS fires AJAX calls that should get 401 and show login messages.
    """

    def test_expired_session_redirects_to_login(self, browser: Browser):
        """
        Load the page authenticated, then clear cookies to simulate
        session expiry. The next AJAX call should redirect to /login/.
        """
        login_url = os.getenv("TP_LOGIN_URL", f"{BASE_URL}/login/")
        test_user = os.getenv("TP_TEST_USER", "")
        test_pass = os.getenv("TP_TEST_PASS", "")

        if not test_user or not test_pass:
            pytest.skip("TP_TEST_USER / TP_TEST_PASS not configured")

        context = browser.new_context(
            viewport={"width": 1280, "height": 900},
            ignore_https_errors=True,
        )
        page = context.new_page()

        # Login
        page.goto(login_url)
        page.get_by_label("Username or Email").fill(test_user)
        page.get_by_label("Password").fill(test_pass)
        page.get_by_role("button", name="Login").click()
        page.wait_for_url(f"{BASE_URL}/**", timeout=15_000)

        # Navigate to Client Links page
        page.goto(f"{BASE_URL}{CLIENT_LINKS_PATH}")
        page.wait_for_selector(".tp-cl-container", timeout=10_000)
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        # Clear all cookies to simulate session expiry
        context.clear_cookies()

        # Trigger a reload by clicking a sort header
        header = page.locator('th.tp-cl-sortable[data-sort="created_at"]')
        header.click()

        # Should redirect to login page
        page.wait_for_url("**/login/**", timeout=15_000)
        assert "/login" in page.url, (
            f"Expected redirect to /login, got: {page.url}"
        )

        page.close()
        context.close()


# -------------------------------------------------------------------
# Logs endpoint auth tests
# -------------------------------------------------------------------
class TestLogsEndpointAuth:
    """Verify the logs REST endpoint rejects unauthenticated requests."""

    LOGS_URL = f"{BASE_URL}/wp-json/tp-link-shortener/v1/logs"

    def test_no_api_key_returns_401(self, anon_page: Page):
        """Request without X-API-Key header should be rejected."""
        response = anon_page.request.get(self.LOGS_URL, params={
            "log": "debug",
            "mode": "tail",
            "n": "10",
        })

        # WordPress REST returns 401 when permission_callback returns false
        assert response.status == 401, (
            f"Expected 401, got {response.status}"
        )

    def test_invalid_api_key_returns_401(self, anon_page: Page):
        """Request with wrong API key should be rejected."""
        response = anon_page.request.get(self.LOGS_URL, headers={
            "X-API-Key": "completely-wrong-key",
        }, params={
            "log": "debug",
            "mode": "tail",
            "n": "10",
        })

        assert response.status == 401, (
            f"Expected 401, got {response.status}"
        )

    def test_valid_api_key_returns_200(self, anon_page: Page):
        """Request with valid LOGS_API_KEY should succeed."""
        api_key = os.getenv("TP_LOGS_API_KEY", "")
        if not api_key:
            pytest.skip("TP_LOGS_API_KEY not configured in .env")

        response = anon_page.request.get(self.LOGS_URL, headers={
            "X-API-Key": api_key,
        }, params={
            "log": "debug",
            "mode": "tail",
            "n": "5",
        })

        assert response.status == 200, (
            f"Expected 200, got {response.status}: {response.text()}"
        )

        body = response.json()
        assert "lines" in body
        assert body["log"] == "debug"
        assert body["mode"] == "tail"
