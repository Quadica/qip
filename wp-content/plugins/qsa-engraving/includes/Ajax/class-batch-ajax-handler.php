<?php
/**
 * Batch AJAX Handler.
 *
 * Handles AJAX requests for batch creation and module operations.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Ajax;

use Quadica\QSA_Engraving\Services\Module_Selector;
use Quadica\QSA_Engraving\Services\Batch_Sorter;
use Quadica\QSA_Engraving\Services\LED_Code_Resolver;
use Quadica\QSA_Engraving\Database\Batch_Repository;
use Quadica\QSA_Engraving\Database\Serial_Repository;
use Quadica\QSA_Engraving\Admin\Admin_Menu;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for the Batch Creator UI.
 *
 * @since 1.0.0
 */
class Batch_Ajax_Handler {

	/**
	 * AJAX nonce action name.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'qsa_engraving_nonce';

	/**
	 * Module Selector service.
	 *
	 * @var Module_Selector
	 */
	private Module_Selector $module_selector;

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
	 * LED Code Resolver service.
	 *
	 * @var LED_Code_Resolver
	 */
	private LED_Code_Resolver $led_code_resolver;

	/**
	 * Constructor.
	 *
	 * @param Module_Selector   $module_selector   Module selector service.
	 * @param Batch_Sorter      $batch_sorter      Batch sorter service.
	 * @param Batch_Repository  $batch_repository  Batch repository.
	 * @param Serial_Repository $serial_repository Serial repository.
	 * @param LED_Code_Resolver $led_code_resolver LED code resolver service.
	 */
	public function __construct(
		Module_Selector $module_selector,
		Batch_Sorter $batch_sorter,
		Batch_Repository $batch_repository,
		Serial_Repository $serial_repository,
		LED_Code_Resolver $led_code_resolver
	) {
		$this->module_selector   = $module_selector;
		$this->batch_sorter      = $batch_sorter;
		$this->batch_repository  = $batch_repository;
		$this->serial_repository = $serial_repository;
		$this->led_code_resolver = $led_code_resolver;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_qsa_get_modules_awaiting', array( $this, 'handle_get_modules_awaiting' ) );
		add_action( 'wp_ajax_qsa_refresh_modules', array( $this, 'handle_refresh_modules' ) );
		add_action( 'wp_ajax_qsa_create_batch', array( $this, 'handle_create_batch' ) );
		add_action( 'wp_ajax_qsa_preview_batch', array( $this, 'handle_preview_batch' ) );
		add_action( 'wp_ajax_qsa_duplicate_batch', array( $this, 'handle_duplicate_batch' ) );
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
	 * Handle get modules awaiting request.
	 *
	 * @return void
	 */
	public function handle_get_modules_awaiting(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		$modules = $this->module_selector->get_modules_awaiting();

		$this->send_success( $modules );
	}

	/**
	 * Handle refresh modules request.
	 *
	 * Same as get_modules_awaiting but forces a fresh query.
	 *
	 * @return void
	 */
	public function handle_refresh_modules(): void {
		// Alias for get_modules_awaiting - could add cache clearing here if needed.
		$this->handle_get_modules_awaiting();
	}

	/**
	 * Handle create batch request.
	 *
	 * @return void
	 */
	public function handle_create_batch(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// Get and validate selections.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$selections_json = isset( $_POST['selections'] ) ? sanitize_text_field( wp_unslash( $_POST['selections'] ) ) : '';

		if ( empty( $selections_json ) ) {
			$this->send_error( __( 'No modules selected.', 'qsa-engraving' ), 'no_selection' );
			return;
		}

		$selections = json_decode( $selections_json, true );

		if ( ! is_array( $selections ) || empty( $selections ) ) {
			$this->send_error( __( 'Invalid selection data.', 'qsa-engraving' ), 'invalid_data' );
			return;
		}

		// Validate each selection.
		$validated_selections = array();
		foreach ( $selections as $selection ) {
			$validated = $this->validate_selection( $selection );
			if ( is_wp_error( $validated ) ) {
				$this->send_error( $validated->get_error_message(), $validated->get_error_code() );
				return;
			}
			$validated_selections[] = $validated;
		}

		// Resolve LED codes for each module.
		$modules_with_leds = $this->resolve_led_codes( $validated_selections );
		if ( is_wp_error( $modules_with_leds ) ) {
			$this->send_error( $modules_with_leds->get_error_message(), $modules_with_leds->get_error_code() );
			return;
		}

		// Expand selections into individual module instances.
		$expanded = $this->batch_sorter->expand_selections( $modules_with_leds );

		// Sort for LED optimization.
		$sorted = $this->batch_sorter->sort_modules( $expanded );

		// Create the batch record.
		$batch_id = $this->batch_repository->create_batch();
		if ( is_wp_error( $batch_id ) ) {
			$this->send_error( $batch_id->get_error_message(), $batch_id->get_error_code() );
			return;
		}

		// Assign modules to QSA arrays (default start position 1).
		$qsa_arrays = $this->batch_sorter->assign_to_arrays( $sorted, 1 );

		// Add modules to the batch.
		$module_count = 0;
		foreach ( $qsa_arrays as $qsa ) {
			foreach ( $qsa as $module ) {
				$result = $this->batch_repository->add_module(
					array(
						'engraving_batch_id'  => $batch_id,
						'production_batch_id' => $module['production_batch_id'],
						'module_sku'          => $module['module_sku'],
						'order_id'            => $module['order_id'],
						'serial_number'       => '', // Serial numbers assigned during engraving.
						'qsa_sequence'        => $module['qsa_sequence'],
						'array_position'      => $module['array_position'],
					)
				);

				if ( is_wp_error( $result ) ) {
					// Log error but continue - batch is already created.
					error_log( sprintf( 'QSA Engraving: Failed to add module to batch %d: %s', $batch_id, $result->get_error_message() ) );
				} else {
					$module_count++;
				}
			}
		}

		// Update batch counts.
		$this->batch_repository->update_batch_counts( $batch_id, $module_count, count( $qsa_arrays ) );

		// Build redirect URL to the engraving queue.
		$redirect_url = admin_url( 'admin.php?page=' . Admin_Menu::MENU_SLUG . '-queue&batch_id=' . $batch_id );

		$this->send_success(
			array(
				'batch_id'     => $batch_id,
				'module_count' => $module_count,
				'qsa_count'    => count( $qsa_arrays ),
				'redirect_url' => $redirect_url,
			),
			sprintf(
				/* translators: 1: Module count, 2: QSA count */
				__( 'Batch created with %1$d modules across %2$d QSAs.', 'qsa-engraving' ),
				$module_count,
				count( $qsa_arrays )
			)
		);
	}

	/**
	 * Handle preview batch request.
	 *
	 * Returns the sorted order and array breakdown without creating a batch.
	 *
	 * @return void
	 */
	public function handle_preview_batch(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// Get and validate selections.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$selections_json = isset( $_POST['selections'] ) ? sanitize_text_field( wp_unslash( $_POST['selections'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$start_position = isset( $_POST['start_position'] ) ? absint( $_POST['start_position'] ) : 1;

		if ( empty( $selections_json ) ) {
			$this->send_error( __( 'No modules selected.', 'qsa-engraving' ), 'no_selection' );
			return;
		}

		$selections = json_decode( $selections_json, true );

		if ( ! is_array( $selections ) || empty( $selections ) ) {
			$this->send_error( __( 'Invalid selection data.', 'qsa-engraving' ), 'invalid_data' );
			return;
		}

		// Validate selections.
		$validated_selections = array();
		foreach ( $selections as $selection ) {
			$validated = $this->validate_selection( $selection );
			if ( is_wp_error( $validated ) ) {
				$this->send_error( $validated->get_error_message(), $validated->get_error_code() );
				return;
			}
			$validated_selections[] = $validated;
		}

		// Resolve LED codes.
		$modules_with_leds = $this->resolve_led_codes( $validated_selections );
		if ( is_wp_error( $modules_with_leds ) ) {
			$this->send_error( $modules_with_leds->get_error_message(), $modules_with_leds->get_error_code() );
			return;
		}

		// Expand and sort.
		$expanded = $this->batch_sorter->expand_selections( $modules_with_leds );
		$sorted   = $this->batch_sorter->sort_modules( $expanded );

		// Calculate array breakdown.
		$breakdown = $this->batch_sorter->calculate_array_breakdown( count( $sorted ), $start_position );

		// Count LED transitions.
		$transitions   = $this->batch_sorter->count_transitions( $sorted );
		$distinct_leds = $this->batch_sorter->get_distinct_led_codes( $sorted );

		$this->send_success(
			array(
				'total_modules'   => count( $sorted ),
				'array_count'     => $breakdown['array_count'],
				'arrays'          => $breakdown['arrays'],
				'led_transitions' => $transitions,
				'distinct_leds'   => $distinct_leds,
				'sorted_order'    => array_map(
					function ( $m ) {
						return array(
							'module_sku' => $m['module_sku'],
							'order_id'   => $m['order_id'],
							'led_codes'  => $m['led_codes'] ?? array(),
						);
					},
					$sorted
				),
			)
		);
	}

	/**
	 * Handle duplicate batch request.
	 *
	 * Creates a new batch that duplicates an existing completed batch.
	 * New serial numbers will be assigned when the batch is processed.
	 *
	 * @return void
	 */
	public function handle_duplicate_batch(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// Get source batch ID.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$source_batch_id = isset( $_POST['source_batch_id'] ) ? absint( $_POST['source_batch_id'] ) : 0;

		if ( $source_batch_id <= 0 ) {
			$this->send_error( __( 'Invalid source batch ID.', 'qsa-engraving' ), 'invalid_batch_id' );
			return;
		}

		// Verify source batch exists and is completed.
		$source_batch = $this->batch_repository->get_batch( $source_batch_id );
		if ( ! $source_batch ) {
			$this->send_error( __( 'Source batch not found.', 'qsa-engraving' ), 'batch_not_found' );
			return;
		}

		if ( 'completed' !== $source_batch['status'] ) {
			$this->send_error(
				__( 'Only completed batches can be duplicated.', 'qsa-engraving' ),
				'batch_not_completed'
			);
			return;
		}

		// Get modules from source batch, grouped by SKU/order/production_batch.
		$source_modules = $this->get_source_batch_modules( $source_batch_id );
		if ( empty( $source_modules ) ) {
			$this->send_error( __( 'Source batch has no modules.', 'qsa-engraving' ), 'no_modules' );
			return;
		}

		// Resolve LED codes for each module group.
		$modules_with_leds = $this->resolve_led_codes( $source_modules );
		if ( is_wp_error( $modules_with_leds ) ) {
			$this->send_error( $modules_with_leds->get_error_message(), $modules_with_leds->get_error_code() );
			return;
		}

		// Expand selections into individual module instances.
		$expanded = $this->batch_sorter->expand_selections( $modules_with_leds );

		// Sort for LED optimization.
		$sorted = $this->batch_sorter->sort_modules( $expanded );

		// Create the new batch record.
		$batch_id = $this->batch_repository->create_batch();
		if ( is_wp_error( $batch_id ) ) {
			$this->send_error( $batch_id->get_error_message(), $batch_id->get_error_code() );
			return;
		}

		// Assign modules to QSA arrays (default start position 1).
		$qsa_arrays = $this->batch_sorter->assign_to_arrays( $sorted, 1 );

		// Add modules to the batch.
		$module_count = 0;
		foreach ( $qsa_arrays as $qsa ) {
			foreach ( $qsa as $module ) {
				$result = $this->batch_repository->add_module(
					array(
						'engraving_batch_id'  => $batch_id,
						'production_batch_id' => $module['production_batch_id'],
						'module_sku'          => $module['module_sku'],
						'order_id'            => $module['order_id'],
						'serial_number'       => '', // Serial numbers assigned during engraving.
						'qsa_sequence'        => $module['qsa_sequence'],
						'array_position'      => $module['array_position'],
					)
				);

				if ( is_wp_error( $result ) ) {
					error_log( sprintf( 'QSA Engraving: Failed to add module to batch %d: %s', $batch_id, $result->get_error_message() ) );
				} else {
					$module_count++;
				}
			}
		}

		// Update batch counts.
		$this->batch_repository->update_batch_counts( $batch_id, $module_count, count( $qsa_arrays ) );

		// Build redirect URL to the engraving queue.
		$redirect_url = admin_url( 'admin.php?page=' . Admin_Menu::MENU_SLUG . '-queue&batch_id=' . $batch_id );

		$this->send_success(
			array(
				'batch_id'        => $batch_id,
				'source_batch_id' => $source_batch_id,
				'module_count'    => $module_count,
				'qsa_count'       => count( $qsa_arrays ),
				'redirect_url'    => $redirect_url,
			),
			sprintf(
				/* translators: 1: Source batch ID, 2: Module count, 3: QSA count */
				__( 'Batch #%1$d duplicated: %2$d modules across %3$d QSAs.', 'qsa-engraving' ),
				$source_batch_id,
				$module_count,
				count( $qsa_arrays )
			)
		);
	}

	/**
	 * Get modules from a source batch grouped for duplication.
	 *
	 * @param int $batch_id Source batch ID.
	 * @return array Array of module selections.
	 */
	private function get_source_batch_modules( int $batch_id ): array {
		global $wpdb;

		$modules_table = $this->batch_repository->get_modules_table_name();

		// Get modules grouped by SKU, order, and production batch.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					production_batch_id,
					module_sku,
					order_id,
					COUNT(*) as quantity
				FROM {$modules_table}
				WHERE engraving_batch_id = %d
				GROUP BY production_batch_id, module_sku, order_id
				ORDER BY module_sku, order_id",
				$batch_id
			),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return array();
		}

		// Format as selections array.
		$selections = array();
		foreach ( $results as $row ) {
			$selections[] = array(
				'production_batch_id' => (int) $row['production_batch_id'],
				'module_sku'          => $row['module_sku'],
				'order_id'            => (int) $row['order_id'],
				'quantity'            => (int) $row['quantity'],
			);
		}

		return $selections;
	}

	/**
	 * Validate a module selection.
	 *
	 * @param array $selection The selection data.
	 * @return array|WP_Error Validated selection or error.
	 */
	private function validate_selection( array $selection ): array|WP_Error {
		// Required fields.
		$required = array( 'production_batch_id', 'module_sku', 'order_id', 'quantity' );
		foreach ( $required as $field ) {
			if ( ! isset( $selection[ $field ] ) ) {
				return new WP_Error(
					'missing_field',
					sprintf(
						/* translators: %s: Field name */
						__( 'Missing required field: %s', 'qsa-engraving' ),
						$field
					)
				);
			}
		}

		// Validate types and values.
		$validated = array(
			'production_batch_id' => absint( $selection['production_batch_id'] ),
			'module_sku'          => sanitize_text_field( $selection['module_sku'] ),
			'order_id'            => absint( $selection['order_id'] ),
			'quantity'            => absint( $selection['quantity'] ),
		);

		if ( $validated['production_batch_id'] <= 0 ) {
			return new WP_Error( 'invalid_batch_id', __( 'Invalid production batch ID.', 'qsa-engraving' ) );
		}

		if ( empty( $validated['module_sku'] ) ) {
			return new WP_Error( 'invalid_sku', __( 'Invalid module SKU.', 'qsa-engraving' ) );
		}

		if ( ! Module_Selector::is_qsa_compatible( $validated['module_sku'] ) ) {
			return new WP_Error(
				'invalid_sku_format',
				sprintf(
					/* translators: %s: Module SKU */
					__( 'Module SKU %s is not QSA-compatible.', 'qsa-engraving' ),
					$validated['module_sku']
				)
			);
		}

		if ( $validated['order_id'] <= 0 ) {
			return new WP_Error( 'invalid_order_id', __( 'Invalid order ID.', 'qsa-engraving' ) );
		}

		if ( $validated['quantity'] <= 0 ) {
			return new WP_Error( 'invalid_quantity', __( 'Quantity must be greater than zero.', 'qsa-engraving' ) );
		}

		return $validated;
	}

	/**
	 * Resolve LED codes for modules.
	 *
	 * LED codes are required for batch creation - missing BOM or shortcode data
	 * will block batch creation to ensure proper LED optimization.
	 *
	 * @param array $selections Validated selections.
	 * @return array|WP_Error Selections with LED codes or error if any LED data is missing.
	 */
	private function resolve_led_codes( array $selections ): array|WP_Error {
		$result = array();
		$errors = array();

		foreach ( $selections as $selection ) {
			$led_codes = $this->led_code_resolver->get_led_codes_for_module(
				$selection['order_id'],
				$selection['module_sku']
			);

			if ( is_wp_error( $led_codes ) ) {
				// Collect errors - we'll report all issues at once.
				$errors[] = sprintf(
					'%s (Order #%d): %s',
					$selection['module_sku'],
					$selection['order_id'],
					$led_codes->get_error_message()
				);
				continue;
			}

			$selection['led_codes'] = $led_codes;
			$result[]               = $selection;
		}

		// If any LED code resolution failed, block batch creation.
		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'led_resolution_failed',
				sprintf(
					/* translators: %s: List of error messages */
					__( 'Cannot create batch - LED data missing for: %s', 'qsa-engraving' ),
					implode( '; ', $errors )
				)
			);
		}

		return $result;
	}
}
