<?php
/**
 * Text Renderer.
 *
 * Renders text elements as SVG text with Roboto Thin font and hair-space character spacing.
 * Optimized for UV laser engraving clarity at small sizes.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\SVG;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Text Renderer class.
 *
 * Generates SVG text elements with proper formatting for laser engraving:
 * - Roboto Thin font family
 * - Hair-space (U+200A) character spacing for engraving clarity
 * - Font size scaling to achieve desired physical height
 * - Rotation transforms for angled text
 *
 * Text heights from specification:
 * - Module ID: 1.5mm
 * - Serial URL: 1.2mm
 * - LED Code: 1.0mm
 *
 * @since 1.0.0
 */
class Text_Renderer {

    /**
     * Font family for text rendering.
     *
     * @var string
     */
    public const FONT_FAMILY = 'Roboto Thin, sans-serif';

    /**
     * Hair space Unicode character for character spacing.
     *
     * @var string
     */
    public const HAIR_SPACE = "\u{200A}";

    /**
     * Font size multiplier to convert desired height to font-size.
     *
     * Based on Roboto Thin metrics: actual character height / font-size ratio.
     * Formula: font_size = height × (0.7 / 0.498) ≈ 1.4056
     *
     * @var float
     */
    public const HEIGHT_TO_FONT_SIZE = 1.4056;

    /**
     * Default text heights in millimeters.
     *
     * @var array
     */
    public const DEFAULT_HEIGHTS = array(
        'module_id'  => 1.5,
        'serial_url' => 1.2,
        'led_code'   => 1.0,
    );

    /**
     * Fill color for text (black for engraving).
     *
     * @var string
     */
    public const TEXT_FILL = '#000000';

    /**
     * Add hair-space between each character for engraving clarity.
     *
     * @param string $text The text to space.
     * @return string Text with hair-spaces between characters.
     */
    public static function add_character_spacing( string $text ): string {
        // Handle multi-byte characters properly.
        $chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
        if ( false === $chars ) {
            return $text;
        }
        return implode( self::HAIR_SPACE, $chars );
    }

    /**
     * Calculate font size from desired text height.
     *
     * @param float $height Desired text height in mm.
     * @return float Font size value for SVG.
     */
    public static function calculate_font_size( float $height ): float {
        return $height * self::HEIGHT_TO_FONT_SIZE;
    }

    /**
     * Render text as SVG text element.
     *
     * @param string $text       The text content.
     * @param float  $x          X coordinate (center for middle anchor).
     * @param float  $y          Y coordinate (baseline).
     * @param float  $height     Text height in mm.
     * @param string $anchor     Text anchor: 'start', 'middle', or 'end'.
     * @param int    $rotation   Rotation angle in degrees (0 = horizontal).
     * @param string $id         Optional ID attribute.
     * @return string SVG text element markup.
     */
    public static function render(
        string $text,
        float $x,
        float $y,
        float $height,
        string $anchor = 'middle',
        int $rotation = 0,
        string $id = ''
    ): string {
        // Add character spacing.
        $spaced_text = self::add_character_spacing( $text );

        // Calculate font size.
        $font_size = self::calculate_font_size( $height );

        // Escape text for XML.
        $escaped_text = htmlspecialchars( $spaced_text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );

        // Build attributes.
        $attrs = array(
            'font-family' => self::FONT_FAMILY,
            'font-size'   => number_format( $font_size, 4, '.', '' ),
            'text-anchor' => $anchor,
            'fill'        => self::TEXT_FILL,
        );

        // Add ID if provided.
        if ( ! empty( $id ) ) {
            $attrs['id'] = $id;
        }

        // Add transform for rotation if non-zero.
        if ( 0 !== $rotation ) {
            // Rotation around the text position point.
            $attrs['transform'] = sprintf(
                'rotate(%d %.4f %.4f)',
                $rotation,
                $x,
                $y
            );
        }

        // Add coordinates.
        $attrs['x'] = number_format( $x, 4, '.', '' );
        $attrs['y'] = number_format( $y, 4, '.', '' );

        // Build attribute string.
        $attr_parts = array();
        foreach ( $attrs as $name => $value ) {
            $attr_parts[] = sprintf( '%s="%s"', $name, esc_attr( (string) $value ) );
        }

        return sprintf( '<text %s>%s</text>', implode( ' ', $attr_parts ), $escaped_text );
    }

    /**
     * Render module ID text.
     *
     * @param string $module_id Module ID (e.g., "STAR-38546").
     * @param float  $x         X coordinate.
     * @param float  $y         Y coordinate.
     * @param int    $rotation  Rotation angle in degrees.
     * @return string SVG text element.
     */
    public static function render_module_id(
        string $module_id,
        float $x,
        float $y,
        int $rotation = 0
    ): string {
        return self::render(
            $module_id,
            $x,
            $y,
            self::DEFAULT_HEIGHTS['module_id'],
            'middle',
            $rotation
        );
    }

    /**
     * Render serial URL text.
     *
     * @param string $serial_number 8-digit serial number.
     * @param float  $x             X coordinate.
     * @param float  $y             Y coordinate.
     * @param int    $rotation      Rotation angle in degrees.
     * @return string SVG text element.
     */
    public static function render_serial_url(
        string $serial_number,
        float $x,
        float $y,
        int $rotation = 0
    ): string {
        $url_text = 'quadi.ca/' . $serial_number;
        return self::render(
            $url_text,
            $x,
            $y,
            self::DEFAULT_HEIGHTS['serial_url'],
            'middle',
            $rotation
        );
    }

    /**
     * Render LED code text with configurable tracking.
     *
     * Uses SVG letter-spacing attribute for precise character spacing control.
     * Tracking value follows AutoCAD convention:
     *   - 1.0 = normal spacing (no extra space)
     *   - 1.3 = 30% wider spacing between characters
     *   - 0.5 = tighter spacing (50% of normal)
     *
     * @param string $led_code 3-character LED code.
     * @param float  $x        X coordinate.
     * @param float  $y        Y coordinate.
     * @param int    $rotation Rotation angle in degrees.
     * @param float  $tracking Tracking multiplier (default 1.0 = normal).
     * @return string SVG text element.
     */
    public static function render_led_code(
        string $led_code,
        float $x,
        float $y,
        int $rotation = 0,
        float $tracking = 1.0
    ): string {
        // If tracking is 1.0 (normal), use the standard render with hair-spaces.
        if ( abs( $tracking - 1.0 ) < 0.01 ) {
            return self::render(
                $led_code,
                $x,
                $y,
                self::DEFAULT_HEIGHTS['led_code'],
                'middle',
                $rotation
            );
        }

        // Use letter-spacing for custom tracking values.
        return self::render_with_tracking(
            $led_code,
            $x,
            $y,
            self::DEFAULT_HEIGHTS['led_code'],
            'middle',
            $rotation,
            $tracking
        );
    }

    /**
     * Render text with letter-spacing based on tracking value.
     *
     * Instead of hair-spaces, this uses SVG's letter-spacing attribute
     * which provides precise control over character spacing.
     *
     * @param string $text     The text content.
     * @param float  $x        X coordinate.
     * @param float  $y        Y coordinate.
     * @param float  $height   Text height in mm.
     * @param string $anchor   Text anchor: 'start', 'middle', or 'end'.
     * @param int    $rotation Rotation angle in degrees.
     * @param float  $tracking Tracking multiplier (1.0 = normal).
     * @return string SVG text element markup.
     */
    public static function render_with_tracking(
        string $text,
        float $x,
        float $y,
        float $height,
        string $anchor,
        int $rotation,
        float $tracking
    ): string {
        // Calculate font size.
        $font_size = self::calculate_font_size( $height );

        // Calculate letter-spacing from tracking value.
        // Tracking 1.0 = 0 letter-spacing (normal)
        // Tracking 1.3 = 0.3 × average character width added between chars
        // Average character width for Roboto Thin ≈ 0.5 × height
        $avg_char_width = $height * 0.5;
        $letter_spacing = ( $tracking - 1.0 ) * $avg_char_width;

        // Escape text for XML (no hair-spaces added).
        $escaped_text = htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );

        // Build attributes.
        $attrs = array(
            'font-family'    => self::FONT_FAMILY,
            'font-size'      => number_format( $font_size, 4, '.', '' ),
            'text-anchor'    => $anchor,
            'fill'           => self::TEXT_FILL,
            'letter-spacing' => number_format( $letter_spacing, 4, '.', '' ),
        );

        // Add transform for rotation if non-zero.
        if ( 0 !== $rotation ) {
            $attrs['transform'] = sprintf(
                'rotate(%d %.4f %.4f)',
                $rotation,
                $x,
                $y
            );
        }

        // Add coordinates.
        $attrs['x'] = number_format( $x, 4, '.', '' );
        $attrs['y'] = number_format( $y, 4, '.', '' );

        // Build attribute string.
        $attr_parts = array();
        foreach ( $attrs as $name => $value ) {
            $attr_parts[] = sprintf( '%s="%s"', $name, esc_attr( (string) $value ) );
        }

        return sprintf( '<text %s>%s</text>', implode( ' ', $attr_parts ), $escaped_text );
    }

    /**
     * Validate LED code against allowed character set.
     *
     * Valid characters: 1234789CEFHJKLPRT (17 characters).
     *
     * @param string $led_code The LED code to validate.
     * @return bool True if valid.
     */
    public static function validate_led_code( string $led_code ): bool {
        // Must be exactly 3 characters.
        if ( strlen( $led_code ) !== 3 ) {
            return false;
        }

        // Check each character against allowed set.
        $allowed = '1234789CEFHJKLPRT';
        for ( $i = 0; $i < 3; $i++ ) {
            if ( strpos( $allowed, $led_code[ $i ] ) === false ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the allowed LED code character set.
     *
     * @return string Allowed characters for LED codes.
     */
    public static function get_led_code_charset(): string {
        return '1234789CEFHJKLPRT';
    }

    /**
     * Estimate text width in millimeters.
     *
     * Approximate calculation based on average character width.
     * Roboto Thin is a proportional font, so this is an estimate.
     *
     * @param string $text   The text content.
     * @param float  $height Text height in mm.
     * @return float Estimated width in mm.
     */
    public static function estimate_width( string $text, float $height ): float {
        // Average character width is approximately 0.5 × height for Roboto Thin.
        // Add hair-space width (approximately 0.05 × height) between characters.
        $char_count     = mb_strlen( $text );
        $space_count    = max( 0, $char_count - 1 );
        $char_width     = $height * 0.5;
        $space_width    = $height * 0.05;

        return ( $char_count * $char_width ) + ( $space_count * $space_width );
    }

    /**
     * Render text with custom height.
     *
     * @param string     $text     Text content.
     * @param float      $x        X coordinate.
     * @param float      $y        Y coordinate.
     * @param float|null $height   Text height in mm (null for auto from type).
     * @param string     $type     Text type: 'module_id', 'serial_url', 'led_code'.
     * @param int        $rotation Rotation angle.
     * @return string SVG text element.
     */
    public static function render_with_type(
        string $text,
        float $x,
        float $y,
        ?float $height,
        string $type,
        int $rotation = 0
    ): string {
        // Use default height if not specified.
        $text_height = $height ?? ( self::DEFAULT_HEIGHTS[ $type ] ?? 1.0 );

        return self::render( $text, $x, $y, $text_height, 'middle', $rotation );
    }
}
