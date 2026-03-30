<?php
/**
 * Stateless adapter that full-outer-joins usage day records with wallet
 * credit transactions by date, producing a unified daily dataset.
 *
 * No I/O, no WordPress dependencies -- pure data transformation.
 */

declare(strict_types=1);

namespace TerrWallet;

use TerrWallet\DTO\WalletTransaction;

class UsageMergeAdapter
{
    /**
     * Merge usage day records with wallet credit transactions by date.
     *
     * Performs a full outer join: days appearing in either source are included.
     * Usage-only days get otherServices: null. Wallet-only days get zero-filled
     * usage fields with apiBalance: null (balance is computed downstream).
     * Multiple transactions on the same day are aggregated.
     *
     * @param array<int, array{date: string, totalHits: int, hitCost: float, apiBalance: ?float}> $usageDays
     * @param WalletTransaction[] $walletTransactions
     * @return array<int, array{date: string, totalHits: int, hitCost: float, apiBalance: ?float, otherServices: ?array}>
     */
    public static function merge(array $usageDays, array $walletTransactions): array
    {
        // 1. Seed map from usage days, each with otherServices: null
        $map = [];
        foreach ($usageDays as $day) {
            $map[$day['date']] = [
                'date'          => $day['date'],
                'totalHits'     => $day['totalHits'],
                'hitCost'       => $day['hitCost'],
                'apiBalance'    => $day['apiBalance'],
                'otherServices' => null,
            ];
        }

        // 2. Iterate wallet transactions, grouping by date
        foreach ($walletTransactions as $tx) {
            $dateKey = $tx->date;

            // Create zero-filled usage record if date not in map
            if (!isset($map[$dateKey])) {
                $map[$dateKey] = [
                    'date'          => $dateKey,
                    'totalHits'     => 0,
                    'hitCost'       => 0.00,
                    'apiBalance'    => null,
                    'otherServices' => null,
                ];
            }

            // Initialize otherServices if null for this date
            if ($map[$dateKey]['otherServices'] === null) {
                $map[$dateKey]['otherServices'] = [
                    'amount' => 0.0,
                    'items'  => [],
                ];
            }

            // Add item and accumulate amount
            $map[$dateKey]['otherServices']['items'][] = [
                'amount'      => $tx->amount,
                'description' => $tx->description,
            ];
            $map[$dateKey]['otherServices']['amount'] += $tx->amount;
        }

        // 3. Round each otherServices amount to 2 decimal places
        foreach ($map as &$record) {
            if ($record['otherServices'] !== null) {
                $record['otherServices']['amount'] = round($record['otherServices']['amount'], 2);
            }
        }
        unset($record);

        // 4. Extract values, sort ascending by date, return indexed array
        $result = array_values($map);
        usort($result, static function (array $a, array $b): int {
            return strcmp($a['date'], $b['date']);
        });

        return $result;
    }
}
