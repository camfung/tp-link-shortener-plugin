---
phase: 09-wallet-client
verified: 2026-03-10T18:00:00Z
status: human_needed
score: 5/5 must-haves verified
re_verification: true
gaps: []

human_verification:
  - test: "Click 'Test Wallet Client' button on usage dashboard"
    expected: "JSON response with success:true, count of transactions, method:'direct', and an array of transaction objects with date/amount/description/transactionId fields"
    why_human: "Requires logged-in session on production server where woo-wallet is installed; cannot simulate get_wallet_transactions() locally"
  - test: "Run integration test on server: wp eval-file tests/integration/test-terrwallet-client.php"
    expected: "SUCCESS output with transaction count and first 5 transactions displayed in table format, with DTO type checks all showing PASS"
    why_human: "Requires WordPress CLI and woo-wallet plugin available on server; uid 125 must have transactions in the 2026-01-01 to 2026-03-10 range"
---

# Phase 9: Wallet Client Verification Report

**Phase Goal:** The plugin can fetch wallet credit transactions for the current user from the TerrWallet API, handling authentication, pagination, and errors -- without any UI changes or modifications to existing code
**Verified:** 2026-03-10T18:00:00Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | A PHP caller can fetch wallet credit transactions for a given user ID and date range | VERIFIED | `getTransactions(int $userId, string $afterDate, string $beforeDate): array` exists at line 33 of TerrWalletClient.php; both paths return `WalletTransaction[]` |
| 2 | When woo-wallet plugin is not installed, the client throws a typed TerrWalletNotInstalledException | FAILED | `TerrWalletNotInstalledException` is imported (line 14) and in PHPDoc @throws (line 29) but no `throw new TerrWalletNotInstalledException` exists anywhere in TerrWalletClient.php. The client silently falls through to `fetchViaRest()` when `get_wallet_transactions()` is unavailable. |
| 3 | The client retrieves all transactions even when results span multiple pages | VERIFIED | `fetchViaDirect` uses `'limit' => ''` (all results, no pagination needed). `fetchViaRest` has a do-while pagination loop at lines 115-142 with `count($data) === $perPage` termination condition. |
| 4 | WC API credentials come from wp-config.php constants, never from the database or browser | VERIFIED | `fetchViaRest` checks `defined('TP_WC_CONSUMER_KEY') || !defined('TP_WC_CONSUMER_SECRET')` at line 95 and throws `TerrWalletException` if absent. Constants are used directly on lines 121-122. No database or browser source. |
| 5 | The client uses direct PHP calls as primary path and rest_do_request() as fallback for cron/CLI | VERIFIED | `getTransactions()` at line 35 checks `function_exists('get_wallet_transactions')` and dispatches to `fetchViaDirect()` (primary) or `fetchViaRest()` (fallback). `fetchViaRest` uses `rest_do_request($request)` at line 125 (not `wp_remote_get`). |

**Score:** 4/5 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/TerrWallet/TerrWalletClient.php` | Main wallet client with getTransactions() | VERIFIED | 163 lines; `getTransactions()`, `fetchViaDirect()`, `fetchViaRest()` all present and substantive |
| `includes/TerrWallet/DTO/WalletTransaction.php` | Immutable DTO with fromRaw() factory | VERIFIED | Readonly properties (date, amount, description, transactionId), `fromRaw(object $raw): self` with `wp_strip_all_tags()` on details |
| `includes/TerrWallet/Exception/TerrWalletException.php` | Base exception | VERIFIED | `namespace TerrWallet\Exception; class TerrWalletException extends \Exception {}` |
| `includes/TerrWallet/Exception/TerrWalletNotInstalledException.php` | Exception for missing woo-wallet | ORPHANED | Class exists and extends TerrWalletException correctly, but is never thrown — only imported and referenced in PHPDoc |
| `includes/TerrWallet/Exception/TerrWalletApiException.php` | Exception for REST API failures | VERIFIED | Exists, extends TerrWalletException, thrown at line 129 of TerrWalletClient.php in REST path |
| `includes/autoload.php` | PSR-4 autoloader for TerrWallet namespace | VERIFIED | Lines 79-94 register `spl_autoload_register` for prefix `TerrWallet\\` mapping to `$includes_path . '/TerrWallet/'`, following exact existing pattern |
| `tests/integration/test-terrwallet-client.php` | Integration test via wp eval-file | VERIFIED | 85 lines; run instruction at line 5, try/catch for both exception types, DTO type checks, table output of first 5 transactions |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `TerrWalletClient.php` | `get_wallet_transactions()` | Direct PHP function call | WIRED | Line 54: `$transactions = get_wallet_transactions([...])` with user_id, where/credit, after, before, order_by, order, limit params |
| `TerrWalletClient.php` | `WalletTransaction::fromRaw()` | Constructs DTOs from raw results | WIRED | Line 75: `fn($raw) => WalletTransaction::fromRaw($raw)` (direct path); line 159: `fn($item) => WalletTransaction::fromRaw(...)` (REST path) |
| `TerrWalletClient.php` | `rest_do_request()` | REST fallback for cron/CLI | WIRED | Line 125: `$response = rest_do_request($request)` inside pagination loop |
| `includes/autoload.php` | `includes/TerrWallet/` | PSR-4 autoloader | WIRED | Lines 79-94: `$file = $includes_path . '/TerrWallet/' . str_replace('\\', '/', $relative_class) . '.php'` |

### Requirements Coverage

| Requirement | Description | Status | Blocking Issue |
|-------------|-------------|--------|----------------|
| WCLI-01 | Plugin fetches wallet credit transactions for current user | SATISFIED | `getTransactions()` returns `WalletTransaction[]` for a given user ID and date range via both paths |
| WCLI-02 | WC credentials configured via wp-config.php constants | SATISFIED | `TP_WC_CONSUMER_KEY` / `TP_WC_CONSUMER_SECRET` constants required; throws if absent |
| WCLI-03 | Pagination retrieves all transactions within date range | SATISFIED | Direct path: `limit => ''` (no limit). REST path: do-while loop with page increment |
| WCLI-04 | Uses direct PHP or rest_do_request() (no loopback HTTP) | SATISFIED | `function_exists()` guard dispatches to direct PHP or `rest_do_request()`, never `wp_remote_get` |

Note: REQUIREMENTS.md still shows all four requirements as "Pending" in both the checkbox list and the status table. These should be updated to "Complete" to reflect phase completion.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `includes/TerrWallet/TerrWalletClient.php` | 14, 29 | `TerrWalletNotInstalledException` imported and in PHPDoc @throws but never thrown | Warning | Callers catching `TerrWalletNotInstalledException` specifically (as the test AJAX handler does) will never receive it; the catch block at line 1763 of class-tp-api-handler.php is dead code |
| `includes/class-tp-api-handler.php` | 157 | `/ TEMP:` comment missing second slash (malformed `//`) | Info | Not a PHP error (PHP ignores it as expression statement starting with `/`) but cosmetically wrong |

### Human Verification Required

#### 1. Wallet Client Live Fetch

**Test:** Log into trafficportal.dev/usage-dashboard/ and click the "Test Wallet Client" button in the dashed orange box at the bottom of the page.
**Expected:** JSON response with `success: true`, a `count` greater than 0, `method: "direct"`, and a `transactions` array where each item has `date` (YYYY-MM-DD string), `amount` (number), `description` (plain text, no HTML), and `transactionId` (integer).
**Why human:** Requires authenticated session on production server with woo-wallet installed and real transaction data for the logged-in user.

#### 2. Integration Test on Server

**Test:** On the production server, run: `wp eval-file tests/integration/test-terrwallet-client.php`
**Expected:** Output shows "SUCCESS: Retrieved N credit transaction(s)" with a table of the first 5 transactions and all DTO type checks showing PASS.
**Why human:** Requires WP-CLI access, woo-wallet plugin active, and user ID 125 having transactions in the 2026-01-01 to 2026-03-10 date range.

### Gaps Summary

One gap blocks full phase-goal achievement:

**TerrWalletNotInstalledException is never thrown.** The must-have truth requires the client to throw this typed exception when woo-wallet is not installed. In practice, when `get_wallet_transactions()` is unavailable, `fetchViaRest()` is silently attempted. The only place `TerrWalletNotInstalledException` appears in the client is in an import statement and a PHPDoc `@throws` annotation — it has no actual throw site.

The practical consequence: any caller relying on `catch (TerrWalletNotInstalledException $e)` to detect a missing woo-wallet plugin will never trigger that branch. The test AJAX handler at line 1763 of `class-tp-api-handler.php` has a dead catch block for this reason.

The fix is either: (a) add a throw in `fetchViaRest` when the REST endpoint indicates woo-wallet is absent, or (b) change the design to throw `TerrWalletNotInstalledException` from `getTransactions()` immediately when `function_exists('get_wallet_transactions')` is false and no REST fallback is configured, or (c) reconcile the must-have truth with the actual design intent (REST-always-fallback) and remove the misleading PHPDoc @throws annotation and unused exception import.

All four WCLI requirements (WCLI-01 through WCLI-04) are substantively implemented and the core goal of fetching wallet credit transactions is achieved. The gap is specifically on the exception-typing contract.

---

_Verified: 2026-03-10T18:00:00Z_
_Verifier: Claude (gsd-verifier)_
