// Inlined verbatim BEFORE the `wp-router` bundle on site-editor.php by
// SiteEditor (it is not a separately enqueued file). It has to run that early:
// the 18.5 router snapshots window.location the moment its bundle evaluates.
//
// Plain ES2017: no modules, no classes, no build step. Type-checked by tsc via
// the root tsconfig.json.
(function () {
	"use strict";

	// WordPress 6.8 moved the Site Editor to `?p=/template`, `?p=/styles`,
	// `?p=/wp_template/<id>&canvas=edit` … and every link core emits (the
	// Appearance menu, template edit links, the dashboard, theme-editor.php)
	// uses that form; site-editor.php even 302s the old form onto it. The 18.5
	// editor addresses the same views the way 6.6 did: `postType`/`postId`
	// query args, `path=/wp_global_styles` for styles. Rewrite the URL in
	// place so the router sees what it understands, and the address bar keeps
	// working as a bookmark.
	var params = new URLSearchParams(window.location.search);
	var p = params.get("p");
	if (p === null) {
		return;
	}
	params.delete("p");

	/**
	 * The list views core names differently from their post type.
	 *
	 * @type {Record<string, string | null>}
	 */
	var lists = {
		"/": null,
		"/template": "wp_template",
		"/pattern": "wp_block",
		"/navigation": "wp_navigation",
		"/page": "page",
	};

	var match;
	if (p === "/styles") {
		params.set("path", "/wp_global_styles");
	} else if (Object.prototype.hasOwnProperty.call(lists, p)) {
		// `?p=/pattern&postType=wp_template_part` is the template parts tab.
		if (lists[p] !== null && !params.has("postType")) {
			params.set("postType", /** @type {string} */ (lists[p]));
		}
	} else if ((match = /^\/([a-z_]+)\/(.+)$/.exec(p)) !== null) {
		// `/wp_template/twentytwentyfour//home`, `/page/12`, `/wp_navigation/7`.
		params.set("postType", match[1]);
		params.set("postId", match[2]);
	} else if ((match = /^\/([a-z_]+)$/.exec(p)) !== null) {
		params.set("postType", match[1]);
	}

	var query = params.toString();
	window.history.replaceState(
		window.history.state,
		"",
		window.location.pathname + (query === "" ? "" : "?" + query) + window.location.hash,
	);
})();
