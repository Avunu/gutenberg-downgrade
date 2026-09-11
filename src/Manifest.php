<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

use RuntimeException;

/**
 * Typed, validated view of assets/gutenberg/manifest.php — the inventory of
 * the vendored Gutenberg build (see bin/generate-manifest.php).
 *
 * @phpstan-type PackageEntry array{js: string, jsDebug: ?string, deps: list<string>, version: string}
 * @phpstan-type BlockEntry array{dir: string, php: ?string}
 * @phpstan-type VendorEntry array{prod: string, dev: string}
 * @phpstan-type ManifestArray array{
 *     gutenbergVersion: string,
 *     packages: array<string, PackageEntry>,
 *     blocks: array<string, BlockEntry>,
 *     vendor: array{react: VendorEntry, react-dom: VendorEntry, react-jsx-runtime: VendorEntry}
 * }
 */
final class Manifest
{
    /** The script handles served from vendor/, as core registers them. */
    public const VENDOR_LIBRARIES = ['react', 'react-dom', 'react-jsx-runtime'];

    /**
     * @param ManifestArray $data
     */
    private function __construct(private readonly array $data)
    {
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            throw new RuntimeException("manifest not found: {$file}");
        }

        /** @var mixed $data */
        $data = require $file;

        return self::fromArray($data);
    }

    /**
     * @param mixed $data
     */
    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            throw new RuntimeException('manifest must be an array');
        }

        $version = $data['gutenbergVersion'] ?? null;
        if (!is_string($version) || $version === '') {
            throw new RuntimeException('manifest.gutenbergVersion must be a non-empty string');
        }

        $packages = [];
        foreach (self::assocArray($data, 'packages') as $handle => $entry) {
            if (
                !is_array($entry)
                || !isset($entry['js'], $entry['deps'], $entry['version'])
                || !is_string($entry['js'])
                || !is_array($entry['deps'])
                || !is_string($entry['version'])
                || !array_key_exists('jsDebug', $entry)
                || !($entry['jsDebug'] === null || is_string($entry['jsDebug']))
            ) {
                throw new RuntimeException("manifest.packages[{$handle}] is malformed");
            }

            $deps = [];
            foreach ($entry['deps'] as $dep) {
                if (!is_string($dep)) {
                    throw new RuntimeException("manifest.packages[{$handle}].deps must be strings");
                }
                $deps[] = $dep;
            }

            $packages[$handle] = [
                'js'      => $entry['js'],
                'jsDebug' => $entry['jsDebug'],
                'deps'    => $deps,
                'version' => $entry['version'],
            ];
        }

        $blocks = [];
        foreach (self::assocArray($data, 'blocks') as $name => $entry) {
            if (
                !is_array($entry)
                || !isset($entry['dir'])
                || !is_string($entry['dir'])
                || !array_key_exists('php', $entry)
                || !($entry['php'] === null || is_string($entry['php']))
            ) {
                throw new RuntimeException("manifest.blocks[{$name}] is malformed");
            }

            $blocks[$name] = ['dir' => $entry['dir'], 'php' => $entry['php']];
        }

        $vendorRaw = $data['vendor'] ?? null;
        if (!is_array($vendorRaw)) {
            throw new RuntimeException('manifest.vendor is missing');
        }
        $vendor = [
            'react'             => self::vendorEntry($vendorRaw, 'react'),
            'react-dom'         => self::vendorEntry($vendorRaw, 'react-dom'),
            'react-jsx-runtime' => self::vendorEntry($vendorRaw, 'react-jsx-runtime'),
        ];

        return new self([
            'gutenbergVersion' => $version,
            'packages'         => $packages,
            'blocks'           => $blocks,
            'vendor'           => $vendor,
        ]);
    }

    public function gutenbergVersion(): string
    {
        return $this->data['gutenbergVersion'];
    }

    /**
     * Script handle (`wp-<package>`) → bundle description.
     *
     * @return array<string, PackageEntry>
     */
    public function packages(): array
    {
        return $this->data['packages'];
    }

    /**
     * Block name (`core/<block>`) → folder holding block.json, and the
     * dynamic-block PHP file when the release ships one.
     *
     * @return array<string, BlockEntry>
     */
    public function blocks(): array
    {
        return $this->data['blocks'];
    }

    /**
     * @param 'react'|'react-dom'|'react-jsx-runtime' $library
     * @return VendorEntry
     */
    public function vendor(string $library): array
    {
        return $this->data['vendor'][$library];
    }

    /**
     * @return ManifestArray
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private static function assocArray(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            throw new RuntimeException("manifest.{$key} must be an array");
        }

        $out = [];
        foreach ($value as $k => $v) {
            if (!is_string($k)) {
                throw new RuntimeException("manifest.{$key} must be keyed by name");
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * @param array<mixed> $vendor
     * @return VendorEntry
     */
    private static function vendorEntry(array $vendor, string $library): array
    {
        $entry = $vendor[$library] ?? null;
        if (
            !is_array($entry)
            || !isset($entry['prod'], $entry['dev'])
            || !is_string($entry['prod'])
            || !is_string($entry['dev'])
        ) {
            throw new RuntimeException("manifest.vendor.{$library} is malformed");
        }

        return ['prod' => $entry['prod'], 'dev' => $entry['dev']];
    }
}
