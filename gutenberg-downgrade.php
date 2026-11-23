<?php

/**
 * Plugin Name:       Gutenberg Downgrade
 * Plugin URI:        https://github.com/Avunu/gutenberg-downgrade
 * Description:       Loads the Gutenberg 18.5 block editor (the WordPress 6.6 series) on current WordPress releases, for sites whose page builder clashes with the modern editor. Configure via wp-config.php — no admin settings.
 * x-release-please-start-version
 * Version:           1.2.0
 * x-release-please-end
 * Requires PHP:      8.3
 * Requires at least: 7.1
 * Tested up to:      7.1
 * Author:            Avunu
 * Author URI:        https://avunu.io/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gutenberg-downgrade
 * Update URI:        https://github.com/Avunu/gutenberg-downgrade
 *
 * ============================================================================
 * WHAT IT DOES
 * ============================================================================
 * On wp-admin screens, REST requests and admin-ajax it serves the `wp-*`
 * package scripts and styles, React 18.3.1 and the core-block definitions from
 * the bundled Gutenberg 18.5.0 build instead of WordPress core's — the post
 * editor, the Site Editor, widgets and the customizer alike. The public front
 * end, cron and WP-CLI keep core's block library untouched. The Font Library
 * and Connectors screens (7.x-only) are excluded.
 *
 * ============================================================================
 * CONFIGURATION (wp-config.php, all optional)
 * ============================================================================
 *   define('GUTENBERG_DOWNGRADE_DISABLE', true);
 *       Kill switch: keep the plugin active but load core's editor everywhere.
 *
 *   define('GUTENBERG_DOWNGRADE_BYPASS_PAGES', ['font-library.php']);
 *       Replace the list of wp-admin screens ($pagenow values) that keep core's
 *       editor stack. Default: font-library.php, options-connectors.php.
 *
 * Filters: gutenberg_downgrade_active, gutenberg_downgrade_bypass_pages,
 * gutenberg_downgrade_editor_settings.
 */

declare(strict_types=1);

defined('WPINC') || exit;

define('GUTENBERG_DOWNGRADE_FILE', __FILE__);
define('GUTENBERG_DOWNGRADE_DIR', __DIR__ . '/');

// Dependencies. The release zip bundles its own vendor/. Under Composer (a
// Bedrock site, for example) the plugin directory has no vendor/ and the
// project's autoloader, loaded before WordPress, already provides the classes.
$gutenbergDowngradeAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($gutenbergDowngradeAutoload)) {
    require_once $gutenbergDowngradeAutoload;
}

if (!class_exists(\GutenbergDowngrade\Plugin::class)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>Gutenberg Downgrade:</strong> dependencies are missing — install the built release zip, or require <code>avunu/gutenberg-downgrade</code> with Composer.</p></div>';
    });
    return;
}

// Self-update from GitHub releases, for installs of the release zip only. The
// built zip attached to each release bundles vendor/ and assets/gutenberg/, so
// end users never need Composer or Nix. A Composer-managed install is updated
// by Composer, and a site where file modifications are off
// (DISALLOW_FILE_MODS) cannot install updates at all, so neither loads it.
if (is_file($gutenbergDowngradeAutoload) && wp_is_file_mod_allowed('gutenberg_downgrade_update_checker')) {
    require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

    $gutenbergDowngradeUpdateChecker = \YahnisElsts\PluginUpdateChecker\v6\PucFactory::buildUpdateChecker(
        'https://github.com/Avunu/gutenberg-downgrade/',
        __FILE__,
        'gutenberg-downgrade'
    );
    // Download the built release asset, never GitHub's source tarball (which lacks
    // vendor/ and assets/gutenberg/). REQUIRE, not the default PREFER: a release
    // that has no zip yet — the suite failed after Release Please tagged it — must
    // offer no update at all rather than fall back to the tarball and leave the
    // site with a plugin that cannot load.
    // The constant is read off the instance: Vcs\Api only exists under the
    // version-suffixed (v6pX) namespace, which moves on every minor release.
    $gutenbergDowngradeVcsApi = $gutenbergDowngradeUpdateChecker->getVcsApi();
    $gutenbergDowngradeVcsApi->enableReleaseAssets(
        '/gutenberg-downgrade\.zip$/',
        $gutenbergDowngradeVcsApi::REQUIRE_RELEASE_ASSETS
    );
}
unset($gutenbergDowngradeAutoload);

add_action('plugins_loaded', [\GutenbergDowngrade\Plugin::class, 'init']);
