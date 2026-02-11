# E2E Tests — Playwright (Python)

Headless browser tests for the **Client Links** UI (`[tp_client_links]` shortcode) using Playwright and pytest.

## Prerequisites

- Python 3.10+
- A running WordPress site with the **TP Link Shortener** plugin active
- A WordPress user account that has links to manage

## Setup

### 1. Create a virtual environment

```bash
cd tests/e2e
python3 -m venv .venv
source .venv/bin/activate
```

### 2. Install dependencies

```bash
pip install pytest pytest-playwright
playwright install chromium
```

### 3. Create a `.env` file

Create `tests/e2e/.env` with your test credentials:

```env
TP_TEST_USER=your-email@example.com
TP_TEST_PASS=your-password
```

The `.env` file is gitignored. **Never commit credentials.**

## Configuration

All settings can be configured via the `.env` file or environment variables. Values in `.env` are loaded automatically by `conftest.py`.

| Variable | Default | Description |
|---|---|---|
| `TP_BASE_URL` | `https://trafficportal.dev` | WordPress site base URL |
| `TP_LOGIN_URL` | `{TP_BASE_URL}/login/` | Login page (UsersWP custom form) |
| `TP_CLIENT_LINKS_PATH` | `/camerons-test-page/` | Path to the page with `[tp_client_links]` |
| `TP_TEST_USER` | *(empty)* | Test user email or username |
| `TP_TEST_PASS` | *(empty)* | Test user password |

## Running tests

Always activate the venv first:

```bash
source tests/e2e/.venv/bin/activate
```

Then run from the project root:

```bash
# All tests, headless (default)
pytest tests/e2e/ -v

# With visible browser window
pytest tests/e2e/ -v --headed

# Single test class
pytest tests/e2e/test_client_links.py::TestSortableColumns -v

# Single test
pytest tests/e2e/test_client_links.py::TestStatusToggle::test_toggle_disable_confirm -v

# With screenshots on failure
pytest tests/e2e/ -v --screenshot=on

# Stop on first failure
pytest tests/e2e/ -v -x
```

## Test coverage

The test suite covers 25 tests across 8 areas:

| Area | Tests | What it verifies |
|---|---|---|
| **Page load** | 5 | Container, title, count badge, chart, date range picker |
| **Table rendering** | 4 | Table/empty state, domain groups, keyword display, toggle presence |
| **Sortable columns** | 4 | Click-to-sort on tpKey, destination, clicks, created_at |
| **Inline actions** | 3 | Hover reveals actions, QR dialog opens/closes, history modal opens/closes |
| **Status toggle** | 1 | Disable confirmation dialog, dismissed keeps toggle checked |
| **Search & filter** | 3 | Search triggers reload, clear button, status filter options |
| **Add link modal** | 3 | Opens on button click, closes on X, closes on overlay click |
| **Edit modal** | 1 | Row click opens modal in edit mode (requires `[tp_link_shortener]` on page) |
| **Pagination** | 1 | Pagination info text present |

## Notes

- **Authentication** is session-scoped — the test suite logs in once and reuses cookies across all tests.
- The site uses a **UsersWP** custom login form at `/login/`, not the standard WordPress `wp-login.php`.
- The **edit modal** test requires both `[tp_client_links]` and `[tp_link_shortener]` shortcodes on the same page. It will skip if the form shortcode is missing.
- The status toggle uses a **hidden checkbox with a CSS slider overlay**, so tests click the `<label>` wrapper rather than the input directly.
