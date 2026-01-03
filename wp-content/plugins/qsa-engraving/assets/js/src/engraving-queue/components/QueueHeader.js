/**
 * Queue Header Component
 *
 * Displays the header with batch information and navigation.
 * Styled to match the Batch Creator WordPress Admin theme.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Queue Header component.
 *
 * @param {Object} props                  Component props.
 * @param {Object} props.batch            The batch data.
 * @param {number} props.activeBatchCount Count of other active batches.
 * @return {JSX.Element} The component.
 */
export default function QueueHeader( { batch, activeBatchCount = 0 } ) {
	// Navigate back based on whether there are other active batches.
	// If there are active batches, go to batch selector (queue page without batch_id).
	// If no active batches, go to dashboard.
	const queueUrl = window.location.href.replace( /&batch_id=\d+/, '' ).replace( /\?batch_id=\d+&/, '?' ).replace( /\?batch_id=\d+$/, '' );
	const dashboardUrl = window.location.href.replace( /qsa-engraving-queue.*/, 'qsa-engraving' );
	const backUrl = activeBatchCount > 0 ? queueUrl : dashboardUrl;
	const backTitle = activeBatchCount > 0
		? __( 'Back to Batch Selector', 'qsa-engraving' )
		: __( 'Back to Dashboard', 'qsa-engraving' );

	return (
		<div className="qsa-queue-header">
			<div className="qsa-queue-header-left">
				<a
					href={ backUrl }
					className="qsa-back-button"
					title={ backTitle }
				>
					<span className="dashicons dashicons-arrow-left-alt2"></span>
				</a>
				<div className="qsa-queue-header-icon">
					<span className="dashicons dashicons-grid-view"></span>
				</div>
				<div className="qsa-queue-header-text">
					<h1>{ __( 'Engraving Queue', 'qsa-engraving' ) }</h1>
					<p>
						{ batch?.batch_name || __( 'Module Engraving Workflow', 'qsa-engraving' ) }
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
								: batch.status === 'complete' || batch.status === 'completed'
								? __( 'Completed', 'qsa-engraving' )
								: __( 'Pending', 'qsa-engraving' ) }
						</span>
					</>
				) }
			</div>
		</div>
	);
}
