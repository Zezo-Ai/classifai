/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../../data/store';
import { OpenAISettings } from './openai';

/**
 * React Component for OpenAI Embeddings settings.
 *
 * This component is used within the ProviderSettings component to allow users to configure the OpenAI Embeddings settings.
 *
 * @param {Object}  props              Component props.
 * @param {boolean} props.isConfigured Whether the provider is configured.
 *
 * @return {React.ReactElement} OpenAIEmbeddingsSettings component.
 */
export const OpenAIEmbeddingsSettings = ( { isConfigured = false } ) => {
	const providerName = 'openai_embeddings';
	const providerSettings = useSelect(
		( select ) =>
			select( STORE_NAME ).getFeatureSettings( providerName ) || {}
	);
	const { setProviderSettings } = useDispatch( STORE_NAME );
	const onChange = ( data ) => setProviderSettings( providerName, data );

	if ( isConfigured ) {
		return null;
	}

	return (
		<OpenAISettings
			providerSettings={ providerSettings }
			onChange={ onChange }
		/>
	);
};
