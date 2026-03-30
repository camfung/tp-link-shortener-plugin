<?php

declare(strict_types=1);

namespace WooWallet\DTO;

/**
 * Immutable value object representing a wallet transaction from the WooWallet API.
 *
 * @package WooWallet\DTO
 */
class WalletTransaction
{
    public readonly int $transactionId;
    public readonly int $userId;
    public readonly string $date;
    public readonly string $type;
    public readonly float $amount;
    public readonly float $balance;
    public readonly string $details;
    public readonly string $currency;
    public readonly int $blogId;

    public function __construct(
        int $transactionId,
        int $userId,
        string $date,
        string $type,
        float $amount,
        float $balance,
        string $details,
        string $currency,
        int $blogId
    ) {
        $this->transactionId = $transactionId;
        $this->userId = $userId;
        $this->date = $date;
        $this->type = $type;
        $this->amount = $amount;
        $this->balance = $balance;
        $this->details = $details;
        $this->currency = $currency;
        $this->blogId = $blogId;
    }

    /**
     * Create from API response array.
     *
     * @param array $data Single transaction from the API response
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            transactionId: (int) ($data['transaction_id'] ?? 0),
            userId: (int) ($data['user_id'] ?? 0),
            date: (string) ($data['date'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            amount: (float) ($data['amount'] ?? 0.0),
            balance: (float) ($data['balance'] ?? 0.0),
            details: (string) ($data['details'] ?? ''),
            currency: (string) ($data['currency'] ?? ''),
            blogId: (int) ($data['blog_id'] ?? 0),
        );
    }
}
