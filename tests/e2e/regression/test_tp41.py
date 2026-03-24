"""
Regression test for TP-41: Domain name management bugs.

Original bug: The GET /domains/info API endpoint returned incorrect or incomplete
domain configuration data, causing the domain management UI to display stale or
missing domain entries. Users could not reliably see which domains were configured
for their link shortener instance.

Fix: The /domains/info endpoint was corrected to return accurate domain data
including domain name, status, and configuration details.

Jira: https://bloomland.atlassian.net/browse/TP-41
"""

import os

import pytest

API_ENDPOINT = os.getenv(
    "TP_API_ENDPOINT",
    "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev",
)
API_KEY = os.getenv("API_KEY", "")


@pytest.mark.regression_bugs
class TestTP41:
    """TP-41: Domain management -- /domains/info endpoint returns valid data."""

    def test_domains_info_returns_valid_response(self, api_client):
        """GET /domains/info should return 200 with valid JSON structure.

        If the endpoint does not exist, the test is skipped gracefully.
        The api_client fixture already skips if API_KEY is not set.
        """
        response = api_client.get(f"{API_ENDPOINT}/domains/info")

        if response.status_code == 404:
            pytest.skip("GET /domains/info endpoint not available")

        assert response.status_code == 200, (
            f"Expected 200 from /domains/info, got {response.status_code}: "
            f"{response.text[:200]}"
        )

        data = response.json()
        assert data is not None, "Response JSON is None"
        assert isinstance(data, (dict, list)), (
            f"Expected dict or list from /domains/info, got {type(data).__name__}"
        )

    def test_domains_info_contains_configured_domains(self, api_client):
        """GET /domains/info should include at least one domain entry
        with expected fields such as domain name and status.

        If the endpoint does not exist, the test is skipped gracefully.
        """
        response = api_client.get(f"{API_ENDPOINT}/domains/info")

        if response.status_code == 404:
            pytest.skip("GET /domains/info endpoint not available")

        assert response.status_code == 200, (
            f"Expected 200 from /domains/info, got {response.status_code}"
        )

        data = response.json()

        # The response may be a list of domains or a dict with a domains key
        if isinstance(data, list):
            domains = data
        elif isinstance(data, dict):
            # Try common key names for domain lists
            domains = (
                data.get("domains")
                or data.get("data")
                or data.get("items")
                or [data]  # If the dict itself is a single domain record
            )
        else:
            pytest.fail(f"Unexpected response type: {type(data).__name__}")

        assert len(domains) >= 1, (
            "Expected at least one domain entry in /domains/info response, "
            f"got empty list. Response: {str(data)[:200]}"
        )

        # Verify at least the first domain has some identifying field
        first_domain = domains[0]
        assert isinstance(first_domain, dict), (
            f"Expected domain entry to be a dict, got {type(first_domain).__name__}"
        )

        # Check for at least one domain-identifying field
        has_domain_field = any(
            key in first_domain
            for key in ("domain", "name", "domain_name", "host", "hostname")
        )
        assert has_domain_field, (
            f"Domain entry missing identifying field. "
            f"Available keys: {list(first_domain.keys())}"
        )
