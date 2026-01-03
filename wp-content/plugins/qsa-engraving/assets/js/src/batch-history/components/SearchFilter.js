/**
 * Search and Filter Component.
 *
 * Provides search and filter controls for batch history.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Module type filter options.
 */
const MODULE_TYPES = [
	{ value: 'all', label: __( 'All Types', 'qsa-engraving' ) },
	{ value: 'CORE', label: 'CORE' },
	{ value: 'SOLO', label: 'SOLO' },
	{ value: 'EDGE', label: 'EDGE' },
	{ value: 'STAR', label: 'STAR' },
];

/**
 * SearchFilter component.
 *
 * @param {Object}   props                Props.
 * @param {Object}   props.filters        Current filter values.
 * @param {Function} props.onFilterChange Callback when filters change.
 * @param {boolean}  props.loading        Whether data is loading.
 * @return {JSX.Element} The component.
 */
export default function SearchFilter( { filters, onFilterChange, loading } ) {
	const [ searchInput, setSearchInput ] = useState( filters.search );
	const [ showFilters, setShowFilters ] = useState( false );

	// Debounce search input.
	useEffect( () => {
		const timer = setTimeout( () => {
			if ( searchInput !== filters.search ) {
				onFilterChange( { ...filters, search: searchInput } );
			}
		}, 300 );

		return () => clearTimeout( timer );
	}, [ searchInput ] );

	/**
	 * Handle search input change.
	 *
	 * @param {Event} e Change event.
	 */
	const handleSearchChange = ( e ) => {
		setSearchInput( e.target.value );
	};

	/**
	 * Handle clear search.
	 */
	const handleClearSearch = () => {
		setSearchInput( '' );
		onFilterChange( { ...filters, search: '' } );
	};

	/**
	 * Handle filter type change.
	 *
	 * @param {string} type The filter type value.
	 */
	const handleFilterTypeChange = ( type ) => {
		onFilterChange( { ...filters, filterType: type } );
	};

	return (
		<div className="qsa-search-filter">
			{ /* Search Bar */ }
			<div className="qsa-search-bar">
				<div className="qsa-search-input-wrapper">
					<input
						type="text"
						placeholder={ __( 'Search by batch ID, order ID, or module SKU...', 'qsa-engraving' ) }
						value={ searchInput }
						onChange={ handleSearchChange }
						className="qsa-search-input"
						disabled={ loading }
					/>
					{ searchInput ? (
						<button
							onClick={ handleClearSearch }
							className="qsa-search-clear"
							title={ __( 'Clear search', 'qsa-engraving' ) }
						>
							<span className="dashicons dashicons-no-alt"></span>
						</button>
					) : (
						<span className="dashicons dashicons-search qsa-search-icon"></span>
					) }
				</div>

				<button
					onClick={ () => setShowFilters( ! showFilters ) }
					className={ `qsa-filter-toggle ${ showFilters ? 'active' : '' }` }
				>
					<span className="dashicons dashicons-filter"></span>
					{ __( 'Filters', 'qsa-engraving' ) }
				</button>
			</div>

			{ /* Filter Options */ }
			{ showFilters && (
				<div className="qsa-filter-options">
					<span className="qsa-filter-label">
						{ __( 'Module Type:', 'qsa-engraving' ) }
					</span>
					<div className="qsa-filter-buttons">
						{ MODULE_TYPES.map( ( type ) => (
							<button
								key={ type.value }
								onClick={ () => handleFilterTypeChange( type.value ) }
								className={ `qsa-filter-button ${ filters.filterType === type.value ? 'active' : '' }` }
								disabled={ loading }
							>
								{ type.label }
							</button>
						) ) }
					</div>
				</div>
			) }
		</div>
	);
}
