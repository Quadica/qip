<?php
/**
 * POC Test: Micro-ID Decode with Reference Images.
 *
 * Tests the decode_with_references() method to validate that including
 * reference images improves decode accuracy.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/qsa-engraving/tests/smoke/test-microid-decode-poc.php
 *
 * Options (via environment variables):
 *   TEST_IMAGE     - Path to image to decode (default: sample-1)
 *   WITH_REFS      - Include reference images: 1 or 0 (default: 1)
 *   REFS_ONLY      - Only use location marker, not samples: 1 or 0 (default: 0)
 *
 * @package QSA_Engraving
 * @since 1.2.0
 */

declare(strict_types=1);

// Ensure we're in WP-CLI context.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "This script must be run via WP-CLI.\n";
	exit( 1 );
}

use Quadica\QSA_Engraving\Services\Claude_Vision_Client;

// Plugin paths.
$plugin_dir       = dirname( __DIR__, 2 );
$reference_dir    = dirname( $plugin_dir, 3 ) . '/docs/sample-data/reference-images';

echo "=== Micro-ID Decode POC Test ===\n\n";
echo "Plugin directory: {$plugin_dir}\n";
echo "Reference images: {$reference_dir}\n\n";

// Check reference images exist.
$location_marker = "{$reference_dir}/sz04-location-marker.jpg";
$sample_1        = "{$reference_dir}/sz04-sample-1.jpg";
$sample_2        = "{$reference_dir}/sz04-sample-2.jpg";
$sample_3        = "{$reference_dir}/sz04-sample-3.jpg";

echo "Checking reference images:\n";
$ref_files = array(
	'Location marker' => $location_marker,
	'Sample 1'        => $sample_1,
	'Sample 2'        => $sample_2,
	'Sample 3'        => $sample_3,
);

foreach ( $ref_files as $name => $path ) {
	$exists = file_exists( $path );
	$size   = $exists ? size_format( filesize( $path ) ) : 'N/A';
	$status = $exists ? '✓' : '✗';
	echo "  {$status} {$name}: {$size}\n";
}
echo "\n";

// Parse options.
$with_refs  = getenv( 'WITH_REFS' ) !== '0';
$refs_only  = getenv( 'REFS_ONLY' ) === '1';
$test_image = getenv( 'TEST_IMAGE' );

// Determine which image to decode.
if ( empty( $test_image ) ) {
	$test_image = $sample_1; // Default to sample-1.
} elseif ( ! file_exists( $test_image ) ) {
	// Try relative to reference dir.
	$test_image = "{$reference_dir}/{$test_image}";
}

if ( ! file_exists( $test_image ) ) {
	echo "ERROR: Test image not found: {$test_image}\n";
	exit( 1 );
}

echo "Test image: {$test_image}\n";
echo "With references: " . ( $with_refs ? 'YES' : 'NO' ) . "\n";
echo "Location marker only: " . ( $refs_only ? 'YES' : 'NO' ) . "\n\n";

// Create the client.
$client = new Claude_Vision_Client();

if ( ! $client->has_api_key() ) {
	echo "ERROR: Claude API key is not configured.\n";
	echo "Please add your API key in QSA Engraving settings.\n";
	exit( 1 );
}

echo "Claude API key: configured\n";
echo "Model: claude-opus-4-5-20251101\n\n";

// Load the test image.
$image_data   = file_get_contents( $test_image );
$image_base64 = base64_encode( $image_data );
$mime_type    = 'image/jpeg';

echo "Image loaded: " . size_format( strlen( $image_data ) ) . "\n\n";

// Build reference images array.
$reference_images = array();
if ( $with_refs ) {
	// Always include location marker.
	if ( file_exists( $location_marker ) ) {
		$reference_images[] = array(
			'path'        => $location_marker,
			'description' => 'Location marker showing where the Micro-ID code is located on the SZ-04 module (marked with red box)',
		);
	}

	// Optionally include sample photos.
	if ( ! $refs_only ) {
		// Include samples that are NOT the test image.
		$samples = array( $sample_1, $sample_2, $sample_3 );
		$sample_num = 1;
		foreach ( $samples as $sample ) {
			if ( file_exists( $sample ) && realpath( $sample ) !== realpath( $test_image ) ) {
				$reference_images[] = array(
					'path'        => $sample,
					'description' => "Sample smartphone photo #{$sample_num} of an SZ-04 module showing typical image quality",
				);
				++$sample_num;
			}
		}
	}

	echo "Reference images to include: " . count( $reference_images ) . "\n";
	foreach ( $reference_images as $i => $ref ) {
		echo "  " . ( $i + 1 ) . ". " . basename( $ref['path'] ) . "\n";
	}
	echo "\n";
}

// Run the decode.
echo "Sending decode request...\n";
$start_time = microtime( true );

if ( $with_refs && ! empty( $reference_images ) ) {
	$result = $client->decode_with_references( $image_base64, $mime_type, $reference_images );
} else {
	$result = $client->decode_micro_id( $image_base64, $mime_type );
}

$elapsed = round( ( microtime( true ) - $start_time ) * 1000 );

echo "\n=== RESULTS ===\n\n";

if ( is_wp_error( $result ) ) {
	echo "ERROR: " . $result->get_error_message() . "\n";
	echo "Error code: " . $result->get_error_code() . "\n";
} else {
	echo "Success: " . ( $result['success'] ? 'YES' : 'NO' ) . "\n";

	if ( $result['success'] ) {
		echo "Serial: {$result['serial']}\n";
		echo "Binary: {$result['binary']}\n";
		echo "Parity valid: " . ( $result['parity_valid'] ? 'YES' : 'NO' ) . "\n";
		echo "Confidence: {$result['confidence']}\n";
	} else {
		echo "Error: {$result['error']}\n";
	}

	if ( ! empty( $result['raw_response'] ) ) {
		echo "\nRaw response:\n";
		echo "---\n";
		echo $result['raw_response'] . "\n";
		echo "---\n";
	}
}

echo "\nMetrics:\n";
echo "  Response time: {$client->get_last_response_time_ms()}ms\n";
echo "  Tokens used: {$client->get_last_tokens_used()}\n";
echo "  Total elapsed: {$elapsed}ms\n";

echo "\n=== TEST COMPLETE ===\n";
