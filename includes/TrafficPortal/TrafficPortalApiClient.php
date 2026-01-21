<?php

declare(strict_types=1);

namespace TrafficPortal;

use TrafficPortal\DTO\CreateMapRequest;
use TrafficPortal\DTO\CreateMapResponse;
use TrafficPortal\DTO\FingerprintSearchResponse;
use TrafficPortal\Exception\ApiException;
use TrafficPortal\Exception\AuthenticationException;
use TrafficPortal\Exception\ValidationException;
use TrafficPortal\Exception\NetworkException;
use TrafficPortal\Exception\RateLimitException;

/**
 * Traffic Portal API Client
 *
 * Client for interacting with the Traffic Portal API /items endpoint
 * to create masked records (shortlinks).
 *
 * @package TrafficPortal
 */
class TrafficPortalApiClient
{
    private string $apiEndpoint;
    private string $apiKey;
    private int $timeout;

    /**
     * Constructor
     *
     * @param string $apiEndpoint The API endpoint URL
     * @param string $apiKey The API key for authentication
     * @param int $timeout Request timeout in seconds (default: 30)
     */
    public function __construct(
        string $apiEndpoint,
        string $apiKey,
        int $timeout = 30
    ) {
        $this->apiEndpoint = rtrim($apiEndpoint, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    /**
     * Create a masked record (shortlink)
     *
     * @param CreateMapRequest $request The request data
     * @return CreateMapResponse The response data
     * @throws AuthenticationException If authentication fails
     * @throws ValidationException If validation fails
     * @throws RateLimitException If rate limit is exceeded (HTTP 429)
     * @throws NetworkException If network error occurs
     * @throws ApiException For other API errors
     */
    public function createMaskedRecord(CreateMapRequest $request): CreateMapResponse
    {
        $url = $this->apiEndpoint . '/items';
        $payload = $request->toArray();

        $this->log_to_file('=== CREATE MASKED RECORD REQUEST ===');
        $this->log_to_file('createMaskedRecord called');
        $this->log_to_file('Client IP: ' . $this->get_client_ip());
        $this->log_to_file('URL: ' . $url);
        $this->log_to_file('Fingerprint: ' . (isset($payload['fingerprint']) ? $payload['fingerprint'] : 'NOT SET'));
        $this->log_to_file('UID: ' . (isset($payload['uid']) ? $payload['uid'] : 'NOT SET'));
        $this->log_to_file('Destination: ' . (isset($payload['destination']) ? $payload['destination'] : 'NOT SET'));
        $this->log_to_file('tpKey: ' . (isset($payload['tpKey']) ? $payload['tpKey'] : 'NOT SET'));
        $this->log_to_file('Full Payload: ' . json_encode($payload, JSON_PRETTY_PRINT));

        // Initialize cURL
        $ch = curl_init($url);
        if ($ch === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        $jsonData = json_encode($payload);

        $this->log_to_file('JSON Payload Size: ' . strlen($jsonData) . ' bytes');

        // Prepare headers
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . substr($this->apiKey, 0, 10) . '...' // Masked for security
        ];

        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $this->log_to_file('Request Headers: ' . json_encode($headers));
        $this->log_to_file('Sending POST request to API...');

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        $this->log_to_file('HTTP Code: ' . $httpCode);
        $this->log_to_file('cURL errno: ' . $curlErrno);
        $this->log_to_file('Raw response: ' . $response);

        curl_close($ch);

        // Handle cURL errors
        if ($curlErrno !== 0) {
            $this->log_to_file('cURL ERROR: ' . $curlError);
            throw new NetworkException(
                sprintf('cURL error: %s', $curlError),
                $curlErrno
            );
        }

        // Handle empty response
        if ($response === false || $response === '') {
            $this->log_to_file('ERROR: Empty response from API');
            throw new NetworkException('Empty response from API');
        }

        // Decode JSON response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_to_file('ERROR: Invalid JSON response: ' . json_last_error_msg());
            throw new ApiException(
                sprintf('Invalid JSON response: %s', json_last_error_msg()),
                $httpCode
            );
        }

        $this->log_to_file('Decoded response: ' . json_encode($data, JSON_PRETTY_PRINT));

        // Handle HTTP errors
        $this->log_to_file('Checking for HTTP errors...');
        $this->handleHttpErrors($httpCode, $data);

        $this->log_to_file('Request completed successfully');
        $this->log_to_file('=== CREATE MASKED RECORD COMPLETE ===');

        // Parse and return response
        return CreateMapResponse::fromArray($data);
    }

    /**
     * Handle HTTP error responses
     *
     * @param int $httpCode The HTTP status code
     * @param array $data The response data
     * @throws AuthenticationException If authentication fails
     * @throws ValidationException If validation fails
     * @throws RateLimitException If rate limit is exceeded
     * @throws ApiException For other API errors
     */
    private function handleHttpErrors(int $httpCode, array $data): void
    {
        if ($httpCode >= 200 && $httpCode < 300) {
            return; // Success
        }

        $message = $data['message'] ?? 'Unknown error';
        $success = $data['success'] ?? false;

        switch ($httpCode) {
            case 401:
                throw new AuthenticationException($message, $httpCode);

            case 400:
                throw new ValidationException($message, $httpCode);

            case 429:
                throw new RateLimitException($message, $httpCode);

            case 502:
            case 500:
                throw new ApiException(
                    sprintf('Server error: %s', $message),
                    $httpCode
                );

            default:
                throw new ApiException(
                    sprintf('API error (HTTP %d): %s', $httpCode, $message),
                    $httpCode
                );
        }
    }

    /**
     * Get a masked record by key
     *
     * @param string $key The shortcode key to retrieve
     * @param int $uid The user ID
     * @return array|null The record data or null if not found
     * @throws AuthenticationException If authentication fails
     * @throws NetworkException If network error occurs
     * @throws ApiException For other API errors
     */
    public function getMaskedRecord(string $key, int $uid): ?array
    {
        $url = $this->apiEndpoint . '/items/' . urlencode($key) . '?uid=' . $uid;

        $this->log_to_file('=== GET MASKED RECORD REQUEST ===');
        $this->log_to_file('getMaskedRecord called');
        $this->log_to_file('Client IP: ' . $this->get_client_ip());
        $this->log_to_file('Key: ' . $key);
        $this->log_to_file('UID: ' . $uid);
        $this->log_to_file('URL: ' . $url);

        // Initialize cURL
        $ch = curl_init($url);
        if ($ch === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        // Handle cURL errors
        if ($curlErrno !== 0) {
            throw new NetworkException(
                sprintf('cURL error: %s', $curlError),
                $curlErrno
            );
        }

        $this->log_to_file('HTTP Code: ' . $httpCode);
        $this->log_to_file('Raw response: ' . substr($response, 0, 500)); // First 500 chars

        // Handle 404 - record not found
        if ($httpCode === 404) {
            $this->log_to_file('Record not found (404)');
            $this->log_to_file('=== GET MASKED RECORD COMPLETE ===');
            return null;
        }

        // Handle empty response
        if ($response === false || $response === '') {
            throw new NetworkException('Empty response from API');
        }

        // Decode JSON response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(
                sprintf('Invalid JSON response: %s', json_last_error_msg()),
                $httpCode
            );
        }

        // Handle HTTP errors
        $this->handleHttpErrors($httpCode, $data);

        $this->log_to_file('Record retrieved successfully');
        $this->log_to_file('=== GET MASKED RECORD COMPLETE ===');

        return $data;
    }

    /**
     * Update a masked record
     *
     * @param int $mid The masked record ID to update
     * @param array $updateData The update data (uid, tpTkn, domain, destination, status, expires_at, etc.)
     * @return array The response data
     * @throws AuthenticationException If authentication fails
     * @throws ValidationException If validation fails
     * @throws NetworkException If network error occurs
     * @throws ApiException For other API errors
     */
    /**
     * Log to file for debugging
     */
    private function log_to_file($message) {
        $log_file = WP_CONTENT_DIR . '/plugins/tp-update-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] API CLIENT: $message\n", FILE_APPEND);
    }

    /**
     * Get client IP address
     */
    private function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if ($key === 'HTTP_X_FORWARDED_FOR' && strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                return $ip;
            }
        }

        return 'UNKNOWN';
    }

    public function updateMaskedRecord(int $mid, array $updateData): array
    {
        $url = $this->apiEndpoint . '/items/' . $mid;

        $this->log_to_file('updateMaskedRecord called');
        $this->log_to_file('Client IP: ' . $this->get_client_ip());
        $this->log_to_file('URL: ' . $url);
        $this->log_to_file('MID: ' . $mid);
        $this->log_to_file('Update data: ' . json_encode($updateData));

        // Initialize cURL
        $ch = curl_init($url);
        if ($ch === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        $jsonData = json_encode($updateData);
        $this->log_to_file('JSON payload: ' . $jsonData);

        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $this->log_to_file('Sending PUT request to API...');

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        $this->log_to_file('HTTP Code: ' . $httpCode);
        $this->log_to_file('cURL errno: ' . $curlErrno);
        $this->log_to_file('Raw response: ' . $response);

        curl_close($ch);

        // Handle cURL errors
        if ($curlErrno !== 0) {
            $this->log_to_file('cURL ERROR: ' . $curlError);
            throw new NetworkException(
                sprintf('cURL error: %s', $curlError),
                $curlErrno
            );
        }

        // Handle empty response
        if ($response === false || $response === '') {
            $this->log_to_file('ERROR: Empty response from API');
            throw new NetworkException('Empty response from API');
        }

        // Decode JSON response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_to_file('ERROR: Invalid JSON response: ' . json_last_error_msg());
            throw new ApiException(
                sprintf('Invalid JSON response: %s', json_last_error_msg()),
                $httpCode
            );
        }

        $this->log_to_file('Decoded response: ' . json_encode($data));

        // Handle HTTP errors
        $this->log_to_file('Checking for HTTP errors...');
        $this->handleHttpErrors($httpCode, $data);

        $this->log_to_file('Request completed successfully');
        return $data;
    }

    /**
     * Bulk update expiry for multiple records (admin only)
     *
     * @param int $uid The admin user ID
     * @param string $token The admin token
     * @param array $updates Array of updates with 'mid' and 'expires_at' keys
     * @return array The response data with 'updated' and 'failed' arrays
     * @throws AuthenticationException If authentication fails
     * @throws ValidationException If validation fails
     * @throws NetworkException If network error occurs
     * @throws ApiException For other API errors
     */
    public function bulkUpdateExpiry(int $uid, string $token, array $updates): array
    {
        $url = $this->apiEndpoint . '/items/expiry/bulk';

        $payload = [
            'uid' => $uid,
            'tpTkn' => $token,
            'updates' => $updates,
        ];

        // Initialize cURL
        $ch = curl_init($url);
        if ($ch === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        // Handle cURL errors
        if ($curlErrno !== 0) {
            throw new NetworkException(
                sprintf('cURL error: %s', $curlError),
                $curlErrno
            );
        }

        // Handle empty response
        if ($response === false || $response === '') {
            throw new NetworkException('Empty response from API');
        }

        // Decode JSON response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(
                sprintf('Invalid JSON response: %s', json_last_error_msg()),
                $httpCode
            );
        }

        // Handle HTTP errors
        $this->handleHttpErrors($httpCode, $data);

        return $data;
    }

    /**
     * Search for records by browser fingerprint
     *
     * @param string $fingerprint The browser fingerprint to search for
     * @param int $uid The user ID
     * @param string $token The user token
     * @return FingerprintSearchResponse The response with matching records and usage stats
     * @throws AuthenticationException If authentication fails
     * @throws NetworkException If network error occurs
     * @throws ApiException For other API errors
     */
    public function searchByFingerprint(string $fingerprint, int $uid, string $token): FingerprintSearchResponse
    {
        $this->log_to_file('=== API CLIENT SEARCH BY FINGERPRINT START ===');
        $url = $this->apiEndpoint . '/items/by-fingerprint/' . urlencode($fingerprint);

        $this->log_to_file('Client IP: ' . $this->get_client_ip());
        $this->log_to_file('Fingerprint: ' . $fingerprint);
        $this->log_to_file('UID: ' . $uid);
        $this->log_to_file('URL: ' . $url);

        // Initialize cURL
        $ch = curl_init($url);
        if ($ch === false) {
            $this->log_to_file('ERROR: Failed to initialize cURL');
            throw new NetworkException('Failed to initialize cURL');
        }

        $this->log_to_file('cURL initialized successfully');

        // Set cURL options with uid and tpTkn in headers
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'uid: ' . $uid,
                'tpTkn: ' . $token,
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $this->log_to_file('Sending GET request to API...');

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        $this->log_to_file('HTTP Code: ' . $httpCode);
        $this->log_to_file('cURL errno: ' . $curlErrno);
        $this->log_to_file('Raw response (first 500 chars): ' . substr($response, 0, 500));

        curl_close($ch);

        // Handle cURL errors
        if ($curlErrno !== 0) {
            $this->log_to_file('cURL ERROR: ' . $curlError);
            $this->log_to_file('=== API CLIENT SEARCH BY FINGERPRINT END (CURL ERROR) ===');
            throw new NetworkException(
                sprintf('cURL error: %s', $curlError),
                $curlErrno
            );
        }

        // Handle empty response
        if ($response === false || $response === '') {
            $this->log_to_file('ERROR: Empty response from API');
            $this->log_to_file('=== API CLIENT SEARCH BY FINGERPRINT END (EMPTY RESPONSE) ===');
            throw new NetworkException('Empty response from API');
        }

        // Decode JSON response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_to_file('ERROR: Invalid JSON response: ' . json_last_error_msg());
            $this->log_to_file('=== API CLIENT SEARCH BY FINGERPRINT END (JSON ERROR) ===');
            throw new ApiException(
                sprintf('Invalid JSON response: %s', json_last_error_msg()),
                $httpCode
            );
        }

        $this->log_to_file('Decoded response: ' . json_encode($data, JSON_PRETTY_PRINT));

        // Handle HTTP errors
        $this->log_to_file('Checking for HTTP errors...');
        $this->handleHttpErrors($httpCode, $data);

        $this->log_to_file('Request completed successfully');
        $this->log_to_file('=== API CLIENT SEARCH BY FINGERPRINT END (SUCCESS) ===');

        return FingerprintSearchResponse::fromArray($data);
    }

    /**
     * Disable an anonymous link by fingerprint
     *
     * Marks the link as inactive, preventing it from redirecting.
     * This allows the user to create a new link with a different keyword/destination.
     *
     * @param string $fingerprint The browser fingerprint
     * @return array The response with success status and disabled link info
     * @throws AuthenticationException If authentication fails
     * @throws NetworkException If network error occurs
     * @throws ApiException For other API errors
     */
    public function disableLinkByFingerprint(string $fingerprint): array
    {
        $this->log_to_file('=== API CLIENT DISABLE LINK BY FINGERPRINT START ===');
        $url = $this->apiEndpoint . '/items/by-fingerprint/' . urlencode($fingerprint) . '/disable';

        $this->log_to_file('Client IP: ' . $this->get_client_ip());
        $this->log_to_file('Fingerprint: ' . $fingerprint);
        $this->log_to_file('URL: ' . $url);

        // Initialize cURL
        $ch = curl_init($url);
        if ($ch === false) {
            $this->log_to_file('ERROR: Failed to initialize cURL');
            throw new NetworkException('Failed to initialize cURL');
        }

        $this->log_to_file('cURL initialized successfully');

        // Set cURL options for DELETE request
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $this->log_to_file('Sending DELETE request to API...');

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        $this->log_to_file('HTTP Code: ' . $httpCode);
        $this->log_to_file('cURL errno: ' . $curlErrno);
        $this->log_to_file('Raw response: ' . $response);

        curl_close($ch);

        // Handle cURL errors
        if ($curlErrno !== 0) {
            $this->log_to_file('cURL ERROR: ' . $curlError);
            $this->log_to_file('=== API CLIENT DISABLE LINK BY FINGERPRINT END (CURL ERROR) ===');
            throw new NetworkException(
                sprintf('cURL error: %s', $curlError),
                $curlErrno
            );
        }

        // Handle empty response
        if ($response === false || $response === '') {
            $this->log_to_file('ERROR: Empty response from API');
            $this->log_to_file('=== API CLIENT DISABLE LINK BY FINGERPRINT END (EMPTY RESPONSE) ===');
            throw new NetworkException('Empty response from API');
        }

        // Decode JSON response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_to_file('ERROR: Invalid JSON response: ' . json_last_error_msg());
            $this->log_to_file('=== API CLIENT DISABLE LINK BY FINGERPRINT END (JSON ERROR) ===');
            throw new ApiException(
                sprintf('Invalid JSON response: %s', json_last_error_msg()),
                $httpCode
            );
        }

        $this->log_to_file('Decoded response: ' . json_encode($data, JSON_PRETTY_PRINT));

        // Handle HTTP errors (404 is expected if no link found)
        if ($httpCode === 404) {
            $this->log_to_file('No link found for fingerprint (404)');
            $this->log_to_file('=== API CLIENT DISABLE LINK BY FINGERPRINT END (NOT FOUND) ===');
            return [
                'success' => false,
                'message' => $data['message'] ?? 'No anonymous link found for this fingerprint.',
                'data' => null
            ];
        }

        // Handle other HTTP errors
        if ($httpCode >= 400) {
            $this->log_to_file('HTTP error: ' . $httpCode);
            $this->log_to_file('=== API CLIENT DISABLE LINK BY FINGERPRINT END (HTTP ERROR) ===');
            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to disable link.',
                'data' => null
            ];
        }

        $this->log_to_file('Request completed successfully');
        $this->log_to_file('=== API CLIENT DISABLE LINK BY FINGERPRINT END (SUCCESS) ===');

        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'Link disabled successfully.',
            'data' => $data['source'] ?? null
        ];
    }

    /**
     * Get the API endpoint
     *
     * @return string
     */
    public function getApiEndpoint(): string
    {
        return $this->apiEndpoint;
    }

    /**
     * Get the timeout setting
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
