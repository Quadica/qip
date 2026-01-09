<?php
/**
 * LED Code Resolver Service.
 *
 * Retrieves LED shortcodes for modules from Order BOM and WooCommerce products.
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
 * Service for resolving LED codes for modules.
 *
 * Queries the Order BOM CPT to find LED SKUs associated with a module,
 * then retrieves the 3-character LED shortcode from the product's
 * led_shortcode_3 field.
 *
 * @since 1.0.0
 */
class LED_Code_Resolver {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Cache for LED codes keyed by order_id-module_sku.
	 *
	 * @var array
	 */
	private array $cache = array();

	/**
	 * Cache for LED shortcodes keyed by product ID.
	 *
	 * @var array
	 */
	private array $product_cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Get LED codes for a module in an order.
	 *
	 * @param int    $order_id   The WooCommerce order ID.
	 * @param string $module_sku The module SKU.
	 * @return array|WP_Error Array of 3-character LED codes or WP_Error.
	 */
	public function get_led_codes_for_module( int $order_id, string $module_sku ): array|WP_Error {
		$cache_key = "{$order_id}-{$module_sku}";

		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		// Find the Order BOM post for this order/module.
		$bom_post = $this->find_order_bom_post( $order_id, $module_sku );

		if ( is_wp_error( $bom_post ) ) {
			return $bom_post;
		}

		if ( null === $bom_post ) {
			return new WP_Error(
				'bom_not_found',
				sprintf(
					/* translators: 1: Order ID, 2: Module SKU */
					__( 'No Order BOM record found for order #%1$d, module %2$s. FIX: Create an Order BOM entry linking this order to the module, or verify the order ID and module SKU are correct.', 'qsa-engraving' ),
					$order_id,
					$module_sku
				)
			);
		}

		// Get LED SKUs from the BOM.
		$led_data = $this->get_led_data_from_bom( $bom_post );

		if ( empty( $led_data ) ) {
			return new WP_Error(
				'led_data_missing',
				sprintf(
					/* translators: 1: Order ID, 2: Module SKU */
					__( 'Order BOM exists for order #%1$d, module %2$s, but contains no LED component data. FIX: Edit the Order BOM record and add LED SKUs to the LED components field.', 'qsa-engraving' ),
					$order_id,
					$module_sku
				)
			);
		}

		// Resolve LED shortcodes for each LED.
		$led_codes = array();
		foreach ( $led_data as $led ) {
			$shortcode = $this->get_led_shortcode( $led['sku'] );
			if ( ! empty( $shortcode ) ) {
				// Ensure shortcode is always a string for consistent array key handling.
				$led_codes[] = (string) $shortcode;
			}
		}

		// Deduplicate LED codes (multiple LEDs of same type shouldn't inflate signatures).
		$led_codes = array_values( array_unique( $led_codes ) );

		if ( empty( $led_codes ) ) {
			// BOM has LED SKUs but none resolved to shortcodes.
			$led_skus_list = implode( ', ', array_column( $led_data, 'sku' ) );

			return new WP_Error(
				'led_shortcodes_missing',
				sprintf(
					/* translators: 1: Order ID, 2: Module SKU, 3: List of LED SKUs */
					__( 'LED products found for order #%1$d, module %2$s (SKUs: %3$s), but none have the "led_shortcode_3" field set. FIX: Edit each LED product and add a 3-character shortcode (e.g., "K7P") to the led_shortcode_3 custom field.', 'qsa-engraving' ),
					$order_id,
					$module_sku,
					$led_skus_list
				)
			);
		}

		$this->cache[ $cache_key ] = $led_codes;
		return $led_codes;
	}

	/**
	 * Find the Order BOM post for an order/module combination.
	 *
	 * Order BOM is stored as a CPT (quad_order_bom) with metadata linking it to orders.
	 * The module SKU is stored in the 'sku' meta field (not 'module_sku').
	 *
	 * @param int    $order_id   The order ID.
	 * @param string $module_sku The module SKU (assembly_sku from oms_batch_items).
	 * @return int|null|WP_Error Post ID, null if not found, or WP_Error.
	 */
	private function find_order_bom_post( int $order_id, string $module_sku ): int|null|WP_Error {
		// Query for quad_order_bom posts matching this order and module.
		// The 'sku' field stores the assembly_sku (e.g., "STAR-29654").
		$args = array(
			'post_type'      => 'quad_order_bom',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'order_id',
					'value'   => $order_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => 'sku',
					'value'   => $module_sku,
					'compare' => '=',
				),
			),
			'fields'         => 'ids',
		);

		$posts = get_posts( $args );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Get LED data from an Order BOM post.
	 *
	 * The Order BOM stores LED data in an ACF repeater field 'leds_and_positions'
	 * with subfields: led_sku, position, description.
	 *
	 * @param int $post_id The BOM post ID.
	 * @return array Array of LED data with 'sku' and 'position' keys.
	 */
	private function get_led_data_from_bom( int $post_id ): array {
		$led_data = array();

		// Try ACF repeater field 'leds_and_positions' first.
		if ( function_exists( 'get_field' ) ) {
			$leds = get_field( 'leds_and_positions', $post_id );
			if ( is_array( $leds ) ) {
				foreach ( $leds as $index => $led ) {
					$led_data[] = array(
						'sku'      => $led['led_sku'] ?? '',
						'position' => $led['position'] ?? ( $index + 1 ),
					);
				}
				return array_filter( $led_data, fn( $l ) => ! empty( $l['sku'] ) );
			}
		}

		// Fallback: Try reading ACF repeater data from meta directly.
		// ACF stores repeater count in 'leds_and_positions' and rows as
		// 'leds_and_positions_0_led_sku', 'leds_and_positions_0_position', etc.
		$count = get_post_meta( $post_id, 'leds_and_positions', true );
		if ( is_numeric( $count ) && $count > 0 ) {
			for ( $i = 0; $i < (int) $count; $i++ ) {
				$sku = get_post_meta( $post_id, "leds_and_positions_{$i}_led_sku", true );
				$position = get_post_meta( $post_id, "leds_and_positions_{$i}_position", true );
				if ( ! empty( $sku ) ) {
					$led_data[] = array(
						'sku'      => $sku,
						'position' => $position ?: ( $i + 1 ),
					);
				}
			}
			if ( ! empty( $led_data ) ) {
				return $led_data;
			}
		}

		// Legacy fallback: Try 'leds' field name.
		if ( function_exists( 'get_field' ) ) {
			$leds = get_field( 'leds', $post_id );
			if ( is_array( $leds ) ) {
				foreach ( $leds as $index => $led ) {
					$led_data[] = array(
						'sku'      => $led['led_sku'] ?? $led['sku'] ?? '',
						'position' => $led['position'] ?? ( $index + 1 ),
					);
				}
				return array_filter( $led_data, fn( $l ) => ! empty( $l['sku'] ) );
			}
		}

		return $led_data;
	}

	/**
	 * Get the LED shortcode for a LED product.
	 *
	 * @param string $led_sku The LED product SKU.
	 * @return string The 3-character LED shortcode or empty string.
	 */
	public function get_led_shortcode( string $led_sku ): string {
		if ( empty( $led_sku ) ) {
			return '';
		}

		// Check product cache.
		if ( isset( $this->product_cache[ $led_sku ] ) ) {
			return $this->product_cache[ $led_sku ];
		}

		// Find product by SKU.
		$product_id = wc_get_product_id_by_sku( $led_sku );

		if ( ! $product_id ) {
			$this->product_cache[ $led_sku ] = '';
			return '';
		}

		// Get the led_shortcode_3 field.
		$shortcode = '';

		// Try ACF first.
		if ( function_exists( 'get_field' ) ) {
			$shortcode = get_field( 'led_shortcode_3', $product_id );
		}

		// Fallback to post meta.
		if ( empty( $shortcode ) ) {
			$shortcode = get_post_meta( $product_id, 'led_shortcode_3', true );
		}

		// Also check with underscore prefix.
		if ( empty( $shortcode ) ) {
			$shortcode = get_post_meta( $product_id, '_led_shortcode_3', true );
		}

		$shortcode = is_string( $shortcode ) ? trim( $shortcode ) : '';

		$this->product_cache[ $led_sku ] = $shortcode;
		return $shortcode;
	}

	/**
	 * Get LED codes for a module with position information preserved.
	 *
	 * Unlike get_led_codes_for_module(), this method does NOT deduplicate
	 * the LED codes. Each position in the Order BOM returns its LED shortcode,
	 * even if multiple positions have the same LED type.
	 *
	 * This is used for SVG rendering where we need to render LED codes
	 * at each physical position on the module.
	 *
	 * @param int    $order_id   The WooCommerce order ID.
	 * @param string $module_sku The module SKU.
	 * @return array|WP_Error Array of LED codes indexed by position (1-based), or WP_Error.
	 */
	public function get_led_codes_by_position( int $order_id, string $module_sku ): array|WP_Error {
		$cache_key = "{$order_id}-{$module_sku}-by-position";

		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		// Find the Order BOM post for this order/module.
		$bom_post = $this->find_order_bom_post( $order_id, $module_sku );

		if ( is_wp_error( $bom_post ) ) {
			return $bom_post;
		}

		if ( null === $bom_post ) {
			return new WP_Error(
				'bom_not_found',
				sprintf(
					/* translators: 1: Order ID, 2: Module SKU */
					__( 'No Order BOM record found for order #%1$d, module %2$s. FIX: Create an Order BOM entry linking this order to the module, or verify the order ID and module SKU are correct.', 'qsa-engraving' ),
					$order_id,
					$module_sku
				)
			);
		}

		// Get LED SKUs from the BOM with position information.
		$led_data = $this->get_led_data_from_bom( $bom_post );

		if ( empty( $led_data ) ) {
			return new WP_Error(
				'led_data_missing',
				sprintf(
					/* translators: 1: Order ID, 2: Module SKU */
					__( 'Order BOM exists for order #%1$d, module %2$s, but contains no LED component data. FIX: Edit the Order BOM record and add LED SKUs to the LED components field.', 'qsa-engraving' ),
					$order_id,
					$module_sku
				)
			);
		}

		// Resolve LED shortcodes for each position - NO deduplication.
		// Build array indexed by position for SVG rendering.
		$led_codes_by_position = array();
		$missing_shortcodes    = array();

		foreach ( $led_data as $led ) {
			$position  = (int) $led['position'];
			$shortcode = $this->get_led_shortcode( $led['sku'] );

			if ( ! empty( $shortcode ) ) {
				$led_codes_by_position[ $position ] = (string) $shortcode;
			} else {
				$missing_shortcodes[] = $led['sku'];
			}
		}

		if ( empty( $led_codes_by_position ) ) {
			// BOM has LED SKUs but none resolved to shortcodes.
			$led_skus_list = implode( ', ', array_unique( $missing_shortcodes ) );

			return new WP_Error(
				'led_shortcodes_missing',
				sprintf(
					/* translators: 1: Order ID, 2: Module SKU, 3: List of LED SKUs */
					__( 'LED products found for order #%1$d, module %2$s (SKUs: %3$s), but none have the "led_shortcode_3" field set. FIX: Edit each LED product and add a 3-character shortcode (e.g., "K7P") to the led_shortcode_3 custom field.', 'qsa-engraving' ),
					$order_id,
					$module_sku,
					$led_skus_list
				)
			);
		}

		// Sort by position to ensure consistent ordering.
		// Keep position keys (1, 2, 3, 4) - do NOT convert to sequential array.
		// The SVG renderer needs actual position numbers for led_code_1, led_code_4, etc.
		ksort( $led_codes_by_position );

		$this->cache[ $cache_key ] = $led_codes_by_position;
		return $led_codes_by_position;
	}

	/**
	 * Validate that a LED shortcode is properly formatted.
	 *
	 * @param string $shortcode The LED shortcode to validate.
	 * @return bool True if valid 3-character alphanumeric code.
	 */
	public static function is_valid_shortcode( string $shortcode ): bool {
		// Must be exactly 3 characters, alphanumeric.
		return (bool) preg_match( '/^[A-Z0-9]{3}$/i', $shortcode );
	}

	/**
	 * Clear all caches.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cache         = array();
		$this->product_cache = array();
	}
}
