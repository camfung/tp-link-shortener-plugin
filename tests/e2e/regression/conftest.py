"""
Regression test fixtures for known Jira bug reproductions.

Tests here are marked with @pytest.mark.regression_bugs and excluded
from the default test run. Run with: pytest -m regression_bugs

Provides shared fixtures:
- unique_keyword: generates isolated test keywords with uuid
- http_client: httpx.Client for redirect testing (follow_redirects=False)
- api_client: httpx.Client with API key header for API-level tests
"""

import os
import uuid

import httpx
import pytest

SHORT_DOMAIN = os.getenv("TP_SHORT_DOMAIN", "dev.trfc.link")
API_ENDPOINT = os.getenv(
    "TP_API_ENDPOINT",
    "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev",
)
API_KEY = os.getenv("API_KEY", "")


@pytest.fixture()
def unique_keyword():
    """Generate a unique keyword for test isolation."""
    return f"reg-{uuid.uuid4().hex[:8]}"


@pytest.fixture()
def http_client():
    """Provide an httpx client configured for redirect testing.

    - timeout=15s for slow dev environments
    - verify=False for self-signed certs
    - follow_redirects=False to inspect raw 301/302 responses
    """
    with httpx.Client(
        timeout=15.0,
        verify=False,
        follow_redirects=False,
    ) as client:
        yield client


@pytest.fixture()
def api_client():
    """Provide an httpx client configured for API testing.

    Skips the test if API_KEY is not set in the environment.
    """
    if not API_KEY:
        pytest.skip("API_KEY not set in environment")
    with httpx.Client(
        timeout=15.0,
        headers={"x-api-key": API_KEY, "Content-Type": "application/json"},
    ) as client:
        yield client
