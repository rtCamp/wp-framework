/**
 * Manage mode -- re-runnable feature toggling for an already-scaffolded project.
 *
 * Entered when .wp-scaffold.json exists. Shares one apply+persist path with the
 * scaffold flow's initial feature pick via `toggleFeatures`.
 */

const {
	makeFeatureApi,
	reconcile,
	computeDiff,
	enableFeature,
	disableFeature,
	detectMap,
} = require( './features' );
const { writeFeatures } = require( './persist' );

/**
 * Capitalise the first letter.
 *
 * @param {string} word - Input.
 * @return {string} Capitalised.
 */
const cap = ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 );

/**
 * Split a comma list flag value into trimmed keys.
 *
 * @param {string} value - Raw flag value.
 * @return {string[]} Keys.
 */
const splitList = ( value ) => ( value ? value.split( ',' ).map( ( s ) => s.trim() ).filter( Boolean ) : [] );

/**
 * Parse manage-mode CLI flags.
 *
 * @param {string[]} argv - Arguments.
 * @return {{flags: Object, unknown: string[]}} Parsed.
 */
const parseManageFlags = ( argv ) => {
	const flags = { yes: false, list: false };
	const unknown = [];

	argv.forEach( ( arg ) => {
		if ( '--yes' === arg || '-y' === arg ) {
			flags.yes = true;
		} else if ( '--list' === arg ) {
			flags.list = true;
		} else if ( '--manage' === arg ) {
			// Explicit manage marker; already in manage mode, no-op.
		} else if ( arg.startsWith( '--features=' ) ) {
			flags.features = splitList( arg.slice( '--features='.length ) );
		} else if ( arg.startsWith( '--enable=' ) ) {
			flags.enable = splitList( arg.slice( '--enable='.length ) );
		} else if ( arg.startsWith( '--disable=' ) ) {
			flags.disable = splitList( arg.slice( '--disable='.length ) );
		} else {
			unknown.push( arg );
		}
	} );

	return { flags, unknown };
};

/**
 * Whether a feature touches package.json (so we know to suggest npm install).
 *
 * @param {Object} feature - Feature definition.
 * @return {boolean} True if it has deps/scripts.
 */
const touchesPackage = ( feature ) => {
	const apply = feature.apply || {};
	return Boolean( apply.dependencies || apply.devDependencies || apply.scripts );
};

/**
 * Render a read-only feature status table.
 *
 * @param {Array}    rows    - Reconciled rows.
 * @param {string[]} unknown - Retired keys still recorded.
 * @param {Object}   ui      - UI.
 * @return {void}
 */
const showStatus = ( rows, unknown, ui ) => {
	if ( ! rows.length ) {
		ui.info( 'No optional features are declared for this project.' );
		return;
	}
	ui.table(
		rows.map( ( r ) => [ r.label, `${ r.on ? 'enabled' : 'disabled' }${ r.drift ? '  (drift)' : '' }` ] ),
		{ title: 'Feature status' }
	);
	rows.forEach( ( r ) => {
		if ( r.description ) {
			ui.info( `${ r.label }: ${ r.description }` );
		}
	} );
	( unknown || [] ).forEach( ( key ) => ui.warn( `${ key }: recorded in .wp-scaffold.json but no longer declared.` ) );
};

/**
 * Drive one enable/disable transition to reach the desired enabled set, then
 * (manage mode) persist the post-apply detected map.
 *
 * @param {Object} config       - Per-project scaffold config.
 * @param {string} root         - Project root.
 * @param {Object} opts         - { mode, wantOn?, flags, api, ui, rows?, unknown? }.
 * @return {Promise<{changed: boolean, failed: string[], finalMap: Object}>}
 */
const toggleFeatures = async ( config, root, opts ) => {
	const { mode, api, ui } = opts;
	const flags = opts.flags || {};
	let { wantOn, rows, unknown } = opts;

	if ( ! rows ) {
		const r = reconcile( config, ( api.identity && api.identity.features ) || {}, api );
		rows = r.rows;
		unknown = r.unknown;
	}

	// Every exit path goes through finalize: in manage mode it rewrites the
	// persisted map from a fresh detect sweep, so persisted intent always trails
	// disk reality (this heals pre-existing drift even on no-op runs).
	const finalize = ( changed, failed ) => {
		const finalMap = detectMap( config, api );
		if ( 'manage' === mode ) {
			writeFeatures( root, finalMap, ui );
		}
		if ( failed && failed.length ) {
			process.exitCode = 1;
			ui.warn( `Some features failed: ${ failed.join( ', ' ) }. Re-run init to retry.` );
		}
		return { changed, failed: failed || [], finalMap };
	};

	// Surface drift + retired features.
	rows.filter( ( r ) => r.drift ).forEach( ( r ) =>
		ui.warn( `${ r.key }: recorded ${ r.intent ? 'enabled' : 'disabled' } but disk says ${ r.on ? 'enabled' : 'disabled' }; using disk state.` )
	);
	( unknown || [] ).forEach( ( key ) => ui.warn( `${ key }: retired feature still recorded; leaving untouched.` ) );

	// Resolve the desired ENABLED set (the returned/flag set is the target, not a delta).
	if ( ! wantOn ) {
		if ( ! rows.length ) {
			ui.info( 'No optional features are declared.' );
			return finalize( false, [] );
		}
		ui.table(
			rows.map( ( r ) => [ r.label, `${ r.on ? 'enabled' : 'disabled' }${ r.drift ? '  (drift)' : '' }` ] ),
			{ title: 'Optional features' }
		);
		const choiceFor = ( r ) => `${ r.on ? '[on]  ' : '[off] ' }${ r.label }`;
		const byChoice = new Map( rows.map( ( r ) => [ choiceFor( r ), r ] ) );
		const picked = await ui.checkbox( {
			message: 'Select EVERY feature you want enabled (unselected ones are disabled)',
			choices: rows.map( choiceFor ),
		} );
		wantOn = new Set( picked.map( ( label ) => byChoice.get( label ).key ) );
	}

	const { toEnable, toDisable } = computeDiff( rows, wantOn );
	if ( ! toEnable.length && ! toDisable.length ) {
		ui.success( 'Already up to date.' );
		return finalize( false, [] );
	}

	ui.heading( 'Planned changes' );
	toEnable.forEach( ( r ) => ui.success( `+ enable  ${ r.label }` ) );
	toDisable.forEach( ( r ) => ui.warn( `- disable ${ r.label }` ) );

	if ( ! flags.yes ) {
		// Default to NO whenever the plan disables anything, so an accidental
		// deselect can never silently remove a feature without confirmation.
		const ok = await ui.confirm( { message: 'Apply these changes?', defaultValue: 0 === toDisable.length } );
		if ( ! ok ) {
			ui.warn( 'No changes applied.' );
			return finalize( false, [] );
		}
	}

	const featuresDir = config.featuresDir || 'bin/features';
	const survivors = ( config.features || [] ).filter( ( f ) => wantOn.has( f.key ) );
	const failed = [];
	let depsChanged = false;

	// Disable before enable: frees files/deps before any re-add.
	toDisable.forEach( ( r ) => {
		const spin = ui.spinner( `Disabling ${ r.label }...` );
		spin.start();
		try {
			disableFeature( r.feature, api, survivors );
			spin.succeed( `Disabled ${ r.label }` );
			depsChanged = depsChanged || touchesPackage( r.feature );
		} catch ( err ) {
			spin.fail( `Failed to disable ${ r.label }` );
			ui.error( err.message );
			failed.push( r.key );
		}
	} );
	toEnable.forEach( ( r ) => {
		const spin = ui.spinner( `Enabling ${ r.label }...` );
		spin.start();
		try {
			enableFeature( r.feature, api, featuresDir );
			spin.succeed( `Enabled ${ r.label }` );
			depsChanged = depsChanged || touchesPackage( r.feature );
		} catch ( err ) {
			spin.fail( `Failed to enable ${ r.label }` );
			ui.error( err.message );
			failed.push( r.key );
		}
	} );

	if ( depsChanged ) {
		ui.warn( 'Dependencies changed -- run `npm install` to sync.' );
	}
	if ( ! failed.length ) {
		ui.success( 'Features updated.' );
	}

	return finalize( true, failed );
};

/**
 * Manage-mode entry. Routes flags or an interactive menu into `toggleFeatures`.
 *
 * @param {Object}   config   - Per-project scaffold config.
 * @param {string}   root     - Project root.
 * @param {string[]} argv     - CLI args.
 * @param {Object}   identity - Parsed .wp-scaffold.json.
 * @param {Object}   ui       - Merged UI.
 * @param {Function} reinit   - Callback to re-run the full scaffold flow.
 * @return {Promise<void>}
 */
const manageFlow = async ( config, root, argv, identity, ui, reinit ) => {
	const { flags, unknown } = parseManageFlags( argv );
	if ( unknown.length ) {
		ui.error( `Unknown argument(s): ${ unknown.join( ' ' ) }` );
		process.exitCode = 1;
		return;
	}
	if ( flags.features && ( flags.enable || flags.disable ) ) {
		ui.error( '--features cannot be combined with --enable/--disable.' );
		process.exitCode = 1;
		return;
	}
	if ( flags.list && ( flags.features || flags.enable || flags.disable ) ) {
		ui.error( '--list cannot be combined with --features/--enable/--disable.' );
		process.exitCode = 1;
		return;
	}

	const features = config.features || [];
	const api = makeFeatureApi( root, identity, ui );
	const { rows, unknown: retired } = reconcile( config, identity.features || {}, api );

	if ( flags.list ) {
		showStatus( rows, retired, ui );
		return;
	}

	const validKeys = new Set( features.map( ( f ) => f.key ) );
	const requested = [ ...( flags.features || [] ), ...( flags.enable || [] ), ...( flags.disable || [] ) ];
	const badKey = requested.find( ( k ) => ! validKeys.has( k ) );
	if ( badKey ) {
		ui.error( `Unknown feature "${ badKey }". Valid: ${ [ ...validKeys ].join( ', ' ) || '(none)' }` );
		process.exitCode = 1;
		return;
	}

	const hasFlagSelection = Boolean( flags.features || flags.enable || flags.disable );
	if ( flags.yes && ! hasFlagSelection ) {
		ui.error( '--yes in manage mode requires --features / --enable / --disable.' );
		process.exitCode = 1;
		return;
	}

	if ( hasFlagSelection ) {
		let wantOn;
		if ( flags.features ) {
			wantOn = new Set( flags.features );
		} else {
			wantOn = new Set( rows.filter( ( r ) => r.on ).map( ( r ) => r.key ) );
			( flags.enable || [] ).forEach( ( k ) => wantOn.add( k ) );
			( flags.disable || [] ).forEach( ( k ) => wantOn.delete( k ) );
		}
		await toggleFeatures( config, root, { mode: 'manage', wantOn, flags, api, ui, rows, unknown: retired } );
		return;
	}

	// Interactive menu.
	ui.heading( `${ cap( config.kind || 'project' ) } -- manage` );
	for ( ;; ) {
		const choices = features.length
			? [ 'Toggle features', 'Show status', 'Re-run full setup', 'Exit' ]
			: [ 'Re-run full setup', 'Exit' ];
		const choice = await ui.radio( { message: 'What would you like to do?', choices } );

		if ( 'Exit' === choice ) {
			return;
		}
		if ( 'Show status' === choice ) {
			const r = reconcile( config, reqReadFeatures( root ), api );
			showStatus( r.rows, r.unknown, ui );
			continue;
		}
		if ( 'Re-run full setup' === choice ) {
			await reinit();
			return;
		}
		// Toggle features: re-read fresh rows each loop.
		const r = reconcile( config, ( reqReadFeatures( root ) ), api );
		await toggleFeatures( config, root, { mode: 'manage', flags, api, ui, rows: r.rows, unknown: r.unknown } );
	}
};

/**
 * Read the persisted features map fresh (after a prior toggle in the same loop).
 *
 * @param {string} root - Project root.
 * @return {Object} Features map.
 */
const reqReadFeatures = ( root ) => {
	const { readFeatures } = require( './persist' );
	return readFeatures( root );
};

module.exports = { manageFlow, toggleFeatures, parseManageFlags, showStatus };
