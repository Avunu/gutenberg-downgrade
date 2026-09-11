<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use GutenbergDowngrade\Manifest;
use GutenbergDowngrade\ManifestGenerator;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * @phpstan-import-type ManifestArray from Manifest
 */
#[CoversClass(Manifest::class)]
#[CoversClass(ManifestGenerator::class)]
final class ManifestTest extends UnitTestCase
{
    /**
     * @return ManifestArray
     */
    private static function valid(): array
    {
        return [
            'gutenbergVersion' => '11.9.1',
            'packages'         => [
                'wp-a11y' => [
                    'js'      => 'build/a11y/index.min.js',
                    'jsDebug' => 'build/a11y/index.js',
                    'deps'    => ['wp-dom-ready', 'wp-i18n', 'wp-polyfill'],
                    'version' => 'abc',
                ],
            ],
            'blocks'           => [
                'core/archives'  => ['dir' => 'build/block-library/blocks/archives', 'php' => 'build/block-library/blocks/archives.php'],
                'core/paragraph' => ['dir' => 'build/block-library/blocks/paragraph', 'php' => null],
            ],
            'vendor'           => [
                'react'     => ['prod' => 'vendor/react.min.js', 'dev' => 'vendor/react.js'],
                'react-dom' => ['prod' => 'vendor/react-dom.min.js', 'dev' => 'vendor/react-dom.js'],
            ],
        ];
    }

    public function testRoundTripsAValidArray(): void
    {
        $manifest = Manifest::fromArray(self::valid());

        self::assertSame('11.9.1', $manifest->gutenbergVersion());
        self::assertSame(['wp-dom-ready', 'wp-i18n', 'wp-polyfill'], $manifest->packages()['wp-a11y']['deps']);
        self::assertNull($manifest->blocks()['core/paragraph']['php']);
        self::assertSame('vendor/react-dom.js', $manifest->vendor('react-dom')['dev']);
        self::assertSame(self::valid(), $manifest->toArray());
    }

    public function testRejectsAPackageWithoutDependencies(): void
    {
        $data = self::valid();
        unset($data['packages']['wp-a11y']['deps']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('manifest.packages[wp-a11y]');
        Manifest::fromArray($data);
    }

    public function testRejectsABlockWithoutTheOptionalPhpKey(): void
    {
        $data = self::valid();
        unset($data['blocks']['core/paragraph']['php']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('manifest.blocks[core/paragraph]');
        Manifest::fromArray($data);
    }

    public function testRejectsMissingVendor(): void
    {
        $data = self::valid();
        unset($data['vendor']['react-dom']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('manifest.vendor.react-dom');
        Manifest::fromArray($data);
    }

    public function testRenderedManifestIsLoadableAndStable(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'manifest');
        self::assertNotFalse($file);
        file_put_contents($file, ManifestGenerator::render(self::valid()));

        $loaded = Manifest::load($file);
        unlink($file);

        self::assertSame(self::valid(), $loaded->toArray());
        self::assertStringNotContainsString('array (', ManifestGenerator::render(self::valid()));
    }

    public function testTheShippedManifestValidatesAndMatchesARegeneration(): void
    {
        $assets = $this->requireAssets();

        $shipped = Manifest::load($assets . '/manifest.php');
        $regenerated = ManifestGenerator::generate($assets, $shipped->gutenbergVersion());

        self::assertSame($regenerated, $shipped->toArray());
        self::assertArrayHasKey('wp-edit-post', $shipped->packages());
        self::assertArrayHasKey('core/paragraph', $shipped->blocks());
        self::assertSame('11.9.1', $shipped->gutenbergVersion());
    }
}
