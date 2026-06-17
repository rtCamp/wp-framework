/**
 * Feature kernel -- generic, feature-agnostic toggle mechanics.
 *
 * A feature is declared as DATA in a scaffold config (key/label + optional
 * declarative `apply` of files/deps/scripts + optional `onEnable`/`onDisable`
 * hooks + optional `detect` probe); the engine never names a specific feature.
 *
 * `.wp-scaffold.json.features` ({ key: bool }) is intent, a fresh `detect` sweep
 * is reality. Reality wins for display; the persisted map is rebuilt from a
 * detect sweep only after a successful apply.
 */

const fs = require( 'fs' );
const path = require( 'path' );

/**
 * Validate a config's feature manifest. Throws on first problem so the caller
 * aborts before touching disk.
 *
 * @param {Object} config - Per-project scaffold config.
 * @return {void}
 */
const validateFeatures = ( config ) => {
	const features = config.features || [];
	const keys = new Set();
	const labels = new Set();

	features.forEach( ( feature ) => {
		if ( ! feature.key || ! /^[a-z][a-z0-9-]*$/.test( feature.key ) ) {
			throw new Error( `Feature key must match /^[a-z][a-z0-9-]*$/, got ${ JSON.stringify( feature.key ) }` );
		}
		if ( keys.has( feature.key ) ) {
			throw new Error( `Duplicate feature key: ${ feature.key }` );
		}
		keys.add( feature.key );

		if ( ! feature.label ) {
			throw new Error( `Feature ${ feature.key } is missing a label` );
		}
		if ( labels.has( feature.label ) ) {
			throw new Error( `Duplicate feature label: ${ feature.label }` );
		}
		labels.add( feature.label );

		( ( feature.apply && feature.apply.files ) || [] ).forEach( ( file ) => {
			if ( ! file.to || path.isAbsolute( file.to ) || file.to.split( /[\\/]/ ).includes( '..' ) ) {
				throw new Error( `Feature ${ feature.key }: file.to must be a relative path without "..", got ${ JSON.stringify( file.to ) }` );
			}
		} );

		if ( Boolean( feature.onEnable ) !== Boolean( feature.onDisable ) ) {
			throw new Error( `Feature ${ feature.key }: onEnable and onDisable must both be present or both absent` );
		}
	} );
};

/**
 * Detect the indentation used by a JSON file so rewrites preserve its style.
 *
 * @param {string} raw - Raw file text.
 * @return {string} The indent unit (tab or spaces).
 */
const detectIndent = ( raw ) => {
	const match = raw.match( /\n([ \t]+)\S/ );
	return match ? match[ 1 ] : '\t';
};

/**
 * Build the FeatureApi handed to every hook / probe. All mutating calls record
 * an undo entry in `api._journal` so a feature can be rolled back on failure.
 *
 * @param {string} root     - Project root (absolute).
 * @param {Object} identity - Parsed .wp-scaffold.json.
 * @param {Object} ui       - Merged wp-tooling + local style UI.
 * @return {Object} The FeatureApi.
 */
const makeFeatureApi = ( root, identity, ui ) => {
	const journal = [];
	const join = ( rel ) => path.join( root, rel );

	const api = {
		root,
		identity,
		ui,
		_journal: journal,
		path: join,
		exists: ( rel ) => fs.existsSync( join( rel ) ),
		read: ( rel ) => ( fs.existsSync( join( rel ) ) ? fs.readFileSync( join( rel ), 'utf8' ) : null ),

		write( rel, body ) {
			const abs = join( rel );
			const existed = fs.existsSync( abs );
			const old = existed ? fs.readFileSync( abs ) : null;
			journal.push( {
				undo: () => {
					if ( existed ) {
						fs.writeFileSync( abs, old );
					} else {
						fs.rmSync( abs, { force: true } );
					}
				},
			} );
			fs.mkdirSync( path.dirname( abs ), { recursive: true } );
			fs.writeFileSync( abs, body );
		},

		remove( rel ) {
			const abs = join( rel );
			if ( ! fs.existsSync( abs ) ) {
				return;
			}
			// Refuse directories: a buffer snapshot can't restore a tree on rollback.
			if ( fs.statSync( abs ).isDirectory() ) {
				throw new Error( `remove(): refusing to delete directory "${ rel }"; remove files individually so rollback can restore them.` );
			}
			const old = fs.readFileSync( abs );
			journal.push( {
				undo: () => {
					fs.mkdirSync( path.dirname( abs ), { recursive: true } );
					fs.writeFileSync( abs, old );
				},
			} );
			fs.rmSync( abs, { force: true } );
		},

		editPackageJson( mutator ) {
			const abs = join( 'package.json' );
			const raw = fs.readFileSync( abs, 'utf8' );
			const indent = detectIndent( raw );
			const obj = JSON.parse( raw );
			mutator( obj );
			journal.push( { undo: () => fs.writeFileSync( abs, raw, 'utf8' ) } );
			fs.writeFileSync( abs, `${ JSON.stringify( obj, null, indent ) }\n`, 'utf8' );
		},

		hasDep( name ) {
			const abs = join( 'package.json' );
			if ( ! fs.existsSync( abs ) ) {
				return false;
			}
			const pkg = JSON.parse( fs.readFileSync( abs, 'utf8' ) );
			return Boolean( ( pkg.dependencies && pkg.dependencies[ name ] ) || ( pkg.devDependencies && pkg.devDependencies[ name ] ) );
		},

		writeFlag( key, on ) {
			const abs = join( '.wp-features.json' );
			const oldRaw = fs.existsSync( abs ) ? fs.readFileSync( abs, 'utf8' ) : null;
			const flags = null !== oldRaw ? JSON.parse( oldRaw ) : {};
			flags[ key ] = on;
			journal.push( {
				undo: () => {
					if ( null !== oldRaw ) {
						fs.writeFileSync( abs, oldRaw );
					} else {
						fs.rmSync( abs, { force: true } );
					}
				},
			} );
			fs.writeFileSync( abs, `${ JSON.stringify( flags, null, '\t' ) }\n` );
		},

		// Read a single KEY=value from a dotenv-style file. Returns the trimmed,
		// unquoted value, or null when the file or key is absent.
		readEnv( rel, key ) {
			const abs = join( rel );
			if ( ! fs.existsSync( abs ) ) {
				return null;
			}
			const raw = fs.readFileSync( abs, 'utf8' );
			const match = raw.match( new RegExp( `^[ \\t]*${ key }[ \\t]*=[ \\t]*(.*)$`, 'm' ) );
			return match ? match[ 1 ].trim().replace( /^["']|["']$/g, '' ) : null;
		},

		// Set KEY=value in a dotenv-style file: replace the line if present,
		// append it otherwise, creating the file when missing. Journaled.
		setEnv( rel, key, value ) {
			const raw = this.read( rel ) || '';
			const line = `${ key }=${ value }`;
			const re = new RegExp( `^[ \\t]*${ key }[ \\t]*=.*$`, 'm' );
			let next;
			if ( re.test( raw ) ) {
				next = raw.replace( re, line );
			} else if ( '' === raw ) {
				next = `${ line }\n`;
			} else {
				next = raw.endsWith( '\n' ) ? `${ raw }${ line }\n` : `${ raw }\n${ line }\n`;
			}
			this.write( rel, next );
		},
	};

	return api;
};

/**
 * Run `fn` inside the api journal; on throw, replay undo entries in reverse to
 * restore exactly what `fn` mutated, then rethrow.
 *
 * @param {Object}   api - FeatureApi.
 * @param {Function} fn  - Synchronous mutation function.
 * @return {void}
 */
const runJournaled = ( api, fn ) => {
	const start = api._journal.length;
	try {
		fn();
		api._journal.length = start;
	} catch ( err ) {
		for ( let i = api._journal.length - 1; i >= start; i-- ) {
			try {
				api._journal[ i ].undo();
			} catch ( undoErr ) {
				// Best-effort rollback; surface nothing further.
			}
		}
		api._journal.length = start;
		throw err;
	}
};

/**
 * Set-if-absent merge of a feature's declarative deps/scripts into package.json.
 *
 * @param {Object} pkg     - Parsed package.json (mutated).
 * @param {Object} apply   - feature.apply.
 * @param {Object} ui      - UI for conflict warnings.
 * @return {void}
 */
const mergePackage = ( pkg, apply, ui ) => {
	const section = ( name ) => {
		pkg[ name ] = pkg[ name ] || {};
		return pkg[ name ];
	};
	const addDeps = ( target, src ) => {
		Object.entries( src || {} ).forEach( ( [ name, version ] ) => {
			if ( undefined === target[ name ] ) {
				target[ name ] = version;
			}
		} );
	};

	if ( apply.dependencies ) {
		addDeps( section( 'dependencies' ), apply.dependencies );
	}
	if ( apply.devDependencies ) {
		addDeps( section( 'devDependencies' ), apply.devDependencies );
	}
	if ( apply.scripts ) {
		const scripts = section( 'scripts' );
		Object.entries( apply.scripts ).forEach( ( [ name, cmd ] ) => {
			if ( undefined === scripts[ name ] ) {
				scripts[ name ] = cmd;
			} else if ( scripts[ name ] !== cmd && ui ) {
				ui.warn( `Script "${ name }" already exists; keeping the current value.` );
			}
		} );
	}
};

/**
 * Remove a feature's declarative deps/scripts keys from package.json, keeping any
 * key still owned by another feature that remains enabled (no shared-dep removal).
 *
 * @param {Object} pkg       - Parsed package.json (mutated).
 * @param {Object} apply     - feature.apply.
 * @param {Array}  survivors - Features that stay enabled after this transition.
 * @return {void}
 */
const unmergePackage = ( pkg, apply, survivors = [] ) => {
	const ownedDeps = new Set();
	const ownedScripts = new Set();
	survivors.forEach( ( feature ) => {
		const a = feature.apply || {};
		Object.keys( a.dependencies || {} ).forEach( ( name ) => ownedDeps.add( name ) );
		Object.keys( a.devDependencies || {} ).forEach( ( name ) => ownedDeps.add( name ) );
		Object.keys( a.scripts || {} ).forEach( ( name ) => ownedScripts.add( name ) );
	} );

	const del = ( target, src, owned ) => {
		if ( ! target ) {
			return;
		}
		Object.keys( src || {} ).forEach( ( name ) => {
			if ( ! owned.has( name ) ) {
				delete target[ name ];
			}
		} );
	};
	del( pkg.dependencies, apply.dependencies, ownedDeps );
	del( pkg.devDependencies, apply.devDependencies, ownedDeps );
	del( pkg.scripts, apply.scripts, ownedScripts );
};

/**
 * Whether a feature looks installed on disk.
 *
 * @param {Object} feature - Feature definition.
 * @param {Object} api     - FeatureApi.
 * @return {boolean} True if detected.
 */
const detectFeature = ( feature, api ) => {
	if ( feature.detect ) {
		return Boolean( feature.detect( api ) );
	}
	const apply = feature.apply || {};
	const files = apply.files || [];
	const deps = [
		...Object.keys( apply.dependencies || {} ),
		...Object.keys( apply.devDependencies || {} ),
	];
	if ( 0 === files.length && 0 === deps.length ) {
		return false;
	}
	return files.every( ( file ) => api.exists( file.to ) ) && deps.every( ( dep ) => api.hasDep( dep ) );
};

/**
 * Enable a feature: declarative files + deps + scripts, then onEnable.
 * Journaled, so a throw mid-way rolls back this feature only.
 *
 * @param {Object} feature     - Feature definition.
 * @param {Object} api         - FeatureApi.
 * @param {string} featuresDir - Project-relative dir holding feature assets.
 * @return {void}
 */
const enableFeature = ( feature, api, featuresDir ) => {
	runJournaled( api, () => {
		const apply = feature.apply || {};

		( apply.files || [] ).forEach( ( file ) => {
			const srcAbs = path.join( api.root, featuresDir, file.from );
			const body = fs.readFileSync( srcAbs );
			const dstAbs = api.path( file.to );
			// Skip if identical, preserving any user edits to a feature-owned file.
			if ( fs.existsSync( dstAbs ) && fs.readFileSync( dstAbs ).equals( body ) ) {
				return;
			}
			api.write( file.to, body );
		} );

		if ( apply.dependencies || apply.devDependencies || apply.scripts ) {
			api.editPackageJson( ( pkg ) => mergePackage( pkg, apply, api.ui ) );
		}

		if ( feature.onEnable ) {
			feature.onEnable( api );
		}
	} );
};

/**
 * Disable a feature: onDisable, then revert declarative files + deps + scripts.
 * Journaled.
 *
 * @param {Object} feature   - Feature definition.
 * @param {Object} api       - FeatureApi.
 * @param {Array}  survivors - Features that remain enabled (shared keys are kept).
 * @return {void}
 */
const disableFeature = ( feature, api, survivors = [] ) => {
	runJournaled( api, () => {
		if ( feature.onDisable ) {
			feature.onDisable( api );
		}
		const apply = feature.apply || {};
		( apply.files || [] ).forEach( ( file ) => api.remove( file.to ) );
		if ( apply.dependencies || apply.devDependencies || apply.scripts ) {
			api.editPackageJson( ( pkg ) => unmergePackage( pkg, apply, survivors ) );
		}
	} );
};

/**
 * Reconcile persisted intent against detected reality.
 *
 * @param {Object} config    - Per-project scaffold config.
 * @param {Object} persisted - Persisted features map ({ key: bool }).
 * @param {Object} api       - FeatureApi.
 * @return {{rows: Array, unknown: string[]}} Rows + retired keys.
 */
const reconcile = ( config, persisted, api ) => {
	const features = config.features || [];
	const map = persisted || {};

	const rows = features.map( ( feature ) => {
		const detected = detectFeature( feature, api );
		const intent = Boolean( map[ feature.key ] );
		return {
			key: feature.key,
			label: feature.label,
			description: feature.description || '',
			on: detected,
			intent,
			drift: detected !== intent,
			feature,
		};
	} );

	const known = new Set( features.map( ( feature ) => feature.key ) );
	const unknown = Object.keys( map ).filter( ( key ) => ! known.has( key ) );

	return { rows, unknown };
};

/**
 * Compute which features must be enabled / disabled to reach `wantOn`.
 *
 * @param {Array}       rows   - Reconciled rows.
 * @param {Set<string>} wantOn - Desired ENABLED key set.
 * @return {{toEnable: Array, toDisable: Array}} Transition lists.
 */
const computeDiff = ( rows, wantOn ) => ( {
	toEnable: rows.filter( ( row ) => ! row.on && wantOn.has( row.key ) ),
	toDisable: rows.filter( ( row ) => row.on && ! wantOn.has( row.key ) ),
} );

/**
 * Fresh detected map across every declared feature ({ key: bool }).
 *
 * @param {Object} config - Per-project scaffold config.
 * @param {Object} api    - FeatureApi.
 * @return {Object} Detected features map.
 */
const detectMap = ( config, api ) => {
	const map = {};
	( config.features || [] ).forEach( ( feature ) => {
		map[ feature.key ] = detectFeature( feature, api );
	} );
	return map;
};

module.exports = {
	validateFeatures,
	makeFeatureApi,
	detectFeature,
	enableFeature,
	disableFeature,
	reconcile,
	computeDiff,
	detectMap,
};
