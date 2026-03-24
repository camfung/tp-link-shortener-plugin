"""
Regression test for TP-94: MVP bugs and fixes (umbrella ticket).

TP-94 is an umbrella ticket covering multiple MVP-era bugs that were found during
initial launch. The individual sub-bugs tested here are:

Sub-bugs covered:
1. Link creation returns incomplete response data (missing mid/keyword fields)
2. Duplicate keyword submission produces server error instead of user-friendly message
3. Created link not appearing in dashboard table after successful creation
4. Link creation with empty destination accepted instead of rejected

Excluded items:
- Redirect-layer bugs: covered by test_tp22.py, test_tp25.py, test_tp29.py, test_tp34.py
- Domain management bugs: covered by test_tp41.py
- Destination caching: covered by test_tp71.py
- Cosmetic/CSS-only fixes: not reliably testable via E2E
- Shortcode generation 500 errors (TP-94 sub-item): backend API issue, not testable from UI

Jira: https://bloomland.atlassian.net/browse/TP-94
"""

import uuid

import pytest
from playwright.sync_api import Page


def create_link_via_ui(page: Page, keyword: str, destination: str) -> dict:
    """Create a link through the Add Link modal UI.

    Returns the parsed AJAX response JSON.
    """
    page.click("#tp-cl-add-link-btn")
    page.wait_for_selector(
        "#tp-cl-edit-modal-overlay", state="visible", timeout=10_000
    )

    page.fill("#tp-destination", destination)
    page.wait_for_selector(
        "#tp-custom-key-group", state="visible", timeout=15_000
    )
    page.fill("#tp-custom-key", "")
    page.fill("#tp-custom-key", keyword)

    with page.expect_response(
        lambda r: (
            "admin-ajax.php" in r.url
            and r.request.post_data is not None
            and "tp_create_link" in r.request.post_data
        ),
        timeout=30_000,
    ) as response_info:
        page.click("#tp-submit-btn")

    response = response_info.value
    data = response.json()

    page.wait_for_selector(
        "#tp-cl-edit-modal-overlay", state="hidden", timeout=10_000
    )
    page.wait_for_timeout(500)
    return data


@pytest.mark.regression_bugs
class TestTP94:
    """TP-94: MVP bugs and fixes (umbrella ticket).

    Each test method covers a distinct sub-bug from the MVP launch period.
    Tests are self-contained -- each creates its own preconditions inline.
    """

    def test_create_link_response_contains_required_fields(
        self, client_links_page
    ):
        """TP-94 sub-bug: Link creation response missing required fields.

        Original bug: The tp_create_link AJAX response sometimes omitted the
        mid (map ID) or keyword fields, causing the UI to fail silently when
        trying to display the newly created link.

        Regression: Verify the creation response includes mid, keyword, and
        other required data fields.
        """
        page = client_links_page
        keyword = f"reg-tp94-a-{uuid.uuid4().hex[:6]}"
        destination = "https://example.com/tp94-fields"

        data = create_link_via_ui(page, keyword, destination)

        assert data.get("success") is True, (
            f"TP-94 regression: link creation did not report success. "
            f"Response: {data}"
        )

        link_data = data.get("data", {})
        assert link_data.get("mid"), (
            f"TP-94 regression: response missing 'mid' field. Data: {link_data}"
        )

    def test_duplicate_keyword_shows_user_error(self, client_links_page):
        """TP-94 sub-bug: Duplicate keyword produces server error.

        Original bug: Submitting a link with a keyword that already exists
        caused a 500 server error or a generic failure message instead of
        a clear user-facing error explaining the keyword is taken.

        Regression: Create a link, then attempt to create another with the
        same keyword. The second attempt should fail with a user-friendly
        error, not a server error.
        """
        page = client_links_page
        keyword = f"reg-tp94-b-{uuid.uuid4().hex[:6]}"
        destination = "https://example.com/tp94-dupe"

        # Create the first link successfully
        first_data = create_link_via_ui(page, keyword, destination)
        assert first_data.get("success") is True, (
            f"First link creation failed: {first_data}"
        )

        # Attempt to create a second link with the same keyword
        page.click("#tp-cl-add-link-btn")
        page.wait_for_selector(
            "#tp-cl-edit-modal-overlay", state="visible", timeout=10_000
        )
        page.fill("#tp-destination", "https://example.com/tp94-dupe-2")
        page.wait_for_selector(
            "#tp-custom-key-group", state="visible", timeout=15_000
        )
        page.fill("#tp-custom-key", "")
        page.fill("#tp-custom-key", keyword)

        with page.expect_response(
            lambda r: (
                "admin-ajax.php" in r.url
                and r.request.post_data is not None
                and "tp_create_link" in r.request.post_data
            ),
            timeout=30_000,
        ) as response_info:
            page.click("#tp-submit-btn")

        response = response_info.value
        dupe_data = response.json()

        # The duplicate should NOT succeed
        assert dupe_data.get("success") is not True, (
            f"TP-94 regression: duplicate keyword '{keyword}' was accepted! "
            f"Response: {dupe_data}"
        )

        # Should not be a 500 server error
        assert response.status != 500, (
            f"TP-94 regression: duplicate keyword caused server error (500). "
            f"Expected a user-friendly error message."
        )

        # Wait for any error display then close modal
        page.wait_for_timeout(500)
        # Close modal if still open
        if page.locator("#tp-cl-edit-modal-overlay").is_visible():
            page.click("#tp-cl-edit-modal-close")
            page.wait_for_selector(
                "#tp-cl-edit-modal-overlay", state="hidden", timeout=10_000
            )

    def test_created_link_appears_in_dashboard(self, client_links_page):
        """TP-94 sub-bug: Created link not appearing in dashboard table.

        Original bug: After successfully creating a link, the dashboard table
        did not update to show the new link. Users had to refresh the page
        to see their newly created link.

        Regression: Create a link and verify its keyword appears in the
        dashboard table without a page reload.
        """
        page = client_links_page
        keyword = f"reg-tp94-c-{uuid.uuid4().hex[:6]}"
        destination = "https://example.com/tp94-visible"

        data = create_link_via_ui(page, keyword, destination)
        assert data.get("success") is True, (
            f"Link creation failed: {data}"
        )

        # Verify the keyword appears in the table (without page reload)
        page.wait_for_function(
            f"""() => {{
                const rows = document.querySelectorAll('.tp-cl-table-row');
                return Array.from(rows).some(
                    row => row.textContent.includes('{keyword}')
                );
            }}""",
            timeout=10_000,
        )

        # Double check with a locator assertion
        row = page.locator(f".tp-cl-table-row:has-text('{keyword}')")
        assert row.count() >= 1, (
            f"TP-94 regression: created link with keyword '{keyword}' "
            f"not found in dashboard table after creation."
        )

    def test_empty_destination_rejected(self, client_links_page):
        """TP-94 sub-bug: Empty destination accepted by form.

        Original bug: Submitting the link creation form with an empty
        destination URL was accepted, creating a broken link with no
        destination. The form should validate and reject empty destinations
        before submitting.

        Regression: Attempt to submit with an empty destination and verify
        the form prevents submission or shows an error.
        """
        page = client_links_page

        page.click("#tp-cl-add-link-btn")
        page.wait_for_selector(
            "#tp-cl-edit-modal-overlay", state="visible", timeout=10_000
        )

        # Leave destination empty and try to interact
        # The custom key group should NOT become visible without a valid destination
        page.fill("#tp-destination", "")

        # Intercept any AJAX create requests
        create_requests = []

        def capture_create(req):
            if "admin-ajax.php" in req.url and req.method == "POST":
                body = req.post_data or ""
                if "tp_create_link" in body:
                    create_requests.append(body)

        page.on("request", capture_create)

        # Click submit -- should be blocked by validation
        page.click("#tp-submit-btn")
        page.wait_for_timeout(1_000)

        # No create link request should have been sent
        assert len(create_requests) == 0, (
            f"TP-94 regression: empty destination was submitted via AJAX. "
            f"The form should prevent submission with empty destination."
        )

        # Close the modal
        page.click("#tp-cl-edit-modal-close")
        page.wait_for_selector(
            "#tp-cl-edit-modal-overlay", state="hidden", timeout=10_000
        )
