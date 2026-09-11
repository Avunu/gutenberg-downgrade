<?php

/**
 * Which server-side implementation each Gutenberg 11.9.1 block gets.
 *
 * Every block in assets/gutenberg/manifest.php must appear here exactly once
 * (tests/Unit/BlockConfigTest.php and the `block-audit` Nix check enforce it,
 * so adding a block to the release, or curating one out, is always a visible
 * change).
 *
 *   static       Register from the 11.9 block.json with no render callback
 *                (11.9 had no PHP for it; 7.x may — e.g. paragraph, list — but
 *                the 11.9 client saves static HTML and expects apiVersion 2).
 *   dynamic      Load the 11.9 PHP file; it registers the block itself at
 *                init@20 with its gutenberg_render_block_core_* callback.
 *   core-render  Register from the 11.9 block.json (so the editor gets the
 *                11.9 attributes/supports) but keep core's render callback,
 *                because the 11.9 PHP misbehaves on modern core.
 *   skip         Leave whatever core registered untouched.
 *
 * Reasons are mandatory for core-render/skip; they are what the audit check
 * verifies has not silently become unnecessary.
 */

declare(strict_types=1);

return [
    'blocks' => [
        // Static blocks (no PHP in the 11.9 release).
        'core/audio'               => 'static',
        'core/button'              => 'static',
        'core/buttons'             => 'static',
        'core/code'                => 'static',
        'core/column'              => 'static',
        'core/columns'             => 'static',
        'core/comments-query-loop' => 'static',
        'core/cover'               => 'static',
        'core/embed'               => 'static',
        'core/freeform'            => 'static',
        'core/group'               => 'static',
        'core/heading'             => 'static',
        'core/html'                => 'static',
        'core/list'                => 'static',
        'core/media-text'          => 'static',
        'core/missing'             => 'static',
        'core/more'                => 'static',
        'core/nextpage'            => 'static',
        'core/paragraph'           => 'static',
        'core/preformatted'        => 'static',
        'core/pullquote'           => 'static',
        'core/quote'               => 'static',
        'core/separator'           => 'static',
        'core/social-links'        => 'static',
        'core/spacer'              => 'static',
        'core/table'               => 'static',
        'core/text-columns'        => 'static',
        'core/verse'               => 'static',
        'core/video'               => 'static',

        // Dynamic blocks whose 11.9 PHP is clean on current core (audited by
        // the `block-audit` Nix check: no undefined functions, no PHP 8.4
        // deprecations).
        'core/archives'                  => 'dynamic',
        'core/block'                     => 'dynamic',
        'core/calendar'                  => 'dynamic',
        'core/categories'                => 'dynamic',
        'core/comment-author-avatar'     => 'dynamic',
        'core/comment-author-name'       => 'dynamic',
        'core/comment-content'           => 'dynamic',
        'core/comment-date'              => 'dynamic',
        'core/comment-edit-link'         => 'dynamic',
        'core/comment-reply-link'        => 'dynamic',
        'core/comment-template'          => 'dynamic',
        'core/file'                      => 'dynamic',
        'core/gallery'                   => 'dynamic',
        'core/home-link'                 => 'dynamic',
        'core/image'                     => 'dynamic',
        'core/latest-comments'           => 'dynamic',
        'core/latest-posts'              => 'dynamic',
        'core/loginout'                  => 'dynamic',
        'core/navigation'                => 'dynamic',
        'core/navigation-area'           => 'dynamic',
        'core/navigation-link'           => 'dynamic',
        'core/navigation-submenu'        => 'dynamic',
        'core/page-list'                 => 'dynamic',
        'core/pattern'                   => 'dynamic',
        'core/post-author'               => 'dynamic',
        'core/post-comment'              => 'dynamic',
        'core/post-comments-count'       => 'dynamic',
        'core/post-comments-form'        => 'dynamic',
        'core/post-comments-link'        => 'dynamic',
        'core/post-content'              => 'dynamic',
        'core/post-date'                 => 'dynamic',
        'core/post-excerpt'              => 'dynamic',
        'core/post-featured-image'       => 'dynamic',
        'core/post-navigation-link'      => 'dynamic',
        'core/post-template'             => 'dynamic',
        'core/post-terms'                => 'dynamic',
        'core/post-title'                => 'dynamic',
        'core/query'                     => 'dynamic',
        'core/query-pagination'          => 'dynamic',
        'core/query-pagination-next'     => 'dynamic',
        'core/query-pagination-numbers'  => 'dynamic',
        'core/query-pagination-previous' => 'dynamic',
        'core/query-title'               => 'dynamic',
        'core/rss'                       => 'dynamic',
        'core/search'                    => 'dynamic',
        'core/shortcode'                 => 'dynamic',
        'core/site-tagline'              => 'dynamic',
        'core/site-title'                => 'dynamic',
        'core/social-link'               => 'dynamic',
        'core/tag-cloud'                 => 'dynamic',
        'core/term-description'          => 'dynamic',
        'core/widget-group'              => 'dynamic',

        // Curated exceptions.
        'core/site-logo'         => [
            'mode'   => 'core-render',
            'reason' => 'The 11.9 file re-adds six option/theme-mod hooks that core already registers, '
                . 'double-syncing site_logo <-> custom_logo.',
        ],
        'core/template-part'     => [
            'mode'   => 'core-render',
            'reason' => 'The 11.9 render callback calls _inject_theme_attribute_in_block_template_content(), '
                . 'deprecated since WordPress 6.4 (a notice on every render).',
        ],
        'core/legacy-widget'     => [
            'mode'   => 'core-render',
            'reason' => 'The 11.9 render callback needs gutenberg_get_widget_key()/gutenberg_get_widget_object() from '
                . "the plugin's lib/widgets-api.php; core's callback reads WP_Widget_Factory directly.",
        ],
        'core/post-comments'     => [
            'mode'   => 'skip',
            'reason' => 'Core re-registers this legacy block itself at init priority 21 '
                . '(register_legacy_post_comments_block), after anything a plugin does. Its title is '
                . 'backfilled from the 11.9 block.json so the client can still register it.',
        ],
        'core/table-of-contents' => [
            'mode'   => 'skip',
            'reason' => 'Experimental in 11.9 (never registered by the client) and its PHP uses '
                . 'utf8_decode(), deprecated since PHP 8.2.',
        ],
    ],

    // Blocks the 11.9 client registers unconditionally but that cannot work on
    // current core (no REST backend). Kept out of the inserter by
    // assets/js/compat.js; existing content still renders.
    'hiddenFromInserter' => [
        'core/navigation-area',
    ],
];
