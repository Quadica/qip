/**
 * Queue Item Component
 *
 * Displays a single queue row with controls.
 * Supports multi-array navigation within a single row.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Calculate array breakdown for a row.
 *
 * Determines how many physical QSA arrays are needed based on
 * total modules and starting position offset.
 *
 * @param {number} totalModules Total modules in the row.
 * @param {number} startOffset  Starting position (1-8).
 * @param {Array}  serials      Array of serial objects with serial_number.
 * @return {Array} Array of array info objects.
 */
function calculateArrayBreakdown( totalModules, startOffset, serials = [] ) {
	const arrays = [];
	let remainingModules = totalModules;
	let serialIndex = 0;

	// First array may have offset (positions startOffset to 8)
	if ( startOffset > 1 ) {
		const firstArrayModules = Math.min( remainingModules, 9 - startOffset );
		const arraySerials = serials.slice( serialIndex, serialIndex + firstArrayModules );
		arrays.push( {
			arrayNum: 1,
			startPos: startOffset,
			endPos: startOffset + firstArrayModules - 1,
			moduleCount: firstArrayModules,
			serialStart: arraySerials[ 0 ]?.serial_number || null,
			serialEnd: arraySerials[ arraySerials.length - 1 ]?.serial_number || null,
			description: `${ startOffset }-${ startOffset + firstArrayModules - 1 }`,
		} );
		serialIndex += firstArrayModules;
		remainingModules -= firstArrayModules;
	}

	// Subsequent arrays always start at position 1
	while ( remainingModules > 0 ) {
		const arrayModules = Math.min( remainingModules, 8 );
		const endPos = arrayModules;
		const arraySerials = serials.slice( serialIndex, serialIndex + arrayModules );
		arrays.push( {
			arrayNum: arrays.length + 1,
			startPos: 1,
			endPos: endPos,
			moduleCount: arrayModules,
			serialStart: arraySerials[ 0 ]?.serial_number || null,
			serialEnd: arraySerials[ arraySerials.length - 1 ]?.serial_number || null,
			description: `1-${ endPos }`,
		} );
		serialIndex += arrayModules;
		remainingModules -= arrayModules;
	}

	return arrays;
}

/**
 * Get status badge styling.
 *
 * @param {string} status          The item status.
 * @param {number} completedArrays Number of completed arrays (for partial status).
 * @return {Object} Styling info.
 */
function getStatusStyle( status, completedArrays = 0 ) {
	switch ( status ) {
		case 'complete':
			return { className: 'status-complete', text: __( 'Complete', 'qsa-engraving' ) };
		case 'in_progress':
			return { className: 'status-in-progress', text: __( 'In Progress', 'qsa-engraving' ) };
		case 'partial':
			return {
				className: 'status-partial',
				text: completedArrays > 0
					? `${ completedArrays } ${ __( 'array(s) done', 'qsa-engraving' ) }`
					: __( 'Partial', 'qsa-engraving' ),
			};
		default:
			return { className: 'status-pending', text: __( 'Pending', 'qsa-engraving' ) };
	}
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
 * @param {Object}   props.item                     The queue item data.
 * @param {boolean}  props.isLast                   Whether this is the last item.
 * @param {boolean}  props.isActive                 Whether this item is currently active.
 * @param {number}   props.currentArray             Current array number (1-based) for this item.
 * @param {boolean}  props.isResending              Whether a resend operation is in progress.
 * @param {boolean}  props.isUpdatingStartPosition  Whether start position is being updated (AJAX in flight).
 * @param {Function} props.onStart                  Handler for start action.
 * @param {Function} props.onComplete               Handler for complete action.
 * @param {Function} props.onNextArray              Handler for next array action.
 * @param {Function} props.onResend                 Handler for resend action.
 * @param {Function} props.onRerun                  Handler for rerun action.
 * @param {Function} props.onStartPositionChange    Handler for start position change.
 * @return {JSX.Element} The component.
 */
export default function QueueItem( {
	item,
	isLast,
	isActive,
	currentArray = 1,
	isResending = false,
	isUpdatingStartPosition = false,
	onStart,
	onComplete,
	onNextArray,
	onResend,
	onRerun,
	onStartPositionChange,
} ) {
	const [ startPos, setStartPos ] = useState( item.startPosition || 1 );
	const completedArrays = item.completedArrays || 0;
	const statusStyle = getStatusStyle( item.status, completedArrays );

	// Track qsa_sequences for backend operations.
	const qsaSequences = item.qsa_sequences || [ item.id ];

	// Calculate array breakdown based on module count and start position.
	// This determines how many PHYSICAL arrays are needed.
	const arrays = calculateArrayBreakdown( item.totalModules, startPos, item.serials || [] );

	// Use calculated array count for display (based on modules and start position).
	// This may differ from qsa_sequences.length if modules were previously split
	// across more sequences than necessary.
	const totalArrays = arrays.length;

	const isLastArray = currentArray >= totalArrays;
	const currentArrayDetails = arrays[ currentArray - 1 ] || null;

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
						{ item.status === 'partial' && (
							<span className="dashicons dashicons-marker"></span>
						) }
						{ item.status === 'pending' && (
							<span className="dashicons dashicons-clock"></span>
						) }
					</div>

					{ /* Base Type - Primary Row Identifier */ }
					<span className="qsa-base-type-badge">{ item.baseType }</span>

					{ /* Module Count Summary */ }
					<span className="qsa-module-summary">
						{ item.totalModules } { __( 'modules', 'qsa-engraving' ) }
					</span>

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
							className={ `qsa-btn-start ${ isUpdatingStartPosition ? 'is-loading' : '' }` }
							onClick={ () => onStart( item.id ) }
							disabled={ isUpdatingStartPosition }
							title={ isUpdatingStartPosition
								? __( 'Please wait while start position is being updated...', 'qsa-engraving' )
								: __( 'Start engraving this row', 'qsa-engraving' )
							}
						>
							<span className={ `dashicons ${ isUpdatingStartPosition ? 'dashicons-update spin' : 'dashicons-controls-play' }` }></span>
							{ isUpdatingStartPosition ? __( 'Updating...', 'qsa-engraving' ) : __( 'Engrave', 'qsa-engraving' ) }
						</button>
					) }

					{ item.status === 'partial' && (
						<button
							type="button"
							className="qsa-btn-resume"
							onClick={ () => onStart( item.id ) }
							title={ __( 'Resume engraving from where you left off', 'qsa-engraving' ) }
						>
							<span className="dashicons dashicons-controls-play"></span>
							{ __( 'Resume', 'qsa-engraving' ) }
						</button>
					) }

					{ item.status === 'in_progress' && (
						<div className="qsa-action-buttons">
							<button
								type="button"
								className={ `qsa-btn-resend ${ isResending ? 'is-loading' : '' }` }
								onClick={ () => onResend( item.id, currentArray ) }
								disabled={ isResending }
								title={ __( 'Resend current SVG to laser (same serials)', 'qsa-engraving' ) }
							>
								<span className={ `dashicons dashicons-update ${ isResending ? 'spin' : '' }` }></span>
								{ isResending ? __( 'Sending...', 'qsa-engraving' ) : __( 'Resend', 'qsa-engraving' ) }
							</button>

							{ isLastArray ? (
								<button
									type="button"
									className="qsa-btn-complete"
									onClick={ () => onComplete( item.id ) }
								>
									<span className="dashicons dashicons-yes"></span>
									{ __( 'Complete', 'qsa-engraving' ) }
								</button>
							) : (
								<button
									type="button"
									className="qsa-btn-next-array"
									onClick={ () => onNextArray( item.id, currentArray ) }
									title={ __( 'Press SPACEBAR or click', 'qsa-engraving' ) }
								>
									<span className="dashicons dashicons-arrow-right-alt2"></span>
									{ __( 'Next Array', 'qsa-engraving' ) }
								</button>
							) }
						</div>
					) }

					{ item.status === 'complete' && (
						<div className="qsa-action-buttons">
							<button
								type="button"
								className="qsa-btn-rerun"
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
								<a
									href={ `${ window.qsaEngraving?.adminUrl || '/wp-admin/' }edit.php?post_type=product&s=${ encodeURIComponent( mod.sku ) }` }
									className="qsa-link"
									target="_blank"
									rel="noopener noreferrer"
								>
									{ mod.sku }
								</a>
								{ ' ' }×{ mod.qty }
							</span>
						) ) }
					</div>
				</div>
			</div>

			{ /* Stats Row */ }
			<div className="qsa-queue-item-stats">
				<div className="qsa-stat-group">
					<span className="qsa-stat-label">{ __( 'Arrays:', 'qsa-engraving' ) }</span>
					<span className="qsa-stat-value">{ totalArrays }</span>
				</div>

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
						disabled={ item.status !== 'pending' || isUpdatingStartPosition }
						className={ `qsa-start-position-input ${ isUpdatingStartPosition ? 'is-loading' : '' }` }
						title={ isUpdatingStartPosition
							? __( 'Updating start position...', 'qsa-engraving' )
							: __( 'Starting position on array (1-8)', 'qsa-engraving' )
						}
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

			{ /* Current Array Details Panel */ }
			{ item.status === 'in_progress' && currentArrayDetails && (
				<div className="qsa-in-progress-details">
					<div className="qsa-progress-indicator">
						<div className="qsa-progress-indicator-left">
							<div className="qsa-progress-badge">
								<span className="dashicons dashicons-grid-view"></span>
								<span>
									{ /* translators: %1$d: current array number, %2$d: total arrays */ }
									{ __( 'Array', 'qsa-engraving' ) } { currentArray } { __( 'of', 'qsa-engraving' ) } { totalArrays }
								</span>
							</div>

							<div className="qsa-progress-info">
								<span className="qsa-info-label">{ __( 'Positions:', 'qsa-engraving' ) }</span>
								<span className="qsa-info-value">
									{ currentArrayDetails.startPos } - { currentArrayDetails.endPos }
								</span>
							</div>

							<div className="qsa-progress-info">
								<span className="qsa-info-label">{ __( 'Modules:', 'qsa-engraving' ) }</span>
								<span className="qsa-info-value">{ currentArrayDetails.moduleCount }</span>
							</div>

							{ currentArrayDetails.serialStart && (
								<div className="qsa-progress-info">
									<span className="qsa-info-label">{ __( 'Serials:', 'qsa-engraving' ) }</span>
									<span className="qsa-serial-range-active">
										{ formatSerial( currentArrayDetails.serialStart ) } - { formatSerial( currentArrayDetails.serialEnd ) }
									</span>
								</div>
							) }
						</div>

						{ /* Progress Dots - one per QSA sequence */ }
						<div className="qsa-progress-dots">
							{ qsaSequences.map( ( _, idx ) => (
								<div
									key={ idx }
									className={ `qsa-progress-dot ${
										idx < currentArray - 1
											? 'completed'
											: idx === currentArray - 1
											? 'current'
											: ''
									}` }
								/>
							) ) }
						</div>
					</div>

					<div className="qsa-keyboard-hint">
						<span className="keyboard-key">SPACEBAR</span>
						<span>
							{ isLastArray
								? __( 'Press spacebar or click Complete to finish', 'qsa-engraving' )
								: __( 'Press spacebar or click Next Array to advance', 'qsa-engraving' )
							}
						</span>
					</div>
				</div>
			) }
		</div>
	);
}
