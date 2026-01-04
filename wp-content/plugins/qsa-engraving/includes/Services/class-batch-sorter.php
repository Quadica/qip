<?php
/**
 * Batch Sorter Service.
 *
 * Sorts modules to minimize LED type transitions during manual pick-and-place assembly.
 * Implements an optimization algorithm to group modules by their LED codes.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Services;

use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for sorting modules to optimize LED pick-and-place.
 *
 * The sorting algorithm minimizes the number of LED type transitions
 * during manual assembly by grouping modules with identical LED codes
 * together and placing modules with overlapping LED codes adjacently.
 *
 * @since 1.0.0
 */
class Batch_Sorter {

	/**
	 * Sort modules to minimize LED type transitions.
	 *
	 * Algorithm:
	 * 1. Group modules by their unique LED code sets
	 * 2. Build a graph of LED code overlaps between groups
	 * 3. Find an ordering that minimizes transitions using greedy approach
	 * 4. Flatten groups back into sorted module list
	 *
	 * @param array $modules Array of module data, each containing 'led_codes' array.
	 * @return array Sorted array of modules.
	 */
	public function sort_modules( array $modules ): array {
		if ( empty( $modules ) ) {
			return array();
		}

		// If only one module, return as-is.
		if ( count( $modules ) === 1 ) {
			return $modules;
		}

		// Group modules by their LED code signature.
		$groups = $this->group_by_led_codes( $modules );

		// If only one group, no optimization needed.
		if ( count( $groups ) === 1 ) {
			return $modules;
		}

		// Calculate overlap scores between groups.
		$overlap_matrix = $this->calculate_overlap_matrix( $groups );

		// Find optimal ordering using greedy approach.
		$ordered_keys = $this->find_optimal_order( array_keys( $groups ), $overlap_matrix );

		// Flatten groups into sorted module list.
		$sorted = array();
		foreach ( $ordered_keys as $key ) {
			foreach ( $groups[ $key ] as $module ) {
				$sorted[] = $module;
			}
		}

		return $sorted;
	}

	/**
	 * Group modules by their LED code signature.
	 *
	 * @param array $modules Array of modules.
	 * @return array Grouped modules keyed by LED code signature.
	 */
	private function group_by_led_codes( array $modules ): array {
		$groups = array();

		foreach ( $modules as $module ) {
			// Get LED codes and normalize them (sort for consistent signature).
			$led_codes = $module['led_codes'] ?? array();
			sort( $led_codes );
			$signature = implode( '|', $led_codes );

			if ( ! isset( $groups[ $signature ] ) ) {
				$groups[ $signature ] = array();
			}
			$groups[ $signature ][] = $module;
		}

		return $groups;
	}

	/**
	 * Calculate overlap matrix between LED code groups.
	 *
	 * @param array $groups Groups keyed by LED code signature.
	 * @return array Matrix of overlap scores [group_key => [group_key => score]].
	 */
	private function calculate_overlap_matrix( array $groups ): array {
		// PHP converts numeric string keys to integers, so we need to cast them back to strings.
		$keys   = array_map( 'strval', array_keys( $groups ) );
		$matrix = array();

		foreach ( $keys as $key1 ) {
			$matrix[ $key1 ] = array();
			$codes1          = empty( $key1 ) ? array() : explode( '|', (string) $key1 );

			foreach ( $keys as $key2 ) {
				if ( $key1 === $key2 ) {
					$matrix[ $key1 ][ $key2 ] = 0;
					continue;
				}

				$codes2  = empty( $key2 ) ? array() : explode( '|', (string) $key2 );
				$overlap = count( array_intersect( $codes1, $codes2 ) );

				// Score: higher = more overlap = better to be adjacent.
				$matrix[ $key1 ][ $key2 ] = $overlap;
			}
		}

		return $matrix;
	}

	/**
	 * Find optimal ordering using a greedy approach.
	 *
	 * Starting with the group with the most unique LED codes,
	 * repeatedly select the next group with the highest overlap.
	 *
	 * @param array $keys         Array of group keys.
	 * @param array $overlap_matrix Overlap scores between groups.
	 * @return array Ordered array of group keys.
	 */
	private function find_optimal_order( array $keys, array $overlap_matrix ): array {
		if ( count( $keys ) <= 1 ) {
			return $keys;
		}

		// Start with the group that has the most LED codes (larger groups tend to overlap more).
		$remaining = array_flip( $keys );
		$ordered   = array();

		// Pick starting group: the one with the most LED codes.
		$start_key   = null;
		$max_codes   = -1;
		foreach ( $keys as $key ) {
			$code_count = empty( $key ) ? 0 : count( explode( '|', $key ) );
			if ( $code_count > $max_codes ) {
				$max_codes = $code_count;
				$start_key = $key;
			}
		}

		$ordered[] = $start_key;
		unset( $remaining[ $start_key ] );

		// Greedy: always pick the next group with highest overlap to current.
		while ( ! empty( $remaining ) ) {
			$current     = end( $ordered );
			$best_key    = null;
			$best_score  = -1;

			foreach ( array_keys( $remaining ) as $candidate ) {
				$score = $overlap_matrix[ $current ][ $candidate ] ?? 0;
				if ( $score > $best_score ) {
					$best_score = $score;
					$best_key   = $candidate;
				}
			}

			// If no overlap found, just pick first remaining.
			if ( null === $best_key ) {
				$best_key = array_key_first( $remaining );
			}

			$ordered[] = $best_key;
			unset( $remaining[ $best_key ] );
		}

		return $ordered;
	}

	/**
	 * Calculate the number of LED bin switch events in a sorted list.
	 *
	 * Counts how many times you need to open a new LED bin when moving
	 * from one module to the next. This metric IS order-dependent and
	 * reflects sorting effectiveness - sorted lists with overlapping
	 * LED codes adjacent will have fewer switch events.
	 *
	 * For the first module, all its LED codes count as switch events
	 * (opening initial bins). For subsequent modules, only LED codes
	 * that weren't needed for the previous module count as switches.
	 *
	 * @param array $modules Sorted array of modules.
	 * @return int Number of LED bin switch events.
	 */
	public function count_transitions( array $modules ): int {
		if ( count( $modules ) <= 1 ) {
			return 0;
		}

		$previous_leds = array();
		$transitions   = 0;

		foreach ( $modules as $module ) {
			$led_codes    = $module['led_codes'] ?? array();
			$current_leds = array_flip( $led_codes );

			// Count how many LEDs in current module weren't in previous.
			foreach ( $led_codes as $code ) {
				if ( ! isset( $previous_leds[ $code ] ) ) {
					// Need to open a new bin = switch event.
					$transitions++;
				}
			}

			// Current becomes previous for next iteration.
			$previous_leds = $current_leds;
		}

		return $transitions;
	}

	/**
	 * Get the distinct LED codes from a list of modules.
	 *
	 * @param array $modules Array of modules.
	 * @return array Array of unique LED codes.
	 */
	public function get_distinct_led_codes( array $modules ): array {
		$codes = array();

		foreach ( $modules as $module ) {
			$led_codes = $module['led_codes'] ?? array();
			foreach ( $led_codes as $code ) {
				$codes[ $code ] = true;
			}
		}

		return array_keys( $codes );
	}

	/**
	 * Expand module selection into individual module instances.
	 *
	 * Takes module selections with quantities and expands them into
	 * individual module instances for sorting and batch creation.
	 *
	 * @param array $selections Array of selections with quantity.
	 * @return array Expanded array with one entry per module instance.
	 */
	public function expand_selections( array $selections ): array {
		$expanded = array();

		foreach ( $selections as $selection ) {
			$quantity = (int) ( $selection['quantity'] ?? 1 );

			for ( $i = 0; $i < $quantity; $i++ ) {
				$instance                     = $selection;
				$instance['instance_index']   = $i;
				$instance['quantity']         = 1; // Each instance represents one unit.
				$expanded[]                   = $instance;
			}
		}

		return $expanded;
	}

	/**
	 * Assign modules to QSA arrays.
	 *
	 * Divides sorted modules into QSA arrays of up to 8 modules each,
	 * accounting for the starting position on the first array.
	 *
	 * @param array $modules        Sorted array of modules.
	 * @param int   $start_position Starting position on first QSA (1-8).
	 * @return array Array of QSA arrays, each containing modules with positions.
	 */
	public function assign_to_arrays( array $modules, int $start_position = 1 ): array {
		// Validate start position.
		$start_position = max( 1, min( 8, $start_position ) );

		$qsa_arrays     = array();
		$current_qsa    = array();
		$current_pos    = $start_position;
		$qsa_sequence   = 1;
		$is_first_array = true;

		foreach ( $modules as $module ) {
			// Add position info to module.
			$module['qsa_sequence']   = $qsa_sequence;
			$module['array_position'] = $current_pos;
			$current_qsa[]            = $module;

			$current_pos++;

			// Check if QSA is full.
			if ( $current_pos > 8 ) {
				$qsa_arrays[] = $current_qsa;
				$current_qsa  = array();
				$current_pos  = 1; // Subsequent arrays always start at 1.
				$qsa_sequence++;
				$is_first_array = false;
			}
		}

		// Don't forget the last partial QSA.
		if ( ! empty( $current_qsa ) ) {
			$qsa_arrays[] = $current_qsa;
		}

		return $qsa_arrays;
	}

	/**
	 * Calculate array breakdown for display purposes.
	 *
	 * @param int $total_modules   Total number of modules.
	 * @param int $start_position  Starting position (1-8).
	 * @return array Array with 'array_count' and 'positions' breakdown.
	 */
	public function calculate_array_breakdown( int $total_modules, int $start_position = 1 ): array {
		if ( $total_modules <= 0 ) {
			return array(
				'array_count' => 0,
				'arrays'      => array(),
			);
		}

		$start_position    = max( 1, min( 8, $start_position ) );
		$first_array_slots = 9 - $start_position; // Positions available on first QSA.
		$arrays            = array();

		// First array.
		$first_array_count = min( $total_modules, $first_array_slots );
		$arrays[]          = array(
			'sequence'       => 1,
			'start_position' => $start_position,
			'end_position'   => $start_position + $first_array_count - 1,
			'module_count'   => $first_array_count,
		);

		$remaining = $total_modules - $first_array_count;
		$sequence  = 2;

		// Subsequent full arrays.
		while ( $remaining > 0 ) {
			$count    = min( $remaining, 8 );
			$arrays[] = array(
				'sequence'       => $sequence,
				'start_position' => 1,
				'end_position'   => $count,
				'module_count'   => $count,
			);
			$remaining -= $count;
			$sequence++;
		}

		return array(
			'array_count' => count( $arrays ),
			'arrays'      => $arrays,
		);
	}
}
