# Phase 10: Merge Adapter - Research

**Researched:** 2026-03-10
**Domain:** Pure data transformation (PHP array merge/join)
**Confidence:** HIGH

## Summary

Phase 10 is a pure data transformation with no I/O, no WordPress dependencies, and no external libraries. It takes two arrays -- usage day records (`{date, totalHits, hitCost, balance}`) and wallet credit transactions (`WalletTransaction[]`) -- and produces a unified daily dataset via full outer join on date key.

The existing codebase already has (a) the `WalletTransaction` DTO from Phase 9 with a `date` property normalized to `YYYY-MM-DD`, and (b) the `validate_usage_summary_response()` method in `TP_API_Handler` that produces `{days: [{date, totalHits, hitCost, balance}]}`. The merge adapter sits between these two data sources and the AJAX response.

**Primary recommendation:** Create a single stateless class `UsageMergeAdapter` in the `TerrWallet` namespace with one public static method `merge(array $usageDays, array $walletTransactions): array` that returns the unified dataset. No dependencies on WordPress functions -- fully testable with PHPUnit fixtures.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Multiple wallet transactions on the same day: comma-separated descriptions
- No deduplication -- if "Store purchase" appears 3 times, show all three
- No truncation or limit -- pass all descriptions through, let UI handle overflow
- Filter out empty/whitespace-only descriptions before combining
- Structure: `{ amount: float, items: [ { amount: float, description: string }, ... ] }`
- Top-level `amount` is the summed daily total
- `items` array preserves per-transaction detail (amount + description only, no transactionId)
- No redundant top-level description string -- UI builds display from items array
- For days with no wallet activity: `otherServices` is `null` (not an empty structure)
- Adapter sorts output ascending by date (oldest first)
- Consistent order guaranteed regardless of input ordering
- Days with wallet transactions but no usage: `totalHits: 0, hitCost: 0.00, balance: 0.00`
- All usage fields set to zero, not null

### Claude's Discretion
- Internal data structure choices (hash map vs array for date lookup)
- Method naming and class organization
- Test fixture design and edge case coverage

### Deferred Ideas (OUT OF SCOPE)
None
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| MERGE-01 | Wallet credit transactions are merged with usage data by date into a unified daily dataset | Full outer join pattern on date key; hash map approach for O(n) merge |
| MERGE-02 | Multiple wallet transactions on the same day are aggregated into a single daily total with combined descriptions | Group-by-date with summing amounts and collecting items array |
| MERGE-03 | Days with only wallet transactions (no usage activity) appear as rows with zero hits/cost | Hash map approach naturally handles this -- wallet-only dates get zero-filled usage fields |
| MERGE-04 | Date formats are normalized between APIs (usage: YYYY-MM-DD, wallet: YYYY-MM-DD HH:MM:SS) | WalletTransaction DTO already normalizes to YYYY-MM-DD via `fromRaw()` -- adapter receives pre-normalized dates |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.1+ | Language runtime | Project uses `readonly` properties (8.1 feature) in WalletTransaction DTO |
| PHPUnit | (existing) | Unit testing | Already configured in project for `Tests\Unit` namespace |

### Supporting
No additional libraries needed. This is pure PHP array manipulation.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Plain PHP arrays | Collection library (e.g., Illuminate Collections) | Overkill -- project has zero composer dependencies; plain arrays are simpler |
| Associative array output | DTO class for merged day | Could add a MergedDay DTO, but project uses plain arrays for API handler responses (`validate_usage_summary_response` returns `['days' => [...]]`); plain arrays maintain consistency |

**Installation:** None required.

## Architecture Patterns

### Recommended Project Structure
```
includes/
└── TerrWallet/
    ├── TerrWalletClient.php          # Existing (Phase 9)
    ├── UsageMergeAdapter.php         # NEW - this phase
    ├── DTO/
    │   └── WalletTransaction.php     # Existing (Phase 9)
    └── Exception/
        └── ...                       # Existing (Phase 9)
```

### Pattern 1: Stateless Merge with Hash Map Join
**What:** Use an associative array keyed by date as the intermediate structure. Seed it from usage days, then layer wallet transactions on top, finally extract values and sort.
**When to use:** Full outer join on a single key with aggregation.
**Example:**
```php
public static function merge(array $usageDays, array $walletTransactions): array
{
    $merged = [];

    // 1. Seed from usage days
    foreach ($usageDays as $day) {
        $date = $day['date'];
        $merged[$date] = [
            'date'          => $date,
            'totalHits'     => (int) $day['totalHits'],
            'hitCost'       => (float) $day['hitCost'],
            'balance'       => (float) $day['balance'],
            'otherServices' => null,
        ];
    }

    // 2. Group wallet transactions by date, aggregate
    foreach ($walletTransactions as $tx) {
        $date = $tx->date; // Already YYYY-MM-DD from WalletTransaction DTO

        if (!isset($merged[$date])) {
            // Wallet-only day: zero-fill usage fields
            $merged[$date] = [
                'date'          => $date,
                'totalHits'     => 0,
                'hitCost'       => 0.00,
                'balance'       => 0.00,
                'otherServices' => null,
            ];
        }

        // Initialize otherServices if first transaction for this date
        if ($merged[$date]['otherServices'] === null) {
            $merged[$date]['otherServices'] = [
                'amount' => 0.0,
                'items'  => [],
            ];
        }

        // Aggregate
        $merged[$date]['otherServices']['amount'] += $tx->amount;

        $desc = trim($tx->description);
        if ($desc !== '') {
            $merged[$date]['otherServices']['items'][] = [
                'amount'      => $tx->amount,
                'description' => $desc,
            ];
        } else {
            // Still include the item for amount, just empty description
            $merged[$date]['otherServices']['items'][] = [
                'amount'      => $tx->amount,
                'description' => '',
            ];
        }
    }

    // 3. Sort ascending by date, return indexed array
    $result = array_values($merged);
    usort($result, fn($a, $b) => strcmp($a['date'], $b['date']));

    return $result;
}
```

### Pattern 2: Input/Output Contract Matching Existing Code
**What:** The adapter must produce output compatible with what `validate_usage_summary_response()` returns, plus the new `otherServices` field.
**Current output shape** (from `TP_API_Handler::validate_usage_summary_response`):
```php
// Current: { days: [{ date, totalHits, hitCost, balance }] }
// After merge: { days: [{ date, totalHits, hitCost, balance, otherServices }] }
```
**When to use:** The AJAX handler will call the merge adapter after `validate_usage_summary_response()`, passing in the `$days` array plus wallet transactions. The merged result replaces `$days` in the response.

### Anti-Patterns to Avoid
- **Mutating input arrays:** The adapter should not modify the input usage days or wallet transactions. Create new arrays.
- **One-to-one transaction mapping:** CONTEXT.md explicitly states multiple wallet transactions on the same day must be AGGREGATED into a single daily total, not mapped as separate rows.
- **Null vs empty confusion:** Days with no wallet activity get `otherServices: null`, NOT `{ amount: 0, items: [] }`. This distinction matters for UI rendering.
- **Filtering descriptions at the wrong layer:** Filter empty/whitespace descriptions from the `items` array content, but still include items with empty descriptions in the items array for amount tracking. Actually -- re-reading CONTEXT.md: "Filter out empty/whitespace-only descriptions before combining." This means items with empty descriptions should still appear (they have amounts), but when the UI combines descriptions, empty ones are skipped. The simplest approach: include all items in the array, store empty string for empty descriptions. The UI/consumer handles display logic.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date parsing/comparison | Custom regex date parser | `strcmp()` on YYYY-MM-DD strings | Lexicographic comparison of ISO date strings is inherently correct |
| Floating point precision | Manual rounding at every step | Let PHP handle float addition, round only at output if needed | The amounts are display values from the API, not currency calculations requiring BC math |

**Key insight:** This phase is intentionally simple -- the complexity budget is near zero. The WalletTransaction DTO (Phase 9) already handles date normalization. The adapter is a straightforward hash-map full outer join.

## Common Pitfalls

### Pitfall 1: Floating Point Display Drift
**What goes wrong:** Summing float amounts like `12.50 + 7.50` could yield `19.999999...` instead of `20.00`.
**Why it happens:** IEEE 754 floating point representation.
**How to avoid:** Round the aggregated `otherServices.amount` to 2 decimal places after summing: `round($total, 2)`. Individual item amounts come directly from the API and don't need rounding.
**Warning signs:** Test assertions failing on exact float comparison. Use `assertEqualsWithDelta()` or compare rounded values.

### Pitfall 2: Empty Wallet Transactions Array
**What goes wrong:** If wallet fetch fails or returns empty, adapter should return usage days unchanged with all `otherServices: null`.
**Why it happens:** Graceful degradation (Phase 11 concern, but adapter must handle it cleanly).
**How to avoid:** The merge algorithm naturally handles this -- if `$walletTransactions` is empty, the foreach is never entered, and all days retain `otherServices: null`.
**Warning signs:** Adapter crashing or returning incorrect structure when wallet array is empty.

### Pitfall 3: Duplicate Date Keys from Usage Data
**What goes wrong:** If usage API ever returns two records for the same date, the second overwrites the first in the hash map.
**Why it happens:** Unlikely but defensive coding matters.
**How to avoid:** This is actually acceptable behavior per the current API contract -- each date should appear once. But if paranoid, could aggregate usage records too. For now, trust the API contract (the `validate_usage_summary_response` already passes through data as-is).
**Warning signs:** Loss of usage data in merged output.

### Pitfall 4: Description Filtering Misunderstanding
**What goes wrong:** Removing items entirely when description is empty, losing the amount tracking.
**Why it happens:** Misreading "filter out empty descriptions" as "remove items with empty descriptions."
**How to avoid:** CONTEXT.md says "Filter out empty/whitespace-only descriptions before combining." The items array should include ALL transactions (they all have amounts). Items with empty descriptions store `""`. The "filtering" applies to how descriptions are combined for display -- the UI skips empty strings when building comma-separated text.
**Warning signs:** Wallet totals not matching when some transactions lack descriptions.

## Code Examples

### Input Data Shapes

**Usage days** (from `validate_usage_summary_response()`):
```php
$usageDays = [
    ['date' => '2026-03-01', 'totalHits' => 150, 'hitCost' => 0.75, 'balance' => 49.25],
    ['date' => '2026-03-02', 'totalHits' => 200, 'hitCost' => 1.00, 'balance' => 48.25],
];
```

**Wallet transactions** (from `TerrWalletClient::getTransactions()`):
```php
// WalletTransaction objects with ->date already normalized to YYYY-MM-DD
$walletTransactions = [
    new WalletTransaction('2026-03-01', 25.00, 'Store purchase', 101),
    new WalletTransaction('2026-03-01', 10.00, 'Referral bonus', 102),
    new WalletTransaction('2026-03-03', 50.00, 'Manual top-up', 103),  // No usage on this day
];
```

### Expected Output

```php
$merged = [
    [
        'date'      => '2026-03-01',
        'totalHits' => 150,
        'hitCost'   => 0.75,
        'balance'   => 49.25,
        'otherServices' => [
            'amount' => 35.00,
            'items'  => [
                ['amount' => 25.00, 'description' => 'Store purchase'],
                ['amount' => 10.00, 'description' => 'Referral bonus'],
            ],
        ],
    ],
    [
        'date'      => '2026-03-02',
        'totalHits' => 200,
        'hitCost'   => 1.00,
        'balance'   => 48.25,
        'otherServices' => null,
    ],
    [
        'date'      => '2026-03-03',
        'totalHits' => 0,
        'hitCost'   => 0.00,
        'balance'   => 0.00,
        'otherServices' => [
            'amount' => 50.00,
            'items'  => [
                ['amount' => 50.00, 'description' => 'Manual top-up'],
            ],
        ],
    ],
];
```

### Test Pattern (following existing project convention)

```php
namespace Tests\Unit\TerrWallet;

use PHPUnit\Framework\TestCase;
use TerrWallet\UsageMergeAdapter;
use TerrWallet\DTO\WalletTransaction;

class UsageMergeAdapterTest extends TestCase
{
    public function testMergesBothSources(): void
    {
        $usage = [
            ['date' => '2026-03-01', 'totalHits' => 100, 'hitCost' => 0.50, 'balance' => 49.50],
        ];
        $wallet = [
            new WalletTransaction('2026-03-01', 25.00, 'Top-up', 1),
        ];

        $result = UsageMergeAdapter::merge($usage, $wallet);

        $this->assertCount(1, $result);
        $this->assertSame('2026-03-01', $result[0]['date']);
        $this->assertSame(100, $result[0]['totalHits']);
        $this->assertNotNull($result[0]['otherServices']);
        $this->assertEqualsWithDelta(25.00, $result[0]['otherServices']['amount'], 0.001);
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| N/A | N/A | N/A | This is a new feature -- no migration needed |

**Deprecated/outdated:** Nothing applicable. Pure PHP array operations are stable.

## Open Questions

1. **Balance field for wallet-only days**
   - What we know: CONTEXT.md says `balance: 0.00` for wallet-only days
   - What's unclear: Is 0.00 the right semantic? The balance is a running tally from the usage API, so a wallet-only day wouldn't have a meaningful balance value from the usage source.
   - Recommendation: Use `0.00` as specified in CONTEXT.md. Phase 11/12 (integration/UI) can decide if balance should be interpolated from adjacent days or hidden for wallet-only rows. The adapter just follows the decision.

2. **Items array for transactions with empty descriptions**
   - What we know: "Filter out empty/whitespace-only descriptions before combining"
   - What's unclear: Should items with empty descriptions be included in the `items` array at all, or excluded? The amount would be lost if excluded.
   - Recommendation: Include all items in the array (preserves amount totals). Store `""` for empty descriptions. The "filter" instruction applies to the UI layer when building display strings. This preserves data integrity -- `sum(items[].amount) === otherServices.amount` always holds true.

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection: `includes/TerrWallet/DTO/WalletTransaction.php` -- confirmed date normalization to YYYY-MM-DD in `fromRaw()`
- Direct codebase inspection: `includes/class-tp-api-handler.php` lines 1644-1673 -- confirmed usage day shape `{date, totalHits, hitCost, balance}`
- Direct codebase inspection: `includes/autoload.php` -- confirmed PSR-4 autoloader pattern for TerrWallet namespace
- Direct codebase inspection: `tests/Unit/TrafficPortal/UserActivitySummaryTest.php` -- confirmed PHPUnit test conventions

### Secondary (MEDIUM confidence)
- None needed -- this is pure internal data transformation with no external dependencies

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No external dependencies, pure PHP
- Architecture: HIGH - Straightforward hash-map join, all input/output shapes confirmed from codebase
- Pitfalls: HIGH - Edge cases are limited and well-understood from CONTEXT.md decisions

**Research date:** 2026-03-10
**Valid until:** Indefinite -- pure PHP array operations, no version sensitivity
