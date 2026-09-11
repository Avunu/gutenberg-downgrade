<?php

/**
 * Global-namespace helpers the vendored Gutenberg 18.5 block files still call.
 *
 * The plugin's build rewrites a handful of core calls in the block PHP to
 * `gutenberg_*` so that the plugin's own lib/ (newer than the core it runs on)
 * wins. This plugin does not ship that lib/: on current core the `wp_*`
 * originals are already at least as new, so each shim delegates straight back
 * to core. Everything is guarded so a real Gutenberg install always wins.
 * Loaded by BlockRegistry::register() — never autoloaded, it is not a class.
 */

declare(strict_types=1);

use GutenbergDowngrade\Assets;

if (!function_exists('gutenberg_style_engine_get_styles')) {
    /**
     * @param array<string, mixed> $block_styles
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    function gutenberg_style_engine_get_styles(array $block_styles, array $options = []): array
    {
        return wp_style_engine_get_styles($block_styles, $options);
    }
}

if (!function_exists('gutenberg_style_engine_get_stylesheet_from_css_rules')) {
    /**
     * @param array<int|string, array{rules_group: string, selector: string, declarations: array<string>|WP_Style_Engine_CSS_Declarations}> $css_rules
     * @param array<string, mixed> $options
     */
    function gutenberg_style_engine_get_stylesheet_from_css_rules(array $css_rules, array $options = []): string
    {
        return wp_style_engine_get_stylesheet_from_css_rules($css_rules, $options);
    }
}

if (!function_exists('gutenberg_get_global_settings')) {
    /**
     * @param list<string> $path
     * @param array<string, mixed> $context
     */
    function gutenberg_get_global_settings(array $path = [], array $context = []): mixed
    {
        return wp_get_global_settings($path, $context);
    }
}

if (!function_exists('gutenberg_get_typography_font_size_value')) {
    /**
     * @param array<string, mixed> $preset
     * @param array<string, mixed> $settings
     */
    function gutenberg_get_typography_font_size_value(array $preset, array $settings = []): ?string
    {
        return wp_get_typography_font_size_value($preset, $settings);
    }
}

if (!function_exists('gutenberg_apply_colors_support')) {
    /**
     * @param array<string, mixed> $block_attributes
     * @return array<string, mixed>
     */
    function gutenberg_apply_colors_support(WP_Block_Type $block_type, array $block_attributes): array
    {
        return wp_apply_colors_support($block_type, $block_attributes);
    }
}

if (!function_exists('gutenberg_serialize_blocks')) {
    /**
     * @param array<int|string, array{blockName: string|null, attrs: array<string, mixed>, innerBlocks: array<array<string, mixed>>, innerHTML: string, innerContent: array<int, string|null>}> $blocks
     */
    function gutenberg_serialize_blocks(array $blocks): string
    {
        return serialize_blocks($blocks);
    }
}

if (!function_exists('gutenberg_is_experiment_enabled')) {
    /**
     * The plugin ships no experiments screen; every experiment is off.
     */
    function gutenberg_is_experiment_enabled(string $name): bool
    {
        return false;
    }
}

if (!function_exists('gutenberg_url')) {
    /**
     * URL of a file inside the vendored build (the block files pass
     * `/build/…` paths). Only reached under IS_GUTENBERG_PLUGIN, which this
     * plugin never defines; provided so the code is complete.
     */
    function gutenberg_url(string $path): string
    {
        return Assets::url(ltrim($path, '/'));
    }
}
