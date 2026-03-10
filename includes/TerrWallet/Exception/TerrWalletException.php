<?php
/**
 * Base exception for all TerrWallet errors.
 *
 * Callers can catch this single type to handle any wallet-related failure.
 */

declare(strict_types=1);

namespace TerrWallet\Exception;

class TerrWalletException extends \Exception {}
