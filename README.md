# wp-framework

Shared PHP base for rtCamp WordPress projects. Consumed as a Composer package
(`rtcamp/wp-framework`) by every rtCamp plugin and theme skeleton — currently
**theme-elementary** and **features-plugin-skeleton**.

It is a **library, not a plugin**: it ships contracts (interfaces, abstracts,
traits) plus concrete loaders and utilities, and the skeletons build on them.
**Zero runtime dependencies.** PHP 8.2+.

## What's inside

- **Registration core** — the spine every skeleton boots through:
  - `Registrable`, `ConditionallyRegistrable`, `Shareable`, `CLICommand`
    interfaces
  - the `Loader` trait (instantiate a list of classes, register their hooks,
    cache the shared ones) and the `Container` it stores instances in
- **Nine `Abstract*` base classes** — one per WordPress registration chore, so a
  consumer writes intent instead of boilerplate: `AbstractModule`,
  `AbstractPostType`, `AbstractTaxonomy`, `AbstractBlock`, `AbstractShortcode`,
  `AbstractRESTController`, `AbstractSettingsPage`, `AbstractAdminPage`,
  `AbstractUserRole`
- **Asset & render loaders** — `AssetLoader` (scripts/styles/modules +
  `*.asset.php` manifests), `ComponentLoader` and `TemplateLoader` (resolve
  components/templates across the child-theme → parent-theme → package hierarchy)
- **`Singleton` trait** — standard `get_instance()` with clone/wakeup guards
- **Utilities & services** (`inc/Utils/`) — context-scoped helpers:
  - `Encryptor` — authenticated AES-256-GCM encryption for values stored in the DB
  - `Cache` — typed wrapper over the WP object cache, group-namespaced, optional SWR
  - `FeatureSelector` + `FeatureSelectorSettingsPage` — a fail-closed feature-flag
    registry and its admin toggle page
  - `XHProf_Profiler` — profile a code block with XHProf; no-ops without the extension

The contract surface (`inc/Contracts/`) is the public API: every interface,
abstract, and signature there is consumed by every skeleton, so changes to it are
treated as breaking.

## What's NOT here (intentional)

- **No bootstrap / `Main` class.** The framework gives you the `Loader` trait and
  the contracts; each skeleton writes its own entry class that kicks off the first
  `load()`. The framework is the spine, not the application.
- **No project scaffolding engine.** The `npm run init` scaffold/init tooling is
  moving into [`@rtcamp/wp-tooling`](https://github.com/rtCamp/wp-tooling).

## Install (consumer side)

```bash
composer require rtcamp/wp-framework
```

PSR-4: `rtCamp\WPFramework\` → `inc/`.

## Documentation

Start with [docs/index.md](docs/index.md), then:

| Doc | What it covers |
|---|---|
| [architecture.md](docs/architecture.md) | How a class becomes a live hook — the `Registrable` → `Loader` → `Container` flow. Read first. |
| [contracts.md](docs/contracts.md) | The interfaces and traits in detail. |
| [abstracts.md](docs/abstracts.md) | Cookbook for the nine `Abstract*` base classes. |
| [loaders.md](docs/loaders.md) | `AssetLoader`, `ComponentLoader`, `TemplateLoader` and the theme-override hierarchy. |
| [utilities.md](docs/utilities.md) | `Encryptor`, `Cache`, `FeatureSelector`, `XHProf_Profiler`, and `Container`. |

## Development

```bash
composer check     # lint (PHPCS) + analyse (PHPStan) + test (PHPUnit)
```

Tests run against real WordPress via [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).
TDD: a failing test first (`tests/` mirrors `inc/`), then the code. See
[AGENTS.md](AGENTS.md) for the full conventions, shared across all AI tools.

## License

GPL-2.0-or-later
