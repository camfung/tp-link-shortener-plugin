"""
Regression test for TP-25: Device-based redirect issues.

Original bug: Short links did not correctly handle different device types.
When accessing a link from a mobile device or via QR scan, the redirect
service either returned the wrong destination or failed to distinguish
between traffic sources, causing incorrect analytics and user experience.

Fix: The redirect service now properly inspects User-Agent headers and
the ?qr=1 query parameter to route traffic correctly and record the
appropriate traffic source in usage analytics.

Jira: https://bloomland.atlassian.net/browse/TP-25
"""

import os
import uuid

import pytest

SHORT_DOMAIN = os.getenv("TP_SHORT_DOMAIN", "dev.trfc.link")

# User-Agent constants for device simulation
MOBILE_UA = (
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) "
    "AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 "
    "Mobile/15E148 Safari/604.1"
)
DESKTOP_UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/120.0.0.0 Safari/537.36"
)


@pytest.mark.regression_bugs
class TestTP25:
    """TP-25: Device-based redirect should not error for any User-Agent."""

    def test_mobile_ua_does_not_error(self, http_client):
        """Accessing a non-existent key with a mobile User-Agent should
        redirect gracefully, not return a 500 or blank page.
        """
        fake_key = f"tp25-mobile-{uuid.uuid4().hex[:8]}"
        response = http_client.get(
            f"https://{SHORT_DOMAIN}/{fake_key}",
            headers={"User-Agent": MOBILE_UA},
        )
        assert response.status_code in (301, 302), (
            f"Expected redirect for mobile UA, got {response.status_code}"
        )
        location = response.headers.get("location", "")
        assert location, "Redirect Location header must not be empty"

    def test_desktop_ua_does_not_error(self, http_client):
        """Accessing a non-existent key with a desktop User-Agent should
        redirect gracefully, not return a 500 or blank page.
        """
        fake_key = f"tp25-desktop-{uuid.uuid4().hex[:8]}"
        response = http_client.get(
            f"https://{SHORT_DOMAIN}/{fake_key}",
            headers={"User-Agent": DESKTOP_UA},
        )
        assert response.status_code in (301, 302), (
            f"Expected redirect for desktop UA, got {response.status_code}"
        )
        location = response.headers.get("location", "")
        assert location, "Redirect Location header must not be empty"

    def test_qr_param_does_not_error(self, http_client):
        """Accessing a link with ?qr=1 query parameter should redirect
        gracefully. The QR parameter is used for traffic source tracking
        and must not cause errors in the redirect service.
        """
        fake_key = f"tp25-qr-{uuid.uuid4().hex[:8]}"
        response = http_client.get(
            f"https://{SHORT_DOMAIN}/{fake_key}?qr=1",
        )
        assert response.status_code in (301, 302), (
            f"Expected redirect with ?qr=1, got {response.status_code}"
        )
        location = response.headers.get("location", "")
        assert location, "Redirect Location header must not be empty"

    def test_different_uas_all_redirect_same_key(self, http_client):
        """All device types should successfully redirect for the same key.
        The redirect service must not fail for any particular UA string.
        """
        fake_key = f"tp25-multi-{uuid.uuid4().hex[:8]}"
        url = f"https://{SHORT_DOMAIN}/{fake_key}"

        for ua_name, ua_string in [("mobile", MOBILE_UA), ("desktop", DESKTOP_UA)]:
            response = http_client.get(url, headers={"User-Agent": ua_string})
            assert response.status_code in (301, 302), (
                f"{ua_name} UA: expected redirect, got {response.status_code}"
            )
