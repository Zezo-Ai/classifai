describe( '[Language processing] Classify Content (Azure OpenAI) Tests', () => {
	before( () => {
		cy.login();
		cy.visit(
			'/wp-admin/tools.php?page=classifai#/language_processing/feature_classification'
		);
		cy.enableFeature();
		cy.selectProvider( 'azure_openai_embeddings' );
		cy.saveFeatureSettings();
		cy.optInAllFeatures();
		cy.disableClassicEditor();
	} );

	beforeEach( () => {
		cy.login();
	} );

	it( 'Can save Azure OpenAI Embeddings "Language Processing" settings', () => {
		cy.visit(
			'/wp-admin/tools.php?page=classifai#/language_processing/feature_classification'
		);

		cy.selectProvider( 'azure_openai_embeddings' );
		cy.get( 'input#azure_openai_embeddings_endpoint_url' )
			.clear()
			.type( 'https://e2e-test-azure-openai.test/' );
		cy.get( 'input#azure_openai_embeddings_api_key' )
			.clear()
			.type( 'password' );
		cy.get( 'input#azure_openai_embeddings_deployment' )
			.clear()
			.type( 'test' );

		cy.enableFeature();
		cy.get( '.settings-allowed-post-types input#post' ).check();
		cy.get(
			'.settings-allowed-post-statuses input#post_status_publish'
		).check();
		cy.get( '#category-enabled' ).check();
		cy.get( '#category-threshold' ).clear().type( 100 );
		cy.saveFeatureSettings();
	} );

	it( 'Can create category and post and category will get auto-assigned', () => {
		// Create test term.
		cy.deleteAllTerms( 'category' );
		cy.createTerm( 'Test', 'category' );

		// Create test post.
		cy.createPost( {
			title: 'Test embeddings',
			content: 'Test embeddings content',
		} );

		// Close post publish panel.
		const closePanelSelector = 'button[aria-label="Close panel"]';
		cy.get( 'body' ).then( ( $body ) => {
			if ( $body.find( closePanelSelector ).length > 0 ) {
				cy.get( closePanelSelector ).click();
			}
		} );

		// Open post settings sidebar.
		cy.openDocumentSettingsSidebar();

		// Find and open the category panel.
		const panelButtonSelector = `.components-panel__body .components-panel__body-title button:contains("Categories")`;

		cy.get( panelButtonSelector ).then( ( $panelButton ) => {
			// Find the panel container.
			const $panel = $panelButton.parents( '.components-panel__body' );

			// Open panel.
			if ( ! $panel.hasClass( 'is-opened' ) ) {
				cy.wrap( $panelButton ).click();
			}

			// Ensure our test category is checked.
			cy.wrap( $panel )
				.find(
					'.editor-post-taxonomies__hierarchical-terms-list .editor-post-taxonomies__hierarchical-terms-choice:first input'
				)
				.should( 'be.checked' );
			cy.wrap( $panel )
				.find( '.editor-post-taxonomies__hierarchical-terms-list' )
				.children()
				.contains( 'Test' );
		} );
	} );

	// TODO: Fix this test.
	it.skip( 'Can see the preview on the settings page', () => {
		cy.visit(
			'/wp-admin/tools.php?page=classifai#/language_processing/feature_classification'
		);

		cy.saveFeatureSettings();

		// Click the Preview button.
		const closePanelSelector = '#get-classifier-preview-data-btn';
		cy.get( closePanelSelector ).click();

		// Check the term is received and visible.
		cy.get( '.tax-row--category' ).should( 'exist' );
	} );

	it( 'Can create category and post and category will not get auto-assigned if feature turned off', () => {
		cy.visit(
			'/wp-admin/tools.php?page=classifai#/language_processing/feature_classification'
		);
		cy.disableFeature();
		cy.saveFeatureSettings();

		// Create test term.
		cy.deleteAllTerms( 'category' );
		cy.createTerm( 'Test', 'category' );

		// Create test post.
		cy.createPost( {
			title: 'Test embeddings disabled',
			content: 'Test embeddings content',
		} );

		// Close post publish panel.
		const closePanelSelector = 'button[aria-label="Close panel"]';
		cy.get( 'body' ).then( ( $body ) => {
			if ( $body.find( closePanelSelector ).length > 0 ) {
				cy.get( closePanelSelector ).click();
			}
		} );

		// Open post settings sidebar.
		cy.openDocumentSettingsSidebar();

		// Find and open the category panel.
		const panelButtonSelector = `.components-panel__body .components-panel__body-title button:contains("Categories")`;

		cy.get( panelButtonSelector ).then( ( $panelButton ) => {
			// Find the panel container.
			const $panel = $panelButton.parents( '.components-panel__body' );

			// Open panel.
			if ( ! $panel.hasClass( 'is-opened' ) ) {
				cy.wrap( $panelButton ).click();
			}

			// Ensure our test category is not checked.
			cy.wrap( $panel )
				.find(
					'.editor-post-taxonomies__hierarchical-terms-list .editor-post-taxonomies__hierarchical-terms-choice:first input'
				)
				.should( 'be.checked' );
			cy.wrap( $panel )
				.find(
					'.editor-post-taxonomies__hierarchical-terms-list .editor-post-taxonomies__hierarchical-terms-choice:first label'
				)
				.contains( 'Uncategorized' );
		} );
	} );

	it( 'Can see the enable button in a post (Classic Editor)', () => {
		cy.enableClassicEditor();

		cy.visit(
			'/wp-admin/tools.php?page=classifai#/language_processing/feature_classification'
		);

		cy.enableFeature();
		cy.get( '.settings-allowed-post-types input#post' ).check();
		cy.get(
			'.settings-allowed-post-statuses input#post_status_publish'
		).check();
		cy.get( '#category-enabled' ).check();
		cy.saveFeatureSettings();

		cy.classicCreatePost( {
			title: 'Embeddings test classic',
			content: "This feature uses OpenAI's Embeddings capabilities.",
			postType: 'post',
		} );

		cy.get( '#classifai_language_processing_metabox' ).should( 'exist' );
		cy.get( '#classifai-process-content' ).check();

		cy.disableClassicEditor();
	} );

	it( 'Can enable/disable content classification feature ', () => {
		cy.disableClassicEditor();

		// Disable feature.
		cy.visit(
			'/wp-admin/tools.php?page=classifai#/language_processing/feature_classification'
		);
		cy.disableFeature();
		cy.saveFeatureSettings();

		// Verify that the feature is not available.
		cy.verifyClassifyContentEnabled( false );

		// Enable feature.
		cy.visit(
			'/wp-admin/tools.php?page=classifai#/language_processing/feature_classification'
		);
		cy.enableFeature();
		cy.saveFeatureSettings();

		// Verify that the feature is available.
		cy.verifyClassifyContentEnabled( true );
	} );

	it( 'Can enable/disable content classification feature by role', () => {
		// Remove custom taxonomies so those don't interfere with the test.
		cy.visit( '/wp-admin/tools.php?page=classifai#/language_processing' );

		// Disable access for all users.
		cy.disableFeatureForUsers();

		cy.saveFeatureSettings();

		// Disable admin role.
		cy.disableFeatureForRoles( 'feature_classification', [
			'administrator',
		] );

		// Verify that the feature is not available.
		cy.verifyClassifyContentEnabled( false );

		// Enable admin role.
		cy.enableFeatureForRoles( 'feature_classification', [
			'administrator',
		] );

		// Verify that the feature is available.
		cy.verifyClassifyContentEnabled( true );
	} );

	it( 'Can enable/disable content classification feature by user', () => {
		// Disable admin role.
		cy.disableFeatureForRoles( 'feature_classification', [
			'administrator',
		] );

		// Verify that the feature is not available.
		cy.verifyClassifyContentEnabled( false );

		// Enable feature for admin user.
		cy.enableFeatureForUsers( 'feature_classification', [ 'admin' ] );

		// Verify that the feature is available.
		cy.verifyClassifyContentEnabled( true );
	} );

	it( 'User can opt-out content classification feature', () => {
		// Enable user based opt-out.
		cy.enableFeatureOptOut(
			'feature_classification',
			'azure_openai_embeddings'
		);

		// opt-out
		cy.optOutFeature( 'feature_classification' );

		// Verify that the feature is not available.
		cy.verifyClassifyContentEnabled( false );

		// opt-in
		cy.optInFeature( 'feature_classification' );

		// Verify that the feature is available.
		cy.verifyClassifyContentEnabled( true );
	} );
} );
