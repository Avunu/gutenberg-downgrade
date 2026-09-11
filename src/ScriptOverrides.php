<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

use WP_Scripts;

/**
 * Points every `wp-*` package script (and React) at the vendored 18.5 build.
 *
 * Port of Gutenberg 18.5's gutenberg_override_script(): the registered
 * _WP_Dependency is mutated in place rather than deregistered, so the inline
 * scripts core attached (api-fetch root URL and nonce, wp-data persistence,
 * wp-date settings, the wp-editor/oldEditor fusion) survive.
 *
 * Idempotent on purpose: WP_Scripts fires `wp_default_scripts` from its
 * constructor AND again at init@0 when it was instantiated before init, and
 * _wp_get_iframed_editor_assets() builds a second instance sharing the same
 * dependency objects.
 */
final class ScriptOverrides
{
    /** After core's wp_default_packages() at 10. */
    public const PRIORITY = 20;

    /** Dependencies build tools cannot detect (the union of what core and 18.5 hard-code). */
    private const MANUAL_DEPS = [
        'wp-block-library' => ['editor'],
        'wp-edit-post'     => ['media-models', 'media-views', 'postbox', 'wp-dom-ready'],
        'wp-edit-site'     => ['wp-dom-ready'],
        'wp-preferences'   => ['wp-preferences-persistence'],
    ];

    /**
     * set_translations() on these exhausts memory: wp-i18n would depend on
     * itself, and wp-polyfill/wp-hooks are its own dependencies (core #46089).
     */
    private const NO_TRANSLATIONS = ['wp-i18n', 'wp-polyfill', 'wp-hooks'];

    public const REACT_VERSION = '18.3.1';

    private static ?Manifest $manifest = null;
    private static ?BlockConfig $config = null;

    public static function register(Manifest $manifest, BlockConfig $config): void
    {
        self::$manifest = $manifest;
        self::$config = $config;

        add_action('wp_default_scripts', [self::class, 'apply'], self::PRIORITY);
        // Early enough to precede core's own wp-blocks inline scripts on that hook.
        add_action('enqueue_block_editor_assets', [self::class, 'exposeHiddenBlocks'], 5);
    }

    public static function apply(WP_Scripts $scripts): void
    {
        $manifest = self::$manifest ?? Assets::manifest();

        self::overrideVendor($scripts, $manifest);
        self::overridePackages($scripts, $manifest);
        self::injectCompatScript($scripts);
    }

    /**
     * The handles this request serves from the vendored build: every manifest
     * package core has registered too. (Packages core registers only as script
     * modules, such as wp-interactivity, are left out — nothing in core can
     * enqueue them as classic scripts.)
     *
     * @return list<string>
     */
    public static function overriddenHandles(WP_Scripts $scripts, Manifest $manifest): array
    {
        $handles = [];
        foreach (array_keys($manifest->packages()) as $handle) {
            if (isset($scripts->registered[$handle])) {
                $handles[] = $handle;
            }
        }

        return $handles;
    }

    /**
     * Contents of assets/js/compat.js, inlined after wp-blocks.
     */
    public static function compatScript(): string
    {
        static $script = null;

        if ($script === null) {
            $contents = file_get_contents(GUTENBERG_DOWNGRADE_DIR . 'assets/js/compat.js');
            $script = is_string($contents) ? trim($contents) : '';
        }

        return $script;
    }

    /**
     * Tells the shim which blocks to keep out of the inserter. Computed on
     * enqueue_block_editor_assets rather than wp_default_scripts because the
     * block registry may not exist yet when scripts are first registered.
     * A no-op while config/blocks.php hides nothing.
     */
    public static function exposeHiddenBlocks(): void
    {
        $hidden = (self::$config ?? BlockConfig::load())->hiddenFromInserter();
        if ($hidden === []) {
            return;
        }

        wp_add_inline_script(
            'wp-blocks',
            'window.gutenbergDowngradeHiddenBlocks = ' . (string) wp_json_encode($hidden, JSON_UNESCAPED_SLASHES) . ';',
            'before'
        );
    }

    /**
     * Port of 18.5's gutenberg_register_vendor_scripts(): React, ReactDOM and
     * the JSX runtime the bundles were compiled against.
     */
    private static function overrideVendor(WP_Scripts $scripts, Manifest $manifest): void
    {
        $key = Assets::scriptDebug() ? 'dev' : 'prod';

        self::override($scripts, 'react', Assets::url($manifest->vendor('react')[$key]), ['wp-polyfill'], self::REACT_VERSION);
        self::override($scripts, 'react-dom', Assets::url($manifest->vendor('react-dom')[$key]), ['react'], self::REACT_VERSION);
        self::override($scripts, 'react-jsx-runtime', Assets::url($manifest->vendor('react-jsx-runtime')[$key]), ['react'], self::REACT_VERSION);
    }

    private static function overridePackages(WP_Scripts $scripts, Manifest $manifest): void
    {
        $debug = Assets::scriptDebug();

        foreach ($manifest->packages() as $handle => $package) {
            if (!isset($scripts->registered[$handle])) {
                continue;
            }

            $file = $debug && $package['jsDebug'] !== null ? $package['jsDebug'] : $package['js'];
            $deps = array_values(array_unique(array_merge($package['deps'], self::MANUAL_DEPS[$handle] ?? [])));

            self::override($scripts, $handle, Assets::url($file), $deps, $package['version']);

            if (!in_array($handle, self::NO_TRANSLATIONS, true)) {
                $scripts->set_translations($handle, 'default');
            }
        }
    }

    /**
     * @param list<string> $deps
     */
    private static function override(WP_Scripts $scripts, string $handle, string $src, array $deps, string $ver): void
    {
        $script = $scripts->registered[$handle] ?? null;

        if ($script === null) {
            $scripts->add($handle, $src, $deps, $ver, 1);
            return;
        }

        // See _WP_Dependency::__construct(): these are the constructor's slots.
        $script->src = $src;
        $script->deps = $deps;
        $script->ver = $ver;
        $script->args = 1;

        // Group 1 = print in the footer; wp_register_script() only sets it
        // when in_footer is true, so clear first to mirror that exactly.
        unset($script->extra['group']);
        $script->add_data('group', 1);

        // 7.x attaches script-module imports (`@wordpress/route` etc.) that
        // the import map would otherwise emit for bundles that never use them.
        unset($script->extra['module_dependencies']);
    }

    private static function injectCompatScript(WP_Scripts $scripts): void
    {
        $blocks = $scripts->registered['wp-blocks'] ?? null;
        $shim = self::compatScript();
        if ($blocks === null || $shim === '') {
            return;
        }

        // The same _WP_Dependency can be visited by several WP_Scripts
        // instances; only ever attach the shim once.
        $after = $blocks->extra['after'] ?? [];
        if (is_array($after) && in_array($shim, $after, true)) {
            return;
        }

        $scripts->add_inline_script('wp-blocks', $shim, 'after');
    }
}
