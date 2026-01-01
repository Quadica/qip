/**
 * Queue Item Component
 *
 * Displays a single queue row with controls.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Get status badge styling.
 *
 * @param {string} status The item status.
 * @return {Object} Styling info.
 */
function getStatusStyle( status ) {
	switch ( status ) {
		case 'complete':
			return { className: 'status-complete', text: __( 'Complete', 'qsa-engraving' ) };
		case 'in_progress':
			return { className: 'status-in-progress', text: __( 'In Progress', 'qsa-engraving' ) };
		default:
			return { className: 'status-pending', text: __( 'Pending', 'qsa-engraving' ) };
	}
}

/**
 * Get group type badge class.
 *
 * @param {string} groupType The group type string.
 * @return {string} CSS class name.
 */
function getGroupTypeClass( groupType ) {
	if ( groupType.includes( 'Same ID' ) && groupType.includes( 'Full' ) ) {
		return 'group-same-full';
	}
	if ( groupType.includes( 'Same ID' ) && groupType.includes( 'Partial' ) ) {
		return 'group-same-partial';
	}
	if ( groupType.includes( 'Mixed ID' ) && groupType.includes( 'Full' ) ) {
		return 'group-mixed-full';
	}
	return 'group-mixed-partial';
}

/**
 * Format serial number.
 *
 * @param {string} serial The serial number.
 * @return {string} Formatted serial.
 */
function formatSerial( serial ) {
	return serial?.padStart( 8, '0' ) || '--------';
}

/**
 * Queue Item component.
 *
 * @param {Object}   props                       Component props.
 * @param {Object}   props.item                  The queue item data.
 * @param {boolean}  props.isLast                Whether this is the last item.
 * @param {boolean}  props.isActive              Whether this item is currently active.
 * @param {Function} props.onStart               Handler for start action.
 * @param {Function} props.onComplete            Handler for complete action.
 * @param {Function} props.onRetry               Handler for retry action.
 * @param {Function} props.onResend              Handler for resend action.
 * @param {Function} props.onRerun               Handler for rerun action.
 * @param {Function} props.onStartPositionChange Handler for start position change.
 * @return {JSX.Element} The component.
 */
export default function QueueItem( {
	item,
	isLast,
	isActive,
	onStart,
	onComplete,
	onRetry,
	onResend,
	onRerun,
	onStartPositionChange,
} ) {
	const [ startPos, setStartPos ] = useState( item.startPosition || 1 );
	const statusStyle = getStatusStyle( item.status );
	const groupTypeClass = getGroupTypeClass( item.groupType );

	/**
	 * Handle start position input change.
	 *
	 * @param {Event} e The input event.
	 */
	const handleStartPosChange = ( e ) => {
		const value = parseInt( e.target.value, 10 );
		if ( value >= 1 && value <= 8 ) {
			setStartPos( value );
			onStartPositionChange( item.id, value );
		}
	};

	// Get serial range for display.
	const serialRange = item.serials && item.serials.length > 0
		? {
				start: item.serials[ 0 ]?.serial_number,
				end: item.serials[ item.serials.length - 1 ]?.serial_number,
		  }
		: null;

	return (
		<div
			className={ `qsa-queue-item ${ item.status === 'in_progress' ? 'is-active' : '' } ${
				isLast ? 'is-last' : ''
			}` }
		>
			{ /* Row Header */ }
			<div className="qsa-queue-item-header">
				<div className="qsa-queue-item-left">
					{ /* Status Icon */ }
					<div className={ `qsa-status-icon ${ statusStyle.className }` }>
						{ item.status === 'complete' && (
							<span className="dashicons dashicons-yes-alt"></span>
						) }
						{ item.status === 'in_progress' && (
							<span className="dashicons dashicons-update spin"></span>
						) }
						{ item.status === 'pending' && (
							<span className="dashicons dashicons-clock"></span>
						) }
					</div>

					{ /* Module Type */ }
					<span className="qsa-module-type">{ item.moduleType }</span>

					{ /* Group Type Badge */ }
					<span className={ `qsa-group-type ${ groupTypeClass }` }>{ item.groupType }</span>

					{ /* Status Badge */ }
					<span className={ `qsa-status-badge ${ statusStyle.className }` }>
						{ statusStyle.text }
					</span>
				</div>

				<div className="qsa-queue-item-right">
					{ /* Action Buttons */ }
					{ item.status === 'pending' && (
						<button
							type="button"
							className="button button-primary qsa-btn-start"
							onClick={ () => onStart( item.id ) }
						>
							<span className="dashicons dashicons-controls-play"></span>
							{ __( 'Engrave', 'qsa-engraving' ) }
						</button>
					) }

					{ item.status === 'in_progress' && (
						<div className="qsa-action-buttons">
							<button
								type="button"
								className="button qsa-btn-resend"
								onClick={ () => onResend( item.id ) }
								title={ __( 'Resend current SVG to laser (same serials)', 'qsa-engraving' ) }
							>
								<span className="dashicons dashicons-update"></span>
								{ __( 'Resend', 'qsa-engraving' ) }
							</button>

							<button
								type="button"
								className="button qsa-btn-retry"
								onClick={ () => onRetry( item.id ) }
								title={ __( 'Scrap current QSA and retry with new serials', 'qsa-engraving' ) }
							>
								<span className="dashicons dashicons-image-rotate"></span>
								{ __( 'Retry', 'qsa-engraving' ) }
							</button>

							<button
								type="button"
								className="button button-primary qsa-btn-complete"
								onClick={ () => onComplete( item.id ) }
							>
								<span className="dashicons dashicons-yes"></span>
								{ __( 'Complete', 'qsa-engraving' ) }
							</button>
						</div>
					) }

					{ item.status === 'complete' && (
						<div className="qsa-action-buttons">
							<button
								type="button"
								className="button qsa-btn-rerun"
								onClick={ () => onRerun( item.id ) }
								title={ __( 'Rerun engraving from beginning', 'qsa-engraving' ) }
							>
								<span className="dashicons dashicons-controls-repeat"></span>
								{ __( 'Rerun', 'qsa-engraving' ) }
							</button>

							<span className="qsa-done-badge">
								<span className="dashicons dashicons-yes-alt"></span>
								{ __( 'Done', 'qsa-engraving' ) }
							</span>
						</div>
					) }
				</div>
			</div>

			{ /* Row Details */ }
			<div className="qsa-queue-item-details">
				{ /* Modules List */ }
				<div className="qsa-detail-group">
					<span className="dashicons dashicons-editor-code"></span>
					<span className="qsa-detail-label">{ __( 'Modules:', 'qsa-engraving' ) }</span>
					<div className="qsa-modules-list">
						{ item.modules.map( ( mod, i ) => (
							<span key={ i } className="qsa-module-sku">
								{ mod.sku } ×{ mod.qty }
							</span>
						) ) }
					</div>
				</div>
			</div>

			{ /* Stats Row */ }
			<div className="qsa-queue-item-stats">
				<div className="qsa-stat-group">
					<span className="qsa-stat-label">{ __( 'Total Modules:', 'qsa-engraving' ) }</span>
					<span className="qsa-stat-value">{ item.totalModules }</span>
				</div>

				<div className="qsa-stat-group">
					<span className="qsa-stat-label">{ __( 'Start Position:', 'qsa-engraving' ) }</span>
					<input
						type="number"
						min="1"
						max="8"
						value={ startPos }
						onChange={ handleStartPosChange }
						disabled={ item.status !== 'pending' }
						className="qsa-start-position-input"
						title={ __( 'Starting position on array (1-8)', 'qsa-engraving' ) }
					/>
				</div>

				{ serialRange && (
					<div className="qsa-stat-group">
						<span className="qsa-stat-label">{ __( 'Serials:', 'qsa-engraving' ) }</span>
						<span className="qsa-serial-range">
							{ formatSerial( serialRange.start ) } - { formatSerial( serialRange.end ) }
						</span>
					</div>
				) }
			</div>

			{ /* In Progress Details */ }
			{ item.status === 'in_progress' && (
				<div className="qsa-in-progress-details">
					<div className="qsa-progress-indicator">
						<div className="qsa-progress-badge">
							<span className="dashicons dashicons-grid-view"></span>
							<span>{ __( 'Array 1 of 1', 'qsa-engraving' ) }</span>
						</div>

						<div className="qsa-progress-info">
							<span className="qsa-info-label">{ __( 'Positions:', 'qsa-engraving' ) }</span>
							<span className="qsa-info-value">
								{ startPos } - { Math.min( startPos + item.totalModules - 1, 8 ) }
							</span>
						</div>

						<div className="qsa-progress-info">
							<span className="qsa-info-label">{ __( 'Modules:', 'qsa-engraving' ) }</span>
							<span className="qsa-info-value">{ item.totalModules }</span>
						</div>

						{ serialRange && (
							<div className="qsa-progress-info">
								<span className="qsa-info-label">{ __( 'Serials:', 'qsa-engraving' ) }</span>
								<span className="qsa-serial-range-active">
									{ formatSerial( serialRange.start ) } - { formatSerial( serialRange.end ) }
								</span>
							</div>
						) }
					</div>

					<div className="qsa-keyboard-hint">
						<span className="keyboard-key">SPACE</span>
						<span>{ __( 'Press spacebar or click Complete to finish', 'qsa-engraving' ) }</span>
					</div>
				</div>
			) }
		</div>
	);
}
