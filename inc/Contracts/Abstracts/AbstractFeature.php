<?php
/**
 * Abstract Feature.
 *
 * @package rtCamp\WPFramework\Contracts\Abstracts
 * @since   0.0.1
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Contracts\Abstracts;

use rtCamp\WPFramework\Contracts\Interfaces\ConditionallyRegistrable;
use rtCamp\WPFramework\Utils\FeatureSelector;

/**
 * Class AbstractFeature
 *
 * Base class for feature-flag-gated services. Each concrete feature extends a
 * consumer-defined intermediate that supplies the shared
 * FeatureSelector registry via get_feature_registry().
 *
 * On construction the feature self-registers its slug into the registry so the
 * admin settings page discovers it automatically. can_register() reads the same
 * registry to decide whether register_hooks() should run.
 *
 * @since 0.0.1
 */
abstract class AbstractFeature implements ConditionallyRegistrable {

	/**
	 * Return the slug that identifies this feature flag.
	 *
	 * @return string Feature flag slug.
	 */
	abstract protected function get_slug(): string;

	/**
	 * Return the shared FeatureSelector registry for this consumer.
	 *
	 * @return FeatureSelector Shared registry instance.
	 */
	abstract protected function get_feature_registry(): FeatureSelector;

	/**
	 * Constructor. Self-registers the flag slug into the shared registry.
	 */
	public function __construct() {
		$this->get_feature_registry()->register( $this->get_slug() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function can_register(): bool {
		return $this->get_feature_registry()->is_enabled( $this->get_slug() );
	}
}
