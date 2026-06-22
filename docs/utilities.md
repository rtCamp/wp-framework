# Utilities & services

Standalone helpers under `inc/Utils/` (plus the `Container`, documented with the
contracts). None of them participate in the registration flow — they're
well-tested building blocks a service can hold, inject, or register as
`Shareable`. Most are **instance-based and context-scoped**, the same pattern as
the loaders: construct with the package's slug/key, and two consumers never
collide.

| Utility | One-liner |
|---|---|
| [`Encryptor`](#encryptor) | Authenticated AES-256-GCM encryption for values stored in the DB |
| [`Cache`](#cache) | Typed wrapper over the WP object cache, group-namespaced, with optional SWR |
| [`FeatureSelector`](#featureselector) | Fail-closed feature-flag registry with per-context toggles |
| [`FeatureSelectorSettingsPage`](#featureselectorsettingspage) | Admin page that renders a `FeatureSelector`'s flags as checkboxes |
| [`XHProf_Profiler`](#xhprof_profiler) | Profile a code block with XHProf; no-ops without the extension |
| [`Timer`](#timer) | Named start/stop/lap timers in float seconds, shared across hooks/scopes |
| [`Container`](contracts.md#container) | Tiny instance map (the storage half of the `Loader`) |

## Encryptor

[`inc/Utils/Encryptor.php`](../inc/Utils/Encryptor.php) — authenticated encryption
for sensitive values before they go into the database (API tokens, secrets).
**AES-256-GCM** by default.

GCM is *authenticated*: it produces an auth tag that makes tampering detectable,
so a modified ciphertext fails to decrypt instead of silently returning garbage.
The class hard-rejects any cipher whose name doesn't end in `-gcm` (the stored
`IV ‖ tag ‖ ciphertext` layout is GCM-specific) — constructing it with, say,
`aes-256-cbc` throws an `InvalidArgumentException`.

```php
$enc   = new Encryptor( $key );      // key supplied at construction
$blob  = $enc->encrypt( $secret );   // base64 string, or false on failure
$plain = $enc->decrypt( $blob );     // original string, or false on tamper/failure
```

- A fresh random IV per call means encrypting the same value twice yields
  different blobs — correct, but you can't compare ciphertexts for equality.
- `decrypt()` returns **`false`** on any failure (failed auth = tampering, or
  non-base64 input). Always check for `false`; it is not an exception.
- Override the protected `key()` seam to source the key from a KMS / env / rotated
  secret without touching the crypto. It must never return an empty string — the
  base throws if no key is available, on purpose.
- Key generation, storage, and rotation are the consumer's job; the class only
  encrypts and decrypts.

## Cache

[`inc/Utils/Cache.php`](../inc/Utils/Cache.php) — a typed wrapper over WordPress's
object-cache functions, with **per-consumer group namespacing** and an optional
**stale-while-revalidate (SWR)** path for stampede protection.

Instance-based, configured with a context slug so each consumer's cache groups
are namespaced and can't collide with another plugin/theme using the same group
name through a shared object cache:

```php
$cache = new Cache( 'my-plugin' );
$nav   = $cache->remember( 'nav_items', fn() => build_nav(), 'theme', 300 );
```

`remember()` returns the cached value or computes, stores, and returns it. Like
the other services, register a `Cache` instance as `Shareable` in a consumer's
container, or extend it to change the backend behaviour.

## FeatureSelector

[`inc/Utils/FeatureSelector.php`](../inc/Utils/FeatureSelector.php) — a
feature-flag registry with per-context toggle storage. It is **fail-closed**:
only registered flags resolve, and an unregistered or mistyped slug always
returns `false` from `is_enabled()`, so a typo can't accidentally run a feature
that doesn't exist.

```php
$features = new FeatureSelector( 'my-plugin' );
$features->register( [ 'dark-mode' => [ 'name' => 'Dark Mode' ] ] );

if ( $features->is_enabled( 'dark-mode' ) ) { /* … */ }
```

For a registered flag the lookup precedence is:

1. **PHP constant** — an instant override (tests, or an emergency disable via
   `wp-config.php`);
2. **stored toggle** — the flag's entry in the per-context feature option (e.g.
   written by a settings page);
3. **default `true`** — features ship on. The selector exists to turn things
   *off*, not on.

## FeatureSelectorSettingsPage

[`inc/Utils/FeatureSelectorSettingsPage.php`](../inc/Utils/FeatureSelectorSettingsPage.php)
— an admin settings page that lists every flag registered with an injected
`FeatureSelector` as a checkbox. It extends
[`AbstractSettingsPage`](abstracts.md#abstractsettingspage) and uses the WordPress
Settings API end-to-end (the form posts to `options.php`; no custom handler). The
page lives under **Settings → {Context} Features**, with the slug, option group,
and titles all derived from the selector's context.

```php
$features = new FeatureSelector( 'my-plugin' );
$features->register( [ 'dark-mode' => [ 'name' => 'Dark Mode' ] ] );

( new FeatureSelectorSettingsPage( $features ) )->register_hooks();
```

It's the ready-made UI for the toggles `FeatureSelector` reads — register it
like any other `Registrable`.

## XHProf_Profiler

[`inc/Utils/XHProf_Profiler.php`](../inc/Utils/XHProf_Profiler.php) — profiles an
arbitrary code block with XHProf, independent of any dev-monitor stack.

```php
$top = XHProf_Profiler::get_instance()->profile( fn() => expensive(), 10, 'expensive' );
```

- **Safe in production and CI:** it silently no-ops when neither the `xhprof` nor
  the `tideways_xhprof` extension is loaded — the callback still runs, it just
  isn't profiled.
- Supports both backends (they share the same `parent==>child` data shape); only
  the enable/disable calls differ.
- It's a `Singleton` (`get_instance()`), and **not `final`** — a downstream
  package can extend it and override `summarize()`. Internal calls use late
  static binding so overrides take effect.

## Timer

[`inc/Utils/Timer.php`](../inc/Utils/Timer.php) — named timing segments that persist
across scopes within a single request: `start()` a timer in one hook or file and
`stop()` it in another, with no globals or hand-passed `microtime( true )` values.
`lap()` records intermediate splits; `get()` / `get_all()` expose the collected data
(in float seconds, like `$wpdb->queries`).

```php
$timer = new Timer();
$timer->start( 'render' );
$timer->lap( 'render', 'after_query' );
// … later, in another hook holding the same instance …
$elapsed = $timer->stop( 'render' );   // float seconds
$all     = $timer->get_all();          // every timer, with computed elapsed
```

- **Instance-based, not a singleton.** The start-here / stop-there pattern shares
  state by sharing the *instance*: register one as `Shareable` in the consumer's
  container (the same pattern as `Cache` / `XHProf_Profiler`) so every hook resolves
  the same object, while a theme and a plugin keep their own decoupled timer sets.
- **Misuse is loud, reads are silent.** Empty / duplicate / never-started /
  already-stopped labels are reported via `_doing_it_wrong()` (the WordPress
  convention for developer error), not exceptions. `get()` / `get_all()` never emit
  notices — an empty or unknown label just returns `null`.
- A running timer's `elapsed` is measured at the moment you read it, without
  stopping it.

## Container

`Container` lives at the `inc/` root and is the storage half of the `Loader` —
a deliberately tiny `set` / `get` / `has` instance map, not a PSR-11 auto-wiring
container. Documented with the registration pieces in
[contracts.md](contracts.md#container).

---

Back to [index.md](index.md).
