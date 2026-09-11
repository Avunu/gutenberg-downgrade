#!/usr/bin/env php
<?php

/**
 * Generate assets/gutenberg/manifest.php from an assembled Gutenberg asset tree.
 *
 * Usage:
 *   php bin/generate-manifest.php --assets <dir> --gutenberg-version <x.y.z> [--out <file>]
 *
 * <dir> is the tree nix/gutenberg-assets.nix assembles: the release's build/
 * directory plus vendor/react{,-dom,-jsx-runtime}{,.min}.js. The manifest is what the plugin
 * reads at runtime, so every path it records is verified to exist here — a
 * missing file fails the build, never a request.
 *
 * Deliberately WordPress-free and dependency-free: it runs inside the Nix
 * sandbox before composer install.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Manifest.php';
require_once __DIR__ . '/../src/ManifestGenerator.php';

use GutenbergDowngrade\ManifestGenerator;

$options = getopt('', ['assets:', 'gutenberg-version:', 'out::']);
$assets = $options['assets'] ?? null;
$gutenbergVersion = $options['gutenberg-version'] ?? null;

if (!is_string($assets) || !is_string($gutenbergVersion)) {
    fwrite(STDERR, "Usage: generate-manifest.php --assets <dir> --gutenberg-version <x.y.z> [--out <file>]\n");
    exit(2);
}

$out = $options['out'] ?? null;
$out = is_string($out) ? $out : rtrim($assets, '/') . '/manifest.php';

try {
    $manifest = ManifestGenerator::generate(rtrim($assets, '/'), $gutenbergVersion);
} catch (RuntimeException $e) {
    fwrite(STDERR, 'generate-manifest: ' . $e->getMessage() . "\n");
    exit(1);
}

$written = file_put_contents($out, ManifestGenerator::render($manifest));
if ($written === false) {
    fwrite(STDERR, "generate-manifest: could not write {$out}\n");
    exit(1);
}

printf(
    "Wrote %s: %d packages, %d blocks (Gutenberg %s)\n",
    $out,
    count($manifest['packages']),
    count($manifest['blocks']),
    $gutenbergVersion
);
