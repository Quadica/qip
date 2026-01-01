/**
 * Queue Header Component
 *
 * Displays the header with batch information and navigation.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Queue Header component.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.batch The batch data.
 * @return {JSX.Element} The component.
 */
export default function QueueHeader( { batch } ) {
	const batchCreatorUrl = window.location.href.replace( /-queue.*/, '-batch-creator' );

	return (
		<div className="qsa-queue-header">
			<div className="qsa-queue-header-left">
				<a
					href={ batchCreatorUrl }
					className="qsa-back-button"
					title={ __( 'Back to Batch Creator', 'qsa-engraving' ) }
				>
					<span className="dashicons dashicons-arrow-left-alt2"></span>
				</a>
				<div className="qsa-queue-header-icon">
					<span className="dashicons dashicons-grid-view"></span>
				</div>
				<div className="qsa-queue-header-text">
					<h1>{ __( 'Engraving Queue', 'qsa-engraving' ) }</h1>
					<p>
						{ batch?.batch_name || __( 'LUXEON STAR LEDs Production System', 'qsa-engraving' ) }
					</p>
				</div>
			</div>
			<div className="qsa-queue-header-right">
				{ batch && (
					<>
						<span className="qsa-batch-id">
							{ __( 'Batch', 'qsa-engraving' ) } #{ batch.id }
						</span>
						<span className={ `qsa-batch-status status-${ batch.status }` }>
							{ batch.status === 'in_progress'
								? __( 'In Progress', 'qsa-engraving' )
								: __( 'Completed', 'qsa-engraving' ) }
						</span>
					</>
				) }
			</div>
		</div>
	);
}
