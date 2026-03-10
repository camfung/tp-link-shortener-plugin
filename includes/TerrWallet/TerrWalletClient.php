<?php
/**
 * TerrWallet Client - Fetches wallet credit transactions for a user.
 *
 * Dual-mode access: direct PHP calls (primary) or REST API fallback (cron/CLI).
 */

declare(strict_types=1);

namespace TerrWallet;

use TerrWallet\DTO\WalletTransaction;
use TerrWallet\Exception\TerrWalletException;
use TerrWallet\Exception\TerrWalletNotInstalledException;
use TerrWallet\Exception\TerrWalletApiException;

class TerrWalletClient
{
    /**
     * Fetch credit transactions for a user within a date range.
     *
     * Tries direct PHP function first (page load context).
     * Falls back to WC REST API via rest_do_request() for cron/CLI contexts.
     *
     * @param int    $userId    WordPress user ID.
     * @param string $afterDate  Start date in YYYY-MM-DD format.
     * @param string $beforeDate End date in YYYY-MM-DD format.
     * @return WalletTransaction[] Array of parsed credit transactions.
     * @throws TerrWalletNotInstalledException If woo-wallet plugin is not available (REST fallback also fails).
     * @throws TerrWalletApiException          If REST API returns an error.
     * @throws TerrWalletException             If WC API credentials are not configured.
     */
    public function getTransactions(int $userId, string $afterDate, string $beforeDate): array
    {
        if (function_exists('get_wallet_transactions')) {
            return $this->fetchViaDirect($userId, $afterDate, $beforeDate);
        }

        return $this->fetchViaRest($userId, $afterDate, $beforeDate);
    }

    /**
     * Fetch transactions via direct PHP call to get_wallet_transactions().
     *
     * Primary path -- no permission overhead, works for any logged-in user.
     *
     * @param int    $userId
     * @param string $afterDate
     * @param string $beforeDate
     * @return WalletTransaction[]
     */
    private function fetchViaDirect(int $userId, string $afterDate, string $beforeDate): array
    {
        $transactions = get_wallet_transactions([
            'user_id'  => $userId,
            'where'    => [
                [
                    'key'      => 'type',
                    'value'    => 'credit',
                    'operator' => '=',
                ],
            ],
            'after'    => $afterDate . ' 00:00:00',
            'before'   => $beforeDate . ' 23:59:59',
            'order_by' => 'date',
            'order'    => 'ASC',
            'limit'    => '',  // No limit = all results
        ]);

        if (!is_array($transactions)) {
            return [];
        }

        return array_map(
            fn($raw) => WalletTransaction::fromRaw($raw),
            $transactions
        );
    }

    /**
     * Fetch transactions via WC REST API using rest_do_request().
     *
     * Fallback for cron/CLI contexts where get_wallet_transactions() is not available.
     * Requires TP_WC_CONSUMER_KEY and TP_WC_CONSUMER_SECRET constants in wp-config.php.
     *
     * @param int    $userId
     * @param string $afterDate
     * @param string $beforeDate
     * @return WalletTransaction[]
     * @throws TerrWalletException    If WC API credentials are not configured.
     * @throws TerrWalletApiException If REST API returns an error.
     */
    private function fetchViaRest(int $userId, string $afterDate, string $beforeDate): array
    {
        if (!defined('TP_WC_CONSUMER_KEY') || !defined('TP_WC_CONSUMER_SECRET')) {
            throw new TerrWalletNotInstalledException(
                'woo-wallet plugin is not available (direct function missing) and WC API credentials are not configured. '
                . 'Either install the woo-wallet plugin or add TP_WC_CONSUMER_KEY and TP_WC_CONSUMER_SECRET to wp-config.php.'
            );
        }

        // v3 API requires email, not user ID
        $userData = get_userdata($userId);
        if (!$userData) {
            throw new TerrWalletException(
                'Could not resolve user ID ' . $userId . ' to an email address.'
            );
        }
        $userEmail = $userData->user_email;

        // Paginate through all results
        $allTransactions = [];
        $page = 1;
        $perPage = 100;

        do {
            $request = new \WP_REST_Request('GET', '/wc/v3/wallet');
            $request->set_query_params([
                'email'           => $userEmail,
                'per_page'        => $perPage,
                'page'            => $page,
                'consumer_key'    => TP_WC_CONSUMER_KEY,
                'consumer_secret' => TP_WC_CONSUMER_SECRET,
            ]);

            $response = rest_do_request($request);

            if ($response->is_error()) {
                $error = $response->as_error();
                throw new TerrWalletApiException(
                    'TerrWallet REST API error: ' . $error->get_error_message()
                );
            }

            $data = $response->get_data();

            if (!is_array($data)) {
                break;
            }

            $allTransactions = array_merge($allTransactions, $data);
            $page++;
        } while (count($data) === $perPage);

        // Filter to credit transactions only
        $credits = array_filter($allTransactions, function ($item) {
            $type = is_object($item) ? ($item->type ?? '') : ($item['type'] ?? '');
            return $type === 'credit';
        });

        // Filter by date range in PHP (REST API has no native date filtering)
        $filtered = array_filter($credits, function ($item) use ($afterDate, $beforeDate) {
            $rawDate = is_object($item) ? ($item->date ?? '') : ($item['date'] ?? '');
            $date = substr((string) $rawDate, 0, 10);
            return $date >= $afterDate && $date <= $beforeDate;
        });

        // Map to WalletTransaction DTOs (cast arrays to objects for fromRaw())
        return array_values(array_map(
            fn($item) => WalletTransaction::fromRaw(is_object($item) ? $item : (object) $item),
            $filtered
        ));
    }
}
