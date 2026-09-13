// Test A — PHP-level assertions inside a booted WordPress (no browser).
//
// Each block simulates a request context (an admin screen, the front end, a
// REST request) by defining the constants WordPress reads before wp-load, then
// inspects what the plugin registered: script/style handles, the block
// registry, the editor settings, and that every block the 18.5 PHP serves
// renders without PHP deprecations.
import {
	bootPlayground,
	CLASSIC_THEME,
	phpJson,
	PLUGIN_PATH,
	PLUGIN_SLUG,
	switchTheme,
	THEME,
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
	unregisteredEditSiteDeps: string[];
	commandPaletteHooked: boolean;
	patterns: string[];
	blocks: Record<
		string,
		{ apiVersion: number | null; render: string | null; attributes: Record<string, unknown> } | null
	>;
	serverSettings: Record<string, { apiVersion?: number; attributes?: Record<string, unknown> }>;
	editorSettings: Record<string, unknown>;
}

// 18.5 block.json still spells the content role `__experimentalRole`; core 7.1
// says `role`. Block supports add `align` on both, so that is no marker.
const is185 = (b: { attributes: Record<string, unknown> } | null | undefined): boolean =>
	"__experimentalRole" in ((b?.attributes["content"] as Record<string, unknown> | undefined) ?? {});

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
	t.check("a block theme is active", env.value.blockTheme, env.value.theme);
	t.check(
		"the vendored build is Gutenberg 18.5.0",
		env.value.gutenberg === "18.5.0",
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
				'react-jsx-runtime' => $dep($scripts, 'react-jsx-runtime'),
				'wp-commands' => $dep($scripts, 'wp-commands'),
				'wp-core-commands' => $dep($scripts, 'wp-core-commands'),
				'wp-private-apis' => $dep($scripts, 'wp-private-apis'),
				'wp-preferences' => $dep($scripts, 'wp-preferences'),
				'wp-edit-site' => $dep($scripts, 'wp-edit-site'),
				'wp-router' => $dep($scripts, 'wp-router'),
			],
			'styles' => [
				'wp-edit-post' => $dep($styles, 'wp-edit-post'),
				'wp-components' => $dep($styles, 'wp-components'),
				'wp-edit-blocks' => $dep($styles, 'wp-edit-blocks'),
				'wp-base-styles' => $dep($styles, 'wp-base-styles'),
				'wp-edit-site' => $dep($styles, 'wp-edit-site'),
			],
			'unregisteredEditSiteDeps' => array_values(array_filter(
				$scripts->registered['wp-edit-site']->deps ?? [],
				static fn(string $h): bool => !isset($scripts->registered[$h])
			)),
			'commandPaletteHooked' => has_action('admin_enqueue_scripts', 'wp_enqueue_command_palette_assets') !== false,
			'patterns' => array_column(WP_Block_Patterns_Registry::get_instance()->get_all_registered(), 'name'),
			'blocks' => [
				'core/paragraph' => $block('core/paragraph'),
				'core/quote' => $block('core/quote'),
				'core/list' => $block('core/list'),
				'core/archives' => $block('core/archives'),
				'core/image' => $block('core/image'),
				'core/site-logo' => $block('core/site-logo'),
				'core/legacy-widget' => $block('core/legacy-widget'),
				'core/post-comments' => $block('core/post-comments'),
				'core/list-item' => $block('core/list-item'),
				'core/navigation-link' => $block('core/navigation-link'),
				'core/post-time-to-read' => $block('core/post-time-to-read'),
				'core/accordion' => $block('core/accordion'),
			],
			'serverSettings' => array_intersect_key(get_block_editor_server_block_settings(), array_flip(['core/paragraph', 'core/image'])),
			'editorSettings' => [
				'keys' => array_keys($settings),
				'paletteOrigins' => array_keys($settings['__experimentalFeatures']['color']['palette'] ?? []),
				'resolvedAssets' => is_array($settings['__unstableResolvedAssets'] ?? null),
			],
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
		"wp-edit-post carries the 18.5 content hash as its version",
		/^[0-9a-f]{20,32}$/.test(String(editPost?.ver)),
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
		"React is the vendored 18.3.1",
		a.scripts["react"]?.ver === "18.3.1" &&
			a.scripts["react"]?.src.includes(`${pluginUrl}vendor/react.min.js`),
		String(a.scripts["react"]?.src),
	);
	t.check(
		"react-dom and the JSX runtime depend on react",
		JSON.stringify(a.scripts["react-dom"]?.deps) === '["react"]' &&
			JSON.stringify(a.scripts["react-jsx-runtime"]?.deps) === '["react"]' &&
			(a.scripts["react-jsx-runtime"]?.src.includes(
				`${pluginUrl}vendor/react-jsx-runtime.min.js`,
			) ??
				false),
		String(a.scripts["react-jsx-runtime"]?.src),
	);
	t.check(
		"wp-preferences keeps its persistence dependency",
		a.scripts["wp-preferences"]?.deps.includes("wp-preferences-persistence") ?? false,
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
		"the command palette handles are served from the vendored build (wp-edit-post needs them)",
		(a.scripts["wp-commands"]?.src.includes(`${pluginUrl}build/commands/`) ?? false) &&
			(a.scripts["wp-core-commands"]?.src.includes(`${pluginUrl}build/core-commands/`) ?? false),
		String(a.scripts["wp-commands"]?.src),
	);
	t.check("the admin-wide command palette is unhooked", !a.commandPaletteHooked);
	t.check(
		"wp-edit-site is served from the vendored build",
		a.scripts["wp-edit-site"]?.src.includes(`${pluginUrl}build/edit-site/index.min.js`),
		a.scripts["wp-edit-site"]?.src,
	);
	t.check(
		"every dependency of the 18.5 edit-site bundle is a handle core registers",
		a.unregisteredEditSiteDeps.length === 0,
		a.unregisteredEditSiteDeps.join(","),
	);
	t.check(
		"wp-router (the site editor's router) is served from the vendored build",
		a.scripts["wp-router"]?.src.includes(`${pluginUrl}build/router/`),
		a.scripts["wp-router"]?.src,
	);
	t.check(
		"wp-edit-site style is served from the vendored build with the 18.5 graph",
		(a.styles["wp-edit-site"]?.src.includes(`${pluginUrl}build/edit-site/style.css`) ?? false) &&
			JSON.stringify(a.styles["wp-edit-site"]?.deps) ===
				JSON.stringify([
					"wp-components",
					"wp-block-editor",
					"wp-editor",
					"wp-edit-blocks",
					"wp-commands",
					"wp-preferences",
				]),
		JSON.stringify(a.styles["wp-edit-site"]),
	);
	t.check("other 7.x-only handles stay registered", a.scripts["wp-private-apis"] !== null);
	t.check(
		"wp-edit-post style is served from the vendored build",
		a.styles["wp-edit-post"]?.src.includes(`${pluginUrl}build/edit-post/style.css`),
		a.styles["wp-edit-post"]?.src,
	);
	t.check(
		"wp-edit-post style has the 18.5 dependency graph",
		JSON.stringify(a.styles["wp-edit-post"]?.deps) ===
			JSON.stringify([
				"wp-components",
				"wp-block-editor",
				"wp-editor",
				"wp-edit-blocks",
				"wp-block-library",
				"wp-commands",
				"wp-preferences",
			]),
		JSON.stringify(a.styles["wp-edit-post"]?.deps),
	);
	t.check(
		"wp-components style depends on dashicons only",
		JSON.stringify(a.styles["wp-components"]?.deps) === '["dashicons"]',
	);
	t.check(
		"wp-edit-blocks skips the classic layout styles for a theme.json theme",
		!a.styles["wp-edit-blocks"]?.deps.includes("wp-editor-classic-layout-styles"),
		JSON.stringify(a.styles["wp-edit-blocks"]?.deps),
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
	t.check(
		"core's query patterns stay, the 7.x navigation overlays go",
		a.patterns.includes("core/query-standard-posts") &&
			!a.patterns.some((n) => n.startsWith("core/navigation-overlay")),
		a.patterns.filter((n) => n.startsWith("core/")).join(","),
	);

	t.check(
		"core/paragraph is the 18.5 static definition (no callback)",
		a.blocks["core/paragraph"]?.apiVersion === 3 &&
			a.blocks["core/paragraph"]?.render === null &&
			is185(a.blocks["core/paragraph"]),
		JSON.stringify(a.blocks["core/paragraph"]),
	);
	t.check(
		"core/image is the 18.5 definition (no 7.x blob/isDecorative attributes)",
		!("isDecorative" in (a.blocks["core/image"]?.attributes ?? {})),
		Object.keys(a.blocks["core/image"]?.attributes ?? {}).join(","),
	);
	const citation = a.blocks["core/quote"]?.attributes["citation"] as
		| { source?: string }
		| undefined;
	t.check(
		"core/quote citation is rich-text-sourced (the 18.5 shape)",
		citation?.source === "rich-text",
		JSON.stringify(citation),
	);
	t.check(
		"core/archives renders with the 18.5 callback",
		a.blocks["core/archives"]?.render === "gutenberg_render_block_core_archives",
		String(a.blocks["core/archives"]?.render),
	);
	t.check(
		"core/site-logo keeps core's renderer on 18.5 metadata",
		a.blocks["core/site-logo"]?.render === "render_block_core_site_logo" &&
			a.blocks["core/site-logo"]?.apiVersion === 3,
		JSON.stringify(a.blocks["core/site-logo"]),
	);
	t.check(
		"core/navigation-link keeps core's renderer (variations would double)",
		a.blocks["core/navigation-link"]?.render === "render_block_core_navigation_link",
		String(a.blocks["core/navigation-link"]?.render),
	);
	t.check(
		"core/legacy-widget renders with the 18.5 callback",
		a.blocks["core/legacy-widget"]?.render === "gutenberg_render_block_core_legacy_widget",
		String(a.blocks["core/legacy-widget"]?.render),
	);
	t.check(
		"core/post-comments is the legacy alias 18.5's comments.php registers",
		a.blocks["core/post-comments"]?.render === "gutenberg_render_block_core_comments",
		String(a.blocks["core/post-comments"]?.render),
	);
	t.check("core/list-item is the 18.5 definition", a.blocks["core/list-item"] !== null);
	t.check(
		"experimental 18.5 blocks are left to core (skip)",
		a.blocks["core/post-time-to-read"] !== null &&
			!String(a.blocks["core/post-time-to-read"]?.render).startsWith("gutenberg_"),
		String(a.blocks["core/post-time-to-read"]?.render),
	);
	t.check("7.x-only blocks stay registered server-side", a.blocks["core/accordion"] !== null);
	t.check(
		"the server bootstrap sends the 18.5 definitions to the client",
		a.serverSettings["core/paragraph"]?.apiVersion === 3 &&
			is185(a.serverSettings["core/paragraph"] as { attributes: Record<string, unknown> }) &&
			!("isDecorative" in (a.serverSettings["core/image"]?.attributes ?? {})),
		JSON.stringify(a.serverSettings["core/paragraph"]),
	);

	const es = a.editorSettings as {
		keys: string[];
		paletteOrigins: string[];
		resolvedAssets: boolean;
	};
	t.check(
		"palette origins keep core's names (18.5 reads default/theme/custom)",
		es.paletteOrigins.includes("default") && !es.paletteOrigins.includes("core"),
		es.paletteOrigins.join(","),
	);
	t.check("__unstableResolvedAssets is present for the 18.5 iframe", es.resolvedAssets);
	t.check(
		"the settings 18.5's editor reads are all present",
		[
			"__experimentalFeatures",
			"styles",
			"__unstableResolvedAssets",
			"__experimentalDashboardLink",
		].every((k) => es.keys.includes(k)),
		es.keys.filter((k) => k.startsWith("__")).join(","),
	);
	t.check(
		"the editor screen bootstrap raises no PHP notices",
		admin.notices.length === 0,
		noticeDetail(admin.notices),
	);

	// ---- the front end is untouched ----------------------------------------
	const front = await phpJson<{ editPost: string; paragraph185: boolean; commands: boolean }>(
		server,
		`$scripts = wp_scripts();
		$b = WP_Block_Type_Registry::get_instance()->get_registered('core/paragraph');
		return [
			'editPost' => (string) ($scripts->registered['wp-edit-post']->src ?? ''),
			'paragraph185' => isset($b->attributes['content']['__experimentalRole']),
			'commands' => isset($scripts->registered['wp-commands']),
		];`,
	);
	t.check(
		"front end: wp-edit-post is core's",
		front.value.editPost.includes("/wp-includes/js/dist/edit-post"),
		front.value.editPost,
	);
	t.check(
		"front end: core/paragraph is core's",
		!front.value.paragraph185 && !front.value.editPost.includes(pluginUrl),
	);
	t.check("front end: the command palette handle exists", front.value.commands);

	// ---- REST requests get the editor's registry ---------------------------
	const rest = await phpJson<{ editPost: string; paragraph185: boolean }>(
		server,
		`$scripts = wp_scripts();
		$b = WP_Block_Type_Registry::get_instance()->get_registered('core/paragraph');
		return ['editPost' => (string) ($scripts->registered['wp-edit-post']->src ?? ''), 'paragraph185' => isset($b->attributes['content']['__experimentalRole'])];`,
		{ constants: { REST_REQUEST: true } },
	);
	t.check("REST: core/paragraph is the 18.5 definition", rest.value.paragraph185);
	t.check("REST: wp-edit-post is the vendored build", rest.value.editPost.includes(pluginUrl));

	// ---- the site editor gets the 18.5 stack, the 7.x-only screens do not --
	const stack = `$scripts = wp_scripts();
		$b = WP_Block_Type_Registry::get_instance()->get_registered('core/paragraph');
		return ['editSite' => (string) ($scripts->registered['wp-edit-site']->src ?? ''), 'paragraph185' => isset($b->attributes['content']['__experimentalRole'])];`;
	const siteEditor = await phpJson<{ editSite: string; paragraph185: boolean }>(server, stack, {
		adminPage: "site-editor.php",
	});
	t.check(
		"site-editor.php: wp-edit-site is the vendored build",
		siteEditor.value.editSite.includes(`${pluginUrl}build/edit-site/`),
		siteEditor.value.editSite,
	);
	t.check("site-editor.php: core/paragraph is the 18.5 definition", siteEditor.value.paragraph185);
	const bypass = await phpJson<{ editSite: string; paragraph185: boolean }>(server, stack, {
		adminPage: "font-library.php",
	});
	t.check(
		"font-library.php (7.x-only): wp-edit-site is core's",
		bypass.value.editSite.includes("/wp-includes/js/dist/"),
		bypass.value.editSite,
	);
	t.check(
		"font-library.php: core/paragraph is core's",
		!bypass.value.paragraph185 && !bypass.value.editSite.includes(pluginUrl),
	);

	// ---- a classic theme: what changes, and what 6.6 offered it -----------
	await switchTheme(server, CLASSIC_THEME);
	const classic = await phpJson<{
		blockTheme: boolean;
		editBlocksDeps: string[];
		menu: [string, string, string] | null;
	}>(
		server,
		`require_once ABSPATH . 'wp-admin/includes/admin.php';
		wp_set_current_user(1);
		// menu.php writes these as file-scope variables; bind them to the globals our admin_menu hook reads.
		global $menu, $submenu, $_wp_last_utility_menu, $_wp_last_object_menu, $_wp_real_parent_file, $_wp_submenu_nopriv, $_registered_pages, $admin_page_hooks;
		require ABSPATH . 'wp-admin/menu.php';
		return [
			'blockTheme' => wp_is_block_theme(),
			'editBlocksDeps' => wp_styles()->registered['wp-edit-blocks']->deps,
			'menu' => $GLOBALS['submenu']['themes.php'][6] ?? null,
		];`,
		{ adminPage: "index.php" },
	);
	t.check("switched to a classic theme", !classic.value.blockTheme);
	t.check(
		"wp-edit-blocks includes the classic layout styles for a classic theme",
		classic.value.editBlocksDeps.includes("wp-editor-classic-layout-styles"),
		JSON.stringify(classic.value.editBlocksDeps),
	);
	t.check(
		"the Appearance entry is 6.6's Patterns link, not the 7.x style book",
		classic.value.menu?.[0] === "Patterns" &&
			classic.value.menu[2] === "site-editor.php?p=/pattern",
		JSON.stringify(classic.value.menu),
	);
	await switchTheme(server, THEME);

	// ---- every block the 18.5 PHP serves renders on current core -----------
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
		"rendered every 18.5-served dynamic block",
		rendered.length >= 65,
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
