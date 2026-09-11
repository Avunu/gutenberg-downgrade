<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

/**
 * Admin notices. There is no settings screen: configuration is wp-config.php
 * constants (see the plugin file header).
 */
final class Admin
{
    public static function conflictNotice(): void
    {
        self::notice(
            'error',
            __(
                'Gutenberg Downgrade is inactive because the Gutenberg plugin is active. '
                . 'Deactivate Gutenberg to load the 11.9 editor.',
                'gutenberg-downgrade'
            )
        );
    }

    public static function missingAssetsNotice(): void
    {
        self::notice(
            'error',
            __(
                'Gutenberg Downgrade is inactive: the bundled Gutenberg build (assets/gutenberg) is missing. '
                . 'Install the built release zip, or run `nix run .#sync-assets` in a development checkout.',
                'gutenberg-downgrade'
            )
        );
    }

    /**
     * Shown on the Plugins screen only: block themes (Site Editor, template
     * mode) are outside what the 11.9 editor supports on current core.
     */
    public static function blockThemeNotice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || $screen->id !== 'plugins' || !wp_is_block_theme()) {
            return;
        }

        self::notice(
            'warning',
            __(
                'Gutenberg Downgrade: the active theme is a block theme. The Site Editor and template editing '
                . 'are not supported by the 11.9 editor; use a classic theme for the intended experience.',
                'gutenberg-downgrade'
            )
        );
    }

    /**
     * @param 'error'|'warning'|'info' $type
     */
    private static function notice(string $type, string $message): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-%1$s"><p><strong>%2$s</strong> %3$s</p></div>',
            esc_attr($type),
            esc_html__('Gutenberg Downgrade:', 'gutenberg-downgrade'),
            esc_html($message)
        );
    }
}
