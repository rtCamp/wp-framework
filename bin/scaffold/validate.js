/**
 * Project-name validation.
 */

const { PHP_RESERVED_WORDS } = require( './constants' );

/**
 * Validate a project name.
 *
 * Returns `undefined` when valid, matching the `text({ validate })` contract in
 * `@rtcamp/wp-tooling/ui`. Rejects names that would generate invalid PHP (leading
 * digits, illegal characters, reserved keywords).
 *
 * @param {string} name - Candidate project name.
 * @return {string|undefined} Error message, or undefined when valid.
 */
const validateName = ( name ) => {
	const trimmed = String( name || '' ).trim();

	if ( '' === trimmed ) {
		return 'Name is required.';
	}

	const slug = trimmed.replace( /\s+/g, '-' ).toLowerCase();

	if ( ! /^[a-z][a-z0-9]*(-[a-z0-9]+)*$/.test( slug ) ) {
		return 'Name must start with a letter and contain only letters, numbers, spaces or hyphens.';
	}

	const reserved = slug.split( '-' ).find( ( word ) => PHP_RESERVED_WORDS.includes( word ) );
	if ( reserved ) {
		return `"${ reserved }" is a reserved PHP keyword. Please choose another name.`;
	}

	return undefined;
};

module.exports = { validateName };
