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
	 * Groups modules by original_qsa_sequence (the QSA sequence assigned at batch creation).
	 * This ensures that modules stay grouped together even when redistributed across
	 * multiple arrays due to start position changes.
	 *
	 * Group types for UI display:
	 * - "Same ID": All modules in the row have the same SKU
	 * - "Mixed ID": Row contains modules with different SKUs
	 *
	 * @param array $modules    Array of module records.
	 * @param int   $batch_id   The batch ID.
	 * @return array Queue items grouped by original_qsa_sequence.
	 */
	private function build_queue_items( array $modules, int $batch_id ): array {
		if ( empty( $modules ) ) {
			return array();
		}

		// Group modules by original_qsa_sequence (with fallback for legacy data).
		$by_original_qsa = array();
		foreach ( $modules as $module ) {
			// Use original_qsa_sequence if available, otherwise fall back to qsa_sequence.
			$original_qsa = ! empty( $module['original_qsa_sequence'] )
				? (int) $module['original_qsa_sequence']
				: (int) $module['qsa_sequence'];

			if ( ! isset( $by_original_qsa[ $original_qsa ] ) ) {
				$by_original_qsa[ $original_qsa ] = array();
			}
			$by_original_qsa[ $original_qsa ][] = $module;
		}

		// Get all serials for the batch once.
		$all_serials = $this->serial_repository->get_by_batch( $batch_id );

		// Build queue items from each row group.
		$queue_items = array();

		foreach ( $by_original_qsa as $original_qsa => $row_modules ) {
			// Get all distinct current QSA sequences for this row.
			$qsa_sequences = array_unique( array_column( $row_modules, 'qsa_sequence' ) );
			$qsa_sequences = array_map( 'intval', $qsa_sequences );
			sort( $qsa_sequences );

			$first_qsa   = $qsa_sequences[0];
			$total_count = count( $row_modules );
			$array_count = count( $qsa_sequences );

			// Base type from first module's SKU (all modules in a row have the same base type).
			$first_sku   = $row_modules[0]['module_sku'];
			$module_type = $this->extract_base_type( $first_sku );

			// Group modules by current qsa_sequence for per-array analysis.
			$by_current_qsa = array();
			foreach ( $row_modules as $module ) {
				$qsa = (int) $module['qsa_sequence'];
				if ( ! isset( $by_current_qsa[ $qsa ] ) ) {
					$by_current_qsa[ $qsa ] = array();
				}
				$by_current_qsa[ $qsa ][] = $module;
			}

			// Build module list (SKU counts) - always show breakdown by config code.
			$sku_counts = array();
			foreach ( $row_modules as $module ) {
				$sku = $module['module_sku'];
				if ( ! isset( $sku_counts[ $sku ] ) ) {
					$sku_counts[ $sku ] = 0;
				}
				$sku_counts[ $sku ]++;
			}
			$module_list = array();
			foreach ( $sku_counts as $sku => $qty ) {
				$module_list[] = array(
					'sku' => $sku,
					'qty' => $qty,
				);
			}

			// Determine overall row status.
			$statuses   = array_map(
				fn( $s ) => $this->normalize_row_status( $s ),
				array_column( $row_modules, 'row_status' )
			);
			$done_count = count( array_filter( $statuses, fn( $s ) => $s === 'done' ) );
			$in_prog    = in_array( 'in_progress', $statuses, true );

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
			foreach ( $by_current_qsa as $qsa_seq => $qsa_modules ) {
				$qsa_statuses = array_map(
					fn( $s ) => $this->normalize_row_status( $s ),
					array_column( $qsa_modules, 'row_status' )
				);
				if ( count( array_filter( $qsa_statuses, fn( $s ) => $s === 'done' ) ) === count( $qsa_modules ) ) {
					$completed_qsa_count++;
				}
			}

			// Get start position from first module of first QSA (sorted by position).
			$first_qsa_modules = $by_current_qsa[ $first_qsa ] ?? array();
			usort( $first_qsa_modules, fn( $a, $b ) => (int) $a['array_position'] <=> (int) $b['array_position'] );
			$start_position = ! empty( $first_qsa_modules ) ? (int) $first_qsa_modules[0]['array_position'] : 1;

			// Get current array (which QSA is in progress).
			$current_array = $this->get_current_array_for_group( $batch_id, $qsa_sequences, $all_serials );

			// Collect serials for display based on status.
			$row_serials = $this->get_serials_for_group( $qsa_sequences, $all_serials, $status );

			$queue_items[] = array(
				'id'               => $first_qsa, // Primary identifier is first QSA sequence.
				'qsa_sequences'    => $qsa_sequences, // All QSA sequences in this row.
				'baseType'         => $module_type, // Row identifier is the base type (e.g., CUBE, STAR).
				'modules'          => $module_list, // Breakdown by config code.
				'totalModules'     => $total_count,
				'arrayCount'       => $array_count,
				'status'           => $status,
				'startPosition'    => $start_position,
				'currentArray'     => $current_array,
				'completedArrays'  => $completed_qsa_count, // How many QSA sequences are fully done.
				'serials'          => $row_serials,
			);
		}

		// Sort by first QSA sequence (original_qsa_sequence order).
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
	 * Includes the revision letter (e.g., "STARa", "CUBEb") because different
	 * revisions have different physical layouts and SVG configurations.
	 *
	 * @param string $sku The module SKU.
	 * @return string The base type with revision (e.g., "STARa").
	 */
	private function extract_base_type( string $sku ): string {
		// Match 4 uppercase letters + optional lowercase revision letter.
		if ( preg_match( '/^([A-Z]{4}[a-z]?)/', $sku, $matches ) ) {
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

		// Check for committable serials (reserved or empty-status that will be auto-fixed).
		$committable_count = $this->serial_repository->count_committable_serials( $batch_id, $qsa_sequence );

		if ( 0 === $committable_count ) {
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

		// CRITICAL: Verify serials were actually committed.
		// This prevents marking rows complete when no engraving occurred.
		$expected_module_count = count( $qsa_modules );

		if ( 0 === $committed ) {
			// Race condition check: Another admin may have completed this row
			// between our count check and commit call. Validate that ALL expected
			// serials are engraved (not just some) to prevent partial commits.
			$already_engraved = $this->serial_repository->count_engraved_serials( $batch_id, $qsa_sequence );

			if ( $already_engraved === $expected_module_count ) {
				// Race condition detected: all serials were committed by another request.
				// Log and proceed to mark the row as done.
				error_log(
					sprintf(
						'QSA Engraving: Race condition in complete_row - all %d serials already engraved for batch %d, QSA %d. Proceeding to mark row done.',
						$already_engraved,
						$batch_id,
						$qsa_sequence
					)
				);
				// Update committed count for accurate response data.
				$committed = $already_engraved;
				// Fall through to mark_qsa_done below.
			} elseif ( $already_engraved > 0 ) {
				// Partial commit detected - data integrity issue.
				error_log(
					sprintf(
						'QSA Engraving: PARTIAL COMMIT detected - %d of %d serials engraved for batch %d, QSA %d. Possible data corruption.',
						$already_engraved,
						$expected_module_count,
						$batch_id,
						$qsa_sequence
					)
				);
				$this->send_error(
					sprintf(
						/* translators: 1: engraved count, 2: expected count */
						__( 'Data integrity issue: Only %1$d of %2$d serials are engraved. Please contact support.', 'qsa-engraving' ),
						$already_engraved,
						$expected_module_count
					),
					'partial_commit'
				);
				return;
			} else {
				// No engraved serials found - this is a genuine error (serials voided or missing).
				$this->send_error(
					__( 'No serials were committed. The row cannot be marked complete without engraved serials. Please use Retry to generate new serials.', 'qsa-engraving' ),
					'zero_serials_committed'
				);
				return;
			}
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

		// Check for committable serials (reserved or empty-status that will be auto-fixed).
		$committable_count = $this->serial_repository->count_committable_serials( $batch_id, $qsa_sequence );

		if ( 0 === $committable_count ) {
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

		// CRITICAL: Verify serials were actually committed.
		// This prevents marking rows complete when no engraving occurred.
		$expected_module_count = count( $qsa_modules );

		if ( 0 === $committed ) {
			// Race condition check: Another admin may have completed this row
			// between our count check and commit call. Validate that ALL expected
			// serials are engraved (not just some) to prevent partial commits.
			$already_engraved = $this->serial_repository->count_engraved_serials( $batch_id, $qsa_sequence );

			if ( $already_engraved === $expected_module_count ) {
				// Race condition detected: all serials were committed by another request.
				// Log and proceed to mark the row as done.
				error_log(
					sprintf(
						'QSA Engraving: Race condition in handle_next_array - all %d serials already engraved for batch %d, QSA %d. Proceeding to mark row done.',
						$already_engraved,
						$batch_id,
						$qsa_sequence
					)
				);
				// Update committed count for accurate response data.
				$committed = $already_engraved;
				// Fall through to mark_qsa_done below.
			} elseif ( $already_engraved > 0 ) {
				// Partial commit detected - data integrity issue.
				error_log(
					sprintf(
						'QSA Engraving: PARTIAL COMMIT detected in handle_next_array - %d of %d serials engraved for batch %d, QSA %d. Possible data corruption.',
						$already_engraved,
						$expected_module_count,
						$batch_id,
						$qsa_sequence
					)
				);
				$this->send_error(
					sprintf(
						/* translators: 1: engraved count, 2: expected count */
						__( 'Data integrity issue: Only %1$d of %2$d serials are engraved. Please contact support.', 'qsa-engraving' ),
						$already_engraved,
						$expected_module_count
					),
					'partial_commit'
				);
				return;
			} else {
				// No engraved serials found - this is a genuine error (serials voided or missing).
				$this->send_error(
					__( 'No serials were committed. The row cannot be marked complete without engraved serials. Please use Retry to generate new serials.', 'qsa-engraving' ),
					'zero_serials_committed'
				);
				return;
			}
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
	 * Updates the start position for a row and redistributes modules across arrays.
	 * The first array uses the new start position; subsequent arrays start at position 1.
	 *
	 * This may change the total number of arrays needed for the row:
	 * - Example: 24 modules with start_position=1 needs 3 arrays (8+8+8)
	 * - Example: 24 modules with start_position=6 needs 4 arrays (3+8+8+5)
	 *
	 * Only allows updating start position when ALL modules in the row are in 'pending' status.
	 * Once any module in the row is started (in_progress) or completed (done), position cannot be changed.
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

		// Validate start position (1-8).
		$start_position = max( 1, min( 8, $start_position ) );

		// Get all modules for the batch to identify the row.
		$all_modules = $this->batch_repository->get_modules_for_batch( $batch_id );

		if ( empty( $all_modules ) ) {
			$this->send_error( __( 'No modules found for this batch.', 'qsa-engraving' ), 'no_modules' );
			return;
		}

		// Identify all QSA sequences that belong to the same "row" (queue item).
		// A row is defined by modules with the same SKU composition:
		// - Same ID: All modules with the same single SKU across QSA sequences
		// - Mixed ID: Single QSA sequence with multiple different SKUs
		$row_qsa_sequences = $this->get_row_qsa_sequences( $all_modules, $qsa_sequence );

		if ( empty( $row_qsa_sequences ) ) {
			$this->send_error( __( 'No modules found for this QSA sequence.', 'qsa-engraving' ), 'no_modules' );
			return;
		}

		// Get all modules in this row and check their status.
		$row_modules = array_filter(
			$all_modules,
			fn( $m ) => in_array( (int) $m['qsa_sequence'], $row_qsa_sequences, true )
		);

		// Enforce pending-only: check ALL modules in the row before allowing update.
		foreach ( $row_modules as $module ) {
			$status = $this->normalize_row_status( $module['row_status'] ?? null );
			if ( 'pending' !== $status ) {
				$this->send_error(
					sprintf(
						/* translators: %s: Current row status */
						__( 'Cannot update start position: some modules have status "%s". Only pending rows can have their start position changed.', 'qsa-engraving' ),
						$status
					),
					'invalid_row_status'
				);
				return;
			}
		}

		// Redistribute modules across arrays with the new start position.
		$result = $this->batch_repository->redistribute_row_modules( $batch_id, $row_qsa_sequences, $start_position );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message(), $result->get_error_code() );
			return;
		}

		// Update the batch's qsa_count if it changed.
		if ( $result['old_qsa_count'] !== $result['new_qsa_count'] ) {
			// Recalculate total QSA count for the entire batch.
			$new_total_qsa_count = $this->recalculate_batch_qsa_count( $batch_id );
			$this->batch_repository->update_batch_counts(
				$batch_id,
				count( $all_modules ), // Module count doesn't change.
				$new_total_qsa_count
			);
		}

		// Extract the NEW QSA sequences from the redistribution result.
		// These may differ from the original row_qsa_sequences if new arrays were allocated.
		$new_qsa_sequences = array_map(
			fn( $arr ) => (int) $arr['sequence'],
			$result['arrays']
		);

		$this->send_success(
			array(
				'batch_id'        => $batch_id,
				'qsa_sequences'   => $new_qsa_sequences, // NEW sequences after redistribution.
				'start_position'  => $start_position,
				'old_array_count' => $result['old_qsa_count'],
				'new_array_count' => $result['new_qsa_count'],
				'arrays'          => $result['arrays'],
			),
			sprintf(
				/* translators: 1: Start position, 2: Array count */
				__( 'Start position updated to %1$d. Row now uses %2$d array(s).', 'qsa-engraving' ),
				$start_position,
				$result['new_qsa_count']
			)
		);
	}

	/**
	 * Get all QSA sequences that belong to the same "row" as the given sequence.
	 *
	 * A row is defined by modules with the same original_qsa_sequence (the QSA
	 * sequence assigned at batch creation time). This allows rows to be redistributed
	 * across multiple arrays while maintaining their logical grouping.
	 *
	 * Status filtering ensures we only group modules in the same workflow state,
	 * preventing in_progress sequences from being mixed with pending sequences.
	 *
	 * @param array $all_modules  All modules in the batch.
	 * @param int   $qsa_sequence The QSA sequence to find the row for.
	 * @return array Array of QSA sequence numbers that form the row.
	 */
	private function get_row_qsa_sequences( array $all_modules, int $qsa_sequence ): array {
		// Find a module in the target QSA sequence to get its original_qsa_sequence.
		$target_module = null;
		foreach ( $all_modules as $module ) {
			if ( (int) $module['qsa_sequence'] === $qsa_sequence ) {
				$target_module = $module;
				break;
			}
		}

		if ( null === $target_module ) {
			return array();
		}

		// Get the original_qsa_sequence that defines this row.
		// For legacy data where original_qsa_sequence might be 0 or missing, fall back to qsa_sequence.
		$original_qsa = ! empty( $target_module['original_qsa_sequence'] )
			? (int) $target_module['original_qsa_sequence']
			: $qsa_sequence;

		// Get the target status for filtering.
		$target_status = $this->normalize_row_status( $target_module['row_status'] ?? null );

		// Find all distinct qsa_sequences for modules with the same original_qsa_sequence AND status.
		$row_sequences = array();
		foreach ( $all_modules as $module ) {
			$module_original_qsa = ! empty( $module['original_qsa_sequence'] )
				? (int) $module['original_qsa_sequence']
				: (int) $module['qsa_sequence'];

			if ( $module_original_qsa === $original_qsa ) {
				$module_status = $this->normalize_row_status( $module['row_status'] ?? null );
				if ( $module_status === $target_status ) {
					$row_sequences[] = (int) $module['qsa_sequence'];
				}
			}
		}

		// Return unique, sorted QSA sequences.
		$row_sequences = array_unique( $row_sequences );
		sort( $row_sequences );
		return $row_sequences;
	}

	/**
	 * Recalculate the total QSA count for a batch.
	 *
	 * @param int $batch_id The batch ID.
	 * @return int Total number of distinct QSA sequences.
	 */
	private function recalculate_batch_qsa_count( int $batch_id ): int {
		global $wpdb;

		$modules_table = $this->batch_repository->get_modules_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT qsa_sequence) FROM {$modules_table} WHERE engraving_batch_id = %d",
				$batch_id
			)
		);

		return (int) $count;
	}
}
