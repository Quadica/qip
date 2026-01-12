<?php
/**
 * Config Import AJAX Handler.
 *
 * Handles AJAX requests for importing QSA configuration from CSV files.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Ajax;

use Quadica\QSA_Engraving\Database\Config_Repository;
use Quadica\QSA_Engraving\Admin\Admin_Menu;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for QSA configuration import.
 *
 * Provides endpoints for:
 * - Uploading and parsing CSV files
 * - Previewing import changes (additions, updates, deletions)
 * - Applying import changes to the database
 *
 * @since 1.0.0
 */
class Config_Import_Ajax_Handler {

	/**
	 * AJAX nonce action name.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'qsa_engraving_nonce';

	/**
	 * Required CSV columns.
	 *
	 * @var array
	 */
	private const REQUIRED_COLUMNS = array(
		'qsa_design',
		'revision',
		'position',
		'element_type',
		'origin_x',
		'origin_y',
		'rotation',
	);

	/**
	 * Optional CSV columns.
	 *
	 * @var array
	 */
	private const OPTIONAL_COLUMNS = array(
		'text_height',
		'element_size',
		'is_active',
		'created_at',
		'updated_at',
		'created_by',
	);

	/**
	 * Config Repository instance.
	 *
	 * @var Config_Repository
	 */
	private Config_Repository $config_repository;

	/**
	 * Constructor.
	 *
	 * @param Config_Repository $config_repository Config repository instance.
	 */
	public function __construct( Config_Repository $config_repository ) {
		$this->config_repository = $config_repository;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_qsa_config_import_preview', array( $this, 'handle_import_preview' ) );
		add_action( 'wp_ajax_qsa_config_import_apply', array( $this, 'handle_import_apply' ) );
	}

	/**
	 * Verify AJAX nonce and capability.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function verify_request(): bool|WP_Error {
		// Check nonce from POST or GET.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		}

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
	 * Handle import preview request.
	 *
	 * Parses uploaded CSV, validates content, and returns preview of changes.
	 *
	 * @return void
	 */
	public function handle_import_preview(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code() );
			return;
		}

		// Check for uploaded file.
		if ( empty( $_FILES['csv_file'] ) ) {
			$this->send_error( __( 'No file uploaded.', 'qsa-engraving' ), 'no_file' );
			return;
		}

		// Validate file upload.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $_FILES['csv_file'];

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			$this->send_error( __( 'File upload failed.', 'qsa-engraving' ), 'upload_error' );
			return;
		}

		// Check file extension.
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			$this->send_error( __( 'Invalid file type. Please upload a CSV file.', 'qsa-engraving' ), 'invalid_type' );
			return;
		}

		// Parse CSV.
		$parsed = $this->parse_csv( $file['tmp_name'] );
		if ( is_wp_error( $parsed ) ) {
			$this->send_error( $parsed->get_error_message(), $parsed->get_error_code() );
			return;
		}

		// Validate required elements.
		$validation = $this->validate_required_elements( $parsed['rows'] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation->get_error_message(), $validation->get_error_code() );
			return;
		}

		// Get design and revision from first row.
		$qsa_design = $parsed['rows'][0]['qsa_design'];
		$revision   = $parsed['rows'][0]['revision'];

		// Get existing config from database.
		$existing = $this->config_repository->get_all_for_design_revision( $qsa_design, $revision );

		// Compare and generate preview.
		$preview = $this->generate_preview( $parsed['rows'], $existing );

		$this->send_success(
			array(
				'qsa_design'  => $qsa_design,
				'revision'    => $revision,
				'csv_rows'    => count( $parsed['rows'] ),
				'preview'     => $preview,
				'parsed_data' => $parsed['rows'], // Send parsed data for apply step.
			),
			sprintf(
				/* translators: 1: design name, 2: revision letter */
				__( 'Preview generated for %1$s revision %2$s.', 'qsa-engraving' ),
				$qsa_design,
				$revision
			)
		);
	}

	/**
	 * Handle import apply request.
	 *
	 * Applies the import changes to the database.
	 *
	 * @return void
	 */
	public function handle_import_apply(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code() );
			return;
		}

		// Get parsed data from POST.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$parsed_data_raw = isset( $_POST['parsed_data'] ) ? wp_unslash( $_POST['parsed_data'] ) : '';
		if ( empty( $parsed_data_raw ) ) {
			$this->send_error( __( 'No data to import.', 'qsa-engraving' ), 'no_data' );
			return;
		}

		// Decode JSON data.
		$parsed_rows = json_decode( $parsed_data_raw, true );
		if ( ! is_array( $parsed_rows ) || empty( $parsed_rows ) ) {
			$this->send_error( __( 'Invalid import data.', 'qsa-engraving' ), 'invalid_data' );
			return;
		}

		// Sanitize each row.
		$rows = array();
		foreach ( $parsed_rows as $row ) {
			$rows[] = $this->sanitize_row( $row );
		}

		// Get design and revision.
		$qsa_design = $rows[0]['qsa_design'];
		$revision   = $rows[0]['revision'];

		// Get existing config.
		$existing = $this->config_repository->get_all_for_design_revision( $qsa_design, $revision );

		// Build lookup for existing rows.
		$existing_lookup = array();
		foreach ( $existing as $row ) {
			$key                    = $row['position'] . ':' . $row['element_type'];
			$existing_lookup[ $key ] = $row;
		}

		// Track what's in the CSV for deletion detection.
		$csv_keys = array();
		$stats    = array(
			'inserted' => 0,
			'updated'  => 0,
			'deleted'  => 0,
			'errors'   => array(),
		);

		// Process each CSV row.
		foreach ( $rows as $row ) {
			$key        = $row['position'] . ':' . $row['element_type'];
			$csv_keys[] = $key;

			$origin_x     = (float) $row['origin_x'];
			$origin_y     = (float) $row['origin_y'];
			$rotation     = (int) $row['rotation'];
			$text_height  = $this->parse_nullable_float( $row['text_height'] ?? null );
			$element_size = $this->parse_nullable_float( $row['element_size'] ?? null );

			if ( isset( $existing_lookup[ $key ] ) ) {
				// Update existing.
				$result = $this->config_repository->update_element(
					$qsa_design,
					$revision,
					(int) $row['position'],
					$row['element_type'],
					$origin_x,
					$origin_y,
					$rotation,
					$text_height,
					$element_size
				);

				if ( $result ) {
					++$stats['updated'];
				} else {
					$stats['errors'][] = sprintf( 'Failed to update %s', $key );
				}
			} else {
				// Insert new.
				$result = $this->config_repository->insert_element(
					$qsa_design,
					$revision,
					(int) $row['position'],
					$row['element_type'],
					$origin_x,
					$origin_y,
					$rotation,
					$text_height,
					$element_size
				);

				if ( false !== $result ) {
					++$stats['inserted'];
				} else {
					$stats['errors'][] = sprintf( 'Failed to insert %s', $key );
				}
			}
		}

		// Delete rows that exist in DB but not in CSV.
		foreach ( $existing as $row ) {
			$key = $row['position'] . ':' . $row['element_type'];
			if ( ! in_array( $key, $csv_keys, true ) ) {
				$result = $this->config_repository->delete_element(
					$qsa_design,
					$revision,
					(int) $row['position'],
					$row['element_type']
				);

				if ( $result ) {
					++$stats['deleted'];
				} else {
					$stats['errors'][] = sprintf( 'Failed to delete %s', $key );
				}
			}
		}

		// Build response message.
		$message_parts = array();
		if ( $stats['inserted'] > 0 ) {
			$message_parts[] = sprintf(
				/* translators: %d: number of rows */
				_n( '%d row inserted', '%d rows inserted', $stats['inserted'], 'qsa-engraving' ),
				$stats['inserted']
			);
		}
		if ( $stats['updated'] > 0 ) {
			$message_parts[] = sprintf(
				/* translators: %d: number of rows */
				_n( '%d row updated', '%d rows updated', $stats['updated'], 'qsa-engraving' ),
				$stats['updated']
			);
		}
		if ( $stats['deleted'] > 0 ) {
			$message_parts[] = sprintf(
				/* translators: %d: number of rows */
				_n( '%d row deleted', '%d rows deleted', $stats['deleted'], 'qsa-engraving' ),
				$stats['deleted']
			);
		}

		$message = empty( $message_parts )
			? __( 'No changes made.', 'qsa-engraving' )
			: implode( ', ', $message_parts ) . '.';

		if ( ! empty( $stats['errors'] ) ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of errors */
				_n( '%d error occurred.', '%d errors occurred.', count( $stats['errors'] ), 'qsa-engraving' ),
				count( $stats['errors'] )
			);
		}

		$this->send_success( $stats, $message );
	}

	/**
	 * Parse CSV file.
	 *
	 * @param string $filepath Path to the CSV file.
	 * @return array|WP_Error Parsed data or error.
	 */
	private function parse_csv( string $filepath ): array|WP_Error {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $filepath );
		if ( false === $content ) {
			return new WP_Error( 'read_error', __( 'Could not read uploaded file.', 'qsa-engraving' ) );
		}

		// Split into lines.
		$lines = preg_split( '/\r\n|\r|\n/', $content );
		if ( count( $lines ) < 2 ) {
			return new WP_Error( 'empty_csv', __( 'CSV file must have a header row and at least one data row.', 'qsa-engraving' ) );
		}

		// Parse header.
		$header = str_getcsv( array_shift( $lines ) );
		$header = array_map( 'trim', $header );
		$header = array_map( 'strtolower', $header );

		// Validate required columns.
		foreach ( self::REQUIRED_COLUMNS as $required ) {
			if ( ! in_array( $required, $header, true ) ) {
				return new WP_Error(
					'missing_column',
					sprintf(
						/* translators: %s: column name */
						__( 'Required column "%s" not found in CSV.', 'qsa-engraving' ),
						$required
					)
				);
			}
		}

		// Parse data rows.
		$rows        = array();
		$line_number = 1; // Header is line 1.
		$qsa_design  = null;
		$revision    = null;

		foreach ( $lines as $line ) {
			++$line_number;

			// Skip empty lines.
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$values = str_getcsv( $line );

			// Skip if not enough values.
			if ( count( $values ) < count( self::REQUIRED_COLUMNS ) ) {
				continue;
			}

			// Map values to column names.
			$row = array();
			foreach ( $header as $i => $column ) {
				$row[ $column ] = isset( $values[ $i ] ) ? trim( $values[ $i ] ) : '';
			}

			// Validate and normalize row.
			$row = $this->sanitize_row( $row );

			// Validate element_type.
			if ( ! in_array( $row['element_type'], Config_Repository::ELEMENT_TYPES, true ) ) {
				return new WP_Error(
					'invalid_element_type',
					sprintf(
						/* translators: 1: element type, 2: line number */
						__( 'Invalid element_type "%1$s" on line %2$d.', 'qsa-engraving' ),
						$row['element_type'],
						$line_number
					)
				);
			}

			// Ensure all rows have same design and revision.
			if ( null === $qsa_design ) {
				$qsa_design = $row['qsa_design'];
				$revision   = $row['revision'];
			} elseif ( $row['qsa_design'] !== $qsa_design || $row['revision'] !== $revision ) {
				return new WP_Error(
					'mixed_designs',
					sprintf(
						/* translators: %d: line number */
						__( 'All rows must have the same qsa_design and revision. Mismatch on line %d.', 'qsa-engraving' ),
						$line_number
					)
				);
			}

			$rows[] = $row;
		}

		if ( empty( $rows ) ) {
			return new WP_Error( 'no_data', __( 'No valid data rows found in CSV.', 'qsa-engraving' ) );
		}

		return array(
			'header' => $header,
			'rows'   => $rows,
		);
	}

	/**
	 * Sanitize a row of data.
	 *
	 * @param array $row Raw row data.
	 * @return array Sanitized row data.
	 */
	private function sanitize_row( array $row ): array {
		return array(
			'qsa_design'   => sanitize_text_field( $row['qsa_design'] ?? '' ),
			'revision'     => sanitize_text_field( $row['revision'] ?? '' ),
			'position'     => absint( $row['position'] ?? 0 ),
			'element_type' => sanitize_text_field( $row['element_type'] ?? '' ),
			'origin_x'     => floatval( $row['origin_x'] ?? 0 ),
			'origin_y'     => floatval( $row['origin_y'] ?? 0 ),
			'rotation'     => intval( $row['rotation'] ?? 0 ),
			'text_height'  => $row['text_height'] ?? null,
			'element_size' => $row['element_size'] ?? null,
		);
	}

	/**
	 * Parse a nullable float value.
	 *
	 * @param mixed $value The value to parse.
	 * @return float|null Float value or null.
	 */
	private function parse_nullable_float( mixed $value ): ?float {
		if ( null === $value || '' === $value || 'NULL' === strtoupper( (string) $value ) ) {
			return null;
		}
		return (float) $value;
	}

	/**
	 * Validate required elements are present in CSV.
	 *
	 * Requires Q0 (qr_code) and at least one module position (M1-M8).
	 *
	 * @param array $rows Parsed CSV rows.
	 * @return true|WP_Error True on success, WP_Error on validation failure.
	 */
	private function validate_required_elements( array $rows ): true|WP_Error {
		$has_qr_code       = false;
		$has_module_id     = false;
		$module_positions  = array();

		foreach ( $rows as $row ) {
			if ( 'qr_code' === $row['element_type'] && 0 === (int) $row['position'] ) {
				$has_qr_code = true;
			}

			if ( 'module_id' === $row['element_type'] ) {
				$has_module_id                       = true;
				$module_positions[ $row['position'] ] = true;
			}
		}

		if ( ! $has_qr_code ) {
			return new WP_Error(
				'missing_qr_code',
				__( 'CSV must include Q0 element (qr_code at position 0).', 'qsa-engraving' )
			);
		}

		if ( ! $has_module_id ) {
			return new WP_Error(
				'missing_module_id',
				__( 'CSV must include at least one module_id element (M1-M8).', 'qsa-engraving' )
			);
		}

		return true;
	}

	/**
	 * Generate preview of import changes.
	 *
	 * Compares CSV rows with existing database rows to determine
	 * what will be added, updated, or deleted.
	 *
	 * @param array $csv_rows Parsed CSV rows.
	 * @param array $existing Existing database rows.
	 * @return array Preview data.
	 */
	private function generate_preview( array $csv_rows, array $existing ): array {
		// Build lookup for existing rows by position:element_type.
		$existing_lookup = array();
		foreach ( $existing as $row ) {
			$key                    = $row['position'] . ':' . $row['element_type'];
			$existing_lookup[ $key ] = $row;
		}

		$additions = array();
		$updates   = array();
		$csv_keys  = array();

		foreach ( $csv_rows as $row ) {
			$key        = $row['position'] . ':' . $row['element_type'];
			$csv_keys[] = $key;

			if ( isset( $existing_lookup[ $key ] ) ) {
				// Will be updated.
				$existing_row = $existing_lookup[ $key ];

				// Check if values actually changed.
				$changed = $this->row_has_changes( $row, $existing_row );

				$updates[] = array(
					'position'     => $row['position'],
					'element_type' => $row['element_type'],
					'changed'      => $changed,
					'old_origin_x' => (float) $existing_row['origin_x'],
					'old_origin_y' => (float) $existing_row['origin_y'],
					'new_origin_x' => (float) $row['origin_x'],
					'new_origin_y' => (float) $row['origin_y'],
				);
			} else {
				// Will be added.
				$additions[] = array(
					'position'     => $row['position'],
					'element_type' => $row['element_type'],
					'origin_x'     => (float) $row['origin_x'],
					'origin_y'     => (float) $row['origin_y'],
				);
			}
		}

		// Find deletions (in DB but not in CSV).
		$deletions = array();
		foreach ( $existing as $row ) {
			$key = $row['position'] . ':' . $row['element_type'];
			if ( ! in_array( $key, $csv_keys, true ) ) {
				$deletions[] = array(
					'position'     => (int) $row['position'],
					'element_type' => $row['element_type'],
				);
			}
		}

		// Count actual changes (updates with changed values).
		$actual_updates = array_filter( $updates, fn( $u ) => $u['changed'] );

		return array(
			'additions'      => $additions,
			'updates'        => $updates,
			'deletions'      => $deletions,
			'summary'        => array(
				'additions'     => count( $additions ),
				'updates'       => count( $actual_updates ),
				'unchanged'     => count( $updates ) - count( $actual_updates ),
				'deletions'     => count( $deletions ),
				'total_csv'     => count( $csv_rows ),
				'total_existing' => count( $existing ),
			),
		);
	}

	/**
	 * Check if a CSV row has changes compared to existing DB row.
	 *
	 * @param array $csv_row CSV row data.
	 * @param array $db_row  Database row data.
	 * @return bool True if changed, false if identical.
	 */
	private function row_has_changes( array $csv_row, array $db_row ): bool {
		// Compare numeric values with tolerance for floating point.
		$tolerance = 0.0001;

		if ( abs( (float) $csv_row['origin_x'] - (float) $db_row['origin_x'] ) > $tolerance ) {
			return true;
		}

		if ( abs( (float) $csv_row['origin_y'] - (float) $db_row['origin_y'] ) > $tolerance ) {
			return true;
		}

		if ( (int) $csv_row['rotation'] !== (int) $db_row['rotation'] ) {
			return true;
		}

		// Compare text_height.
		$csv_height = $this->parse_nullable_float( $csv_row['text_height'] ?? null );
		$db_height  = $db_row['text_height'] !== null ? (float) $db_row['text_height'] : null;

		if ( $csv_height === null && $db_height !== null ) {
			return true;
		}
		if ( $csv_height !== null && $db_height === null ) {
			return true;
		}
		if ( $csv_height !== null && $db_height !== null && abs( $csv_height - $db_height ) > $tolerance ) {
			return true;
		}

		// Compare element_size.
		$csv_size = $this->parse_nullable_float( $csv_row['element_size'] ?? null );
		$db_size  = $db_row['element_size'] !== null ? (float) $db_row['element_size'] : null;

		if ( $csv_size === null && $db_size !== null ) {
			return true;
		}
		if ( $csv_size !== null && $db_size === null ) {
			return true;
		}
		if ( $csv_size !== null && $db_size !== null && abs( $csv_size - $db_size ) > $tolerance ) {
			return true;
		}

		return false;
	}
}
