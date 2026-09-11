<?php

/**
 * Test-environment tweaks for the playground harness. Loaded as a must-use
 * plugin, so it runs before the plugin under test.
 */

declare(strict_types=1);

// Block outbound HTTP: nothing the editor does in these tests should reach
// the network, and a hanging request would only slow the suite down.
add_filter('pre_http_request', static function (mixed $preempt, array $args, string $url): mixed {
    if (str_starts_with($url, 'https://api.wordpress.org/') || str_starts_with($url, 'https://downloads.wordpress.org/')) {
        return $preempt;
    }
    return new WP_Error('http_request_blocked', "Blocked in tests: {$url}");
}, 10, 3);
