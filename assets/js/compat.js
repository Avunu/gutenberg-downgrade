// Inlined verbatim after the `wp-blocks` bundle by ScriptOverrides (it is not
// a separately enqueued file). Everything here bridges what WordPress 7.x's
// PHP expects from the client and what the Gutenberg 18.5 bundles provide.
//
// Plain ES2017: no modules, no classes, no build step. Type-checked by tsc via
// the root tsconfig.json.
(function () {
	"use strict";

	var wp = window.wp;
	if (!wp || !wp.blocks) {
		return;
	}

	// wp-admin/edit-form-blocks.php (and the widgets/customizer screens) call
	// this for every server-registered block-bindings source. In 18.5 the
	// client-side registration API is still private; a no-op keeps the inline
	// script from throwing.
	if (typeof wp.blocks.registerBlockBindingsSource !== "function") {
		wp.blocks.registerBlockBindingsSource = function () {};
	}

	// Blocks the 18.5 client registers but that cannot work on current core
	// (their REST backend never shipped). The list is injected by PHP before
	// this script runs; hide them from the inserter rather than deleting them
	// so existing content still renders and can be removed.
	if (wp.hooks && typeof wp.hooks.addFilter === "function") {
		wp.hooks.addFilter(
			"blocks.registerBlockType",
			"gutenberg-downgrade/hide-unsupported-blocks",
			/**
			 * @param {BlockSettings} settings
			 * @param {string} name
			 */
			function (settings, name) {
				var hidden = window.gutenbergDowngradeHiddenBlocks || [];
				if (hidden.indexOf(name) === -1) {
					return settings;
				}
				var supports = Object.assign({}, settings.supports || {}, { inserter: false });
				return Object.assign({}, settings, { supports: supports });
			},
		);
	}
})();
