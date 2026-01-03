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
 * Format date string for display.
 *
 * @param {string} dateString The date string to format.
 * @return {string} Formatted date string.
 */
function formatDate( dateString ) {
	if ( ! dateString ) {
		return '';
	}
	const date = new Date( dateString );
	return date.toLocaleDateString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	} );
}

/**
 * Engraving Queue component.
 *
 * @return {JSX.Element} The component.
 */
export default function EngravingQueue() {
	const [ batchId, setBatchId ] = useState( getBatchIdFromUrl );
	const [ batch, setBatch ] = useState( null );
	const [ queueItems, setQueueItems ] = useState( [] );
	const [ capacity, setCapacity ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ activeItemId, setActiveItemId ] = useState( null );
	const [ activeBatches, setActiveBatches ] = useState( [] );
	const [ activeBatchCount, setActiveBatchCount ] = useState( 0 ); // Count of other active batches
	const [ showBatchSelector, setShowBatchSelector ] = useState( false );
	const [ currentArrays, setCurrentArrays ] = useState( {} ); // Track current array per item
	const [ lightburnStatus, setLightburnStatus ] = useState( {
		enabled: window.qsaEngraving?.lightburnEnabled ?? false,
		autoLoad: window.qsaEngraving?.lightburnAutoLoad ?? true,
		connected: false,
		loading: false,
		lastFile: null,
	} );

	/**
	 * Calculate total arrays for an item based on module count and start position.
	 *
	 * @param {number} totalModules Total modules in the row.
	 * @param {number} startOffset  Starting position (1-8).
	 * @return {number} Total number of physical arrays needed.
	 */
	const calculateTotalArrays = ( totalModules, startOffset ) => {
		let remaining = totalModules;
		let arrayCount = 0;

		if ( startOffset > 1 ) {
			const firstArrayModules = Math.min( remaining, 9 - startOffset );
			arrayCount++;
			remaining -= firstArrayModules;
		}

		if ( remaining > 0 ) {
			arrayCount += Math.ceil( remaining / 8 );
		}

		return arrayCount;
	};

	/**
	 * Get current array number for an item.
	 *
	 * @param {number} itemId The item ID.
	 * @return {number} Current array number (1-based).
	 */
	const getCurrentArray = ( itemId ) => {
		return currentArrays[ itemId ] || 1;
	};

	/**
	 * Fetch active batches when no batch_id is specified.
	 */
	const fetchActiveBatches = useCallback( async () => {
		try {
			const formData = new FormData();
			formData.append( 'action', 'qsa_get_active_batches' );
			formData.append( 'nonce', window.qsaEngraving?.nonce || '' );

			const response = await fetch( window.qsaEngraving?.ajaxUrl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData,
			} );

			const data = await response.json();

			if ( data.success ) {
				setActiveBatches( data.data.batches );
				if ( data.data.batches.length === 0 ) {
					setError( __( 'No active batches found. Create a new batch first.', 'qsa-engraving' ) );
				} else {
					setShowBatchSelector( true );
				}
			} else {
				setError( data.message || __( 'Failed to load active batches.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			setError( __( 'Network error loading active batches.', 'qsa-engraving' ) );
		}

		setLoading( false );
	}, [] );

	/**
	 * Fetch queue data from the server.
	 */
	const fetchQueue = useCallback( async () => {
		if ( ! batchId ) {
			// No batch_id specified, fetch active batches for selection.
			fetchActiveBatches();
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
				setActiveBatchCount( data.data.active_batch_count || 0 );
				setError( null );
				setShowBatchSelector( false );
			} else {
				setError( data.message || __( 'Failed to load queue.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			setError( __( 'Network error loading queue.', 'qsa-engraving' ) );
		}

		setLoading( false );
	}, [ batchId, fetchActiveBatches ] );

	/**
	 * Handle batch selection from the selector.
	 *
	 * @param {number} selectedBatchId The selected batch ID.
	 */
	const handleSelectBatch = ( selectedBatchId ) => {
		// Update URL with batch_id.
		const url = new URL( window.location.href );
		url.searchParams.set( 'batch_id', selectedBatchId );
		window.history.pushState( {}, '', url.toString() );

		// Update state and fetch queue.
		setBatchId( selectedBatchId );
		setShowBatchSelector( false );
		setLoading( true );
	};

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

			// Spacebar advances to next array or completes the current row (when in progress).
			if ( e.code === 'Space' && activeItemId !== null ) {
				e.preventDefault();

				const activeItem = queueItems.find( ( item ) => item.id === activeItemId );
				if ( activeItem && activeItem.status === 'in_progress' ) {
					// Always calculate array count dynamically based on totalModules and startPosition.
					const totalArrays = calculateTotalArrays( activeItem.totalModules, activeItem.startPosition || 1 );
					const current = getCurrentArray( activeItemId );
					const isLastArray = current >= totalArrays;

					if ( isLastArray ) {
						handleComplete( activeItemId );
					} else {
						handleNextArray( activeItemId, current );
					}
				}
			}
		};

		window.addEventListener( 'keydown', handleKeyDown );
		return () => window.removeEventListener( 'keydown', handleKeyDown );
	}, [ activeItemId, queueItems, currentArrays ] );

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
	 * @return {Promise<{success: boolean, data?: Object, error?: string}>} Result with success flag and data or error.
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
				return { success: true, data: data.data };
			}
			setLightburnStatus( ( prev ) => ( { ...prev, loading: false } ) );
			return { success: false, error: data.message || __( 'SVG generation failed.', 'qsa-engraving' ) };
		} catch ( err ) {
			setLightburnStatus( ( prev ) => ( { ...prev, loading: false } ) );
			return { success: false, error: __( 'Network error generating SVG.', 'qsa-engraving' ) };
		}
	};

	/**
	 * Get the current QSA sequence for an item based on array index.
	 *
	 * @param {Object} item         The queue item.
	 * @param {number} arrayIndex   The array index (1-based).
	 * @return {number} The QSA sequence number.
	 */
	const getQsaSequenceForArray = ( item, arrayIndex ) => {
		const sequences = item.qsa_sequences || [ item.id ];
		// For multi-QSA groups, each array corresponds to a QSA sequence.
		// For single-QSA with multiple arrays (start position offset), all arrays use the same QSA.
		if ( sequences.length > 1 ) {
			return sequences[ arrayIndex - 1 ] || sequences[ 0 ];
		}
		return sequences[ 0 ];
	};

	/**
	 * Handle start engraving for a row.
	 *
	 * @param {number} itemId The queue item ID (first QSA sequence in group).
	 */
	const handleStart = async ( itemId ) => {
		const item = queueItems.find( ( i ) => i.id === itemId );
		if ( ! item ) {
			return;
		}

		// For multi-QSA groups, start with the first QSA sequence.
		const qsaSequence = getQsaSequenceForArray( item, 1 );

		try {
			const data = await queueAction( 'qsa_start_row', { qsa_sequence: qsaSequence } );

			if ( data.success ) {
				setActiveItemId( itemId );
				// Initialize current array to 1.
				setCurrentArrays( ( prev ) => ( { ...prev, [ itemId ]: 1 } ) );
				// Update the queue item status.
				setQueueItems( ( prev ) =>
					prev.map( ( i ) =>
						i.id === itemId
							? { ...i, status: 'in_progress', serials: data.data.serials }
							: i
					)
				);

				// Generate SVG and optionally auto-load in LightBurn.
				if ( lightburnStatus.enabled ) {
					const svgResult = await generateSvg( qsaSequence, lightburnStatus.autoLoad );
					if ( ! svgResult.success ) {
						// SVG generation failed - alert operator with error details.
						alert(
							__( 'Row started but SVG generation failed:', 'qsa-engraving' ) +
								'\n\n' +
								svgResult.error +
								'\n\n' +
								__( 'Please resolve the issue and use Resend to regenerate the SVG.', 'qsa-engraving' )
						);
					} else if ( svgResult.data && ! svgResult.data.lightburn_loaded && lightburnStatus.autoLoad ) {
						// SVG generated but LightBurn load failed.
						console.warn( 'SVG generated but LightBurn load failed:', svgResult.data.lightburn_error );
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
	 * For multi-QSA groups, this completes the current QSA sequence.
	 * For the last array in a group, it marks the entire group as complete.
	 *
	 * @param {number} itemId The queue item ID (first QSA sequence in group).
	 */
	const handleComplete = async ( itemId ) => {
		const item = queueItems.find( ( i ) => i.id === itemId );
		if ( ! item ) {
			return;
		}

		// Get the current array and its QSA sequence.
		const current = getCurrentArray( itemId );
		const qsaSequence = getQsaSequenceForArray( item, current );

		try {
			const data = await queueAction( 'qsa_complete_row', { qsa_sequence: qsaSequence } );

			if ( data.success ) {
				setActiveItemId( null );
				// Reset current array state for this item.
				setCurrentArrays( ( prev ) => ( { ...prev, [ itemId ]: 0 } ) );
				// Update the queue item status.
				setQueueItems( ( prev ) =>
					prev.map( ( i ) =>
						i.id === itemId ? { ...i, status: 'complete' } : i
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
	 * Handle advancing to next array within a row.
	 *
	 * For multi-QSA groups: completes current QSA sequence, starts next QSA sequence
	 * (if a corresponding qsa_sequence exists).
	 * For single-QSA with start position offset: advances array counter within same QSA.
	 *
	 * @param {number} itemId       The queue item ID (first QSA sequence in group).
	 * @param {number} currentArray The current array number.
	 */
	const handleNextArray = async ( itemId, currentArray ) => {
		const item = queueItems.find( ( i ) => i.id === itemId );
		if ( ! item ) {
			return;
		}

		const sequences = item.qsa_sequences || [ item.id ];
		// Always calculate array count dynamically based on totalModules and startPosition.
		const totalArrays = calculateTotalArrays( item.totalModules, item.startPosition || 1 );
		const nextArray = currentArray + 1;

		if ( nextArray > totalArrays ) {
			// Should not happen, but handle gracefully by completing
			handleComplete( itemId );
			return;
		}

		// Check if we have a qsa_sequence for the next array.
		// For multi-QSA groups, each array maps to a qsa_sequence.
		// For single-QSA with multiple calculated arrays, all use the same qsa_sequence.
		const hasNextQsaSequence = nextArray <= sequences.length;

		if ( hasNextQsaSequence && sequences.length > 1 ) {
			// Multi-QSA group with available next qsa_sequence: complete current, start next.
			const currentQsaSequence = getQsaSequenceForArray( item, currentArray );
			const nextQsaSequence = getQsaSequenceForArray( item, nextArray );

			try {
				// Complete the current QSA sequence.
				const completeData = await queueAction( 'qsa_complete_row', { qsa_sequence: currentQsaSequence } );

				if ( ! completeData.success ) {
					alert( completeData.message || __( 'Failed to complete current array.', 'qsa-engraving' ) );
					return;
				}

				// Start the next QSA sequence.
				const startData = await queueAction( 'qsa_start_row', { qsa_sequence: nextQsaSequence } );

				if ( startData.success ) {
					// Advance the current array counter.
					setCurrentArrays( ( prev ) => ( { ...prev, [ itemId ]: nextArray } ) );

					// Update serials with new ones from next QSA.
					setQueueItems( ( prev ) =>
						prev.map( ( i ) =>
							i.id === itemId
								? { ...i, serials: startData.data.serials }
								: i
						)
					);

					// Generate SVG for the next QSA and load in LightBurn if enabled.
					if ( lightburnStatus.enabled ) {
						const svgResult = await generateSvg( nextQsaSequence, lightburnStatus.autoLoad );
						if ( ! svgResult.success ) {
							alert(
								__( 'Array started but SVG generation failed:', 'qsa-engraving' ) +
									'\n\n' +
									svgResult.error +
									'\n\n' +
									__( 'Use Resend to try again.', 'qsa-engraving' )
							);
						}
					}
				} else {
					alert( startData.message || __( 'Failed to start next array.', 'qsa-engraving' ) );
				}
			} catch ( err ) {
				alert( __( 'Network error advancing to next array.', 'qsa-engraving' ) );
			}
		} else {
			// Single-QSA or calculated arrays beyond available qsa_sequences: just advance counter.
			setCurrentArrays( ( prev ) => ( { ...prev, [ itemId ]: nextArray } ) );

			// Generate SVG for the next array (same QSA sequence).
			if ( lightburnStatus.enabled ) {
				const qsaSequence = getQsaSequenceForArray( item, 1 );
				const svgResult = await generateSvg( qsaSequence, lightburnStatus.autoLoad );
				if ( ! svgResult.success ) {
					alert(
						__( 'Failed to generate SVG for next array:', 'qsa-engraving' ) +
							'\n\n' +
							svgResult.error +
							'\n\n' +
							__( 'Use Resend to try again.', 'qsa-engraving' )
					);
				}
			}
		}
	};

	/**
	 * Handle retry for current array.
	 *
	 * Voids current serials, assigns new ones, regenerates SVG, and loads in LightBurn.
	 *
	 * @param {number} itemId The queue item ID (first QSA sequence in group).
	 */
	const handleRetry = async ( itemId ) => {
		if ( ! confirm( __( 'This will void current serials and assign new ones. Continue?', 'qsa-engraving' ) ) ) {
			return;
		}

		const item = queueItems.find( ( i ) => i.id === itemId );
		if ( ! item ) {
			return;
		}

		// Get the current array's QSA sequence.
		const current = getCurrentArray( itemId );
		const qsaSequence = getQsaSequenceForArray( item, current );

		try {
			const data = await queueAction( 'qsa_retry_array', { qsa_sequence: qsaSequence } );

			if ( data.success ) {
				// Update serials for this item.
				setQueueItems( ( prev ) =>
					prev.map( ( item ) =>
						item.id === qsaSequence ? { ...item, serials: data.data.serials } : item
					)
				);

				// Regenerate SVG with new serials and load in LightBurn if enabled.
				if ( lightburnStatus.enabled ) {
					const svgResult = await generateSvg( qsaSequence, lightburnStatus.autoLoad );
					if ( ! svgResult.success ) {
						// SVG regeneration failed - alert operator with error details.
						alert(
							__( 'Serials assigned but SVG generation failed:', 'qsa-engraving' ) +
								'\n\n' +
								svgResult.error +
								'\n\n' +
								__( 'Please resolve the issue and use Resend to regenerate the SVG.', 'qsa-engraving' )
						);
					} else if ( svgResult.data && ! svgResult.data.lightburn_loaded && lightburnStatus.autoLoad ) {
						console.warn( 'SVG regenerated but LightBurn load failed:', svgResult.data.lightburn_error );
					}
				}
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
	 * @param {number} itemId The queue item ID (first QSA sequence in group).
	 */
	const handleResend = async ( itemId ) => {
		const item = queueItems.find( ( i ) => i.id === itemId );
		if ( ! item ) {
			return;
		}

		// Get the current array's QSA sequence.
		const current = getCurrentArray( itemId );
		const qsaSequence = getQsaSequenceForArray( item, current );

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
				if ( svgResult.success ) {
					setLightburnStatus( ( prev ) => ( { ...prev, loading: false } ) );
				} else {
					setLightburnStatus( ( prev ) => ( { ...prev, loading: false } ) );
					alert(
						__( 'Failed to regenerate SVG:', 'qsa-engraving' ) +
							'\n\n' +
							svgResult.error +
							'\n\n' +
							__( 'Please resolve the issue and try again.', 'qsa-engraving' )
					);
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
	 * For multi-QSA groups, this resets ALL QSA sequences in the group.
	 *
	 * @param {number} itemId The queue item ID (first QSA sequence in group).
	 */
	const handleRerun = async ( itemId ) => {
		if ( ! confirm( __( 'This will reset this row to pending for re-engraving. Continue?', 'qsa-engraving' ) ) ) {
			return;
		}

		const item = queueItems.find( ( i ) => i.id === itemId );
		if ( ! item ) {
			return;
		}

		const sequences = item.qsa_sequences || [ item.id ];

		try {
			// Reset all QSA sequences in the group.
			for ( const qsaSequence of sequences ) {
				const data = await queueAction( 'qsa_rerun_row', { qsa_sequence: qsaSequence } );

				if ( ! data.success ) {
					alert( data.message || __( 'Failed to rerun.', 'qsa-engraving' ) );
					return;
				}
			}

			// Reset current array state for this item.
			setCurrentArrays( ( prev ) => ( { ...prev, [ itemId ]: 0 } ) );
			setQueueItems( ( prev ) =>
				prev.map( ( i ) =>
					i.id === itemId ? { ...i, status: 'pending', serials: [] } : i
				)
			);
		} catch ( err ) {
			alert( __( 'Network error during rerun.', 'qsa-engraving' ) );
		}
	};

	/**
	 * Handle start position change.
	 *
	 * Updates the start position which affects the calculated array count.
	 * For multi-QSA groups, this updates the first QSA sequence's positions.
	 *
	 * @param {number} itemId        The queue item ID (first QSA sequence in group).
	 * @param {number} startPosition The new start position (1-8).
	 */
	const handleStartPositionChange = async ( itemId, startPosition ) => {
		const item = queueItems.find( ( i ) => i.id === itemId );
		if ( ! item ) {
			return;
		}

		const sequences = item.qsa_sequences || [ item.id ];
		const qsaSequence = sequences[ 0 ];

		try {
			const data = await queueAction( 'qsa_update_start_position', {
				qsa_sequence: qsaSequence,
				start_position: startPosition,
			} );

			if ( data.success ) {
				setQueueItems( ( prev ) =>
					prev.map( ( i ) =>
						i.id === itemId ? { ...i, startPosition } : i
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
			<div className="qsa-engraving-queue">
				<div className="qsa-queue-loading">
					<span className="spinner is-active"></span>
					<p>{ __( 'Loading engraving queue...', 'qsa-engraving' ) }</p>
				</div>
			</div>
		);
	}

	// Get dashboard URL for navigation (also used in error state).
	const dashboardUrl = window.location.href.replace( /qsa-engraving-queue.*/, 'qsa-engraving' );

	// Render error state.
	if ( error ) {
		return (
			<div className="qsa-engraving-queue">
				<div className="qsa-queue-error">
					<div className="notice notice-error">
						<p>{ error }</p>
					</div>
					<div className="qsa-batch-selector-actions">
						<a href={ dashboardUrl } className="button">
							<span className="dashicons dashicons-arrow-left-alt2"></span>
							{ __( 'Back to Dashboard', 'qsa-engraving' ) }
						</a>
						<a
							href={ window.location.href.replace( /&batch_id=\d+/, '' ).replace( /\?page=/, '?page=qsa-engraving-batch-creator&_from=' ) }
							className="button button-primary"
						>
							<span className="dashicons dashicons-plus-alt2"></span>
							{ __( 'Create New Batch', 'qsa-engraving' ) }
						</a>
					</div>
				</div>
			</div>
		);
	}

	// Render batch selector when no batch_id is specified.
	if ( showBatchSelector ) {
		return (
			<div className="qsa-engraving-queue">
				<div className="qsa-batch-selector">
				<div className="qsa-batch-selector-header">
					<span className="dashicons dashicons-list-view"></span>
					<h2>{ __( 'Select an Active Batch', 'qsa-engraving' ) }</h2>
				</div>
				<p className="qsa-batch-selector-desc">
					{ __( 'Choose a batch to continue engraving, or create a new batch.', 'qsa-engraving' ) }
				</p>

				<div className="qsa-batch-selector-list">
					{ activeBatches.map( ( activeBatch ) => (
						<div
							key={ activeBatch.id }
							className="qsa-batch-selector-item"
							onClick={ () => handleSelectBatch( activeBatch.id ) }
							onKeyDown={ ( e ) => e.key === 'Enter' && handleSelectBatch( activeBatch.id ) }
							role="button"
							tabIndex={ 0 }
						>
							<div className="qsa-batch-selector-item-header">
								<span className="qsa-batch-id">
									{ __( 'Batch', 'qsa-engraving' ) } #{ activeBatch.id }
								</span>
								{ activeBatch.batch_name && (
									<span className="qsa-batch-name">{ activeBatch.batch_name }</span>
								) }
							</div>
							<div className="qsa-batch-selector-item-meta">
								<span className="qsa-batch-modules">
									<span className="dashicons dashicons-screenoptions"></span>
									{ activeBatch.module_count } { __( 'modules', 'qsa-engraving' ) }
								</span>
								<span className="qsa-batch-arrays">
									<span className="dashicons dashicons-editor-ol"></span>
									{ activeBatch.array_count } { __( 'arrays', 'qsa-engraving' ) }
								</span>
								<span className="qsa-batch-date">
									<span className="dashicons dashicons-calendar-alt"></span>
									{ formatDate( activeBatch.created_at ) }
								</span>
								{ activeBatch.created_by_name && (
									<span className="qsa-batch-creator">
										<span className="dashicons dashicons-admin-users"></span>
										{ activeBatch.created_by_name }
									</span>
								) }
							</div>
							<div className="qsa-batch-selector-item-progress">
								<div className="qsa-progress-bar">
									<div
										className="qsa-progress-fill"
										style={ { width: `${ activeBatch.progress_percent }%` } }
									></div>
								</div>
								<span className="qsa-progress-text">
									{ activeBatch.completed_modules } / { activeBatch.module_count }
									{ ' ' }({ activeBatch.progress_percent }%)
								</span>
							</div>
						</div>
					) ) }
				</div>

				<div className="qsa-batch-selector-actions">
					<a
						href={ dashboardUrl }
						className="button"
					>
						<span className="dashicons dashicons-arrow-left-alt2"></span>
						{ __( 'Back to Dashboard', 'qsa-engraving' ) }
					</a>
					<a
						href={ window.location.href.replace( 'qsa-engraving-queue', 'qsa-engraving-batch-creator' ) }
						className="button button-primary"
					>
						<span className="dashicons dashicons-plus-alt2"></span>
						{ __( 'Create New Batch', 'qsa-engraving' ) }
					</a>
				</div>
				</div>
			</div>
		);
	}

	return (
		<div className="qsa-engraving-queue">
			<QueueHeader batch={ batch } activeBatchCount={ activeBatchCount } />

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
							currentArray={ getCurrentArray( item.id ) }
							onStart={ handleStart }
							onComplete={ handleComplete }
							onNextArray={ handleNextArray }
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
