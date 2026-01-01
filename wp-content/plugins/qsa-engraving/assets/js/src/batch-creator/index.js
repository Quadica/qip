/**
 * Batch Creator - Main Entry Point
 *
 * Renders the Module Engraving Batch Creator interface.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { createRoot } from '@wordpress/element';
import BatchCreator from './components/BatchCreator';
import './style.css';

// Wait for DOM to be ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'qsa-engraving-batch-creator' );
	if ( container ) {
		const root = createRoot( container );
		root.render( <BatchCreator /> );
	}
} );
