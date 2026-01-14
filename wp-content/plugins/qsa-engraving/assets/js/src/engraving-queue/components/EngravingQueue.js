/**
 * Engraving Queue Component
 *
 * Main container for the engraving workflow interface.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
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
	const [ resendingItemId, setResendingItemId ] = useState( null ); // Track which item is being resent
	const [ updatingStartPositionId, setUpdatingStartPositionId ] = useState( null ); // Track which item's start position is being updated
	const [ processingNextArrayId, setProcessingNextArrayId ] = useState( null ); // Track which item's Next Array is being processed (for UI)
	const [ rerunningItemId, setRerunningItemId ] = useState( null ); // Track which item is being rerun
	const processingNextArrayRef = useRef( false ); // Synchronous guard to prevent rapid clicks
	const [ lightburnStatus, setLightburnStatus ] = useState( {
		enabled: window.qsaEngraving?.lightburnEnabled ?? false,
		autoLoad: window.qsaEngraving?.lightburnAutoLoad ?? true,
		connected: false,
		loading: false,
		lastFile: null,
	} );

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

				// Initialize currentArrays from backend data for in_progress items.
				// This ensures resuming a batch shows the correct array position.
				const initialArrays = {};
				let activeItem = null;
				data.data.queue_items.forEach( ( item ) => {
					if ( item.status === 'in_progress' && item.currentArray > 0 ) {
						initialArrays[ item.id ] = item.currentArray;
						activeItem = item.id;
					} else if ( item.status === 'partial' && item.completedArrays > 0 ) {
						// For partial items, set to next array after completed ones.
						initialArrays[ item.id ] = item.completedArrays + 1;
					}
				} );
				if ( Object.keys( initialArrays ).length > 0 ) {
					setCurrentArrays( initialArrays );
				}
				if ( activeItem ) {
					setActiveItemId( activeItem );
				}
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
	 * @param {number}  itemId      The queue item ID (first QSA sequence in group).
	 * @param {boolean} autoLoad    Whether to auto-load in LightBurn.
	 * @return {Promise<{success: boolean, data?: Object, error?: string}>} Result with success flag and data or error.
	 */
	const generateSvg = async ( qsaSequence, itemId, autoLoad = true ) => {
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

				// Store QSA ID in the queue item if returned.
				if ( data.data.qsa_id && itemId ) {
					setQueueItems( ( prev ) =>
						prev.map( ( i ) =>
							i.id === itemId
								? { ...i, qsaId: data.data.qsa_id }
								: i
						)
					);
				}

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
	 * @return {number|null} The QSA sequence number, or null if invalid.
	 */
	const getQsaSequenceForArray = ( item, arrayIndex ) => {
		const sequences = item.qsa_sequences || [ item.id ];
		// Each array corresponds to exactly one QSA sequence.
		// Return null if arrayIndex is out of bounds (prevents silent fallback to wrong sequence).
		if ( arrayIndex < 1 || arrayIndex > sequences.length ) {
			console.error( `Invalid array index ${ arrayIndex } for item with ${ sequences.length } sequences` );
			return null;
		}
		return sequences[ arrayIndex - 1 ];
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

		// For pending batches, always start from array 1.
		// For partial batches, use nextPendingArray from backend (reliable) or fall back to calculation.
		// This handles race conditions where arrays may have been completed out of order.
		let startingArray;
		if ( item.status === 'pending' ) {
			startingArray = 1;
		} else if ( item.nextPendingArray > 0 ) {
			// Backend tells us exactly which array has pending modules.
			startingArray = item.nextPendingArray;
		} else if ( item.status === 'partial' && item.nextPendingArray === 0 ) {
			// Partial status but no pending arrays - inconsistent state from race condition.
			// User needs to use Rerun to reset all arrays.
			alert(
				__( 'No pending arrays found. This may be due to a previous error.', 'qsa-engraving' ) +
					'\n\n' +
					__( 'Please use Rerun to reset this row and start over.', 'qsa-engraving' )
			);
			return;
		} else {
			// Fallback: calculate from completedArrays (may be inaccurate after race conditions).
			const completedArrays = item.completedArrays || 0;
			startingArray = completedArrays + 1;
		}
		const qsaSequence = getQsaSequenceForArray( item, startingArray );

		// Validate the QSA sequence - prevents operating on wrong sequence if index is out of bounds.
		if ( qsaSequence === null ) {
			alert(
				__( 'Invalid array state. The starting array index is out of sync with available sequences.', 'qsa-engraving' ) +
					'\n\n' +
					__( 'Please refresh the page and try again.', 'qsa-engraving' )
			);
			return;
		}

		try {
			const data = await queueAction( 'qsa_start_row', { qsa_sequence: qsaSequence } );

			if ( data.success ) {
				setActiveItemId( itemId );
				// Initialize current array to the starting array (1 for pending, completedArrays+1 for partial).
				setCurrentArrays( ( prev ) => ( { ...prev, [ itemId ]: startingArray } ) );
				// Update the queue item status and clear any stale QSA ID from previous array.
				setQueueItems( ( prev ) =>
					prev.map( ( i ) =>
						i.id === itemId
							? { ...i, status: 'in_progress', serials: data.data.serials, qsaId: null }
							: i
					)
				);

				// Generate SVG if LightBurn is enabled OR Keep SVG Files is enabled.
				const keepSvgFiles = window.qsaEngraving?.keepSvgFiles ?? false;
				const shouldGenerateSvg = lightburnStatus.enabled || keepSvgFiles;

				if ( shouldGenerateSvg ) {
					// Only attempt LightBurn auto-load if LightBurn is enabled AND autoLoad is on.
					const shouldAutoLoad = lightburnStatus.enabled && lightburnStatus.autoLoad;
					const svgResult = await generateSvg( qsaSequence, itemId, shouldAutoLoad );

					if ( ! svgResult.success ) {
						// SVG generation failed - alert operator with error details.
						alert(
							__( 'Row started but SVG generation failed:', 'qsa-engraving' ) +
								'\n\n' +
								svgResult.error +
								'\n\n' +
								__( 'Please resolve the issue and use Resend to regenerate the SVG.', 'qsa-engraving' )
						);
					} else if ( svgResult.data && ! svgResult.data.lightburn_loaded && shouldAutoLoad ) {
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

		// Validate the QSA sequence - prevents operating on wrong sequence.
		if ( qsaSequence === null ) {
			alert(
				__( 'Invalid array state. The current array index is out of sync.', 'qsa-engraving' ) +
					'\n\n' +
					__( 'Please refresh the page and try again.', 'qsa-engraving' )
			);
			return;
		}

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

				// If batch is complete, update batch status.
				if ( data.data.batch_complete ) {
					setBatch( ( prev ) => ( { ...prev, status: 'completed' } ) );
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
	 * For multi-QSA groups: completes current QSA sequence, starts next QSA sequence.
	 * Each array corresponds to exactly one QSA sequence.
	 *
	 * @param {number} itemId       The queue item ID (first QSA sequence in group).
	 * @param {number} currentArray The current array number.
	 */
	const handleNextArray = async ( itemId, currentArray ) => {
		// Synchronous guard using ref - prevents rapid clicks before React state updates.
		// This is critical because useState updates are asynchronous.
		if ( processingNextArrayRef.current ) {
			return;
		}
		processingNextArrayRef.current = true;

		const item = queueItems.find( ( i ) => i.id === itemId );
		if ( ! item ) {
			processingNextArrayRef.current = false;
			return;
		}

		// Set processing state for UI (button disabled/loading indicator).
		setProcessingNextArrayId( itemId );

		const sequences = item.qsa_sequences || [ item.id ];
		// Calculate array count based on modules and start position.
		const startPosition = item.startPosition || 1;
		const firstArraySlots = 9 - startPosition;
		const modulesAfterFirst = Math.max( 0, item.totalModules - firstArraySlots );
		const totalArrays = 1 + Math.ceil( modulesAfterFirst / 8 );
		const nextArray = currentArray + 1;

		if ( nextArray > totalArrays ) {
			// No more arrays - complete the row.
			handleComplete( itemId );
			return;
		}

		// Get QSA sequences for current and next arrays.
		const currentQsaSequence = getQsaSequenceForArray( item, currentArray );
		const nextQsaSequence = getQsaSequenceForArray( item, nextArray );

		// Validate both sequences exist.
		if ( currentQsaSequence === null || nextQsaSequence === null ) {
			alert(
				__( 'Invalid array state. The current array index is out of sync.', 'qsa-engraving' ) +
					'\n\n' +
					__( 'Please refresh the page and try again.', 'qsa-engraving' )
			);
			return;
		}

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

				// Update serials, increment completedArrays, and clear stale QSA ID.
				// The currentArray counter just advanced to nextArray, so completedArrays = nextArray - 1.
				setQueueItems( ( prev ) =>
					prev.map( ( i ) =>
						i.id === itemId
							? {
									...i,
									serials: startData.data.serials,
									qsaId: null,
									completedArrays: currentArray, // Current array just completed.
									status: 'in_progress',
							  }
							: i
					)
				);

				// Generate SVG for the next QSA if LightBurn is enabled OR Keep SVG Files is enabled.
				const keepSvgFiles = window.qsaEngraving?.keepSvgFiles ?? false;
				const shouldGenerateSvg = lightburnStatus.enabled || keepSvgFiles;

				if ( shouldGenerateSvg ) {
					const shouldAutoLoad = lightburnStatus.enabled && lightburnStatus.autoLoad;
					const svgResult = await generateSvg( nextQsaSequence, itemId, shouldAutoLoad );
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
		} finally {
			// Clear both ref (synchronous guard) and state (UI).
			processingNextArrayRef.current = false;
			setProcessingNextArrayId( null );
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

		// Validate the QSA sequence.
		if ( qsaSequence === null ) {
			alert(
				__( 'Invalid array state. The current array index is out of sync.', 'qsa-engraving' ) +
					'\n\n' +
					__( 'Please refresh the page and try again.', 'qsa-engraving' )
			);
			return;
		}

		try {
			// Track which specific item is being resent.
			setResendingItemId( itemId );
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
				const svgResult = await generateSvg( qsaSequence, itemId, true );
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
		} finally {
			// Clear the resending state.
			setResendingItemId( null );
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

		// Set loading state to show spinner.
		setRerunningItemId( itemId );

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
					i.id === itemId
						? { ...i, status: 'pending', serials: [], completedArrays: 0 }
						: i
				)
			);
		} catch ( err ) {
			alert( __( 'Network error during rerun.', 'qsa-engraving' ) );
		} finally {
			// Clear loading state.
			setRerunningItemId( null );
		}
	};

	/**
	 * Handle start position change.
	 *
	 * Updates the start position which affects the calculated array count.
	 * For multi-QSA groups, this updates the first QSA sequence's positions
	 * and may allocate new QSA sequences if more arrays are needed.
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

		// Set loading state to prevent Engrave button from being clicked
		// while the start position update is in progress.
		setUpdatingStartPositionId( itemId );

		try {
			const data = await queueAction( 'qsa_update_start_position', {
				qsa_sequence: qsaSequence,
				start_position: startPosition,
			} );

			if ( data.success ) {
				// Update both startPosition AND qsa_sequences (which may have changed
				// if more arrays were needed and new sequences were allocated).
				setQueueItems( ( prev ) =>
					prev.map( ( i ) =>
						i.id === itemId
							? {
									...i,
									startPosition,
									qsa_sequences: data.data.qsa_sequences || i.qsa_sequences,
							  }
							: i
					)
				);
			} else {
				alert( data.message || __( 'Failed to update start position.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			alert( __( 'Network error updating start position.', 'qsa-engraving' ) );
		} finally {
			// Clear loading state after update completes (success or failure).
			setUpdatingStartPositionId( null );
		}
	};

	/**
	 * Calculate completed modules for a queue item based on completed arrays.
	 *
	 * For multi-array rows, we need to calculate how many modules have been
	 * completed based on the number of completed arrays and their distribution.
	 *
	 * @param {Object} item The queue item.
	 * @return {number} Number of completed modules.
	 */
	const getCompletedModulesForItem = ( item ) => {
		// Fully complete rows - all modules done.
		if ( item.status === 'complete' ) {
			return item.totalModules;
		}

		// Pending rows - no modules done.
		if ( item.status === 'pending' || ! item.completedArrays || item.completedArrays === 0 ) {
			return 0;
		}

		// Partial or in_progress rows - calculate based on completed arrays.
		const startPosition = item.startPosition || 1;
		const totalModules = item.totalModules || 0;
		const completedArrays = item.completedArrays || 0;
		const arrayCount = item.arrayCount || 1;

		// If all arrays completed but status not 'complete', count all modules.
		if ( completedArrays >= arrayCount ) {
			return totalModules;
		}

		// Calculate modules per array:
		// First array: (9 - startPosition) modules (max 8 if startPosition = 1)
		// Subsequent arrays: 8 modules each
		// Last array: remaining modules
		const firstArrayModules = Math.min( 9 - startPosition, totalModules );
		let completedModules = 0;

		for ( let i = 0; i < completedArrays; i++ ) {
			if ( i === 0 ) {
				// First array.
				completedModules += firstArrayModules;
			} else {
				// Subsequent arrays - 8 modules each, but don't exceed total.
				const remainingAfterFirst = totalModules - firstArrayModules;
				const modulesInThisArray = Math.min( 8, remainingAfterFirst - ( ( i - 1 ) * 8 ) );
				completedModules += Math.max( 0, modulesInThisArray );
			}
		}

		return Math.min( completedModules, totalModules );
	};

	// Calculate stats for the stats bar.
	const stats = {
		totalItems: queueItems.length,
		completedItems: queueItems.filter( ( item ) => item.status === 'complete' ).length,
		totalModules: queueItems.reduce( ( sum, item ) => sum + item.totalModules, 0 ),
		completedModules: queueItems.reduce(
			( sum, item ) => sum + getCompletedModulesForItem( item ),
			0
		),
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
							isResending={ resendingItemId === item.id }
							isUpdatingStartPosition={ updatingStartPositionId === item.id }
							isProcessingNextArray={ processingNextArrayId === item.id }
							isRerunning={ rerunningItemId === item.id }
							onStart={ handleStart }
							onComplete={ handleComplete }
							onNextArray={ handleNextArray }
							onResend={ handleResend }
							onRerun={ handleRerun }
							onStartPositionChange={ handleStartPositionChange }
						/>
					) ) }
				</div>
			</div>

			{ /* Footer removed - group type legend no longer needed */ }
		</div>
	);
}
