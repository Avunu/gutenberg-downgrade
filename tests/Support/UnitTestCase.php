<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Support;

use Brain\Monkey;
use GutenbergDowngrade\Assets;
use GutenbergDowngrade\Runtime;
use PHPUnit\Framework\TestCase;
use WP_Block_Type_Registry;

/**
 * Base class for suites that stub WordPress with Brain Monkey rather than
 * loading it.
 */
abstract class UnitTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Assets::reset();
        Runtime::reset();
        WP_Block_Type_Registry::reset();

        // Used by every layer that builds an asset URL.
        Monkey\Functions\when('plugins_url')->alias(
            static fn(string $path = '', string $plugin = ''): string => 'https://example.test/wp-content/plugins/gutenberg-downgrade/' . ltrim($path, '/')
        );
    }

    protected function tearDown(): void
    {
        // Brain Monkey / Mockery expectations are verified in Monkey\tearDown();
        // count them so expectation-only tests are not reported as risky.
        $this->addToAssertionCount(max(0, \Mockery::getContainer()->mockery_getExpectationCount()));
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * The vendored Gutenberg build, when it is available to this test run.
     * Set GUTENBERG_DOWNGRADE_ASSETS to point elsewhere; tests that need real
     * assets call markTestSkipped() when this returns null.
     */
    protected static function assetsDir(): ?string
    {
        $dir = getenv('GUTENBERG_DOWNGRADE_ASSETS');
        if (!is_string($dir) || $dir === '') {
            $dir = GUTENBERG_DOWNGRADE_DIR . 'assets/gutenberg';
        }

        return is_file($dir . '/manifest.php') ? rtrim($dir, '/') : null;
    }

    protected function requireAssets(): string
    {
        $dir = self::assetsDir();
        if ($dir === null) {
            self::markTestSkipped('assets/gutenberg is not synced (nix run .#sync-assets)');
        }

        return $dir;
    }
}
