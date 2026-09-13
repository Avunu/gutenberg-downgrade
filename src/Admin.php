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
                . 'Deactivate Gutenberg to load the 18.5 editor.',
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
