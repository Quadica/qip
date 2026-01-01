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
	 * Whether to use fallback LED codes when BOM data is missing.
	 * This should be true for testing/staging and false for production.
	 *
	 * @var bool
	 */
	private bool $use_fallback = true;

	/**
	 * Default fallback LED code when BOM data is missing.
	 *
	 * @var string
	 */
	private const FALLBACK_LED_CODE = 'K7P';

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
			// No BOM found - use fallback if enabled, otherwise return error.
			if ( $this->use_fallback ) {
				// Log warning but return fallback code for testing.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'QSA Engraving: No BOM found for order %d, module %s. Using fallback LED code: %s',
							$order_id,
							$module_sku,
							self::FALLBACK_LED_CODE
						)
					);
				}
				$this->cache[ $cache_key ] = array( self::FALLBACK_LED_CODE );
				return array( self::FALLBACK_LED_CODE );
			}

			return new WP_Error(
				'bom_not_found',
				sprintf(
					/* translators: 1: Order ID, 2: Module SKU */
					__( 'No BOM found for order %1$d, module %2$s.', 'qsa-engraving' ),
					$order_id,
					$module_sku
				)
			);
		}

		// Get LED SKUs from the BOM.
		$led_data = $this->get_led_data_from_bom( $bom_post );

		if ( empty( $led_data ) ) {
			// BOM exists but no LED data - use fallback if enabled.
			if ( $this->use_fallback ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'QSA Engraving: BOM found but no LED data for order %d, module %s. Using fallback: %s',
							$order_id,
							$module_sku,
							self::FALLBACK_LED_CODE
						)
					);
				}
				$this->cache[ $cache_key ] = array( self::FALLBACK_LED_CODE );
				return array( self::FALLBACK_LED_CODE );
			}

			return new WP_Error(
				'led_data_missing',
				sprintf(
					/* translators: 1: Order ID, 2: Module SKU */
					__( 'BOM found for order %1$d, module %2$s, but no LED data present.', 'qsa-engraving' ),
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
				$led_codes[] = $shortcode;
			}
		}

		// Deduplicate LED codes (multiple LEDs of same type shouldn't inflate signatures).
		$led_codes = array_values( array_unique( $led_codes ) );

		if ( empty( $led_codes ) ) {
			// BOM has LED SKUs but none resolved to shortcodes.
			if ( $this->use_fallback ) {
				// Use fallback code for testing.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'QSA Engraving: LED SKUs found for order %d, module %s, but no shortcodes resolved. Using fallback: %s',
							$order_id,
							$module_sku,
							self::FALLBACK_LED_CODE
						)
					);
				}
				$this->cache[ $cache_key ] = array( self::FALLBACK_LED_CODE );
				return array( self::FALLBACK_LED_CODE );
			}

			return new WP_Error(
				'led_shortcodes_missing',
				sprintf(
					/* translators: 1: Order ID, 2: Module SKU */
					__( 'LED SKUs found for order %1$d, module %2$s, but no shortcodes resolved.', 'qsa-engraving' ),
					$order_id,
					$module_sku
				)
			);
		}

		$this->cache[ $cache_key ] = $led_codes;
		return $led_codes;
	}

	/**
	 * Find the Order BOM post for an order/module combination.
	 *
	 * Order BOM is stored as a CPT with metadata linking it to orders.
	 *
	 * @param int    $order_id   The order ID.
	 * @param string $module_sku The module SKU.
	 * @return int|null|WP_Error Post ID, null if not found, or WP_Error.
	 */
	private function find_order_bom_post( int $order_id, string $module_sku ): int|null|WP_Error {
		// Query for order_bom posts matching this order and module.
		$args = array(
			'post_type'      => 'order_bom',
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
					'key'     => 'module_sku',
					'value'   => $module_sku,
					'compare' => '=',
				),
			),
			'fields'         => 'ids',
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			// Try alternative meta key names.
			$args['meta_query'] = array(
				'relation' => 'AND',
				array(
					'key'     => '_order_id',
					'value'   => $order_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => '_module_sku',
					'value'   => $module_sku,
					'compare' => '=',
				),
			);

			$posts = get_posts( $args );
		}

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Get LED data from an Order BOM post.
	 *
	 * @param int $post_id The BOM post ID.
	 * @return array Array of LED data with 'sku' and 'position' keys.
	 */
	private function get_led_data_from_bom( int $post_id ): array {
		$led_data = array();

		// Try ACF repeater field first.
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

		// Fallback: Try post meta.
		$leds_meta = get_post_meta( $post_id, 'led_components', true );
		if ( is_array( $leds_meta ) ) {
			foreach ( $leds_meta as $index => $led ) {
				$led_data[] = array(
					'sku'      => $led['sku'] ?? '',
					'position' => $led['position'] ?? ( $index + 1 ),
				);
			}
			return array_filter( $led_data, fn( $l ) => ! empty( $l['sku'] ) );
		}

		// Try individual meta keys (led_1_sku, led_2_sku, etc.).
		for ( $i = 1; $i <= 9; $i++ ) {
			$sku = get_post_meta( $post_id, "led_{$i}_sku", true );
			if ( ! empty( $sku ) ) {
				$led_data[] = array(
					'sku'      => $sku,
					'position' => $i,
				);
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
