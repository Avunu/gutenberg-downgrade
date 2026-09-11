<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use Brain\Monkey\Functions;
use GutenbergDowngrade\EditorSettings;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_Block_Editor_Context;
use WP_Block_Pattern_Categories_Registry;
use WP_Block_Patterns_Registry;

#[CoversClass(EditorSettings::class)]
final class EditorSettingsTest extends UnitTestCase
{
    public function testRemapsPresetOriginsToTheNamesTheElevenNineClientReads(): void
    {
        $features = [
            'color'      => [
                'palette'   => [
                    'default' => [['slug' => 'black']],
                    'theme'   => [['slug' => 'brand']],
                    'custom'  => [['slug' => 'mine']],
                ],
                'gradients' => ['default' => []],
                'custom'    => true,
            ],
            'typography' => [
                'fontSizes'    => ['default' => [['slug' => 'small']], 'blocks' => 'not-an-origin'],
                'fontFamilies' => ['theme' => [['slug' => 'body']]],
                'dropCap'      => false,
            ],
            'spacing'    => ['units' => ['px']],
        ];

        $remapped = EditorSettings::remapOrigins($features);

        self::assertSame([['slug' => 'black']], $remapped['color']['palette']['core']);
        self::assertSame([['slug' => 'brand']], $remapped['color']['palette']['theme']);
        self::assertSame([['slug' => 'mine']], $remapped['color']['palette']['user']);
        self::assertArrayNotHasKey('default', $remapped['color']['palette']);
        self::assertArrayNotHasKey('custom', $remapped['color']['palette']);
        self::assertSame(['core' => []], $remapped['color']['gradients']);
        // Non-preset settings are untouched.
        self::assertTrue($remapped['color']['custom']);
        self::assertFalse($remapped['typography']['dropCap']);
        self::assertSame(['px'], $remapped['spacing']['units']);
        // A map with an unknown origin is not an origin map: left alone.
        self::assertSame(
            ['default' => [['slug' => 'small']], 'blocks' => 'not-an-origin'],
            $remapped['typography']['fontSizes']
        );
        self::assertSame(['theme' => [['slug' => 'body']]], $remapped['typography']['fontFamilies']);
    }

    public function testRemapsPerBlockOverridesToo(): void
    {
        $features = [
            'color'  => ['palette' => ['default' => ['a']]],
            'blocks' => [
                'core/button' => ['color' => ['palette' => ['theme' => ['b'], 'custom' => ['c']]]],
                'core/quote'  => ['typography' => ['fontSizes' => ['default' => ['d']]]],
            ],
        ];

        $remapped = EditorSettings::remapOrigins($features);

        self::assertSame(['theme' => ['b'], 'user' => ['c']], $remapped['blocks']['core/button']['color']['palette']);
        self::assertSame(['core' => ['d']], $remapped['blocks']['core/quote']['typography']['fontSizes']);
    }

    public function testRemapPresetOriginsDropsUnknownOrigins(): void
    {
        self::assertSame(
            ['core' => 1, 'theme' => 2, 'user' => 3],
            EditorSettings::remapPresetOrigins(['default' => 1, 'theme' => 2, 'custom' => 3, 'blocks' => 4])
        );
        self::assertSame([], EditorSettings::remapPresetOrigins([]));
    }

    public function testFilterInjectsWhatTheElevenNineEditorExpects(): void
    {
        Functions\when('wp_is_block_theme')->justReturn(false);
        Functions\when('current_theme_supports')->justReturn(false);
        Functions\when('is_rtl')->justReturn(false);
        Functions\expect('wp_add_inline_script')
            ->once()
            ->with('wp-block-editor', \Mockery::pattern('/^window\.__editorAssets = \{"styles":"<link>"/'), 'before');
        Functions\when('wp_json_encode')->alias(static fn(mixed $v, int $flags = 0): string => (string) json_encode($v, $flags));

        $patterns = [['name' => 'theme/hero', 'content' => '<!-- wp:paragraph /-->']];
        $categories = [['name' => 'featured']];
        WP_Block_Patterns_Registry::$items = $patterns;
        WP_Block_Pattern_Categories_Registry::$items = $categories;

        $settings = EditorSettings::filter(
            [
                'supportsTemplateMode'     => true,
                '__experimentalFeatures'   => ['color' => ['palette' => ['default' => ['x']]]],
                '__unstableResolvedAssets' => ['styles' => '<link>', 'scripts' => ''],
            ],
            new WP_Block_Editor_Context()
        );

        self::assertFalse($settings['__unstableEnableFullSiteEditingBlocks']);
        self::assertFalse($settings['supportsTemplateMode']);
        self::assertSame($patterns, $settings['__experimentalBlockPatterns']);
        self::assertSame($categories, $settings['__experimentalBlockPatternCategories']);
        self::assertSame(['core' => ['x']], $settings['__experimentalFeatures']['color']['palette']);
        if (self::assetsDir() !== null) {
            self::assertStringContainsString('font-family', $settings['defaultEditorStyles'][0]['css']);
        } else {
            self::assertArrayNotHasKey('defaultEditorStyles', $settings);
        }
    }

    public function testBlockThemesEnableTheFullSiteEditingBlocks(): void
    {
        Functions\when('wp_is_block_theme')->justReturn(true);
        Functions\when('current_theme_supports')->justReturn(false);
        Functions\when('is_rtl')->justReturn(false);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_json_encode')->alias(static fn(mixed $v, int $flags = 0): string => (string) json_encode($v, $flags));
        WP_Block_Patterns_Registry::$items = [];
        WP_Block_Pattern_Categories_Registry::$items = [];

        $settings = EditorSettings::filter([], new WP_Block_Editor_Context());

        self::assertTrue($settings['__unstableEnableFullSiteEditingBlocks']);
    }
}
