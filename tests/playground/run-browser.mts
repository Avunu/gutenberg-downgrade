// Test B — headless Chrome against the booted WordPress. This is the only test
// that exercises the 18.5 bundles as a browser would: the post editor and the
// Site Editor must mount, throw nothing, run on React 18.3, and round-trip
// content (a post, a template, global styles) through save and reload.
import { chromium, type Page } from "playwright-core";
import {
	bootPlayground,
	chromePath,
	CLASSIC_THEME,
	phpJson,
	PLUGIN_SLUG,
	PHP_VERSION,
	switchTheme,
	THEME,
	WP_VERSION,
} from "./lib.mts";
import { tally } from "./assert.mts";

const t = tally();
console.log(`Test B — browser (WordPress ${WP_VERSION}, PHP ${PHP_VERSION})\n`);

const PHP_ERROR = /<b>(Notice|Deprecated|Warning|Fatal error|Parse error)<\/b>:/;

interface Watch {
	pageErrors: string[];
	consoleErrors: string[];
	failedAssets: string[];
	reset(): void;
}

function watch(page: Page): Watch {
	const w: Watch = {
		pageErrors: [],
		consoleErrors: [],
		failedAssets: [],
		reset() {
			w.pageErrors.length = 0;
			w.consoleErrors.length = 0;
			w.failedAssets.length = 0;
		},
	};
	page.on("pageerror", (e) => w.pageErrors.push(`${e.name}: ${e.message}`));
	page.on("console", (m) => {
		if (m.type() !== "error") return;
		const text = m.text();
		// Chrome reports every 4xx resource as a console error; only our own
		// assets matter (favicons and the like are noise in a fresh install).
		if (/Failed to load resource/.test(text) && !m.location().url.includes(PLUGIN_SLUG)) return;
		// The iframe warning is core's, raised for its own global-styles inline
		// stylesheet on the site editor canvas (with or without this plugin).
		if (/was added to the iframe incorrectly/.test(text)) return;
		w.consoleErrors.push(text.slice(0, 300));
	});
	page.on("response", (r) => {
		if (r.status() >= 400 && r.url().includes(`/plugins/${PLUGIN_SLUG}/`)) {
			w.failedAssets.push(`${r.status()} ${r.url()}`);
		}
	});
	return w;
}

async function login(page: Page, url: string): Promise<void> {
	await page.goto(`${url}/wp-login.php`, { waitUntil: "networkidle" });
	if (page.url().includes("wp-login.php")) {
		await page.fill("#user_login", "admin");
		await page.fill("#user_pass", "password");
		await page.click("#wp-submit");
		await page.waitForLoadState("networkidle");
	}
}

/**
 * Load an admin screen and assert it is clean: no JS errors, no PHP errors printed, no failed
 * plugin assets.
 */
async function visitClean(page: Page, w: Watch, url: string, label: string): Promise<string> {
	w.reset();
	// Not networkidle: the editor screens keep polling (heartbeat, autosave).
	await page.goto(url, { waitUntil: "load", timeout: 90_000 });
	await page.waitForTimeout(2000);
	const html = await page.content();
	t.check(
		`${label}: no uncaught page errors`,
		w.pageErrors.length === 0,
		w.pageErrors.slice(0, 3).join(" | "),
	);
	t.check(
		`${label}: no console errors`,
		w.consoleErrors.length === 0,
		w.consoleErrors.slice(0, 3).join(" | "),
	);
	t.check(
		`${label}: no failed plugin assets`,
		w.failedAssets.length === 0,
		w.failedAssets.slice(0, 3).join(" | "),
	);
	t.check(
		`${label}: no PHP errors in the page`,
		!PHP_ERROR.test(html),
		(PHP_ERROR.exec(html)?.[0] ?? "") &&
			html.slice(Math.max(0, html.search(PHP_ERROR) - 20), html.search(PHP_ERROR) + 200),
	);
	return html;
}

/** Dismiss 18.5's guides (welcome, "Edit your site", styles) and plain modals. */
async function dismissModals(page: Page): Promise<void> {
	for (let i = 0; i < 4; i++) {
		const finish = page.locator(".components-guide__finish-button");
		const close = page.locator(
			".components-modal__screen-overlay .components-modal__header button",
		);
		if (await finish.count()) await finish.first().click();
		else if (await close.count()) await close.first().click();
		else return;
		await page.waitForTimeout(400);
	}
}

/** The site editor's layout has mounted and its canvas (when the view has one) holds blocks. */
async function waitForSiteEditor(page: Page, canvas: boolean): Promise<void> {
	await page.waitForSelector(".edit-site-layout", { timeout: 60_000 });
	if (canvas) {
		await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60_000 });
		await page.waitForFunction(
			() =>
				((
					globalThis as {
						wp?: { data: { select: (s: string) => { getBlockCount: () => number } } };
					}
				).wp?.data
					.select("core/block-editor")
					.getBlockCount() ?? 0) > 0,
			undefined,
			{ timeout: 60_000 },
		);
	}
	await page.waitForTimeout(2000);
	await dismissModals(page);
}

interface SiteEditorState {
	search: string;
	title: string;
	screen: string | null;
	canvas: boolean;
	canvasText: string;
}

/** The URL the 18.5 router ended up on, and what it rendered. */
async function siteEditorState(page: Page): Promise<SiteEditorState> {
	return page.evaluate(() => ({
		search: decodeURIComponent(location.search),
		title: document.title,
		screen:
			document.querySelector(".edit-site-sidebar-navigation-screen__title")?.textContent?.trim() ??
			null,
		canvas: document.querySelector('iframe[name="editor-canvas"]') !== null,
		canvasText:
			(
				document.querySelector('iframe[name="editor-canvas"]') as HTMLIFrameElement | null
			)?.contentDocument?.body?.innerText.slice(0, 3000) ?? "",
	}));
}

/** Click Save in the site editor header, confirm the entities panel, wait for the request. */
async function saveSiteEditor(page: Page): Promise<void> {
	await page.locator("button.editor-post-publish-button__button").first().click();
	await page.waitForTimeout(1200);
	// 18.5 prefixes the panel's classes with `editor-`; a lone entity saves without it.
	const confirm = page.locator(".editor-entities-saved-states__save-button");
	if (await confirm.count()) await confirm.first().click();
	await page.waitForTimeout(5000);
}

async function waitForEditor(page: Page): Promise<void> {
	await page.waitForFunction(
		() => {
			const wp = (
				globalThis as {
					wp?: { data?: { select: (s: string) => { __unstableIsEditorReady?: () => boolean } } };
				}
			).wp;
			return wp?.data?.select("core/editor")?.__unstableIsEditorReady?.() === true;
		},
		undefined,
		{ timeout: 60_000 },
	);
	await page.waitForSelector(".edit-post-visual-editor", { timeout: 60_000 });
	// The welcome guide overlays the canvas on a fresh user; it would swallow
	// every click below. One click is not always enough: it can land before
	// React has wired the button, and a second guide can follow the first, so
	// retry until the overlay is gone rather than wait once on a single click.
	const overlay = page.locator(".components-modal__screen-overlay");
	for (let attempt = 0; attempt < 5 && (await overlay.count()); attempt++) {
		await dismissModals(page);
		await overlay
			.first()
			.waitFor({ state: "detached", timeout: 5_000 })
			.catch(() => {});
	}
	await page.waitForSelector(".components-modal__screen-overlay", { state: "detached" });
}

let server;
let browser;
try {
	server = await bootPlayground({ port: 9430 });
	const url = server.serverUrl;
	browser = await chromium.launch({ executablePath: chromePath(), headless: true });
	const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
	const w = watch(page);

	await login(page, url);

	// ---- dashboard: the command palette is gone on every admin screen -----
	await visitClean(page, w, `${url}/wp-admin/index.php`, "dashboard");
	t.check(
		"dashboard: no command palette script",
		!(await page.locator('script[src*="/wp-includes/js/dist/commands"]').count()),
	);

	// ---- the post editor ---------------------------------------------------
	await visitClean(page, w, `${url}/wp-admin/post-new.php`, "post-new.php");
	await waitForEditor(page);

	// Package exports a page builder built for the WordPress 6.1–6.6 packages
	// reads on the post editor (what Cwicly 1.4.4's bundle binds), as `wp.<pkg>.<name>`,
	// plus the post-18.5 exports assets/js/block-editor-compat.js backports.
	const BUILDER_EXPORTS = [
		"editPost.PluginSidebar",
		"editPost.PluginSidebarMoreMenuItem",
		"preferences.store",
		"blockEditor.__experimentalUseBlockPreview",
		"blockEditor.useHasRecursion",
		"blockEditor.__experimentalLinkControl",
		"components.__experimentalNavigationBackButton",
		"compose.useCopyToClipboard",
		"coreData.useEntityRecord",
		"keyboardShortcuts.useShortcut",
		// Backported by the shim. Secure Custom Fields calls this from an
		// `editor.BlockEdit` filter, so without it every block in the post
		// crashes to "This block has encountered an error".
		"blockEditor.useBlockBindingsUtils",
	];
	const facts = await page.evaluate((wanted: string[]) => {
		const g = globalThis as unknown as {
			React: { version: string };
			wp: Record<string, Record<string, unknown> | undefined> & {
				blocks: {
					getBlockType: (
						n: string,
					) => { apiVersion?: number; supports?: { inserter?: boolean } } | undefined;
					getBlockTypes: () => unknown[];
				};
			};
		};
		return {
			react: g.React.version,
			editPostSrc:
				(document.getElementById("wp-edit-post-js") as HTMLScriptElement | null)?.src ?? "",
			blocksSrc: (document.getElementById("wp-blocks-js") as HTMLScriptElement | null)?.src ?? "",
			paragraphApi: g.wp.blocks.getBlockType("core/paragraph")?.apiVersion,
			listItem: g.wp.blocks.getBlockType("core/list-item"),
			accordion: g.wp.blocks.getBlockType("core/accordion"),
			timeToRead: g.wp.blocks.getBlockType("core/post-time-to-read"),
			tocInserter: g.wp.blocks.getBlockType("core/table-of-contents")?.supports?.inserter,
			blockCount: g.wp.blocks.getBlockTypes().length,
			missingExports: wanted.filter((path) => {
				const [pkg, name] = path.split(".") as [string, string];
				return g.wp[pkg]?.[name] === undefined;
			}),
		};
	}, BUILDER_EXPORTS);
	t.check("editor runs on React 18.3.1", facts.react === "18.3.1", facts.react);
	t.check(
		"wp-edit-post is served from the plugin",
		facts.editPostSrc.includes(`/plugins/${PLUGIN_SLUG}/assets/gutenberg/build/edit-post/`),
		facts.editPostSrc,
	);
	t.check(
		"wp-blocks is served from the plugin",
		facts.blocksSrc.includes(`/plugins/${PLUGIN_SLUG}/assets/gutenberg/build/blocks/`),
		facts.blocksSrc,
	);
	t.check(
		"core/paragraph registered client-side as apiVersion 3",
		facts.paragraphApi === 3,
		String(facts.paragraphApi),
	);
	t.check("core/list-item is registered client-side", facts.listItem !== undefined);
	t.check("core/accordion (7.x-only) is not registered client-side", facts.accordion === undefined);
	t.check(
		"core/post-time-to-read is registered client-side (core backs it server-side)",
		facts.timeToRead !== undefined,
	);
	t.check(
		"core/table-of-contents is hidden from the inserter",
		facts.tocInserter === false,
		String(facts.tocInserter),
	);
	t.check(
		"a 6.6-era number of block types is registered",
		facts.blockCount > 85 && facts.blockCount < 110,
		String(facts.blockCount),
	);
	t.check(
		"every export a 6.6-era page builder reads is present",
		facts.missingExports.length === 0,
		facts.missingExports.join(","),
	);

	// Insert paragraph + list + quote (the attribute shapes that differ most
	// from current core), save, and check what reached the database.
	const postId = await page.evaluate(async () => {
		const g = globalThis as unknown as {
			wp: {
				blocks: {
					createBlock: (n: string, a: Record<string, unknown>, inner?: unknown[]) => unknown;
				};
				data: {
					dispatch: (s: string) => {
						insertBlocks: (b: unknown[]) => Promise<void>;
						editPost: (e: Record<string, unknown>) => void;
						savePost: () => Promise<void>;
					};
					select: (s: string) => {
						isSavingPost: () => boolean;
						isAutosavingPost: () => boolean;
						getCurrentPostId: () => number;
						didPostSaveRequestSucceed: () => boolean;
					};
				};
			};
		};
		const { blocks, data } = g.wp;
		await data
			.dispatch("core/block-editor")
			.insertBlocks([
				blocks.createBlock("core/paragraph", { content: "Hello from 18.5" }),
				blocks.createBlock("core/list", { ordered: false }, [
					blocks.createBlock("core/list-item", { content: "One" }),
					blocks.createBlock("core/list-item", { content: "Two" }),
				]),
				blocks.createBlock("core/quote", { citation: "Cited" }, [
					blocks.createBlock("core/paragraph", { content: "Quoted" }),
				]),
			]);
		data.dispatch("core/editor").editPost({ title: "Round trip" });
		await data.dispatch("core/editor").savePost();
		const editor = data.select("core/editor");
		for (let i = 0; i < 100 && (editor.isSavingPost() || editor.isAutosavingPost()); i++) {
			await new Promise((r) => setTimeout(r, 100));
		}
		return editor.didPostSaveRequestSucceed() ? editor.getCurrentPostId() : 0;
	});
	t.check("the post saved", postId > 0, String(postId));
	t.check(
		"saving raised no page errors",
		w.pageErrors.length === 0,
		w.pageErrors.slice(0, 3).join(" | "),
	);

	const saved = await phpJson<{ content: string; title: string }>(
		server,
		`$p = get_post(${postId}); return ['content' => (string) $p?->post_content, 'title' => (string) $p?->post_title];`,
	);
	t.check(
		"saved content has the paragraph",
		saved.value.content.includes("<!-- wp:paragraph -->"),
		saved.value.content.slice(0, 120),
	);
	t.check(
		"saved content has a 6.6-style list (list-item inner blocks)",
		saved.value.content.includes("<!-- wp:list -->") &&
			/<!-- wp:list-item -->\s*<li>One<\/li>/.test(saved.value.content),
		saved.value.content.slice(0, 300),
	);
	t.check(
		"saved content has the quote with its citation",
		/<!-- wp:quote -->[\s\S]*<cite>Cited<\/cite>/.test(saved.value.content),
	);
	t.check("saved title", saved.value.title === "Round trip", saved.value.title);

	// Reopen: every block must validate against the 18.5 definitions.
	await visitClean(
		page,
		w,
		`${url}/wp-admin/post.php?post=${postId}&action=edit`,
		"post.php (reopen)",
	);
	await waitForEditor(page);
	const reopened = await page.evaluate(() => {
		const g = globalThis as unknown as {
			wp: {
				data: { select: (s: string) => { getBlocks: () => { name: string; isValid: boolean }[] } };
			};
		};
		return g.wp.data
			.select("core/block-editor")
			.getBlocks()
			.map((b) => `${b.name}:${b.isValid}`);
	});
	t.check(
		"reopened post has the three blocks, all valid",
		reopened.join(",") === "core/paragraph:true,core/list:true,core/quote:true",
		reopened.join(","),
	);

	// The inserter and its Patterns tab (patterns come over REST; 18.5 lists
	// categories first).
	await page.click(".editor-document-tools__inserter-toggle");
	await page.waitForSelector(".block-editor-inserter__menu", { timeout: 15_000 });
	await page.getByRole("tab", { name: "Patterns" }).click();
	// Categories are tabs; the list behind one renders its previews lazily.
	await page
		.locator(".block-editor-inserter__menu")
		.getByRole("tab", { name: "All", exact: true })
		.click();
	await page
		.waitForSelector(".block-editor-block-patterns-list__list-item", { timeout: 20_000 })
		.catch(() => undefined);
	t.check(
		"opening the inserter and its Patterns tab raised no errors",
		w.pageErrors.length === 0 && w.consoleErrors.length === 0,
		[...w.pageErrors, ...w.consoleErrors].slice(0, 3).join(" | "),
	);
	const patternCount = await page.locator(".block-editor-block-patterns-list__list-item").count();
	t.check("patterns are listed", patternCount > 0, String(patternCount));
	const patternNames = await page.evaluate(() =>
		(
			globalThis as unknown as {
				wp: { data: { select: (s: string) => { getBlockPatterns: () => { name: string }[] } } };
			}
		).wp.data
			.select("core")
			.getBlockPatterns()
			.map((p) => p.name),
	);
	t.check(
		"the client fetched theme and core query patterns, but no 7.x overlays",
		patternNames.some((n) => n.startsWith(`${THEME}/`)) &&
			patternNames.includes("core/query-standard-posts") &&
			!patternNames.some((n) => n.startsWith("core/navigation-overlay")),
		patternNames.filter((n) => n.startsWith("core/")).join(","),
	);

	// ---- the Site Editor: 18.5's edit-site on 7.x's site-editor.php ------
	// Every link core emits (`?p=/…`) must land on the 18.5 view it names.
	const siteEditorUrls: [string, string, (s: SiteEditorState) => boolean][] = [
		["site-editor.php", "home", (s) => s.screen === "Design"],
		[
			"site-editor.php?p=%2Ftemplate",
			"templates",
			(s) => s.screen === "Templates" && s.search.includes("postType=wp_template"),
		],
		[
			"site-editor.php?p=%2Fpattern",
			"patterns",
			(s) => s.screen === "Patterns" && s.search.includes("postType=wp_block"),
		],
		[
			"site-editor.php?p=%2Fstyles",
			"styles",
			(s) => s.screen === "Styles" && s.search.includes("path=/wp_global_styles"),
		],
		[
			"site-editor.php?p=%2Fnavigation",
			"navigation",
			(s) => s.screen === "Navigation" && s.search.includes("postType=wp_navigation"),
		],
		[
			"site-editor.php?p=%2Fpage",
			"pages",
			(s) => s.screen === "Pages" && s.search.includes("postType=page"),
		],
		[
			`site-editor.php?p=%2Fwp_template_part%2F${encodeURIComponent(`${THEME}//header`)}&canvas=edit`,
			"template part canvas",
			(s) => s.canvas && s.search.includes("postType=wp_template_part") && /Header/.test(s.title),
		],
		[
			"site-editor.php?p=%2Fpage%2F2&canvas=edit",
			"page canvas",
			(s) => s.canvas && s.search.includes("postId=2") && s.canvasText.includes("Sample Page"),
		],
		// The 18.5 form itself (what the front-end admin bar and the router emit);
		// core 302s it to `?p=` and the shim brings it back.
		[
			`site-editor.php?postType=wp_template&postId=${encodeURIComponent(`${THEME}//index`)}&canvas=edit`,
			"reloaded 18.5 URL",
			(s) => s.canvas && s.search.includes(`postId=${THEME}//index`) && /Index/.test(s.title),
		],
	];
	for (const [path, label, ok] of siteEditorUrls) {
		await visitClean(page, w, `${url}/wp-admin/${path}`, `site editor (${label})`);
		await waitForSiteEditor(page, label.includes("canvas") || label.includes("reloaded"));
		const state = await siteEditorState(page);
		t.check(
			`site editor: ${label} is the 18.5 view core's URL names`,
			ok(state),
			JSON.stringify(state),
		);
	}
	const siteFacts = await page.evaluate(() => ({
		editSiteSrc:
			(document.getElementById("wp-edit-site-js") as HTMLScriptElement | null)?.src ?? "",
		react: (globalThis as unknown as { React: { version: string } }).React.version,
	}));
	t.check(
		"wp-edit-site is served from the plugin",
		siteFacts.editSiteSrc.includes(`/plugins/${PLUGIN_SLUG}/assets/gutenberg/build/edit-site/`),
		siteFacts.editSiteSrc,
	);
	t.check("the site editor runs on React 18.3.1", siteFacts.react === "18.3.1", siteFacts.react);

	// Navigate in-app, then reload: the router's own URL survives core's redirect.
	await page
		.locator(".edit-site-site-hub, .edit-site-layout__hub")
		.first()
		.waitFor({ timeout: 10_000 })
		.catch(() => undefined);
	await visitClean(page, w, `${url}/wp-admin/site-editor.php`, "site editor (hub)");
	await waitForSiteEditor(page, false);
	await page
		.locator(".edit-site-sidebar-navigation-item", { hasText: "Templates" })
		.first()
		.click();
	await page.waitForTimeout(2500);
	t.check(
		"clicking Templates moves the URL to the 18.5 form",
		(await siteEditorState(page)).search.includes("postType=wp_template"),
	);
	await page.reload({ waitUntil: "load" });
	await waitForSiteEditor(page, false);
	const afterReload = await siteEditorState(page);
	t.check(
		"reloading lands back on Templates",
		afterReload.screen === "Templates" && afterReload.search.includes("postType=wp_template"),
		JSON.stringify(afterReload),
	);

	// Edit the home template, save, and confirm both the database and the front end.
	await visitClean(
		page,
		w,
		`${url}/wp-admin/site-editor.php?p=%2Fwp_template%2F${encodeURIComponent(`${THEME}//home`)}&canvas=edit`,
		"site editor (home template canvas)",
	);
	await waitForSiteEditor(page, true);
	await page
		.waitForFunction(
			() =>
				(
					(document.querySelector('iframe[name="editor-canvas"]') as HTMLIFrameElement | null)
						?.contentDocument?.body?.innerText ?? ""
				).includes("Sample Page"),
			undefined,
			{ timeout: 30_000 },
		)
		.catch(() => undefined);
	t.check(
		"the header's navigation block resolved its fallback menu in the canvas",
		(await siteEditorState(page)).canvasText.includes("Sample Page"),
	);
	const marker = `downgrade-fse-${Date.now()}`;
	const dirty = await page.evaluate(async (text) => {
		const g = globalThis as unknown as {
			wp: {
				blocks: { createBlock: (n: string, a: Record<string, unknown>) => unknown };
				data: {
					dispatch: (s: string) => { insertBlock: (b: unknown, i: number) => void };
					select: (s: string) => {
						__experimentalGetDirtyEntityRecords: () => {
							kind: string;
							name: string;
							key: string;
						}[];
					};
				};
			};
		};
		g.wp.data
			.dispatch("core/block-editor")
			.insertBlock(g.wp.blocks.createBlock("core/paragraph", { content: text }), 0);
		await new Promise((r) => setTimeout(r, 500));
		return g.wp.data
			.select("core")
			.__experimentalGetDirtyEntityRecords()
			.map((e) => `${e.kind}/${e.name}/${e.key}`);
	}, marker);
	t.check(
		"inserting a block dirties the wp_template entity",
		dirty.some((d) => d.startsWith("postType/wp_template/")),
		dirty.join(","),
	);
	await saveSiteEditor(page);
	t.check(
		"saving the template raised no page errors",
		w.pageErrors.length === 0,
		w.pageErrors.slice(0, 3).join(" | "),
	);
	const savedTemplate = await phpJson<{ found: boolean; hasMarker: boolean }>(
		server,
		`$posts = get_posts(['post_type' => 'wp_template', 'post_status' => 'any', 'numberposts' => -1, 'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => get_stylesheet()]]]);
		$home = array_values(array_filter($posts, fn($p) => $p->post_name === 'home'));
		return ['found' => $home !== [], 'hasMarker' => $home !== [] && str_contains($home[0]->post_content, ${JSON.stringify(marker)})];`,
	);
	t.check(
		"the home template was saved as a wp_template post with the new block",
		savedTemplate.value.found && savedTemplate.value.hasMarker,
		JSON.stringify(savedTemplate.value),
	);
	await visitClean(page, w, `${url}/`, "front end (block theme)");
	const frontTemplate = await page.evaluate(
		(m) => ({
			marker: document.body.innerText.includes(m),
			pluginAssets: [...document.querySelectorAll("script[src], link[href]")].filter((el) =>
				(el.getAttribute("src") ?? el.getAttribute("href") ?? "").includes(
					"/plugins/gutenberg-downgrade/",
				),
			).length,
		}),
		marker,
	);
	t.check("the front end renders the saved template change", frontTemplate.marker);
	t.check(
		"the block theme front end loads nothing from the plugin",
		frontTemplate.pluginAssets === 0,
		String(frontTemplate.pluginAssets),
	);

	// Global styles: edit through core-data (what the Styles panel dispatches), save, verify.
	await visitClean(
		page,
		w,
		`${url}/wp-admin/site-editor.php?p=%2Fstyles&canvas=edit`,
		"site editor (styles canvas)",
	);
	await waitForSiteEditor(page, true);
	const gsDirty = await page.evaluate(async () => {
		const g = globalThis as unknown as {
			wp: {
				data: {
					select: (s: string) => {
						__experimentalGetCurrentGlobalStylesId: () => number;
						getEditedEntityRecord: (
							k: string,
							n: string,
							id: number,
						) => { styles?: Record<string, unknown> };
						__experimentalGetDirtyEntityRecords: () => { kind: string; name: string }[];
					};
					dispatch: (s: string) => {
						editEntityRecord: (
							k: string,
							n: string,
							id: number,
							e: Record<string, unknown>,
						) => void;
					};
				};
			};
		};
		const id = g.wp.data.select("core").__experimentalGetCurrentGlobalStylesId();
		const styles =
			g.wp.data.select("core").getEditedEntityRecord("root", "globalStyles", id).styles ?? {};
		g.wp.data.dispatch("core").editEntityRecord("root", "globalStyles", id, {
			styles: {
				...styles,
				color: {
					...(styles["color"] as Record<string, unknown> | undefined),
					background: "#123456",
				},
			},
		});
		await new Promise((r) => setTimeout(r, 500));
		return g.wp.data
			.select("core")
			.__experimentalGetDirtyEntityRecords()
			.map((e) => `${e.kind}/${e.name}`);
	});
	t.check(
		"editing global styles dirties the globalStyles entity",
		gsDirty.includes("root/globalStyles"),
		gsDirty.join(","),
	);
	await saveSiteEditor(page);
	const savedStyles = await phpJson<{ bg: string | null }>(
		server,
		`$data = json_decode((string) get_post(WP_Theme_JSON_Resolver::get_user_global_styles_post_id())?->post_content, true);
		return ['bg' => $data['styles']['color']['background'] ?? null];`,
	);
	t.check(
		"global styles persisted",
		savedStyles.value.bg === "#123456",
		JSON.stringify(savedStyles.value),
	);

	// Patterns: create one the way 18.5's dialog does, then open it through core's 7.x link.
	await visitClean(
		page,
		w,
		`${url}/wp-admin/site-editor.php?p=%2Fpattern`,
		"site editor (patterns)",
	);
	await waitForSiteEditor(page, false);
	const pattern = await page.evaluate(async () => {
		const g = globalThis as unknown as {
			wp: {
				data: {
					dispatch: (s: string) => {
						saveEntityRecord: (
							k: string,
							n: string,
							r: Record<string, unknown>,
						) => Promise<{ id?: number } | undefined>;
					};
				};
			};
		};
		const record = await g.wp.data.dispatch("core").saveEntityRecord("postType", "wp_block", {
			title: "Downgrade pattern",
			content: "<!-- wp:paragraph --><p>pattern body</p><!-- /wp:paragraph -->",
			status: "publish",
			meta: { wp_pattern_sync_status: "unsynced" },
		});
		return record?.id ?? 0;
	});
	t.check("an unsynced pattern can be created (18.5's payload)", pattern > 0, String(pattern));
	await visitClean(
		page,
		w,
		`${url}/wp-admin/site-editor.php?p=%2Fwp_block%2F${pattern}&canvas=edit`,
		"site editor (pattern canvas)",
	);
	await waitForSiteEditor(page, true);
	const patternState = await siteEditorState(page);
	t.check(
		"the pattern opens in the canvas through its 7.x edit link",
		patternState.canvas &&
			patternState.search.includes("postType=wp_block") &&
			patternState.canvasText.includes("pattern body"),
		JSON.stringify(patternState),
	);

	// ---- other package-driven admin screens --------------------------------
	await visitClean(page, w, `${url}/wp-admin/edit.php?post_type=wp_block`, "reusable blocks list");
	await visitClean(page, w, `${url}/wp-admin/upload.php`, "media library");

	// ---- the screens only a classic theme has ------------------------------
	await switchTheme(server, CLASSIC_THEME);
	await visitClean(
		page,
		w,
		`${url}/wp-admin/site-editor.php`,
		"site-editor.php on a classic theme",
	);
	await waitForSiteEditor(page, false);
	const classicSiteEditor = await siteEditorState(page);
	t.check(
		"a classic theme is sent to the patterns view, as 6.6 did",
		classicSiteEditor.screen === "Patterns" &&
			classicSiteEditor.search.includes("postType=wp_block"),
		JSON.stringify(classicSiteEditor),
	);
	await visitClean(page, w, `${url}/wp-admin/widgets.php`, "widgets.php");
	t.check(
		"the block widgets editor mounted",
		(await page.locator(".edit-widgets-layout, .edit-widgets-main-block-list").count()) > 0,
	);
	await visitClean(page, w, `${url}/wp-admin/customize.php`, "customize.php");
	t.check(
		"the customizer controls rendered",
		(await page.locator("#customize-controls").count()) > 0,
	);

	// ---- the front end keeps core's stack and renders the post -------------
	await visitClean(page, w, `${url}/?p=${postId}`, "front end");
	const front = await page.evaluate(() => ({
		quote: document.querySelector(".wp-block-quote cite")?.textContent ?? "",
		listItems: document.querySelectorAll(".entry-content ul li").length,
		pluginAssets: [...document.querySelectorAll("script[src], link[href]")].filter((el) =>
			(el.getAttribute("src") ?? el.getAttribute("href") ?? "").includes(
				"/plugins/gutenberg-downgrade/",
			),
		).length,
	}));
	t.check("front end renders the quote citation", front.quote === "Cited", front.quote);
	t.check("front end renders the list items", front.listItems === 2, String(front.listItems));
	t.check(
		"front end loads nothing from the plugin",
		front.pluginAssets === 0,
		String(front.pluginAssets),
	);
} catch (err) {
	console.error(`\nUnexpected error: ${err instanceof Error ? err.message : String(err)}`);
	t.check(
		"test completed without an unexpected error",
		false,
		err instanceof Error ? err.message : String(err),
	);
} finally {
	if (browser) await browser.close();
	if (server) await server[Symbol.asyncDispose]();
}

console.log(t.failures ? `\nTest B FAILED (${t.failures})` : "\nTest B PASSED");
process.exit(t.failures ? 1 : 0);
