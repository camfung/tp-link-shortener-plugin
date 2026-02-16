# Roadmap: Traffic Portal — Mobile Responsive Update

## Overview

Transform the Traffic Portal WordPress plugin from desktop-only to fully mobile-friendly. The work moves through CSS architecture cleanup, then responsive forms and modals, then table/card conversion with touch-friendly controls, and finally chart collapse behavior. Each phase builds on the previous: foundation enables component work, forms feed into modals, modals and cards serve both views, and the chart is the final isolated complexity. Total scope: ~250-320 lines CSS + ~20 lines JS across 3 existing CSS files and 1 JS file.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: CSS Foundation** - Standardize breakpoints, clean up specificity, and convert hover patterns for touch devices
- [ ] **Phase 2: Forms and Modals** - Make link creation/edit forms and all modal dialogs fully usable on phone screens
- [ ] **Phase 3: Table Cards and Controls** - Convert link tables to card layout and make pagination, date pickers, and action buttons touch-friendly
- [ ] **Phase 4: Chart Collapse** - Hide performance chart by default on mobile with expand toggle and summary stats bar

## Phase Details

### Phase 1: CSS Foundation
**Goal**: The CSS architecture supports mobile-specific overrides without specificity conflicts or inconsistent breakpoint behavior
**Depends on**: Nothing (first phase)
**Requirements**: FNDTN-01, FNDTN-02, FNDTN-03
**Success Criteria** (what must be TRUE):
  1. All three CSS files (frontend.css, dashboard.css, client-links.css) use identical breakpoint values with Bootstrap 5's .98 convention (e.g., 767.98px, 479.98px)
  2. Responsive media query overrides at 480px are not blocked by any existing `!important` declarations
  3. On a touch device (or using touch device emulation), action buttons on link rows are visible without hovering -- hover-dependent visibility only applies on pointer devices
**Plans**: TBD

Plans:
- [ ] 01-01: TBD
- [ ] 01-02: TBD

### Phase 2: Forms and Modals
**Goal**: Users can create and edit links inside full-screen modals on their phone without scrolling issues, keyboard overlap, or cramped inputs
**Depends on**: Phase 1
**Requirements**: FORM-01, MODAL-01, MODAL-02, MODAL-03
**Success Criteria** (what must be TRUE):
  1. On a phone screen (320px-480px), the link creation/edit form has full-width stacked inputs that are easy to tap and type into
  2. All modals (create, edit, QR, history) occupy the full screen on mobile devices instead of floating centered with margins
  3. On iOS Safari, modals render correctly without content being cut off behind the address bar or hidden by the virtual keyboard (dvh units working)
  4. Modals animate with a slide-up-from-bottom motion on mobile, feeling native to phone interaction patterns
**Plans**: TBD

Plans:
- [ ] 02-01: TBD
- [ ] 02-02: TBD

### Phase 3: Table Cards and Controls
**Goal**: Users can browse, search, and interact with their links on a phone using a card-based layout with touch-friendly controls
**Depends on**: Phase 2
**Requirements**: CARD-01, CARD-02, CARD-03, CTRL-01, CTRL-02
**Success Criteria** (what must be TRUE):
  1. On phone screens (320px-480px), the links table displays as stacked cards with labeled fields instead of a multi-column table
  2. Action buttons (copy, QR, edit, history) are always visible on each card without requiring hover or long-press
  3. All interactive elements on cards -- status toggles, action buttons, pagination controls -- have at least 44px touch targets
  4. Pagination is usable on a phone screen without horizontal overflow (simplified prev/next or adequately spaced page numbers)
  5. Date picker inputs stack vertically and are usable on phone screens without cramped side-by-side layout
**Plans**: TBD

Plans:
- [ ] 03-01: TBD
- [ ] 03-02: TBD

### Phase 4: Chart Collapse
**Goal**: Users see summary stats immediately on mobile without the chart consuming vertical space, and can expand the full chart on demand
**Depends on**: Phase 3
**Requirements**: CHART-01, CHART-02
**Success Criteria** (what must be TRUE):
  1. On mobile, the performance chart is hidden by default and replaced with a compact summary stats bar showing total clicks and QR scans
  2. User can tap a toggle to expand the full chart, and it renders correctly without resize loops or layout breakage
  3. After expanding and collapsing the chart, the page layout remains stable (no content jumping or infinite resize)
**Plans**: TBD

Plans:
- [ ] 04-01: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. CSS Foundation | 0/0 | Not started | - |
| 2. Forms and Modals | 0/0 | Not started | - |
| 3. Table Cards and Controls | 0/0 | Not started | - |
| 4. Chart Collapse | 0/0 | Not started | - |
