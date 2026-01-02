/**
 * OrderRow - Order Tree Row
 *
 * Displays an order row with expandable modules.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import CheckboxIcon from './CheckboxIcon';
import ModuleRow from './ModuleRow';

/**
 * OrderRow component.
 *
 * @param {Object}   props                      Component props.
 * @param {string}   props.baseType             The parent base type.
 * @param {Object}   props.order                The order data.
 * @param {boolean}  props.isExpanded           Whether the row is expanded.
 * @param {string}   props.selectionState       Selection state: 'none', 'partial', 'all'.
 * @param {Set}      props.selectedModules      Set of selected module IDs.
 * @param {Function} props.onToggleExpand       Toggle expansion callback.
 * @param {Function} props.onToggleSelect       Toggle selection callback.
 * @param {Function} props.onToggleModuleSelect Toggle module selection callback.
 * @param {Function} props.getEngraveQty        Get engrave quantity.
 * @param {Function} props.updateEngraveQty     Update engrave quantity.
 * @return {JSX.Element} The component.
 */
export default function OrderRow( {
	baseType,
	order,
	isExpanded,
	selectionState,
	selectedModules,
	onToggleExpand,
	onToggleSelect,
	onToggleModuleSelect,
	getEngraveQty,
	updateEngraveQty,
} ) {
	const skuCount = order.items.length;
	const totalQty = order.total_qty;
	const isSelected = selectionState !== 'none';

	return (
		<div className={ `qsa-order ${ isSelected ? 'is-selected' : '' }` }>
			{ /* Order Header */ }
			<div className="qsa-order-row">
				<button
					className={ `qsa-checkbox qsa-checkbox--${ selectionState }` }
					onClick={ onToggleSelect }
					aria-checked={ selectionState === 'all' }
					aria-label={ __( 'Select all modules in this order', 'qsa-engraving' ) }
				>
					<CheckboxIcon state={ selectionState } />
				</button>

				<button
					className="qsa-expand-btn"
					onClick={ onToggleExpand }
					aria-expanded={ isExpanded }
					aria-label={ isExpanded ? __( 'Collapse', 'qsa-engraving' ) : __( 'Expand', 'qsa-engraving' ) }
				>
					<span className={ `dashicons ${ isExpanded ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-right-alt2' }` }></span>
				</button>

				<span className="dashicons dashicons-portfolio qsa-order-icon"></span>

				<div className="qsa-order-info" onClick={ onToggleExpand }>
					<span className="qsa-order-id">#{ order.order_id }</span>
				</div>

				<div className="qsa-order-stats">
					<span className="qsa-order-module-count">
						{ skuCount } { skuCount === 1 ? __( 'module', 'qsa-engraving' ) : __( 'modules', 'qsa-engraving' ) }
					</span>
				</div>
			</div>

			{ /* Modules (when expanded) */ }
			{ isExpanded && (
				<div className="qsa-modules">
					{ order.items.map( ( module ) => {
						const moduleId = `${ module.production_batch_id }-${ module.module_sku }-${ module.order_id }`;
						return (
							<ModuleRow
								key={ moduleId }
								module={ module }
								moduleId={ moduleId }
								isSelected={ selectedModules.has( moduleId ) }
								engraveQty={ getEngraveQty( moduleId, module.qty_to_engrave ) }
								onToggleSelect={ () => onToggleModuleSelect( moduleId ) }
								onUpdateQty={ ( value ) => updateEngraveQty( moduleId, value, module.qty_to_engrave ) }
							/>
						);
					} ) }
				</div>
			) }
		</div>
	);
}
