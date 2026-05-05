# Issue #7 — Add Feature_Selector Utility

**Status:** in-progress
**Branch:** `v1.0.0/task/feature-selector-utility`
**PR:** https://github.com/rtCamp/wp-php-toolkit/pull/12
**Assignee:** @Adi-ty

---

## Summary

Add the `Feature_Selector` utility (`src/Utilities/Feature_Selector.php`) and its accompanying `Feature_Selector_Settings_Page` (`src/Utilities/Feature_Selector_Settings_Page.php`). `Feature_Selector` is a singleton registry of feature-flag slugs with a typed check API. Lookup precedence is **PHP constant `FEATURE_<UPPER_SLUG>` → WP option `rtcamp_feature_<lower_slug>` → default `false`**, so a `wp-config.php` constant can hard-override an admin toggle (useful for emergency disables and tests). The settings page lists every registered flag as a checkbox under `Settings → rtCamp Features`, persisted via the WordPress Settings API. This is the foundation for modular plugin development across all v2 client plugins — features register, then check at boot, then a site administrator toggles them from a settings page without code changes.

---

## Decisions made

- [2026-04-30] **Tests run against real WordPress via `wp-tests-lib`, not in-test stubs.** Same broken-stub pattern as #4 Transients / #2 Logger / #3 Cache: the issue's reference snippet defines `function get_option()` / `function update_option()` inside `setUpBeforeClass()` in a namespaced file, but PHP namespace resolution would never route the production class's unqualified `get_option()` call to those stubs (they land at `\RtCamp\WPToolkit\Tests\Utilities\get_option`, while production resolves to `\RtCamp\WPToolkit\Utilities\get_option` first then global). The reference test would silently never call the stubs at all.
- [2026-04-30] **Test isolation strategy is documented in the test class docblock.** `WP_UnitTestCase::tear_down()` rolls back DB transactions so options reset between tests, but the Singleton's `$registered` array is *process* state and accumulates. Tests use `assertContains` (not `assertEquals`) and unique flag slugs per test so assertions are order-independent under PHPUnit's `executionOrder="random"`.
- [2026-04-30] **`define( 'FEATURE_FORCED_ON', true )` in `test_constant_overrides_option` is guarded with `! defined()`.** PHP constants are immutable once defined; the guard makes the test idempotent if it runs twice in the same process (e.g. via PHPUnit's process-isolation flag) and avoids a "Constant already defined" notice. Slug `forced-on` is unique to this test so the lingering constant cannot leak into another test.
- [2026-04-30] **No `Toolkit::` static facade.** Spec is explicit: out of scope for v1.0.0. Consumers call `Feature_Selector::get_instance()->is_feature_enabled( ... )` directly. The static facade can come in v1.x once the call patterns are clear.
- [2026-04-30] **Settings page is built on the WordPress Settings API end-to-end.** `register_setting` (one boolean per flag, with `(bool) $value` sanitiser) + `settings_fields` + `submit_button` + `options.php` form action. No custom POST handling, no nonce code of our own — the Settings API generates and verifies the nonce. `current_user_can( 'manage_options' )` guards `render()`. Every output goes through `esc_html` / `esc_attr`.
- [2026-04-30] **Settings page slug `rtcamp-features`, settings group `rtcamp_features_group`, option-key prefix `rtcamp_feature_`** — matches the spec's wire format verbatim. Hyphens in flag slugs become underscores in both the constant name (`reaction-feature` → `FEATURE_REACTION_FEATURE`) and option key (`rtcamp_feature_reaction_feature`).
- [2026-04-30] **Text domain `rtcamp-toolkit` matches the spec example.** CLAUDE.md notes the package has no text domain of its own and `phpcs.xml.dist` excludes `WordPress.WP.I18n.MissingArgDomain`, but the spec example explicitly uses `'rtcamp-toolkit'` for the four user-facing strings on the settings page. Keeping the spec wire format. Consumers wanting translation register `.mo` files under that domain.
- [2026-04-30] **No automated test for `Feature_Selector_Settings_Page`.** Spec defers to a manual smoke test in wp-env (register a feature, visit `Settings → rtCamp Features`, toggle, save, reload). Unit-testing admin-page rendering would require simulating `admin_menu` / `admin_init` / `current_user_can` and parsing rendered HTML — much more harness than the four-checkbox page warrants. PHPCS + PHPStan validate the code structure (escapers, capability check, hook registrations) automatically.

---

## Files changed so far

- `src/Utilities/Feature_Selector.php` — new (registry + check API)
- `src/Utilities/Feature_Selector_Settings_Page.php` — new (admin page renderer)
- `tests/Utilities/Feature_SelectorTest.php` — new (4 tests, 6 assertions)
- `src/Traits/Singleton.php` — `private __construct` → `final protected __construct` (PHPStan `new static()` fix)
- `phpstan.neon.dist` — removed stale `trait.unused` ignore
- `CHANGELOG.md` — Unreleased entries
- `.claude/issues/05-feature-selector-utility.md` — new (this file)

---

## Verification run

Ran on 2026-04-30 against the final state of the files. wp-tests-lib was already provisioned from earlier work; no re-install needed.

```bash
❯ vendor/bin/phpcs src/Utilities/Feature_Selector.php src/Utilities/Feature_Selector_Settings_Page.php tests/Utilities/Feature_SelectorTest.php
... 3 / 3 (100%)
Time: 232ms; Memory: 16MB
exit=0

❯ vendor/bin/phpstan analyse src/Utilities/Feature_Selector.php src/Utilities/Feature_Selector_Settings_Page.php --level=5 --memory-limit=512M
 2/2 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%
 [OK] No errors
exit=0

❯ vendor/bin/phpunit tests/Utilities/Feature_SelectorTest.php
PHPUnit 9.6.34 by Sebastian Bergmann and contributors.
Random Seed:   1777550951
....                                                                4 / 4 (100%)
Time: 00:00.013, Memory: 42.50 MB
OK (4 tests, 6 assertions)
exit=0
```

All three checks exit 0.

---

## How to run the tests locally

Same wp-tests-lib setup as #4 Transients — see `.claude/issues/04-transients-utility.md` "How to run the tests locally" for the full walkthrough (composer install → MySQL container → `bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest true`). If wp-tests-lib is already provisioned in `sys_get_temp_dir()` from a previous session:

```bash
vendor/bin/phpcs src/Utilities/Feature_Selector.php src/Utilities/Feature_Selector_Settings_Page.php tests/Utilities/Feature_SelectorTest.php
vendor/bin/phpstan analyse src/Utilities/Feature_Selector.php src/Utilities/Feature_Selector_Settings_Page.php --level=5 --memory-limit=512M
vendor/bin/phpunit tests/Utilities/Feature_SelectorTest.php
```

### Manual smoke test (settings page)

The settings page is not exercised by an automated test; smoke-test it in any WordPress install (wp-env, Local, or your existing dev environment):

```php
add_action( 'plugins_loaded', function () {
    \RtCamp\WPToolkit\Utilities\Feature_Selector::get_instance()->has_features(
        array( 'demo-feature' )
    );
    \RtCamp\WPToolkit\Utilities\Feature_Selector_Settings_Page::get_instance()->setup();
} );
```

Confirm:

1. `Settings → rtCamp Features` menu appears.
2. The page lists `demo-feature` with a checkbox.
3. Toggling and saving persists across page reloads.
4. The same toggle is visible to `Feature_Selector::get_instance()->is_feature_enabled( 'demo-feature' )` from PHP code.

---

## Open questions

- **Settings page rendering is not exercised by an automated test** (see Decisions). Intentional — the manual smoke test in any WP environment covers the integration far better than parsing rendered HTML in PHPUnit. Reviewer should run the snippet in "How to run the tests locally" if they want to verify visually.

---

## Notes for the reviewer

- **No PR yet at the time of this writing** — implementation validated end-to-end locally before opening one.
- **Branch base**: this branch was forked from earlier in-progress work while #4 Transients / #2 Logger / #3 Cache are in review. Once those merge into `release/v1.0.0`, this branch will rebase onto `release/v1.0.0` and the upstream commits will drop out of the diff cleanly.
- **`composer.lock` is intentionally not committed** — same convention as Transients (#4), Logger (#2), Cache (#3), and Singleton (#8): `"type": "library"`, consuming applications resolve their own deps.
- **Reviewer checklist**:
  - Both classes use `Singleton` trait correctly (`use Singleton;`, `get_instance()` works, `setup(): void` stub present and wires hooks where needed).
  - Constant precedence works — verified by `test_constant_overrides_option` (option set to false, constant set to true, `is_feature_enabled` returns true).
  - Settings page escapes every output: `esc_html`, `esc_attr` on every dynamic value; `esc_html_e` / `esc_html__` on every translatable string.
  - `current_user_can( 'manage_options' )` guard at the top of `render()`.
  - Settings registered via WP Settings API — `register_setting` + `settings_fields` + form action `options.php` + `submit_button` (no custom POST handling, no hand-rolled nonce).
  - Hyphen-to-underscore normalisation is consistent across `Feature_Selector::is_feature_enabled` (constant), `Feature_Selector::option_key` (option), and `Feature_Selector_Settings_Page::option_key` (option) — same transform in all three sites.

---

## Handoff log

_(no rotations yet — delete this line when the first entry is added)_
