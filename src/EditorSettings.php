<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

use WP_Block_Editor_Context;

/**
 * The editor settings current core produces are, for the 18.5 client, a
 * superset of what WordPress 6.6 produced: the same keys, newer theme.json
 * processing. Nothing needs reshaping; this only exposes a hook for sites to
 * adjust the settings the downgraded editor receives.
 */
final class EditorSettings
{
    /** Late, so every other plugin's adjustments are already in. */
    public const PRIORITY = 100;

    public static function register(): void
    {
        add_filter('block_editor_settings_all', [self::class, 'filter'], self::PRIORITY, 2);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function filter(array $settings, WP_Block_Editor_Context $context): array
    {
        /**
         * Filters the editor settings handed to the downgraded editor.
         *
         * @param array<string, mixed>    $settings
         * @param WP_Block_Editor_Context $context
         */
        $filtered = apply_filters('gutenberg_downgrade_editor_settings', $settings, $context);

        return is_array($filtered) ? $filtered : $settings;
    }
}
