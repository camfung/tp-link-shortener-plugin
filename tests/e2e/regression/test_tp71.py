"""
Regression test for TP-71: Link shortener uploading wrong destination (caching bug).

Original bug: When creating or updating a short link, the stored destination URL
did not always match what the user submitted. The form or API cached a previously
entered destination, causing newly created links to point to a stale URL. Similarly,
editing a link's destination could appear to succeed in the UI but the old value
persisted when the edit modal was reopened.

Fix: Caching logic was corrected so that link creation and update operations
always store the exact destination the user submitted.

Jira: https://bloomland.atlassian.net/browse/TP-71
"""

import uuid

import pytest
from playwright.sync_api import Page


def create_link_via_ui(page: Page, keyword: str, destination: str) -> str:
    """Create a link through the Add Link modal UI. Returns the MID."""
    page.click("#tp-cl-add-link-btn")
    page.wait_for_selector(
        "#tp-cl-edit-modal-overlay", state="visible", timeout=10_000
    )

    # Fill destination
    page.fill("#tp-destination", destination)

    # Wait for URL validation to expose custom key field
    page.wait_for_selector(
        "#tp-custom-key-group", state="visible", timeout=15_000
    )

    # Clear and fill custom keyword
    page.fill("#tp-custom-key", "")
    page.fill("#tp-custom-key", keyword)

    # Submit and capture response
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
    mid = data.get("data", {}).get("mid", "")

    # Wait for modal to close
    page.wait_for_selector(
        "#tp-cl-edit-modal-overlay", state="hidden", timeout=10_000
    )
    page.wait_for_timeout(500)
    return mid


def open_edit_modal_for_keyword(page: Page, keyword: str) -> None:
    """Click a table row matching the keyword to open the edit modal."""
    # Find the row containing the keyword and click it
    row_selector = f".tp-cl-table-row:has-text('{keyword}')"
    page.wait_for_selector(row_selector, timeout=10_000)
    page.click(row_selector)

    # Wait for edit modal to open and populate
    page.wait_for_selector(
        "#tp-cl-edit-modal-overlay", state="visible", timeout=10_000
    )
    # Wait for the destination field to be populated (not empty)
    page.wait_for_function(
        """() => {
            const input = document.querySelector('#tp-destination');
            return input && input.value && input.value.trim().length > 0;
        }""",
        timeout=10_000,
    )


def get_destination_from_edit_modal(page: Page) -> str:
    """Read the current destination value from the open edit modal."""
    return page.input_value("#tp-destination")


def close_edit_modal(page: Page) -> None:
    """Close the edit modal."""
    page.click("#tp-cl-edit-modal-close")
    page.wait_for_selector(
        "#tp-cl-edit-modal-overlay", state="hidden", timeout=10_000
    )
    page.wait_for_timeout(300)


@pytest.mark.regression_bugs
class TestTP71:
    """TP-71: Destination must not be cached -- stored value must match submission."""

    def test_destination_matches_submission(self, client_links_page):
        """Creating a link stores the exact destination URL that was submitted.

        Steps:
        1. Create a link with destination "https://example.com/original"
        2. Open the edit modal for that link
        3. Verify the destination field shows "https://example.com/original"
        """
        page = client_links_page
        keyword = f"reg-tp71-{uuid.uuid4().hex[:6]}"
        destination = "https://example.com/original"

        create_link_via_ui(page, keyword, destination)

        # Open edit modal and verify stored destination
        open_edit_modal_for_keyword(page, keyword)
        stored = get_destination_from_edit_modal(page)

        assert stored == destination, (
            f"TP-71 regression: destination mismatch after creation. "
            f"Submitted '{destination}', but edit modal shows '{stored}'"
        )

        close_edit_modal(page)

    def test_destination_updates_not_cached(self, client_links_page):
        """Updating a link's destination persists the new value, not the old cached one.

        Steps:
        1. Create a link with destination "https://example.com/before"
        2. Open edit modal, change destination to "https://example.com/after"
        3. Save the edit
        4. Reopen edit modal and verify destination is "https://example.com/after"
        """
        page = client_links_page
        keyword = f"reg-tp71-{uuid.uuid4().hex[:6]}"
        original_dest = "https://example.com/before"
        updated_dest = "https://example.com/after"

        # Create the link
        create_link_via_ui(page, keyword, original_dest)

        # Open edit modal
        open_edit_modal_for_keyword(page, keyword)

        # Clear destination and enter new value
        page.fill("#tp-destination", "")
        page.fill("#tp-destination", updated_dest)

        # Submit the update
        with page.expect_response(
            lambda r: (
                "admin-ajax.php" in r.url
                and r.request.post_data is not None
                and "tp_update_link" in r.request.post_data
            ),
            timeout=30_000,
        ) as response_info:
            page.click("#tp-submit-btn")

        response = response_info.value
        assert response.status in (200,), (
            f"Update request failed with status {response.status}"
        )

        # Wait for modal to close after update
        page.wait_for_selector(
            "#tp-cl-edit-modal-overlay", state="hidden", timeout=10_000
        )
        page.wait_for_timeout(500)

        # Reopen edit modal and verify the destination was updated
        open_edit_modal_for_keyword(page, keyword)
        stored = get_destination_from_edit_modal(page)

        assert stored == updated_dest, (
            f"TP-71 regression: destination cached after update. "
            f"Changed to '{updated_dest}', but edit modal still shows '{stored}'"
        )

        close_edit_modal(page)
