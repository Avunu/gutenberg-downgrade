#!/usr/bin/env php
<?php

/**
 * Verifies the assembled assets/gutenberg tree against what the runtime
 * expects: the manifest validates and matches a fresh regeneration, every
 * stylesheet in StyleOverrides::STYLES (and its RTL twin) exists, every
 * package bundle exists, and the block config covers exactly the shipped
 * blocks. Exit status 1 on any problem.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use GutenbergDowngrade\BlockConfig;
use GutenbergDowngrade\Manifest;
use GutenbergDowngrade\ManifestGenerator;
use GutenbergDowngrade\StyleOverrides;

$root = dirname(__DIR__, 2);
$assets = $root . '/assets/gutenberg';
$problems = [];

$exists = static function (string $relative) use ($assets, &$problems): void {
    if (!is_file($assets . '/' . $relative)) {
        $problems[] = "missing: {$relative}";
    }
};

$manifest = Manifest::load($assets . '/manifest.php');
if (ManifestGenerator::generate($assets, $manifest->gutenbergVersion()) !== $manifest->toArray()) {
    $problems[] = 'manifest.php differs from a fresh regeneration';
}

foreach ($manifest->packages() as $handle => $package) {
    $exists($package['js']);
    if ($package['jsDebug'] !== null) {
        $exists($package['jsDebug']);
    }
}
foreach ($manifest->blocks() as $name => $block) {
    $exists($block['dir'] . '/block.json');
    if ($block['php'] !== null) {
        $exists($block['php']);
    }
}
foreach (Manifest::VENDOR_LIBRARIES as $lib) {
    $exists($manifest->vendor($lib)['prod']);
    $exists($manifest->vendor($lib)['dev']);
}

foreach (StyleOverrides::STYLES as $handle => $style) {
    $exists($style['file']);
    $exists((string) preg_replace('/\.css$/', '-rtl.css', $style['file']));
}
$exists(StyleOverrides::blockLibraryFile(true));
$exists('build/block-editor/default-editor-styles.css');
$exists('build/block-editor/default-editor-styles-rtl.css');
$exists('SOURCE.txt');

$config = BlockConfig::load($root . '/config/blocks.php');
$shipped = array_keys($manifest->blocks());
$configured = array_keys($config->entries());
sort($shipped);
sort($configured);
if ($shipped !== $configured) {
    $problems[] = 'config/blocks.php does not list exactly the shipped blocks: '
        . implode(', ', array_merge(array_diff($shipped, $configured), array_diff($configured, $shipped)));
}

// Every handle core's editor page can enqueue must resolve to a vendored bundle.
foreach (['wp-edit-post', 'wp-blocks', 'wp-block-library', 'wp-block-editor', 'wp-editor', 'wp-components', 'wp-format-library', 'wp-block-directory', 'wp-edit-widgets', 'wp-customize-widgets', 'wp-list-reusable-blocks'] as $handle) {
    if (!isset($manifest->packages()[$handle])) {
        $problems[] = "manifest has no package for {$handle}";
    }
}

if ($problems !== []) {
    echo "FAIL:\n";
    foreach ($problems as $problem) {
        echo "  - {$problem}\n";
    }
    exit(1);
}

printf(
    "assets/gutenberg is consistent: Gutenberg %s, %d packages, %d blocks, %d stylesheets.\n",
    $manifest->gutenbergVersion(),
    count($manifest->packages()),
    count($manifest->blocks()),
    count(StyleOverrides::STYLES)
);
