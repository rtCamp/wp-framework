# Abstracts — the base-class cookbook

The ten `Abstract*` classes in
[`inc/Contracts/Abstracts/`](../inc/Contracts/Abstracts/) are the part of the
framework a service author touches most. Each one wraps a single WordPress
registration chore so the subclass writes *what* it is, not *how* to register it.

Every abstract here (except `AbstractModule`, which is structural, and
`AbstractFeature`, which gates another service behind a flag) follows the same
shape:

- it `implements Registrable`, so the [`Loader`](architecture.md) drives it;
- its `register_hooks()` attaches **one** WordPress hook;
- it declares a few `abstract` methods for the bits that vary (slug, labels,
  render), and provides sensible, overridable defaults for everything else.

So the lifecycle is always: the loader calls `register_hooks()` → that adds an
action on the right WordPress hook → when that hook fires, the actual
registration runs. Read [architecture.md](architecture.md) if that split isn't
familiar yet.

## Which hook each one uses

| Abstract | Registers on | You must implement |
|---|---|---|
| `AbstractModule` | — (loads children immediately) | `get_classes()` |
| `AbstractPostType` | `init` | `get_slug()`, `get_singular_label()`, `get_plural_label()`, `get_menu_icon()` |
| `AbstractTaxonomy` | `init` | `get_slug()`, `get_object_types()`, `get_singular_label()`, `get_plural_label()` |
| `AbstractBlock` | `init` | `get_name()`, `render()` |
| `AbstractShortcode` | `init` | `get_tag()`, `render()` |
| `AbstractRESTController` | `rest_api_init` | `register_routes()` (see note) |
| `AbstractSettingsPage` | `admin_menu` + `admin_init` + `rest_api_init` | `get_slug()`, `get_page_title()`, `get_menu_title()`, `get_settings()`, `render()` |
| `AbstractAdminPage` | `admin_menu` | `get_slug()`, `get_page_title()`, `get_menu_title()`, `render()` |
| `AbstractUserRole` | `admin_init` | `get_slug()`, `get_display_name()`, `get_capabilities()`, `get_version()` |
| `AbstractFeature` | — (gates the subclass's own hooks) | `get_slug()`, `get_feature_registry()`, plus the subclass's `register_hooks()` |

---

## AbstractModule

[`AbstractModule.php`](../inc/Contracts/Abstracts/AbstractModule.php) — a
`Registrable` that `use`s the `Loader` trait. It groups related services so the
top-level loader deals in a handful of modules instead of dozens of leaf classes.
Its `register_hooks()` just calls `load( $this->get_classes() )`. Covered in full
in [architecture.md](architecture.md#modules-loaders-that-hold-loaders).

```php
final class ContentModule extends AbstractModule {
    protected function get_classes(): array {
        return [ ArticlePostType::class, GenreTaxonomy::class ];
    }
}
```

Because a module is itself a `Loader`, services it loads can be `Shareable` and
retrieved later via the module's `get_shared()`.

---

## Content registration

### AbstractPostType

[`AbstractPostType.php`](../inc/Contracts/Abstracts/AbstractPostType.php) — the
richest abstract. Declare the identity; the base builds the full
`register_post_type()` args and registers on `init`.

**Must implement:** `get_slug()` (static), `get_singular_label()`,
`get_plural_label()`, `get_menu_icon()`.

```php
final class ArticlePostType extends AbstractPostType {
    public static function get_slug(): string    { return 'article'; }
    public function get_singular_label(): string { return __( 'Article', 'my-plugin' ); }
    public function get_plural_label(): string   { return __( 'Articles', 'my-plugin' ); }
    public function get_menu_icon(): string      { return 'dashicons-media-document'; }
}
```

That's a complete, REST-enabled, archive-having post type. To go further, override
the seam you need rather than the whole thing:

| Override | Default | Use it to |
|---|---|---|
| `get_editor_supports()` | `title, editor, author, thumbnail, excerpt, revisions` | add/remove editor features (e.g. `custom-fields`). |
| `get_supported_taxonomies()` | `[]` | associate taxonomies (registered against this type via `register_taxonomy_for_object_type()`). |
| `is_hierarchical()` | `false` | make it page-like. |
| `get_menu_position()` | `null` | place it in the admin menu. |
| `get_labels()` | name + singular_name | supply the full label set. |
| `get_custom_options()` | `[]` | merge in any other `register_post_type()` arg. |
| `get_options()` | the assembled array | replace the whole args array (rarely needed). |
| `after_register()` | no-op | run setup once the type exists (e.g. rewrite-rule flush logic). |

**Defaults worth knowing:** `public`, `has_archive`, `show_in_rest`, and
`show_in_menu` are all `true`; `show_in_nav_menus` is `false`. If those don't
suit, override `get_custom_options()`.

### AbstractTaxonomy

[`AbstractTaxonomy.php`](../inc/Contracts/Abstracts/AbstractTaxonomy.php) — the
taxonomy counterpart, same pattern, also on `init`.

**Must implement:** `get_slug()` (static), `get_object_types()` (static),
`get_singular_label()`, `get_plural_label()`.

```php
final class GenreTaxonomy extends AbstractTaxonomy {
    public static function get_slug(): string        { return 'genre'; }
    public static function get_object_types(): array { return [ 'article' ]; }
    public function get_singular_label(): string     { return __( 'Genre', 'my-plugin' ); }
    public function get_plural_label(): string        { return __( 'Genres', 'my-plugin' ); }
}
```

Same override seams as the post type where they apply: `is_hierarchical()`,
`get_labels()`, `get_custom_options()`, `get_options()`, `after_register()`.
Defaults: `public`, `show_admin_column`, `show_in_rest`, `query_var` all `true`.

> **Two ways to wire a taxonomy to a post type.** Either list it in the post
> type's `get_supported_taxonomies()`, or set the taxonomy's
> `get_object_types()`. Both end up calling WordPress correctly; pick one side as
> the source of truth per project so the association lives in one place.

### AbstractBlock

[`AbstractBlock.php`](../inc/Contracts/Abstracts/AbstractBlock.php) — for
**dynamic** (server-rendered) blocks. Registers on `init`.

**Must implement:** `get_name()` (static, namespaced e.g. `my-plugin/hero`),
`render( array $attributes, string $content, \WP_Block $block ): string`.

The base always wires your `render()` as the block's `render_callback`. Where the
block metadata comes from depends on `get_block_dir()`:

- return an absolute path to a build directory with a `block.json` → registered
  via `register_block_type( $dir, $args )`;
- return `null` (the default) → registered by name with whatever
  `get_block_args()` returns (a purely programmatic block).

```php
final class HeroBlock extends AbstractBlock {
    public static function get_name(): string { return 'my-plugin/hero'; }

    protected function get_block_dir(): ?string {
        return MY_PLUGIN_PATH . 'build/hero';
    }

    public function render( array $attributes, string $content, \WP_Block $block ): string {
        return sprintf( '<section class="hero">%s</section>', esc_html( $attributes['title'] ?? '' ) );
    }
}
```

> For **static** blocks (JSON only, no server render), skip this abstract — the
> class docblock points you at `AssetLoader::register_block_manifest()` instead.
> See [loaders.md](loaders.md).

### AbstractShortcode

[`AbstractShortcode.php`](../inc/Contracts/Abstracts/AbstractShortcode.php) —
registers a shortcode on `init` and gives you a clean, attribute-parsed render
method.

**Must implement:** `get_tag()` (static), `render( array $atts, ?string $content ): string`.

The base's `shortcode_callback()` runs your `$atts` through
`shortcode_atts( $this->default_atts(), … )` before calling `render()`, so by the
time you see them the attributes are merged with your declared defaults.

```php
final class ButtonShortcode extends AbstractShortcode {
    public static function get_tag(): string { return 'button'; }

    protected function default_atts(): array {
        return [ 'href' => '#', 'label' => 'Click' ];
    }

    protected function render( array $atts, ?string $content ): string {
        return sprintf(
            '<a class="btn" href="%s">%s</a>',
            esc_url( $atts['href'] ),
            esc_html( $atts['label'] )
        );
    }
}
```

`render()` is `protected` — it's an implementation detail behind the public
`shortcode_callback()`. **You escape your own output;** the framework parses
attributes but does not sanitise or escape on your behalf.

---

## REST

### AbstractRESTController

[`AbstractRESTController.php`](../inc/Contracts/Abstracts/AbstractRESTController.php)
— extends WordPress core's `WP_REST_Controller` and adds the hook wiring.
Registers `register_routes()` on `rest_api_init`.

It pre-fills two properties: `$namespace` (empty — **you must set it**, usually to
your plugin/theme slug) and `$version` (`'1'`). A child overrides `$namespace`
and implements `register_routes()` to call `register_rest_route()` for each
endpoint.

```php
final class ArticlesController extends AbstractRESTController {
    protected $namespace = 'my-plugin';

    public function register_routes(): void {
        register_rest_route(
            "{$this->namespace}/v{$this->version}",
            '/articles',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_items' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
            ]
        );
    }
}
```

Because it's a real `WP_REST_Controller`, all the core helper methods
(`get_items_permissions_check()`, schema helpers, …) are available to override.

> **Implementation note.** The base declares `register_routes()` as a concrete
> method that throws a "not implemented" `Exception` (the message is prefixed with
> the method name) rather than as `abstract`. The
> effect is "you must override it," but the failure surfaces at **runtime** (when
> `rest_api_init` fires), not at class-load time. Always provide your own
> `register_routes()`. (An `abstract` method would catch a missing override at
> class-load time instead of at first request — a deliberate trade-off here.)

---

## Admin & roles

### AbstractAdminPage

[`AbstractAdminPage.php`](../inc/Contracts/Abstracts/AbstractAdminPage.php) — a
bare admin menu page. Registers on `admin_menu`.

**Must implement:** `get_slug()` (static), `get_page_title()`, `get_menu_title()`,
`render()`.

Override `get_parent_slug()` to choose top-level vs. submenu (it returns `null`
by default = a **top-level** menu), plus `get_capability()` (`manage_options`),
`get_icon()`, `get_position()`.

```php
final class DashboardPage extends AbstractAdminPage {
    public static function get_slug(): string { return 'my-plugin'; }
    protected function get_page_title(): string { return __( 'My Plugin', 'my-plugin' ); }
    protected function get_menu_title(): string { return __( 'My Plugin', 'my-plugin' ); }
    public function render(): void {
        echo '<div class="wrap"><h1>', esc_html( get_admin_page_title() ), '</h1></div>';
    }
}
```

**You escape everything `render()` echoes.** The framework registers the page; the
page body is yours.

### AbstractSettingsPage

[`AbstractSettingsPage.php`](../inc/Contracts/Abstracts/AbstractSettingsPage.php)
— the `AbstractAdminPage` pattern plus the Settings API (a parallel class, not a
subclass — it re-declares the same menu seams). It registers on **three** hooks:
`admin_menu` (the page), `admin_init` (the settings), and `rest_api_init` (so
`show_in_rest` settings are registered for the block editor / REST too).

**Must implement:** `get_slug()` (static), `get_page_title()`, `get_menu_title()`,
`get_settings()`, `render()`.

`get_settings()` returns a map of `option_name => register_setting() args`; the
base loops it and calls `register_setting( $this->get_option_group(), … )` for
each. The option group defaults to the page slug.

```php
final class SettingsPage extends AbstractSettingsPage {
    public static function get_slug(): string { return 'my-plugin-settings'; }
    protected function get_page_title(): string { return __( 'Settings', 'my-plugin' ); }
    protected function get_menu_title(): string { return __( 'Settings', 'my-plugin' ); }

    protected function get_settings(): array {
        return [
            'my_plugin_api_key' => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => false,
            ],
        ];
    }

    public function render(): void { /* settings_fields() + do_settings_sections() + form */ }
}
```

Unlike `AbstractAdminPage`, this one defaults `get_parent_slug()` to
`'options-general.php'` — i.e. it lands under **Settings** as a submenu by
default. Return `null` for a top-level page.

### AbstractUserRole

[`AbstractUserRole.php`](../inc/Contracts/Abstracts/AbstractUserRole.php) — a
**versioned** custom role. Roles are persisted in the database via `add_role()`,
so naively calling `add_role()` on every request is wasteful and re-adding a
changed role is a no-op (WordPress ignores `add_role()` if the role exists). This
abstract solves both with a stored version number.

**Must implement:** `get_slug()` (static), `get_display_name()`,
`get_capabilities()`, `get_version()`.

It hooks `admin_init` → `maybe_update_role()`, which compares `get_version()` to a
stored option and, only when your version is higher, removes and re-adds the role
with fresh capabilities, then bumps the stored version. So: **change the
capabilities, increment `get_version()`, and the role updates itself** on the next
admin request.

```php
final class EditorPlusRole extends AbstractUserRole {
    public static function get_slug(): string { return 'editor_plus'; }
    protected function get_display_name(): string { return 'Editor Plus'; }
    protected function get_version(): int { return 2; }   // bump when caps change
    protected function get_capabilities(): array {
        return [ 'read' => true, 'edit_posts' => true, 'manage_categories' => true ];
    }
}
```

Two things to keep in mind:

- The update runs on `admin_init`, so the role refreshes the next time **an admin
  area request happens**, not the instant you deploy. For activation-time
  registration, call your role's logic from a plugin activation hook as well.
- `remove_role()` is provided for deactivation/uninstall — it drops the role and
  deletes the version option. Call it from your uninstall path; the framework
  won't.

---

## Feature flags

### AbstractFeature

[`AbstractFeature.php`](../inc/Contracts/Abstracts/AbstractFeature.php) — the odd
one out. It doesn't register a WordPress object of its own; it gates a subclass's
**own** registration behind a feature flag. It's the only abstract that
`implements ConditionallyRegistrable`, so the [`Loader`](architecture.md) still
instantiates the class (its constructor runs) but calls `can_register()` before
`register_hooks()` — skipping only the hook registration when the flag is off.

Two jobs, both automatic:

- **Discovery.** On construction — which runs even when the flag is off — it
  registers its slug, name, and description into a shared
  [`FeatureSelector`](utilities.md#featureselector) registry, so the flag shows up
  on the settings page without any extra wiring.
- **Gating.** `can_register()` returns the registry's `is_enabled( $slug )` (an
  instance call via `get_feature_registry()`), so the subclass's `register_hooks()`
  runs only when the flag is enabled.

**Must implement:** `get_slug()`, `get_feature_registry()` (return the shared
registry), and — since `AbstractFeature` deliberately leaves it out — the
subclass's own `register_hooks()` with the actual feature behavior. Optionally
override `get_name()` (defaults to a title-cased slug — `author-bio` → `Author
Bio`) and `get_description()` (defaults to empty).

Because every feature in a project shares one registry, the usual shape is a thin
consumer-side base that supplies it, then one small class per feature:

```php
// One shared registry for the whole plugin.
abstract class Feature extends AbstractFeature {
    protected function get_feature_registry(): FeatureSelector {
        return MyPlugin::feature_selector(); // the shared instance
    }
}

final class AuthorBio extends Feature {
    protected function get_slug(): string { return 'author-bio'; }
    protected function get_description(): string {
        return __( 'Show an author bio box beneath each post.', 'my-plugin' );
    }

    public function register_hooks(): void {          // runs only when the flag is on
        add_filter( 'the_content', [ $this, 'append_bio' ] );
    }
}
```

The feature now appears on the `FeatureSelector` settings page as "Author Bio"
with that description, and its `the_content` filter attaches only while the flag
is enabled. See [utilities.md](utilities.md#featureselector) for the registry and
its settings page.

---

Next: [loaders.md](loaders.md) for the asset and template machinery, or back to
[architecture.md](architecture.md) for how all of these get loaded.
