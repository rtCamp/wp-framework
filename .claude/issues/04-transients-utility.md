# Issue #4 — Add Transients Utility

**Status:** in-progress
**Branch:** `v1.0.0/task/transients-utility`
**PR:** _(not yet opened)_
**Assignee:** @Adi-ty

---

## Summary

Add the `Transients` utility to `src/Utilities/Transients.php`. It wraps WordPress's transient API with a per-instance prefix so two modules in the same plugin can use the same logical key (`user_count`, `report_summary`, etc.) without colliding. This is the only utility in `wp-php-toolkit` that intentionally avoids the `Singleton` trait — each consumer constructs its own instance with its own prefix, which can't be shared state.

---

## Decisions made

- [2026-04-29] **Tests run against real WordPress via `wp-tests-lib`, not in-test stubs.** CLAUDE.md's testing section already prescribes the `bin/install-wp-tests.sh wordpress_test root '' localhost latest` flow, and that's the only approach that scales to upcoming utilities (`Cache`'s object-cache fallback, `Feature_Flags`'s hook firing) without re-implementing WordPress in test scaffolding. `tests/bootstrap.php` boots `wp-tests-lib`; `TransientsTest` extends `WP_UnitTestCase` and exercises real `set_transient` / `get_transient` / `delete_transient`.
- [2026-04-29] **Use the official WP-CLI scaffold-command `install-wp-tests.sh` verbatim.** No project-specific changes — keeps it interchangeable across repos.
- [2026-04-29] **Pin `phpunit/phpunit` to `^9.6`, not `^12.0`.** First PHPUnit run blew up with `Call to undefined method PHPUnit\Util\Test::parseTestMethodAnnotations()` — that static was removed in PHPUnit 10, but `WP_UnitTestCase::expectDeprecated()` (called from `set_up()`) still references it. WordPress core's test suite officially targets PHPUnit 9.x; `yoast/phpunit-polyfills ^4.0` papers over PHP version differences but does not restore removed PHPUnit statics. CLAUDE.md's `^12.0 max` is the polyfills' theoretical ceiling — the practical ceiling against real `wp-tests-lib` is 9.x. Updated `phpunit.xml.dist` schema URL to `9.6` to match, and replaced the PHPUnit-10+ `cacheDirectory` attribute with PHPUnit 9's `cacheResultFile`.
- [2026-04-29] **`get()` documents its `mixed` return per CLAUDE.md.** CLAUDE.md requires a code comment when `mixed` is used. Transients can hold any serialisable value (mirrors WordPress's own `get_transient()` signature) — captured in the method docblock.
- [2026-04-29] **No `flush_all_for_prefix()` method.** Spec excludes it from v1.0.0. WordPress doesn't expose a clean API to enumerate transients by prefix, and faking it via `wp_options` LIKE queries is exactly what VIP Minimum discourages.
- [2026-04-29] **PHPStan needs `--memory-limit=256M`** to analyse with the WordPress extension loaded. Default 128M is insufficient.

---

## Files changed so far

- `src/Utilities/Transients.php` — new (the wrapper)
- `tests/Utilities/TransientsTest.php` — new (extends `WP_UnitTestCase`; 3 tests, 5 assertions)
- `tests/bootstrap.php` — boots `wp-tests-lib`; honours `WP_TESTS_DIR` env override
- `bin/install-wp-tests.sh` — new, executable; verbatim WP-CLI scaffold-command template
- `composer.json` — `phpunit/phpunit` pinned to `^9.6` (was `^12.0`)
- `phpunit.xml.dist` — schema URL updated to `9.6`; PHPUnit-10+ attributes replaced with 9.x equivalents
- `CHANGELOG.md` — Unreleased entries for `Transients`, `bin/install-wp-tests.sh`, PHPUnit pin
- `.claude/issues/04-transients-utility.md` — new (this file)

---

## Verification run

Ran on 2026-04-29 against the final state of all files. MySQL was provided via a local Docker container; `bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest true` provisioned `wp-tests-lib` and the test database (the trailing `true` skips DB drop/recreate, since the container DB is already clean).

```bash
❯ vendor/bin/phpcs src/Utilities/Transients.php tests/Utilities/TransientsTest.php
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


Time: 194ms; Memory: 16MB

❯ vendor/bin/phpstan analyse --memory-limit=256M  src/Utilities/Transients.php --level=5
Note: Using configuration file /Users/adi/rtproj/wp-php-toolkit/phpstan.neon.dist.
 1/1 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%


                                                                                                     
 [OK] No errors                                                                                      
                                                                                                     

❯ vendor/bin/phpunit tests/Utilities/TransientsTest.php
Installing...
Running as single site... To run multisite, use -c tests/phpunit/multisite.xml
Not running ajax tests. To execute these, use --group ajax.
Not running ms-files tests. To execute these, use --group ms-files.
Not running external-http tests. To execute these, use --group external-http.
PHPUnit 9.6.34 by Sebastian Bergmann and contributors.

Random Seed:   1777535921

...                                                                 3 / 3 (100%)

Time: 00:00.014, Memory: 42.50 MB

OK (3 tests, 5 assertions)
```

All three checks exit 0.

---

## How to run the tests locally

`vendor/bin/phpunit` boots the **real** WordPress test suite (wp-tests-lib) against a MySQL/MariaDB instance — not stubs, not Brain\Monkey. One-time setup on a fresh checkout:

```bash
# 1. Install dev dependencies — pulls down vendor/bin/phpcs, phpstan, phpunit, etc.
composer install

# 2. Bring up MySQL. Docker is the quickest path:
docker run --name wp-tests-db -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=wordpress_test -p 3306:3306 -d mysql

# 3. Download WP core + wp-tests-lib into the system temp dir.
#    Args: <db-name> <db-user> <db-pass> <db-host> <wp-version> [skip-db-create]
#    The trailing `true` skips DB drop/recreate (we already created it in step 2).
bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest true

# 4. Run the three checks (commands taken verbatim from the issue spec).
vendor/bin/phpcs src/Utilities/Transients.php tests/Utilities/TransientsTest.php
vendor/bin/phpstan analyse src/Utilities/Transients.php --level=5 --memory-limit=512M
vendor/bin/phpunit tests/Utilities/TransientsTest.php
```

If you already have a mysql client on your host (e.g. via Homebrew `mysql-client`), you can skip step 2 and drop the trailing `true` in step 4 — `install-wp-tests.sh` will create the DB itself.

Override `WP_TESTS_DIR` / `WP_CORE_DIR` env vars if the default `sys_get_temp_dir()` location doesn't suit your environment — `tests/bootstrap.php` honours both.

---

## Open questions

- **CI is intentionally deferred to a follow-up issue.** This PR ships green-locally; a GitHub Actions workflow that runs the same three checks against a MySQL service container is the obvious next step but is out of scope here. The workflow shape (drafted from wp-cli/sample-plugin's Travis config, modernised) is roughly:

  ```yaml
  jobs:
    test:
      runs-on: ubuntu-latest
      services:
        mysql:
          image: mysql:8
          env: { MYSQL_ROOT_PASSWORD: root }
          ports: [3306:3306]
          options: >-
            --health-cmd="mysqladmin ping" --health-interval=10s
            --health-timeout=5s --health-retries=5
      steps:
        - uses: actions/checkout@v4
        - uses: shivammathur/setup-php@v2
          with: { php-version: '8.3', tools: 'composer:v2' }
        - run: composer install --no-interaction --prefer-dist
        - run: bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
        - run: composer lint
        - run: composer analyse -- --memory-limit=512M
        - run: composer test
  ```

---

## Notes for the reviewer

- Reviewer prereq: follow the "How to run the tests locally" section above. MySQL/MariaDB must be reachable.

---

## Handoff log

_(no rotations yet — delete this line when the first entry is added)_
