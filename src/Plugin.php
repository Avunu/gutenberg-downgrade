<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

/**
 * Bootstrap: wires every layer of the downgrade. Kept deliberately small.
 */
final class Plugin
{
    public static function init(): void
    {
        // The real Gutenberg plugin swaps the same handles and blocks; two
        // plugins fighting over them would be worse than either alone.
        if (defined('GUTENBERG_VERSION')) {
            add_action('admin_notices', [Admin::class, 'conflictNotice']);
            return;
        }

        if (!Assets::isInstalled()) {
            add_action('admin_notices', [Admin::class, 'missingAssetsNotice']);
            return;
        }

        add_action('admin_notices', [Admin::class, 'blockThemeNotice']);

        if (!Runtime::isActive()) {
            return;
        }

        $manifest = Assets::manifest();
        $config = BlockConfig::load();

        CoreNeutralizer::register();
        ScriptOverrides::register($manifest, $config);
        StyleOverrides::register();
        BlockRegistry::register($manifest, $config);
        EditorSettings::register();
        Translations::register();
        RestCompat::register();
    }
}
