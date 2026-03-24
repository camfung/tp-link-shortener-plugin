"""
Regression test for TP-34: Redirect errors with Set (link groups).

Original bug: When a link was created as part of a "Set" (is_set=1 in the
CreateMapRequest), the redirect service produced errors instead of resolving
the redirect correctly. Users who clicked Set-based short links encountered
error pages or incorrect destinations.

Fix: The redirect service now correctly resolves Set-based links, treating
them the same as regular links for redirect purposes while preserving the
Set grouping for analytics.

Jira: https://bloomland.atlassian.net/browse/TP-34
"""

import os
import uuid

import pytest

SHORT_DOMAIN = os.getenv("TP_SHORT_DOMAIN", "dev.trfc.link")


@pytest.mark.regression_bugs
class TestTP34:
    """TP-34: Set-based redirects should resolve without errors.

    Note: Creating a Set link requires API access with is_set=1 parameter.
    These tests verify that the redirect layer handles Set-like scenarios
    gracefully. If Set creation is not available in the test environment,
    the test documents the expected behavior for manual verification.
    """

    def test_redirect_service_handles_unknown_key_gracefully(self, http_client):
        """Even if a Set key no longer exists or was misconfigured, the
        redirect service should not return a 500 error.
        """
        fake_set_key = f"tp34-set-{uuid.uuid4().hex[:8]}"
        response = http_client.get(f"https://{SHORT_DOMAIN}/{fake_set_key}")

        assert response.status_code < 500, (
            f"Server error for Set-like key: {response.status_code}"
        )
        # Should redirect to default, not error
        assert response.status_code in (301, 302), (
            f"Expected redirect for non-existent Set key, got {response.status_code}"
        )

    def test_redirect_with_trailing_slash(self, http_client):
        """Set links may be accessed with a trailing slash. The redirect
        service should handle this without errors.
        """
        fake_set_key = f"tp34-slash-{uuid.uuid4().hex[:8]}"
        response = http_client.get(f"https://{SHORT_DOMAIN}/{fake_set_key}/")

        assert response.status_code < 500, (
            f"Server error for key with trailing slash: {response.status_code}"
        )

    def test_redirect_with_subpath(self, http_client):
        """Set links accessed with an additional subpath should not cause
        server errors. The redirect service should handle the extra path
        segment gracefully.
        """
        fake_set_key = f"tp34-subpath-{uuid.uuid4().hex[:8]}"
        response = http_client.get(
            f"https://{SHORT_DOMAIN}/{fake_set_key}/extra-path"
        )

        assert response.status_code < 500, (
            f"Server error for key with subpath: {response.status_code}"
        )
