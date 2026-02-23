# Phase 6: Stats Table and Summary Strip - Research

**Researched:** 2026-02-22
**Domain:** Client-side table rendering, data processing, currency arithmetic, pagination, sorting -- all within existing jQuery IIFE + Bootstrap design system
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **Table layout and columns**: Match the client links dashboard table style -- gradient headers, uppercase labels, sortable columns, same CSS design system. Show estimated click/QR breakdown using the same icon pattern (QR icon + count, mouse icon + count) with an "estimated" disclaimer. All columns sortable (date, hits, cost, balance) -- same sort behavior as client links. Paginated -- same pagination pattern as client links dashboard.
- **Summary cards**: Positioned above the table as a horizontal stats strip. Distinct but consistent styling -- different look from client links cards but same color palette and design system. Balance displayed neutrally -- no color coding based on amount. Cards show value + secondary context (e.g., daily average or similar derived metric). Cards show loading skeletons while data loads.
- **Data formatting**: Currency: 2 decimal places ($0.00) -- round sub-cent values for display. Dates: Same relative format as client links -- "Today", "Yesterday", "3 days ago", then "Jan 15, 2026" for older. Zero-activity days: Hidden by default (only show days with activity). Number formatting: Use locale-aware commas (1,234 hits).
- **Empty and loading states**: Empty state matches client links dashboard pattern -- message in the table area showing the date range queried.

### Claude's Discretion
- Loading-to-data transition style (instant vs fade)
- Error state UX pattern
- Exact secondary metric for summary cards (daily average, trend, etc.)
- Skeleton animation details
- Exact column widths and responsive breakpoints

### Deferred Ideas (OUT OF SCOPE)
- WP admin setting to toggle showing/hiding zero-activity days -- admin settings UI is a separate capability
- Admin-configurable display preferences -- future phase
</user_constraints>

## Summary

Phase 6 builds the data visualization layer on top of Phase 5's AJAX proxy. The JavaScript receives an array of `{ date, totalHits, hitCost, balance }` objects from the `tp_get_usage_summary` AJAX endpoint, processes that data entirely client-side (mock click/QR split, currency formatting, running balance recalculation, sorting, pagination), and renders a stats table plus summary cards into the existing template placeholders (`#tp-ud-summary-strip` and `#tp-ud-table-container`).

The reference implementation is the client links dashboard (`client-links.js` / `client-links.css`). Phase 6 replicates its patterns: gradient table headers with uppercase labels, sortable column headers with font-awesome sort icons, Bootstrap pagination with "Showing X-Y of Z" info text, shimmer skeleton loading, and responsive card layout on mobile. The key difference is that client links sorting happens server-side (the API supports `sort` parameter), whereas usage stats sorting must happen client-side because the AJAX proxy returns the full dataset without pagination support.

The critical technical challenges are: (1) floating-point precision for running balance calculation -- must use integer-cent math to avoid drift over 30+ rows of sub-cent values like $0.001, (2) deterministic mock click/QR split from `totalHits` that produces consistent results for the same input, and (3) client-side sorting and pagination of the full dataset without any server-side support.

**Primary recommendation:** Extend the existing `usage-dashboard.js` IIFE with table rendering, sorting, pagination, and summary card functions following the exact patterns from `client-links.js`. Add CSS to `usage-dashboard.css` reusing the `tp-ud-` prefix. No new files needed -- everything goes into the existing JS/CSS created in Phase 5.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| jQuery | 3.x (WP bundled) | DOM manipulation, event binding, AJAX | Already used in usage-dashboard.js IIFE from Phase 5 |
| Bootstrap | 5.3.0 (CDN) | Pagination component, table classes, responsive grid | Already enqueued by Phase 5 shortcode |
| Font Awesome | 6.4.0 (CDN) | Sort icons, stat card icons, QR/click icons | Already enqueued by Phase 5 shortcode |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `Number.prototype.toLocaleString()` | ES5+ | Locale-aware number formatting (1,234) | For hit counts in table cells and summary cards |
| `Number.prototype.toFixed(2)` | ES5+ | Currency formatting to 2 decimal places | For cost and balance columns |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Client-side sorting | Server-side sort param on API | API doesn't support sort for usage summary; client-side is the only option |
| Client-side pagination | Server-side `page`/`page_size` on API | API returns full dataset; 30-day range is small enough for client-side |
| `Intl.NumberFormat` for currency | Manual `$` + `toFixed(2)` | `Intl.NumberFormat` is more robust but heavier; `toFixed(2)` with `$` prefix matches the existing codebase pattern and the requirement for exactly 2 decimal places |

## Architecture Patterns

### Recommended Project Structure
```
assets/
    css/
        usage-dashboard.css   # MODIFY - add table, pagination, summary card, skeleton styles
    js/
        usage-dashboard.js    # MODIFY - add rendering, sorting, pagination, data processing

templates/
    usage-dashboard-template.php  # MODIFY - add table HTML structure and summary card placeholders
```

No new files needed. All work modifies the three Phase 5 files.

### Pattern 1: Client-Side Sort with Sort State (from client-links.js)

**What:** Sortable column headers with click handler that toggles sort direction and re-renders the table from the client-side data array.

**When to use:** Usage stats table -- all 4 sortable columns (date, hits, cost, balance).

**Reference implementation:** `client-links.js` lines 225-238 (sort click handler) and lines 352-367 (updateSortIndicators).

**Key difference from client-links:** Client links sends `sort` param to server and re-fetches. Usage stats must sort the local `state.data` array and re-render without an AJAX call.

**Example:**
```javascript
// Sort state in the IIFE
var state = {
    // ... existing state ...
    sort: 'date:desc',           // default sort: newest first
    currentPage: 1,
    pageSize: 10
};

// Sort handler (delegated, same pattern as client-links.js)
$(document).on('click', '.tp-ud-sortable', function() {
    var field = $(this).data('sort');
    if (!field) return;

    var parts = state.sort.split(':');
    if (parts[0] === field) {
        state.sort = field + ':' + (parts[1] === 'asc' ? 'desc' : 'asc');
    } else {
        state.sort = field + ':asc';
    }
    state.currentPage = 1;
    updateSortIndicators();
    renderTable();
});

// Sort the data array
function getSortedData() {
    var parts = state.sort.split(':');
    var field = parts[0];
    var dir = parts[1];
    var sorted = state.data.slice(); // shallow copy

    sorted.sort(function(a, b) {
        var aVal = a[field];
        var bVal = b[field];

        // Date comparison
        if (field === 'date') {
            aVal = new Date(aVal).getTime();
            bVal = new Date(bVal).getTime();
        }

        if (aVal < bVal) return dir === 'asc' ? -1 : 1;
        if (aVal > bVal) return dir === 'asc' ? 1 : -1;
        return 0;
    });

    return sorted;
}
```

### Pattern 2: Client-Side Pagination (adapted from client-links.js)

**What:** Pagination computed from the local data array, not from server response.

**When to use:** Usage stats table.

**Key difference from client-links:** Client links receives `total_records` and `total_pages` from the server. Usage stats must compute these from the local sorted/filtered array.

**Example:**
```javascript
function renderTable() {
    var sorted = getSortedData();
    var totalRecords = sorted.length;
    var totalPages = Math.ceil(totalRecords / state.pageSize);

    // Clamp current page
    if (state.currentPage > totalPages) state.currentPage = totalPages;
    if (state.currentPage < 1) state.currentPage = 1;

    // Slice for current page
    var start = (state.currentPage - 1) * state.pageSize;
    var pageData = sorted.slice(start, start + state.pageSize);

    // Render rows from pageData...
    renderRows(pageData);
    renderPagination(totalRecords, totalPages);
}
```

### Pattern 3: Mock Click/QR Split (DATA-07)

**What:** Deterministic split of `totalHits` into estimated clicks and QR scans.

**When to use:** Every table row and summary card totals.

**Rationale:** The API only returns `totalHits`. The user wants the same icon pattern as client links (QR icon + count, mouse icon + count). The split must be deterministic -- the same `totalHits` on the same `date` always produces the same click/QR values.

**Example:**
```javascript
/**
 * Split totalHits into estimated clicks and QR scans.
 * Uses a fixed ratio (70% clicks / 30% QR) for simplicity.
 * Deterministic: same input always produces same output.
 * The "estimated" label makes the exact ratio less critical.
 */
function splitHits(totalHits) {
    var qr = Math.round(totalHits * 0.3);
    var clicks = totalHits - qr;  // remainder to clicks, so they always sum correctly
    return { clicks: clicks, qr: qr };
}
```

### Pattern 4: Running Balance Without Floating-Point Drift (TABLE-03)

**What:** Recalculate running balance using integer-cent arithmetic to avoid drift.

**When to use:** When displaying the balance column.

**Critical requirement:** The API provides `balance` as a float on each record. However, when sorting changes the order, the running balance must be recalculated. Even when using the API-provided balance values, display formatting must avoid floating-point artifacts like `$-1.5000000000000002`.

**Example:**
```javascript
/**
 * Format currency value avoiding floating-point display artifacts.
 * Uses Math.round to snap to cents before formatting.
 *
 * API provides balance per-row, so we don't need to recalculate running totals.
 * We just need to format the float cleanly.
 */
function formatCurrency(value) {
    // Round to cents (2 decimal places) to kill floating-point drift
    var rounded = Math.round(value * 100) / 100;
    var abs = Math.abs(rounded);
    var formatted = '$' + abs.toFixed(2);
    return rounded < 0 ? '-' + formatted : formatted;
}
```

**Important note on running balance:** The API returns `balance` on each record as the running cumulative balance for that date. When the table is sorted by date (the natural order), the balance column directly uses the API values. When sorted by a different column (e.g., hits), the balance values still reflect the per-date cumulative balance from the API -- they do NOT get recalculated based on the new sort order. The balance is a property of the date, not a running calculation across visible rows.

### Pattern 5: Summary Cards HTML (STATS-01)

**What:** Horizontal stats strip with 3-4 cards showing aggregate metrics.

**Reference:** Client links has a mobile-only stats bar (`tp-cl-chart-mobile` with `tp-cl-stat` items). The usage dashboard cards should be a more prominent, always-visible version.

**Example structure:**
```html
<div class="tp-ud-summary-strip">
    <div class="tp-ud-stat-card">
        <div class="tp-ud-stat-icon"><i class="fas fa-chart-line"></i></div>
        <div class="tp-ud-stat-body">
            <div class="tp-ud-stat-value">1,234</div>
            <div class="tp-ud-stat-label">Total Hits</div>
            <div class="tp-ud-stat-secondary">~41 per day</div>
        </div>
    </div>
    <!-- more cards... -->
</div>
```

### Anti-Patterns to Avoid

- **Recalculating running balance on sort:** The `balance` field from the API is a cumulative running balance tied to each date. Do not attempt to recalculate it when the sort order changes. Display the API-provided value as-is (with proper formatting).

- **Using `parseFloat` for currency display:** Never display `parseFloat(hitCost).toFixed(2)` directly. Always round to cents first: `Math.round(value * 100) / 100` then `.toFixed(2)`. This prevents `0.1 + 0.2 = 0.30000000000000004` artifacts.

- **Creating new AJAX endpoints:** Phase 6 consumes the existing `tp_get_usage_summary` endpoint from Phase 5. No server-side changes needed.

- **Sorting via server re-fetch:** The API doesn't support sort parameters for usage summary. Sort the local data array and re-render.

- **Modifying the AJAX response shape:** The validated response `{ days: [{ date, totalHits, hitCost, balance }] }` is the contract from Phase 5. Phase 6 consumes it as-is.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Pagination UI | Custom pagination HTML/CSS | Bootstrap `.pagination` component + same CSS as client-links | Already has styled pagination with `.page-link`, `.page-item.active`, etc. |
| Number formatting | Manual comma insertion | `Number.toLocaleString()` | Handles locale differences, already used in client-links.js |
| Skeleton animation | Custom loading spinner | CSS shimmer animation (same `@keyframes` as client-links) | Existing shimmer pattern in `client-links.css` lines 650-683 |
| Responsive card layout | Custom grid system | Bootstrap responsive utilities + `@media` breakpoints matching client-links | Same breakpoints: 767.98px for mobile, 991.98px for tablet |
| Sort icon toggling | Custom icon management | Font Awesome `fa-sort`, `fa-sort-up`, `fa-sort-down` + same CSS pattern | Exact pattern from client-links `updateSortIndicators()` |

**Key insight:** Every UI pattern needed for Phase 6 already exists in `client-links.js` and `client-links.css`. The implementation is a matter of replicating these patterns with usage-dashboard-specific data, not inventing new UI paradigms.

## Common Pitfalls

### Pitfall 1: Floating-Point Display Artifacts in Currency
**What goes wrong:** Table shows `$-1.5000000000000002` or `$0.30000000000000004` instead of clean dollar amounts.
**Why it happens:** JavaScript floating-point arithmetic (`-0.5 + -0.5 + -0.5 = -1.4999999999999998`). The API returns float values like `hitCost: -0.5`, and naive `toFixed(2)` can still show artifacts depending on the input.
**How to avoid:** Round to cents before formatting: `Math.round(value * 100) / 100` then `.toFixed(2)`. This snaps to the nearest cent, eliminating sub-cent floating-point noise.
**Warning signs:** Dollar amounts with more than 2 decimal places, or slightly-off totals in summary cards.

### Pitfall 2: Sort State Not Resetting Page Number
**What goes wrong:** User sorts by cost, is on page 3, and sees an empty or partially-filled page because the new sort has fewer items at that page offset.
**Why it happens:** The sort handler doesn't reset `currentPage` to 1.
**How to avoid:** Always set `state.currentPage = 1` when sort changes (same pattern as client-links.js line 237).
**Warning signs:** Empty table after changing sort column.

### Pitfall 3: Mock Click/QR Split Not Summing to totalHits
**What goes wrong:** `clicks + qr !== totalHits` due to rounding. Summary cards show inconsistent totals.
**Why it happens:** Both values are independently rounded: `Math.round(totalHits * 0.3)` and `Math.round(totalHits * 0.7)` can differ by 1.
**How to avoid:** Calculate one value (e.g., QR), then derive the other as `totalHits - qr`. This guarantees they always sum correctly.
**Warning signs:** Summary card "Total Hits" doesn't match sum of "Clicks" + "QR Scans".

### Pitfall 4: Empty Data Array Causing Division by Zero in Summary
**What goes wrong:** Daily average calculation divides by 0 when there are no records.
**Why it happens:** `totalHits / days.length` when `days.length === 0`.
**How to avoid:** Guard all division operations: `days.length > 0 ? total / days.length : 0`.
**Warning signs:** `NaN` or `Infinity` displayed in summary cards.

### Pitfall 5: Table Header Click Events Not Delegated
**What goes wrong:** Sort headers work on first render but stop working after table re-render.
**Why it happens:** Direct event binding (`.on('click', ...)` on the element) is lost when the DOM is replaced. Client-links uses `$(document).on('click', '.tp-cl-sortable', ...)` for delegation.
**How to avoid:** Use delegated events: `$(document).on('click', '.tp-ud-sortable', handler)` -- same pattern as client-links.
**Warning signs:** Sort icons update on first click but stop responding after data reload.

### Pitfall 6: Loading Skeleton Visible During Sort/Pagination
**What goes wrong:** Skeleton flashes briefly when user sorts or paginates, even though data is already loaded.
**Why it happens:** Re-using the full loading flow (show skeleton, hide content) for client-side operations that don't need AJAX.
**How to avoid:** Only show skeleton during AJAX fetches. For client-side sort/pagination, directly re-render the table body and pagination without toggling the skeleton/content states.
**Warning signs:** Flickering/flash when clicking sort headers or pagination buttons.

## Code Examples

### Table Row Rendering
```javascript
// Source: Adapted from client-links.js renderTable() pattern
function renderRows(pageData) {
    var $tbody = $('#tp-ud-tbody');
    $tbody.empty();

    pageData.forEach(function(day) {
        var split = splitHits(day.totalHits);

        var row =
            '<tr>' +
                '<td class="tp-ud-col-date" data-label="Date">' +
                    '<span class="tp-ud-date">' + formatDate(day.date) + '</span>' +
                '</td>' +
                '<td class="tp-ud-col-hits" data-label="Hits">' +
                    '<div class="tp-ud-hits-cell">' +
                        '<span class="tp-ud-hits-total">' + day.totalHits.toLocaleString() + '</span>' +
                        '<span class="tp-ud-hits-breakdown">' +
                            '<i class="fas fa-mouse-pointer"></i> ' + split.clicks.toLocaleString() +
                            ' <i class="fas fa-qrcode ms-1"></i> ' + split.qr.toLocaleString() +
                        '</span>' +
                    '</div>' +
                '</td>' +
                '<td class="tp-ud-col-cost" data-label="Cost">' +
                    '<span class="tp-ud-cost">' + formatCurrency(day.hitCost) + '</span>' +
                '</td>' +
                '<td class="tp-ud-col-balance" data-label="Balance">' +
                    '<span class="tp-ud-balance">' + formatCurrency(day.balance) + '</span>' +
                '</td>' +
            '</tr>';

        $tbody.append(row);
    });
}
```

### Summary Cards Rendering
```javascript
// Source: Adapted from client-links.js chart stats pattern
function renderSummaryCards(data) {
    var $strip = $('#tp-ud-summary-strip');
    $strip.empty();

    if (!data || data.length === 0) {
        $strip.hide();
        return;
    }

    var totalHits = 0;
    var totalCostCents = 0;  // integer cents to avoid float drift
    var latestBalance = data.length > 0 ? data[data.length - 1].balance : 0;

    data.forEach(function(day) {
        totalHits += day.totalHits;
        totalCostCents += Math.round(day.hitCost * 100);
    });

    var totalCost = totalCostCents / 100;
    var dailyAvg = data.length > 0 ? Math.round(totalHits / data.length) : 0;

    var html =
        '<div class="tp-ud-summary-strip">' +
            buildStatCard('fa-chart-line', totalHits.toLocaleString(), 'Total Hits', '~' + dailyAvg.toLocaleString() + '/day') +
            buildStatCard('fa-dollar-sign', formatCurrency(totalCost), 'Total Cost', data.length + ' days') +
            buildStatCard('fa-wallet', formatCurrency(latestBalance), 'Balance', 'Current') +
        '</div>';

    $strip.html(html).show();
}

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

### Sortable Table Header HTML
```html
<!-- Source: Adapted from client-links-template.php table headers -->
<thead>
    <tr>
        <th class="tp-ud-col-date tp-ud-sortable" data-sort="date">
            Date <i class="fas fa-sort tp-ud-sort-icon"></i>
        </th>
        <th class="tp-ud-col-hits tp-ud-sortable" data-sort="totalHits">
            Hits <i class="fas fa-sort tp-ud-sort-icon"></i>
        </th>
        <th class="tp-ud-col-cost tp-ud-sortable" data-sort="hitCost">
            Cost <i class="fas fa-sort tp-ud-sort-icon"></i>
        </th>
        <th class="tp-ud-col-balance tp-ud-sortable" data-sort="balance">
            Balance <i class="fas fa-sort tp-ud-sort-icon"></i>
        </th>
    </tr>
</thead>
```

### Date Formatting (Reuse from client-links.js)
```javascript
// Source: Exact copy from client-links.js lines 889-898
function formatDate(dateString) {
    if (!dateString) return '-';
    var date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    var now = new Date();
    var days = Math.floor((now - date) / (1000 * 60 * 60 * 24));
    if (days === 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days < 7) return days + ' days ago';
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
```

### Pagination (Adapted from client-links.js)
```javascript
// Source: Adapted from client-links.js renderPagination() lines 781-836
function renderPagination(totalRecords, totalPages) {
    var $paginationInfo = $('#tp-ud-pagination-info');
    var $paginationList = $('#tp-ud-pagination-list');
    var $pagination = $('#tp-ud-pagination');
    $paginationList.empty();

    if (totalPages <= 1) {
        $pagination.hide();
        $paginationInfo.text(totalRecords + ' ' + (totalRecords === 1 ? 'day' : 'days'));
        return;
    }
    $pagination.show();

    // Same maxVisible=5 windowed pagination as client-links
    var maxVisible = 5;
    var startPage = Math.max(1, state.currentPage - Math.floor(maxVisible / 2));
    var endPage = Math.min(totalPages, startPage + maxVisible - 1);
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }

    // Prev button
    $paginationList.append(
        '<li class="page-item ' + (state.currentPage === 1 ? 'disabled' : '') + '">' +
            '<a class="page-link" href="#" data-page="' + (state.currentPage - 1) + '"><i class="fas fa-chevron-left"></i></a>' +
        '</li>'
    );

    // ... (same windowed page number logic as client-links) ...

    // Next button
    $paginationList.append(
        '<li class="page-item ' + (state.currentPage === totalPages ? 'disabled' : '') + '">' +
            '<a class="page-link" href="#" data-page="' + (state.currentPage + 1) + '"><i class="fas fa-chevron-right"></i></a>' +
        '</li>'
    );

    var start = (state.currentPage - 1) * state.pageSize + 1;
    var end = Math.min(state.currentPage * state.pageSize, totalRecords);
    $paginationInfo.text('Showing ' + start + '-' + end + ' of ' + totalRecords + ' days');
}
```

## Claude's Discretion Recommendations

### Loading-to-Data Transition
**Recommendation:** Instant swap (no fade animation).
**Rationale:** The client-links dashboard uses instant show/hide (`$loading.show()` / `$loading.hide()`). Consistency with the reference implementation is more important than a marginal UX improvement. A fade transition adds complexity (CSS transitions, timing, potential flash-of-content) for little benefit on a fast AJAX response.

### Error State UX Pattern
**Recommendation:** Same pattern as the existing Phase 5 error state with retry button. For errors during sort/pagination (which should never happen since they're client-side), silently log and keep the current view.

### Secondary Metric for Summary Cards
**Recommendation:**
- Total Hits card: daily average ("~41/day")
- Total Cost card: number of active days ("15 days")
- Balance card: static label ("Current")

**Rationale:** Daily average is the most useful derived metric for hits. For cost, the number of active days provides context for the total. Balance is a point-in-time value (the latest balance from the API), so "Current" is the appropriate qualifier.

### Skeleton Animation
**Recommendation:** Use the shimmer animation pattern from client-links (`@keyframes tp-cl-shimmer` with linear gradient background) rather than the pulse animation from the Phase 5 skeleton. The shimmer looks more polished and matches the reference implementation.

However, the Phase 5 skeleton already uses pulse animation. For the summary cards skeleton (new), use the shimmer. For the table skeleton, render skeleton rows with shimmer that match the table column layout (same approach as `generateSkeletonRows()` in client-links).

### Column Widths and Responsive Breakpoints
**Recommendation:**
- Date: 25% (min-width: 110px)
- Hits: 30% (min-width: 130px) -- needs room for breakdown icons
- Cost: 20% (min-width: 90px)
- Balance: 25% (min-width: 100px)

Responsive breakpoints: Same as client-links (767.98px for mobile card layout, 991.98px for compact). On mobile, the table converts to stacked cards with `data-label` pseudo-elements, identical to client-links mobile pattern.

## Data Flow

```
Phase 5 AJAX proxy (already built)
    |
    v
state.data = response.data.days   (existing code in usage-dashboard.js)
    |
    v
Phase 6: Process data
    |-- splitHits() on each record (add estimated clicks/qr)
    |-- computeSummary() for summary cards
    |-- getSortedData() for current sort order
    |-- slice for current page
    |
    v
Phase 6: Render
    |-- renderSummaryCards() -> #tp-ud-summary-strip
    |-- renderRows() -> #tp-ud-tbody (inside #tp-ud-table-container)
    |-- renderPagination() -> #tp-ud-pagination
    |-- updateSortIndicators() -> thead th icons
    |-- Show "estimated" disclaimer near hits column
```

## Estimated Disclaimer

The hits column shows estimated click/QR breakdown. A small disclaimer is needed per the requirements. Two options:

**Recommended:** Add a small note below the table header row or as a tooltip on the column header: `<span class="tp-ud-estimated-note" title="Click/QR split is estimated">est.</span>` next to the breakdown icons. This matches the requirement "clearly labeled as estimated" without being intrusive.

## Empty State

When `state.data.length === 0`, display the empty state matching client-links pattern:
```html
<div class="tp-ud-empty text-center py-5">
    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
    <h5>No usage data</h5>
    <p class="text-muted">No activity from Jan 23, 2026 to Feb 22, 2026</p>
</div>
```

The date range should be formatted using the same relative-date function but with explicit full dates since it represents a range, not a single date.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Server-side pagination for all tables | Client-side pagination for small datasets (<100 rows) | Phase 6 (new) | First use of client-side pagination in this plugin; client-links uses server-side |
| Raw float display for currency | Math.round to cents before toFixed(2) | Phase 6 (new) | Prevents floating-point display artifacts; not needed in client-links because its data doesn't have sub-cent values |

**Deprecated/outdated:**
- None relevant. All patterns are stable jQuery/Bootstrap/Font Awesome.

## Open Questions

1. **Hits column: should sort be on totalHits or should estimated clicks/QR be individually sortable?**
   - What we know: The user said "all columns sortable (date, hits, cost, balance)". The "hits" column shows totalHits with a click/QR breakdown.
   - What's unclear: Whether "hits" sorting means sort by totalHits (the source data), or if clicks and QR should be separate sortable sub-columns.
   - Recommendation: Sort by `totalHits` (the real data from API). The click/QR breakdown is estimated and decorative -- sorting by estimated values would be misleading. The sort applies to the total, which is the accurate figure.

2. **Page size: hardcoded 10 or configurable?**
   - What we know: Client-links supports a `page_size` shortcode attribute defaulting to 10. The user hasn't specified a page size for usage stats.
   - What's unclear: Whether the user wants the same configurability.
   - Recommendation: Hardcode to 10 for v1. This matches the client-links default and is reasonable for a 30-day dataset (3 pages maximum). Can be made configurable later if needed.

3. **"Estimated" disclaimer placement**
   - What we know: The click/QR split must be "clearly labeled as estimated" per the requirements.
   - What's unclear: Exact visual placement -- column header, each row, a footnote, or a tooltip.
   - Recommendation: A small "(est.)" label in the table header next to "Hits", plus a footnote below the table. This is visible without cluttering each row.

## Sources

### Primary (HIGH confidence)
- `assets/js/client-links.js` -- canonical reference for table rendering, sorting, pagination, date formatting, chart stats, skeleton loading, event delegation
- `assets/css/client-links.css` -- canonical reference for table styles, gradient headers, sort icons, pagination, skeleton shimmer, responsive card layout, CSS variables
- `templates/client-links-template.php` -- canonical reference for table HTML structure, sortable headers with data-sort, pagination container, skeleton tbody
- `assets/js/usage-dashboard.js` -- Phase 5 stub with AJAX fetching, state management, skeleton/error/content toggling (the file Phase 6 extends)
- `assets/css/usage-dashboard.css` -- Phase 5 styles with skeleton, error, date range, responsive (the file Phase 6 extends)
- `templates/usage-dashboard-template.php` -- Phase 5 template with `#tp-ud-summary-strip` and `#tp-ud-table-container` placeholders
- `includes/class-tp-api-handler.php` lines 1679-1701 -- validates response shape `{ days: [{ date, totalHits, hitCost, balance }] }`
- `API_REFERENCE.md` lines 113-170 -- API response format for `GET /user-activity-summary/{uid}`

### Secondary (MEDIUM confidence)
- [MDN - Number.prototype.toFixed()](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Number/toFixed) -- toFixed behavior with floating-point inputs
- [MDN - Number.prototype.toLocaleString()](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Number/toLocaleString) -- locale-aware number formatting

### Tertiary (LOW confidence)
- None. All findings verified against primary codebase sources.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all libraries already in use; no new dependencies
- Architecture: HIGH -- every pattern (sort, pagination, table rendering, skeleton, responsive cards) directly verified against client-links.js implementation
- Pitfalls: HIGH -- floating-point precision issues are well-documented; all other pitfalls derived from direct code inspection of client-links patterns
- Data processing: HIGH -- API response shape verified against both API_REFERENCE.md and the Phase 5 validation code

**Research date:** 2026-02-22
**Valid until:** 2026-03-22 (stable domain -- jQuery/Bootstrap patterns, no external dependency changes)
