# Changelog

All notable changes to `rtcamp/wp-framework` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet.

## [1.0.0] - 2026-07-28

Initial release. Requires PHP 8.2+.

### Added

- **Loaders**
  - `AssetLoader` — registers scripts, styles and script modules from a build
    directory, reading dependencies and versions from the generated
    `*.asset.php` manifests, with a `handle()` helper for namespaced handles.
  - `TemplateLoader` — locates and renders templates across the child theme,
    parent theme and the package's own directory, honouring WordPress'
    `locate_template()` precedence, with a per-request location cache.
  - `ComponentLoader` — resolves and renders self-contained component packages
    across the same hierarchy and registers their assets on demand. Render and
    asset hooks are namespaced per loader context, so one package's listeners
    never fire for another's.
- **Composition**
  - `Container` and the `Loader` trait — instantiate a list of classes, register
    hooks for anything `Registrable`, and cache anything `Shareable`.
  - Contracts: `Registrable`, `ConditionallyRegistrable`, `Shareable`,
    `CLICommand`.
  - `Singleton` trait, storing one instance per concrete class so a parent and
    subclass never share one.
- **Abstract base classes** — `AbstractModule`, `AbstractFeature`,
  `AbstractBlock`, `AbstractPostType`, `AbstractTaxonomy`, `AbstractUserRole`,
  `AbstractShortcode`, `AbstractAdminPage`, `AbstractSettingsPage` and
  `AbstractRESTController`.
- **Utilities**
  - `Cache` — object-cache wrapper with group namespacing and opt-in
    stale-while-revalidate.
  - `Encryptor` — authenticated encryption (AES-256-GCM by default), injectable
    per key domain, with a `key()` seam for sourcing secrets from a KMS or
    environment. Secrets of any length are derived to a full cipher-length key.
  - `FeatureSelector` and `FeatureSelectorSettingsPage` — feature flags stored in
    a single option, overridable by PHP constants for hard locks.
  - `Transients` — prefix-namespaced transient access.
  - `Timer` — named, multi-scope timing.
  - `Logger` — levelled logging.
- Reference documentation under `docs/`, a GPL-2.0-or-later `LICENSE`, and a
  WordPress integration test suite running against `@wordpress/env`.

[Unreleased]: https://github.com/rtCamp/wp-framework/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/rtCamp/wp-framework/releases/tag/v1.0.0
