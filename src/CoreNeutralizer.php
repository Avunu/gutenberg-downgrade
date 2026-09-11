<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

use WP_Block_Patterns_Registry;

/**
 * Switches off the parts of current core that assume its own editor stack.
 *
 * remove_action() on a callback that does not exist is a harmless no-op, so
 * these stay safe across core versions that add or drop the functions.
 */
final class CoreNeutralizer
{
    /** After every core/theme/plugin pattern has registered on init. */
    public const PATTERNS_PRIORITY = 100;

    private static ?Manifest $manifest = null;

    public static function register(?Manifest $manifest = null): void
    {
        self::$manifest = $manifest;

        // The admin-wide command palette: 7.x boots it on every screen with
        // wp.coreCommands.initializeCommandPalette(), which 18.5's package does
        // not have (its palette lives inside the editors). The wp-commands
        // handles themselves stay: 18.5's wp-edit-post depends on them.
        remove_action('admin_enqueue_scripts', 'wp_enqueue_command_palette_assets');

        // Script modules the editor page pulls in (@wordpress/latex-to-mathml)
        // and the auto-register-blocks bootstrap, neither known to 18.5.
        remove_action('enqueue_block_editor_assets', 'wp_enqueue_block_editor_script_modules');
        remove_action('enqueue_block_editor_assets', '_wp_enqueue_auto_register_blocks');

        // Client-side media processing: stops both the
        // `window.__clientSideMediaProcessing` inline and the
        // Document-Isolation-Policy output buffer on the editor screens.
        add_filter('wp_client_side_media_processing_enabled', '__return_false');

        // The pattern directory serves patterns built for current core.
        add_filter('should_load_remote_block_patterns', '__return_false');
        // Core's own bundled patterns are fine except where they use blocks
        // newer than 18.5 (the 7.x navigation overlays).
        add_action('init', [self::class, 'removeUnsupportedPatterns'], self::PATTERNS_PRIORITY);
    }

    /**
     * Unregisters every pattern whose content uses a `core/` block the
     * vendored build does not define — the client would show it as missing.
     */
    public static function removeUnsupportedPatterns(): void
    {
        $known = array_keys((self::$manifest ?? Assets::manifest())->blocks());
        // The legacy alias 18.5's comments.php registers next to core/comments.
        $known[] = 'core/post-comments';

        $registry = WP_Block_Patterns_Registry::get_instance();
        foreach ($registry->get_all_registered() as $pattern) {
            $content = $pattern['content'] ?? '';
            if (!is_string($content) || !isset($pattern['name']) || !is_string($pattern['name'])) {
                continue;
            }
            if (self::unknownCoreBlocks($content, $known) !== []) {
                $registry->unregister($pattern['name']);
            }
        }
    }

    /**
     * Pure helper: the `core/` block names in $content that are not in $known.
     *
     * @param list<string> $known
     * @return list<string>
     */
    public static function unknownCoreBlocks(string $content, array $known): array
    {
        // A block comment names the block as `wp:name` (core, namespace
        // implied) or `wp:namespace/name`; only the core namespace is ours.
        preg_match_all('/<!--\s+wp:([a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)?)[\s\/]/', $content, $matches);

        $unknown = [];
        foreach ($matches[1] as $rawName) {
            $name = str_contains($rawName, '/') ? $rawName : 'core/' . $rawName;
            if (!str_starts_with($name, 'core/')) {
                continue;
            }
            if (!in_array($name, $known, true) && !in_array($name, $unknown, true)) {
                $unknown[] = $name;
            }
        }

        return $unknown;
    }
}
