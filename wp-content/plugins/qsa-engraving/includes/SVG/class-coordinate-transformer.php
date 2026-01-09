<?php
/**
 * Coordinate Transformer.
 *
 * Transforms coordinates between CAD (bottom-left origin) and SVG (top-left origin) coordinate systems.
 * Handles QSA-specific calibration offsets from configuration.
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
 * Coordinate Transformer class.
 *
 * Transforms coordinates for QSA array layouts:
 * - CAD coordinates use bottom-left origin (standard engineering convention)
 * - SVG coordinates use top-left origin (standard web/graphics convention)
 * - Formula: svg_y = canvas_height - cad_y
 *
 * Canvas dimensions for QSA Standard Array: 148mm x 113.7mm
 *
 * @since 1.0.0
 */
class Coordinate_Transformer {

    /**
     * Default canvas width in millimeters.
     *
     * @var float
     */
    public const DEFAULT_CANVAS_WIDTH = 148.0;

    /**
     * Default canvas height in millimeters.
     *
     * @var float
     */
    public const DEFAULT_CANVAS_HEIGHT = 113.7;

    /**
     * Canvas width in millimeters.
     *
     * @var float
     */
    private float $canvas_width;

    /**
     * Canvas height in millimeters.
     *
     * @var float
     */
    private float $canvas_height;

    /**
     * Calibration X offset in millimeters.
     *
     * @var float
     */
    private float $calibration_x = 0.0;

    /**
     * Calibration Y offset in millimeters.
     *
     * @var float
     */
    private float $calibration_y = 0.0;

    /**
     * Constructor.
     *
     * @param float $canvas_width  Canvas width in mm (default 148.0).
     * @param float $canvas_height Canvas height in mm (default 113.7).
     */
    public function __construct(
        float $canvas_width = self::DEFAULT_CANVAS_WIDTH,
        float $canvas_height = self::DEFAULT_CANVAS_HEIGHT
    ) {
        $this->canvas_width  = $canvas_width;
        $this->canvas_height = $canvas_height;
    }

    /**
     * Set calibration offsets.
     *
     * Calibration offsets are applied after coordinate transformation
     * to compensate for mechanical alignment differences.
     *
     * @param float $x_offset X calibration offset in mm.
     * @param float $y_offset Y calibration offset in mm.
     * @return self For method chaining.
     */
    public function set_calibration( float $x_offset, float $y_offset ): self {
        $this->calibration_x = $x_offset;
        $this->calibration_y = $y_offset;
        return $this;
    }

    /**
     * Get calibration offsets.
     *
     * @return array{x: float, y: float} Calibration offsets.
     */
    public function get_calibration(): array {
        return array(
            'x' => $this->calibration_x,
            'y' => $this->calibration_y,
        );
    }

    /**
     * Transform CAD Y coordinate to SVG Y coordinate.
     *
     * CAD uses bottom-left origin, SVG uses top-left origin.
     * Formula: svg_y = canvas_height - cad_y
     *
     * @param float $cad_y Y coordinate in CAD format.
     * @return float Y coordinate in SVG format.
     */
    public function cad_to_svg_y( float $cad_y ): float {
        return $this->canvas_height - $cad_y + $this->calibration_y;
    }

    /**
     * Transform SVG Y coordinate to CAD Y coordinate.
     *
     * Inverse of cad_to_svg_y().
     * Formula: cad_y = canvas_height - svg_y
     *
     * @param float $svg_y Y coordinate in SVG format.
     * @return float Y coordinate in CAD format.
     */
    public function svg_to_cad_y( float $svg_y ): float {
        return $this->canvas_height - $svg_y - $this->calibration_y;
    }

    /**
     * Transform CAD X coordinate to SVG X coordinate.
     *
     * X coordinates are the same in both systems (both increase rightward),
     * but calibration offset is applied.
     *
     * @param float $cad_x X coordinate in CAD format.
     * @return float X coordinate in SVG format.
     */
    public function cad_to_svg_x( float $cad_x ): float {
        return $cad_x + $this->calibration_x;
    }

    /**
     * Transform SVG X coordinate to CAD X coordinate.
     *
     * @param float $svg_x X coordinate in SVG format.
     * @return float X coordinate in CAD format.
     */
    public function svg_to_cad_x( float $svg_x ): float {
        return $svg_x - $this->calibration_x;
    }

    /**
     * Transform CAD coordinates to SVG coordinates.
     *
     * @param float $cad_x X coordinate in CAD format.
     * @param float $cad_y Y coordinate in CAD format.
     * @return array{x: float, y: float} Coordinates in SVG format.
     */
    public function cad_to_svg( float $cad_x, float $cad_y ): array {
        return array(
            'x' => $this->cad_to_svg_x( $cad_x ),
            'y' => $this->cad_to_svg_y( $cad_y ),
        );
    }

    /**
     * Transform SVG coordinates to CAD coordinates.
     *
     * @param float $svg_x X coordinate in SVG format.
     * @param float $svg_y Y coordinate in SVG format.
     * @return array{x: float, y: float} Coordinates in CAD format.
     */
    public function svg_to_cad( float $svg_x, float $svg_y ): array {
        return array(
            'x' => $this->svg_to_cad_x( $svg_x ),
            'y' => $this->svg_to_cad_y( $svg_y ),
        );
    }

    /**
     * Clamp coordinates to canvas bounds.
     *
     * Ensures coordinates stay within the valid canvas area.
     *
     * @param float $x X coordinate.
     * @param float $y Y coordinate.
     * @return array{x: float, y: float} Clamped coordinates.
     */
    public function clamp_to_bounds( float $x, float $y ): array {
        return array(
            'x' => max( 0.0, min( $this->canvas_width, $x ) ),
            'y' => max( 0.0, min( $this->canvas_height, $y ) ),
        );
    }

    /**
     * Check if coordinates are within canvas bounds.
     *
     * @param float $x X coordinate.
     * @param float $y Y coordinate.
     * @return bool True if within bounds.
     */
    public function is_within_bounds( float $x, float $y ): bool {
        return $x >= 0.0 && $x <= $this->canvas_width
            && $y >= 0.0 && $y <= $this->canvas_height;
    }

    /**
     * Get canvas dimensions.
     *
     * @return array{width: float, height: float} Canvas dimensions in mm.
     */
    public function get_canvas_dimensions(): array {
        return array(
            'width'  => $this->canvas_width,
            'height' => $this->canvas_height,
        );
    }

    /**
     * Calculate Micro-ID position offset for SVG placement.
     *
     * The Micro-ID center point from CSV needs adjustment for SVG rendering.
     * The Micro-ID grid is 1mm x 1mm, so we offset by 0.5mm to position
     * from the grid's top-left corner.
     *
     * @param float $cad_x CAD X coordinate (center of Micro-ID).
     * @param float $cad_y CAD Y coordinate (center of Micro-ID).
     * @return array{x: float, y: float} SVG coordinates for translate transform.
     */
    public function get_micro_id_transform( float $cad_x, float $cad_y ): array {
        $svg = $this->cad_to_svg( $cad_x, $cad_y );

        // Offset by 0.5mm to convert from center to top-left of 1mm grid.
        return array(
            'x' => $svg['x'] - 0.5,
            'y' => $svg['y'] - 0.5,
        );
    }

    /**
     * Create a transformer with calibration from configuration array.
     *
     * @param array $config Configuration with optional 'calibration_x' and 'calibration_y' keys.
     * @return self Configured transformer instance.
     */
    public static function from_config( array $config ): self {
        $transformer = new self(
            $config['canvas_width'] ?? self::DEFAULT_CANVAS_WIDTH,
            $config['canvas_height'] ?? self::DEFAULT_CANVAS_HEIGHT
        );

        if ( isset( $config['calibration_x'] ) || isset( $config['calibration_y'] ) ) {
            $transformer->set_calibration(
                $config['calibration_x'] ?? 0.0,
                $config['calibration_y'] ?? 0.0
            );
        }

        return $transformer;
    }
}
