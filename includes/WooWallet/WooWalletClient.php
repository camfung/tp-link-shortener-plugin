<?php

declare(strict_types=1);

namespace WooWallet;

use WooWallet\DTO\WalletBalance;
use WooWallet\DTO\WalletTransaction;
use WooWallet\Exception\ApiException;
use WooWallet\Exception\AuthenticationException;
use WooWallet\Exception\NetworkException;
use WooWallet\Exception\ValidationException;
use WooWallet\Http\HttpClientInterface;
use WooWallet\Http\CurlHttpClient;

/**
 * WooWallet API V3 Client
 *
 * Client for interacting with the WooCommerce Wallet REST API (wc/v3/wallet).
 *
 * @see https://github.com/malsubrata/woo-wallet/wiki/API-V3
 * @package WooWallet
 */
class WooWalletClient
{
    private string $baseUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private HttpClientInterface $httpClient;

    /**
     * @param string $baseUrl Site URL (e.g. https://example.com)
     * @param string $consumerKey WC REST API consumer key
     * @param string $consumerSecret WC REST API consumer secret
     * @param HttpClientInterface|null $httpClient HTTP client (defaults to CurlHttpClient)
     * @param int $timeout Request timeout in seconds
     */
    public function __construct(
        string $baseUrl,
        string $consumerKey,
        string $consumerSecret,
        ?HttpClientInterface $httpClient = null,
        int $timeout = 30
    ) {
        $this->baseUrl = rtrim($baseUrl, '/') . '/wp-json/wc/v3';
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->httpClient = $httpClient ?? new CurlHttpClient($timeout);
    }

    /**
     * Get wallet transactions for a user.
     *
     * @param string $email User's email address
     * @param int|null $perPage Number of transactions per page
     * @param int|null $page Page number
     * @return WalletTransaction[]
     * @throws AuthenticationException If WC API authentication fails
     * @throws ValidationException If the email is invalid
     * @throws NetworkException If a network error occurs
     * @throws ApiException For other API errors
     */
    public function getTransactions(string $email, ?int $perPage = null, ?int $page = null): array
    {
        $params = ['email' => $email];
        if ($perPage !== null) {
            $params['per_page'] = $perPage;
        }
        if ($page !== null) {
            $params['page'] = $page;
        }

        $url = $this->buildUrl('/wallet', $params);

        $this->log('=== GET TRANSACTIONS START ===');
        $this->log('Email: ' . $email);
        $this->log('URL: ' . preg_replace('/consumer_secret=[^&]+/', 'consumer_secret=***', $url));

        try {
            $response = $this->httpClient->request('GET', $url);

            $this->log('Response status: ' . $response->getStatusCode());

            $data = $this->parseResponse($response->getStatusCode(), $response->getBody());

            if (!is_array($data)) {
                throw new ApiException('Expected array of transactions, got ' . gettype($data));
            }

            $transactions = array_map(
                fn(array $item) => WalletTransaction::fromArray($item),
                $data
            );

            $this->log('Parsed ' . count($transactions) . ' transactions');
            $this->log('=== GET TRANSACTIONS END ===');

            return $transactions;

        } catch (NetworkException $e) {
            $this->log('EXCEPTION - NetworkException: ' . $e->getMessage());
            $this->log('=== GET TRANSACTIONS END ===');
            throw $e;
        } catch (\Exception $e) {
            $this->log('EXCEPTION - ' . get_class($e) . ': ' . $e->getMessage());
            $this->log('=== GET TRANSACTIONS END ===');
            if ($e instanceof AuthenticationException || $e instanceof ValidationException || $e instanceof ApiException) {
                throw $e;
            }
            throw new NetworkException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get all transactions for a user (handles pagination automatically).
     *
     * @param string $email User's email address
     * @param int $perPage Page size (default 100)
     * @return WalletTransaction[]
     */
    public function getAllTransactions(string $email, int $perPage = 100): array
    {
        $all = [];
        $page = 1;

        do {
            $batch = $this->getTransactions($email, $perPage, $page);
            $all = array_merge($all, $batch);
            $page++;
        } while (count($batch) === $perPage);

        return $all;
    }

    /**
     * Get wallet balance for a user.
     *
     * @param string $email User's email address
     * @return WalletBalance
     * @throws AuthenticationException If WC API authentication fails
     * @throws ValidationException If the email is invalid
     * @throws NetworkException If a network error occurs
     * @throws ApiException For other API errors
     */
    public function getBalance(string $email): WalletBalance
    {
        $url = $this->buildUrl('/wallet/balance', ['email' => $email]);

        $this->log('=== GET BALANCE START ===');
        $this->log('Email: ' . $email);

        try {
            $response = $this->httpClient->request('GET', $url);

            $this->log('Response status: ' . $response->getStatusCode());

            $data = $this->parseResponse($response->getStatusCode(), $response->getBody());

            $balance = is_numeric($data) ? (float) $data : (float) ($data['balance'] ?? 0.0);

            $this->log('Balance: ' . $balance);
            $this->log('=== GET BALANCE END ===');

            return new WalletBalance($email, $balance);

        } catch (NetworkException $e) {
            $this->log('EXCEPTION - NetworkException: ' . $e->getMessage());
            $this->log('=== GET BALANCE END ===');
            throw $e;
        } catch (\Exception $e) {
            $this->log('EXCEPTION - ' . get_class($e) . ': ' . $e->getMessage());
            $this->log('=== GET BALANCE END ===');
            if ($e instanceof AuthenticationException || $e instanceof ValidationException || $e instanceof ApiException) {
                throw $e;
            }
            throw new NetworkException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create a wallet transaction (credit or debit).
     *
     * @param string $email User's email address
     * @param string $type Transaction type: "credit" or "debit"
     * @param float $amount Transaction amount
     * @param string|null $note Optional transaction note
     * @return int Transaction ID
     * @throws AuthenticationException If WC API authentication fails
     * @throws ValidationException If the request is invalid
     * @throws NetworkException If a network error occurs
     * @throws ApiException For other API errors
     */
    public function createTransaction(string $email, string $type, float $amount, ?string $note = null): int
    {
        $url = $this->buildUrl('/wallet');

        $payload = [
            'email' => $email,
            'type' => $type,
            'amount' => $amount,
        ];
        if ($note !== null) {
            $payload['note'] = $note;
        }

        $this->log('=== CREATE TRANSACTION START ===');
        $this->log('Email: ' . $email);
        $this->log('Type: ' . $type);
        $this->log('Amount: ' . $amount);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type: application/json',
                ],
                'body' => json_encode($payload),
            ]);

            $this->log('Response status: ' . $response->getStatusCode());

            $data = $this->parseResponse($response->getStatusCode(), $response->getBody());

            $transactionId = is_numeric($data) ? (int) $data : (int) ($data['id'] ?? $data['transaction_id'] ?? 0);

            $this->log('Transaction ID: ' . $transactionId);
            $this->log('=== CREATE TRANSACTION END ===');

            return $transactionId;

        } catch (NetworkException $e) {
            $this->log('EXCEPTION - NetworkException: ' . $e->getMessage());
            $this->log('=== CREATE TRANSACTION END ===');
            throw $e;
        } catch (\Exception $e) {
            $this->log('EXCEPTION - ' . get_class($e) . ': ' . $e->getMessage());
            $this->log('=== CREATE TRANSACTION END ===');
            if ($e instanceof AuthenticationException || $e instanceof ValidationException || $e instanceof ApiException) {
                throw $e;
            }
            throw new NetworkException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Convenience: credit a user's wallet.
     */
    public function credit(string $email, float $amount, ?string $note = null): int
    {
        return $this->createTransaction($email, 'credit', $amount, $note);
    }

    /**
     * Convenience: debit a user's wallet.
     */
    public function debit(string $email, float $amount, ?string $note = null): int
    {
        return $this->createTransaction($email, 'debit', $amount, $note);
    }

    /**
     * Build a full URL with query parameters including WC auth.
     */
    private function buildUrl(string $path, array $params = []): string
    {
        $params['consumer_key'] = $this->consumerKey;
        $params['consumer_secret'] = $this->consumerSecret;

        return $this->baseUrl . $path . '?' . http_build_query($params);
    }

    /**
     * Parse and validate the API response.
     *
     * @return mixed Decoded response data
     */
    private function parseResponse(int $statusCode, string $body): mixed
    {
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // The balance endpoint may return a plain number
            if (is_numeric(trim($body))) {
                return (float) trim($body);
            }
            throw new ApiException(
                sprintf('Invalid JSON response: %s', json_last_error_msg()),
                $statusCode
            );
        }

        if ($statusCode >= 400) {
            $message = $data['message'] ?? $body;
            $code = $data['code'] ?? '';

            $this->handleHttpErrors($statusCode, $message, $code);
        }

        return $data;
    }

    /**
     * Handle HTTP error responses by throwing typed exceptions.
     */
    private function handleHttpErrors(int $statusCode, string $message, string $code): void
    {
        if ($code === 'invalid_username') {
            throw new ValidationException($message, $statusCode);
        }

        switch ($statusCode) {
            case 401:
            case 403:
                throw new AuthenticationException($message, $statusCode);

            case 400:
                throw new ValidationException($message, $statusCode);

            case 500:
            case 502:
            case 503:
                throw new ApiException(
                    sprintf('Server error: %s', $message),
                    $statusCode
                );

            default:
                throw new ApiException(
                    sprintf('API error (HTTP %d): %s', $statusCode, $message),
                    $statusCode
                );
        }
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    private function log(string $message): void
    {
        if (!defined('WP_CONTENT_DIR')) {
            return;
        }

        $logFile = WP_CONTENT_DIR . '/plugins/tp-update-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] WOOWALLET CLIENT: $message\n", FILE_APPEND);
    }
}
