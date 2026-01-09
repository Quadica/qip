<?php
/**
 * Data Matrix Renderer.
 *
 * Generates Data Matrix ECC 200 barcodes as SVG elements using tecnickcom/tc-lib-barcode.
 * Encodes module URLs (quadi.ca/{serial_number}) for scanning.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\SVG;

use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Data Matrix Renderer class.
 *
 * Generates Data Matrix ECC 200 barcodes:
 * - Uses tecnickcom/tc-lib-barcode library
 * - Encodes URLs in format: https://quadi.ca/{serial_number}
 * - Outputs SVG rect elements or path elements
 *
 * Default dimensions: 14mm x 6.5mm (per QSA specification).
 *
 * @since 1.0.0
 */
class Datamatrix_Renderer {

    /**
     * Default Data Matrix width in millimeters.
     *
     * @var float
     */
    public const DEFAULT_WIDTH = 14.0;

    /**
     * Default Data Matrix height in millimeters.
     *
     * @var float
     */
    public const DEFAULT_HEIGHT = 6.5;

    /**
     * URL base for serial number lookup.
     *
     * @var string
     */
    public const URL_BASE = 'https://quadi.ca/';

    /**
     * Fill color for barcode modules (black for engraving).
     *
     * @var string
     */
    public const MODULE_FILL = '#000000';

    /**
     * Check if tc-lib-barcode library is available.
     *
     * @return bool True if library is loaded.
     */
    public static function is_library_available(): bool {
        return class_exists( '\Com\Tecnick\Barcode\Barcode' );
    }

    /**
     * Generate Data Matrix barcode from a serial number.
     *
     * @param string $serial_number 8-digit serial number.
     * @param float  $width         Target width in mm (default 14.0).
     * @param float  $height        Target height in mm (default 6.5).
     * @return string|WP_Error SVG group element or WP_Error.
     */
    public static function render(
        string $serial_number,
        float $width = self::DEFAULT_WIDTH,
        float $height = self::DEFAULT_HEIGHT
    ): string|WP_Error {
        // Validate serial number format.
        if ( ! preg_match( '/^[0-9]{8}$/', $serial_number ) ) {
            return new WP_Error(
                'invalid_serial',
                sprintf(
                    /* translators: %s: Serial number */
                    __( 'Invalid serial number format: %s. Must be 8 digits.', 'qsa-engraving' ),
                    $serial_number
                )
            );
        }

        // Build the URL to encode.
        $url = self::URL_BASE . $serial_number;

        // Check if library is available.
        if ( ! self::is_library_available() ) {
            // Return placeholder for testing/development.
            return self::render_placeholder( $serial_number, $width, $height );
        }

        return self::render_with_library( $url, $width, $height );
    }

    /**
     * Render Data Matrix using tc-lib-barcode library.
     *
     * @param string $data   Data to encode.
     * @param float  $width  Target width in mm.
     * @param float  $height Target height in mm.
     * @return string|WP_Error SVG markup or WP_Error.
     */
    private static function render_with_library(
        string $data,
        float $width,
        float $height
    ): string|WP_Error {
        try {
            $barcode = new \Com\Tecnick\Barcode\Barcode();

            // Generate Data Matrix ECC 200 in rectangular format.
            // DATAMATRIX,R specifies rectangular shape (vs S for square).
            // Rectangular format is required for 14mm x 6.5mm dimension per QSA spec.
            $bobj = $barcode->getBarcodeObj(
                'DATAMATRIX,R',
                $data,
                -1, // Width multiplication factor.
                -1, // Height multiplication factor.
                'black',
                array( 0, 0, 0, 0 ) // No padding.
            );

            // Get the raw barcode grid data as 2D array.
            // getGridArray() returns array<int, array<int, string>> which is more reliable
            // than getGrid() which returns a newline-separated string.
            $grid = $bobj->getGridArray();
            if ( empty( $grid ) ) {
                return new WP_Error(
                    'barcode_generation_failed',
                    __( 'Failed to generate Data Matrix barcode.', 'qsa-engraving' )
                );
            }

            // Convert grid to SVG rects with proper scaling.
            return self::grid_to_svg( $grid, $width, $height );

        } catch ( \Exception $e ) {
            return new WP_Error(
                'barcode_exception',
                sprintf(
                    /* translators: %s: Error message */
                    __( 'Barcode generation error: %s', 'qsa-engraving' ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Convert barcode grid to SVG rect elements.
     *
     * @param array $grid   2D array from getGridArray() - array<int, array<int, string>>.
     *                      Each inner array element is '0' (space) or '1' (bar).
     * @param float $width  Target width in mm.
     * @param float $height Target height in mm.
     * @return string SVG group markup.
     */
    private static function grid_to_svg( array $grid, float $width, float $height ): string {
        $rows = count( $grid );
        // getGridArray returns array<int, array<int, string>>, so count the first row.
        $cols = ! empty( $grid[0] ) ? count( $grid[0] ) : 0;

        if ( 0 === $rows || 0 === $cols ) {
            return '<g><!-- Empty barcode grid --></g>';
        }

        // Calculate module size to fit target dimensions.
        $module_width  = $width / $cols;
        $module_height = $height / $rows;

        // Use square modules based on smaller dimension.
        $module_size = min( $module_width, $module_height );

        // Calculate actual barcode size and centering offset.
        $actual_width  = $module_size * $cols;
        $actual_height = $module_size * $rows;
        $offset_x      = ( $width - $actual_width ) / 2;
        $offset_y      = ( $height - $actual_height ) / 2;

        // Build SVG rects for filled modules.
        $rects = array();
        for ( $row = 0; $row < $rows; $row++ ) {
            $row_data = $grid[ $row ];
            for ( $col = 0; $col < $cols; $col++ ) {
                // getGridArray returns '1' for bars, '0' for spaces.
                if ( isset( $row_data[ $col ] ) && '1' === $row_data[ $col ] ) {
                    $x = $offset_x + ( $col * $module_size );
                    $y = $offset_y + ( $row * $module_size );

                    $rects[] = sprintf(
                        '<rect x="%.4f" y="%.4f" width="%.4f" height="%.4f" fill="%s" stroke="none"/>',
                        $x,
                        $y,
                        $module_size,
                        $module_size,
                        self::MODULE_FILL
                    );
                }
            }
        }

        return '<g>' . "\n  " . implode( "\n  ", $rects ) . "\n" . '</g>';
    }

    /**
     * Render placeholder when library is not available.
     *
     * Generates a placeholder rectangle with text for development/testing.
     *
     * @param string $serial_number Serial number for label.
     * @param float  $width         Width in mm.
     * @param float  $height        Height in mm.
     * @return string SVG placeholder markup.
     */
    private static function render_placeholder(
        string $serial_number,
        float $width,
        float $height
    ): string {
        $svg  = '<g>' . "\n";
        $svg .= sprintf(
            '  <rect x="0" y="0" width="%.4f" height="%.4f" fill="none" stroke="%s" stroke-width="0.1" stroke-dasharray="0.5,0.5"/>',
            $width,
            $height,
            self::MODULE_FILL
        );
        $svg .= "\n";
        $svg .= sprintf(
            '  <text font-family="Roboto Thin, sans-serif" font-size="1.4" text-anchor="middle" x="%.4f" y="%.4f" fill="#888888">DATA MATRIX</text>',
            $width / 2,
            $height / 2 + 0.5
        );
        $svg .= "\n" . '</g>';

        return $svg;
    }

    /**
     * Render Data Matrix at a specific position.
     *
     * @param string $serial_number 8-digit serial number.
     * @param float  $x             X position (top-left corner).
     * @param float  $y             Y position (top-left corner).
     * @param float  $width         Width in mm.
     * @param float  $height        Height in mm.
     * @param string $id            Optional ID attribute.
     * @return string|WP_Error SVG group with transform or WP_Error.
     */
    public static function render_positioned(
        string $serial_number,
        float $x,
        float $y,
        float $width = self::DEFAULT_WIDTH,
        float $height = self::DEFAULT_HEIGHT,
        string $id = ''
    ): string|WP_Error {
        $barcode = self::render( $serial_number, $width, $height );
        if ( is_wp_error( $barcode ) ) {
            return $barcode;
        }

        $id_attr = $id ? sprintf( ' id="%s"', esc_attr( $id ) ) : '';

        return sprintf(
            '<g%s transform="translate(%.4f, %.4f)">%s</g>',
            $id_attr,
            $x,
            $y,
            "\n" . $barcode . "\n"
        );
    }

    /**
     * Generate the URL that would be encoded for a serial number.
     *
     * @param string $serial_number 8-digit serial number.
     * @return string Full URL.
     */
    public static function get_url( string $serial_number ): string {
        return self::URL_BASE . $serial_number;
    }

    /**
     * Validate that a Data Matrix can be generated for a serial.
     *
     * @param string $serial_number Serial number to validate.
     * @return true|WP_Error True if valid, WP_Error if not.
     */
    public static function validate_serial( string $serial_number ): true|WP_Error {
        if ( ! preg_match( '/^[0-9]{8}$/', $serial_number ) ) {
            return new WP_Error(
                'invalid_format',
                sprintf(
                    /* translators: %s: Serial number */
                    __( 'Serial number "%s" must be exactly 8 digits.', 'qsa-engraving' ),
                    $serial_number
                )
            );
        }

        return true;
    }

    /**
     * Get library status information.
     *
     * @return array{available: bool, version: string|null, message: string}
     */
    public static function get_library_status(): array {
        if ( ! self::is_library_available() ) {
            return array(
                'available' => false,
                'version'   => null,
                'message'   => __( 'tc-lib-barcode library not installed. Run composer install.', 'qsa-engraving' ),
            );
        }

        // Try to get version info.
        $version = null;
        if ( defined( '\Com\Tecnick\Barcode\VERSION' ) ) {
            $version = \Com\Tecnick\Barcode\VERSION;
        }

        return array(
            'available' => true,
            'version'   => $version,
            'message'   => __( 'tc-lib-barcode library is available.', 'qsa-engraving' ),
        );
    }
}
