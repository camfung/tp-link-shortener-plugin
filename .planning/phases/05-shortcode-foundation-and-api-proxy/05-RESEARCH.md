# Phase 5: Shortcode Foundation and API Proxy - Research

**Researched:** 2026-02-22
**Domain:** WordPress shortcode registration, authentication gating, AJAX proxy to external REST API
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **Authentication gate**: Logged-out users see an inline login form using `wp_login_form()` on the page. Simple WordPress form, no extra branding or styled messaging. After login, standard form submit reloads the page and dashboard appears. Any logged-in WordPress user can access the dashboard (no role restriction).
- **Proxy error handling**: On API failure, show a friendly error message with a "Retry" button that re-fetches without full page reload. Generic error message for regular users; admins see the actual error type (e.g., timeout, 500, connection refused). Proxy validates and reshapes the API response before sending to frontend -- check structure, strip unexpected fields, normalize format.
- **Caching**: No caching for v1.0 of this feature -- every page load and every retry hits the external API fresh. Keep it simple; caching can be added as a future optimization.

### Claude's Discretion
- Page skeleton layout and loading state design
- Proxy timeout value
- Response validation/reshaping specifics
- AJAX nonce and security implementation details

### Deferred Ideas (OUT OF SCOPE)
- Caching with WordPress transients -- add once the feature is stable and API call patterns are understood
- Per-user cache keying strategy -- revisit when caching is implemented
</user_constraints>

## Summary

Phase 5 establishes the foundation for the v2.0 Usage Dashboard by registering the `[tp_usage_dashboard]` shortcode, rendering a page skeleton with loading state, gating unauthenticated users behind `wp_login_form()`, and wiring an AJAX proxy that fetches real data from the external `GET /user-activity-summary/{uid}` API endpoint. Caching is explicitly deferred.

The codebase already has three working shortcodes (`tp_link_shortener`, `tp_link_dashboard`, `tp_client_links`) that follow identical patterns: constructor registers shortcode, `render_shortcode()` gates on auth then enqueues assets and includes template via output buffer, AJAX handlers verify nonce and determine UID server-side. Phase 5 adds one more shortcode following this exact pattern, plus a new API client method and AJAX handler.

The critical difference from existing shortcodes: instead of returning an empty string for logged-out users, this shortcode returns the `wp_login_form()` output. This is the only structural deviation from the established pattern, and it is a locked user decision.

**Primary recommendation:** Follow the established shortcode/template/AJAX/API-client pattern exactly. The only new concepts are `wp_login_form()` for the auth gate and response validation/reshaping in the AJAX handler.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Shortcode API | WP 5.8+ | Register `[tp_usage_dashboard]` | Built-in; `add_shortcode()` used by all 3 existing shortcodes |
| WordPress AJAX API | WP 5.8+ | `admin-ajax.php` proxy endpoint | Built-in; `wp_ajax_` hooks used by all existing AJAX handlers |
| WordPress HTTP API | WP 5.8+ | Not used directly (existing codebase uses cURL) | Available but the existing API client uses cURL via `CurlHttpClient` |
| jQuery | 3.x (bundled) | AJAX calls from JS IIFE | Bundled with WordPress; all existing JS files use jQuery IIFE pattern |
| Bootstrap | 5.3.0 (CDN) | Page skeleton layout | Already enqueued by both existing dashboard shortcodes |
| Font Awesome | 6.4.0 (CDN) | Icons in skeleton | Already enqueued by both existing dashboard shortcodes |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Chart.js | 4.4.1 (CDN) | Chart placeholder in skeleton | Enqueued now for the canvas placeholder; actual chart rendering is Phase 7 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `wp_login_form()` | Custom login template | `wp_login_form()` is the locked decision; no alternative needed |
| cURL via `CurlHttpClient` | `wp_remote_get()` (WordPress HTTP API) | Codebase already uses cURL everywhere; switching to `wp_remote_get()` would be inconsistent. Keep cURL. |
| `admin-ajax.php` | WP REST API (`register_rest_route`) | Existing codebase uses admin-ajax for all frontend endpoints; REST API only used for the logs endpoint. Consistency wins. |

## Architecture Patterns

### Recommended Project Structure
```
includes/
    class-tp-usage-dashboard-shortcode.php   # new -- shortcode class

templates/
    usage-dashboard-template.php             # new -- HTML skeleton with login form fallback

assets/
    css/
        usage-dashboard.css                  # new -- scoped styles with tp-ud- prefix
    js/
        usage-dashboard.js                   # new -- AJAX + loading state + retry logic

# Modified files (additive only):
includes/class-tp-api-handler.php            # add ajax_get_usage_summary() + hook registrations
includes/TrafficPortal/TrafficPortalApiClient.php  # add getUserActivitySummary() method
includes/class-tp-link-shortener.php         # add $usage_dashboard_shortcode property + init
tp-link-shortener.php                        # add require_once for new shortcode class
```

### Pattern 1: Shortcode Class with wp_login_form() Auth Gate

**What:** Register shortcode, show `wp_login_form()` for logged-out users, render dashboard skeleton for logged-in users.

**When to use:** This shortcode only.

**Key difference from existing shortcodes:** The existing `TP_Dashboard_Shortcode` and `TP_Client_Links_Shortcode` return an empty string `''` for logged-out users, hiding the entire component. The CONTEXT decision explicitly requires showing `wp_login_form()` instead.

**Example:**
```php
// Source: wp_login_form() docs (developer.wordpress.org/reference/functions/wp_login_form/)
// + existing shortcode pattern from class-tp-client-links-shortcode.php

public function render_shortcode($atts): string {
    if (!is_user_logged_in()) {
        // Locked decision: show inline login form
        return wp_login_form(array(
            'echo'     => false,  // return HTML string, don't echo
            'redirect' => get_permalink(),  // reload current page after login
            'remember' => true,
        ));
    }

    $atts = shortcode_atts(array(
        'days' => 30,
    ), $atts);

    $this->enqueue_assets($atts);

    ob_start();
    include TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/usage-dashboard-template.php';
    return ob_get_clean();
}
```

**Critical `wp_login_form()` detail:** The `echo` parameter MUST be `false` to return the HTML as a string (shortcodes must return, not echo). The `redirect` parameter should use `get_permalink()` to redirect back to the current page so the dashboard appears after login.

Source: [WordPress Developer Reference - wp_login_form()](https://developer.wordpress.org/reference/functions/wp_login_form/)

### Pattern 2: AJAX Handler with Response Validation/Reshaping

**What:** The AJAX handler calls the external API, validates the response structure, strips unexpected fields, and normalizes the format before returning to the frontend.

**When to use:** Every AJAX handler that proxies external API data.

**Example:**
```php
public function ajax_get_usage_summary(): void {
    // 1. Verify nonce
    check_ajax_referer('tp_link_shortener_nonce', 'nonce');

    // 2. Ensure authenticated
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('You must be logged in.', 'tp-link-shortener')], 401);
        return;
    }

    // 3. Get UID server-side (DATA-02: never from client)
    $uid = TP_Link_Shortener::get_user_id();

    // 4. Sanitize date inputs
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date   = isset($_POST['end_date'])   ? sanitize_text_field($_POST['end_date'])   : '';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        wp_send_json_error(['message' => __('Invalid date format.', 'tp-link-shortener')], 400);
        return;
    }

    try {
        $raw = $this->client->getUserActivitySummary($uid, $start_date, $end_date);

        // 5. Validate + reshape response (locked decision)
        $validated = $this->validate_usage_summary_response($raw);

        wp_send_json_success($validated);

    } catch (NetworkException $e) {
        $this->send_proxy_error($e, 'network');
    } catch (ApiException $e) {
        $this->send_proxy_error($e, 'api');
    } catch (\Exception $e) {
        $this->send_proxy_error($e, 'unknown');
    }
}

/**
 * Validate and reshape the API response.
 * Strip unexpected fields, check types, normalize format.
 */
private function validate_usage_summary_response(array $raw): array {
    // API returns: { message, success, source: [{ date, totalHits, hitCost, balance }] }
    $source = $raw['source'] ?? [];

    if (!is_array($source)) {
        $source = [];
    }

    $days = [];
    foreach ($source as $record) {
        // Only include records with expected fields
        if (!isset($record['date']) || !isset($record['totalHits'])) {
            continue;
        }

        $days[] = [
            'date'      => sanitize_text_field($record['date']),
            'totalHits' => (int) $record['totalHits'],
            'hitCost'   => (float) ($record['hitCost'] ?? 0),
            'balance'   => (float) ($record['balance'] ?? 0),
        ];
    }

    return ['days' => $days];
}

/**
 * Send error response with admin-conditional detail.
 * Locked decision: generic for regular users, detailed for admins.
 */
private function send_proxy_error(\Exception $e, string $type): void {
    $response = [
        'message' => __('Unable to load usage data. Please try again.', 'tp-link-shortener'),
    ];

    // Admins see the actual error type
    if (current_user_can('manage_options')) {
        $response['error_type'] = $type;
        $response['error_detail'] = $e->getMessage();
    }

    wp_send_json_error($response);
}
```

### Pattern 3: Loading Skeleton HTML (Claude's Discretion)

**What:** Static HTML skeleton rendered by the template, visible while AJAX fetches data.

**Recommendation:** Use animated CSS pulse placeholders matching the dashboard layout (chart area + table rows). This follows the skeleton loading pattern used by modern dashboards and is simple to implement with pure CSS.

**Example skeleton structure:**
```html
<div class="tp-ud-container">
    <!-- Loading skeleton (shown initially, hidden when data arrives) -->
    <div class="tp-ud-skeleton" id="tp-ud-skeleton">
        <!-- Chart placeholder -->
        <div class="tp-ud-skeleton-chart">
            <div class="tp-ud-skeleton-bar"></div>
        </div>
        <!-- Table placeholder rows -->
        <div class="tp-ud-skeleton-table">
            <div class="tp-ud-skeleton-row"></div>
            <div class="tp-ud-skeleton-row"></div>
            <div class="tp-ud-skeleton-row"></div>
        </div>
    </div>

    <!-- Error state (hidden initially) -->
    <div class="tp-ud-error" id="tp-ud-error" style="display: none;">
        <p class="tp-ud-error-msg" id="tp-ud-error-msg"></p>
        <button class="btn btn-primary" id="tp-ud-retry">
            <i class="fas fa-redo me-1"></i> Retry
        </button>
    </div>

    <!-- Dashboard content (hidden until data loaded) -->
    <div class="tp-ud-content" id="tp-ud-content" style="display: none;">
        <!-- Chart canvas, stats table, date inputs -->
    </div>
</div>
```

### Anti-Patterns to Avoid

- **Sending UID from JavaScript:** The uid must ALWAYS come from `TP_Link_Shortener::get_user_id()` server-side. Never accept it from `$_POST`. This is explicitly documented in the codebase architecture and in commit history (commit `e063541` removed client-side uid passing).

- **Creating a new API client class:** Add `getUserActivitySummary()` as a method on the existing `TrafficPortalApiClient`. Do not create a separate client class.

- **Returning raw API response without validation:** The locked decision requires the proxy to validate and reshape the API response. Strip unexpected fields, check types, normalize format.

- **Echoing from the shortcode:** WordPress shortcodes MUST return their output, not echo it. Use `ob_start()` / `ob_get_clean()` for templates, and `echo => false` for `wp_login_form()`.

- **Calling `wp_localize_script` before `wp_enqueue_script`:** `wp_localize_script` silently fails if called before the script handle is enqueued. Always enqueue first, then localize.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Login form | Custom HTML login form | `wp_login_form()` | WordPress built-in; handles CSRF tokens, redirects, password reset link. Locked decision. |
| AJAX nonce | Custom CSRF tokens | `wp_create_nonce()` + `check_ajax_referer()` | WordPress built-in nonce system; all existing handlers use it |
| JSON responses | Manual `header()` + `echo json_encode()` | `wp_send_json_success()` / `wp_send_json_error()` | Handles headers, encoding, and `wp_die()` automatically |
| Date validation | Custom date parsing | `preg_match('/^\d{4}-\d{2}-\d{2}$/', ...)` | Simple regex is sufficient for YYYY-MM-DD format validation |

**Key insight:** Every WordPress API function used here (shortcodes, AJAX, nonces, login form) has well-established conventions in the codebase. There is nothing novel to build -- only a new combination of existing patterns.

## Common Pitfalls

### Pitfall 1: wp_login_form() echo vs return
**What goes wrong:** The shortcode renders a blank page or the login form appears above the page content instead of in the shortcode location.
**Why it happens:** `wp_login_form()` defaults to `echo => true`, which outputs HTML immediately instead of returning it. Shortcodes must return strings.
**How to avoid:** Always pass `'echo' => false` in the args array.
**Warning signs:** Login form HTML appearing at the top of the page instead of where the shortcode is placed.

### Pitfall 2: wp_login_form() redirect back to wrong page
**What goes wrong:** User logs in from the usage dashboard page and gets redirected to the home page or admin dashboard instead of back to the usage dashboard page.
**Why it happens:** Default redirect is `admin_url()`. The form must redirect back to the page containing the shortcode.
**How to avoid:** Pass `'redirect' => get_permalink()` which returns the current page's URL.
**Warning signs:** After login, user is on a different page than where they started.

### Pitfall 3: AJAX handler registered but not firing
**What goes wrong:** JS sends AJAX request, gets 0 status or `admin-ajax.php` returns "0" or "-1".
**Why it happens:** Three common causes: (1) Action name in JS doesn't match `wp_ajax_` hook suffix. (2) `wp_ajax_nopriv_` handler missing for unauthenticated edge cases. (3) Nonce name in JS doesn't match `check_ajax_referer()`.
**How to avoid:** Use consistent naming: action `tp_get_usage_summary` in JS matches `wp_ajax_tp_get_usage_summary` hook. Nonce name `tp_link_shortener_nonce` matches all existing handlers. Register `wp_ajax_nopriv_tp_get_usage_summary` pointing to `ajax_require_login()` for clean 401 response.
**Warning signs:** Network tab shows 200 response but body is "0" or empty.

### Pitfall 4: API response structure mismatch
**What goes wrong:** Proxy returns success but frontend JS cannot find the data fields.
**Why it happens:** The external API wraps data in `{ message, success, source: [...] }` where `source` is the actual data array. If the proxy doesn't unwrap this, the JS receives a double-nested structure: `{ success: true, data: { message: ..., success: ..., source: [...] } }`.
**How to avoid:** In the AJAX handler, extract the `source` array from the API response, validate each record, and return the cleaned data via `wp_send_json_success()`. The JS receives `{ success: true, data: { days: [...] } }`.
**Warning signs:** JS receives data but `data.days` is undefined; the actual array is at `data.source`.

### Pitfall 5: External API timeout blocking page load
**What goes wrong:** The AJAX request hangs for 30+ seconds or times out, and the user sees only the loading skeleton indefinitely.
**Why it happens:** The external API Lambda has a 15-second timeout, but network latency adds more. The existing `TrafficPortalApiClient` has a 30-second default timeout which is too long for a user-facing AJAX request.
**How to avoid:** Use a proxy timeout of 15 seconds (matching the Lambda timeout plus small buffer). The JS should have its own timeout to show the error/retry UI if the AJAX takes too long.
**Warning signs:** Loading spinner visible for more than 10 seconds.

## Code Examples

### Complete Shortcode Class
```php
<?php
// Source: Derived from class-tp-client-links-shortcode.php pattern
// + wp_login_form() from developer.wordpress.org/reference/functions/wp_login_form/
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

class TP_Usage_Dashboard_Shortcode {

    public function __construct() {
        add_shortcode('tp_usage_dashboard', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts): string {
        // Auth gate: show login form for unauthenticated users
        if (!is_user_logged_in()) {
            return '<div class="tp-ud-login-wrapper">'
                . wp_login_form([
                    'echo'     => false,
                    'redirect' => get_permalink(),
                    'remember' => true,
                ])
                . '</div>';
        }

        $atts = shortcode_atts(['days' => 30], $atts);

        $this->enqueue_assets($atts);

        ob_start();
        include TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/usage-dashboard-template.php';
        return ob_get_clean();
    }

    private function enqueue_assets(array $atts): void {
        // CSS
        wp_enqueue_style('tp-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', [], '5.3.0');
        wp_enqueue_style('tp-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');
        wp_enqueue_style('tp-link-shortener', TP_LINK_SHORTENER_PLUGIN_URL . 'assets/css/frontend.css', ['tp-bootstrap'], TP_LINK_SHORTENER_VERSION);
        wp_enqueue_style('tp-usage-dashboard', TP_LINK_SHORTENER_PLUGIN_URL . 'assets/css/usage-dashboard.css', ['tp-bootstrap', 'tp-link-shortener'], TP_LINK_SHORTENER_VERSION);

        // JS
        wp_enqueue_script('tp-bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', ['jquery'], '5.3.0', true);
        wp_enqueue_script('tp-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], '4.4.1', true);
        wp_enqueue_script('tp-usage-dashboard-js', TP_LINK_SHORTENER_PLUGIN_URL . 'assets/js/usage-dashboard.js', ['jquery', 'tp-bootstrap-js', 'tp-chartjs'], TP_LINK_SHORTENER_VERSION, true);

        // Localize (MUST be after enqueue)
        $end   = date('Y-m-d');
        $start = date('Y-m-d', strtotime('-' . intval($atts['days']) . ' days'));

        wp_localize_script('tp-usage-dashboard-js', 'tpUsageDashboard', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('tp_link_shortener_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'isAdmin'    => current_user_can('manage_options'),
            'dateRange'  => ['start' => $start, 'end' => $end],
            'strings'    => [
                'loading'  => __('Loading usage data...', 'tp-link-shortener'),
                'error'    => __('Unable to load usage data. Please try again.', 'tp-link-shortener'),
                'noData'   => __('No activity in this date range.', 'tp-link-shortener'),
                'retry'    => __('Retry', 'tp-link-shortener'),
            ],
        ]);
    }
}
```

### AJAX Handler Registration (addition to class-tp-api-handler.php)
```php
// Source: Existing register_ajax_handlers() pattern in class-tp-api-handler.php

// In register_ajax_handlers():
add_action('wp_ajax_tp_get_usage_summary', [$this, 'ajax_get_usage_summary']);
add_action('wp_ajax_nopriv_tp_get_usage_summary', [$this, 'ajax_require_login']);
```

### API Client Method (addition to TrafficPortalApiClient.php)
```php
// Source: Existing getUserMapItems() pattern in TrafficPortalApiClient.php

public function getUserActivitySummary(int $uid, string $startDate, string $endDate): array
{
    $queryParams = [
        'start_date' => $startDate,
        'end_date'   => $endDate,
    ];

    $url = $this->apiEndpoint . '/user-activity-summary/' . $uid
         . '?' . http_build_query($queryParams);

    $httpClient = $this->getHttpClient();

    $response = $httpClient->request('GET', $url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key'    => $this->apiKey,
        ],
        'timeout' => $this->timeout,
    ]);

    $httpCode = $response->getStatusCode();
    $body     = $response->getBody();

    if ($body === '') {
        throw new NetworkException('Empty response from API');
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new ApiException(
            sprintf('Invalid JSON response: %s', json_last_error_msg()),
            $httpCode
        );
    }

    $this->handleHttpErrors($httpCode, $data);

    return $data;
}
```

### JavaScript AJAX + Retry Pattern
```javascript
// Source: Existing client-links.js pattern

function loadData() {
    if (state.isLoading) return;
    state.isLoading = true;

    showSkeleton();
    hideError();
    hideContent();

    $.ajax({
        url: tpUsageDashboard.ajaxUrl,
        type: 'POST',
        data: {
            action: 'tp_get_usage_summary',
            nonce: tpUsageDashboard.nonce,
            start_date: state.dateStart,
            end_date: state.dateEnd
            // NOTE: no uid -- server determines it
        },
        timeout: 20000,  // 20s JS timeout (API has 15s Lambda timeout)
        success: function(response) {
            state.isLoading = false;
            if (response.success && response.data && response.data.days) {
                state.data = response.data.days;
                hideSkeleton();
                renderContent();
            } else {
                showError(response.data ? response.data.message : tpUsageDashboard.strings.error);
            }
        },
        error: function(xhr, status, error) {
            state.isLoading = false;
            hideSkeleton();
            showError(tpUsageDashboard.strings.error);
        }
    });
}

// Retry button handler (locked decision: re-fetch without page reload)
function bindEvents() {
    $retryBtn.on('click', function() {
        loadData();
    });
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Return empty string for unauth shortcode | Show `wp_login_form()` inline | This phase (user decision) | First shortcode in plugin to show login form; others still return `''` |
| Pass raw API response to frontend | Validate + reshape in proxy | This phase (user decision) | Adds a validation layer that strips unexpected fields |
| `wp_remote_get()` for HTTP calls | `CurlHttpClient` via `HttpClientInterface` | Established in codebase | All API calls use the existing cURL-based HTTP client; do not introduce `wp_remote_get()` |

**Deprecated/outdated:**
- None relevant. The WordPress APIs used (`add_shortcode`, `wp_ajax_`, `wp_login_form`, `wp_create_nonce`) have been stable for 10+ years.

## API Details (Critical for Implementation)

### External API: `GET /user-activity-summary/{uid}`

**Endpoint:** `{TP_API_ENDPOINT}/user-activity-summary/{uid}`
**Auth:** `x-api-key` header (same key used by all `TrafficPortalApiClient` methods)
**Query params:** `start_date` (YYYY-MM-DD), `end_date` (YYYY-MM-DD) -- both optional
**Lambda timeout:** 15 seconds (from API_REFERENCE.md)

**Response shape:**
```json
{
  "message": "Activity summary retrieved",
  "success": true,
  "source": [
    {
      "date": "2025-07-30",
      "totalHits": 5,
      "hitCost": -0.5,
      "balance": -0.5
    }
  ]
}
```

**Critical notes:**
- The data array is in `source`, not `data` or `days`
- `hitCost` and `balance` are floats (can be negative)
- `totalHits` is an integer
- `date` is YYYY-MM-DD format
- No authentication required (besides the shared `x-api-key`)
- The UID in the URL path is the WordPress user ID from `get_current_user_id()`
- Empty date range returns all data; date range filtering is server-side

### Response Validation Contract

The proxy should validate and reshape as follows:

| API field | Type check | Normalization | Frontend field |
|-----------|-----------|---------------|----------------|
| `date` | string, non-empty | `sanitize_text_field()` | `date` |
| `totalHits` | numeric | cast to `(int)` | `totalHits` |
| `hitCost` | numeric | cast to `(float)` | `hitCost` |
| `balance` | numeric | cast to `(float)` | `balance` |

Records missing `date` or `totalHits` are silently skipped. The proxy returns `{ days: [...] }` to the frontend (unwrapping the API's `source` key and renaming it for clarity).

## Proxy Timeout Recommendation (Claude's Discretion)

**Recommended value: 15 seconds**

Rationale:
- External API Lambda timeout: 15 seconds (documented in API_REFERENCE.md)
- Existing `TrafficPortalApiClient` default: 30 seconds (too long for user-facing request)
- WordPress VIP guidance: "timeout greater than 3 seconds is strongly discouraged" for page-blocking requests, but AJAX requests don't block page rendering
- The AJAX call happens after page load, so a 15-second timeout is acceptable
- JS-side timeout: 20 seconds (to allow the server 15s + network overhead)

Implementation: Pass `15` as the timeout to the new `getUserActivitySummary()` method, or use the client's existing timeout.

## Skeleton Layout Recommendation (Claude's Discretion)

Three states the template must support:

1. **Loading (skeleton):** Visible on initial page load. Animated CSS pulse placeholders for chart area and table rows. No spinner -- skeleton screens are preferred for perceived performance.

2. **Error + Retry:** Visible when AJAX fails. Friendly message + "Retry" button. Admins additionally see error type.

3. **Content:** Visible when data loads successfully. Chart canvas, summary stats strip, stats table, date range controls.

The skeleton should approximately match the final content layout so there is minimal layout shift when data arrives.

## AJAX Nonce and Security Recommendation (Claude's Discretion)

**Reuse the existing nonce:** `tp_link_shortener_nonce`

Rationale: All existing AJAX handlers use this same nonce name. Using a separate nonce would require generating multiple nonces on pages that have both the usage dashboard and link management shortcodes. WordPress nonces are not truly "number used once" -- they are valid for 12-24 hours and are user+action specific.

Security layers for this endpoint:
1. `check_ajax_referer('tp_link_shortener_nonce', 'nonce')` -- verifies request origin
2. `is_user_logged_in()` check -- enforces authentication
3. `TP_Link_Shortener::get_user_id()` -- determines UID server-side
4. `wp_ajax_nopriv_*` hook points to `ajax_require_login()` -- clean 401 for unauthenticated AJAX

No additional security measures needed for this endpoint. It reads billing data (not destructive) and the UID is never client-supplied.

## Open Questions

1. **API query parameter names: `start_date`/`end_date` vs `start`/`end`**
   - What we know: API_REFERENCE.md documents them as `start_date` and `end_date`. The architecture research file uses `start` and `end`.
   - What's unclear: Which exact query parameter names the API accepts.
   - Recommendation: Use `start_date` and `end_date` as documented in API_REFERENCE.md (the authoritative source). The `get-usage.sh` script also uses `start_date` and `end_date`.

2. **API response when no data exists for date range**
   - What we know: The API returns `{ message, success: true, source: [] }` with an empty array.
   - What's unclear: Whether it returns 404 or 200 with empty source. The `getUserMapItems` method handles 404 via `PageNotFoundException`.
   - Recommendation: Treat empty `source` array as valid response (no data for range). Let the JS display a "no data" message. Don't throw an exception for empty results.

## Sources

### Primary (HIGH confidence)
- `includes/class-tp-client-links-shortcode.php` -- canonical shortcode pattern with auth gate, asset enqueue, template include, `wp_localize_script`
- `includes/class-tp-dashboard-shortcode.php` -- second canonical shortcode pattern
- `includes/class-tp-api-handler.php` -- AJAX handler registration pattern, nonce verification, UID server-side resolution, error handling pattern
- `includes/TrafficPortal/TrafficPortalApiClient.php` -- API client method pattern (`getUserMapItems` as reference), HTTP client usage, error handling via `handleHttpErrors()`
- `includes/TrafficPortal/Http/CurlHttpClient.php` -- HTTP client implementation, `request()` method signature
- `API_REFERENCE.md` -- authoritative API shape for `GET /user-activity-summary/{uid}`
- `get-usage.sh` -- confirms API query parameter names are `start_date` and `end_date`
- `tp-link-shortener.php` -- plugin entry, `require_once` pattern
- `includes/class-tp-link-shortener.php` -- plugin singleton, component registration in `init()`
- `.planning/research/ARCHITECTURE.md` -- previously researched architecture patterns, data flow, naming conventions (HIGH confidence -- direct codebase analysis)

### Secondary (MEDIUM confidence)
- [WordPress Developer Reference - wp_login_form()](https://developer.wordpress.org/reference/functions/wp_login_form/) -- function signature, parameters, `echo => false` usage
- [WordPress Nonces API](https://developer.wordpress.org/apis/security/nonces/) -- nonce lifecycle (12-24 hour validity)
- [WordPress VIP - Retrieving Remote Data](https://docs.wpvip.com/technical-references/code-quality-and-best-practices/retrieving-remote-data/) -- timeout guidance for remote API calls

### Tertiary (LOW confidence)
- None. All findings verified against primary sources.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all libraries and patterns already exist in the codebase
- Architecture: HIGH -- follows established shortcode/AJAX/API-client pattern exactly; verified against 3 existing implementations
- Pitfalls: HIGH -- all pitfalls derived from direct codebase patterns and WordPress API documentation
- API integration: HIGH -- API endpoint and response shape verified against API_REFERENCE.md and get-usage.sh
- wp_login_form() usage: MEDIUM -- verified against official docs but not yet tested in this specific shortcode context

**Research date:** 2026-02-22
**Valid until:** 2026-03-22 (stable domain -- WordPress APIs and codebase patterns change slowly)
