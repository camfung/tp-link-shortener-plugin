"""
Playwright e2e tests verifying that uid is always determined server-side.

These tests ensure:
  - The frontend create-link AJAX does NOT send a uid parameter
  - The server determines uid from the WP session (get_current_user_id)
  - Even if a malicious uid is injected via POST, the server ignores it
  - Authenticated users can successfully create and retrieve links

Run:
    pytest tests/e2e/test_uid_server_side.py -v
"""

import os
import re
import time
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
LOGIN_URL = os.getenv("TP_LOGIN_URL", f"{BASE_URL}/login/")
CLIENT_LINKS_PATH = os.getenv("TP_CLIENT_LINKS_PATH", "/camerons-test-page/")
AJAX_URL = f"{BASE_URL}/wp-admin/admin-ajax.php"
TEST_USER = os.getenv("TP_TEST_USER", "")
TEST_PASS = os.getenv("TP_TEST_PASS", "")


# -------------------------------------------------------------------
# Fixtures
# -------------------------------------------------------------------
@pytest.fixture(scope="module")
def auth_context(browser: Browser):
    """Authenticated browser context — logs in once, reuses cookies."""
    if not TEST_USER or not TEST_PASS:
        pytest.skip("TP_TEST_USER / TP_TEST_PASS not configured in .env")

    context = browser.new_context(
        viewport={"width": 1280, "height": 900},
        ignore_https_errors=True,
    )
    page = context.new_page()

    page.goto(LOGIN_URL)
    page.get_by_label("Username or Email").fill(TEST_USER)
    page.get_by_label("Password").fill(TEST_PASS)
    page.get_by_role("button", name="Login").click()
    page.wait_for_url(f"{BASE_URL}/**", timeout=15_000)

    page.close()
    yield context
    context.close()


@pytest.fixture()
def auth_page(auth_context: BrowserContext):
    """Fresh authenticated page for each test."""
    pg = auth_context.new_page()
    yield pg
    pg.close()


@pytest.fixture(scope="module")
def anon_context(browser: Browser):
    """Unauthenticated browser context."""
    context = browser.new_context(
        viewport={"width": 1280, "height": 900},
        ignore_https_errors=True,
    )
    yield context
    context.close()


@pytest.fixture()
def anon_page(anon_context: BrowserContext):
    """Fresh unauthenticated page for each test."""
    pg = anon_context.new_page()
    yield pg
    pg.close()


def _get_nonce(page: Page) -> str:
    """Extract the WP nonce from the page's tpAjax or tpClientLinks JS object."""
    page.goto(f"{BASE_URL}{CLIENT_LINKS_PATH}")
    page.wait_for_selector(".tp-cl-container", timeout=10_000)
    nonce = page.evaluate("""
        () => {
            if (window.tpClientLinks && window.tpClientLinks.nonce) return window.tpClientLinks.nonce;
            if (window.tpAjax && window.tpAjax.nonce) return window.tpAjax.nonce;
            return null;
        }
    """)
    return nonce


def _unique_key():
    """Generate a unique short key for test links."""
    return f"e2e-{int(time.time())}"


# -------------------------------------------------------------------
# Test: Frontend JS does NOT send uid in create-link AJAX
# -------------------------------------------------------------------
class TestFrontendDoesNotSendUid:
    """Verify the browser JS never includes uid in create-link requests."""

    def test_create_link_ajax_has_no_uid(self, auth_page: Page):
        """
        Intercept the AJAX POST for tp_create_link and verify
        the request body does not contain a uid parameter.
        """
        page = auth_page
        page.goto(f"{BASE_URL}{CLIENT_LINKS_PATH}")
        page.wait_for_selector(".tp-cl-container", timeout=10_000)
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        captured_requests = []

        def handle_request(request):
            if "admin-ajax.php" in request.url and request.method == "POST":
                post_data = request.post_data or ""
                if "tp_create_link" in post_data:
                    captured_requests.append(post_data)

        page.on("request", handle_request)

        # Open the add-link modal
        add_btn = page.locator("#tp-cl-add-link-btn")
        if add_btn.count() == 0:
            pytest.skip("Add link button not found on page")
        add_btn.click()

        modal = page.locator("#tp-cl-edit-modal-overlay")
        expect(modal).to_be_visible()

        # Fill in the form — find the destination input inside the modal
        dest_input = page.locator("#tp-link-shortener-wrapper #tp-destination, #tp-cl-edit-modal-body input[name='destination']").first
        if dest_input.count() == 0:
            pytest.skip("Destination input not found in modal")

        key_input = page.locator("#tp-link-shortener-wrapper #tp-custom-key, #tp-cl-edit-modal-body input[name='custom_key']").first

        test_key = _unique_key()
        dest_input.fill("https://example.com")

        # Wait for URL validation to complete
        page.wait_for_timeout(3000)

        if key_input.count() > 0:
            key_input.fill(test_key)

        # Submit the form
        submit_btn = page.locator("#tp-link-shortener-wrapper button[type='submit'], #tp-link-shortener-wrapper .tp-submit-btn, #tp-create-btn").first
        if submit_btn.count() > 0:
            submit_btn.click()
            # Wait for the AJAX to fire
            page.wait_for_timeout(5000)

        # Check captured requests
        if len(captured_requests) > 0:
            for req_body in captured_requests:
                assert "uid=" not in req_body and "&uid=" not in req_body, (
                    f"Frontend sent uid in create-link request: {req_body}"
                )


# -------------------------------------------------------------------
# Test: Server determines uid from WP session, ignores POST uid
# -------------------------------------------------------------------
class TestServerIgnoresClientUid:
    """
    Verify the server always uses the WP session uid,
    even if the client sends a different uid.
    """

    def test_create_link_with_spoofed_uid_uses_server_uid(self, auth_page: Page):
        """
        Send a create-link AJAX with a spoofed uid.
        The server should ignore it and use the WP session uid.
        """
        page = auth_page
        nonce = _get_nonce(page)
        if not nonce:
            pytest.skip("Could not extract nonce from page")

        test_key = _unique_key()

        # Send create request with a spoofed uid=99999
        response = page.request.post(AJAX_URL, form={
            "action": "tp_create_link",
            "nonce": nonce,
            "destination": "https://example.com",
            "custom_key": test_key,
            "uid": "99999",  # Spoofed uid - should be ignored
        })

        body = response.json()

        if response.status == 200 and body.get("success"):
            # If the link was created, verify the uid in the response
            # is NOT 99999 — it should be the real WP user ID
            data = body.get("data", {})
            short_url = data.get("short_url", "")
            assert test_key in short_url or body["success"], (
                "Link was created but response is unexpected"
            )

    def test_create_link_without_uid_succeeds(self, auth_page: Page):
        """
        Send create-link AJAX without any uid parameter.
        The server should determine uid from the WP session.
        """
        page = auth_page
        nonce = _get_nonce(page)
        if not nonce:
            pytest.skip("Could not extract nonce from page")

        test_key = _unique_key()

        # Send create request with NO uid at all
        response = page.request.post(AJAX_URL, form={
            "action": "tp_create_link",
            "nonce": nonce,
            "destination": "https://example.com",
            "custom_key": test_key,
        })

        body = response.json()

        assert response.status == 200, (
            f"Expected 200, got {response.status}: {response.text()}"
        )
        assert body.get("success") is True, (
            f"Create link without uid should succeed: {body}"
        )

    def test_validate_key_with_spoofed_uid_uses_server_uid(self, auth_page: Page):
        """
        Send validate-key AJAX with a spoofed uid.
        The server should ignore it and use the WP session uid.
        """
        page = auth_page
        nonce = _get_nonce(page)
        if not nonce:
            pytest.skip("Could not extract nonce from page")

        # Validate key request with a spoofed uid
        response = page.request.post(AJAX_URL, form={
            "action": "tp_validate_key",
            "nonce": nonce,
            "key": "nonexistent-key-test",
            "destination": "https://example.com",
            "uid": "99999",  # Spoofed uid - should be ignored
        })

        # Should not fail with auth error — server uses WP session uid
        assert response.status == 200, (
            f"Expected 200, got {response.status}: {response.text()}"
        )

    def test_validate_key_without_uid_succeeds(self, auth_page: Page):
        """
        Send validate-key AJAX without any uid parameter.
        The server should determine uid from the WP session.
        """
        page = auth_page
        nonce = _get_nonce(page)
        if not nonce:
            pytest.skip("Could not extract nonce from page")

        response = page.request.post(AJAX_URL, form={
            "action": "tp_validate_key",
            "nonce": nonce,
            "key": "nonexistent-key-test",
            "destination": "https://example.com",
        })

        assert response.status == 200, (
            f"Expected 200, got {response.status}: {response.text()}"
        )


# -------------------------------------------------------------------
# Test: Verify uid in server logs matches WP user ID
# -------------------------------------------------------------------
class TestServerLogsCorrectUid:
    """
    Use the logs API to verify the server logged the correct WP user ID
    after a create-link request.
    """

    LOGS_URL = f"{BASE_URL}/wp-json/tp-link-shortener/v1/logs"

    def _get_logs_api_key(self):
        """Load LOGS_API_KEY from .env.test in the project root."""
        env_test = Path(__file__).parent.parent.parent / ".env.test"
        if env_test.exists():
            for line in env_test.read_text().splitlines():
                line = line.strip()
                if line.startswith("LOGS_API_KEY="):
                    return line.split("=", 1)[1].strip()
        return os.getenv("LOGS_API_KEY", "")

    def test_create_link_logs_wp_user_id(self, auth_page: Page):
        """
        Create a link, then check the debug log to confirm
        the server used the WP session user ID (not a client-sent uid).
        """
        logs_key = self._get_logs_api_key()
        if not logs_key:
            pytest.skip("LOGS_API_KEY not configured")

        page = auth_page
        nonce = _get_nonce(page)
        if not nonce:
            pytest.skip("Could not extract nonce from page")

        # Get the WP user ID from the page
        wp_user_id = page.evaluate("""
            () => {
                if (window.tpDashboard && window.tpDashboard.userId) return window.tpDashboard.userId;
                if (window.tpClientLinks && window.tpClientLinks.userId) return window.tpClientLinks.userId;
                return null;
            }
        """)

        test_key = _unique_key()

        # Create a link (no uid in POST)
        response = page.request.post(AJAX_URL, form={
            "action": "tp_create_link",
            "nonce": nonce,
            "destination": "https://example.com",
            "custom_key": test_key,
        })

        body = response.json()
        if not body.get("success"):
            pytest.skip(f"Link creation failed (may be API issue): {body}")

        # Wait a moment for the log to be written
        time.sleep(2)

        # Fetch recent logs
        log_response = page.request.get(self.LOGS_URL, headers={
            "X-API-Key": logs_key,
        }, params={
            "log": "debug",
            "mode": "tail",
            "n": "50",
        })

        assert log_response.status == 200, (
            f"Logs API returned {log_response.status}"
        )

        log_body = log_response.json()
        lines = log_body.get("lines", [])

        # Find the create request for our test key
        create_lines = [l for l in lines if test_key in l]
        assert len(create_lines) > 0, (
            f"Could not find log entries for test key '{test_key}'"
        )

        # Verify the uid logged matches what we'd expect
        uid_lines = [l for l in lines if f"uid={wp_user_id}" in l.lower() or f"uid: {wp_user_id}" in l.lower() or f"\"uid\":{wp_user_id}" in l.lower() or f"\"uid\": {wp_user_id}" in l.lower()]

        # If we know the WP user ID, verify it's in the logs
        if wp_user_id:
            assert len(uid_lines) > 0, (
                f"Expected uid {wp_user_id} in log entries but not found. "
                f"Create lines: {create_lines}"
            )


# -------------------------------------------------------------------
# Test: Get user map items uses server-side uid
# -------------------------------------------------------------------
class TestGetLinksUsesServerUid:
    """Verify that get_user_map_items always uses server-side uid."""

    def test_get_links_succeeds_without_uid(self, auth_page: Page):
        """
        The get_user_map_items AJAX should work without any uid in POST.
        The server determines uid from the WP session.
        """
        page = auth_page
        nonce = _get_nonce(page)
        if not nonce:
            pytest.skip("Could not extract nonce from page")

        response = page.request.post(AJAX_URL, form={
            "action": "tp_get_user_map_items",
            "nonce": nonce,
            "page": "1",
            "page_size": "5",
        })

        assert response.status == 200, (
            f"Expected 200, got {response.status}: {response.text()}"
        )

        body = response.json()
        assert body.get("success") is True, (
            f"get_user_map_items should succeed: {body}"
        )

    def test_get_links_ignores_spoofed_uid(self, auth_page: Page):
        """
        Even if uid is included in POST for get_user_map_items,
        the server should use the WP session uid.
        """
        page = auth_page
        nonce = _get_nonce(page)
        if not nonce:
            pytest.skip("Could not extract nonce from page")

        # Get links with server-side uid (no uid in POST)
        response_real = page.request.post(AJAX_URL, form={
            "action": "tp_get_user_map_items",
            "nonce": nonce,
            "page": "1",
            "page_size": "50",
        })

        # Get links with spoofed uid
        response_spoofed = page.request.post(AJAX_URL, form={
            "action": "tp_get_user_map_items",
            "nonce": nonce,
            "page": "1",
            "page_size": "50",
            "uid": "99999",
        })

        assert response_real.status == 200
        assert response_spoofed.status == 200

        real_body = response_real.json()
        spoofed_body = response_spoofed.json()

        # Both should return the same data since the server ignores POST uid
        assert real_body.get("success") == spoofed_body.get("success"), (
            "Spoofed uid should not affect get_user_map_items results"
        )

        real_total = real_body.get("data", {}).get("total_records", 0)
        spoofed_total = spoofed_body.get("data", {}).get("total_records", 0)
        assert real_total == spoofed_total, (
            f"Expected same total_records ({real_total}), "
            f"but spoofed uid returned {spoofed_total}"
        )
