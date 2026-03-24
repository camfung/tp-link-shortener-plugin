"""
Regression test for TP-22: Non-existent key default redirect.

Original bug: Accessing a short link with a non-existent or empty key displayed
an error page or blank page instead of gracefully redirecting to the default
destination. Users who clicked stale or mistyped short links saw broken pages.

Fix: The redirect service now returns a 301/302 redirect to trafficportal.com
for any unrecognized key, ensuring users always land on a valid page.

Jira: https://bloomland.atlassian.net/browse/TP-22
"""

import os
import uuid

import pytest

SHORT_DOMAIN = os.getenv("TP_SHORT_DOMAIN", "dev.trfc.link")


@pytest.mark.regression_bugs
class TestTP22:
    """TP-22: Non-existent key should redirect to trafficportal.com."""

    def test_nonexistent_key_redirects_to_default(self, http_client):
        """Accessing a key that does not exist should 301/302 redirect,
        not return an error page or blank response.

        Uses a UUID-based key to guarantee it has never been created.
        """
        fake_key = f"this-key-does-not-exist-tp22-{uuid.uuid4().hex[:8]}"
        response = http_client.get(f"https://{SHORT_DOMAIN}/{fake_key}")

        assert response.status_code in (301, 302), (
            f"Expected redirect (301/302) for non-existent key, "
            f"got {response.status_code}"
        )
        location = response.headers.get("location", "")
        assert "trafficportal" in location.lower(), (
            f"Expected redirect to trafficportal domain, got Location: {location}"
        )

    def test_empty_key_does_not_error(self, http_client):
        """Accessing the root path (empty key) should not produce a 500
        server error. A 403, redirect, or served page are all acceptable.
        """
        response = http_client.get(f"https://{SHORT_DOMAIN}/")

        # Root path must not produce a server error (5xx)
        assert response.status_code < 500, (
            f"Expected non-error response for root path, "
            f"got {response.status_code}"
        )
        if response.status_code in (301, 302):
            location = response.headers.get("location", "")
            assert "trafficportal" in location.lower(), (
                f"Expected redirect to trafficportal domain, "
                f"got Location: {location}"
            )
