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

		$this->send_success(
			array(
				'batch'       => $batch,
				'queue_items' => $queue_items,
				'capacity'    => $capacity,
			)
		);
	}

	/**
	 * Build queue items from modules.
	 *
	 * Groups modules by QSA sequence and calculates statistics for each row.
	 *
	 * @param array $modules    Array of module records.
	 * @param int   $batch_id   The batch ID.
	 * @return array Queue items grouped by QSA sequence.
	 */
	private function build_queue_items( array $modules, int $batch_id ): array {
		if ( empty( $modules ) ) {
			return array();
		}

		// Group modules by QSA sequence.
		$grouped = array();
		foreach ( $modules as $module ) {
			$qsa_seq = (int) $module['qsa_sequence'];
			if ( ! isset( $grouped[ $qsa_seq ] ) ) {
				$grouped[ $qsa_seq ] = array();
			}
			$grouped[ $qsa_seq ][] = $module;
		}

		// Build queue items.
		$queue_items = array();
		foreach ( $grouped as $qsa_seq => $qsa_modules ) {
			// Get unique SKUs and their counts.
			$sku_counts = array();
			foreach ( $qsa_modules as $module ) {
				$sku = $module['module_sku'];
				if ( ! isset( $sku_counts[ $sku ] ) ) {
					$sku_counts[ $sku ] = 0;
				}
				$sku_counts[ $sku ]++;
			}

			// Determine module type from first module.
			$first_sku   = $qsa_modules[0]['module_sku'];
			$module_type = $this->extract_base_type( $first_sku );

			// Determine group type.
			$unique_skus = count( $sku_counts );
			$is_same_id  = $unique_skus === 1;
			$total_count = count( $qsa_modules );

			// Calculate if this is a full array (8 modules) or partial.
			$is_full = $total_count === 8;

			$group_type = ( $is_same_id ? 'Same ID' : 'Mixed ID' ) . ' × ' . ( $is_full ? 'Full' : 'Partial' );

			// Build module list for display.
			$module_list = array();
			foreach ( $sku_counts as $sku => $qty ) {
				$module_list[] = array(
					'sku' => $sku,
					'qty' => $qty,
				);
			}

			// Determine row status.
			$statuses    = array_column( $qsa_modules, 'row_status' );
			$all_done    = count( array_filter( $statuses, fn( $s ) => $s === 'done' ) ) === $total_count;
			$any_in_prog = in_array( 'in_progress', $statuses, true );

			if ( $all_done ) {
				$status = 'complete';
			} elseif ( $any_in_prog ) {
				$status = 'in_progress';
			} else {
				$status = 'pending';
			}

			// Get serial numbers for this QSA - filter based on row status.
			// For in_progress rows: show reserved serials (the current working set).
			// For complete rows: show engraved serials (the committed serials).
			// For pending rows: no serials to show.
			$serials = $this->serial_repository->get_by_batch( $batch_id );

			// Determine which serial status to display based on row status.
			$serial_status_filter = null;
			if ( 'in_progress' === $status ) {
				$serial_status_filter = 'reserved';
			} elseif ( 'complete' === $status ) {
				$serial_status_filter = 'engraved';
			}

			$row_serials = array();
			if ( null !== $serial_status_filter ) {
				$row_serials = array_filter(
					$serials,
					fn( $s ) => (int) $s['qsa_sequence'] === $qsa_seq && $s['status'] === $serial_status_filter
				);
			}

			// Get start position from first module in this QSA.
			$start_position = (int) $qsa_modules[0]['array_position'];

			// Get current array progress (track by meta or compute from serial status).
			$current_array = $this->get_current_array_for_qsa( $batch_id, $qsa_seq );

			$queue_items[] = array(
				'id'             => $qsa_seq,
				'qsa_sequence'   => $qsa_seq,
				'groupType'      => $group_type,
				'moduleType'     => $module_type,
				'modules'        => $module_list,
				'totalModules'   => $total_count,
				'arrayCount'     => 1, // Each QSA is one array.
				'status'         => $status,
				'startPosition'  => $start_position,
				'currentArray'   => $current_array,
				'serials'        => array_map(
					fn( $s ) => array(
						'serial_number' => $s['serial_number'],
						'status'        => $s['status'],
					),
					array_values( $row_serials )
				),
			);
		}

		// Sort by QSA sequence.
		usort( $queue_items, fn( $a, $b ) => $a['qsa_sequence'] <=> $b['qsa_sequence'] );

		return $queue_items;
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
		$current_status = $qsa_modules[0]['row_status'] ?? 'pending';
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

		$current_status = reset( $qsa_modules )['row_status'] ?? 'pending';
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

		$current_status = reset( $qsa_modules )['row_status'] ?? 'pending';
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

		$current_status = reset( $qsa_modules )['row_status'] ?? 'pending';
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
