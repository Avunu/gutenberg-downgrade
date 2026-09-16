# Gutenberg Downgrade

A WordPress plugin that loads the **Gutenberg 18.5** block editor — the WordPress 6.6 series — on current WordPress releases (7.1+).

It exists for sites that run a page builder which clashes with the modern block editor but still need the latest WordPress for security fixes and performance. It ships one fixed editor version; there is nothing to choose or configure in wp-admin.

## What it does

The Gutenberg plugin has always worked by swapping WordPress core's block-editor packages for its own build. This plugin does the same thing in reverse, with the 18.5.0 release build bundled in `assets/gutenberg/`, and nothing else from the old plugin (its `lib/` — the WordPress 6.6 compatibility layer, global styles, experiments — is exactly what fights modern core, so it is not shipped).

On wp-admin screens, REST requests and admin-ajax it:

- **repoints every `wp-*` package script** core registered at the vendored 18.5 bundles, keeping core's inline scripts (API root and nonce, date settings, editor fusion) intact, and serves the **React 18.3.1** build those bundles were compiled against;
- **re-registers the package stylesheets** (`wp-edit-post`, `wp-components`, `wp-block-library`, …) with the 18.5 dependency graph;
- **re-registers the core blocks server-side from the 18.5 `block.json`** files, so the editor receives the block definitions its client was built for rather than core's newer attributes, supports and selectors. Dynamic blocks run their 18.5 render callbacks where the audit found them clean, and core's where it did not (`config/blocks.php` records every decision and why);
- **switches off** the parts of core that assume its own editor (the admin-wide command palette, script modules, client-side media processing, the pattern directory, and the bundled patterns that use blocks 18.5 does not have);
- **bridges the Site Editor**: core's `site-editor.php` still boots through `wp.editSite.initializeEditor()`, so the 18.5 `edit-site` bundle runs the whole screen — templates, template parts, patterns, styles, navigation, pages. A shim translates the URL scheme WordPress 6.8 introduced (`?p=/template`, `?p=/wp_template/…&canvas=edit`, and the 302 core issues for the older form) into the `postType`/`postId` query the 18.5 router reads, so every link core emits and every bookmark keeps working. Classic themes get what 6.6 gave them here: the patterns view (and template parts when the theme opts in).

The public front end, cron and WP-CLI are untouched: they keep WordPress core's block library, CSS and view scripts. The Font Library and Connectors screens (7.x-only, built on packages 18.5 never had) are excluded.

## Requirements

- WordPress 7.1+
- PHP 8.3+
- The Gutenberg plugin must not be active (this plugin stands down if it is).

## Installation

Download `gutenberg-downgrade.zip` from the [latest release](https://github.com/Avunu/gutenberg-downgrade/releases/latest) and install it through Plugins → Add New → Upload. The zip bundles everything (Composer dependencies and the Gutenberg build). Installed sites self-update from GitHub releases.

A git checkout is not a working plugin: `vendor/` and `assets/gutenberg/` are produced by the build (see Development).

## Configuration

Optional constants in `wp-config.php`:

```php
// Kill switch: keep the plugin active but load core's editor everywhere.
define('GUTENBERG_DOWNGRADE_DISABLE', true);

// Replace the wp-admin screens ($pagenow values) that keep core's editor stack.
// Default: font-library.php, options-connectors.php
define('GUTENBERG_DOWNGRADE_BYPASS_PAGES', ['font-library.php']);
```

Filters: `gutenberg_downgrade_active` (bool), `gutenberg_downgrade_bypass_pages` (array), `gutenberg_downgrade_editor_settings` (the final editor settings).

## Known limitations

- **Content authored with a newer editor.** Posts saved by WordPress 6.7+/7.x that use blocks or markup 18.5 never had (the 7.x navigation overlays, for instance) open with "This block contains unexpected or invalid content" in the 18.5 editor. This is inherent to running an older editor; the plugin targets sites that stay on it.
- **Translations.** The bundles are served from the plugin, so core's language packs do not match them by path. The plugin points them at the file core would have used for the same package; strings that changed since 18.5 stay untranslated.
- **Third-party editor scripts built for current core** that use package exports added after Gutenberg 18.5 will not work while the downgrade is active. Scripts built for the WordPress 6.1–6.6 packages are the target. `assets/js/block-editor-compat.js` backports the ones whose absence breaks more than the calling plugin — a missing export reached from an `editor.BlockEdit` filter crashes _every_ block in the post, not just that plugin's panel (Secure Custom Fields and `useBlockBindingsUtils` are the case this exists for), plus renames where the old export is the same object (`LinkControl`, stabilised from `__experimentalLinkControl` in 6.8, which Rank Math's link toolbar reads at load time).

## Development

Everything is driven by Nix. Enter the shell with `nix develop` (or direnv): it provides PHP 8.4, Composer, Node 22, PHPStan, PHPCS, the git hooks (via prek), and copies the Gutenberg build into `assets/gutenberg/` (gitignored — `nix run .#sync-assets` refreshes it).

```sh
composer install && composer install --working-dir=tests/tools && npm ci
npm --prefix tests/playground ci

composer phpstan                       # level 8, WordPress-aware
composer phpcs                         # PSR-12
php tests/tools/vendor/bin/phpunit -c tests/phpunit-unit.xml
composer audit:blocks                  # the vendored 18.5 block PHP against WP 7.1 stubs + PHP 8.4
npm run check                          # oxfmt, oxlint, tsc (shim + playground harness)

nix build .#default                    # the plugin directory
nix build .#zip                        # result/gutenberg-downgrade.zip
nix flake check                        # phpstan, phpcs, unit, block-audit, manifest, plugin-header
```

The runtime tests boot WordPress in [Playground](https://wordpress.github.io/wordpress-playground/) (WebAssembly PHP, no services) with the built plugin mounted and a block theme active (a classic theme is switched in for the widgets and customizer passes):

```sh
nix build .#default                    # the harness mounts result/
npm run test:e2e                       # PHP-level assertions: handles, registry, settings, every dynamic block renders
CHROME_PATH=$(command -v google-chrome-stable) npm run test:browser   # headless Chrome: site editor + post editor round trips, no console errors
WP_VERSION=7.1 npm run test            # or latest (default), nightly
```

### Where the Gutenberg version is pinned

`nix/gutenberg-release.nix` — the wordpress.org release zip, hash-pinned. It is deliberately not a Composer or npm dependency (the `wp-*` bundles only exist in the plugin zip), so no dependency bot will ever bump it. Changing it means re-auditing `config/blocks.php`.

## Releasing

Conventional Commits on `main` feed [Release Please](https://github.com/googleapis/release-please). Merging its release PR bumps `composer.json`, the plugin header and `CHANGELOG.md` and creates a draft release; the full test suite then runs against that commit and, only if it passes, the Nix-built zip is attached and the release published (which creates the tag). The zip is the asset installed sites update from — the update checker requires it, so a draft, or a release without one, is never offered.

## License

GPL-2.0-or-later. The bundled Gutenberg build is © the WordPress contributors, GPL-2.0-or-later (`assets/gutenberg/SOURCE.txt`).
