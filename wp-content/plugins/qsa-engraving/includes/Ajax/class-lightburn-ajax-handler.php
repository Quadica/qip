<?php
/**
 * LightBurn AJAX Handler.
 *
 * Handles AJAX requests for LightBurn integration and SVG file management.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Ajax;

use Quadica\QSA_Engraving\Services\LightBurn_Client;
use Quadica\QSA_Engraving\Services\SVG_File_Manager;
use Quadica\QSA_Engraving\Services\SVG_Generator;
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
 * Handles AJAX requests for LightBurn integration.
 *
 * Provides endpoints for:
 * - Testing LightBurn connection
 * - Generating and loading SVG files
 * - Resending SVG files to LightBurn
 * - Managing LightBurn settings
 *
 * @since 1.0.0
 */
class LightBurn_Ajax_Handler {

	/**
	 * AJAX nonce action name.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'qsa_engraving_nonce';

	/**
	 * LightBurn Client instance.
	 *
	 * @var LightBurn_Client|null
	 */
	private ?LightBurn_Client $lightburn_client = null;

	/**
	 * SVG File Manager instance.
	 *
	 * @var SVG_File_Manager
	 */
	private SVG_File_Manager $file_manager;

	/**
	 * SVG Generator instance.
	 *
	 * @var SVG_Generator
	 */
	private SVG_Generator $svg_generator;

	/**
	 * Batch Repository instance.
	 *
	 * @var Batch_Repository
	 */
	private Batch_Repository $batch_repository;

	/**
	 * Serial Repository instance.
	 *
	 * @var Serial_Repository
	 */
	private Serial_Repository $serial_repository;

	/**
	 * LED Code Resolver instance.
	 *
	 * @var LED_Code_Resolver
	 */
	private LED_Code_Resolver $led_code_resolver;

	/**
	 * Constructor.
	 *
	 * @param Batch_Repository  $batch_repository  Batch repository.
	 * @param Serial_Repository $serial_repository Serial repository.
	 * @param LED_Code_Resolver $led_code_resolver LED code resolver.
	 */
	public function __construct(
		Batch_Repository $batch_repository,
		Serial_Repository $serial_repository,
		LED_Code_Resolver $led_code_resolver
	) {
		$this->batch_repository  = $batch_repository;
		$this->serial_repository = $serial_repository;
		$this->led_code_resolver = $led_code_resolver;
		$this->file_manager      = new SVG_File_Manager();
		$this->svg_generator     = new SVG_Generator();
	}

	/**
	 * Get or create LightBurn client.
	 *
	 * @return LightBurn_Client
	 */
	private function get_lightburn_client(): LightBurn_Client {
		if ( null === $this->lightburn_client ) {
			$this->lightburn_client = new LightBurn_Client();
		}
		return $this->lightburn_client;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_qsa_test_lightburn', array( $this, 'handle_test_connection' ) );
		add_action( 'wp_ajax_qsa_generate_svg', array( $this, 'handle_generate_svg' ) );
		add_action( 'wp_ajax_qsa_load_svg', array( $this, 'handle_load_svg' ) );
		add_action( 'wp_ajax_qsa_resend_svg', array( $this, 'handle_resend_svg' ) );
		add_action( 'wp_ajax_qsa_get_lightburn_status', array( $this, 'handle_get_status' ) );
		add_action( 'wp_ajax_qsa_save_lightburn_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_qsa_clear_test_data', array( $this, 'handle_clear_test_data' ) );
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
	 * Check if we're in a development/staging environment.
	 *
	 * @return bool
	 */
	private function is_development_environment(): bool {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$dev_patterns = array( 'env-', 'staging', '.dev', '.test', 'localhost', '.local' );

		foreach ( $dev_patterns as $pattern ) {
			if ( stripos( $host, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle test LightBurn connection request.
	 *
	 * @return void
	 */
	public function handle_test_connection(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		$client = $this->get_lightburn_client();
		$result = $client->test_connection();

		if ( $result['success'] ) {
			$this->send_success(
				$result['details'],
				$result['message']
			);
		} else {
			$this->send_error(
				$result['message'],
				'connection_failed'
			);
		}
	}

	/**
	 * Handle generate SVG request.
	 *
	 * Generates an SVG file for a batch/QSA and optionally loads it in LightBurn.
	 *
	 * @return void
	 */
	public function handle_generate_svg(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$batch_id     = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$qsa_sequence = isset( $_POST['qsa_sequence'] ) ? absint( $_POST['qsa_sequence'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		$auto_load    = isset( $_POST['auto_load'] ) && filter_var( $_POST['auto_load'], FILTER_VALIDATE_BOOLEAN );

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

		// Get serial numbers for these modules.
		$serials = $this->serial_repository->get_by_batch( $batch_id, 'reserved' );
		$qsa_serials = array_filter(
			$serials,
			fn( $s ) => (int) $s['qsa_sequence'] === $qsa_sequence
		);

		if ( empty( $qsa_serials ) ) {
			$this->send_error( __( 'No reserved serials found. Please start the row first.', 'qsa-engraving' ), 'no_serials' );
			return;
		}

		// Build module data for SVG generation.
		$svg_result = $this->generate_svg_for_qsa( $qsa_modules, $qsa_serials, $batch_id );
		if ( is_wp_error( $svg_result ) ) {
			$this->send_error( $svg_result->get_error_message(), $svg_result->get_error_code() );
			return;
		}

		// Save SVG to file.
		$file_result = $this->file_manager->save_svg( $svg_result['svg'], $batch_id, $qsa_sequence );
		if ( is_wp_error( $file_result ) ) {
			$this->send_error( $file_result->get_error_message(), $file_result->get_error_code() );
			return;
		}

		// Auto-load in LightBurn if requested.
		$lightburn_result = null;
		if ( $auto_load ) {
			$lightburn_result = $this->load_in_lightburn( $file_result['lightburn_path'] );
		}

		$this->send_success(
			array(
				'batch_id'        => $batch_id,
				'qsa_sequence'    => $qsa_sequence,
				'filename'        => $file_result['filename'],
				'path'            => $file_result['path'],
				'lightburn_path'  => $file_result['lightburn_path'],
				'size'            => $file_result['size'],
				'module_count'    => count( $qsa_modules ),
				'lightburn_loaded' => $lightburn_result['success'] ?? false,
				'lightburn_error' => $lightburn_result['error'] ?? null,
			),
			__( 'SVG generated successfully.', 'qsa-engraving' )
		);
	}

	/**
	 * Generate SVG content for a QSA.
	 *
	 * @param array $modules    The modules for this QSA.
	 * @param array $serials    The reserved serials.
	 * @param int   $batch_id   The batch ID.
	 * @return array{svg: string, design: string}|WP_Error
	 */
	private function generate_svg_for_qsa( array $modules, array $serials, int $batch_id ): array|WP_Error {
		// Get QSA design from first module SKU.
		$first_sku = $modules[0]['module_sku'] ?? '';
		if ( empty( $first_sku ) ) {
			return new WP_Error( 'missing_sku', __( 'Module SKU is missing.', 'qsa-engraving' ) );
		}

		// Parse SKU to get design and revision.
		$config_loader = $this->svg_generator->get_config_loader();
		$parsed        = $config_loader->parse_sku( $first_sku );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		// Build serial map by position.
		$serial_map = array();
		foreach ( $serials as $serial ) {
			$position = (int) $serial['array_position'];
			$serial_map[ $position ] = $serial['serial_number'];
		}

		// Build module data for each position.
		$module_data = array();
		$led_code_errors = array();

		foreach ( $modules as $module ) {
			$position = (int) $module['array_position'];
			$serial   = $serial_map[ $position ] ?? null;

			if ( ! $serial ) {
				continue; // Skip modules without serials.
			}

			// Resolve LED codes.
			$led_codes = $this->resolve_led_codes( $module );

			// Collect LED code resolution errors but continue processing.
			if ( is_wp_error( $led_codes ) ) {
				$led_code_errors[] = sprintf(
					'Position %d (%s): %s',
					$position,
					$module['module_sku'] ?? 'unknown',
					$led_codes->get_error_message()
				);
				continue; // Skip this module - cannot engrave without LED codes.
			}

			$module_data[ $position ] = array(
				'serial_number' => $serial,
				'module_id'     => $module['module_sku'],
				'led_codes'     => $led_codes,
			);
		}

		// If any LED code errors occurred, block SVG generation with detailed error.
		if ( ! empty( $led_code_errors ) ) {
			return new WP_Error(
				'led_code_resolution_failed',
				sprintf(
					/* translators: %s: List of errors */
					__( 'Cannot generate SVG - LED code resolution failed: %s', 'qsa-engraving' ),
					implode( '; ', $led_code_errors )
				)
			);
		}

		if ( empty( $module_data ) ) {
			return new WP_Error( 'no_module_data', __( 'No module data could be built.', 'qsa-engraving' ) );
		}

		// Generate SVG.
		$svg = $this->svg_generator->generate_array(
			$module_data,
			$parsed['design'],
			$parsed['revision']
		);

		if ( is_wp_error( $svg ) ) {
			return $svg;
		}

		return array(
			'svg'      => $svg,
			'design'   => $parsed['design'],
			'revision' => $parsed['revision'],
		);
	}

	/**
	 * Resolve LED codes for a module.
	 *
	 * Uses the LED_Code_Resolver to query Order BOM and product metadata
	 * for the 3-character LED shortcodes.
	 *
	 * @param array $module The module data.
	 * @return array|WP_Error Array of LED shortcodes or WP_Error if resolution fails.
	 */
	private function resolve_led_codes( array $module ): array|WP_Error {
		// Check if module already has LED codes in its data (from batch sorter).
		if ( ! empty( $module['led_codes'] ) ) {
			if ( is_array( $module['led_codes'] ) ) {
				$led_codes = array_filter( $module['led_codes'], fn( $code ) => ! empty( $code ) && '---' !== $code );
				if ( ! empty( $led_codes ) ) {
					return array_values( $led_codes );
				}
			} elseif ( is_string( $module['led_codes'] ) ) {
				$led_codes = array_map( 'trim', explode( ',', $module['led_codes'] ) );
				$led_codes = array_filter( $led_codes, fn( $code ) => ! empty( $code ) && '---' !== $code );
				if ( ! empty( $led_codes ) ) {
					return array_values( $led_codes );
				}
			}
		}

		// Use LED_Code_Resolver to query Order BOM for LED shortcodes.
		$order_id   = (int) ( $module['order_id'] ?? 0 );
		$module_sku = $module['module_sku'] ?? '';

		if ( $order_id <= 0 || empty( $module_sku ) ) {
			return new WP_Error(
				'missing_module_data',
				sprintf(
					/* translators: %s: Module SKU */
					__( 'Missing order ID or module SKU for module: %s', 'qsa-engraving' ),
					$module_sku ?: 'unknown'
				)
			);
		}

		$led_codes = $this->led_code_resolver->get_led_codes_for_module( $order_id, $module_sku );

		if ( is_wp_error( $led_codes ) ) {
			return $led_codes;
		}

		if ( empty( $led_codes ) ) {
			return new WP_Error(
				'no_led_codes',
				sprintf(
					/* translators: 1: Order ID, 2: Module SKU */
					__( 'No LED codes found for order %1$d, module %2$s.', 'qsa-engraving' ),
					$order_id,
					$module_sku
				)
			);
		}

		return $led_codes;
	}

	/**
	 * Load SVG file in LightBurn.
	 *
	 * @param string $lightburn_path The path to load in LightBurn.
	 * @return array{success: bool, error: string|null}
	 */
	private function load_in_lightburn( string $lightburn_path ): array {
		$client = $this->get_lightburn_client();

		// Check if LightBurn is enabled.
		if ( ! $client->is_enabled() ) {
			return array(
				'success' => false,
				'error'   => __( 'LightBurn integration is disabled.', 'qsa-engraving' ),
			);
		}

		// Load file with retry.
		$result = $client->load_file_with_retry( $lightburn_path );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'error'   => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'error'   => null,
		);
	}

	/**
	 * Handle load SVG in LightBurn request.
	 *
	 * @return void
	 */
	public function handle_load_svg(): void {
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

		// Check if file exists.
		$existing_file = $this->file_manager->get_existing_file( $batch_id, $qsa_sequence );
		if ( ! $existing_file ) {
			$this->send_error( __( 'SVG file not found. Please generate it first.', 'qsa-engraving' ), 'file_not_found' );
			return;
		}

		// Load in LightBurn.
		$result = $this->load_in_lightburn( $existing_file['lightburn_path'] );

		if ( ! $result['success'] ) {
			$this->send_error( $result['error'] ?? __( 'Failed to load file in LightBurn.', 'qsa-engraving' ), 'load_failed' );
			return;
		}

		$this->send_success(
			array(
				'batch_id'       => $batch_id,
				'qsa_sequence'   => $qsa_sequence,
				'filename'       => $existing_file['filename'],
				'lightburn_path' => $existing_file['lightburn_path'],
			),
			__( 'SVG loaded in LightBurn.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle resend SVG request.
	 *
	 * Uses existing SVG file if available, otherwise regenerates.
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

		// Check if file exists.
		$existing_file = $this->file_manager->get_existing_file( $batch_id, $qsa_sequence );
		if ( ! $existing_file ) {
			$this->send_error( __( 'SVG file not found. Please regenerate.', 'qsa-engraving' ), 'file_not_found' );
			return;
		}

		// Load in LightBurn.
		$result = $this->load_in_lightburn( $existing_file['lightburn_path'] );

		if ( ! $result['success'] ) {
			$this->send_error(
				$result['error'] ?? __( 'Failed to resend file to LightBurn.', 'qsa-engraving' ),
				'resend_failed'
			);
			return;
		}

		$this->send_success(
			array(
				'batch_id'       => $batch_id,
				'qsa_sequence'   => $qsa_sequence,
				'filename'       => $existing_file['filename'],
				'lightburn_path' => $existing_file['lightburn_path'],
				'resent'         => true,
			),
			__( 'SVG resent to LightBurn.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle get LightBurn status request.
	 *
	 * @return void
	 */
	public function handle_get_status(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		$client     = $this->get_lightburn_client();
		$dir_status = $this->file_manager->get_status();

		// Check connection status (without failing if not connected).
		$is_connected = false;
		if ( $client->is_enabled() ) {
			$is_connected = $client->ping();
		}

		$settings = get_option( 'qsa_engraving_settings', array() );

		$this->send_success(
			array(
				'enabled'       => $client->is_enabled(),
				'connected'     => $is_connected,
				'host'          => $client->get_host(),
				'out_port'      => $client->get_out_port(),
				'in_port'       => $client->get_in_port(),
				'directory'     => $dir_status,
				'auto_load'     => (bool) ( $settings['lightburn_auto_load'] ?? true ),
			)
		);
	}

	/**
	 * Handle save LightBurn settings request.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// Get current settings.
		$settings = get_option( 'qsa_engraving_settings', array() );

		// Update LightBurn settings.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		if ( isset( $_POST['lightburn_enabled'] ) ) {
			$settings['lightburn_enabled'] = filter_var( $_POST['lightburn_enabled'], FILTER_VALIDATE_BOOLEAN );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		if ( isset( $_POST['lightburn_host'] ) ) {
			$host = sanitize_text_field( wp_unslash( $_POST['lightburn_host'] ) );
			// Validate IP address format.
			if ( ! empty( $host ) && ! filter_var( $host, FILTER_VALIDATE_IP ) ) {
				$this->send_error( __( 'Invalid IP address format.', 'qsa-engraving' ), 'invalid_ip' );
				return;
			}
			$settings['lightburn_host'] = $host ?: LightBurn_Client::DEFAULT_HOST;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		if ( isset( $_POST['lightburn_out_port'] ) ) {
			$port = absint( $_POST['lightburn_out_port'] );
			if ( $port < 1 || $port > 65535 ) {
				$this->send_error( __( 'Invalid port number. Must be between 1 and 65535.', 'qsa-engraving' ), 'invalid_port' );
				return;
			}
			$settings['lightburn_out_port'] = $port;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		if ( isset( $_POST['lightburn_in_port'] ) ) {
			$port = absint( $_POST['lightburn_in_port'] );
			if ( $port < 1 || $port > 65535 ) {
				$this->send_error( __( 'Invalid port number. Must be between 1 and 65535.', 'qsa-engraving' ), 'invalid_port' );
				return;
			}
			$settings['lightburn_in_port'] = $port;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		if ( isset( $_POST['lightburn_timeout'] ) ) {
			$timeout = absint( $_POST['lightburn_timeout'] );
			$settings['lightburn_timeout'] = max( 1, min( 30, $timeout ) ); // Clamp between 1-30 seconds.
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		if ( isset( $_POST['lightburn_auto_load'] ) ) {
			$settings['lightburn_auto_load'] = filter_var( $_POST['lightburn_auto_load'], FILTER_VALIDATE_BOOLEAN );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		if ( isset( $_POST['svg_output_dir'] ) ) {
			$dir = sanitize_text_field( wp_unslash( $_POST['svg_output_dir'] ) );
			// Basic validation - don't save invalid paths.
			if ( ! empty( $dir ) && ! preg_match( '/^[A-Za-z]:|^\//', $dir ) ) {
				$this->send_error( __( 'Invalid directory path format.', 'qsa-engraving' ), 'invalid_path' );
				return;
			}
			$settings['svg_output_dir'] = $dir;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified.
		if ( isset( $_POST['lightburn_path_prefix'] ) ) {
			$settings['lightburn_path_prefix'] = sanitize_text_field( wp_unslash( $_POST['lightburn_path_prefix'] ) );
		}

		// Save settings.
		update_option( 'qsa_engraving_settings', $settings );

		$this->send_success(
			$settings,
			__( 'Settings saved successfully.', 'qsa-engraving' )
		);
	}

	/**
	 * Handle clear test data request.
	 *
	 * Clears all data from serial_numbers, engraving_batches, and engraved_modules tables.
	 * Only available when WP_DEBUG is enabled.
	 *
	 * @return void
	 */
	public function handle_clear_test_data(): void {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), $verify->get_error_code(), 403 );
			return;
		}

		// Only allow in development/staging environments.
		if ( ! $this->is_development_environment() ) {
			$this->send_error( __( 'This action is only available in development environments.', 'qsa-engraving' ), 'not_dev_environment', 403 );
			return;
		}

		global $wpdb;

		// Get table names.
		$serials_table = $wpdb->prefix . 'quad_serial_numbers';
		$batches_table = $wpdb->prefix . 'quad_engraving_batches';
		$modules_table = $wpdb->prefix . 'quad_engraved_modules';

		// Truncate tables in correct order (modules first due to foreign key constraints).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$modules_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$serials_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$batches_table}" );

		// Clean up any SVG files.
		$this->file_manager->cleanup_all_files();

		$this->send_success(
			null,
			__( 'All test data cleared successfully.', 'qsa-engraving' )
		);
	}
}
