<?php
/**
 * Micro-ID Decoder AJAX Handler.
 *
 * Handles AJAX requests for decoding Micro-ID codes from smartphone images.
 *
 * @package QSA_Engraving
 * @since 1.1.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Ajax;

use Quadica\QSA_Engraving\Database\Decode_Log_Repository;
use Quadica\QSA_Engraving\Database\Serial_Repository;
use Quadica\QSA_Engraving\Services\Claude_Vision_Client;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler for Micro-ID decoding operations.
 *
 * Provides endpoints for:
 * - Public image upload and decode (basic info)
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
	 * Rate limit: maximum requests per window.
	 *
	 * @var int
	 */
	public const RATE_LIMIT_MAX = 10;

	/**
	 * Rate limit: time window in seconds.
	 *
	 * @var int
	 */
	public const RATE_LIMIT_WINDOW = 60;

	/**
	 * Maximum image size in bytes (10 MB).
	 *
	 * @var int
	 */
	public const MAX_IMAGE_SIZE = 10 * 1024 * 1024;

	/**
	 * Minimum image dimension in pixels.
	 *
	 * @var int
	 */
	public const MIN_IMAGE_DIMENSION = 120;

	/**
	 * Allowed MIME types.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_MIME_TYPES = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * Required capability for full details.
	 *
	 * @var string
	 */
	public const STAFF_CAPABILITY = 'manage_woocommerce';

	/**
	 * Claude Vision Client instance.
	 *
	 * @var Claude_Vision_Client
	 */
	private Claude_Vision_Client $vision_client;

	/**
	 * Decode Log Repository instance.
	 *
	 * @var Decode_Log_Repository
	 */
	private Decode_Log_Repository $decode_log_repository;

	/**
	 * Serial Repository instance.
	 *
	 * @var Serial_Repository
	 */
	private Serial_Repository $serial_repository;

	/**
	 * Constructor.
	 *
	 * @param Claude_Vision_Client  $vision_client         Claude Vision client instance.
	 * @param Decode_Log_Repository $decode_log_repository Decode log repository instance.
	 * @param Serial_Repository     $serial_repository     Serial repository instance.
	 */
	public function __construct(
		Claude_Vision_Client $vision_client,
		Decode_Log_Repository $decode_log_repository,
		Serial_Repository $serial_repository
	) {
		$this->vision_client         = $vision_client;
		$this->decode_log_repository = $decode_log_repository;
		$this->serial_repository     = $serial_repository;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Public decode endpoint (no auth required for basic info).
		add_action( 'wp_ajax_nopriv_qsa_microid_decode', array( $this, 'handle_decode' ) );
		add_action( 'wp_ajax_qsa_microid_decode', array( $this, 'handle_decode' ) );

		// Staff-only full details endpoint.
		add_action( 'wp_ajax_qsa_microid_full_details', array( $this, 'handle_full_details' ) );
	}

	/**
	 * Handle image decode request.
	 *
	 * Accepts image upload, validates, sends to Claude API, returns basic info.
	 *
	 * @return void
	 */
	public function handle_decode(): void {
		// Check rate limit first (before processing upload).
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			$this->send_error( $rate_check->get_error_message(), $rate_check->get_error_code(), 429 );
			return;
		}

		// Verify nonce.
		$nonce = $this->get_request_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->send_error( __( 'Security check failed. Please refresh the page and try again.', 'qsa-engraving' ), 'invalid_nonce', 403 );
			return;
		}

		// Check if decoder is enabled.
		if ( ! $this->vision_client->is_enabled() ) {
			$this->send_error( __( 'Micro-ID decoder is currently disabled.', 'qsa-engraving' ), 'decoder_disabled', 503 );
			return;
		}

		// Validate uploaded file.
		$validation = $this->validate_upload();
		if ( is_wp_error( $validation ) ) {
			$this->log_decode_attempt( null, 'invalid_image', $validation->get_error_code(), $validation->get_error_message() );
			$this->send_error( $validation->get_error_message(), $validation->get_error_code() );
			return;
		}

		// Extract validated data.
		$image_data = $validation['image_data'];
		$mime_type  = $validation['mime_type'];
		$image_hash = $validation['image_hash'];
		$file_size  = $validation['file_size'];
		$dimensions = $validation['dimensions'];

		// Check for duplicate/cached result (only if table exists).
		if ( $this->decode_log_repository->table_exists() ) {
			$cached = $this->decode_log_repository->get_by_image_hash( $image_hash );
			if ( $cached && 'success' === $cached['decode_status'] && ! empty( $cached['decoded_serial'] ) ) {
				// Return cached result.
				$serial_info = $this->get_basic_serial_info( $cached['decoded_serial'] );
				$this->send_success(
					array(
						'serial'     => $cached['decoded_serial'],
						'cached'     => true,
						'product'    => $serial_info,
					),
					__( 'Serial number decoded (cached result).', 'qsa-engraving' )
				);
				return;
			}
		}

		// Encode image as base64 for API.
		$image_base64 = base64_encode( $image_data );

		// Call Claude Vision API.
		$decode_result = $this->vision_client->decode_micro_id( $image_base64, $mime_type );

		if ( is_wp_error( $decode_result ) ) {
			$this->log_decode_attempt(
				$image_hash,
				'error',
				$decode_result->get_error_code(),
				$decode_result->get_error_message(),
				$file_size,
				$dimensions['width'] ?? null,
				$dimensions['height'] ?? null
			);
			$this->send_error( $decode_result->get_error_message(), $decode_result->get_error_code() );
			return;
		}

		// Process decode result.
		if ( $decode_result['success'] && ! empty( $decode_result['serial'] ) ) {
			$serial = $decode_result['serial'];

			// Log successful decode.
			$this->log_decode_attempt(
				$image_hash,
				'success',
				null,
				null,
				$file_size,
				$dimensions['width'] ?? null,
				$dimensions['height'] ?? null,
				$serial,
				$this->vision_client->get_last_response_time_ms(),
				$this->vision_client->get_last_tokens_used()
			);

			// Get product info for this serial.
			$serial_info = $this->get_basic_serial_info( $serial );

			$this->send_success(
				array(
					'serial'     => $serial,
					'confidence' => $decode_result['confidence'] ?? 'medium',
					'cached'     => false,
					'product'    => $serial_info,
				),
				__( 'Serial number decoded successfully.', 'qsa-engraving' )
			);
			return;
		}

		// Decode failed.
		$error_message = $decode_result['error'] ?? __( 'Could not decode Micro-ID from image.', 'qsa-engraving' );
		$this->log_decode_attempt(
			$image_hash,
			'failed',
			'decode_failed',
			$error_message,
			$file_size,
			$dimensions['width'] ?? null,
			$dimensions['height'] ?? null,
			null,
			$this->vision_client->get_last_response_time_ms(),
			$this->vision_client->get_last_tokens_used()
		);

		$this->send_error( $error_message, 'decode_failed' );
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
	 * Validate uploaded image file.
	 *
	 * @return array{image_data: string, mime_type: string, image_hash: string, file_size: int, dimensions: array}|WP_Error
	 */
	private function validate_upload(): array|WP_Error {
		// Check if file was uploaded.
		if ( empty( $_FILES['image'] ) ) {
			return new WP_Error( 'no_file', __( 'No image file uploaded.', 'qsa-engraving' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $_FILES['image'];

		// Check for upload errors.
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			$error_message = $this->get_upload_error_message( $file['error'] );
			return new WP_Error( 'upload_error', $error_message );
		}

		// Check file size.
		if ( $file['size'] > self::MAX_IMAGE_SIZE ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: Maximum file size */
					__( 'Image exceeds maximum size of %s.', 'qsa-engraving' ),
					size_format( self::MAX_IMAGE_SIZE )
				)
			);
		}

		// Verify MIME type from file content (not just extension).
		$file_info = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$mime_type = $file_info['type'];

		// Fallback to finfo if WordPress check fails.
		if ( empty( $mime_type ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );
		}

		if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
			return new WP_Error(
				'invalid_mime_type',
				sprintf(
					/* translators: %s: Allowed file types */
					__( 'Invalid file type. Allowed types: %s.', 'qsa-engraving' ),
					'JPEG, PNG, WebP'
				)
			);
		}

		// Security check: Verify this is a genuine PHP upload (prevents path tampering).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_uploaded_file( $file['tmp_name'] ) || ! file_exists( $file['tmp_name'] ) ) {
			return new WP_Error( 'invalid_upload', __( 'Invalid file upload.', 'qsa-engraving' ) );
		}

		// Validate image dimensions using getimagesize() - this also confirms the file is a valid image.
		$image_info = getimagesize( $file['tmp_name'] );
		if ( false === $image_info || ! is_array( $image_info ) ) {
			return new WP_Error( 'invalid_image', __( 'File is not a valid image.', 'qsa-engraving' ) );
		}

		$dimensions = array(
			'width'  => $image_info[0],
			'height' => $image_info[1],
		);

		// Check minimum dimension - BOTH width and height must meet the minimum.
		if ( min( $dimensions['width'], $dimensions['height'] ) < self::MIN_IMAGE_DIMENSION ) {
			return new WP_Error(
				'image_too_small',
				sprintf(
					/* translators: %d: Minimum dimension in pixels */
					__( 'Image is too small. Minimum dimension is %dpx.', 'qsa-engraving' ),
					self::MIN_IMAGE_DIMENSION
				)
			);
		}

		// Read image data.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$image_data = file_get_contents( $file['tmp_name'] );
		if ( false === $image_data ) {
			return new WP_Error( 'read_error', __( 'Could not read uploaded file.', 'qsa-engraving' ) );
		}

		// Calculate image hash for caching/deduplication.
		$image_hash = Decode_Log_Repository::hash_image( $image_data );

		return array(
			'image_data' => $image_data,
			'mime_type'  => $mime_type,
			'image_hash' => $image_hash,
			'file_size'  => (int) $file['size'],
			'dimensions' => $dimensions,
		);
	}

	/**
	 * Check rate limit for current IP.
	 *
	 * @return true|WP_Error True if within limit, WP_Error if exceeded.
	 */
	private function check_rate_limit(): true|WP_Error {
		$ip = $this->get_client_ip();
		if ( empty( $ip ) ) {
			// Can't rate limit without IP - allow request but log warning.
			return true;
		}

		$transient_key = 'qsa_microid_rate_' . md5( $ip );
		$current_count = (int) get_transient( $transient_key );

		if ( $current_count >= self::RATE_LIMIT_MAX ) {
			return new WP_Error(
				'rate_limited',
				sprintf(
					/* translators: %d: Rate limit window in seconds */
					__( 'Too many requests. Please wait %d seconds before trying again.', 'qsa-engraving' ),
					self::RATE_LIMIT_WINDOW
				)
			);
		}

		// Increment counter.
		set_transient( $transient_key, $current_count + 1, self::RATE_LIMIT_WINDOW );

		return true;
	}

	/**
	 * Get client IP address.
	 *
	 * Uses a secure approach that only trusts headers that cannot be easily spoofed:
	 * 1. CF-Connecting-IP (Cloudflare sets this; trusted since site uses Cloudflare)
	 * 2. REMOTE_ADDR (direct connection IP, cannot be spoofed)
	 *
	 * X-Forwarded-For and X-Real-IP are NOT trusted as they can be spoofed by attackers
	 * to bypass rate limiting. These headers are only useful when behind a known trusted
	 * proxy with proper configuration.
	 *
	 * @return string|null
	 */
	private function get_client_ip(): ?string {
		// 1. Cloudflare header - trusted because our site uses Cloudflare CDN.
		//    Cloudflare sets this header with the original client IP.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		// 2. Direct connection - cannot be spoofed, always available.
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return null;
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
	 * Log a decode attempt.
	 *
	 * @param string|null $image_hash          Image hash.
	 * @param string      $status              Decode status.
	 * @param string|null $error_code          Error code if failed.
	 * @param string|null $error_message       Error message if failed.
	 * @param int|null    $file_size           File size in bytes.
	 * @param int|null    $width               Image width.
	 * @param int|null    $height              Image height.
	 * @param string|null $decoded_serial      Decoded serial if successful.
	 * @param int|null    $response_time_ms    API response time.
	 * @param int|null    $tokens_used         API tokens used.
	 * @return void
	 */
	private function log_decode_attempt(
		?string $image_hash,
		string $status,
		?string $error_code = null,
		?string $error_message = null,
		?int $file_size = null,
		?int $width = null,
		?int $height = null,
		?string $decoded_serial = null,
		?int $response_time_ms = null,
		?int $tokens_used = null
	): void {
		// Don't log if table doesn't exist.
		if ( ! $this->decode_log_repository->table_exists() ) {
			return;
		}

		$data = array(
			'session_id'           => Decode_Log_Repository::generate_session_id(),
			'image_hash'           => $image_hash ?? '',
			'decode_status'        => $status,
			'serial_found'         => ! empty( $decoded_serial ),
			'decoded_serial'       => $decoded_serial,
			'error_code'           => $error_code,
			'error_message'        => $error_message,
			'image_size_bytes'     => $file_size,
			'image_width'          => $width,
			'image_height'         => $height,
			'api_response_time_ms' => $response_time_ms,
			'api_tokens_used'      => $tokens_used,
			'client_ip'            => $this->get_client_ip(),
			'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null,
			'user_id'              => get_current_user_id() ?: null,
		);

		$this->decode_log_repository->log_decode_attempt( $data );
	}

	/**
	 * Get upload error message.
	 *
	 * @param int $error_code PHP upload error code.
	 * @return string
	 */
	private function get_upload_error_message( int $error_code ): string {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'File is too large.', 'qsa-engraving' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'File was only partially uploaded.', 'qsa-engraving' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was uploaded.', 'qsa-engraving' );
			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
			case UPLOAD_ERR_EXTENSION:
				return __( 'Server error during upload.', 'qsa-engraving' );
			default:
				return __( 'Unknown upload error.', 'qsa-engraving' );
		}
	}

	/**
	 * Get request parameter from POST only.
	 *
	 * POST-only retrieval prevents nonces from being exposed in URLs, referrer headers,
	 * and browser history. This is a security best practice for AJAX endpoints.
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
