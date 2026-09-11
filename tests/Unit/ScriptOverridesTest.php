<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use Brain\Monkey\Functions;
use GutenbergDowngrade\BlockConfig;
use GutenbergDowngrade\Manifest;
use GutenbergDowngrade\ScriptOverrides;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use WP_Scripts;

#[CoversClass(ScriptOverrides::class)]
final class ScriptOverridesTest extends UnitTestCase
{
    private const BASE = 'https://example.test/wp-content/plugins/gutenberg-downgrade/assets/gutenberg/';

    private function manifest(): Manifest
    {
        return Manifest::fromArray([
            'gutenbergVersion' => '11.9.1',
            'packages'         => [
                'wp-blocks'          => ['js' => 'build/blocks/index.min.js', 'jsDebug' => 'build/blocks/index.js', 'deps' => ['wp-hooks', 'wp-polyfill'], 'version' => 'v-blocks'],
                'wp-edit-post'       => ['js' => 'build/edit-post/index.min.js', 'jsDebug' => null, 'deps' => ['wp-blocks', 'wp-polyfill'], 'version' => 'v-edit-post'],
                'wp-i18n'            => ['js' => 'build/i18n/index.min.js', 'jsDebug' => null, 'deps' => ['wp-hooks', 'wp-polyfill'], 'version' => 'v-i18n'],
                'wp-edit-navigation' => ['js' => 'build/edit-navigation/index.min.js', 'jsDebug' => null, 'deps' => [], 'version' => 'v-nav'],
            ],
            'blocks'           => ['core/paragraph' => ['dir' => 'build/block-library/blocks/paragraph', 'php' => null]],
            'vendor'           => [
                'react'     => ['prod' => 'vendor/react.min.js', 'dev' => 'vendor/react.js'],
                'react-dom' => ['prod' => 'vendor/react-dom.min.js', 'dev' => 'vendor/react-dom.js'],
            ],
        ]);
    }

    private function coreScripts(): WP_Scripts
    {
        $scripts = new WP_Scripts();
        $scripts->add('react', '/wp-includes/js/dist/vendor/react.min.js', [], '18.3.1.1', 1);
        $scripts->add('react-dom', '/wp-includes/js/dist/vendor/react-dom.min.js', ['react'], '18.3.1.1', 1);
        $scripts->add('wp-hooks', '/wp-includes/js/dist/hooks.min.js', [], 'core', 1);
        $scripts->add('wp-i18n', '/wp-includes/js/dist/i18n.min.js', ['wp-hooks'], 'core', 1);
        $scripts->add('wp-blocks', '/wp-includes/js/dist/blocks.min.js', ['react-jsx-runtime', 'wp-private-apis'], 'core', 1);
        $scripts->add_inline_script('wp-blocks', 'wp.blocks.setCategories([]);', 'after');
        $scripts->add('wp-edit-post', '/wp-includes/js/dist/edit-post.min.js', ['wp-commands', 'wp-private-apis'], 'core', 1);
        $scripts->add_data('wp-edit-post', 'module_dependencies', [['id' => '@wordpress/route', 'import' => 'static']]);
        $scripts->add('wp-commands', '/wp-includes/js/dist/commands.min.js', [], 'core', 1);
        $scripts->add('wp-core-commands', '/wp-includes/js/dist/core-commands.min.js', ['wp-commands'], 'core', 1);
        $scripts->add('wp-private-apis', '/wp-includes/js/dist/private-apis.min.js', [], 'core', 1);
        $scripts->add('wp-base-styles', '/wp-includes/js/dist/base-styles.min.js', [], 'core', 1);

        return $scripts;
    }

    public function testRepointsCoreHandlesAtTheVendoredBuild(): void
    {
        $scripts = $this->coreScripts();
        ScriptOverrides::register($this->manifest(), BlockConfig::load());

        ScriptOverrides::apply($scripts);

        $editPost = $scripts->registered['wp-edit-post'];
        self::assertSame(self::BASE . 'build/edit-post/index.min.js', $editPost->src);
        self::assertSame('v-edit-post', $editPost->ver);
        self::assertSame(1, $editPost->args);
        self::assertSame(1, $editPost->extra['group']);
        self::assertArrayNotHasKey('module_dependencies', $editPost->extra, '7.x import-map data must not leak into the 11.9 bundle');
        self::assertSame(
            ['wp-blocks', 'wp-polyfill', 'media-models', 'media-views', 'postbox', 'wp-dom-ready', 'wp-i18n'],
            $editPost->deps,
            'asset.php deps + the manual extras core cannot detect + wp-i18n from set_translations'
        );
        self::assertSame('default', $editPost->textdomain);

        $react = $scripts->registered['react'];
        self::assertSame(self::BASE . 'vendor/react.min.js', $react->src);
        self::assertSame(ScriptOverrides::REACT_VERSION, $react->ver);
        self::assertSame(['wp-polyfill'], $react->deps);
        self::assertSame(['react'], $scripts->registered['react-dom']->deps);
    }

    public function testKeepsCoreInlineScriptsAndDropsRetiredHandles(): void
    {
        $scripts = $this->coreScripts();
        ScriptOverrides::register($this->manifest(), BlockConfig::load());

        ScriptOverrides::apply($scripts);

        $after = $scripts->registered['wp-blocks']->extra['after'];
        self::assertSame('wp.blocks.setCategories([]);', $after[0], 'core inline scripts survive the in-place override');
        self::assertStringContainsString('registerBlockBindingsSource', $after[1], 'the compat shim is appended');
        self::assertArrayNotHasKey('wp-commands', $scripts->registered);
        self::assertArrayNotHasKey('wp-core-commands', $scripts->registered);
        self::assertArrayHasKey('wp-private-apis', $scripts->registered, 'other 7.x-only handles stay registered');
        self::assertArrayHasKey('wp-base-styles', $scripts->registered);
        self::assertArrayNotHasKey('wp-edit-navigation', $scripts->registered, 'handles core never registered are not added');
        self::assertSame(['wp-hooks', 'wp-polyfill'], $scripts->registered['wp-i18n']->deps, 'wp-i18n gets no set_translations() (core #46089)');
        self::assertSame(['wp-hooks', 'wp-polyfill', 'wp-i18n'], $scripts->registered['wp-blocks']->deps);
    }

    public function testIsIdempotentAcrossRepeatedFirings(): void
    {
        $scripts = $this->coreScripts();
        ScriptOverrides::register($this->manifest(), BlockConfig::load());

        ScriptOverrides::apply($scripts);
        // Core re-adds the handle on the init@0 re-fire; our callback runs after it again.
        $scripts->add('wp-commands', '/wp-includes/js/dist/commands.min.js', [], 'core', 1);
        ScriptOverrides::apply($scripts);

        self::assertCount(2, $scripts->registered['wp-blocks']->extra['after'], 'the shim is attached exactly once');
        self::assertArrayNotHasKey('wp-commands', $scripts->registered);
        self::assertCount(1, array_keys($scripts->registered['wp-edit-post']->deps, 'wp-i18n', true));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testServesUnminifiedBundlesUnderScriptDebug(): void
    {
        define('SCRIPT_DEBUG', true);

        $scripts = $this->coreScripts();
        ScriptOverrides::register($this->manifest(), BlockConfig::load());
        ScriptOverrides::apply($scripts);

        self::assertSame(self::BASE . 'build/blocks/index.js', $scripts->registered['wp-blocks']->src);
        self::assertSame(self::BASE . 'build/edit-post/index.min.js', $scripts->registered['wp-edit-post']->src, 'no unminified file shipped: falls back');
        self::assertSame(self::BASE . 'vendor/react.js', $scripts->registered['react']->src);
    }

    public function testExposesTheHiddenBlockListBeforeWpBlocks(): void
    {
        Functions\when('wp_json_encode')->alias(static fn(mixed $v, int $flags = 0): string => (string) json_encode($v, $flags));
        Functions\expect('wp_add_inline_script')
            ->once()
            ->with('wp-blocks', 'window.gutenbergDowngradeHiddenBlocks = ["core/navigation-area"];', 'before');

        ScriptOverrides::register($this->manifest(), BlockConfig::load());
        ScriptOverrides::exposeHiddenBlocks();
    }

    public function testCompatScriptIsThePlainShim(): void
    {
        $shim = ScriptOverrides::compatScript();

        self::assertStringStartsWith('// Inlined verbatim', $shim);
        self::assertStringContainsString('gutenbergDowngradeHiddenBlocks', $shim);
        self::assertStringNotContainsString('import ', $shim);
    }
}
