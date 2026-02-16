# Pitfalls Research

**Domain:** Mobile-responsive retrofit of WordPress plugin dashboard (Traffic Portal Link Shortener)
**Researched:** 2026-02-15
**Confidence:** HIGH

## Critical Pitfalls

### Pitfall 1: Hover-Dependent Interactions Invisible on Touch Devices

**What goes wrong:**
The codebase uses `opacity: 0; visibility: hidden` on `.tp-cl-inline-actions` and `.tp-inline-actions`, revealing copy/QR/history buttons only on `:hover`. On mobile touch devices, hover states either do not fire or become "sticky" (the element stays hovered after a tap until another element is tapped). Users on mobile literally cannot see or access the action buttons for each link.

**Why it happens:**
Touch devices have no concept of "hovering" -- a finger either touches or it doesn't. Mobile browsers simulate hover on tap, but the result is a sticky state where the previous row's actions remain visible after tapping away. The existing mobile CSS at `@media (max-width: 768px)` already forces `.tp-cl-inline-actions { opacity: 1; visibility: visible; }`, but this only covers the client-links page, and it uses a pixel breakpoint that misses tablets in landscape and devices between 768-992px.

**How to avoid:**
1. Wrap ALL hover-reveal styles in `@media (hover: hover)` so they only apply to pointer devices.
2. On touch devices (the default without the media query), always show inline action buttons.
3. The existing `@media (max-width: 768px)` override is the right idea but should be the DEFAULT -- hover-hide should be the exception, not the rule.

```css
/* Default: always visible */
.tp-cl-inline-actions {
    opacity: 1;
    visibility: visible;
}

/* Only hide on devices that actually support hover */
@media (hover: hover) {
    .tp-cl-inline-actions {
        opacity: 0;
        visibility: hidden;
    }
    .tp-cl-table tbody tr:hover .tp-cl-inline-actions {
        opacity: 1;
        visibility: visible;
    }
}
```

**Warning signs:**
- QA on a real phone: "I can't find the copy button"
- Action buttons stuck highlighted after tapping a row then scrolling
- User taps row to edit, but the inline-action buttons flash and intercept the tap

**Phase to address:**
Phase 1 (Foundation) -- this must be resolved before any table-to-card conversion work begins, as it determines how action buttons are architecturally presented.

---

### Pitfall 2: CSS Specificity Wars When Adding Mobile Overrides to Desktop-First Styles

**What goes wrong:**
The existing ~1800 lines of CSS across 3 files use a mix of class selectors, nested selectors, and `!important` declarations (17 instances already). Adding responsive overrides via `@media` queries does NOT increase specificity. Developers add mobile styles that silently fail because an earlier desktop rule with higher specificity or `!important` wins. The temptation then is to add MORE `!important` declarations, creating an unwinnable escalation.

**Why it happens:**
Media queries do not change CSS specificity -- they are simply conditional wrappers. A rule like `.tp-cl-domain-row td { padding: .6rem 1rem !important; }` (line 472 in client-links.css) will beat any media query override that doesn't also use `!important`. The codebase already has `!important` on domain group rows, domain row borders, and form inputs -- all areas that need responsive changes.

**How to avoid:**
1. Audit every `!important` in the 3 CSS files BEFORE writing any responsive code. Remove `!important` where possible by restructuring selectors.
2. For the existing `!important` declarations that can't be removed (e.g., overriding Bootstrap defaults), document them with comments explaining why.
3. Never add new `!important` in responsive overrides. If a media query override isn't working, the fix is to lower the specificity of the desktop rule, not raise the specificity of the mobile rule.
4. Use a consistent naming convention for responsive utility classes (e.g., `.tp-mobile-hidden`, `.tp-desktop-only`) instead of complex selector overrides.

**Warning signs:**
- A responsive style "not working" and dev adding `!important` to fix it
- Styles that work in isolation but break when the full stylesheet loads
- Desktop layout breaking after mobile styles are added (a mobile override leaking into desktop because of wrong breakpoint or missing max-width)

**Phase to address:**
Phase 1 (Foundation) -- CSS audit and `!important` cleanup is prerequisite work before writing ANY responsive overrides.

---

### Pitfall 3: Modals Broken by Mobile Virtual Keyboard and 100vh Viewport Issues

**What goes wrong:**
The edit modal (`.tp-cl-modal`, `.tp-edit-modal`) uses `max-height: 90vh` and is centered with `display: flex; align-items: center; justify-content: center` on the overlay. On iOS Safari: (a) `90vh` includes the space behind the browser's address bar, cutting off the bottom of the modal, (b) when the virtual keyboard opens for the form fields inside the modal, Safari does NOT resize the layout viewport -- `position: fixed` elements break and the modal becomes unscrollable, and (c) the form inputs at the bottom of the modal get hidden behind the keyboard with no way to scroll to them.

**Why it happens:**
iOS Safari treats `100vh` (and `90vh`) as the maximum possible viewport height with address bar hidden. When the address bar is showing, the actual visible area is ~70-80px less. Additionally, iOS Safari does not resize the viewport when the virtual keyboard appears -- it overlays the keyboard on top of the existing viewport, pushing fixed-position content upward unpredictably.

**How to avoid:**
1. Use `max-height: 90dvh` (dynamic viewport height) instead of `90vh`. `dvh` adjusts for the browser chrome. Falls back gracefully in older browsers.
2. For the edit modal specifically (which contains form inputs), consider making it a slide-up panel on mobile (`position: fixed; bottom: 0; max-height: 85dvh`) rather than a centered dialog. This keeps form inputs above the keyboard.
3. Add `<meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">` to ensure the viewport resizes when the keyboard appears on supporting browsers.
4. Test on REAL iOS Safari -- simulators do not replicate the viewport/keyboard behavior accurately.

**Warning signs:**
- Modal footer or "Save" button hidden below the fold on mobile
- Form inputs impossible to see when keyboard is open
- Users reporting they "can't submit" on iPhone

**Phase to address:**
Phase 2 (Component Conversion) -- when converting modals to full-screen on mobile. Must test with actual device.

---

### Pitfall 4: Chart.js Canvas Infinite Resize Loop in Flex/Grid Containers

**What goes wrong:**
The Chart.js performance chart (`.tp-cl-chart-wrapper`) uses `responsive: true` and `maintainAspectRatio: false`. When this chart is inside a flex or percentage-sized container (which it is -- the wrapper has no explicit width), resizing the viewport can cause an infinite resize loop: Chart.js resizes the canvas, which changes the container size, which triggers another Chart.js resize. On mobile, this manifests as the chart growing endlessly or performance grinding to a halt.

**Why it happens:**
Chart.js uses its parent container to determine canvas size. Flex containers have an implicit `min-width: auto` that prevents shrinking below content width. When the chart expands the canvas, the container grows to match. Chart.js detects the container size change and resizes again. This is a well-documented Chart.js bug (GitHub issues #5805, #9001).

**How to avoid:**
1. The chart wrapper MUST have an explicit `position: relative` and its flex/grid child must have `min-width: 0` (or `overflow: hidden`).
2. On mobile, consider collapsing the chart by default with a "Show Chart" toggle. This avoids the resize issue entirely AND saves valuable mobile screen space.
3. When the chart IS visible on mobile, give the wrapper an explicit `height` (e.g., `height: 200px`) instead of relying on `min-height`.
4. Call `chart.resize()` explicitly after viewport orientation changes using `matchMedia` instead of relying on the automatic resize observer.

**Warning signs:**
- Chart area growing taller each time the browser is resized
- Performance lag when rotating a mobile device between portrait and landscape
- Console warnings about ResizeObserver loop limits

**Phase to address:**
Phase 2 (Component Conversion) -- when implementing the collapsible chart section for mobile.

---

### Pitfall 5: WordPress Admin Bar Collides with Fixed-Position Modal Overlays

**What goes wrong:**
All modal overlays use `position: fixed; inset: 0; z-index: 10000`. The WordPress admin bar has `z-index: 99999` and is `position: fixed` at the top with height `32px` (desktop) or `46px` (mobile below 783px). When a logged-in user opens a modal: (a) the overlay doesn't account for the admin bar, so the modal appears partially behind it, (b) the close button at the top of the modal may be unreachable behind the admin bar, and (c) on mobile where the admin bar is taller (46px), the problem is worse.

**Why it happens:**
Plugin developers typically test in a logged-out state or forget that WordPress adds a fixed-position admin bar for logged-in users. The admin bar's z-index of 99999 is higher than the plugin's modal z-index of 10000, so the admin bar always wins the stacking context.

**How to avoid:**
1. Use the `.admin-bar` body class to offset modal overlays:
```css
.admin-bar .tp-cl-modal-overlay {
    top: 32px;
}
@media screen and (max-width: 782px) {
    .admin-bar .tp-cl-modal-overlay {
        top: 46px;
    }
}
```
2. Alternatively, set the modal overlay z-index to `100000` (above WordPress admin bar's `99999`).
3. On mobile, when making modals full-screen, the `top` offset is critical because the close button sits at the very top.

**Warning signs:**
- Modal header/close button partially hidden behind the admin toolbar
- Users tap the modal overlay expecting it to close but hit the admin bar instead
- "I can't close the modal" reports from admin/editor users

**Phase to address:**
Phase 1 (Foundation) -- admin bar awareness should be baked into the responsive CSS foundation, not retrofitted later.

---

### Pitfall 6: Breakpoint Inconsistency Across the Three CSS Files

**What goes wrong:**
The three CSS files use subtly different breakpoints:
- `frontend.css`: `991.98px`, `767.98px` (Bootstrap's exact breakpoints)
- `dashboard.css`: `992px`, `768px`, `520px` (rounded values)
- `client-links.css`: `992px`, `768px`, `520px` (rounded values)

A device at exactly `768px` viewport width hits the `max-width: 768px` rule in dashboard/client-links but NOT the `max-width: 767.98px` rule in frontend.css. This means at exactly 768px, the table transforms to cards but the frontend form embedded in the modal does NOT get its responsive adjustments. The form inside the modal looks broken.

**Why it happens:**
Bootstrap 5 uses `.98` breakpoints (e.g., `767.98px`) to avoid overlap with its `min-width: 768px` medium breakpoint. The dashboard and client-links files were written without Bootstrap's conventions, using round numbers. Since the edit modal embeds the frontend form (which uses Bootstrap breakpoints), the two systems conflict at boundary pixels.

**How to avoid:**
1. Standardize ALL breakpoints to Bootstrap 5's values: `575.98px`, `767.98px`, `991.98px`, `1199.98px`.
2. Define breakpoints as CSS custom properties or Sass variables so changes propagate everywhere.
3. Better yet, use Bootstrap 5's breakpoint mixins if a build step exists, or define shared breakpoint values in a comment block at the top of each file.

**Warning signs:**
- Layout looks correct on most phones but "weird" on tablets or specific devices
- Testing only on iPhone/Chrome DevTools presets (which don't test boundary pixels)
- Form elements inside modals not reflowing when the surrounding table does

**Phase to address:**
Phase 1 (Foundation) -- breakpoint standardization must happen before adding new responsive rules, otherwise new code perpetuates the inconsistency.

---

### Pitfall 7: Table-to-Card Conversion Breaks Screen Reader Accessibility

**What goes wrong:**
The current mobile card pattern uses `display: block` on table rows and `td::before { content: attr(data-label); }` to create pseudo-labels. This completely breaks table semantics for screen readers. When `display: block` is applied to `<tr>` and `<td>` elements, assistive technology can no longer parse the table structure -- it sees a flat list of text blocks with no header-cell association. The `data-label` pseudo-element content is NOT read by most screen readers.

**Why it happens:**
The CSS `display` property override strips ARIA table roles from the DOM in most browsers. `content: attr(data-label)` generates CSS pseudo-content, which has inconsistent screen reader support (some read it, some don't -- NVDA and JAWS behave differently).

**How to avoid:**
1. Add `role="table"`, `role="row"`, and `role="cell"` to the relevant elements to preserve table semantics even when display is overridden.
2. Add `aria-label` or visually-hidden `<span>` elements for the data labels instead of relying solely on `::before` pseudo-elements.
3. For each table cell in the JS-rendered rows, include both `data-label` (for visual CSS) AND an `aria-label` attribute (for screen readers):
```html
<td data-label="Link" aria-label="Link">...</td>
```
4. Test with VoiceOver on iOS (the primary mobile screen reader) after conversion.

**Warning signs:**
- VoiceOver reads "blank, blank, blank" on the mobile card layout
- Accessibility audit tools flagging "table with no headers"
- Users with screen readers reporting the mobile view is unusable

**Phase to address:**
Phase 2 (Component Conversion) -- must be addressed simultaneously with the table-to-card CSS work, not as an afterthought.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Using `!important` in media queries to override desktop styles | Quick fix when desktop specificity is too high | Escalating specificity war, unmaintainable CSS | Never -- fix the desktop specificity instead |
| Duplicating CSS across dashboard.css and client-links.css instead of sharing | Avoid touching working code | ~400 lines of near-identical code (containers, modals, pagination, skeletons) means fixing a responsive bug in one file requires fixing it in two | Phase 1 only -- extract shared styles into a common file during foundation work |
| Using `max-height: 90vh` on modals instead of `dvh` units | Works on desktop, simpler | Broken on iOS Safari, cuts off modal content on mobile | Only on desktop-specific overrides within `@media (hover: hover)` |
| Showing chart at full size on mobile | No JS changes needed for chart visibility toggle | Chart dominates mobile screen, pushes table below fold, potential resize loops | Never on mobile -- always collapse or reduce chart on small screens |
| Using `window.innerHeight` JS hack for viewport instead of `dvh` | Works everywhere including older browsers | JS dependency for a CSS concern, layout shift on load | Only as a fallback for browsers without `dvh` support (check caniuse) |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| WordPress admin bar + fixed modals | Ignoring the 32px/46px admin bar when positioning full-screen modals | Use `.admin-bar` body class with responsive top-offset, or z-index above 99999 |
| WordPress theme CSS + plugin CSS | Theme's responsive media queries override plugin styles due to load order | Use high-specificity selectors scoped to plugin container class (`.tp-cl-container`), ensure plugin CSS loads AFTER theme CSS via `wp_enqueue_style` priority |
| Bootstrap 5 responsive utilities + custom CSS | Using Bootstrap's `.d-none .d-md-block` alongside custom media queries with different breakpoints | Stick to one system -- either use Bootstrap utility classes OR custom media queries, never mix |
| Chart.js + CSS containers | Putting Chart.js canvas in a flex child without `min-width: 0` | Wrap canvas in a `position: relative` container with explicit dimensions or `min-width: 0; overflow: hidden` |
| jQuery offset/position + mobile scroll | `$btn.offset()` for tooltip positioning returns wrong values when the page is scrolled on mobile | Use `getBoundingClientRect()` which accounts for scroll position, or use `position: fixed` with `clientX/clientY` from the event |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Loading full Chart.js library on mobile when chart is collapsed | 200KB+ JavaScript parsed even if chart is hidden | Lazy-load Chart.js only when user taps "Show Chart", or use a smaller chart library for mobile | Noticeable on 3G connections, phones with < 4GB RAM |
| CSS `backdrop-filter: blur(8px)` on mobile | Scroll jank, dropped frames, battery drain | Remove or reduce blur on mobile via media query; use solid semi-transparent background instead | Older Android devices, any device with low GPU, long scrolling sessions |
| Re-rendering full table HTML on every page change via jQuery `.append()` | DOM thrashing, slow pagination on 50+ rows | For mobile cards (more DOM nodes than table rows), consider virtual scrolling or limiting page size to 10 on mobile | > 20 cards on mobile, especially with backdrop-filter active |
| Multiple `box-shadow` declarations on card elements | Paint cost on every scroll frame | Simplify shadows to single-layer on mobile, remove from elements inside scroll containers | Scrolling a list of 10+ cards, each with multi-layer box-shadow |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Not applying mobile-specific rate limiting for touch-triggered actions | A tap event fires copy-to-clipboard on every tap without debounce; mobile users accidentally spam API calls by multi-tapping | Debounce action button clicks on mobile (300ms minimum) |
| Modal forms not re-validating CSRF nonce after long idle on mobile | Mobile users often leave tabs open for hours, nonce expires, form submit silently fails or is rejected | Check nonce validity before showing modal form, refresh nonce via AJAX if expired |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Touch targets too small (copy/QR buttons are 0.25rem padding = ~18px) | Users miss taps, frustration, accidentally tap wrong button | Minimum 44x44px touch targets per WCAG 2.5.5; increase padding on mobile to `0.625rem` minimum |
| Date range picker inputs (`type="date"`) too narrow (130px) on mobile | Date picker overlaps other controls, hard to read selected date | On mobile, stack date inputs vertically, or use a single "Date Range" button that opens a bottom sheet |
| Pagination controls too small on mobile (page-link padding: 0.35rem 0.7rem) | Hard to tap specific page numbers, user accidentally hits wrong page | Increase touch padding on mobile, show fewer page numbers (3 max), use larger prev/next arrows |
| Horizontal scroll on table before card breakpoint kicks in (between 520-768px range) | User sees partial table with horizontal scroll rather than cards | Lower the table-to-card breakpoint to 992px (not 768px) to catch tablets in portrait too |
| Copy tooltip positioned using jQuery `.offset()` | Tooltip appears at wrong position on scrolled mobile pages, or off-screen entirely | Use `position: fixed` with `getBoundingClientRect()`, clamp to viewport edges |
| Confirm dialog (`confirm()`) for disabling links | Blocks the JS thread, looks alien on mobile, no custom styling | Use a custom inline confirmation pattern (e.g., button turns red with "Tap again to confirm") |

## "Looks Done But Isn't" Checklist

- [ ] **Table cards on mobile:** Often missing data-label attributes on dynamically-generated rows -- verify EVERY `<td>` in `renderTable()` has `data-label` AND `aria-label`
- [ ] **Modal close behavior:** Often missing "close on back button" -- verify pressing the browser back button on mobile closes the modal instead of navigating away from the page
- [ ] **Touch target sizes:** Often too small after CSS retrofit -- verify ALL interactive elements meet 44x44px minimum using Chrome DevTools "Show Layout Shift Regions" or manual measurement
- [ ] **Orientation change:** Often causes layout glitch -- verify rotating from portrait to landscape and back does NOT break the chart, modal, or table layout
- [ ] **iOS Safari address bar:** Often ignored -- verify the bottom of modals and the full-screen overlay are visible in Safari with the address bar showing AND hidden
- [ ] **WP Admin bar:** Often missing from testing -- verify modals, fixed tooltips, and overlays work correctly for logged-in admin users on mobile
- [ ] **Keyboard navigation in modals:** Often broken on mobile -- verify focus trap works in modals so tab/swipe doesn't escape behind the overlay
- [ ] **Scroll restoration:** Often broken after modal close -- verify page scrolls back to where it was before the modal opened, especially after editing a link
- [ ] **Empty state on mobile:** Often untested -- verify the "No links found" empty state looks correct on mobile (not just the populated state)
- [ ] **Loading skeleton on mobile:** Often the table skeleton doesn't match the card layout -- verify skeleton rows render as cards on mobile, not as table rows

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Specificity wars from `!important` proliferation | MEDIUM | Audit all `!important`, create specificity map, refactor from highest-specificity down. Use CSS layer `@layer` if browser support allows |
| Modals broken on iOS Safari | LOW | Switch from `vh` to `dvh` units, add `interactive-widget` viewport meta, test on real device |
| Chart resize loop | LOW | Add `min-width: 0; overflow: hidden` to flex parent, set explicit height on chart wrapper |
| Accessibility regression from table-to-card | MEDIUM | Add ARIA roles to table elements in JS render function, add visually-hidden span labels, test with VoiceOver |
| Breakpoint inconsistency causing edge-case bugs | LOW | Search-and-replace breakpoint values, standardize to Bootstrap 5's `.98` convention |
| Admin bar overlap with modals | LOW | Add `.admin-bar` offset rules to existing modal CSS |
| Touch targets too small | LOW | Increase padding in mobile media query, no structural changes needed |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Hover-dependent actions | Phase 1: Foundation | Test on real touch device -- all action buttons visible without hovering |
| CSS specificity wars | Phase 1: Foundation | Zero new `!important` added; audit log showing reduced count |
| Modal viewport/keyboard issues | Phase 2: Component Conversion | Test edit modal with keyboard open on iOS Safari -- form fields reachable |
| Chart.js resize loop | Phase 2: Component Conversion | Rotate device 5 times rapidly -- chart stays at correct size |
| WP admin bar collision | Phase 1: Foundation | Open modal as logged-in admin on mobile -- close button fully visible |
| Breakpoint inconsistency | Phase 1: Foundation | All three CSS files use identical breakpoint values |
| Table accessibility | Phase 2: Component Conversion | VoiceOver reads card labels correctly on mobile |
| Touch target sizes | Phase 3: Polish | Accessibility audit passes WCAG 2.5.5 target size |
| Date picker mobile UX | Phase 3: Polish | Date range usable with one hand on a phone |
| Copy tooltip positioning | Phase 2: Component Conversion | Tooltip visible and correctly positioned after scrolling |

## Sources

- [Solving Sticky Hover States with @media (hover: hover) - CSS-Tricks](https://css-tricks.com/solving-sticky-hover-states-with-media-hover-hover/)
- [Chart.js Responsive Charts Documentation](https://www.chartjs.org/docs/latest/configuration/responsive.html)
- [Chart.js Responsive Canvas Grows Indefinitely - GitHub Issue #5805](https://github.com/chartjs/Chart.js/issues/5805)
- [Chart.js Resizing in Flex Containers - GitHub Issue #9001](https://github.com/chartjs/Chart.js/issues/9001)
- [The Trick to Viewport Units on Mobile - CSS-Tricks](https://css-tricks.com/the-trick-to-viewport-units-on-mobile/)
- [New Viewport Units (svh, lvh, dvh) - Ahmad Shadeed](https://ishadeed.com/article/new-viewport-units/)
- [Challenges with Retrofitting Responsive Design - Telerik Blog](https://www.telerik.com/blogs/challenges-with-retrofitting-responsive-design)
- [Understanding the Limitations of a Responsive Retrofit - Diagram](https://www.wearediagram.com/blog/understanding-the-limitations-of-a-responsive-retrofit)
- [WordPress Admin Bar Break Points - Spigot Design](https://spigotdesign.com/wordpress-admin-bar-break-points/)
- [Getting Sticky Headers and WP Admin Bar to Behave - SitePoint](https://www.sitepoint.com/getting-sticky-headers-wordpress-admin-bar-behave/)
- [Methods for Overriding Styles in WordPress - CSS-Tricks](https://css-tricks.com/methods-overriding-styles-wordpress/)
- [Control the Viewport Resize Behavior on Mobile with interactive-widget - HTMHell](https://www.htmhell.dev/adventcalendar/2024/4/)
- [Mobile Safari position: fixed and the virtual keyboard - Medium](https://medium.com/@im_rahul/safari-and-position-fixed-978122be5f29)
- [How to Debug Breakpoint Issues in CSS Media Queries - Medium](https://medium.com/@Adekola_Olawale/how-to-debug-breakpoint-issues-in-css-media-queries-4ba87d25eb69)

---
*Pitfalls research for: Mobile-responsive retrofit of WordPress plugin dashboard*
*Researched: 2026-02-15*
