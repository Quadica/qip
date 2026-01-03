/**
 * ModuleRow - Individual Module Row
 *
 * Displays a single module with selection and quantity editing.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * ModuleRow component.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.module        The module data.
 * @param {string}   props.moduleId      The unique module ID.
 * @param {boolean}  props.isSelected    Whether the module is selected.
 * @param {number}   props.engraveQty    The quantity to engrave.
 * @param {Function} props.onToggleSelect Toggle selection callback.
 * @param {Function} props.onUpdateQty   Update quantity callback.
 * @return {JSX.Element} The component.
 */
export default function ModuleRow( {
	module,
	moduleId,
	isSelected,
	engraveQty,
	onToggleSelect,
	onUpdateQty,
} ) {
	const { module_sku, build_qty, qty_received, qty_to_engrave } = module;

	return (
		<div className={ `qsa-module ${ isSelected ? 'is-selected' : '' }` }>
			<button
				className={ `qsa-checkbox qsa-checkbox--${ isSelected ? 'all' : 'none' }` }
				onClick={ onToggleSelect }
				aria-checked={ isSelected }
				aria-label={ __( 'Select this module', 'qsa-engraving' ) }
			>
				{ isSelected && <span className="dashicons dashicons-yes"></span> }
			</button>

			<span className="qsa-module-icon"></span>

			<div className="qsa-module-info">
				<span className="qsa-module-sku">{ module_sku }</span>
			</div>

			<div className="qsa-module-progress">
				<span className="qsa-progress-text">
					{ qty_received }/{ build_qty } { __( 'complete', 'qsa-engraving' ) }
				</span>
			</div>

			<div className="qsa-module-qty">
				<input
					type="number"
					min="1"
					max={ qty_to_engrave }
					value={ engraveQty }
					onChange={ ( e ) => onUpdateQty( e.target.value ) }
					onClick={ ( e ) => e.stopPropagation() }
					className="qsa-qty-input"
					aria-label={ __( 'Quantity to engrave', 'qsa-engraving' ) }
				/>
				<span className="qsa-qty-max">/ { qty_to_engrave }</span>
			</div>
		</div>
	);
}
