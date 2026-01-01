/**
 * Batch History Entry Point.
 *
 * Initializes the React application for the Batch History page.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { createRoot } from '@wordpress/element';
import BatchHistory from './components/BatchHistory';
import './style.css';

// Wait for DOM ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'qsa-engraving-batch-history' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <BatchHistory /> );
	}
} );
