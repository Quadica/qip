/**
 * ActionBar - Action Buttons Bar
 *
 * Displays action buttons when modules are selected including
 * Preview (to see LED transitions) and Create Batch.
 * Matches the mockup design with appropriate icons.
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
 * @param {Function} props.onPreview     Preview batch callback.
 * @param {Function} props.onCreateBatch Create batch callback.
 * @param {boolean}  props.creating      Whether batch is being created.
 * @param {boolean}  props.previewing    Whether preview is loading.
 * @param {Function} props.onRefresh     Refresh callback.
 * @return {JSX.Element} The component.
 */
export default function ActionBar( {
	hasSelection,
	moduleCount,
	unitCount,
	onClear,
	onPreview,
	onCreateBatch,
	creating,
	previewing,
	onRefresh,
} ) {
	if ( hasSelection ) {
		return (
			<div className="qsa-action-bar qsa-action-bar--selected">
				<div className="qsa-action-info">
					<span className="qsa-action-text">
						{ moduleCount } { moduleCount === 1 ? __( 'module', 'qsa-engraving' ) : __( 'modules', 'qsa-engraving' ) }
						{ ' ' }{ __( 'selected', 'qsa-engraving' ) }
					</span>
					<span className="qsa-action-text-muted">
						({ unitCount } { __( 'units', 'qsa-engraving' ) })
					</span>
				</div>
				<div className="qsa-action-buttons">
					<button
						className="qsa-btn-clear"
						onClick={ onClear }
						disabled={ creating || previewing }
					>
						{ __( 'Clear Selection', 'qsa-engraving' ) }
					</button>
					<button
						className="qsa-btn-preview"
						onClick={ onPreview }
						disabled={ creating || previewing }
						title={ __( 'Preview LED transitions and array breakdown', 'qsa-engraving' ) }
					>
						{ previewing ? (
							<>
								<span className="spinner is-active"></span>
								{ __( 'Previewing...', 'qsa-engraving' ) }
							</>
						) : (
							<>
								<span className="dashicons dashicons-visibility"></span>
								{ __( 'Preview', 'qsa-engraving' ) }
							</>
						) }
					</button>
					<button
						className="qsa-btn-create"
						onClick={ onCreateBatch }
						disabled={ creating || previewing }
					>
						{ creating ? (
							<>
								<span className="spinner is-active"></span>
								{ __( 'Creating...', 'qsa-engraving' ) }
							</>
						) : (
							<>
								<span className="dashicons dashicons-superhero-alt"></span>
								{ __( 'Start Engraving', 'qsa-engraving' ) }
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
				<span className="dashicons dashicons-editor-ul qsa-action-icon"></span>
				<span className="qsa-action-text">
					{ __( 'Modules Awaiting Engraving', 'qsa-engraving' ) }
				</span>
			</div>
			<button
				className="qsa-btn-refresh"
				onClick={ onRefresh }
				title={ __( 'Refresh module list', 'qsa-engraving' ) }
			>
				<span className="dashicons dashicons-update"></span>
			</button>
		</div>
	);
}
