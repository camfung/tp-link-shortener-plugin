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
import pytest
from playwright.sync_api import sync_playwright, Page, Browser, BrowserContext

# -------------------------------------------------------------------
# Configuration — override via environment variables
# -------------------------------------------------------------------
BASE_URL = os.getenv("TP_BASE_URL", "https://trafficportal.dev")
LOGIN_URL = os.getenv("TP_LOGIN_URL", f"{BASE_URL}/wp-login.php")
CLIENT_LINKS_PATH = os.getenv("TP_CLIENT_LINKS_PATH", "/client-links/")
TEST_USER = os.getenv("TP_TEST_USER", "TestUser@gmail.com")
TEST_PASS = os.getenv("TP_TEST_PASS", "Test123456!?")


@pytest.fixture(scope="session")
def browser():
    """Launch a single browser instance for the whole test session."""
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        yield browser
        browser.close()


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

    # WordPress login
    page.goto(LOGIN_URL)
    page.fill("#user_login", TEST_USER)
    page.fill("#user_pass", TEST_PASS)
    page.click("#wp-submit")
    page.wait_for_url(f"{BASE_URL}/**")

    page.close()
    yield context
    context.close()


@pytest.fixture()
def page(auth_context: BrowserContext):
    """
    Provide a fresh page (tab) for each test, already authenticated.
    """
    page = auth_context.new_page()
    yield page
    page.close()


@pytest.fixture()
def client_links_page(page: Page):
    """Navigate to the Client Links page and wait for it to load."""
    page.goto(f"{BASE_URL}{CLIENT_LINKS_PATH}")
    # Wait for the container to appear
    page.wait_for_selector(".tp-cl-container", timeout=10_000)
    return page
