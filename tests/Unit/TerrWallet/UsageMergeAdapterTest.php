<?php

declare(strict_types=1);

namespace Tests\Unit\TerrWallet;

use PHPUnit\Framework\TestCase;
use TerrWallet\UsageMergeAdapter;
use TerrWallet\DTO\WalletTransaction;

/**
 * Tests for UsageMergeAdapter::merge() -- full outer join of usage days
 * and wallet credit transactions by date.
 */
class UsageMergeAdapterTest extends TestCase
{
    /**
     * Helper to create a WalletTransaction without going through fromRaw()
     * (which depends on wp_strip_all_tags).
     */
    private function makeTx(string $date, float $amount, string $description, int $id = 1): WalletTransaction
    {
        return new WalletTransaction($date, $amount, $description, $id);
    }

    /**
     * MERGE-01: Usage day + single wallet transaction on same date
     * produces merged record with otherServices populated.
     */
    public function testMergeUsageDayWithSingleTransaction(): void
    {
        $usageDays = [
            ['date' => '2025-01-15', 'totalHits' => 42, 'hitCost' => 2.10, 'balance' => 97.90],
        ];
        $transactions = [
            $this->makeTx('2025-01-15', 10.00, 'Top-up'),
        ];

        $result = UsageMergeAdapter::merge($usageDays, $transactions);

        $this->assertCount(1, $result);
        $this->assertSame('2025-01-15', $result[0]['date']);
        $this->assertSame(42, $result[0]['totalHits']);
        $this->assertSame(2.10, $result[0]['hitCost']);
        $this->assertSame(97.90, $result[0]['balance']);
        $this->assertNotNull($result[0]['otherServices']);
        $this->assertSame(10.00, $result[0]['otherServices']['amount']);
        $this->assertCount(1, $result[0]['otherServices']['items']);
        $this->assertSame(10.00, $result[0]['otherServices']['items'][0]['amount']);
        $this->assertSame('Top-up', $result[0]['otherServices']['items'][0]['description']);
    }

    /**
     * MERGE-02: Multiple wallet transactions on the same day are aggregated.
     */
    public function testMergeMultipleTransactionsSameDay(): void
    {
        $usageDays = [
            ['date' => '2025-02-10', 'totalHits' => 5, 'hitCost' => 0.25, 'balance' => 50.00],
        ];
        $transactions = [
            $this->makeTx('2025-02-10', 15.50, 'Store purchase', 1),
            $this->makeTx('2025-02-10', 4.50, 'Bonus credit', 2),
        ];

        $result = UsageMergeAdapter::merge($usageDays, $transactions);

        $this->assertCount(1, $result);
        $this->assertSame(20.00, $result[0]['otherServices']['amount']);
        $this->assertCount(2, $result[0]['otherServices']['items']);
        $this->assertSame(15.50, $result[0]['otherServices']['items'][0]['amount']);
        $this->assertSame('Store purchase', $result[0]['otherServices']['items'][0]['description']);
        $this->assertSame(4.50, $result[0]['otherServices']['items'][1]['amount']);
        $this->assertSame('Bonus credit', $result[0]['otherServices']['items'][1]['description']);
    }

    /**
     * MERGE-03: Wallet-only day (no usage record) produces zero-filled usage fields.
     */
    public function testWalletOnlyDayZeroFilled(): void
    {
        $usageDays = [];
        $transactions = [
            $this->makeTx('2025-03-01', 25.00, 'Manual top-up'),
        ];

        $result = UsageMergeAdapter::merge($usageDays, $transactions);

        $this->assertCount(1, $result);
        $this->assertSame('2025-03-01', $result[0]['date']);
        $this->assertSame(0, $result[0]['totalHits']);
        $this->assertSame(0.00, $result[0]['hitCost']);
        $this->assertSame(0.00, $result[0]['balance']);
        $this->assertNotNull($result[0]['otherServices']);
        $this->assertSame(25.00, $result[0]['otherServices']['amount']);
    }

    /**
     * Usage-only days have otherServices: null.
     */
    public function testUsageOnlyDaysHaveNullOtherServices(): void
    {
        $usageDays = [
            ['date' => '2025-01-01', 'totalHits' => 10, 'hitCost' => 0.50, 'balance' => 99.50],
            ['date' => '2025-01-02', 'totalHits' => 20, 'hitCost' => 1.00, 'balance' => 98.50],
        ];
        $transactions = [];

        $result = UsageMergeAdapter::merge($usageDays, $transactions);

        $this->assertCount(2, $result);
        $this->assertNull($result[0]['otherServices']);
        $this->assertNull($result[1]['otherServices']);
    }

    /**
     * Both inputs empty returns empty array.
     */
    public function testBothEmptyReturnsEmptyArray(): void
    {
        $result = UsageMergeAdapter::merge([], []);
        $this->assertSame([], $result);
    }

    /**
     * Output is sorted ascending by date regardless of input order.
     */
    public function testOutputSortedAscendingByDate(): void
    {
        $usageDays = [
            ['date' => '2025-01-20', 'totalHits' => 5, 'hitCost' => 0.25, 'balance' => 50.00],
            ['date' => '2025-01-10', 'totalHits' => 3, 'hitCost' => 0.15, 'balance' => 60.00],
        ];
        $transactions = [
            $this->makeTx('2025-01-25', 10.00, 'Credit'),
        ];

        $result = UsageMergeAdapter::merge($usageDays, $transactions);

        $this->assertCount(3, $result);
        $this->assertSame('2025-01-10', $result[0]['date']);
        $this->assertSame('2025-01-20', $result[1]['date']);
        $this->assertSame('2025-01-25', $result[2]['date']);
    }

    /**
     * Float precision: summed amounts are rounded to 2 decimal places.
     */
    public function testFloatPrecisionRounding(): void
    {
        $usageDays = [
            ['date' => '2025-04-01', 'totalHits' => 1, 'hitCost' => 0.05, 'balance' => 99.95],
        ];
        // These amounts cause IEEE 754 drift: 0.1 + 0.2 = 0.30000000000000004
        $transactions = [
            $this->makeTx('2025-04-01', 0.1, 'A', 1),
            $this->makeTx('2025-04-01', 0.2, 'B', 2),
        ];

        $result = UsageMergeAdapter::merge($usageDays, $transactions);

        $this->assertSame(0.30, $result[0]['otherServices']['amount']);
    }

    /**
     * Items with empty descriptions preserve empty string in description field.
     */
    public function testEmptyDescriptionPreservedAsEmptyString(): void
    {
        $usageDays = [];
        $transactions = [
            $this->makeTx('2025-05-01', 5.00, '', 1),
            $this->makeTx('2025-05-01', 3.00, '   ', 2),
        ];

        $result = UsageMergeAdapter::merge($usageDays, $transactions);

        $this->assertCount(1, $result);
        $this->assertSame(8.00, $result[0]['otherServices']['amount']);
        $this->assertCount(2, $result[0]['otherServices']['items']);
        // Empty descriptions stored as-is (empty string), not filtered out
        $this->assertSame('', $result[0]['otherServices']['items'][0]['description']);
        $this->assertSame('   ', $result[0]['otherServices']['items'][1]['description']);
    }

    /**
     * Mixed scenario: some days usage-only, some both, some wallet-only.
     */
    public function testMixedScenario(): void
    {
        $usageDays = [
            ['date' => '2025-06-01', 'totalHits' => 10, 'hitCost' => 0.50, 'balance' => 100.00],
            ['date' => '2025-06-02', 'totalHits' => 20, 'hitCost' => 1.00, 'balance' => 99.00],
            ['date' => '2025-06-04', 'totalHits' => 5,  'hitCost' => 0.25, 'balance' => 73.75],
        ];
        $transactions = [
            $this->makeTx('2025-06-02', 50.00, 'Big top-up', 1),
            $this->makeTx('2025-06-03', 25.00, 'Auto-refill', 2),
        ];

        $result = UsageMergeAdapter::merge($usageDays, $transactions);

        // 4 unique dates: 06-01, 06-02, 06-03, 06-04
        $this->assertCount(4, $result);

        // 06-01: usage only
        $this->assertSame('2025-06-01', $result[0]['date']);
        $this->assertNull($result[0]['otherServices']);

        // 06-02: both
        $this->assertSame('2025-06-02', $result[1]['date']);
        $this->assertSame(20, $result[1]['totalHits']);
        $this->assertSame(50.00, $result[1]['otherServices']['amount']);

        // 06-03: wallet only
        $this->assertSame('2025-06-03', $result[2]['date']);
        $this->assertSame(0, $result[2]['totalHits']);
        $this->assertSame(25.00, $result[2]['otherServices']['amount']);

        // 06-04: usage only
        $this->assertSame('2025-06-04', $result[3]['date']);
        $this->assertNull($result[3]['otherServices']);
    }
}
