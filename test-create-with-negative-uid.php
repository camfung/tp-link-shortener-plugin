<?php
/**
 * Integration Test: Create Map Item with UID -1
 *
 * This test sends a request to the Traffic Portal API with uid=-1
 * to verify that non-logged-in users can create shortlinks.
 */

declare(strict_types=1);

// Simple autoloader for standalone test (bypasses WordPress requirement)
spl_autoload_register(function ($class) {
    $prefix = 'TrafficPortal\\';
    $base_dir = __DIR__ . '/includes/TrafficPortal/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use TrafficPortal\TrafficPortalApiClient;
use TrafficPortal\DTO\CreateMapRequest;

// Load .env.test file if it exists
$envFile = __DIR__ . '/.env.test';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!empty($key) && !empty($value)) {
                putenv("$key=$value");
            }
        }
    }
}

// Configuration - Load from .env.test or environment
$apiEndpoint = getenv('TP_API_ENDPOINT') ?: 'https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev';
$apiKey = getenv('API_KEY') ?: '';
$domain = getenv('TP_DOMAIN') ?: 'dev.trfc.link';

if (empty($apiKey) || $apiKey === 'your-api-key-here') {
    die("ERROR: API_KEY is not set.\n" .
        "Please edit .env.test and add your API key.\n");
}

echo "================================================\n";
echo "Integration Test: Create Map Item with UID -1\n";
echo "================================================\n\n";

echo "Configuration:\n";
echo "  API Endpoint: $apiEndpoint\n";
echo "  Domain: $domain\n";
echo "  API Key: " . substr($apiKey, 0, 10) . "..." . "\n\n";

// Initialize API client
$client = new TrafficPortalApiClient($apiEndpoint, $apiKey, 30);

// Generate a unique test key
$testKey = 'test-' . substr(md5(uniqid((string)rand(), true)), 0, 8);
$testDestination = 'https://example.com/test-' . time();

echo "Test Parameters:\n";
echo "  UID: -1 (non-logged-in user)\n";
echo "  Key: $testKey\n";
echo "  Destination: $testDestination\n\n";

echo "Creating request...\n";

try {
    // Create the request with uid=-1
    $request = new CreateMapRequest(
        uid: -1,  // This is the key test: uid=-1 for non-logged-in users
        tpKey: $testKey,
        domain: $domain,
        destination: $testDestination,
        status: 'active',
        type: 'redirect',
        isSet: 0,
        tags: 'test,integration,uid-negative-one',
        notes: 'Integration test: UID -1 for non-logged-in users',
        settings: '{}',
        cacheContent: 0
    );

    echo "Request payload:\n";
    echo json_encode($request->toArray(), JSON_PRETTY_PRINT) . "\n\n";

    echo "Sending request to API...\n";
    $response = $client->createMaskedRecord($request);

    echo "\n✅ SUCCESS!\n\n";
    echo "Response:\n";
    echo "  Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n";
    echo "  Message: " . $response->getMessage() . "\n";
    echo "  MID: " . $response->getMid() . "\n";
    echo "  Short URL: https://$domain/$testKey\n\n";

    echo "Test Result: PASSED ✓\n";
    echo "The API successfully accepted uid=-1 for non-logged-in users.\n\n";

    exit(0);

} catch (TrafficPortal\Exception\AuthenticationException $e) {
    echo "\n❌ AUTHENTICATION ERROR\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n\n";
    echo "Test Result: FAILED ✗\n";
    exit(1);

} catch (TrafficPortal\Exception\ValidationException $e) {
    echo "\n❌ VALIDATION ERROR\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n\n";
    echo "Test Result: FAILED ✗\n";
    echo "This could mean the key is already taken or the API rejected uid=-1\n\n";
    exit(1);

} catch (TrafficPortal\Exception\NetworkException $e) {
    echo "\n❌ NETWORK ERROR\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n\n";
    echo "Test Result: FAILED ✗\n";
    exit(1);

} catch (TrafficPortal\Exception\ApiException $e) {
    echo "\n❌ API ERROR\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n\n";
    echo "Test Result: FAILED ✗\n";
    exit(1);

} catch (Exception $e) {
    echo "\n❌ UNEXPECTED ERROR\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "Type: " . get_class($e) . "\n\n";
    echo "Test Result: FAILED ✗\n";
    exit(1);
}
