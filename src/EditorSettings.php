<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

use WP_Block_Editor_Context;
use WP_Block_Pattern_Categories_Registry;
use WP_Block_Patterns_Registry;

/**
 * Reshapes the editor settings current core produces into what the 11.9
 * client reads.
 */
final class EditorSettings
{
    /** Late, so every other plugin's adjustments are already in. */
    public const PRIORITY = 100;

    /**
     * Preset paths the 11.9 client merges by origin (`user ?? theme ?? core`,
     * see @wordpress/blocks __EXPERIMENTAL_PATHS_WITH_MERGE at v11.9.1).
     * Core has since renamed the origins to default/theme/custom.
     */
    public const MERGE_PATHS = [
        'color.palette',
        'color.gradients',
        'color.duotone',
        'typography.fontSizes',
        'typography.fontFamilies',
    ];

    private const ORIGIN_MAP = [
        'default' => 'core',
        'theme'   => 'theme',
        'custom'  => 'user',
    ];

    private static bool $editorAssetsExposed = false;

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
        // 11.9's gutenberg_supports_block_templates(): FSE-only blocks are
        // registered client-side only for block themes.
        $settings['__unstableEnableFullSiteEditingBlocks'] = wp_is_block_theme()
            || current_theme_supports('block-templates');

        // 11.9's template mode drives current core's templates REST API;
        // untested and out of scope.
        $settings['supportsTemplateMode'] = false;

        // Core stopped inlining patterns (the client fetches them over REST
        // now); the 11.9 inserter reads only these two settings.
        $settings['__experimentalBlockPatterns'] = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
        $settings['__experimentalBlockPatternCategories'] =
            WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();

        if (isset($settings['__experimentalFeatures']) && is_array($settings['__experimentalFeatures'])) {
            $settings['__experimentalFeatures'] = self::remapOrigins($settings['__experimentalFeatures']);
        }

        $defaultStyles = self::defaultEditorStyles();
        if ($defaultStyles !== null) {
            $settings['defaultEditorStyles'] = [['css' => $defaultStyles]];
        }

        self::exposeEditorAssets($settings);

        /**
         * Filters the editor settings after the downgrade's adjustments.
         *
         * @param array<string, mixed>    $settings
         * @param WP_Block_Editor_Context $context
         */
        $filtered = apply_filters('gutenberg_downgrade_editor_settings', $settings, $context);

        return is_array($filtered) ? $filtered : $settings;
    }

    /**
     * Renames preset origins for every merge path, at the top level and under
     * each `blocks.<name>` override. Origins 11.9 does not know are dropped.
     *
     * @param array<string, mixed> $features
     * @return array<string, mixed>
     */
    public static function remapOrigins(array $features): array
    {
        foreach (self::MERGE_PATHS as $path) {
            $features = self::remapPath($features, explode('.', $path));
        }

        if (isset($features['blocks']) && is_array($features['blocks'])) {
            foreach ($features['blocks'] as $block => $blockFeatures) {
                if (!is_array($blockFeatures)) {
                    continue;
                }
                foreach (self::MERGE_PATHS as $path) {
                    $blockFeatures = self::remapPath($blockFeatures, explode('.', $path));
                }
                $features['blocks'][$block] = $blockFeatures;
            }
        }

        return $features;
    }

    /**
     * @param array<string, mixed> $preset A per-origin preset map, e.g. color.palette.
     * @return array<string, mixed>
     */
    public static function remapPresetOrigins(array $preset): array
    {
        $out = [];
        foreach (self::ORIGIN_MAP as $from => $to) {
            if (array_key_exists($from, $preset)) {
                $out[$to] = $preset[$from];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $tree
     * @param list<string> $segments
     * @return array<string, mixed>
     */
    private static function remapPath(array $tree, array $segments): array
    {
        $key = array_shift($segments);
        if ($key === null || !array_key_exists($key, $tree) || !is_array($tree[$key])) {
            return $tree;
        }

        /** @var array<string, mixed> $child */
        $child = $tree[$key];

        if ($segments === []) {
            // Only remap maps that actually look like per-origin presets;
            // a theme could put a plain list here in an old theme.json.
            if (self::isOriginMap($child)) {
                $tree[$key] = self::remapPresetOrigins($child);
            }

            return $tree;
        }

        $tree[$key] = self::remapPath($child, $segments);

        return $tree;
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function isOriginMap(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach (array_keys($value) as $origin) {
            if (!array_key_exists($origin, self::ORIGIN_MAP)) {
                return false;
            }
        }

        return true;
    }

    private static function defaultEditorStyles(): ?string
    {
        $file = Assets::path(is_rtl()
            ? 'build/block-editor/default-editor-styles-rtl.css'
            : 'build/block-editor/default-editor-styles.css');

        if (!is_file($file)) {
            return null;
        }

        $css = file_get_contents($file);

        return is_string($css) ? $css : null;
    }

    /**
     * 11.9's iframed canvas (tablet/mobile preview) reads the editor's
     * stylesheets from window.__editorAssets. Core already computes the same
     * markup for its own iframe as __unstableResolvedAssets; hand it over.
     *
     * @param array<string, mixed> $settings
     */
    private static function exposeEditorAssets(array $settings): void
    {
        if (self::$editorAssetsExposed || !is_array($settings['__unstableResolvedAssets'] ?? null)) {
            return;
        }
        self::$editorAssetsExposed = true;

        wp_add_inline_script(
            'wp-block-editor',
            'window.__editorAssets = ' . (string) wp_json_encode($settings['__unstableResolvedAssets']) . ';',
            'before'
        );
    }
}
