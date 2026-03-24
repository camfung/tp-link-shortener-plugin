"""
Stress test: Create 50 short links via the Client Links Add Link modal.

Stage 1 of the stress pipeline -- populates the dev environment with links
that the usage generator and dashboard verifier will consume.

Run:
    pytest tests/e2e/stress/test_create_links.py -m stress --headed -s
"""

import json
import os

import pytest
from playwright.sync_api import BrowserContext, Page

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
LINK_COUNT = int(os.getenv("STRESS_LINK_COUNT", "50"))
DESTINATION_URL = "https://example.com"

# Reuse base URL / path from environment (same source as conftest.py)
BASE_URL = os.getenv("TP_BASE_URL", "https://trafficportal.dev")
CLIENT_LINKS_PATH = os.getenv("TP_CLIENT_LINKS_PATH", "/camerons-test-page/")


def create_single_link(page: Page, keyword: str, destination_url: str) -> dict:
    """
    Create one short link through the Add Link modal UI.

    Returns dict with keyword, url, and mid.
    """
    # Open the Add Link modal
    page.click("#tp-cl-add-link-btn")
    page.wait_for_selector(
        "#tp-cl-edit-modal-overlay",
        state="visible",
        timeout=10_000,
    )

    # Fill destination URL
    page.fill("#tp-destination", destination_url)

    # Wait for async URL validation to complete -- the custom key group
    # becomes visible only after the debounced validation AJAX resolves
    # (Pitfall 1: custom key field not visible until validation completes)
    page.wait_for_selector(
        "#tp-custom-key-group",
        state="visible",
        timeout=15_000,
    )

    # Clear and fill the custom keyword
    page.fill("#tp-custom-key", "")
    page.fill("#tp-custom-key", keyword)

    # Set up response interception BEFORE clicking submit
    # (Pitfall 2: must enter expect_response context before the click)
    # (Pitfall 3: filter by tp_create_link to avoid capturing validation AJAX)
    with page.expect_response(
        lambda r: (
            "admin-ajax.php" in r.url
            and r.request.post_data is not None
            and "tp_create_link" in r.request.post_data
        ),
        timeout=30_000,
    ) as response_info:
        page.click("#tp-submit-btn")

    # Parse the response to extract the link MID
    response = response_info.value
    response_data = response.json()
    mid = response_data.get("data", {}).get("mid", "")

    # Wait for modal to close (success dismisses the overlay)
    page.wait_for_selector(
        "#tp-cl-edit-modal-overlay",
        state="hidden",
        timeout=10_000,
    )

    # Small pause to let animations and form reset complete
    # (Pitfall 6: form state not reset without switchToCreateMode trigger)
    page.wait_for_timeout(500)

    return {"keyword": keyword, "url": destination_url, "mid": mid}


@pytest.mark.stress
def test_create_stress_links(
    auth_context: BrowserContext,
    run_id: str,
    stress_data_file,
):
    """Create LINK_COUNT short links and write their data to a JSON file."""
    page = auth_context.new_page()

    try:
        # Navigate to Client Links page
        page.goto(f"{BASE_URL}{CLIENT_LINKS_PATH}")
        page.wait_for_selector(".tp-cl-container", timeout=15_000)

        created_links: list[dict] = []

        for i in range(LINK_COUNT):
            keyword = f"{run_id}-{i:03d}"
            link_data = create_single_link(page, keyword, DESTINATION_URL)
            created_links.append(link_data)
            print(f"Created {i + 1}/{LINK_COUNT}: {keyword}")

        # Persist all link data for downstream pipeline stages
        with open(stress_data_file, "w") as f:
            json.dump(created_links, f, indent=2)

        assert len(created_links) == LINK_COUNT, (
            f"Expected {LINK_COUNT} links, got {len(created_links)}"
        )
    finally:
        page.close()
