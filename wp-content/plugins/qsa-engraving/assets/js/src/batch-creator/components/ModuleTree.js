/**
 * ModuleTree - Hierarchical Module Display
 *
 * Displays modules in a tree structure: Base Type > Order > Module.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import BaseTypeRow from './BaseTypeRow';

/**
 * ModuleTree component.
 *
 * @param {Object}   props                         Component props.
 * @param {Object}   props.moduleData              The module data grouped by base type.
 * @param {Object}   props.baseTypeNames           Display names for base types.
 * @param {Set}      props.selectedModules         Set of selected module IDs.
 * @param {Set}      props.expandedBaseTypes       Set of expanded base types.
 * @param {Set}      props.expandedOrders          Set of expanded order IDs.
 * @param {Function} props.getBaseTypeSelectionState Get selection state for base type.
 * @param {Function} props.getOrderSelectionState  Get selection state for order.
 * @param {Function} props.toggleBaseTypeExpansion Toggle base type expansion.
 * @param {Function} props.toggleOrderExpansion    Toggle order expansion.
 * @param {Function} props.toggleBaseTypeSelection Toggle base type selection.
 * @param {Function} props.toggleOrderSelection    Toggle order selection.
 * @param {Function} props.toggleModuleSelection   Toggle module selection.
 * @param {Function} props.getEngraveQty           Get engrave quantity.
 * @param {Function} props.updateEngraveQty        Update engrave quantity.
 * @return {JSX.Element} The component.
 */
export default function ModuleTree( {
	moduleData,
	baseTypeNames,
	selectedModules,
	expandedBaseTypes,
	expandedOrders,
	getBaseTypeSelectionState,
	getOrderSelectionState,
	toggleBaseTypeExpansion,
	toggleOrderExpansion,
	toggleBaseTypeSelection,
	toggleOrderSelection,
	toggleModuleSelection,
	getEngraveQty,
	updateEngraveQty,
} ) {
	const baseTypes = Object.keys( moduleData );

	return (
		<div className="qsa-module-tree">
			<div className="qsa-tree-header">
				<span className="qsa-tree-header-text">
					{ __( 'Modules Awaiting Engraving', 'qsa-engraving' ) }
				</span>
			</div>
			<div className="qsa-tree-body">
				{ baseTypes.map( ( baseType, index ) => (
					<BaseTypeRow
						key={ baseType }
						baseType={ baseType }
						baseTypeName={ baseTypeNames[ baseType ] || baseType }
						data={ moduleData[ baseType ] }
						isExpanded={ expandedBaseTypes.has( baseType ) }
						selectionState={ getBaseTypeSelectionState( baseType ) }
						expandedOrders={ expandedOrders }
						selectedModules={ selectedModules }
						getOrderSelectionState={ getOrderSelectionState }
						onToggleExpand={ () => toggleBaseTypeExpansion( baseType ) }
						onToggleSelect={ () => toggleBaseTypeSelection( baseType ) }
						onToggleOrderExpand={ toggleOrderExpansion }
						onToggleOrderSelect={ ( orderId ) => toggleOrderSelection( baseType, orderId ) }
						onToggleModuleSelect={ toggleModuleSelection }
						getEngraveQty={ getEngraveQty }
						updateEngraveQty={ updateEngraveQty }
						isLast={ index === baseTypes.length - 1 }
					/>
				) ) }
			</div>
		</div>
	);
}
