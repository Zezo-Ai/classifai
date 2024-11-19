/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { CheckboxControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SettingsRow } from '../settings-row';
import { STORE_NAME } from '../../data/store';

/**
 * Component for Term Cleanup feature settings.
 *
 * This component is used within the FeatureSettings component
 * to allow users to configure the Term Cleanup feature.
 *
 * @return {React.ReactElement} TermCleanupSettings component.
 */
export const TermCleanupSettings = () => {
	const featureSettings = useSelect( ( select ) =>
		select( STORE_NAME ).getFeatureSettings()
	);
	const { setFeatureSettings } = useDispatch( STORE_NAME );

	let description = sprintf(
		// translators: %1$s: opening anchor tag, %2$s: closing anchor tag
		__(
			'Install and activate the %1$sElasticPress%2$s plugin to use Elasticsearch for finding similar terms.',
			'classifai'
		),
		'<a href="https://wordpress.org/plugins/elasticpress/" target="_blank">',
		'</a>'
	);
	if ( window.classifAISettings?.isEPinstalled ) {
		description = __(
			'Use Elasticsearch for finding similar terms; this will speed up the process for finding similar terms.',
			'classifai'
		);
	}

	return (
		<SettingsRow
			label={ __( 'Use ElasticPress', 'classifai' ) }
			description={ description }
			className="settings-term-cleanup-use-ep"
		>
			<CheckboxControl
				id="use_ep"
				key="use_ep"
				checked={ featureSettings.use_ep }
				disabled={ ! window.classifAISettings?.isEPinstalled }
				label={ __( 'Use ElasticPress', 'classifai' ) }
				onChange={ ( value ) => {
					setFeatureSettings( {
						use_ep: value,
					} );
				} }
			/>
		</SettingsRow>
	);
};
