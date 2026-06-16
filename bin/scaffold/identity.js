/**
 * Project identity: derive every case variant and WordPress conventions from a name.
 */

/**
 * Split a name into words on spaces/hyphens/underscores and camelCase,
 * PascalCase and ACRONYM boundaries:
 *   "Elementary Theme" -> [ Elementary, Theme ]
 *   "myWidget"         -> [ my, Widget ]
 *   "WPGraphQL"        -> [ WP, Graph, QL ]
 *
 * @param {string} name - Raw name.
 * @return {string[]} Words.
 */
const tokenizeWords = ( name ) =>
	String( name )
		.replace( /([a-z0-9])([A-Z])/g, '$1 $2' ) // camelCase boundary.
		.replace( /([A-Z]+)([A-Z][a-z])/g, '$1 $2' ) // acronym -> word boundary.
		.split( /[\s_-]+/ )
		.filter( Boolean );

/**
 * Title-case a word, preserving an all-caps acronym (length > 1).
 *
 * @param {string} word - Input word.
 * @return {string} Title-cased word (or the acronym unchanged).
 */
const titleWord = ( word ) =>
	( word.length > 1 && word === word.toUpperCase() )
		? word
		: word.charAt( 0 ).toUpperCase() + word.slice( 1 ).toLowerCase();

/**
 * Build the full identity from a name: case variants + WP-convention prefixes.
 *
 * @param {string} name             - Project name (e.g. "My Test Theme").
 * @param {Object} [options]        - Options.
 * @param {string} [options.vendor] - Package vendor prefix (default "rtcamp").
 * @return {Object} The identity object.
 */
const generateIdentity = ( name, options = {} ) => {
	const { vendor = 'rtcamp' } = options;

	const trimmed = String( name ).trim();
	const words = tokenizeWords( trimmed );
	const lowerWords = words.map( ( word ) => word.toLowerCase() );

	const lower = lowerWords.join( ' ' );
	const kebab = lowerWords.join( '-' );
	const snake = lowerWords.join( '_' );
	const train = words.map( titleWord ).join( '-' );
	const pascalSnake = words.map( titleWord ).join( '_' );
	const macro = snake.toUpperCase();
	const cobolKebab = kebab.toUpperCase();
	const cobolDisplay = lower.toUpperCase();

	return {
		name: trimmed,
		lower,
		cobolDisplay,
		kebab,
		train,
		cobolKebab,
		snake,
		pascalSnake,
		macro,
		slug: kebab,
		textDomain: kebab,
		functionPrefix: `${ snake }_`,
		constantPrefix: macro,
		cssPrefix: `${ kebab }-`,
		package: `${ vendor }/${ kebab }`,
	};
};

/** Case-variant keys produced by {@link generateIdentity}, used to build token pairs. */
const CASE_KEYS = [
	'name',
	'lower',
	'cobolDisplay',
	'kebab',
	'train',
	'cobolKebab',
	'snake',
	'pascalSnake',
	'macro',
];

/** Semantic identity fields a config may template or a user may override. */
const FIELD_KEYS = [ 'textDomain', 'package', 'namespace', 'functionPrefix', 'constantPrefix', 'cssPrefix' ];

/**
 * Build a full, canonical identity from a name, applying the config's namespace
 * and package templates and any explicit field overrides.
 *
 * The returned shape is what `.wp-scaffold.json` stores (minus version/features):
 * `{ name, textDomain, slug, package, namespace, functionPrefix, constantPrefix, cssPrefix }`.
 *
 * @param {string} name          - Project name.
 * @param {Object} config        - Scaffold config (uses `vendor`, `namespace`, `package`).
 * @param {Object} [overrides]   - Explicit `{ field: value }` overrides.
 * @return {Object} The full identity.
 */
const identityFromName = ( name, config, overrides = {} ) => {
	const base = generateIdentity( name, { vendor: config.vendor || 'rtcamp' } );

	const id = {
		name: base.name,
		textDomain: base.textDomain,
		slug: base.slug,
		package: 'function' === typeof config.package ? config.package( base ) : base.package,
		namespace: 'function' === typeof config.namespace ? config.namespace( base ) : '',
		functionPrefix: base.functionPrefix,
		constantPrefix: base.constantPrefix,
		cssPrefix: base.cssPrefix,
	};

	FIELD_KEYS.forEach( ( field ) => {
		if ( undefined !== overrides[ field ] ) {
			id[ field ] = overrides[ field ];
		}
	} );
	id.slug = id.textDomain;

	return id;
};

module.exports = { generateIdentity, identityFromName, CASE_KEYS, FIELD_KEYS };
