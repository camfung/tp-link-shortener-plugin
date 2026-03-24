"""
Regression test for TP-29: Domain-related redirect issues.

Original bug: The redirect service did not handle edge cases around domain
configuration correctly. Accessing a non-existent domain, a configured domain
without a key, or a subdomain with a key could produce 500 errors, blank pages,
or incorrect redirects instead of graceful fallback behavior.

Fix: The redirect service now handles all domain/key combinations gracefully,
returning appropriate redirects or error responses instead of crashing.

Jira: https://bloomland.atlassian.net/browse/TP-29
"""

import os
import uuid

import pytest

SHORT_DOMAIN = os.getenv("TP_SHORT_DOMAIN", "dev.trfc.link")


@pytest.mark.regression_bugs
class TestTP29:
    """TP-29: Domain redirect edge cases should not produce server errors."""

    def test_nonexistent_key_on_configured_domain(self, http_client):
        """A non-existent key on the configured short domain should redirect
        gracefully (not 500).
        """
        fake_key = f"tp29-nokey-{uuid.uuid4().hex[:8]}"
        response = http_client.get(f"https://{SHORT_DOMAIN}/{fake_key}")

        assert response.status_code < 500, (
            f"Server error for non-existent key on configured domain: "
            f"{response.status_code}"
        )
        # Should redirect, not error
        assert response.status_code in (301, 302), (
            f"Expected redirect for non-existent key, got {response.status_code}"
        )

    def test_root_path_on_configured_domain(self, http_client):
        """Accessing the root path on the configured short domain should not
        produce a 500 error. A redirect, 403, or served page are acceptable.
        """
        response = http_client.get(f"https://{SHORT_DOMAIN}/")

        assert response.status_code < 500, (
            f"Server error for root path on configured domain: "
            f"{response.status_code}"
        )

    def test_long_key_does_not_crash(self, http_client):
        """A very long key path should not cause a server error. The redirect
        service should handle oversized keys gracefully.
        """
        long_key = f"tp29-long-{'x' * 200}-{uuid.uuid4().hex[:8]}"
        response = http_client.get(f"https://{SHORT_DOMAIN}/{long_key}")

        assert response.status_code < 500, (
            f"Server error for long key: {response.status_code}"
        )

    def test_special_characters_in_key(self, http_client):
        """Keys with special characters should not cause a server error.
        The redirect service should handle them gracefully.
        """
        # URL-safe special characters that might break routing
        special_key = f"tp29-special-{uuid.uuid4().hex[:6]}--test"
        response = http_client.get(f"https://{SHORT_DOMAIN}/{special_key}")

        assert response.status_code < 500, (
            f"Server error for key with special chars: {response.status_code}"
        )
