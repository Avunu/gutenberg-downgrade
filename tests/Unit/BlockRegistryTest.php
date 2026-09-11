<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use Brain\Monkey\Functions;
use GutenbergDowngrade\BlockConfig;
use GutenbergDowngrade\BlockRegistry;
use GutenbergDowngrade\Manifest;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_Block_Type_Registry;

#[CoversClass(BlockRegistry::class)]
final class BlockRegistryTest extends UnitTestCase
{
    public function testBackfillsTitlesForBlocksLeftToCore(): void
    {
        $assets = $this->requireAssets();
        $manifest = Manifest::fromArray([
            'gutenbergVersion' => '18.5.0',
            'packages'         => ['wp-blocks' => ['js' => 'build/blocks/index.min.js', 'jsDebug' => null, 'deps' => [], 'version' => 'v']],
            'blocks'           => [
                'core/post-time-to-read' => ['dir' => 'build/block-library/blocks/post-time-to-read', 'php' => 'build/block-library/blocks/post-time-to-read.php'],
                'core/paragraph'     => ['dir' => 'build/block-library/blocks/paragraph', 'php' => null],
            ],
            'vendor'           => [
                'react'             => ['prod' => 'vendor/react.min.js', 'dev' => 'vendor/react.js'],
                'react-dom'         => ['prod' => 'vendor/react-dom.min.js', 'dev' => 'vendor/react-dom.js'],
                'react-jsx-runtime' => ['prod' => 'vendor/react-jsx-runtime.min.js', 'dev' => 'vendor/react-jsx-runtime.js'],
            ],
        ]);
        $registry = WP_Block_Type_Registry::get_instance();
        $legacy = new \stdClass();
        $legacy->title = '';
        $registry->register('core/post-time-to-read', $legacy);
        $paragraph = new \stdClass();
        $paragraph->title = 'Kept';
        $registry->register('core/paragraph', $paragraph);
        Functions\when('register_block_type_from_metadata')->justReturn(null);

        BlockRegistry::register($manifest, BlockConfig::load());
        BlockRegistry::backfillTitles();

        self::assertSame('Time To Read', $legacy->title, 'from ' . $assets . '/build/block-library/blocks/post-time-to-read/block.json');
        self::assertSame('Kept', $paragraph->title, 'only skip-mode blocks are touched');
        self::assertNull(BlockRegistry::metadataTitle('/nonexistent/block.json'));
    }

    public function testCoreRenderCallbackNaming(): void
    {
        self::assertSame('render_block_core_site_logo', BlockRegistry::coreRenderCallback('core/site-logo'));
        self::assertSame('render_block_core_template_part', BlockRegistry::coreRenderCallback('core/template-part'));
        self::assertSame('render_block_core_x', BlockRegistry::coreRenderCallback('x'));
    }

    public function testReregisterFollowsTheConfiguredModes(): void
    {
        $assets = $this->requireAssets();
        $manifest = Manifest::fromArray([
            'gutenbergVersion' => '18.5.0',
            'packages'         => ['wp-blocks' => ['js' => 'build/blocks/index.min.js', 'jsDebug' => null, 'deps' => [], 'version' => 'v']],
            'blocks'           => [
                'core/paragraph' => ['dir' => 'build/block-library/blocks/paragraph', 'php' => null],
                'core/archives'  => ['dir' => 'build/block-library/blocks/archives', 'php' => 'build/block-library/blocks/archives.php'],
                'core/site-logo' => ['dir' => 'build/block-library/blocks/site-logo', 'php' => 'build/block-library/blocks/site-logo.php'],
                'core/post-time-to-read' => ['dir' => 'build/block-library/blocks/post-time-to-read', 'php' => 'build/block-library/blocks/post-time-to-read.php'],
            ],
            'vendor'           => [
                'react'             => ['prod' => 'vendor/react.min.js', 'dev' => 'vendor/react.js'],
                'react-dom'         => ['prod' => 'vendor/react-dom.min.js', 'dev' => 'vendor/react-dom.js'],
                'react-jsx-runtime' => ['prod' => 'vendor/react-jsx-runtime.min.js', 'dev' => 'vendor/react-jsx-runtime.js'],
            ],
        ]);

        $registry = WP_Block_Type_Registry::get_instance();
        foreach (['core/paragraph', 'core/archives', 'core/site-logo', 'core/post-time-to-read'] as $name) {
            $registry->register($name, []);
        }

        // Core defines this one; the 18.5 file (loaded by register()) defines its own gutenberg_ copy.
        Functions\when('render_block_core_site_logo')->justReturn('');

        $registered = [];
        Functions\when('register_block_type_from_metadata')->alias(
            static function (string $dir, array $args = []) use (&$registered): void {
                $registered[basename($dir)] = ['dir' => $dir, 'args' => $args];
            }
        );

        BlockRegistry::register($manifest, BlockConfig::load());
        self::assertTrue(function_exists('gutenberg_render_block_core_archives'), 'the 18.5 dynamic-block file is loaded');
        self::assertTrue(function_exists('gutenberg_style_engine_get_styles'), 'the lib/ shims are defined');

        BlockRegistry::reregister();

        self::assertSame(['core/paragraph', 'core/archives', 'core/site-logo'], $registry->unregistered);
        self::assertTrue($registry->is_registered('core/post-time-to-read'), 'skip leaves core alone');
        self::assertSame([], $registered['paragraph']['args'], 'static: 18.5 block.json, no callback');
        self::assertSame(
            ['render_callback' => 'render_block_core_site_logo'],
            $registered['site-logo']['args'],
            'core-render: 18.5 metadata with the core callback'
        );
        self::assertArrayNotHasKey('archives', $registered, 'dynamic: the 18.5 file registers it itself at init@20');
        // The registration dir must be the vendored copy, not core's.
        self::assertSame($assets . '/build/block-library/blocks/paragraph', $registered['paragraph']['dir']);
    }
}
