# E2E Tests — Playwright (Python)

Headless browser tests for the Client Links UI using Playwright.

## Setup

```bash
pip install pytest pytest-playwright
playwright install chromium
```

## Configuration

Set environment variables to point at your test environment:

| Variable | Default | Description |
|---|---|---|
| `TP_BASE_URL` | `https://trafficportal.dev` | WordPress site base URL |
| `TP_LOGIN_URL` | `{BASE_URL}/wp-login.php` | WordPress login page |
| `TP_CLIENT_LINKS_PATH` | `/client-links/` | Path to the page with `[tp_client_links]` |
| `TP_TEST_USER` | `TestUser@gmail.com` | WordPress test user email |
| `TP_TEST_PASS` | `Test123456!?` | WordPress test user password |

## Running

```bash
# Headless (default)
pytest tests/e2e/ -v

# With visible browser
pytest tests/e2e/ --headed

# Single test
pytest tests/e2e/test_client_links.py::TestSortableColumns -v

# With screenshots on failure
pytest tests/e2e/ --screenshot=on
```

## Test coverage

- Page load and structure
- Table rendering with domain groups
- Sortable column headers (tpKey, destination, clicks, created_at)
- Inline actions (copy, QR, history) on hover
- Status toggle (enable/disable)
- Search and filter controls
- Add link modal
- Edit modal via row click
- Pagination
