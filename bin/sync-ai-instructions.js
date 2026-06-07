#! /usr/bin/env node

/* eslint no-console: 0 */

/**
 * Project each package's path-scoped Copilot instructions up to the wp-content root.
 *
 * Copilot code review only reads instruction files from the repo root `.github/`.
 * In an assembled project the root is wp-content, but rules are authored per package
 * (plugins/<slug>/.github/, themes/<slug>/.github/). This finds the root by walking
 * up to the first ancestor containing plugins/, discovers every package, re-globs
 * each applyTo to the wp-content root, and writes wp-content/.github/instructions/.
 *
 * Files identical across packages (the framework-fed framework-php) are written once
 * with a combined applyTo; files unique to a package (structure) are written per
 * package as <slug>-<name>. Run via `npm run sync-ai`; pass --check for a CI gate
 * (exit 1 on drift) or --root DIR to force the root.
 *
 * Shipped by rtcamp/wp-framework; run from a consuming package's vendored copy.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const PACKAGE_DIRS = [ 'plugins', 'themes' ];
const MARKER = '<!-- GENERATED: projected from a package by rtcamp/wp-framework; edit the source, not here. -->';

// Copilot code review reads only the first ~4000 chars of an instruction file.
// Keep this and the GENERATED banner consistent with the PHP twin, bin/install-ai-instructions.php.
const COPILOT_CHAR_LIMIT = 4000;

const argv = process.argv.slice( 2 );
const isCheck = argv.includes( '--check' );
const rootIdx = argv.indexOf( '--root' );

const color = {
	red: ( m ) => `\x1b[31m${ m }\x1b[0m`,
	green: ( m ) => `\x1b[32m${ m }\x1b[0m`,
	yellow: ( m ) => `\x1b[33m${ m }\x1b[0m`,
	blue: ( m ) => `\x1b[34m${ m }\x1b[0m`,
};

/**
 * Walk up from `start` to the first ancestor containing a `plugins/` dir.
 *
 * @param {string} start Directory to start from.
 * @return {string|null} The wp-content root, or null.
 */
const findRoot = ( start ) => {
	let dir = start;
	for ( ;; ) {
		const candidate = path.join( dir, 'plugins' );
		if ( fs.existsSync( candidate ) && fs.statSync( candidate ).isDirectory() ) {
			return dir;
		}
		const parent = path.dirname( dir );
		if ( parent === dir ) {
			return null;
		}
		dir = parent;
	}
};

/**
 * Find every package shipping path-scoped instructions.
 *
 * @param {string} root wp-content root.
 * @return {Array<{type: string, slug: string, dir: string}>} packages
 */
const discoverPackages = ( root ) => {
	const found = [];
	PACKAGE_DIRS.forEach( ( type ) => {
		const base = path.join( root, type );
		if ( ! fs.existsSync( base ) ) {
			return;
		}
		fs.readdirSync( base ).forEach( ( slug ) => {
			const dir = path.join( base, slug, '.github', 'instructions' );
			if ( fs.existsSync( dir ) && fs.statSync( dir ).isDirectory() ) {
				found.push( { type, slug, dir } );
			}
		} );
	} );
	return found;
};

/**
 * Read the `applyTo` value out of a frontmatter block.
 *
 * @param {string} content File content.
 * @return {string} Comma-separated globs, or ''.
 */
const readApplyTo = ( content ) => {
	const m = content.match( /^applyTo:\s*(["']?)(.+?)\1\s*$/m );
	return m ? m[ 2 ] : '';
};

/**
 * Replace the `applyTo` value in a frontmatter block.
 *
 * @param {string} content File content.
 * @param {string} value   New comma-separated globs.
 * @return {string} Rewritten content.
 */
const writeApplyTo = ( content, value ) =>
	content.replace( /^(applyTo:\s*)(["']?)(.+?)\2\s*$/m, `$1"${ value }"` );

/**
 * Normalised content for grouping: everything except the applyTo value.
 *
 * @param {string} content File content.
 * @return {string} Normalised content.
 */
const normalise = ( content ) => content.replace( /^applyTo:.*$/m, 'applyTo:' );

/**
 * Drop any inherited GENERATED banner (e.g. the installer's); we append our own.
 *
 * @param {string} content File content.
 * @return {string} Content without GENERATED banner lines.
 */
const stripBanner = ( content ) =>
	content.replace( /^<!-- GENERATED.*?-->\s*$/gm, '' ).trim() + '\n';

/**
 * Prefix each package-relative glob with `<type>/<slug>/`.
 *
 * @param {string} applyTo Comma-separated package-relative globs.
 * @param {string} prefix  `<type>/<slug>`.
 * @return {string[]} Repo-root-relative globs.
 */
const prefixGlobs = ( applyTo, prefix ) =>
	applyTo.split( ',' ).map( ( g ) => `${ prefix }/${ g.trim().replace( /^\.?\//, '' ) }` );

/**
 * Append the GENERATED marker at the END so it never eats the first ~4000 chars.
 *
 * @param {string} content Re-globbed content.
 * @return {string} Content with marker appended.
 */
const withMarker = ( content ) => `${ content.trimEnd() }\n\n${ MARKER }\n`;

/**
 * Build the projected file set: { outName: content }.
 *
 * @param {Array} packages Discovered packages.
 * @return {Object<string, string>} files keyed by output basename.
 */
const buildOutputs = ( packages ) => {
	const groups = new Map();

	packages.forEach( ( { type, slug, dir } ) => {
		const prefix = `${ type }/${ slug }`;
		fs.readdirSync( dir )
			.filter( ( f ) => f.endsWith( '.instructions.md' ) )
			.forEach( ( file ) => {
				const content = stripBanner( fs.readFileSync( path.join( dir, file ), 'utf8' ) );
				const key = `${ file }\0${ normalise( content ) }`;
				if ( ! groups.has( key ) ) {
					groups.set( key, { basename: file, content, members: [] } );
				}
				groups.get( key ).members.push( {
					slug,
					globs: prefixGlobs( readApplyTo( content ), prefix ),
				} );
			} );
	} );

	const outputs = {};
	for ( const { basename, content, members } of groups.values() ) {
		const allGlobs = [ ...new Set( members.flatMap( ( m ) => m.globs ) ) ];
		const body = withMarker( writeApplyTo( content, allGlobs.join( ',' ) ) );
		const outName = members.length > 1 ? basename : `${ members[ 0 ].slug }-${ basename }`;
		outputs[ outName ] = body;
	}
	return outputs;
};

const main = () => {
	const start = rootIdx !== -1 ? path.resolve( argv[ rootIdx + 1 ] ) : process.cwd();
	const root = findRoot( start );
	if ( ! root ) {
		console.log( color.yellow( `No wp-content root (a dir containing plugins/) found above ${ start }. Nothing to do.` ) );
		process.exit( 0 );
	}

	const packages = discoverPackages( root );
	if ( 0 === packages.length ) {
		console.log( color.yellow( 'No packages with .github/instructions/ under plugins/ or themes/. Nothing to do.' ) );
		process.exit( 0 );
	}

	const outDir = path.join( root, '.github', 'instructions' );
	const outputs = buildOutputs( packages );

	const existing = fs.existsSync( outDir )
		? fs.readdirSync( outDir ).filter( ( f ) => fs.readFileSync( path.join( outDir, f ), 'utf8' ).includes( MARKER ) )
		: [];
	const wanted = new Set( Object.keys( outputs ) );
	const stale = existing.filter( ( f ) => ! wanted.has( f ) );

	let drift = false;
	const log = [];

	Object.entries( outputs ).forEach( ( [ name, content ] ) => {
		// Copilot reads the first ~4000 chars; banner is appended after the rules,
		// so guard the rules length (offset of the marker), not the whole file.
		const rulesLen = content.indexOf( MARKER );
		if ( rulesLen > COPILOT_CHAR_LIMIT ) {
			console.error( color.yellow( `  WARNING ${ name } rules are ${ rulesLen } chars (>${ COPILOT_CHAR_LIMIT }); Copilot only reads the first ~4000; trim the source.` ) );
		}

		const outPath = path.join( outDir, name );
		const current = fs.existsSync( outPath ) ? fs.readFileSync( outPath, 'utf8' ) : null;
		if ( current !== content ) {
			drift = true;
			log.push( color.green( `  ${ current === null ? 'create' : 'update' } ${ path.relative( root, outPath ) }` ) );
			if ( ! isCheck ) {
				fs.mkdirSync( outDir, { recursive: true } );
				fs.writeFileSync( outPath, content, 'utf8' );
			}
		}
	} );

	stale.forEach( ( name ) => {
		drift = true;
		log.push( color.red( `  prune  .github/instructions/${ name }` ) );
		if ( ! isCheck ) {
			fs.unlinkSync( path.join( outDir, name ) );
		}
	} );

	console.log( color.blue( `Root: ${ root }` ) );
	console.log( color.blue( `Packages: ${ packages.map( ( p ) => `${ p.type }/${ p.slug }` ).join( ', ' ) }` ) );

	if ( ! drift ) {
		console.log( color.green( 'AI instructions already in sync.' ) );
		process.exit( 0 );
	}

	console.log( log.join( '\n' ) );

	if ( isCheck ) {
		console.log( color.red( '\nOut of date. Run: npm run sync-ai' ) );
		process.exit( 1 );
	}
	console.log( color.green( '\nDone.' ) );
};

main();
