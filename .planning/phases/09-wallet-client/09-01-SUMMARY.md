---
phase: 09-wallet-client
plan: 01
subsystem: api
tags: [php, terrwallet, woo-wallet, rest-api, dto, psr4]

# Dependency graph
requires:
  - phase: 05-shortcode-foundation
    provides: autoloader pattern and plugin structure
provides:
  - TerrWalletClient with dual-mode transaction fetch (direct PHP + REST fallback)
  - WalletTransaction DTO with fromRaw() factory
  - Exception hierarchy (TerrWalletException, NotInstalled, Api)
  - PSR-4 autoloader for TerrWallet namespace
  - Temporary test UI and AJAX endpoint for verification
affects: [10-merge-adapter, 11-backend-integration]

# Tech tracking
tech-stack:
  added: []
  patterns: [dual-mode-client, direct-php-primary-rest-fallback, typed-exception-hierarchy]

key-files:
  created:
    - includes/TerrWallet/TerrWalletClient.php
    - includes/TerrWallet/DTO/WalletTransaction.php
    - includes/TerrWallet/Exception/TerrWalletException.php
    - includes/TerrWallet/Exception/TerrWalletNotInstalledException.php
    - includes/TerrWallet/Exception/TerrWalletApiException.php
    - tests/integration/test-terrwallet-client.php
  modified:
    - includes/autoload.php
    - includes/class-tp-api-handler.php
    - templates/usage-dashboard-template.php
    - assets/js/usage-dashboard.js

key-decisions:
  - "Direct PHP get_wallet_transactions() as primary path -- no permission overhead for regular users"
  - "REST fallback uses rest_do_request() with email lookup and PHP-side date/type filtering"
  - "WalletTransaction DTO sanitizes HTML via wp_strip_all_tags() on details field"
  - "Temporary test UI deployed for manual verification before proceeding to Phase 10"

patterns-established:
  - "TerrWallet namespace: PSR-4 autoloaded, same pattern as TrafficPortal/ShortCode/SnapCapture"
  - "Dual-mode client: function_exists() check at call time, not construction time"

requirements-completed: [WCLI-01, WCLI-02, WCLI-03, WCLI-04]

# Metrics
duration: 5min
completed: 2026-03-10
---

# Phase 9 Plan 1: Wallet Client Summary

**TerrWallet PHP client with dual-mode fetch (direct PHP primary, REST fallback), typed exceptions, WalletTransaction DTO, and temporary test UI**

## Performance

- **Duration:** 5 min
- **Started:** 2026-03-10T16:56:36Z
- **Completed:** 2026-03-10T17:01:07Z
- **Tasks:** 3
- **Files modified:** 10

## Accomplishments
- TerrWalletClient fetches credit transactions via get_wallet_transactions() (primary) or /wc/v3/wallet REST API (cron/CLI fallback)
- Typed exception hierarchy: TerrWalletException base with NotInstalled and Api subtypes
- WalletTransaction immutable DTO with fromRaw() factory that sanitizes HTML from details field
- PSR-4 autoloader registered for TerrWallet namespace following existing project pattern
- Temporary AJAX endpoint and test UI deployed for manual verification

## Task Commits

Each task was committed atomically:

1. **Task 1: Foundation - Exceptions, DTO, and Autoloader** - `7a2fb4f` (feat)
2. **Task 2: TerrWalletClient with Dual-Mode Fetch and Integration Test** - `26a1888` (feat)
3. **Task 3: Temporary Test UI and AJAX Endpoint** - `4b59b91` (feat)

## Files Created/Modified
- `includes/TerrWallet/TerrWalletClient.php` - Main client class with getTransactions(), fetchViaDirect(), fetchViaRest()
- `includes/TerrWallet/DTO/WalletTransaction.php` - Immutable value object with readonly properties and fromRaw() factory
- `includes/TerrWallet/Exception/TerrWalletException.php` - Base exception for all wallet errors
- `includes/TerrWallet/Exception/TerrWalletNotInstalledException.php` - Thrown when woo-wallet not installed
- `includes/TerrWallet/Exception/TerrWalletApiException.php` - Thrown on REST API errors
- `includes/autoload.php` - Added TerrWallet namespace PSR-4 autoloader block
- `includes/class-tp-api-handler.php` - Added temp AJAX handler tp_test_wallet_client
- `templates/usage-dashboard-template.php` - Added temp test UI section
- `assets/js/usage-dashboard.js` - Added temp JS click handler for test button
- `tests/integration/test-terrwallet-client.php` - Integration test runnable via wp eval-file

## Decisions Made
- Direct PHP get_wallet_transactions() as primary path -- avoids permission callback issues that would block regular users via REST
- REST fallback resolves user ID to email (v3 API requirement) and filters by type/date in PHP since REST has no native date filtering
- WalletTransaction DTO uses wp_strip_all_tags() on details field to prevent HTML injection from varied transaction sources
- Plugin detection at call time (not construction time) to avoid load-order timing issues

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- TerrWalletClient ready for Phase 10 (merge adapter) to consume via getTransactions()
- Test UI deployed at https://trafficportal.dev/usage-dashboard/ -- user should click "Test Wallet Client" to verify
- All temporary code clearly marked with "TEMP: Remove after milestone v2.2 complete" comments
- WC API credentials (TP_WC_CONSUMER_KEY, TP_WC_CONSUMER_SECRET) must be in wp-config.php for REST fallback path

---
*Phase: 09-wallet-client*
*Completed: 2026-03-10*
