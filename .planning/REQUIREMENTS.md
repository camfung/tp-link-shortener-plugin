# Requirements: Traffic Portal — Mobile Responsive Update

**Defined:** 2026-02-15
**Core Value:** Users can fully manage their short links from a phone — create, edit, toggle, view analytics, and scan QR codes — without needing a desktop.

## v1 Requirements

Requirements for mobile responsive update. Each maps to roadmap phases.

### CSS Foundation

- [ ] **FNDTN-01**: Standardize breakpoints across all 3 CSS files (consistent 768px, add 480px phone breakpoint)
- [ ] **FNDTN-02**: Audit and remove/reduce `!important` declarations that block responsive overrides
- [ ] **FNDTN-03**: Convert hover-dependent actions to always-visible on touch devices using `@media (hover: hover)`

### Table & Card Layout

- [ ] **CARD-01**: Refine existing table-to-card layout for phone screens (320px-480px) with stacked labels
- [ ] **CARD-02**: Make action buttons (copy, QR, edit, history) always visible on mobile cards
- [ ] **CARD-03**: Increase status toggle touch targets to minimum 44px on mobile

### Modals & Dialogs

- [ ] **MODAL-01**: Make modals full-screen on mobile devices
- [ ] **MODAL-02**: Fix iOS Safari viewport bug using `dvh` units for modal sizing
- [ ] **MODAL-03**: Add bottom-sheet slide-up animation for mobile modals

### Chart

- [ ] **CHART-01**: Collapse performance chart by default on mobile with expand/collapse toggle
- [ ] **CHART-02**: Show summary stats bar (total clicks, QR scans) replacing chart space on mobile

### Controls & Inputs

- [ ] **CTRL-01**: Make pagination touch-friendly with 44px minimum tap targets
- [ ] **CTRL-02**: Optimize date picker inputs for phone screens

### Form

- [ ] **FORM-01**: Make link creation/edit form responsive for mobile (form is embedded in modals, must work first)

## v2 Requirements

### Controls & Inputs

- **CTRL-03**: Stacked filter bar — filters stack vertically on mobile instead of horizontal row
- **CTRL-04**: Filter drawer — slide-in panel for advanced filtering on mobile

### Table & Card Layout

- **CARD-04**: Swipe actions on mobile cards (swipe to copy, edit, delete)
- **CARD-05**: Expandable card details with tap-to-expand animation

### Accessibility

- **A11Y-01**: Add ARIA roles/labels for table-to-card conversion for screen readers
- **A11Y-02**: Add prefers-reduced-motion media query for animations

## Out of Scope

| Feature | Reason |
|---------|--------|
| Native mobile app | Web responsive only — no app development |
| Desktop UI redesign | Only mobile adaptations; desktop layout unchanged |
| New features | Purely mobile responsiveness, no new capabilities |
| Performance optimization | Focus is layout/interaction, not speed |
| WordPress theme compatibility testing | Scoped to plugin styles only |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| FNDTN-01 | — | Pending |
| FNDTN-02 | — | Pending |
| FNDTN-03 | — | Pending |
| CARD-01 | — | Pending |
| CARD-02 | — | Pending |
| CARD-03 | — | Pending |
| MODAL-01 | — | Pending |
| MODAL-02 | — | Pending |
| MODAL-03 | — | Pending |
| CHART-01 | — | Pending |
| CHART-02 | — | Pending |
| CTRL-01 | — | Pending |
| CTRL-02 | — | Pending |
| FORM-01 | — | Pending |

**Coverage:**
- v1 requirements: 14 total
- Mapped to phases: 0
- Unmapped: 14 ⚠️

---
*Requirements defined: 2026-02-15*
*Last updated: 2026-02-15 after initial definition*
