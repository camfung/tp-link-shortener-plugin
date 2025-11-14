<?php
/**
 * Debug Test: Check exact type and format of UID being sent
 */

declare(strict_types=1);

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'TrafficPortal\\';
    $base_dir = __DIR__ . '/includes/TrafficPortal/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

use TrafficPortal\DTO\CreateMapRequest;

echo "================================================\n";
echo "UID Type Debug Test\n";
echo "================================================\n\n";

// Test different uid values and how they're serialized
$testCases = [
    -1,
    '-1',
    0,
    '0',
    125,
    '125',
];

foreach ($testCases as $uid) {
    echo "---\n";
    echo "Input UID: ";
    var_dump($uid);
    echo "Type: " . gettype($uid) . "\n";

    // Create request - this will enforce int type due to type hint
    try {
        $request = new CreateMapRequest(
            uid: $uid,
            tpKey: 'test',
            domain: 'dev.trfc.link',
            destination: 'https://example.com',
            status: 'active',
            type: 'redirect',
            isSet: 0,
            tags: '',
            notes: '',
            settings: '{}',
            cacheContent: 0
        );

        $array = $request->toArray();
        echo "Array UID value: ";
        var_dump($array['uid']);
        echo "Array UID type: " . gettype($array['uid']) . "\n";

        $json = json_encode($array);
        echo "JSON representation: ";
        // Extract just the uid field from JSON
        preg_match('/"uid":([^,}]+)/', $json, $matches);
        echo "uid field = " . ($matches[1] ?? 'not found') . "\n";

    } catch (TypeError $e) {
        echo "❌ TypeError: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "================================================\n";
echo "Now testing what the API client actually sends:\n";
echo "================================================\n\n";

// Load environment
$envFile = __DIR__ . '/.env.test';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            if (!empty($key = trim($key)) && !empty($value = trim($value))) {
                putenv("$key=$value");
            }
        }
    }
}

// Manually construct the request to see what cURL sends
$request = new CreateMapRequest(
    uid: -1,
    tpKey: 'debug-test',
    domain: getenv('TP_DOMAIN'),
    destination: 'https://example.com/debug',
    status: 'active',
    type: 'redirect',
    isSet: 0,
    tags: 'debug',
    notes: 'Debug test',
    settings: '{}',
    cacheContent: 0
);

$payload = $request->toArray();
$json = json_encode($payload);

echo "Full JSON payload that will be sent:\n";
echo $json . "\n\n";

echo "JSON encoded payload (formatted):\n";
echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

echo "Checking JSON_NUMERIC_CHECK behavior:\n";
echo "Without JSON_NUMERIC_CHECK: " . json_encode(['uid' => -1]) . "\n";
echo "With JSON_NUMERIC_CHECK:    " . json_encode(['uid' => -1], JSON_NUMERIC_CHECK) . "\n";
echo "String '-1' without flag:   " . json_encode(['uid' => '-1']) . "\n";
echo "String '-1' with flag:      " . json_encode(['uid' => '-1'], JSON_NUMERIC_CHECK) . "\n";
