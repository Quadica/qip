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
     * QR code module stroke color (green for engraving layer differentiation).
     *
     * @var string
     */
    public const MODULE_STROKE = '#00E000';

    /**
     * QR code module stroke width as fraction of module size.
     * 0.1 = 10% of module size for visible outlines.
     *
     * @var float
     */
    public const STROKE_WIDTH_RATIO = 0.1;

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
        // Validate data (checks empty and length limits).
        $validation = self::validate_data( $data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Validate size.
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
                self::MODULE_STROKE,
                array( 0, 0, 0, 0 ) // No padding.
            );

            // Get the barcode array to determine actual dimensions.
            $barcode_array = $bobj->getArray();
            $native_size   = (int) $barcode_array['ncols']; // QR codes are square.

            if ( $native_size <= 0 ) {
                return new WP_Error(
                    'generation_failed',
                    __( 'QR code generation produced invalid dimensions.', 'qsa-engraving' )
                );
            }

            // Calculate module size in mm.
            $module_size  = $size / $native_size;
            $stroke_width = $module_size * self::STROKE_WIDTH_RATIO;

            // Get the bars array and expand to individual modules.
            $bars    = $barcode_array['bars'];
            $modules = self::expand_bars_to_modules( $bars );

            // Generate SVG rect elements for each module (outline squares).
            $rects = array();
            foreach ( $modules as $module ) {
                $x = $module[0] * $module_size;
                $y = $module[1] * $module_size;

                $rects[] = sprintf(
                    '<rect x="%.4f" y="%.4f" width="%.4f" height="%.4f" fill="none" stroke="%s" stroke-width="%.4f"/>',
                    $x,
                    $y,
                    $module_size,
                    $module_size,
                    self::MODULE_STROKE,
                    $stroke_width
                );
            }

            // Build output group.
            $output = sprintf(
                '<g>%s</g>',
                "\n\t\t" . implode( "\n\t\t", $rects ) . "\n\t"
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
     * Note: Coordinates specify the CENTER of the QR code, not the top-left corner.
     * The translate transform is adjusted by half the size to center the QR code.
     *
     * @param string $data Data to encode in the QR code.
     * @param float  $x    X coordinate of QR code CENTER in millimeters.
     * @param float  $y    Y coordinate of QR code CENTER in millimeters.
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

        // Calculate top-left position from center coordinates.
        $half_size = $size / 2.0;
        $top_left_x = $x - $half_size;
        $top_left_y = $y - $half_size;

        // Build comment with data for debugging (shows center coordinates).
        $data_comment = sprintf(
            '<!-- QR Code: %s (%.1fmm centered at %.4f, %.4f) -->',
            esc_html( substr( $data, 0, 50 ) ), // Truncate long data.
            $size,
            $x,
            $y
        );

        // Wrap in positioned group (translate to top-left corner).
        $output = sprintf(
            '%s
<g%s transform="translate(%.4f, %.4f)">
%s
</g>',
            $data_comment,
            $id_attr,
            $top_left_x,
            $top_left_y,
            $qr_content
        );

        return $output;
    }

    /**
     * Expand horizontal bars into individual 1x1 module positions.
     *
     * The tc-lib-barcode library outputs bars as merged horizontal runs.
     * This method expands each bar into individual module coordinates
     * for outline rendering.
     *
     * @param array $bars Array of bars, each as [x, y, width, height].
     * @return array Array of module positions, each as [x, y].
     */
    private static function expand_bars_to_modules( array $bars ): array {
        $modules = array();

        foreach ( $bars as $bar ) {
            $x      = (int) $bar[0];
            $y      = (int) $bar[1];
            $width  = (int) $bar[2];
            $height = (int) $bar[3];

            // Expand the bar into individual 1x1 modules.
            for ( $dy = 0; $dy < $height; $dy++ ) {
                for ( $dx = 0; $dx < $width; $dx++ ) {
                    $modules[] = array( $x + $dx, $y + $dy );
                }
            }
        }

        return $modules;
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
                self::MODULE_STROKE,
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
