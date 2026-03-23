"""
Stress test fixtures for e2e stress testing.

Provides session-scoped fixtures for:
- RUN_ID generation and isolation
- Stress data file management
- Rate limiting configuration

Data files are stored in tests/e2e/data/ (gitignored).
"""

import json
import os
import uuid
from pathlib import Path

import pytest

DATA_DIR = Path(__file__).parent.parent / "data"


def pytest_configure(config):
    """Ensure the data directory exists before tests run."""
    DATA_DIR.mkdir(exist_ok=True)


@pytest.fixture(scope="session")
def run_id():
    """
    Unique identifier for this stress test run.

    Override via STRESS_RUN_ID env var for reproducibility,
    otherwise generates a random ID.
    """
    return os.getenv("STRESS_RUN_ID", f"stress-{uuid.uuid4().hex[:8]}")


@pytest.fixture(scope="session")
def stress_data_file(run_id):
    """
    Path to the JSON data file for this stress run.

    The file stores created link MIDs for cleanup.
    """
    return DATA_DIR / f"stress_data_{run_id}.json"


@pytest.fixture(scope="session")
def stress_links(stress_data_file):
    """
    Load previously created stress links from the data file.

    Skips the test gracefully if the data file does not exist
    (e.g., when running verification tests before creation).
    """
    if not stress_data_file.exists():
        pytest.skip(f"Stress data file not found: {stress_data_file}")

    with open(stress_data_file, "r") as f:
        data = json.load(f)

    return data


@pytest.fixture(scope="session")
def stress_rate_limit():
    """
    Rate limit delay (seconds) between API calls during stress tests.

    Override via STRESS_RATE_LIMIT env var. Default is 1.5s (conservative).
    """
    return float(os.getenv("STRESS_RATE_LIMIT", "1.5"))
