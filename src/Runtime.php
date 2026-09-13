<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

/**
 * Decides, once per request, whether the downgrade applies.
 *
 * The public front end, cron and WP-CLI keep WordPress' own block library:
 * only wp-admin screens (minus the ones that need the modern package stack),
 * REST requests and admin-ajax get the 18.5 editor.
 */
final class Runtime
{
    /**
     * Admin screens that only exist in 7.x and are built on packages Gutenberg
     * 18.5 never had (@wordpress/boot, dataviews). They keep core's stack.
     */
    public const DEFAULT_BYPASS_PAGES = [
        'font-library.php',
        'options-connectors.php',
    ];

    private static ?bool $active = null;

    public static function isActive(): bool
    {
        if (self::$active !== null) {
            return self::$active;
        }

        $decision = self::decide(
            self::isDisabled(),
            is_admin(),
            self::pagenow(),
            self::bypassPages(),
            self::isRestRequest(),
            wp_doing_ajax()
        );

        /**
         * Filters whether the block editor downgrade applies to this request.
         *
         * @param bool $active The computed decision.
         */
        self::$active = (bool) apply_filters('gutenberg_downgrade_active', $decision);

        return self::$active;
    }

    /**
     * The decision itself, free of WordPress globals so it can be unit tested.
     *
     * @param list<string> $bypassPages
     */
    public static function decide(
        bool $disabled,
        bool $isAdmin,
        ?string $pagenow,
        array $bypassPages,
        bool $isRest,
        bool $doingAjax
    ): bool {
        if ($disabled) {
            return false;
        }

        if ($isAdmin && $pagenow !== null && in_array($pagenow, $bypassPages, true)) {
            return false;
        }

        return $isAdmin || $isRest || $doingAjax;
    }

    /**
     * @return list<string>
     */
    public static function bypassPages(): array
    {
        $pages = self::DEFAULT_BYPASS_PAGES;

        if (defined('GUTENBERG_DOWNGRADE_BYPASS_PAGES') && is_array(GUTENBERG_DOWNGRADE_BYPASS_PAGES)) {
            $pages = array_values(array_filter(GUTENBERG_DOWNGRADE_BYPASS_PAGES, 'is_string'));
        }

        /**
         * Filters the wp-admin screens (values of $pagenow) that keep core's
         * block editor packages.
         *
         * @param list<string> $pages
         */
        $filtered = apply_filters('gutenberg_downgrade_bypass_pages', $pages);

        return is_array($filtered) ? array_values(array_filter($filtered, 'is_string')) : $pages;
    }

    public static function isDisabled(): bool
    {
        return defined('GUTENBERG_DOWNGRADE_DISABLE') && GUTENBERG_DOWNGRADE_DISABLE;
    }

    /**
     * REST detection that works at plugins_loaded, before core defines
     * REST_REQUEST (that happens at parse_request, after init — too late for
     * block registration).
     */
    public static function isRestRequest(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (isset($_GET['rest_route'])) {
            return true;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (!is_string($requestUri) || $requestUri === '') {
            return false;
        }

        $requestPath = wp_parse_url($requestUri, PHP_URL_PATH);
        $homePath = wp_parse_url(home_url('/'), PHP_URL_PATH);

        return self::pathIsRest(
            is_string($requestPath) ? $requestPath : '/',
            is_string($homePath) ? $homePath : '/',
            rest_get_url_prefix()
        );
    }

    /**
     * Pure helper: is $requestPath, relative to the site's $homePath, under the
     * REST prefix (`wp-json` by default)?
     */
    public static function pathIsRest(string $requestPath, string $homePath, string $prefix): bool
    {
        $home = rtrim($homePath, '/');
        if ($home !== '' && str_starts_with($requestPath, $home)) {
            $requestPath = substr($requestPath, strlen($home));
        }

        $relative = trim($requestPath, '/');
        $prefix = trim($prefix, '/');

        return $relative === $prefix || str_starts_with($relative, $prefix . '/');
    }

    private static function pagenow(): ?string
    {
        $pagenow = $GLOBALS['pagenow'] ?? null;

        return is_string($pagenow) ? $pagenow : null;
    }

    /**
     * Test seam: forget the memoised decision.
     */
    public static function reset(): void
    {
        self::$active = null;
    }
}
