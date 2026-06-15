/**
 * Persist the resolved project identity to a JSON file.
 *
 * Downstream tooling (e.g. a component-download script) reads this to rewrite
 * namespace / text-domain / prefixes inside fetched components.
 */

const fs = require( 'fs' );
const path = require( 'path' );

/** Name of the persisted identity file at the project root. */
const IDENTITY_FILE = '.wp-scaffold.json';

/**
 * Write the identity payload to `<root>/.wp-scaffold.json` (tab-indented).
 *
 * @param {string} root    - Project root.
 * @param {Object} payload - Identity payload to persist.
 * @param {Object} [ui]    - `@rtcamp/wp-tooling/ui` for an optional log line.
 * @return {string} Absolute path written.
 */
const writeIdentityFile = ( root, payload, ui ) => {
	const filePath = path.join( root, IDENTITY_FILE );
	fs.writeFileSync( filePath, `${ JSON.stringify( payload, null, '\t' ) }\n`, 'utf8' );
	if ( ui ) {
		ui.info( `wrote ${ IDENTITY_FILE }` );
	}
	return filePath;
};

/**
 * Read the persisted identity, or null when absent / unparseable.
 *
 * @param {string} root - Project root.
 * @return {Object|null} The parsed identity, or null.
 */
const readIdentityFile = ( root ) => {
	const filePath = path.join( root, IDENTITY_FILE );
	if ( ! fs.existsSync( filePath ) ) {
		return null;
	}
	try {
		return JSON.parse( fs.readFileSync( filePath, 'utf8' ) );
	} catch ( err ) {
		return null;
	}
};

module.exports = { writeIdentityFile, readIdentityFile, IDENTITY_FILE };
