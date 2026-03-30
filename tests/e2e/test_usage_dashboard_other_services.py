"""
Playwright e2e tests for Usage Dashboard: Credits column, tooltips, and summary card.

These tests verify:
  - Credits column header is visible and sortable
  - Cell values display in +$X.XX format (or $0.00 / dash for zero)
  - Bootstrap tooltips appear on hover over Credits amounts
  - 4th summary card (Credits) is visible with correct icon and value

Requires Phase 12 code deployed to dev site.

Run:
    pytest tests/e2e/test_usage_dashboard_other_services.py -v

NOTE: Requires Phase 12 code deployed via `deploy.sh feature/client-links`.
Tests auto-skip if tp-ud- implementation is not active or no wallet data exists.
"""

import re
import pytest
from playwright.sync_api import Page, expect

from conftest import BASE_URL, USAGE_DASHBOARD_PATH


# -------------------------------------------------------------------
# Deployment detection (self-contained helpers, same pattern as table tests)
# -------------------------------------------------------------------
def _check_deployment(page: Page) -> bool:
    """Check if Phase 5+ tp-ud- implementation is deployed."""
    page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
    page.wait_for_load_state("networkidle")
    return page.locator(".tp-ud-container").count() > 0


def _require_deployment(page: Page):
    """Skip test if Phase 5+ code is not deployed."""
    if not _check_deployment(page):
        pytest.skip(
            "Phase 5+ tp-ud- implementation not deployed. "
            "Deploy commits from feature/client-links and re-run."
        )


def _wait_for_data(page: Page):
    """Navigate to dashboard and wait for data to fully load."""
    _require_deployment(page)
    page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
    page.wait_for_selector(".tp-ud-container", timeout=10_000)
    page.wait_for_selector("#tp-ud-skeleton", state="hidden", timeout=45_000)


def _has_table_data(page: Page) -> bool:
    """Check if the table container is visible with rows."""
    table = page.locator("#tp-ud-table-container")
    return table.is_visible() and page.locator("#tp-ud-tbody tr").count() > 0


# -------------------------------------------------------------------
# Credits column
# -------------------------------------------------------------------
class TestCreditsColumn:
    """Verify the Credits column renders correctly."""

    def test_credits_header_visible(self, page: Page):
        """Credits sortable header should be visible."""
        _wait_for_data(page)
        header = page.locator('th.tp-ud-sortable[data-sort="otherServices"]')
        expect(header).to_be_visible()

    def test_credits_header_is_sortable(self, page: Page):
        """Clicking the Credits header should activate sort."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data for sort test")

        header = page.locator('th.tp-ud-sortable[data-sort="otherServices"]')
        header.click()

        # After click, header should have active class
        expect(header).to_have_class(re.compile(r"tp-ud-sort-active"))

        # Sort icon should be fa-sort-up or fa-sort-down
        icon = header.locator(".tp-ud-sort-icon")
        icon_classes = icon.get_attribute("class")
        assert "fa-sort-up" in icon_classes or "fa-sort-down" in icon_classes, \
            f"Expected sort direction icon, got: {icon_classes}"

    def test_credits_cell_format(self, page: Page):
        """Credits cell should show +$X.XX, $0.00, or a dash."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        cell = page.locator("#tp-ud-tbody tr").first.locator(".tp-ud-col-other")
        amount = cell.locator(".tp-ud-other-amount")
        zero = cell.locator(".tp-ud-other-zero")

        # Cell should contain one of: amount with +$, zero showing $0.00, or a dash
        has_amount = amount.count() > 0
        has_zero = zero.count() > 0
        cell_text = cell.inner_text().strip()
        has_dash = cell_text == "-"

        assert has_amount or has_zero or has_dash, \
            f"Credits cell should show amount, zero, or dash. Got: {cell_text}"

    def test_credits_amounts_have_plus_prefix(self, page: Page):
        """Non-zero Credits amounts should start with +$."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        amounts = page.locator(".tp-ud-other-amount")
        if amounts.count() == 0:
            pytest.skip("No wallet data in current date range")

        for i in range(amounts.count()):
            text = amounts.nth(i).inner_text().strip()
            assert text.startswith("+$"), \
                f"Amount should start with +$, got: {text}"
            assert re.match(r"\+\$\d+\.\d{2}$", text), \
                f"Amount should match +$X.XX format, got: {text}"


# -------------------------------------------------------------------
# Tooltips
# -------------------------------------------------------------------
class TestCreditsTooltip:
    """Verify Bootstrap tooltips on Credits amounts."""

    def test_tooltip_appears_on_hover(self, page: Page):
        """Hovering over a Credits amount should show a tooltip."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        amounts = page.locator(".tp-ud-other-amount")
        if amounts.count() == 0:
            pytest.skip("No wallet data in current date range")

        # Hover over the first amount
        amounts.first.hover()

        # Bootstrap tooltip renders to body with container: 'body'
        tooltip = page.locator(".tooltip-inner")
        tooltip.wait_for(state="visible", timeout=3000)
        assert tooltip.inner_text().strip() != "", \
            "Tooltip should have content"

    def test_tooltip_disappears_after_unhover(self, page: Page):
        """Tooltip should disappear when hovering away from the amount."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        amounts = page.locator(".tp-ud-other-amount")
        if amounts.count() == 0:
            pytest.skip("No wallet data in current date range")

        # Hover to show tooltip
        amounts.first.hover()
        tooltip = page.locator(".tooltip-inner")
        tooltip.wait_for(state="visible", timeout=3000)

        # Hover away to a different element (table header)
        page.locator('th.tp-ud-sortable[data-sort="date"]').hover()

        # Tooltip should disappear
        page.wait_for_timeout(500)
        assert tooltip.count() == 0 or not tooltip.is_visible(), \
            "Tooltip should disappear after unhovering"


# -------------------------------------------------------------------
# Summary card
# -------------------------------------------------------------------
class TestCreditsSummaryCard:
    """Verify the 4th summary card for Credits."""

    def test_fourth_summary_card_visible(self, page: Page):
        """The 4th summary card (Credits) should be visible."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        card = page.locator(".tp-ud-stat-card:nth-child(4)")
        expect(card).to_be_visible()

    def test_fourth_card_has_icon(self, page: Page):
        """The 4th summary card should have the hand-holding-dollar icon."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        icon = page.locator(".tp-ud-stat-card:nth-child(4) .fa-hand-holding-dollar")
        assert icon.count() > 0, \
            "4th card should have fa-hand-holding-dollar icon"

    def test_fourth_card_has_dollar_value(self, page: Page):
        """The 4th summary card should display a dollar value."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        card = page.locator(".tp-ud-stat-card:nth-child(4)")
        value = card.locator(".tp-ud-stat-value").inner_text().strip()
        assert "$" in value, f"4th card value should contain $, got: {value}"
        assert re.match(r"\+?\$\d+\.\d{2}$", value), \
            f"4th card value should match $X.XX format, got: {value}"
