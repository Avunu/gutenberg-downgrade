<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use Brain\Monkey\Functions;
use GutenbergDowngrade\StyleOverrides;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_Styles;

#[CoversClass(StyleOverrides::class)]
final class StyleOverridesTest extends UnitTestCase
{
    private const BASE = 'https://example.test/wp-content/plugins/gutenberg-downgrade/assets/gutenberg/';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('wp_should_load_separate_core_block_assets')->justReturn(false);
        Functions\when('wp_theme_has_theme_json')->justReturn(false);
        Functions\when('current_theme_supports')->justReturn(true);
        $GLOBALS['editor_styles'] = [];
    }

    private function coreStyles(): WP_Styles
    {
        $styles = new WP_Styles();
        $styles->add('wp-components', '/wp-includes/css/dist/components/style.min.css', ['wp-theme'], 'core');
        $styles->add_data('wp-components', 'rtl', 'replace');
        $styles->add_data('wp-components', 'suffix', '.min');
        $styles->add('wp-edit-post', '/wp-includes/css/dist/edit-post/style.min.css', ['wp-commands', 'wp-preferences'], 'core');
        $styles->add('wp-base-styles', '/wp-includes/css/dist/base-styles/admin-schemes.min.css', [], 'core');
        $styles->add('wp-theme', '/wp-includes/css/dist/theme/design-tokens.min.css', [], 'core');

        return $styles;
    }

    public function testReplacesThePackageStylesheetsWithTheEighteenFiveGraph(): void
    {
        $this->requireAssets();
        $styles = $this->coreStyles();

        StyleOverrides::apply($styles);

        $components = $styles->registered['wp-components'];
        self::assertSame(self::BASE . 'build/components/style.css', $components->src);
        self::assertSame(['dashicons'], $components->deps);
        self::assertSame('18.5.0', $components->ver);
        self::assertSame('replace', $components->extra['rtl']);
        self::assertArrayNotHasKey('suffix', $components->extra, "18.5 ships style-rtl.css, not style-rtl.min.css");

        self::assertSame(
            ['wp-components', 'wp-block-editor', 'wp-editor', 'wp-edit-blocks', 'wp-block-library', 'wp-commands', 'wp-preferences'],
            $styles->registered['wp-edit-post']->deps
        );
        self::assertSame(self::BASE . 'build/block-library/style.css', $styles->registered['wp-block-library']->src);
        self::assertStringEndsWith('build/block-library/style.css', $styles->registered['wp-block-library']->extra['path']);

        // Handles 18.5 never had are not touched: wp-admin depends on wp-base-styles.
        self::assertSame('/wp-includes/css/dist/base-styles/admin-schemes.min.css', $styles->registered['wp-base-styles']->src);
        self::assertSame('/wp-includes/css/dist/theme/design-tokens.min.css', $styles->registered['wp-theme']->src);

        foreach (array_keys(StyleOverrides::STYLES) as $handle) {
            self::assertArrayHasKey($handle, $styles->registered, "{$handle} is registered even when core lacked it");
        }
    }

    public function testEditBlocksDependenciesFollowTheThemeCapabilities(): void
    {
        $base = [
            'wp-components',
            'wp-reset-editor-styles',
            'wp-block-library',
            'wp-patterns',
            'wp-reusable-blocks',
            'wp-block-editor-content',
        ];

        self::assertSame(
            [...$base, 'wp-editor-classic-layout-styles', 'wp-block-library-theme'],
            StyleOverrides::editBlocksDeps(false, true, false),
            'classic theme opting into block styles, no editor styles: layout defaults + opinionated block styles'
        );
        self::assertSame([...$base, 'wp-block-library-theme'], StyleOverrides::editBlocksDeps(true, true, false));
        self::assertSame([...$base, 'wp-editor-classic-layout-styles'], StyleOverrides::editBlocksDeps(false, true, true));
        self::assertSame([...$base, 'wp-editor-classic-layout-styles'], StyleOverrides::editBlocksDeps(false, false, false), 'no wp-block-styles support: never the opinionated styles');
        self::assertSame($base, StyleOverrides::editBlocksDeps(true, true, true));
    }

    public function testBlockLibraryUsesTheCommonSubsetWhenCoreSplitsBlockStyles(): void
    {
        self::assertSame('build/block-library/common.css', StyleOverrides::blockLibraryFile(true));
        self::assertSame('build/block-library/style.css', StyleOverrides::blockLibraryFile(false));
    }

    public function testEveryStylesheetInTheTableShips(): void
    {
        $assets = $this->requireAssets();

        foreach (StyleOverrides::STYLES as $handle => $style) {
            self::assertFileExists($assets . '/' . $style['file'], "{$handle}");
            $rtl = preg_replace('/\.css$/', '-rtl.css', $style['file']);
            self::assertFileExists($assets . '/' . $rtl, "{$handle} RTL");
        }
        self::assertFileExists($assets . '/build/block-library/common.css');
    }
}
