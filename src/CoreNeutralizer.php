<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

/**
 * Switches off the parts of current core that assume its own editor stack.
 *
 * remove_action() on a callback that does not exist is a harmless no-op, so
 * these stay safe across core versions that add or drop the functions.
 */
final class CoreNeutralizer
{
    public static function register(): void
    {
        // The command palette: wp-commands/wp-core-commands on every admin
        // screen, built on packages 11.9 does not have. (The handles are also
        // retired in ScriptOverrides.)
        remove_action('admin_enqueue_scripts', 'wp_enqueue_command_palette_assets');

        // Script modules the editor page pulls in (@wordpress/latex-to-mathml)
        // and the auto-register-blocks bootstrap, neither known to 11.9.
        remove_action('enqueue_block_editor_assets', 'wp_enqueue_block_editor_script_modules');
        remove_action('enqueue_block_editor_assets', '_wp_enqueue_auto_register_blocks');

        // Client-side media processing: stops both the
        // `window.__clientSideMediaProcessing` inline and the
        // Document-Isolation-Policy output buffer on the editor screens.
        add_filter('wp_client_side_media_processing_enabled', '__return_false');

        // Core's bundled patterns and the pattern directory use blocks 11.9
        // cannot parse (navigation overlays, modern query loops). Theme and
        // plugin patterns still register.
        add_filter('should_load_remote_block_patterns', '__return_false');
        add_action('after_setup_theme', [self::class, 'removeCorePatterns'], 20);
    }

    public static function removeCorePatterns(): void
    {
        remove_theme_support('core-block-patterns');
    }
}
