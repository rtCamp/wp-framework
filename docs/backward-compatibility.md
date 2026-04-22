# Backward Compatibility — `wp-php-toolkit`

This is a shared Composer package. Consumers pin to `^1.0` and trust the public contract stays stable within v1. Silent breakage is the fastest way to lose consumer trust across the rtCamp ecosystem.

---

## The principle

Every public class, method, trait, and interface is a contract. Once a version is tagged, that contract is frozen for that major version.

- **Additive over modificative** — add alongside, don't change.
- **Deprecate before remove** — at least one minor release marking a symbol `@deprecated` before removal.
- **Breaking change = new major = new release branch** — never on `release/v1.*`.

---

## The release-branch routing rule

Every PR against this repo targets one specific branch. Match the change type:

| Change type | PR target | Version bump |
|---|---|---|
| Bug fix, no API change | `release/v1.0.0` | Patch — `v1.0.1` |
| Additive — new class / method / optional parameter | `release/v1.0.0` | Minor — `v1.1.0` |
| Deprecation — mark existing `@deprecated`, keep it working | `release/v1.0.0` | Minor — `v1.1.0` |
| Breaking change | `release/v2.0.0` | Major — `v2.0.0` |

If `release/v2.0.0` doesn't exist yet and you have a breaking change, open a discussion issue first. Creating a new major branch is a project-owner decision, not an individual call.

---

## What counts as breaking

See [`breaking-changes-checklist.md`](./breaking-changes-checklist.md) for the full list with examples.

When in doubt: **ask**. A 10-minute review is cheaper than weeks of consumer support.

---

## The deprecation lifecycle

1. **Build the replacement first.** Never deprecate without a working replacement already in the same PR.
2. **Mark `@deprecated` in the same PR as the replacement.** The deprecation docblock points at the replacement.
3. **Bump minor version** — consumers upgrading see the deprecation notice in `error_log` (when `WP_DEBUG` is on).
4. **Keep the deprecated symbol working** for at least one minor cycle — minimum.
5. **Remove only in the next major.** Never in the same major it was added in.

### Example — deprecating a method

```php
/**
 * Old method — kept for backward compatibility.
 *
 * @deprecated 1.3.0 Use self::get_formatted_value() instead. Will be removed in v2.0.0.
 * @see self::get_formatted_value()
 */
public function get_value( string $key ): string {
    _deprecated_function(
        __METHOD__,
        '1.3.0',
        self::class . '::get_formatted_value()'
    );
    return $this->get_formatted_value( $key );
}

public function get_formatted_value( string $key, bool $with_unit = true ): string {
    // New, richer behaviour.
}
```

### Example — deprecating a class

```php
/**
 * @deprecated 1.3.0 Use RtCamp\WPToolkit\Utilities\Cache_Store instead. Will be removed in v2.0.0.
 */
class Cache_Adapter { ... }
```

### Example — adding an optional parameter (non-breaking)

```php
// Before (v1.0.0)
public function get( string $key, string $group = '' ): mixed { ... }

// After (v1.1.0 — non-breaking: new optional param with default)
public function get( string $key, string $group = '', bool $force = false ): mixed { ... }
```

---

## Updating `CHANGELOG.md`

Every deprecation gets an entry under `### Deprecated`:

```markdown
## [1.3.0] — 2026-07-15

### Added
- `Cache::get_formatted_value()` — replacement for `Cache::get_value()`

### Deprecated
- `Cache::get_value()` — use `Cache::get_formatted_value()` instead. Will be removed in v2.0.0.
```

Every removal (in a major) gets an entry under `### Removed`:

```markdown
## [2.0.0] — 2027-03-10

### Removed
- `Cache::get_value()` — deprecated in 1.3.0 (2026-07-15). Use `Cache::get_formatted_value()`.
```

---

## Public surface — what is and isn't covered

The BC contract covers everything in `src/` that is **not** marked `@internal`.

### Covered (public contract)

- Every `public` method on every non-`@internal` class
- Every `protected` method on a non-`final`, non-`@internal` class (subclasses rely on them)
- Every trait in `src/Traits/`
- Every interface in `src/Dev/Interfaces/`
- Every constant on a public class
- Every `static` factory method

### Not covered (internal — may change anytime)

- Classes with `@internal` in their docblock
- Classes in any `Internal/` sub-namespace (e.g., `Utilities\Internal\`)
- `private` methods and properties
- Anything in `tests/`

### Marking a class internal

```php
/**
 * @internal Used by Logger only. May change without notice.
 */
final class Log_Formatter { ... }
```

PHPStan can flag consumers that reach into `@internal` code. Add this once per repo:

```yaml
# phpstan.neon
parameters:
    exceptions:
        uncheckedExceptionClasses:
            - RtCamp\WPToolkit\Exceptions\InternalApiException
```

---

## How long major versions stay supported

- **Current major** — all new feature work
- **Previous major** — critical bug fixes only, for 6 months after the next major ships
- **Two majors back** — security-only for a further 6 months; frozen after 12 months total

Dropping support earlier than this requires project-owner sign-off — consumers plan their upgrades against this policy.

---

## Before you open a PR

Run:

```
/check-bc
```

It audits your diff against the checklist and tells you which release branch to target. Non-optional for PRs touching `src/`.

---

## Automated enforcement

- `.github/workflows/ci-bc-check.yml` — runs on every PR, fails if a breaking change targets `release/v1.*`
- Consumer canary — weekly workflow that clones `features-plugin-skeleton` against the latest `release/v1` head of this repo. If canary goes red, the last-merged PR author is pinged.

---

## What still needs human review

Automation catches signature drift. It doesn't catch:

- **Intent mismatch** — the API signature is unchanged but semantic behaviour changes (hook priority swap, cache group rename, different error exception type).
- **New public API design** — is this shape worth supporting for years, or should it start as `@internal`?
- **Deprecation timing** — can we actually remove a symbol in the next major, or do consumers need longer?

These are flagged but not decided by `/check-bc`. A reviewer makes the call.

---

## Recent breakages (lessons log)

Append-only. Each entry: date · what broke · what we changed to prevent it.

- _(none yet — keep this list honest when it grows)_
