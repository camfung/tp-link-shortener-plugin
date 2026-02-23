---
phase: 05-shortcode-foundation-and-api-proxy
plan: 02
subsystem: api
tags: [wordpress, ajax, php, javascript, jquery, api-proxy, response-validation]

# Dependency graph
requires:
  - phase: 05-01
    provides: "[tp_usage_dashboard] shortcode with skeleton template, localized tpUsageDashboard script data"
provides:
  - "getUserActivitySummary() API client method with 15s timeout"
  - "tp_get_usage_summary AJAX handler with nonce, auth, server-side UID, date validation"
  - "Response validation/reshaping: API source array -> { days: [...] } with type-checked fields"
  - "Admin-conditional error detail (error_type, error_detail for manage_options users)"
  - "JavaScript AJAX fetching with skeleton/error/content state management and retry"
affects: [05-03-e2e-tests, 06-table, 07-chart, 08-date-filtering]

# Tech tracking
tech-stack:
  added: []
  patterns: [AJAX proxy with response validation/reshaping, admin-conditional error detail, three-state JS management]

key-files:
  created:
    - assets/js/usage-dashboard.js
    - tests/Unit/TrafficPortal/UserActivitySummaryTest.php
    - tests/Integration/UserActivitySummaryIntegrationTest.php
  modified:
    - includes/TrafficPortal/TrafficPortalApiClient.php
    - includes/class-tp-api-handler.php

key-decisions:
  - "15-second API client timeout for getUserActivitySummary (matching Lambda timeout)"
  - "20-second JS timeout (15s server + network overhead)"
  - "Validation helper replicated in unit tests to verify contract without WordPress"

patterns-established:
  - "AJAX proxy pattern: nonce + auth + server-side UID + date validation + try/catch with typed exceptions"
  - "Response reshaping: unwrap API source key, rename to days, type-check each field, strip extras"
  - "Admin error detail: generic message for regular users, error_type + error_detail for manage_options"

# Metrics
duration: 4min
completed: 2026-02-23
---

# Phase 5 Plan 02: AJAX Proxy and JavaScript Wiring Summary

**AJAX proxy to user-activity-summary API with response validation, admin error detail, and jQuery state management for skeleton/error/content transitions**

## Performance

- **Duration:** 4 min
- **Started:** 2026-02-23T05:08:28Z
- **Completed:** 2026-02-23T05:12:29Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Complete data pipeline: JS AJAX POST -> AJAX handler (nonce + auth + server-side UID) -> API client (external API) -> response validation -> JS state update
- UID never sent from client-side: always determined server-side via TP_Link_Shortener::get_user_id()
- API response validated and reshaped: source array unwrapped to { days: [...] } with type-checked fields
- Error handling: generic message for users, detailed error_type/error_detail for admins
- 19 unit tests + 4 integration tests all passing

## Task Commits

Each task was committed atomically:

1. **Task 1: Add getUserActivitySummary() to API client and AJAX handler with response validation** - `3e388d5` (feat)
2. **Task 2: Create JavaScript for AJAX fetching, state management, and retry** - `ce3a492` (feat)

## Files Created/Modified
- `includes/TrafficPortal/TrafficPortalApiClient.php` - Added getUserActivitySummary() method with 15s timeout
- `includes/class-tp-api-handler.php` - Added AJAX handler registration, ajax_get_usage_summary(), validate_usage_summary_response(), send_usage_proxy_error()
- `assets/js/usage-dashboard.js` - Full jQuery IIFE with AJAX fetching, state management, retry, admin error detail
- `tests/Unit/TrafficPortal/UserActivitySummaryTest.php` - 19 unit tests for API client method and validation contract
- `tests/Integration/UserActivitySummaryIntegrationTest.php` - 4 integration tests verifying real API response shape

## Decisions Made
- Used 15-second timeout for the API client method (matching the Lambda timeout documented in API_REFERENCE.md) rather than the default 30-second client timeout
- Used 20-second JS timeout (allowing 15s server timeout + network overhead)
- Replicated the validate_usage_summary_response() logic in the unit test helper to verify the validation contract without loading WordPress

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Full data pipeline is wired: browser AJAX -> WordPress proxy -> external API -> validated response -> JS state
- Content div shows on success (actual chart/table rendering deferred to Phases 6-7)
- Error state with retry is functional
- Date range parameters are passed through (date filtering UI deferred to Phase 8)
- Plan 05-03 E2E tests can now test the complete flow

## Self-Check: PASSED

All 3 created files verified on disk. All 2 modified files verified. Commits `3e388d5` and `ce3a492` verified in git log.

---
*Phase: 05-shortcode-foundation-and-api-proxy*
*Completed: 2026-02-23*
