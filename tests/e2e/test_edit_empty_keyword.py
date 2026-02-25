"""
Integration test for TP-103: empty keyword should NOT allow save when editing a link.

Bug: After editing a link, clearing the keyword field and clicking save
succeeds with "link updated successfully" even though the keyword is empty.

Expected: The form should show a validation error and prevent submission.

Run:
    pytest tests/e2e/test_edit_empty_keyword.py -v
"""

import pytest
from playwright.sync_api import Page, expect


class TestEditEmptyKeywordBlocked:
    """Verify that clearing the keyword field during edit prevents saving."""

    def _open_edit_modal(self, page: Page):
        """Helper: open the edit modal for the first link row."""
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links available to test editing")

        if page.locator("#tp-link-shortener-wrapper").count() == 0:
            pytest.skip("Form shortcode [tp_link_shortener] not on page")

        # Click the clicks column — it has no <a> or <button> children, so
        # the row-click handler won't filter it out.
        row = page.locator("tr[data-mid]").first
        clicks_cell = row.locator("td.tp-cl-col-clicks")
        clicks_cell.click()

        modal = page.locator("#tp-cl-edit-modal-overlay")
        expect(modal).to_be_visible(timeout=10_000)
        return modal

    def test_clear_keyword_shows_error_on_save(self, client_links_page: Page):
        """Clearing the keyword and saving should show an error, not success."""
        page = client_links_page
        self._open_edit_modal(page)

        # Wait for the form to populate with the existing link data
        keyword_input = page.locator("#tp-custom-key")
        page.wait_for_function(
            "document.querySelector('#tp-custom-key').value.length > 0",
            timeout=5_000,
        )

        # Store original keyword for later verification
        original_keyword = keyword_input.input_value()
        assert original_keyword != "", "Keyword should be pre-filled when editing"

        # Clear the keyword field
        keyword_input.fill("")
        assert keyword_input.input_value() == "", "Keyword field should be empty after clearing"

        # Click the save/submit button
        submit_btn = page.locator("#tp-submit-btn")
        submit_btn.click()

        # Should see an error snackbar — NOT a success message
        snackbar = page.locator("#tp-snackbar, .tp-snackbar")
        snackbar.wait_for(state="visible", timeout=5_000)

        snackbar_text = snackbar.inner_text()

        # The snackbar should NOT say "Link updated successfully!"
        assert "updated successfully" not in snackbar_text.lower(), (
            f"Bug TP-103: Empty keyword was accepted! Snackbar said: '{snackbar_text}'"
        )

        # The snackbar should contain an error about the keyword
        assert "keyword" in snackbar_text.lower() or "required" in snackbar_text.lower(), (
            f"Expected a keyword validation error, got: '{snackbar_text}'"
        )

    def test_clear_keyword_does_not_send_ajax(self, client_links_page: Page):
        """Clearing the keyword and saving should NOT fire an AJAX request."""
        page = client_links_page
        self._open_edit_modal(page)

        keyword_input = page.locator("#tp-custom-key")
        page.wait_for_function(
            "document.querySelector('#tp-custom-key').value.length > 0",
            timeout=5_000,
        )

        # Clear the keyword
        keyword_input.fill("")

        # Intercept AJAX POST bodies to detect update/create link requests
        link_requests = []

        def capture_request(req):
            if "admin-ajax.php" in req.url and req.method == "POST":
                body = req.post_data or ""
                if "tp_update_link" in body or "tp_create_link" in body:
                    link_requests.append(body)

        page.on("request", capture_request)

        # Click save
        submit_btn = page.locator("#tp-submit-btn")
        submit_btn.click()

        # Wait a moment for any async activity
        page.wait_for_timeout(1_000)

        # No update/create link AJAX should have been sent
        assert len(link_requests) == 0, (
            f"Bug TP-103: Link save request was sent despite empty keyword."
        )
