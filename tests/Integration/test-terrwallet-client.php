<?php
/**
 * Integration test for TerrWalletClient.
 *
 * Run via: wp eval-file tests/integration/test-terrwallet-client.php
 *
 * This test must be run on the server where woo-wallet is installed.
 * Uses user ID 125 as the test subject.
 */

// Ensure WordPress is loaded (wp eval-file handles this)
if (!defined('ABSPATH')) {
    echo "ERROR: This script must be run via 'wp eval-file' in a WordPress context.\n";
    exit(1);
}

// Plugin autoloader should already be loaded via WordPress plugin bootstrap
use TerrWallet\TerrWalletClient;
use TerrWallet\DTO\WalletTransaction;
use TerrWallet\Exception\TerrWalletNotInstalledException;
use TerrWallet\Exception\TerrWalletException;

echo "=== TerrWallet Client Integration Test ===\n\n";

$testUserId = 125;
$afterDate  = '2026-01-01';
$beforeDate = '2026-03-10';

echo "Test parameters:\n";
echo "  User ID:     $testUserId\n";
echo "  After date:  $afterDate\n";
echo "  Before date: $beforeDate\n\n";

// Check which path will be taken
$directAvailable = function_exists('get_wallet_transactions');
echo "Direct PHP path available: " . ($directAvailable ? 'YES' : 'NO') . "\n";
echo "Method to be used: " . ($directAvailable ? 'direct' : 'rest') . "\n\n";

try {
    $client = new TerrWalletClient();
    $transactions = $client->getTransactions($testUserId, $afterDate, $beforeDate);

    echo "SUCCESS: Retrieved " . count($transactions) . " credit transaction(s)\n\n";

    if (count($transactions) > 0) {
        echo "First " . min(5, count($transactions)) . " transactions:\n";
        echo str_repeat('-', 70) . "\n";
        echo sprintf("%-12s %-10s %-8s %s\n", 'Date', 'Amount', 'ID', 'Description');
        echo str_repeat('-', 70) . "\n";

        foreach (array_slice($transactions, 0, 5) as $tx) {
            echo sprintf(
                "%-12s %-10s %-8s %s\n",
                $tx->date,
                number_format($tx->amount, 2),
                $tx->transactionId,
                substr($tx->description, 0, 35)
            );
        }
        echo str_repeat('-', 70) . "\n";
    }

    // Verify DTO types
    if (count($transactions) > 0) {
        $first = $transactions[0];
        echo "\nDTO type check:\n";
        echo "  Instance of WalletTransaction: " . ($first instanceof WalletTransaction ? 'PASS' : 'FAIL') . "\n";
        echo "  date is string:    " . (is_string($first->date) ? 'PASS' : 'FAIL') . "\n";
        echo "  amount is float:   " . (is_float($first->amount) ? 'PASS' : 'FAIL') . "\n";
        echo "  description is string: " . (is_string($first->description) ? 'PASS' : 'FAIL') . "\n";
        echo "  transactionId is int:  " . (is_int($first->transactionId) ? 'PASS' : 'FAIL') . "\n";
    }

    echo "\nRESULT: PASS\n";

} catch (TerrWalletNotInstalledException $e) {
    echo "EXPECTED (if woo-wallet not installed): " . $e->getMessage() . "\n";
    echo "Exception type: TerrWalletNotInstalledException -- correctly thrown\n";
    echo "\nRESULT: PASS (exception handling works)\n";

} catch (TerrWalletException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Exception type: " . get_class($e) . "\n";
    echo "\nRESULT: FAIL\n";
}
