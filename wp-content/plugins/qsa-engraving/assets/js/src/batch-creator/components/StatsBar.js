/**
 * StatsBar - Statistics Display Bar
 *
 * Displays summary statistics for the batch creator including
 * LED transitions and QSA array breakdown from preview data.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * StatsBar component.
 *
 * @param {Object}      props               Component props.
 * @param {number}      props.baseTypeCount Number of base types.
 * @param {number}      props.selectedCount Number of selected modules.
 * @param {number}      props.unitCount     Total units selected.
 * @param {Object|null} props.previewData   Preview data from server (null if not previewed).
 * @return {JSX.Element} The component.
 */
export default function StatsBar( { baseTypeCount, selectedCount, unitCount, previewData } ) {
	return (
		<div className="qsa-stats-bar">
			<div className="qsa-stat">
				<span className="qsa-stat-value">{ baseTypeCount }</span>
				<span className="qsa-stat-label">{ __( 'Base Types', 'qsa-engraving' ) }</span>
			</div>
			<div className="qsa-stat">
				<span className="qsa-stat-value">{ selectedCount }</span>
				<span className="qsa-stat-label">{ __( 'Selected SKUs', 'qsa-engraving' ) }</span>
			</div>
			<div className="qsa-stat">
				<span className="qsa-stat-value">{ unitCount }</span>
				<span className="qsa-stat-label">{ __( 'Total Units', 'qsa-engraving' ) }</span>
			</div>

			{ /* Show preview stats when available */ }
			{ previewData && (
				<>
					<div className="qsa-stat qsa-stat-preview">
						<span className="qsa-stat-value">{ previewData.array_count || 0 }</span>
						<span className="qsa-stat-label">{ __( 'QSA Arrays', 'qsa-engraving' ) }</span>
					</div>
					<div className="qsa-stat qsa-stat-preview">
						<span className="qsa-stat-value">{ previewData.led_transitions || 0 }</span>
						<span className="qsa-stat-label">{ __( 'LED Transitions', 'qsa-engraving' ) }</span>
					</div>
					<div className="qsa-stat qsa-stat-preview">
						<span className="qsa-stat-value">{ previewData.distinct_leds?.length || 0 }</span>
						<span className="qsa-stat-label">{ __( 'Distinct LEDs', 'qsa-engraving' ) }</span>
					</div>
				</>
			) }
		</div>
	);
}
