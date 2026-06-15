#! /usr/bin/env node

/* eslint no-console: 0 */

/**
 * Scaffold engine -- shared project setup for rtCamp WordPress starters.
 *
 * Consumed by a thin `bin/init.js` in each starter (theme / plugin), which calls
 * `run( config, { root } )` with a per-project `scaffold.config.js`. All terminal
 * I/O goes through `@rtcamp/wp-tooling/ui`.
 *
 * Flow: confirm -> name (validated) -> review identity -> rename + search-replace
 * -> apply version -> persist `.wp-scaffold.json` -> composer dump-autoload ->
 * cleanup -> optional git + Husky -> initial commit.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

// Interactive primitives (Wizard, text, confirm, spinner, CancelledError) come
// from the shared wp-tooling UI kit; styled status lines + the details table are
// wp-framework-local (the kit deliberately does not ship those).
const ui = { ...require( '@rtcamp/wp-tooling/ui' ), ...require( './lib/style' ) };

const { generateIdentity } = require( './scaffold/identity' );
const { validateName } = require( './scaffold/validate' );
const { buildReplacements } = require( './scaffold/tokens' );
const { collectFiles, replaceInFiles, renameFiles } = require( './scaffold/replace' );
const { applyVersion } = require( './scaffold/version' );
const { writeIdentityFile, readIdentityFile } = require( './scaffold/persist' );
const { initRepo, commitAll, installHusky } = require( './scaffold/git' );
const { runCleanup } = require( './scaffold/cleanup' );

const DEFAULT_VERSION = '1.0.0';

/**
 * Capitalise the first letter of a word.
 *
 * @param {string} word - Input.
 * @return {string} Capitalised word.
 */
const cap = ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 );

/**
 * Print CLI usage.
 *
 * @param {string} kind - "theme" / "plugin".
 * @return {void}
 */
const printHelp = ( kind ) => {
	console.log( `
Usage: npm run init [-- options]

Set up this ${ kind }: rename the starter tokens to your project name, apply the
version, persist identity to .wp-scaffold.json, then optional git / Husky / cleanup.

Options:
  --name=NAME      Use NAME without prompting (required with --yes).
  --version=VER    Set the project version (default 1.0.0).
  -y, --yes        Accept defaults, no prompts; for CI. Needs --name.
  -c, --clean      Run cleanup only (remove scaffolding files).
  -h, --help       Show this help.
` );
};

/**
 * Parse CLI flags for the setup flow.
 *
 * @param {string[]} argv - Arguments (without --help/--clean).
 * @return {{ flags: Object, unknown: string[] }} Parsed flags and any unrecognised args.
 */
const parseFlags = ( argv ) => {
	const flags = { yes: false };
	const unknown = [];

	argv.forEach( ( arg ) => {
		if ( '--yes' === arg || '-y' === arg ) {
			flags.yes = true;
		} else if ( arg.startsWith( '--name=' ) ) {
			flags.name = arg.slice( '--name='.length );
		} else if ( arg.startsWith( '--version=' ) ) {
			flags.version = arg.slice( '--version='.length );
		} else {
			unknown.push( arg );
		}
	} );

	return { flags, unknown };
};

/**
 * Resolve the full identity, replacement pairs, details table and persisted
 * payload for a chosen name, from the project config.
 *
 * @param {Object} config             - Per-project scaffold config.
 * @param {string} name               - Chosen project name.
 * @param {Object} [overrides]        - Optional { version, vendor } overrides.
 * @return {Object} { target, replacements, namespace, version, details, persistPayload }
 */
const buildContext = ( config, name, overrides = {} ) => {
	const vendor = overrides.vendor || config.vendor || 'rtcamp';
	const target = generateIdentity( name, { vendor } );
	const source = generateIdentity( config.source.name, { vendor } );

	const extra = 'function' === typeof config.extraTokens ? config.extraTokens( target, source ) : {};
	const replacements = buildReplacements( source, target, extra );

	const namespace = 'function' === typeof config.namespace ? config.namespace( target ) : '';
	const version = overrides.version || config.version || DEFAULT_VERSION;

	const details = 'function' === typeof config.details
		? config.details( target, { namespace, version } )
		: {};

	const persistPayload = {
		name: target.name,
		kind: config.kind,
		version,
		slug: target.slug,
		textDomain: target.textDomain,
		package: target.package,
		namespace,
		functionPrefix: target.functionPrefix,
		constantPrefix: target.constantPrefix,
		cssPrefix: target.cssPrefix,
		generatedBy: 'rtcamp/wp-framework scaffold',
	};

	return { target, replacements, namespace, version, details, persistPayload };
};

/**
 * Run `composer dump-autoload` when a composer.json is present.
 *
 * @param {string} root - Project root.
 * @return {void}
 */
const composerDump = ( root ) => {
	if ( ! fs.existsSync( path.join( root, 'composer.json' ) ) ) {
		return;
	}
	const spin = ui.spinner( 'Running composer dump-autoload...' );
	spin.start();
	try {
		execFileSync( 'composer', [ 'dump-autoload' ], { cwd: root, stdio: 'pipe' } );
		spin.succeed( 'Autoloader regenerated' );
	} catch ( err ) {
		spin.fail( 'composer dump-autoload failed (continuing)' );
		ui.warn( err.message );
	}
};

/**
 * Build the ordered wizard steps for the setup flow.
 *
 * @param {Object} config - Per-project scaffold config.
 * @param {string} root   - Project root.
 * @param {Object} ctx    - Shared wizard context.
 * @return {Array<Object>} Wizard steps.
 */
const setupSteps = ( config, root, flags ) => {
	const kind = config.kind || 'project';
	const steps = config.steps || {};
	const existing = readIdentityFile( root );

	return [
		{
			name: 'Confirm',
			async run( c ) {
				if ( existing ) {
					ui.warn( `This ${ kind } is already initialized (.wp-scaffold.json, name: "${ existing.name }").` );
					const again = flags.yes ? true : await ui.confirm( { message: 'Run setup again anyway?', defaultValue: false } );
					if ( ! again ) {
						c.cancelled = true;
						return;
					}
				}
				if ( flags.yes ) {
					return;
				}
				const go = await ui.confirm( { message: `Set up this ${ kind } now?`, defaultValue: false } );
				if ( ! go ) {
					c.cancelled = true;
				}
			},
		},
		{
			name: 'Project name',
			skip: ( c ) => c.cancelled,
			async run( c ) {
				if ( flags.name ) {
					const err = validateName( flags.name );
					if ( err ) {
						ui.error( `--name: ${ err }` );
						c.cancelled = true;
						process.exitCode = 1;
						return;
					}
					c.name = flags.name.trim();
					return;
				}
				c.name = await ui.text( {
					message: `Enter ${ kind } name (shown in WordPress admin)`,
					validate: validateName,
				} );
			},
		},
		{
			name: 'Review',
			skip: ( c ) => c.cancelled,
			async run( c ) {
				const overrides = { version: flags.version };

				// Re-render the identity until the user confirms or cancels.
				for ( ;; ) {
					Object.assign( c, buildContext( config, c.name, overrides ) );
					ui.table( c.details, { title: `${ cap( kind ) } details` } );

					if ( flags.yes ) {
						return;
					}

					const ok = await ui.confirm( { message: 'Looks good?', defaultValue: true } );
					if ( ok ) {
						return;
					}

					const choice = await ui.radio( {
						message: 'What would you like to change?',
						choices: [ 'Name', 'Version', 'Cancel setup' ],
					} );

					if ( 'Cancel setup' === choice ) {
						c.cancelled = true;
						ui.warn( 'Setup cancelled. Nothing was changed.' );
						return;
					}
					if ( 'Name' === choice ) {
						c.name = await ui.text( { message: `${ cap( kind ) } name`, defaultValue: c.name, validate: validateName } );
					} else if ( 'Version' === choice ) {
						overrides.version = await ui.text( { message: 'Version', defaultValue: c.version } );
					}
				}
			},
		},
		{
			name: 'Apply identity',
			skip: ( c ) => c.cancelled,
			async run( c ) {
				const files = collectFiles( root );
				const changed = replaceInFiles( files, c.replacements, ui );
				const renamed = renameFiles( files, c.replacements, ui );
				ui.success( `Updated ${ changed } file(s), renamed ${ renamed } file(s)` );
			},
		},
		{
			name: 'Apply version',
			skip: ( c ) => c.cancelled || ! config.versionFiles,
			async run( c ) {
				// Resolve any function paths against the chosen identity, since
				// files may have just been renamed in the previous step.
				const files = config.versionFiles.map( ( spec ) => ( {
					...spec,
					path: 'function' === typeof spec.path ? spec.path( c.target ) : spec.path,
				} ) );
				applyVersion( root, files, c.version, ui );
			},
		},
		{
			name: 'Persist identity',
			skip: ( c ) => c.cancelled,
			async run( c ) {
				writeIdentityFile( root, { ...c.persistPayload, generatedAt: new Date().toISOString() }, ui );
			},
		},
		{
			name: 'Regenerate autoloader',
			skip: ( c ) => c.cancelled || ! steps.composer,
			async run() {
				composerDump( root );
			},
		},
		{
			name: 'Cleanup',
			skip: ( c ) => c.cancelled || ! steps.cleanup,
			async run() {
				runCleanup( root, ( config.cleanup && config.cleanup.targets ) || [], ui );
			},
		},
		{
			name: 'Git',
			skip: ( c ) => c.cancelled || ! steps.git,
			async run( c ) {
				const go = flags.yes
					? false
					: await ui.confirm( {
						message: 'Initialize a git repository? (removes any existing .git)',
						defaultValue: false,
					} );
				if ( go ) {
					c.gitReady = initRepo( root, ui );
				}
			},
		},
		{
			name: 'Husky',
			skip: ( c ) => c.cancelled || ! c.gitReady || ! steps.husky,
			async run() {
				const go = flags.yes ? true : await ui.confirm( { message: 'Install Husky git hooks?', defaultValue: true } );
				if ( go ) {
					installHusky( root, ui );
				}
			},
		},
		{
			name: 'Commit',
			skip: ( c ) => c.cancelled || ! c.gitReady,
			async run() {
				commitAll( root, `Initialize project using ${ config.repoUrl || 'rtcamp/wp-framework scaffold' }`, ui );
			},
		},
	];
};

/**
 * Run the interactive setup flow.
 *
 * @param {Object} config - Per-project scaffold config.
 * @param {string} root   - Project root.
 * @return {Promise<void>}
 */
const setupFlow = async ( config, root, flags ) => {
	const kind = config.kind || 'project';
	ui.heading( `${ cap( kind ) } setup` );

	const ctx = { cancelled: false };
	await new ui.Wizard( setupSteps( config, root, flags ), ctx ).run();

	if ( ctx.cancelled ) {
		ui.warn( '\nNothing was changed.' );
		return;
	}

	ui.heading( 'Done' );
	ui.success( `Your new ${ kind } is ready.` );
	if ( config.docsUrl ) {
		ui.info( `Docs: ${ config.docsUrl }` );
	}
};

/**
 * Run the cleanup-only flow (`--clean`).
 *
 * @param {Object} config - Per-project scaffold config.
 * @param {string} root   - Project root.
 * @return {Promise<void>}
 */
const cleanFlow = async ( config, root ) => {
	const kind = config.kind || 'project';
	const go = await ui.confirm( { message: `Run ${ kind } cleanup now?`, defaultValue: false } );
	if ( ! go ) {
		ui.warn( 'Cleanup skipped.' );
		return;
	}
	const removed = runCleanup( root, ( config.cleanup && config.cleanup.targets ) || [], ui );
	ui.success( `Cleanup complete (${ removed } removed).` );
};

/**
 * Entry point. Called by each starter's `bin/init.js`.
 *
 * @param {Object} config         - Per-project scaffold config.
 * @param {Object} options        - Options.
 * @param {string} options.root   - Project root (required).
 * @param {string[]} [options.argv] - CLI args (defaults to process args).
 * @return {Promise<void>}
 */
const run = async ( config, options = {} ) => {
	const root = options.root;
	const argv = options.argv || process.argv.slice( 2 );
	const kind = config.kind || 'project';

	if ( ! root ) {
		throw new Error( 'scaffold.run: options.root is required' );
	}

	if ( argv.includes( '--help' ) || argv.includes( '-h' ) ) {
		printHelp( kind );
		return;
	}

	try {
		if ( argv.includes( '--clean' ) || argv.includes( '-c' ) ) {
			const others = argv.filter( ( arg ) => '--clean' !== arg && '-c' !== arg );
			if ( others.length ) {
				ui.error( 'Invalid arguments.' );
				process.exitCode = 1;
				return;
			}
			await cleanFlow( config, root );
			return;
		}

		const { flags, unknown } = parseFlags( argv );
		if ( unknown.length ) {
			ui.error( `Unknown argument(s): ${ unknown.join( ' ' ) }` );
			process.exitCode = 1;
			return;
		}
		if ( flags.yes && ! flags.name ) {
			ui.error( '--yes requires --name=<name>.' );
			process.exitCode = 1;
			return;
		}

		await setupFlow( config, root, flags );
	} catch ( err ) {
		if ( err instanceof ui.CancelledError ) {
			ui.warn( '\nCancelled.' );
			process.exitCode = 130;
			return;
		}
		throw err;
	}
};

module.exports = { run };
