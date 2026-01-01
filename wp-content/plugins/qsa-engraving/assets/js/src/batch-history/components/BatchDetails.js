/**
 * Batch Details Component.
 *
 * Displays detailed information about a selected batch
 * with option to load for re-engraving.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Format a date for display.
 *
 * @param {string} dateString ISO date string.
 * @return {string} Formatted date.
 */
function formatDate( dateString ) {
	if ( ! dateString ) {
		return '';
	}
	const date = new Date( dateString );
	return date.toLocaleDateString( 'en-US', {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	} );
}

/**
 * BatchDetails component.
 *
 * @param {Object}   props                       Props.
 * @param {Object}   props.batch                 Batch data.
 * @param {boolean}  props.loading               Whether details are loading.
 * @param {Function} props.onLoadForReengraving  Callback when load button clicked.
 * @return {JSX.Element} The component.
 */
export default function BatchDetails( { batch, loading, onLoadForReengraving } ) {
	// Loading state.
	if ( loading ) {
		return (
			<div className="qsa-batch-details">
				<div className="qsa-details-header">
					<span className="dashicons dashicons-list-view"></span>
					<span className="qsa-details-title">
						{ __( 'Batch Details', 'qsa-engraving' ) }
					</span>
				</div>
				<div className="qsa-details-loading">
					<span className="spinner is-active"></span>
					<p>{ __( 'Loading batch details...', 'qsa-engraving' ) }</p>
				</div>
			</div>
		);
	}

	// Empty state.
	if ( ! batch ) {
		return (
			<div className="qsa-batch-details">
				<div className="qsa-details-header">
					<span className="dashicons dashicons-list-view"></span>
					<span className="qsa-details-title">
						{ __( 'Batch Details', 'qsa-engraving' ) }
					</span>
				</div>
				<div className="qsa-details-empty">
					<span className="dashicons dashicons-info"></span>
					<p>{ __( 'Select a batch to view details', 'qsa-engraving' ) }</p>
				</div>
			</div>
		);
	}

	return (
		<div className="qsa-batch-details">
			{ /* Header */ }
			<div className="qsa-details-header">
				<span className="dashicons dashicons-list-view"></span>
				<span className="qsa-details-title">
					{ __( 'Batch Details', 'qsa-engraving' ) }
				</span>
			</div>

			{ /* Batch Info Card */ }
			<div className="qsa-details-card">
				<div className="qsa-card-header">
					<span className="qsa-batch-number">
						{ __( 'Batch', 'qsa-engraving' ) } #{ batch.id }
					</span>
					<span className="qsa-batch-status completed">
						<span className="dashicons dashicons-yes-alt"></span>
						{ __( 'Completed', 'qsa-engraving' ) }
					</span>
				</div>

				<div className="qsa-card-info">
					<div className="qsa-info-row">
						<span className="qsa-info-label">
							{ __( 'Created:', 'qsa-engraving' ) }
						</span>
						<span className="qsa-info-value">
							{ formatDate( batch.created_at ) }
						</span>
					</div>
					<div className="qsa-info-row">
						<span className="qsa-info-label">
							{ __( 'Completed:', 'qsa-engraving' ) }
						</span>
						<span className="qsa-info-value">
							{ formatDate( batch.completed_at ) }
						</span>
					</div>
					<div className="qsa-info-row">
						<span className="qsa-info-label">
							{ __( 'Total Arrays:', 'qsa-engraving' ) }
						</span>
						<span className="qsa-info-value">
							{ batch.qsa_count }
						</span>
					</div>
					<div className="qsa-info-row">
						<span className="qsa-info-label">
							{ __( 'Orders:', 'qsa-engraving' ) }
						</span>
						<span className="qsa-info-value">
							{ batch.order_ids.join( ', ' ) }
						</span>
					</div>
					{ batch.created_by_name && (
						<div className="qsa-info-row">
							<span className="qsa-info-label">
								{ __( 'Created by:', 'qsa-engraving' ) }
							</span>
							<span className="qsa-info-value">
								{ batch.created_by_name }
							</span>
						</div>
					) }
				</div>
			</div>

			{ /* Modules List */ }
			<div className="qsa-modules-section">
				<div className="qsa-modules-header">
					<span className="qsa-modules-title">
						{ __( 'Modules in Batch', 'qsa-engraving' ) }
					</span>
					<span className="qsa-modules-count">
						({ batch.module_count })
					</span>
				</div>

				<div className="qsa-modules-list">
					{ batch.modules && batch.modules.map( ( module, index ) => (
						<div key={ index } className="qsa-module-item">
							<div className="qsa-module-main">
								<span className="qsa-module-sku">{ module.module_sku }</span>
								<span className="qsa-module-qty">&times; { module.qty }</span>
							</div>
							<div className="qsa-module-meta">
								<span className="qsa-serial-range">
									{ module.serial_start } - { module.serial_end }
								</span>
								<span className="qsa-order-id">
									{ __( 'Order', 'qsa-engraving' ) } #{ module.order_id }
								</span>
							</div>
						</div>
					) ) }
				</div>
			</div>

			{ /* Action Button */ }
			<div className="qsa-details-actions">
				<button
					onClick={ onLoadForReengraving }
					className="qsa-load-button"
				>
					<span className="dashicons dashicons-update"></span>
					{ __( 'Load for Re-engraving', 'qsa-engraving' ) }
				</button>
			</div>

			{ /* Help Text */ }
			<p className="qsa-details-help">
				{ __( 'Loading this batch will display all modules in the Batch Creator, where you can select specific modules to re-engrave with new serial numbers.', 'qsa-engraving' ) }
			</p>
		</div>
	);
}
