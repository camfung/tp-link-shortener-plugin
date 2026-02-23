# Phase 5: Shortcode Foundation and API Proxy - Context

**Gathered:** 2026-02-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Register the `[tp_usage_dashboard]` shortcode, render the page skeleton with loading state, gate on authentication, and wire the AJAX proxy to the external API. Caching is deferred -- every request hits the API fresh.

</domain>

<decisions>
## Implementation Decisions

### Authentication gate
- Logged-out users see an inline login form using `wp_login_form()` on the page
- Simple WordPress form, no extra branding or styled messaging
- After login, standard form submit reloads the page and dashboard appears
- Any logged-in WordPress user can access the dashboard (no role restriction)

### Proxy error handling
- On API failure, show a friendly error message with a "Retry" button that re-fetches without full page reload
- Generic error message for regular users; admins see the actual error type (e.g., timeout, 500, connection refused)
- Proxy validates and reshapes the API response before sending to frontend -- check structure, strip unexpected fields, normalize format

### Proxy timeout
- Claude's discretion on timeout value

### Caching
- No caching for v1.0 of this feature -- every page load and every retry hits the external API fresh
- Keep it simple; caching can be added as a future optimization

### Claude's Discretion
- Page skeleton layout and loading state design
- Proxy timeout value
- Response validation/reshaping specifics
- AJAX nonce and security implementation details

</decisions>

<specifics>
## Specific Ideas

No specific requirements -- open to standard approaches.

</specifics>

<deferred>
## Deferred Ideas

- Caching with WordPress transients -- add once the feature is stable and API call patterns are understood
- Per-user cache keying strategy -- revisit when caching is implemented

</deferred>

---

*Phase: 05-shortcode-foundation-and-api-proxy*
*Context gathered: 2026-02-22*
