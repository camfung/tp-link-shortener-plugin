# Stack Research: Mobile Responsiveness for Bootstrap 5 WordPress Dashboard

**Domain:** Mobile-responsive conversion of existing WordPress plugin dashboard
**Researched:** 2026-02-15
**Confidence:** HIGH

## Executive Summary

The existing codebase already has a solid foundation for mobile responsiveness. Both `dashboard.css` and `client-links.css` already implement table-to-card transformations at 768px using the `data-label` + `::before` pseudo-element pattern, and have breakpoints at 992px, 768px, and 520px. The chart uses Chart.js 4.4.1 with `responsive: true` and `maintainAspectRatio: false` already set. Modals use custom overlay patterns (not Bootstrap's modal component).

**The mobile work is NOT a framework migration. It is CSS refinement, touch optimization, and gap-filling for the 320px-480px range that the current breakpoints miss.**

No new libraries are needed. The stack additions are purely CSS patterns and techniques applied to the existing design system.

## Recommended Stack

### Core Technologies (Already Present -- No Changes)

| Technology | Version | Purpose | Status |
|------------|---------|---------|--------|
| Bootstrap 5 | 5.3.0 (CDN) | Layout grid, utilities, responsive helpers | KEEP -- already loaded |
| Chart.js | 4.4.1 (CDN) | Performance bar chart | KEEP -- already responsive |
| Font Awesome | 6.4.0 (CDN) | Icons throughout UI | KEEP |
| jQuery | WP-bundled | DOM manipulation, AJAX | KEEP -- used by client-links.js |
| Custom CSS Variables | N/A | Design tokens (--tp-primary, etc.) | KEEP -- extend for mobile |

### Stack Additions: Zero New Libraries

No additional JavaScript libraries or CSS frameworks are needed. The entire mobile conversion uses:

1. **CSS-only techniques** (media queries, clamp(), CSS custom properties)
2. **Bootstrap 5.3 built-in utilities** (already loaded but underutilized)
3. **Chart.js built-in responsive options** (already partially configured)

### CSS Patterns to Implement

| Pattern | Purpose | Where to Apply |
|---------|---------|----------------|
| Extended media queries (320px-480px) | Cover small phone screens | All 3 CSS files |
| `clamp()` for spacing | Fluid padding/margins without breakpoint jumps | Container padding, card padding, table cell padding |
| CSS custom properties for mobile | Override design tokens at mobile breakpoints | `:root` scope within media queries |
| `min()` for max-widths | Prevent overflow on tiny screens | Modal widths, search input, date range picker |
| Touch target sizing (44px min) | WCAG 2.5.8 compliance, fat-finger prevention | All buttons, toggle switches, inline action icons |
| `gap` with responsive values | Consistent spacing in flex layouts | Controls, filters, pagination |
| `scroll-snap-type` | Smooth horizontal scrolling for filter bar | `.tp-cl-controls` on mobile |
| `-webkit-overflow-scrolling: touch` | Smooth momentum scrolling | Modal body, table wrapper |

### Bootstrap 5.3 Utilities to Leverage (Already Loaded)

These classes are available from the existing Bootstrap 5.3.0 CDN but are not currently used in the templates. They should be added to the PHP template markup:

| Utility Class | Purpose | When to Use |
|---------------|---------|-------------|
| `d-none d-md-block` | Hide on mobile, show on tablet+ | Secondary table columns, verbose labels |
| `d-md-none` | Show only on mobile | Compact mobile-only UI elements |
| `text-truncate` | Single-line truncation with ellipsis | Destination URLs, long link labels |
| `overflow-auto` | Scrollable containers | Filter bar horizontal scroll |
| `gap-*` utilities | Flex/grid gap spacing | Already using custom gap; BS5 utilities available |
| `visually-hidden` | Accessible screen-reader text | Labels for icon-only buttons on mobile |
| `modal-fullscreen-md-down` | Full-viewport modal below 768px | NOT applicable -- uses custom modals, not BS5 modals |

**Important note on modals:** The codebase uses custom modal overlays (`tp-cl-modal-overlay`, `tp-edit-modal-overlay`), NOT Bootstrap's `<div class="modal">` component. The `modal-fullscreen-md-down` Bootstrap utility class will NOT work here. Full-screen mobile behavior must be implemented in custom CSS targeting the existing `.tp-cl-modal` and `.tp-edit-modal` classes.

### Chart.js Mobile Optimizations (No Library Change)

The existing Chart.js 4.4.1 instance already has `responsive: true` and `maintainAspectRatio: false`. Additional mobile optimizations to apply in the JavaScript:

| Configuration | Value | Purpose |
|---------------|-------|---------|
| `options.aspectRatio` | `1.2` on mobile (via JS check) | Taller chart on narrow screens |
| `plugins.legend.position` | `'bottom'` on mobile | Save vertical header space |
| `plugins.legend.labels.boxWidth` | `12` on mobile | Smaller legend color boxes |
| `plugins.title.font.size` | `12` on mobile | Smaller title |
| `scales.x.ticks.maxRotation` | `90` on mobile | Rotate labels to fit |
| `scales.x.ticks.font.size` | `9` on mobile | Smaller tick labels |

Detection method: Check `window.innerWidth < 768` at render time and on resize.

## CSS Architecture for Mobile

### New CSS Custom Properties for Mobile Breakpoints

Add to each CSS file's `:root` or use media query overrides:

```css
/* Mobile spacing tokens -- add inside @media (max-width: 480px) */
:root {
    --tp-mobile-padding: 0.75rem;
    --tp-mobile-gap: 6px;
    --tp-mobile-radius: 0.75rem;
    --tp-touch-target: 44px;  /* WCAG 2.5.8 recommended */
}
```

### Breakpoint Strategy

The existing codebase uses 992px, 768px, and 520px. Add a 480px breakpoint for small phones and refine 768px for touch:

| Breakpoint | Device | Purpose | Status |
|------------|--------|---------|--------|
| 992px | Tablet landscape | Stack header controls vertically | EXISTS -- working |
| 768px | Tablet portrait | Table-to-card, stack pagination | EXISTS -- needs refinement |
| 520px | Small tablet / large phone | Reduce modal size, stack QR buttons | EXISTS -- needs refinement |
| **480px** | Phone portrait | **NEW** -- Tighten all spacing, smaller fonts, full-width modals | NEEDS ADDING |
| **360px** | Small phone | **NEW** -- Minimum viable layout, emergency overflows | NEEDS ADDING |

### Table-to-Card Pattern (Already Implemented, Needs Refinement)

The existing pattern in both `dashboard.css` and `client-links.css` at 768px is correct:

```css
/* EXISTING -- already in codebase */
@media (max-width: 768px) {
    .tp-cl-table thead { display: none; }
    .tp-cl-table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 2px solid var(--tp-border);
        border-radius: 1rem;
        padding: 1rem;
    }
    .tp-cl-table tbody td {
        display: flex;
        justify-content: space-between;
    }
    .tp-cl-table tbody td::before {
        content: attr(data-label);
        /* label styling */
    }
}
```

**Refinements needed at 480px:**

```css
@media (max-width: 480px) {
    .tp-cl-table tbody tr {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
        border-radius: 0.75rem;
    }
    .tp-cl-table tbody td {
        flex-direction: column;  /* Stack label above value */
        align-items: flex-start;
        gap: 2px;
    }
    .tp-cl-table tbody td::before {
        font-size: 0.6rem;
    }
}
```

### Full-Screen Mobile Modals (Custom Implementation)

Since the codebase uses custom modals (not Bootstrap modals), implement full-screen behavior manually:

```css
@media (max-width: 768px) {
    .tp-cl-modal,
    .tp-edit-modal {
        width: 100vw;
        max-width: 100vw;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
        margin: 0;
    }
    .tp-cl-modal-overlay,
    .tp-edit-modal-overlay {
        align-items: stretch;  /* Fill viewport */
    }
    .tp-cl-modal-body,
    .tp-edit-modal-body {
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        flex: 1;
    }
}
```

### Touch-Friendly Sizing

All interactive elements need minimum 44px touch targets on mobile:

```css
@media (max-width: 768px) {
    /* Inline action buttons -- currently 0.25rem padding = ~20px total */
    .tp-cl-inline-btn {
        min-width: 44px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;  /* Up from 0.78rem */
    }

    /* Toggle switch -- currently 38x20px, too small */
    .tp-cl-toggle {
        width: 52px;
        height: 28px;
    }
    .tp-cl-toggle-slider::before {
        width: 22px;
        height: 22px;
        left: 3px;
        top: 3px;
    }
    .tp-cl-toggle input:checked + .tp-cl-toggle-slider::before {
        transform: translateX(24px);
    }

    /* Pagination links */
    .tp-cl-pagination .page-link {
        min-width: 44px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Filter dropdowns */
    .tp-cl-filters .form-select {
        min-height: 44px;
    }
}
```

### Collapsible Chart Pattern

Wrap the chart in a collapsible section to save vertical space on mobile:

```css
@media (max-width: 768px) {
    .tp-cl-chart-wrapper {
        max-height: 0;
        overflow: hidden;
        padding: 0;
        border: none;
        margin: 0;
        transition: max-height 0.3s ease, padding 0.3s ease, margin 0.3s ease;
    }
    .tp-cl-chart-wrapper.tp-cl-chart-expanded {
        max-height: 300px;
        padding: 0.75rem;
        border: 1px solid var(--tp-border);
        margin-bottom: 1rem;
    }
}
```

This requires a small JS addition: a toggle button that adds/removes the `tp-cl-chart-expanded` class. Approximately 10 lines of JavaScript.

### Horizontal Scroll for Filter/Control Bar

When controls overflow on mobile, use horizontal scroll instead of wrapping to multiple lines:

```css
@media (max-width: 480px) {
    .tp-cl-controls {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scroll-snap-type: x mandatory;
        gap: 8px;
        padding-bottom: 8px; /* Space for scrollbar */
    }
    .tp-cl-controls > * {
        flex-shrink: 0;
        scroll-snap-align: start;
    }
}
```

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| Pure CSS table-to-card (existing) | DataTables.js responsive plugin | Only if you need column priority/toggle -- overkill here since full card conversion already works |
| Custom full-screen modal CSS | Migrate to Bootstrap 5 modal component | Only if rebuilding modals from scratch -- not worth migration cost for existing custom modals |
| CSS `clamp()` for fluid sizing | Multiple media query breakpoints | Use breakpoints for layout changes; use `clamp()` only for continuous scaling (fonts, padding) |
| Chart.js responsive options | Separate mobile chart component | Only if chart needs completely different visualization on mobile (e.g., horizontal bar) -- not needed here |
| CSS-only collapsible chart | JavaScript accordion library | CSS-only is sufficient since we just need show/hide with a toggle |
| Horizontal scroll for controls | Stacked vertical layout | Stacked layout is already implemented at 992px; horizontal scroll is for tighter 480px range |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Bootstrap modal component migration | The codebase uses custom modal overlays with their own animation system. Migrating would require rewriting PHP templates and JS event handling for zero user-visible benefit | Custom CSS `@media` rules targeting existing `.tp-cl-modal` |
| CSS container queries (`@container`) | WordPress sites load plugins in unpredictable theme contexts where containment context may conflict with theme CSS. Browser support is good but adds complexity without clear benefit over media queries for this use case | Standard `@media` queries (proven in codebase) |
| Viewport units (dvh, svh, lvh) | New viewport units have inconsistent mobile browser support, especially in WordPress WebViews and older Android browsers that plugin users may use | `100vh` with JS fallback for mobile address bar (or `min-height: -webkit-fill-available`) |
| Additional CSS frameworks (Tailwind, etc.) | Adding a second CSS framework creates specificity conflicts and bloats page weight. Bootstrap 5.3 already provides all needed utilities | Bootstrap 5.3 utility classes + custom CSS |
| jQuery Mobile / Hammer.js | Touch event libraries are unnecessary. The dashboard has simple tap interactions, no swipe gestures. Native CSS and Bootstrap handle everything | Native CSS `:active` states, standard click/tap events |
| Separate mobile stylesheet | Splitting into a mobile CSS file adds HTTP requests and complicates the build. WordPress doesn't have a build pipeline in this plugin | Media queries within existing CSS files |

## Stack Patterns by Variant

**If targeting minimum 320px (older small phones):**
- Add a 360px breakpoint with emergency overflow handling
- Use `min-width: 0` on flex children to prevent overflow
- Reduce all padding to 0.5rem
- Consider hiding the chart entirely (not just collapsing)

**If targeting minimum 375px (modern phones):**
- 480px breakpoint is sufficient as the smallest custom breakpoint
- Chart can be collapsible rather than hidden
- All current features can remain, just need spacing/sizing adjustments

**If the date range picker causes problems on mobile:**
- Replace the two date inputs + apply button with a single "Period" dropdown (7d, 30d, 90d, custom)
- Native `<input type="date">` on mobile opens the OS date picker which is touch-friendly, but two date inputs side-by-side are cramped below 400px

## Version Compatibility

| Component | Version | Compatible With | Notes |
|-----------|---------|-----------------|-------|
| Bootstrap CSS | 5.3.0 | Bootstrap JS 5.3.0 | Already matched in codebase |
| Chart.js | 4.4.1 | Modern browsers (ES6+) | `responsive: true` works in all target browsers |
| CSS `clamp()` | N/A | 95%+ browser support | Safe for production; fallback not needed |
| CSS Custom Properties | N/A | 97%+ browser support | Already used extensively in codebase |
| `scroll-snap-type` | N/A | 95%+ browser support | Safe for horizontal control scroll |
| `backdrop-filter: blur()` | N/A | 93% support | Already used in `.tp-cl-content`; not critical |
| `-webkit-overflow-scrolling: touch` | N/A | iOS Safari | Deprecated but still needed for smooth scroll on older iOS |

## Installation

```bash
# No packages to install.
# All changes are CSS modifications to existing files:
#   - assets/css/dashboard.css
#   - assets/css/client-links.css
#   - assets/css/frontend.css
#
# Plus minor JS additions (~30 lines total) in:
#   - assets/js/client-links.js (chart resize + chart toggle)
#   - assets/js/dashboard.js (if applicable)
#
# And HTML attribute additions in:
#   - templates/client-links-template.php (Bootstrap utility classes)
#   - templates/dashboard-template.php (Bootstrap utility classes)
```

## Summary of Changes by File

| File | Type of Change | Scope |
|------|----------------|-------|
| `assets/css/client-links.css` | Add 480px/360px breakpoints, touch targets, full-screen modal, collapsible chart, refined card layout | ~150 lines of new CSS |
| `assets/css/dashboard.css` | Add 480px/360px breakpoints, touch targets, full-screen modal, refined card layout | ~120 lines of new CSS |
| `assets/css/frontend.css` | Add 480px breakpoint for form inputs, touch targets on buttons | ~40 lines of new CSS |
| `assets/js/client-links.js` | Chart responsive options by screen width, chart toggle button logic | ~30 lines of JS |
| `templates/client-links-template.php` | Add chart toggle button, Bootstrap responsive utility classes | ~10 lines of HTML |
| `templates/dashboard-template.php` | Bootstrap responsive utility classes | ~5 lines of HTML |

## Sources

- [Bootstrap 5.3 Display Utilities](https://getbootstrap.com/docs/5.3/utilities/display/) -- responsive hiding/showing classes (HIGH confidence)
- [Bootstrap 5.3 Modal Documentation](https://getbootstrap.com/docs/5.3/components/modal/) -- `modal-fullscreen-*-down` classes confirmed (HIGH confidence, but NOT applicable to custom modals in this codebase)
- [Bootstrap 5.3 Offcanvas Documentation](https://getbootstrap.com/docs/5.3/components/offcanvas/) -- responsive offcanvas variants confirmed (HIGH confidence)
- [Bootstrap 5.3 Breakpoints](https://getbootstrap.com/docs/5.3/layout/breakpoints/) -- sm/md/lg/xl/xxl breakpoint values confirmed (HIGH confidence)
- [Chart.js Responsive Configuration](https://www.chartjs.org/docs/latest/configuration/responsive.html) -- responsive, maintainAspectRatio, aspectRatio options (HIGH confidence)
- [WCAG 2.5.8 Target Size (Minimum)](https://www.w3.org/WAI/WCAG21/Understanding/target-size.html) -- 24px minimum, 44px recommended (HIGH confidence)
- [CSS-Tricks: Responsive Data Tables](https://css-tricks.com/responsive-data-tables/) -- `data-label` + `::before` pattern reference (HIGH confidence, matches existing codebase pattern)
- [MDN: CSS clamp()](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/clamp) -- browser support and usage (HIGH confidence)
- [Smashing Magazine: Accessible Tap Target Sizes](https://www.smashingmagazine.com/2023/04/accessible-tap-target-sizes-rage-taps-clicks/) -- touch target sizing research (MEDIUM confidence)

---
*Stack research for: Mobile-responsive WordPress plugin dashboard*
*Researched: 2026-02-15*
