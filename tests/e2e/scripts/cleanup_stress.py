#!/usr/bin/env python3
"""
Cleanup stress test data by disabling or deleting created links via API.

Usage:
    python cleanup_stress.py <RUN_ID>       # Clean up a specific stress run
    python cleanup_stress.py --all-stress   # Clean up ALL stress runs

This script reads stress data JSON files from tests/e2e/data/ and attempts
to delete each link via the API. If DELETE fails, it falls back to disabling
the link via PUT.

Environment variables (loaded from tests/e2e/.env):
    TP_API_ENDPOINT  - API base URL (required)
    API_KEY          - API authentication key (required)
"""

import argparse
import json
import os
import sys
from glob import glob
from pathlib import Path

import httpx

SCRIPT_DIR = Path(__file__).parent
E2E_DIR = SCRIPT_DIR.parent
DATA_DIR = E2E_DIR / "data"


def load_env():
    """Load environment variables from tests/e2e/.env file."""
    env_path = E2E_DIR / ".env"
    if env_path.exists():
        for line in env_path.read_text().splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                key, _, value = line.partition("=")
                os.environ.setdefault(key.strip(), value.strip())

    api_endpoint = os.getenv("TP_API_ENDPOINT")
    api_key = os.getenv("API_KEY")

    if not api_endpoint or not api_key:
        print("ERROR: TP_API_ENDPOINT and API_KEY must be set (via .env or environment)")
        sys.exit(1)

    return api_endpoint, api_key


def delete_links_for_run(run_id, api_endpoint, api_key):
    """
    Delete or disable all links from a specific stress run.

    Returns (processed_count, file_removed).
    """
    data_file = DATA_DIR / f"stress_data_{run_id}.json"

    if not data_file.exists():
        print(f"  No data file found: {data_file}")
        return 0, False

    with open(data_file, "r") as f:
        links = json.load(f)

    if not isinstance(links, list):
        links = [links]

    processed = 0
    headers = {"x-api-key": api_key}

    with httpx.Client(timeout=30.0) as client:
        for link in links:
            mid = link.get("mid") or link.get("id")
            if not mid:
                print(f"  SKIP: No MID found in link data: {link}")
                continue

            # Try DELETE first
            try:
                resp = client.delete(
                    f"{api_endpoint}/items/{mid}",
                    headers=headers,
                )
                if resp.status_code < 400:
                    print(f"  DELETE {mid}: {resp.status_code} OK")
                    processed += 1
                    continue
            except httpx.HTTPError as e:
                print(f"  DELETE {mid}: request failed ({e})")

            # Fall back to PUT (disable)
            try:
                resp = client.put(
                    f"{api_endpoint}/items/{mid}",
                    headers=headers,
                    json={"status": "disabled"},
                )
                if resp.status_code < 400:
                    print(f"  DISABLE {mid}: {resp.status_code} OK")
                else:
                    print(f"  DISABLE {mid}: {resp.status_code} FAILED")
                processed += 1
            except httpx.HTTPError as e:
                print(f"  DISABLE {mid}: request failed ({e})")
                processed += 1

    # Remove the data file after processing
    data_file.unlink()
    print(f"  Removed: {data_file.name}")
    return processed, True


def delete_all_stress(api_endpoint, api_key):
    """Delete or disable links from ALL stress runs."""
    pattern = str(DATA_DIR / "stress_data_stress-*.json")
    files = glob(pattern)

    if not files:
        print("No stress data files found.")
        return 0, 0

    total_links = 0
    total_files = 0

    for filepath in sorted(files):
        filename = Path(filepath).name
        # Extract run_id from filename: stress_data_{run_id}.json
        run_id = filename.replace("stress_data_", "").replace(".json", "")
        print(f"\nProcessing run: {run_id}")
        links, removed = delete_links_for_run(run_id, api_endpoint, api_key)
        total_links += links
        if removed:
            total_files += 1

    return total_links, total_files


def main():
    parser = argparse.ArgumentParser(
        description="Clean up stress test links via API",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
    %(prog)s stress-a1b2c3d4           # Clean up a specific run
    %(prog)s --all-stress               # Clean up all stress runs
        """,
    )

    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument(
        "run_id",
        nargs="?",
        help="Stress run ID to clean up (e.g., stress-a1b2c3d4)",
    )
    group.add_argument(
        "--all-stress",
        action="store_true",
        help="Clean up ALL stress test runs",
    )

    args = parser.parse_args()

    load_env()
    api_endpoint, api_key = os.getenv("TP_API_ENDPOINT"), os.getenv("API_KEY")

    if args.all_stress:
        print("Cleaning up ALL stress test data...")
        total_links, total_files = delete_all_stress(api_endpoint, api_key)
        print(f"\nSummary: {total_links} links processed, {total_files} files removed")
    else:
        print(f"Cleaning up stress run: {args.run_id}")
        total_links, file_removed = delete_links_for_run(
            args.run_id, api_endpoint, api_key
        )
        files_removed = 1 if file_removed else 0
        print(f"\nSummary: {total_links} links processed, {files_removed} files removed")


if __name__ == "__main__":
    main()
