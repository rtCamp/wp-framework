# Issue #4 — Add Logger Utility

**Status:** in-progress
**Branch:** `v1.0.0/task/logger-utility`
**PR:** _(not yet opened)_
**Assignee:** @Adi-ty

---

## Summary

Add a PSR-3-style Logger utility to `src/Utilities/Logger.php`. It provides `debug()`, `info()`, `warning()`, `error()` methods that write to `error_log()` when `WP_DEBUG` is true — silent in production. Uses the Singleton trait (one log stream per request). The PSR-3 method names allow a future swap to Monolog without changing callers.

---

## Decisions made

- [2026-04-30] **Tests extend `WP_UnitTestCase` (not `TestCase` with inline stubs).** The wp-tests-lib bootstrap provides `wp_json_encode()` and `WP_DEBUG` natively — no stubs needed.
- [2026-04-30] **WP_DEBUG=false test omitted from initial implementation.** WordPress test bootstrap defines `WP_DEBUG = true` and constants cannot be undefined. Testing this path requires `@runInSeparateProcess` which adds complexity for a trivially-correct guard clause. Can be added if reviewer requests it.

---

## Files changed so far

- `src/Utilities/Logger.php` — new (the Logger class)
- `tests/Utilities/LoggerTest.php` — new (2 tests, 6 assertions)
- `CHANGELOG.md` — edited (added Logger entry under Unreleased)
- `.claude/issues/02-logger-utility.md` — new (this file)

---

## Verification run

```bash
vendor/bin/phpcs src/Utilities/Logger.php tests/Utilities/LoggerTest.php
DEPRECATED: Scanning CSS/JS files is deprecated and support will be removed in PHP_CodeSniffer 4.0.
The WordPressVIPMinimum.JS.Window sniff is listening for JS.
DEPRECATED: Scanning CSS/JS files is deprecated and support will be removed in PHP_CodeSniffer 4.0.
The WordPressVIPMinimum.JS.DangerouslySetInnerHTML sniff is listening for JS.
DEPRECATED: Scanning CSS/JS files is deprecated and support will be removed in PHP_CodeSniffer 4.0.
The WordPressVIPMinimum.JS.InnerHTML sniff is listening for JS.
DEPRECATED: Scanning CSS/JS files is deprecated and support will be removed in PHP_CodeSniffer 4.0.
The WordPressVIPMinimum.JS.StrippingTags sniff is listening for JS.
DEPRECATED: Scanning CSS/JS files is deprecated and support will be removed in PHP_CodeSniffer 4.0.
The WordPressVIPMinimum.JS.StringConcat sniff is listening for JS.
DEPRECATED: Scanning CSS/JS files is deprecated and support will be removed in PHP_CodeSniffer 4.0.
The WordPressVIPMinimum.JS.HTMLExecutingFunctions sniff is listening for JS.

.. 2 / 2 (100%)


Time: 184ms; Memory: 16MB

❯ vendor/bin/phpstan analyse src/Utilities/Logger.php --level=5 --memory-limit=256M
Note: Using configuration file /Users/adi/rtproj/wp-php-toolkit/phpstan.neon.dist.
 1/1 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%


                                                                                                                        
 [OK] No errors                                                                                                         
                                                                                                                        

❯ vendor/bin/phpunit tests/Utilities/LoggerTest.php
Installing...
Running as single site... To run multisite, use -c tests/phpunit/multisite.xml
Not running ajax tests. To execute these, use --group ajax.
Not running ms-files tests. To execute these, use --group ms-files.
Not running external-http tests. To execute these, use --group external-http.
PHPUnit 9.6.34 by Sebastian Bergmann and contributors.

Random Seed:   1777545208

..                                                                  2 / 2 (100%)

Time: 00:00.012, Memory: 42.50 MB

OK (2 tests, 6 assertions)
```

---

## Open questions

- _(none yet)_
