<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use GutenbergDowngrade\BlockConfig;
use GutenbergDowngrade\Manifest;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(BlockConfig::class)]
final class BlockConfigTest extends UnitTestCase
{
    public function testTheCommittedConfigLoads(): void
    {
        $config = BlockConfig::load();

        self::assertSame(BlockConfig::MODE_STATIC, $config->mode('core/paragraph'));
        self::assertSame(BlockConfig::MODE_DYNAMIC, $config->mode('core/archives'));
        self::assertSame(BlockConfig::MODE_CORE_RENDER, $config->mode('core/site-logo'));
        self::assertSame(BlockConfig::MODE_SKIP, $config->mode('core/post-comments'));
        self::assertNull($config->mode('core/list-item'));
        self::assertContains('core/navigation-area', $config->hiddenFromInserter());
    }

    public function testCuratedModesRequireAReason(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must give a reason');
        BlockConfig::fromArray(['blocks' => ['core/x' => 'skip']]);
    }

    public function testUnknownModesAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown mode');
        BlockConfig::fromArray(['blocks' => ['core/x' => 'maybe']]);
    }

    public function testEveryShippedBlockIsConfiguredExactlyOnce(): void
    {
        $assets = $this->requireAssets();
        $manifest = Manifest::load($assets . '/manifest.php');
        $config = BlockConfig::load();

        $shipped = array_keys($manifest->blocks());
        $configured = array_keys($config->entries());
        sort($shipped);
        sort($configured);

        self::assertSame($shipped, $configured, 'config/blocks.php must list exactly the blocks in the manifest');

        foreach ($manifest->blocks() as $name => $block) {
            $mode = $config->mode($name);
            if ($block['php'] === null) {
                self::assertContains(
                    $mode,
                    [BlockConfig::MODE_STATIC, BlockConfig::MODE_SKIP],
                    "{$name} has no PHP in 11.9 and cannot be {$mode}"
                );
            } else {
                self::assertNotSame(BlockConfig::MODE_STATIC, $mode, "{$name} ships PHP in 11.9; static would drop its render callback");
            }
        }

        foreach ($config->hiddenFromInserter() as $hidden) {
            self::assertArrayHasKey($hidden, $manifest->blocks(), "{$hidden} is hidden but not a shipped block");
        }
    }
}
