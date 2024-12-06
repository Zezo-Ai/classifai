/**
 * External dependencies
 */
import { useLocation } from 'react-router-dom';

/**
 * Custom hook to determine if the current page is a setup page.
 *
 * This hook provides an object containing:
 * - `isSetupPage`: A boolean indicating whether the current page is a setup page.
 *
 * @return {Object} An object containing the setup page status.
 */
export const useSetupPage = () => {
	const location = useLocation();
	const isSetupPage =
		location?.pathname?.startsWith( '/classifai_setup' ) || false;
	return {
		isSetupPage,
	};
};
