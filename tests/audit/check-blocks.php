#!/usr/bin/env php
<?php

/**
 * Reconciles the static audit of the vendored 11.9 block PHP with
 * config/blocks.php.
 *
 * Inputs:
 *   --phpstan-json <file>   PHPStan's --error-format=json output for
 *                           tests/audit/phpstan-blocks.neon
 *
 * Plus a compile pass (`php -l` with E_ALL) over every dynamic-block file,
 * which surfaces PHP 8.4 compile-time deprecations (`${var}` interpolation,
 * implicitly nullable parameters) that static analysis does not.
 *
 * Rules:
 *   - every shipped block is configured, and every configured block ships;
 *   - a file with findings must not be `dynamic` (curate it: core-render/skip);
 *   - core-render needs a shipped PHP file to replace;
 *   - a curated file with no findings is reported (its reason must then be
 *     behavioural, e.g. duplicate hooks) so stale curation is visible.
 *
 * Exit status 1 on any violation.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use GutenbergDowngrade\BlockConfig;
use GutenbergDowngrade\Manifest;

$root = dirname(__DIR__, 2);
$assets = $root . '/assets/gutenberg';

$options = getopt('', ['phpstan-json:']);
$phpstanJson = $options['phpstan-json'] ?? null;
if (!is_string($phpstanJson) || !is_file($phpstanJson)) {
    fwrite(STDERR, "Usage: check-blocks.php --phpstan-json <phpstan json output>\n");
    exit(2);
}

/** @var array<string, list<string>> $findings file (relative to assets) → messages */
$findings = [];

$raw = json_decode((string) file_get_contents($phpstanJson), true);
if (!is_array($raw) || !isset($raw['files']) || !is_array($raw['files'])) {
    fwrite(STDERR, "check-blocks: {$phpstanJson} is not PHPStan JSON output\n");
    exit(2);
}
foreach ($raw['files'] as $file => $result) {
    if (!is_string($file) || !is_array($result) || !isset($result['messages']) || !is_array($result['messages'])) {
        continue;
    }
    $relative = str_replace(realpath($assets) . '/', '', (string) realpath($file));
    foreach ($result['messages'] as $message) {
        if (is_array($message) && isset($message['message'], $message['line'])) {
            $findings[$relative][] = sprintf('phpstan L%d: %s', (int) $message['line'], (string) $message['message']);
        }
    }
}
if (isset($raw['errors']) && is_array($raw['errors']) && $raw['errors'] !== []) {
    fwrite(STDERR, "check-blocks: PHPStan reported internal errors:\n");
    foreach ($raw['errors'] as $error) {
        fwrite(STDERR, '  ' . (is_string($error) ? $error : json_encode($error)) . "\n");
    }
    exit(2);
}

$manifest = Manifest::load($assets . '/manifest.php');
$config = BlockConfig::load($root . '/config/blocks.php');

// Compile pass.
foreach ($manifest->blocks() as $name => $block) {
    if ($block['php'] === null) {
        continue;
    }
    $output = [];
    $status = 0;
    exec(
        sprintf(
            '%s -d error_reporting=E_ALL -d display_errors=1 -n -l %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($assets . '/' . $block['php'])
        ),
        $output,
        $status
    );
    foreach ($output as $line) {
        if ($status !== 0 || preg_match('/^(Deprecated|Warning|Fatal error|Parse error):/', $line) === 1) {
            $findings[$block['php']][] = 'php -l: ' . trim(preg_replace('/ in \S+ on line/', ' on line', $line) ?? $line);
        }
    }
}

$failures = [];
$notes = [];

$shipped = array_keys($manifest->blocks());
$configured = array_keys($config->entries());
foreach (array_diff($shipped, $configured) as $missing) {
    $failures[] = "{$missing} ships in the manifest but is not in config/blocks.php";
}
foreach (array_diff($configured, $shipped) as $extra) {
    $failures[] = "{$extra} is in config/blocks.php but not in the manifest";
}

foreach ($manifest->blocks() as $name => $block) {
    $mode = $config->mode($name);
    if ($mode === null) {
        continue;
    }
    $php = $block['php'];
    $blockFindings = $php !== null ? ($findings[$php] ?? []) : [];

    if ($php === null && in_array($mode, [BlockConfig::MODE_DYNAMIC, BlockConfig::MODE_CORE_RENDER], true)) {
        $failures[] = "{$name} is {$mode} but 11.9 ships no PHP for it";
    }
    if ($php !== null && $mode === BlockConfig::MODE_STATIC) {
        $failures[] = "{$name} is static but 11.9 ships {$php} (its render callback would be lost)";
    }
    if ($blockFindings !== [] && $mode === BlockConfig::MODE_DYNAMIC) {
        $failures[] = "{$name} is dynamic but its 11.9 PHP has findings:\n    " . implode("\n    ", $blockFindings);
    }
    if ($blockFindings === [] && in_array($mode, BlockConfig::CURATED_MODES, true) && $php !== null) {
        $notes[] = "{$name} is {$mode} with a clean file — reason on record: "
            . ($config->entries()[$name]['reason'] ?? '(none)');
    }
}

$dynamic = count(array_filter($config->entries(), static fn(array $rule): bool => $rule['mode'] === BlockConfig::MODE_DYNAMIC));
printf(
    "Audited %d blocks (%d with 11.9 PHP, %d served by it); %d file(s) with findings.\n",
    count($manifest->blocks()),
    count(array_filter($manifest->blocks(), static fn(array $b): bool => $b['php'] !== null)),
    $dynamic,
    count($findings)
);
foreach ($findings as $file => $messages) {
    echo "  {$file}\n    " . implode("\n    ", $messages) . "\n";
}
foreach ($notes as $note) {
    echo "note: {$note}\n";
}
if ($failures !== []) {
    echo "\nFAIL:\n";
    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }
    exit(1);
}
echo "config/blocks.php answers every finding.\n";
