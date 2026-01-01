/**
 * Engraving Queue - Main Entry Point
 *
 * Renders the Engraving Queue interface.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { createRoot } from '@wordpress/element';
import EngravingQueue from './components/EngravingQueue';
import './style.css';

// Wait for DOM to be ready.
document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'qsa-engraving-engraving-queue' );
	if ( container ) {
		const root = createRoot( container );
		root.render( <EngravingQueue /> );
	}
} );
