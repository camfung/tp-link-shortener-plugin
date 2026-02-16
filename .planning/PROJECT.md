# Traffic Portal — Mobile Responsive Update

## What This Is

A WordPress plugin (Traffic Portal) that provides link shortening, click tracking, QR code generation, and a client links management dashboard. The plugin serves both end-users (creating/managing short links) and administrators (viewing analytics, managing client links). This milestone focuses on making the entire plugin mobile-friendly.

## Core Value

Users can fully manage their short links from a phone — create, edit, toggle, view analytics, and scan QR codes — without needing a desktop.

## Requirements

### Validated

<!-- Existing capabilities confirmed working on desktop -->

- ✓ Short link creation with custom codes — existing
- ✓ Click tracking and analytics — existing
- ✓ QR code generation and display — existing
- ✓ Client links dashboard with table, filters, pagination — existing
- ✓ Link editing and status toggling — existing
- ✓ Date range filtering and search — existing
- ✓ Performance chart (clicks + QR scans) — existing
- ✓ User authentication and session management — existing

### Active

- [ ] All plugin pages render correctly on phone-sized screens (320px-480px)
- [ ] Links table converts to card layout on mobile
- [ ] Performance chart collapses by default on mobile, expandable on tap
- [ ] Modals and dialogs go full-screen on mobile (slide up from bottom)
- [ ] Filter bar reorganizes for touch-friendly use on small screens
- [ ] Create/edit link forms are usable on mobile
- [ ] QR code dialogs work on mobile
- [ ] Pagination is touch-friendly
- [ ] All interactive elements have adequate touch targets (min 44px)

### Out of Scope

- Native mobile app — web responsive only
- Desktop UI redesign — only mobile adaptations, desktop stays the same
- New features — this is purely about mobile responsiveness
- Performance optimization — focus is layout/interaction, not speed

## Context

- **Framework:** Bootstrap 5.3.0 (via CDN) + custom CSS with CSS variables design system
- **Icons:** Font Awesome 6.4.0
- **Typography:** Poppins font family
- **Existing breakpoints:** Some responsive rules at 768px, 992px, 520px already exist but are incomplete
- **CSS files:** `frontend.css` (~1022 lines), `dashboard.css` (~1024 lines), `client-links.css` (~921 lines)
- **Design system:** CSS custom properties for colors, spacing, typography (--tp-primary, --tp-accent, etc.)
- **UI critique documented:** `docs/client-links-ui-critique.md` — includes issues that overlap with mobile (table column widths, filter bar spacing, pagination)
- **Brownfield:** Existing codebase with working desktop UI; changes should be additive CSS (media queries) with minimal HTML restructuring

## Constraints

- **Tech stack**: Must use existing Bootstrap 5 + custom CSS approach — no new frameworks
- **Desktop preservation**: All changes must be mobile-only via media queries; desktop layout unchanged
- **WordPress**: Plugin runs inside WordPress themes, so styles must be scoped to avoid conflicts
- **Touch targets**: Minimum 44px tap targets per Apple/Google guidelines

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Card layout for links table on mobile | 5-column table doesn't fit on phone screens; cards show key info with tap-to-expand | — Pending |
| Chart hidden by default on mobile | Chart takes too much vertical space; users can expand when needed | — Pending |
| Full-screen modals on mobile | Better mobile UX; slide up from bottom feels native | — Pending |
| Phone-first focus (320px-480px) | Primary concern is phone screens; tablets secondary | — Pending |

---
*Last updated: 2026-02-15 after initialization*
