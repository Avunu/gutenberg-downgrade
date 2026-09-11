<?php

/**
 * Which server-side implementation each Gutenberg 18.5.0 block gets.
 *
 * Every block in assets/gutenberg/manifest.php must appear here exactly once
 * (tests/Unit/BlockConfigTest.php and the `block-audit` Nix check enforce it,
 * so adding a block to the release, or curating one out, is always a visible
 * change).
 *
 *   static       Register from the 18.5 block.json with no render callback
 *                (18.5 had no PHP for it; 7.x may — e.g. paragraph, quote — but
 *                the 18.5 client saves static HTML and expects its own
 *                attribute shapes).
 *   dynamic      Load the 18.5 PHP file; it registers the block itself at
 *                init@20 with its gutenberg_render_block_core_* callback.
 *   core-render  Register from the 18.5 block.json (so the editor gets the
 *                18.5 attributes/supports) but keep core's render callback,
 *                because the 18.5 PHP misbehaves on modern core.
 *   skip         Leave whatever core registered untouched.
 *
 * Reasons are mandatory for core-render/skip; they are what the audit check
 * verifies has not silently become unnecessary.
 */

declare(strict_types=1);

return [
    'blocks' => [
        // Static blocks (no PHP in the 18.5 release).
        'core/audio'         => 'static',
        'core/button'        => 'static',
        'core/buttons'       => 'static',
        'core/code'          => 'static',
        'core/column'        => 'static',
        'core/columns'       => 'static',
        'core/details'       => 'static',
        'core/embed'         => 'static',
        'core/freeform'      => 'static',
        'core/group'         => 'static',
        'core/html'          => 'static',
        'core/list-item'     => 'static',
        'core/missing'       => 'static',
        'core/more'          => 'static',
        'core/nextpage'      => 'static',
        'core/paragraph'     => 'static',
        'core/preformatted'  => 'static',
        'core/pullquote'     => 'static',
        'core/quote'         => 'static',
        'core/separator'     => 'static',
        'core/social-links'  => 'static',
        'core/spacer'        => 'static',
        'core/table'         => 'static',
        'core/text-columns'  => 'static',
        'core/verse'         => 'static',
        'core/video'         => 'static',

        // Dynamic blocks whose 18.5 PHP is clean on current core (audited by
        // the `block-audit` Nix check: no undefined functions beyond the
        // src/shims.php delegations, no PHP 8.4 deprecations).
        'core/archives'                    => 'dynamic',
        'core/avatar'                      => 'dynamic',
        'core/block'                       => 'dynamic',
        'core/calendar'                    => 'dynamic',
        'core/categories'                  => 'dynamic',
        'core/comment-author-avatar'       => 'dynamic',
        'core/comment-author-name'         => 'dynamic',
        'core/comment-content'             => 'dynamic',
        'core/comment-date'                => 'dynamic',
        'core/comment-edit-link'           => 'dynamic',
        'core/comment-reply-link'          => 'dynamic',
        'core/comment-template'            => 'dynamic',
        'core/comments'                    => 'dynamic',
        'core/comments-pagination'         => 'dynamic',
        'core/comments-pagination-next'    => 'dynamic',
        'core/comments-pagination-numbers' => 'dynamic',
        'core/comments-pagination-previous' => 'dynamic',
        'core/comments-title'              => 'dynamic',
        'core/cover'                       => 'dynamic',
        'core/file'                        => 'dynamic',
        'core/footnotes'                   => 'dynamic',
        'core/gallery'                     => 'dynamic',
        'core/heading'                     => 'dynamic',
        'core/home-link'                   => 'dynamic',
        'core/image'                       => 'dynamic',
        'core/latest-comments'             => 'dynamic',
        'core/latest-posts'                => 'dynamic',
        'core/legacy-widget'               => 'dynamic',
        'core/list'                        => 'dynamic',
        'core/loginout'                    => 'dynamic',
        'core/media-text'                  => 'dynamic',
        'core/navigation'                  => 'dynamic',
        'core/navigation-submenu'          => 'dynamic',
        'core/page-list'                   => 'dynamic',
        'core/page-list-item'              => 'dynamic',
        'core/pattern'                     => 'dynamic',
        'core/post-author'                 => 'dynamic',
        'core/post-author-biography'       => 'dynamic',
        'core/post-author-name'            => 'dynamic',
        'core/post-comment'                => 'dynamic',
        'core/post-comments-count'         => 'dynamic',
        'core/post-comments-form'          => 'dynamic',
        'core/post-comments-link'          => 'dynamic',
        'core/post-content'                => 'dynamic',
        'core/post-date'                   => 'dynamic',
        'core/post-excerpt'                => 'dynamic',
        'core/post-featured-image'         => 'dynamic',
        'core/post-navigation-link'        => 'dynamic',
        'core/post-template'               => 'dynamic',
        'core/post-terms'                  => 'dynamic',
        'core/post-title'                  => 'dynamic',
        'core/query'                       => 'dynamic',
        'core/query-no-results'            => 'dynamic',
        'core/query-pagination'            => 'dynamic',
        'core/query-pagination-next'       => 'dynamic',
        'core/query-pagination-numbers'    => 'dynamic',
        'core/query-pagination-previous'   => 'dynamic',
        'core/query-title'                 => 'dynamic',
        'core/read-more'                   => 'dynamic',
        'core/rss'                         => 'dynamic',
        'core/search'                      => 'dynamic',
        'core/shortcode'                   => 'dynamic',
        'core/site-tagline'                => 'dynamic',
        'core/site-title'                  => 'dynamic',
        'core/social-link'                 => 'dynamic',
        'core/tag-cloud'                   => 'dynamic',
        'core/template-part'               => 'dynamic',
        'core/term-description'            => 'dynamic',
        'core/widget-group'                => 'dynamic',

        // Curated exceptions.
        'core/site-logo'       => [
            'mode'   => 'core-render',
            'reason' => 'The 18.5 file re-adds the option/theme-mod hooks core already registers, '
                . 'double-syncing site_logo <-> custom_logo.',
        ],
        'core/navigation-link' => [
            'mode'   => 'core-render',
            'reason' => 'The 18.5 file hooks get_block_type_variations to append the same post-type and '
                . 'taxonomy variations core already appends; loading it lists every variation twice '
                . 'in the inserter.',
        ],

        // Experimental in 18.5: the client registers them only behind
        // window.__experimentalEnable* flags the plugin never sets, and
        // post-time-to-read needs wp_word_count() from lib/experimental/.
        'core/form'                         => [
            'mode'   => 'skip',
            'reason' => 'Experimental (window.__experimentalEnableFormBlocks).',
        ],
        'core/form-input'                   => [
            'mode'   => 'skip',
            'reason' => 'Experimental (window.__experimentalEnableFormBlocks).',
        ],
        'core/form-submission-notification' => [
            'mode'   => 'skip',
            'reason' => 'Experimental (window.__experimentalEnableFormBlocks).',
        ],
        'core/form-submit-button'           => [
            'mode'   => 'skip',
            'reason' => 'Experimental (window.__experimentalEnableFormBlocks).',
        ],
        'core/post-time-to-read'            => [
            'mode'   => 'skip',
            'reason' => 'Its PHP calls wp_word_count() from the plugin\'s lib/experimental/, which core does not '
                . 'have; core ships the block itself, which backs the client\'s registration.',
        ],
        'core/table-of-contents'            => [
            'mode'   => 'skip',
            'reason' => 'Experimental, but the 18.5 client registers it; core has no server-side definition, '
                . 'so it would never render on the front end. Hidden from the inserter instead.',
        ],
    ],

    // Blocks the 18.5 client registers unconditionally but that cannot work on
    // current core (no server-side block for the front end). Kept out of the
    // inserter by assets/js/compat.js; existing content still renders.
    'hiddenFromInserter' => [
        'core/table-of-contents',
    ],
];
