# Stack Research: Billing/Usage Dashboard for WordPress Link Shortener Plugin

**Domain:** WordPress plugin — billing/usage analytics dashboard (shortcode-based)
**Researched:** 2026-02-22
**Confidence:** HIGH

## Context: What Already Exists (Do Not Re-add)

The plugin already loads these on every shortcode page — they are available for free to the new `[tp_usage_dashboard]` shortcode:

| Technology | Version | How Loaded | Notes |
|------------|---------|-----------|-------|
| Bootstrap CSS | 5.3.0 | CDN (`tp-bootstrap` handle) | Grid, utilities, badges |
| Bootstrap JS | 5.3.0 | CDN (`tp-bootstrap-js` handle) | Bundle includes Popper |
| Font Awesome | 6.4.0 | CDN (`tp-fontawesome` handle) | Icons throughout |
| jQuery | WP-bundled | WP core | Available as dependency |
| Chart.js | 4.4.1 | CDN (`tp-chartjs` handle in client-links) | Area/line chart capable |
| frontend.css | Plugin local | CSS custom properties, design tokens | `--tp-primary`, `--tp-border`, etc. |
| Poppins font | Google Fonts (via frontend.css) | Typography | Already declared |

The `[tp_usage_dashboard]` shortcode file will be `class-tp-usage-dashboard-shortcode.php`, matching the established pattern (`class-tp-dashboard-shortcode.php`, `class-tp-client-links-shortcode.php`).

---

## Recommended Stack

### Core Technologies (Existing — Reuse)

| Technology | Version | Purpose in Billing Dashboard | Why Reuse |
|------------|---------|------------------------------|-----------|
| Chart.js | 4.4.1 | Area chart showing daily clicks + QR scans over time | Already loaded; `type: 'line'` with `fill: 'origin'` is the area chart mode — no new library needed |
| Bootstrap 5 | 5.3.0 | Table layout, badges for balance status, responsive grid | Already loaded; `.table`, `.badge`, `.card` classes cover all billing UI needs |
| jQuery | WP-bundled | AJAX call to fetch API data, DOM update on date filter change | Existing AJAX pattern throughout codebase — maintain consistency |
| CSS Custom Properties | Plugin-defined | `--tp-primary` (yellow), `--tp-success` (green) for chart colors | Design system already established; chart colors must match |
| `wp_localize_script` | WP core | Pass default date range and API config to JS | Exact same pattern as `tpClientLinks` in `class-tp-client-links-shortcode.php` |

### Stack Additions for the Billing Dashboard

**Zero new JavaScript libraries are needed.** One CSS pattern is new to this feature.

| Addition | Type | Purpose | Why This and Not Alternative |
|----------|------|---------|------------------------------|
| Native `<input type="date">` | HTML — no library | Date range start/end filter inputs | Already used in `client-links-template.php` as `#tp-cl-date-start` and `#tp-cl-date-end` — identical pattern. No flatpickr needed: mobile OS pickers are superior, and the existing plugin has no date picker library. Adding flatpickr (49 KB JS + 16 KB CSS) for two inputs would be unjustified bloat. |
| `class-tp-usage-dashboard-shortcode.php` | PHP — new file | Shortcode class for `[tp_usage_dashboard]` | Follows established naming convention; `enqueue_assets()` method re-uses `tp-chartjs`, `tp-bootstrap`, `tp-fontawesome` handles — no duplicate loads |
| `assets/css/usage-dashboard.css` | CSS — new file | Styles scoped to `.tp-ud-*` prefix | Follows `client-links.css` pattern; prefix `tp-ud-` avoids collisions with `tp-cl-*` and `tp-*` classes |
| `assets/js/usage-dashboard.js` | JS — new file | Chart init, date filter, AJAX fetch, table render | Self-contained IIFE using `var` + jQuery, matching `client-links.js` and `dashboard.js` style — no ES modules, no build step |

### Chart.js Area Chart Configuration

Chart.js 4.4.1 supports area charts natively using `type: 'line'` with `fill: 'origin'` on each dataset. No plugin or extra library is required. The `filler` plugin is built into `chart.umd.min.js`.

Relevant dataset properties for the yellow/green area chart:

```javascript
// Clicks dataset (yellow — mock: 80% of totalHits)
{
    label: 'Clicks',
    data: clicksData,           // array of numbers per day
    borderColor: '#f5a623',     // yellow, matches design mockup
    backgroundColor: 'rgba(245, 166, 35, 0.15)',
    fill: 'origin',             // fills area between line and x-axis
    tension: 0.4,               // smooth curve
    pointRadius: 3,
    pointHoverRadius: 6
}

// QR Scans dataset (green — mock: 20% of totalHits)
{
    label: 'QR Scans',
    data: qrData,               // array of numbers per day
    borderColor: '#22b573',     // green — already used in client-links.js
    backgroundColor: 'rgba(34, 181, 115, 0.12)',
    fill: 'origin',
    tension: 0.4,
    pointRadius: 3,
    pointHoverRadius: 6
}
```

X-axis labels are `date` strings from the API (`YYYY-MM-DD`). The API returns `totalHits` only — clicks/QR split must be mocked client-side (see Mocking Strategy below).

### Mocking Strategy: Clicks vs QR Scans Split

The `GET /user-activity-summary/{uid}` endpoint returns only `totalHits` per day. The `by-source` endpoint returns QR traffic but requires links to be tagged with `?qr=1`. Since this billing dashboard shows account-level totals (not per-link), use this deterministic split:

```javascript
// Applied per daily record in the JS transform:
function splitHits(totalHits) {
    var qr = Math.round(totalHits * 0.2);   // 20% QR assumption
    return {
        clicks: totalHits - qr,              // 80% clicks
        qr: qr
    };
}
```

This is clearly labeled in the UI as "estimated" or with a note. If the `by-source` endpoint becomes viable later (when all QR links use `?qr=1`), the JS function is the only change point.

### Date Range Filter: Native HTML Inputs

The existing `client-links.js` already uses `<input type="date" id="tp-cl-date-start">` + `<input type="date" id="tp-cl-date-end">` + an "Apply" button. The billing dashboard uses the identical pattern:

- Default range: last 30 days (set via `wp_localize_script` in PHP, same as `tpClientLinks.dateRange`)
- No library: native `<input type="date">` opens the OS date picker on mobile (superior UX per flatpickr's own docs)
- Browser support: 97%+ for `input[type="date"]` (HIGH confidence — caniuse.com)

### WordPress AJAX Pattern

The billing dashboard fetches from the external REST API directly from the browser (same as `client-links.js`), NOT through `admin-ajax.php`. The user ID is passed via `wp_localize_script`. Pattern:

```javascript
// In usage-dashboard.js — direct external API fetch
$.ajax({
    url: tpUsageDashboard.apiBase + '/user-activity-summary/' + tpUsageDashboard.uid,
    type: 'GET',
    data: { start_date: state.dateStart, end_date: state.dateEnd },
    success: function(response) { /* render */ },
    error: function() { /* show error state */ }
});
```

`tpUsageDashboard.uid` is the TP API user ID (not WP user ID), passed via `wp_localize_script` in PHP using `TP_Link_Shortener::get_user_id()` — the same pattern used in `class-tp-client-links-shortcode.php`.

---

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| Native `<input type="date">` | flatpickr 4.6.13 | Only if a date range calendar overlay (visual range selection on a month grid) is required. Not needed here: two separate date inputs with an Apply button match the existing UX pattern in client-links. |
| Chart.js `type: 'line'` + `fill: 'origin'` | Chart.js `type: 'bar'` (existing in client-links) | Bar chart is better for per-link comparison. Area chart is better for time-series trends (this dashboard is time-series by day). The area form communicates "accumulation over time." |
| Direct external API fetch in JS | WordPress AJAX proxy (admin-ajax.php) | Use the proxy pattern only if the external API requires a server-side auth token that must not be exposed client-side. The current API has no auth header, so direct fetch is correct. |
| `fill: 'origin'` (non-stacked area) | `stacked: true` + `fill: true` | Stacked mode would make QR scans appear on top of clicks, misrepresenting the data (they overlap, not add). Non-stacked with separate fills is more honest for mocked data. |
| Reuse `tp-chartjs` handle | Load Chart.js 4.5.1 (newer) | Chart.js 4.5.1 exists as of late 2025. Do not upgrade unless there is a specific bug fix needed — the existing `tp-chartjs` handle at 4.4.1 is already loaded by `client-links-shortcode.php` and both shortcodes may appear on the same page. Version conflict would break both. |

---

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| flatpickr | 49 KB JS + 16 KB CSS for functionality already covered by two `<input type="date">` elements. The existing `client-links` UI uses the same native inputs. Introducing flatpickr here but not there creates inconsistency. | Native `<input type="date">` — already the codebase pattern |
| Moment.js / Day.js for date formatting | Date strings from the API are already `YYYY-MM-DD` format. JavaScript `new Date(dateStr)` or string slicing handles any display formatting needed. This feature has zero timezone issues (dates only, not datetimes). | Vanilla JS string operations |
| DataTables.js | The table has a fixed schema (date, clicks, QR, totalHits, cost, balance) and ~30 rows. DataTables adds 84 KB for pagination and sorting that can be done with 20 lines of vanilla JS sort. | Inline JS sort function + `Array.prototype.sort` |
| Chart.js adapter libraries (date-fns adapter, Luxon adapter) | The X-axis labels are pre-formatted date strings from the API. No adapter is needed when using `type: 'category'` scale (Chart.js default for string labels). | `type: 'line'` with `labels: dateArray` as category scale |
| Additional Bootstrap components (modal, offcanvas) | The billing dashboard has no add/edit flow requiring modals. It is read-only: chart + table + date filter. Bootstrap modals would add complexity without value. | None — the dashboard is display-only |
| A separate build tool (webpack, vite) | The plugin has no build pipeline. All JS is plain IIFE. Adding a build step for one new JS file would require updating `package.json` and the deployment workflow. | Plain IIFE following `client-links.js` pattern |

---

## Stack Patterns by Variant

**If the design requires a stacked area chart (QR scans ON TOP of clicks, totaling to totalHits):**
- Use `options.scales.y.stacked: true` and `fill: true` on both datasets
- This requires clicks + QR to always sum to totalHits — compatible with the mock split
- Current recommendation (non-stacked) is safer because mock percentages make the total line irrelevant

**If the `/by-source` API is used instead of mocking:**
- Replace the `splitHits()` function with a fetch to `GET /user-activity-summary/{uid}/by-source`
- Requires a second API call and merging by date
- The JS structure is the same; only the data source changes
- Note: by-source only returns records where `?qr=1` was used — older data will have 0 QR hits even if QR codes were used without the parameter

**If Chart.js is upgraded from 4.4.1 to 4.5.x:**
- Change the CDN URL in `class-tp-client-links-shortcode.php` only (the handle `tp-chartjs` propagates to all consumers)
- Verify `fill: 'origin'` behavior has not changed (it is stable since Chart.js 3.x — HIGH confidence)

---

## Version Compatibility

| Package | Version in Use | Compatible With | Notes |
|---------|---------------|-----------------|-------|
| Chart.js | 4.4.1 | Bootstrap 5.3.0 (no conflict — separate concerns) | `fill: 'origin'` stable since Chart.js 3.x |
| Chart.js | 4.4.1 | jQuery (WP-bundled) | Chart.js is dependency-free; no jQuery conflict |
| Bootstrap 5.3.0 | CSS + JS | Custom CSS with `tp-ud-*` prefix | No specificity conflicts if prefix is used |
| Native `input[type="date"]` | Browser native | Chrome 20+, Firefox 57+, Safari 14.1+, Edge 12+ | 97%+ global support (HIGH confidence) |
| `wp_localize_script` | WP 2.2+ | All modern WordPress versions | Standard — no compat concerns |

---

## Installation

```bash
# No npm packages to install.
# No build step required.
#
# New files to create:
#   includes/class-tp-usage-dashboard-shortcode.php
#   assets/css/usage-dashboard.css
#   assets/js/usage-dashboard.js
#
# Existing file to update:
#   tp-link-shortener.php  (add require_once for new shortcode class)
#
# Handles to reuse (already registered by other shortcodes on the same page):
#   tp-bootstrap, tp-bootstrap-js, tp-fontawesome, tp-link-shortener, tp-chartjs
#   WordPress will not double-load these — wp_enqueue_script/style deduplicates by handle.
```

---

## Sources

- Chart.js official docs (chartjs.org/docs/latest/charts/area.html) — `fill: 'origin'` area chart configuration (HIGH confidence, WebSearch verified)
- Chart.js official docs (chartjs.org/docs/latest/charts/line.html) — `tension`, `pointRadius`, dataset configuration (HIGH confidence)
- jsDelivr CDN (cdn.jsdelivr.net) — Chart.js 4.5.1 exists; plugin currently uses 4.4.1 (MEDIUM confidence via WebSearch)
- flatpickr.js.org — flatpickr file size ~49 KB JS; mobile browser defers to native input automatically (MEDIUM confidence via WebSearch)
- API_REFERENCE.md (in this repo) — `/user-activity-summary/{uid}` returns `totalHits`, `hitCost`, `balance` per day; `by-source` returns QR source data (HIGH confidence — primary source)
- `includes/class-tp-client-links-shortcode.php` (in this repo) — existing enqueue pattern, `tp-chartjs` handle at 4.4.1, native date input pattern (HIGH confidence — direct code inspection)
- `assets/js/client-links.js` (in this repo) — Chart.js bar chart config, IIFE pattern, AJAX pattern (HIGH confidence — direct code inspection)
- MDN Web Docs / caniuse.com — `input[type="date"]` browser support 97%+ (HIGH confidence)

---
*Stack research for: Billing/usage dashboard — WordPress link shortener plugin*
*Researched: 2026-02-22*
