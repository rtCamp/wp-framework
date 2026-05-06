# Issue #13 — Add Timer Utility

**Status:** in-review
**Branch:** `v1.0.0/task/timer-utility`
**PR:** https://github.com/rtCamp/wp-php-toolkit/pull/14
**Assignee:** @Adi-ty

---

## Summary

Add the `Timer` utility to `src/Utilities/Timer.php` — a singleton with named start/stop timers (float seconds), lap/split support, and `get_all()` for bulk consumption by Dev Monitor's Timing collector. Code that calls `start()` and code that calls `stop()` typically lives in different hooks/files, so a singleton sharing state across the request is the right fit; no globals needed at call sites.

---

## Decisions made

- [2026-05-05] **No `reset()` method.** Singleton state is request-scoped; YAGNI. Tests work around process-level state accumulation by using unique labels per test and asserting via `assertArrayHasKey` rather than `assertEquals` against the full set.
- [2026-05-05] **`stop()` on already-stopped returns the cached elapsed instead of re-computing or warning.** Predictable for instrumentation code that may stop the same timer from multiple guarded paths. Documented in the method docblock.
- [2026-05-05] **`_doing_it_wrong()` for misuse, not exceptions.** Matches the WordPress convention used elsewhere in the toolkit. Tests assert via `setExpectedIncorrectUsage()` (the polyfilled WP test helper).
- [2026-05-05] **Empty-string label is a silent no-op across `start`, `stop`, `lap`, `get`.** No `_doing_it_wrong()` noise for what's most likely a missing variable in caller code; the `null` / `0.0` return signals it.
- [2026-05-05] **`Singleton` trait constructor changed from `private` → `final protected`, and stale `trait.unused` ignore removed from `phpstan.neon.dist`.** `composer analyse` runs PHPStan over the whole `src/` tree with `reportUnmatchedIgnoredErrors: true`, so once the trait had a real consumer (Timer) two errors fired: `new.staticInAbstractClassStaticMethod` (trait analysed in context of a concrete class wants a consistent constructor) and `ignore.unmatched` (the previously-needed `trait.unused` ignore became stale). `final protected` keeps the singleton contract — `final` blocks subclass redefinition of the constructor signature, `protected` allows the trait to be used by a class that itself can be subclassed without re-exposing `new` to external code. `private` would have triggered `consistentConstructor.private` instead.

---

## Files changed so far

- `src/Utilities/Timer.php` — new (the utility)
- `tests/Utilities/TimerTest.php` — new (extends `WP_UnitTestCase`; 13 tests, 22 assertions)
- `src/Traits/Singleton.php` — `private __construct` → `final protected __construct` so PHPStan's `new static()` consistency check passes once a class consumes the trait, while keeping the singleton contract (external `new Timer()` still blocked) and allowing subclassing
- `phpstan.neon.dist` — removed the now-stale `trait.unused` ignore (Singleton has a consumer, so the warning no longer fires and `reportUnmatchedIgnoredErrors` was turning the dead suppression into a hard error)
- `CHANGELOG.md` — Unreleased entry for `Timer`
- `.claude/issues/06-timer-utility.md` — new (this file)

---

## Verification run

Ran on 2026-05-05 against the final state of all files. All three checks exit 0.

```bash
❯ composer lint
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

....... 7 / 7 (100%)


Time: 254ms; Memory: 16MB

❯ composer analyse
Note: Using configuration file /Users/adi/rtproj/wp-php-toolkit/phpstan.neon.dist.
 3/3 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%


                                                                                                                        
 [OK] No errors                                                                                                         
                                                                                                                        

❯ composer test
Installing...
Running as single site... To run multisite, use -c tests/phpunit/multisite.xml
Not running ajax tests. To execute these, use --group ajax.
Not running ms-files tests. To execute these, use --group ms-files.
Not running external-http tests. To execute these, use --group external-http.
PHPUnit 9.6.34 by Sebastian Bergmann and contributors.

Random Seed:   1777990798

....................                                              20 / 20 (100%)

Time: 00:00.090, Memory: 42.50 MB

OK (20 tests, 35 assertions)
```

---

## Open questions

- _(none yet)_

---

## Notes for the reviewer

- **`Singleton` trait + `phpstan.neon.dist` change is shared with the Cache, Logger, and Feature_Selector PRs.** The same `private __construct` → `final protected __construct` fix and the `trait.unused` ignore removal appear on all four branches (composer analyse fails without it once the trait has a consumer). Whichever PR merges into `release/v1.0.0` first carries the change in; the rest will drop those two lines out of their diff on rebase. Just for information — no action needed here.
- The Singleton's `$timers` array is process state, not DB state, so timers accumulate across tests within a run. Tests use unique labels per test and assert via `assertArrayHasKey` rather than `assertEquals` against the full set. This is documented in the test class docblock.
- `stop()` has three return paths (silent 0.0 for empty label, warning + 0.0 for never-started, cached elapsed for already-stopped). The docblock enumerates them — please confirm this is the right policy vs. always-warn-on-misuse.

---

## Handoff log

_(no rotations yet — delete this line when the first entry is added)_
