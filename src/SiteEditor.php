<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

/**
 * Bridges site-editor.php between current core and the 18.5 edit-site bundle.
 *
 * Core's screen still boots the editor with
 * `wp.editSite.initializeEditor( 'site-editor', settings )`, exactly the entry
 * point 18.5 exports, and every package the bundle depends on is a handle
 * ScriptOverrides already repoints. What changed underneath is the URL scheme
 * (WordPress 6.8's `?p=/template`), which the client-side shim translates,
 * and what core offers classic themes on this screen, which 6.6's rules
 * restore.
 */
final class SiteEditor
{
    public const SHIM = 'site-editor-compat.js';

    /** The view classic themes are sent to: the one 18.5 can serve them. */
    public const CLASSIC_ROUTE = 'site-editor.php?p=/pattern';

    public static function register(): void
    {
        add_action('load-site-editor.php', [self::class, 'guardClassicTheme']);
        add_action('load-site-editor.php', [self::class, 'bridgeUrls']);
        add_action('admin_menu', [self::class, 'classicThemeMenu'], 20);
    }

    /**
     * Rewrites core's `?p=…` URLs into the query args the 18.5 router reads,
     * before that router's bundle snapshots window.location. wp-router also
     * loads on the post editor (through wp-core-commands), hence the screen
     * hook rather than a ScriptOverrides shim.
     */
    public static function bridgeUrls(): void
    {
        wp_add_inline_script('wp-router', ScriptOverrides::shimScript(self::SHIM), 'before');
    }

    /**
     * WordPress 6.6 let a classic theme into the Site Editor for patterns, and
     * for template parts when the theme declares `block-template-parts`;
     * anything else was refused. 7.x opens the whole screen to classic themes
     * for its style book, which the 18.5 bundle does not have — so every other
     * route goes to the patterns view instead of a half-empty editor.
     */
    public static function guardClassicTheme(): void
    {
        if (wp_is_block_theme()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
        $redirect = self::classicThemeRedirect($_GET, current_theme_supports('block-template-parts'));
        if ($redirect === null) {
            return;
        }

        wp_safe_redirect(admin_url($redirect));
        exit;
    }

    /**
     * Pure helper: where a classic theme's request should go, or null when the
     * requested view is one 6.6 allowed. Reads both URL forms, core's `p` and
     * the 18.5 router's `postType`, since both reach this screen.
     *
     * @param array<mixed> $query
     */
    public static function classicThemeRedirect(array $query, bool $supportsTemplateParts): ?string
    {
        $postType = is_string($query['postType'] ?? null) ? $query['postType'] : '';
        $p = is_string($query['p'] ?? null) ? $query['p'] : '';

        $templateParts = $postType === 'wp_template_part' || str_starts_with($p, '/wp_template_part/');
        if ($templateParts) {
            return $supportsTemplateParts ? null : self::CLASSIC_ROUTE;
        }

        $patterns = $postType === 'wp_block' || $p === '/pattern' || str_starts_with($p, '/wp_block/');

        return $patterns ? null : self::CLASSIC_ROUTE;
    }

    /**
     * 7.x labels a classic theme's Appearance entry "Design" (its style book)
     * or "Patterns"; 6.6 had one entry, Patterns, pointing at the view the
     * guard above admits. Block themes keep core's "Editor" entry untouched.
     */
    public static function classicThemeMenu(): void
    {
        global $submenu;

        if (wp_is_block_theme() || !is_array($submenu) || !isset($submenu['themes.php'][6])) {
            return;
        }

        $submenu['themes.php'][6] = [
            _x('Patterns', 'patterns menu item', 'gutenberg-downgrade'),
            'edit_theme_options',
            self::CLASSIC_ROUTE,
        ];
    }
}
