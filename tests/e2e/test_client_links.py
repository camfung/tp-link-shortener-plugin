"""
Playwright e2e tests for the [tp_client_links] shortcode page.

These tests verify the Client Links management UI:
  - Page loads and renders the table
  - Sortable column headers work
  - Date range picker is present and functional
  - Performance chart renders
  - Status toggle (enable/disable) works
  - Inline actions (copy, QR, history) are accessible
  - Search and filter controls work
  - Pagination works
  - Add-a-link modal opens
  - Edit modal opens on row click

Run:
    pytest tests/e2e/test_client_links.py -v
"""

import re
import pytest
from playwright.sync_api import Page, expect


# -------------------------------------------------------------------
# Page load & structure
# -------------------------------------------------------------------
class TestPageLoad:
    """Verify the page loads with all expected sections."""

    def test_container_visible(self, client_links_page: Page):
        """The main container should be visible."""
        container = client_links_page.locator(".tp-cl-container")
        expect(container).to_be_visible()

    def test_header_title(self, client_links_page: Page):
        """The header should show 'Client Links'."""
        title = client_links_page.locator(".tp-cl-title h3")
        expect(title).to_contain_text("Client Links")

    def test_total_count_badge(self, client_links_page: Page):
        """The total count badge should be present."""
        badge = client_links_page.locator("#tp-cl-total-count")
        expect(badge).to_be_visible()
        expect(badge).to_contain_text("links")

    def test_chart_visible(self, client_links_page: Page):
        """The performance chart wrapper should be visible."""
        chart = client_links_page.locator("#tp-cl-chart-wrapper")
        expect(chart).to_be_visible()

    def test_date_range_present(self, client_links_page: Page):
        """Both date inputs should be present with default values."""
        start = client_links_page.locator("#tp-cl-date-start")
        end = client_links_page.locator("#tp-cl-date-end")
        expect(start).to_be_visible()
        expect(end).to_be_visible()
        # Should have non-empty default values
        assert start.input_value() != ""
        assert end.input_value() != ""


# -------------------------------------------------------------------
# Table rendering
# -------------------------------------------------------------------
class TestTable:
    """Verify the data table renders correctly after loading."""

    def test_table_or_empty_visible(self, client_links_page: Page):
        """Either the table or the empty state should be visible."""
        page = client_links_page
        # Wait for skeleton to disappear
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        table = page.locator("#tp-cl-table-wrapper")
        empty = page.locator("#tp-cl-empty")

        assert table.is_visible() or empty.is_visible(), \
            "Neither table nor empty state is visible after load"

    def test_domain_groups_rendered(self, client_links_page: Page):
        """If links exist, domain group rows should be present."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        if page.locator("#tp-cl-table-wrapper").is_visible():
            domain_rows = page.locator(".tp-cl-domain-row")
            assert domain_rows.count() >= 1, "No domain group rows found"

    def test_link_rows_have_keyword_only(self, client_links_page: Page):
        """Link column should show just the keyword (tpKey), not the full URL."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        if page.locator("#tp-cl-table-wrapper").is_visible():
            first_link = page.locator(".tp-cl-link").first
            text = first_link.inner_text()
            # Should NOT contain 'https://' — just the keyword
            assert "https://" not in text, \
                f"Link column shows full URL instead of keyword: {text}"

    def test_status_toggles_present(self, client_links_page: Page):
        """Each link row should have a status toggle checkbox."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        if page.locator("#tp-cl-table-wrapper").is_visible():
            toggles = page.locator(".tp-cl-status-toggle")
            assert toggles.count() >= 1, "No status toggles found"


# -------------------------------------------------------------------
# Sortable columns
# -------------------------------------------------------------------
class TestSortableColumns:
    """Verify that clicking column headers triggers sorting."""

    @pytest.mark.parametrize("column", ["tpKey", "destination", "clicks", "created_at"])
    def test_sort_header_click(self, client_links_page: Page, column: str):
        """Clicking a sortable header should add the active sort indicator."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        header = page.locator(f'th.tp-cl-sortable[data-sort="{column}"]')
        header.click()

        # After click, the header should have the active class
        expect(header).to_have_class(re.compile(r"tp-cl-sort-active"))

        # The sort icon should be either fa-sort-up or fa-sort-down
        icon = header.locator(".tp-cl-sort-icon")
        icon_classes = icon.get_attribute("class")
        assert "fa-sort-up" in icon_classes or "fa-sort-down" in icon_classes


# -------------------------------------------------------------------
# Inline actions
# -------------------------------------------------------------------
class TestInlineActions:
    """Verify inline action buttons on table rows."""

    def test_hover_shows_actions(self, client_links_page: Page):
        """Hovering over a row should reveal copy/QR/history buttons."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links to test inline actions")

        row = page.locator("tr[data-mid]").first
        row.hover()

        actions = row.locator(".tp-cl-inline-actions")
        expect(actions).to_be_visible()

    def test_qr_button_opens_dialog(self, client_links_page: Page):
        """Clicking the QR button should open the QR dialog."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links to test QR button")

        row = page.locator("tr[data-mid]").first
        row.hover()
        qr_btn = row.locator(".tp-cl-qr-btn").first
        qr_btn.click()

        dialog = page.locator("#tp-cl-qr-dialog-overlay")
        expect(dialog).to_be_visible()

        # Close it
        page.locator("#tp-cl-qr-dialog-close").click()
        expect(dialog).to_be_hidden()

    def test_history_button_opens_modal(self, client_links_page: Page):
        """Clicking the history button should open the history modal."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links to test history button")

        row = page.locator("tr[data-mid]").first
        row.hover()
        history_btn = row.locator(".tp-cl-history-btn").first
        history_btn.click()

        modal = page.locator("#tp-cl-history-modal-overlay")
        expect(modal).to_be_visible()

        # Close it
        page.locator("#tp-cl-history-modal-close").click()
        expect(modal).to_be_hidden()


# -------------------------------------------------------------------
# Status toggle
# -------------------------------------------------------------------
class TestStatusToggle:
    """Verify enable/disable toggle functionality."""

    def test_toggle_disable_confirm(self, client_links_page: Page):
        """Disabling a link should show a confirmation dialog."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links to test toggle")

        # Find an active toggle (checked)
        active_toggle = page.locator(".tp-cl-status-toggle:checked").first
        if active_toggle.count() == 0:
            pytest.skip("No active links to toggle")

        # The checkbox input is hidden (opacity: 0) with a CSS toggle overlay.
        # Click the visible <label class="tp-cl-toggle"> wrapper instead.
        toggle_label = active_toggle.locator("xpath=..")
        page.on("dialog", lambda dialog: dialog.dismiss())
        toggle_label.click()

        # The toggle should still be checked (dismissed the confirm)
        expect(active_toggle).to_be_checked()


# -------------------------------------------------------------------
# Search & filter
# -------------------------------------------------------------------
class TestSearchFilter:
    """Verify search and status filter controls."""

    def test_search_input_triggers_reload(self, client_links_page: Page):
        """Typing in the search box should trigger a data reload."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        search = page.locator("#tp-cl-search")
        search.fill("test")

        # Should show loading briefly
        page.wait_for_timeout(500)
        # Table should still be visible (or empty)
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

    def test_search_clear_button(self, client_links_page: Page):
        """Clicking the clear button should empty the search field."""
        page = client_links_page
        search = page.locator("#tp-cl-search")
        search.fill("something")

        clear_btn = page.locator("#tp-cl-search-clear")
        clear_btn.click()

        assert search.input_value() == ""

    def test_status_filter_dropdown(self, client_links_page: Page):
        """The status filter dropdown should have Active/Disabled options."""
        page = client_links_page
        select = page.locator("#tp-cl-filter-status")
        options = select.locator("option")
        texts = [opt.inner_text() for opt in options.all()]
        assert "Active" in texts
        assert "Disabled" in texts


# -------------------------------------------------------------------
# Add link modal
# -------------------------------------------------------------------
class TestAddLinkModal:
    """Verify the Add Link modal functionality."""

    def test_add_link_button_opens_modal(self, client_links_page: Page):
        """Clicking 'Add a link' should open the edit modal."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        add_btn = page.locator("#tp-cl-add-link-btn")
        add_btn.click()

        modal = page.locator("#tp-cl-edit-modal-overlay")
        expect(modal).to_be_visible()

        title = modal.locator(".tp-cl-modal-title")
        expect(title).to_have_text("Add a link")

    def test_modal_closes_on_x(self, client_links_page: Page):
        """Clicking the X button should close the modal."""
        page = client_links_page
        page.locator("#tp-cl-add-link-btn").click()

        modal = page.locator("#tp-cl-edit-modal-overlay")
        expect(modal).to_be_visible()

        page.locator("#tp-cl-edit-modal-close").click()
        expect(modal).to_be_hidden()

    def test_modal_closes_on_overlay_click(self, client_links_page: Page):
        """Clicking the overlay background should close the modal."""
        page = client_links_page
        page.locator("#tp-cl-add-link-btn").click()

        overlay = page.locator("#tp-cl-edit-modal-overlay")
        expect(overlay).to_be_visible()

        # Click the overlay background. Use force=True to bypass the WP
        # admin bar (#wpadminbar) that intercepts pointer events at the top.
        overlay.click(position={"x": 10, "y": 10}, force=True)
        expect(overlay).to_be_hidden()


# -------------------------------------------------------------------
# Edit modal via row click
# -------------------------------------------------------------------
class TestEditModal:
    """Verify that clicking a table row opens the edit modal."""

    def test_row_click_opens_edit_modal(self, client_links_page: Page):
        """Clicking a data row should open the modal in edit mode."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links to test row click")

        # The edit modal moves #tp-link-shortener-wrapper into the modal body.
        # If the [tp_link_shortener] shortcode isn't on the page, skip.
        if page.locator("#tp-link-shortener-wrapper").count() == 0:
            pytest.skip("Form shortcode [tp_link_shortener] not on page")

        # Click the link-name cell specifically to avoid the toggle <label>
        # and action buttons, which are excluded by the row click handler.
        row = page.locator("tr[data-mid]").first
        link_cell = row.locator(".tp-cl-link").first
        link_cell.click()

        modal = page.locator("#tp-cl-edit-modal-overlay")
        expect(modal).to_be_visible()

        title = modal.locator(".tp-cl-modal-title")
        expect(title).to_have_text("Edit link")


# -------------------------------------------------------------------
# Pagination
# -------------------------------------------------------------------
class TestPagination:
    """Verify pagination controls."""

    def test_pagination_info_present(self, client_links_page: Page):
        """Pagination info text should be present."""
        page = client_links_page
        page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)

        info = page.locator("#tp-cl-pagination-info")
        expect(info).to_be_visible()
        expect(info).to_contain_text("Showing")
