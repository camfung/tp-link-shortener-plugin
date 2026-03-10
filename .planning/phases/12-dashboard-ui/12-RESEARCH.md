# Phase 12: Dashboard UI - Research

**Researched:** 2026-03-10
**Domain:** WordPress usage dashboard frontend (jQuery + Bootstrap 5.3 + vanilla JS)
**Confidence:** HIGH

## Summary

Phase 12 adds wallet credit visibility to an existing usage dashboard. The dashboard is a jQuery-based SPA-style widget using Bootstrap 5.3.0, Font Awesome 6.4, and Chart.js 4.4.1. All data arrives via a single AJAX call (`tp_get_usage_summary`) that already returns merged usage + wallet data from Phase 11. The `otherServices` field on each day record is either `null` (no wallet activity or wallet API failure) or an object with `amount` (float) and `items` (array of `{amount, description}`).

The work is purely frontend: add a column to the HTML table template and JS row renderer, add a 4th summary card, initialize Bootstrap tooltips on cells with wallet data, and adjust CSS column widths. No new AJAX endpoints, no new libraries, no PHP logic changes.

**Primary recommendation:** Modify three files -- `usage-dashboard-template.php` (add column header), `usage-dashboard.js` (render column + summary card + tooltip init), `usage-dashboard.css` (5-column widths + green amount styling). Use Bootstrap 5.3's native Tooltip API with `data-bs-toggle="tooltip"` and `data-bs-html="true"` for multi-line descriptions.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Column order: Date | Hits | **Other Services** | Cost | Balance (before Cost)
- Amounts displayed as +$X.XX in green/success color for days with wallet activity
- Days without wallet activity show $0.00 (plain text, no green, no tooltip)
- Column is sortable, consistent with existing columns (Date, Hits, Cost, Balance)
- Hovering an Other Services amount with wallet activity shows a Bootstrap tooltip with transaction descriptions
- Multiple transactions on same day: each line shows "Description (+$amount)" format
- Single transaction: just the description text
- No tooltip on $0.00 cells (no activity = nothing to describe)
- No visual hover indicator (no dotted underline, no info icon) -- clean look, users discover on hover
- Mobile: tap the amount to toggle Bootstrap tooltip, tap elsewhere to dismiss

### Claude's Discretion
- Summary card design: icon choice, color scheme, label text, secondary text for the 4th stat card
- Column width distribution across the 5 columns (currently 4 columns: 25%/30%/20%/25%)
- Mobile card layout integration for the new column
- Loading skeleton adjustment if needed

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

## Standard Stack

### Core (already in use -- no new dependencies)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Bootstrap | 5.3.0 | CSS framework + Tooltip JS component | Already loaded via CDN (`bootstrap.bundle.min.js` includes Popper) |
| jQuery | WP bundled | DOM manipulation, AJAX, event binding | Existing codebase pattern |
| Font Awesome | 6.4.0 | Icons for stat cards and UI elements | Already loaded via CDN |
| Chart.js | 4.4.1 | Area chart (no changes needed for this phase) | Already loaded |

### Supporting
None needed. All required functionality is available via the existing stack.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Bootstrap Tooltip | Custom tooltip div | Unnecessary -- Bootstrap already loaded, handles positioning, mobile touch, accessibility |
| Separate AJAX call | N/A | Phase 11 already merges data into single response |

**Installation:**
No new packages needed.

## Architecture Patterns

### Current File Structure (relevant files)
```
templates/
  usage-dashboard-template.php   # HTML structure (skeleton + content states)
assets/js/
  usage-dashboard.js             # jQuery SPA: fetch, render, sort, paginate
assets/css/
  usage-dashboard.css            # All styling, including mobile card layout
includes/
  class-tp-usage-dashboard-shortcode.php  # Enqueues assets, renders template
```

### Pattern 1: Row Rendering (existing pattern to extend)
**What:** The `renderRows()` function in `usage-dashboard.js` builds HTML strings for each day record and appends to `$tbody`.
**When to use:** Adding the Other Services column cell to each row.
**Current code (lines 313-337):**
```javascript
function renderRows(pageData) {
    $tbody.empty();
    for (var i = 0; i < pageData.length; i++) {
        var day = pageData[i];
        var split = splitHits(day.totalHits);
        var row = '<tr>' +
            '<td class="tp-ud-col-date" data-label="Date">...</td>' +
            '<td class="tp-ud-col-hits" data-label="Hits">...</td>' +
            '<td class="tp-ud-col-cost" data-label="Cost">...</td>' +
            '<td class="tp-ud-col-balance" data-label="Balance">...</td>' +
        '</tr>';
        $tbody.append(row);
    }
}
```
**Extension point:** Insert new `<td class="tp-ud-col-other" data-label="Other Services">` between Hits and Cost columns.

### Pattern 2: Summary Card Rendering (existing pattern to extend)
**What:** `buildStatCard(icon, value, label, secondary)` builds HTML for stat cards. `renderSummaryCards()` aggregates totals and calls it 3 times.
**When to use:** Adding the 4th "Other Services" summary card.
**Current code (lines 409-447):**
```javascript
function buildStatCard(icon, value, label, secondary) {
    return '<div class="tp-ud-stat-card">' +
        '<div class="tp-ud-stat-icon"><i class="fas ' + icon + '"></i></div>' +
        '<div class="tp-ud-stat-body">' +
            '<div class="tp-ud-stat-value">' + value + '</div>' +
            '<div class="tp-ud-stat-label">' + label + '</div>' +
            '<div class="tp-ud-stat-secondary">' + secondary + '</div>' +
        '</div>' +
    '</div>';
}
```

### Pattern 3: Sorting (existing pattern to extend)
**What:** `getSortedData()` reads `state.sort` field name and direction, sorts the data array. Sort headers use `data-sort` attribute.
**When to use:** Making the Other Services column sortable.
**Extension:** The sort field should be a computed value (the `otherServices.amount` or 0 if null). The sort function already handles numeric comparison; need to add field extraction logic for the nested `otherServices` object.

### Pattern 4: Bootstrap 5.3 Tooltip Initialization
**What:** Bootstrap 5.3 tooltips must be explicitly initialized via JS. They are NOT auto-initialized.
**When to use:** After rendering rows that contain tooltip-enabled cells.
**Bootstrap 5.3 pattern:**
```javascript
// Initialize all tooltips in the table body
var tooltipTriggerList = [].slice.call(
    document.querySelectorAll('#tp-ud-tbody [data-bs-toggle="tooltip"]')
);
tooltipTriggerList.forEach(function(el) {
    new bootstrap.Tooltip(el);
});
```
**Critical:** Must re-initialize after every `renderRows()` call (pagination, sort changes). Must dispose old tooltips before clearing tbody to prevent memory leaks.

### Anti-Patterns to Avoid
- **Initializing tooltips before DOM is ready:** Bootstrap Tooltip needs the element to exist in DOM. Initialize AFTER `$tbody.append()`.
- **Not disposing tooltips before re-render:** Calling `$tbody.empty()` without disposing tooltip instances causes orphaned Popper elements. Dispose first.
- **Using `title` attribute directly with HTML:** Must use `data-bs-html="true"` and `data-bs-title` for multi-line HTML tooltip content.
- **Floating-point accumulation for Other Services total:** Use the same integer-cents pattern as existing `renderSummaryCards()` (`Math.round(value * 100)`).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Tooltip positioning | Custom absolute-positioned div | `bootstrap.Tooltip` | Already loaded, handles viewport edges, touch devices, z-index, accessibility |
| Currency formatting | New formatter | Existing `formatCurrency()` | Already handles rounding, negative signs, cent precision |
| Sort logic | New sort handler | Existing `getSortedData()` pattern | Just need field extraction for nested otherServices.amount |

**Key insight:** This phase should produce minimal new code. The existing patterns for row rendering, summary cards, sorting, and pagination just need extending -- not replacing.

## Common Pitfalls

### Pitfall 1: Tooltip Memory Leak on Re-render
**What goes wrong:** Each `renderTable()` call clears `$tbody` and re-renders rows. If Bootstrap Tooltip instances are not disposed, they leak (orphaned Popper.js instances remain in memory).
**Why it happens:** `$tbody.empty()` removes DOM elements but does not call Bootstrap's `dispose()` on tooltip instances.
**How to avoid:** Before `$tbody.empty()`, iterate existing tooltips and dispose them:
```javascript
$tbody.find('[data-bs-toggle="tooltip"]').each(function() {
    var tooltip = bootstrap.Tooltip.getInstance(this);
    if (tooltip) tooltip.dispose();
});
```
**Warning signs:** Tooltips appearing in wrong positions after sorting/pagination, increasing memory usage.

### Pitfall 2: Sorting on Nested otherServices Field
**What goes wrong:** The existing sort comparator does `a[field]` / `b[field]` which returns the object `{amount, items}` not a number.
**Why it happens:** `otherServices` is an object or null, not a primitive.
**How to avoid:** Add special handling in `getSortedData()` for the `otherServices` sort field -- extract `.amount` (or 0 if null) before comparison. Use a new sort key like `otherServicesAmount` or handle `otherServices` as a special case in the comparator.

### Pitfall 3: null vs Object Check for otherServices
**What goes wrong:** Rendering code crashes with `Cannot read property 'amount' of null`.
**Why it happens:** `otherServices` is `null` for days without wallet activity AND for all days when wallet API fails (Phase 11 fallback).
**How to avoid:** Always check `day.otherServices !== null && day.otherServices !== undefined` before accessing `.amount` or `.items`. Default to 0 / empty display.

### Pitfall 4: HTML Injection via Tooltip Content
**What goes wrong:** Wallet transaction descriptions could contain HTML/script content.
**Why it happens:** Description text comes from the TerrWallet API.
**How to avoid:** The backend already sanitizes via `wp_strip_all_tags()` on the WalletTransaction DTO (Phase 9 decision). But as defense-in-depth, escape the description text client-side when building tooltip HTML. Use a simple text escaping function before inserting into `data-bs-title`.

### Pitfall 5: Skeleton and Mobile Layout Mismatch
**What goes wrong:** Loading skeleton shows 4 columns but live table shows 5.
**Why it happens:** Skeleton table in template HTML is not updated to match new column count.
**How to avoid:** Add a 5th `<td>` to each skeleton row and a 5th `<th>` to the skeleton table header. Also add the new column class to the mobile card layout CSS reset.

## Code Examples

### Rendering an Other Services Cell
```javascript
// Build the Other Services cell HTML for a single day record
function buildOtherServicesCell(day) {
    var os = day.otherServices;

    // No wallet data: show $0.00 plain
    if (!os || os.amount === 0) {
        return '<td class="tp-ud-col-other" data-label="Other Services">' +
            '<span class="tp-ud-other-zero">$0.00</span>' +
        '</td>';
    }

    // Has wallet data: show green +$X.XX with tooltip
    var tooltipHtml = buildTooltipContent(os.items);
    return '<td class="tp-ud-col-other" data-label="Other Services">' +
        '<span class="tp-ud-other-amount" ' +
            'data-bs-toggle="tooltip" ' +
            'data-bs-html="true" ' +
            'data-bs-title="' + escapeAttr(tooltipHtml) + '">' +
            '+' + formatCurrency(os.amount) +
        '</span>' +
    '</td>';
}
```

### Building Multi-line Tooltip Content
```javascript
// Build tooltip HTML from items array
function buildTooltipContent(items) {
    if (items.length === 1) {
        return escapeHtml(items[0].description);
    }
    return items.map(function(item) {
        return escapeHtml(item.description) + ' (+' + formatCurrency(item.amount) + ')';
    }).join('<br>');
}
```

### Tooltip Lifecycle (init + dispose)
```javascript
// Call after renderRows() in renderTable()
function initTooltips() {
    var tooltipEls = document.querySelectorAll('#tp-ud-tbody [data-bs-toggle="tooltip"]');
    [].slice.call(tooltipEls).forEach(function(el) {
        new bootstrap.Tooltip(el, {
            trigger: 'hover focus',  // hover for desktop, focus for mobile tap
            placement: 'top',
            container: 'body'        // prevents tooltip clipping by table overflow
        });
    });
}

// Call before $tbody.empty() in renderRows()
function disposeTooltips() {
    $tbody.find('[data-bs-toggle="tooltip"]').each(function() {
        var tt = bootstrap.Tooltip.getInstance(this);
        if (tt) tt.dispose();
    });
}
```

### Summary Card for Other Services (Claude's Discretion recommendation)
```javascript
// Recommended 4th card design
// Icon: fa-hand-holding-dollar (money being received -- fits "credits")
// Label: "Other Services"
// Secondary: number of days with activity
var otherServicesDays = 0;
var otherServicesCents = 0;
for (var i = 0; i < data.length; i++) {
    if (data[i].otherServices && data[i].otherServices.amount > 0) {
        otherServicesDays++;
        otherServicesCents += Math.round(data[i].otherServices.amount * 100);
    }
}
var otherServicesTotal = otherServicesCents / 100;
html += buildStatCard(
    'fa-hand-holding-dollar',
    '+' + formatCurrency(otherServicesTotal),
    'Other Services',
    otherServicesDays + ' day' + (otherServicesDays !== 1 ? 's' : '') + ' with credits'
);
```

### Recommended 5-Column Width Distribution
```css
/* 5-column layout (was 4: 25%/30%/20%/25%) */
.tp-ud-col-date    { width: 20%; min-width: 100px; }
.tp-ud-col-hits    { width: 25%; min-width: 120px; }
.tp-ud-col-other   { width: 18%; min-width: 100px; }
.tp-ud-col-cost    { width: 17%; min-width: 85px; }
.tp-ud-col-balance { width: 20%; min-width: 95px; }
```

### Green Amount Styling
```css
.tp-ud-other-amount {
    color: var(--bs-success, #198754);  /* Bootstrap 5 success green */
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: .875rem;
    font-weight: 600;
    cursor: default;
}

.tp-ud-other-zero {
    color: var(--tp-muted);
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: .875rem;
}
```

## Data Shape Reference

The AJAX response `response.data.days` array contains objects with this shape (from Phase 10/11):

```javascript
// Day WITH wallet activity:
{
    "date": "2026-03-05",
    "totalHits": 42,
    "hitCost": 0.84,
    "balance": 12.50,
    "otherServices": {
        "amount": 7.50,          // aggregated total for the day
        "items": [
            { "amount": 5.00, "description": "Referral bonus" },
            { "amount": 2.50, "description": "Store refund" }
        ]
    }
}

// Day WITHOUT wallet activity:
{
    "date": "2026-03-06",
    "totalHits": 15,
    "hitCost": 0.30,
    "balance": 12.20,
    "otherServices": null       // null, not absent
}

// ALL days when wallet API fails (GRACE-01):
{
    "date": "2026-03-06",
    "totalHits": 15,
    "hitCost": 0.30,
    "balance": 12.20,
    "otherServices": null       // null on every record
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Bootstrap 4 `$(el).tooltip()` (jQuery) | Bootstrap 5 `new bootstrap.Tooltip(el)` (vanilla JS) | Bootstrap 5.0 (2021) | Must use `bootstrap.Tooltip` not jQuery plugin |
| `title` attribute for tooltips | `data-bs-title` attribute | Bootstrap 5.3 | `title` still works but `data-bs-title` prevents browser native tooltip flash |

**Deprecated/outdated:**
- jQuery-based tooltip init (`$('[data-toggle]').tooltip()`): Bootstrap 5 dropped jQuery dependency. Use `bootstrap.Tooltip` constructor.
- `data-toggle` attribute: Changed to `data-bs-toggle` in Bootstrap 5.

## Open Questions

1. **Summary card positioning**
   - What we know: 3 existing cards are Total Hits, Total Cost, Balance. New card is Other Services.
   - What's unclear: Should the 4th card go after Balance (end of strip) or between Cost and Balance?
   - Recommendation: Place after Balance (4th position) -- it's additive information, not part of the Hits > Cost > Balance flow.

2. **Empty Other Services column when wallet never had data**
   - What we know: All rows show `otherServices: null` when wallet API fails or when user has zero transactions.
   - What's unclear: Should the column still appear? The CONTEXT.md says "show $0.00" for days without activity.
   - Recommendation: Always show the column. All cells show $0.00 when all null. Summary card shows +$0.00.

## Sources

### Primary (HIGH confidence)
- **Codebase inspection** -- `usage-dashboard.js` (current row rendering, sort, summary card patterns)
- **Codebase inspection** -- `usage-dashboard.css` (current column widths, mobile card layout, stat card styles)
- **Codebase inspection** -- `usage-dashboard-template.php` (table structure, skeleton, column headers)
- **Codebase inspection** -- `UsageMergeAdapter.php` (exact otherServices data shape: `{amount, items[{amount, description}]}`)
- **Codebase inspection** -- `class-tp-usage-dashboard-shortcode.php` (Bootstrap 5.3.0 bundle loaded, includes Popper.js)
- **Codebase inspection** -- `class-tp-api-handler.php` (null fallback on wallet failure confirmed)

### Secondary (MEDIUM confidence)
- Bootstrap 5.3 Tooltip API -- standard documented behavior for `data-bs-toggle`, `data-bs-html`, `data-bs-title`, `bootstrap.Tooltip` constructor, `dispose()` method. Based on well-known stable API.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all dependencies already loaded, verified from shortcode enqueue
- Architecture: HIGH -- all patterns read directly from existing codebase
- Pitfalls: HIGH -- tooltip lifecycle is well-documented Bootstrap behavior; data shape verified from merge adapter source
- Data shape: HIGH -- verified from `UsageMergeAdapter.php` and `class-tp-api-handler.php`

**Research date:** 2026-03-10
**Valid until:** 2026-04-10 (stable -- no external dependencies changing)
