<?php
/**
 * Micro-ID Decoder AJAX Handler.
 *
 * Handles AJAX requests for serial number lookups from the Micro-ID decoder pages.
 *
 * @package QSA_Engraving
 * @since 1.1.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Ajax;

use Quadica\QSA_Engraving\Database\Serial_Repository;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler for Micro-ID serial lookup operations.
 *
 * Provides endpoints for:
 * - Public serial lookup (basic info)
 * - Staff-only full details retrieval
 *
 * @since 1.1.0
 */
class MicroID_Decoder_Ajax_Handler {

	/**
	 * AJAX nonce action for decode requests.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'qsa_microid_decode';

	/**
	 * Required capability for full details.
	 *
	 * @var string
	 */
	public const STAFF_CAPABILITY = 'manage_woocommerce';

	/**
	 * Maximum image size for uploads (10 MB).
	 * Used by landing page for client-side validation.
	 *
	 * @var int
	 */
	public const MAX_IMAGE_SIZE = 10 * 1024 * 1024;

	/**
	 * Minimum image dimension (120px).
	 * Used by landing page for client-side validation.
	 *
	 * @var int
	 */
	public const MIN_IMAGE_DIMENSION = 120;

	/**
	 * Allowed MIME types for image uploads.
	 * Used by landing page for client-side validation.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_MIME_TYPES = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * Serial Repository instance.
	 *
	 * @var Serial_Repository
	 */
	private Serial_Repository $serial_repository;

	/**
	 * Constructor.
	 *
	 * @param Serial_Repository $serial_repository Serial repository instance.
	 */
	public function __construct( Serial_Repository $serial_repository ) {
		$this->serial_repository = $serial_repository;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Public serial lookup endpoint (for manual decoder).
		add_action( 'wp_ajax_nopriv_qsa_microid_serial_lookup', array( $this, 'handle_serial_lookup' ) );
		add_action( 'wp_ajax_qsa_microid_serial_lookup', array( $this, 'handle_serial_lookup' ) );

		// Staff-only full details endpoint.
		add_action( 'wp_ajax_qsa_microid_full_details', array( $this, 'handle_full_details' ) );
	}

	/**
	 * Handle serial lookup request (for manual decoder).
	 *
	 * Public endpoint that looks up a serial number and returns basic info.
	 * Used by the /id page when accessed with ?serial=XXXXXXXX parameter.
	 *
	 * @return void
	 */
	public function handle_serial_lookup(): void {
		// Verify nonce.
		$nonce = $this->get_request_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->send_error( __( 'Security check failed. Please refresh the page and try again.', 'qsa-engraving' ), 'invalid_nonce', 403 );
			return;
		}

		// Get serial from request.
		$serial = $this->get_request_param( 'serial' );

		// Validate serial format.
		if ( empty( $serial ) || ! Serial_Repository::is_valid_format( $serial ) ) {
			$this->send_error( __( 'Invalid serial number format.', 'qsa-engraving' ), 'invalid_serial' );
			return;
		}

		// Get basic serial info.
		$serial_info = $this->get_basic_serial_info( $serial );

		// Get current user staff status for determining if we should show full details link.
		$is_staff = current_user_can( self::STAFF_CAPABILITY );

		$this->send_success(
			array(
				'serial'   => $serial,
				'source'   => 'manual',
				'product'  => $serial_info,
				'is_staff' => $is_staff,
			),
			__( 'Serial lookup completed.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle full details request (staff only).
	 *
	 * Returns complete traceability information for a decoded serial.
	 *
	 * @return void
	 */
	public function handle_full_details(): void {
		// Verify user is logged in and has staff capability.
		if ( ! is_user_logged_in() ) {
			$this->send_error( __( 'Please log in to view full details.', 'qsa-engraving' ), 'not_logged_in', 401 );
			return;
		}

		if ( ! current_user_can( self::STAFF_CAPABILITY ) ) {
			$this->send_error( __( 'You do not have permission to view full details.', 'qsa-engraving' ), 'insufficient_permissions', 403 );
			return;
		}

		// Verify nonce.
		$nonce = $this->get_request_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->send_error( __( 'Security check failed.', 'qsa-engraving' ), 'invalid_nonce', 403 );
			return;
		}

		// Get serial from request.
		$serial = $this->get_request_param( 'serial' );
		if ( empty( $serial ) || ! Serial_Repository::is_valid_format( $serial ) ) {
			$this->send_error( __( 'Invalid serial number format.', 'qsa-engraving' ), 'invalid_serial' );
			return;
		}

		// Get full serial info.
		$full_info = $this->get_full_serial_info( $serial );

		if ( null === $full_info ) {
			$this->send_error( __( 'Serial number not found in system.', 'qsa-engraving' ), 'serial_not_found', 404 );
			return;
		}

		$this->send_success( $full_info, __( 'Full details retrieved.', 'qsa-engraving' ) );
	}

	/**
	 * Get basic serial info (public data).
	 *
	 * @param string $serial The serial number.
	 * @return array{found: bool, sku: ?string, product_name: ?string, engraved_at: ?string}
	 */
	private function get_basic_serial_info( string $serial ): array {
		$result = array(
			'found'        => false,
			'sku'          => null,
			'product_name' => null,
			'engraved_at'  => null,
		);

		$serial_record = $this->serial_repository->get_by_serial_number( $serial );

		if ( ! $serial_record ) {
			return $result;
		}

		$result['found'] = true;
		$result['sku']   = $serial_record['module_sku'] ?? null;

		// Get product name from SKU.
		if ( ! empty( $result['sku'] ) ) {
			$product_id = wc_get_product_id_by_sku( $result['sku'] );
			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$result['product_name'] = $product->get_name();
				}
			}
		}

		// Format engraved date.
		if ( ! empty( $serial_record['engraved_at'] ) ) {
			$result['engraved_at'] = wp_date( get_option( 'date_format' ), strtotime( $serial_record['engraved_at'] ) );
		}

		return $result;
	}

	/**
	 * Get full serial info (staff only).
	 *
	 * @param string $serial The serial number.
	 * @return array|null Full serial details or null if not found.
	 */
	private function get_full_serial_info( string $serial ): ?array {
		$serial_record = $this->serial_repository->get_by_serial_number( $serial );

		if ( ! $serial_record ) {
			return null;
		}

		$result = array(
			'serial_number'      => $serial,
			'status'             => $serial_record['status'],
			'sku'                => $serial_record['module_sku'] ?? null,
			'product_name'       => null,
			'product_url'        => null,
			'order_id'           => $serial_record['order_id'] ?? null,
			'order_url'          => null,
			'customer_name'      => null,
			'engraving_batch_id' => $serial_record['engraving_batch_id'] ?? null,
			'qsa_sequence'       => $serial_record['qsa_sequence'] ?? null,
			'array_position'     => $serial_record['array_position'] ?? null,
			'engraved_at'        => null,
			'created_at'         => null,
		);

		// Get product details.
		if ( ! empty( $result['sku'] ) ) {
			$product_id = wc_get_product_id_by_sku( $result['sku'] );
			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$result['product_name'] = $product->get_name();
					$result['product_url']  = get_edit_post_link( $product_id, 'raw' );
				}
			}
		}

		// Get order details.
		if ( ! empty( $result['order_id'] ) ) {
			$order = wc_get_order( $result['order_id'] );
			if ( $order ) {
				$result['order_url']     = $order->get_edit_order_url();
				$result['customer_name'] = $order->get_formatted_billing_full_name();
			}
		}

		// Format dates.
		if ( ! empty( $serial_record['engraved_at'] ) ) {
			$result['engraved_at'] = wp_date( 'Y-m-d H:i:s', strtotime( $serial_record['engraved_at'] ) );
		}
		if ( ! empty( $serial_record['created_at'] ) ) {
			$result['created_at'] = wp_date( 'Y-m-d H:i:s', strtotime( $serial_record['created_at'] ) );
		}

		return $result;
	}

	/**
	 * Get request parameter from POST only.
	 *
	 * @param string $key Parameter key.
	 * @return string
	 */
	private function get_request_param( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $key ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}
		return '';
	}

	/**
	 * Send JSON success response.
	 *
	 * @param mixed  $data    Response data.
	 * @param string $message Optional message.
	 * @return void
	 */
	private function send_success( mixed $data = null, string $message = '' ): void {
		wp_send_json(
			array(
				'success' => true,
				'data'    => $data,
				'message' => $message,
			)
		);
	}

	/**
	 * Send JSON error response.
	 *
	 * @param string $message Error message.
	 * @param string $code    Error code.
	 * @param int    $status  HTTP status code.
	 * @return void
	 */
	private function send_error( string $message, string $code = 'error', int $status = 400 ): void {
		wp_send_json(
			array(
				'success' => false,
				'message' => $message,
				'code'    => $code,
			),
			$status
		);
	}

	/**
	 * Create a nonce for decode requests.
	 *
	 * @return string
	 */
	public static function create_nonce(): string {
		return wp_create_nonce( self::NONCE_ACTION );
	}
}
