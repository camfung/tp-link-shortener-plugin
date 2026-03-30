<?php

declare(strict_types=1);

namespace WooWallet\DTO;

/**
 * Immutable value object representing a wallet balance.
 *
 * @package WooWallet\DTO
 */
class WalletBalance
{
    public readonly string $email;
    public readonly float $balance;

    public function __construct(string $email, float $balance)
    {
        $this->email = $email;
        $this->balance = $balance;
    }
}
