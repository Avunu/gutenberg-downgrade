<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use GutenbergDowngrade\CoreNeutralizer;
use GutenbergDowngrade\Manifest;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_Block_Patterns_Registry;

#[CoversClass(CoreNeutralizer::class)]
final class CoreNeutralizerTest extends UnitTestCase
{
    private function manifest(): Manifest
    {
        return Manifest::fromArray([
            'gutenbergVersion' => '18.5.0',
            'packages'         => ['wp-blocks' => ['js' => 'build/blocks/index.min.js', 'jsDebug' => null, 'deps' => [], 'version' => 'v']],
            'blocks'           => [
                'core/paragraph'  => ['dir' => 'build/block-library/blocks/paragraph', 'php' => null],
                'core/navigation' => ['dir' => 'build/block-library/blocks/navigation', 'php' => 'build/block-library/blocks/navigation.php'],
            ],
            'vendor'           => [
                'react'             => ['prod' => 'vendor/react.min.js', 'dev' => 'vendor/react.js'],
                'react-dom'         => ['prod' => 'vendor/react-dom.min.js', 'dev' => 'vendor/react-dom.js'],
                'react-jsx-runtime' => ['prod' => 'vendor/react-jsx-runtime.min.js', 'dev' => 'vendor/react-jsx-runtime.js'],
            ],
        ]);
    }

    public function testUnhooksTheModernStackAndFiltersPatterns(): void
    {
        Actions\expectRemoved('admin_enqueue_scripts')->once()->with('wp_enqueue_command_palette_assets');
        Actions\expectRemoved('enqueue_block_editor_assets')->twice();
        Filters\expectAdded('wp_client_side_media_processing_enabled')->once()->with('__return_false');
        Filters\expectAdded('should_load_remote_block_patterns')->once()->with('__return_false');
        Actions\expectAdded('init')
            ->once()
            ->with([CoreNeutralizer::class, 'removeUnsupportedPatterns'], CoreNeutralizer::PATTERNS_PRIORITY);

        CoreNeutralizer::register($this->manifest());
    }

    public function testUnknownCoreBlocksSpotsWhatTheBuildLacks(): void
    {
        $known = ['core/paragraph', 'core/navigation'];
        $content = '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->'
            . '<!-- wp:core/navigation {"ref":1} /-->'
            . '<!-- wp:navigation-overlay-close /-->'
            . '<!-- wp:acme/hero {"a":1} -->'
            . '<!-- wp:navigation-overlay-close /-->';

        self::assertSame(['core/navigation-overlay-close'], CoreNeutralizer::unknownCoreBlocks($content, $known));
        self::assertSame([], CoreNeutralizer::unknownCoreBlocks('<!-- wp:paragraph /-->', $known));
        self::assertSame([], CoreNeutralizer::unknownCoreBlocks('plain html', $known));
    }

    public function testRemoveUnsupportedPatternsUnregistersOnlyTheOffenders(): void
    {
        WP_Block_Patterns_Registry::$items = [
            ['name' => 'core/query-standard-posts', 'content' => '<!-- wp:paragraph /-->'],
            ['name' => 'core/navigation-overlay', 'content' => '<!-- wp:navigation-overlay-close /-->'],
            ['name' => 'theme/legacy-comments', 'content' => '<!-- wp:post-comments /-->'],
            ['name' => 'theme/custom', 'content' => '<!-- wp:acme/thing /-->'],
            ['name' => 'broken', 'content' => null],
        ];
        CoreNeutralizer::register($this->manifest());

        CoreNeutralizer::removeUnsupportedPatterns();

        self::assertSame(
            ['core/query-standard-posts', 'theme/legacy-comments', 'theme/custom', 'broken'],
            array_column(WP_Block_Patterns_Registry::$items, 'name'),
            'the 7.x overlay goes; the legacy alias 18.5 registers itself stays'
        );
    }
}
