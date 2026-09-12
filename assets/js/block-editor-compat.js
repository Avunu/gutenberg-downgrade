// Inlined verbatim after the `wp-block-editor` bundle by ScriptOverrides (it is
// not a separately enqueued file), so it patches the package object the moment
// it exists and long before any plugin bundle that reads from it renders.
//
// Plain ES2017: no modules, no classes, no build step. Type-checked by tsc via
// the root tsconfig.json.
(function () {
	"use strict";

	/** @typedef {Record<string, unknown> & { bindings?: Record<string, unknown> }} BlockMetadata */

	// `const`, not `var`: tsc only keeps the null checks below narrowed inside
	// the closures when the binding cannot be reassigned.
	const wp = window.wp;
	const blockEditor = wp && wp.blockEditor;
	const data = wp && wp.data;
	if (!blockEditor || !data) {
		return;
	}

	const STORE = "core/block-editor";

	// `useBlockBindingsUtils()` is the public hook WordPress 6.7 added for
	// editing a block's `metadata.bindings`. 18.5 already stores and resolves
	// those bindings — only the hook that writes them is missing.
	//
	// This matters far beyond the plugins that call it. Secure Custom Fields
	// calls it from an `editor.BlockEdit` filter, which wraps the edit
	// component of EVERY block, so an undefined export there is not a missing
	// panel: it is "This block has encountered an error and cannot be
	// previewed" on every block in the post.
	//
	// Ported from packages/block-editor/src/utils/block-bindings.js.
	if (typeof blockEditor.useBlockBindingsUtils !== "function") {
		blockEditor.useBlockBindingsUtils = function (clientId) {
			// Called unconditionally even when `clientId` was passed: it is a
			// hook, and short-circuiting it would change the hook order
			// between renders of the same component.
			const context = blockEditor.useBlockEditContext();
			const blockClientId = clientId || context.clientId;
			const dispatch = data.useDispatch(STORE);
			const registry = data.useRegistry();

			// A plain (non-reactive) read, as the original does: these run from
			// event handlers, never during render.
			const readMetadata = function () {
				const attributes = registry.select(STORE).getBlockAttributes(blockClientId);
				return /** @type {BlockMetadata} */ (
					Object.assign({}, (attributes && attributes.metadata) || {})
				);
			};

			// An empty `bindings` (or `metadata`) is dropped rather than left
			// behind as `{}`, so a block that has had every binding removed
			// serializes exactly as it did before one was ever added.
			/** @param {BlockMetadata} metadata */
			const writeMetadata = function (metadata) {
				if (metadata.bindings && Object.keys(metadata.bindings).length === 0) {
					delete metadata.bindings;
				}
				dispatch.updateBlockAttributes(blockClientId, {
					metadata: Object.keys(metadata).length ? metadata : undefined,
				});
			};

			return {
				/** @param {Record<string, unknown>} bindings */
				updateBlockBindings: function (bindings) {
					const metadata = readMetadata();
					const updated = Object.assign({}, metadata.bindings);
					Object.keys(bindings).forEach(function (attribute) {
						if (!bindings[attribute]) {
							delete updated[attribute];
							return;
						}
						updated[attribute] = bindings[attribute];
					});
					metadata.bindings = updated;
					writeMetadata(metadata);
				},
				removeAllBlockBindings: function () {
					const metadata = readMetadata();
					delete metadata.bindings;
					writeMetadata(metadata);
				},
			};
		};
	}
})();
