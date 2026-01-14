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
     * Fill color for text (blue for engraving layer).
     *
     * @var string
     */
    public const TEXT_FILL = '#0000FF';

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
     * Render LED code text with configurable tracking and height.
     *
     * Uses explicit character positioning for precise spacing control.
     * Tracking value follows AutoCAD convention:
     *   - 1.0 = normal spacing (no extra space)
     *   - 1.3 = 30% wider spacing between characters
     *   - 0.5 = tighter spacing (50% of normal)
     *
     * @param string     $led_code 3-character LED code.
     * @param float      $x        X coordinate.
     * @param float      $y        Y coordinate.
     * @param int        $rotation Rotation angle in degrees.
     * @param float      $tracking Tracking multiplier (default 1.0 = normal).
     * @param float|null $height   Text height in mm (null = use default 1.0mm).
     * @return string SVG text element.
     */
    public static function render_led_code(
        string $led_code,
        float $x,
        float $y,
        int $rotation = 0,
        float $tracking = 1.0,
        ?float $height = null
    ): string {
        // Use provided height or fall back to default.
        $text_height = $height ?? self::DEFAULT_HEIGHTS['led_code'];

        // If tracking is 1.0 (normal), use the standard render with hair-spaces.
        if ( abs( $tracking - 1.0 ) < 0.01 ) {
            return self::render(
                $led_code,
                $x,
                $y,
                $text_height,
                'middle',
                $rotation
            );
        }

        // Use explicit character positioning for custom tracking values.
        return self::render_with_tracking(
            $led_code,
            $x,
            $y,
            $text_height,
            'middle',
            $rotation,
            $tracking
        );
    }

    /**
     * Render text with custom tracking using explicit character positioning.
     *
     * Renders each character as a separate text element with calculated x positions.
     * This approach works reliably in LightBurn, which ignores letter-spacing and
     * tspan dx attributes when importing SVG files.
     *
     * @param string $text     The text content.
     * @param float  $x        X coordinate (center point for the text).
     * @param float  $y        Y coordinate (baseline).
     * @param float  $height   Text height in mm.
     * @param string $anchor   Text anchor: 'start', 'middle', or 'end'.
     * @param int    $rotation Rotation angle in degrees.
     * @param float  $tracking Tracking multiplier (1.0 = normal, 2.0 = 2x spacing).
     * @return string SVG markup (group of text elements).
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

        // Calculate character advance (distance between character centers).
        // Average character width for Roboto Thin ≈ 0.5 × height.
        // Advance = char_width × tracking.
        $char_width = $height * 0.5;
        $advance    = $char_width * $tracking;

        // Split text into individual characters (UTF-8 safe).
        $chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
        if ( false === $chars || empty( $chars ) ) {
            return '';
        }

        $num_chars = count( $chars );

        // Calculate the total span from first to last character center.
        // For n chars: span = (n-1) × advance.
        $total_span = ( $num_chars - 1 ) * $advance;

        // Calculate starting x based on anchor.
        // For 'middle': center the character group around x.
        // For 'start': first char at x.
        // For 'end': last char at x.
        switch ( $anchor ) {
            case 'start':
                $start_x = $x;
                break;
            case 'end':
                $start_x = $x - $total_span;
                break;
            case 'middle':
            default:
                $start_x = $x - ( $total_span / 2 );
                break;
        }

        // Build individual text elements for each character.
        $elements = array();
        foreach ( $chars as $index => $char ) {
            $char_x       = $start_x + ( $index * $advance );
            $escaped_char = htmlspecialchars( $char, ENT_XML1 | ENT_QUOTES, 'UTF-8' );

            $elements[] = sprintf(
                '<text font-family="%s" font-size="%s" text-anchor="middle" fill="%s" x="%s" y="%s">%s</text>',
                self::FONT_FAMILY,
                number_format( $font_size, 4, '.', '' ),
                self::TEXT_FILL,
                number_format( $char_x, 4, '.', '' ),
                number_format( $y, 4, '.', '' ),
                $escaped_char
            );
        }

        // Wrap in a group, applying rotation if needed.
        $group_content = implode( '', $elements );

        if ( 0 !== $rotation ) {
            return sprintf(
                '<g transform="rotate(%d %.4f %.4f)">%s</g>',
                $rotation,
                $x,
                $y,
                $group_content
            );
        }

        // No rotation - return elements directly (no wrapper needed for cleaner SVG).
        return $group_content;
    }

    /**
     * Restricted character set for LED codes (default).
     */
    public const LED_CODE_CHARSET_RESTRICTED = '1234789CEFHJKLPRT';

    /**
     * Full alphanumeric character set for legacy LED codes.
     */
    public const LED_CODE_CHARSET_LEGACY = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * Validate LED code against allowed character set.
     *
     * By default, validates against the restricted charset (17 characters).
     * When $allow_legacy is true, allows any uppercase alphanumeric (A-Z, 0-9).
     *
     * LEDs with an existing 2-character code (legacy LEDs) are allowed to use
     * any alphanumeric characters for their 3-character code to reduce confusion
     * when migrating (e.g., "5B" can become "5B0").
     *
     * @param string $led_code     The LED code to validate.
     * @param bool   $allow_legacy Allow full alphanumeric charset for legacy LEDs.
     * @return bool True if valid.
     */
    public static function validate_led_code( string $led_code, bool $allow_legacy = false ): bool {
        // Must be exactly 3 characters.
        if ( strlen( $led_code ) !== 3 ) {
            return false;
        }

        // Select charset based on legacy mode.
        $allowed = $allow_legacy ? self::LED_CODE_CHARSET_LEGACY : self::LED_CODE_CHARSET_RESTRICTED;

        // Check each character against allowed set (case-insensitive).
        for ( $i = 0; $i < 3; $i++ ) {
            if ( strpos( $allowed, strtoupper( $led_code[ $i ] ) ) === false ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the allowed LED code character set.
     *
     * @param bool $legacy Return the legacy (full alphanumeric) charset.
     * @return string Allowed characters for LED codes.
     */
    public static function get_led_code_charset( bool $legacy = false ): string {
        return $legacy ? self::LED_CODE_CHARSET_LEGACY : self::LED_CODE_CHARSET_RESTRICTED;
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
