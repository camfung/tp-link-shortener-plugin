"""
Playwright e2e tests for mobile responsive behavior.

Covers the 4 phases of the mobile responsive update:
  Phase 1: CSS Foundation (breakpoints, !important removal, hover-to-touch)
  Phase 2: Forms and Modals (full-screen bottom sheets, dvh, slide-up)
  Phase 3: Table Cards and Controls (44px targets, toggle size, date stacking)
  Phase 4: Chart Collapse (hidden chart, stats bar, expand toggle)

Run:
    pytest tests/e2e/test_mobile_responsive.py -v
"""

import pytest
from playwright.sync_api import Page, Browser, BrowserContext, expect

import os
from pathlib import Path

# Load .env
_env_path = Path(__file__).parent / ".env"
if _env_path.exists():
    for line in _env_path.read_text().splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            key, _, value = line.partition("=")
            os.environ.setdefault(key.strip(), value.strip())

BASE_URL = os.getenv("TP_BASE_URL", "https://trafficportal.dev")
LOGIN_URL = os.getenv("TP_LOGIN_URL", f"{BASE_URL}/login/")
CLIENT_LINKS_PATH = os.getenv("TP_CLIENT_LINKS_PATH", "/camerons-test-page/")
TEST_USER = os.getenv("TP_TEST_USER", "")
TEST_PASS = os.getenv("TP_TEST_PASS", "")

MOBILE_VIEWPORT = {"width": 375, "height": 812}  # iPhone-sized
DESKTOP_VIEWPORT = {"width": 1280, "height": 900}


# -------------------------------------------------------------------
# Fixtures
# -------------------------------------------------------------------
@pytest.fixture(scope="session")
def mobile_auth_context(browser: Browser):
    """Authenticated browser context with mobile viewport."""
    context = browser.new_context(
        viewport=MOBILE_VIEWPORT,
        ignore_https_errors=True,
        has_touch=True,
        is_mobile=True,
        user_agent=(
            "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) "
            "AppleWebKit/605.1.15 (KHTML, like Gecko) "
            "Version/17.0 Mobile/15E148 Safari/604.1"
        ),
    )
    page = context.new_page()
    page.goto(LOGIN_URL)
    page.get_by_label("Username or Email").fill(TEST_USER)
    page.get_by_label("Password").fill(TEST_PASS)
    page.get_by_role("button", name="Login").click()
    page.wait_for_url(f"{BASE_URL}/**", timeout=15_000)
    page.close()
    yield context
    context.close()


@pytest.fixture()
def mobile_page(mobile_auth_context: BrowserContext):
    """Fresh mobile page tab, already authenticated."""
    pg = mobile_auth_context.new_page()
    yield pg
    pg.close()


@pytest.fixture()
def mobile_client_links(mobile_page: Page):
    """Navigate to Client Links page on a mobile viewport."""
    mobile_page.goto(f"{BASE_URL}{CLIENT_LINKS_PATH}")
    mobile_page.wait_for_selector(".tp-cl-container", timeout=10_000)
    mobile_page.wait_for_selector("#tp-cl-loading", state="hidden", timeout=15_000)
    return mobile_page


def _computed(page: Page, selector: str, prop: str) -> str:
    """Get a computed CSS property value for the first matching element."""
    return page.evaluate(
        """([sel, prop]) => {
            const el = document.querySelector(sel);
            if (!el) return '';
            return window.getComputedStyle(el).getPropertyValue(prop);
        }""",
        [selector, prop],
    )


def _bounding_box(page: Page, selector: str):
    """Get the bounding box of the first matching element."""
    loc = page.locator(selector).first
    return loc.bounding_box()


# ===================================================================
# Phase 1: CSS Foundation
# ===================================================================
class TestCSSFoundation:
    """Verify breakpoints, no !important conflicts, and hover-to-touch."""

    def test_inline_actions_visible_without_hover(self, mobile_client_links: Page):
        """On touch devices, inline actions should be visible without hover."""
        page = mobile_client_links
        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links to test")

        actions = page.locator(".tp-cl-inline-actions").first
        expect(actions).to_be_visible()

        opacity = _computed(page, ".tp-cl-inline-actions", "opacity")
        assert opacity == "1", f"Inline actions opacity is {opacity}, expected 1"

    def test_header_stacks_vertically(self, mobile_client_links: Page):
        """On mobile, header should stack (column direction)."""
        page = mobile_client_links
        direction = _computed(page, ".tp-cl-header", "flex-direction")
        assert direction == "column", f"Header flex-direction is {direction}"

    def test_controls_stack_vertically(self, mobile_client_links: Page):
        """On mobile, controls should stack (column direction)."""
        page = mobile_client_links
        direction = _computed(page, ".tp-cl-controls", "flex-direction")
        assert direction == "column", f"Controls flex-direction is {direction}"


# ===================================================================
# Phase 2: Forms and Modals
# ===================================================================
class TestModals:
    """Verify modals are full-screen bottom sheets on mobile."""

    def test_edit_modal_full_screen(self, mobile_client_links: Page):
        """Edit modal should occupy full screen on mobile."""
        page = mobile_client_links

        page.locator("#tp-cl-add-link-btn").click()
        overlay = page.locator("#tp-cl-edit-modal-overlay")
        expect(overlay).to_be_visible()

        modal = page.locator("#tp-cl-edit-modal-overlay .tp-cl-modal")
        box = modal.bounding_box()

        assert box is not None, "Modal bounding box not found"
        # Should be full viewport width
        assert box["width"] >= MOBILE_VIEWPORT["width"] - 2, \
            f"Modal width {box['width']} is not full screen ({MOBILE_VIEWPORT['width']})"
        # Should be full viewport height (or close, accounting for dvh)
        assert box["height"] >= MOBILE_VIEWPORT["height"] * 0.9, \
            f"Modal height {box['height']} is not full screen ({MOBILE_VIEWPORT['height']})"

        # JS click to bypass WP admin bar and viewport issues on full-screen modal
        page.evaluate('document.getElementById("tp-cl-edit-modal-close").click()')
        expect(overlay).to_be_hidden()

    def test_edit_modal_no_border_radius(self, mobile_client_links: Page):
        """Full-screen edit modal should have no border radius."""
        page = mobile_client_links

        page.locator("#tp-cl-add-link-btn").click()
        expect(page.locator("#tp-cl-edit-modal-overlay")).to_be_visible()

        radius = _computed(page, "#tp-cl-edit-modal-overlay .tp-cl-modal", "border-radius")
        assert radius == "0px", f"Full-screen modal border-radius is {radius}, expected 0px"

        # JS click to bypass WP admin bar and viewport issues on full-screen modal
        page.evaluate('document.getElementById("tp-cl-edit-modal-close").click()')

    def test_qr_dialog_bottom_sheet(self, mobile_client_links: Page):
        """QR dialog should appear as a bottom sheet with rounded top corners."""
        page = mobile_client_links

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links to test QR dialog")

        row = page.locator("tr[data-mid]").first
        qr_btn = row.locator(".tp-cl-qr-btn").first
        qr_btn.click()

        dialog_overlay = page.locator("#tp-cl-qr-dialog-overlay")
        expect(dialog_overlay).to_be_visible()

        dialog = page.locator(".tp-cl-qr-dialog")
        box = dialog.bounding_box()

        assert box is not None
        # Should be full width
        assert box["width"] >= MOBILE_VIEWPORT["width"] - 2, \
            f"QR dialog width {box['width']} not full screen"
        # Should NOT be full height (partial bottom sheet)
        assert box["height"] < MOBILE_VIEWPORT["height"] * 0.9, \
            f"QR dialog height {box['height']} should be partial, not full screen"

        page.locator("#tp-cl-qr-dialog-close").click()
        expect(dialog_overlay).to_be_hidden()

    def test_history_modal_bottom_sheet(self, mobile_client_links: Page):
        """History modal should appear as a partial bottom sheet."""
        page = mobile_client_links

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links to test history")

        row = page.locator("tr[data-mid]").first
        history_btn = row.locator(".tp-cl-history-btn").first
        history_btn.click()

        modal_overlay = page.locator("#tp-cl-history-modal-overlay")
        expect(modal_overlay).to_be_visible()

        modal = page.locator("#tp-cl-history-modal-overlay .tp-cl-modal")
        box = modal.bounding_box()

        assert box is not None
        # Should be full width
        assert box["width"] >= MOBILE_VIEWPORT["width"] - 2
        # Partial height (bottom sheet, not full screen)
        assert box["height"] < MOBILE_VIEWPORT["height"] * 0.9

        page.locator("#tp-cl-history-modal-close").click()
        expect(modal_overlay).to_be_hidden()

    def test_overlay_aligns_to_bottom(self, mobile_client_links: Page):
        """Modal overlay should use flex-end alignment (bottom sheet)."""
        page = mobile_client_links

        page.locator("#tp-cl-add-link-btn").click()
        expect(page.locator("#tp-cl-edit-modal-overlay")).to_be_visible()

        align = _computed(page, "#tp-cl-edit-modal-overlay", "align-items")
        assert align == "flex-end", f"Overlay align-items is {align}, expected flex-end"

        page.evaluate('document.getElementById("tp-cl-edit-modal-close").click()')


# ===================================================================
# Phase 3: Table Cards and Controls
# ===================================================================
class TestTableCardsAndControls:
    """Verify card layout, touch targets, and control sizing."""

    def test_table_header_hidden(self, mobile_client_links: Page):
        """Table thead should be hidden on mobile (card layout)."""
        page = mobile_client_links
        thead_display = _computed(page, ".tp-cl-table thead", "display")
        assert thead_display == "none", \
            f"Table thead display is {thead_display}, expected none"

    def test_rows_display_as_cards(self, mobile_client_links: Page):
        """Table rows should display as block (card) on mobile."""
        page = mobile_client_links

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links to test")

        row_display = _computed(page, "tr[data-mid]", "display")
        assert row_display == "block", \
            f"Table row display is {row_display}, expected block"

    def test_inline_buttons_44px_touch_target(self, mobile_client_links: Page):
        """Inline action buttons should have at least 44px touch targets."""
        page = mobile_client_links

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links to test")

        btn = page.locator(".tp-cl-inline-btn").first
        box = btn.bounding_box()

        assert box is not None, "Inline button bounding box not found"
        assert box["width"] >= 43, \
            f"Inline button width {box['width']}px is below 44px touch target"
        assert box["height"] >= 43, \
            f"Inline button height {box['height']}px is below 44px touch target"

    def test_pagination_links_44px(self, mobile_client_links: Page):
        """Pagination links should have at least 44px touch targets."""
        page = mobile_client_links

        page_link = page.locator(".tp-cl-pagination .page-link").first
        if not page_link.is_visible():
            pytest.skip("No pagination links visible")

        box = page_link.bounding_box()
        assert box is not None
        assert box["width"] >= 43, \
            f"Page link width {box['width']}px below 44px"
        assert box["height"] >= 43, \
            f"Page link height {box['height']}px below 44px"

    def test_toggle_switch_enlarged(self, mobile_client_links: Page):
        """Toggle switch should be enlarged for touch on mobile (48px wide)."""
        page = mobile_client_links

        if not page.locator(".tp-cl-toggle").first.is_visible():
            pytest.skip("No toggle switches visible")

        box = _bounding_box(page, ".tp-cl-toggle")
        assert box is not None
        assert box["width"] >= 47, \
            f"Toggle width {box['width']}px, expected ~48px"

    def test_pagination_stacks_vertically(self, mobile_client_links: Page):
        """Pagination should stack vertically on mobile."""
        page = mobile_client_links
        direction = _computed(page, ".tp-cl-pagination", "flex-direction")
        assert direction == "column", \
            f"Pagination flex-direction is {direction}, expected column"


# ===================================================================
# Phase 4: Chart Collapse
# ===================================================================
class TestChartCollapse:
    """Verify chart is hidden by default with stats bar and expand toggle."""

    def test_chart_hidden_by_default(self, mobile_client_links: Page):
        """Chart wrapper should be hidden on mobile by default."""
        page = mobile_client_links
        chart_display = _computed(page, "#tp-cl-chart-wrapper", "display")
        assert chart_display == "none", \
            f"Chart display is {chart_display}, expected none"

    def test_stats_bar_visible(self, mobile_client_links: Page):
        """Mobile stats bar should be visible when links exist."""
        page = mobile_client_links

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links — stats bar won't show")

        stats_bar = page.locator("#tp-cl-chart-mobile")
        expect(stats_bar).to_be_visible()

    def test_stats_bar_shows_totals(self, mobile_client_links: Page):
        """Stats bar should show total clicks and QR scans."""
        page = mobile_client_links

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links for stats")

        clicks = page.locator("#tp-cl-stat-clicks")
        qr = page.locator("#tp-cl-stat-qr")
        expect(clicks).to_be_visible()
        expect(qr).to_be_visible()

        # Values should be numeric (0 or more)
        clicks_text = clicks.inner_text()
        qr_text = qr.inner_text()
        assert clicks_text.isdigit(), f"Clicks stat '{clicks_text}' is not numeric"
        assert qr_text.isdigit(), f"QR stat '{qr_text}' is not numeric"

    def test_toggle_button_present(self, mobile_client_links: Page):
        """Chart toggle button should be present on mobile."""
        page = mobile_client_links

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links for chart toggle")

        toggle = page.locator("#tp-cl-chart-toggle")
        expect(toggle).to_be_visible()
        expect(toggle).to_contain_text("Show Chart")

    def test_toggle_expands_chart(self, mobile_client_links: Page):
        """Clicking toggle should expand the chart."""
        page = mobile_client_links

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links for chart toggle")

        toggle = page.locator("#tp-cl-chart-toggle")
        toggle.click()

        # Chart should now be visible
        chart = page.locator("#tp-cl-chart-wrapper")
        expect(chart).to_be_visible()

        # Toggle text should change
        toggle_text = page.locator("#tp-cl-chart-toggle-text")
        expect(toggle_text).to_have_text("Hide Chart")

    def test_toggle_collapses_chart(self, mobile_client_links: Page):
        """Clicking toggle again should collapse the chart."""
        page = mobile_client_links

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links for chart toggle")

        toggle = page.locator("#tp-cl-chart-toggle")

        # Expand
        toggle.click()
        expect(page.locator("#tp-cl-chart-wrapper")).to_be_visible()

        # Collapse
        toggle.click()
        chart_display = _computed(page, "#tp-cl-chart-wrapper", "display")
        assert chart_display == "none", \
            f"Chart display after collapse is {chart_display}, expected none"

        toggle_text = page.locator("#tp-cl-chart-toggle-text")
        expect(toggle_text).to_have_text("Show Chart")

    def test_layout_stable_after_toggle_cycle(self, mobile_client_links: Page):
        """After expand→collapse, layout should remain stable (no content jump)."""
        page = mobile_client_links

        if not page.locator("#tp-cl-table-wrapper").is_visible():
            pytest.skip("No links for chart toggle")

        # Get stats bar position before toggle
        stats_before = _bounding_box(page, "#tp-cl-chart-mobile")

        toggle = page.locator("#tp-cl-chart-toggle")
        toggle.click()  # expand
        page.wait_for_timeout(200)
        toggle.click()  # collapse
        page.wait_for_timeout(200)

        # Stats bar position should be the same
        stats_after = _bounding_box(page, "#tp-cl-chart-mobile")

        assert stats_before is not None and stats_after is not None
        assert abs(stats_before["y"] - stats_after["y"]) < 5, \
            f"Stats bar shifted from y={stats_before['y']} to y={stats_after['y']}"
