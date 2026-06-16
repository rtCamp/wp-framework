/**
 * Apply the chosen version to project files, preserving each file's formatting.
 */

const fs = require( 'fs' );
const path = require( 'path' );

/**
 * Write `version` into each configured file.
 *
 * Edits are regex on raw text so each file's formatting survives (no full JSON
 * re-serialise).
 *
 * Supported kinds:
 *   - `json`        : the top-level `"version": "..."` value.
 *   - `css-header`  : a `Version:` line in a stylesheet header comment.
 *   - `php-header`  : a ` * Version:` line in a plugin header docblock.
 *
 * @param {string} root                                  - Project root.
 * @param {Array<{path: string, kind: string}>} versionFiles - Targets.
 * @param {string} version                               - Version to apply.
 * @param {Object} ui                                    - `@rtcamp/wp-tooling/ui`.
 * @return {void}
 */
const applyVersion = ( root, versionFiles, version, ui ) => {
	if ( ! version || ! Array.isArray( versionFiles ) ) {
		return;
	}

	versionFiles.forEach( ( spec ) => {
		const filePath = path.join( root, spec.path );
		if ( ! fs.existsSync( filePath ) ) {
			return;
		}

		try {
			let content = fs.readFileSync( filePath, 'utf8' );

			if ( 'json' === spec.kind ) {
				content = content.replace( /("version"\s*:\s*")[^"]*(")/, `$1${ version }$2` );
			} else {
				// css-header / php-header: first "Version:" line value.
				content = content.replace( /^(\s*\*?\s*Version:\s*).*$/m, `$1${ version }` );
			}

			fs.writeFileSync( filePath, content, 'utf8' );
			ui.info( `version ${ version } -> ${ spec.path }` );
		} catch ( err ) {
			ui.warn( `Could not set version in ${ spec.path }: ${ err.message }` );
		}
	} );
};

module.exports = { applyVersion };
