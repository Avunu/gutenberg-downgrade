// Test B — headless Chrome against the booted WordPress. This is the only test
// that exercises the 18.5 bundles as a browser would: the editor must mount,
// throw nothing, run on React 18.3, and round-trip a post through save and reload.
import { chromium, type Page } from "playwright-core";
import {
	bootPlayground,
	chromePath,
	phpJson,
	PLUGIN_SLUG,
	PHP_VERSION,
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
	// every click below.
	const close = page.locator(".components-modal__screen-overlay .components-modal__header button");
	if (await close.count()) {
		await close.first().click();
		await page.waitForSelector(".components-modal__screen-overlay", { state: "detached" });
	}
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
	// reads on the post editor (what Cwicly 1.4.4's bundle binds), as `wp.<pkg>.<name>`.
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
		patternNames.some((n) => n.startsWith("twentytwentyone/")) &&
			patternNames.includes("core/query-standard-posts") &&
			!patternNames.some((n) => n.startsWith("core/navigation-overlay")),
		patternNames.filter((n) => n.startsWith("core/")).join(","),
	);

	// ---- other package-driven admin screens --------------------------------
	await visitClean(page, w, `${url}/wp-admin/edit.php?post_type=wp_block`, "reusable blocks list");
	await visitClean(page, w, `${url}/wp-admin/upload.php`, "media library");
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
