<?php
/**
 * Queue AJAX Handler.
 *
 * Handles AJAX requests for the Engraving Queue workflow.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Ajax;

use Quadica\QSA_Engraving\Services\Batch_Sorter;
use Quadica\QSA_Engraving\Services\SVG_Generator;
use Quadica\QSA_Engraving\Services\SVG_File_Manager;
use Quadica\QSA_Engraving\Database\Batch_Repository;
use Quadica\QSA_Engraving\Database\Serial_Repository;
use Quadica\QSA_Engraving\Admin\Admin_Menu;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for the Engraving Queue UI.
 *
 * @since 1.0.0
 */
class Queue_Ajax_Handler {

	/**
	 * AJAX nonce action name.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'qsa_engraving_nonce';

	/**
	 * Batch Sorter service.
	 *
	 * @var Batch_Sorter
	 */
	private Batch_Sorter $batch_sorter;

	/**
	 * Batch Repository.
	 *
	 * @var Batch_Repository
	 */
	private Batch_Repository $batch_repository;

	/**
	 * Serial Repository.
	 *
	 * @var Serial_Repository
	 */
	private Serial_Repository $serial_repository;

	/**
	 * SVG File Manager.
	 *
	 * @var SVG_File_Manager
	 */
	private SVG_File_Manager $svg_file_manager;

	/**
	 * Constructor.
	 *
	 * @param Batch_Sorter      $batch_sorter      Batch sorter service.
	 * @param Batch_Repository  $batch_repository  Batch repository.
	 * @param Serial_Repository $serial_repository Serial repository.
	 */
	public function __construct(
		Batch_Sorter $batch_sorter,
		Batch_Repository $batch_repository,
		Serial_Repository $serial_repository
	) {
		$this->batch_sorter      = $batch_sorter;
		$this->batch_repository  = $batch_repository;
		$this->serial_repository = $serial_repository;
		$this->svg_file_manager  = new SVG_File_Manager();
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_qsa_get_queue', array( $this, 'handle_get_queue' ) );
		add_action( 'wp_ajax_qsa_get_active_batches', array( $this, 'handle_get_active_batches' ) );
		add_action( 'wp_ajax_qsa_start_row', array( $this, 'handle_start_row' ) );
		add_action( 'wp_ajax_qsa_next_array', array( $this, 'handle_next_array' ) );
		add_action( 'wp_ajax_qsa_complete_row', array( $this, 'handle_complete_row' ) );
		add_action( 'wp_ajax_qsa_retry_array', array( $this, 'handle_retry_array' ) );
		add_action( 'wp_ajax_qsa_resend_svg', array( $this, 'handle_resend_svg' ) );
		add_action( 'wp_ajax_qsa_back_array', array( $this, 'handle_back_array' ) );
		add_action( 'wp_ajax_qsa_rerun_row', array( $this, 'handle_rerun_row' ) );
		add_action( 'wp_ajax_qsa_update_start_position', array( $this, 'handle_update_start_position' ) );
	}

	/**
	 * Verify AJAX nonce and capability.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function verify_request(): bool|WP_Error {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'qsa-engraving' ) );
		}

		if ( ! current_user_can( Admin_Menu::REQUIRED_CAPABILITY ) ) {
			return new WP_Error( 'insufficient_permissions', __( 'You do not have permission to perform this action.', 'qsa-engraving' ) );
		}

		return true;
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
	 * Handle get queue request - retrieves queue items for a batch.
	 *
	 * @return void
	 */
	public function handle_get_queue(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$batch_id = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;

		if ( $batch_id <= 0 ) {
			$this->send_error( __( 'Invalid batch ID.', 'qsa-engraving' ), 'invalid_batch_id' );
			return;
		}

		// Get the batch.
		$batch = $this->batch_repository->get_batch( $batch_id );
		if ( ! $batch ) {
			$this->send_error( __( 'Batch not found.', 'qsa-engraving' ), 'batch_not_found', 404 );
			return;
		}

		// Get all modules for the batch.
		$modules = $this->batch_repository->get_modules_for_batch( $batch_id );

		// Build queue items from modules.
		$queue_items = $this->build_queue_items( $modules, $batch_id );

		// Get serial capacity.
		$capacity = $this->serial_repository->get_capacity();

		// Get count of active batches (excluding current batch).
		$active_batch_count = $this->get_active_batch_count( $batch_id );

		$this->send_success(
			array(
				'batch'              => $batch,
				'queue_items'        => $queue_items,
				'capacity'           => $capacity,
				'active_batch_count' => $active_batch_count,
			)
		);
	}

	/**
	 * Handle get active batches request.
	 *
	 * Returns all batches with status 'in_progress' for batch selection.
	 *
	 * @return void
	 */
	public function handle_get_active_batches(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		global $wpdb;

		$batches_table = $this->batch_repository->get_batches_table_name();
		$modules_table = $this->batch_repository->get_modules_table_name();

		// Get all in_progress batches with additional info.
		// Note: start_position is the array_position of the first module (lowest qsa_sequence, lowest id).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$batches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					b.id,
					b.batch_name,
					b.module_count,
					b.qsa_count,
					b.status,
					b.created_at,
					b.created_by,
					(SELECT COUNT(*) FROM {$modules_table} m WHERE m.engraving_batch_id = b.id AND m.row_status = 'done') as completed_modules,
					(SELECT COUNT(DISTINCT m.qsa_sequence) FROM {$modules_table} m WHERE m.engraving_batch_id = b.id AND m.row_status = 'done') as completed_qsa_sequences,
					(SELECT m.array_position FROM {$modules_table} m
					 WHERE m.engraving_batch_id = b.id
					 ORDER BY m.qsa_sequence ASC, m.id ASC
					 LIMIT 1) as start_position
				FROM {$batches_table} b
				WHERE b.status = %s
				ORDER BY b.created_at DESC",
				'in_progress'
			),
			ARRAY_A
		);

		if ( empty( $batches ) ) {
			$this->send_success( array( 'batches' => array() ) );
			return;
		}

		// Enhance each batch with creator name and progress.
		$enhanced_batches = array();
		foreach ( $batches as $batch ) {
			$created_by_name = '';
			if ( ! empty( $batch['created_by'] ) ) {
				$user = get_user_by( 'id', $batch['created_by'] );
				if ( $user ) {
					$created_by_name = $user->display_name;
				}
			}

			// Calculate array count based on module count and start position.
			$module_count   = (int) $batch['module_count'];
			$start_position = (int) ( $batch['start_position'] ?? 1 );
			$start_position = max( 1, min( 8, $start_position ) ); // Clamp to 1-8.
			$array_count    = $this->calculate_array_count( $module_count, $start_position );

			// Calculate completed arrays based on completed QSA sequences and start position.
			$completed_qsa = (int) $batch['completed_qsa_sequences'];
			$completed_arrays = $completed_qsa; // Each completed QSA sequence = 1 completed array.

			$enhanced_batches[] = array(
				'id'                => (int) $batch['id'],
				'batch_name'        => $batch['batch_name'],
				'module_count'      => $module_count,
				'array_count'       => $array_count,
				'start_position'    => $start_position,
				'status'            => $batch['status'],
				'created_at'        => $batch['created_at'],
				'created_by_name'   => $created_by_name,
				'completed_modules' => (int) $batch['completed_modules'],
				'completed_arrays'  => $completed_arrays,
				'progress_percent'  => $module_count > 0
					? round( ( (int) $batch['completed_modules'] / $module_count ) * 100 )
					: 0,
			);
		}

		$this->send_success( array( 'batches' => $enhanced_batches ) );
	}

	/**
	 * Calculate the number of physical arrays needed for a batch.
	 *
	 * @param int $module_count   Total number of modules.
	 * @param int $start_position Starting position (1-8).
	 * @return int Number of arrays needed.
	 */
	private function calculate_array_count( int $module_count, int $start_position ): int {
		if ( $module_count <= 0 ) {
			return 0;
		}

		$remaining   = $module_count;
		$array_count = 0;

		// First array may be partial if start_position > 1.
		if ( $start_position > 1 ) {
			$first_array_capacity = 9 - $start_position; // e.g., position 7 = 2 slots (7,8).
			$first_array_modules  = min( $remaining, $first_array_capacity );
			$array_count++;
			$remaining -= $first_array_modules;
		}

		// Remaining modules fill full arrays of 8.
		if ( $remaining > 0 ) {
			$array_count += (int) ceil( $remaining / 8 );
		}

		return $array_count;
	}

	/**
	 * Get count of active batches (excluding a specific batch).
	 *
	 * @param int $exclude_batch_id Batch ID to exclude from count.
	 * @return int Count of active batches.
	 */
	private function get_active_batch_count( int $exclude_batch_id = 0 ): int {
		global $wpdb;

		$batches_table = $this->batch_repository->get_batches_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$batches_table} WHERE status = %s AND id != %d",
				'in_progress',
				$exclude_batch_id
			)
		);

		return (int) $count;
	}

	/**
	 * Build queue items from modules.
	 *
	 * Groups modules intelligently:
	 * - "Same ID" groups: All modules with the same single SKU are merged into one row,
	 *   even if they span multiple QSA sequences (physical arrays).
	 * - "Mixed ID" groups: Modules with different SKUs within the same QSA sequence
	 *   stay as separate rows.
	 *
	 * @param array $modules    Array of module records.
	 * @param int   $batch_id   The batch ID.
	 * @return array Queue items grouped by SKU composition.
	 */
	private function build_queue_items( array $modules, int $batch_id ): array {
		if ( empty( $modules ) ) {
			return array();
		}

		// First pass: Group modules by QSA sequence to analyze composition.
		$by_qsa = array();
		foreach ( $modules as $module ) {
			$qsa_seq = (int) $module['qsa_sequence'];
			if ( ! isset( $by_qsa[ $qsa_seq ] ) ) {
				$by_qsa[ $qsa_seq ] = array();
			}
			$by_qsa[ $qsa_seq ][] = $module;
		}

		// Second pass: Identify "Same ID" QSAs (single SKU) and group them.
		// "Mixed ID" QSAs stay separate.
		$same_id_groups = array(); // SKU => array of qsa_sequences.
		$mixed_id_qsas  = array(); // qsa_sequence => modules.

		foreach ( $by_qsa as $qsa_seq => $qsa_modules ) {
			$unique_skus = array_unique( array_column( $qsa_modules, 'module_sku' ) );

			if ( count( $unique_skus ) === 1 ) {
				// Same ID - group by SKU.
				$sku = $unique_skus[0];
				if ( ! isset( $same_id_groups[ $sku ] ) ) {
					$same_id_groups[ $sku ] = array();
				}
				$same_id_groups[ $sku ][ $qsa_seq ] = $qsa_modules;
			} else {
				// Mixed ID - keep separate.
				$mixed_id_qsas[ $qsa_seq ] = $qsa_modules;
			}
		}

		// Get all serials for the batch once.
		$all_serials = $this->serial_repository->get_by_batch( $batch_id );

		// Build queue items from Same ID groups (merged across QSA sequences).
		$queue_items = array();

		foreach ( $same_id_groups as $sku => $qsa_data ) {
			// Sort QSA sequences.
			ksort( $qsa_data );
			$qsa_sequences = array_keys( $qsa_data );
			$first_qsa     = $qsa_sequences[0];

			// Collect all modules across QSA sequences.
			$all_group_modules = array();
			foreach ( $qsa_data as $qsa_modules ) {
				$all_group_modules = array_merge( $all_group_modules, $qsa_modules );
			}

			$total_count = count( $all_group_modules );
			$array_count = count( $qsa_sequences );

			// Module type from SKU.
			$module_type = $this->extract_base_type( $sku );

			// Determine if full or partial (last array has 8 modules?).
			$last_qsa_modules = $qsa_data[ end( $qsa_sequences ) ];
			$is_full          = count( $last_qsa_modules ) === 8;

			$group_type = 'Same ID × ' . ( $is_full ? 'Full' : 'Partial' );

			// Build module list.
			$module_list = array(
				array(
					'sku' => $sku,
					'qty' => $total_count,
				),
			);

			// Determine status from all modules.
			$statuses    = array_map(
				fn( $s ) => $this->normalize_row_status( $s ),
				array_column( $all_group_modules, 'row_status' )
			);
			$done_count  = count( array_filter( $statuses, fn( $s ) => $s === 'done' ) );
			$in_prog     = in_array( 'in_progress', $statuses, true );

			if ( $done_count === $total_count ) {
				$status = 'complete';
			} elseif ( $in_prog ) {
				$status = 'in_progress';
			} elseif ( $done_count > 0 ) {
				$status = 'partial'; // Some QSA sequences completed, others pending.
			} else {
				$status = 'pending';
			}

			// Count completed QSA sequences for partial progress tracking.
			$completed_qsa_count = 0;
			foreach ( $qsa_data as $qsa_seq => $qsa_modules ) {
				$qsa_statuses = array_map(
					fn( $s ) => $this->normalize_row_status( $s ),
					array_column( $qsa_modules, 'row_status' )
				);
				if ( count( array_filter( $qsa_statuses, fn( $s ) => $s === 'done' ) ) === count( $qsa_modules ) ) {
					$completed_qsa_count++;
				}
			}

			// Get start position from first module of first QSA.
			$first_qsa_modules = $qsa_data[ $first_qsa ];
			$start_position    = (int) $first_qsa_modules[0]['array_position'];

			// Get current array (which QSA is in progress).
			$current_array = $this->get_current_array_for_group( $batch_id, $qsa_sequences, $all_serials );

			// Collect serials for display based on status.
			$row_serials = $this->get_serials_for_group( $qsa_sequences, $all_serials, $status );

			$queue_items[] = array(
				'id'               => $first_qsa, // Primary identifier is first QSA sequence.
				'qsa_sequences'    => $qsa_sequences, // All QSA sequences in this group.
				'groupType'        => $group_type,
				'moduleType'       => $module_type,
				'modules'          => $module_list,
				'totalModules'     => $total_count,
				'arrayCount'       => $array_count,
				'status'           => $status,
				'startPosition'    => $start_position,
				'currentArray'     => $current_array,
				'completedArrays'  => $completed_qsa_count, // How many QSA sequences are fully done.
				'serials'          => $row_serials,
			);
		}

		// Build queue items from Mixed ID QSAs (one row per QSA).
		foreach ( $mixed_id_qsas as $qsa_seq => $qsa_modules ) {
			// Get unique SKUs and counts.
			$sku_counts = array();
			foreach ( $qsa_modules as $module ) {
				$sku = $module['module_sku'];
				if ( ! isset( $sku_counts[ $sku ] ) ) {
					$sku_counts[ $sku ] = 0;
				}
				$sku_counts[ $sku ]++;
			}

			$total_count = count( $qsa_modules );
			$is_full     = $total_count === 8;

			// Module type from first module.
			$first_sku   = $qsa_modules[0]['module_sku'];
			$module_type = $this->extract_base_type( $first_sku );

			$group_type = 'Mixed ID × ' . ( $is_full ? 'Full' : 'Partial' );

			// Build module list.
			$module_list = array();
			foreach ( $sku_counts as $sku => $qty ) {
				$module_list[] = array(
					'sku' => $sku,
					'qty' => $qty,
				);
			}

			// Determine status.
			$statuses   = array_map(
				fn( $s ) => $this->normalize_row_status( $s ),
				array_column( $qsa_modules, 'row_status' )
			);
			$done_count = count( array_filter( $statuses, fn( $s ) => $s === 'done' ) );
			$in_prog    = in_array( 'in_progress', $statuses, true );

			if ( $done_count === $total_count ) {
				$status = 'complete';
			} elseif ( $in_prog ) {
				$status = 'in_progress';
			} else {
				$status = 'pending';
			}

			// Start position.
			$start_position = (int) $qsa_modules[0]['array_position'];

			// Current array (for single QSA, it's 1 or 0).
			$current_array = $this->get_current_array_for_qsa( $batch_id, $qsa_seq );

			// Serials.
			$row_serials = $this->get_serials_for_group( array( $qsa_seq ), $all_serials, $status );

			$queue_items[] = array(
				'id'               => $qsa_seq,
				'qsa_sequences'    => array( $qsa_seq ),
				'groupType'        => $group_type,
				'moduleType'       => $module_type,
				'modules'          => $module_list,
				'totalModules'     => $total_count,
				'arrayCount'       => 1,
				'status'           => $status,
				'startPosition'    => $start_position,
				'currentArray'     => $current_array,
				'completedArrays'  => ( 'complete' === $status ) ? 1 : 0,
				'serials'          => $row_serials,
			);
		}

		// Sort by first QSA sequence.
		usort( $queue_items, fn( $a, $b ) => $a['id'] <=> $b['id'] );

		return $queue_items;
	}

	/**
	 * Get current array index for a group of QSA sequences.
	 *
	 * @param int   $batch_id      The batch ID.
	 * @param array $qsa_sequences Array of QSA sequence numbers.
	 * @param array $all_serials   All serials for the batch.
	 * @return int Current array index (1-based), 0 if not started.
	 */
	private function get_current_array_for_group( int $batch_id, array $qsa_sequences, array $all_serials ): int {
		// Find which QSA sequence is currently in progress (has reserved serials).
		foreach ( $qsa_sequences as $index => $qsa_seq ) {
			$qsa_serials = array_filter(
				$all_serials,
				fn( $s ) => (int) $s['qsa_sequence'] === $qsa_seq
			);

			if ( empty( $qsa_serials ) ) {
				// No serials yet - this is where we'd start.
				return $index > 0 ? $index : 0;
			}

			$reserved = array_filter( $qsa_serials, fn( $s ) => $s['status'] === 'reserved' );
			if ( ! empty( $reserved ) ) {
				// This QSA is in progress.
				return $index + 1;
			}
		}

		// All done or not started.
		return 0;
	}

	/**
	 * Get serials for a group of QSA sequences.
	 *
	 * @param array  $qsa_sequences Array of QSA sequence numbers.
	 * @param array  $all_serials   All serials for the batch.
	 * @param string $status        The group status.
	 * @return array Formatted serial data.
	 */
	private function get_serials_for_group( array $qsa_sequences, array $all_serials, string $status ): array {
		// Determine which serial status to show.
		$serial_status_filter = null;
		if ( 'in_progress' === $status ) {
			$serial_status_filter = 'reserved';
		} elseif ( 'complete' === $status ) {
			$serial_status_filter = 'engraved';
		}

		if ( null === $serial_status_filter ) {
			return array();
		}

		$row_serials = array();
		foreach ( $qsa_sequences as $qsa_seq ) {
			$qsa_serials = array_filter(
				$all_serials,
				fn( $s ) => (int) $s['qsa_sequence'] === $qsa_seq && $s['status'] === $serial_status_filter
			);
			$row_serials = array_merge( $row_serials, array_values( $qsa_serials ) );
		}

		return array_map(
			fn( $s ) => array(
				'serial_number' => $s['serial_number'],
				'status'        => $s['status'],
			),
			$row_serials
		);
	}

	/**
	 * Extract base type from SKU.
	 *
	 * @param string $sku The module SKU.
	 * @return string The base type (CORE, SOLO, EDGE, STAR, etc.).
	 */
	private function extract_base_type( string $sku ): string {
		if ( preg_match( '/^([A-Z]{4})/', $sku, $matches ) ) {
			return $matches[1];
		}
		return 'UNKNOWN';
	}

	/**
	 * Get current array index for a QSA in the queue.
	 *
	 * @param int $batch_id    The batch ID.
	 * @param int $qsa_sequence The QSA sequence.
	 * @return int The current array index (0 = not started).
	 */
	private function get_current_array_for_qsa( int $batch_id, int $qsa_sequence ): int {
		// Check if there are any serials reserved for this QSA.
		$serials = $this->serial_repository->get_by_batch( $batch_id );
		$qsa_serials = array_filter(
			$serials,
			fn( $s ) => (int) $s['qsa_sequence'] === $qsa_sequence
		);

		if ( empty( $qsa_serials ) ) {
			return 0; // Not started.
		}

		// Check if any are still reserved (in progress).
		$reserved = array_filter( $qsa_serials, fn( $s ) => $s['status'] === 'reserved' );
		if ( ! empty( $reserved ) ) {
			return 1; // In progress (we only have one array per QSA now).
		}

		// All engraved.
		return 1;
	}

	/**
	 * Normalize row_status value, handling empty strings.
	 *
	 * MySQL ENUM columns can contain empty strings if strict mode is disabled.
	 * This method ensures consistent handling of row_status values.
	 *
	 * @param string|null $status The raw row_status value.
	 * @return string Normalized status ('pending' if empty/null).
	 */
	private function normalize_row_status( ?string $status ): string {
		return ! empty( $status ) ? $status : 'pending';
	}

	/**
	 * Handle start row request - reserves serials and prepares for engraving.
	 *
	 * Enforces row status transition: only pending rows can be started.
	 * Checks for existing reserved serials to prevent duplicate reservations.
	 *
	 * @return void
	 */
	public function handle_start_row(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$batch_id     = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$qsa_sequence = isset( $_POST['qsa_sequence'] ) ? absint( $_POST['qsa_sequence'] ) : 0;

		if ( $batch_id <= 0 || $qsa_sequence <= 0 ) {
			$this->send_error( __( 'Invalid batch or QSA sequence.', 'qsa-engraving' ), 'invalid_params' );
			return;
		}

		// Get modules for this QSA.
		$all_modules = $this->batch_repository->get_modules_for_batch( $batch_id );
		$qsa_modules = array_filter(
			$all_modules,
			fn( $m ) => (int) $m['qsa_sequence'] === $qsa_sequence
		);
		$qsa_modules = array_values( $qsa_modules );

		if ( empty( $qsa_modules ) ) {
			$this->send_error( __( 'No modules found for this QSA.', 'qsa-engraving' ), 'no_modules' );
			return;
		}

		// Check current row status - only allow starting from 'pending' state.
		$current_status = $this->normalize_row_status( $qsa_modules[0]['row_status'] ?? null );
		if ( 'pending' !== $current_status ) {
			$this->send_error(
				sprintf(
					/* translators: %s: Current row status */
					__( 'Cannot start row: current status is "%s". Only pending rows can be started.', 'qsa-engraving' ),
					$current_status
				),
				'invalid_row_status'
			);
			return;
		}

		// Check for existing reserved serials to prevent duplicate reservations.
		$existing_serials = $this->serial_repository->get_by_batch( $batch_id, 'reserved' );
		$existing_for_qsa = array_filter(
			$existing_serials,
			fn( $s ) => (int) $s['qsa_sequence'] === $qsa_sequence
		);

		if ( ! empty( $existing_for_qsa ) ) {
			$this->send_error(
				__( 'This row already has reserved serials. Use Retry to get new serials or Complete to finish.', 'qsa-engraving' ),
				'serials_already_reserved'
			);
			return;
		}

		// Reserve serials for these modules.
		$modules_for_serial = array_map(
			fn( $m ) => array(
				'module_sku'          => $m['module_sku'],
				'production_batch_id' => $m['production_batch_id'],
				'order_id'            => $m['order_id'] ?? null,
				'qsa_sequence'        => $m['qsa_sequence'],
				'array_position'      => $m['array_position'],
			),
			$qsa_modules
		);

		$reserved = $this->serial_repository->reserve_serials( $batch_id, $modules_for_serial );

		if ( is_wp_error( $reserved ) ) {
			$this->send_error( $reserved->get_error_message(), $reserved->get_error_code() );
			return;
		}

		// Link reserved serial numbers to engraved_modules table.
		$this->batch_repository->link_serials_to_modules( $batch_id, $qsa_sequence, $reserved );

		// Update module row status to in_progress.
		$status_result = $this->batch_repository->update_row_status( $batch_id, $qsa_sequence, 'in_progress' );
		if ( is_wp_error( $status_result ) ) {
			// Compensating action: void the reserved serials to prevent orphaned reservations.
			$this->serial_repository->void_serials( $batch_id, $qsa_sequence );
			$this->send_error(
				__( 'Failed to update row status. Reserved serials have been voided.', 'qsa-engraving' ),
				'status_update_failed'
			);
			return;
		}

		$this->send_success(
			array(
				'batch_id'     => $batch_id,
				'qsa_sequence' => $qsa_sequence,
				'serials'      => $reserved,
			),
			__( 'Row started. Serials reserved.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle next array request - commits current array serials and marks row done.
	 *
	 * Note: In the current one-array-per-row implementation, each QSA row IS one array.
	 * This action commits the serials and marks the row as done, making it functionally
	 * equivalent to handle_complete_row but kept for API consistency.
	 *
	 * Enforces same guards as handle_complete_row(): row must be in_progress with reserved serials.
	 *
	 * @return void
	 */
	public function handle_next_array(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$batch_id     = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$qsa_sequence = isset( $_POST['qsa_sequence'] ) ? absint( $_POST['qsa_sequence'] ) : 0;

		if ( $batch_id <= 0 || $qsa_sequence <= 0 ) {
			$this->send_error( __( 'Invalid batch or QSA sequence.', 'qsa-engraving' ), 'invalid_params' );
			return;
		}

		// Check that the row is in_progress (valid state to complete from).
		$all_modules = $this->batch_repository->get_modules_for_batch( $batch_id );
		$qsa_modules = array_filter(
			$all_modules,
			fn( $m ) => (int) $m['qsa_sequence'] === $qsa_sequence
		);

		if ( empty( $qsa_modules ) ) {
			$this->send_error( __( 'No modules found for this QSA.', 'qsa-engraving' ), 'no_modules' );
			return;
		}

		$current_status = $this->normalize_row_status( reset( $qsa_modules )['row_status'] ?? null );
		if ( 'in_progress' !== $current_status ) {
			$this->send_error(
				sprintf(
					/* translators: %s: Current row status */
					__( 'Cannot complete row: current status is "%s". Only in-progress rows can be completed.', 'qsa-engraving' ),
					$current_status
				),
				'invalid_row_status'
			);
			return;
		}

		// Check for reserved serials - must have serials to commit.
		$reserved_serials = $this->serial_repository->get_by_batch( $batch_id, 'reserved' );
		$reserved_for_qsa = array_filter(
			$reserved_serials,
			fn( $s ) => (int) $s['qsa_sequence'] === $qsa_sequence
		);

		if ( empty( $reserved_for_qsa ) ) {
			$this->send_error(
				__( 'No reserved serials found to commit. The row may have already been completed or serials were voided.', 'qsa-engraving' ),
				'no_reserved_serials'
			);
			return;
		}

		// Commit the serials for this QSA.
		$committed = $this->serial_repository->commit_serials( $batch_id, $qsa_sequence );

		if ( is_wp_error( $committed ) ) {
			$this->send_error( $committed->get_error_message(), $committed->get_error_code() );
			return;
		}

		// Mark the row as done (one-array-per-row implementation).
		$mark_result = $this->batch_repository->mark_qsa_done( $batch_id, $qsa_sequence );
		if ( is_wp_error( $mark_result ) ) {
			$this->send_error( $mark_result->get_error_message(), $mark_result->get_error_code() );
			return;
		}

		// Check if entire batch is complete.
		$batch_complete = $this->batch_repository->is_batch_complete( $batch_id );
		if ( $batch_complete ) {
			$this->batch_repository->complete_batch( $batch_id );

			// Clean up all SVG files for the completed batch (ephemeral files).
			$this->svg_file_manager->cleanup_batch_files( $batch_id );
		} else {
			// Clean up SVG files for this completed row.
			$this->svg_file_manager->cleanup_old_files( $batch_id, $qsa_sequence );
		}

		$this->send_success(
			array(
				'batch_id'          => $batch_id,
				'qsa_sequence'      => $qsa_sequence,
				'serials_committed' => $committed,
				'batch_complete'    => $batch_complete,
			),
			$batch_complete
				? __( 'Array complete. Batch is now complete!', 'qsa-engraving' )
				: __( 'Serials committed. Row complete.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle complete row request - finalizes the row as done.
	 *
	 * Enforces that the row must be in_progress with reserved serials to complete.
	 * Commits serials (reserved -> engraved) and marks row as done.
	 *
	 * @return void
	 */
	public function handle_complete_row(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$batch_id     = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$qsa_sequence = isset( $_POST['qsa_sequence'] ) ? absint( $_POST['qsa_sequence'] ) : 0;

		if ( $batch_id <= 0 || $qsa_sequence <= 0 ) {
			$this->send_error( __( 'Invalid batch or QSA sequence.', 'qsa-engraving' ), 'invalid_params' );
			return;
		}

		// Check that the row is in_progress (valid state to complete from).
		$all_modules = $this->batch_repository->get_modules_for_batch( $batch_id );
		$qsa_modules = array_filter(
			$all_modules,
			fn( $m ) => (int) $m['qsa_sequence'] === $qsa_sequence
		);

		if ( empty( $qsa_modules ) ) {
			$this->send_error( __( 'No modules found for this QSA.', 'qsa-engraving' ), 'no_modules' );
			return;
		}

		$current_status = $this->normalize_row_status( reset( $qsa_modules )['row_status'] ?? null );
		if ( 'in_progress' !== $current_status ) {
			$this->send_error(
				sprintf(
					/* translators: %s: Current row status */
					__( 'Cannot complete row: current status is "%s". Only in-progress rows can be completed.', 'qsa-engraving' ),
					$current_status
				),
				'invalid_row_status'
			);
			return;
		}

		// Check for reserved serials - must have serials to commit.
		$reserved_serials = $this->serial_repository->get_by_batch( $batch_id, 'reserved' );
		$reserved_for_qsa = array_filter(
			$reserved_serials,
			fn( $s ) => (int) $s['qsa_sequence'] === $qsa_sequence
		);

		if ( empty( $reserved_for_qsa ) ) {
			$this->send_error(
				__( 'No reserved serials found to commit. The row may have already been completed or serials were voided.', 'qsa-engraving' ),
				'no_reserved_serials'
			);
			return;
		}

		// Commit the serials.
		$committed = $this->serial_repository->commit_serials( $batch_id, $qsa_sequence );

		if ( is_wp_error( $committed ) ) {
			$this->send_error( $committed->get_error_message(), $committed->get_error_code() );
			return;
		}

		// Mark the row as done.
		$mark_result = $this->batch_repository->mark_qsa_done( $batch_id, $qsa_sequence );
		if ( is_wp_error( $mark_result ) ) {
			$this->send_error( $mark_result->get_error_message(), $mark_result->get_error_code() );
			return;
		}

		// Check if entire batch is complete.
		$batch_complete = $this->batch_repository->is_batch_complete( $batch_id );
		if ( $batch_complete ) {
			$this->batch_repository->complete_batch( $batch_id );

			// Clean up all SVG files for the completed batch (ephemeral files).
			$this->svg_file_manager->cleanup_batch_files( $batch_id );
		} else {
			// Clean up SVG files for this completed row.
			$this->svg_file_manager->cleanup_old_files( $batch_id, $qsa_sequence );
		}

		$this->send_success(
			array(
				'batch_id'         => $batch_id,
				'qsa_sequence'     => $qsa_sequence,
				'serials_committed' => $committed,
				'batch_complete'   => $batch_complete,
			),
			$batch_complete
				? __( 'Row completed. Batch is now complete!', 'qsa-engraving' )
				: __( 'Row completed.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle retry array request - voids current serials and reserves new ones.
	 *
	 * @return void
	 */
	public function handle_retry_array(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$batch_id     = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$qsa_sequence = isset( $_POST['qsa_sequence'] ) ? absint( $_POST['qsa_sequence'] ) : 0;

		if ( $batch_id <= 0 || $qsa_sequence <= 0 ) {
			$this->send_error( __( 'Invalid batch or QSA sequence.', 'qsa-engraving' ), 'invalid_params' );
			return;
		}

		// Void the current reserved serials.
		$voided = $this->serial_repository->void_serials( $batch_id, $qsa_sequence );

		if ( is_wp_error( $voided ) ) {
			$this->send_error( $voided->get_error_message(), $voided->get_error_code() );
			return;
		}

		// Get modules for this QSA to reserve new serials.
		$all_modules = $this->batch_repository->get_modules_for_batch( $batch_id );
		$qsa_modules = array_filter(
			$all_modules,
			fn( $m ) => (int) $m['qsa_sequence'] === $qsa_sequence
		);
		$qsa_modules = array_values( $qsa_modules );

		// Reserve new serials.
		$modules_for_serial = array_map(
			fn( $m ) => array(
				'module_sku'          => $m['module_sku'],
				'production_batch_id' => $m['production_batch_id'],
				'order_id'            => $m['order_id'] ?? null,
				'qsa_sequence'        => $m['qsa_sequence'],
				'array_position'      => $m['array_position'],
			),
			$qsa_modules
		);

		$reserved = $this->serial_repository->reserve_serials( $batch_id, $modules_for_serial );

		if ( is_wp_error( $reserved ) ) {
			$this->send_error( $reserved->get_error_message(), $reserved->get_error_code() );
			return;
		}

		// Link new serial numbers to engraved_modules table.
		$this->batch_repository->link_serials_to_modules( $batch_id, $qsa_sequence, $reserved );

		$this->send_success(
			array(
				'batch_id'       => $batch_id,
				'qsa_sequence'   => $qsa_sequence,
				'serials_voided' => $voided,
				'serials'        => $reserved,
			),
			__( 'Previous serials voided. New serials reserved.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle resend SVG request - same serials, just resend the file.
	 *
	 * @return void
	 */
	public function handle_resend_svg(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$batch_id     = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$qsa_sequence = isset( $_POST['qsa_sequence'] ) ? absint( $_POST['qsa_sequence'] ) : 0;

		if ( $batch_id <= 0 || $qsa_sequence <= 0 ) {
			$this->send_error( __( 'Invalid batch or QSA sequence.', 'qsa-engraving' ), 'invalid_params' );
			return;
		}

		// Get the current serials for this QSA.
		$serials = $this->serial_repository->get_by_batch( $batch_id );
		$qsa_serials = array_filter(
			$serials,
			fn( $s ) => (int) $s['qsa_sequence'] === $qsa_sequence && $s['status'] === 'reserved'
		);

		if ( empty( $qsa_serials ) ) {
			$this->send_error( __( 'No reserved serials found for resend.', 'qsa-engraving' ), 'no_serials' );
			return;
		}

		// TODO: Phase 7 - Generate SVG and send to LightBurn via UDP.
		// For now, just return the serial info for the UI.

		$this->send_success(
			array(
				'batch_id'     => $batch_id,
				'qsa_sequence' => $qsa_sequence,
				'serials'      => array_values( array_map(
					fn( $s ) => array(
						'serial_number' => $s['serial_number'],
						'status'        => $s['status'],
					),
					$qsa_serials
				) ),
			),
			__( 'SVG resend requested.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle back array request - reserve new serials for previous array position.
	 *
	 * Note: This is for going back to redo a previously engraved array with new serials.
	 *
	 * @return void
	 */
	public function handle_back_array(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$batch_id     = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$qsa_sequence = isset( $_POST['qsa_sequence'] ) ? absint( $_POST['qsa_sequence'] ) : 0;

		if ( $batch_id <= 0 || $qsa_sequence <= 0 ) {
			$this->send_error( __( 'Invalid batch or QSA sequence.', 'qsa-engraving' ), 'invalid_params' );
			return;
		}

		// Void any currently reserved serials - check for errors.
		$voided = $this->serial_repository->void_serials( $batch_id, $qsa_sequence );
		if ( is_wp_error( $voided ) ) {
			$this->send_error( $voided->get_error_message(), $voided->get_error_code() );
			return;
		}

		// Get modules for this QSA to reserve new serials.
		$all_modules = $this->batch_repository->get_modules_for_batch( $batch_id );
		$qsa_modules = array_filter(
			$all_modules,
			fn( $m ) => (int) $m['qsa_sequence'] === $qsa_sequence
		);
		$qsa_modules = array_values( $qsa_modules );

		if ( empty( $qsa_modules ) ) {
			$this->send_error( __( 'No modules found for this QSA.', 'qsa-engraving' ), 'no_modules' );
			return;
		}

		// Reserve new serials.
		$modules_for_serial = array_map(
			fn( $m ) => array(
				'module_sku'          => $m['module_sku'],
				'production_batch_id' => $m['production_batch_id'],
				'order_id'            => $m['order_id'] ?? null,
				'qsa_sequence'        => $m['qsa_sequence'],
				'array_position'      => $m['array_position'],
			),
			$qsa_modules
		);

		$reserved = $this->serial_repository->reserve_serials( $batch_id, $modules_for_serial );

		if ( is_wp_error( $reserved ) ) {
			$this->send_error( $reserved->get_error_message(), $reserved->get_error_code() );
			return;
		}

		// Link new serial numbers to engraved_modules table.
		$this->batch_repository->link_serials_to_modules( $batch_id, $qsa_sequence, $reserved );

		$this->send_success(
			array(
				'batch_id'       => $batch_id,
				'qsa_sequence'   => $qsa_sequence,
				'serials_voided' => $voided,
				'serials'        => $reserved,
			),
			__( 'Going back with new serials.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle rerun row request - resets a completed row to pending.
	 *
	 * Note: Previously engraved serials remain committed (they're on physical modules).
	 * New serials will be reserved when the row is started again.
	 *
	 * @return void
	 */
	public function handle_rerun_row(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$batch_id     = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$qsa_sequence = isset( $_POST['qsa_sequence'] ) ? absint( $_POST['qsa_sequence'] ) : 0;

		if ( $batch_id <= 0 || $qsa_sequence <= 0 ) {
			$this->send_error( __( 'Invalid batch or QSA sequence.', 'qsa-engraving' ), 'invalid_params' );
			return;
		}

		// Reset the row status to pending - check for errors.
		$reset_result = $this->batch_repository->reset_row_status( $batch_id, $qsa_sequence );
		if ( is_wp_error( $reset_result ) ) {
			$this->send_error( $reset_result->get_error_message(), $reset_result->get_error_code() );
			return;
		}

		// If the batch was marked complete, reopen it.
		$batch = $this->batch_repository->get_batch( $batch_id );
		$batch_reopened = false;
		if ( $batch && $batch['status'] === 'completed' ) {
			$batch_reopened = $this->batch_repository->reopen_batch( $batch_id );
		}

		$this->send_success(
			array(
				'batch_id'       => $batch_id,
				'qsa_sequence'   => $qsa_sequence,
				'batch_reopened' => $batch_reopened,
			),
			__( 'Row reset to pending. Ready for re-engraving.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle update start position request.
	 *
	 * Only allows updating start position when row is in 'pending' status.
	 * Once a row is started (in_progress) or completed (done), position cannot be changed.
	 *
	 * @return void
	 */
	public function handle_update_start_position(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$batch_id       = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$qsa_sequence   = isset( $_POST['qsa_sequence'] ) ? absint( $_POST['qsa_sequence'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$start_position = isset( $_POST['start_position'] ) ? absint( $_POST['start_position'] ) : 1;

		if ( $batch_id <= 0 || $qsa_sequence <= 0 ) {
			$this->send_error( __( 'Invalid batch or QSA sequence.', 'qsa-engraving' ), 'invalid_params' );
			return;
		}

		// Enforce pending-only: check row status before allowing update.
		$all_modules = $this->batch_repository->get_modules_for_batch( $batch_id );
		$qsa_modules = array_filter(
			$all_modules,
			fn( $m ) => (int) $m['qsa_sequence'] === $qsa_sequence
		);

		if ( empty( $qsa_modules ) ) {
			$this->send_error( __( 'No modules found for this QSA.', 'qsa-engraving' ), 'no_modules' );
			return;
		}

		$current_status = $this->normalize_row_status( reset( $qsa_modules )['row_status'] ?? null );
		if ( 'pending' !== $current_status ) {
			$this->send_error(
				sprintf(
					/* translators: %s: Current row status */
					__( 'Cannot update start position: row status is "%s". Only pending rows can have their start position changed.', 'qsa-engraving' ),
					$current_status
				),
				'invalid_row_status'
			);
			return;
		}

		// Validate start position (1-8).
		$start_position = max( 1, min( 8, $start_position ) );

		// Update the start position for all modules in this QSA.
		$updated = $this->batch_repository->update_start_position( $batch_id, $qsa_sequence, $start_position );

		if ( is_wp_error( $updated ) ) {
			$this->send_error( $updated->get_error_message(), $updated->get_error_code() );
			return;
		}

		$this->send_success(
			array(
				'batch_id'       => $batch_id,
				'qsa_sequence'   => $qsa_sequence,
				'start_position' => $start_position,
			),
			__( 'Start position updated.', 'qsa-engraving' )
		);
	}
}
