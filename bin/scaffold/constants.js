/**
 * Shared constants for the scaffold engine.
 */

/**
 * File extensions treated as binary and never opened for search-replace.
 */
const BINARY_EXTENSIONS = [
	'.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico', '.bmp', '.avif',
	'.woff', '.woff2', '.ttf', '.otf', '.eot',
	'.map', '.pdf', '.zip', '.gz', '.tar', '.mp4', '.webm', '.mov', '.mp3', '.wav',
];

/**
 * Directory / file names skipped while walking a project for replacement.
 *
 * `bin` is skipped so the per-project scaffold config (which embeds the search
 * tokens verbatim) is never corrupted; `build`/lock files avoid generated noise.
 */
const DEFAULT_IGNORE = [
	'.git',
	'node_modules',
	'vendor',
	'bin',
	'build',
	'package-lock.json',
	'composer.lock',
];

/**
 * PHP reserved keywords that cannot be used as a namespace segment or identifier.
 */
const PHP_RESERVED_WORDS = [
	'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch',
	'class', 'clone', 'const', 'continue', 'declare', 'default', 'do', 'echo', 'else',
	'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
	'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final', 'finally', 'float',
	'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include',
	'instanceof', 'insteadof', 'int', 'interface', 'isset', 'iterable', 'list', 'match',
	'mixed', 'namespace', 'never', 'new', 'null', 'object', 'or', 'parent', 'print',
	'private', 'protected', 'public', 'readonly', 'require', 'return', 'self', 'static',
	'string', 'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'void',
	'while', 'xor', 'yield',
];

module.exports = { BINARY_EXTENSIONS, DEFAULT_IGNORE, PHP_RESERVED_WORDS };
