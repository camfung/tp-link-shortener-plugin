# Phase 9: Wallet Client - Context

**Gathered:** 2026-03-10
**Status:** Ready for planning

<domain>
## Phase Boundary

The plugin can fetch wallet credit transactions for the current user from the TerrWallet API, handling authentication, pagination, and errors — without any UI changes or modifications to existing code.

</domain>

<decisions>
## Implementation Decisions

### API access method
- Use `rest_do_request()` for internal WordPress REST dispatch — no HTTP loopback, no network overhead, runs in-process
- Primary auth: current logged-in user's WordPress session (for dashboard page loads)
- Fallback auth: WC API keys from wp-config.php constants (for cron/CLI contexts where no user session exists)
- Client must support both authenticated page loads and cron/CLI contexts

### Claude's Discretion
- Transaction parsing: which fields to extract, how to identify credits vs debits, date range handling
- Error handling: exception types, retry behavior, missing plugin detection
- Credential config: wp-config.php constant naming convention, validation on missing creds

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 09-wallet-client*
*Context gathered: 2026-03-10*
