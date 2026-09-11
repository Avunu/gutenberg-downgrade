<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

use WP_Block_Type_Registry;

/**
 * Replaces core's server-side core-block definitions with the 11.9 ones.
 *
 * This is what makes the editor consistent: the 11.9 client lets the
 * server-bootstrapped block definitions win over its bundled block.json, and
 * core's now carry apiVersion 3 and `rich-text`-sourced attributes the 11.9
 * parser cannot read. Port of 11.9's gutenberg_reregister_core_block_types().
 */
final class BlockRegistry
{
    /**
     * After core's registrations at init@10 (register_core_block_types_from_metadata
     * and every register_block_core_*), before the 11.9 files register at init@20.
     */
    public const INIT_PRIORITY = 15;

    /**
     * After core's latest re-registration (register_legacy_post_comments_block
     * at init@21), for the blocks we leave to core.
     */
    public const BACKFILL_PRIORITY = 22;

    private static ?Manifest $manifest = null;
    private static ?BlockConfig $config = null;

    public static function register(Manifest $manifest, BlockConfig $config): void
    {
        self::$manifest = $manifest;
        self::$config = $config;

        self::defineShims();
        self::loadDynamicBlockFiles();

        add_action('init', [self::class, 'reregister'], self::INIT_PRIORITY);
        add_action('init', [self::class, 'backfillTitles'], self::BACKFILL_PRIORITY);
    }

    /**
     * Drops core's version of every block we take over and registers the
     * static ones from the 11.9 block.json. Dynamic blocks are registered a
     * moment later, at init@20, by the 11.9 files loaded in register().
     */
    public static function reregister(): void
    {
        $manifest = self::$manifest ?? Assets::manifest();
        $config = self::$config ?? BlockConfig::load();
        $registry = WP_Block_Type_Registry::get_instance();

        foreach ($manifest->blocks() as $name => $block) {
            $mode = $config->mode($name);

            if ($mode === null || $mode === BlockConfig::MODE_SKIP) {
                continue;
            }

            if ($registry->is_registered($name)) {
                $registry->unregister($name);
            }

            $dir = Assets::path($block['dir']);

            switch ($mode) {
                case BlockConfig::MODE_STATIC:
                    register_block_type_from_metadata($dir);
                    break;

                case BlockConfig::MODE_CORE_RENDER:
                    $callback = self::coreRenderCallback($name);
                    register_block_type_from_metadata(
                        $dir,
                        function_exists($callback) ? ['render_callback' => $callback] : []
                    );
                    break;

                case BlockConfig::MODE_DYNAMIC:
                    // The 11.9 PHP registers it at init@20.
                    break;
            }
        }
    }

    /**
     * The 11.9 client refuses to register a block whose server definition has
     * no title ("The block … must have a title"). Core registers legacy
     * aliases such as core/post-comments that way; give the blocks we skip the
     * title from their 11.9 block.json so the client can still register them.
     */
    public static function backfillTitles(): void
    {
        $manifest = self::$manifest ?? Assets::manifest();
        $config = self::$config ?? BlockConfig::load();
        $registry = WP_Block_Type_Registry::get_instance();

        foreach ($manifest->blocks() as $name => $block) {
            if ($config->mode($name) !== BlockConfig::MODE_SKIP) {
                continue;
            }

            $type = $registry->get_registered($name);
            if ($type === null || (is_string($type->title) && $type->title !== '')) {
                continue;
            }

            $title = self::metadataTitle(Assets::path($block['dir'] . '/block.json'));
            if ($title !== null) {
                $type->title = $title;
            }
        }
    }

    /**
     * Core's render callback for a core block, e.g. core/site-logo →
     * render_block_core_site_logo.
     */
    public static function coreRenderCallback(string $block): string
    {
        $slug = str_starts_with($block, 'core/') ? substr($block, 5) : $block;

        return 'render_block_core_' . str_replace('-', '_', $slug);
    }

    public static function metadataTitle(string $blockJson): ?string
    {
        $raw = is_file($blockJson) ? file_get_contents($blockJson) : false;
        $metadata = is_string($raw) ? json_decode($raw, true) : null;
        $title = is_array($metadata) ? ($metadata['title'] ?? null) : null;

        return is_string($title) && $title !== '' ? $title : null;
    }

    /**
     * The 11.9 dynamic-block files hook their own registration on init@20 and
     * the render callbacks they declare are already `gutenberg_`-prefixed, so
     * they can be loaded next to core's copies.
     */
    private static function loadDynamicBlockFiles(): void
    {
        $manifest = self::$manifest ?? Assets::manifest();
        $config = self::$config ?? BlockConfig::load();

        foreach ($manifest->blocks() as $name => $block) {
            if ($config->mode($name) !== BlockConfig::MODE_DYNAMIC || $block['php'] === null) {
                continue;
            }

            require_once Assets::path($block['php']);
        }
    }

    /**
     * Global helpers the shipped block files still expect from 11.9's lib/.
     */
    private static function defineShims(): void
    {
        require_once __DIR__ . '/shims.php';
    }
}
