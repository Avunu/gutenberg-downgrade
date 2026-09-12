// Ambient globals for the assets/js shims. No imports/exports at top level keeps
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

/** Only the members block-editor-compat.js reads; both packages export far more. */
interface WpBlockEditor {
	useBlockBindingsUtils?: (clientId?: string) => unknown;
	useBlockEditContext: () => { clientId?: string };
	[key: string]: unknown;
}

interface WpDataStoreDispatch {
	updateBlockAttributes: (
		clientId: string | undefined,
		attributes: Record<string, unknown>,
	) => void;
}

interface WpDataStoreSelect {
	getBlockAttributes: (clientId: string | undefined) => Record<string, unknown> | undefined;
}

interface WpData {
	useDispatch: (store: string) => WpDataStoreDispatch;
	useRegistry: () => { select: (store: string) => WpDataStoreSelect };
}

interface Wp {
	blocks?: WpBlocks;
	hooks?: WpHooks;
	blockEditor?: WpBlockEditor;
	data?: WpData;
}

interface Window {
	wp?: Wp;
	/** Block names injected by ScriptOverrides::exposeHiddenBlocks(). */
	gutenbergDowngradeHiddenBlocks?: string[];
}
