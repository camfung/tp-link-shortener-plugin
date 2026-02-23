# Architecture Research

**Domain:** WordPress plugin shortcode — billing/usage dashboard (`[tp_usage_dashboard]`)
**Researched:** 2026-02-22
**Confidence:** HIGH — based on direct codebase inspection of the three existing shortcodes and their patterns

---

## Standard Architecture

### System Overview

```
Browser (WordPress Page)
    |
    | [1] Page load: shortcode renders HTML, enqueues JS/CSS, injects ajaxUrl + nonce via wp_localize_script
    v
templates/usage-dashboard-template.php
    |
    | [2] On DOMContentLoaded: JS reads date range, fires AJAX
    v
assets/js/usage-dashboard.js  (jQuery IIFE, global tpUsageDashboard object)
    |
    | [3] POST admin-ajax.php  action=tp_get_usage_summary  (nonce, uid, start_date, end_date)
    v
WordPress wp-admin/admin-ajax.php
    |
    | [4] Dispatches to registered wp_ajax_ handler
    v
includes/class-tp-api-handler.php  ::ajax_get_usage_summary()
    |
    | [5] Validates nonce, reads uid from TP_Link_Shortener::get_user_id()
    | [6] Calls TrafficPortalApiClient::getUserActivitySummary(uid, start_date, end_date)
    v
includes/TrafficPortal/TrafficPortalApiClient.php  ::getUserActivitySummary()
    |
    | [7] GET {TP_API_ENDPOINT}/user-activity-summary/{uid}?start=...&end=...
    v
External Traffic Portal REST API
    |
    | [8] Returns daily totals: [{ date, totalHits }, ...]
    v
TrafficPortalApiClient  (parse response → DTO or plain array)
    |
    v
TP_API_Handler  (wp_send_json_success($data))
    |
    v
usage-dashboard.js
    | [9] Receives { success: true, data: { days: [...] } }
    | [10] Derives clicks/QR split via mock ratio (e.g. 80/20)
    | [11] Renders Chart.js area chart + stats table
    v
Browser DOM (chart canvas + stats table updated)
```

---

### Component Responsibilities

| Component | File | Responsibility |
|-----------|------|----------------|
| Shortcode class | `includes/class-tp-usage-dashboard-shortcode.php` | Register `[tp_usage_dashboard]` shortcode, gate behind `is_user_logged_in()`, enqueue assets, include template via output buffer |
| Template | `templates/usage-dashboard-template.php` | Static HTML skeleton: chart canvas placeholder, stats table shell, date range inputs, Apply button |
| JavaScript | `assets/js/usage-dashboard.js` | AJAX call orchestration, date state, Chart.js area chart render, table render, mock clicks/QR split, date range Apply button handler |
| CSS | `assets/css/usage-dashboard.css` | Dashboard-specific layout: chart wrapper, stats table, date range controls; imports shared CSS custom properties from `:root` |
| AJAX handler | `includes/class-tp-api-handler.php` (new method) | `ajax_get_usage_summary()` — nonce verify, get uid, call API client, return JSON |
| API client method | `includes/TrafficPortal/TrafficPortalApiClient.php` (new method) | `getUserActivitySummary(int $uid, string $start, string $end)` — GET request to external API, parse response |
| Plugin singleton | `includes/class-tp-link-shortener.php` | Instantiate new shortcode class in `init()` |
| Plugin entry | `tp-link-shortener.php` | `require_once` the new shortcode class file |

---

## Recommended Project Structure

New files required (additions only — no existing files deleted):

```
includes/
    class-tp-usage-dashboard-shortcode.php   # new — shortcode class

templates/
    usage-dashboard-template.php             # new — HTML skeleton

assets/
    css/
        usage-dashboard.css                  # new — scoped styles
    js/
        usage-dashboard.js                   # new — chart + table logic
```

Existing files modified (minimal, additive only):

```
includes/
    class-tp-api-handler.php         # add ajax_get_usage_summary() + register_ajax_handlers() entries
    TrafficPortal/
        TrafficPortalApiClient.php   # add getUserActivitySummary() method

includes/
    class-tp-link-shortener.php      # add $usage_dashboard_shortcode property + instantiate in init()

tp-link-shortener.php               # add require_once for new shortcode class
```

---

## Architectural Patterns

### Pattern 1: Shortcode Class — Template Method

Every shortcode in the plugin follows an identical four-step template method:

1. `__construct()` calls `add_shortcode('tp_xxx', [$this, 'render_shortcode'])`
2. `render_shortcode()` gates with `is_user_logged_in()`, calls `$this->enqueue_assets()`
3. `enqueue_assets()` calls `wp_enqueue_style()` / `wp_enqueue_script()` / `wp_localize_script()`
4. `render_shortcode()` uses `ob_start()` / `include template` / `return ob_get_clean()`

**Apply this exactly.** Do not deviate. The existing three shortcodes are the canonical reference.

**Example (`class-tp-usage-dashboard-shortcode.php`):**

```php
<?php
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

class TP_Usage_Dashboard_Shortcode {

    public function __construct() {
        add_shortcode('tp_usage_dashboard', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts): string {
        if (!is_user_logged_in()) {
            return '';
        }

        $atts = shortcode_atts([
            'days' => 30,
        ], $atts);

        $this->enqueue_assets($atts);

        ob_start();
        include TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/usage-dashboard-template.php';
        return ob_get_clean();
    }

    private function enqueue_assets(array $atts): void {
        wp_enqueue_style('tp-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', [], '5.3.0');
        wp_enqueue_style('tp-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');
        wp_enqueue_style('tp-link-shortener', TP_LINK_SHORTENER_PLUGIN_URL . 'assets/css/frontend.css', ['tp-bootstrap'], TP_LINK_SHORTENER_VERSION);
        wp_enqueue_style('tp-usage-dashboard', TP_LINK_SHORTENER_PLUGIN_URL . 'assets/css/usage-dashboard.css', ['tp-bootstrap', 'tp-link-shortener'], TP_LINK_SHORTENER_VERSION);

        wp_enqueue_script('tp-bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', ['jquery'], '5.3.0', true);
        wp_enqueue_script('tp-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], '4.4.1', true);
        wp_enqueue_script('tp-usage-dashboard-js', TP_LINK_SHORTENER_PLUGIN_URL . 'assets/js/usage-dashboard.js', ['jquery', 'tp-bootstrap-js', 'tp-chartjs'], TP_LINK_SHORTENER_VERSION, true);

        $end = date('Y-m-d');
        $start = date('Y-m-d', strtotime('-' . intval($atts['days']) . ' days'));

        wp_localize_script('tp-usage-dashboard-js', 'tpUsageDashboard', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('tp_link_shortener_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'dateRange'  => ['start' => $start, 'end' => $end],
            'strings'    => [
                'loading' => __('Loading...', 'tp-link-shortener'),
                'error'   => __('Error loading usage data. Please try again.', 'tp-link-shortener'),
                'noData'  => __('No activity in this date range.', 'tp-link-shortener'),
                'apply'   => __('Apply', 'tp-link-shortener'),
            ],
        ]);
    }
}
```

---

### Pattern 2: AJAX Handler — Nonce → UID → API Client → JSON

Every AJAX handler in `TP_API_Handler` follows this exact sequence:

```php
public function ajax_get_usage_summary(): void {
    // 1. Verify nonce (always first, before any data access)
    check_ajax_referer('tp_link_shortener_nonce', 'nonce');

    // 2. Get UID server-side (never trust client-sent uid)
    $uid = TP_Link_Shortener::get_user_id();

    // 3. Sanitize and validate inputs
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date   = isset($_POST['end_date'])   ? sanitize_text_field($_POST['end_date'])   : '';

    // 4. Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        wp_send_json_error(['message' => 'Invalid date range']);
    }

    // 5. Delegate to API client
    try {
        $data = $this->client->getUserActivitySummary($uid, $start_date, $end_date);
        wp_send_json_success($data);
    } catch (NetworkException $e) {
        wp_send_json_error(['message' => __('Network error. Please try again.', 'tp-link-shortener')]);
    } catch (ApiException $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
```

Register in `register_ajax_handlers()`:

```php
// Logged-in only (usage dashboard requires authentication)
add_action('wp_ajax_tp_get_usage_summary', [$this, 'ajax_get_usage_summary']);
add_action('wp_ajax_nopriv_tp_get_usage_summary', [$this, 'ajax_require_login']);
```

---

### Pattern 3: JavaScript IIFE with State Object

All three existing JS files use the same structure: a jQuery IIFE containing a `state` object and named functions. Follow this exactly.

```javascript
(function($) {
    'use strict';

    // State (single source of truth)
    var state = {
        isLoading: false,
        chart: null,
        dateStart: '',
        dateEnd: '',
        data: []
    };

    // DOM cache (populated in cacheDom, never query DOM outside cacheDom/init)
    var $container, $chartCanvas, $tbody, $dateStart, $dateEnd, $applyBtn, $loading, $error;

    function init() {
        $container = $('.tp-ud-container');
        if (!$container.length) return;

        cacheDom();

        if (!tpUsageDashboard.isLoggedIn) return;

        state.dateStart = tpUsageDashboard.dateRange.start;
        state.dateEnd   = tpUsageDashboard.dateRange.end;
        $dateStart.val(state.dateStart);
        $dateEnd.val(state.dateEnd);

        bindEvents();
        loadData();
    }

    function cacheDom() { /* ... */ }
    function bindEvents() { /* ... */ }
    function loadData() { /* AJAX call, then renderChart() + renderTable() */ }
    function renderChart(days) { /* Chart.js area chart */ }
    function renderTable(days) { /* stats table rows */ }
    function mockSplit(totalHits) { /* returns { clicks, qr } */ }

    $(document).ready(init);

})(jQuery);
```

---

### Pattern 4: Mock Clicks/QR Split

The API returns only `totalHits` per day. The split is mocked client-side using a deterministic ratio seeded from the date string (to avoid random flicker on re-render):

```javascript
function mockSplit(date, totalHits) {
    // Deterministic 80/20 split: stable across renders, no random flicker
    // The 80/20 ratio is a placeholder — replace when API returns real split
    var clicks = Math.round(totalHits * 0.80);
    var qr     = totalHits - clicks;
    return { clicks: clicks, qr: qr };
}
```

This keeps the mock centralized in one function, making it trivial to replace when the API evolves to return the real split.

---

### Pattern 5: API Client Method Addition

Add `getUserActivitySummary()` to `TrafficPortalApiClient` following the same pattern as the existing `getMapItems()` method (GET request, handle HTTP errors via `handleHttpErrors()`, return parsed data):

```php
/**
 * Get user activity summary for a date range
 *
 * @param int $uid User ID
 * @param string $startDate Format: Y-m-d
 * @param string $endDate Format: Y-m-d
 * @return array Daily activity records: [['date' => 'Y-m-d', 'totalHits' => int], ...]
 * @throws NetworkException
 * @throws ApiException
 */
public function getUserActivitySummary(int $uid, string $startDate, string $endDate): array
{
    $url = $this->apiEndpoint . '/user-activity-summary/' . $uid
         . '?start=' . urlencode($startDate)
         . '&end='   . urlencode($endDate);

    $httpClient = $this->getHttpClient();
    $response   = $httpClient->get($url, ['x-api-key' => $this->apiKey]);

    $this->handleHttpErrors($response->getStatusCode(), $response->getBody());

    return $response->getBody()['days'] ?? [];
}
```

**Note:** The exact response envelope shape (`data.days`, `data.records`, etc.) must be verified against the live API before implementation. The method returns a plain array — no new DTO class is needed for this simple structure, but one can be added if the team prefers type safety.

---

## Data Flow

### Request Flow (Full)

```
[User loads page with [tp_usage_dashboard]]
    |
    v
WordPress resolves shortcode → TP_Usage_Dashboard_Shortcode::render_shortcode()
    |  is_user_logged_in() check → return '' if not logged in
    v
enqueue_assets() → wp_enqueue_script/style + wp_localize_script('tpUsageDashboard', {...})
    |
    v
ob_start() → include usage-dashboard-template.php → ob_get_clean()
    (HTML: container div, canvas#tp-ud-chart, table#tp-ud-stats, date inputs, Apply btn)
    |
    v
Browser: DOM ready fires usage-dashboard.js init()
    |  Reads tpUsageDashboard.dateRange.start / .end from wp_localize_script data
    v
loadData() → $.ajax POST to admin-ajax.php
    |  action: 'tp_get_usage_summary'
    |  nonce:  tpUsageDashboard.nonce
    |  start_date, end_date
    v
admin-ajax.php → TP_API_Handler::ajax_get_usage_summary()
    |  check_ajax_referer('tp_link_shortener_nonce', 'nonce')
    |  uid = TP_Link_Shortener::get_user_id()    ← server-side, never from POST
    v
TrafficPortalApiClient::getUserActivitySummary(uid, start, end)
    |  GET /user-activity-summary/{uid}?start=...&end=...
    |  x-api-key header
    v
External Traffic Portal API
    |  Response: { days: [{ date, totalHits }, ...] }
    v
wp_send_json_success({ days: [...] })
    v
usage-dashboard.js AJAX success callback
    |  data.data.days → apply mockSplit() per day
    v
renderChart(days)   → Chart.js area chart (clicks + QR stacked/overlaid)
renderTable(days)   → <tr> per day with date / clicks / QR / total columns
```

### Date Range Filter Flow

```
User changes date inputs + clicks Apply
    |
    v
$applyBtn click handler → reads $dateStart.val(), $dateEnd.val()
    |  validates: start < end, not future
    v
state.dateStart = ..., state.dateEnd = ...
    |
    v
loadData()   ← same function as initial load, reads from state
    |
    v
AJAX → re-renders chart and table
```

---

## Component Build Order

Build in this order — each step depends on the previous:

```
Step 1: PHP Shortcode Class + Plugin Registration (no dependencies)
    class-tp-usage-dashboard-shortcode.php
    + register in class-tp-link-shortener.php + tp-link-shortener.php

Step 2: HTML Template (depends on shortcode class existing to include it)
    templates/usage-dashboard-template.php
    (static skeleton: container, canvas, table, date inputs, Apply btn)

Step 3: API Client Method (depends on knowing the API response shape)
    TrafficPortalApiClient::getUserActivitySummary()
    + AJAX handler in class-tp-api-handler.php

Step 4: JavaScript (depends on template HTML IDs + AJAX handler action name)
    assets/js/usage-dashboard.js
    — AJAX call wired to action 'tp_get_usage_summary'
    — Chart.js area chart render
    — Stats table render
    — Mock split function
    — Date Apply button handler

Step 5: CSS (depends on template HTML classes)
    assets/css/usage-dashboard.css
    — Chart wrapper sizing
    — Stats table layout
    — Date range control styles
    — Loading/error state styles
```

**Rationale for this order:** PHP shortcode and template define the HTML structure (IDs and classes). JS depends on those IDs for DOM targeting. CSS depends on those classes for styling. API client and AJAX handler must exist before JS fires live requests. Never write JS or CSS before the HTML contract (template) is finalized.

---

## File Naming Conventions

Follow the established pattern exactly:

| New File | Naming Rationale |
|----------|-----------------|
| `class-tp-usage-dashboard-shortcode.php` | WordPress class prefix `class-tp-`, then component name in kebab-case, suffix `-shortcode.php` — matches `class-tp-dashboard-shortcode.php` and `class-tp-client-links-shortcode.php` |
| `templates/usage-dashboard-template.php` | Component name kebab-case + `-template.php` — matches `dashboard-template.php`, `client-links-template.php` |
| `assets/js/usage-dashboard.js` | Component name kebab-case — matches `dashboard.js`, `client-links.js` |
| `assets/css/usage-dashboard.css` | Component name kebab-case — matches `dashboard.css`, `client-links.css` |

CSS class prefix for template: use `tp-ud-` (Usage Dashboard) to avoid collision with `tp-dashboard` (existing dashboard) and `tp-cl-` (client-links).

`wp_localize_script` global object name: `tpUsageDashboard` — matches `tpDashboard` and `tpClientLinks`.

AJAX action names: `tp_get_usage_summary` — follows `tp_get_user_map_items` naming pattern.

PHP class name: `TP_Usage_Dashboard_Shortcode` — follows `TP_Dashboard_Shortcode`, `TP_Client_Links_Shortcode`.

---

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| Traffic Portal API | `GET /user-activity-summary/{uid}` via `TrafficPortalApiClient` | Endpoint and auth already configured in existing client; add one method, no new client class |
| Chart.js 4.4.1 | Already enqueued in client-links shortcode as `tp-chartjs` — reuse the same handle | WordPress deduplicates scripts by handle; registering `tp-chartjs` twice is safe |
| Bootstrap 5.3.0 | Already enqueued as `tp-bootstrap` — reuse same handle | Same deduplication applies |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Shortcode class → TP_API_Handler | No direct call; AJAX via browser | Shortcode only enqueues assets and renders template; all data goes via AJAX |
| usage-dashboard.js → admin-ajax.php | jQuery $.ajax POST with nonce | Standard wp_ajax pattern used by all existing shortcodes |
| TP_API_Handler → TrafficPortalApiClient | Direct PHP method call | Handler holds `$this->client` reference; add new method to existing client |
| Template → JavaScript | `tpUsageDashboard` global injected by `wp_localize_script` | The only PHP→JS data bridge; all runtime data fetched via AJAX |

---

## Anti-Patterns

### Anti-Pattern 1: Adding a New API Client Class for Usage Data

**What people do:** Create `includes/TrafficPortal/UsageClient.php` as a separate class for the activity summary endpoint.

**Why it's wrong:** The existing `TrafficPortalApiClient` already owns all Traffic Portal endpoints and holds the API key and endpoint configuration. Creating a parallel client duplicates initialization, creates a second place to update when the API key changes, and breaks the Facade pattern `TP_API_Handler` relies on.

**Do this instead:** Add `getUserActivitySummary()` as a new method on the existing `TrafficPortalApiClient`.

---

### Anti-Pattern 2: Sending UID from JavaScript

**What people do:** In the AJAX POST body, include `uid: tpUsageDashboard.userId`.

**Why it's wrong:** Client-supplied user IDs can be spoofed. Every existing AJAX handler in `TP_API_Handler` calls `TP_Link_Shortener::get_user_id()` server-side and ignores any uid from `$_POST`. Recent commits explicitly removed client-side uid passing (see commit `e063541`).

**Do this instead:** Never include uid in the AJAX payload. The handler calls `TP_Link_Shortener::get_user_id()` which reads `get_current_user_id()` for logged-in users.

---

### Anti-Pattern 3: Randomizing the Mock Clicks/QR Split Per Render

**What people do:** `var qr = Math.floor(Math.random() * totalHits)`.

**Why it's wrong:** Chart re-renders on date change, page focus, or any trigger. Random split causes chart bars to jitter between renders for identical data — confusing and looks broken.

**Do this instead:** Use a deterministic formula (fixed ratio, or a hash of the date string) so the same totalHits always produces the same split regardless of how many times the chart renders.

---

### Anti-Pattern 4: Sharing a CSS File with the Existing Dashboard

**What people do:** Add usage dashboard styles to `dashboard.css` because "they're similar."

**Why it's wrong:** The existing convention is one CSS file per shortcode/view (`dashboard.css` for `[tp_link_dashboard]`, `client-links.css` for `[tp_client_links]`). Mixing concerns forces both CSS files to load even when only one shortcode is on the page.

**Do this instead:** Create `usage-dashboard.css` and enqueue it only from `TP_Usage_Dashboard_Shortcode::enqueue_assets()`.

---

### Anti-Pattern 5: Calling `wp_localize_script` Before `wp_enqueue_script`

**What people do:** Call `wp_localize_script('tp-usage-dashboard-js', ...)` before `wp_enqueue_script('tp-usage-dashboard-js', ...)`.

**Why it's wrong:** `wp_localize_script` requires the script handle to be registered/enqueued first. It silently fails if called before enqueue.

**Do this instead:** Call `wp_enqueue_script()` for the handle, then immediately call `wp_localize_script()` — in this order, in the same `enqueue_assets()` method. All existing shortcodes follow this ordering.

---

## Scalability Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0–1k users | Current AJAX proxy to external API is fine. No caching needed. |
| 1k–10k users | Consider WordPress transient caching on the API response (keyed by uid + date range) with 5–15 minute TTL. Reduces Traffic Portal API calls when multiple users view the same day's data. |
| 10k+ users | If Traffic Portal API rate-limits per-account, the WordPress transient cache becomes necessary. Also consider whether the usage dashboard should pull from a local WordPress table rather than the external API. |

**First bottleneck:** External API rate limits from Traffic Portal, not WordPress load. The proxy pattern means every usage dashboard page load hits the external API. Add `get_transient`/`set_transient` wrapping in `ajax_get_usage_summary()` before this becomes a problem.

---

## Sources

- Direct inspection: `includes/class-tp-dashboard-shortcode.php` — canonical shortcode pattern (HIGH confidence)
- Direct inspection: `includes/class-tp-client-links-shortcode.php` — second canonical shortcode pattern (HIGH confidence)
- Direct inspection: `includes/class-tp-api-handler.php` — AJAX handler registration and nonce pattern (HIGH confidence)
- Direct inspection: `includes/TrafficPortal/TrafficPortalApiClient.php` — HTTP client method pattern (HIGH confidence)
- Direct inspection: `assets/js/client-links.js` — IIFE/state/DOM-cache JS pattern (HIGH confidence)
- Direct inspection: `includes/class-tp-link-shortener.php` — plugin singleton and component registration (HIGH confidence)
- Direct inspection: `tp-link-shortener.php` — plugin entry, require_once pattern (HIGH confidence)
- Direct inspection: `.planning/codebase/ARCHITECTURE.md` — layer map and data flows (HIGH confidence)
- Direct inspection: `.planning/codebase/CONVENTIONS.md` — naming rules, file patterns (HIGH confidence)
- Git log: commit `e063541` — confirmed uid must always be server-side (HIGH confidence)

---

*Architecture research for: `[tp_usage_dashboard]` billing/usage dashboard shortcode*
*Researched: 2026-02-22*
