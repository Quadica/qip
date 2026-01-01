/**
 * Engraving Queue Component
 *
 * Main container for the engraving workflow interface.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import QueueHeader from './QueueHeader';
import StatsBar from './StatsBar';
import QueueItem from './QueueItem';

/**
 * Get batch ID from URL query parameters.
 *
 * @return {number|null} The batch ID or null if not present.
 */
function getBatchIdFromUrl() {
	const urlParams = new URLSearchParams( window.location.search );
	const batchId = urlParams.get( 'batch_id' );
	return batchId ? parseInt( batchId, 10 ) : null;
}

/**
 * Engraving Queue component.
 *
 * @return {JSX.Element} The component.
 */
export default function EngravingQueue() {
	const [ batchId ] = useState( getBatchIdFromUrl );
	const [ batch, setBatch ] = useState( null );
	const [ queueItems, setQueueItems ] = useState( [] );
	const [ capacity, setCapacity ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ activeItemId, setActiveItemId ] = useState( null );
	const [ lightburnStatus, setLightburnStatus ] = useState( {
		enabled: window.qsaEngraving?.lightburnEnabled || false,
		autoLoad: window.qsaEngraving?.lightburnAutoLoad || true,
		connected: false,
		loading: false,
		lastFile: null,
	} );

	/**
	 * Fetch queue data from the server.
	 */
	const fetchQueue = useCallback( async () => {
		if ( ! batchId ) {
			setError( __( 'No batch ID specified.', 'qsa-engraving' ) );
			setLoading( false );
			return;
		}

		try {
			const formData = new FormData();
			formData.append( 'action', 'qsa_get_queue' );
			formData.append( 'nonce', window.qsaEngraving?.nonce || '' );
			formData.append( 'batch_id', batchId );

			const response = await fetch( window.qsaEngraving?.ajaxUrl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData,
			} );

			const data = await response.json();

			if ( data.success ) {
				setBatch( data.data.batch );
				setQueueItems( data.data.queue_items );
				setCapacity( data.data.capacity );
				setError( null );
			} else {
				setError( data.message || __( 'Failed to load queue.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			setError( __( 'Network error loading queue.', 'qsa-engraving' ) );
		}

		setLoading( false );
	}, [ batchId ] );

	// Initial load.
	useEffect( () => {
		fetchQueue();
	}, [ fetchQueue ] );

	/**
	 * Restore active item ID from queue state on load/reload.
	 * Sets activeItemId to the first in_progress row if one exists.
	 */
	useEffect( () => {
		if ( queueItems.length > 0 && activeItemId === null ) {
			const inProgressItem = queueItems.find( ( item ) => item.status === 'in_progress' );
			if ( inProgressItem ) {
				setActiveItemId( inProgressItem.id );
			}
		}
	}, [ queueItems, activeItemId ] );

	/**
	 * Handle keyboard shortcuts.
	 */
	useEffect( () => {
		const handleKeyDown = ( e ) => {
			// Ignore keyboard shortcuts when an input, textarea, or select is focused.
			const activeElement = document.activeElement;
			const tagName = activeElement?.tagName?.toLowerCase();
			if ( tagName === 'input' || tagName === 'textarea' || tagName === 'select' ) {
				return;
			}

			// Also check for contenteditable elements.
			if ( activeElement?.isContentEditable ) {
				return;
			}

			// Spacebar advances to next array (when in progress).
			if ( e.code === 'Space' && activeItemId !== null ) {
				e.preventDefault();

				const activeItem = queueItems.find( ( item ) => item.id === activeItemId );
				if ( activeItem && activeItem.status === 'in_progress' ) {
					// Trigger complete for this row.
					handleComplete( activeItemId );
				}
			}
		};

		window.addEventListener( 'keydown', handleKeyDown );
		return () => window.removeEventListener( 'keydown', handleKeyDown );
	}, [ activeItemId, queueItems ] );

	/**
	 * Make AJAX request for queue operations.
	 *
	 * @param {string} action  The AJAX action.
	 * @param {Object} params  Additional parameters.
	 * @return {Promise<Object>} The response data.
	 */
	const queueAction = async ( action, params = {} ) => {
		const formData = new FormData();
		formData.append( 'action', action );
		formData.append( 'nonce', window.qsaEngraving?.nonce || '' );
		formData.append( 'batch_id', batchId );

		Object.entries( params ).forEach( ( [ key, value ] ) => {
			formData.append( key, value );
		} );

		const response = await fetch( window.qsaEngraving?.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData,
		} );

		return response.json();
	};

	/**
	 * Generate and optionally load SVG in LightBurn.
	 *
	 * @param {number}  qsaSequence The QSA sequence number.
	 * @param {boolean} autoLoad    Whether to auto-load in LightBurn.
	 * @return {Promise<Object|null>} The SVG generation result or null on error.
	 */
	const generateSvg = async ( qsaSequence, autoLoad = true ) => {
		try {
			setLightburnStatus( ( prev ) => ( { ...prev, loading: true } ) );

			const data = await queueAction( 'qsa_generate_svg', {
				qsa_sequence: qsaSequence,
				auto_load: autoLoad && lightburnStatus.enabled ? '1' : '0',
			} );

			if ( data.success ) {
				setLightburnStatus( ( prev ) => ( {
					...prev,
					loading: false,
					lastFile: data.data.filename,
					connected: data.data.lightburn_loaded || prev.connected,
				} ) );
				return data.data;
			}
			setLightburnStatus( ( prev ) => ( { ...prev, loading: false } ) );
			return null;
		} catch ( err ) {
			setLightburnStatus( ( prev ) => ( { ...prev, loading: false } ) );
			return null;
		}
	};

	/**
	 * Handle start engraving for a row.
	 *
	 * @param {number} qsaSequence The QSA sequence number.
	 */
	const handleStart = async ( qsaSequence ) => {
		try {
			const data = await queueAction( 'qsa_start_row', { qsa_sequence: qsaSequence } );

			if ( data.success ) {
				setActiveItemId( qsaSequence );
				// Update the queue item status.
				setQueueItems( ( prev ) =>
					prev.map( ( item ) =>
						item.id === qsaSequence
							? { ...item, status: 'in_progress', serials: data.data.serials }
							: item
					)
				);

				// Generate SVG and optionally auto-load in LightBurn.
				if ( lightburnStatus.enabled ) {
					const svgResult = await generateSvg( qsaSequence, lightburnStatus.autoLoad );
					if ( svgResult && ! svgResult.lightburn_loaded && lightburnStatus.autoLoad ) {
						// SVG generated but LightBurn load failed.
						console.warn( 'SVG generated but LightBurn load failed:', svgResult.lightburn_error );
					}
				}
			} else {
				alert( data.message || __( 'Failed to start row.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			alert( __( 'Network error starting row.', 'qsa-engraving' ) );
		}
	};

	/**
	 * Handle complete/next for a row.
	 *
	 * @param {number} qsaSequence The QSA sequence number.
	 */
	const handleComplete = async ( qsaSequence ) => {
		try {
			const data = await queueAction( 'qsa_complete_row', { qsa_sequence: qsaSequence } );

			if ( data.success ) {
				setActiveItemId( null );
				// Update the queue item status.
				setQueueItems( ( prev ) =>
					prev.map( ( item ) =>
						item.id === qsaSequence ? { ...item, status: 'complete' } : item
					)
				);

				// If batch is complete, show notification.
				if ( data.data.batch_complete ) {
					alert( __( 'Batch complete! All modules have been engraved.', 'qsa-engraving' ) );
				}
			} else {
				alert( data.message || __( 'Failed to complete row.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			alert( __( 'Network error completing row.', 'qsa-engraving' ) );
		}
	};

	/**
	 * Handle retry for current array.
	 *
	 * @param {number} qsaSequence The QSA sequence number.
	 */
	const handleRetry = async ( qsaSequence ) => {
		if ( ! confirm( __( 'This will void current serials and assign new ones. Continue?', 'qsa-engraving' ) ) ) {
			return;
		}

		try {
			const data = await queueAction( 'qsa_retry_array', { qsa_sequence: qsaSequence } );

			if ( data.success ) {
				// Update serials for this item.
				setQueueItems( ( prev ) =>
					prev.map( ( item ) =>
						item.id === qsaSequence ? { ...item, serials: data.data.serials } : item
					)
				);
			} else {
				alert( data.message || __( 'Failed to retry.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			alert( __( 'Network error during retry.', 'qsa-engraving' ) );
		}
	};

	/**
	 * Handle resend SVG to LightBurn.
	 *
	 * @param {number} qsaSequence The QSA sequence number.
	 */
	const handleResend = async ( qsaSequence ) => {
		try {
			setLightburnStatus( ( prev ) => ( { ...prev, loading: true } ) );

			// Try to load existing SVG in LightBurn.
			const data = await queueAction( 'qsa_load_svg', { qsa_sequence: qsaSequence } );

			if ( data.success ) {
				setLightburnStatus( ( prev ) => ( {
					...prev,
					loading: false,
					lastFile: data.data.filename,
					connected: true,
				} ) );
			} else {
				// File not found - regenerate it.
				const svgResult = await generateSvg( qsaSequence, true );
				if ( svgResult ) {
					setLightburnStatus( ( prev ) => ( { ...prev, loading: false } ) );
				} else {
					setLightburnStatus( ( prev ) => ( { ...prev, loading: false } ) );
					alert( data.message || __( 'Failed to load SVG. Try generating a new one.', 'qsa-engraving' ) );
				}
			}
		} catch ( err ) {
			setLightburnStatus( ( prev ) => ( { ...prev, loading: false } ) );
			alert( __( 'Network error during resend.', 'qsa-engraving' ) );
		}
	};

	/**
	 * Handle rerun for a completed row.
	 *
	 * @param {number} qsaSequence The QSA sequence number.
	 */
	const handleRerun = async ( qsaSequence ) => {
		if ( ! confirm( __( 'This will reset this row to pending for re-engraving. Continue?', 'qsa-engraving' ) ) ) {
			return;
		}

		try {
			const data = await queueAction( 'qsa_rerun_row', { qsa_sequence: qsaSequence } );

			if ( data.success ) {
				setQueueItems( ( prev ) =>
					prev.map( ( item ) =>
						item.id === qsaSequence ? { ...item, status: 'pending', serials: [] } : item
					)
				);
			} else {
				alert( data.message || __( 'Failed to rerun.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			alert( __( 'Network error during rerun.', 'qsa-engraving' ) );
		}
	};

	/**
	 * Handle start position change.
	 *
	 * @param {number} qsaSequence   The QSA sequence number.
	 * @param {number} startPosition The new start position (1-8).
	 */
	const handleStartPositionChange = async ( qsaSequence, startPosition ) => {
		try {
			const data = await queueAction( 'qsa_update_start_position', {
				qsa_sequence: qsaSequence,
				start_position: startPosition,
			} );

			if ( data.success ) {
				setQueueItems( ( prev ) =>
					prev.map( ( item ) =>
						item.id === qsaSequence ? { ...item, startPosition } : item
					)
				);
			} else {
				alert( data.message || __( 'Failed to update start position.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			alert( __( 'Network error updating start position.', 'qsa-engraving' ) );
		}
	};

	// Calculate stats for the stats bar.
	const stats = {
		totalItems: queueItems.length,
		completedItems: queueItems.filter( ( item ) => item.status === 'complete' ).length,
		totalModules: queueItems.reduce( ( sum, item ) => sum + item.totalModules, 0 ),
		completedModules: queueItems
			.filter( ( item ) => item.status === 'complete' )
			.reduce( ( sum, item ) => sum + item.totalModules, 0 ),
	};

	// Render loading state.
	if ( loading ) {
		return (
			<div className="qsa-queue-loading">
				<span className="spinner is-active"></span>
				<p>{ __( 'Loading engraving queue...', 'qsa-engraving' ) }</p>
			</div>
		);
	}

	// Render error state.
	if ( error ) {
		return (
			<div className="qsa-queue-error">
				<div className="notice notice-error">
					<p>{ error }</p>
				</div>
				<p>
					<a href={ window.location.href.replace( /&batch_id=\d+/, '' ) } className="button">
						{ __( 'Go to Batch Creator', 'qsa-engraving' ) }
					</a>
				</p>
			</div>
		);
	}

	return (
		<div className="qsa-engraving-queue">
			<QueueHeader batch={ batch } />

			<StatsBar
				stats={ stats }
				capacity={ capacity }
			/>

			{ /* LightBurn Status Indicator */ }
			{ lightburnStatus.enabled && (
				<div className={ `qsa-lightburn-status ${ lightburnStatus.loading ? 'loading' : '' }` }>
					<span className="dashicons dashicons-admin-generic"></span>
					<span className="qsa-lightburn-label">{ __( 'LightBurn:', 'qsa-engraving' ) }</span>
					{ lightburnStatus.loading ? (
						<span className="qsa-lightburn-state loading">
							<span className="spinner is-active"></span>
							{ __( 'Sending...', 'qsa-engraving' ) }
						</span>
					) : (
						<span className="qsa-lightburn-state ready">
							{ __( 'Ready', 'qsa-engraving' ) }
						</span>
					) }
					{ lightburnStatus.lastFile && (
						<span className="qsa-lightburn-file">
							<code>{ lightburnStatus.lastFile }</code>
						</span>
					) }
				</div>
			) }

			<div className="qsa-queue-list">
				<div className="qsa-queue-list-header">
					<span className="dashicons dashicons-list-view"></span>
					<span className="qsa-queue-list-title">
						{ __( 'Array Engraving Queue', 'qsa-engraving' ) }
					</span>
				</div>

				<div className="qsa-queue-items">
					{ queueItems.map( ( item, index ) => (
						<QueueItem
							key={ item.id }
							item={ item }
							isLast={ index === queueItems.length - 1 }
							isActive={ activeItemId === item.id }
							onStart={ handleStart }
							onComplete={ handleComplete }
							onRetry={ handleRetry }
							onResend={ handleResend }
							onRerun={ handleRerun }
							onStartPositionChange={ handleStartPositionChange }
						/>
					) ) }
				</div>
			</div>

			<div className="qsa-queue-footer">
				<div className="qsa-queue-legend">
					<span className="qsa-legend-title">{ __( 'Group Types:', 'qsa-engraving' ) }</span>
					<span className="qsa-legend-item same-full">
						<span className="qsa-legend-dot"></span>
						{ __( 'Same ID x Full', 'qsa-engraving' ) }
					</span>
					<span className="qsa-legend-item same-partial">
						<span className="qsa-legend-dot"></span>
						{ __( 'Same ID x Partial', 'qsa-engraving' ) }
					</span>
					<span className="qsa-legend-item mixed-full">
						<span className="qsa-legend-dot"></span>
						{ __( 'Mixed ID x Full', 'qsa-engraving' ) }
					</span>
					<span className="qsa-legend-item mixed-partial">
						<span className="qsa-legend-dot"></span>
						{ __( 'Mixed ID x Partial', 'qsa-engraving' ) }
					</span>
				</div>
				<div className="qsa-queue-keyboard-hint">
					<span className="keyboard-key">SPACE</span>
					<span>{ __( 'Complete current row', 'qsa-engraving' ) }</span>
				</div>
			</div>
		</div>
	);
}
