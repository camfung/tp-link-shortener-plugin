# Phase 12: Dashboard UI - Context

**Gathered:** 2026-03-10
**Status:** Ready for planning

<domain>
## Phase Boundary

Add wallet credit visibility to the existing usage dashboard: an "Other Services" column in the stats table with tooltip descriptions, and a summary card in the strip. No new AJAX endpoints — Phase 11 already returns merged data. No chart changes.

</domain>

<decisions>
## Implementation Decisions

### Column placement & format
- Column order: Date | Hits | **Other Services** | Cost | Balance (before Cost)
- Amounts displayed as +$X.XX in green/success color for days with wallet activity
- Days without wallet activity show $0.00 (plain text, no green, no tooltip)
- Column is sortable, consistent with existing columns (Date, Hits, Cost, Balance)

### Tooltip & mobile behavior
- Hovering an Other Services amount with wallet activity shows a Bootstrap tooltip with transaction descriptions
- Multiple transactions on same day: each line shows "Description (+$amount)" format
- Single transaction: just the description text
- No tooltip on $0.00 cells (no activity = nothing to describe)
- No visual hover indicator (no dotted underline, no info icon) — clean look, users discover on hover
- Mobile: tap the amount to toggle Bootstrap tooltip, tap elsewhere to dismiss

### Claude's Discretion
- Summary card design: icon choice, color scheme, label text, secondary text for the 4th stat card
- Column width distribution across the 5 columns (currently 4 columns: 25%/30%/20%/25%)
- Mobile card layout integration for the new column
- Loading skeleton adjustment if needed

</decisions>

<specifics>
## Specific Ideas

- Green color for +$X.XX amounts should use the existing Bootstrap success color or the theme's success variable for consistency
- Tooltip content for multiple transactions per day should show individual line items with amounts, e.g.:
  ```
  Referral bonus (+$5.00)
  Store refund (+$2.50)
  ```

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 12-dashboard-ui*
*Context gathered: 2026-03-10*
