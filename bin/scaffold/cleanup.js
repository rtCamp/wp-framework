/**
 * Remove scaffolding-only files/directories after a successful setup.
 */

const fs = require( 'fs' );
const path = require( 'path' );

/**
 * Delete each target (file or directory) under `root` if it exists.
 *
 * @param {string}   root    - Project root.
 * @param {string[]} targets - Project-relative paths to remove.
 * @param {Object}   ui      - `@rtcamp/wp-tooling/ui`.
 * @return {number} Count of targets removed.
 */
const runCleanup = ( root, targets, ui ) => {
	let removed = 0;
	( targets || [] ).forEach( ( target ) => {
		const full = path.join( root, target );
		if ( ! fs.existsSync( full ) ) {
			return;
		}
		try {
			fs.rmSync( full, { recursive: true, force: true } );
			ui.info( `removed ${ target }` );
			removed++;
		} catch ( err ) {
			ui.warn( `Could not remove ${ target }: ${ err.message }` );
		}
	} );
	return removed;
};

module.exports = { runCleanup };
