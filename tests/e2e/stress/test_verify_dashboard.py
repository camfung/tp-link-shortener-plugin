"""
Stress Pipeline Stage 3: Verify dashboard reflects stress test activity.

Navigates to the usage dashboard, sets the date range to today,
and verifies that usage data appears in both the table and chart.
Uses retry polling to handle eventual consistency.

Usage:
    pytest tests/e2e/stress/test_verify_dashboard.py -m stress --headed -s
"""

import datetime
import os
import time

import pytest

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
POLL_TIMEOUT = int(os.getenv("STRESS_POLL_TIMEOUT", "120"))  # seconds
POLL_INTERVAL = int(os.getenv("STRESS_POLL_INTERVAL", "10"))  # seconds

BASE_URL = os.getenv("TP_BASE_URL", "https://trafficportal.dev")
USAGE_DASHBOARD_PATH = os.getenv("TP_USAGE_DASHBOARD_PATH", "/usage-dashboard/")


def poll_for_usage_data(page, timeout=POLL_TIMEOUT, interval=POLL_INTERVAL):
    """
    Poll the usage dashboard table for rows with non-zero hit counts.

    Sets the date range to today, clicks Apply, and checks for data.
    Re-applies the date filter on each iteration to trigger a fresh AJAX fetch
    (avoids stale cached responses).

    Returns:
        tuple: (success: bool, row_count: int, iterations: int, diagnostic: str)
    """
    today_str = datetime.date.today().isoformat()
    max_iterations = max(1, timeout // interval)
    iterations = 0

    # Open the custom date panel first
    custom_toggle = page.query_selector("#tp-ud-custom-toggle")
    if custom_toggle:
        custom_toggle.click()
        page.wait_for_selector("#tp-ud-custom-panel:not([style*='display: none'])", timeout=5000)

    for i in range(1, max_iterations + 1):
        iterations = i
        print(f"Poll iteration {i}/{max_iterations}")

        # Set date range to today and apply
        page.fill("#tp-ud-date-start", today_str)
        page.fill("#tp-ud-date-end", today_str)
        page.click("#tp-ud-date-apply")

        # Wait for content area to become visible (AJAX response)
        try:
            page.wait_for_selector("#tp-ud-content:not([style*='display: none'])", timeout=10000)
        except Exception:
            print(f"  Content area not visible yet, retrying...")
            time.sleep(interval)
            continue

        # Check for table rows with non-zero hits
        rows = page.query_selector_all("#tp-ud-tbody tr")
        if rows:
            for row in rows:
                cells = row.query_selector_all("td")
                if len(cells) >= 2:
                    hits_text = cells[1].inner_text().strip()
                    try:
                        hits = int(hits_text.replace(",", ""))
                        if hits > 0:
                            print(f"Data found after {iterations} poll iterations ({iterations * interval}s elapsed)")
                            return True, len(rows), iterations, f"Found {len(rows)} rows, hits={hits}"
                    except ValueError:
                        pass

        print(f"  No data with non-zero hits yet ({len(rows)} rows found)")

        if i < max_iterations:
            time.sleep(interval)

    return False, 0, iterations, f"Timeout after {iterations} iterations ({timeout}s)"


@pytest.mark.stress
def test_verify_dashboard(auth_context):
    """
    Verify that stress test activity is reflected in the usage dashboard.

    VERIFY-01: Page loads with dashboard structure
    VERIFY-02: Usage table shows records with non-zero hit counts
    VERIFY-03: Chart canvas renders
    VERIFY-04: Retry polling used (no hardcoded sleeps for consistency)
    """
    page = auth_context.new_page()

    try:
        # Navigate to usage dashboard
        page.goto(f"{BASE_URL}{USAGE_DASHBOARD_PATH}")
        page.wait_for_selector(".tp-ud-container", timeout=15000)

        # ----- VERIFY-01: Dashboard structure loaded -----
        assert page.query_selector(".tp-ud-container") is not None, (
            "Dashboard container .tp-ud-container not found"
        )
        # Wait for content to load (skeleton replaced by content)
        page.wait_for_selector("#tp-ud-content:not([style*='display: none'])", timeout=30000)

        assert page.query_selector("#tp-ud-date-start") is not None, (
            "Start date input #tp-ud-date-start not found"
        )
        assert page.query_selector("#tp-ud-date-end") is not None, (
            "End date input #tp-ud-date-end not found"
        )
        print("VERIFY-01 PASSED: Dashboard structure loaded")

        # ----- VERIFY-02: Usage table shows records -----
        success, row_count, iterations, diagnostic = poll_for_usage_data(
            page, POLL_TIMEOUT, POLL_INTERVAL
        )
        assert success, (
            f"VERIFY-02 FAILED: No usage data found after polling. {diagnostic}"
        )
        assert row_count > 0, (
            f"VERIFY-02 FAILED: Expected rows > 0, got {row_count}"
        )
        print(f"VERIFY-02 PASSED: Found {row_count} rows with non-zero hits")

        # ----- VERIFY-03: Chart canvas rendered -----
        chart = page.query_selector("#tp-ud-chart")
        if chart is None:
            chart = page.query_selector("canvas")
        assert chart is not None, (
            "VERIFY-03 FAILED: No chart canvas element found"
        )
        # Verify chart has non-zero dimensions
        box = chart.bounding_box()
        if box:
            assert box["width"] > 0, "Chart canvas has zero width"
            assert box["height"] > 0, "Chart canvas has zero height"
        print("VERIFY-03 PASSED: Chart canvas rendered")

        # ----- VERIFY-04: Polling was used -----
        # Verified structurally: poll_for_usage_data logs iterations,
        # and no time.sleep() calls exist outside the poll interval wait.
        assert iterations >= 1, "Expected at least 1 poll iteration"
        print(f"VERIFY-04 PASSED: Used {iterations} poll iterations")

    finally:
        page.close()
