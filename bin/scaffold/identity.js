/**
 * Project identity -- derive every case variant + WordPress conventions from a name.
 */

/**
 * Generate a full identity object from a human project name.
 *
 * Example for "My Test Theme" (vendor `rtcamp`):
 *   kebab            my-test-theme
 *   snake            my_test_theme
 *   train            My-Test-Theme
 *   pascalSnake      My_Test_Theme
 *   macro            MY_TEST_THEME
 *   functionPrefix   my_test_theme_
 *   constantPrefix   MY_TEST_THEME
 *   cssPrefix        my-test-theme-
 *   package          rtcamp/my-test-theme
 *
 * @param {string} name              - Human project name (e.g. "My Test Theme").
 * @param {Object} [options]         - Options.
 * @param {string} [options.vendor]  - Package vendor prefix (default "rtcamp").
 * @return {Object} The identity object.
 */
const generateIdentity = ( name, options = {} ) => {
	const { vendor = 'rtcamp' } = options;

	const trimmed = String( name ).trim();
	const lower = trimmed.toLowerCase();
	const kebab = trimmed.replace( /\s+/g, '-' ).toLowerCase();
	const snake = kebab.replace( /-/g, '_' );
	const train = kebab.replace( /\b\w/g, ( char ) => char.toUpperCase() );
	const pascalSnake = train.replace( /-/g, '_' );
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

module.exports = { generateIdentity, CASE_KEYS };
