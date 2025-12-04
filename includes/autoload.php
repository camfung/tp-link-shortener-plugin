<?php
/**
 * Autoloader for Traffic Portal API Client
 *
 * Loads the PHP API client classes
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the API clients from bundled directory
$includes_path = TP_LINK_SHORTENER_PLUGIN_DIR . '/includes';

// Register PSR-4 autoloader for TrafficPortal namespace
spl_autoload_register(function ($class) use ($includes_path) {
    // Check if class is in TrafficPortal namespace
    $prefix = 'TrafficPortal\\';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    $file = $includes_path . '/TrafficPortal/' . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Register PSR-4 autoloader for SnapCapture namespace
spl_autoload_register(function ($class) use ($includes_path) {
    // Check if class is in SnapCapture namespace
    $prefix = 'SnapCapture\\';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    $file = $includes_path . '/SnapCapture/' . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
