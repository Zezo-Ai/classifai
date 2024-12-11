/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { NavLink } from 'react-router-dom';

/**
 * ClassifAI Onboarding Component.
 *
 * This component handles the rendering of the entire onboarding process for ClassifAI.
 * It guides users through the necessary steps to configure and enable various features.
 *
 * @return {React.ReactElement} ClassifAIOnboarding component.
 */
export const ClassifAIOnboarding = () => {
	return (
		<div className="classifai-setup__content">
			<h1>{ __( 'Welcome to ClassifAI', 'classifai' ) }</h1>
			<div className="classifai-onboarding__welcome-note">
				<p>
					{ __(
						'Our plug-in helps thousands of people across the world to speed up their workflow, using the power of AI.',
						'classifai'
					) }
				</p>
				<p>
					{ __(
						'To get started, you need to follow a few simple steps:',
						'classifai'
					) }
				</p>
				<ol>
					<li>
						<strong>
							{ __(
								'Switch on features that you want to use. ',
								'classifai'
							) }
						</strong>
						<NavLink to="/language_processing">
							{ __(
								'Visit the settings dashboard',
								'classifai'
							) }
						</NavLink>
					</li>
					<li>
						<strong>
							{ __( 'Get an AI platform account.', 'classifai' ) }
						</strong>
					</li>
					<li>
						<strong>
							{ __(
								'Connect your AI platform to ClassifAI. ',
								'classifai'
							) }
						</strong>
						<a
							href="https://github.com/10up/classifai?tab=readme-ov-file#set-up-classification-via-ibm-watson"
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Find out how to get set up', 'classifai' ) }
						</a>
					</li>
				</ol>

				<p>
					{ __(
						'Once that’s done, you can start using the plug-in and see for yourself how much time you save.',
						'classifai'
					) }
				</p>
				<p>
					{ __(
						'If you need any help along the way, ',
						'classifai'
					) }
					<a
						href="https://github.com/10up/classifai#frequently-asked-questions"
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'browse our help topics.', 'classifai' ) }
					</a>
				</p>
				<p>
					{ __(
						'Thanks for choosing ClassifAI from WordPress experts, 10up.',
						'classifai'
					) }
				</p>
			</div>
		</div>
	);
};
