<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use Brain\Monkey\Filters;
use GutenbergDowngrade\EditorSettings;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_Block_Editor_Context;

#[CoversClass(EditorSettings::class)]
final class EditorSettingsTest extends UnitTestCase
{
    public function testHooksLateOnTheSettingsFilter(): void
    {
        Filters\expectAdded('block_editor_settings_all')
            ->once()
            ->with([EditorSettings::class, 'filter'], EditorSettings::PRIORITY, 2);

        EditorSettings::register();
    }

    public function testPassesCoreSettingsThroughAndExposesTheFilter(): void
    {
        $context = new WP_Block_Editor_Context();
        $settings = ['supportsTemplateMode' => true, '__experimentalFeatures' => ['color' => []]];

        Filters\expectApplied('gutenberg_downgrade_editor_settings')
            ->once()
            ->with($settings, $context)
            ->andReturn($settings + ['custom' => true]);

        self::assertSame($settings + ['custom' => true], EditorSettings::filter($settings, $context));
    }

    public function testIgnoresAFilterThatReturnsGarbage(): void
    {
        $context = new WP_Block_Editor_Context();
        Filters\expectApplied('gutenberg_downgrade_editor_settings')->once()->andReturn('nope');

        self::assertSame(['a' => 1], EditorSettings::filter(['a' => 1], $context));
    }
}
