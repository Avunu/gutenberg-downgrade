// Test A — PHP-level assertions inside a booted WordPress (no browser).
//
// Each block simulates a request context (an admin screen, the front end, a
// REST request) by defining the constants WordPress reads before wp-load, then
// inspects what the plugin registered: script/style handles, the block
// registry, the editor settings, and that every block the 11.9 PHP serves
// renders without PHP deprecations.
import {
	bootPlayground,
	phpJson,
	PLUGIN_PATH,
	PLUGIN_SLUG,
	WP_VERSION,
	PHP_VERSION,
} from "./lib.mts";
import { deprecations, noticeDetail, tally } from "./assert.mts";

const t = tally();
console.log(`Test A — PHP-level (WordPress ${WP_VERSION}, PHP ${PHP_VERSION})\n`);

interface Env {
	wp: string;
	php: string;
	theme: string;
	active: boolean;
	blockTheme: boolean;
	gutenberg: string;
}

interface Dependency {
	src: string;
	ver: string | false | null;
	deps: string[];
	extra: Record<string, unknown>;
}

interface AdminState {
	scripts: Record<string, Dependency | null>;
	styles: Record<string, Dependency | null>;
	commandPaletteHooked: boolean;
	corePatterns: boolean;
	blocks: Record<
		string,
		{ apiVersion: number | null; render: string | null; attributes: Record<string, unknown> } | null
	>;
	serverSettings: Record<string, { apiVersion?: number }>;
	editorSettings: Record<string, unknown>;
	routes: string[];
}

let server;
try {
	server = await bootPlayground({ port: 9400 });

	// ---- environment -------------------------------------------------------
	const env = await phpJson<Env>(
		server,
		`require_once ABSPATH . 'wp-admin/includes/plugin.php';
		return [
			'wp' => get_bloginfo('version'),
			'php' => PHP_VERSION,
			'theme' => get_stylesheet(),
			'active' => is_plugin_active(${JSON.stringify(PLUGIN_PATH)}),
			'blockTheme' => wp_is_block_theme(),
			'gutenberg' => \\GutenbergDowngrade\\Assets::version(),
		];`,
	);
	console.log(`  WordPress ${env.value.wp}, PHP ${env.value.php}, theme ${env.value.theme}`);
	t.check("the plugin is active", env.value.active);
	t.check("a classic theme is active", !env.value.blockTheme, env.value.theme);
	t.check(
		"the vendored build is Gutenberg 11.9.1",
		env.value.gutenberg === "11.9.1",
		env.value.gutenberg,
	);

	// ---- the post editor screen --------------------------------------------
	const admin = await phpJson<AdminState>(
		server,
		`require_once ABSPATH . 'wp-admin/includes/post.php';
		$dep = static function ($registry, string $handle): ?array {
			$d = $registry->registered[$handle] ?? null;
			return $d === null ? null : ['src' => (string) $d->src, 'ver' => $d->ver, 'deps' => $d->deps, 'extra' => $d->extra];
		};
		$scripts = wp_scripts();
		$styles = wp_styles();
		$registry = WP_Block_Type_Registry::get_instance();
		$block = static function (string $name) use ($registry): ?array {
			$b = $registry->get_registered($name);
			return $b === null ? null : [
				'apiVersion' => $b->api_version,
				'render' => is_string($b->render_callback) ? $b->render_callback : (is_callable($b->render_callback) ? 'closure' : null),
				'attributes' => $b->attributes ?? [],
			];
		};
		$post_id = wp_insert_post(['post_title' => 'Harness', 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post']);
		$context = new WP_Block_Editor_Context(['post' => get_post($post_id)]);
		$settings = get_block_editor_settings(['supportsTemplateMode' => true], $context);
		return [
			'scripts' => [
				'wp-edit-post' => $dep($scripts, 'wp-edit-post'),
				'wp-blocks' => $dep($scripts, 'wp-blocks'),
				'wp-api-fetch' => $dep($scripts, 'wp-api-fetch'),
				'wp-i18n' => $dep($scripts, 'wp-i18n'),
				'wp-block-library' => $dep($scripts, 'wp-block-library'),
				'react' => $dep($scripts, 'react'),
				'react-dom' => $dep($scripts, 'react-dom'),
				'wp-commands' => $dep($scripts, 'wp-commands'),
				'wp-core-commands' => $dep($scripts, 'wp-core-commands'),
				'wp-private-apis' => $dep($scripts, 'wp-private-apis'),
			],
			'styles' => [
				'wp-edit-post' => $dep($styles, 'wp-edit-post'),
				'wp-components' => $dep($styles, 'wp-components'),
				'wp-edit-blocks' => $dep($styles, 'wp-edit-blocks'),
				'wp-base-styles' => $dep($styles, 'wp-base-styles'),
			],
			'commandPaletteHooked' => has_action('admin_enqueue_scripts', 'wp_enqueue_command_palette_assets') !== false,
			'corePatterns' => (bool) current_theme_supports('core-block-patterns'),
			'blocks' => [
				'core/paragraph' => $block('core/paragraph'),
				'core/quote' => $block('core/quote'),
				'core/list' => $block('core/list'),
				'core/archives' => $block('core/archives'),
				'core/site-logo' => $block('core/site-logo'),
				'core/legacy-widget' => $block('core/legacy-widget'),
				'core/post-comments' => $block('core/post-comments'),
				'core/list-item' => $block('core/list-item'),
				'core/navigation-area' => $block('core/navigation-area'),
			],
			'serverSettings' => array_intersect_key(get_block_editor_server_block_settings(), array_flip(['core/paragraph', 'core/image'])),
			'editorSettings' => [
				'fse' => $settings['__unstableEnableFullSiteEditingBlocks'] ?? null,
				'templateMode' => $settings['supportsTemplateMode'] ?? null,
				'paletteOrigins' => array_keys($settings['__experimentalFeatures']['color']['palette'] ?? []),
				'fontSizeOrigins' => array_keys($settings['__experimentalFeatures']['typography']['fontSizes'] ?? []),
				'patterns' => array_column($settings['__experimentalBlockPatterns'] ?? [], 'name'),
				'patternCategories' => is_array($settings['__experimentalBlockPatternCategories'] ?? null),
				'defaultEditorStyles' => strlen($settings['defaultEditorStyles'][0]['css'] ?? ''),
				'editorAssets' => in_array(true, array_map(static fn($s) => str_starts_with($s, 'window.__editorAssets'), $scripts->registered['wp-block-editor']->extra['before'] ?? []), true),
			],
			'routes' => array_values(array_filter(array_keys(rest_get_server()->get_routes()), static fn(string $r): bool => str_starts_with($r, '/__experimental'))),
		];`,
		{ adminPage: "post.php" },
	);
	const a = admin.value;
	const pluginUrl = `/wp-content/plugins/${PLUGIN_SLUG}/assets/gutenberg/`;
	const editPost = a.scripts["wp-edit-post"];
	t.check(
		"wp-edit-post is served from the vendored build",
		editPost?.src.includes(`${pluginUrl}build/edit-post/index.min.js`),
		editPost?.src,
	);
	t.check(
		"wp-edit-post carries the 11.9 content hash as its version",
		/^[0-9a-f]{32}$/.test(String(editPost?.ver)),
		String(editPost?.ver),
	);
	t.check(
		"wp-edit-post keeps the deps core cannot detect",
		["postbox", "media-views", "wp-dom-ready"].every((d) => editPost?.deps.includes(d)),
	);
	t.check(
		"wp-edit-post drops core's script-module imports",
		!(editPost && "module_dependencies" in editPost.extra),
	);
	t.check("wp-edit-post prints in the footer", editPost?.extra["group"] === 1);
	t.check(
		"React is the vendored 17.0.1",
		a.scripts["react"]?.ver === "17.0.1" &&
			a.scripts["react"]?.src.includes(`${pluginUrl}vendor/react.min.js`),
		String(a.scripts["react"]?.src),
	);
	t.check(
		"react-dom depends on react",
		JSON.stringify(a.scripts["react-dom"]?.deps) === '["react"]',
	);
	t.check(
		"wp-block-library still depends on the classic editor bridge",
		a.scripts["wp-block-library"]?.deps.includes("editor"),
	);
	t.check(
		"core's wp-api-fetch inline scripts survive",
		JSON.stringify(a.scripts["wp-api-fetch"]?.extra["after"]).includes("createRootURLMiddleware"),
	);
	t.check(
		"core's wp-i18n text-direction inline survives",
		JSON.stringify(a.scripts["wp-i18n"]?.extra["after"]).includes("setLocaleData"),
	);
	t.check(
		"the compat shim is attached after wp-blocks",
		JSON.stringify(a.scripts["wp-blocks"]?.extra["after"]).includes("registerBlockBindingsSource"),
	);
	t.check(
		"the command palette handles are retired",
		a.scripts["wp-commands"] === null && a.scripts["wp-core-commands"] === null,
	);
	t.check("the command palette is unhooked", !a.commandPaletteHooked);
	t.check("other 7.x-only handles stay registered", a.scripts["wp-private-apis"] !== null);
	t.check(
		"wp-edit-post style is served from the vendored build",
		a.styles["wp-edit-post"]?.src.includes(`${pluginUrl}build/edit-post/style.css`),
		a.styles["wp-edit-post"]?.src,
	);
	t.check(
		"wp-edit-post style has the 11.9 dependency graph",
		JSON.stringify(a.styles["wp-edit-post"]?.deps) ===
			JSON.stringify([
				"wp-components",
				"wp-block-editor",
				"wp-editor",
				"wp-edit-blocks",
				"wp-block-library",
				"wp-nux",
			]),
		JSON.stringify(a.styles["wp-edit-post"]?.deps),
	);
	t.check(
		"wp-components style depends on dashicons only",
		JSON.stringify(a.styles["wp-components"]?.deps) === '["dashicons"]',
	);
	t.check(
		"wp-edit-blocks includes the classic layout styles for a classic theme",
		a.styles["wp-edit-blocks"]?.deps.includes("wp-editor-classic-layout-styles"),
	);
	t.check(
		"package styles use style-rtl.css, not a .min suffix",
		a.styles["wp-components"]?.extra["rtl"] === "replace" &&
			!("suffix" in (a.styles["wp-components"]?.extra ?? {})),
	);
	t.check(
		"wp-base-styles (a wp-admin dependency) is untouched",
		a.styles["wp-base-styles"]?.src.includes("/wp-includes/"),
		a.styles["wp-base-styles"]?.src,
	);
	t.check("core's bundled patterns are disabled", !a.corePatterns);

	t.check(
		"core/paragraph is the 11.9 static definition (apiVersion 2, no callback)",
		a.blocks["core/paragraph"]?.apiVersion === 2 && a.blocks["core/paragraph"]?.render === null,
		JSON.stringify(a.blocks["core/paragraph"]),
	);
	const citation = a.blocks["core/quote"]?.attributes["citation"] as
		| { source?: string }
		| undefined;
	t.check(
		"core/quote citation is html-sourced (11.9 parser can read it)",
		citation?.source === "html",
		JSON.stringify(citation),
	);
	t.check(
		"core/list has the 11.9 values attribute",
		"values" in (a.blocks["core/list"]?.attributes ?? {}),
	);
	t.check(
		"core/archives renders with the 11.9 callback",
		a.blocks["core/archives"]?.render === "gutenberg_render_block_core_archives",
		String(a.blocks["core/archives"]?.render),
	);
	t.check(
		"core/site-logo keeps core's renderer on 11.9 metadata",
		a.blocks["core/site-logo"]?.render === "render_block_core_site_logo" &&
			a.blocks["core/site-logo"]?.apiVersion === 2,
		JSON.stringify(a.blocks["core/site-logo"]),
	);
	t.check(
		"core/legacy-widget keeps core's renderer",
		a.blocks["core/legacy-widget"]?.render === "render_block_core_legacy_widget",
		String(a.blocks["core/legacy-widget"]?.render),
	);
	t.check(
		"core/post-comments is left to core (skip)",
		a.blocks["core/post-comments"] !== null &&
			!String(a.blocks["core/post-comments"]?.render).startsWith("gutenberg_"),
		String(a.blocks["core/post-comments"]?.render),
	);
	t.check("7.x-only blocks stay registered server-side", a.blocks["core/list-item"] !== null);
	t.check(
		"the server bootstrap sends apiVersion 2 to the client",
		a.serverSettings["core/paragraph"]?.apiVersion === 2 &&
			a.serverSettings["core/image"]?.apiVersion === 2,
	);

	const es = a.editorSettings as {
		fse: boolean;
		templateMode: boolean;
		paletteOrigins: string[];
		fontSizeOrigins: string[];
		patterns: string[];
		patternCategories: boolean;
		defaultEditorStyles: number;
		editorAssets: boolean;
	};
	t.check("FSE-only blocks are off for a classic theme", es.fse === false);
	t.check("template mode is off", es.templateMode === false);
	t.check(
		"palette origins are the 11.9 names",
		es.paletteOrigins.includes("core") && !es.paletteOrigins.includes("default"),
		es.paletteOrigins.join(","),
	);
	t.check(
		"font size origins are the 11.9 names",
		es.fontSizeOrigins.includes("core") && !es.fontSizeOrigins.includes("default"),
		es.fontSizeOrigins.join(","),
	);
	t.check(
		"block patterns are inlined again and contain no core patterns",
		Array.isArray(es.patterns) && es.patterns.every((n) => !n.startsWith("core/")),
		es.patterns.slice(0, 5).join(","),
	);
	t.check("block pattern categories are inlined", es.patternCategories);
	t.check(
		"defaultEditorStyles comes from the 11.9 stylesheet",
		es.defaultEditorStyles > 100,
		String(es.defaultEditorStyles),
	);
	t.check("window.__editorAssets is exposed for the 11.9 iframe", es.editorAssets);
	t.check(
		"the __experimental menu routes are aliased",
		["/__experimental/menus", "/__experimental/menu-items", "/__experimental/menu-locations"].every(
			(r) => a.routes.includes(r),
		),
		a.routes.join(","),
	);
	t.check(
		"the editor screen bootstrap raises no PHP notices",
		admin.notices.length === 0,
		noticeDetail(admin.notices),
	);

	// ---- the front end is untouched ----------------------------------------
	const front = await phpJson<{ editPost: string; paragraphApi: number | null; commands: boolean }>(
		server,
		`$scripts = wp_scripts();
		$b = WP_Block_Type_Registry::get_instance()->get_registered('core/paragraph');
		return [
			'editPost' => (string) ($scripts->registered['wp-edit-post']->src ?? ''),
			'paragraphApi' => $b?->api_version,
			'commands' => isset($scripts->registered['wp-commands']),
		];`,
	);
	t.check(
		"front end: wp-edit-post is core's",
		front.value.editPost.includes("/wp-includes/js/dist/edit-post"),
		front.value.editPost,
	);
	t.check(
		"front end: core/paragraph is core's (apiVersion 3)",
		front.value.paragraphApi === 3,
		String(front.value.paragraphApi),
	);
	t.check("front end: the command palette handle exists", front.value.commands);

	// ---- REST requests get the editor's registry ---------------------------
	const rest = await phpJson<{ editPost: string; paragraphApi: number | null }>(
		server,
		`$scripts = wp_scripts();
		$b = WP_Block_Type_Registry::get_instance()->get_registered('core/paragraph');
		return ['editPost' => (string) ($scripts->registered['wp-edit-post']->src ?? ''), 'paragraphApi' => $b?->api_version];`,
		{ constants: { REST_REQUEST: true } },
	);
	t.check(
		"REST: core/paragraph is the 11.9 definition",
		rest.value.paragraphApi === 2,
		String(rest.value.paragraphApi),
	);
	t.check("REST: wp-edit-post is the vendored build", rest.value.editPost.includes(pluginUrl));

	// ---- bypassed screens keep core's stack --------------------------------
	const bypass = await phpJson<{ editPost: string; paragraphApi: number | null }>(
		server,
		`$scripts = wp_scripts();
		$b = WP_Block_Type_Registry::get_instance()->get_registered('core/paragraph');
		return ['editPost' => (string) ($scripts->registered['wp-edit-post']->src ?? ''), 'paragraphApi' => $b?->api_version];`,
		{ adminPage: "site-editor.php" },
	);
	t.check(
		"site-editor.php: wp-edit-post is core's",
		bypass.value.editPost.includes("/wp-includes/js/dist/"),
		bypass.value.editPost,
	);
	t.check("site-editor.php: core/paragraph is core's", bypass.value.paragraphApi === 3);

	// ---- every block the 11.9 PHP serves renders on current core -----------
	const render = await phpJson<
		Record<string, { html: number; error: string | null; notices: string[] }>
	>(
		server,
		`$manifest = \\GutenbergDowngrade\\Assets::manifest();
		$config = \\GutenbergDowngrade\\BlockConfig::load();
		$post_id = wp_insert_post(['post_title' => 'Render fixture', 'post_content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->', 'post_status' => 'publish', 'post_type' => 'post']);
		$GLOBALS['post'] = get_post($post_id);
		setup_postdata($GLOBALS['post']);
		$results = [];
		foreach ($manifest->blocks() as $name => $entry) {
			$mode = $config->mode($name);
			if ($entry['php'] === null || !in_array($mode, ['dynamic', 'core-render'], true)) {
				continue;
			}
			$before = count($GLOBALS['__notices']);
			$error = null;
			$html = '';
			try {
				$html = render_block(['blockName' => $name, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
			} catch (\\Throwable $e) {
				$error = get_class($e) . ': ' . $e->getMessage();
			}
			$results[$name] = ['html' => strlen((string) $html), 'error' => $error, 'notices' => array_slice($GLOBALS['__notices'], $before)];
		}
		return $results;`,
		{ adminPage: "post.php" },
	);
	const rendered = Object.entries(render.value);
	t.check(
		"rendered every 11.9-served dynamic block",
		rendered.length >= 50,
		`${rendered.length} blocks`,
	);
	for (const [name, r] of rendered) {
		const deprecated = deprecations(r.notices);
		t.check(
			`${name} renders without errors or deprecations`,
			r.error === null && deprecated.length === 0,
			r.error ?? deprecated.join(" | "),
		);
	}
	const noisy = rendered
		.filter(([, r]) => r.notices.length > 0)
		.map(([n, r]) => `${n} (${r.notices.length})`);
	if (noisy.length) {
		console.log(
			`  ℹ blocks raising PHP notices when rendered without context: ${noisy.join(", ")}`,
		);
	}
} catch (err) {
	console.error(`\nUnexpected error: ${err instanceof Error ? err.message : String(err)}`);
	t.check(
		"test completed without an unexpected error",
		false,
		err instanceof Error ? err.message : String(err),
	);
} finally {
	if (server) await server[Symbol.asyncDispose]();
}

console.log(t.failures ? `\nTest A FAILED (${t.failures})` : "\nTest A PASSED");
process.exit(t.failures ? 1 : 0);
