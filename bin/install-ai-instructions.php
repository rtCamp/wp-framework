<?php
/**
 * Install the canonical AI / Copilot PHP instructions into the consuming package.
 *
 * Shipped by rtcamp/wp-framework. Meant to run from a consumer's Composer
 * scripts (post-install-cmd / post-update-cmd), so the consumer always carries
 * a committed, up-to-date copy of the shared framework + WordPress review rules
 * at `.github/instructions/framework-php.instructions.md`.
 *
 * The source of truth is `ai/framework-php.instructions.md` in this package; the
 * consumer copy is generated (carries a GENERATED banner); do not hand-edit it.
 *
 * Usage (Composer runs it via post-install/post-update; or manually from the consumer root):
 *   php vendor/rtcamp/wp-framework/bin/install-ai-instructions.php
 *   php vendor/rtcamp/wp-framework/bin/install-ai-instructions.php --check   (CI: exit 1 if drifted)
 *
 * @package rtCamp\WPFramework
 */

declare( strict_types = 1 );

// Copilot code review reads only the first ~4000 chars of an instruction file.
// Keep this and the GENERATED banner consistent with the JS twin, bin/sync-ai-instructions.js.
const COPILOT_CHAR_LIMIT = 4000;

$is_check = in_array( '--check', $argv, true );

// Consumer root = where Composer runs the script (the package's own root).
$consumer_root = getcwd();
$source        = __DIR__ . '/../ai/framework-php.instructions.md';
$target        = $consumer_root . '/.github/instructions/framework-php.instructions.md';

$banner = '<!-- GENERATED from rtcamp/wp-framework; edit the source there, not here. -->';

/**
 * Print and exit.
 */
$done = static function ( string $message, int $code = 0 ): void {
	fwrite( 0 === $code ? STDOUT : STDERR, $message . "\n" );
	exit( $code );
};

// Don't install into the framework's own repo.
$consumer_composer = $consumer_root . '/composer.json';
if ( is_readable( $consumer_composer ) ) {
	$data = json_decode( (string) file_get_contents( $consumer_composer ), true );
	if ( is_array( $data ) && ( $data['name'] ?? '' ) === 'rtcamp/wp-framework' ) {
		$done( 'install-ai-instructions: skipped (running inside the framework itself).' );
	}
}

if ( ! is_readable( $source ) ) {
	$done( "install-ai-instructions: source not found at {$source}", 1 );
}

// Build the consumer copy: rules first, GENERATED banner appended at the END so
// it never eats into the first ~4000 chars Copilot reads.
$body    = (string) file_get_contents( $source );
$content = rtrim( $body ) . "\n\n" . $banner . "\n";

// Guard the rules length (frontmatter + body), not the whole file; the banner sits after.
if ( strlen( $body ) > COPILOT_CHAR_LIMIT ) {
	fwrite( STDERR, 'install-ai-instructions: WARNING framework-php.instructions.md rules are ' . strlen( $body ) . ' chars (>' . COPILOT_CHAR_LIMIT . "); Copilot only reads the first ~4000. Trim ai/framework-php.instructions.md.\n" );
}

$current = is_readable( $target ) ? (string) file_get_contents( $target ) : null;

if ( $current === $content ) {
	$done( 'install-ai-instructions: already up to date.' );
}

if ( $is_check ) {
	$done(
		'install-ai-instructions: OUT OF DATE; run `@php vendor/rtcamp/wp-framework/bin/install-ai-instructions.php`.',
		1
	);
}

if ( ! is_dir( dirname( $target ) ) ) {
	mkdir( dirname( $target ), 0755, true );
}
file_put_contents( $target, $content );
$done( 'install-ai-instructions: ' . ( null === $current ? 'wrote' : 'updated' ) . ' .github/instructions/framework-php.instructions.md' );
