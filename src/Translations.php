<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

/**
 * Best-effort translations for the overridden bundles.
 *
 * load_script_textdomain() keys a script's JSON translation file by the md5
 * of its path relative to the plugins directory, so bundles served from this
 * plugin never match the core language packs. Point them at the file core
 * would have used for the same package; strings that changed since 18.5 stay
 * untranslated.
 */
final class Translations
{
    public static function register(): void
    {
        add_filter('load_script_translation_file', [self::class, 'filter'], 10, 3);
    }

    /**
     * @param string|false $file
     * @return string|false
     */
    public static function filter(string|false $file, string $handle, string $domain): string|false
    {
        if ($domain !== 'default' || !str_starts_with($handle, 'wp-')) {
            return $file;
        }

        $packages = Assets::manifest()->packages();
        if (!isset($packages[$handle])) {
            return $file;
        }

        $candidate = self::corePackTranslationFile($handle, determine_locale());

        return is_readable($candidate) ? $candidate : $file;
    }

    /**
     * The path core's language pack uses for `wp-<package>`.
     */
    public static function corePackTranslationFile(string $handle, string $locale): string
    {
        $package = substr($handle, 3);

        return WP_LANG_DIR . '/' . $locale . '-' . md5('wp-includes/js/dist/' . $package . '.min.js') . '.json';
    }
}
