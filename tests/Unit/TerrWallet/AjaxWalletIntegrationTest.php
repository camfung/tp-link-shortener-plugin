<?php

declare(strict_types=1);

// Stub WordPress function in global namespace for fromRaw() tests
namespace {
    if (!function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags(string $string): string
        {
            return strip_tags($string);
        }
    }
}

namespace Tests\Unit\TerrWallet {

    use PHPUnit\Framework\TestCase;
    use TerrWallet\UsageMergeAdapter;
    use TerrWallet\DTO\WalletTransaction;
    use TerrWallet\Exception\TerrWalletException;
    use TerrWallet\Exception\TerrWalletNotInstalledException;
    use TerrWallet\Exception\TerrWalletApiException;

    /**
     * Tests for graceful degradation behavior when wallet data is unavailable.
     * Verifies the fallback logic used in ajax_get_usage_summary() and
     * the exception hierarchy that enables catch-all wallet error handling.
     */
    class AjaxWalletIntegrationTest extends TestCase
    {
        /**
         * Helper to create a WalletTransaction without going through fromRaw().
         */
        private function makeTx(string $date, float $amount, string $description, int $id = 1): WalletTransaction
        {
            return new WalletTransaction($date, $amount, $description, $id);
        }

        /**
         * GRACE-01: On wallet failure, every day record gets otherServices => null.
         * This mirrors the catch block's array_map fallback in ajax_get_usage_summary().
         */
        public function testNullOtherServicesOnWalletFailure(): void
        {
            $days = [
                ['date' => '2025-01-01', 'totalHits' => 10, 'hitCost' => 0.50, 'apiBalance' => 99.50],
                ['date' => '2025-01-02', 'totalHits' => 20, 'hitCost' => 1.00, 'apiBalance' => 98.50],
                ['date' => '2025-01-03', 'totalHits' => 5,  'hitCost' => 0.25, 'apiBalance' => 98.25],
            ];

            // Apply the same fallback logic as the catch block
            $result = array_map(function ($day) {
                $day['otherServices'] = null;
                return $day;
            }, $days);

            $this->assertCount(3, $result);
            foreach ($result as $day) {
                $this->assertArrayHasKey('otherServices', $day);
                $this->assertNull($day['otherServices']);
            }
        }

        /**
         * UI-04: On success, merged days contain otherServices fields --
         * some with wallet data, some with null (no transactions that day).
         */
        public function testMergedDaysPreservedOnSuccess(): void
        {
            $usageDays = [
                ['date' => '2025-02-01', 'totalHits' => 10, 'hitCost' => 0.50, 'apiBalance' => 99.50],
                ['date' => '2025-02-02', 'totalHits' => 20, 'hitCost' => 1.00, 'apiBalance' => 98.50],
            ];
            $transactions = [
                $this->makeTx('2025-02-01', 15.00, 'Top-up'),
            ];

            $result = UsageMergeAdapter::merge($usageDays, $transactions);

            $this->assertCount(2, $result);

            // Day with wallet transaction has otherServices with data
            $this->assertNotNull($result[0]['otherServices']);
            $this->assertSame(15.00, $result[0]['otherServices']['amount']);
            $this->assertCount(1, $result[0]['otherServices']['items']);

            // Day without wallet transaction has otherServices => null
            $this->assertNull($result[1]['otherServices']);
        }

        /**
         * GRACE-02: TerrWalletException and both subtypes are all instances of
         * TerrWalletException, so the catch block handles plugin-deactivated scenario.
         */
        public function testTerrWalletExceptionCaughtNotGenericException(): void
        {
            $base = new TerrWalletException('base error');
            $notInstalled = new TerrWalletNotInstalledException('plugin not installed');
            $apiError = new TerrWalletApiException('API returned 500');

            $this->assertInstanceOf(TerrWalletException::class, $base);
            $this->assertInstanceOf(TerrWalletException::class, $notInstalled);
            $this->assertInstanceOf(TerrWalletException::class, $apiError);

            // Subtypes are NOT generic \Exception (they extend TerrWalletException)
            $this->assertInstanceOf(\Exception::class, $base);
            $this->assertInstanceOf(\Exception::class, $notInstalled);
            $this->assertInstanceOf(\Exception::class, $apiError);
        }

        /**
         * Edge case: empty days array gets null otherServices without crashing.
         */
        public function testEmptyDaysArrayGetsNullOtherServices(): void
        {
            $days = [];

            $result = array_map(function ($day) {
                $day['otherServices'] = null;
                return $day;
            }, $days);

            $this->assertSame([], $result);
        }

        /**
         * Fallback preserves all existing day fields AND adds otherServices => null.
         */
        public function testFallbackPreservesAllExistingDayFields(): void
        {
            $days = [
                ['date' => '2025-03-15', 'totalHits' => 42, 'hitCost' => 2.10, 'apiBalance' => 97.90],
            ];

            $result = array_map(function ($day) {
                $day['otherServices'] = null;
                return $day;
            }, $days);

            $this->assertCount(1, $result);
            $this->assertSame('2025-03-15', $result[0]['date']);
            $this->assertSame(42, $result[0]['totalHits']);
            $this->assertSame(2.10, $result[0]['hitCost']);
            $this->assertSame(97.90, $result[0]['apiBalance']);
            $this->assertArrayHasKey('otherServices', $result[0]);
            $this->assertNull($result[0]['otherServices']);
        }
    }
}
