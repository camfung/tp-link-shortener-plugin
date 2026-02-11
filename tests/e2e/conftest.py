"""
Playwright e2e test fixtures for Client Links page.

Setup:
    pip install pytest pytest-playwright
    playwright install chromium

Usage:
    pytest tests/e2e/ --headed          # run with visible browser
    pytest tests/e2e/                   # run headless (default)
    pytest tests/e2e/ -k test_table     # run specific test
"""

import os
from pathlib import Path

import pytest
from playwright.sync_api import Page, Browser, BrowserContext

# -------------------------------------------------------------------
# Load .env file from the same directory as this conftest
# -------------------------------------------------------------------
_env_path = Path(__file__).parent / ".env"
if _env_path.exists():
    for line in _env_path.read_text().splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            key, _, value = line.partition("=")
            os.environ.setdefault(key.strip(), value.strip())

# -------------------------------------------------------------------
# Configuration — override via environment variables or .env
# -------------------------------------------------------------------
BASE_URL = os.getenv("TP_BASE_URL", "https://trafficportal.dev")
LOGIN_URL = os.getenv("TP_LOGIN_URL", f"{BASE_URL}/login/")
CLIENT_LINKS_PATH = os.getenv("TP_CLIENT_LINKS_PATH", "/camerons-test-page/")
TEST_USER = os.getenv("TP_TEST_USER", "")
TEST_PASS = os.getenv("TP_TEST_PASS", "")


@pytest.fixture(scope="session")
def auth_context(browser: Browser):
    """
    Create an authenticated browser context.
    Logs in once and reuses cookies for all tests.
    """
    context = browser.new_context(
        viewport={"width": 1280, "height": 900},
        ignore_https_errors=True,
    )
    page = context.new_page()

    # WordPress login (custom UsersWP form)
    page.goto(LOGIN_URL)
    page.get_by_label("Username or Email").fill(TEST_USER)
    page.get_by_label("Password").fill(TEST_PASS)
    page.get_by_role("button", name="Login").click()
    page.wait_for_url(f"{BASE_URL}/**", timeout=15_000)

    page.close()
    yield context
    context.close()


@pytest.fixture()
def page(auth_context: BrowserContext):
    """
    Provide a fresh page (tab) for each test, already authenticated.
    """
    pg = auth_context.new_page()
    yield pg
    pg.close()


@pytest.fixture()
def client_links_page(page: Page):
    """Navigate to the Client Links page and wait for it to load."""
    page.goto(f"{BASE_URL}{CLIENT_LINKS_PATH}")
    # Wait for the container to appear
    page.wait_for_selector(".tp-cl-container", timeout=10_000)
    return page
