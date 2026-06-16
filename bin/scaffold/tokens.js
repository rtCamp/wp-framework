/**
 * Build ordered search-replace pairs between two full identities.
 *
 * Used both for the initial scaffold (old = the starter placeholder identity) and
 * for manage-mode editing (old = the persisted `.wp-scaffold.json` identity).
 */

const { generateIdentity, CASE_KEYS, FIELD_KEYS } = require( './identity' );

/**
 * Double every backslash, matching how a namespace is JSON-escaped in
 * composer.json (`rtCamp\Theme\X` on disk becomes `rtCamp\\Theme\\X`).
 *
 * @param {string} value - Source value.
 * @return {string} Backslash-doubled value.
 */
const escapeBackslashes = ( value ) => String( value || '' ).replace( /\\/g, '\\\\' );

/**
 * Build replacement pairs that rewrite `oldId` to `newId` everywhere.
 *
 * Covers every case variant of the name plus each semantic field
 * (textDomain / package / prefixes), and the namespace in both single-backslash
 * (PHP source) and double-backslash (composer.json) forms. De-duplicated and
 * sorted longest-source-first so more-specific tokens replace before shorter ones.
 *
 * @param {Object} oldId - Current identity (replacement source).
 * @param {Object} newId - New identity (replacement target).
 * @return {Array<[string, string]>} Ordered [ from, to ] pairs.
 */
const buildIdentityReplacements = ( oldId, newId ) => {
	const pairs = [];
	const add = ( from, to ) => {
		if ( from && to && from !== to ) {
			pairs.push( [ from, to ] );
		}
	};

	// Every case variant of the name (vendor is irrelevant to these keys).
	const oldBase = generateIdentity( oldId.name, { vendor: 'x' } );
	const newBase = generateIdentity( newId.name, { vendor: 'x' } );
	CASE_KEYS.forEach( ( key ) => add( oldBase[ key ], newBase[ key ] ) );

	// Semantic fields (cover overrides + non-pattern values like package).
	FIELD_KEYS.filter( ( field ) => 'namespace' !== field ).forEach( ( field ) => add( oldId[ field ], newId[ field ] ) );

	// Namespace: single-backslash (PHP) + double-backslash (composer.json).
	add( oldId.namespace, newId.namespace );
	add( escapeBackslashes( oldId.namespace ), escapeBackslashes( newId.namespace ) );

	// De-duplicate by source token, then longest source first.
	const seen = new Set();
	const unique = pairs.filter( ( [ from ] ) => {
		if ( seen.has( from ) ) {
			return false;
		}
		seen.add( from );
		return true;
	} );
	unique.sort( ( a, b ) => b[ 0 ].length - a[ 0 ].length );

	return unique;
};

module.exports = { buildIdentityReplacements };
