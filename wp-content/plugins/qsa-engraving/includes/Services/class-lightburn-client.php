<?php
/**
 * LightBurn UDP Client.
 *
 * Communicates with LightBurn software via UDP for loading SVG files.
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
 * LightBurn UDP Client class.
 *
 * Handles communication with LightBurn software via UDP protocol.
 * Supports PING command to check connectivity and LOADFILE command
 * to load SVG files for engraving.
 *
 * @since 1.0.0
 */
class LightBurn_Client {

	/**
	 * Default LightBurn host IP.
	 *
	 * @var string
	 */
	public const DEFAULT_HOST = '127.0.0.1';

	/**
	 * Default output port (send commands).
	 *
	 * @var int
	 */
	public const DEFAULT_OUT_PORT = 19840;

	/**
	 * Default input port (receive responses).
	 *
	 * @var int
	 */
	public const DEFAULT_IN_PORT = 19841;

	/**
	 * Default timeout in seconds.
	 *
	 * @var int
	 */
	public const DEFAULT_TIMEOUT = 2;

	/**
	 * Maximum retry attempts for commands.
	 *
	 * @var int
	 */
	public const MAX_RETRY_ATTEMPTS = 3;

	/**
	 * Host IP address.
	 *
	 * @var string
	 */
	private string $host;

	/**
	 * Output port for sending commands.
	 *
	 * @var int
	 */
	private int $out_port;

	/**
	 * Input port for receiving responses.
	 *
	 * @var int
	 */
	private int $in_port;

	/**
	 * Timeout in seconds.
	 *
	 * @var int
	 */
	private int $timeout;

	/**
	 * Output socket resource.
	 *
	 * @var resource|false|null
	 */
	private $out_socket = null;

	/**
	 * Input socket resource.
	 *
	 * @var resource|false|null
	 */
	private $in_socket = null;

	/**
	 * Whether sockets are initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Last error message.
	 *
	 * @var string
	 */
	private string $last_error = '';

	/**
	 * Constructor.
	 *
	 * @param string|null $host     Optional host IP address.
	 * @param int|null    $out_port Optional output port.
	 * @param int|null    $in_port  Optional input port.
	 * @param int|null    $timeout  Optional timeout in seconds.
	 */
	public function __construct(
		?string $host = null,
		?int $out_port = null,
		?int $in_port = null,
		?int $timeout = null
	) {
		$this->host     = $host ?? $this->get_option( 'lightburn_host', self::DEFAULT_HOST );
		$this->out_port = $out_port ?? (int) $this->get_option( 'lightburn_out_port', self::DEFAULT_OUT_PORT );
		$this->in_port  = $in_port ?? (int) $this->get_option( 'lightburn_in_port', self::DEFAULT_IN_PORT );
		$this->timeout  = $timeout ?? (int) $this->get_option( 'lightburn_timeout', self::DEFAULT_TIMEOUT );
	}

	/**
	 * Get option value with fallback.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function get_option( string $key, mixed $default ): mixed {
		$settings = get_option( 'qsa_engraving_settings', array() );
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Initialize sockets for UDP communication.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function init_sockets(): bool|WP_Error {
		if ( $this->initialized ) {
			return true;
		}

		// Check if socket extension is available.
		if ( ! function_exists( 'socket_create' ) ) {
			$this->last_error = 'PHP sockets extension is not available.';
			return new WP_Error(
				'sockets_unavailable',
				__( 'PHP sockets extension is not available. Please enable it in php.ini.', 'qsa-engraving' )
			);
		}

		// Create output socket.
		$this->out_socket = @socket_create( AF_INET, SOCK_DGRAM, SOL_UDP );
		if ( false === $this->out_socket ) {
			$this->last_error = socket_strerror( socket_last_error() );
			return new WP_Error(
				'socket_create_failed',
				sprintf(
					/* translators: %s: Socket error message */
					__( 'Failed to create output socket: %s', 'qsa-engraving' ),
					$this->last_error
				)
			);
		}

		// Create input socket.
		$this->in_socket = @socket_create( AF_INET, SOCK_DGRAM, SOL_UDP );
		if ( false === $this->in_socket ) {
			$this->last_error = socket_strerror( socket_last_error() );
			$this->close();
			return new WP_Error(
				'socket_create_failed',
				sprintf(
					/* translators: %s: Socket error message */
					__( 'Failed to create input socket: %s', 'qsa-engraving' ),
					$this->last_error
				)
			);
		}

		// Bind input socket to receive responses.
		// Bind to 0.0.0.0 (all local interfaces) to receive replies from remote LightBurn.
		// The $this->host is only used for sending commands, not for binding.
		$bind_result = @socket_bind( $this->in_socket, '0.0.0.0', $this->in_port );
		if ( false === $bind_result ) {
			$this->last_error = socket_strerror( socket_last_error( $this->in_socket ) );
			$this->close();
			return new WP_Error(
				'socket_bind_failed',
				sprintf(
					/* translators: 1: Port number, 2: Socket error message */
					__( 'Failed to bind to port %1$d: %2$s', 'qsa-engraving' ),
					$this->in_port,
					$this->last_error
				)
			);
		}

		// Set receive timeout.
		$timeout_config = array(
			'sec'  => $this->timeout,
			'usec' => 0,
		);
		socket_set_option( $this->in_socket, SOL_SOCKET, SO_RCVTIMEO, $timeout_config );

		$this->initialized = true;
		return true;
	}

	/**
	 * Send a command to LightBurn and wait for response.
	 *
	 * @param string $command The command to send.
	 * @return array{success: bool, response: string, error: string}
	 */
	public function send_command( string $command ): array {
		$init = $this->init_sockets();
		if ( is_wp_error( $init ) ) {
			return array(
				'success'  => false,
				'response' => '',
				'error'    => $init->get_error_message(),
			);
		}

		// Send command via output socket.
		$sent = @socket_sendto(
			$this->out_socket,
			$command,
			strlen( $command ),
			0,
			$this->host,
			$this->out_port
		);

		if ( false === $sent ) {
			$this->last_error = socket_strerror( socket_last_error( $this->out_socket ) );
			return array(
				'success'  => false,
				'response' => '',
				'error'    => sprintf(
					/* translators: %s: Socket error message */
					__( 'Failed to send command: %s', 'qsa-engraving' ),
					$this->last_error
				),
			);
		}

		// Wait for response.
		$response = '';
		$from     = '';
		$port     = 0;

		$result = @socket_recvfrom(
			$this->in_socket,
			$response,
			1024,
			0,
			$from,
			$port
		);

		if ( false === $result ) {
			$error_code = socket_last_error( $this->in_socket );
			// Check for timeout (EAGAIN/EWOULDBLOCK).
			if ( in_array( $error_code, array( 11, 35 ), true ) ) {
				return array(
					'success'  => false,
					'response' => '',
					'error'    => __( 'Timeout waiting for LightBurn response. Is LightBurn running?', 'qsa-engraving' ),
				);
			}

			$this->last_error = socket_strerror( $error_code );
			return array(
				'success'  => false,
				'response' => '',
				'error'    => sprintf(
					/* translators: %s: Socket error message */
					__( 'Failed to receive response: %s', 'qsa-engraving' ),
					$this->last_error
				),
			);
		}

		return array(
			'success'  => true,
			'response' => trim( $response ),
			'error'    => '',
		);
	}

	/**
	 * Ping LightBurn to check if it's running and responsive.
	 *
	 * @return bool True if LightBurn responds.
	 */
	public function ping(): bool {
		$result = $this->send_command( 'PING' );
		return $result['success'];
	}

	/**
	 * Load an SVG file in LightBurn.
	 *
	 * @param string $filepath The file path to load (must be accessible from LightBurn).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function load_file( string $filepath ): bool|WP_Error {
		// Validate filepath.
		if ( empty( $filepath ) ) {
			return new WP_Error(
				'empty_filepath',
				__( 'File path cannot be empty.', 'qsa-engraving' )
			);
		}

		// Convert to Windows-style path if needed (LightBurn runs on Windows).
		$filepath = str_replace( '/', '\\', $filepath );

		// Send LOADFILE command.
		$command = "LOADFILE:{$filepath}";
		$result  = $this->send_command( $command );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'loadfile_failed',
				$result['error']
			);
		}

		return true;
	}

	/**
	 * Load file with retry logic.
	 *
	 * @param string $filepath     The file path to load.
	 * @param int    $max_attempts Maximum retry attempts.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function load_file_with_retry( string $filepath, int $max_attempts = self::MAX_RETRY_ATTEMPTS ): bool|WP_Error {
		$last_error = null;

		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			$result = $this->load_file( $filepath );

			if ( true === $result ) {
				return true;
			}

			$last_error = $result;

			// Wait before retry (exponential backoff).
			if ( $attempt < $max_attempts ) {
				sleep( $attempt );
			}
		}

		return $last_error ?? new WP_Error(
			'loadfile_failed',
			__( 'Failed to load file after maximum retry attempts.', 'qsa-engraving' )
		);
	}

	/**
	 * Test connection to LightBurn.
	 *
	 * @return array{success: bool, message: string, details: array}
	 */
	public function test_connection(): array {
		$details = array(
			'host'         => $this->host,
			'out_port'     => $this->out_port,
			'in_port'      => $this->in_port,
			'timeout'      => $this->timeout,
			'sockets_ext'  => function_exists( 'socket_create' ),
		);

		// Check if sockets extension is available.
		if ( ! function_exists( 'socket_create' ) ) {
			return array(
				'success' => false,
				'message' => __( 'PHP sockets extension is not available.', 'qsa-engraving' ),
				'details' => $details,
			);
		}

		// Try to ping LightBurn.
		$ping_result = $this->ping();
		$details['ping_success'] = $ping_result;

		if ( $ping_result ) {
			return array(
				'success' => true,
				'message' => __( 'Successfully connected to LightBurn.', 'qsa-engraving' ),
				'details' => $details,
			);
		}

		return array(
			'success' => false,
			'message' => $this->last_error ?: __( 'Could not connect to LightBurn. Please ensure LightBurn is running.', 'qsa-engraving' ),
			'details' => $details,
		);
	}

	/**
	 * Get the last error message.
	 *
	 * @return string
	 */
	public function get_last_error(): string {
		return $this->last_error;
	}

	/**
	 * Get the configured host.
	 *
	 * @return string
	 */
	public function get_host(): string {
		return $this->host;
	}

	/**
	 * Get the configured output port.
	 *
	 * @return int
	 */
	public function get_out_port(): int {
		return $this->out_port;
	}

	/**
	 * Get the configured input port.
	 *
	 * @return int
	 */
	public function get_in_port(): int {
		return $this->in_port;
	}

	/**
	 * Check if LightBurn integration is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = get_option( 'qsa_engraving_settings', array() );
		return (bool) ( $settings['lightburn_enabled'] ?? false );
	}

	/**
	 * Close sockets and clean up.
	 *
	 * @return void
	 */
	public function close(): void {
		if ( is_resource( $this->in_socket ) || $this->in_socket instanceof \Socket ) {
			@socket_close( $this->in_socket );
		}
		if ( is_resource( $this->out_socket ) || $this->out_socket instanceof \Socket ) {
			@socket_close( $this->out_socket );
		}
		$this->in_socket   = null;
		$this->out_socket  = null;
		$this->initialized = false;
	}

	/**
	 * Destructor - ensure sockets are closed.
	 */
	public function __destruct() {
		$this->close();
	}
}
