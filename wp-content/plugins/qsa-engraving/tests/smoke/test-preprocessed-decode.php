<?php
/**
 * Test: Decode preprocessed Micro-ID crop images.
 *
 * Tests if Claude can reliably decode Micro-ID from preprocessed/cropped images.
 * Compares Opus vs Haiku models.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/qsa-engraving/tests/smoke/test-preprocessed-decode.php
 *
 * Options (via environment variables):
 *   IMAGE_PATH  - Path to preprocessed crop image
 *   MODEL       - Model to use: opus, haiku, or both (default: both)
 *
 * @package QSA_Engraving
 * @since 1.2.0
 */

// Ensure we're in WP-CLI context.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "This script must be run via WP-CLI.\n";
	exit( 1 );
}

echo "=== Preprocessed Micro-ID Decode Test ===\n\n";

// Get settings.
$image_path = getenv( 'IMAGE_PATH' );
$model_choice = getenv( 'MODEL' ) ?: 'both';

// Default test image.
if ( empty( $image_path ) ) {
	// Look for sample-5 crop in standard locations.
	$possible_paths = array(
		'/tmp/microid-test/sample-5/sz04-sample-5_crop_0.jpg',
		dirname( __DIR__, 2 ) . '/assets/reference-images/test-crop.jpg',
	);

	foreach ( $possible_paths as $path ) {
		if ( file_exists( $path ) ) {
			$image_path = $path;
			break;
		}
	}
}

if ( empty( $image_path ) || ! file_exists( $image_path ) ) {
	echo "ERROR: No test image found.\n";
	echo "Set IMAGE_PATH environment variable to the preprocessed crop image.\n";
	exit( 1 );
}

echo "Image: {$image_path}\n";
echo "Size: " . size_format( filesize( $image_path ) ) . "\n";
echo "Model: {$model_choice}\n\n";

// Load image.
$image_data = file_get_contents( $image_path );
$image_base64 = base64_encode( $image_data );

// Get API key from settings.
$settings = get_option( 'qsa_engraving_settings', array() );
$encrypted_key = $settings['claude_api_key'] ?? '';

if ( empty( $encrypted_key ) ) {
	echo "ERROR: Claude API key not configured.\n";
	exit( 1 );
}

// Decrypt API key (simplified - in production use the client class).
$encryption_key = defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : wp_salt( 'logged_in' );
$encryption_key = hash( 'sha256', $encryption_key, true );
$data = base64_decode( $encrypted_key );
$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
$iv = substr( $data, 0, $iv_length );
$encrypted = substr( $data, $iv_length );
$api_key = openssl_decrypt( $encrypted, 'aes-256-cbc', $encryption_key, OPENSSL_RAW_DATA, $iv );

if ( empty( $api_key ) ) {
	echo "ERROR: Could not decrypt API key.\n";
	exit( 1 );
}

echo "API Key: configured\n\n";

// Simplified prompt for preprocessed crops.
$prompt = <<<'PROMPT'
This image shows a cropped and enhanced Micro-ID 5x5 dot matrix code from an LED module.

The image has been preprocessed to isolate the Micro-ID area. You should see a 5x5 grid of dots (copper/bronze colored or dark dots on light background).

## Decode Instructions

1. **Find the 4 corner anchors** - all 4 corners always have dots
2. **Map each cell** - Row by row, mark each position as dot (1) or empty (0)
3. **Extract binary** - Read the 20 data bits (excluding corners and parity)
4. **Verify parity** - Count 1s in data bits + parity bit must be even
5. **Convert to decimal** - Zero-pad to 8 digits

## Grid Layout
```
Row 0: [ANCHOR] [Bit19] [Bit18] [Bit17] [ANCHOR]
Row 1: [Bit16]  [Bit15] [Bit14] [Bit13] [Bit12]
Row 2: [Bit11]  [Bit10] [Bit9]  [Bit8]  [Bit7]
Row 3: [Bit6]   [Bit5]  [Bit4]  [Bit3]  [Bit2]
Row 4: [ANCHOR] [Bit1]  [Bit0]  [PARITY][ANCHOR]
```

Show your work, then respond with JSON:
```json
{
  "success": true/false,
  "serial": "00000XXX",
  "binary": "20-bit string",
  "parity_valid": true/false,
  "confidence": "high/medium/low",
  "error": null or "reason"
}
```
PROMPT;

// Models to test.
$models = array();
if ( $model_choice === 'opus' || $model_choice === 'both' ) {
	$models['opus'] = 'claude-opus-4-5-20251101';
}
if ( $model_choice === 'haiku' || $model_choice === 'both' ) {
	$models['haiku'] = 'claude-3-5-haiku-20241022';
}

// Test each model.
foreach ( $models as $name => $model_id ) {
	echo "--- Testing {$name} ({$model_id}) ---\n\n";

	$request_body = array(
		'model'      => $model_id,
		'max_tokens' => 2048,
		'messages'   => array(
			array(
				'role'    => 'user',
				'content' => array(
					array(
						'type'   => 'image',
						'source' => array(
							'type'         => 'base64',
							'media_type'   => 'image/jpeg',
							'data'         => $image_base64,
						),
					),
					array(
						'type' => 'text',
						'text' => $prompt,
					),
				),
			),
		),
	);

	$start_time = microtime( true );

	$response = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 120,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body'    => wp_json_encode( $request_body ),
		)
	);

	$elapsed_ms = round( ( microtime( true ) - $start_time ) * 1000 );

	if ( is_wp_error( $response ) ) {
		echo "ERROR: " . $response->get_error_message() . "\n\n";
		continue;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( $status_code !== 200 ) {
		echo "HTTP Error: {$status_code}\n";
		echo "Response: " . substr( $body, 0, 500 ) . "\n\n";
		continue;
	}

	// Extract response text.
	$response_text = '';
	if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
		foreach ( $data['content'] as $block ) {
			if ( $block['type'] === 'text' ) {
				$response_text .= $block['text'];
			}
		}
	}

	// Parse JSON from response.
	$json_match = array();
	if ( preg_match( '/```json\s*(\{.*?\})\s*```/s', $response_text, $json_match ) ) {
		$result = json_decode( $json_match[1], true );
	} elseif ( preg_match( '/(\{[^{}]*"success"[^{}]*\})/s', $response_text, $json_match ) ) {
		$result = json_decode( $json_match[1], true );
	} else {
		$result = null;
	}

	// Display results.
	echo "Response time: {$elapsed_ms}ms\n";

	$usage = $data['usage'] ?? array();
	$input_tokens = $usage['input_tokens'] ?? 'N/A';
	$output_tokens = $usage['output_tokens'] ?? 'N/A';
	echo "Tokens: {$input_tokens} in / {$output_tokens} out\n\n";

	if ( $result ) {
		echo "RESULT:\n";
		echo "  Success: " . ( $result['success'] ? 'YES' : 'NO' ) . "\n";
		if ( $result['success'] ) {
			echo "  Serial: {$result['serial']}\n";
			echo "  Binary: {$result['binary']}\n";
			echo "  Parity: " . ( $result['parity_valid'] ? 'VALID' : 'INVALID' ) . "\n";
			echo "  Confidence: {$result['confidence']}\n";
		} else {
			echo "  Error: {$result['error']}\n";
		}
	} else {
		echo "Could not parse JSON result.\n";
	}

	echo "\n--- Full Response ---\n";
	echo substr( $response_text, 0, 2000 );
	if ( strlen( $response_text ) > 2000 ) {
		echo "\n...[truncated]...";
	}
	echo "\n\n";
}

echo "=== TEST COMPLETE ===\n";
