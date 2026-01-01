/**
 * ActionBar - Action Buttons Bar
 *
 * Displays action buttons when modules are selected or refresh button otherwise.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * ActionBar component.
 *
 * @param {Object}   props               Component props.
 * @param {boolean}  props.hasSelection  Whether modules are selected.
 * @param {number}   props.moduleCount   Number of selected modules.
 * @param {number}   props.unitCount     Total units selected.
 * @param {Function} props.onClear       Clear selection callback.
 * @param {Function} props.onCreateBatch Create batch callback.
 * @param {boolean}  props.creating      Whether batch is being created.
 * @param {Function} props.onRefresh     Refresh callback.
 * @return {JSX.Element} The component.
 */
export default function ActionBar( {
	hasSelection,
	moduleCount,
	unitCount,
	onClear,
	onCreateBatch,
	creating,
	onRefresh,
} ) {
	if ( hasSelection ) {
		return (
			<div className="qsa-action-bar qsa-action-bar--selected">
				<div className="qsa-action-info">
					<span className="dashicons dashicons-yes-alt qsa-action-icon"></span>
					<span className="qsa-action-text">
						{ moduleCount } { moduleCount === 1 ? __( 'module', 'qsa-engraving' ) : __( 'modules', 'qsa-engraving' ) }
						{ ' ' }{ __( 'ready for engraving', 'qsa-engraving' ) }
						{ ' ' }({ unitCount } { __( 'units', 'qsa-engraving' ) })
					</span>
				</div>
				<div className="qsa-action-buttons">
					<button
						className="button qsa-btn-clear"
						onClick={ onClear }
						disabled={ creating }
					>
						{ __( 'Clear Selection', 'qsa-engraving' ) }
					</button>
					<button
						className="button button-primary qsa-btn-create"
						onClick={ onCreateBatch }
						disabled={ creating }
					>
						{ creating ? (
							<>
								<span className="spinner is-active"></span>
								{ __( 'Creating...', 'qsa-engraving' ) }
							</>
						) : (
							<>
								{ __( 'Start Engraving', 'qsa-engraving' ) }
								<span className="dashicons dashicons-arrow-right-alt"></span>
							</>
						) }
					</button>
				</div>
			</div>
		);
	}

	return (
		<div className="qsa-action-bar">
			<div className="qsa-action-info">
				<span className="dashicons dashicons-list-view qsa-action-icon"></span>
				<span className="qsa-action-text">
					{ __( 'Modules Awaiting Engraving', 'qsa-engraving' ) }
				</span>
			</div>
			<button
				className="button qsa-btn-refresh"
				onClick={ onRefresh }
				title={ __( 'Refresh module list', 'qsa-engraving' ) }
			>
				<span className="dashicons dashicons-update"></span>
			</button>
		</div>
	);
}
