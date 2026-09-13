<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use GutenbergDowngrade\ScriptOverrides;
use GutenbergDowngrade\SiteEditor;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SiteEditor::class)]
final class SiteEditorTest extends UnitTestCase
{
    public function testHooksTheSiteEditorScreenAndTheMenu(): void
    {
        Actions\expectAdded('load-site-editor.php')->once()->with([SiteEditor::class, 'guardClassicTheme']);
        Actions\expectAdded('load-site-editor.php')->once()->with([SiteEditor::class, 'bridgeUrls']);
        Actions\expectAdded('admin_menu')->once()->with([SiteEditor::class, 'classicThemeMenu'], 20);

        SiteEditor::register();
    }

    public function testInlinesTheUrlShimBeforeTheRouterBundle(): void
    {
        Functions\expect('wp_add_inline_script')
            ->once()
            ->with('wp-router', ScriptOverrides::shimScript(SiteEditor::SHIM), 'before');

        SiteEditor::bridgeUrls();
    }

    public function testShimTranslatesEveryUrlFormCoreEmits(): void
    {
        $shim = ScriptOverrides::shimScript(SiteEditor::SHIM);

        self::assertStringStartsWith('// Inlined verbatim BEFORE', $shim);
        self::assertStringNotContainsString('import ', $shim);
        // The list views 6.8 renamed, and the styles path 18.5 still keys on.
        foreach (['"/template"', '"/pattern"', '"/navigation"', '"/page"', '"/styles"', '/wp_global_styles'] as $needle) {
            self::assertStringContainsString($needle, $shim);
        }
        self::assertStringContainsString('replaceState', $shim);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool, ?string}>
     */
    public static function classicRoutes(): iterable
    {
        $patterns = SiteEditor::CLASSIC_ROUTE;

        yield 'patterns list (7.x)'              => [['p' => '/pattern'], false, null];
        yield 'patterns list (18.5)'             => [['postType' => 'wp_block'], false, null];
        yield 'a pattern (7.x)'                  => [['p' => '/wp_block/12', 'canvas' => 'edit'], false, null];
        yield 'a pattern (18.5)'                 => [['postType' => 'wp_block', 'postId' => '12'], false, null];
        yield 'template parts tab, unsupported'  => [['p' => '/pattern', 'postType' => 'wp_template_part'], false, $patterns];
        yield 'template parts tab, supported'    => [['p' => '/pattern', 'postType' => 'wp_template_part'], true, null];
        yield 'a template part, supported'       => [['p' => '/wp_template_part/t//header'], true, null];
        yield 'a template part, unsupported'     => [['p' => '/wp_template_part/t//header'], false, $patterns];
        yield 'the home (7.x style book)'        => [[], false, $patterns];
        yield 'the home with p=/'                => [['p' => '/'], false, $patterns];
        yield 'styles'                           => [['p' => '/styles'], false, $patterns];
        yield 'templates'                        => [['p' => '/template'], false, $patterns];
        yield 'a template (18.5)'                => [['postType' => 'wp_template', 'postId' => 't//home'], true, $patterns];
        yield 'garbage values'                   => [['p' => ['x'], 'postType' => 5], false, $patterns];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('classicRoutes')]
    public function testClassicThemesGetWhat66Offered(array $query, bool $supportsTemplateParts, ?string $expected): void
    {
        self::assertSame($expected, SiteEditor::classicThemeRedirect($query, $supportsTemplateParts));
    }

    public function testBlockThemesAreNeverRedirected(): void
    {
        Functions\when('wp_is_block_theme')->justReturn(true);
        Functions\expect('wp_safe_redirect')->never();

        SiteEditor::guardClassicTheme();
        $this->addToAssertionCount(1);
    }

    public function testClassicThemeMenuMirrors66(): void
    {
        Functions\when('wp_is_block_theme')->justReturn(false);
        Functions\when('_x')->returnArg();
        $GLOBALS['submenu'] = ['themes.php' => [
            5 => ['Themes', 'switch_themes', 'themes.php'],
            6 => ['Design', 'edit_theme_options', 'site-editor.php'],
            9 => ['Fonts', 'edit_theme_options', 'font-library.php'],
        ]];

        SiteEditor::classicThemeMenu();

        self::assertSame(['Patterns', 'edit_theme_options', SiteEditor::CLASSIC_ROUTE], $GLOBALS['submenu']['themes.php'][6]);
        self::assertSame('font-library.php', $GLOBALS['submenu']['themes.php'][9][2], 'other entries untouched');
        unset($GLOBALS['submenu']);
    }

    public function testBlockThemeMenuIsLeftAlone(): void
    {
        Functions\when('wp_is_block_theme')->justReturn(true);
        $GLOBALS['submenu'] = ['themes.php' => [6 => ['Editor', 'edit_theme_options', 'site-editor.php']]];

        SiteEditor::classicThemeMenu();

        self::assertSame('site-editor.php', $GLOBALS['submenu']['themes.php'][6][2]);
        unset($GLOBALS['submenu']);
    }
}
