# Issue #3 — Add Singleton Trait

**Status:** in-review
**Branch:** `v1.0.0/task/singleton-trait`
**PR:** https://github.com/rtCamp/wp-php-toolkit/pull/8
**Assignee:** @Adi-ty

---

## Summary

Add the `Singleton` trait to `src/Traits/Singleton.php`. This is the first task in the repo — every utility class consumes it (and the Dev Monitor collectors in the sibling `rtcamp/wp-dev-monitor` package use it too, via their dependency on this package). The trait provides `get_instance()` with late static binding, a private constructor, and clone protection. Skeleton repos do not keep a local copy; they pull it in through Composer.

---

## Decisions made

- [2026-04-27] **`__clone()` is `final public` with `throw new \RuntimeException(...)`**, not `private` as the issue body's code block suggested. PHP performs the visibility check before invoking the method body, so a `private __clone()` raises a generic `Error: Call to private __clone() from global scope` and the throw never executes. The acceptance test `test_clone_throws_runtime_exception` asserts `expectException(RuntimeException::class)` and would fail. Public-plus-throw is the correct shape for a library trait — the visibility check passes, the body runs, and consumers get a domain-meaningful exception with a readable message. `final` prevents a subclass from overriding to remove the throw.
- [2026-04-27] **`get_instance()` uses a function-local `static $instances = array()` keyed by `static::class`**, not a single trait-level `private static ?self $instance` property as the issue body specified. A trait-declared static property is shared across the inheritance chain — `Child::get_instance()` would return the parent's instance whenever the parent had been instantiated first. The function-local static array keyed by the late-bound class name is the only shape that satisfies the *"Subclasses receive their own instance"* acceptance criterion.
- [2026-04-27] **`get_instance()` is `final`** — subclasses must not redefine the resolution rule, which would break the per-class isolation guarantee.
- [2026-04-27] **Test file has three tests, not the two the issue body's code block shows.** The acceptance criteria explicitly require *"Subclasses receive their own instance (late static binding verified by test)"* but the spec's own test file omits the test that would verify it. Added `test_subclass_receives_its_own_instance` with a `ChildClass extends ConcreteClass {}` fixture. Without this test, the per-class instance-map fix has no automated regression guard.
- [2026-04-27] **Two out-of-scope config files are part of this PR.** The issue body says PHPCS / PHPStan baseline configuration is a Sprint 2 concern, but the starter-kit's baselines were broken on day one and prevented all four spec-mandated verification commands from exiting 0. Repaired in separate commits so they're easy to revert if needed:
  - `phpcs.xml.dist` — `WordPress.PHP.DisallowShortTernary.Found` is a non-existent sniff (the rule was renamed in WPCS 3.x to `Universal.Operators.DisallowShortTernary`, and the `.Found` suffix is the *error code* of that sniff, not the sniff itself); the broken reference made the whole ruleset fail to load with `phpcs exit 3`. Also broadened `WordPress.Files.FileName` exclude to `src/*` (PSR-4 source layout vs WordPress's `class-foo.php` convention), added `Generic.Files.OneObjectStructurePerFile` exclude for `tests/*` (the spec's test file deliberately bundles a `ConcreteClass` fixture alongside `SingletonTest`), and removed the `node_modules` exclude (this is a pure PHP Composer library — there is no JS, no asset build, no npm tooling, so `node_modules` will never exist here).
  - `phpstan.neon.dist` — added a defensive `trait.unused` ignore (library traits are consumed by external packages, so PHPStan analysing this repo alone can't see usages).

---

## Files changed so far

- `src/Traits/Singleton.php` — new (the trait itself)
- `tests/Traits/SingletonTest.php` — new (3 tests, 6 assertions)
- `CHANGELOG.md` — new (Unreleased entry: "Added `Singleton` trait")
- `phpcs.xml.dist` — modified (sniff rename, PSR-4 + test-fixture exclusions, dead `node_modules` exclude removed)
- `phpstan.neon.dist` — modified (dead `node_modules` exclude removed, `trait.unused` ignore added)
- `.claude/issues/01-singleton-trait.md` — modified issue tracking file with decisions, verification, and reviewer notes

---

## Verification run

Ran on 2026-04-27 against the final state of all six files.

```bash
❯ vendor/bin/phpcs src/Traits/ tests/Traits/
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


Time: 193ms; Memory: 16MB

❯ vendor/bin/phpstan analyse src/Traits/ --level=5
Note: Using configuration file /Users/adi/rtproj/wp-php-toolkit/phpstan.neon.dist.
 1/1 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%


                                                                                       
 [OK] No errors                                                                        
                                                                                       

❯ vendor/bin/phpunit tests/Traits/
PHPUnit 12.5.23 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.5
Configuration: /Users/adi/rtproj/wp-php-toolkit/phpunit.xml.dist
Random Seed:   1777283178

...                                                                 3 / 3 (100%)

Time: 00:00.001, Memory: 14.00 MB

OK (3 tests, 6 assertions)


❯ php -r "
require 'vendor/autoload.php';
class Foo { use RtCamp\WPToolkit\Traits\Singleton; public function setup(): void {} }
var_dump( Foo::get_instance() === Foo::get_instance() );
"

bool(true)
```

All four commands exit 0.

---

## Open questions

These are issue-body corrections that should be made before the same template lands in the next four utility-class issues (`Logger`, `Cache`, `Transients`, `Feature_Selector`).
- The issue body's `src/Traits/Singleton.php` code block specifies `private static ?self $instance = null;` and `private function __clone()`. The instance storage was changed to a function-local static array (see *Decisions made*), and `__clone()` was changed to `final public` with a throw.
- The issue body's `tests/Traits/SingletonTest.php` code block omits the third test (`test_subclass_receives_its_own_instance`) that the issue's own *Acceptance criteria → Runtime behaviour* section explicitly requires. Either add it to the spec, or remove the acceptance-criterion line.

---

## Notes for the reviewer

- The diff covers **6 files**, not the 4 listed in this issue's *Reviewer checklist → Diff contains only the four expected file changes*. The two extra files are the config repairs documented in *Decisions made* — none of them could be deferred without leaving the four spec-mandated verification commands unable to exit 0.
- The trait differs from the issue body's code block in two places (clone visibility, instance storage). Each deviation is logged with rationale in *Decisions made*; please read that section before flagging the differences as oversights.
- `composer.lock` is intentionally **not committed** — Composer libraries (`"type": "library"` in `composer.json`) leave dependency resolution to the consuming application. If your local checkout has `composer.lock` untracked, that's expected.
