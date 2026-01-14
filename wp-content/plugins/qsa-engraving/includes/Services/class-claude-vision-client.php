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
	 * Updated to Claude Sonnet 4.5 per Anthropic docs (2025).
	 *
	 * @var string
	 */
	public const DEFAULT_MODEL = 'claude-sonnet-4-5-20250929';

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
You are analyzing a smartphone photo of an LED module PCB to decode a Quadica 5x5 Micro-ID dot matrix code.

## How to Find the Micro-ID Code

**IMPORTANT - Distinguishing Micro-ID from other PCB features:**
- The Micro-ID is VERY SMALL: only 1.0mm x 1.0mm total (about the size of a pinhead)
- Dot color varies depending on PCB layers beneath:
  - **Copper/reddish/bronze** when engraved over a copper plane (exposes copper)
  - **Dark brown/black** when engraved over FR4 substrate (no copper beneath)
- The dots are much SMALLER and more closely spaced than solder pads, vias, or mounting holes
- Usually located near product text/branding on the module
- Look for a tight 5x5 grid pattern with uniform spacing - NOT the larger scattered circuit elements

**What to IGNORE (these are NOT the Micro-ID):**
- Large circular solder pads (typically 1-3mm diameter, silver/gray)
- Mounting holes (large, often 2-4mm)
- Thermal vias (larger dots, often in arrays but with different spacing)
- LED dome areas
- Through-hole component pads

## Micro-ID Specification

**Physical Properties:**
- 5x5 grid of 25 dot positions (1.0mm x 1.0mm total footprint)
- Dot diameter: 0.10mm, pitch: 0.225mm center-to-center
- Dots appear as copper/bronze OR dark brown/black marks on white PCB surface
- Orientation marker: single fixed dot outside grid, near top-left corner

**Structure:**
- 4 corner anchors (always present as dots): positions (0,0), (0,4), (4,0), (4,4)
- 20 data bit positions + 1 parity bit position
- Parity position: row 4, col 3
- Some positions will have dots (1), others will be blank/unmarked (0)

**Bit Layout (row-major, MSB first):**
```
Row 0: [ANCHOR] [Bit 19] [Bit 18] [Bit 17] [ANCHOR]
Row 1: [Bit 16] [Bit 15] [Bit 14] [Bit 13] [Bit 12]
Row 2: [Bit 11] [Bit 10] [Bit 9]  [Bit 8]  [Bit 7]
Row 3: [Bit 6]  [Bit 5]  [Bit 4]  [Bit 3]  [Bit 2]
Row 4: [ANCHOR] [Bit 1]  [Bit 0]  [PARITY] [ANCHOR]
```

**Decoding Steps:**
1. Scan the image for a tiny 5x5 grid of small dots (copper/bronze or dark brown/black colored)
2. Locate the 4 corner anchors (always present) to confirm grid boundaries
3. Find the orientation marker (single dot outside grid near top-left)
4. Read each of the 25 positions: 1 = dot present, 0 = no dot/blank
5. Extract the 20-bit binary value from data positions
6. Verify even parity (total count of 1s including parity bit must be even)
7. Convert binary to decimal, format as 8-digit zero-padded string

**Valid Range:** 00000001 to 01048575 (serial 0 is not used)

## Your Task

1. Locate the small Micro-ID code (~1mm square grid of tiny dots) - ignore large circuit features
2. Identify all 25 grid positions using corner anchors as reference
3. Determine which positions have dots (1) vs are blank (0)
4. Extract and verify the serial number

Respond ONLY with a JSON object in this exact format:
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

If you cannot decode the image, respond with:
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

Confidence levels:
- "high": All dots clearly visible, parity verified
- "medium": Some dots unclear but decode seems correct
- "low": Significant uncertainty in reading
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
