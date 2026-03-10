# Phase 10: Merge Adapter - Context

**Gathered:** 2026-03-10
**Status:** Ready for planning

<domain>
## Phase Boundary

Pure data transformation that takes usage day records (`{date, totalHits, hitCost, balance}`) and wallet credit transactions (`WalletTransaction[]` with `{date, amount, description, transactionId}`), and produces a unified daily dataset via full outer join. No I/O, no UI -- independently testable with fixture data.

</domain>

<decisions>
## Implementation Decisions

### Description combining
- Multiple wallet transactions on the same day: comma-separated descriptions
- No deduplication -- if "Store purchase" appears 3 times, show all three
- No truncation or limit -- pass all descriptions through, let UI handle overflow
- Filter out empty/whitespace-only descriptions before combining

### otherServices field shape
- Structure: `{ amount: float, items: [ { amount: float, description: string }, ... ] }`
- Top-level `amount` is the summed daily total
- `items` array preserves per-transaction detail (amount + description only, no transactionId)
- No redundant top-level description string -- UI builds display from items array
- For days with no wallet activity: `otherServices` is `null` (not an empty structure)

### Output sort order
- Adapter sorts output ascending by date (oldest first)
- Consistent order guaranteed regardless of input ordering

### Zero-fill for wallet-only days
- Days with wallet transactions but no usage: `totalHits: 0, hitCost: 0.00, balance: 0.00`
- All usage fields set to zero, not null

### Claude's Discretion
- Internal data structure choices (hash map vs array for date lookup)
- Method naming and class organization
- Test fixture design and edge case coverage

</decisions>

<specifics>
## Specific Ideas

No specific requirements -- open to standard approaches

</specifics>

<deferred>
## Deferred Ideas

None -- discussion stayed within phase scope

</deferred>

---

*Phase: 10-merge-adapter*
*Context gathered: 2026-03-10*
