<?php
/**
 *  Abstract class that defines the providers for a service.
 */

namespace Classifai\Providers;

abstract class Provider {

	/**
	 * @var string The ID of the provider.
	 *
	 * To be set in the subclass.
	 */
	const ID = '';

	/**
	 * @var string The display name for the provider, i.e. Azure
	 */
	public $provider_name;

	/**
	 * @var string $provider_service_name Formal name of the provider, i.e AI Vision, NLU, Rekongnition.
	 */
	public $provider_service_name;

	/**
	 * @var string $option_name Name of the option where the provider settings are stored.
	 */
	protected $option_name;

	/**
	 * @var string $service The name of the service this provider belongs to.
	 */
	protected $service;

	/**
	 * Feature instance.
	 *
	 * @var \Classifai\Features\Feature
	 */
	protected $feature_instance = null;

	/**
	 * @var array $features Array of features provided by this provider.
	 */
	protected $features = array();

	/**
	 * Provider constructor.
	 *
	 * @param string $provider_name         The name of the Provider that will appear in the admin tab
	 * @param string $provider_service_name The name of the Service.
	 * @param string $option_name           Name of the option where the provider settings are stored.
	 */
	public function __construct( string $provider_name, string $provider_service_name, string $option_name ) {
		$this->provider_name         = $provider_name;
		$this->provider_service_name = $provider_service_name;
		$this->option_name           = $option_name;
	}

	/**
	 * Provides the provider name.
	 *
	 * @return string
	 */
	public function get_provider_name(): string {
		return $this->provider_name;
	}

	/**
	 * Returns the name of the settings section for this provider.
	 *
	 * @return string
	 */
	public function get_settings_section(): string {
		return $this->option_name;
	}

	/**
	 * Get the option name.
	 *
	 * @return string
	 */
	public function get_option_name(): string {
		return 'classifai_' . $this->option_name;
	}

	/**
	 * Get provider features.
	 *
	 * @return array
	 */
	public function get_features(): array {
		return $this->features;
	}

	/**
	 * Can the Provider be initialized?
	 *
	 * @return bool
	 */
	public function can_register(): bool {
		return $this->is_configured();
	}

	/**
	 * Register the functionality for the Provider.
	 */
	abstract public function register();

	/**
	 * Initialization routine
	 */
	public function register_admin() {
		add_action( 'admin_init', [ $this, 'setup_fields_sections' ] );
	}

	/**
	 * Helper to get the settings and allow for settings default values.
	 *
	 * @param string|bool|mixed $index Optional. Name of the settings option index.
	 * @return string|array|mixed
	 */
	public function get_settings( $index = false ) {
		$defaults = $this->get_default_settings();
		$settings = get_option( $this->get_option_name(), [] );
		$settings = wp_parse_args( $settings, $defaults );

		if ( $index && isset( $settings[ $index ] ) ) {
			return $settings[ $index ];
		}

		return $settings;
	}

	/**
	 * Default settings for Provider.
	 *
	 * @return array
	 */
	public function get_default_settings(): array {
		return [];
	}

	/**
	 * Common entry point for all REST endpoints for this provider.
	 *
	 * @param int    $post_id       The Post Id we're processing.
	 * @param string $route_to_call The name of the route we're going to be processing.
	 * @param array  $args          Optional arguments to pass to the route.
	 * @return mixed
	 */
	public function rest_endpoint_callback( int $post_id, string $route_to_call, array $args = [] ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return null;
	}

	/**
	 * Format the result of most recent request.
	 *
	 * @param array|WP_Error $data Response data to format.
	 * @return string
	 */
	protected function get_formatted_latest_response( $data ): string {
		if ( ! $data ) {
			return __( 'N/A', 'classifai' );
		}

		if ( is_wp_error( $data ) ) {
			return $data->get_error_message();
		}

		return preg_replace( '/,"/', ', "', wp_json_encode( $data ) );
	}

	/**
	 * Returns whether the provider is configured or not.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		$settings = $this->get_settings();

		$is_configured = false;
		if ( ! empty( $settings ) && ! empty( $settings['authenticated'] ) ) {
			$is_configured = true;
		}

		return $is_configured;
	}

	/**
	 * Adds an API key field.
	 *
	 * @param array $args API key field arguments.
	 */
	public function add_api_key_field( array $args = [] ) {
		$default_settings = $this->feature_instance->get_settings();
		$default_settings = $default_settings[ static::ID ];
		$id               = $args['id'] ?? 'api_key';

		add_settings_field(
			$id,
			$args['label'] ?? esc_html__( 'API Key', 'classifai' ),
			[ $this->feature_instance, 'render_input' ],
			$this->feature_instance->get_option_name(),
			$this->feature_instance->get_option_name() . '_section',
			[
				'option_index'  => static::ID,
				'label_for'     => $id,
				'input_type'    => 'password',
				'default_value' => $default_settings[ $id ],
				'class'         => 'classifai-provider-field hidden provider-scope-' . static::ID, // Important to add this.
			]
		);
	}

	/**
	 * Determine if the current user has access of the feature
	 *
	 * @param string $feature Feature to check.
	 * @return bool
	 */
	protected function has_access( string $feature ): bool {
		$access_control = new AccessControl( $this, $feature );
		return $access_control->has_access();
	}

	/**
	 * Determine if the feature is enabled and current user can access the feature
	 *
	 * @param string $feature Feature to check.
	 * @return bool
	 */
	public function is_feature_enabled( string $feature ): bool {
		$is_feature_enabled = false;
		$settings           = $this->get_settings();

		// Check if provider is configured, user has access to the feature and the feature is turned on.
		if (
			$this->is_configured() &&
			$this->has_access( $feature ) &&
			$this->is_enabled( $feature )
		) {
			$is_feature_enabled = true;
		}

		/**
		 * Filter to override permission to a specific classifai feature.
		 *
		 * @since 2.4.0
		 * @hook classifai_{$this->option_name}_enable_{$feature}
		 *
		 * @param {bool}  $is_feature_enabled Is the feature enabled?
		 * @param {array} $settings           Current feature settings.
		 *
		 * @return {bool} Returns true if the user has access and the feature is enabled, false otherwise.
		 */
		return apply_filters( "classifai_{$this->option_name}_enable_{$feature}", $is_feature_enabled, $settings );
	}

	/**
	 * Determine if the feature is turned on.
	 *
	 * Note: This function does not check if the user has access to the feature.
	 *
	 * - Use `is_feature_enabled()` to check if the user has access to the feature and feature is turned on.
	 * - Use `has_access()` to check if the user has access to the feature.
	 *
	 * @param string $feature Feature to check.
	 * @return bool
	 */
	public function is_enabled( string $feature ): bool {
		$settings   = $this->get_settings();
		$enable_key = 'enable_' . $feature;

		// Check if feature is turned on.
		$is_enabled = ( isset( $settings[ $enable_key ] ) && 1 === (int) $settings[ $enable_key ] );

		/**
		 * Filter to override a specific classifai feature enabled.
		 *
		 * @since 2.5.0
		 * @hook classifai_is_{$feature}_enabled
		 *
		 * @param {bool}  $is_enabled Is the feature enabled?
		 * @param {array} $settings   Current feature settings.
		 *
		 * @return {bool} Returns true if the feature is enabled, false otherwise.
		 */
		return apply_filters( "classifai_is_{$feature}_enabled", $is_enabled, $settings );
	}
}
