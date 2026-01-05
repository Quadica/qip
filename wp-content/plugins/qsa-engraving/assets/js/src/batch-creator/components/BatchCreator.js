/**
 * BatchCreator - Main Component
 *
 * Displays a hierarchical list of modules awaiting engraving,
 * allows selection and quantity editing, and creates engraving batches.
 *
 * Supports re-engraving workflow when loaded with URL parameters:
 * - source=history: Indicates modules come from Batch History
 * - source_batch_id: The batch ID to load for re-engraving
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ModuleTree from './ModuleTree';
import StatsBar from './StatsBar';
import ActionBar from './ActionBar';

/**
 * Base type display names.
 */
const BASE_TYPE_NAMES = {
	SOLO: 'Star/O',
	CORE: 'LUXEON C ES',
	EDGE: 'LUXEON UV U1',
	STAR: 'Cree XPG',
	NORD: 'Nordic',
	ATOM: 'Atomic',
	APEX: 'Apex',
	PICO: 'Pico',
	QUAD: 'Quad',
};

/**
 * Parse URL parameters for re-engraving source.
 *
 * @return {Object|null} Source info or null if not from history.
 */
function getReengravingSource() {
	const urlParams = new URLSearchParams( window.location.search );
	const source = urlParams.get( 'source' );
	const sourceBatchId = urlParams.get( 'source_batch_id' );

	if ( source === 'history' && sourceBatchId ) {
		return {
			type: 'history',
			batchId: parseInt( sourceBatchId, 10 ),
		};
	}

	return null;
}

/**
 * BatchCreator component.
 *
 * @return {JSX.Element} The component.
 */
export default function BatchCreator() {
	// State management.
	const [ moduleData, setModuleData ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ selectedModules, setSelectedModules ] = useState( new Set() );
	const [ engraveQuantities, setEngraveQuantities ] = useState( {} );
	const [ expandedBaseTypes, setExpandedBaseTypes ] = useState( new Set() );
	const [ expandedOrders, setExpandedOrders ] = useState( new Set() );
	const [ creatingBatch, setCreatingBatch ] = useState( false );
	const [ reengravingSource, setReengravingSource ] = useState( null );
	const [ reengravingData, setReengravingData ] = useState( null );

	/**
	 * Make an AJAX request.
	 *
	 * @param {string} action   The AJAX action.
	 * @param {Object} data     Additional data to send.
	 * @return {Promise<Object>} The response.
	 */
	const ajaxRequest = async ( action, data = {} ) => {
		const formData = new FormData();
		formData.append( 'action', action );
		formData.append( 'nonce', window.qsaEngraving?.nonce || '' );

		Object.entries( data ).forEach( ( [ key, value ] ) => {
			formData.append( key, typeof value === 'object' ? JSON.stringify( value ) : value );
		} );

		const response = await fetch( window.qsaEngraving?.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} );

		// Check if response is OK before parsing JSON.
		if ( ! response.ok ) {
			throw new Error( `HTTP error ${ response.status }: ${ response.statusText }` );
		}

		const text = await response.text();
		try {
			return JSON.parse( text );
		} catch ( e ) {
			// If JSON parsing fails, throw an error with the response text (truncated).
			throw new Error( `Invalid response: ${ text.substring( 0, 200 ) }` );
		}
	};

	/**
	 * Fetch modules from the server via AJAX.
	 */
	const fetchModules = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const response = await ajaxRequest( 'qsa_get_modules_awaiting' );

			if ( response.success && response.data ) {
				setModuleData( response.data );
			} else {
				setError( response.message || __( 'Failed to load modules.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			setError( __( 'Failed to connect to server.', 'qsa-engraving' ) );
		} finally {
			setLoading( false );
		}
	}, [] );

	/**
	 * Fetch re-engraving data from a previous batch.
	 *
	 * @param {number} batchId The source batch ID.
	 */
	const fetchReengravingData = useCallback( async ( batchId ) => {
		try {
			const response = await ajaxRequest( 'qsa_get_batch_for_reengraving', {
				batch_id: batchId,
			} );

			if ( response.success && response.data ) {
				setReengravingData( response.data );
				return response.data;
			}
			setError( response.data?.message || __( 'Failed to load re-engraving data.', 'qsa-engraving' ) );
			return null;
		} catch ( err ) {
			setError( __( 'Failed to load re-engraving data.', 'qsa-engraving' ) );
			return null;
		}
	}, [] );

	/**
	 * Apply re-engraving selections - NOT USED for direct batch duplication.
	 *
	 * Note: Re-engraving now creates a direct duplicate of the source batch
	 * via the backend. This function is kept for potential future use but
	 * is no longer called in the re-engraving workflow.
	 *
	 * @param {Object} reengraveData The re-engraving data from the API.
	 * @param {Object} availableModules The currently available modules.
	 */
	const applyReengravingSelections = useCallback( ( reengraveData, availableModules ) => {
		// This function is no longer used - re-engraving creates a direct
		// duplicate of the source batch without matching against awaiting modules.
		return;
	}, [] );

	/**
	 * Load modules on mount and handle re-engraving source.
	 */
	useEffect( () => {
		const source = getReengravingSource();
		setReengravingSource( source );

		const initializeData = async () => {
			// First fetch available modules.
			await fetchModules();
		};

		initializeData();
	}, [ fetchModules ] );

	/**
	 * When we have both module data and re-engraving source, fetch and apply selections.
	 */
	useEffect( () => {
		if ( reengravingSource && Object.keys( moduleData ).length > 0 && ! reengravingData ) {
			const loadReengravingData = async () => {
				const data = await fetchReengravingData( reengravingSource.batchId );
				if ( data ) {
					applyReengravingSelections( data, moduleData );
				}
			};
			loadReengravingData();
		}
	}, [ reengravingSource, moduleData, reengravingData, fetchReengravingData, applyReengravingSelections ] );

	/**
	 * Get all module identifiers from the data.
	 *
	 * @return {Array} Array of module identifiers.
	 */
	const getAllModuleIds = useMemo( () => {
		const ids = [];
		Object.values( moduleData ).forEach( ( baseTypeData ) => {
			baseTypeData.modules.forEach( ( order ) => {
				order.items.forEach( ( module ) => {
					// Create unique identifier: batch_id-sku-order_id
					const id = `${ module.production_batch_id }-${ module.module_sku }-${ module.order_id }`;
					ids.push( { id, module } );
				} );
			} );
		} );
		return ids;
	}, [ moduleData ] );

	/**
	 * Get module IDs for a base type.
	 *
	 * @param {string} baseType The base type.
	 * @return {Set} Set of module IDs.
	 */
	const getBaseTypeModuleIds = useCallback(
		( baseType ) => {
			const ids = new Set();
			if ( moduleData[ baseType ] ) {
				moduleData[ baseType ].modules.forEach( ( order ) => {
					order.items.forEach( ( module ) => {
						ids.add( `${ module.production_batch_id }-${ module.module_sku }-${ module.order_id }` );
					} );
				} );
			}
			return ids;
		},
		[ moduleData ]
	);

	/**
	 * Get module IDs for an order.
	 *
	 * @param {string} baseType The base type.
	 * @param {number} orderId  The order ID.
	 * @return {Set} Set of module IDs.
	 */
	const getOrderModuleIds = useCallback(
		( baseType, orderId ) => {
			const ids = new Set();
			if ( moduleData[ baseType ] ) {
				const order = moduleData[ baseType ].modules.find( ( o ) => o.order_id === orderId );
				if ( order ) {
					order.items.forEach( ( module ) => {
						ids.add( `${ module.production_batch_id }-${ module.module_sku }-${ module.order_id }` );
					} );
				}
			}
			return ids;
		},
		[ moduleData ]
	);

	/**
	 * Get selection state for a base type.
	 *
	 * @param {string} baseType The base type.
	 * @return {string} 'none', 'partial', or 'all'.
	 */
	const getBaseTypeSelectionState = useCallback(
		( baseType ) => {
			const moduleIds = getBaseTypeModuleIds( baseType );
			const selectedCount = [ ...moduleIds ].filter( ( id ) => selectedModules.has( id ) ).length;
			if ( selectedCount === 0 ) {
				return 'none';
			}
			if ( selectedCount === moduleIds.size ) {
				return 'all';
			}
			return 'partial';
		},
		[ getBaseTypeModuleIds, selectedModules ]
	);

	/**
	 * Get selection state for an order.
	 *
	 * @param {string} baseType The base type.
	 * @param {number} orderId  The order ID.
	 * @return {string} 'none', 'partial', or 'all'.
	 */
	const getOrderSelectionState = useCallback(
		( baseType, orderId ) => {
			const moduleIds = getOrderModuleIds( baseType, orderId );
			const selectedCount = [ ...moduleIds ].filter( ( id ) => selectedModules.has( id ) ).length;
			if ( selectedCount === 0 ) {
				return 'none';
			}
			if ( selectedCount === moduleIds.size ) {
				return 'all';
			}
			return 'partial';
		},
		[ getOrderModuleIds, selectedModules ]
	);

	/**
	 * Toggle base type expansion.
	 *
	 * @param {string} baseType The base type.
	 */
	const toggleBaseTypeExpansion = useCallback( ( baseType ) => {
		setExpandedBaseTypes( ( prev ) => {
			const newSet = new Set( prev );
			if ( newSet.has( baseType ) ) {
				newSet.delete( baseType );
			} else {
				newSet.add( baseType );
			}
			return newSet;
		} );
	}, [] );

	/**
	 * Toggle order expansion.
	 *
	 * @param {number} orderId The order ID.
	 */
	const toggleOrderExpansion = useCallback( ( orderId ) => {
		setExpandedOrders( ( prev ) => {
			const newSet = new Set( prev );
			if ( newSet.has( orderId ) ) {
				newSet.delete( orderId );
			} else {
				newSet.add( orderId );
			}
			return newSet;
		} );
	}, [] );

	/**
	 * Toggle base type selection.
	 *
	 * @param {string} baseType The base type.
	 */
	const toggleBaseTypeSelection = useCallback(
		( baseType ) => {
			const moduleIds = getBaseTypeModuleIds( baseType );
			const currentState = getBaseTypeSelectionState( baseType );
			const newSelected = new Set( selectedModules );

			if ( currentState === 'all' ) {
				// Deselect all modules for this base type.
				moduleIds.forEach( ( id ) => newSelected.delete( id ) );
			} else {
				// Select all modules for this base type.
				moduleIds.forEach( ( id ) => newSelected.add( id ) );
				// Auto-expand.
				setExpandedBaseTypes( ( prev ) => new Set( [ ...prev, baseType ] ) );
				if ( moduleData[ baseType ] ) {
					const orderIds = moduleData[ baseType ].modules.map( ( o ) => o.order_id );
					setExpandedOrders( ( prev ) => new Set( [ ...prev, ...orderIds ] ) );
				}
			}

			setSelectedModules( newSelected );
		},
		[ getBaseTypeModuleIds, getBaseTypeSelectionState, moduleData, selectedModules ]
	);

	/**
	 * Toggle order selection.
	 *
	 * @param {string} baseType The base type.
	 * @param {number} orderId  The order ID.
	 */
	const toggleOrderSelection = useCallback(
		( baseType, orderId ) => {
			const moduleIds = getOrderModuleIds( baseType, orderId );
			const currentState = getOrderSelectionState( baseType, orderId );
			const newSelected = new Set( selectedModules );

			if ( currentState === 'all' ) {
				moduleIds.forEach( ( id ) => newSelected.delete( id ) );
			} else {
				moduleIds.forEach( ( id ) => newSelected.add( id ) );
			}

			setSelectedModules( newSelected );
		},
		[ getOrderModuleIds, getOrderSelectionState, selectedModules ]
	);

	/**
	 * Toggle individual module selection.
	 *
	 * @param {string} moduleId The module ID.
	 */
	const toggleModuleSelection = useCallback( ( moduleId ) => {
		setSelectedModules( ( prev ) => {
			const newSet = new Set( prev );
			if ( newSet.has( moduleId ) ) {
				newSet.delete( moduleId );
			} else {
				newSet.add( moduleId );
			}
			return newSet;
		} );
	}, [] );

	/**
	 * Get engrave quantity for a module.
	 *
	 * @param {string} moduleId   The module ID.
	 * @param {number} defaultQty The default quantity.
	 * @return {number} The engrave quantity.
	 */
	const getEngraveQty = useCallback(
		( moduleId, defaultQty ) => {
			return engraveQuantities[ moduleId ] !== undefined ? engraveQuantities[ moduleId ] : defaultQty;
		},
		[ engraveQuantities ]
	);

	/**
	 * Update engrave quantity for a module.
	 * Enforces minimum of 1. No maximum - operators may need to engrave
	 * more modules than originally required for an order.
	 *
	 * @param {string} moduleId The module ID.
	 * @param {number} value    The new quantity.
	 */
	const updateEngraveQty = useCallback(
		( moduleId, value ) => {
			const numValue = parseInt( value, 10 ) || 1;
			// Enforce minimum of 1, no maximum restriction.
			const clampedValue = Math.max( 1, numValue );

			setEngraveQuantities( ( prev ) => ( {
				...prev,
				[ moduleId ]: clampedValue,
			} ) );

			// Auto-select if not already selected.
			if ( ! selectedModules.has( moduleId ) ) {
				setSelectedModules( ( prev ) => new Set( [ ...prev, moduleId ] ) );
			}
		},
		[ selectedModules ]
	);

	/**
	 * Clear all selections.
	 */
	const clearSelections = useCallback( () => {
		setSelectedModules( new Set() );
		setEngraveQuantities( {} );
	}, [] );

	/**
	 * Build selections array from current state.
	 *
	 * @return {Array} Array of selection objects.
	 */
	const buildSelections = useCallback( () => {
		const selections = [];
		getAllModuleIds.forEach( ( { id, module } ) => {
			if ( selectedModules.has( id ) ) {
				selections.push( {
					production_batch_id: module.production_batch_id,
					module_sku: module.module_sku,
					order_id: module.order_id,
					quantity: getEngraveQty( id, module.qty_to_engrave ),
				} );
			}
		} );
		return selections;
	}, [ getAllModuleIds, selectedModules, getEngraveQty ] );

	/**
	 * Calculate totals.
	 */
	const totals = useMemo( () => {
		let moduleCount = 0;
		let unitCount = 0;

		getAllModuleIds.forEach( ( { id, module } ) => {
			if ( selectedModules.has( id ) ) {
				moduleCount++;
				unitCount += getEngraveQty( id, module.qty_to_engrave );
			}
		} );

		return { moduleCount, unitCount };
	}, [ getAllModuleIds, selectedModules, getEngraveQty ] );

	/**
	 * Create the engraving batch.
	 */
	const createBatch = useCallback( async () => {
		if ( totals.moduleCount === 0 ) {
			return;
		}

		setCreatingBatch( true );

		try {
			const response = await ajaxRequest( 'qsa_create_batch', {
				selections: buildSelections(),
			} );

			if ( response.success ) {
				// Redirect to the engraving queue.
				if ( response.data?.redirect_url ) {
					window.location.href = response.data.redirect_url;
				} else {
					// Refresh the module list.
					clearSelections();
					fetchModules();
				}
			} else {
				setError( response.message || __( 'Failed to create batch.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			console.error( 'Batch creation error:', err );
			setError( err.message || __( 'Failed to create batch. Please try again.', 'qsa-engraving' ) );
		} finally {
			setCreatingBatch( false );
		}
	}, [ totals, buildSelections, clearSelections, fetchModules ] );

	// Loading state.
	if ( loading ) {
		return (
			<div className="qsa-batch-creator qsa-loading">
				<span className="spinner is-active"></span>
				<p>{ __( 'Loading modules...', 'qsa-engraving' ) }</p>
			</div>
		);
	}

	/**
	 * Render error banner component.
	 * Parses error messages and displays them in a well-formatted banner.
	 *
	 * @return {JSX.Element|null} Error banner or null if no error.
	 */
	const renderErrorBanner = () => {
		if ( ! error ) {
			return null;
		}

		// Parse error message - look for the main message and affected modules list.
		// Format: "Cannot create batch - LED data missing for: MODULE1 (Order #X): reason; MODULE2..."
		const mainMessageMatch = error.match( /^([^:]+(?::[^:]+)?):?\s*(.*)$/s );
		let mainMessage = error;
		let affectedModules = [];

		if ( mainMessageMatch ) {
			// Check if this is the "Cannot create batch" format.
			if ( error.includes( 'LED data missing for:' ) ) {
				const parts = error.split( 'LED data missing for:' );
				mainMessage = parts[ 0 ].trim().replace( /[-–]\s*$/, '' ).trim();
				if ( parts[ 1 ] ) {
					affectedModules = parts[ 1 ].split( ';' ).map( ( s ) => s.trim() ).filter( Boolean );
				}
			} else {
				// Single error message - check for FIX: instruction.
				const fixMatch = error.match( /^(.+?)\s*(FIX:.+)$/s );
				if ( fixMatch ) {
					mainMessage = fixMatch[ 1 ].trim();
					affectedModules = [ fixMatch[ 2 ].trim() ];
				}
			}
		}

		return (
			<div style={ {
				padding: '16px 20px',
				marginBottom: '16px',
				backgroundColor: '#f8d7da',
				borderLeft: '4px solid #dc3545',
				borderRadius: '4px',
			} }>
				<div style={ { display: 'flex', alignItems: 'flex-start', gap: '12px' } }>
					<span style={ { fontSize: '20px', lineHeight: 1 } }>⚠️</span>
					<div style={ { flex: 1 } }>
						<p style={ {
							margin: 0,
							fontSize: '14px',
							color: '#721c24',
							fontWeight: 'bold',
						} }>
							{ __( 'Error: Cannot Proceed With Batch Creation', 'qsa-engraving' ) }
						</p>
						<p style={ {
							margin: '8px 0 0 0',
							fontSize: '13px',
							color: '#721c24',
							lineHeight: 1.5,
						} }>
							{ mainMessage || __( 'LED data is missing for one or more selected modules.', 'qsa-engraving' ) }
						</p>

						{ affectedModules.length > 0 && (
							<details style={ { marginTop: '12px' } }>
								<summary style={ {
									cursor: 'pointer',
									fontSize: '13px',
									color: '#721c24',
									fontWeight: '600',
									userSelect: 'none',
								} }>
									{ __( 'Details', 'qsa-engraving' ) } ({ affectedModules.length } { affectedModules.length === 1 ? 'item' : 'items' })
								</summary>
								<ul style={ {
									margin: '10px 0 0 0',
									padding: '12px 16px 12px 32px',
									listStyle: 'disc',
									backgroundColor: 'rgba(255, 255, 255, 0.5)',
									borderRadius: '4px',
								} }>
									{ affectedModules.map( ( item, index ) => (
										<li key={ index } style={ {
											margin: '6px 0',
											fontSize: '12px',
											color: '#721c24',
											lineHeight: 1.5,
										} }>
											{ item }
										</li>
									) ) }
								</ul>
							</details>
						) }

						<div style={ { marginTop: '12px' } }>
							<button
								className="button"
								onClick={ () => setError( null ) }
								style={ { marginRight: '8px' } }
							>
								{ __( 'Dismiss', 'qsa-engraving' ) }
							</button>
						</div>
					</div>
				</div>
			</div>
		);
	};

	// Empty state.
	const hasModules = Object.keys( moduleData ).length > 0;

	/**
	 * Render re-engraving source banner.
	 *
	 * @return {JSX.Element|null} Banner or null.
	 */
	const renderReengravingBanner = () => {
		if ( ! reengravingSource || ! reengravingData ) {
			return null;
		}

		// Check if any modules were matched from the source batch.
		const hasMatchedModules = selectedModules.size > 0;

		if ( ! hasMatchedModules ) {
			// No matching modules found - show warning.
			return (
				<div style={ {
					padding: '12px 16px',
					marginBottom: '16px',
					backgroundColor: '#fff3cd',
					borderLeft: '4px solid #dba617',
					borderRadius: '4px',
				} }>
					<div style={ { display: 'flex', alignItems: 'flex-start', gap: '10px' } }>
						<span className="dashicons dashicons-warning" style={ { color: '#856404', marginTop: '2px' } }></span>
						<div>
							<strong style={ { color: '#856404' } }>
								{ __( 'No Matching Modules Found', 'qsa-engraving' ) }
							</strong>
							<p style={ { margin: '4px 0 0 0', fontSize: '13px', color: '#856404' } }>
								{ __( 'Batch', 'qsa-engraving' ) } #{ reengravingData.batch_id }
								{ __( ' was loaded, but none of its modules are currently awaiting engraving. This typically means all modules from that batch have already been engraved.', 'qsa-engraving' ) }
							</p>
							<p style={ { margin: '8px 0 0 0', fontSize: '13px', color: '#856404' } }>
								{ __( 'If you need to re-engrave specific modules, they must first be added back to the production queue (oms_batch_items) with the same SKU and order details.', 'qsa-engraving' ) }
							</p>
						</div>
					</div>
				</div>
			);
		}

		return (
			<div style={ {
				padding: '12px 16px',
				marginBottom: '16px',
				backgroundColor: '#e7f3ff',
				borderLeft: '4px solid #0073aa',
				borderRadius: '4px',
			} }>
				<div style={ { display: 'flex', alignItems: 'center', gap: '10px' } }>
					<span className="dashicons dashicons-update" style={ { color: '#0073aa' } }></span>
					<div>
						<strong style={ { color: '#0073aa' } }>
							{ __( 'Re-engraving Mode', 'qsa-engraving' ) }
						</strong>
						<p style={ { margin: '4px 0 0 0', fontSize: '13px', color: '#444' } }>
							{ __( 'Loaded from Batch', 'qsa-engraving' ) } #{ reengravingData.batch_id }
							{ reengravingData.batch_name && ` — ${ reengravingData.batch_name }` }.
							{ ' ' }
							{ __( 'Modules matching the source batch have been pre-selected. New serial numbers will be assigned upon batch creation.', 'qsa-engraving' ) }
						</p>
					</div>
				</div>
			</div>
		);
	};

	/**
	 * Get the dashboard URL.
	 *
	 * @return {string} The main QSA Engraving dashboard URL.
	 */
	const getDashboardUrl = () => {
		const baseUrl = window.qsaEngraving?.adminUrl || '/wp-admin/';
		return `${ baseUrl }admin.php?page=qsa-engraving`;
	};

	/**
	 * Get the batch history URL.
	 *
	 * @return {string} The batch history page URL.
	 */
	const getBatchHistoryUrl = () => {
		const baseUrl = window.qsaEngraving?.adminUrl || '/wp-admin/';
		return `${ baseUrl }admin.php?page=qsa-engraving-history`;
	};

	return (
		<div className="qsa-batch-creator">
			{ /* Page Header - matches mockup design */ }
			<div className="qsa-batch-creator-header">
				<div className="qsa-batch-creator-header-left">
					<div className="qsa-batch-creator-icon">
						<span className="dashicons dashicons-superhero-alt"></span>
					</div>
					<div>
						<h1 className="qsa-batch-creator-title">
							{ __( 'Module Engraving Batch Creator', 'qsa-engraving' ) }
						</h1>
						<p className="qsa-batch-creator-subtitle">
							{ __( 'Select modules to include in engraving batch', 'qsa-engraving' ) }
						</p>
					</div>
				</div>
				<div className="qsa-batch-creator-header-buttons">
					<a
						href={ getDashboardUrl() }
						className="qsa-btn-back"
						title={ __( 'Return to QSA Engraving dashboard', 'qsa-engraving' ) }
					>
						<span className="dashicons dashicons-arrow-left-alt"></span>
						{ __( 'Back to Dashboard', 'qsa-engraving' ) }
					</a>
					<a
						href={ getBatchHistoryUrl() }
						className="qsa-btn-history"
						title={ __( 'View previously completed batches for re-engraving', 'qsa-engraving' ) }
					>
						<span className="dashicons dashicons-backup"></span>
						{ __( 'View Batch History', 'qsa-engraving' ) }
					</a>
				</div>
			</div>

			<StatsBar
				baseTypeCount={ Object.keys( moduleData ).length }
				selectedCount={ totals.moduleCount }
				unitCount={ totals.unitCount }
			/>

			{ /* Re-engraving source banner */ }
			{ renderReengravingBanner() }

			{ /* Error banner display */ }
			{ renderErrorBanner() }

			<ActionBar
				hasSelection={ totals.moduleCount > 0 }
				moduleCount={ totals.moduleCount }
				unitCount={ totals.unitCount }
				onClear={ clearSelections }
				onCreateBatch={ createBatch }
				creating={ creatingBatch }
				onRefresh={ fetchModules }
			/>

			{ hasModules ? (
				<ModuleTree
					moduleData={ moduleData }
					baseTypeNames={ BASE_TYPE_NAMES }
					selectedModules={ selectedModules }
					expandedBaseTypes={ expandedBaseTypes }
					expandedOrders={ expandedOrders }
					getBaseTypeSelectionState={ getBaseTypeSelectionState }
					getOrderSelectionState={ getOrderSelectionState }
					toggleBaseTypeExpansion={ toggleBaseTypeExpansion }
					toggleOrderExpansion={ toggleOrderExpansion }
					toggleBaseTypeSelection={ toggleBaseTypeSelection }
					toggleOrderSelection={ toggleOrderSelection }
					toggleModuleSelection={ toggleModuleSelection }
					getEngraveQty={ getEngraveQty }
					updateEngraveQty={ updateEngraveQty }
				/>
			) : (
				<div className="qsa-empty-state">
					<p>{ __( 'No modules awaiting engraving.', 'qsa-engraving' ) }</p>
					<button className="button" onClick={ fetchModules }>
						{ __( 'Refresh', 'qsa-engraving' ) }
					</button>
				</div>
			) }
		</div>
	);
}
