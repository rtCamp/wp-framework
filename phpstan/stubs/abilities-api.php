<?php
/**
 * WordPress Abilities API (Core 6.9) stubs for PHPStan only.
 *
 * The phpstan-wordpress extension does not yet ship these symbols; the
 * module guards every call with function_exists().
 *
 * @package rtCamp\WPFramework
 */

// phpcs:ignoreFile -- analysis-only stubs, intentionally minimal.

/**
 * @param array<string, mixed> $args
 */
function wp_register_ability( string $name, array $args ): ?object {
	return null;
}

/**
 * @param array<string, mixed> $args
 */
function wp_register_ability_category( string $slug, array $args ): bool {
	return true;
}
