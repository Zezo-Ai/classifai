<?php
/**
 * IBM Watson NLU
 */

namespace Classifai\Providers\Watson;

use Classifai\Admin\SavePostHandler;
use Classifai\Admin\PreviewClassifierData;
use Classifai\Providers\Provider;
use Classifai\Taxonomy\TaxonomyFactory;
use Classifai\Features\Classification;
use function Classifai\get_plugin_settings;
use function Classifai\get_post_types_for_language_settings;
use function Classifai\get_post_statuses_for_language_settings;
use function Classifai\get_asset_info;
use function Classifai\check_term_permissions;
use function Classifai\get_classification_mode;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

class NLU extends Provider {

	const ID = 'ibm_watson_nlu';

	/**
	 * @var $taxonomy_factory TaxonomyFactory Watson taxonomy factory
	 */
	public $taxonomy_factory;

	/**
	 * @var $save_post_handler SavePostHandler Triggers a classification with Watson
	 */
	public $save_post_handler;

	/**
	 * @var $nlu_features array The list of NLU features
	 */
	protected $nlu_features = [];

	/**
	 * Watson NLU constructor.
	 *
	 * @param string $service The service this class belongs to.
	 */
	public function __construct( $feature = null ) {
		parent::__construct(
			'IBM Watson',
			'Natural Language Understanding',
			'watson_nlu'
		);

		// Features provided by this provider.
		$this->features = array(
			'content_classification' => __( 'Classify content', 'classifai' ),
		);

		$this->nlu_features = [
			'category' => [
				'feature'           => __( 'Category', 'classifai' ),
				'threshold'         => __( 'Category Threshold (%)', 'classifai' ),
				'taxonomy'          => __( 'Category Taxonomy', 'classifai' ),
				'threshold_default' => WATSON_CATEGORY_THRESHOLD,
				'taxonomy_default'  => WATSON_CATEGORY_TAXONOMY,
			],
			'keyword'  => [
				'feature'           => __( 'Keyword', 'classifai' ),
				'threshold'         => __( 'Keyword Threshold (%)', 'classifai' ),
				'taxonomy'          => __( 'Keyword Taxonomy', 'classifai' ),
				'threshold_default' => WATSON_KEYWORD_THRESHOLD,
				'taxonomy_default'  => WATSON_KEYWORD_TAXONOMY,
			],
			'entity'   => [
				'feature'           => __( 'Entity', 'classifai' ),
				'threshold'         => __( 'Entity Threshold (%)', 'classifai' ),
				'taxonomy'          => __( 'Entity Taxonomy', 'classifai' ),
				'threshold_default' => WATSON_ENTITY_THRESHOLD,
				'taxonomy_default'  => WATSON_ENTITY_TAXONOMY,
			],
			'concept'  => [
				'feature'           => __( 'Concept', 'classifai' ),
				'threshold'         => __( 'Concept Threshold (%)', 'classifai' ),
				'taxonomy'          => __( 'Concept Taxonomy', 'classifai' ),
				'threshold_default' => WATSON_CONCEPT_THRESHOLD,
				'taxonomy_default'  => WATSON_CONCEPT_TAXONOMY,
			],
		];

		$this->feature_instance = $feature;

		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
	}

	public function render_provider_fields() {
		$settings = $this->feature_instance->get_settings( static::ID );

		add_settings_field(
			static::ID . '_endpoint_url',
			esc_html__( 'API URL', 'classifai' ),
			[ $this->feature_instance, 'render_input' ],
			$this->feature_instance->get_option_name(),
			$this->feature_instance->get_option_name() . '_section',
			[
				'option_index'  => static::ID,
				'label_for'     => 'endpoint_url',
				'default_value' => $settings['endpoint_url'],
				'input_type'    => 'text',
				'large'         => true,
			]
		);

		add_settings_field(
			static::ID . '_username',
			esc_html__( 'API Username', 'classifai' ),
			[ $this->feature_instance, 'render_input' ],
			$this->feature_instance->get_option_name(),
			$this->feature_instance->get_option_name() . '_section',
			[
				'option_index'  => static::ID,
				'label_for'     => 'username',
				'default_value' => $settings['username'],
				'input_type'    => 'text',
				'default_value' => 'apikey',
				'large'         => true,
				'class'         => $this->use_username_password() ? 'hidden' : '',
			]
		);

		add_settings_field(
			static::ID . '_password',
			esc_html__( 'API Key', 'classifai' ),
			[ $this->feature_instance, 'render_input' ],
			$this->feature_instance->get_option_name(),
			$this->feature_instance->get_option_name() . '_section',
			[
				'option_index'  => static::ID,
				'label_for'    => 'password',
				'default_value' => $settings['password'],
				'input_type'   => 'password',
				'large'        => true,
			]
		);

		add_settings_field(
			static::ID . '_toggle',
			'',
			function( $args = [] ) {
				printf(
					'<a id="classifai-waston-cred-toggle" href="#" class="%s">%s</a>',
					$args['class'] ?? '',
					$this->use_username_password()
						? esc_html__( 'Use a username/password instead?', 'classifai' )
						: esc_html__( 'Use an API Key instead?', 'classifai' )
				);
			},
			$this->feature_instance->get_option_name(),
			$this->feature_instance->get_option_name() . '_section',
			[
				'class' => 'classifai-provider-field hidden' . ' provider-scope-' . static::ID, // Important to add this.
			]
		);

		foreach ( $this->nlu_features as $classify_by => $labels ) {
			add_settings_field(
				static::ID . '_' . $classify_by,
				esc_html( $labels['feature'] ),
				[ $this, 'render_nlu_feature_settings' ],
				$this->feature_instance->get_option_name(),
				$this->feature_instance->get_option_name() . '_section',
				[
					'option_index'  => static::ID,
					'feature'       => $classify_by,
					'labels'        => $labels,
					'default_value' => $settings[ $classify_by ],
					'class'         => 'classifai-provider-field hidden' . ' provider-scope-' . static::ID, // Important to add this.
				]
			);
		}
	}

	public function get_default_provider_settings() {
		$common_settings = [
			'endpoint_url' => '',
			'apikey'       => '',
			'username'     => '',
			'password'     => '',
		];

		switch ( $this->feature_instance::ID ) {
			case Classification::ID:
				return array_merge(
					$common_settings,
					[
						'category'           => true,
						'category_threshold' => WATSON_CATEGORY_THRESHOLD,
						'category_taxonomy'  => WATSON_CATEGORY_TAXONOMY,

						'keyword'            => true,
						'keyword_threshold'  => WATSON_KEYWORD_THRESHOLD,
						'keyword_taxonomy'   => WATSON_KEYWORD_TAXONOMY,

						'concept'            => false,
						'concept_threshold'  => WATSON_CONCEPT_THRESHOLD,
						'concept_taxonomy'   => WATSON_CONCEPT_TAXONOMY,

						'entity'             => false,
						'entity_threshold'   => WATSON_ENTITY_THRESHOLD,
						'entity_taxonomy'    => WATSON_ENTITY_TAXONOMY,
					]
				);
		}

		return $common_settings;
	}

	/**
	 * Resets the settings for the NLU provider.
	 */
	public function reset_settings() {
		$settings = $this->get_default_settings() ?? [];
		update_option( $this->get_option_name(), $settings );
	}

	/**
	 * Default settings for Watson NLU.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		$default_settings = parent::get_default_settings() ?? [];

		return array_merge(
			$default_settings,
			[
				'enable_content_classification' => false,
				'post_types'                    => [
					'post' => 1,
					'page' => null,
				],
				'post_statuses'                 => [
					'publish' => 1,
					'draft'   => null,
				],
				'features'                      => [
					'category'           => true,
					'category_threshold' => WATSON_CATEGORY_THRESHOLD,
					'category_taxonomy'  => WATSON_CATEGORY_TAXONOMY,

					'keyword'            => true,
					'keyword_threshold'  => WATSON_KEYWORD_THRESHOLD,
					'keyword_taxonomy'   => WATSON_KEYWORD_TAXONOMY,

					'concept'            => false,
					'concept_threshold'  => WATSON_CONCEPT_THRESHOLD,
					'concept_taxonomy'   => WATSON_CONCEPT_TAXONOMY,

					'entity'             => false,
					'entity_threshold'   => WATSON_ENTITY_THRESHOLD,
					'entity_taxonomy'    => WATSON_ENTITY_TAXONOMY,
				],
			]
		);
	}

	/**
	 * Register what we need for the plugin.
	 */
	public function register() {
		if ( ( new Classification() )->is_feature_enabled() ) {
			add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

			// Add classifai meta box to classic editor.
			add_action( 'add_meta_boxes', [ $this, 'add_classifai_meta_box' ], 10, 2 );
			add_action( 'save_post', [ $this, 'classifai_save_post_metadata' ], 5 );

			add_filter( 'rest_api_init', [ $this, 'add_process_content_meta_to_rest_api' ] );

			$this->taxonomy_factory = new TaxonomyFactory();
			$this->taxonomy_factory->build_all();

			$this->save_post_handler = new SavePostHandler();
			$this->save_post_handler->register();

			new PreviewClassifierData();
		}
	}

	/**
	 * Helper to get the settings and allow for settings default values.
	 *
	 * Overridden from parent to polyfill older settings storage schema.
	 *
	 * @param string|bool|mixed $index Optional. Name of the settings option index.
	 *
	 * @return array
	 */
	public function get_settings( $index = false ) {}

	/**
	 * Enqueue the editor scripts.
	 */
	public function enqueue_editor_assets() {
		global $post;
		wp_enqueue_script(
			'classifai-editor',
			CLASSIFAI_PLUGIN_URL . 'dist/editor.js',
			get_asset_info( 'editor', 'dependencies' ),
			get_asset_info( 'editor', 'version' ),
			true
		);

		if ( empty( $post ) ) {
			return;
		}

		wp_enqueue_script(
			'classifai-gutenberg-plugin',
			CLASSIFAI_PLUGIN_URL . 'dist/gutenberg-plugin.js',
			array_merge( get_asset_info( 'gutenberg-plugin', 'dependencies' ), array( 'lodash' ) ),
			get_asset_info( 'gutenberg-plugin', 'dependencies' ),
			get_asset_info( 'gutenberg-plugin', 'version' ),
			true
		);

		wp_localize_script(
			'classifai-gutenberg-plugin',
			'classifaiPostData',
			[
				'NLUEnabled'           => ( new Classification() )->is_feature_enabled(),
				'supportedPostTypes'   => \Classifai\get_supported_post_types(),
				'supportedPostStatues' => \Classifai\get_supported_post_statuses(),
				'noPermissions'        => ! is_user_logged_in() || ! current_user_can( 'edit_post', $post->ID ),
			]
		);
	}

	/**
	 * Enqueue the admin scripts.
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_script(
			'classifai-language-processing-script',
			CLASSIFAI_PLUGIN_URL . 'dist/language-processing.js',
			get_asset_info( 'language-processing', 'dependencies' ),
			get_asset_info( 'language-processing', 'version' ),
			true
		);

		wp_enqueue_style(
			'classifai-language-processing-style',
			CLASSIFAI_PLUGIN_URL . 'dist/language-processing.css',
			array(),
			get_asset_info( 'language-processing', 'version' ),
			'all'
		);
	}

	/**
	 * Setup fields
	 */
	public function setup_fields_sections() {
		// Add the settings section.
		add_settings_section(
			$this->get_option_name(),
			$this->provider_service_name,
			function() {
				printf(
					wp_kses(
						/* translators: %1$s is the link to register for an IBM Cloud account, %2$s is the link to setup the NLU service */
						__( 'Don\'t have an IBM Cloud account yet? <a title="Register for an IBM Cloud account" href="%1$s">Register for one</a> and set up a <a href="%2$s">Natural Language Understanding</a> Resource to get your API key.', 'classifai' ),
						[
							'a' => [
								'href'  => [],
								'title' => [],
							],
						]
					),
					esc_url( 'https://cloud.ibm.com/registration' ),
					esc_url( 'https://cloud.ibm.com/catalog/services/natural-language-understanding' )
				);

				$credentials = $this->get_settings( 'credentials' );
				$watson_url  = $credentials['watson_url'] ?? '';

				if ( ! empty( $watson_url ) && strpos( $watson_url, 'watsonplatform.net' ) !== false ) {
					echo '<div class="notice notice-error"><p><strong>';
						printf(
							wp_kses(
								/* translators: %s is the link to the IBM Watson documentation */
								__( 'The `watsonplatform.net` endpoint URLs were retired on 26 May 2021. Please update the endpoint url. Check <a title="Deprecated Endpoint: watsonplatform.net" href="%s">here</a> for details.', 'classifai' ),
								[
									'a' => [
										'href'  => [],
										'title' => [],
									],
								]
							),
							esc_url( 'https://cloud.ibm.com/docs/watson?topic=watson-endpoint-change' )
						);
					echo '</strong></p></div>';
				}

			},
			$this->get_option_name()
		);
	}

	/**
	 * Check if a username/password is used instead of API key.
	 *
	 * @return bool
	 */
	protected function use_username_password() {
		$settings = $this->get_settings( 'credentials' );

		if ( empty( $settings['watson_username'] ) ) {
			return false;
		}

		return 'apikey' === $settings['watson_username'];
	}

	/**
	 * Render the NLU features settings.
	 *
	 * @param array $args Settings for the inputs
	 *
	 * @return void
	 */
	public function render_nlu_feature_settings( $args ) {
		$feature      = $args['feature'];
		$labels       = $args['labels'];
		$option_index = $args['option_index'];

		$taxonomies = $this->get_supported_taxonomies();
		$features   = $this->feature_instance->get_settings( static::ID );
		$taxonomy   = isset( $features[ "{$feature}_taxonomy" ] ) ? $features[ "{$feature}_taxonomy" ] : $labels['taxonomy_default'];

		// Enable classification type
		$feature_args = [
			'label_for'    => $feature,
			'option_index' => $option_index,
			'input_type'   => 'checkbox',
		];

		$threshold_args = [
			'label_for'     => "{$feature}_threshold",
			'option_index'  => $option_index,
			'input_type'    => 'number',
			'default_value' => $labels['threshold_default'],
		];
		?>

		<fieldset>
		<legend class="screen-reader-text"><?php esc_html_e( 'Watson Category Settings', 'classifai' ); ?></legend>

		<p>
			<?php $this->feature_instance->render_input( $feature_args ); ?>
			<label
				for="classifai-settings-<?php echo esc_attr( $feature ); ?>"><?php esc_html_e( 'Enable', 'classifai' ); ?></label>
		</p>

		<p>
			<label
				for="classifai-settings-<?php echo esc_attr( "{$feature}_threshold" ); ?>"><?php echo esc_html( $labels['threshold'] ); ?></label><br/>
			<?php $this->feature_instance->render_input( $threshold_args ); ?>
		</p>

		<p>
			<label
				for="classifai-settings-<?php echo esc_attr( "{$feature}_taxonomy" ); ?>"><?php echo esc_html( $labels['taxonomy'] ); ?></label><br/>
			<select id="classifai-settings-<?php echo esc_attr( "{$feature}_taxonomy" ); ?>"
				name="<?php echo esc_attr( $this->feature_instance->get_option_name() ); ?>[<?php echo self::ID; ?>][<?php echo esc_attr( "{$feature}_taxonomy" ); ?>]">
				<?php foreach ( $taxonomies as $name => $singular_name ) : ?>
					<option
						value="<?php echo esc_attr( $name ); ?>" <?php selected( $taxonomy, esc_attr( $name ) ); ?> ><?php echo esc_html( $singular_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Return the list of supported taxonomies
	 *
	 * @return array
	 */
	public function get_supported_taxonomies() {
		$taxonomies = \get_taxonomies( [], 'objects' );
		$supported  = [];

		foreach ( $taxonomies as $taxonomy ) {
			$supported[ $taxonomy->name ] = $taxonomy->labels->singular_name;
		}

		return $supported;
	}

	/**
	 * Helper to ensure the authentication works.
	 *
	 * @param array $settings The list of settings to be saved
	 *
	 * @return bool|WP_Error
	 */
	protected function nlu_authentication_check( $settings ) {
		// Check that we have credentials before hitting the API.
		if ( empty( $settings[ static::ID ]['username'] )
			|| empty( $settings[ static::ID ]['password'] )
			|| empty( $settings[ static::ID ]['endpoint_url'] )
		) {
			return new WP_Error( 'auth', esc_html__( 'Please enter your credentials.', 'classifai' ) );
		}

		$request           = new \Classifai\Watson\APIRequest();
		$request->username = $settings[ static::ID ]['username'];
		$request->password = $settings[ static::ID ]['password'];
		$base_url          = trailingslashit( $settings[ static::ID ]['endpoint_url'] ) . 'v1/analyze';
		$url               = esc_url( add_query_arg( [ 'version' => WATSON_NLU_VERSION ], $base_url ) );
		$options           = [
			'body' => wp_json_encode(
				[
					'text'     => 'Lorem ipsum dolor sit amet.',
					'language' => 'en',
					'features' => [
						'keywords' => [
							'emotion' => false,
							'limit'   => 1,
						],
					],
				]
			),
		];

		$response = $request->post( $url, $options );

		if ( ! is_wp_error( $response ) ) {
			update_option( 'classifai_configured', true );
			return true;
		} else {
			delete_option( 'classifai_configured' );
			return $response;
		}
	}

	/**
	 * Sanitization for the options being saved.
	 *
	 * @param array $new_settings Array of settings about to be saved.
	 *
	 * @return array The sanitized settings to be saved.
	 */
	public function sanitize_settings( $new_settings ) {
		$settings      = $this->feature_instance->get_settings();
		$authenticated = $this->nlu_authentication_check( $new_settings );

		if ( is_wp_error( $authenticated ) ) {
			$new_settings[ static::ID ]['authenticated'] = false;
			add_settings_error(
				'classifai-credentials',
				'classifai-auth',
				$authenticated->get_error_message(),
				'error'
			);
		} else {
			$new_settings[ static::ID ]['authenticated'] = true;
		}

		$new_settings[ static::ID ]['endpoint_url'] = esc_url_raw( $new_settings[ static::ID ]['endpoint_url'] ?? $settings[ static::ID ]['endpoint_url'] );
		$new_settings[ static::ID ]['username']     = sanitize_text_field( $new_settings[ static::ID ]['username'] ?? $settings[ static::ID ]['username'] );
		$new_settings[ static::ID ]['password']     = sanitize_text_field( $new_settings[ static::ID ]['password'] ?? $settings[ static::ID ]['password'] );

		$new_settings[ static::ID ]['category']           = absint( $new_settings[ static::ID ]['category'] ?? $settings[ static::ID ]['category'] );
		$new_settings[ static::ID ]['category_threshold'] = absint( $new_settings[ static::ID ]['category_threshold'] ?? $settings[ static::ID ]['category_threshold'] );
		$new_settings[ static::ID ]['category_taxonomy']  = sanitize_text_field( $new_settings[ static::ID ]['category_taxonomy'] ?? $settings[ static::ID ]['category_taxonomy'] );

		$new_settings[ static::ID ]['keyword']           = absint( $new_settings[ static::ID ]['keyword'] ?? $settings[ static::ID ]['keyword'] );
		$new_settings[ static::ID ]['keyword_threshold'] = absint( $new_settings[ static::ID ]['keyword_threshold'] ?? $settings[ static::ID ]['keyword_threshold'] );
		$new_settings[ static::ID ]['keyword_taxonomy']  = sanitize_text_field( $new_settings[ static::ID ]['keyword_taxonomy'] ?? $settings[ static::ID ]['keyword_taxonomy'] );

		$new_settings[ static::ID ]['entity']           = absint( $new_settings[ static::ID ]['entity'] ?? $settings[ static::ID ]['entity'] );
		$new_settings[ static::ID ]['entity_threshold'] = absint( $new_settings[ static::ID ]['entity_threshold'] ?? $settings[ static::ID ]['entity_threshold'] );
		$new_settings[ static::ID ]['entity_taxonomy']  = sanitize_text_field( $new_settings[ static::ID ]['entity_taxonomy'] ?? $settings[ static::ID ]['entity_taxonomy'] );

		$new_settings[ static::ID ]['concept']           = absint( $new_settings[ static::ID ]['concept'] ?? $settings[ static::ID ]['concept'] );
		$new_settings[ static::ID ]['concept_threshold'] = absint( $new_settings[ static::ID ]['concept_threshold'] ?? $settings[ static::ID ]['concept_threshold'] );
		$new_settings[ static::ID ]['concept_taxonomy']  = sanitize_text_field( $new_settings[ static::ID ]['concept_taxonomy'] ?? $settings[ static::ID ]['concept_taxonomy'] );

		return $new_settings;
	}

	/**
	 * Sanitization for the options being saved.
	 *
	 * @param array $settings Array of settings about to be saved.
	 *
	 * @return array The sanitized settings to be saved.
	 */
	public function sanitize_settings_old( $settings ) {
		$new_settings  = $this->get_settings();
		$new_settings  = array_merge( $new_settings, $this->sanitize_access_settings( $settings, 'content_classification' ) );
		$authenticated = $this->nlu_authentication_check( $settings );

		if ( is_wp_error( $authenticated ) ) {
			$new_settings['authenticated'] = false;
			add_settings_error(
				'credentials',
				'classifai-auth',
				$authenticated->get_error_message(),
				'error'
			);
		} else {
			$new_settings['authenticated'] = true;
		}

		if ( isset( $settings['credentials']['watson_url'] ) ) {
			$new_settings['credentials']['watson_url'] = esc_url_raw( $settings['credentials']['watson_url'] );
		}

		if ( isset( $settings['credentials']['watson_username'] ) ) {
			$new_settings['credentials']['watson_username'] = sanitize_text_field( $settings['credentials']['watson_username'] );
		}

		if ( isset( $settings['credentials']['watson_password'] ) ) {
			$new_settings['credentials']['watson_password'] = sanitize_text_field( $settings['credentials']['watson_password'] );
		}

		if ( empty( $settings['enable_content_classification'] ) || 1 !== (int) $settings['enable_content_classification'] ) {
			$new_settings['enable_content_classification'] = 'no';
		} else {
			$new_settings['enable_content_classification'] = '1';
		}

		if ( isset( $settings['classification_mode'] ) ) {
			$new_settings['classification_mode'] = sanitize_text_field( $settings['classification_mode'] );
		}

		// Sanitize the post type checkboxes
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( isset( $settings['post_types'][ $post_type->name ] ) ) {
				$new_settings['post_types'][ $post_type->name ] = absint( $settings['post_types'][ $post_type->name ] );
			} else {
				$new_settings['post_types'][ $post_type->name ] = null;
			}
		}

		// Sanitize the post statuses checkboxes
		$post_statuses = get_post_statuses_for_language_settings();
		foreach ( $post_statuses as $post_status_key => $post_status_value ) {
			if ( isset( $settings['post_statuses'][ $post_status_key ] ) ) {
				$new_settings['post_statuses'][ $post_status_key ] = absint( $settings['post_statuses'][ $post_status_key ] );
			} else {
				$new_settings['post_statuses'][ $post_status_key ] = null;
			}
		}

		$feature_enabled = false;

		foreach ( $this->nlu_features as $feature => $labels ) {
			// Set the enabled flag.
			if ( isset( $settings['features'][ $feature ] ) ) {
				$new_settings['features'][ $feature ] = absint( $settings['features'][ $feature ] );
				$feature_enabled                      = true;
			} else {
				$new_settings['features'][ $feature ] = null;
			}

			// Set the threshold
			if ( isset( $settings['features'][ "{$feature}_threshold" ] ) ) {
				$new_settings['features'][ "{$feature}_threshold" ] = min( absint( $settings['features'][ "{$feature}_threshold" ] ), 100 );
			}

			if ( isset( $settings['features'][ "{$feature}_taxonomy" ] ) ) {
				$new_settings['features'][ "{$feature}_taxonomy" ] = sanitize_text_field( $settings['features'][ "{$feature}_taxonomy" ] );
			}
		}

		// Show a warning if the NLU feature and Embeddings feature are both enabled.
		if ( $feature_enabled && '1' === $new_settings['enable_content_classification'] ) {
			$embeddings_settings = get_plugin_settings( 'language_processing', 'Embeddings' );

			if ( isset( $embeddings_settings['enable_classification'] ) && 1 === (int) $embeddings_settings['enable_classification'] ) {
				add_settings_error(
					'features',
					'conflict',
					esc_html__( 'OpenAI Embeddings classification is turned on. This may conflict with the NLU classification feature. It is possible to run both features but if they use the same taxonomies, one will overwrite the other.', 'classifai' ),
					'warning'
				);
			}
		}

		return $new_settings;
	}

	/**
	 * Provides debug information related to the provider.
	 *
	 * @param array|null $settings Settings array. If empty, settings will be retrieved.
	 * @param boolean    $configured Whether the provider is correctly configured. If null, the option will be retrieved.
	 * @return string|array
	 * @since 1.4.0
	 */
	public function get_provider_debug_information( $settings = null, $configured = null ) {
		if ( is_null( $settings ) ) {
			$settings = $this->sanitize_settings( $this->get_settings() );
		}

		if ( is_null( $configured ) ) {
			$configured = get_option( 'classifai_configured' );
		}

		$settings_post_types = $settings['post_types'] ?? [];
		$post_types          = array_filter(
			array_keys( $settings_post_types ),
			function( $post_type ) use ( $settings_post_types ) {
				return 1 === intval( $settings_post_types[ $post_type ] );
			}
		);

		$credentials = $settings['credentials'] ?? [];

		return [
			__( 'Configured', 'classifai' )      => $configured ? __( 'yes', 'classifai' ) : __( 'no', 'classifai' ),
			__( 'API URL', 'classifai' )         => $credentials['watson_url'] ?? '',
			__( 'API username', 'classifai' )    => $credentials['watson_username'] ?? '',
			__( 'Post types', 'classifai' )      => implode( ', ', $post_types ),
			__( 'Features', 'classifai' )        => preg_replace( '/,"/', ', "', wp_json_encode( $settings['features'] ?? '' ) ),
			__( 'Latest response', 'classifai' ) => $this->get_formatted_latest_response( get_transient( 'classifai_watson_nlu_latest_response' ) ),
		];
	}

	/**
	 * Format the result of most recent request.
	 *
	 * @param array|WP_Error $data Response data to format.
	 *
	 * @return string
	 */
	protected function get_formatted_latest_response( $data ) {
		if ( ! $data ) {
			return __( 'N/A', 'classifai' );
		}

		if ( is_wp_error( $data ) ) {
			return $data->get_error_message();
		}

		$formatted_data = array_intersect_key(
			$data,
			[
				'usage'    => 1,
				'language' => 1,
			]
		);

		foreach ( array_diff_key( $data, $formatted_data ) as $key => $value ) {
			$formatted_data[ $key ] = count( $value );
		}

		return preg_replace( '/,"/', ', "', wp_json_encode( $formatted_data ) );
	}

	/**
	 * Add metabox to enable/disable language processing on post/post types.
	 *
	 * @param string  $post_type Post Type.
	 * @param WP_Post $post      WP_Post object.
	 *
	 * @since 1.8.0
	 */
	public function add_classifai_meta_box( $post_type, $post ) {
		$supported_post_types = \Classifai\get_supported_post_types();
		$post_statuses        = \Classifai\get_supported_post_statuses();
		$post_status          = get_post_status( $post );
		if ( in_array( $post_type, $supported_post_types, true ) && in_array( $post_status, $post_statuses, true ) ) {
			add_meta_box(
				'classifai_language_processing_metabox',
				__( 'ClassifAI Language Processing', 'classifai' ),
				[ $this, 'render_classifai_meta_box' ],
				null,
				'side',
				'low',
				array( '__back_compat_meta_box' => true )
			);
		}
	}

	/**
	 * Render metabox content.
	 *
	 * @param WP_Post $post WP_Post object.
	 *
	 * @since 1.8.0
	 */
	public function render_classifai_meta_box( $post ) {
		wp_nonce_field( 'classifai_language_processing_meta_action', 'classifai_language_processing_meta' );
		$classifai_process_content = get_post_meta( $post->ID, '_classifai_process_content', true );
		$classifai_process_content = ( 'no' === $classifai_process_content ) ? 'no' : 'yes';

		$post_type       = get_post_type_object( get_post_type( $post ) );
		$post_type_label = esc_html__( 'Post', 'classifai' );
		if ( $post_type ) {
			$post_type_label = $post_type->labels->singular_name;
		}
		?>
		<p>
			<label for="_classifai_process_content">
				<input type="checkbox" value="yes" id="_classifai_process_content" name="_classifai_process_content" <?php checked( $classifai_process_content, 'yes' ); ?> />
				<?php esc_html_e( 'Automatically tag content on update', 'classifai' ); ?>
			</label>
		</p>
		<div class="classifai-clasify-post-wrapper" style="display: none;">
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=classifai_classify_post&post_id=' . $post->ID ), 'classifai_classify_post_action', 'classifai_classify_post_nonce' ) ); ?>" class="button button-classify-post">
				<?php
				/* translators: %s Post type label */
				printf( esc_html__( 'Classify %s', 'classifai' ), esc_html( $post_type_label ) );
				?>
			</a>
		</div>
		<?php
	}

	/**
	 * Save language processing meta data on post/post types.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @since 1.8.0
	 */
	public function classifai_save_post_metadata( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || 'revision' === get_post_type( $post_id ) ) {
			return;
		}

		if ( empty( $_POST['classifai_language_processing_meta'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['classifai_language_processing_meta'] ) ), 'classifai_language_processing_meta_action' ) ) {
			return;
		}

		$supported_post_types = \Classifai\get_supported_post_types();
		if ( ! in_array( get_post_type( $post_id ), $supported_post_types, true ) ) {
			return;
		}

		if ( isset( $_POST['_classifai_process_content'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['_classifai_process_content'] ) ) ) {
			$classifai_process_content = 'yes';
		} else {
			$classifai_process_content = 'no';
		}

		update_post_meta( $post_id, '_classifai_process_content', $classifai_process_content );
	}

	/**
	 * Add `classifai_process_content` to rest API for view/edit.
	 */
	public function add_process_content_meta_to_rest_api() {
		$supported_post_types = \Classifai\get_supported_post_types();
		register_rest_field(
			$supported_post_types,
			'classifai_process_content',
			array(
				'get_callback'    => function( $object ) {
					$process_content = get_post_meta( $object['id'], '_classifai_process_content', true );
					return ( 'no' === $process_content ) ? 'no' : 'yes';
				},
				'update_callback' => function ( $value, $object ) {
					$value = ( 'no' === $value ) ? 'no' : 'yes';
					return update_post_meta( $object->ID, '_classifai_process_content', $value );
				},
				'schema'          => [
					'type'    => 'string',
					'context' => [ 'view', 'edit' ],
				],
			)
		);
	}

	/**
	 * Returns whether the provider is configured or not.
	 *
	 * For backwards compat, we've maintained the use of the
	 * `classifai_configured` option. We default to looking for
	 * the `authenticated` setting though.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$is_configured = parent::is_configured();

		if ( ! $is_configured ) {
			$is_configured = (bool) get_option( 'classifai_configured', false );
		}

		return $is_configured;
	}

	public function register_endpoints() {
		register_rest_route(
			'classifai/v1',
			'generate-tags/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'generate_post_tags' ],
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => esc_html__( 'Post ID to generate tags.', 'classifai' ),
					),
				),
				'permission_callback' => [ $this, 'generate_post_tags_permissions_check' ],
			]
		);
	}

	/**
	 * Handle request to generate tags for given post ID.
	 *
	 * @param WP_REST_Request $request The full request object.
	 *
	 * @return array|bool|string|WP_Error
	 */
	public function generate_post_tags( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'id' );

		if ( empty( $post_id ) ) {
			return new WP_Error( 'post_id_required', esc_html__( 'Post ID is required to classify post.', 'classifai' ) );
		}

		return rest_ensure_response(
			$this->rest_endpoint_callback(
				$post_id,
				'classify'
			)
		);
	}

	/**
	 * Common entry point for all REST endpoints for this provider.
	 * This is called by the Service.
	 *
	 * @param int    $post_id The Post Id we're processing.
	 * @param string $route_to_call The route we are processing.
	 * @param array  $args Optional arguments to pass to the route.
	 * @return string|WP_Error
	 */
	public function rest_endpoint_callback( $post_id = 0, $route_to_call = '', $args = [] ) {
		$route_to_call = strtolower( $route_to_call );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new WP_Error( 'post_id_required', esc_html__( 'A valid post ID is required to generate an excerpt.', 'classifai' ) );
		}

		$return = '';

		// Handle all of our routes.
		switch ( $route_to_call ) {
			case 'classify':
				$return = ( new Classification() )->run( $post_id );
				break;
		}

		return $return;
	}

	/**
	 * Handle request to generate tags for given post ID.
	 *
	 * @param int $post_id The Post Id we're processing.
	 *
	 * @return mixed
	 */
	public function classify_post( $post_id ) {
		try {
			if ( empty( $post_id ) ) {
				return new WP_Error( 'post_id_required', esc_html__( 'Post ID is required to classify post.', 'classifai' ) );
			}

			$taxonomy_terms = [];
			$features       = [ 'category', 'keyword', 'concept', 'entity' ];

			// Process post content.
			$result = $this->classify( $post_id );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			foreach ( $features as $feature ) {
				$taxonomy = \Classifai\get_feature_taxonomy( $feature );
				$terms    = wp_get_object_terms( $post_id, $taxonomy );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$taxonomy_terms[ $taxonomy ][] = $term->term_id;
					}
				}
			}

			// Return taxonomy terms.
			return rest_ensure_response( [ 'terms' => $taxonomy_terms ] );
		} catch ( \Exception $e ) {
			return new WP_Error( 'request_failed', $e->getMessage() );
		}
	}

	/**
	 * Check if a given request has access to generate tags
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function generate_post_tags_permissions_check( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'id' );

		// Ensure we have a logged in user that can edit the item.
		if ( empty( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		$post_type     = get_post_type( $post_id );
		$post_type_obj = get_post_type_object( $post_type );

		// Ensure the post type is allowed in REST endpoints.
		if ( ! $post_type || empty( $post_type_obj ) || empty( $post_type_obj->show_in_rest ) ) {
			return false;
		}

		// For all enabled features, ensure the user has proper permissions to add/edit terms.
		foreach ( [ 'category', 'keyword', 'concept', 'entity' ] as $feature ) {
			if ( ! \Classifai\get_feature_enabled( $feature ) ) {
				continue;
			}

			$taxonomy   = \Classifai\get_feature_taxonomy( $feature );
			$permission = check_term_permissions( $taxonomy );

			if ( is_wp_error( $permission ) ) {
				return $permission;
			}
		}

		$post_status   = get_post_status( $post_id );
		$supported     = \Classifai\get_supported_post_types();
		$post_statuses = \Classifai\get_supported_post_statuses();

		// Check if processing allowed.
		if ( ! in_array( $post_status, $post_statuses, true ) || ! in_array( $post_type, $supported, true ) || ! ( new Classification() )->is_feature_enabled() ) {
			return new WP_Error( 'not_enabled', esc_html__( 'Language Processing not enabled for current post.', 'classifai' ) );
		}

		return true;
	}

	/**
	 * Classifies the post specified with the PostClassifier object.
	 * Existing terms relationships are removed before classification.
	 *
	 * @param int $post_id the post to classify & link
	 *
	 * @return array
	 */
	public function classify( $post_id ) {
		/**
		 * Filter whether ClassifAI should classify a post.
		 *
		 * Default is true, return false to skip classifying a post.
		 *
		 * @since 1.2.0
		 * @hook classifai_should_classify_post
		 *
		 * @param {bool} $should_classify Whether the post should be classified. Default `true`, return `false` to skip
		 *                                classification for this post.
		 * @param {int}  $post_id         The ID of the post to be considered for classification.
		 *
		 * @return {bool} Whether the post should be classified.
		 */
		$classifai_should_classify_post = apply_filters( 'classifai_should_classify_post', true, $post_id );
		if ( ! $classifai_should_classify_post ) {
			return false;
		}

		$classifier = new \Classifai\PostClassifier();

		if ( \Classifai\get_feature_enabled( 'category' ) ) {
			wp_delete_object_term_relationships( $post_id, \Classifai\get_feature_taxonomy( 'category' ) );
		}

		if ( \Classifai\get_feature_enabled( 'keyword' ) ) {
			wp_delete_object_term_relationships( $post_id, \Classifai\get_feature_taxonomy( 'keyword' ) );
		}

		if ( \Classifai\get_feature_enabled( 'concept' ) ) {
			wp_delete_object_term_relationships( $post_id, \Classifai\get_feature_taxonomy( 'concept' ) );
		}

		if ( \Classifai\get_feature_enabled( 'entity' ) ) {
			wp_delete_object_term_relationships( $post_id, \Classifai\get_feature_taxonomy( 'entity' ) );
		}

		$output = $classifier->classify_and_link( $post_id );

		if ( is_wp_error( $output ) ) {
			update_post_meta(
				$post_id,
				'_classifai_error',
				wp_json_encode(
					[
						'code'    => $output->get_error_code(),
						'message' => $output->get_error_message(),
					]
				)
			);
		} else {
			// If there is no error, clear any existing error states.
			delete_post_meta( $post_id, '_classifai_error' );
		}

		return $output;
	}
}
