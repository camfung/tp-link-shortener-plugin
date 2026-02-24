---
phase: 08-date-filtering-and-api-doc
plan: 02
subsystem: api
tags: [documentation, api-requirements, usage-dashboard]

# Dependency graph
requires:
  - phase: 05-shortcode-foundation-and-api-proxy
    provides: API client and AJAX handler for usage summary
  - phase: 06-stats-table-and-summary-cards
    provides: Frontend rendering with mock splitHits() function
provides:
  - API requirements document specifying clicks/qrScans breakdown, other services data, and wallet transaction history
affects: [backend-api, usage-dashboard-v2.1]

# Tech tracking
tech-stack:
  added: []
  patterns: [api-requirements-handoff-document]

key-files:
  created:
    - docs/API-REQUIREMENTS-V2.md
  modified: []

key-decisions:
  - "Recommend exploring by-source endpoint before building new backend pipeline for clicks/QR split"
  - "Other Services and Wallet Transactions marked LOW priority, deferred past v2.0"

patterns-established:
  - "API requirements doc pattern: current state baseline, proposed changes with JSON examples, frontend integration notes, migration path"

# Metrics
duration: 1min
completed: 2026-02-23
---

# Phase 8 Plan 02: API Requirements Document Summary

**Standalone API requirements doc specifying clicks/QR split fields, other services data shape, and wallet transaction history for backend team handoff**

## Performance

- **Duration:** 1 min
- **Started:** 2026-02-24T04:33:50Z
- **Completed:** 2026-02-24T04:34:58Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments

- Created comprehensive API requirements document at `docs/API-REQUIREMENTS-V2.md`
- Documented current API endpoint, response shape, and auth pattern as baseline
- Specified three backend requirements with proposed JSON response shapes and priority levels
- Included frontend integration notes showing exactly which PHP and JS files need updates
- Defined 3-phase migration path from mock data to real API data

## Task Commits

Each task was committed atomically:

1. **Task 1: Write API requirements document for backend team** - `535a543` (docs)

## Files Created/Modified

- `docs/API-REQUIREMENTS-V2.md` - Backend API requirements specifying clicks/QR split, other services, wallet transactions, frontend integration notes, and migration path

## Decisions Made

- Recommended exploring the existing `/by-source` endpoint as the path to real clicks/QR data before building new backend pipeline
- Marked Other Services (Req 2) and Wallet Transactions (Req 3) as LOW priority, deferred past v2.0
- Provided two delivery options for Other Services (new response key vs new endpoint) -- frontend supports either

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 8 is now fully complete (both plans: date filtering + API doc)
- Backend team can begin implementing clicks/QR split based on the requirements document
- Frontend is ready to consume new API fields once backend delivers them (only need to update `validate_usage_summary_response()` and remove `splitHits()`)

## Self-Check: PASSED

- FOUND: docs/API-REQUIREMENTS-V2.md
- FOUND: commit 535a543

---
*Phase: 08-date-filtering-and-api-doc*
*Completed: 2026-02-23*
