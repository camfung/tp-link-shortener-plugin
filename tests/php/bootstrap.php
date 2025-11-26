<?php
/**
 * PHPUnit Bootstrap File
 */

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Define WordPress ABSPATH constant for plugin code that checks it
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../');
}

// Define plugin directory constant
if (!defined('TP_LINK_SHORTENER_PLUGIN_DIR')) {
    define('TP_LINK_SHORTENER_PLUGIN_DIR', dirname(__DIR__, 2));
}

// Load the plugin autoloader
require_once TP_LINK_SHORTENER_PLUGIN_DIR . '/includes/autoload.php';
