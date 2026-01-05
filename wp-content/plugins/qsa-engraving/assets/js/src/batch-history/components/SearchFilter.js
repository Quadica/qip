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
	const [ moduleTypes, setModuleTypes ] = useState( [] );
	const [ typesLoading, setTypesLoading ] = useState( true );

	// Fetch available module types on mount.
	useEffect( () => {
		const fetchModuleTypes = async () => {
			try {
				const formData = new FormData();
				formData.append( 'action', 'qsa_get_available_module_types' );
				formData.append( 'nonce', window.qsaEngraving?.nonce || '' );

				const response = await fetch( window.qsaEngraving?.ajaxUrl || '/wp-admin/admin-ajax.php', {
					method: 'POST',
					body: formData,
				} );

				const data = await response.json();

				if ( data.success && data.data.types ) {
					setModuleTypes( data.data.types );
				}
			} catch ( err ) {
				// Silently fail - filters will just show "All Types" only.
			}
			setTypesLoading( false );
		};

		fetchModuleTypes();
	}, [] );

	// Debounce search input (500ms delay to avoid triggering on every keystroke).
	useEffect( () => {
		const timer = setTimeout( () => {
			if ( searchInput !== filters.search ) {
				onFilterChange( { ...filters, search: searchInput } );
			}
		}, 500 );

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
						{ /* All Types button */ }
						<button
							onClick={ () => handleFilterTypeChange( 'all' ) }
							className={ `qsa-filter-button ${ filters.filterType === 'all' ? 'active' : '' }` }
							disabled={ loading }
						>
							{ __( 'All Types', 'qsa-engraving' ) }
						</button>
						{ /* Dynamic module type buttons */ }
						{ typesLoading ? (
							<span className="qsa-filter-loading">{ __( 'Loading...', 'qsa-engraving' ) }</span>
						) : (
							moduleTypes.map( ( type ) => (
								<button
									key={ type }
									onClick={ () => handleFilterTypeChange( type ) }
									className={ `qsa-filter-button ${ filters.filterType === type ? 'active' : '' }` }
									disabled={ loading }
								>
									{ type }
								</button>
							) )
						) }
					</div>
				</div>
			) }
		</div>
	);
}
