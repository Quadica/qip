/**
 * Batch List Component.
 *
 * Displays a paginated list of completed batches.
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
 * Format relative time.
 *
 * @param {string} dateString ISO date string.
 * @return {string} Relative time string.
 */
function formatRelativeTime( dateString ) {
	if ( ! dateString ) {
		return '';
	}

	const date = new Date( dateString );
	const now = new Date();
	const diffMs = now - date;
	const diffDays = Math.floor( diffMs / ( 1000 * 60 * 60 * 24 ) );

	if ( diffDays === 0 ) {
		return __( 'Today', 'qsa-engraving' );
	}
	if ( diffDays === 1 ) {
		return __( 'Yesterday', 'qsa-engraving' );
	}
	if ( diffDays < 7 ) {
		return diffDays + ' ' + __( 'days ago', 'qsa-engraving' );
	}
	if ( diffDays < 30 ) {
		return Math.floor( diffDays / 7 ) + ' ' + __( 'weeks ago', 'qsa-engraving' );
	}
	return Math.floor( diffDays / 30 ) + ' ' + __( 'months ago', 'qsa-engraving' );
}

/**
 * BatchList component.
 *
 * @param {Object}   props                  Props.
 * @param {Array}    props.batches          Array of batch objects.
 * @param {number}   props.selectedBatchId  Currently selected batch ID.
 * @param {Function} props.onSelectBatch    Callback when batch is selected.
 * @param {Object}   props.pagination       Pagination state.
 * @param {Function} props.onPageChange     Callback when page changes.
 * @param {boolean}  props.loading          Whether data is loading.
 * @return {JSX.Element} The component.
 */
export default function BatchList( {
	batches,
	selectedBatchId,
	onSelectBatch,
	pagination,
	onPageChange,
	loading,
} ) {
	/**
	 * Render pagination controls.
	 *
	 * @return {JSX.Element|null} Pagination component.
	 */
	const renderPagination = () => {
		if ( pagination.total_pages <= 1 ) {
			return null;
		}

		return (
			<div className="qsa-pagination">
				<button
					onClick={ () => onPageChange( pagination.page - 1 ) }
					disabled={ pagination.page <= 1 || loading }
					className="qsa-pagination-btn"
				>
					<span className="dashicons dashicons-arrow-left-alt2"></span>
				</button>
				<span className="qsa-pagination-info">
					{ pagination.page } / { pagination.total_pages }
				</span>
				<button
					onClick={ () => onPageChange( pagination.page + 1 ) }
					disabled={ pagination.page >= pagination.total_pages || loading }
					className="qsa-pagination-btn"
				>
					<span className="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		);
	};

	// Empty state.
	if ( batches.length === 0 && ! loading ) {
		return (
			<div className="qsa-batch-list">
				<div className="qsa-batch-list-header">
					<span className="dashicons dashicons-archive"></span>
					<span className="qsa-batch-list-title">
						{ __( 'Completed Batches', 'qsa-engraving' ) }
					</span>
					<span className="qsa-batch-count">(0)</span>
				</div>
				<div className="qsa-batch-list-empty">
					<span className="dashicons dashicons-info"></span>
					<p>{ __( 'No completed batches found matching your criteria.', 'qsa-engraving' ) }</p>
				</div>
			</div>
		);
	}

	return (
		<div className="qsa-batch-list">
			{ /* Header */ }
			<div className="qsa-batch-list-header">
				<span className="dashicons dashicons-archive"></span>
				<span className="qsa-batch-list-title">
					{ __( 'Completed Batches', 'qsa-engraving' ) }
				</span>
				<span className="qsa-batch-count">({ pagination.total })</span>
				{ loading && <span className="spinner is-active"></span> }
			</div>

			{ /* Batch Items */ }
			<div className="qsa-batch-list-items">
				{ batches.map( ( batch ) => {
					const isSelected = selectedBatchId === batch.id;

					return (
						<div
							key={ batch.id }
							onClick={ () => onSelectBatch( batch.id ) }
							className={ `qsa-batch-item ${ isSelected ? 'selected' : '' }` }
							role="button"
							tabIndex={ 0 }
							onKeyDown={ ( e ) => {
								if ( e.key === 'Enter' || e.key === ' ' ) {
									e.preventDefault();
									onSelectBatch( batch.id );
								}
							} }
						>
							{ /* Batch Header */ }
							<div className="qsa-batch-item-header">
								<div className="qsa-batch-id">
									<span className="batch-label">
										{ __( 'Batch', 'qsa-engraving' ) }
									</span>
									<span className="batch-number">#{ batch.id }</span>
								</div>
								<span className="qsa-batch-status completed">
									<span className="dashicons dashicons-yes-alt"></span>
									{ __( 'Completed', 'qsa-engraving' ) }
								</span>
								<span className="qsa-batch-time">
									{ formatRelativeTime( batch.completed_at ) }
								</span>
							</div>

							{ /* Batch Stats */ }
							<div className="qsa-batch-stats">
								<span className="qsa-batch-stat">
									<span className="dashicons dashicons-products"></span>
									{ batch.module_count } { __( 'modules', 'qsa-engraving' ) }
								</span>
								<span className="qsa-batch-stat">
									<span className="dashicons dashicons-grid-view"></span>
									{ batch.qsa_count } { __( 'arrays', 'qsa-engraving' ) }
								</span>
								<span className="qsa-batch-stat">
									<span className="dashicons dashicons-cart"></span>
									{ batch.order_ids.length > 2
										? batch.order_ids.slice( 0, 2 ).join( ', ' ) + ' +' + ( batch.order_ids.length - 2 )
										: batch.order_ids.join( ', ' )
									}
								</span>
							</div>

							{ /* Module Types */ }
							<div className="qsa-batch-types">
								{ batch.module_types.map( ( type ) => (
									<span key={ type } className="qsa-type-badge">
										{ type }
									</span>
								) ) }
							</div>
						</div>
					);
				} ) }
			</div>

			{ /* Pagination */ }
			{ renderPagination() }
		</div>
	);
}
