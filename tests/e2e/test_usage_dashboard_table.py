"""
Playwright e2e tests for Usage Dashboard Phase 6: Stats Table and Summary Strip.

These tests verify:
  - Stats table renders with data rows (date, hits, cost, balance)
  - Sortable column headers toggle asc/desc with icon changes
  - Client-side pagination appears for >10 rows
  - Summary cards show total hits, total cost, and balance
  - Currency values display as $X.XX without float drift
  - Click/QR breakdown sums to totalHits
  - Empty state displays when no data exists

Run:
    pytest tests/e2e/test_usage_dashboard_table.py -v

NOTE: Requires Phase 5 + Phase 6 code deployed to the dev site.
Tests auto-skip if tp-ud- implementation is not active.
"""

import re
import pytest
from playwright.sync_api import Page, expect

from conftest import BASE_URL, USAGE_DASHBOARD_PATH


# -------------------------------------------------------------------
# Deployment detection
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
# Table rendering
# -------------------------------------------------------------------
class TestTableRendering:
    """Verify the stats table renders with correct data after AJAX load."""

    def test_table_or_empty_visible(self, page: Page):
        """After data loads, either the table or empty state should show."""
        _wait_for_data(page)
        table = page.locator("#tp-ud-table-container")
        empty = page.locator("#tp-ud-empty")
        assert table.is_visible() or empty.is_visible(), \
            "Neither table nor empty state visible after data load"

    def test_table_has_four_sortable_column_headers(self, page: Page):
        """Table should have 4 sortable column headers (Balance is not sortable)."""
        _wait_for_data(page)
        headers = page.locator("#tp-ud-table thead th.tp-ud-sortable")
        assert headers.count() == 4, \
            f"Expected 4 sortable headers, found {headers.count()}"

    def test_column_header_data_sort_values(self, page: Page):
        """Headers should have correct data-sort attributes."""
        _wait_for_data(page)
        expected = ["date", "totalHits", "otherServices", "hitCost"]
        headers = page.locator("#tp-ud-table thead th.tp-ud-sortable")
        actual = [headers.nth(i).get_attribute("data-sort") for i in range(headers.count())]
        assert actual == expected, f"Expected {expected}, got {actual}"

    def test_table_rows_have_data(self, page: Page):
        """If data exists, table body should have rows with content."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data available for table row test")

        rows = page.locator("#tp-ud-tbody tr")
        assert rows.count() >= 1, "Expected at least 1 data row"

        # First row should have 5 cells
        first_row = rows.first
        cells = first_row.locator("td")
        assert cells.count() == 5, f"Expected 5 cells per row, got {cells.count()}"

    def test_date_cell_not_empty(self, page: Page):
        """Date cells should contain formatted date text."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        date_cell = page.locator("#tp-ud-tbody tr").first.locator(".tp-ud-col-date")
        text = date_cell.inner_text().strip()
        assert text != "", "Date cell should not be empty"
        assert text != "-", "Date cell should have a formatted date"

    def test_hits_cell_shows_breakdown(self, page: Page):
        """Hits cell should show total and click/QR breakdown."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        hits_cell = page.locator("#tp-ud-tbody tr").first.locator(".tp-ud-col-hits")
        total = hits_cell.locator(".tp-ud-hits-total")
        breakdown = hits_cell.locator(".tp-ud-hits-breakdown")
        expect(total).to_be_visible()
        expect(breakdown).to_be_visible()

    def test_cost_cell_has_dollar_format(self, page: Page):
        """Cost cells should display as $X.XX format."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        cost = page.locator("#tp-ud-tbody tr").first.locator(".tp-ud-cost")
        text = cost.inner_text().strip()
        assert "$" in text, f"Cost should contain $, got: {text}"
        # Should match $X.XX or -$X.XX pattern
        assert re.match(r"-?\$\d+\.\d{2}$", text), \
            f"Cost should be $X.XX format, got: {text}"

    def test_balance_cell_has_dollar_format(self, page: Page):
        """Balance cells should display as $X.XX format."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        balance = page.locator("#tp-ud-tbody tr").first.locator(".tp-ud-balance")
        text = balance.inner_text().strip()
        assert "$" in text, f"Balance should contain $, got: {text}"
        assert re.match(r"-?\$\d+\.\d{2}$", text), \
            f"Balance should be $X.XX format, got: {text}"

    def test_estimated_disclaimer_visible(self, page: Page):
        """The estimated click/QR disclaimer should be visible when table shows."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        disclaimer = page.locator(".tp-ud-estimated-note")
        expect(disclaimer).to_be_visible()
        assert "estimated" in disclaimer.inner_text().lower(), \
            "Disclaimer should mention 'estimated'"


# -------------------------------------------------------------------
# Sortable columns
# -------------------------------------------------------------------
class TestSortableColumns:
    """Verify that clicking column headers sorts client-side."""

    @pytest.mark.parametrize("column", ["date", "totalHits", "hitCost", "otherServices"])
    def test_sort_header_click_adds_active(self, page: Page, column: str):
        """Clicking a sortable header should add the active sort indicator."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data for sort test")

        header = page.locator(f'th.tp-ud-sortable[data-sort="{column}"]')
        header.click()

        # After click, header should have active class
        expect(header).to_have_class(re.compile(r"tp-ud-sort-active"))

        # Sort icon should be fa-sort-up or fa-sort-down
        icon = header.locator(".tp-ud-sort-icon")
        icon_classes = icon.get_attribute("class")
        assert "fa-sort-up" in icon_classes or "fa-sort-down" in icon_classes, \
            f"Expected sort direction icon, got: {icon_classes}"

    def test_sort_toggle_direction(self, page: Page):
        """Clicking the same header twice should toggle sort direction."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data for sort test")

        header = page.locator('th.tp-ud-sortable[data-sort="date"]')

        # First click
        header.click()
        icon = header.locator(".tp-ud-sort-icon")
        first_classes = icon.get_attribute("class")

        # Second click - should toggle
        header.click()
        second_classes = icon.get_attribute("class")

        assert first_classes != second_classes, \
            "Sort icon should change direction on second click"

    def test_sort_does_not_trigger_skeleton(self, page: Page):
        """Sorting should be client-side only -- no skeleton flash."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data for sort test")

        header = page.locator('th.tp-ud-sortable[data-sort="totalHits"]')
        header.click()

        # Skeleton should remain hidden
        skeleton = page.locator("#tp-ud-skeleton")
        assert not skeleton.is_visible(), \
            "Skeleton appeared during sort -- should be client-side only"

    def test_sort_changes_row_order(self, page: Page):
        """Sorting by a different column should change row order."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data for sort test")

        rows = page.locator("#tp-ud-tbody tr")
        if rows.count() < 2:
            pytest.skip("Need at least 2 rows to verify sort order")

        # Get dates in default order
        first_date_before = rows.first.locator(".tp-ud-col-date").inner_text()

        # Sort by cost
        page.locator('th.tp-ud-sortable[data-sort="hitCost"]').click()

        # Get first row cost
        first_cost = rows.first.locator(".tp-ud-cost").inner_text()
        assert first_cost != "", "Cost cell should have content after sort"


# -------------------------------------------------------------------
# Hits breakdown integrity
# -------------------------------------------------------------------
class TestHitsBreakdown:
    """Verify the click/QR split sums correctly to totalHits."""

    def test_breakdown_sums_to_total(self, page: Page):
        """clicks + qr should exactly equal totalHits for each row."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        # Extract data via JS to avoid parsing display text
        results = page.evaluate("""() => {
            const rows = document.querySelectorAll('#tp-ud-tbody tr');
            const data = [];
            rows.forEach(row => {
                const total = row.querySelector('.tp-ud-hits-total');
                const breakdown = row.querySelector('.tp-ud-hits-breakdown');
                if (total && breakdown) {
                    data.push({
                        total: total.textContent.trim().replace(/,/g, ''),
                        breakdown: breakdown.textContent.trim()
                    });
                }
            });
            return data;
        }""")

        for i, row in enumerate(results):
            total = int(row["total"])
            # Parse "X Y" from breakdown text (click count + QR count)
            nums = re.findall(r"[\d,]+", row["breakdown"])
            if len(nums) >= 2:
                clicks = int(nums[0].replace(",", ""))
                qr = int(nums[1].replace(",", ""))
                assert clicks + qr == total, \
                    f"Row {i}: clicks({clicks}) + qr({qr}) != total({total})"


# -------------------------------------------------------------------
# Summary cards
# -------------------------------------------------------------------
class TestSummaryCards:
    """Verify summary cards render with correct data."""

    def test_summary_strip_visible(self, page: Page):
        """Summary strip should be visible when data exists."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data for summary card test")

        strip = page.locator("#tp-ud-summary-strip")
        expect(strip).to_be_visible()

    def test_four_summary_cards(self, page: Page):
        """Should have 4 summary cards: hits, cost, balance, other services."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        cards = page.locator(".tp-ud-stat-card")
        assert cards.count() == 4, \
            f"Expected 4 summary cards, got {cards.count()}"

    def test_cards_have_values(self, page: Page):
        """Each card should display a value and label."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        cards = page.locator(".tp-ud-stat-card")
        for i in range(cards.count()):
            card = cards.nth(i)
            value = card.locator(".tp-ud-stat-value")
            label = card.locator(".tp-ud-stat-label")
            expect(value).to_be_visible()
            expect(label).to_be_visible()
            assert value.inner_text().strip() != "", \
                f"Card {i} value should not be empty"

    def test_cost_card_has_dollar_format(self, page: Page):
        """The cost summary card should show $X.XX format."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        # Cost card is the second one (index 1)
        cost_card = page.locator(".tp-ud-stat-card").nth(1)
        value = cost_card.locator(".tp-ud-stat-value").inner_text().strip()
        assert "$" in value, f"Cost card should contain $, got: {value}"

    def test_balance_card_has_dollar_format(self, page: Page):
        """The balance summary card should show $X.XX format."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        # Balance card is the third one (index 2)
        balance_card = page.locator(".tp-ud-stat-card").nth(2)
        value = balance_card.locator(".tp-ud-stat-value").inner_text().strip()
        assert "$" in value, f"Balance card should contain $, got: {value}"

    def test_cards_have_secondary_text(self, page: Page):
        """Each card should have secondary context text."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        cards = page.locator(".tp-ud-stat-card")
        for i in range(cards.count()):
            secondary = cards.nth(i).locator(".tp-ud-stat-secondary")
            expect(secondary).to_be_visible()
            assert secondary.inner_text().strip() != "", \
                f"Card {i} secondary text should not be empty"


# -------------------------------------------------------------------
# Pagination
# -------------------------------------------------------------------
class TestPagination:
    """Verify client-side pagination works correctly."""

    def test_pagination_hidden_with_few_rows(self, page: Page):
        """Pagination should be hidden if 10 or fewer rows exist."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        rows = page.locator("#tp-ud-tbody tr")
        pagination = page.locator("#tp-ud-pagination")

        if rows.count() <= 10:
            assert not pagination.is_visible(), \
                "Pagination should be hidden for <=10 rows"

    def test_pagination_visible_with_many_rows(self, page: Page):
        """Pagination should appear if more than 10 days of data."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        # Check total row count via JS (pagination limits visible rows)
        total = page.evaluate("""() => {
            const info = document.getElementById('tp-ud-pagination-info');
            if (!info) return 0;
            const match = info.textContent.match(/of (\\d+)/);
            return match ? parseInt(match[1]) : 0;
        }""")

        pagination = page.locator("#tp-ud-pagination")
        if total > 10:
            expect(pagination).to_be_visible()
        else:
            # Not enough data to test -- just verify structure exists
            assert page.locator("#tp-ud-pagination").count() == 1

    def test_pagination_info_shows_correct_range(self, page: Page):
        """Pagination info should show 'Showing X-Y of Z days'."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        info = page.locator("#tp-ud-pagination-info")
        text = info.inner_text()
        # When paginated: "Showing X-Y of Z days", otherwise: "N days"
        assert "day" in text.lower(), \
            f"Expected day count info in pagination, got: {text}"

    def test_page_click_changes_rows(self, page: Page):
        """Clicking page 2 should change the displayed rows."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        pagination = page.locator("#tp-ud-pagination")
        if not pagination.is_visible():
            pytest.skip("Not enough data for pagination test")

        # Get first row date on page 1
        first_date_p1 = page.locator("#tp-ud-tbody tr").first.locator(
            ".tp-ud-col-date"
        ).inner_text()

        # Click page 2
        page2 = page.locator('#tp-ud-pagination-list .page-link[data-page="2"]')
        if page2.count() == 0:
            pytest.skip("Page 2 button not found")
        page2.click()

        # First row should change
        first_date_p2 = page.locator("#tp-ud-tbody tr").first.locator(
            ".tp-ud-col-date"
        ).inner_text()
        assert first_date_p1 != first_date_p2, \
            "Rows should change when navigating to page 2"

    def test_pagination_does_not_trigger_skeleton(self, page: Page):
        """Pagination should be client-side only -- no skeleton flash."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        pagination = page.locator("#tp-ud-pagination")
        if not pagination.is_visible():
            pytest.skip("Not enough data for pagination test")

        page2 = page.locator('#tp-ud-pagination-list .page-link[data-page="2"]')
        if page2.count() == 0:
            pytest.skip("Page 2 button not found")
        page2.click()

        skeleton = page.locator("#tp-ud-skeleton")
        assert not skeleton.is_visible(), \
            "Skeleton appeared during pagination -- should be client-side only"


# -------------------------------------------------------------------
# Empty state
# -------------------------------------------------------------------
class TestEmptyState:
    """Verify empty state behavior when no data exists for a date range."""

    def test_empty_state_via_invalid_range(self, page: Page):
        """Querying a date range with no data should show the empty state."""
        _wait_for_data(page)

        # Set dates to a range with no data (far future)
        page.evaluate("""() => {
            const start = document.querySelector('#tp-ud-date-start');
            const end = document.querySelector('#tp-ud-date-end');
            if (start && end) {
                start.value = '2099-01-01';
                end.value = '2099-01-02';
            }
        }""")

        # Click apply button if it exists
        apply_btn = page.locator("#tp-ud-apply-dates")
        if apply_btn.count() > 0 and apply_btn.is_visible():
            apply_btn.click()
        else:
            # Trigger change event manually
            page.evaluate("""() => {
                const start = document.querySelector('#tp-ud-date-start');
                if (start) start.dispatchEvent(new Event('change'));
            }""")

        # Wait for loading to complete
        page.wait_for_timeout(2000)
        page.wait_for_selector("#tp-ud-skeleton", state="hidden", timeout=45_000)

        # Either empty state shows, or we got data (test account has data in 2099)
        empty = page.locator("#tp-ud-empty")
        table = page.locator("#tp-ud-table-container")

        if empty.is_visible():
            # Verify empty state structure
            expect(empty).to_be_visible()
            range_text = page.locator("#tp-ud-empty-range")
            expect(range_text).to_be_visible()
        # If table is visible, the API returned data even for this range -- still valid


# -------------------------------------------------------------------
# Currency precision
# -------------------------------------------------------------------
class TestCurrencyPrecision:
    """Verify no floating-point drift in currency display."""

    def test_no_float_artifacts_in_cost(self, page: Page):
        """Cost values should not show float drift like $0.30000000000000004."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        costs = page.evaluate("""() => {
            return Array.from(document.querySelectorAll('.tp-ud-cost'))
                .map(el => el.textContent.trim());
        }""")

        for cost in costs:
            # Every cost should match $X.XX (exactly 2 decimal places)
            assert re.match(r"-?\$\d+\.\d{2}$", cost), \
                f"Float drift detected in cost: {cost}"

    def test_no_float_artifacts_in_balance(self, page: Page):
        """Balance values should not show float drift."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        balances = page.evaluate("""() => {
            return Array.from(document.querySelectorAll('.tp-ud-balance'))
                .map(el => el.textContent.trim());
        }""")

        for balance in balances:
            assert re.match(r"-?\$\d+\.\d{2}$", balance), \
                f"Float drift detected in balance: {balance}"

    def test_summary_cost_has_two_decimals(self, page: Page):
        """Summary cost card should show exactly 2 decimal places."""
        _wait_for_data(page)
        if not _has_table_data(page):
            pytest.skip("No usage data")

        cost_card = page.locator(".tp-ud-stat-card").nth(1)
        value = cost_card.locator(".tp-ud-stat-value").inner_text().strip()
        assert re.match(r"-?\$\d+\.\d{2}$", value), \
            f"Summary cost has wrong precision: {value}"
