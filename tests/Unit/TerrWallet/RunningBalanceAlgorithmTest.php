<?php

declare(strict_types=1);

namespace Tests\Unit\TerrWallet;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the running balance algorithm used in ajax_get_usage_summary().
 *
 * This mirrors the server-side compute_running_balances() logic without
 * requiring WordPress/AJAX dependencies. The algorithm:
 *   1. Sum credits and costs in the selected range
 *   2. If end_date < today, reverse bridge-period transactions
 *   3. Compute opening balance by reversing the selected range
 *   4. Walk forward assigning running balance per row
 */
class RunningBalanceAlgorithmTest extends TestCase
{
    /**
     * Apply the running balance algorithm to merged days.
     * Mirrors compute_running_balances() in class-tp-api-handler.php.
     *
     * @param array $days Merged day records (ascending date order)
     * @param float $currentWalletBalance Authoritative current balance
     * @param int   $bridgeCreditsCents Bridge-period credits (0 if endDate >= today)
     * @param int   $bridgeCostsCents   Bridge-period costs (0 if endDate >= today)
     */
    private function applyRunningBalances(
        array $days,
        float $currentWalletBalance,
        int $bridgeCreditsCents = 0,
        int $bridgeCostsCents = 0
    ): array {
        $currentBalanceCents = (int) round($currentWalletBalance * 100);

        $rangeCreditsCents = 0;
        $rangeCostsCents = 0;
        foreach ($days as $day) {
            $rangeCreditsCents += (int) round((float) (($day['otherServices']['amount'] ?? 0.0)) * 100);
            $rangeCostsCents += (int) round((float) $day['hitCost'] * 100);
        }

        $balanceAtEndCents = $currentBalanceCents - $bridgeCreditsCents + $bridgeCostsCents;
        $openingBalanceCents = $balanceAtEndCents - $rangeCreditsCents + $rangeCostsCents;

        $runningCents = $openingBalanceCents;
        foreach ($days as &$day) {
            $creditCents = (int) round((float) (($day['otherServices']['amount'] ?? 0.0)) * 100);
            $costCents = (int) round((float) $day['hitCost'] * 100);

            $runningCents += $creditCents;
            $runningCents -= $costCents;

            $day['balance'] = round($runningCents / 100, 2);
        }
        unset($day);

        return $days;
    }

    /**
     * Basic case: range ends today, no bridge needed.
     * User has $50 wallet, range has $10 credits and $3 costs.
     * Opening = 50 - 10 + 3 = 43. Final row should end at 50.
     */
    public function testBasicRunningBalanceNobridge(): void
    {
        $days = [
            ['date' => '2026-03-27', 'totalHits' => 0, 'hitCost' => 0.00, 'apiBalance' => null,
             'otherServices' => ['amount' => 10.00, 'items' => []]],
            ['date' => '2026-03-28', 'totalHits' => 30, 'hitCost' => 3.00, 'apiBalance' => -3.00,
             'otherServices' => null],
            ['date' => '2026-03-29', 'totalHits' => 0, 'hitCost' => 0.00, 'apiBalance' => -3.00,
             'otherServices' => null],
        ];

        $result = $this->applyRunningBalances($days, 50.00);

        // Opening = 50 - 10 + 3 = 43
        // Day 1: 43 + 10 - 0 = 53
        // Day 2: 53 + 0 - 3 = 50
        // Day 3: 50 + 0 - 0 = 50
        $this->assertSame(53.00, $result[0]['balance']);
        $this->assertSame(50.00, $result[1]['balance']);
        $this->assertSame(50.00, $result[2]['balance']);
    }

    /**
     * Historical range: end_date < today requires bridge period.
     * Wallet = $100 now. Bridge has $20 credits and $5 costs since end_date.
     * Balance at end_date = 100 - 20 + 5 = 85.
     */
    public function testHistoricalRangeWithBridge(): void
    {
        $days = [
            ['date' => '2026-03-01', 'totalHits' => 10, 'hitCost' => 1.00, 'apiBalance' => -1.00,
             'otherServices' => ['amount' => 5.00, 'items' => []]],
            ['date' => '2026-03-02', 'totalHits' => 20, 'hitCost' => 2.00, 'apiBalance' => -3.00,
             'otherServices' => null],
        ];

        $bridgeCreditsCents = 2000; // $20 in credits after range
        $bridgeCostsCents = 500;    // $5 in costs after range

        $result = $this->applyRunningBalances($days, 100.00, $bridgeCreditsCents, $bridgeCostsCents);

        // Balance at end = 100 - 20 + 5 = 85
        // Opening = 85 - 5 + (1+2) = 83
        // Day 1: 83 + 5 - 1 = 87
        // Day 2: 87 + 0 - 2 = 85
        $this->assertSame(87.00, $result[0]['balance']);
        $this->assertSame(85.00, $result[1]['balance']);
    }

    /**
     * Empty range returns empty array.
     */
    public function testEmptyDaysReturnsEmpty(): void
    {
        $result = $this->applyRunningBalances([], 50.00);
        $this->assertSame([], $result);
    }

    /**
     * Credits only, no costs: balance increases.
     */
    public function testCreditsOnlyNoCharges(): void
    {
        $days = [
            ['date' => '2026-03-28', 'totalHits' => 0, 'hitCost' => 0.00, 'apiBalance' => null,
             'otherServices' => ['amount' => 25.00, 'items' => []]],
            ['date' => '2026-03-29', 'totalHits' => 0, 'hitCost' => 0.00, 'apiBalance' => null,
             'otherServices' => ['amount' => 15.00, 'items' => []]],
        ];

        $result = $this->applyRunningBalances($days, 100.00);

        // Opening = 100 - 40 + 0 = 60
        // Day 1: 60 + 25 = 85
        // Day 2: 85 + 15 = 100
        $this->assertSame(85.00, $result[0]['balance']);
        $this->assertSame(100.00, $result[1]['balance']);
    }

    /**
     * Costs only, no credits: balance decreases.
     */
    public function testCostsOnlyNoCredits(): void
    {
        $days = [
            ['date' => '2026-03-28', 'totalHits' => 50, 'hitCost' => 5.00, 'apiBalance' => -5.00,
             'otherServices' => null],
            ['date' => '2026-03-29', 'totalHits' => 30, 'hitCost' => 3.00, 'apiBalance' => -8.00,
             'otherServices' => null],
        ];

        $result = $this->applyRunningBalances($days, 42.00);

        // Opening = 42 - 0 + 8 = 50
        // Day 1: 50 + 0 - 5 = 45
        // Day 2: 45 + 0 - 3 = 42
        $this->assertSame(45.00, $result[0]['balance']);
        $this->assertSame(42.00, $result[1]['balance']);
    }

    /**
     * Float precision: small amounts don't cause drift.
     */
    public function testFloatPrecisionNoDrift(): void
    {
        $days = [
            ['date' => '2026-03-29', 'totalHits' => 1, 'hitCost' => 0.01, 'apiBalance' => -0.01,
             'otherServices' => ['amount' => 0.1, 'items' => []]],
        ];

        $result = $this->applyRunningBalances($days, 10.00);

        // Opening = 10 - 0.1 + 0.01 = 9.91
        // Day 1: 9.91 + 0.1 - 0.01 = 10.00
        $this->assertSame(10.00, $result[0]['balance']);
    }
}
