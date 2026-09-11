<?php

/**
 * Global-namespace helpers the vendored Gutenberg 11.9 block files still call.
 *
 * 11.9's block PHP was built against the plugin's own lib/, which this
 * plugin does not ship. Everything the audited block files need from it is
 * defined here, guarded so a real Gutenberg install (or core) always wins.
 * Loaded by BlockRegistry::register() — never autoloaded, it is not a class.
 */

declare(strict_types=1);

if (!function_exists('gutenberg_experimental_to_kebab_case')) {
    /**
     * Used by 11.9's page-list.php to build CSS class names. Core's
     * _wp_to_kebab_case() has the same contract.
     */
    function gutenberg_experimental_to_kebab_case(string $string): string
    {
        return _wp_to_kebab_case($string);
    }
}
