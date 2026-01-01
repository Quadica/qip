/**
 * CheckboxIcon - Tri-state Checkbox Icon
 *
 * Displays different icons based on selection state.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

/**
 * CheckboxIcon component.
 *
 * @param {Object} props       Component props.
 * @param {string} props.state Selection state: 'none', 'partial', 'all'.
 * @return {JSX.Element} The component.
 */
export default function CheckboxIcon( { state } ) {
	if ( state === 'all' ) {
		return <span className="dashicons dashicons-yes-alt"></span>;
	}
	if ( state === 'partial' ) {
		return <span className="dashicons dashicons-minus"></span>;
	}
	return <span className="dashicons dashicons-marker qsa-checkbox-empty"></span>;
}
