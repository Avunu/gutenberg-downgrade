<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

use WP_Styles;

/**
 * Re-registers the package stylesheets from the vendored 11.9 build.
 *
 * Port of Gutenberg 11.9's gutenberg_register_packages_styles(): the same 19
 * handles, the same dependency graph. Handles 11.9 never shipped
 * (wp-base-styles, wp-theme, wp-commands …) are left alone — wp-admin's own
 * stylesheet depends on wp-base-styles.
 */
final class StyleOverrides
{
    /** After core's wp_default_styles() at 10. */
    public const PRIORITY = 20;

    /**
     * Handle → stylesheet (relative to assets/gutenberg/) and static deps.
     * wp-block-library and wp-edit-blocks have request-dependent values, see
     * blockLibraryFile() / editBlocksDeps().
     *
     * @var array<string, array{file: string, deps: list<string>}>
     */
    public const STYLES = [
        'wp-block-editor'                 => ['file' => 'build/block-editor/style.css', 'deps' => ['wp-components']],
        'wp-editor'                       => [
            'file' => 'build/editor/style.css',
            'deps' => ['wp-components', 'wp-block-editor', 'wp-nux', 'wp-reusable-blocks'],
        ],
        'wp-edit-post'                    => [
            'file' => 'build/edit-post/style.css',
            'deps' => ['wp-components', 'wp-block-editor', 'wp-editor', 'wp-edit-blocks', 'wp-block-library', 'wp-nux'],
        ],
        'wp-components'                   => ['file' => 'build/components/style.css', 'deps' => ['dashicons']],
        'wp-block-library'                => ['file' => 'build/block-library/style.css', 'deps' => []],
        'wp-format-library'               => [
            'file' => 'build/format-library/style.css',
            'deps' => ['wp-block-editor', 'wp-components'],
        ],
        // Loaded after the default admin styles it resets.
        'wp-reset-editor-styles'          => ['file' => 'build/block-library/reset.css', 'deps' => ['common', 'forms']],
        'wp-editor-classic-layout-styles' => ['file' => 'build/edit-post/classic.css', 'deps' => []],
        'wp-edit-blocks'                  => ['file' => 'build/block-library/editor.css', 'deps' => []],
        'wp-nux'                          => ['file' => 'build/nux/style.css', 'deps' => ['wp-components']],
        'wp-block-library-theme'          => ['file' => 'build/block-library/theme.css', 'deps' => []],
        'wp-list-reusable-blocks'         => ['file' => 'build/list-reusable-blocks/style.css', 'deps' => ['wp-components']],
        'wp-edit-site'                    => [
            'file' => 'build/edit-site/style.css',
            'deps' => ['wp-components', 'wp-block-editor', 'wp-edit-blocks'],
        ],
        'wp-edit-widgets'                 => [
            'file' => 'build/edit-widgets/style.css',
            'deps' => ['wp-components', 'wp-block-editor', 'wp-edit-blocks', 'wp-reusable-blocks', 'wp-widgets'],
        ],
        'wp-block-directory'              => [
            'file' => 'build/block-directory/style.css',
            'deps' => ['wp-block-editor', 'wp-components'],
        ],
        'wp-customize-widgets'            => [
            'file' => 'build/customize-widgets/style.css',
            'deps' => ['wp-components', 'wp-block-editor', 'wp-edit-blocks', 'wp-widgets'],
        ],
        'wp-reusable-blocks'              => ['file' => 'build/reusable-blocks/style.css', 'deps' => ['wp-components']],
        'wp-widgets'                      => ['file' => 'build/widgets/style.css', 'deps' => ['wp-components']],
    ];

    public static function register(): void
    {
        add_action('wp_default_styles', [self::class, 'apply'], self::PRIORITY);
    }

    public static function apply(WP_Styles $styles): void
    {
        $version = Assets::version();
        $separateAssets = wp_should_load_separate_core_block_assets();

        global $editor_styles;
        $hasEditorStyles = is_array($editor_styles) && $editor_styles !== [];

        foreach (self::STYLES as $handle => $style) {
            $file = $style['file'];
            $deps = $style['deps'];

            if ($handle === 'wp-block-library') {
                $file = self::blockLibraryFile($separateAssets);
            } elseif ($handle === 'wp-edit-blocks') {
                $deps = self::editBlocksDeps(wp_theme_has_theme_json(), $hasEditorStyles);
            }

            self::override($styles, $handle, $file, $deps, $version);
        }

        // Lets wp_maybe_inline_styles() read the file size when core inlines
        // small stylesheets on the front end.
        $styles->add_data('wp-block-library', 'path', Assets::path(self::blockLibraryFile($separateAssets)));
    }

    /**
     * The combined block styles in the editor; the shared "common" subset when
     * core serves per-block stylesheets separately.
     */
    public static function blockLibraryFile(bool $separateAssets): string
    {
        return $separateAssets ? 'build/block-library/common.css' : 'build/block-library/style.css';
    }

    /**
     * wp-edit-blocks dependency list as 11.9 computed it.
     *
     * @return list<string>
     */
    public static function editBlocksDeps(bool $themeHasJson, bool $hasEditorStyles): array
    {
        $deps = [
            'wp-components',
            'wp-editor',
            // Must precede the block library styles, which override the reset.
            'wp-reset-editor-styles',
            'wp-block-library',
            'wp-reusable-blocks',
        ];

        // Default layout and margin styles only for themes without theme.json.
        if (!$themeHasJson) {
            $deps[] = 'wp-editor-classic-layout-styles';
        }

        // Opinionated block styles when the theme declares no editor styles,
        // so the editor never appears broken.
        if (!$hasEditorStyles) {
            $deps[] = 'wp-block-library-theme';
        }

        return $deps;
    }

    /**
     * @param list<string> $deps
     */
    private static function override(WP_Styles $styles, string $handle, string $file, array $deps, string $version): void
    {
        if (isset($styles->registered[$handle])) {
            $styles->remove($handle);
        }

        $styles->add($handle, Assets::url($file), $deps, $version);
        // 11.9 ships style-rtl.css next to style.css; core's RTL setup adds a
        // `.min` suffix we must not inherit.
        $styles->add_data($handle, 'rtl', 'replace');
    }
}
