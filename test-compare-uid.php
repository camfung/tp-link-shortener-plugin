<?php
/**
 * Comparison Test: Test different UID values
 *
 * This test compares creating shortlinks with different UID values
 * to determine which values the API accepts.
 */

declare(strict_types=1);

// Simple autoloader for standalone test
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

// Load .env.test file
$envFile = __DIR__ . '/.env.test';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
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

$apiEndpoint = getenv('TP_API_ENDPOINT');
$apiKey = getenv('API_KEY');
$domain = getenv('TP_DOMAIN');

echo "================================================\n";
echo "UID Comparison Test\n";
echo "================================================\n\n";

// Test different UID values
$testCases = [
    ['uid' => 125, 'description' => 'Valid UID (default from settings)'],
    ['uid' => 0, 'description' => 'UID = 0'],
    ['uid' => -1, 'description' => 'UID = -1 (non-logged-in)'],
];

$client = new TrafficPortalApiClient($apiEndpoint, $apiKey, 30);

foreach ($testCases as $testCase) {
    $uid = $testCase['uid'];
    $description = $testCase['description'];

    $testKey = 'test-uid' . $uid . '-' . substr(md5(uniqid((string)rand(), true)), 0, 6);
    $testDestination = 'https://example.com/test-uid-' . $uid . '-' . time();

    echo "---\n";
    echo "Test: $description\n";
    echo "UID: $uid\n";
    echo "Key: $testKey\n";

    try {
        $request = new CreateMapRequest(
            uid: $uid,
            tpKey: $testKey,
            domain: $domain,
            destination: $testDestination,
            status: 'active',
            type: 'redirect',
            isSet: 0,
            tags: 'test,uid-comparison',
            notes: "Test with uid=$uid",
            settings: '{}',
            cacheContent: 0
        );

        $response = $client->createMaskedRecord($request);

        echo "Result: ✅ SUCCESS\n";
        echo "Message: " . $response->getMessage() . "\n";
        echo "MID: " . $response->getMid() . "\n\n";

    } catch (Exception $e) {
        echo "Result: ❌ FAILED\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "Type: " . get_class($e) . "\n\n";
    }
}

echo "================================================\n";
echo "Test Complete\n";
echo "================================================\n";
