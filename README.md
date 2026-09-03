<h1 align="center">wp-framework</h1>

<p align="center">
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg" alt="License: GPL-2.0-or-later"></a>
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777bb4.svg" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/runtime%20dependencies-0-brightgreen.svg" alt="Zero runtime dependencies">
</p>

<p align="center">
  A shared PHP base for WordPress projects, distributed as a Composer package
  (<code>rtcamp/wp-framework</code>). It ships the contracts, loaders, and
  utilities that plugins and themes boot through — so you write intent instead
  of registration boilerplate.
</p>

---

`wp-framework` is a **library, not a plugin**. It ships contracts (interfaces,
abstracts, traits) plus concrete loaders and utilities; consuming plugins and
themes build their features on top. **Zero runtime dependencies. PHP 8.2+.**

## Install

```bash
composer require rtcamp/wp-framework
```

PSR-4 autoloading: `rtCamp\WPFramework\` → `inc/`.

## What's inside

- **Registration core** — the spine every consumer boots through:
  - `Registrable`, `ConditionallyRegistrable`, `Shareable`, `CLICommand`
    interfaces
  - the `Loader` trait (instantiate a list of classes, register their hooks,
    cache the shared ones) and the `Container` it stores instances in
- **Twelve `Abstract*` base classes** — one per WordPress registration chore, so
  a consumer writes intent instead of boilerplate: `AbstractModule`,
  `AbstractPostType`, `AbstractTaxonomy`, `AbstractBlock`, `AbstractShortcode`,
  `AbstractRESTController`, `AbstractSettingsPage`, `AbstractAdminPage`,
  `AbstractUserRole`, `AbstractFeature`, `AbstractAbility`,
  `AbstractAbilityRegistrar`
- **Asset & render loaders** — `AssetLoader` (scripts/styles/modules +
  `*.asset.php` manifests), `ComponentLoader` and `TemplateLoader` (resolve
  components/templates across the child-theme → parent-theme → package hierarchy)
- **`Singleton` trait** — standard `get_instance()` with clone/wakeup guards
- **Utilities & services** (`inc/Utils/`) — context-scoped helpers:
  - `Encryptor` — authenticated AES-256-GCM encryption for values stored in the DB
  - `Cache` — typed wrapper over the WP object cache, group-namespaced, optional SWR
  - `FeatureSelector` + `FeatureSelectorSettingsPage` — a fail-closed feature-flag
    registry and its admin toggle page

The contract surface (`inc/Contracts/`) is the public API: every interface,
abstract, and signature there is consumed by dependents, so changes to it are
treated as breaking.

## What's NOT here (intentional)

- **No bootstrap / `Main` class.** The framework gives you the `Loader` trait and
  the contracts; each consumer writes its own entry class that kicks off the first
  `load()`. The framework is the spine, not the application.
- **No project scaffolding engine.** Scaffold/init tooling lives in a separate
  package (`@rtcamp/wp-tooling`), not here.

## Documentation

Start with [docs/index.md](docs/index.md), then:

| Doc | What it covers |
|---|---|
| [architecture.md](docs/architecture.md) | How a class becomes a live hook — the `Registrable` → `Loader` → `Container` flow. Read first. |
| [contracts.md](docs/contracts.md) | The interfaces and traits in detail. |
| [abstracts.md](docs/abstracts.md) | Cookbook for the `Abstract*` base classes. |
| [loaders.md](docs/loaders.md) | `AssetLoader`, `ComponentLoader`, `TemplateLoader` and the theme-override hierarchy. |
| [utilities.md](docs/utilities.md) | `Encryptor`, `Cache`, `FeatureSelector`, and `Container`. |

## Development

```bash
composer install   # PHP dev dependencies
composer check     # lint (PHPCS) + analyse (PHPStan) + test (PHPUnit)
```

Tests run against real WordPress via [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
(`npm install && npm run wp-env start`). TDD: a failing test first (`tests/` mirrors
`inc/`), then the code. Conventions live in [AGENTS.md](AGENTS.md), shared across
all contributors and AI tools.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

[GPL-2.0-or-later](LICENSE.md) © rtCamp

<p align="center">
  <a href="https://rtcamp.com"><img src="https://n8e0ka87m9.gdcdn.us/kfnbt046p8/GitHub_Banner.webp" alt="rtCamp" width="100%"></a>
</p>
