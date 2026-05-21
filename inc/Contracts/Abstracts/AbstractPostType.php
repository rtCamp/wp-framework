<?php
/**
 * Abstract Post Type class.
 *
 * Provides a rich base for registering WordPress custom post types.
 * Subclasses declare the name, labels and icon; the base class builds
 * the full options array and handles registration, including optional
 * taxonomy association and a post-registration hook.
 *
 * Usage:
 *
 *   class FooPostType extends AbstractPostType {
 *       public function get_name(): string          { return 'foo'; }
 *       public function get_singular_label(): string { return 'Foo'; }
 *       public function get_plural_label(): string   { return 'Foos'; }
 *       public function get_menu_icon(): string      { return 'dashicons-admin-post'; }
 *       protected function get_text_domain(): string { return 'my-plugin'; }
 *   }
 *
 * @package rtCamp\WPFramework\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Contracts\Abstracts;

use rtCamp\WPFramework\Contracts\Interfaces\Registrable;

/**
 * Class - AbstractPostType
 */
abstract class AbstractPostType implements Registrable {

	/**
	 * Get the post type slug.
	 *
	 * @return lowercase-string&non-empty-string
	 */
	abstract public function get_name(): string;

	/**
	 * Get the singular label (e.g. "Article").
	 *
	 * @return non-empty-string
	 */
	abstract public function get_singular_label(): string;

	/**
	 * Get the plural label (e.g. "Articles").
	 *
	 * @return non-empty-string
	 */
	abstract public function get_plural_label(): string;

	/**
	 * Get the menu icon.
	 *
	 * Can be a Dashicons class ('dashicons-admin-post'), a base64-encoded
	 * SVG, a URL, or 'none' to leave it empty for CSS.
	 *
	 * @see https://developer.wordpress.org/resource/dashicons/
	 *
	 * @return string
	 */
	abstract public function get_menu_icon(): string;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register' ] );
	}

	/**
	 * Register the post type and its taxonomies.
	 *
	 * Calls after_register() when done — override that for any
	 * additional setup that must run after the post type exists.
	 */
	public function register(): void {
		$this->register_post_type();
		$this->register_taxonomies();
		$this->after_register();
	}

	/**
	 * Register the post type with WordPress.
	 */
	public function register_post_type(): void {
		register_post_type( $this->get_name(), $this->get_options() ); // phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral
	}

	/**
	 * Associate supported taxonomies with this post type.
	 *
	 * Called after register_post_type(). Uses the slugs returned by
	 * get_supported_taxonomies() to call register_taxonomy_for_object_type().
	 */
	public function register_taxonomies(): void {
		foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
			register_taxonomy_for_object_type( $taxonomy, $this->get_name() );
		}
	}

	/**
	 * Runs after the post type has been registered.
	 *
	 * Override to add any additional setup (e.g. flushing rewrite rules on
	 * first activation) without touching the registration logic.
	 */
	public function after_register(): void {}

	/**
	 * Get the full args array passed to register_post_type().
	 *
	 * Override to customise any option; or override the individual
	 * helper methods (get_editor_supports, is_hierarchical, …) instead.
	 *
	 * @return array<string, mixed>
	 */
	public function get_options(): array {
		$options = [
			'labels'            => $this->get_labels(),
			'public'            => true,
			'has_archive'       => true,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_nav_menus' => false,
			'show_in_rest'      => true,
			'supports'          => $this->get_editor_supports(),
			'menu_icon'         => $this->get_menu_icon(),
			'hierarchical'      => $this->is_hierarchical(),
		];

		$menu_position = $this->get_menu_position();
		if ( null !== $menu_position ) {
			$options['menu_position'] = $menu_position;
		}

		return $options;
	}

	/**
	 * Build the labels array from the singular and plural label methods.
	 *
	 * Uses get_text_domain() for translations — override that in the
	 * child class to set your plugin's text domain.
	 *
	 * @return array<string, string>
	 */
	public function get_labels(): array {
		$singular = $this->get_singular_label();
		$plural   = $this->get_plural_label();
		$domain   = $this->get_text_domain();

		// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment,WordPress.WP.I18n.NonSingularStringLiteralDomain
		return [
			'name'                     => $plural,
			'singular_name'            => $singular,
			'add_new'                  => sprintf( __( 'Add New %s', $domain ), $singular ),
			'add_new_item'             => sprintf( __( 'Add New %s', $domain ), $singular ),
			'edit_item'                => sprintf( __( 'Edit %s', $domain ), $singular ),
			'new_item'                 => sprintf( __( 'New %s', $domain ), $singular ),
			'view_item'                => sprintf( __( 'View %s', $domain ), $singular ),
			'view_items'               => sprintf( __( 'View %s', $domain ), $plural ),
			'search_items'             => sprintf( __( 'Search %s', $domain ), $plural ),
			'not_found'                => sprintf( __( 'No %s found.', $domain ), strtolower( $plural ) ),
			'not_found_in_trash'       => sprintf( __( 'No %s found in Trash.', $domain ), strtolower( $plural ) ),
			'parent_item_colon'        => sprintf( __( 'Parent %s:', $domain ), $plural ),
			'all_items'                => sprintf( __( 'All %s', $domain ), $plural ),
			'archives'                 => sprintf( __( '%s Archives', $domain ), $singular ),
			'attributes'               => sprintf( __( '%s Attributes', $domain ), $singular ),
			'insert_into_item'         => sprintf( __( 'Insert into %s', $domain ), strtolower( $singular ) ),
			'uploaded_to_this_item'    => sprintf( __( 'Uploaded to this %s', $domain ), strtolower( $singular ) ),
			'filter_items_list'        => sprintf( __( 'Filter %s list', $domain ), strtolower( $plural ) ),
			'items_list_navigation'    => sprintf( __( '%s list navigation', $domain ), $plural ),
			'items_list'               => sprintf( __( '%s list', $domain ), $plural ),
			'item_published'           => sprintf( __( '%s published.', $domain ), $singular ),
			'item_published_privately' => sprintf( __( '%s published privately.', $domain ), $singular ),
			'item_reverted_to_draft'   => sprintf( __( '%s reverted to draft.', $domain ), $singular ),
			'item_scheduled'           => sprintf( __( '%s scheduled.', $domain ), $singular ),
			'item_updated'             => sprintf( __( '%s updated.', $domain ), $singular ),
			'menu_name'                => $plural,
			'name_admin_bar'           => $singular,
		];
		// phpcs:enable WordPress.WP.I18n.MissingTranslatorsComment,WordPress.WP.I18n.NonSingularStringLiteralDomain
	}

	/**
	 * Get the supported editor features.
	 *
	 * Override to add or remove entries (e.g. add 'custom-fields').
	 *
	 * @return list<string>
	 */
	public function get_editor_supports(): array {
		return [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions' ];
	}

	/**
	 * Get taxonomy slugs to associate with this post type via
	 * register_taxonomy_for_object_type().
	 *
	 * @return list<string>
	 */
	public function get_supported_taxonomies(): array {
		return [];
	}

	/**
	 * Get the menu position for the post type.
	 *
	 * Return null to use the WordPress default.
	 *
	 * @return int|null
	 */
	public function get_menu_position(): ?int {
		return null;
	}

	/**
	 * Whether this post type is hierarchical (like pages).
	 *
	 * @return bool
	 */
	public function is_hierarchical(): bool {
		return false;
	}

	/**
	 * Get the text domain for label translations.
	 *
	 * Override in the child class with your plugin's or theme's text domain.
	 *
	 * @return string
	 */
	protected function get_text_domain(): string {
		return '';
	}
}
