/**
 * BatchCreator - Main Component
 *
 * Displays a hierarchical list of modules awaiting engraving,
 * allows selection and quantity editing, and creates engraving batches.
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
	const [ previewData, setPreviewData ] = useState( null );
	const [ previewing, setPreviewing ] = useState( false );

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

		return response.json();
	};

	/**
	 * Fetch modules from the server via AJAX.
	 */
	const fetchModules = useCallback( async () => {
		setLoading( true );
		setError( null );
		setPreviewData( null );

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
	 * Load modules on mount.
	 */
	useEffect( () => {
		fetchModules();
	}, [ fetchModules ] );

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
			setPreviewData( null ); // Clear preview when selection changes.
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
			setPreviewData( null ); // Clear preview when selection changes.
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
		setPreviewData( null ); // Clear preview when selection changes.
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
	 * Enforces 1 <= qty <= maxQty as per specification.
	 *
	 * @param {string} moduleId The module ID.
	 * @param {number} value    The new quantity.
	 * @param {number} maxQty   The maximum allowed quantity.
	 */
	const updateEngraveQty = useCallback(
		( moduleId, value, maxQty ) => {
			const numValue = parseInt( value, 10 ) || 1;
			// Enforce minimum of 1, maximum of available quantity.
			const clampedValue = Math.max( 1, Math.min( numValue, maxQty ) );

			setEngraveQuantities( ( prev ) => ( {
				...prev,
				[ moduleId ]: clampedValue,
			} ) );

			// Auto-select if not already selected.
			if ( ! selectedModules.has( moduleId ) ) {
				setSelectedModules( ( prev ) => new Set( [ ...prev, moduleId ] ) );
			}

			setPreviewData( null ); // Clear preview when quantity changes.
		},
		[ selectedModules ]
	);

	/**
	 * Clear all selections.
	 */
	const clearSelections = useCallback( () => {
		setSelectedModules( new Set() );
		setEngraveQuantities( {} );
		setPreviewData( null );
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
	 * Preview the batch to see LED transitions and array breakdown.
	 */
	const previewBatch = useCallback( async () => {
		if ( totals.moduleCount === 0 ) {
			return;
		}

		setPreviewing( true );
		setError( null );

		try {
			const response = await ajaxRequest( 'qsa_preview_batch', {
				selections: buildSelections(),
				start_position: 1,
			} );

			// eslint-disable-next-line no-console
			console.log( 'Preview response:', response );

			if ( response.success && response.data ) {
				setPreviewData( response.data );
			} else {
				// Extract error message from response.
				const errorMsg = response.message || response.data?.message || __( 'Failed to preview batch.', 'qsa-engraving' );
				setError( errorMsg );
			}
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( 'Preview error:', err );
			setError( __( 'Failed to preview batch.', 'qsa-engraving' ) + ' ' + ( err.message || '' ) );
		} finally {
			setPreviewing( false );
		}
	}, [ totals, buildSelections ] );

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
			setError( __( 'Failed to create batch. Please try again.', 'qsa-engraving' ) );
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

	// Error state.
	if ( error ) {
		// Check if error contains multiple lines (separated by semicolon or newline).
		const errorParts = error.split( /[;\n]/ ).map( ( s ) => s.trim() ).filter( Boolean );
		const isMultiLine = errorParts.length > 1;

		return (
			<div className="qsa-batch-creator qsa-error">
				<div className="notice notice-error" style={ { padding: '12px 20px', marginBottom: '12px' } }>
					<p style={ { margin: 0, fontSize: '14px', color: '#721c24', fontWeight: 'bold' } }>
						{ __( 'Error: Cannot proceed with batch creation', 'qsa-engraving' ) }
					</p>
					{ isMultiLine ? (
						<ul style={ { margin: '10px 0 0 20px', padding: 0, listStyle: 'disc' } }>
							{ errorParts.map( ( part, index ) => (
								<li key={ index } style={ { margin: '4px 0', fontSize: '13px', color: '#721c24' } }>
									{ part }
								</li>
							) ) }
						</ul>
					) : (
						<p style={ { margin: '8px 0 0 0', fontSize: '13px', color: '#721c24' } }>
							{ error }
						</p>
					) }
				</div>
				<button className="button" onClick={ () => { setError( null ); fetchModules(); } }>
					{ __( 'Retry', 'qsa-engraving' ) }
				</button>
			</div>
		);
	}

	// Empty state.
	const hasModules = Object.keys( moduleData ).length > 0;

	return (
		<div className="qsa-batch-creator">
			<StatsBar
				baseTypeCount={ Object.keys( moduleData ).length }
				selectedCount={ totals.moduleCount }
				unitCount={ totals.unitCount }
				previewData={ previewData }
			/>

			<ActionBar
				hasSelection={ totals.moduleCount > 0 }
				moduleCount={ totals.moduleCount }
				unitCount={ totals.unitCount }
				onClear={ clearSelections }
				onPreview={ previewBatch }
				onCreateBatch={ createBatch }
				creating={ creatingBatch }
				previewing={ previewing }
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
