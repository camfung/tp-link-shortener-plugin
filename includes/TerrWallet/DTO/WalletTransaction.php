<?php
/**
 * Immutable value object representing a parsed wallet credit transaction.
 */

declare(strict_types=1);

namespace TerrWallet\DTO;

class WalletTransaction
{
    public readonly string $date;
    public readonly float $amount;
    public readonly string $description;
    public readonly int $transactionId;

    public function __construct(
        string $date,
        float $amount,
        string $description,
        int $transactionId
    ) {
        $this->date          = $date;
        $this->amount        = $amount;
        $this->description   = $description;
        $this->transactionId = $transactionId;
    }

    /**
     * Create a WalletTransaction from a raw transaction object returned by
     * get_wallet_transactions() or the WC REST API.
     *
     * @param object $raw Raw transaction object with transaction_id, amount, date, details fields.
     * @return self
     */
    public static function fromRaw(object $raw): self
    {
        // Extract date portion (Y-m-d) from MySQL datetime string
        $date = substr((string) $raw->date, 0, 10);

        return new self(
            $date,
            (float) $raw->amount,
            wp_strip_all_tags((string) $raw->details),
            (int) $raw->transaction_id
        );
    }
}
