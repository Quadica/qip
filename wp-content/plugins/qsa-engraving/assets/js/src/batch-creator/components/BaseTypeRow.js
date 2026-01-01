/**
 * BaseTypeRow - Base Type Tree Row
 *
 * Displays a base type row with expandable orders.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import CheckboxIcon from './CheckboxIcon';
import OrderRow from './OrderRow';

/**
 * BaseTypeRow component.
 *
 * @param {Object}   props                        Component props.
 * @param {string}   props.baseType               The base type code (e.g., 'CORE').
 * @param {string}   props.baseTypeName           Display name for the base type.
 * @param {Object}   props.data                   The base type data.
 * @param {boolean}  props.isExpanded             Whether the row is expanded.
 * @param {string}   props.selectionState         Selection state: 'none', 'partial', 'all'.
 * @param {Set}      props.expandedOrders         Set of expanded order IDs.
 * @param {Set}      props.selectedModules        Set of selected module IDs.
 * @param {Function} props.getOrderSelectionState Get selection state for order.
 * @param {Function} props.onToggleExpand         Toggle expansion callback.
 * @param {Function} props.onToggleSelect         Toggle selection callback.
 * @param {Function} props.onToggleOrderExpand    Toggle order expansion callback.
 * @param {Function} props.onToggleOrderSelect    Toggle order selection callback.
 * @param {Function} props.onToggleModuleSelect   Toggle module selection callback.
 * @param {Function} props.getEngraveQty          Get engrave quantity.
 * @param {Function} props.updateEngraveQty       Update engrave quantity.
 * @param {boolean}  props.isLast                 Whether this is the last item.
 * @return {JSX.Element} The component.
 */
export default function BaseTypeRow( {
	baseType,
	baseTypeName,
	data,
	isExpanded,
	selectionState,
	expandedOrders,
	selectedModules,
	getOrderSelectionState,
	onToggleExpand,
	onToggleSelect,
	onToggleOrderExpand,
	onToggleOrderSelect,
	onToggleModuleSelect,
	getEngraveQty,
	updateEngraveQty,
	isLast,
} ) {
	const orderCount = data.order_count || data.modules.length;
	const totalQty = data.total_qty;
	const isSelected = selectionState !== 'none';

	return (
		<div className={ `qsa-base-type ${ isSelected ? 'is-selected' : '' } ${ isLast ? 'is-last' : '' }` }>
			{ /* Base Type Header */ }
			<div className="qsa-base-type-row">
				<button
					className="qsa-expand-btn"
					onClick={ onToggleExpand }
					aria-expanded={ isExpanded }
					aria-label={ isExpanded ? __( 'Collapse', 'qsa-engraving' ) : __( 'Expand', 'qsa-engraving' ) }
				>
					<span className={ `dashicons ${ isExpanded ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-right-alt2' }` }></span>
				</button>

				<button
					className={ `qsa-checkbox qsa-checkbox--${ selectionState }` }
					onClick={ onToggleSelect }
					aria-checked={ selectionState === 'all' }
					aria-label={ __( 'Select all modules in this base type', 'qsa-engraving' ) }
				>
					<CheckboxIcon state={ selectionState } />
				</button>

				<div className="qsa-base-type-info" onClick={ onToggleExpand }>
					<span className="qsa-base-type-code">{ baseType }</span>
					<span className="qsa-base-type-name">{ baseTypeName }</span>
				</div>

				<div className="qsa-base-type-stats">
					<span className="qsa-stat-orders">
						{ orderCount } { orderCount === 1 ? __( 'order', 'qsa-engraving' ) : __( 'orders', 'qsa-engraving' ) }
					</span>
					<span className="qsa-stat-units">
						{ totalQty } { __( 'units', 'qsa-engraving' ) }
					</span>
				</div>
			</div>

			{ /* Orders (when expanded) */ }
			{ isExpanded && (
				<div className="qsa-orders">
					{ data.modules.map( ( order ) => (
						<OrderRow
							key={ order.order_id }
							baseType={ baseType }
							order={ order }
							isExpanded={ expandedOrders.has( order.order_id ) }
							selectionState={ getOrderSelectionState( baseType, order.order_id ) }
							selectedModules={ selectedModules }
							onToggleExpand={ () => onToggleOrderExpand( order.order_id ) }
							onToggleSelect={ () => onToggleOrderSelect( order.order_id ) }
							onToggleModuleSelect={ onToggleModuleSelect }
							getEngraveQty={ getEngraveQty }
							updateEngraveQty={ updateEngraveQty }
						/>
					) ) }
				</div>
			) }
		</div>
	);
}
