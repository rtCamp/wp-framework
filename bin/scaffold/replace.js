/**
 * File collection, content search-replace, and file renaming.
 *
 * All replacement is literal (no regex), binary-safe, and skips ignored paths.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { BINARY_EXTENSIONS, DEFAULT_IGNORE } = require( './constants' );

/**
 * Recursively collect every file under `dir`, skipping ignored names.
 *
 * @param {string}   dir      - Directory to walk.
 * @param {string[]} [ignore] - Directory / file names to skip.
 * @return {string[]} Absolute file paths.
 */
const collectFiles = ( dir, ignore = DEFAULT_IGNORE ) => {
	let entries;
	try {
		entries = fs.readdirSync( dir, { withFileTypes: true } );
	} catch ( err ) {
		return [];
	}

	let files = [];
	entries.forEach( ( entry ) => {
		if ( ignore.includes( entry.name ) ) {
			return;
		}
		const full = path.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			files = files.concat( collectFiles( full, ignore ) );
		} else if ( entry.isFile() ) {
			files.push( full );
		}
	} );
	return files;
};

/**
 * Whether a file should be treated as binary (skip content replacement).
 *
 * @param {string} filePath - File path (for the extension check).
 * @param {Buffer} buffer   - File contents.
 * @return {boolean} True if binary.
 */
const isBinary = ( filePath, buffer ) => {
	if ( BINARY_EXTENSIONS.includes( path.extname( filePath ).toLowerCase() ) ) {
		return true;
	}
	return buffer.includes( 0 );
};

/**
 * Apply every replacement pair literally to a string.
 *
 * @param {string}                   text         - Input string.
 * @param {Array<[string, string]>}  replacements - Ordered [ from, to ] pairs.
 * @return {string} Replaced string.
 */
const applyReplacements = ( text, replacements ) => {
	let out = text;
	replacements.forEach( ( [ from, to ] ) => {
		out = out.split( from ).join( to );
	} );
	return out;
};

/**
 * Replace token content across files in place.
 *
 * @param {string[]}                 files        - Absolute file paths.
 * @param {Array<[string, string]>}  replacements - Ordered [ from, to ] pairs.
 * @param {Object}                   ui           - `@rtcamp/wp-tooling/ui`.
 * @return {number} Count of files changed.
 */
const replaceInFiles = ( files, replacements, ui ) => {
	let changed = 0;
	files.forEach( ( filePath ) => {
		try {
			const buffer = fs.readFileSync( filePath );
			if ( isBinary( filePath, buffer ) ) {
				return;
			}
			const original = buffer.toString( 'utf8' );
			const updated = applyReplacements( original, replacements );
			if ( updated !== original ) {
				fs.writeFileSync( filePath, updated, 'utf8' );
				changed++;
			}
		} catch ( err ) {
			ui.warn( `Skipped ${ path.basename( filePath ) }: ${ err.message }` );
		}
	} );
	return changed;
};

/**
 * Rename files whose basename contains any token.
 *
 * @param {string[]}                 files        - Absolute file paths.
 * @param {Array<[string, string]>}  replacements - Ordered [ from, to ] pairs.
 * @param {Object}                   ui           - `@rtcamp/wp-tooling/ui`.
 * @return {number} Count of files renamed.
 */
const renameFiles = ( files, replacements, ui ) => {
	let renamed = 0;
	files.forEach( ( filePath ) => {
		const base = path.basename( filePath );
		const newBase = applyReplacements( base, replacements );
		if ( newBase === base ) {
			return;
		}
		try {
			fs.renameSync( filePath, path.join( path.dirname( filePath ), newBase ) );
			ui.info( `${ base } -> ${ newBase }` );
			renamed++;
		} catch ( err ) {
			ui.warn( `Could not rename ${ base }: ${ err.message }` );
		}
	} );
	return renamed;
};

module.exports = { collectFiles, applyReplacements, replaceInFiles, renameFiles };
