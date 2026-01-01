/**
 * Stats Bar Component
 *
 * Displays queue progress statistics.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Stats Bar component.
 *
 * @param {Object} props          Component props.
 * @param {Object} props.stats    Queue statistics.
 * @param {Object} props.capacity Serial capacity info.
 * @return {JSX.Element} The component.
 */
export default function StatsBar( { stats, capacity } ) {
	const progressPercent = stats.totalItems > 0
		? Math.round( ( stats.completedItems / stats.totalItems ) * 100 )
		: 0;

	return (
		<div className="qsa-queue-stats">
			<div className="qsa-stats-grid">
				<div className="qsa-stat-item">
					<div className="qsa-stat-label">{ __( 'Queue Items', 'qsa-engraving' ) }</div>
					<div className="qsa-stat-value">{ stats.totalItems }</div>
				</div>

				<div className="qsa-stat-item">
					<div className="qsa-stat-label">{ __( 'Completed', 'qsa-engraving' ) }</div>
					<div className="qsa-stat-value">
						<span className="qsa-stat-completed">{ stats.completedItems }</span>
						<span className="qsa-stat-separator">/</span>
						<span className="qsa-stat-total">{ stats.totalItems }</span>
					</div>
				</div>

				<div className="qsa-stat-item">
					<div className="qsa-stat-label">{ __( 'Modules', 'qsa-engraving' ) }</div>
					<div className="qsa-stat-value">
						<span className="qsa-stat-completed">{ stats.completedModules }</span>
						<span className="qsa-stat-separator">/</span>
						<span className="qsa-stat-total">{ stats.totalModules }</span>
					</div>
				</div>

				<div className="qsa-stat-item">
					<div className="qsa-stat-label">{ __( 'Progress', 'qsa-engraving' ) }</div>
					<div className="qsa-stat-value qsa-stat-progress">{ progressPercent }%</div>
				</div>
			</div>

			<div className="qsa-progress-bar-container">
				<div
					className="qsa-progress-bar"
					style={ { width: `${ progressPercent }%` } }
				></div>
			</div>

			{ capacity && capacity.warning && (
				<div className={ `qsa-capacity-warning ${ capacity.critical ? 'critical' : '' }` }>
					<span className="dashicons dashicons-warning"></span>
					<span>
						{ capacity.critical
							? __( 'Critical: Serial capacity critically low!', 'qsa-engraving' )
							: __( 'Warning: Serial capacity running low.', 'qsa-engraving' ) }
						{ ' ' }
						{ capacity.remaining.toLocaleString() } { __( 'remaining', 'qsa-engraving' ) }
					</span>
				</div>
			) }
		</div>
	);
}
