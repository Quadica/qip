<?php
/**
 * Micro-ID Encoder.
 *
 * Encodes serial numbers as 5x5 dot matrix patterns per Quadica Micro-ID specification.
 * Generates SVG circle elements for UV laser engraving.
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
 * Micro-ID Encoder class.
 *
 * Implements the Quadica 5x5 Micro-ID encoding specification:
 * - 20-bit data capacity (1 to 1,048,575)
 * - 4 corner anchors (always ON)
 * - 1 parity bit for even parity
 * - 1 orientation marker outside grid
 *
 * Physical specifications:
 * - Grid: 1.0mm x 1.0mm total area
 * - Dot diameter: 0.10mm (radius 0.05mm)
 * - Dot pitch: 0.225mm center-to-center
 * - Orientation marker: -0.175mm from left edge
 *
 * @since 1.0.0
 */
class Micro_ID_Encoder {

    /**
     * Maximum encodable serial number (2^20 - 1).
     *
     * @var int
     */
    public const MAX_SERIAL = 1048575;

    /**
     * Minimum valid serial number.
     *
     * @var int
     */
    public const MIN_SERIAL = 1;

    /**
     * Dot radius in millimeters.
     *
     * @var float
     */
    public const DOT_RADIUS = 0.05;

    /**
     * Dot pitch (center-to-center) in millimeters.
     *
     * @var float
     */
    public const DOT_PITCH = 0.225;

    /**
     * Offset from grid edge to first dot center in millimeters.
     *
     * @var float
     */
    public const EDGE_OFFSET = 0.05;

    /**
     * Orientation marker X position relative to grid origin.
     *
     * @var float
     */
    public const ORIENTATION_X = -0.175;

    /**
     * Orientation marker Y position relative to grid origin.
     *
     * @var float
     */
    public const ORIENTATION_Y = 0.05;

    /**
     * Fill color for dots (black for laser engraving).
     *
     * @var string
     */
    public const DOT_FILL = '#000000';

    /**
     * Anchor positions (row, col) - corners of 5x5 grid.
     *
     * These are always ON regardless of data.
     *
     * @var array<array{int, int}>
     */
    private const ANCHOR_POSITIONS = array(
        array( 0, 0 ), // Top-left.
        array( 0, 4 ), // Top-right.
        array( 4, 0 ), // Bottom-left.
        array( 4, 4 ), // Bottom-right.
    );

    /**
     * Parity bit position (row, col).
     *
     * @var array{int, int}
     */
    private const PARITY_POSITION = array( 4, 3 );

    /**
     * Bit-to-grid mapping in row-major order.
     *
     * Maps bit index (19 = MSB, 0 = LSB) to (row, col) position.
     * Excludes corners (anchors) and parity position.
     *
     * Grid layout (per specification):
     * Row 0: [ANCHOR] [Bit 19] [Bit 18] [Bit 17] [ANCHOR]
     * Row 1: [Bit 16] [Bit 15] [Bit 14] [Bit 13] [Bit 12]
     * Row 2: [Bit 11] [Bit 10] [Bit 9]  [Bit 8]  [Bit 7]
     * Row 3: [Bit 6]  [Bit 5]  [Bit 4]  [Bit 3]  [Bit 2]
     * Row 4: [ANCHOR] [Bit 1]  [Bit 0]  [PARITY] [ANCHOR]
     *
     * @var array<int, array{int, int}>
     */
    private const BIT_POSITIONS = array(
        19 => array( 0, 1 ),
        18 => array( 0, 2 ),
        17 => array( 0, 3 ),
        16 => array( 1, 0 ),
        15 => array( 1, 1 ),
        14 => array( 1, 2 ),
        13 => array( 1, 3 ),
        12 => array( 1, 4 ),
        11 => array( 2, 0 ),
        10 => array( 2, 1 ),
        9  => array( 2, 2 ),
        8  => array( 2, 3 ),
        7  => array( 2, 4 ),
        6  => array( 3, 0 ),
        5  => array( 3, 1 ),
        4  => array( 3, 2 ),
        3  => array( 3, 3 ),
        2  => array( 3, 4 ),
        1  => array( 4, 1 ),
        0  => array( 4, 2 ),
    );

    /**
     * Encode a serial number to its 20-bit binary representation.
     *
     * @param int $serial_integer The serial number (1 to 1,048,575).
     * @return string|WP_Error 20-character binary string or WP_Error if invalid.
     */
    public static function encode_binary( int $serial_integer ): string|WP_Error {
        // Validate input range.
        $validation = self::validate_serial( $serial_integer );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Convert to 20-bit binary string, padded with leading zeros.
        return str_pad( decbin( $serial_integer ), 20, '0', STR_PAD_LEFT );
    }

    /**
     * Calculate even parity bit for a binary string.
     *
     * The parity bit ensures the total count of ON bits (data + parity) is even.
     *
     * @param string $binary_string The 20-bit binary string.
     * @return int 0 if even count of 1s, 1 if odd count of 1s.
     */
    public static function calculate_parity( string $binary_string ): int {
        // Count the number of '1' bits.
        $ones_count = substr_count( $binary_string, '1' );

        // If count is odd, parity bit = 1 (to make total even).
        // If count is even, parity bit = 0 (already even).
        return $ones_count % 2;
    }

    /**
     * Validate a serial number is within valid range.
     *
     * @param int $serial_integer The serial number to validate.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    public static function validate_serial( int $serial_integer ): true|WP_Error {
        if ( $serial_integer < self::MIN_SERIAL ) {
            return new WP_Error(
                'serial_too_low',
                sprintf(
                    /* translators: 1: Provided value, 2: Minimum value */
                    __( 'Serial number %1$d is below minimum value %2$d.', 'qsa-engraving' ),
                    $serial_integer,
                    self::MIN_SERIAL
                )
            );
        }

        if ( $serial_integer > self::MAX_SERIAL ) {
            return new WP_Error(
                'serial_too_high',
                sprintf(
                    /* translators: 1: Provided value, 2: Maximum value */
                    __( 'Serial number %1$d exceeds maximum value %2$d.', 'qsa-engraving' ),
                    $serial_integer,
                    self::MAX_SERIAL
                )
            );
        }

        return true;
    }

    /**
     * Validate a serial number string format.
     *
     * @param string $serial_string The serial number string.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    public static function validate_serial_string( string $serial_string ): true|WP_Error {
        // Must be exactly 8 characters.
        if ( strlen( $serial_string ) !== 8 ) {
            return new WP_Error(
                'invalid_length',
                sprintf(
                    /* translators: %s: The serial string */
                    __( 'Serial number "%s" must be exactly 8 characters.', 'qsa-engraving' ),
                    $serial_string
                )
            );
        }

        // Must be numeric only.
        if ( ! ctype_digit( $serial_string ) ) {
            return new WP_Error(
                'invalid_characters',
                sprintf(
                    /* translators: %s: The serial string */
                    __( 'Serial number "%s" must contain only digits 0-9.', 'qsa-engraving' ),
                    $serial_string
                )
            );
        }

        // Convert and validate range.
        $serial_integer = (int) $serial_string;
        return self::validate_serial( $serial_integer );
    }

    /**
     * Calculate grid position coordinates in millimeters.
     *
     * Coordinate formula from specification:
     * X = 0.05 + (col × 0.225)
     * Y = 0.05 + (row × 0.225)
     *
     * @param int $row Row index (0-4).
     * @param int $col Column index (0-4).
     * @return array{x: float, y: float} Coordinates in millimeters.
     */
    public static function get_grid_coordinates( int $row, int $col ): array {
        return array(
            'x' => self::EDGE_OFFSET + ( $col * self::DOT_PITCH ),
            'y' => self::EDGE_OFFSET + ( $row * self::DOT_PITCH ),
        );
    }

    /**
     * Get all dot positions for a serial number.
     *
     * Returns an array of coordinates for all dots that should be ON,
     * including anchors, orientation marker, data bits, and parity.
     *
     * @param int $serial_integer The serial number (1 to 1,048,575).
     * @return array<array{x: float, y: float, type: string}>|WP_Error Array of dot positions or WP_Error.
     */
    public static function get_dot_positions( int $serial_integer ): array|WP_Error {
        // Validate and encode.
        $binary = self::encode_binary( $serial_integer );
        if ( is_wp_error( $binary ) ) {
            return $binary;
        }

        $parity = self::calculate_parity( $binary );
        $dots   = array();

        // 1. Orientation marker (always ON).
        $dots[] = array(
            'x'    => self::ORIENTATION_X,
            'y'    => self::ORIENTATION_Y,
            'type' => 'orientation',
        );

        // 2. Anchor dots (always ON).
        foreach ( self::ANCHOR_POSITIONS as $anchor ) {
            $coords = self::get_grid_coordinates( $anchor[0], $anchor[1] );
            $dots[] = array(
                'x'    => $coords['x'],
                'y'    => $coords['y'],
                'type' => 'anchor',
            );
        }

        // 3. Data bits (ON where bit = 1).
        for ( $bit = 19; $bit >= 0; $bit-- ) {
            // Get the bit value (1 or 0).
            $bit_value = $binary[19 - $bit]; // String is indexed 0-19, bit 19 is at index 0.

            if ( '1' === $bit_value ) {
                $position = self::BIT_POSITIONS[ $bit ];
                $coords   = self::get_grid_coordinates( $position[0], $position[1] );
                $dots[]   = array(
                    'x'    => $coords['x'],
                    'y'    => $coords['y'],
                    'type' => 'data',
                    'bit'  => $bit,
                );
            }
        }

        // 4. Parity bit (ON if parity = 1).
        if ( 1 === $parity ) {
            $coords = self::get_grid_coordinates( self::PARITY_POSITION[0], self::PARITY_POSITION[1] );
            $dots[] = array(
                'x'    => $coords['x'],
                'y'    => $coords['y'],
                'type' => 'parity',
            );
        }

        return $dots;
    }

    /**
     * Render Micro-ID as SVG group element.
     *
     * Generates an SVG <g> element containing all dots for the Micro-ID code.
     * The group can be positioned using a transform attribute on the parent.
     *
     * @param int    $serial_integer The serial number (1 to 1,048,575).
     * @param string $id Optional ID attribute for the group element.
     * @return string|WP_Error SVG markup string or WP_Error if invalid.
     */
    public static function render_svg( int $serial_integer, string $id = '' ): string|WP_Error {
        $dots = self::get_dot_positions( $serial_integer );
        if ( is_wp_error( $dots ) ) {
            return $dots;
        }

        // Build SVG group.
        $id_attr = $id ? sprintf( ' id="%s"', esc_attr( $id ) ) : '';
        $svg     = sprintf( '<g%s>', $id_attr ) . "\n";

        // Add comment with serial info for debugging.
        $binary = self::encode_binary( $serial_integer );
        $parity = is_string( $binary ) ? self::calculate_parity( $binary ) : 0;
        $svg   .= sprintf(
            "  <!-- Serial: %08d | Binary: %s | Parity: %d -->\n",
            $serial_integer,
            is_string( $binary ) ? $binary : 'error',
            $parity
        );

        // Render each dot as a circle.
        foreach ( $dots as $dot ) {
            $svg .= sprintf(
                '  <circle cx="%.3f" cy="%.3f" r="%.2f" fill="%s"/>',
                $dot['x'],
                $dot['y'],
                self::DOT_RADIUS,
                self::DOT_FILL
            );
            $svg .= "\n";
        }

        $svg .= '</g>';

        return $svg;
    }

    /**
     * Render Micro-ID as SVG group with position transform.
     *
     * Wraps the Micro-ID in a positioned group for placement on a larger canvas.
     *
     * @param int    $serial_integer The serial number.
     * @param float  $x X position offset in millimeters.
     * @param float  $y Y position offset in millimeters.
     * @param string $id Optional ID attribute for the group.
     * @return string|WP_Error SVG markup string or WP_Error.
     */
    public static function render_svg_positioned( int $serial_integer, float $x, float $y, string $id = '' ): string|WP_Error {
        $inner_svg = self::render_svg( $serial_integer );
        if ( is_wp_error( $inner_svg ) ) {
            return $inner_svg;
        }

        // Wrap in positioned group.
        $id_attr = $id ? sprintf( ' id="%s"', esc_attr( $id ) ) : '';
        return sprintf(
            '<g%s transform="translate(%.4f, %.4f)">%s</g>',
            $id_attr,
            $x,
            $y,
            "\n" . $inner_svg . "\n"
        );
    }

    /**
     * Decode a 5x5 grid back to a serial number (for verification).
     *
     * Takes a binary grid representation and decodes it to the original serial.
     * Validates parity to detect errors.
     *
     * @param array $grid 5x5 array of 0/1 values (row-major order).
     * @return int|WP_Error Decoded serial number or WP_Error if invalid.
     */
    public static function decode_grid( array $grid ): int|WP_Error {
        // Validate grid dimensions.
        if ( count( $grid ) !== 5 ) {
            return new WP_Error( 'invalid_grid', __( 'Grid must have exactly 5 rows.', 'qsa-engraving' ) );
        }

        foreach ( $grid as $row ) {
            if ( count( $row ) !== 5 ) {
                return new WP_Error( 'invalid_grid', __( 'Each row must have exactly 5 columns.', 'qsa-engraving' ) );
            }
        }

        // Verify anchors are all ON.
        foreach ( self::ANCHOR_POSITIONS as $anchor ) {
            if ( $grid[ $anchor[0] ][ $anchor[1] ] !== 1 ) {
                return new WP_Error(
                    'invalid_anchor',
                    sprintf(
                        /* translators: 1: Row, 2: Column */
                        __( 'Anchor at position (%1$d, %2$d) must be ON.', 'qsa-engraving' ),
                        $anchor[0],
                        $anchor[1]
                    )
                );
            }
        }

        // Extract data bits in order (bit 19 to bit 0).
        $binary = '';
        for ( $bit = 19; $bit >= 0; $bit-- ) {
            $position = self::BIT_POSITIONS[ $bit ];
            $binary  .= $grid[ $position[0] ][ $position[1] ] ? '1' : '0';
        }

        // Extract and verify parity.
        $parity_value    = $grid[ self::PARITY_POSITION[0] ][ self::PARITY_POSITION[1] ];
        $expected_parity = self::calculate_parity( $binary );

        if ( $parity_value !== $expected_parity ) {
            return new WP_Error(
                'parity_error',
                sprintf(
                    /* translators: 1: Expected parity, 2: Actual parity */
                    __( 'Parity check failed. Expected %1$d, got %2$d.', 'qsa-engraving' ),
                    $expected_parity,
                    $parity_value
                )
            );
        }

        // Convert binary to integer.
        $serial = bindec( $binary );

        // Validate range (should not be zero).
        if ( $serial < self::MIN_SERIAL ) {
            return new WP_Error(
                'invalid_serial',
                __( 'Decoded serial number is below minimum value.', 'qsa-engraving' )
            );
        }

        return $serial;
    }

    /**
     * Get the full 5x5 grid representation for a serial number.
     *
     * Returns a 5x5 array where 1 = dot ON, 0 = dot OFF.
     * Useful for testing and verification.
     *
     * @param int $serial_integer The serial number.
     * @return array<array<int>>|WP_Error 5x5 grid array or WP_Error.
     */
    public static function get_grid( int $serial_integer ): array|WP_Error {
        // Validate and encode.
        $binary = self::encode_binary( $serial_integer );
        if ( is_wp_error( $binary ) ) {
            return $binary;
        }

        $parity = self::calculate_parity( $binary );

        // Initialize empty grid.
        $grid = array_fill( 0, 5, array_fill( 0, 5, 0 ) );

        // Set anchors.
        foreach ( self::ANCHOR_POSITIONS as $anchor ) {
            $grid[ $anchor[0] ][ $anchor[1] ] = 1;
        }

        // Set data bits.
        for ( $bit = 19; $bit >= 0; $bit-- ) {
            $bit_value = $binary[19 - $bit];
            if ( '1' === $bit_value ) {
                $position                             = self::BIT_POSITIONS[ $bit ];
                $grid[ $position[0] ][ $position[1] ] = 1;
            }
        }

        // Set parity bit.
        if ( 1 === $parity ) {
            $grid[ self::PARITY_POSITION[0] ][ self::PARITY_POSITION[1] ] = 1;
        }

        return $grid;
    }

    /**
     * Get a visual ASCII representation of the grid (for debugging).
     *
     * @param int $serial_integer The serial number.
     * @return string|WP_Error ASCII art grid or WP_Error.
     */
    public static function get_grid_ascii( int $serial_integer ): string|WP_Error {
        $grid = self::get_grid( $serial_integer );
        if ( is_wp_error( $grid ) ) {
            return $grid;
        }

        $output = '';
        foreach ( $grid as $row ) {
            foreach ( $row as $cell ) {
                $output .= $cell ? '●' : '○';
                $output .= ' ';
            }
            $output .= "\n";
        }

        return $output;
    }

    /**
     * Count the total number of ON dots for a serial number.
     *
     * Includes anchors (4), orientation marker (1), data bits (0-20), and parity (0-1).
     *
     * @param int $serial_integer The serial number.
     * @return int|WP_Error Total dot count or WP_Error.
     */
    public static function count_dots( int $serial_integer ): int|WP_Error {
        $dots = self::get_dot_positions( $serial_integer );
        if ( is_wp_error( $dots ) ) {
            return $dots;
        }

        return count( $dots );
    }

    /**
     * Parse a serial string to integer.
     *
     * Handles both 8-digit strings like "00123456" and plain integers.
     *
     * @param string|int $serial The serial number.
     * @return int|WP_Error The integer value or WP_Error if invalid.
     */
    public static function parse_serial( string|int $serial ): int|WP_Error {
        if ( is_int( $serial ) ) {
            $validation = self::validate_serial( $serial );
            return is_wp_error( $validation ) ? $validation : $serial;
        }

        $validation = self::validate_serial_string( $serial );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        return (int) $serial;
    }
}
