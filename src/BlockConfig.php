<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

use RuntimeException;

/**
 * Typed view of config/blocks.php: the per-block server-side strategy.
 *
 * @phpstan-type BlockMode 'static'|'dynamic'|'core-render'|'skip'
 * @phpstan-type BlockRule array{mode: BlockMode, reason: ?string}
 */
final class BlockConfig
{
    public const MODE_STATIC = 'static';
    public const MODE_DYNAMIC = 'dynamic';
    public const MODE_CORE_RENDER = 'core-render';
    public const MODE_SKIP = 'skip';

    /** Modes that must carry a reason. */
    public const CURATED_MODES = [self::MODE_CORE_RENDER, self::MODE_SKIP];

    private const MODES = [self::MODE_STATIC, self::MODE_DYNAMIC, self::MODE_CORE_RENDER, self::MODE_SKIP];

    /**
     * @param array<string, BlockRule> $blocks
     * @param list<string> $hiddenFromInserter
     */
    private function __construct(
        private readonly array $blocks,
        private readonly array $hiddenFromInserter
    ) {
    }

    public static function load(?string $file = null): self
    {
        $file ??= GUTENBERG_DOWNGRADE_DIR . 'config/blocks.php';

        /** @var mixed $data */
        $data = require $file;

        return self::fromArray($data);
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !isset($data['blocks']) || !is_array($data['blocks'])) {
            throw new RuntimeException('config/blocks.php must return an array with a "blocks" key');
        }

        $blocks = [];
        foreach ($data['blocks'] as $name => $rule) {
            if (!is_string($name)) {
                throw new RuntimeException('config/blocks.php: block names must be strings');
            }
            $blocks[$name] = self::normaliseRule($name, $rule);
        }

        $hidden = [];
        foreach ($data['hiddenFromInserter'] ?? [] as $name) {
            if (!is_string($name)) {
                throw new RuntimeException('config/blocks.php: hiddenFromInserter must list block names');
            }
            $hidden[] = $name;
        }

        return new self($blocks, $hidden);
    }

    /**
     * @return array<string, BlockRule>
     */
    public function entries(): array
    {
        return $this->blocks;
    }

    /**
     * @return BlockMode|null Null when the block is not configured at all.
     */
    public function mode(string $block): ?string
    {
        return $this->blocks[$block]['mode'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function hiddenFromInserter(): array
    {
        return $this->hiddenFromInserter;
    }

    /**
     * @return BlockRule
     */
    private static function normaliseRule(string $name, mixed $rule): array
    {
        if (is_string($rule)) {
            $rule = ['mode' => $rule, 'reason' => null];
        }

        if (!is_array($rule) || !isset($rule['mode']) || !is_string($rule['mode'])) {
            throw new RuntimeException("config/blocks.php: {$name} needs a mode");
        }

        $mode = $rule['mode'];
        if (!in_array($mode, self::MODES, true)) {
            throw new RuntimeException("config/blocks.php: {$name} has unknown mode \"{$mode}\"");
        }

        $reason = $rule['reason'] ?? null;
        if ($reason !== null && !is_string($reason)) {
            throw new RuntimeException("config/blocks.php: {$name} reason must be a string");
        }

        if (in_array($mode, self::CURATED_MODES, true) && ($reason === null || trim($reason) === '')) {
            throw new RuntimeException("config/blocks.php: {$name} is {$mode} and must give a reason");
        }

        /** @var BlockMode $mode */
        return ['mode' => $mode, 'reason' => $reason];
    }
}
