# Architecture Patterns

**Domain:** Mobile-responsive CSS for existing WordPress plugin (Traffic Portal Link Shortener)
**Researched:** 2026-02-15
**Overall confidence:** HIGH

## Current Architecture Analysis

### Existing CSS File Structure

| File | Lines | Scope | Existing Breakpoints |
|------|-------|-------|---------------------|
| `frontend.css` | ~1022 | Link creation form, QR codes, snackbars, result panel | 991.98px, 767.98px |
| `dashboard.css` | ~1024 | Admin dashboard table, search/filters, edit modal, QR dialog, skeletons | 992px, 768px, 520px |
| `client-links.css` | ~921 | Client links table, chart, date range, history modal, status toggles | 992px, 768px, 520px |

### Current Breakpoint Map

```
992px  - Header stacking, controls column layout (dashboard.css, client-links.css)
768px  - Table-to-card conversion, padding reduction, skeleton mobile (all three files)
520px  - Modal full-width, QR dialog stacking (dashboard.css, client-links.css)
```

**What is already done at 768px:** Table thead hidden, tr becomes block with card styling, td uses `data-label` pseudo-element for labels, inline actions always visible, pagination stacked, domain group rows converted to cards.

**What is NOT done:** No styles below 520px targeting phones (320px-480px). No adjustments to the frontend form. No chart collapsibility. No full-screen modal behavior. No touch-optimized tap targets.

### Existing Design System (CSS Custom Properties)

All three files redeclare identical `:root` variables. The design tokens are:
- Colors: `--tp-primary`, `--tp-accent`, `--tp-secondary`, `--tp-warning`, `--tp-danger`
- Surfaces: `--tp-surface`, `--tp-surface-soft`, `--tp-section`
- Text: `--tp-text`, `--tp-muted`
- Layout: `--tp-border`, `--tp-shadow`

### HTML Architecture

- Tables are dynamically built in JavaScript (`dashboard.js`, `client-links.js`) using template literals/string concatenation
- Table cells already include `data-label` attributes on every `<td>` (critical for card layout)
- Modals are plain DOM overlays (not Bootstrap modals), positioned with `position: fixed` and centered with flexbox
- Chart uses `<canvas>` inside `.tp-cl-chart-wrapper`
- Form uses nested flex/grid with `.tp-input-visual` wrappers

---

## Recommended Architecture

### Decision: Inline Media Queries in Existing Files (NOT a Separate Mobile CSS File)

**Recommendation:** Add mobile breakpoints directly into each existing CSS file, co-located with the component styles they modify.

**Why NOT a separate `mobile.css` file:**

1. **Maintenance burden.** A separate file creates a second place to look for every component's styles. When someone modifies `.tp-cl-link-cell` in `client-links.css`, they must also remember to check `mobile.css`. This coupling across files leads to drift and bugs.
2. **Existing pattern.** The codebase already has responsive rules at 992px, 768px, and 520px inline within each file. A separate mobile file would break the established convention.
3. **Specificity alignment.** Inline media queries naturally appear after the desktop styles they override, maintaining correct cascade order without cross-file specificity battles.
4. **No build step.** This is a WordPress plugin with raw CSS files (no Sass, no PostCSS, no bundler). A separate file means another `wp_enqueue_style()` call, another HTTP request, and no tooling to merge them.

**Why inline media queries work here:**

- Files are already ~1000 lines each. Adding 100-150 lines of mobile rules per file keeps them under 1200 lines -- manageable.
- Each file maps to a distinct page/view (frontend form, dashboard, client links). Mobile styles for dashboard components belong in `dashboard.css`.
- The `data-label` card pattern already exists at 768px. The phone breakpoint extends this pattern, not replaces it.

### Breakpoint Strategy

**Add one new breakpoint at 480px** to each file. Do NOT add breakpoints at 320px, 375px, or 414px individually.

```
Existing:
  992px  - Tablet landscape (header/controls stacking)
  768px  - Tablet portrait (table-to-card, padding reduction)
  520px  - Small tablet/large phone (modal sizing)

New:
  480px  - Phone (all phone-specific optimizations)
```

**Rationale for a single 480px breakpoint:**
- Covers all phones (320px-480px) with one rule set
- Below 480px, layout is already single-column from the 768px rules; the 480px rules handle sizing, spacing, and touch optimization
- Avoids breakpoint sprawl that creates testing burden
- CSS `clamp()` and percentage-based sizing handle the 320px-480px range fluidly within this single breakpoint

**Implementation pattern in each file:**

```css
/* ---- Existing ---- */
@media (max-width: 992px) { /* tablet landscape */ }
@media (max-width: 768px) { /* tablet portrait - card layout */ }
@media (max-width: 520px) { /* small screens - modal sizing */ }

/* ---- New ---- */
@media (max-width: 480px) { /* phone - touch targets, spacing, full-screen modals */ }
```

### Component Boundaries

| Component | File | What Changes for Mobile |
|-----------|------|------------------------|
| Link creation form | `frontend.css` | Input stacking, button sizing, result grid single-column, QR/screenshot stack |
| Dashboard table | `dashboard.css` | Card padding reduction, touch targets, full-screen edit modal |
| Dashboard controls | `dashboard.css` | Search/filter full-width, button touch sizing |
| Client links table | `client-links.css` | Card padding, toggle touch area, inline actions layout |
| Client links controls | `client-links.css` | Date range stacking, search full-width |
| Chart | `client-links.css` | Collapsible wrapper, reduced height |
| Modals (edit/QR/history) | `dashboard.css`, `client-links.css` | Full-screen on phones |
| Pagination | `dashboard.css`, `client-links.css` | Simplified (prev/next only) |
| Snackbar/tooltips | `frontend.css` | Full-width at bottom |

### Data Flow (CSS Cascade)

```
:root variables (shared tokens)
    |
    v
Desktop styles (base rules, no media query)
    |
    v
@media (max-width: 992px)  -- tablet landscape adjustments
    |
    v
@media (max-width: 768px)  -- tablet portrait, table-to-card
    |
    v
@media (max-width: 520px)  -- modal/dialog sizing
    |
    v
@media (max-width: 480px)  -- phone-specific (NEW)
```

Each breakpoint inherits and extends the one above it. The 480px rules only need to specify what changes FROM the 768px/520px state, not re-declare everything.

---

## Patterns to Follow

### Pattern 1: Extend Existing Card Layout for Phones

The 768px breakpoint already converts tables to cards using `display: block` on `<tr>` and `data-label` pseudo-elements. The 480px breakpoint should refine spacing and touch targets within this existing card structure.

**What:** Reduce card padding, increase tap target sizes, adjust font sizes.
**When:** Adding phone-specific table card styles.
**Example:**

```css
/* In dashboard.css */
@media (max-width: 480px) {
    .tp-dashboard-table tbody tr {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .tp-dashboard-table tbody td {
        padding: 0.4rem 0;
        font-size: 0.85rem;
    }

    /* Larger touch targets for action buttons */
    .tp-inline-btn {
        min-width: 44px;
        min-height: 44px;
        padding: 0.5rem;
        font-size: 0.9rem;
    }
}
```

### Pattern 2: Full-Screen Modals on Phones

Custom modals (not Bootstrap) use `position: fixed` with centered flex. On phones, they should fill the viewport.

**What:** Convert centered modals to full-screen sheets on phones.
**When:** All modal/dialog overlays at 480px.
**Example:**

```css
@media (max-width: 480px) {
    .tp-edit-modal,
    .tp-cl-modal {
        width: 100vw;
        max-width: 100vw;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
        margin: 0;
    }

    .tp-edit-modal-overlay,
    .tp-cl-modal-overlay {
        align-items: stretch; /* fills viewport instead of centering */
    }
}
```

### Pattern 3: Collapsible Chart Wrapper

The chart canvas has a known issue: if hidden on initial render, it renders at 0x0. The solution is to render it, then allow toggle.

**What:** Add a collapse/expand toggle for the chart section on mobile.
**When:** Client links page on phones.
**How:** CSS hides by default at 480px. JavaScript adds a toggle button that shows/hides and calls `chart.resize()` on show.

```css
/* In client-links.css */
@media (max-width: 480px) {
    .tp-cl-chart-wrapper {
        display: none; /* Hidden by default on phone */
    }

    .tp-cl-chart-wrapper.tp-cl-chart-expanded {
        display: block;
    }

    .tp-cl-chart-toggle {
        display: block; /* Only visible on phone */
    }
}

/* Desktop: toggle button hidden */
.tp-cl-chart-toggle {
    display: none;
}
```

**JavaScript requirement:** When `.tp-cl-chart-expanded` is added, call `state.chart.resize()` to re-render at correct dimensions.

### Pattern 4: Touch-Friendly Tap Targets

WCAG 2.2 requires 24x24px minimum, Apple recommends 44x44px for tap targets.

**What:** Ensure all interactive elements meet 44x44px minimum on phones.
**When:** All buttons, links, toggles at 480px.
**Example:**

```css
@media (max-width: 480px) {
    .tp-cl-toggle {
        width: 48px;
        height: 26px;
    }

    .tp-cl-toggle-slider::before {
        width: 22px;
        height: 22px;
    }

    .tp-cl-inline-btn,
    .tp-inline-btn {
        min-width: 44px;
        min-height: 44px;
    }
}
```

### Pattern 5: Pagination Simplification

On phones, numbered pagination is wasteful. Show only prev/next with page indicator.

**What:** Hide numbered page links, show only prev/next arrows.
**When:** Pagination at 480px.
**Approach:** CSS-only; hide `.page-item` except first, last, and active. JavaScript unchanged.

```css
@media (max-width: 480px) {
    .tp-dashboard-pagination .page-item:not(:first-child):not(:last-child):not(.active) {
        display: none;
    }

    .tp-pagination-info {
        font-size: 0.8rem;
        text-align: center;
    }
}
```

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: Creating a Separate mobile.css File

**What:** Putting all phone styles in a new `assets/css/mobile.css` file.
**Why bad:** Breaks the established file-per-view convention. Creates maintenance burden where every component change requires checking two files. Adds an extra HTTP request in a plugin with no build tooling.
**Instead:** Add `@media (max-width: 480px)` blocks at the bottom of each existing CSS file.

### Anti-Pattern 2: Using `!important` to Override Desktop Styles

**What:** Overriding desktop styles with `!important` in mobile media queries.
**Why bad:** The cascade already handles this. Media queries at the bottom of the file naturally override earlier rules at the same specificity. Using `!important` creates specificity debt.
**Instead:** Match the same selector specificity inside the media query. If the desktop rule is `.tp-cl-table tbody td`, use the same selector in the mobile query.

### Anti-Pattern 3: Duplicating the Table-to-Card Pattern

**What:** Re-implementing `display: block`, `data-label::before`, etc. at 480px when it already exists at 768px.
**Why bad:** The 768px rules already handle the card conversion. The 480px rules inherit this. Duplicating creates maintenance burden and potential conflicts.
**Instead:** The 480px breakpoint should ONLY adjust sizing, spacing, and touch targets within the already-converted card layout.

### Anti-Pattern 4: JavaScript-Based Responsive Layout

**What:** Using JavaScript to detect screen width and toggle classes for layout changes.
**Why bad:** Flash of unstyled content on load. Doesn't respond to resize/rotation. Race condition with DOM rendering.
**Instead:** Use CSS media queries for all layout changes. Only use JavaScript for behavioral changes (chart toggle requiring `.resize()`, programmatic scroll management).

### Anti-Pattern 5: Using Bootstrap Responsive Utilities Extensively

**What:** Relying on Bootstrap's `d-none d-md-block` classes to show/hide elements at breakpoints.
**Why bad:** These are viewport-based (Bootstrap uses media queries internally), but they create tight coupling between HTML and responsive behavior. Changes require editing PHP templates (server-side rendered) instead of CSS.
**Instead:** Use custom CSS media queries. The existing codebase barely uses Bootstrap's responsive utilities and this should continue.

### Anti-Pattern 6: Hiding Table Columns Selectively

**What:** Hiding the "Date" or "Usage" column on mobile to make the table fit.
**Why bad:** The card layout already eliminates the column constraint. Every column becomes a labeled row inside the card. Hiding data removes information the user may need.
**Instead:** The card layout shows all data stacked vertically. Prioritize layout within the card (most important fields first) rather than removing fields.

---

## Component Conversion Order (Build Dependencies)

The order matters because some components depend on others being responsive first.

### Phase 1: Foundation (No Dependencies)

**File: `frontend.css` -- Link creation form**

The form exists independently and is embedded inside modals in dashboard and client-links views. Making it responsive first means the modals automatically inherit form responsiveness.

Changes:
- Input visual stacking at 480px (paste button + input + submit should not overflow)
- Result grid single-column (QR + screenshot stacking)
- QR dialog full-screen
- Snackbar full-width

**Why first:** The form is used inside edit/add modals on both dashboard and client-links pages. If the form is responsive, the modal content is automatically responsive.

### Phase 2: Dashboard (Depends on Phase 1)

**File: `dashboard.css` -- Dashboard view**

Changes:
- Controls area: search full-width, filters stack, button sizes increase
- Table cards: padding/spacing refinement for phones
- Edit modal: full-screen, which contains the form from Phase 1
- QR dialog: full-screen
- Pagination: simplified prev/next

**Why second:** The edit modal embeds the frontend form. Phase 1 makes the form responsive; Phase 2 makes the modal container responsive. Together they work.

### Phase 3: Client Links (Depends on Phase 1, builds on Phase 2 patterns)

**File: `client-links.css` -- Client links view**

Changes:
- Chart: collapsible toggle (requires JavaScript addition)
- Controls area: date range stacking, search full-width
- Table cards: same pattern as dashboard but with status toggle sizing
- Status toggle: touch-friendly sizing
- All modals (edit, history, QR): full-screen
- Pagination: simplified

**Why third:** This view has the most components (chart + table + 3 modals + date range). It benefits from patterns established in Phase 2. The chart collapse requires a small JavaScript addition, making it the most complex phase.

### Phase 4: Cross-Cutting Polish

**All files:**
- Verify touch targets meet 44px minimum across all interactive elements
- Test all animation/transitions perform well on mobile (reduce motion preference)
- Verify no horizontal overflow at 320px (smallest target)
- Add `@media (prefers-reduced-motion: reduce)` rules for mobile animations

### Dependency Graph

```
Phase 1: frontend.css (form)
    |
    +---> Phase 2: dashboard.css (uses form in modal)
    |         |
    |         +---> Phase 4: Cross-cutting polish
    |
    +---> Phase 3: client-links.css (uses form in modal, extends Phase 2 patterns)
              |
              +---> Phase 4: Cross-cutting polish
```

---

## JavaScript Changes Required

Most mobile responsiveness is CSS-only, but three areas require JavaScript:

### 1. Chart Toggle Button (client-links.js)

**What:** Add a toggle button before `.tp-cl-chart-wrapper` that shows/hides the chart on mobile.
**Why JavaScript:** Chart.js renders at 0x0 if its container is hidden. After showing, `chart.resize()` must be called.
**Scope:** ~15 lines of JS in `client-links.js` `init()` function.

```javascript
// Add chart toggle for mobile
var $chartToggle = $('<button class="btn btn-sm btn-outline-primary tp-cl-chart-toggle" type="button">' +
    '<i class="fas fa-chart-line me-1"></i> Toggle Chart</button>');
$chartWrapper.before($chartToggle);
$chartToggle.on('click', function() {
    $chartWrapper.toggleClass('tp-cl-chart-expanded');
    if ($chartWrapper.hasClass('tp-cl-chart-expanded') && state.chart) {
        state.chart.resize();
    }
});
```

### 2. Inline Actions Always Visible (already done)

The CSS at 768px already sets `.tp-inline-actions { opacity: 1; visibility: visible; }`. No JS change needed. This carries forward to 480px.

### 3. Modal Scroll Lock (optional enhancement)

**What:** Prevent body scrolling when full-screen modal is open on mobile.
**Scope:** Already partially handled. The existing modal open/close JS could add `document.body.style.overflow = 'hidden'` on open and restore on close.

---

## File Organization Summary

```
assets/css/
  frontend.css      (+50-80 lines at bottom: @media max-width 480px block)
  dashboard.css     (+80-120 lines at bottom: @media max-width 480px block)
  client-links.css  (+80-120 lines at bottom: @media max-width 480px block)

assets/js/
  client-links.js   (+15-20 lines: chart toggle button creation and handler)
```

**No new files created.** All changes are additions to existing files.

**Total estimated CSS additions:** 210-320 lines across 3 files.
**Total estimated JS additions:** ~20 lines in 1 file.

---

## Scalability Considerations

| Concern | Current (Desktop Focus) | After Mobile Phase | Future Consideration |
|---------|------------------------|-------------------|---------------------|
| CSS file size | ~3000 lines total | ~3250 lines total | If files exceed 1500 lines, consider CSS custom properties for breakpoint values |
| Breakpoint consistency | 3 breakpoints, inconsistent (991.98 vs 992) | 4 breakpoints, standardized | Consider container queries for component-level responsiveness in future |
| Testing surface | Desktop + tablet | Desktop + tablet + phone | Playwright mobile viewport tests recommended |
| Performance | 3 CSS files loaded | Same 3 files, marginally larger | No performance impact; mobile CSS is inside existing files |

---

## Sources

- Codebase analysis: `assets/css/frontend.css`, `assets/css/dashboard.css`, `assets/css/client-links.css`
- Codebase analysis: `templates/dashboard-template.php`, `templates/client-links-template.php`, `templates/shortcode-template.php`
- Codebase analysis: `assets/js/dashboard.js`, `assets/js/client-links.js`
- Codebase analysis: `includes/class-tp-assets.php`
- [CSS-Tricks: Responsive Data Tables](https://css-tricks.com/responsive-data-tables/) -- data-label card pattern (HIGH confidence)
- [Chart.js: Responsive Charts](https://www.chartjs.org/docs/latest/configuration/responsive.html) -- canvas resize behavior (HIGH confidence)
- [Chart.js Issue #762: Bootstrap collapse interaction](https://github.com/chartjs/Chart.js/issues/762) -- hidden container rendering (HIGH confidence)
- [Josh W. Comeau: Container Queries Unleashed](https://www.joshwcomeau.com/css/container-queries-unleashed/) -- container vs media queries guidance (MEDIUM confidence)
- [MDN: Media query fundamentals](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Media_queries) -- media query best practices (HIGH confidence)
- [LogRocket: CSS breakpoints for responsive design](https://blog.logrocket.com/css-breakpoints-responsive-design/) -- breakpoint strategy (MEDIUM confidence)
- [FreeCodeCamp: Media Queries vs Container Queries](https://www.freecodecamp.org/news/media-queries-vs-container-queries/) -- when to use each (MEDIUM confidence)
