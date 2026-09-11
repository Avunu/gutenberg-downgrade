<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use GutenbergDowngrade\CoreNeutralizer;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CoreNeutralizer::class)]
final class CoreNeutralizerTest extends UnitTestCase
{
    public function testUnhooksTheModernStackAndDisablesCorePatterns(): void
    {
        Actions\expectRemoved('admin_enqueue_scripts')->once()->with('wp_enqueue_command_palette_assets');
        Actions\expectRemoved('enqueue_block_editor_assets')->twice();
        Filters\expectAdded('wp_client_side_media_processing_enabled')->once()->with('__return_false');
        Filters\expectAdded('should_load_remote_block_patterns')->once()->with('__return_false');
        Actions\expectAdded('after_setup_theme')->once()->with([CoreNeutralizer::class, 'removeCorePatterns'], 20);

        CoreNeutralizer::register();
    }

    public function testRemoveCorePatternsDropsTheThemeSupport(): void
    {
        Functions\expect('remove_theme_support')->once()->with('core-block-patterns');

        CoreNeutralizer::removeCorePatterns();
    }
}
