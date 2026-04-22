# Issue #12 — Add Singleton Trait

**Status:** in-progress
**Branch:** `v1.0.0/task/singleton-trait`
**PR:** _(not opened yet)_
**Assignee:** @sagar

---

## Summary

Add the `Singleton` trait to `src/Traits/Singleton.php`. This is the first task in the repo — every utility class and Dev Monitor collector consumes it. The trait provides `get_instance()` with late static binding, a private constructor, and clone protection. Skeleton repos do not keep a local copy; they pull it in through Composer.

---

## Decisions made

- [2026-04-22] Used `static::$instance` (late static binding) instead of `self::$instance` so subclasses receive their own isolated instance.
- [2026-04-22] Made `__construct()` private, not protected. Tighter API — external code cannot instantiate, even via reflection-heavy subclasses.
- [2026-04-22] Kept `__clone()` as a `throw` instead of a no-op — silent failure would hide bugs in tests.
- [2026-04-22] Did not add a `reset()` helper for tests. Can revisit if test isolation becomes painful; for now, each test class uses its own `ConcreteClass` fixture.
- [2026-04-23] Set PHPUnit to `^12.0` not `^13` — `yoast/phpunit-polyfills ^4.0` caps at v12. Flagged as non-negotiable in the root CLAUDE.md.

---

## Files changed so far

- `composer.json` — new (PSR-4 autoload, dev dependencies)
- `src/Traits/Singleton.php` — new (trait implementation)
- `tests/Traits/SingletonTest.php` — new (2 tests)
- `CHANGELOG.md` — new (Unreleased entry: "Added Singleton trait")
- `.gitignore` — new (vendor/, .phpunit.cache, .idea/)

---

## Verification run

Ran on 2026-04-23 after the test file was added.

```bash
$ composer install
Installing dependencies from lock file (including require-dev)
Package operations: 42 installs, 0 updates, 0 removals
...

$ vendor/bin/phpcs src/Traits/ --standard=WordPress
(no output — 0 errors, 0 warnings)

$ vendor/bin/phpstan analyse src/Traits/ --level=5
 [OK] No errors

$ vendor/bin/phpunit tests/Traits/
PHPUnit 12.0.3 by Sebastian Bergmann and contributors.

..                                                                  2 / 2 (100%)

Time: 00:00.041, Memory: 6.00 MB

OK (2 tests, 2 assertions)
```

All four commands exit 0. Ready for PR once the open question below is resolved.

---

## Open questions

- Docblock on the trait currently says `@package RtCamp\WPToolkit\Traits`. Should this be `@package RtCamp\WPToolkit` (package-level) instead? Checking how other rtCamp Composer packages conventionally use `@package` before opening the PR.

---

## Notes for the reviewer

- The test file intentionally defines `ConcreteClass` inline inside the test file rather than as a fixture class. Keeps the whole test readable in one place; the fixture is only used here.
- Intentionally did not export the trait to any registry. Traits are flat by design — the autoloader handles discovery.
- `CHANGELOG.md` entry is deliberately terse ("Added Singleton trait"). The richer context lives in this file and in the PR description — changelog stays consumer-facing.
