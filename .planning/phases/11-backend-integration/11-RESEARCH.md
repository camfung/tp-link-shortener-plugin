# Phase 11: Backend Integration - Research

**Researched:** 2026-03-10
**Domain:** WordPress AJAX handler modification, PHP error handling, graceful degradation
**Confidence:** HIGH

## Summary

Phase 11 wires the existing `TerrWalletClient` (Phase 9) and `UsageMergeAdapter` (Phase 10) into the existing `ajax_get_usage_summary()` method in `class-tp-api-handler.php`. The integration point is narrow and well-defined: after the usage API returns validated day records (line 1620), wallet transactions are fetched and merged before sending the JSON response. The entire wallet path must be wrapped in a try/catch that catches `TerrWalletException` (the base class) so any wallet failure results in null `otherServices` values rather than breaking the response.

The existing code provides clear patterns to follow. The test AJAX endpoint `ajax_test_wallet_client()` (line 1732) already demonstrates instantiating `TerrWalletClient`, calling `getTransactions()`, and catching the exception hierarchy. The `validate_usage_summary_response()` method (line 1650) already produces the `$days` array format that `UsageMergeAdapter::merge()` expects as its first argument. The integration is a ~30-line modification to one method.

**Primary recommendation:** Modify `ajax_get_usage_summary()` to add a wallet fetch + merge step between validation (line 1620) and `wp_send_json_success()` (line 1625), with the entire wallet path in a try/catch that falls back to returning usage data with null otherServices fields.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- When wallet API errors or times out, otherServices fields are present but set to `null` -- frontend checks for null, not field existence
- Timeout threshold: **5 seconds** before giving up on wallet API and returning nulls
- Wallet failures logged via `error_log()` so site admins can see issues in server logs -- no user-facing error indication
- **Partial failure handling:** If wallet API fails for some days in a date range, return usage data for all days with `null` otherServices only on the failed days -- don't throw away good data
- No caching -- compute merged result fresh on every AJAX request
- Initial wallet data fetch matches the dashboard's default date range (same range as usage data)
- If the user selects a date range wider than the initial fetch, re-fetch wallet data for the full new range
- Client-side slicing for narrower ranges within what's already been fetched

### Claude's Discretion
- Response shape: how otherServices is structured in the JSON response (nested object vs flat fields)
- Plugin deactivation detection: how to check if TerrWallet is active (class_exists, function_exists, etc.)
- Error classification: which wallet API errors are retryable vs immediate null

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| TerrWallet\TerrWalletClient | Phase 9 | Fetch wallet credit transactions | Already built, tested, deployed |
| TerrWallet\UsageMergeAdapter | Phase 10 | Full outer join of usage + wallet by date | Already built, unit tested |
| class-tp-api-handler.php | Existing | WordPress AJAX handler hosting `ajax_get_usage_summary()` | The single file being modified |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| TerrWallet\Exception\TerrWalletException | Phase 9 | Base exception class for all wallet errors | Catch this single type to handle any wallet failure |
| TerrWallet\Exception\TerrWalletNotInstalledException | Phase 9 | Plugin not installed/activated | Detected when `function_exists('get_wallet_transactions')` is false AND REST creds missing |
| TerrWallet\Exception\TerrWalletApiException | Phase 9 | REST API returned error | Only in REST fallback path |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Modifying existing method | New separate AJAX endpoint | Violates UI-04 requirement (single AJAX call) |
| Catching base TerrWalletException | Catching individual subtypes | No benefit -- all subtypes result in same action (null otherServices) |

## Architecture Patterns

### Integration Point

The modification targets a single method. Here is the current flow and where the wallet integration fits:

```
CURRENT FLOW (ajax_get_usage_summary):
1. Verify nonce + login
2. Get UID server-side
3. Sanitize/validate dates
4. $raw = $this->client->getUserActivitySummary($uid, $start_date, $end_date)
5. $validated = $this->validate_usage_summary_response($raw)
6. wp_send_json_success($validated)

NEW FLOW (insert between steps 5 and 6):
5a. Try: fetch wallet transactions via TerrWalletClient
5b. Try: merge via UsageMergeAdapter::merge($validated['days'], $walletTransactions)
5c. Catch TerrWalletException: log error, keep $validated['days'] with null otherServices
5d. wp_send_json_success(['days' => $mergedDays])
```

### Pattern 1: Graceful Degradation via Exception Boundary

**What:** Wrap the entire wallet fetch + merge in a try/catch around the base `TerrWalletException`. On any failure, ensure each day record has `otherServices: null`.

**When to use:** Any time an optional data source enriches required data.

**Example:**
```php
// After validate_usage_summary_response() produces $validated['days']
$days = $validated['days'];

try {
    $walletClient = new \TerrWallet\TerrWalletClient();
    $wpUserId = get_current_user_id();
    $transactions = $walletClient->getTransactions($wpUserId, $start_date, $end_date);
    $days = \TerrWallet\UsageMergeAdapter::merge($days, $transactions);
} catch (\TerrWallet\Exception\TerrWalletException $e) {
    error_log('TP Link Shortener: Wallet data unavailable: ' . $e->getMessage());
    // $days remains unmodified -- usage-only, no otherServices field
    // Add null otherServices to each day for consistent response shape
    $days = array_map(function ($day) {
        $day['otherServices'] = null;
        return $day;
    }, $days);
}

wp_send_json_success(['days' => $days]);
```

### Pattern 2: Plugin Deactivation Detection

**What:** Check if TerrWallet/woo-wallet is available before attempting wallet operations.

**Recommendation:** Use `function_exists('get_wallet_transactions')` as the primary check -- this is already the check used inside `TerrWalletClient::getTransactions()`. When the function does not exist AND REST creds are missing, `TerrWalletNotInstalledException` is thrown, which is a subclass of `TerrWalletException` and caught by the same catch block.

No separate pre-check is needed. The existing exception hierarchy handles this case naturally. When woo-wallet is deactivated:
1. `function_exists('get_wallet_transactions')` returns false
2. `fetchViaRest()` is tried
3. If REST creds not configured, `TerrWalletNotInstalledException` is thrown
4. Caught by `catch (TerrWalletException $e)` -- usage data returned with null otherServices

### Pattern 3: Response Shape Consistency

**What:** Every day record in the response MUST have the `otherServices` key, even when wallet data is unavailable.

**Why:** The frontend should check `day.otherServices !== null` rather than `'otherServices' in day`. This is simpler and matches the locked decision.

**Two scenarios produce consistent shape:**
1. **Wallet success:** `UsageMergeAdapter::merge()` already adds `otherServices: null` to usage-only days and `otherServices: {amount, items}` to wallet days
2. **Wallet failure:** The catch block adds `otherServices: null` to all days via array_map

### Anti-Patterns to Avoid
- **Separate AJAX endpoint for wallet data:** Violates UI-04. The browser must make one call, not two.
- **Letting wallet exceptions bubble up:** Would cause the entire AJAX call to fail, breaking usage display.
- **Pre-checking plugin status with a separate is_active check:** Unnecessary -- the exception hierarchy already handles this. Adding a check would be redundant logic.
- **Timeout at PHP level:** The `getTransactions()` call uses either direct PHP (no network) or `rest_do_request()` (in-process). Neither has a configurable timeout the way `wp_remote_get()` does. The 5-second timeout decision applies to the wallet fetch, but since both paths are synchronous PHP calls on the same server, they will not hang like a remote HTTP call would. If the database is slow, WordPress's own timeouts apply. No custom timeout implementation is needed.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Merging usage + wallet data | Custom merge logic in AJAX handler | `UsageMergeAdapter::merge()` | Already built, tested with 8 unit tests, handles all edge cases |
| Wallet data fetching | Custom API calls in AJAX handler | `TerrWalletClient::getTransactions()` | Already handles direct PHP vs REST fallback, pagination, date filtering |
| Exception hierarchy | Custom error codes/flags | `TerrWalletException` base class | One catch handles all wallet failure modes |
| Response validation | Custom day-record reshaping | `validate_usage_summary_response()` | Already strips unexpected fields, casts types |

**Key insight:** Phase 11 is pure wiring. Both data-fetching (Phase 9) and data-transformation (Phase 10) classes already exist and are tested. The AJAX handler just needs to call them in sequence with a try/catch boundary.

## Common Pitfalls

### Pitfall 1: Missing otherServices Key on Wallet Failure
**What goes wrong:** If the catch block just returns `$validated` unchanged, the `days` array items will not have an `otherServices` key at all. Frontend code doing `day.otherServices` gets `undefined` instead of `null`.
**Why it happens:** `validate_usage_summary_response()` does not add `otherServices` -- it only produces `{date, totalHits, hitCost, balance}`.
**How to avoid:** In the catch block, explicitly map each day to add `'otherServices' => null`.
**Warning signs:** JavaScript `undefined !== null` check fails silently.

### Pitfall 2: Wrong User ID for Wallet Lookup
**What goes wrong:** Using the Traffic Portal `$uid` (from `TP_Link_Shortener::get_user_id()`) instead of the WordPress `get_current_user_id()` for wallet lookups.
**Why it happens:** The AJAX handler already has `$uid` in scope, which is the Traffic Portal user ID. The wallet client needs the WordPress user ID.
**How to avoid:** Use `get_current_user_id()` for `TerrWalletClient::getTransactions()`. The existing test handler (`ajax_test_wallet_client()` at line 1740) already demonstrates this pattern.
**Warning signs:** Wallet returns no transactions or wrong user's transactions.

### Pitfall 3: Catching Too Broad an Exception
**What goes wrong:** Catching `\Exception` instead of `\TerrWalletException` would silently swallow bugs in the merge adapter or other code.
**Why it happens:** Defensive coding instinct.
**How to avoid:** Catch only `\TerrWallet\Exception\TerrWalletException`. If `UsageMergeAdapter::merge()` has a bug (e.g., type error), it should bubble up as an unhandled exception so it is visible.
**Warning signs:** Silent data corruption that is hard to debug.

### Pitfall 4: Forgetting That merge() Returns a New Array
**What goes wrong:** Assigning `UsageMergeAdapter::merge()` result to `$validated['days']` but then sending the original `$validated` which still has the old days.
**Why it happens:** PHP arrays are copied by value.
**How to avoid:** Either assign `$validated['days'] = $mergedDays` or build a new response array.
**Warning signs:** Response always shows null otherServices even when wallet has data.

### Pitfall 5: Date Parameter Mismatch
**What goes wrong:** Passing dates in wrong format to wallet client.
**Why it happens:** The AJAX handler uses `$start_date` and `$end_date` already validated as `YYYY-MM-DD`. The wallet client expects the same format. No issue here as long as the same variables are passed.
**How to avoid:** Pass the same `$start_date` and `$end_date` variables to both API calls.

## Code Examples

### The Complete Integration (Recommended Implementation)

```php
// Source: Direct analysis of class-tp-api-handler.php lines 1576-1642
// This replaces lines 1616-1625 in the existing try block

try {
    $raw = $this->client->getUserActivitySummary($uid, $start_date, $end_date);
    $validated = $this->validate_usage_summary_response($raw);
    $days = $validated['days'];

    // --- BEGIN WALLET INTEGRATION ---
    try {
        $walletClient = new \TerrWallet\TerrWalletClient();
        $wpUserId = get_current_user_id();
        $transactions = $walletClient->getTransactions($wpUserId, $start_date, $end_date);
        $days = \TerrWallet\UsageMergeAdapter::merge($days, $transactions);
    } catch (\TerrWallet\Exception\TerrWalletException $e) {
        // GRACE-01/GRACE-02: Wallet failure never breaks usage display
        error_log('TP Link Shortener: Wallet data unavailable: ' . $e->getMessage());
        // Ensure consistent response shape: every day has otherServices key
        $days = array_map(function ($day) {
            $day['otherServices'] = null;
            return $day;
        }, $days);
    }
    // --- END WALLET INTEGRATION ---

    $this->log_to_file('Usage summary validated successfully: ' . count($days) . ' days');
    $this->log_to_file('=== GET USAGE SUMMARY REQUEST END ===');

    wp_send_json_success(['days' => $days]);

} catch (NetworkException $e) {
    // ... existing error handling unchanged ...
```

### Response Shape: Wallet Available

```json
{
    "success": true,
    "data": {
        "days": [
            {
                "date": "2025-01-15",
                "totalHits": 42,
                "hitCost": 2.10,
                "balance": 97.90,
                "otherServices": {
                    "amount": 10.00,
                    "items": [
                        { "amount": 10.00, "description": "Top-up" }
                    ]
                }
            },
            {
                "date": "2025-01-16",
                "totalHits": 30,
                "hitCost": 1.50,
                "balance": 96.40,
                "otherServices": null
            }
        ]
    }
}
```

### Response Shape: Wallet Unavailable (GRACE-01/GRACE-02)

```json
{
    "success": true,
    "data": {
        "days": [
            {
                "date": "2025-01-15",
                "totalHits": 42,
                "hitCost": 2.10,
                "balance": 97.90,
                "otherServices": null
            }
        ]
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Separate AJAX endpoints per data source | Single endpoint with server-side merge | v2.2 design decision | One round-trip, simpler frontend |
| `wp_remote_get()` for same-server API | `rest_do_request()` / direct PHP | Phase 9 | No loopback HTTP issues |

## Open Questions

1. **Timeout Implementation**
   - What we know: User decided on 5-second timeout threshold. Both wallet paths (direct PHP, rest_do_request) are synchronous in-process calls.
   - What's unclear: There is no native PHP mechanism to timeout a `get_wallet_transactions()` call or `rest_do_request()`. These are database/internal calls, not HTTP.
   - Recommendation: Document that the 5s timeout applies conceptually -- if the wallet query is slow, it is the database that is slow, and WordPress's own `$wpdb` query timeout would apply. No custom timeout wrapper is needed for in-process calls. If this becomes a real issue, it can be addressed with `set_time_limit()` or async processing in a future phase.

2. **Partial Failure Within Date Range**
   - What we know: User wants partial failure handling where some days get null otherServices while others get real data.
   - What's unclear: The current `TerrWalletClient::getTransactions()` is all-or-nothing for a date range -- it either returns all transactions or throws an exception. There is no mechanism for partial failure within a single call.
   - Recommendation: The partial failure scenario described in CONTEXT.md cannot occur with the current wallet client design (it fetches all transactions in one call). The all-or-nothing behavior already satisfies the intent: if the call succeeds, all days get wallet data; if it fails, all days get null. Document this as a non-issue for now.

3. **Client-Side Date Range Slicing**
   - What we know: CONTEXT.md mentions "client-side slicing for narrower ranges within what's already been fetched."
   - What's unclear: This is a frontend optimization, not a backend concern.
   - Recommendation: This is Phase 12 (frontend) scope. The backend always fetches fresh on every AJAX call (no caching decision is locked). The AJAX handler simply processes whatever date range the client sends.

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection: `includes/class-tp-api-handler.php` lines 1576-1693 -- existing AJAX handler and validation
- Direct codebase inspection: `includes/TerrWallet/TerrWalletClient.php` -- wallet client with dual-mode access
- Direct codebase inspection: `includes/TerrWallet/UsageMergeAdapter.php` -- merge adapter with full outer join
- Direct codebase inspection: `includes/TerrWallet/Exception/` -- exception hierarchy (base + 2 subtypes)
- Direct codebase inspection: `tests/Unit/TerrWallet/UsageMergeAdapterTest.php` -- 8 unit tests covering all merge scenarios
- Direct codebase inspection: `assets/js/usage-dashboard.js` lines 598-640 -- frontend AJAX consumption pattern
- Direct codebase inspection: `ajax_test_wallet_client()` line 1732 -- existing wallet client usage pattern in AJAX context

### Secondary (MEDIUM confidence)
- `.planning/STATE.md` -- accumulated project decisions and context
- `.planning/REQUIREMENTS.md` -- requirement definitions (GRACE-01, GRACE-02, UI-04)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all components exist in codebase, directly inspected
- Architecture: HIGH -- modification point is narrow and well-understood (one method, ~30 lines added)
- Pitfalls: HIGH -- derived from direct code analysis of both current handler and new components

**Research date:** 2026-03-10
**Valid until:** 2026-04-10 (stable -- no external dependencies, all code is local)
