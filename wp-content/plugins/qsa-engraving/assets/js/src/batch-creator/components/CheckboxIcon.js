/**
 * CheckboxIcon - Tri-state Checkbox Icon
 *
 * Displays different icons based on selection state.
 * Matches the mockup design with check and minus icons.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

/**
 * CheckboxIcon component.
 *
 * @param {Object} props       Component props.
 * @param {string} props.state Selection state: 'none', 'partial', 'all'.
 * @return {JSX.Element|null} The component or null.
 */
export default function CheckboxIcon( { state } ) {
	if ( state === 'all' ) {
		return <span className="dashicons dashicons-yes"></span>;
	}
	if ( state === 'partial' ) {
		return <span className="dashicons dashicons-minus"></span>;
	}
	// Return null for 'none' state - the checkbox border is shown via CSS
	return null;
}
