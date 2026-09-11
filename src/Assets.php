<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

/**
 * Locates the vendored Gutenberg build (assets/gutenberg/) on disk and by URL.
 */
final class Assets
{
    public const RELATIVE_DIR = 'assets/gutenberg/';

    private static ?Manifest $manifest = null;

    public static function dir(): string
    {
        return GUTENBERG_DOWNGRADE_DIR . self::RELATIVE_DIR;
    }

    public static function path(string $relative): string
    {
        return self::dir() . ltrim($relative, '/');
    }

    public static function url(string $relative): string
    {
        return plugins_url(self::RELATIVE_DIR . ltrim($relative, '/'), GUTENBERG_DOWNGRADE_FILE);
    }

    /**
     * True when the release build has been copied in. The git checkout has no
     * assets/gutenberg/ — it comes from `nix run .#sync-assets` or the built zip.
     */
    public static function isInstalled(): bool
    {
        return is_file(self::path('manifest.php'));
    }

    public static function manifest(): Manifest
    {
        return self::$manifest ??= Manifest::load(self::path('manifest.php'));
    }

    /**
     * Whether to serve the unminified bundles. Mirrors core's own switch; the
     * dependency/version data always comes from the .min asset file because
     * the release only ships that one.
     */
    public static function scriptDebug(): bool
    {
        return defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
    }

    /**
     * Cache-busting fallback for assets without their own content hash.
     */
    public static function version(): string
    {
        return self::manifest()->gutenbergVersion();
    }

    /**
     * Test seam: forget the cached manifest.
     */
    public static function reset(): void
    {
        self::$manifest = null;
    }
}
