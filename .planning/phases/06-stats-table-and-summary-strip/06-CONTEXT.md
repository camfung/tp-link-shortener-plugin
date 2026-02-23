# Phase 6: Stats Table and Summary Strip - Context

**Gathered:** 2026-02-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Render the daily usage stats table and summary cards using data from the Phase 5 AJAX proxy. Includes JS fetch calls to the existing endpoint, client-side data processing (estimated click/QR split, running balance, currency formatting), table rendering with sorting and pagination, and summary stats cards. No new server-side API work — Phase 5's data pipeline is consumed here.

</domain>

<decisions>
## Implementation Decisions

### Table layout and columns
- Match the client links dashboard table style — gradient headers, uppercase labels, sortable columns, same CSS design system
- Show estimated click/QR breakdown using the same icon pattern (QR icon + count, mouse icon + count) with an "estimated" disclaimer
- All columns sortable (date, hits, cost, balance) — same sort behavior as client links
- Paginated — same pagination pattern as client links dashboard

### Summary cards
- Positioned above the table as a horizontal stats strip
- Distinct but consistent styling — different look from client links cards but same color palette and design system
- Balance displayed neutrally — no color coding based on amount
- Cards show value + secondary context (e.g., daily average or similar derived metric)
- Cards show loading skeletons while data loads

### Data formatting
- Currency: 2 decimal places ($0.00) — round sub-cent values for display
- Dates: Same relative format as client links — "Today", "Yesterday", "3 days ago", then "Jan 15, 2026" for older
- Zero-activity days: Hidden by default (only show days with activity)
- Number formatting: Use locale-aware commas (1,234 hits)

### Empty and loading states
- Empty state matches client links dashboard pattern — message in the table area showing the date range queried
- Error state handling: Claude's discretion
- Loading-to-data transition: Claude's discretion

### Claude's Discretion
- Loading-to-data transition style (instant vs fade)
- Error state UX pattern
- Exact secondary metric for summary cards (daily average, trend, etc.)
- Skeleton animation details
- Exact column widths and responsive breakpoints

</decisions>

<specifics>
## Specific Ideas

- "It should look the same as what is in the client links dashboard" — the existing table with gradient headers, icon-based click breakdown, sortable columns, and pagination is the reference
- Estimated click/QR split uses same visual pattern as client links (QR icon + mouse icon with counts)

</specifics>

<deferred>
## Deferred Ideas

- WP admin setting to toggle showing/hiding zero-activity days — admin settings UI is a separate capability
- Admin-configurable display preferences — future phase

</deferred>

---

*Phase: 06-stats-table-and-summary-strip*
*Context gathered: 2026-02-22*
