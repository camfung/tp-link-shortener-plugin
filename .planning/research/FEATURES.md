# Feature Landscape: Mobile-Responsive Dashboard

**Domain:** Mobile-responsive link management dashboard (WordPress plugin)
**Researched:** 2026-02-15
**Confidence:** MEDIUM-HIGH (patterns well-established; competitor specifics from search only)

## Table Stakes

Features users expect on mobile. Missing any of these means the dashboard is unusable or frustrating on a phone.

### Data Display

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Card layout for link rows (replaces table) | 5-column table is unreadable at 320px-480px. Every major mobile dashboard (Bitly, Rebrandly, Shopify) uses cards for data lists on mobile. Current CSS already starts this at 768px but needs refinement for phone-sized screens. | Medium | Already partially implemented in `client-links.css` lines 826-887 and `dashboard.css` lines 556-631. Current implementation uses `data-label` pseudo-elements, but cards need tighter layout, better hierarchy, and adequate spacing at 320px. |
| Primary info visible without interaction | Users must see link keyword, click count, and status at a glance on each card without tapping. Bitly shows link name + click count on the card face. | Low | Prioritize: keyword (large, tappable), total clicks (prominent number), status indicator (color dot or badge). Destination URL and date are secondary. |
| Touch-friendly action buttons (min 44x44px) | Apple HIG mandates 44pt minimum; Google Material says 48dp. Current inline action buttons are ~28px. Users will mis-tap copy/QR/history buttons at their current size. | Low | Current `.tp-cl-inline-btn` has `padding: .25rem .35rem` and `font-size: .78rem` -- far below 44px touch target. Must increase on mobile. |
| Always-visible actions on mobile (no hover) | Hover states do not exist on touch screens. Current copy/QR/history buttons use hover-reveal (`opacity: 0` until `tr:hover`). Mobile users will never see them. | Low | Already partially fixed: `client-links.css` line 867 sets `opacity: 1; visibility: visible` at 768px breakpoint. Verify this works at 320px and that buttons are reachable. |
| Full-screen modals on mobile | Centered modals with `max-width: 90vw` leave thin margins that feel cramped. Bitly, Rebrandly, and every modern mobile app use full-screen or bottom-sheet modals for create/edit flows. | Medium | Current modals: `.tp-cl-modal` at `width: 900px; max-width: 90vw`. At 520px breakpoint changes to `95vw`. Should go to `100vw; 100vh` with slide-up animation, top-left close/back button. |
| Scrollable modal body (not page) | Edit forms inside modals must scroll within the modal, not cause the background page to scroll. Critical for forms with multiple fields. | Low | Current modals have `max-height: 90vh; overflow-y: auto` which is correct. On mobile full-screen, change to `100vh` with fixed header. |
| Stacked filter controls | Current filter bar (search + date range + status dropdown + add button) is a flex row. On mobile, these must stack vertically with full-width inputs. | Low | Already partially implemented at 992px breakpoint (`flex-direction: column`). Needs verification at 320px that inputs don't overflow. |
| Readable pagination | Current pagination shows page numbers 1-26. On a phone, 5+ page buttons overflow. Must simplify to prev/next with current page indicator. | Low | Reduce visible page numbers to 3 max on mobile, or switch to simple prev/next arrows with "Page 1 of 26" text. |

### Interaction Patterns

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Tap card to edit (full card is tap target) | On desktop, clicking a table row opens edit. On mobile, the entire card should be tappable to open the edit modal. This is how Bitly's mobile app works -- tap a link card to see details/edit. | Low | Already implemented via `$tbody.on('click', 'tr[data-mid]', ...)`. Cards are `<tr>` elements, so this carries over. Just ensure the tap target area is clear. |
| Tap-to-copy short link | Most common action for link managers. Bitly highlights this with prominent "tap to copy" on mobile. Must work without hover tooltips. | Low | Copy functionality exists. On mobile, replace hover tooltip with inline "Copied!" feedback text that appears briefly next to the button, since fixed-position tooltips can mis-position on mobile. |
| Status toggle with adequate size | Toggle switches are 38x20px currently. Too small for reliable thumb tapping. | Low | Increase to at least 48x26px on mobile. The toggle is a critical action (enable/disable links). |
| Pull-to-refresh or visible refresh button | Mobile users expect either pull-to-refresh or a clearly visible refresh mechanism. Current refresh button is small and easy to miss. | Low | Make the refresh button more prominent on mobile, or rely on auto-refresh after actions (already happens on save/toggle). |

### Chart

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Chart collapsed by default on mobile | Performance chart takes 220px+ vertical space. On a 667px-tall phone screen, that is one-third of the viewport consumed before the user sees their links. Bitly and analytics dashboards collapse charts on mobile. | Medium | Add a collapsible wrapper with a summary bar showing total clicks/scans. Tap to expand. Default state: collapsed. Chart.js handles `responsive: true` already but needs resize trigger on expand. |
| Chart readable at small widths | Bar chart x-axis labels (link keywords) truncate poorly at narrow widths. Chart needs horizontal bars or limited labels at mobile sizes. | Low | Chart.js `maintainAspectRatio: false` is already set. May need to switch to horizontal bars on mobile or limit to top 3-5 links. Truncate labels to 8-10 chars. |

### Forms

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Full-width form inputs on mobile | Destination URL and Magic Keyword inputs must span 100% width. No side-by-side layout at phone sizes. | Low | The shortener form likely already does this given Bootstrap's responsive grid, but verify inside the modal context. |
| Large submit/save buttons | Primary action buttons must be full-width and at least 48px tall on mobile. Easy thumb reach at bottom of screen. | Low | Style override for `.tp-btn` inside modals on mobile breakpoints. |

### QR Code Dialog

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| QR code dialog works on mobile | QR code must be visible and scannable on mobile screen. Download/copy/open actions must have adequate touch targets. | Low | Current `.tp-cl-qr-dialog` has `min-width: 320px; max-width: 90vw`. At 520px breakpoint switches to `width: 90vw`. QR buttons go to `flex-direction: column`. This is reasonable. Verify QR canvas renders at appropriate size. |
| QR code auto-sized for screen | QR code canvas should not overflow on small screens. Should be ~200px on phones. | Low | Check that QR generation respects container width. |


## Differentiators

Features that would set the mobile experience apart. Not expected, but create a polished native-app-like feel.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Bottom sheet modals (slide up from bottom) | Feels native on iOS/Android. NNGroup research shows bottom sheets outperform centered modals on mobile for focused tasks. Users can peek at background content. | Medium | Requires changing modal positioning from `align-items: center` to `align-items: flex-end` on mobile, with slide-up animation. Current animation is already `slideUp` which is a good start, but origin should be screen bottom, not just fade-up. |
| Swipe actions on link cards | Swipe left to reveal quick actions (copy, QR, delete/disable). Common in iOS Mail, Slack, Bitly mobile. Reduces tap count for power users. | High | Requires JavaScript touch event handling (touchstart/touchmove/touchend). Not worth the complexity for v1. Consider for v2 if mobile usage is significant. |
| Summary stats bar above card list | Show "127 links / 482 clicks / 31 QR scans" as a compact stats strip above the card list. Gives context without the full chart. | Low | Pull totals from existing data. Display as a simple flex row of stat pills. This replaces the chart as default view on mobile. |
| Sticky "Add Link" FAB (floating action button) | Material Design pattern: a floating + button at bottom-right that stays visible as user scrolls. Primary creation action always one tap away. | Low-Medium | Position fixed, bottom-right, 56px circular button. Hides on scroll-down, shows on scroll-up. Simple CSS + minimal JS. |
| Infinite scroll (replace pagination on mobile) | Tapping tiny page numbers is painful on phones. Infinite scroll with "load more" button at bottom is more natural for mobile. Bitly uses infinite scroll in their mobile app. | Medium | Requires changing `loadData()` to append rather than replace. Add intersection observer or "Load more" button. Keep server-side pagination, just change client rendering. |
| Haptic feedback on copy | Subtle vibration on successful copy action. Tiny detail that feels premium. | Low | `navigator.vibrate(50)` -- one line of code. Only fires if Vibration API is available. Progressive enhancement. |
| Collapsible domain groups | On mobile, group headers become collapsible accordions. Tap domain header to show/hide its links. Useful when user has links across multiple domains. | Medium | Requires JS to toggle visibility of child rows per domain. Add chevron icon to domain row. Store collapsed state in sessionStorage. |
| Search with debounce + clear "X" button | Already exists in desktop. On mobile, ensure the search input has a visible clear button and that the soft keyboard does not obscure results. | Low | Already implemented. Verify keyboard behavior does not push content off-screen. Consider `position: sticky` for the search bar. |


## Anti-Features

Things to explicitly NOT build or do on mobile. These seem tempting but degrade the mobile experience.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Horizontal scrolling table | Wrapping the table in `.table-responsive` and letting it scroll horizontally is Bootstrap's default. Users hate horizontal scrolling on mobile -- it conflicts with back-swipe gestures, is hard to discover, and hides data. Research consistently shows this is the worst mobile table pattern. | Use card layout. Each row becomes a card with stacked fields. Already partially implemented. |
| Pinch-to-zoom chart | Letting users pinch-zoom into the Chart.js chart sounds useful but creates a terrible experience: the chart escapes viewport, interferes with page zoom, and is nearly impossible to use with one hand. | Collapse chart by default. When expanded, show simplified version with fewer data points. Tap a bar to see details in a tooltip/popover. |
| Desktop hover tooltips on mobile | `.tp-cl-copy-tooltip` uses `position: fixed` and calculates position from `$btn.offset()`. On mobile, this often mis-positions (keyboard open, scroll offset, viewport changes). Hover-triggered tooltips are invisible on touch. | Replace with inline feedback: change button icon to checkmark for 1.5s, or show "Copied!" text next to the button. |
| Sortable column headers on mobile | Table headers are hidden on mobile (card layout). The sort mechanism via clicking `<th>` elements disappears. Trying to add sort controls to each card field is visual clutter. | Add a sort dropdown above the card list (like the dashboard already has with `$sortOrder`). Single select: "Sort by: Clicks / Date / Name". |
| Cramming all filters on screen at once | Showing date range (2 inputs + button), search, status dropdown, and add button all stacked takes 200px+ of vertical space before any content appears. | Use a filter icon/button that opens a filter sheet/drawer. Show active filter count as badge. Search stays visible (most-used); other filters behind the icon. |
| Confirmation dialogs for toggle | The current `confirm()` dialog for disabling links is jarring on mobile and blocks the UI thread. | Use an undo pattern instead: toggle immediately, show "Link disabled. Undo" toast for 5 seconds. More mobile-friendly, fewer taps. |
| Multiple open modals/overlays | QR dialog can open while edit modal is open. Stacked overlays on a small screen are disorienting. | Close any existing modal/overlay before opening a new one. One overlay at a time on mobile. |
| Pixel-precise positioning for tooltips/popovers | `$copyTooltip.css({ top: off.top - 40, left: ... })` breaks when viewport shifts (keyboard, scroll, orientation change). | Use CSS-based positioning (transform anchored to button) or replace with inline state changes. |


## Feature Dependencies

```
Card Layout (table stakes) --> Always-visible actions (no hover on touch)
Card Layout --> Tap card to edit (tap target is the card)
Card Layout --> Sort dropdown replaces sortable headers
Full-screen modals --> Scrollable modal body
Full-screen modals --> Full-width form inputs inside modal
Chart collapsed by default --> Summary stats bar (provides context when chart hidden)
Filter stacking --> Filter icon/drawer (differentiator, declutters stacked filters)
Summary stats bar --> Chart collapsed by default (summary replaces chart at glance)
Collapsible domain groups --> Card layout (groups need card context)
Infinite scroll --> Card layout (pagination alternative)
Bottom sheet modals --> Full-screen modals (bottom sheet IS the mobile modal pattern)
```


## MVP Recommendation

Build these first -- they make the dashboard functional on phones:

1. **Card layout refinement (320px-480px)** -- Current partial implementation needs: proper field hierarchy, adequate spacing, 44px+ touch targets on action buttons
2. **Full-screen modals on mobile** -- Change modal sizing from `max-width: 90vw` to `100vw/100vh` with slide-up from bottom, fixed header with close button
3. **Chart collapsed by default** -- Add collapsible wrapper, summary stats bar, expand on tap
4. **Filter bar reorganization** -- Search stays visible, other filters behind filter icon/button, add-link button remains prominent
5. **Pagination simplification** -- Prev/next only with page counter, or "Load more" button
6. **Touch target enlargement** -- All interactive elements to 44px+ on mobile breakpoints
7. **Remove hover dependencies** -- Actions always visible, tooltips replaced with inline feedback

**Defer:** Swipe actions (High complexity, v2), infinite scroll (Medium complexity, requires refactoring loadData), haptic feedback (trivial but depends on copy flow changes), collapsible domain groups (nice-to-have).

**Prioritization rationale:** Items 1-3 address the three biggest mobile pain points: unreadable data, unusable modals, and wasted vertical space. Items 4-7 are smaller fixes that make the difference between "works on mobile" and "feels good on mobile."


## Complexity Budget

| Category | Items | Estimated Effort |
|----------|-------|-----------------|
| CSS-only changes (media queries) | Card refinement, filter stacking, touch targets, pagination, toggle sizing | ~60% of work |
| CSS + minor HTML | Full-screen modals, chart collapse toggle, filter icon | ~25% of work |
| JavaScript changes | Chart expand/collapse, sort dropdown, copy feedback, modal stacking prevention | ~15% of work |

The majority of this milestone is CSS media queries, which is consistent with the brownfield constraint (additive CSS, minimal HTML restructuring).


## Existing Responsive State (What is Already Built)

The codebase is NOT starting from zero. Key responsive rules already exist:

| Breakpoint | File | What It Does | Gaps |
|------------|------|-------------|------|
| 992px | client-links.css, dashboard.css | Header stacks vertically, controls go column, search goes full-width | Works for tablets. No phone-specific refinement. |
| 768px | client-links.css, dashboard.css | **Table head hidden, rows become block-level cards** with `data-label` pseudo-elements, actions always visible, pagination stacks, domain rows become cards | Good foundation. But cards need hierarchy refinement, spacing is too generous for 320px, no chart handling. |
| 520px | client-links.css, dashboard.css | Modals go to 95vw, QR dialog buttons stack vertically | Close but not full-screen. No slide-up animation. |
| 768px (frontend.css) | frontend.css | Card border-radius reduced, title font smaller, action buttons shrink, QR container smaller | Minimal. Form layout needs verification in modal context. |

**Key gap:** No breakpoint below 520px for truly small phones (320px iPhone SE). The 768px card layout was designed for tablets, not phones. Spacing, font sizes, and touch targets all need phone-specific overrides.


## Sources

- [User-Friendly Mobile Data Tables (Medium/Bootcamp)](https://medium.com/design-bootcamp/designing-user-friendly-data-tables-for-mobile-devices-c470c82403ad) -- MEDIUM confidence
- [Intuitive Mobile Dashboard UI (Toptal)](https://www.toptal.com/designers/dashboard-design/mobile-dashboard-ui) -- MEDIUM confidence
- [Tables Best Practice for Mobile UX (WebOsmotic)](https://webosmotic.com/blog/tables-best-practice-for-mobile-ux-design/) -- MEDIUM confidence
- [Bottom Sheets: Definition and UX Guidelines (NN/g)](https://www.nngroup.com/articles/bottom-sheet/) -- HIGH confidence (NNGroup is authoritative UX research)
- [Mobile Filter UX Design Patterns (Pencil & Paper)](https://www.pencilandpaper.io/articles/ux-pattern-analysis-mobile-filters) -- MEDIUM confidence
- [Bitly Mobile App (App Store)](https://apps.apple.com/us/app/bitly-link-shortener/id525106063) -- HIGH confidence (primary source)
- [Rebrandly Mobile App (Google Play)](https://play.google.com/store/apps/details?id=com.rebrandlynative&hl=en_US) -- HIGH confidence (primary source)
- [Table vs List vs Cards Pattern Guide (UX Patterns)](https://uxpatterns.dev/pattern-guide/table-vs-list-vs-cards) -- MEDIUM confidence
- Existing codebase analysis: `client-links.css`, `dashboard.css`, `frontend.css`, `client-links.js`, `dashboard.js` -- HIGH confidence (direct source inspection)
- `docs/client-links-ui-critique.md` -- HIGH confidence (project documentation)
- `docs/DASHBOARD-UI-REQUIREMENTS.md` -- HIGH confidence (project documentation)
