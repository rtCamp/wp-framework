# Backward compatibility — `wp-framework`

This is a shared Composer package. Consumers pin to `^1.0` and trust the public
contract stays stable within v1 — and they fork the skeletons, so a silent break
ripples across every fork at once. Everything under `inc/Contracts/` (interfaces,
abstracts, traits) plus the public methods of `Container`, `AssetLoader`,
`ComponentLoader`, `TemplateLoader`, and `Utils/Encryptor` is that contract.

## The principle

Every public class, method, trait, and interface is a contract. Once a version is
tagged, that contract is frozen for that major version.

- **Additive over modificative** — add alongside, don't change.
- **Deprecate before remove** — mark a symbol `@deprecated` for at least one minor
  release before it goes.
- **Breaking change = new major = new release branch** — never on `release/v1.*`.

## Release-branch routing

Every PR targets one branch. Match the change type:

| Change type | PR target | Version bump |
|---|---|---|
| Bug fix, no API change | `release/v1.0.0` | Patch — `v1.0.1` |
| Additive — new class / method / optional parameter | `release/v1.0.0` | Minor — `v1.1.0` |
| Deprecation — mark `@deprecated`, keep it working | `release/v1.0.0` | Minor — `v1.1.0` |
| Breaking change | `release/v2.0.0` | Major — `v2.0.0` |

If `release/v2.0.0` doesn't exist yet and you have a breaking change, open a
discussion issue first — creating a new major branch is a project-owner decision.

## What counts as breaking

Anything a consumer's code could be relying on that no longer holds:

- renaming or removing a public/protected method, class, interface, or trait;
- changing a method signature — parameter types, order, return type, or making an
  optional parameter required;
- changing the signature of an `abstract` method (every subclass must then change);
- a semantic change behind an unchanged signature — different hook priority, a
  renamed option key, a different exception type. These are the dangerous ones
  because automation won't catch them; flag them in review.

Adding an **optional** parameter with a default, a new method, or a new class is
**not** breaking.

When in doubt: **ask.** A 10-minute review is cheaper than weeks of fork support.

## The deprecation lifecycle

1. **Build the replacement first** — never deprecate without a working replacement
   in the same PR.
2. **Mark `@deprecated` in that PR**, pointing at the replacement.
3. **Bump the minor version** — consumers see the notice in `error_log` when
   `WP_DEBUG` is on.
4. **Keep the deprecated symbol working** for at least one minor cycle.
5. **Remove only in the next major** — never in the major it was added in.

### Example — deprecating a method

```php
/**
 * @deprecated 1.3.0 Use Encryptor::encrypt() instead. Will be removed in v2.0.0.
 * @see Encryptor::encrypt()
 */
public function encode( string $raw_value ): string|false {
    _deprecated_function( __METHOD__, '1.3.0', static::class . '::encrypt()' );
    return $this->encrypt( $raw_value );
}
```

### Example — adding an optional parameter (non-breaking)

```php
// Before (v1.0.0)
public function register_style( string $handle, string $filename, array $deps = [] ): bool { … }

// After (v1.1.0 — non-breaking: new optional param with a default)
public function register_style( string $handle, string $filename, array $deps = [], string $media = 'all' ): bool { … }
```

## Public surface — what is and isn't covered

The BC contract covers everything under `inc/` that is **not** marked `@internal`:

- every `public` method on every non-`@internal` class;
- every `protected` method on a non-`final`, non-`@internal` class (subclasses and
  the `Abstract*` consumers rely on them);
- every interface and trait under `inc/Contracts/`;
- every constant on a public class.

**Not** covered (may change anytime): anything with `@internal` in its docblock,
`private` members, and everything under `tests/`.

```php
/**
 * @internal Used by ComponentLoader only. May change without notice.
 */
final class SomeHelper { … }
```

## How long majors stay supported

- **Current major** — all new feature work.
- **Previous major** — critical bug fixes only, for 6 months after the next major.
- **Two majors back** — security-only for a further 6 months; frozen after 12
  months total.

Dropping support earlier needs project-owner sign-off — consumers plan upgrades
against this.

## Recent breakages (lessons log)

Append-only. Each entry: date · what broke · what we changed to prevent it.

- _(none yet — keep this list honest when it grows)_
