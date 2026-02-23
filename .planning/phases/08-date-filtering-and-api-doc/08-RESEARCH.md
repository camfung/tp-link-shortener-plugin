# Phase 8: Date Filtering and API Doc - Research

**Researched:** 2026-02-22
**Domain:** HTML5 date inputs, date preset buttons, date validation, API requirements documentation -- all within existing jQuery IIFE + Bootstrap design system
**Confidence:** HIGH

## Summary

Phase 8 adds interactive date filtering to the existing usage dashboard and produces an API requirements document for the backend team. The date filtering work is primarily a JavaScript feature -- wiring up the already-existing HTML date inputs and Apply button (placed in Phase 5's template) to the already-existing `loadData()` AJAX function (built in Phase 5/6), plus adding preset buttons (7d, 30d, 90d) that don't exist yet. The template already has `#tp-ud-date-start`, `#tp-ud-date-end`, and `#tp-ud-date-apply` elements; they just aren't wired to any event handlers. The state already tracks `dateStart` and `dateEnd` and passes them to the AJAX call. The missing pieces are: (1) setting the date input values on page load, (2) binding the Apply button to update state and reload, (3) adding preset buttons with click handlers, (4) enforcing the `max` attribute on the end date input to prevent future dates, and (5) updating the chart when data changes (depends on Phase 7's chart infrastructure).

The API doc is a standalone Markdown document specifying what the backend team needs to build. The current API endpoint `GET /user-activity-summary/{uid}` returns `{ source: [{ date, totalHits, hitCost, balance }] }`. It has no clicks vs QR scans breakdown, no "other services" column, and no wallet transaction data. The document will specify three backend changes: (1) adding `clicks` and `qrScans` fields to the daily summary response, (2) a new endpoint or fields for "other services" charges, and (3) wallet transaction integration. There is also a `by-source` endpoint at `/user-activity-summary/{uid}/by-source` that may already contain some source breakdown data -- this needs to be documented as the path for real clicks/QR split.

**Primary recommendation:** Wire event handlers in `usage-dashboard.js` for the existing date inputs and Apply button, add preset buttons to the template, enforce `max` date attribute via JS, and write a standalone `docs/API-REQUIREMENTS-V2.md` document. Two plans: one for date filtering (JS + template + CSS), one for the API doc.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| jQuery | 3.x (WP bundled) | Event binding, DOM manipulation, AJAX | Already used in usage-dashboard.js IIFE |
| Bootstrap | 5.3.0 (CDN) | Button styling for preset buttons, form-control for date inputs | Already enqueued by Phase 5 shortcode |
| Font Awesome | 6.4.0 (CDN) | Calendar icon, check icon on Apply button | Already enqueued by Phase 5 shortcode |
| HTML5 `<input type="date">` | Native | Date picker UI with `min`/`max` constraints | Already in template; native browser date picker is sufficient |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `Date` (JS built-in) | ES5+ | Date arithmetic for preset calculations (subtract 7/30/90 days from today) | For preset button click handlers |
| `HTMLInputElement.max` | HTML5 | Prevent selecting future dates on end-date input | Set via JS on page load |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Native `<input type="date">` | Flatpickr or other date picker library | Native date inputs are already in the template and working; adding a library is unnecessary complexity for this use case. The only downside is inconsistent cross-browser styling, but the codebase already uses native date inputs in client-links without issues |
| Preset buttons (separate buttons) | Dropdown/select for presets | Buttons are more visible and one-click; the requirements explicitly say "Preset buttons (7d, 30d, 90d)" |
| JS `max` attribute enforcement | Server-side validation only | Both are needed. JS `max` prevents the UI from allowing future dates (TABLE-05 requirement), and the server already validates date format |

## Architecture Patterns

### Recommended Project Structure
```
assets/
    css/
        usage-dashboard.css   # MODIFY - add preset button styles, active state styles
    js/
        usage-dashboard.js    # MODIFY - add date event handlers, preset logic, max enforcement

templates/
    usage-dashboard-template.php  # MODIFY - add preset buttons to date header

docs/
    API-REQUIREMENTS-V2.md    # NEW - backend API requirements document
```

Three files modified, one file created.

### Pattern 1: Date Input Initialization and Apply Handler (from client-links.js)

**What:** On init, populate date inputs from state defaults, bind Apply button to read input values into state and re-fetch data.

**When to use:** The exact same pattern as client-links.js lines 92-96 and 241-247.

**Example:**
```javascript
// In init/cacheElements -- populate inputs from server defaults
$dateStart.val(state.dateStart);
$dateEnd.val(state.dateEnd);

// Set max on end-date to prevent future selection (TABLE-05)
$dateEnd.attr('max', new Date().toISOString().split('T')[0]);
$dateStart.attr('max', new Date().toISOString().split('T')[0]);

// Apply button handler
$dateApply.on('click', function() {
    var newStart = $dateStart.val();
    var newEnd = $dateEnd.val();

    // Basic validation: start <= end
    if (newStart && newEnd && newStart > newEnd) {
        // Swap or show error
        return;
    }

    state.dateStart = newStart;
    state.dateEnd = newEnd;
    state.currentPage = 1;
    loadData();
});
```

### Pattern 2: Preset Buttons with Date Arithmetic

**What:** Buttons that calculate a date range relative to today and set both the input values and state, then trigger data reload.

**When to use:** The 7d, 30d, 90d preset buttons.

**Example:**
```javascript
// Preset button handler (delegated for future-proofing)
$(document).on('click', '.tp-ud-preset-btn', function() {
    var days = parseInt($(this).data('days'));
    var today = new Date();
    var start = new Date();
    start.setDate(today.getDate() - days);

    // Format as YYYY-MM-DD
    var endStr = formatDateISO(today);
    var startStr = formatDateISO(start);

    $dateStart.val(startStr);
    $dateEnd.val(endStr);
    state.dateStart = startStr;
    state.dateEnd = endStr;
    state.currentPage = 1;

    // Update active state on preset buttons
    $('.tp-ud-preset-btn').removeClass('active');
    $(this).addClass('active');

    loadData();
});

function formatDateISO(date) {
    var y = date.getFullYear();
    var m = String(date.getMonth() + 1).padStart(2, '0');
    var d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
}
```

### Pattern 3: Chart Re-render on Date Change

**What:** After `loadData()` succeeds with new data, the chart must be destroyed and recreated with the new data. This depends on Phase 7's chart infrastructure.

**When to use:** Every time `loadData()` completes successfully.

**Note:** Phase 7 will establish the chart rendering function (e.g., `renderChart(data)`) and canvas lifecycle management (destroy before re-create). Phase 8 simply calls that function in the `loadData()` success callback -- the same place that already calls `renderSummaryCards(state.data)` and `renderTable()`. No chart code needs to be written in Phase 8; Phase 7 handles all chart internals.

### Anti-Patterns to Avoid
- **Re-implementing date arithmetic:** Use `Date.setDate()` for subtracting days -- don't manually subtract milliseconds or handle month/year boundaries
- **Forgetting to update both state AND inputs:** Preset buttons must update both `state.dateStart`/`state.dateEnd` AND the visible `$dateStart`/`$dateEnd` input values -- otherwise the UI shows stale dates
- **Not resetting pagination on date change:** Must set `state.currentPage = 1` before reloading (same pattern as sort/filter changes in Phase 6)
- **Blocking future dates only on end-date:** Both start and end dates should have `max` set to today -- a user shouldn't be able to set a start date in the future either

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date formatting to YYYY-MM-DD | String concatenation with manual zero-padding | A small `formatDateISO()` helper using `padStart` | Avoids bugs with single-digit months/days; one central function |
| Date picker UI | Custom calendar widget | Native `<input type="date">` | Already in template, browser-native, no extra JS/CSS needed |
| Date validation (start <= end) | Complex validation framework | Simple string comparison (YYYY-MM-DD strings sort correctly lexicographically) | ISO date strings compare correctly with `<` and `>` operators |
| Active button state tracking | Manual class toggling per button | Simple `$('.tp-ud-preset-btn').removeClass('active'); $(this).addClass('active');` | jQuery makes this trivial |

**Key insight:** Date filtering is fundamentally just "update two state values and call loadData()" -- the infrastructure is already built. The only net-new code is event handlers, preset button HTML, and the `max` attribute enforcement.

## Common Pitfalls

### Pitfall 1: Timezone Offset in Date Calculations
**What goes wrong:** `new Date().toISOString().split('T')[0]` returns UTC date, which can be "tomorrow" or "yesterday" depending on user's timezone.
**Why it happens:** `toISOString()` always returns UTC time, not local time.
**How to avoid:** Use `getFullYear()`, `getMonth()`, `getDate()` on the local Date object to construct the YYYY-MM-DD string manually. Never use `toISOString()` for user-facing local dates.
**Warning signs:** End date showing as "tomorrow" for users in UTC+ timezones, or "yesterday" for UTC- timezones.

### Pitfall 2: Preset Button Active State Desync
**What goes wrong:** Preset button stays highlighted even after user manually changes the date inputs, making it look like the preset is still active when it's not.
**Why it happens:** No handler clears the active preset state when the date inputs change.
**How to avoid:** On manual date input change (or Apply button click without matching a preset), remove `active` class from all preset buttons. Only add `active` when a preset button is clicked.
**Warning signs:** 30d button stays highlighted but the dates show a custom range.

### Pitfall 3: End Date Max Attribute Becomes Stale
**What goes wrong:** If user leaves the page open overnight, the `max` attribute still reflects yesterday's date, preventing them from selecting today.
**Why it happens:** `max` is set once on page load and never updated.
**How to avoid:** This is a very minor edge case. Setting `max` on page load is sufficient for v1.0. If needed in the future, update `max` in the Apply handler. For Phase 8, set it once on page load.
**Warning signs:** User complaints about not being able to select "today" after midnight.

### Pitfall 4: Empty Date Input Submission
**What goes wrong:** User clears a date input and clicks Apply, sending an empty string to the API.
**Why it happens:** No validation on the Apply handler to check for empty date values.
**How to avoid:** In the Apply handler, check that both date values are non-empty before updating state and calling loadData(). If empty, either ignore the click or restore the previous values.
**Warning signs:** AJAX call fails with "Invalid date format" error from the PHP handler's regex validation.

### Pitfall 5: Chart Not Re-rendering After Date Change
**What goes wrong:** Table and summary cards update but chart shows old data.
**Why it happens:** Phase 8 developer forgets to call the chart render function after loadData succeeds.
**How to avoid:** The `loadData()` success callback already has a clear pattern: `renderSummaryCards(state.data); renderTable();`. The chart render call (from Phase 7) should go in the same location. Verify it's there during Phase 8.
**Warning signs:** Changing dates updates table numbers but chart lines don't move.

## Code Examples

Verified patterns from the existing codebase:

### Date State Initialization (from client-links.js:92-96)
```javascript
// Source: assets/js/client-links.js lines 92-96
state.dateStart = tpClientLinks.dateRange.start;
state.dateEnd = tpClientLinks.dateRange.end;
$dateStart.val(state.dateStart);
$dateEnd.val(state.dateEnd);
```

The usage dashboard already does step 1 (state init from localized config) in `usage-dashboard.js:14-15`:
```javascript
dateStart: tpUsageDashboard.dateRange.start,
dateEnd: tpUsageDashboard.dateRange.end,
```
But it does NOT do step 2 (populating the input elements). Phase 8 adds the `val()` calls.

### Apply Button Handler (from client-links.js:241-247)
```javascript
// Source: assets/js/client-links.js lines 241-247
$dateApply.on('click', function() {
    state.dateStart = $dateStart.val();
    state.dateEnd = $dateEnd.val();
    state.currentPage = 1;
    loadData();
});
```

### PHP Date Max from Server (from class-tp-usage-dashboard-shortcode.php:102-104)
```php
// Source: includes/class-tp-usage-dashboard-shortcode.php lines 102-104
$days = intval($atts['days']);
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime("-{$days} days"));
```

The `$end_date` is already set to today server-side. Phase 8 uses this in the localized JS object to set the `max` attribute on the input elements.

### Template Preset Buttons (NET NEW)
```php
<!-- Preset buttons alongside existing date inputs -->
<div class="tp-ud-presets">
    <button class="btn btn-sm btn-outline-secondary tp-ud-preset-btn" data-days="7">7d</button>
    <button class="btn btn-sm btn-outline-secondary tp-ud-preset-btn" data-days="30">30d</button>
    <button class="btn btn-sm btn-outline-secondary tp-ud-preset-btn" data-days="90">90d</button>
</div>
```

### API Requirements Document Structure (NET NEW)
The API doc follows the pattern of existing docs in `docs/` (e.g., `GENERATE_SHORT_CODE_API.md`, `SCREENSHOT_API.md`) -- plain Markdown with endpoint specs, request/response examples, and field descriptions.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Date inputs exist but unconnected | Phase 8 wires them to AJAX | Phase 8 | Users can filter by date range |
| No preset buttons | Phase 8 adds 7d/30d/90d | Phase 8 | One-click date selection |
| No `max` attribute on date inputs | Phase 8 enforces max=today | Phase 8 | TABLE-05 requirement met |
| Mock click/QR split (70/30) | API doc specifies path to real data | Phase 8 (DOC-01) | Backend team has requirements |

**Deprecated/outdated:**
- Nothing in this phase is replacing deprecated technology. HTML5 date inputs are stable and well-supported across modern browsers.

## Existing Infrastructure Inventory

### Already Built (Phase 5/6) -- Available for Phase 8

| Element | Location | What It Does | Phase 8 Usage |
|---------|----------|--------------|---------------|
| `#tp-ud-date-start` | template line 81 | Date input element | Wire to event handler, set `max`, set initial `val()` |
| `#tp-ud-date-end` | template line 83 | Date input element | Wire to event handler, set `max`, set initial `val()` |
| `#tp-ud-date-apply` | template line 84-86 | Apply button | Wire click handler |
| `state.dateStart` | JS line 14 | State property initialized from server | Already working |
| `state.dateEnd` | JS line 15 | State property initialized from server | Already working |
| `loadData()` | JS line 397-454 | AJAX fetch with `start_date`/`end_date` | Already sends dates from state |
| `tpUsageDashboard.dateRange` | shortcode PHP line 111-114 | Server-calculated defaults (30 days) | Already localized to JS |
| `renderTable()` | JS line 221-243 | Re-renders table from state.data | Called after loadData succeeds |
| `renderSummaryCards()` | JS line 359-381 | Re-renders summary cards | Called after loadData succeeds |

### NOT Built Yet -- Phase 8 Must Add

| Element | What's Missing |
|---------|---------------|
| Input `val()` initialization | Date inputs never receive their initial values from state |
| Apply button event handler | No click handler bound |
| Preset buttons (7d/30d/90d) | Not in template HTML at all |
| Preset button click handlers | No JS for presets |
| `max` attribute on date inputs | Inputs allow any date, including future |
| Date validation (start <= end) | No client-side validation |
| Active state for preset buttons | No visual indicator of which preset is active |
| Chart re-render call | Phase 7 will add the chart; Phase 8 ensures date changes trigger chart update |
| API requirements doc | Does not exist yet |

## API Documentation Research

### Current API Endpoints (from `get-usage.sh` and `TrafficPortalApiClient.php`)

| Endpoint | Method | Current Response | What's Missing |
|----------|--------|------------------|----------------|
| `/user-activity-summary/{uid}?start_date=X&end_date=Y` | GET | `{ source: [{ date, totalHits, hitCost, balance }] }` | No `clicks` vs `qrScans` breakdown |
| `/user-activity-summary/{uid}/by-link?start_date=X&end_date=Y` | GET | Unknown response shape | Per-link breakdown (useful for future features) |
| `/user-activity-summary/{uid}/by-source?start_date=X&end_date=Y` | GET | Unknown response shape | Likely has source breakdown -- document this as the path for real click/QR data |

### What the API Doc Must Specify (DOC-01)

1. **Real clicks/QR split** -- Either add `clicks` and `qrScans` fields to the existing daily summary response, or document how to use the `by-source` endpoint to get this breakdown
2. **Other Services data** -- One-time charges like domain renewals, wallet top-ups. Currently not returned by any endpoint. Specify the shape: `{ date, description, amount }` or similar
3. **Wallet transactions** -- Current balance is returned per-day, but there's no way to see top-up events. Specify whether this should be a new endpoint or embedded in the daily summary
4. **Current API authentication** -- Document the `x-api-key` header pattern for completeness

### DOC-01 Scope Boundary

Per the prior decision: "Skip Other Services and second table for v2.0." The API doc SPECIFIES what's needed but does NOT implement it. It's a requirements handoff to the backend team. The document should be clear about what's needed for v2.1+ vs what's already working.

## Open Questions

1. **What does `/user-activity-summary/{uid}/by-source` return?**
   - What we know: The endpoint exists (referenced in `get-usage.sh`)
   - What's unclear: The response shape -- it may already contain click vs QR breakdown data
   - Recommendation: Document in the API requirements doc that this endpoint should be explored. If it already returns source breakdown, the frontend mock split can be replaced with real data without backend changes.

2. **Should preset 30d match the default `days=30` shortcode attribute?**
   - What we know: The shortcode has a `days` attribute defaulting to 30, and the 30d preset calculates 30 days from today
   - What's unclear: Whether they should produce exactly the same date range
   - Recommendation: Yes, make them consistent. Both calculate `today - 30 days` to `today`. The 30d preset should visually highlight as "active" on initial page load since it matches the default.

3. **Chart integration surface area**
   - What we know: Phase 7 will create a chart rendering function. Phase 8 needs to call it after date changes.
   - What's unclear: The exact function name and signature from Phase 7
   - Recommendation: Phase 8 plan should note a dependency on Phase 7's chart render function. The loadData success callback is the integration point -- Phase 8 adds the chart render call alongside the existing `renderSummaryCards()` and `renderTable()` calls.

## Sources

### Primary (HIGH confidence)
- `assets/js/usage-dashboard.js` -- Current JS with state, loadData, rendering functions
- `assets/js/client-links.js` -- Reference implementation for date filtering pattern (lines 92-96, 241-247)
- `templates/usage-dashboard-template.php` -- Current template with date inputs (lines 76-88)
- `includes/class-tp-usage-dashboard-shortcode.php` -- Shortcode with date range localization (lines 101-121)
- `includes/class-tp-api-handler.php` -- AJAX handler with date validation (lines 1605-1722)
- `includes/TrafficPortal/TrafficPortalApiClient.php` -- API client with getUserActivitySummary (lines 777-843)
- `get-usage.sh` -- Shell script revealing three API endpoints including by-link and by-source
- `.planning/REQUIREMENTS.md` -- DATA-05, DATA-06, TABLE-05, DOC-01 requirement definitions

### Secondary (MEDIUM confidence)
- HTML5 date input `max` attribute -- well-documented web standard, supported in all modern browsers
- `Date.prototype.setDate()` -- standard JS date arithmetic, works correctly across month/year boundaries

### Tertiary (LOW confidence)
- `/user-activity-summary/{uid}/by-source` response shape -- only known from the shell script reference; actual response format not verified

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All tools already in the codebase; no new dependencies
- Architecture: HIGH - Pattern directly cloned from client-links.js with minor additions
- Pitfalls: HIGH - Date handling pitfalls are well-known and straightforward to avoid
- API doc: MEDIUM - The `by-source` endpoint response shape is unverified

**Research date:** 2026-02-22
**Valid until:** 2026-03-22 (stable domain; HTML5 date inputs and jQuery are not changing)
