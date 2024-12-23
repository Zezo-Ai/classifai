/**
 * External dependencies
 */
import {
	Route,
	Routes,
	Navigate,
	HashRouter,
	useParams,
	NavLink,
	useLocation,
} from 'react-router-dom';

/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { SlotFillProvider, SnackbarList } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { store as noticeStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import { FeatureSettings, Header, ServiceSettings } from '..';
import { STORE_NAME } from '../../data/store';
import { FeatureContext } from '../feature-settings/context';
import { ClassifAIRegistration } from '../classifai-registration';
import { ClassifAIWelcomeGuide } from './welcome-guide';
import { Notices } from '../feature-settings/notices';

const { services, features } = window.classifAISettings;

/**
 * FeatureSettingsWrapper component to render the feature settings.
 * If the feature is not available from URL parameters, it will redirect to the first feature of selected service.
 *
 * @return {React.ReactElement} The FeatureSettingsWrapper component.
 */
const FeatureSettingsWrapper = () => {
	const { service, feature } = useParams();
	const serviceFeatures = Object.keys( features[ service ] || {} );

	if ( ! serviceFeatures.includes( feature ) ) {
		return <Navigate to={ serviceFeatures[ 0 ] } replace />;
	}

	return (
		<FeatureContext.Provider value={ { featureName: feature } }>
			<FeatureSettings />
		</FeatureContext.Provider>
	);
};

/**
 * ServiceSettingsWrapper component to render the service settings.
 * If the service is not available from URL parameters, it will redirect to the language processing page.
 *
 * @return {React.ReactElement} The ServiceSettingsWrapper component.
 */
const ServiceSettingsWrapper = () => {
	const { service } = useParams();

	// If the service is not available, redirect to the language processing page.
	if ( ! services[ service ] ) {
		return <Navigate to="/language_processing" replace />;
	}

	return <ServiceSettings />;
};

/**
 * ServiceNavigation component to render the service navigation tabs.
 *
 * This component renders the service navigation tabs based on the available services.
 *
 * @return {React.ReactElement} The ServiceNavigation component.
 */
export const ServiceNavigation = () => {
	const location = useLocation();
	const queryParams = new URLSearchParams( location.search );
	const [ showWelcomeGuide, setShowWelcomeGuide ] = useState(
		() => '1' === queryParams.get( 'welcome_guide' )
	);

	const serviceKeys = Object.keys( services || {} );
	return (
		<>
			{ !! showWelcomeGuide && (
				<ClassifAIWelcomeGuide
					closeWelcomeGuide={ () => setShowWelcomeGuide( false ) }
				/>
			) }
			<div className="classifai-tabs" aria-orientation="horizontal">
				{ serviceKeys.map( ( service ) => (
					<NavLink
						to={ service }
						key={ service }
						className={ ( { isActive } ) =>
							isActive
								? 'active-tab classifai-tabs-item'
								: 'classifai-tabs-item'
						}
					>
						{ services[ service ] }
					</NavLink>
				) ) }
				<NavLink
					to="classifai_registration"
					key="classifai_registration"
					className={ ( { isActive } ) =>
						isActive
							? 'active-tab classifai-tabs-item'
							: 'classifai-tabs-item'
					}
				>
					{ __( 'ClassifAI Registration', 'classifai' ) }
				</NavLink>
			</div>
		</>
	);
};

/**
 * Snackbar component to render the snackbar notifications.
 *
 * @return {React.ReactElement} The Snackbar component.
 */
export const SnackbarNotifications = () => {
	const { removeNotice } = useDispatch( noticeStore );

	const { notices } = useSelect( ( select ) => {
		const allNotices = select( noticeStore ).getNotices();
		return {
			notices: allNotices.filter(
				( notice ) => notice.type === 'snackbar'
			),
		};
	}, [] );

	return (
		<SnackbarList
			className="classifai-settings-snackbar-notices"
			notices={ notices }
			onRemove={ ( notice ) => removeNotice( notice ) }
		/>
	);
};

/**
 * Main ClassifAI Settings Component.
 *
 * This component serves as the primary entry point for the ClassifAI settings interface.
 * It is responsible for rendering the header, service navigation, feature navigation, and feature settings based on the current URL path.
 *
 * @return {React.ReactElement} The ClassifAISettings component.
 */
export const ClassifAISettings = () => {
	const { setSettings, setIsLoaded, setError } = useDispatch( STORE_NAME );

	// Load the settings.
	useEffect( () => {
		( async () => {
			try {
				const classifAISettings = await apiFetch( {
					path: '/classifai/v1/settings',
				} );
				setSettings( classifAISettings );
			} catch ( e ) {
				console.error( e ); // eslint-disable-line no-console
				setError(
					sprintf(
						/* translators: %s: error message */
						__( 'Error: %s', 'classifai' ),
						e.message ||
							__(
								'An error occurred while loading the settings. Please try again.',
								'classifai'
							)
					)
				);
			}
			setIsLoaded( true );
		} )();
	}, [ setSettings, setIsLoaded, setError ] );

	// Render admin notices after the header.
	useEffect( () => {
		const isWelcomePage =
			document.location?.hash?.includes( 'classifai_setup' );

		// Ignore showing notices on the welcome page.
		if ( isWelcomePage ) {
			return;
		}

		const notices = document.querySelectorAll(
			'div.updated, div.error, div.notice'
		);
		const target = document.querySelector( '.classifai-admin-notices' );

		notices.forEach( ( notice ) => {
			if ( ! target ) {
				return;
			}

			target.appendChild( notice );
		} );
	}, [] );

	return (
		<SlotFillProvider>
			<HashRouter>
				<Header />
				<div className="classifai-settings-wrapper">
					<div className="classifai-admin-notices wrap"></div>
					<Notices feature={ 'generic-notices' } />
					<ServiceNavigation />
					<Routes>
						<Route
							path=":service"
							element={ <ServiceSettingsWrapper /> }
						/>
						<Route
							path=":service/:feature"
							element={ <FeatureSettingsWrapper /> }
						/>
						<Route
							path="classifai_registration"
							element={ <ClassifAIRegistration /> }
						/>
						{ /* When no routes match, it will redirect to this route path. Note that it should be registered above. */ }
						<Route
							path="*"
							element={
								<Navigate to="/language_processing" replace />
							}
						/>
					</Routes>
				</div>
				<SnackbarNotifications />
			</HashRouter>
		</SlotFillProvider>
	);
};
