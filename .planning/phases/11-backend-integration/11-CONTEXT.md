# Phase 11: Backend Integration - Context

**Gathered:** 2026-03-10
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire the UsageMergeAdapter (Phase 10) into the existing AJAX handler (`tp_get_usage_summary`) so the browser receives merged usage + wallet data in a single response. Wallet failures never break the dashboard -- usage data always displays even if the wallet API is unavailable. If TerrWallet is deactivated, no PHP errors occur.

</domain>

<decisions>
## Implementation Decisions

### Failure behavior
- When wallet API errors or times out, otherServices fields are present but set to `null` -- frontend checks for null, not field existence
- Timeout threshold: **5 seconds** before giving up on wallet API and returning nulls
- Wallet failures logged via `error_log()` so site admins can see issues in server logs -- no user-facing error indication
- **Partial failure handling:** If wallet API fails for some days in a date range, return usage data for all days with `null` otherServices only on the failed days -- don't throw away good data

### Data fetching strategy
- No caching -- compute merged result fresh on every AJAX request
- Initial wallet data fetch matches the dashboard's default date range (same range as usage data)
- If the user selects a date range wider than the initial fetch, re-fetch wallet data for the full new range
- Client-side slicing for narrower ranges within what's already been fetched

### Claude's Discretion
- Response shape: how otherServices is structured in the JSON response (nested object vs flat fields)
- Plugin deactivation detection: how to check if TerrWallet is active (class_exists, function_exists, etc.)
- Error classification: which wallet API errors are retryable vs immediate null

</decisions>

<specifics>
## Specific Ideas

No specific requirements -- open to standard approaches. The key constraint is that the existing `tp_get_usage_summary` AJAX endpoint must be extended (not replaced) and wallet data must never block or break usage data display.

</specifics>

<deferred>
## Deferred Ideas

None -- discussion stayed within phase scope

</deferred>

---

*Phase: 11-backend-integration*
*Context gathered: 2026-03-10*
