/**
 * Batch History Component.
 *
 * Main container for viewing completed engraving batches
 * and loading them for re-engraving.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import BatchList from './BatchList';
import BatchDetails from './BatchDetails';
import SearchFilter from './SearchFilter';

/**
 * Batch History component.
 *
 * @return {JSX.Element} The component.
 */
export default function BatchHistory() {
	const [ batches, setBatches ] = useState( [] );
	const [ selectedBatchId, setSelectedBatchId ] = useState( null );
	const [ selectedBatch, setSelectedBatch ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ detailsLoading, setDetailsLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ pagination, setPagination ] = useState( {
		page: 1,
		per_page: 20,
		total: 0,
		total_pages: 0,
	} );
	const [ filters, setFilters ] = useState( {
		search: '',
		filterType: 'all',
	} );

	/**
	 * Fetch batch history from the server.
	 */
	const fetchBatchHistory = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const formData = new FormData();
			formData.append( 'action', 'qsa_get_batch_history' );
			formData.append( 'nonce', window.qsaEngraving?.nonce || '' );
			formData.append( 'page', pagination.page );
			formData.append( 'per_page', pagination.per_page );
			formData.append( 'search', filters.search );
			formData.append( 'filter_type', filters.filterType );
			formData.append( 'status', 'completed' );

			const response = await fetch( window.qsaEngraving?.ajaxUrl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData,
			} );

			const data = await response.json();

			if ( data.success ) {
				setBatches( data.data.batches );
				setPagination( ( prev ) => ( {
					...prev,
					...data.data.pagination,
				} ) );
			} else {
				setError( data.data?.message || __( 'Failed to load batch history.', 'qsa-engraving' ) );
			}
		} catch ( err ) {
			setError( __( 'Network error loading batch history.', 'qsa-engraving' ) );
		}

		setLoading( false );
	}, [ pagination.page, pagination.per_page, filters.search, filters.filterType ] );

	// Fetch on mount and when filters change.
	useEffect( () => {
		fetchBatchHistory();
	}, [ fetchBatchHistory ] );

	/**
	 * Fetch batch details when selected.
	 */
	const fetchBatchDetails = useCallback( async ( batchId ) => {
		if ( ! batchId ) {
			setSelectedBatch( null );
			return;
		}

		setDetailsLoading( true );

		try {
			const formData = new FormData();
			formData.append( 'action', 'qsa_get_batch_details' );
			formData.append( 'nonce', window.qsaEngraving?.nonce || '' );
			formData.append( 'batch_id', batchId );

			const response = await fetch( window.qsaEngraving?.ajaxUrl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData,
			} );

			const data = await response.json();

			if ( data.success ) {
				setSelectedBatch( data.data.batch );
			} else {
				setSelectedBatch( null );
			}
		} catch ( err ) {
			setSelectedBatch( null );
		}

		setDetailsLoading( false );
	}, [] );

	// Fetch details when selection changes.
	useEffect( () => {
		fetchBatchDetails( selectedBatchId );
	}, [ selectedBatchId, fetchBatchDetails ] );

	/**
	 * Handle batch selection.
	 *
	 * @param {number} batchId The batch ID.
	 */
	const handleSelectBatch = ( batchId ) => {
		setSelectedBatchId( batchId );
	};

	/**
	 * Handle search/filter changes.
	 *
	 * @param {Object} newFilters Updated filters.
	 */
	const handleFilterChange = ( newFilters ) => {
		setFilters( newFilters );
		setPagination( ( prev ) => ( { ...prev, page: 1 } ) );
		setSelectedBatchId( null );
		setSelectedBatch( null );
	};

	/**
	 * Handle page change.
	 *
	 * @param {number} newPage The new page number.
	 */
	const handlePageChange = ( newPage ) => {
		setPagination( ( prev ) => ( { ...prev, page: newPage } ) );
		setSelectedBatchId( null );
		setSelectedBatch( null );
	};

	/**
	 * Handle load for re-engraving.
	 */
	const handleLoadForReengraving = () => {
		if ( ! selectedBatch ) {
			return;
		}

		// Navigate to Batch Creator with source=history and batch_id.
		const batchCreatorUrl = new URL( window.location.href );
		batchCreatorUrl.searchParams.set( 'page', 'qsa-engraving-batch-creator' );
		batchCreatorUrl.searchParams.set( 'source', 'history' );
		batchCreatorUrl.searchParams.set( 'source_batch_id', selectedBatch.id );

		window.location.href = batchCreatorUrl.toString();
	};

	/**
	 * Navigate back to Batch Creator.
	 */
	const handleBackToBatchCreator = () => {
		const batchCreatorUrl = new URL( window.location.href );
		batchCreatorUrl.searchParams.set( 'page', 'qsa-engraving-batch-creator' );
		batchCreatorUrl.searchParams.delete( 'source' );
		batchCreatorUrl.searchParams.delete( 'source_batch_id' );

		window.location.href = batchCreatorUrl.toString();
	};

	// Render loading state.
	if ( loading && batches.length === 0 ) {
		return (
			<div className="qsa-history-loading">
				<span className="spinner is-active"></span>
				<p>{ __( 'Loading batch history...', 'qsa-engraving' ) }</p>
			</div>
		);
	}

	// Render error state.
	if ( error && batches.length === 0 ) {
		return (
			<div className="qsa-history-error">
				<div className="notice notice-error">
					<p>{ error }</p>
				</div>
				<button onClick={ fetchBatchHistory } className="button">
					{ __( 'Retry', 'qsa-engraving' ) }
				</button>
			</div>
		);
	}

	return (
		<div className="qsa-batch-history">
			{ /* Header with Back Button */ }
			<div className="qsa-history-header">
				<button
					onClick={ handleBackToBatchCreator }
					className="qsa-back-button"
					title={ __( 'Back to Batch Creator', 'qsa-engraving' ) }
				>
					<span className="dashicons dashicons-arrow-left-alt"></span>
					{ __( 'Batch Creator', 'qsa-engraving' ) }
				</button>
				<div className="qsa-history-title">
					<span className="dashicons dashicons-backup"></span>
					<h2>{ __( 'Engraving Batch History', 'qsa-engraving' ) }</h2>
				</div>
			</div>

			{ /* Search and Filters */ }
			<SearchFilter
				filters={ filters }
				onFilterChange={ handleFilterChange }
				loading={ loading }
			/>

			{ /* Main Content */ }
			<div className="qsa-history-content">
				{ /* Batch List Panel */ }
				<div className="qsa-history-list-panel">
					<BatchList
						batches={ batches }
						selectedBatchId={ selectedBatchId }
						onSelectBatch={ handleSelectBatch }
						pagination={ pagination }
						onPageChange={ handlePageChange }
						loading={ loading }
					/>
				</div>

				{ /* Batch Details Panel */ }
				<div className="qsa-history-details-panel">
					<BatchDetails
						batch={ selectedBatch }
						loading={ detailsLoading }
						onLoadForReengraving={ handleLoadForReengraving }
					/>
				</div>
			</div>

			{ /* Re-engraving Workflow Legend */ }
			<div className="qsa-history-legend">
				<span className="qsa-legend-title">
					{ __( 'Re-engraving Workflow:', 'qsa-engraving' ) }
				</span>
				<div className="qsa-legend-steps">
					<span className="qsa-legend-step">
						<span className="step-number">1</span>
						{ __( 'Select batch', 'qsa-engraving' ) }
					</span>
					<span className="dashicons dashicons-arrow-right-alt"></span>
					<span className="qsa-legend-step">
						<span className="step-number">2</span>
						{ __( 'Load into Batch Creator', 'qsa-engraving' ) }
					</span>
					<span className="dashicons dashicons-arrow-right-alt"></span>
					<span className="qsa-legend-step">
						<span className="step-number">3</span>
						{ __( 'Select modules to re-engrave', 'qsa-engraving' ) }
					</span>
					<span className="dashicons dashicons-arrow-right-alt"></span>
					<span className="qsa-legend-step">
						<span className="step-number">4</span>
						{ __( 'New serials assigned', 'qsa-engraving' ) }
					</span>
				</div>
			</div>
		</div>
	);
}
