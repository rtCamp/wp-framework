<?php
/**
 * PHPUnit bootstrap — wp-php-toolkit.
 *
 * Most classes in this package are pure PHP and need no WordPress runtime.
 * Tests that DO need WordPress functions (Cache, Transients, Feature_Flags)
 * should use Brain\Monkey or a wp-tests-lib environment — see tests/README.md.
 *
 * @package RtCamp\WPToolkit\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Ensure Yoast's PHPUnit polyfills are loaded — these keep tests portable
// between PHPUnit 9, 10, 11, and 12 assertion signatures.
require_once __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
