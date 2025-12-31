<?php
/**
 * SVG Document Assembler.
 *
 * Generates complete SVG documents for QSA engraving with all element types:
 * Micro-ID codes, Data Matrix barcodes, and text elements.
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
     * Coordinate transformer instance.
     *
     * @var Coordinate_Transformer
     */
    private Coordinate_Transformer $transformer;

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

        // Add alignment marks.
        if ( $this->include_boundary ) {
            $output .= "\n\n  " . $this->render_boundary();
        }

        if ( $this->include_crosshair ) {
            $output .= "\n  " . $this->render_crosshair();
        }

        // Render each module.
        ksort( $this->modules );
        foreach ( $this->modules as $position => $module ) {
            $module_svg = $this->render_module( $position, $module['data'], $module['config'] );
            if ( is_wp_error( $module_svg ) ) {
                return $module_svg;
            }
            $output .= "\n\n  " . $module_svg;
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
     * @return string SVG opening tag.
     */
    private function render_svg_open(): string {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg"
     width="%smm" height="%smm"
     viewBox="0 0 %s %s">',
            number_format( $this->width, 1, '.', '' ),
            number_format( $this->height, 1, '.', '' ),
            number_format( $this->width, 1, '.', '' ),
            number_format( $this->height, 1, '.', '' )
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

        // Data Matrix.
        if ( isset( $config['datamatrix'] ) ) {
            $datamatrix_svg = $this->render_datamatrix( $position, $serial_number, $config['datamatrix'] );
            if ( is_wp_error( $datamatrix_svg ) ) {
                return $datamatrix_svg;
            }
            $elements[] = '  ' . $datamatrix_svg;
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
        foreach ( $led_codes as $index => $led_code ) {
            $config_key = 'led_code_' . ( $index + 1 );
            if ( ! empty( $led_code ) && isset( $config[ $config_key ] ) ) {
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

        return Micro_ID_Encoder::render_svg_positioned(
            $serial_int,
            $svg_coords['x'],
            $svg_coords['y'],
            sprintf( 'micro-id-%d', $position )
        );
    }

    /**
     * Render Data Matrix element.
     *
     * @param int    $position      Module position.
     * @param string $serial_number Serial number.
     * @param array  $config        Element configuration.
     * @return string|WP_Error SVG markup or error.
     */
    private function render_datamatrix( int $position, string $serial_number, array $config ): string|WP_Error {
        // Transform CAD coordinates to SVG.
        $svg_coords = $this->transformer->get_datamatrix_position(
            $config['origin_x'],
            $config['origin_y']
        );

        return Datamatrix_Renderer::render_positioned(
            $serial_number,
            $svg_coords['x'],
            $svg_coords['y'],
            Datamatrix_Renderer::DEFAULT_WIDTH,
            Datamatrix_Renderer::DEFAULT_HEIGHT,
            sprintf( 'datamatrix-%d', $position )
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
