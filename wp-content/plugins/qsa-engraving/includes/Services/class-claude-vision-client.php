<?php
/**
 * Claude Vision API Client.
 *
 * Communicates with Anthropic Claude API for Micro-ID code decoding.
 *
 * @package QSA_Engraving
 * @since 1.1.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Services;

use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Claude Vision API Client class.
 *
 * Handles communication with Anthropic's Claude API for decoding
 * Micro-ID 5x5 dot matrix codes from smartphone images.
 *
 * @since 1.1.0
 */
class Claude_Vision_Client {

	/**
	 * Anthropic API endpoint.
	 *
	 * @var string
	 */
	public const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/**
	 * Default model to use for vision tasks.
	 *
	 * Claude Opus 4.5 recommended for Micro-ID decoding due to superior
	 * visual reasoning capabilities with complex PCB images.
	 *
	 * @var string
	 */
	public const DEFAULT_MODEL = 'claude-opus-4-5-20251101';

	/**
	 * Maximum tokens for response.
	 *
	 * @var int
	 */
	public const MAX_TOKENS = 1024;

	/**
	 * API version header value.
	 *
	 * @var string
	 */
	public const API_VERSION = '2023-06-01';

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	public const REQUEST_TIMEOUT = 60;

	/**
	 * Maximum image size in bytes (10 MB).
	 *
	 * @var int
	 */
	public const MAX_IMAGE_SIZE_BYTES = 10 * 1024 * 1024;

	/**
	 * Encryption cipher for API key storage.
	 *
	 * @var string
	 */
	private const CIPHER = 'aes-256-cbc';

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	private const SETTINGS_OPTION = 'qsa_engraving_settings';

	/**
	 * API key from settings.
	 *
	 * @var string|null
	 */
	private ?string $api_key = null;

	/**
	 * Model to use for requests.
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * Last error message.
	 *
	 * @var string
	 */
	private string $last_error = '';

	/**
	 * Last API response time in milliseconds.
	 *
	 * @var int|null
	 */
	private ?int $last_response_time_ms = null;

	/**
	 * Last API tokens used.
	 *
	 * @var int|null
	 */
	private ?int $last_tokens_used = null;

	/**
	 * Constructor.
	 *
	 * @param string|null $api_key Optional API key (overrides settings).
	 * @param string|null $model   Optional model name.
	 */
	public function __construct( ?string $api_key = null, ?string $model = null ) {
		$this->api_key = $api_key ?? $this->get_decrypted_api_key();
		$this->model   = $model ?? $this->get_option( 'claude_model', self::DEFAULT_MODEL );
	}

	/**
	 * Get option value from plugin settings.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function get_option( string $key, mixed $default ): mixed {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Get the decrypted API key from settings.
	 *
	 * @return string|null The decrypted API key or null if not set.
	 */
	private function get_decrypted_api_key(): ?string {
		$encrypted_key = $this->get_option( 'claude_api_key', '' );

		if ( empty( $encrypted_key ) ) {
			return null;
		}

		return $this->decrypt( $encrypted_key );
	}

	/**
	 * Encrypt a value using AES-256-CBC.
	 *
	 * Per SECURITY.md, uses WordPress salts for key derivation.
	 *
	 * @param string $value The value to encrypt.
	 * @return string The encrypted and base64-encoded value.
	 */
	public static function encrypt( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$key = substr( wp_salt(), 0, 32 );
		$iv  = substr( wp_salt( 'secure_auth' ), 0, 16 );

		$encrypted = openssl_encrypt( $value, self::CIPHER, $key, 0, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		return base64_encode( $encrypted );
	}

	/**
	 * Decrypt a value encrypted with AES-256-CBC.
	 *
	 * @param string $encrypted_value The base64-encoded encrypted value.
	 * @return string|null The decrypted value or null on failure.
	 */
	private function decrypt( string $encrypted_value ): ?string {
		if ( empty( $encrypted_value ) ) {
			return null;
		}

		$key = substr( wp_salt(), 0, 32 );
		$iv  = substr( wp_salt( 'secure_auth' ), 0, 16 );

		$decoded = base64_decode( $encrypted_value, true );

		if ( false === $decoded ) {
			return null;
		}

		$decrypted = openssl_decrypt( $decoded, self::CIPHER, $key, 0, $iv );

		return false === $decrypted ? null : $decrypted;
	}

	/**
	 * Check if the client has a valid API key configured.
	 *
	 * @return bool True if API key is set.
	 */
	public function has_api_key(): bool {
		return ! empty( $this->api_key );
	}

	/**
	 * Check if Micro-ID decoder is enabled.
	 *
	 * @return bool True if decoder is enabled.
	 */
	public function is_enabled(): bool {
		return (bool) $this->get_option( 'microid_decoder_enabled', false );
	}

	/**
	 * Decode a Micro-ID code from an image using extended thinking.
	 *
	 * Extended thinking gives the model a dedicated "thinking budget" for internal
	 * reasoning before responding, which can improve accuracy on complex visual tasks.
	 *
	 * @since 1.2.0
	 *
	 * @param string $image_base64   Base64-encoded image data.
	 * @param string $mime_type      Image MIME type (image/jpeg, image/png, image/webp).
	 * @param int    $thinking_budget Tokens allocated for thinking (default 10000).
	 * @return array{success: bool, serial: ?string, binary: ?string, parity_valid: ?bool, confidence: ?string, error: ?string, raw_response: ?string, thinking: ?string}|WP_Error
	 */
	public function decode_micro_id_with_thinking( string $image_base64, string $mime_type, int $thinking_budget = 10000 ): array|WP_Error {
		// Reset metrics.
		$this->last_response_time_ms = null;
		$this->last_tokens_used      = null;
		$this->last_error            = '';

		// Validate API key.
		if ( ! $this->has_api_key() ) {
			$this->last_error = 'Claude API key is not configured.';
			return new WP_Error(
				'api_key_missing',
				__( 'Claude API key is not configured. Please add your API key in QSA Engraving settings.', 'qsa-engraving' )
			);
		}

		// Validate MIME type.
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/webp' );
		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			$this->last_error = 'Unsupported image type: ' . $mime_type;
			return new WP_Error(
				'invalid_mime_type',
				sprintf(
					/* translators: %s: MIME type */
					__( 'Unsupported image type: %s. Allowed types: JPEG, PNG, WebP.', 'qsa-engraving' ),
					$mime_type
				)
			);
		}

		// Validate base64 encoding.
		if ( empty( $image_base64 ) ) {
			$this->last_error = 'Image data is empty.';
			return new WP_Error(
				'invalid_image_data',
				__( 'Image data is empty.', 'qsa-engraving' )
			);
		}

		// Validate base64 format.
		$decoded_image = base64_decode( $image_base64, true );
		if ( false === $decoded_image ) {
			$this->last_error = 'Invalid base64 encoding.';
			return new WP_Error(
				'invalid_base64',
				__( 'Image data is not valid base64 encoding.', 'qsa-engraving' )
			);
		}

		// Build the request with extended thinking enabled.
		// Extended thinking requires higher max_tokens to accommodate thinking + response.
		$request_body = array(
			'model'      => $this->model,
			'max_tokens' => 16000, // Higher limit for thinking + response.
			'thinking'   => array(
				'type'          => 'enabled',
				'budget_tokens' => $thinking_budget,
			),
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type'   => 'image',
							'source' => array(
								'type'       => 'base64',
								'media_type' => $mime_type,
								'data'       => $image_base64,
							),
						),
						array(
							'type' => 'text',
							'text' => $this->get_decode_prompt(),
						),
					),
				),
			),
		);

		// Make the API request with extended timeout for thinking.
		$start_time = microtime( true );

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => 120, // Extended timeout for thinking.
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::API_VERSION,
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		$this->last_response_time_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		// Handle request errors.
		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return new WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to connect to Claude API: %s', 'qsa-engraving' ),
					$this->last_error
				)
			);
		}

		// Check HTTP status.
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			$error_msg  = $error_data['error']['message'] ?? "HTTP {$status_code}";

			$this->last_error = $error_msg;

			$error_code = 'api_error';
			if ( $status_code === 401 ) {
				$error_code = 'api_key_invalid';
				$error_msg  = __( 'Invalid API key. Please check your Claude API key in settings.', 'qsa-engraving' );
			} elseif ( $status_code === 429 ) {
				$error_code = 'rate_limited';
				$error_msg  = __( 'API rate limit exceeded. Please try again later.', 'qsa-engraving' );
			} elseif ( $status_code >= 500 ) {
				$error_code = 'api_server_error';
				$error_msg  = __( 'Claude API is temporarily unavailable. Please try again later.', 'qsa-engraving' );
			}

			return new WP_Error( $error_code, $error_msg );
		}

		// Parse response.
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$this->last_error = 'Invalid JSON response from API.';
			return new WP_Error(
				'invalid_response',
				__( 'Received invalid response from Claude API.', 'qsa-engraving' )
			);
		}

		// Track token usage.
		if ( isset( $data['usage']['input_tokens'], $data['usage']['output_tokens'] ) ) {
			$this->last_tokens_used = $data['usage']['input_tokens'] + $data['usage']['output_tokens'];
		}

		// Extract thinking and response text from content blocks.
		$thinking_text = '';
		$response_text = '';
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'] ) ) {
					if ( 'thinking' === $block['type'] && isset( $block['thinking'] ) ) {
						$thinking_text .= $block['thinking'];
					} elseif ( 'text' === $block['type'] && isset( $block['text'] ) ) {
						$response_text .= $block['text'];
					}
				}
			}
		}

		// Parse the decode result and include thinking.
		$result = $this->parse_decode_response( $response_text );
		$result['thinking'] = $thinking_text;

		return $result;
	}

	/**
	 * Decode a Micro-ID code from an image.
	 *
	 * @param string $image_base64 Base64-encoded image data.
	 * @param string $mime_type    Image MIME type (image/jpeg, image/png, image/webp).
	 * @return array{success: bool, serial: ?string, binary: ?string, parity_valid: ?bool, confidence: ?string, error: ?string, raw_response: ?string}|WP_Error
	 */
	public function decode_micro_id( string $image_base64, string $mime_type ): array|WP_Error {
		// Reset metrics.
		$this->last_response_time_ms = null;
		$this->last_tokens_used      = null;
		$this->last_error            = '';

		// Validate API key.
		if ( ! $this->has_api_key() ) {
			$this->last_error = 'Claude API key is not configured.';
			return new WP_Error(
				'api_key_missing',
				__( 'Claude API key is not configured. Please add your API key in QSA Engraving settings.', 'qsa-engraving' )
			);
		}

		// Validate MIME type (JPEG, PNG, WebP only per spec - no GIF support).
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/webp' );
		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			$this->last_error = 'Unsupported image type: ' . $mime_type;
			return new WP_Error(
				'invalid_mime_type',
				sprintf(
					/* translators: %s: MIME type */
					__( 'Unsupported image type: %s. Allowed types: JPEG, PNG, WebP.', 'qsa-engraving' ),
					$mime_type
				)
			);
		}

		// Validate base64 encoding.
		if ( empty( $image_base64 ) ) {
			$this->last_error = 'Image data is empty.';
			return new WP_Error(
				'invalid_image_data',
				__( 'Image data is empty.', 'qsa-engraving' )
			);
		}

		// Pre-decode size guard: Check encoded length before decoding to prevent
		// memory exhaustion from very large payloads. Base64 increases size by ~33%,
		// so max encoded size is approximately MAX_IMAGE_SIZE_BYTES * 1.4.
		$max_encoded_size = (int) ( self::MAX_IMAGE_SIZE_BYTES * 1.4 );
		if ( strlen( $image_base64 ) > $max_encoded_size ) {
			$this->last_error = 'Image data exceeds maximum encoded size.';
			return new WP_Error(
				'image_too_large',
				sprintf(
					/* translators: %s: Maximum file size */
					__( 'Image exceeds maximum size of %s.', 'qsa-engraving' ),
					size_format( self::MAX_IMAGE_SIZE_BYTES )
				)
			);
		}

		// Validate base64 format (strict mode).
		$decoded_image = base64_decode( $image_base64, true );
		if ( false === $decoded_image ) {
			$this->last_error = 'Invalid base64 encoding.';
			return new WP_Error(
				'invalid_base64',
				__( 'Image data is not valid base64 encoding.', 'qsa-engraving' )
			);
		}

		// Validate image size (check decoded size to prevent oversized payloads).
		$image_size = strlen( $decoded_image );
		if ( $image_size > self::MAX_IMAGE_SIZE_BYTES ) {
			$this->last_error = 'Image exceeds maximum size of 10 MB.';
			return new WP_Error(
				'image_too_large',
				sprintf(
					/* translators: %s: Maximum file size */
					__( 'Image exceeds maximum size of %s.', 'qsa-engraving' ),
					size_format( self::MAX_IMAGE_SIZE_BYTES )
				)
			);
		}

		// Build the request.
		$request_body = array(
			'model'      => $this->model,
			'max_tokens' => self::MAX_TOKENS,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type'   => 'image',
							'source' => array(
								'type'         => 'base64',
								'media_type'   => $mime_type,
								'data'         => $image_base64,
							),
						),
						array(
							'type' => 'text',
							'text' => $this->get_decode_prompt(),
						),
					),
				),
			),
		);

		// Make the API request.
		$start_time = microtime( true );

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => self::REQUEST_TIMEOUT,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::API_VERSION,
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		$this->last_response_time_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		// Handle request errors.
		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return new WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to connect to Claude API: %s', 'qsa-engraving' ),
					$this->last_error
				)
			);
		}

		// Check HTTP status.
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			$error_msg  = $error_data['error']['message'] ?? "HTTP {$status_code}";

			$this->last_error = $error_msg;

			// Map common error codes.
			$error_code = 'api_error';
			if ( $status_code === 401 ) {
				$error_code = 'api_key_invalid';
				$error_msg  = __( 'Invalid API key. Please check your Claude API key in settings.', 'qsa-engraving' );
			} elseif ( $status_code === 429 ) {
				$error_code = 'rate_limited';
				$error_msg  = __( 'API rate limit exceeded. Please try again later.', 'qsa-engraving' );
			} elseif ( $status_code >= 500 ) {
				$error_code = 'api_server_error';
				$error_msg  = __( 'Claude API is temporarily unavailable. Please try again later.', 'qsa-engraving' );
			}

			return new WP_Error( $error_code, $error_msg );
		}

		// Parse response.
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$this->last_error = 'Invalid JSON response from API.';
			return new WP_Error(
				'invalid_response',
				__( 'Received invalid response from Claude API.', 'qsa-engraving' )
			);
		}

		// Track token usage.
		if ( isset( $data['usage']['input_tokens'], $data['usage']['output_tokens'] ) ) {
			$this->last_tokens_used = $data['usage']['input_tokens'] + $data['usage']['output_tokens'];
		}

		// Extract the response text.
		$response_text = '';
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'] ) && 'text' === $block['type'] && isset( $block['text'] ) ) {
					$response_text .= $block['text'];
				}
			}
		}

		// Parse the decode result.
		return $this->parse_decode_response( $response_text );
	}

	/**
	 * Parse the decode response from Claude.
	 *
	 * @param string $response_text The raw text response from Claude.
	 * @return array{success: bool, serial: ?string, binary: ?string, parity_valid: ?bool, confidence: ?string, error: ?string, raw_response: string}
	 */
	private function parse_decode_response( string $response_text ): array {
		// Try to extract JSON from the response.
		$json_match = array();
		if ( preg_match( '/\{[^{}]*"success"[^{}]*\}/s', $response_text, $json_match ) ) {
			$decoded = json_decode( $json_match[0], true );

			if ( is_array( $decoded ) && isset( $decoded['success'] ) ) {
				// If JSON explicitly indicates success=false, respect that.
				if ( ! $decoded['success'] ) {
					return array(
						'success'      => false,
						'serial'       => null,
						'binary'       => $decoded['binary'] ?? null,
						'parity_valid' => $decoded['parity_valid'] ?? null,
						'confidence'   => $decoded['confidence'] ?? null,
						'error'        => $decoded['error'] ?? 'Decoding failed.',
						'raw_response' => $response_text,
					);
				}

				// Validate and normalize serial from JSON response.
				// Serial can be string "00123456" or numeric 123456 from JSON.
				$raw_serial = $decoded['serial'] ?? null;

				// Success requires a valid serial - can't succeed without one.
				if ( null === $raw_serial ) {
					return array(
						'success'      => false,
						'serial'       => null,
						'binary'       => $decoded['binary'] ?? null,
						'parity_valid' => $decoded['parity_valid'] ?? null,
						'confidence'   => null,
						'error'        => 'Decode reported success but no serial number was provided.',
						'raw_response' => $response_text,
					);
				}

				// Normalize serial (handles int/string, validates range).
				$normalized_serial = $this->normalize_serial( $raw_serial );
				if ( null === $normalized_serial ) {
					return array(
						'success'      => false,
						'serial'       => null,
						'binary'       => $decoded['binary'] ?? null,
						'parity_valid' => false,
						'confidence'   => null,
						'error'        => 'Decoded serial number is invalid (out of range or wrong format).',
						'raw_response' => $response_text,
					);
				}

				return array(
					'success'      => true,
					'serial'       => $normalized_serial,
					'binary'       => $decoded['binary'] ?? null,
					'parity_valid' => $decoded['parity_valid'] ?? null,
					'confidence'   => $decoded['confidence'] ?? null,
					'error'        => null,
					'raw_response' => $response_text,
				);
			}
		}

		// Fallback: Try to find a serial number pattern, but only if response contains
		// affirmative success indicators (not failure messages).
		$serial_match = array();
		if ( preg_match( '/\b([0-9]{8})\b/', $response_text, $serial_match ) ) {
			$potential_serial = $serial_match[1];

			// Check for negative indicators - if found, don't treat as success.
			$negative_patterns = array(
				'/\bfailed?\b/i',
				'/\bcannot\b/i',
				'/\bcould\s*n[o\']t\b/i',
				'/\bunable\b/i',
				'/\berror\b/i',
				'/\bnot\s+(visible|readable|detected|found)\b/i',
				'/"success"\s*:\s*false/i',
				'/parity\s+(invalid|failed|error)/i',
			);

			$has_negative_indicator = false;
			foreach ( $negative_patterns as $pattern ) {
				if ( preg_match( $pattern, $response_text ) ) {
					$has_negative_indicator = true;
					break;
				}
			}

			// Only accept fallback if no negative indicators AND serial is valid.
			if ( ! $has_negative_indicator && $this->is_valid_serial( $potential_serial ) ) {
				return array(
					'success'      => true,
					'serial'       => $potential_serial,
					'binary'       => null,
					'parity_valid' => null,
					'confidence'   => 'low',
					'error'        => null,
					'raw_response' => $response_text,
				);
			}
		}

		// Could not parse response.
		$this->last_error = 'Could not parse decode response.';
		return array(
			'success'      => false,
			'serial'       => null,
			'binary'       => null,
			'parity_valid' => null,
			'confidence'   => null,
			'error'        => 'Could not decode Micro-ID from the image. Please ensure the code is clearly visible and try again.',
			'raw_response' => $response_text,
		);
	}

	/**
	 * Validate a serial number format and range.
	 *
	 * Accepts mixed input to handle JSON numeric values (e.g., 12345678 vs "12345678").
	 *
	 * @param mixed $serial The serial number to validate.
	 * @return bool True if valid.
	 */
	private function is_valid_serial( mixed $serial ): bool {
		// Handle non-scalar types (arrays, objects, null).
		if ( ! is_scalar( $serial ) ) {
			return false;
		}

		// Convert to string for validation (handles int/float from JSON).
		$serial_str = (string) $serial;

		// Must be exactly 8 digits.
		if ( ! preg_match( '/^[0-9]{8}$/', $serial_str ) ) {
			return false;
		}

		// Convert to integer and check range (1 to 1,048,575 per Micro-ID spec).
		$serial_int = (int) $serial_str;
		return $serial_int >= 1 && $serial_int <= 1048575;
	}

	/**
	 * Normalize a serial to 8-digit string format.
	 *
	 * @param mixed $serial The serial number to normalize.
	 * @return string|null The normalized 8-digit serial or null if invalid.
	 */
	private function normalize_serial( mixed $serial ): ?string {
		// Handle non-scalar types.
		if ( ! is_scalar( $serial ) ) {
			return null;
		}

		// Convert to string (handles int/float from JSON).
		$serial_str = (string) $serial;

		// Must be numeric.
		if ( ! preg_match( '/^[0-9]+$/', $serial_str ) ) {
			return null;
		}

		// Convert to integer and check range.
		$serial_int = (int) $serial_str;
		if ( $serial_int < 1 || $serial_int > 1048575 ) {
			return null;
		}

		// Return as zero-padded 8-digit string.
		return str_pad( (string) $serial_int, 8, '0', STR_PAD_LEFT );
	}

	/**
	 * Get the prompt for decoding Micro-ID codes.
	 *
	 * @return string The decode prompt with full Micro-ID specification.
	 */
	private function get_decode_prompt(): string {
		return <<<'PROMPT'
You are analyzing a photo of an LED module to decode a Quadica 5x5 Micro-ID dot matrix code.

## CRITICAL: Methodical Decoding Required

You MUST follow this exact process. Do not skip steps or rush to conclusions.

## Step 1: Locate the Micro-ID Grid

The Micro-ID is VERY SMALL (1.0mm x 1.0mm, pinhead-sized) with tiny copper/bronze or dark brown dots.
- Located near the product text (e.g., "SZ-04.net", "SABER Z4")
- Between or near the mounting holes on the left side of the module
- IGNORE: Large solder pads, mounting holes, LED pads, thermal vias (these are much larger)

## Step 2: Find the 4 Corner Anchors FIRST

The 4 corners of the 5x5 grid ALWAYS have dots (anchors). Find these first to establish the grid boundaries:
- Top-left corner (0,0) = ANCHOR (always a dot)
- Top-right corner (0,4) = ANCHOR (always a dot)
- Bottom-left corner (4,0) = ANCHOR (always a dot)
- Bottom-right corner (4,4) = ANCHOR (always a dot)

Once you see 4 dots forming a square pattern, you've found the grid boundaries.

## Step 3: Map Each Row Explicitly

Read the grid row by row. Use ● for dot present, ○ for no dot.

**You MUST write out each row like this:**
```
Row 0: [●] [?] [?] [?] [●]  ← Anchors at corners
Row 1: [?] [?] [?] [?] [?]
Row 2: [?] [?] [?] [?] [?]
Row 3: [?] [?] [?] [?] [?]
Row 4: [●] [?] [?] [?] [●]  ← Anchors at corners
```

Replace each [?] with [●] or [○] based on what you see.

## Step 4: Extract the Binary Value

**Bit positions (excluding anchors):**
```
Row 0: [ANCHOR] [Bit19] [Bit18] [Bit17] [ANCHOR]
Row 1: [Bit16]  [Bit15] [Bit14] [Bit13] [Bit12]
Row 2: [Bit11]  [Bit10] [Bit9]  [Bit8]  [Bit7]
Row 3: [Bit6]   [Bit5]  [Bit4]  [Bit3]  [Bit2]
Row 4: [ANCHOR] [Bit1]  [Bit0]  [PARITY][ANCHOR]
```

Write the 20-bit binary string: Bit19 Bit18 ... Bit1 Bit0

## Step 5: Verify Parity (MANDATORY)

Count the 1s in your 20 data bits. Add the parity bit (Row4, Col3).
- Total must be EVEN
- If odd, you made a reading error - go back and recheck

## Step 6: Calculate Decimal

Convert the 20-bit binary to decimal. Zero-pad to 8 digits.

**IMPORTANT:** Most serial numbers are LOW values (under 1000). If you get a large number like 50000+, double-check your reading - you likely misread some positions.

## Response Format

After showing your work above, end with this JSON:
```json
{
  "success": true,
  "serial": "00000208",
  "binary": "00000000000011010000",
  "parity_valid": true,
  "confidence": "high",
  "error": null
}
```

If you cannot decode:
```json
{
  "success": false,
  "serial": null,
  "binary": null,
  "parity_valid": null,
  "confidence": null,
  "error": "Specific reason"
}
```
PROMPT;
	}

	/**
	 * Test the API connection.
	 *
	 * Sends a simple text request to verify the API key works.
	 *
	 * @return array{success: bool, message: string, details: array<string, mixed>}
	 */
	public function test_connection(): array {
		$details = array(
			'has_api_key'   => $this->has_api_key(),
			'model'         => $this->model,
			'endpoint'      => self::API_ENDPOINT,
			'openssl_avail' => function_exists( 'openssl_encrypt' ),
		);

		if ( ! $this->has_api_key() ) {
			return array(
				'success' => false,
				'message' => __( 'Claude API key is not configured.', 'qsa-engraving' ),
				'details' => $details,
			);
		}

		// Send a simple test request.
		$request_body = array(
			'model'      => $this->model,
			'max_tokens' => 50,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => 'Respond with exactly: CONNECTION_OK',
				),
			),
		);

		$start_time = microtime( true );

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::API_VERSION,
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		$response_time_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
		$details['response_time_ms'] = $response_time_ms;

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Connection failed: %s', 'qsa-engraving' ),
					$response->get_error_message()
				),
				'details' => $details,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$details['http_status'] = $status_code;

		if ( $status_code === 200 ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %d: Response time in milliseconds */
					__( 'Successfully connected to Claude API. Response time: %dms', 'qsa-engraving' ),
					$response_time_ms
				),
				'details' => $details,
			);
		}

		$body       = wp_remote_retrieve_body( $response );
		$error_data = json_decode( $body, true );
		$error_msg  = $error_data['error']['message'] ?? "HTTP {$status_code}";

		if ( $status_code === 401 ) {
			$error_msg = __( 'Invalid API key. Please check your Claude API key.', 'qsa-engraving' );
		}

		return array(
			'success' => false,
			'message' => $error_msg,
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
	 * Get the last API response time in milliseconds.
	 *
	 * @return int|null
	 */
	public function get_last_response_time_ms(): ?int {
		return $this->last_response_time_ms;
	}

	/**
	 * Get the last API tokens used.
	 *
	 * @return int|null
	 */
	public function get_last_tokens_used(): ?int {
		return $this->last_tokens_used;
	}

	/**
	 * Decode a Micro-ID code using reference images for improved accuracy.
	 *
	 * This method includes reference images (location markers, sample photos) in the
	 * API request to help Claude identify and decode the Micro-ID code more accurately.
	 *
	 * @since 1.2.0
	 *
	 * @param string $image_base64    Base64-encoded image data to decode.
	 * @param string $mime_type       Image MIME type (image/jpeg, image/png, image/webp).
	 * @param array  $reference_images Array of reference images, each with keys:
	 *                                 - 'path' (string): Absolute file path to the image.
	 *                                 - 'description' (string): Description of what the image shows.
	 * @return array{success: bool, serial: ?string, binary: ?string, parity_valid: ?bool, confidence: ?string, error: ?string, raw_response: ?string}|WP_Error
	 */
	public function decode_with_references( string $image_base64, string $mime_type, array $reference_images ): array|WP_Error {
		// Reset metrics.
		$this->last_response_time_ms = null;
		$this->last_tokens_used      = null;
		$this->last_error            = '';

		// Validate API key.
		if ( ! $this->has_api_key() ) {
			$this->last_error = 'Claude API key is not configured.';
			return new WP_Error(
				'api_key_missing',
				__( 'Claude API key is not configured. Please add your API key in QSA Engraving settings.', 'qsa-engraving' )
			);
		}

		// Validate MIME type.
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/webp' );
		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			$this->last_error = 'Unsupported image type: ' . $mime_type;
			return new WP_Error(
				'invalid_mime_type',
				sprintf(
					/* translators: %s: MIME type */
					__( 'Unsupported image type: %s. Allowed types: JPEG, PNG, WebP.', 'qsa-engraving' ),
					$mime_type
				)
			);
		}

		// Validate base64 encoding.
		if ( empty( $image_base64 ) ) {
			$this->last_error = 'Image data is empty.';
			return new WP_Error(
				'invalid_image_data',
				__( 'Image data is empty.', 'qsa-engraving' )
			);
		}

		// Validate base64 format.
		$decoded_image = base64_decode( $image_base64, true );
		if ( false === $decoded_image ) {
			$this->last_error = 'Invalid base64 encoding.';
			return new WP_Error(
				'invalid_base64',
				__( 'Image data is not valid base64 encoding.', 'qsa-engraving' )
			);
		}

		// Build multi-image request.
		$request_body = $this->build_request_with_references( $image_base64, $mime_type, $reference_images );

		if ( is_wp_error( $request_body ) ) {
			return $request_body;
		}

		// Make the API request.
		$start_time = microtime( true );

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => self::REQUEST_TIMEOUT,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::API_VERSION,
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		$this->last_response_time_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		// Handle request errors.
		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return new WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to connect to Claude API: %s', 'qsa-engraving' ),
					$this->last_error
				)
			);
		}

		// Check HTTP status.
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			$error_msg  = $error_data['error']['message'] ?? "HTTP {$status_code}";

			$this->last_error = $error_msg;

			$error_code = 'api_error';
			if ( $status_code === 401 ) {
				$error_code = 'api_key_invalid';
				$error_msg  = __( 'Invalid API key. Please check your Claude API key in settings.', 'qsa-engraving' );
			} elseif ( $status_code === 429 ) {
				$error_code = 'rate_limited';
				$error_msg  = __( 'API rate limit exceeded. Please try again later.', 'qsa-engraving' );
			} elseif ( $status_code >= 500 ) {
				$error_code = 'api_server_error';
				$error_msg  = __( 'Claude API is temporarily unavailable. Please try again later.', 'qsa-engraving' );
			}

			return new WP_Error( $error_code, $error_msg );
		}

		// Parse response.
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$this->last_error = 'Invalid JSON response from API.';
			return new WP_Error(
				'invalid_response',
				__( 'Received invalid response from Claude API.', 'qsa-engraving' )
			);
		}

		// Track token usage.
		if ( isset( $data['usage']['input_tokens'], $data['usage']['output_tokens'] ) ) {
			$this->last_tokens_used = $data['usage']['input_tokens'] + $data['usage']['output_tokens'];
		}

		// Extract the response text.
		$response_text = '';
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'] ) && 'text' === $block['type'] && isset( $block['text'] ) ) {
					$response_text .= $block['text'];
				}
			}
		}

		// Parse the decode result.
		return $this->parse_decode_response( $response_text );
	}

	/**
	 * Build API request body with reference images.
	 *
	 * @param string $user_image_base64 Base64-encoded user image to decode.
	 * @param string $user_mime_type    MIME type of user image.
	 * @param array  $reference_images  Array of reference images with 'path' and 'description'.
	 * @return array|WP_Error Request body array or error.
	 */
	private function build_request_with_references( string $user_image_base64, string $user_mime_type, array $reference_images ): array|WP_Error {
		$content = array();

		// Add reference images first with descriptions.
		$ref_count = 0;
		foreach ( $reference_images as $ref ) {
			if ( empty( $ref['path'] ) || ! file_exists( $ref['path'] ) ) {
				continue;
			}

			// Read and encode the reference image.
			$ref_data = file_get_contents( $ref['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $ref_data ) {
				continue;
			}

			$ref_base64 = base64_encode( $ref_data );

			// Determine MIME type from extension.
			$ext       = strtolower( pathinfo( $ref['path'], PATHINFO_EXTENSION ) );
			$mime_map  = array(
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png'  => 'image/png',
				'webp' => 'image/webp',
			);
			$ref_mime  = $mime_map[ $ext ] ?? 'image/jpeg';

			++$ref_count;
			$description = $ref['description'] ?? "Reference image {$ref_count}";

			// Add reference image.
			$content[] = array(
				'type' => 'text',
				'text' => "**Reference Image {$ref_count}:** {$description}",
			);
			$content[] = array(
				'type'   => 'image',
				'source' => array(
					'type'       => 'base64',
					'media_type' => $ref_mime,
					'data'       => $ref_base64,
				),
			);
		}

		// Add the user's image to decode.
		$content[] = array(
			'type' => 'text',
			'text' => '**Image to Decode:** This is the customer\'s photo. Find and decode the Micro-ID code in this image.',
		);
		$content[] = array(
			'type'   => 'image',
			'source' => array(
				'type'       => 'base64',
				'media_type' => $user_mime_type,
				'data'       => $user_image_base64,
			),
		);

		// Add the decode prompt with reference context.
		$content[] = array(
			'type' => 'text',
			'text' => $this->get_decode_prompt_with_references( $ref_count ),
		);

		return array(
			'model'      => $this->model,
			'max_tokens' => self::MAX_TOKENS,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $content,
				),
			),
		);
	}

	/**
	 * Get the prompt for decoding with reference images.
	 *
	 * @param int $reference_count Number of reference images included.
	 * @return string The decode prompt.
	 */
	private function get_decode_prompt_with_references( int $reference_count ): string {
		$reference_section = '';
		if ( $reference_count > 0 ) {
			$reference_section = <<<REFS

## Reference Images Provided

You have been provided with {$reference_count} reference image(s) above showing:
- **Location marker images** show exactly WHERE the Micro-ID code is located on this module type (marked with a red box/arrow)
- **Sample photos** show what typical customer smartphone photos of this module look like

Use these references to:
1. Identify the correct location of the Micro-ID code on the module
2. Understand the scale and appearance of the Micro-ID relative to other PCB features
3. Distinguish the tiny Micro-ID dots from larger solder pads, mounting holes, and other features

REFS;
		}

		return <<<PROMPT
{$reference_section}
## Your Task

Analyze the **Image to Decode** (the final image above) and extract the Micro-ID serial number.

## Micro-ID Specification

**Physical Properties:**
- 5x5 grid of 25 dot positions (1.0mm x 1.0mm total footprint - VERY SMALL)
- Dot diameter: 0.10mm, pitch: 0.225mm center-to-center
- Dots appear as copper/bronze OR dark brown/black marks on white PCB surface
- Orientation marker: single fixed dot outside grid, near top-left corner

**Structure:**
- 4 corner anchors (always present as dots): positions (0,0), (0,4), (4,0), (4,4)
- 20 data bit positions + 1 parity bit position
- Parity position: row 4, col 3

**Bit Layout (row-major, MSB first):**
```
Row 0: [ANCHOR] [Bit 19] [Bit 18] [Bit 17] [ANCHOR]
Row 1: [Bit 16] [Bit 15] [Bit 14] [Bit 13] [Bit 12]
Row 2: [Bit 11] [Bit 10] [Bit 9]  [Bit 8]  [Bit 7]
Row 3: [Bit 6]  [Bit 5]  [Bit 4]  [Bit 3]  [Bit 2]
Row 4: [ANCHOR] [Bit 1]  [Bit 0]  [PARITY] [ANCHOR]
```

**Decoding Steps:**
1. Use the reference images to locate the Micro-ID position on the module
2. Find the 4 corner anchor dots to confirm grid boundaries
3. Read each of the 25 positions: dot present = 1, no dot/blank = 0
4. Extract the 20-bit binary value from data positions
5. Verify even parity (total 1s including parity must be even)
6. Convert binary to decimal, format as 8-digit zero-padded string

**Valid Range:** 00000001 to 01048575

## Response Format

Respond ONLY with a JSON object:
```json
{
  "success": true,
  "serial": "00123456",
  "binary": "00000000000111100010",
  "parity_valid": true,
  "confidence": "high",
  "error": null
}
```

If you cannot decode, respond with:
```json
{
  "success": false,
  "serial": null,
  "binary": null,
  "parity_valid": null,
  "confidence": null,
  "error": "Description of why decoding failed"
}
```

Confidence levels: "high" (all dots clear, parity verified), "medium" (some uncertainty), "low" (significant uncertainty)
PROMPT;
	}

	/**
	 * Mask an API key for display.
	 *
	 * @param string $api_key The API key to mask.
	 * @return string The masked key (e.g., "sk-ant-...abc123").
	 */
	public static function mask_api_key( string $api_key ): string {
		if ( empty( $api_key ) ) {
			return '';
		}

		$length = strlen( $api_key );

		if ( $length <= 12 ) {
			return str_repeat( '*', $length );
		}

		$prefix = substr( $api_key, 0, 7 );
		$suffix = substr( $api_key, -6 );

		return $prefix . '...' . $suffix;
	}
}
