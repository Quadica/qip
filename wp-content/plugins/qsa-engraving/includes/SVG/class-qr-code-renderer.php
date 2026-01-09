<?php
/**
 * QR Code Renderer.
 *
 * Generates QR code SVG elements for QSA array-level identification.
 * Uses tc-lib-barcode library with High error correction (H = 30% recovery).
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\SVG;

use WP_Error;
use Com\Tecnick\Barcode\Barcode;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QR Code Renderer class.
 *
 * Renders QR codes as SVG elements for laser engraving:
 * - Uses QRCODE,H (High error correction - 30% recovery)
 * - Configurable size (default 10mm)
 * - Black modules on transparent background
 * - Outputs inline SVG suitable for embedding in larger documents
 *
 * @since 1.0.0
 */
class QR_Code_Renderer {

    /**
     * Default QR code size in millimeters.
     *
     * @var float
     */
    public const DEFAULT_SIZE = 10.0;

    /**
     * QR code module fill color (black for engraving).
     *
     * @var string
     */
    public const MODULE_FILL = '#000000';

    /**
     * QR code type with High error correction.
     * H = High (30% recovery capability)
     *
     * @var string
     */
    private const QR_TYPE = 'QRCODE,H';

    /**
     * Minimum valid QR code size in mm.
     *
     * @var float
     */
    private const MIN_SIZE = 3.0;

    /**
     * Maximum valid QR code size in mm.
     *
     * @var float
     */
    private const MAX_SIZE = 50.0;

    /**
     * Check if the tc-lib-barcode library is available.
     *
     * @return bool True if library is available.
     */
    public static function is_library_available(): bool {
        return class_exists( 'Com\\Tecnick\\Barcode\\Barcode' );
    }

    /**
     * Render QR code as SVG content.
     *
     * Generates SVG elements for a QR code without positioning.
     * The output is suitable for embedding in a larger SVG document.
     *
     * @param string $data Data to encode in the QR code.
     * @param float  $size Target size in millimeters.
     * @return string|WP_Error SVG content (group element) or error.
     */
    public static function render( string $data, float $size = self::DEFAULT_SIZE ): string|WP_Error {
        // Validate inputs.
        if ( empty( $data ) ) {
            return new WP_Error(
                'empty_data',
                __( 'QR code data cannot be empty.', 'qsa-engraving' )
            );
        }

        if ( $size < self::MIN_SIZE || $size > self::MAX_SIZE ) {
            return new WP_Error(
                'invalid_size',
                sprintf(
                    /* translators: 1: Minimum size, 2: Maximum size, 3: Provided size */
                    __( 'QR code size must be between %1$s and %2$s mm. Provided: %3$s mm.', 'qsa-engraving' ),
                    self::MIN_SIZE,
                    self::MAX_SIZE,
                    $size
                )
            );
        }

        // Check library availability.
        if ( ! self::is_library_available() ) {
            return new WP_Error(
                'library_missing',
                __( 'QR code library (tc-lib-barcode) is not available. Ensure vendor directory is deployed.', 'qsa-engraving' )
            );
        }

        try {
            // Generate QR code using tc-lib-barcode.
            $barcode = new Barcode();

            // Get barcode object with H (High) error correction.
            // Using -1 for width/height to get 1:1 module ratio, we'll scale after.
            $bobj = $barcode->getBarcodeObj(
                self::QR_TYPE,
                $data,
                -1,       // Width multiplier (1 unit per module).
                -1,       // Height multiplier (1 unit per module).
                self::MODULE_FILL,
                array( 0, 0, 0, 0 ) // No padding.
            );

            // Get the barcode array to determine actual dimensions.
            $barcode_array = $bobj->getArray();
            $native_width  = $barcode_array['width'];
            $native_height = $barcode_array['height'];

            // Calculate scale factor to achieve target size.
            // QR codes are always square, so use the larger dimension.
            $native_size = max( $native_width, $native_height );
            if ( $native_size <= 0 ) {
                return new WP_Error(
                    'generation_failed',
                    __( 'QR code generation produced invalid dimensions.', 'qsa-engraving' )
                );
            }

            $scale = $size / $native_size;

            // Get inline SVG code (without XML declaration and outer SVG tags).
            // We need to extract just the rect elements from the full SVG.
            $inline_svg = $bobj->getInlineSvgCode();

            // The inline SVG contains rect elements for each module.
            // We need to wrap them in a group with a scale transform.
            // Extract the inner content (everything between <svg> and </svg>).
            $inner_content = self::extract_svg_inner_content( $inline_svg );

            if ( empty( $inner_content ) ) {
                return new WP_Error(
                    'parsing_failed',
                    __( 'Failed to parse QR code SVG output.', 'qsa-engraving' )
                );
            }

            // Build output group with scale transform.
            $output = sprintf(
                '<g transform="scale(%.6f)">%s</g>',
                $scale,
                "\n" . $inner_content . "\n"
            );

            return $output;

        } catch ( \Exception $e ) {
            return new WP_Error(
                'generation_exception',
                sprintf(
                    /* translators: %s: Exception message */
                    __( 'QR code generation failed: %s', 'qsa-engraving' ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Render QR code positioned at specified coordinates.
     *
     * Generates SVG group element with QR code positioned using translate transform.
     * This is the main method for embedding QR codes in array SVG documents.
     *
     * @param string $data Data to encode in the QR code.
     * @param float  $x    X coordinate in millimeters.
     * @param float  $y    Y coordinate in millimeters.
     * @param float  $size Target size in millimeters.
     * @param string $id   Optional group ID attribute.
     * @return string|WP_Error SVG group element or error.
     */
    public static function render_positioned(
        string $data,
        float $x,
        float $y,
        float $size = self::DEFAULT_SIZE,
        string $id = ''
    ): string|WP_Error {
        // Validate coordinates.
        if ( $x < 0 || $y < 0 ) {
            return new WP_Error(
                'invalid_coordinates',
                sprintf(
                    /* translators: 1: X coordinate, 2: Y coordinate */
                    __( 'QR code coordinates must be non-negative. Provided: x=%1$s, y=%2$s.', 'qsa-engraving' ),
                    $x,
                    $y
                )
            );
        }

        // Render the base QR code.
        $qr_content = self::render( $data, $size );

        if ( is_wp_error( $qr_content ) ) {
            return $qr_content;
        }

        // Build ID attribute if provided.
        $id_attr = '';
        if ( ! empty( $id ) ) {
            $id_attr = sprintf( ' id="%s"', esc_attr( $id ) );
        }

        // Build comment with data for debugging.
        $data_comment = sprintf(
            '<!-- QR Code: %s (%.1fmm at %.4f, %.4f) -->',
            esc_html( substr( $data, 0, 50 ) ), // Truncate long data.
            $size,
            $x,
            $y
        );

        // Wrap in positioned group.
        $output = sprintf(
            '%s
<g%s transform="translate(%.4f, %.4f)">
%s
</g>',
            $data_comment,
            $id_attr,
            $x,
            $y,
            $qr_content
        );

        return $output;
    }

    /**
     * Extract inner content from SVG markup.
     *
     * Removes the outer <svg> tags and returns just the inner elements.
     *
     * @param string $svg Full SVG markup.
     * @return string Inner content (rect elements, etc.).
     */
    private static function extract_svg_inner_content( string $svg ): string {
        // Remove XML declaration if present.
        $svg = preg_replace( '/<\?xml[^>]*\?>/', '', $svg );

        // Extract content between <svg...> and </svg>.
        // Use a regex that handles attributes in the svg tag.
        if ( preg_match( '/<svg[^>]*>(.*)<\/svg>/s', $svg, $matches ) ) {
            $content = trim( $matches[1] );

            // Clean up any style or defs elements that might interfere.
            // We only want the rect elements for the QR modules.
            $content = preg_replace( '/<style[^>]*>.*?<\/style>/s', '', $content );
            $content = preg_replace( '/<defs[^>]*>.*?<\/defs>/s', '', $content );

            return trim( $content );
        }

        return '';
    }

    /**
     * Get QR code dimensions for a given data string.
     *
     * Useful for layout calculations before rendering.
     *
     * @param string $data Data to encode.
     * @return array{modules: int, size_at_default: float}|WP_Error Dimension info or error.
     */
    public static function get_dimensions( string $data ): array|WP_Error {
        if ( empty( $data ) ) {
            return new WP_Error(
                'empty_data',
                __( 'QR code data cannot be empty.', 'qsa-engraving' )
            );
        }

        if ( ! self::is_library_available() ) {
            return new WP_Error(
                'library_missing',
                __( 'QR code library (tc-lib-barcode) is not available.', 'qsa-engraving' )
            );
        }

        try {
            $barcode = new Barcode();
            $bobj    = $barcode->getBarcodeObj(
                self::QR_TYPE,
                $data,
                -1,
                -1,
                self::MODULE_FILL,
                array( 0, 0, 0, 0 )
            );

            $barcode_array = $bobj->getArray();

            return array(
                'modules'         => $barcode_array['ncols'], // Number of modules per side.
                'size_at_default' => self::DEFAULT_SIZE,
            );
        } catch ( \Exception $e ) {
            return new WP_Error(
                'dimensions_failed',
                sprintf(
                    /* translators: %s: Exception message */
                    __( 'Failed to calculate QR code dimensions: %s', 'qsa-engraving' ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Validate QR code data before encoding.
     *
     * Checks that the data can be encoded in a QR code.
     *
     * @param string $data Data to validate.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    public static function validate_data( string $data ): bool|WP_Error {
        if ( empty( $data ) ) {
            return new WP_Error(
                'empty_data',
                __( 'QR code data cannot be empty.', 'qsa-engraving' )
            );
        }

        // QR codes can encode up to ~4296 alphanumeric characters.
        // For URLs (binary mode), limit is ~2953 bytes.
        // Our QSA IDs will be very short (e.g., "quadi.ca/cube00076" = 18 chars).
        $max_length = 2000; // Conservative limit.

        if ( strlen( $data ) > $max_length ) {
            return new WP_Error(
                'data_too_long',
                sprintf(
                    /* translators: 1: Maximum length, 2: Actual length */
                    __( 'QR code data exceeds maximum length of %1$d characters. Provided: %2$d characters.', 'qsa-engraving' ),
                    $max_length,
                    strlen( $data )
                )
            );
        }

        return true;
    }
}
