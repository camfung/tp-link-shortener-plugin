# Phase 7: Chart Rendering - Research

**Researched:** 2026-02-23
**Domain:** Chart.js 4.4.1 area chart rendering, canvas lifecycle management, flex container stability, stacked series configuration -- all within existing jQuery IIFE + Bootstrap design system
**Confidence:** HIGH

## Summary

Phase 7 adds an area chart to the usage dashboard that visualizes daily clicks and QR scans over time. The chart renders into an existing `<canvas id="tp-ud-chart">` element inside a `<div class="tp-ud-chart-wrapper">` that was placed in the Phase 5 template. Chart.js 4.4.1 is already loaded via CDN (handle `tp-chartjs`) and declared as a dependency of `usage-dashboard.js`. The data pipeline is fully built: `loadData()` fetches daily records `{ date, totalHits, hitCost, balance }` into `state.data`, `splitHits()` deterministically splits `totalHits` into estimated clicks (70%) and QR scans (30%), and Phase 8's date filtering already triggers `loadData()` on date range changes. The chart function will be called from `loadData()`'s success callback alongside the existing `renderSummaryCards()` and `renderTable()` calls.

The primary technical challenges are: (1) Chart.js canvas lifecycle -- the chart must be destroyed before recreation on date range changes to prevent "Canvas already in use" errors, (2) flex container resize stability -- the chart wrapper must use `position: relative` and `min-width: 0` to prevent an infinite resize loop documented in Chart.js issues #5805 and #9001, (3) the X-axis should use `type: 'category'` scale with pre-formatted date strings to avoid requiring a date adapter library, and (4) the stacked area chart must use yellow (`#f5a623`) for clicks and green (`#22b573`) for QR scans per the TP-59 design reference, with both series using `fill: 'origin'` to create the area fill effect.

The existing client-links dashboard (`client-links.js`) already has a Chart.js bar chart implementation that demonstrates the destroy/recreate pattern (`state.chart.destroy()`) and Chart.js configuration. Phase 7 adapts this pattern for a line/area chart with stacked series.

**Primary recommendation:** Add a `renderChart(data)` function to `usage-dashboard.js` that uses Chart.js `type: 'line'` with `fill: 'origin'` on two datasets (clicks and QR), call it from `loadData()` success callback, store the chart instance in state for lifecycle management, and fix the chart wrapper CSS to prevent resize loops.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Chart.js | 4.4.1 (CDN, `tp-chartjs` handle) | Area chart with `type: 'line'` + `fill: 'origin'` | Already loaded and declared as dependency of `usage-dashboard.js` in shortcode PHP |
| jQuery | 3.x (WP bundled) | DOM manipulation, state management | Already used throughout `usage-dashboard.js` IIFE |
| Bootstrap | 5.3.0 (CDN) | No direct chart use, but provides responsive container context | Already enqueued |
| Font Awesome | 6.4.0 (CDN) | Info icon for estimated disclaimer | Already enqueued |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Chart.js Filler plugin | Built into chart.umd.min.js | Enables `fill: 'origin'` on line datasets | Automatically active; no separate load needed |
| `splitHits()` | Existing in usage-dashboard.js | Deterministic 70/30 click/QR split | Already used by table rendering; chart reuses same function |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `type: 'category'` X-axis | `type: 'time'` X-axis | Time scale requires a date adapter library (`chartjs-adapter-date-fns`, ~16KB). Category scale works perfectly with pre-formatted date strings from the API and handles the daily granularity use case without any additional dependency. Use category. |
| Two separate `fill: 'origin'` datasets | `scales.y.stacked: true` with `fill: true` | Stacked mode makes QR scans visually appear on top of clicks, summing to totalHits. With mocked data using a fixed ratio, stacking is visually accurate (clicks + QR = totalHits). The TP-59 design reference shows stacked series. Use stacked. |
| Destroy/recreate chart on date change | `chart.data = newData; chart.update()` | In-place update is more performant and avoids lifecycle issues. However, if label count changes (different date ranges have different day counts), the category axis labels must also be updated. In-place update with label replacement is the preferred approach for performance, with destroy/recreate as fallback. |

## Architecture Patterns

### Recommended Project Structure
```
assets/
    css/
        usage-dashboard.css   # MODIFY - fix chart wrapper CSS (position, min-width, height)
    js/
        usage-dashboard.js    # MODIFY - add renderChart(), chart state, Chart global check
```

Two files modified. No new files needed.

### Pattern 1: Chart Instance Lifecycle (from client-links.js)

**What:** Store chart instance in module-scoped state, destroy before recreating.

**When to use:** Every time the chart needs to re-render with new data (date range changes, initial load).

**Reference implementation:** `client-links.js` lines 576-581 (destroy before create):
```javascript
// Source: assets/js/client-links.js lines 576-581
if (state.chart) {
    state.chart.destroy();
    state.chart = null;
}
state.chart = new Chart(ctx, { /* config */ });
```

**Example for usage dashboard:**
```javascript
// Add to state object
var state = {
    // ... existing state ...
    chart: null
};

function renderChart(data) {
    var ctx = document.getElementById('tp-ud-chart');
    if (!ctx) return;

    // Guard: Chart.js must be loaded
    if (typeof Chart === 'undefined') return;

    // Destroy previous instance to prevent "Canvas already in use" error
    if (state.chart) {
        state.chart.destroy();
        state.chart = null;
    }

    // Prepare data arrays
    var labels = [];
    var clicksData = [];
    var qrData = [];

    for (var i = 0; i < data.length; i++) {
        labels.push(data[i].date);
        var split = splitHits(data[i].totalHits);
        clicksData.push(split.clicks);
        qrData.push(split.qr);
    }

    state.chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Clicks (est.)',
                    data: clicksData,
                    borderColor: '#f5a623',
                    backgroundColor: 'rgba(245, 166, 35, 0.15)',
                    fill: 'origin',
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#f5a623',
                    pointHoverRadius: 6,
                    borderWidth: 2
                },
                {
                    label: 'QR Scans (est.)',
                    data: qrData,
                    borderColor: '#22b573',
                    backgroundColor: 'rgba(34, 181, 115, 0.12)',
                    fill: 'origin',
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#22b573',
                    pointHoverRadius: 6,
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { family: "'Poppins', sans-serif", size: 12 },
                        usePointStyle: true,
                        padding: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        title: function(items) {
                            if (!items.length) return '';
                            return items[0].label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        font: { size: 11 }
                    },
                    grid: {
                        color: 'rgba(207, 226, 255, 0.4)'
                    }
                },
                x: {
                    ticks: {
                        font: { size: 11 },
                        maxRotation: 45,
                        minRotation: 0,
                        maxTicksLimit: 15
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}
```

### Pattern 2: Chart Wrapper CSS for Flex Container Stability (CHART-04)

**What:** The chart wrapper must use `position: relative` (required by Chart.js) and the flex parent (or the wrapper itself) must use `min-width: 0` to prevent the infinite resize loop.

**When to use:** The `.tp-ud-chart-wrapper` already exists in `usage-dashboard.css` with `position: relative; width: 100%; margin-bottom: 24px;`. It needs explicit height and `min-width: 0`.

**Current CSS (needs modification):**
```css
/* Current in usage-dashboard.css */
.tp-ud-chart-wrapper {
    position: relative;
    width: 100%;
    margin-bottom: 24px;
}
```

**Required CSS:**
```css
.tp-ud-chart-wrapper {
    position: relative;   /* Required by Chart.js for size detection */
    width: 100%;
    min-width: 0;         /* Prevents flex child from growing indefinitely */
    height: 280px;        /* Explicit height -- not min-height */
    margin-bottom: 24px;
}
```

**Source:** Chart.js official responsive docs state the container must have `position: relative`. Chart.js GitHub issues #5805 and #9001 document that `min-width: 0` on flex children prevents the infinite resize loop.

### Pattern 3: Stacked Area with Category Scale

**What:** Use `type: 'line'` with `fill: 'origin'` on each dataset and `scales.y.stacked: true` to create a stacked area chart. Use `type: 'category'` (default) for X-axis with date string labels.

**When to use:** The TP-59 design shows two stacked series (yellow clicks on bottom, green QR on top). With the mocked 70/30 split, the stacked total equals `totalHits` which is visually accurate.

**Key insight:** When `scales.y.stacked: true`, each dataset's values are cumulated on the Y-axis. The clicks series forms the bottom area, QR scans stack on top. The combined visual height at any point equals `totalHits`, which is correct since `clicks + qr === totalHits` (guaranteed by `splitHits()`).

### Pattern 4: Integration Point in loadData()

**What:** The `renderChart(data)` call goes in the same success callback branch as `renderSummaryCards()` and `renderTable()`.

**Where in current code:** `usage-dashboard.js` line ~489, after `renderSummaryCards(state.data)` and before/after `renderTable()`.

**Example integration:**
```javascript
// In loadData() success handler, after data is stored in state
if (state.data.length === 0) {
    showContent();
    showEmptyState();
    // Hide chart on empty data
    renderChart([]);
} else {
    state.currentPage = 1;
    showContent();
    renderSummaryCards(state.data);
    renderChart(state.data);     // <-- NEW: Phase 7 addition
    renderTable();
}
```

### Anti-Patterns to Avoid

- **Using `min-height` instead of `height` for chart wrapper:** `min-height` allows the chart to grow indefinitely during resize events. Use explicit `height` so Chart.js has a definitive constraint.

- **Not checking `typeof Chart === 'undefined'`:** If Chart.js CDN fails to load (network issue), calling `new Chart()` throws an uncaught error that breaks the entire dashboard. Always guard with a typeof check, matching `client-links.js` line 582.

- **Using `type: 'time'` for X-axis scale:** Requires a date adapter library (`chartjs-adapter-date-fns`) that is not enqueued. Category scale with string labels works perfectly for daily data. The adapter would add an extra CDN dependency for zero functional benefit.

- **Calling `renderChart()` during sort/pagination:** Sort and page changes only affect the table -- the chart shows the full date range regardless of table pagination. Only call `renderChart()` when `loadData()` returns new data, never from `renderTable()`.

- **Applying `fill: 'stack'` instead of `fill: 'origin'`:** The `'stack'` fill mode fills to the previous dataset, not to the X-axis. With `'origin'`, each dataset fills from its line down to zero. Combined with `stacked: true` on Y-axis, this produces the correct visual stacking.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Area chart fill | Custom canvas drawing | Chart.js `fill: 'origin'` on line datasets | Built into Chart.js filler plugin (included in UMD bundle) |
| Stacked series | Manual data accumulation | `scales.y.stacked: true` | Chart.js handles cumulative rendering automatically |
| Responsive resize | Custom ResizeObserver | Chart.js `responsive: true` | Chart.js has built-in ResizeObserver; just needs correct CSS on wrapper |
| Date axis labels | Custom date formatting for axis | Category scale with API date strings | API already returns `YYYY-MM-DD` strings; Chart.js renders them as-is |
| Data point markers | Custom SVG/HTML overlays | Chart.js `pointRadius` and `pointBackgroundColor` | Native Chart.js point rendering with hover effects |
| Chart tooltips | Custom tooltip HTML | Chart.js built-in tooltip plugin | Default tooltip behavior is sufficient; customize with `tooltip.callbacks` if needed |

**Key insight:** Chart.js 4.4.1 provides every capability needed for this chart out of the box. The implementation is purely configuration, not custom rendering.

## Common Pitfalls

### Pitfall 1: "Canvas Already in Use" Error on Date Range Change (CHART-03)
**What goes wrong:** Changing the date range triggers `loadData()` which calls `renderChart()` with new data. If the previous Chart instance is not destroyed, Chart.js throws: `"Canvas is already in use. Chart with ID X must be destroyed before the canvas with ID Y can be reused."` The chart may also render doubled datasets (old + new data stacked).
**Why it happens:** Chart.js tracks instances per canvas element internally. Calling `new Chart(canvas, config)` on an already-bound canvas without destroying the previous instance causes a conflict.
**How to avoid:** Store `state.chart` reference. Before every `new Chart()`, check `if (state.chart) { state.chart.destroy(); state.chart = null; }`. This exact pattern exists in `client-links.js` lines 576-581.
**Warning signs:** Console error containing "Canvas is already in use"; two colored areas stacked on top of each other after a date change; memory usage growing with each date change.

### Pitfall 2: Infinite Resize Loop in Flex Container (CHART-04)
**What goes wrong:** The chart grows taller with every browser window resize event, never stabilizing. Console may show `ResizeObserver loop limit exceeded`. Chart.js reads container width, resizes canvas, which grows the container, which triggers another resize.
**Why it happens:** Flex containers have implicit `min-width: auto` that prevents flex children from shrinking below content width. Chart.js's ResizeObserver detects size changes and triggers another resize. Documented in Chart.js GitHub issues #5805 and #9001.
**How to avoid:** Set `min-width: 0` on the chart wrapper (the flex child). Set explicit `height: 280px` (not `min-height`). Set `position: relative` (required by Chart.js docs for responsive mode).
**Warning signs:** Chart area visibly grows taller when resizing the browser window; `ResizeObserver` errors in console; chart breaks after toggling to/from mobile view.

### Pitfall 3: Missing Chart.js Guard Causes Uncaught Error
**What goes wrong:** If Chart.js CDN fails to load (network timeout, CDN outage), `new Chart()` throws `ReferenceError: Chart is not defined`, breaking the entire dashboard including the working table and summary cards.
**Why it happens:** The UMD bundle loads asynchronously; race conditions or network issues can prevent it from being available.
**How to avoid:** Always check `if (typeof Chart === 'undefined') return;` before any Chart.js code. The table and summary cards should work independently of the chart. Same pattern as `client-links.js` line 582.
**Warning signs:** Blank dashboard with JavaScript error in console; table not rendering even though data loaded successfully.

### Pitfall 4: Chart Labels Overcrowded on Long Date Ranges
**What goes wrong:** A 90-day date range produces 90 X-axis labels, which overlap and become unreadable.
**Why it happens:** Category scale renders one tick per label by default, without considering available space.
**How to avoid:** Set `scales.x.ticks.maxTicksLimit: 15` (or similar) to limit the number of visible ticks. Chart.js will automatically skip intermediate labels while keeping the data points.
**Warning signs:** X-axis labels overlapping each other at 45-degree rotation; chart looking cluttered.

### Pitfall 5: Chart Visible During Loading/Error States
**What goes wrong:** The chart canvas remains visible while the skeleton or error state is shown, showing stale data from the previous load.
**Why it happens:** The chart wrapper is inside `#tp-ud-content` which is hidden during loading/error, but if show/hide toggling has a race condition, the chart may flash.
**How to avoid:** The existing state management (`showSkeleton` hides `$content`, `showContent` shows `$content`) already handles this correctly because the chart wrapper is inside `#tp-ud-content`. No additional hiding logic is needed. When data is empty, call `renderChart([])` which should hide or clear the chart.
**Warning signs:** Old chart data visible while new data is loading.

### Pitfall 6: Chart Not Rendering on Initial Load (Data Order)
**What goes wrong:** Chart renders with 0 data points on initial load because `renderChart()` is called before `state.data` is populated.
**Why it happens:** `renderChart()` added to the wrong place in the code flow (e.g., in `init` instead of in `loadData` success callback).
**How to avoid:** Only call `renderChart(state.data)` inside the `loadData()` success callback, after `state.data = response.data.days` is set. Never call it from initialization code.
**Warning signs:** Chart is empty/blank on first page load but works after changing date range.

## Code Examples

### Complete renderChart() Function
```javascript
// Source: Adapted from client-links.js renderChart() + Chart.js 4.4.1 area chart docs
function renderChart(data) {
    var ctx = document.getElementById('tp-ud-chart');
    if (!ctx) return;
    if (typeof Chart === 'undefined') return;

    // Destroy previous instance (CHART-03)
    if (state.chart) {
        state.chart.destroy();
        state.chart = null;
    }

    // Empty data: leave canvas blank
    if (!data || data.length === 0) {
        return;
    }

    // Build arrays from data (already sorted by date from API)
    var labels = [];
    var clicksData = [];
    var qrData = [];

    for (var i = 0; i < data.length; i++) {
        labels.push(data[i].date);
        var split = splitHits(data[i].totalHits);
        clicksData.push(split.clicks);
        qrData.push(split.qr);
    }

    state.chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Clicks (est.)',
                    data: clicksData,
                    borderColor: '#f5a623',
                    backgroundColor: 'rgba(245, 166, 35, 0.15)',
                    fill: 'origin',
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#f5a623',
                    pointBorderColor: '#f5a623',
                    pointHoverRadius: 6,
                    borderWidth: 2,
                    order: 2
                },
                {
                    label: 'QR Scans (est.)',
                    data: qrData,
                    borderColor: '#22b573',
                    backgroundColor: 'rgba(34, 181, 115, 0.12)',
                    fill: 'origin',
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#22b573',
                    pointBorderColor: '#22b573',
                    pointHoverRadius: 6,
                    borderWidth: 2,
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { family: "'Poppins', sans-serif", size: 12 },
                        usePointStyle: true,
                        padding: 16
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(30, 47, 80, 0.9)',
                    titleFont: { family: "'Poppins', sans-serif", size: 13 },
                    bodyFont: { family: "'Poppins', sans-serif", size: 12 },
                    padding: 10,
                    cornerRadius: 6
                }
            },
            scales: {
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        font: { size: 11 }
                    },
                    grid: {
                        color: 'rgba(207, 226, 255, 0.4)'
                    }
                },
                x: {
                    ticks: {
                        font: { size: 11 },
                        maxRotation: 45,
                        minRotation: 0,
                        maxTicksLimit: 15
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}
```

### Chart Wrapper CSS Fix
```css
/* Source: Chart.js responsive docs + GitHub issues #5805, #9001 */
.tp-ud-chart-wrapper {
    position: relative;   /* Required by Chart.js for responsive sizing */
    width: 100%;
    min-width: 0;         /* Prevents flex resize loop (CHART-04) */
    height: 280px;        /* Explicit height, NOT min-height */
    margin-bottom: 24px;
}
```

### loadData() Integration Point
```javascript
// In loadData() success handler, after state.data is set:
if (state.data.length === 0) {
    showContent();
    showEmptyState();
    renderChart([]);  // Clear chart on empty data
} else {
    state.currentPage = 1;
    showContent();
    renderSummaryCards(state.data);
    renderChart(state.data);   // Phase 7 addition
    renderTable();
}
```

### State Object Addition
```javascript
var state = {
    isLoading: false,
    dateStart: tpUsageDashboard.dateRange.start,
    dateEnd: tpUsageDashboard.dateRange.end,
    data: null,
    sort: 'date:desc',
    currentPage: 1,
    pageSize: 10,
    chart: null             // Phase 7 addition: Chart.js instance reference
};
```

## Data Flow

```
loadData() AJAX success
    |
    v
state.data = response.data.days    (array of {date, totalHits, hitCost, balance})
    |
    v
renderChart(state.data)
    |
    |-- if (state.chart) state.chart.destroy()
    |-- for each day: splitHits(totalHits) -> {clicks, qr}
    |-- labels[] = dates, clicksData[] = clicks, qrData[] = qr
    |-- new Chart(canvas, config) with type:'line', fill:'origin', stacked:true
    |-- state.chart = chartInstance
    |
    v
Chart visible in <canvas id="tp-ud-chart"> inside .tp-ud-chart-wrapper
```

## Design Reference: TP-59 Colors

The TP-59 design reference specifies:
- **Yellow for clicks:** `#f5a623` (border), `rgba(245, 166, 35, 0.15)` (fill)
- **Green for QR scans:** `#22b573` (border), `rgba(34, 181, 115, 0.12)` (fill)

The green (`#22b573`) is already used throughout the codebase as `--tp-accent`. The yellow (`#f5a623`) is a design-specific color not in the CSS variables but referenced in the project research (`SUMMARY.md` line 132, `STACK.md` line 59).

Note: The existing `--tp-warning: #f0ad4e` is close but not identical to the design yellow `#f5a623`. Use the design reference color directly in the Chart.js config, not the CSS variable -- Chart.js config takes raw color values, not CSS custom properties.

## Estimated Disclaimer Strategy (CHART-05)

The template already has a footnote disclaimer below the table:
```html
<!-- Already exists in usage-dashboard-template.php line 128-131 -->
<p class="tp-ud-estimated-note">
    <i class="fas fa-info-circle"></i>
    Click/QR breakdown is estimated from total hits.
</p>
```

For the chart, the "(est.)" suffix in the dataset labels (`'Clicks (est.)'` and `'QR Scans (est.)'`) serves as the chart legend disclaimer. This is sufficient: the chart legend will show "Clicks (est.)" and "QR Scans (est.)" with their respective color indicators, making the estimated nature visible directly on the chart.

No additional disclaimer element is needed -- the existing table footnote + chart legend labels together satisfy CHART-05.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| No chart in usage dashboard | Area chart with stacked clicks + QR | Phase 7 (new) | Primary data visualization for usage trends |
| Client-links bar chart only | Second chart type (area/line) in plugin | Phase 7 (new) | Area chart better communicates daily time-series trends vs bar chart's per-item comparison |

**Deprecated/outdated:**
- `fill: true` is equivalent to `fill: 'origin'` for backward compatibility. Both work in Chart.js 4.4.1. Use the explicit `'origin'` for clarity.
- Chart.js 3.x migration changed `fill` from boolean to string modes. The plugin uses 4.4.1, so `'origin'` is the correct modern syntax.

## Open Questions

1. **Should the chart use stacked or non-stacked area?**
   - What we know: The TP-59 design reference mentions "two stacked series." The requirements say "two stacked series (yellow/green matching TP-59 design)." The mocked 70/30 split guarantees `clicks + qr === totalHits`, so stacking is numerically accurate.
   - What's unclear: Whether the design intent is truly stacked (QR visually on top of clicks) or overlapping (both areas from zero, with QR in front).
   - Recommendation: Use stacked (`scales.y.stacked: true`). This matches the requirement text "stacked series" and produces a visually accurate chart since the mock split always sums to totalHits. The stacked visual clearly communicates "these two categories make up the total."

2. **Should the chart hide on empty data or show an empty chart?**
   - What we know: When `state.data.length === 0`, the table is hidden and an empty state message is shown.
   - What's unclear: Whether the chart should also be hidden or show empty axes.
   - Recommendation: Destroy the chart on empty data (call `renderChart([])` which destroys without creating). The chart wrapper remains in the DOM but contains a blank canvas. The empty state message below is sufficient. This avoids the complexity of showing/hiding the wrapper div.

3. **resizeDelay configuration**
   - What we know: Chart.js 4.4.1 has a `resizeDelay` option (default 0) that debounces resize events.
   - What's unclear: Whether the flex container fix alone is sufficient or if `resizeDelay` adds extra safety.
   - Recommendation: Start without `resizeDelay` (rely on the CSS fix for `min-width: 0`). If resize instability is observed during testing, add `resizeDelay: 100` as an additional safeguard. The CSS fix addresses the root cause; `resizeDelay` is a band-aid.

## Sources

### Primary (HIGH confidence)
- `assets/js/client-links.js` lines 530-635 -- Canonical Chart.js integration in this codebase: destroy/recreate pattern, `typeof Chart` guard, responsive config, dataset configuration, Chart.js constructor
- `assets/js/usage-dashboard.js` -- Current JS with state, loadData, splitHits, renderSummaryCards, renderTable; no chart code yet
- `assets/css/usage-dashboard.css` lines 186-191 -- Current `.tp-ud-chart-wrapper` CSS (needs modification)
- `templates/usage-dashboard-template.php` lines 98-101 -- Existing `<canvas id="tp-ud-chart">` inside `.tp-ud-chart-wrapper`
- `includes/class-tp-usage-dashboard-shortcode.php` lines 83-89 -- `tp-chartjs` handle enqueued as dependency of dashboard JS
- Chart.js 4.4.1 official docs (chartjs.org/docs/4.4.1/charts/area.html) -- `fill: 'origin'`, filler plugin options
- Chart.js 4.4.1 official docs (chartjs.org/docs/4.4.1/charts/line.html) -- Line dataset properties: borderColor, backgroundColor, fill, tension, pointRadius, pointHoverRadius, borderWidth
- Chart.js 4.4.1 official docs (chartjs.org/docs/4.4.1/configuration/responsive.html) -- Container must have `position: relative`, responsive/maintainAspectRatio/resizeDelay options
- Chart.js 4.4.1 official docs (chartjs.org/docs/4.4.1/developers/api.html) -- `destroy()` method: "must be called before the canvas is reused for a new chart"

### Secondary (MEDIUM confidence)
- Chart.js GitHub issue #5805 -- Flex container infinite resize loop; CSS `min-width: 0` fix
- Chart.js GitHub issue #9001 -- Additional flex resize discussion; `overflow: hidden` as alternative fix
- `.planning/research/STACK.md` lines 52-78 -- Yellow/green color codes for TP-59 design reference chart
- `.planning/research/PITFALLS.md` lines 11-46, 79-117 -- Chart.js flex resize loop and canvas destroy pitfalls
- `.planning/research/SUMMARY.md` lines 127-132 -- Phase 3 (chart rendering) deliverables and color codes

### Tertiary (LOW confidence)
- None. All findings verified against official Chart.js docs and direct codebase inspection.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- Chart.js 4.4.1 already enqueued; `fill: 'origin'` verified in official docs; all supporting patterns exist in client-links.js
- Architecture: HIGH -- Integration point (loadData success callback) is known; state management pattern matches codebase; canvas element already in template
- Pitfalls: HIGH -- All three Chart.js pitfalls (resize loop, canvas reuse, missing guard) verified against official GitHub issues and docs; CSS fix verified against responsive docs
- Design colors: MEDIUM -- Yellow `#f5a623` sourced from project research docs, not directly from TP-59 ticket; green `#22b573` confirmed in codebase CSS variables

**Research date:** 2026-02-23
**Valid until:** 2026-03-23 (stable domain -- Chart.js 4.4.1 is a locked CDN version; no breaking changes possible)
