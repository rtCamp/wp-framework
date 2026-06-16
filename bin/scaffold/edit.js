/**
 * Interactive identity editing: every field is editable, in both scaffold review
 * and the manage-mode "Edit project details" flow.
 *
 * Editing an initialized project sources old values from `.wp-scaffold.json` and
 * replaces them (old -> new) across the repo.
 */

const { identityFromName } = require( './identity' );
const { buildIdentityReplacements } = require( './tokens' );
const { collectFiles, replaceInFiles, renameFiles } = require( './replace' );
const { applyVersion } = require( './version' );
const { writeIdentityFile, readIdentityFile } = require( './persist' );
const { validateName } = require( './validate' );

/** Editable identity fields, in display order. */
const FIELDS = [
	{ key: 'name', label: 'Name' },
	{ key: 'version', label: 'Version' },
	{ key: 'textDomain', label: 'Text Domain' },
	{ key: 'package', label: 'Package' },
	{ key: 'namespace', label: 'Namespace' },
	{ key: 'functionPrefix', label: 'Function Prefix' },
	{ key: 'constantPrefix', label: 'Constant Prefix' },
	{ key: 'cssPrefix', label: 'CSS Prefix' },
];

/**
 * Capitalise the first letter.
 *
 * @param {string} word - Input.
 * @return {string} Capitalised.
 */
const cap = ( word = '' ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 );

/**
 * Render the details table for an identity, via the project's `details()` if any.
 *
 * @param {Object} config - Scaffold config.
 * @param {Object} id     - Full identity.
 * @return {Object} Label -> value map.
 */
const detailsOf = ( config, id ) => {
	if ( 'function' === typeof config.details ) {
		return config.details( id, { version: id.version, namespace: id.namespace } );
	}
	return FIELDS.reduce( ( out, field ) => {
		out[ field.label ] = id[ field.key ];
		return out;
	}, {} );
};

/**
 * Per-field validators: return an error string, or undefined when valid. Lenient
 * but format-correct, just to catch typos.
 */
const FIELD_VALIDATORS = {
	name: validateName,
	version: ( v ) => /^\d+(\.\d+)*(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$/.test( v.trim() ) ? undefined : 'Version must look like 1.2.3.',
	textDomain: ( v ) => /^[a-z][a-z0-9-]*$/.test( v.trim() ) ? undefined : 'Text domain: lowercase letters, numbers, hyphens; start with a letter.',
	package: ( v ) => /^[a-z0-9]([a-z0-9._-]*[a-z0-9])?\/[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/.test( v.trim() ) ? undefined : 'Package must be vendor/name (lowercase).',
	namespace: ( v ) => /^[A-Za-z_][A-Za-z0-9_]*(\\[A-Za-z_][A-Za-z0-9_]*)*$/.test( v.trim() ) ? undefined : 'Namespace must be PHP-style, e.g. Vendor\\Sub\\Name.',
	functionPrefix: ( v ) => /^[a-z_][a-z0-9_]*$/.test( v.trim() ) ? undefined : 'Function prefix: lowercase letters, numbers, underscores.',
	constantPrefix: ( v ) => /^[A-Z_][A-Z0-9_]*$/.test( v.trim() ) ? undefined : 'Constant prefix: UPPERCASE letters, numbers, underscores.',
	cssPrefix: ( v ) => /^[a-z][a-z0-9-]*$/.test( v.trim() ) ? undefined : 'CSS prefix: lowercase letters, numbers, hyphens; start with a letter.',
};

/**
 * Interactively edit identity fields until the user confirms or cancels.
 *
 * Editing Name re-derives every field from the new name (keeping version and any
 * fields already overridden); editing any other field sets just that one. With
 * `flags.yes` the editor is skipped and startId accepted as-is. With `gate`, a
 * "Looks good?" confirm opens the field editor only on "no".
 *
 * @param {Object}  config  - Scaffold config.
 * @param {Object}  startId - Identity to start from.
 * @param {Object}  ui      - Merged UI.
 * @param {Object}  [flags] - CLI flags (honours `flags.yes`).
 * @param {boolean} [gate]  - Offer a "Looks good?" confirm before the editor.
 * @return {Promise<{id: Object, confirmed: boolean}>}
 */
const editIdentityFields = async ( config, startId, ui, flags = {}, gate = false ) => {
	let id = { ...startId };
	const overridden = new Set();

	for ( let first = true; ; first = false ) {
		ui.table( detailsOf( config, id ), { title: `${ cap( config.kind || 'project' ) } details` } );

		if ( flags.yes ) {
			return { id, confirmed: true };
		}

		if ( first && gate && await ui.confirm( { message: 'Looks good?', defaultValue: true } ) ) {
			return { id, confirmed: true };
		}

		const choice = await ui.radio( {
			message: 'Edit a field, or confirm',
			choices: [ ...FIELDS.map( ( field ) => field.label ), 'Confirm', 'Cancel' ],
		} );

		if ( 'Confirm' === choice ) {
			return { id, confirmed: true };
		}
		if ( 'Cancel' === choice ) {
			return { id: startId, confirmed: false };
		}

		const field = FIELDS.find( ( entry ) => entry.label === choice ).key;
		const value = await ui.text( { message: choice, defaultValue: id[ field ], validate: FIELD_VALIDATORS[ field ] } );

		if ( 'name' === field ) {
			const base = identityFromName( value, config, {} );
			const kept = {};
			overridden.forEach( ( key ) => {
				kept[ key ] = id[ key ];
			} );
			id = { ...base, version: id.version, ...kept };
		} else if ( 'version' === field ) {
			id.version = value;
		} else {
			id[ field ] = value;
			overridden.add( field );
		}
	}
};

/**
 * Apply an identity change (old -> new) across the repo: search-replace, rename,
 * version, and persist. Used by manage-mode editing (old comes from the JSON).
 *
 * @param {Object} config - Scaffold config.
 * @param {string} root   - Project root.
 * @param {Object} oldId  - Current identity (replacement source).
 * @param {Object} newId  - Edited identity (replacement target).
 * @param {Object} ui     - Merged UI.
 * @return {boolean} Whether anything changed.
 */
const applyIdentityEdit = ( config, root, oldId, newId, ui ) => {
	const replacements = buildIdentityReplacements( oldId, newId );

	if ( ! replacements.length ) {
		ui.info( 'No identity changes to apply.' );
		return false;
	}

	const files = collectFiles( root );
	const changed = replaceInFiles( files, replacements, ui );
	const renamed = renameFiles( files, replacements, ui );
	ui.success( `Updated ${ changed } file(s), renamed ${ renamed } file(s)` );

	if ( oldId.version !== newId.version && Array.isArray( config.versionFiles ) ) {
		const target = { ...newId, kebab: newId.textDomain };
		const versionFiles = config.versionFiles.map( ( spec ) => ( {
			...spec,
			path: 'function' === typeof spec.path ? spec.path( target ) : spec.path,
		} ) );
		applyVersion( root, versionFiles, newId.version, ui );
	}

	const current = readIdentityFile( root ) || {};
	writeIdentityFile( root, {
		...current,
		name: newId.name,
		version: newId.version,
		slug: newId.textDomain,
		textDomain: newId.textDomain,
		package: newId.package,
		namespace: newId.namespace,
		functionPrefix: newId.functionPrefix,
		constantPrefix: newId.constantPrefix,
		cssPrefix: newId.cssPrefix,
	}, ui );

	return true;
};

/**
 * Manage-mode "Edit project details" flow: edit fields starting from the
 * persisted identity, then apply the diff against the repo.
 *
 * @param {Object} config   - Scaffold config.
 * @param {string} root     - Project root.
 * @param {Object} identity - Parsed `.wp-scaffold.json`.
 * @param {Object} ui       - Merged UI.
 * @param {Object} [flags]  - CLI flags.
 * @return {Promise<void>}
 */
const editDetailsFlow = async ( config, root, identity, ui, flags = {} ) => {
	const startId = {
		name: identity.name,
		version: identity.version,
		textDomain: identity.textDomain || identity.slug,
		package: identity.package,
		namespace: identity.namespace,
		functionPrefix: identity.functionPrefix,
		constantPrefix: identity.constantPrefix,
		cssPrefix: identity.cssPrefix,
	};

	const { id, confirmed } = await editIdentityFields( config, startId, ui, flags );
	if ( ! confirmed ) {
		ui.warn( 'No changes made.' );
		return;
	}

	const changed = FIELDS.filter( ( field ) => String( startId[ field.key ] ?? '' ) !== String( id[ field.key ] ?? '' ) );
	if ( ! changed.length ) {
		ui.info( 'Nothing changed.' );
		return;
	}

	ui.heading( 'Changes to apply' );
	changed.forEach( ( field ) => ui.warn( `${ field.label }: ${ startId[ field.key ] } -> ${ id[ field.key ] }` ) );

	if ( ! flags.yes ) {
		const ok = await ui.confirm( { message: 'Apply these changes across the project?', defaultValue: true } );
		if ( ! ok ) {
			ui.warn( 'No changes applied.' );
			return;
		}
	}

	if ( ! applyIdentityEdit( config, root, startId, id, ui ) ) {
		return;
	}
	ui.success( 'Project details updated.' );
};

module.exports = { FIELDS, editIdentityFields, applyIdentityEdit, editDetailsFlow, detailsOf };
