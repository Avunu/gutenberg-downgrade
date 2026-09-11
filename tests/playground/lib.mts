// Shared helpers for the wp-playground harness: boot WordPress with the built
// plugin mounted, run PHP against it, and locate a browser for the browser pass.
import { runCLI, type RunCLIServer } from "@wp-playground/cli";
import { existsSync, realpathSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

export const HARNESS_DIR = dirname(fileURLToPath(import.meta.url));
export const REPO_ROOT = resolve(HARNESS_DIR, "..", "..");
export const PLUGIN_SLUG = "gutenberg-downgrade";
export const PLUGIN_PATH = `${PLUGIN_SLUG}/${PLUGIN_SLUG}.php`;
export const PLUGIN_VFS = `/wordpress/wp-content/plugins/${PLUGIN_SLUG}`;

/** A classic theme, so the editor screens are the ones this plugin targets. */
export const THEME = "twentytwentyone";

export const WP_VERSION = process.env.WP_VERSION ?? "latest";
/** The plugin declares `Requires PHP: 8.3`; WordPress enforces it on activation. */
export const PHP_VERSION = (process.env.PHP_VERSION ?? "8.4") as "8.3" | "8.4";

/**
 * The built plugin directory: `nix build .#default` output by default. This is exactly what the
 * release zip contains (vendor/ and assets/gutenberg/ included), so the harness never tests a
 * half-installed checkout.
 */
export function pluginDir(): string {
	const dir =
		process.env.GUTENBERG_DOWNGRADE_PLUGIN_DIR ??
		resolve(REPO_ROOT, "result/share/wordpress/plugins", PLUGIN_SLUG);
	for (const required of [
		"gutenberg-downgrade.php",
		"vendor/autoload.php",
		"assets/gutenberg/manifest.php",
	]) {
		if (!existsSync(resolve(dir, required))) {
			throw new Error(
				`${resolve(dir, required)} is missing — run \`nix build .#default\` (or point GUTENBERG_DOWNGRADE_PLUGIN_DIR at a built plugin).`,
			);
		}
	}
	// Playground mounts real directories; `result` is a symlink into the store.
	return realpathSync(dir);
}

/**
 * Boot a playground server with the built plugin mounted and activated on a classic theme, WP_DEBUG
 * on and displayed so PHP notices from the vendored 18.5 code surface in the page. Dispose with
 * `await server[Symbol.asyncDispose]()`.
 */
export async function bootPlayground({
	port = 9400,
}: { port?: number } = {}): Promise<RunCLIServer> {
	return runCLI({
		command: "server",
		php: PHP_VERSION,
		wp: WP_VERSION,
		port,
		login: true,
		quiet: true,
		// Playground already defaults WP_DEBUG on; display it too so PHP notices
		// from the vendored 18.5 code surface in the pages the browser pass loads.
		"define-bool": { WP_DEBUG_DISPLAY: true },
		mount: [
			{ hostPath: pluginDir(), vfsPath: PLUGIN_VFS },
			{ hostPath: resolve(HARNESS_DIR, "mu-plugins"), vfsPath: "/wordpress/wp-content/mu-plugins" },
		],
		blueprint: {
			steps: [
				{
					step: "installTheme",
					themeData: { resource: "wordpress.org/themes", slug: THEME },
					options: { activate: true },
				},
				{ step: "activatePlugin", pluginPath: PLUGIN_PATH },
			],
		},
	});
}

const MARK = "@@GBD@@";

export interface PhpContext {
	/** Constants defined before wp-load (REST_REQUEST, WP_ADMIN, …). */
	constants?: Record<string, string | boolean | number>;
	/**
	 * Simulate a wp-admin screen: defines WP_ADMIN and points PHP_SELF at /wp-admin/<page>, which is
	 * where core derives $pagenow. (REQUEST_URI is left alone: an admin URL there makes the bootstrap
	 * redirect.)
	 */
	adminPage?: string;
}

/**
 * Run a PHP function body against the booted WordPress (full bootstrap: wp-load fires
 * plugins_loaded and init, so the plugin's hooks run) and return its JSON-encoded result.
 *
 * PHP notices/deprecations raised during the snippet are collected and returned alongside the value
 * as `notices` (prefixed with the E_* code), so every caller can assert on them.
 */
export async function phpJson<T = unknown>(
	server: RunCLIServer,
	body: string,
	{ constants = {}, adminPage }: PhpContext = {},
): Promise<{ value: T; notices: string[] }> {
	const allConstants: Record<string, string | boolean | number> = adminPage
		? { WP_ADMIN: true, ...constants }
		: constants;
	const defines = Object.entries(allConstants)
		.map(([name, value]) => `define(${JSON.stringify(name)}, ${JSON.stringify(value)});`)
		.join("\n");
	const serverVars = adminPage
		? ["PHP_SELF", "SCRIPT_NAME"]
				.map((k) => `$_SERVER[${JSON.stringify(k)}] = ${JSON.stringify(`/wp-admin/${adminPage}`)};`)
				.join("\n")
		: "";
	const code = `<?php
${defines}
${serverVars}
$__notices = [];
set_error_handler(function (int $errno, string $errstr, string $errfile = '', int $errline = 0) use (&$__notices): bool {
	// Playground predefines its own constants and lets wp-config.php redefine them.
	if (preg_match('/^Constant \\w+ already defined$/', $errstr) === 1) {
		return true;
	}
	$__notices[] = sprintf('[%d] %s in %s:%d', $errno, $errstr, basename($errfile), $errline);
	return true;
});
ob_start();
require '/wordpress/wp-load.php';
$__value = (function () {
	global $wpdb, $wp_scripts, $wp_styles, $pagenow;
	${body}
})();
ob_end_clean();
restore_error_handler();
echo ${JSON.stringify(MARK)} . json_encode(['value' => $__value, 'notices' => $__notices], JSON_INVALID_UTF8_SUBSTITUTE) . ${JSON.stringify(MARK)};`;

	let text: string;
	try {
		const res = await server.playground.run({ code });
		text = res.text ?? "";
	} catch (err) {
		// PHP fatals surface as a thrown error whose message dumps the whole
		// response; extract the "Fatal error: …" line WordPress prints.
		const raw = String(err instanceof Error ? err.message : err);
		const fatal =
			/<b>Fatal error<\/b>:\s*(.*?)(?:<br|\s+in\s+<b>)/is.exec(raw) ??
			/Fatal error:\s*(.*)/i.exec(raw);
		throw new Error(
			fatal ? `PHP fatal error: ${fatal[1]?.trim()}` : `PHP execution failed: ${raw.slice(0, 400)}`,
			{ cause: err },
		);
	}
	const start = text.indexOf(MARK);
	const end = text.lastIndexOf(MARK);
	if (start === -1 || end === start) {
		throw new Error(`PHP produced no marked JSON. Raw output:\n${text.slice(0, 2000)}`);
	}
	const json = text.slice(start + MARK.length, end).trim();
	try {
		return JSON.parse(json) as { value: T; notices: string[] };
	} catch (cause) {
		throw new Error(`PHP output was not valid JSON: ${json.slice(0, 2000)}`, { cause });
	}
}

/** System Chrome — no Playwright browser download. Override with CHROME_PATH. */
export function chromePath(): string {
	if (process.env.CHROME_PATH) {
		return process.env.CHROME_PATH;
	}
	const candidates = ["google-chrome-stable", "google-chrome", "chromium", "chromium-browser"];
	for (const name of candidates) {
		for (const dir of (process.env.PATH ?? "").split(":")) {
			const candidate = resolve(dir, name);
			if (dir !== "" && existsSync(candidate)) {
				return candidate;
			}
		}
	}
	throw new Error(`No Chrome/Chromium found on PATH (${candidates.join(", ")}); set CHROME_PATH.`);
}
