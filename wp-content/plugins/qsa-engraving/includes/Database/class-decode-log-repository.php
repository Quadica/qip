<?php
/**
 * Decode Log Repository.
 *
 * Handles CRUD operations for the quad_microid_decode_logs table.
 *
 * @package QSA_Engraving
 * @since 1.1.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Database;

use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository class for Micro-ID decode log operations.
 *
 * @since 1.1.0
 */
class Decode_Log_Repository {

	/**
	 * Valid decode statuses.
	 *
	 * @var array<string>
	 */
	public const VALID_STATUSES = array( 'success', 'failed', 'error', 'invalid_image' );

	/**
	 * Default log retention period in days.
	 *
	 * @var int
	 */
	public const DEFAULT_RETENTION_DAYS = 90;

	/**
	 * Default image retention period in days.
	 *
	 * @var int
	 */
	public const DEFAULT_IMAGE_RETENTION_DAYS = 30;

	/**
	 * Maximum limit for paginated queries.
	 *
	 * @var int
	 */
	public const MAX_QUERY_LIMIT = 500;

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Table name with prefix.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'quad_microid_decode_logs';
	}

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		return $this->table_name;
	}

	/**
	 * Check if the table exists.
	 *
	 * @return bool
	 */
	public function table_exists(): bool {
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$this->table_name
			)
		);
		return $result === $this->table_name;
	}

	/**
	 * Log a decode attempt.
	 *
	 * @param array{
	 *     session_id: string,
	 *     image_hash: string,
	 *     image_path?: string|null,
	 *     image_size_bytes?: int|null,
	 *     image_width?: int|null,
	 *     image_height?: int|null,
	 *     decoded_serial?: string|null,
	 *     serial_found?: bool,
	 *     decode_status: string,
	 *     error_code?: string|null,
	 *     error_message?: string|null,
	 *     api_response_time_ms?: int|null,
	 *     api_tokens_used?: int|null,
	 *     client_ip?: string|null,
	 *     user_agent?: string|null,
	 *     user_id?: int|null
	 * } $data The decode attempt data.
	 * @return int|WP_Error The inserted log ID or WP_Error on failure.
	 */
	public function log_decode_attempt( array $data ): int|WP_Error {
		// Validate required fields.
		if ( empty( $data['session_id'] ) ) {
			return new WP_Error(
				'missing_session_id',
				__( 'Session ID is required.', 'qsa-engraving' )
			);
		}

		if ( empty( $data['image_hash'] ) ) {
			return new WP_Error(
				'missing_image_hash',
				__( 'Image hash is required.', 'qsa-engraving' )
			);
		}

		if ( empty( $data['decode_status'] ) || ! in_array( $data['decode_status'], self::VALID_STATUSES, true ) ) {
			return new WP_Error(
				'invalid_decode_status',
				sprintf(
					/* translators: %s: Valid status list */
					__( 'Invalid decode status. Valid values: %s', 'qsa-engraving' ),
					implode( ', ', self::VALID_STATUSES )
				)
			);
		}

		// Build insert data.
		$insert_data = array(
			'session_id'    => sanitize_text_field( $data['session_id'] ),
			'image_hash'    => sanitize_text_field( $data['image_hash'] ),
			'decode_status' => $data['decode_status'],
			'serial_found'  => isset( $data['serial_found'] ) && $data['serial_found'] ? 1 : 0,
		);

		$insert_format = array( '%s', '%s', '%s', '%d' );

		// Optional fields.
		if ( isset( $data['image_path'] ) && ! empty( $data['image_path'] ) ) {
			$insert_data['image_path'] = sanitize_text_field( $data['image_path'] );
			$insert_format[]           = '%s';
		}

		if ( isset( $data['image_size_bytes'] ) && is_int( $data['image_size_bytes'] ) ) {
			$insert_data['image_size_bytes'] = $data['image_size_bytes'];
			$insert_format[]                 = '%d';
		}

		if ( isset( $data['image_width'] ) && is_int( $data['image_width'] ) ) {
			$insert_data['image_width'] = $data['image_width'];
			$insert_format[]            = '%d';
		}

		if ( isset( $data['image_height'] ) && is_int( $data['image_height'] ) ) {
			$insert_data['image_height'] = $data['image_height'];
			$insert_format[]             = '%d';
		}

		if ( isset( $data['decoded_serial'] ) && ! empty( $data['decoded_serial'] ) ) {
			// Validate and normalize serial to 8-digit format.
			$serial = $this->normalize_serial( $data['decoded_serial'] );
			if ( null !== $serial ) {
				$insert_data['decoded_serial'] = $serial;
				$insert_format[]               = '%s';
			}
			// Invalid serials are silently ignored to prevent polluting analytics.
		}

		if ( isset( $data['error_code'] ) && ! empty( $data['error_code'] ) ) {
			$insert_data['error_code'] = sanitize_text_field( $data['error_code'] );
			$insert_format[]           = '%s';
		}

		if ( isset( $data['error_message'] ) && ! empty( $data['error_message'] ) ) {
			$insert_data['error_message'] = sanitize_textarea_field( $data['error_message'] );
			$insert_format[]              = '%s';
		}

		if ( isset( $data['api_response_time_ms'] ) && is_int( $data['api_response_time_ms'] ) ) {
			$insert_data['api_response_time_ms'] = $data['api_response_time_ms'];
			$insert_format[]                     = '%d';
		}

		if ( isset( $data['api_tokens_used'] ) && is_int( $data['api_tokens_used'] ) ) {
			$insert_data['api_tokens_used'] = $data['api_tokens_used'];
			$insert_format[]                = '%d';
		}

		if ( isset( $data['client_ip'] ) && ! empty( $data['client_ip'] ) ) {
			$insert_data['client_ip'] = sanitize_text_field( $data['client_ip'] );
			$insert_format[]          = '%s';
		}

		if ( isset( $data['user_agent'] ) && ! empty( $data['user_agent'] ) ) {
			$insert_data['user_agent'] = sanitize_text_field( substr( $data['user_agent'], 0, 500 ) );
			$insert_format[]           = '%s';
		}

		if ( isset( $data['user_id'] ) && is_int( $data['user_id'] ) && $data['user_id'] > 0 ) {
			$insert_data['user_id'] = $data['user_id'];
			$insert_format[]        = '%d';
		}

		// Insert the record.
		$result = $this->wpdb->insert(
			$this->table_name,
			$insert_data,
			$insert_format
		);

		if ( false === $result ) {
			return new WP_Error(
				'insert_failed',
				sprintf(
					/* translators: %s: Database error message */
					__( 'Failed to log decode attempt: %s', 'qsa-engraving' ),
					$this->wpdb->last_error
				)
			);
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get a log entry by ID.
	 *
	 * @param int $id The log ID.
	 * @return array<string, mixed>|null The log entry or null if not found.
	 */
	public function get_by_id( int $id ): ?array {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Get logs by session ID.
	 *
	 * @param string $session_id The session ID.
	 * @return array<int, array<string, mixed>> Array of log entries.
	 */
	public function get_by_session( string $session_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE session_id = %s ORDER BY created_at DESC",
				$session_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Get recent decode logs with pagination.
	 *
	 * @param int         $limit   Maximum records to return (clamped to MAX_QUERY_LIMIT).
	 * @param int         $offset  Records to skip (must be non-negative).
	 * @param string|null $status  Optional status filter.
	 * @param string|null $serial  Optional serial number filter.
	 * @return array<int, array<string, mixed>> Array of log entries.
	 */
	public function get_recent_logs( int $limit = 100, int $offset = 0, ?string $status = null, ?string $serial = null ): array {
		// Clamp limit and offset to safe values.
		$limit  = min( absint( $limit ), self::MAX_QUERY_LIMIT );
		$offset = absint( $offset );

		// Ensure minimum limit of 1.
		if ( $limit < 1 ) {
			$limit = 1;
		}

		$sql    = "SELECT * FROM {$this->table_name} WHERE 1=1";
		$params = array();

		if ( null !== $status && in_array( $status, self::VALID_STATUSES, true ) ) {
			$sql     .= ' AND decode_status = %s';
			$params[] = $status;
		}

		if ( null !== $serial && preg_match( '/^[0-9]{1,8}$/', $serial ) ) {
			$sql     .= ' AND decoded_serial LIKE %s';
			$params[] = '%' . $this->wpdb->esc_like( str_pad( $serial, 8, '0', STR_PAD_LEFT ) ) . '%';
		}

		$sql     .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
		$params[] = $limit;
		$params[] = $offset;

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$params ),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Count total logs with optional filters.
	 *
	 * @param string|null $status Optional status filter.
	 * @param string|null $serial Optional serial number filter.
	 * @return int Total count.
	 */
	public function count_logs( ?string $status = null, ?string $serial = null ): int {
		$sql    = "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1";
		$params = array();

		if ( null !== $status && in_array( $status, self::VALID_STATUSES, true ) ) {
			$sql     .= ' AND decode_status = %s';
			$params[] = $status;
		}

		if ( null !== $serial && preg_match( '/^[0-9]{1,8}$/', $serial ) ) {
			$sql     .= ' AND decoded_serial LIKE %s';
			$params[] = '%' . $this->wpdb->esc_like( str_pad( $serial, 8, '0', STR_PAD_LEFT ) ) . '%';
		}

		if ( empty( $params ) ) {
			return (int) $this->wpdb->get_var( $sql );
		}

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare( $sql, ...$params )
		);
	}

	/**
	 * Get decode statistics.
	 *
	 * @param int $days Number of days to include (0 = all time).
	 * @return array{
	 *     total_attempts: int,
	 *     success_count: int,
	 *     failed_count: int,
	 *     error_count: int,
	 *     invalid_image_count: int,
	 *     success_rate: float,
	 *     avg_response_time_ms: float,
	 *     unique_serials: int,
	 *     serials_found_count: int
	 * }
	 */
	public function get_statistics( int $days = 0 ): array {
		$date_condition = '';
		$params         = array();

		if ( $days > 0 ) {
			$date_condition = ' AND created_at >= %s';
			$params[]       = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		}

		$sql = "SELECT
			COUNT(*) as total_attempts,
			SUM(CASE WHEN decode_status = 'success' THEN 1 ELSE 0 END) as success_count,
			SUM(CASE WHEN decode_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
			SUM(CASE WHEN decode_status = 'error' THEN 1 ELSE 0 END) as error_count,
			SUM(CASE WHEN decode_status = 'invalid_image' THEN 1 ELSE 0 END) as invalid_image_count,
			AVG(api_response_time_ms) as avg_response_time_ms,
			COUNT(DISTINCT decoded_serial) as unique_serials,
			SUM(CASE WHEN serial_found = 1 THEN 1 ELSE 0 END) as serials_found_count
			FROM {$this->table_name}
			WHERE 1=1{$date_condition}";

		if ( ! empty( $params ) ) {
			$result = $this->wpdb->get_row(
				$this->wpdb->prepare( $sql, ...$params ),
				ARRAY_A
			);
		} else {
			$result = $this->wpdb->get_row( $sql, ARRAY_A );
		}

		$total   = (int) ( $result['total_attempts'] ?? 0 );
		$success = (int) ( $result['success_count'] ?? 0 );

		return array(
			'total_attempts'       => $total,
			'success_count'        => $success,
			'failed_count'         => (int) ( $result['failed_count'] ?? 0 ),
			'error_count'          => (int) ( $result['error_count'] ?? 0 ),
			'invalid_image_count'  => (int) ( $result['invalid_image_count'] ?? 0 ),
			'success_rate'         => $total > 0 ? round( ( $success / $total ) * 100, 1 ) : 0.0,
			'avg_response_time_ms' => round( (float) ( $result['avg_response_time_ms'] ?? 0 ), 0 ),
			'unique_serials'       => (int) ( $result['unique_serials'] ?? 0 ),
			'serials_found_count'  => (int) ( $result['serials_found_count'] ?? 0 ),
		);
	}

	/**
	 * Clean up old log entries.
	 *
	 * @param int $days Number of days to retain (older entries are deleted).
	 * @return int Number of deleted rows.
	 */
	public function cleanup_old_logs( int $days = self::DEFAULT_RETENTION_DAYS ): int {
		if ( $days <= 0 ) {
			return 0;
		}

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE created_at < %s",
				$cutoff_date
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Get logs with stored images older than retention period.
	 *
	 * @param int $days Number of days to retain images.
	 * @return array<int, array{id: int, image_path: string}> Array of log entries with image paths.
	 */
	public function get_logs_with_old_images( int $days = self::DEFAULT_IMAGE_RETENTION_DAYS ): array {
		if ( $days <= 0 ) {
			return array();
		}

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, image_path FROM {$this->table_name}
				WHERE image_path IS NOT NULL
				AND image_path != ''
				AND created_at < %s",
				$cutoff_date
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Clear image path for a log entry.
	 *
	 * @param int $id The log ID.
	 * @return bool True on success.
	 */
	public function clear_image_path( int $id ): bool {
		$result = $this->wpdb->update(
			$this->table_name,
			array( 'image_path' => null ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Check if a duplicate request exists (same image hash within time window).
	 *
	 * Used for rate limiting and deduplication.
	 *
	 * @param string $image_hash The image hash.
	 * @param int    $seconds    Time window in seconds.
	 * @return bool True if duplicate exists.
	 */
	public function has_recent_duplicate( string $image_hash, int $seconds = 60 ): bool {
		$cutoff_time = gmdate( 'Y-m-d H:i:s', time() - $seconds );

		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name}
				WHERE image_hash = %s AND created_at >= %s",
				$image_hash,
				$cutoff_time
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get the most recent decode result for an image hash.
	 *
	 * @param string $image_hash The image hash.
	 * @return array<string, mixed>|null The log entry or null if not found.
	 */
	public function get_by_image_hash( string $image_hash ): ?array {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE image_hash = %s
				ORDER BY created_at DESC
				LIMIT 1",
				$image_hash
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Normalize and validate a serial number to 8-digit format.
	 *
	 * Accepts mixed input to handle JSON numeric values (e.g., 12345678 vs "12345678").
	 *
	 * @param mixed $serial The serial number to normalize.
	 * @return string|null The normalized 8-digit serial or null if invalid.
	 */
	private function normalize_serial( mixed $serial ): ?string {
		// Handle non-scalar types (arrays, objects, null).
		if ( ! is_scalar( $serial ) ) {
			return null;
		}

		// Convert to string and trim whitespace (handles int/float from JSON).
		$serial_str = trim( (string) $serial );

		// Check if it's numeric (allows leading zeros).
		if ( ! preg_match( '/^[0-9]+$/', $serial_str ) ) {
			return null;
		}

		// Parse as integer to validate range.
		$serial_int = (int) $serial_str;

		// Valid range: 1 to 1,048,575 (per Micro-ID spec).
		if ( $serial_int < 1 || $serial_int > 1048575 ) {
			return null;
		}

		// Return as zero-padded 8-digit string.
		return str_pad( (string) $serial_int, 8, '0', STR_PAD_LEFT );
	}

	/**
	 * Generate a unique session ID.
	 *
	 * @return string A unique session identifier.
	 */
	public static function generate_session_id(): string {
		return wp_generate_uuid4();
	}

	/**
	 * Calculate SHA-256 hash of image data.
	 *
	 * @param string $image_data Raw image data or base64-encoded data.
	 * @return string The SHA-256 hash.
	 */
	public static function hash_image( string $image_data ): string {
		return hash( 'sha256', $image_data );
	}
}
