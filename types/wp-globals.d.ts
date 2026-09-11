// Ambient globals for assets/js/compat.js. No imports/exports at top level keeps
// this a script file, so the Window merge applies globally without `declare global`.

interface BlockSettings {
	supports?: Record<string, unknown>;
	[key: string]: unknown;
}

interface WpBlocks {
	registerBlockBindingsSource?: (...args: unknown[]) => void;
	[key: string]: unknown;
}

interface WpHooks {
	addFilter: (
		hookName: string,
		namespace: string,
		callback: (...args: any[]) => unknown,
		priority?: number,
	) => void;
}

interface Wp {
	blocks?: WpBlocks;
	hooks?: WpHooks;
}

interface Window {
	wp?: Wp;
	/** Block names injected by ScriptOverrides::exposeHiddenBlocks(). */
	gutenbergDowngradeHiddenBlocks?: string[];
}
