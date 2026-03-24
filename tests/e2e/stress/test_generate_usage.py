"""
Stress test: Generate usage traffic by hitting short link redirect endpoints.

Stage 2 of the stress pipeline -- reads link data from stress_data_{run_id}.json
(created by plan 15-01) and sends HTTP GET requests to each short link URL to
generate measurable usage records in the dev environment.

Usage:
    pytest tests/e2e/stress/test_generate_usage.py -m stress -s
"""

import asyncio
import os

import httpx
import pytest

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

SHORT_DOMAIN = os.getenv("TP_SHORT_DOMAIN", "dev.trfc.link")
HITS_PER_LINK = int(os.getenv("STRESS_HITS_PER_LINK", "5"))
MAX_CONCURRENCY = int(os.getenv("STRESS_MAX_CONCURRENCY", "5"))
MAX_RETRIES = 3
USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/120.0.0.0 Safari/537.36"
)


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


async def hit_link(
    client: httpx.AsyncClient,
    url: str,
    semaphore: asyncio.Semaphore,
    rate_delay: float,
    stats: dict,
) -> None:
    """Send a single GET request to *url* to register a usage hit.

    Uses *semaphore* for concurrency control and sleeps *rate_delay* seconds
    after each request to stay under rate limits.  Retries with exponential
    backoff on 429 responses.
    """
    async with semaphore:
        for attempt in range(MAX_RETRIES + 1):
            try:
                response = await client.get(
                    url,
                    headers={"User-Agent": USER_AGENT},
                    follow_redirects=False,
                )

                if response.status_code == 429:
                    if attempt < MAX_RETRIES:
                        backoff = 2**attempt
                        print(f"  429 on {url} -- backing off {backoff}s (attempt {attempt + 1})")
                        stats["retried"] += 1
                        await asyncio.sleep(backoff)
                        continue
                    else:
                        print(f"  429 on {url} -- max retries exceeded")
                        stats["failed"] += 1
                        break

                if 200 <= response.status_code < 400:
                    stats["success"] += 1
                else:
                    print(f"  Unexpected {response.status_code} on {url}")
                    stats["failed"] += 1
                break

            except httpx.HTTPError as exc:
                print(f"  HTTP error on {url}: {exc}")
                stats["failed"] += 1
                break

        await asyncio.sleep(rate_delay)


# ---------------------------------------------------------------------------
# Test
# ---------------------------------------------------------------------------


@pytest.mark.stress
@pytest.mark.asyncio
async def test_generate_usage(stress_links, stress_rate_limit):
    """Hit every short link multiple times to generate usage records."""

    urls = [f"https://{SHORT_DOMAIN}/{link['keyword']}" for link in stress_links]

    semaphore = asyncio.Semaphore(MAX_CONCURRENCY)
    stats = {
        "success": 0,
        "failed": 0,
        "retried": 0,
        "total": len(stress_links) * HITS_PER_LINK,
    }

    async with httpx.AsyncClient(timeout=30.0, verify=False) as client:
        for round_num in range(HITS_PER_LINK):
            print(f"Round {round_num + 1}/{HITS_PER_LINK}: {len(urls)} requests")
            tasks = [
                hit_link(client, url, semaphore, stress_rate_limit, stats)
                for url in urls
            ]
            await asyncio.gather(*tasks)

    # Summary
    print(
        f"\nUsage generation complete: "
        f"{stats['success']} success, "
        f"{stats['failed']} failed, "
        f"{stats['retried']} retried "
        f"(of {stats['total']} total)"
    )

    assert stats["success"] > 0, (
        f"No successful hits! stats={stats}"
    )

    if stats["failed"] > 0:
        print(
            f"WARNING: {stats['failed']} requests failed -- "
            f"usage may be partially recorded"
        )
