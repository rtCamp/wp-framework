/**
 * Build the ordered search-replace token pairs from a source + target identity.
 */

const { CASE_KEYS } = require( './identity' );

/**
 * Build replacement pairs mapping every source case-variant to the target's.
 *
 * Extra explicit pairs (e.g. package name, PHP namespace) that do not follow the
 * name pattern are appended. The result is de-duplicated and sorted by source
 * length descending, so longer/more-specific tokens replace before shorter ones
 * and never leave a partial match behind.
 *
 * @param {Object} source            - Identity of the placeholder (e.g. "Elementary Theme").
 * @param {Object} target            - Identity of the new project name.
 * @param {Object} [extra]           - Explicit `{ fromString: toString }` pairs.
 * @return {Array<[string, string]>} Ordered [ from, to ] pairs.
 */
const buildReplacements = ( source, target, extra = {} ) => {
	const pairs = [];

	CASE_KEYS.forEach( ( key ) => {
		if ( source[ key ] && target[ key ] && source[ key ] !== target[ key ] ) {
			pairs.push( [ source[ key ], target[ key ] ] );
		}
	} );

	Object.entries( extra ).forEach( ( [ from, to ] ) => {
		if ( from && to && from !== to ) {
			pairs.push( [ from, to ] );
		}
	} );

	// De-duplicate by source token.
	const seen = new Set();
	const unique = pairs.filter( ( [ from ] ) => {
		if ( seen.has( from ) ) {
			return false;
		}
		seen.add( from );
		return true;
	} );

	// Longest source first to avoid partial-overlap corruption.
	unique.sort( ( a, b ) => b[ 0 ].length - a[ 0 ].length );

	return unique;
};

module.exports = { buildReplacements };
