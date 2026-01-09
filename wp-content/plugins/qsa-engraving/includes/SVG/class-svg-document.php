<?php
/**
 * SVG Document Assembler.
 *
 * Generates complete SVG documents for QSA engraving with all element types:
 * Micro-ID codes, QR codes, and text elements.
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
 * SVG Document class.
 *
 * Assembles complete SVG documents for LightBurn:
 * - Proper XML declaration and SVG namespaces
 * - Millimeter units with viewBox
 * - Module groups for each position
 * - Optional alignment marks
 *
 * Canvas: 148mm x 113.7mm (QSA Standard Array).
 *
 * @since 1.0.0
 */
class SVG_Document {

    /**
     * Default canvas width in millimeters.
     *
     * @var float
     */
    public const CANVAS_WIDTH = 148.0;

    /**
     * Default canvas height in millimeters.
     *
     * @var float
     */
    public const CANVAS_HEIGHT = 113.7;

    /**
     * Alignment mark color (red = non-engraving layer in LightBurn).
     *
     * @var string
     */
    public const ALIGNMENT_COLOR = '#FF0000';

    /**
     * Engraving content color (black).
     *
     * @var string
     */
    public const ENGRAVING_COLOR = '#000000';

    /**
     * Canvas width.
     *
     * @var float
     */
    private float $width;

    /**
     * Canvas height.
     *
     * @var float
     */
    private float $height;

    /**
     * Document title/comment.
     *
     * @var string
     */
    private string $title = '';

    /**
     * Module groups to render.
     *
     * @var array
     */
    private array $modules = array();

    /**
     * Whether to include boundary rectangle.
     *
     * @var bool
     */
    private bool $include_boundary = true;

    /**
     * Whether to include center crosshair.
     *
     * @var bool
     */
    private bool $include_crosshair = false;

    /**
     * Rotation angle in degrees (0, 90, 180, 270).
     *
     * @var int
     */
    private int $rotation = 0;

    /**
     * Top offset in mm (shifts content down when positive).
     *
     * @var float
     */
    private float $top_offset = 0.0;

    /**
     * Coordinate transformer instance.
     *
     * @var Coordinate_Transformer
     */
    private Coordinate_Transformer $transformer;

    /**
     * QR code data to encode.
     *
     * @var string|null
     */
    private ?string $qr_code_data = null;

    /**
     * QR code configuration (position and size).
     *
     * @var array|null
     */
    private ?array $qr_code_config = null;

    /**
     * Constructor.
     *
     * @param float $width  Canvas width in mm.
     * @param float $height Canvas height in mm.
     */
    public function __construct(
        float $width = self::CANVAS_WIDTH,
        float $height = self::CANVAS_HEIGHT
    ) {
        $this->width       = $width;
        $this->height      = $height;
        $this->transformer = new Coordinate_Transformer( $width, $height );
    }

    /**
     * Set document title (added as XML comment).
     *
     * @param string $title Document title.
     * @return self For method chaining.
     */
    public function set_title( string $title ): self {
        $this->title = $title;
        return $this;
    }

    /**
     * Set whether to include boundary rectangle.
     *
     * @param bool $include Include boundary.
     * @return self For method chaining.
     */
    public function set_include_boundary( bool $include ): self {
        $this->include_boundary = $include;
        return $this;
    }

    /**
     * Set whether to include center crosshair.
     *
     * @param bool $include Include crosshair.
     * @return self For method chaining.
     */
    public function set_include_crosshair( bool $include ): self {
        $this->include_crosshair = $include;
        return $this;
    }

    /**
     * Set rotation angle for the entire SVG.
     *
     * @param int $degrees Rotation in degrees (0, 90, 180, 270).
     * @return self For method chaining.
     */
    public function set_rotation( int $degrees ): self {
        // Normalize to valid values.
        $degrees = $degrees % 360;
        if ( $degrees < 0 ) {
            $degrees += 360;
        }
        // Only accept 0, 90, 180, 270.
        if ( ! in_array( $degrees, array( 0, 90, 180, 270 ), true ) ) {
            $degrees = 0;
        }
        $this->rotation = $degrees;
        return $this;
    }

    /**
     * Get rotation angle.
     *
     * @return int Rotation in degrees.
     */
    public function get_rotation(): int {
        return $this->rotation;
    }

    /**
     * Set top offset for the SVG content.
     *
     * Positive values shift content down, negative values shift up.
     *
     * @param float $offset Offset in mm (-5 to +5).
     * @return self For method chaining.
     */
    public function set_top_offset( float $offset ): self {
        // Clamp to valid range.
        $this->top_offset = max( -5.0, min( 5.0, $offset ) );
        return $this;
    }

    /**
     * Get top offset.
     *
     * @return float Offset in mm.
     */
    public function get_top_offset(): float {
        return $this->top_offset;
    }

    /**
     * Set calibration offsets.
     *
     * @param float $x_offset X offset in mm.
     * @param float $y_offset Y offset in mm.
     * @return self For method chaining.
     */
    public function set_calibration( float $x_offset, float $y_offset ): self {
        $this->transformer->set_calibration( $x_offset, $y_offset );
        return $this;
    }

    /**
     * Get the coordinate transformer.
     *
     * @return Coordinate_Transformer
     */
    public function get_transformer(): Coordinate_Transformer {
        return $this->transformer;
    }

    /**
     * Set QR code data and configuration.
     *
     * The QR code is rendered at the design level (position 0), after alignment marks
     * but before per-module elements. It encodes a URL linking to the QSA ID.
     *
     * @param string $data   Data to encode (e.g., "quadi.ca/cube00076").
     * @param array  $config Configuration with origin_x, origin_y, element_size keys.
     * @return self For method chaining.
     */
    public function set_qr_code( string $data, array $config ): self {
        $this->qr_code_data   = $data;
        $this->qr_code_config = $config;
        return $this;
    }

    /**
     * Check if QR code is configured.
     *
     * @return bool True if QR code data and config are set.
     */
    public function has_qr_code(): bool {
        return ! empty( $this->qr_code_data ) && ! empty( $this->qr_code_config );
    }

    /**
     * Add a module to the document.
     *
     * @param int   $position      Module position (1-8).
     * @param array $module_data   Module data with serial, module_id, led_codes.
     * @param array $element_config Element positions from config.
     * @return self|WP_Error For method chaining or error.
     */
    public function add_module( int $position, array $module_data, array $element_config ): self|WP_Error {
        // Validate position.
        if ( $position < 1 || $position > 8 ) {
            return new WP_Error(
                'invalid_position',
                sprintf(
                    /* translators: %d: Position number */
                    __( 'Invalid module position: %d. Must be 1-8.', 'qsa-engraving' ),
                    $position
                )
            );
        }

        // Validate required data.
        if ( empty( $module_data['serial_number'] ) ) {
            return new WP_Error(
                'missing_serial',
                __( 'Module data must include serial_number.', 'qsa-engraving' )
            );
        }

        $this->modules[ $position ] = array(
            'data'   => $module_data,
            'config' => $element_config,
        );

        return $this;
    }

    /**
     * Render the complete SVG document.
     *
     * @return string|WP_Error Complete SVG markup or error.
     */
    public function render(): string|WP_Error {
        $output = $this->render_xml_declaration();
        $output .= $this->render_svg_open();

        // Add title comment if set.
        if ( ! empty( $this->title ) ) {
            $output .= sprintf( "\n  <!-- %s -->", esc_html( $this->title ) );
        }

        // Add defs element (for future use).
        $output .= "\n  <defs/>";

        // Determine if we need transform groups.
        $has_rotation = ( $this->rotation !== 0 );
        $has_offset   = ( abs( $this->top_offset ) > 0.001 ); // Allow for float comparison.
        $indent       = '  ';

        // Open rotation group if rotation is applied.
        if ( $has_rotation ) {
            $output .= "\n\n  " . $this->render_rotation_group_open();
            $indent = '    ';
        }

        // Add alignment marks OUTSIDE the offset group (perimeter should not move).
        if ( $this->include_boundary ) {
            $output .= "\n\n" . $indent . $this->render_boundary();
        }

        if ( $this->include_crosshair ) {
            $output .= "\n" . $indent . $this->render_crosshair();
        }

        // Render QR code at design level (position 0) - after alignment marks, before modules.
        // QR code is affected by offset group like other engraved content.
        if ( $this->has_qr_code() ) {
            $qr_svg = $this->render_qr_code();
            if ( is_wp_error( $qr_svg ) ) {
                return $qr_svg;
            }
            // Add QR code with proper indentation.
            $qr_svg = str_replace( "\n", "\n" . $indent, $qr_svg );
            $output .= "\n\n" . $indent . $qr_svg;
        }

        // Open offset group if offset is applied (only affects engraved content, not alignment marks).
        if ( $has_offset ) {
            $output .= "\n\n" . $indent . $this->render_offset_group_open();
            $indent .= '  ';
        }

        // Render each module (inside offset group if offset applied).
        ksort( $this->modules );
        foreach ( $this->modules as $position => $module ) {
            $module_svg = $this->render_module( $position, $module['data'], $module['config'] );
            if ( is_wp_error( $module_svg ) ) {
                return $module_svg;
            }
            // Replace base indent with current indent level.
            $module_svg = str_replace( "\n  ", "\n" . $indent, $module_svg );
            $output .= "\n\n" . $indent . $module_svg;
        }

        // Close offset group if offset was applied.
        if ( $has_offset ) {
            $indent = substr( $indent, 0, -2 ); // Decrease indent.
            $output .= "\n\n" . $indent . '</g>';
        }

        // Close rotation group if rotation was applied.
        if ( $has_rotation ) {
            $output .= "\n\n  </g>";
        }

        $output .= "\n\n</svg>\n";

        return $output;
    }

    /**
     * Render XML declaration.
     *
     * @return string XML declaration.
     */
    private function render_xml_declaration(): string {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    }

    /**
     * Render SVG opening tag with namespaces.
     *
     * For 90° and 270° rotations, the canvas dimensions are swapped
     * so that the rotated content fits within the viewBox.
     *
     * @return string SVG opening tag.
     */
    private function render_svg_open(): string {
        // For 90° and 270° rotations, swap width and height.
        $display_width  = $this->width;
        $display_height = $this->height;

        if ( $this->rotation === 90 || $this->rotation === 270 ) {
            $display_width  = $this->height;
            $display_height = $this->width;
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg"
     width="%smm" height="%smm"
     viewBox="0 0 %s %s">',
            number_format( $display_width, 1, '.', '' ),
            number_format( $display_height, 1, '.', '' ),
            number_format( $display_width, 1, '.', '' ),
            number_format( $display_height, 1, '.', '' )
        );
    }

    /**
     * Render the opening tag for the rotation group.
     *
     * Applies a transform to rotate all content around the appropriate
     * origin point based on the rotation angle.
     *
     * Transform explanations:
     * - 90° CW:  translate(height, 0) rotate(90) - pivot at top-left, then shift right
     * - 180°:    translate(width, height) rotate(180) - pivot at center
     * - 270° CW: translate(0, width) rotate(270) - pivot at top-left, then shift down
     *
     * @return string Opening <g> tag with transform attribute.
     */
    private function render_rotation_group_open(): string {
        $transform = '';

        switch ( $this->rotation ) {
            case 90:
                // Rotate 90° CW: content rotates, then translate to fit in swapped canvas.
                $transform = sprintf(
                    'translate(%.1f, 0) rotate(90)',
                    $this->height
                );
                break;

            case 180:
                // Rotate 180°: translate to opposite corner, then rotate.
                $transform = sprintf(
                    'translate(%.1f, %.1f) rotate(180)',
                    $this->width,
                    $this->height
                );
                break;

            case 270:
                // Rotate 270° CW (= 90° CCW): rotate, then translate to fit.
                $transform = sprintf(
                    'translate(0, %.1f) rotate(270)',
                    $this->width
                );
                break;

            default:
                // 0° - no transform needed, but this shouldn't be called.
                return '<g id="content">';
        }

        return sprintf( '<g id="rotated-content" transform="%s">', $transform );
    }

    /**
     * Render the opening tag for the offset group.
     *
     * Applies a translate to shift content down (positive) or up (negative)
     * relative to the visual top of the canvas, accounting for rotation.
     *
     * Since this group is nested inside the rotation group, we must adjust
     * the translation direction based on rotation so the offset always
     * moves content in the visual "down" direction:
     * - 0°:   translate(0, +offset) - down is +Y
     * - 90°:  translate(+offset, 0) - after 90° CW, down is +X
     * - 180°: translate(0, -offset) - after 180°, down is -Y
     * - 270°: translate(-offset, 0) - after 270° CW, down is -X
     *
     * @return string Opening <g> tag with transform attribute.
     */
    private function render_offset_group_open(): string {
        $translate_x = 0.0;
        $translate_y = 0.0;

        switch ( $this->rotation ) {
            case 90:
                $translate_x = $this->top_offset;
                break;

            case 180:
                $translate_y = -$this->top_offset;
                break;

            case 270:
                $translate_x = -$this->top_offset;
                break;

            default: // 0°
                $translate_y = $this->top_offset;
                break;
        }

        return sprintf(
            '<g id="offset-content" transform="translate(%.2f, %.2f)">',
            $translate_x,
            $translate_y
        );
    }

    /**
     * Render boundary rectangle for alignment.
     *
     * @return string SVG rect element.
     */
    private function render_boundary(): string {
        $margin = 0.5;
        return sprintf(
            '<rect fill="none" stroke="%s" stroke-width="0.25" x="%.1f" y="%.1f" width="%.1f" height="%.1f"/>',
            self::ALIGNMENT_COLOR,
            $margin,
            $margin,
            $this->width - ( 2 * $margin ),
            $this->height - ( 2 * $margin )
        );
    }

    /**
     * Render center crosshair for alignment.
     *
     * @return string SVG line elements.
     */
    private function render_crosshair(): string {
        $center_x = $this->width / 2;
        $center_y = $this->height / 2;
        $size     = 2.0; // 4mm total size (±2mm from center).

        $h_line = sprintf(
            '<line stroke="%s" stroke-width="0.2" x1="%.4f" x2="%.4f" y1="%.4f" y2="%.4f"/>',
            self::ALIGNMENT_COLOR,
            $center_x - $size,
            $center_x + $size,
            $center_y,
            $center_y
        );

        $v_line = sprintf(
            '<line stroke="%s" stroke-width="0.2" x1="%.4f" x2="%.4f" y1="%.4f" y2="%.4f"/>',
            self::ALIGNMENT_COLOR,
            $center_x,
            $center_x,
            $center_y - $size,
            $center_y + $size
        );

        return $h_line . "\n  " . $v_line;
    }

    /**
     * Render QR code element.
     *
     * Renders a single QR code at the design level (position 0).
     * The QR code encodes a URL with the QSA ID for array identification.
     *
     * @return string|WP_Error SVG group element or error.
     */
    private function render_qr_code(): string|WP_Error {
        if ( ! $this->has_qr_code() ) {
            return new WP_Error(
                'no_qr_code',
                __( 'QR code data not configured.', 'qsa-engraving' )
            );
        }

        $config = $this->qr_code_config;

        // Get coordinates from config (CAD format).
        $cad_x = (float) ( $config['origin_x'] ?? 0 );
        $cad_y = (float) ( $config['origin_y'] ?? 0 );
        $size  = (float) ( $config['element_size'] ?? QR_Code_Renderer::DEFAULT_SIZE );

        // Transform CAD coordinates to SVG coordinates.
        $svg_coords = $this->transformer->cad_to_svg( $cad_x, $cad_y );

        // Validate coordinates are within bounds (clamp if needed).
        if ( ! $this->transformer->is_within_bounds( $svg_coords['x'], $svg_coords['y'] ) ) {
            $svg_coords = $this->transformer->clamp_to_bounds( $svg_coords['x'], $svg_coords['y'] );
        }

        // Render the QR code at the calculated position.
        $qr_svg = QR_Code_Renderer::render_positioned(
            $this->qr_code_data,
            $svg_coords['x'],
            $svg_coords['y'],
            $size,
            'qr-code-design'
        );

        if ( is_wp_error( $qr_svg ) ) {
            return $qr_svg;
        }

        // Add section comment for the design-level element.
        $comment = sprintf(
            '<!-- ========== DESIGN LEVEL QR CODE ========== -->
<!-- QSA ID: %s -->',
            esc_html( $this->qr_code_data )
        );

        return $comment . "\n" . $qr_svg;
    }

    /**
     * Render a single module group.
     *
     * @param int   $position Module position.
     * @param array $data     Module data.
     * @param array $config   Element configuration.
     * @return string|WP_Error SVG group or error.
     */
    private function render_module( int $position, array $data, array $config ): string|WP_Error {
        $serial_number = $data['serial_number'];
        $serial_int    = (int) $serial_number;
        $module_id     = $data['module_id'] ?? '';
        $led_codes     = $data['led_codes'] ?? array();

        $elements = array();

        // Comment header.
        $elements[] = sprintf(
            '<!-- ========== MODULE POSITION %d ========== -->',
            $position
        );
        $elements[] = sprintf(
            '<!-- Serial: %s | Module: %s | LEDs: %s -->',
            $serial_number,
            $module_id,
            implode( ', ', $led_codes )
        );

        // Open module group.
        $elements[] = sprintf( '<g id="module-%d">', $position );

        // Micro-ID.
        if ( isset( $config['micro_id'] ) ) {
            $micro_id_svg = $this->render_micro_id( $position, $serial_int, $config['micro_id'] );
            if ( is_wp_error( $micro_id_svg ) ) {
                return $micro_id_svg;
            }
            $elements[] = '  ' . $micro_id_svg;
        }

        // Module ID text.
        if ( ! empty( $module_id ) && isset( $config['module_id'] ) ) {
            $elements[] = '  ' . $this->render_text_element(
                'module_id',
                $module_id,
                $config['module_id']
            );
        }

        // Serial URL text.
        if ( isset( $config['serial_url'] ) ) {
            $elements[] = '  ' . $this->render_text_element(
                'serial_url',
                'quadi.ca/' . $serial_number,
                $config['serial_url']
            );
        }

        // LED codes.
        // The led_codes array is keyed by LED position (1, 2, 3, 4, etc.) from the Order BOM.
        // This preserves actual position info for modules with gaps (e.g., LEDs at positions 1 and 4 only).
        foreach ( $led_codes as $led_position => $led_code ) {
            $config_key = 'led_code_' . $led_position;
            if ( ! empty( $led_code ) && isset( $config[ $config_key ] ) ) {
                // Validate LED code against allowed character set.
                if ( ! Text_Renderer::validate_led_code( $led_code ) ) {
                    return new WP_Error(
                        'invalid_led_code',
                        sprintf(
                            /* translators: 1: LED code, 2: Position number, 3: Allowed characters */
                            __( 'Invalid LED code "%1$s" at position %2$d. Only these characters allowed: %3$s', 'qsa-engraving' ),
                            $led_code,
                            $led_position,
                            Text_Renderer::get_led_code_charset()
                        )
                    );
                }

                $elements[] = '  ' . $this->render_text_element(
                    'led_code',
                    $led_code,
                    $config[ $config_key ]
                );
            }
        }

        // Close module group.
        $elements[] = '</g>';

        return implode( "\n  ", $elements );
    }

    /**
     * Render Micro-ID element.
     *
     * @param int   $position    Module position.
     * @param int   $serial_int  Serial number as integer.
     * @param array $config      Element configuration.
     * @return string|WP_Error SVG markup or error.
     */
    private function render_micro_id( int $position, int $serial_int, array $config ): string|WP_Error {
        // Transform CAD coordinates to SVG.
        $svg_coords = $this->transformer->get_micro_id_transform(
            $config['origin_x'],
            $config['origin_y']
        );

        // Validate coordinates are within bounds (warn but don't fail).
        if ( ! $this->transformer->is_within_bounds( $svg_coords['x'], $svg_coords['y'] ) ) {
            // Clamp to bounds to ensure valid SVG output.
            $svg_coords = $this->transformer->clamp_to_bounds( $svg_coords['x'], $svg_coords['y'] );
        }

        return Micro_ID_Encoder::render_svg_positioned(
            $serial_int,
            $svg_coords['x'],
            $svg_coords['y'],
            sprintf( 'micro-id-%d', $position )
        );
    }

    /**
     * Render text element.
     *
     * @param string $type   Element type (module_id, serial_url, led_code).
     * @param string $text   Text content.
     * @param array  $config Element configuration.
     * @return string SVG text element.
     */
    private function render_text_element( string $type, string $text, array $config ): string {
        // Transform CAD coordinates to SVG.
        $svg_coords = $this->transformer->cad_to_svg(
            $config['origin_x'],
            $config['origin_y']
        );

        // Validate coordinates are within bounds (clamp if needed).
        if ( ! $this->transformer->is_within_bounds( $svg_coords['x'], $svg_coords['y'] ) ) {
            $svg_coords = $this->transformer->clamp_to_bounds( $svg_coords['x'], $svg_coords['y'] );
        }

        $height   = $config['text_height'] ?? ( Text_Renderer::DEFAULT_HEIGHTS[ $type ] ?? 1.0 );
        $rotation = $config['rotation'] ?? 0;

        return Text_Renderer::render(
            $text,
            $svg_coords['x'],
            $svg_coords['y'],
            $height,
            'middle',
            $rotation
        );
    }

    /**
     * Get canvas dimensions.
     *
     * @return array{width: float, height: float}
     */
    public function get_dimensions(): array {
        return array(
            'width'  => $this->width,
            'height' => $this->height,
        );
    }

    /**
     * Get count of modules added.
     *
     * @return int Module count.
     */
    public function get_module_count(): int {
        return count( $this->modules );
    }

    /**
     * Clear all modules.
     *
     * @return self For method chaining.
     */
    public function clear_modules(): self {
        $this->modules = array();
        return $this;
    }

    /**
     * Create document from array of module data with configuration.
     *
     * Convenience factory method for batch generation.
     *
     * @param array  $modules       Array of module data indexed by position.
     * @param array  $config        Configuration data from Config_Repository.
     * @param string $qsa_design    QSA design name.
     * @param array  $options       Optional document options.
     * @return self|WP_Error Document instance or error.
     */
    public static function create_from_data(
        array $modules,
        array $config,
        string $qsa_design,
        array $options = array()
    ): self|WP_Error {
        $doc = new self(
            $options['width'] ?? self::CANVAS_WIDTH,
            $options['height'] ?? self::CANVAS_HEIGHT
        );

        if ( isset( $options['title'] ) ) {
            $doc->set_title( $options['title'] );
        }

        if ( isset( $options['include_boundary'] ) ) {
            $doc->set_include_boundary( $options['include_boundary'] );
        }

        if ( isset( $options['include_crosshair'] ) ) {
            $doc->set_include_crosshair( $options['include_crosshair'] );
        }

        if ( isset( $options['calibration_x'] ) || isset( $options['calibration_y'] ) ) {
            $doc->set_calibration(
                $options['calibration_x'] ?? 0.0,
                $options['calibration_y'] ?? 0.0
            );
        }

        if ( isset( $options['rotation'] ) ) {
            $doc->set_rotation( (int) $options['rotation'] );
        }

        if ( isset( $options['top_offset'] ) ) {
            $doc->set_top_offset( (float) $options['top_offset'] );
        }

        // Set QR code if data and config provided.
        // QR code data is in options['qr_code_data'], config is in the position 0 config.
        if ( ! empty( $options['qr_code_data'] ) && isset( $config[0]['qr_code'] ) ) {
            $doc->set_qr_code( $options['qr_code_data'], $config[0]['qr_code'] );
        }

        // Add each module.
        foreach ( $modules as $position => $module_data ) {
            $position_config = $config[ $position ] ?? array();

            $result = $doc->add_module( (int) $position, $module_data, $position_config );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        return $doc;
    }
}
