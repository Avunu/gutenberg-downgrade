<?php

/**
 * Bootstrap for the pure-unit suite.
 *
 * WordPress is deliberately NOT loaded: Brain Monkey works by defining the
 * WordPress functions itself, so they must be undefined when a test starts.
 *
 * Load order: the plugin's own vendor/ first, so plugin code runs against the
 * dependency versions it ships; then the test toolchain; then the tests.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$pluginVendor = $root . '/vendor';
if (!is_file($pluginVendor . '/autoload.php')) {
    fwrite(STDERR, "Plugin dependencies are not installed: run `composer install`.\n");
    exit(1);
}
require_once $pluginVendor . '/autoload.php';

$toolsVendor = $root . '/tests/tools/vendor';
if (!is_file($toolsVendor . '/autoload.php')) {
    fwrite(STDERR, "Test toolchain is not installed: run `composer install --working-dir=tests/tools`.\n");
    exit(1);
}
require_once $toolsVendor . '/autoload.php';

// Test classes: a plain PSR-4 loader keeps them out of the shipped classmap.
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'GutenbergDowngrade\\Tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $root . '/tests/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// The constants the main plugin file defines.
define('GUTENBERG_DOWNGRADE_FILE', $root . '/gutenberg-downgrade.php');
define('GUTENBERG_DOWNGRADE_DIR', $root . '/');

// Minimal stand-ins for the core classes the plugin type-hints.
require_once $root . '/tests/Support/wp-doubles.php';
