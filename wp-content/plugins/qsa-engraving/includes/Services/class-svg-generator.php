<?php
/**
 * SVG Generator Service.
 *
 * High-level service for generating SVG files from module data.
 * Orchestrates Config_Loader, SVG_Document, and component renderers.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Services;

use Quadica\QSA_Engraving\SVG\SVG_Document;
use Quadica\QSA_Engraving\SVG\Coordinate_Transformer;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SVG Generator class.
 *
 * Generates complete SVG documents for engraving jobs:
 * - Loads configuration for QSA designs
 * - Creates SVG documents with all elements
 * - Supports batch generation for arrays
 *
 * @since 1.0.0
 */
class SVG_Generator {

    /**
     * Config Loader instance.
     *
     * @var Config_Loader
     */
    private Config_Loader $config_loader;

    /**
     * Constructor.
     *
     * @param Config_Loader|null $config_loader Optional config loader instance.
     */
    public function __construct( ?Config_Loader $config_loader = null ) {
        $this->config_loader = $config_loader ?? new Config_Loader();
    }

    /**
     * Generate SVG for a single module.
     *
     * @param array $module_data Module data with serial_number, module_id, led_codes, sku.
     * @param int   $position    Position on the array (1-8).
     * @return string|WP_Error SVG markup or error.
     */
    public function generate_single_module( array $module_data, int $position = 1 ): string|WP_Error {
        // Get configuration from SKU.
        $sku = $module_data['sku'] ?? $module_data['module_id'] ?? '';
        if ( empty( $sku ) ) {
            return new WP_Error(
                'missing_sku',
                __( 'Module data must include sku or module_id.', 'qsa-engraving' )
            );
        }

        $parsed = $this->config_loader->parse_sku( $sku );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        $config = $this->config_loader->get_config( $parsed['design'], $parsed['revision'] );
        if ( empty( $config ) ) {
            return new WP_Error(
                'no_config',
                sprintf(
                    /* translators: %s: Design name */
                    __( 'No configuration found for design: %s', 'qsa-engraving' ),
                    $parsed['design']
                )
            );
        }

        // Create document with single module.
        $doc = new SVG_Document();
        $doc->set_title( sprintf( 'Single Module: %s', $sku ) );

        $result = $doc->add_module( $position, $module_data, $config[ $position ] ?? array() );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $doc->render();
    }

    /**
     * Generate SVG for an array of modules.
     *
     * @param array  $modules     Array of module data indexed by position (1-8).
     * @param string $qsa_design  QSA design name.
     * @param string $revision    Optional revision letter.
     * @param array  $options     Optional document options.
     * @return string|WP_Error SVG markup or error.
     */
    public function generate_array(
        array $modules,
        string $qsa_design,
        ?string $revision = null,
        array $options = array()
    ): string|WP_Error {
        $config = $this->config_loader->get_config( $qsa_design, $revision );
        if ( empty( $config ) ) {
            return new WP_Error(
                'no_config',
                sprintf(
                    /* translators: %s: Design name */
                    __( 'No configuration found for design: %s', 'qsa-engraving' ),
                    $qsa_design
                )
            );
        }

        // Get calibration offsets.
        $calibration = $this->config_loader->get_calibration( $qsa_design, $revision );
        $options['calibration_x'] = $calibration['x'];
        $options['calibration_y'] = $calibration['y'];

        // Get rotation from settings if not provided in options.
        if ( ! isset( $options['rotation'] ) ) {
            $options['rotation'] = $this->get_rotation_setting();
        }

        // Get top offset from settings if not provided in options.
        if ( ! isset( $options['top_offset'] ) ) {
            $options['top_offset'] = $this->get_top_offset_setting();
        }

        // Create document from data.
        $doc = SVG_Document::create_from_data( $modules, $config, $qsa_design, $options );
        if ( is_wp_error( $doc ) ) {
            return $doc;
        }

        return $doc->render();
    }

    /**
     * Get the SVG rotation setting from WordPress options.
     *
     * @return int Rotation in degrees (0, 90, 180, 270).
     */
    public function get_rotation_setting(): int {
        $settings = get_option( 'qsa_engraving_settings', array() );
        $rotation = isset( $settings['svg_rotation'] ) ? (int) $settings['svg_rotation'] : 0;

        // Validate - only allow 0, 90, 180, 270.
        if ( ! in_array( $rotation, array( 0, 90, 180, 270 ), true ) ) {
            return 0;
        }

        return $rotation;
    }

    /**
     * Get the SVG top offset setting from WordPress options.
     *
     * @return float Top offset in mm (-5 to +5).
     */
    public function get_top_offset_setting(): float {
        $settings = get_option( 'qsa_engraving_settings', array() );
        $offset   = isset( $settings['svg_top_offset'] ) ? (float) $settings['svg_top_offset'] : 0.0;

        // Clamp to valid range.
        return max( -5.0, min( 5.0, $offset ) );
    }

    /**
     * Generate SVG for a batch of modules, split into arrays.
     *
     * @param array  $all_modules All modules to engrave.
     * @param string $qsa_design  QSA design name.
     * @param string $revision    Optional revision letter.
     * @param int    $start_position Starting position for first array (1-8).
     * @param array  $options     Optional document options.
     * @return array{arrays: array, module_count: int, array_count: int}|WP_Error
     */
    public function generate_batch(
        array $all_modules,
        string $qsa_design,
        ?string $revision = null,
        int $start_position = 1,
        array $options = array()
    ): array|WP_Error {
        // Validate start_position is within valid range (1-8).
        if ( $start_position < 1 || $start_position > 8 ) {
            return new WP_Error(
                'invalid_start_position',
                sprintf(
                    /* translators: %d: Position number */
                    __( 'Invalid start position: %d. Must be between 1 and 8.', 'qsa-engraving' ),
                    $start_position
                )
            );
        }

        $config = $this->config_loader->get_config( $qsa_design, $revision );
        if ( empty( $config ) ) {
            return new WP_Error(
                'no_config',
                sprintf(
                    /* translators: %s: Design name */
                    __( 'No configuration found for design: %s', 'qsa-engraving' ),
                    $qsa_design
                )
            );
        }

        $arrays      = array();
        $module_index = 0;
        $module_count = count( $all_modules );

        // Split modules into arrays of up to 8.
        while ( $module_index < $module_count ) {
            $array_modules = array();
            $position      = ( count( $arrays ) === 0 ) ? $start_position : 1;

            // Fill array positions.
            while ( $position <= 8 && $module_index < $module_count ) {
                $array_modules[ $position ] = $all_modules[ $module_index ];
                $position++;
                $module_index++;
            }

            // Generate SVG for this array.
            $array_options          = $options;
            $array_options['title'] = sprintf(
                'Array %d of %d - %s',
                count( $arrays ) + 1,
                (int) ceil( ( $module_count - $start_position + 1 ) / 8 ) + ( $start_position > 1 ? 1 : 0 ),
                $qsa_design
            );

            $svg = $this->generate_array( $array_modules, $qsa_design, $revision, $array_options );
            if ( is_wp_error( $svg ) ) {
                return $svg;
            }

            $arrays[] = array(
                'svg'          => $svg,
                'positions'    => array_keys( $array_modules ),
                'module_count' => count( $array_modules ),
            );
        }

        return array(
            'arrays'       => $arrays,
            'module_count' => $module_count,
            'array_count'  => count( $arrays ),
        );
    }

    /**
     * Validate that modules can be processed.
     *
     * @param array  $modules    Modules to validate.
     * @param string $qsa_design Design name.
     * @param string $revision   Optional revision.
     * @return true|WP_Error True if valid, WP_Error if not.
     */
    public function validate_modules(
        array $modules,
        string $qsa_design,
        ?string $revision = null
    ): true|WP_Error {
        // Check configuration exists.
        $config = $this->config_loader->get_config( $qsa_design, $revision );
        if ( empty( $config ) ) {
            return new WP_Error(
                'no_config',
                sprintf(
                    /* translators: %s: Design name */
                    __( 'No configuration found for design: %s', 'qsa-engraving' ),
                    $qsa_design
                )
            );
        }

        // Validate each module.
        foreach ( $modules as $index => $module ) {
            if ( empty( $module['serial_number'] ) ) {
                return new WP_Error(
                    'missing_serial',
                    sprintf(
                        /* translators: %d: Module index */
                        __( 'Module %d is missing serial_number.', 'qsa-engraving' ),
                        $index + 1
                    )
                );
            }

            // Validate serial format.
            if ( ! preg_match( '/^[0-9]{8}$/', $module['serial_number'] ) ) {
                return new WP_Error(
                    'invalid_serial',
                    sprintf(
                        /* translators: %s: Serial number */
                        __( 'Invalid serial number format: %s', 'qsa-engraving' ),
                        $module['serial_number']
                    )
                );
            }
        }

        return true;
    }

    /**
     * Get module count that would fit in arrays with a starting offset.
     *
     * @param int $total_modules Total modules to place.
     * @param int $start_position Starting position (1-8).
     * @return array{array_count: int, last_array_count: int}
     */
    public function calculate_array_breakdown( int $total_modules, int $start_position = 1 ): array {
        if ( $total_modules <= 0 ) {
            return array(
                'array_count'      => 0,
                'last_array_count' => 0,
            );
        }

        // Clamp start_position to valid range (1-8).
        $start_position = max( 1, min( 8, $start_position ) );

        // First array can hold (9 - start_position) modules.
        $first_array_capacity = 9 - $start_position;
        $remaining            = max( 0, $total_modules - $first_array_capacity );

        // Additional full arrays needed.
        $additional_arrays = (int) ceil( $remaining / 8 );
        $total_arrays      = 1 + $additional_arrays;

        // Last array module count.
        if ( $remaining <= 0 ) {
            $last_count = $total_modules;
        } else {
            $last_count = $remaining % 8;
            if ( $last_count === 0 && $remaining > 0 ) {
                $last_count = 8;
            }
        }

        return array(
            'array_count'      => $total_arrays,
            'last_array_count' => $last_count,
        );
    }

    /**
     * Get the Config Loader instance.
     *
     * @return Config_Loader
     */
    public function get_config_loader(): Config_Loader {
        return $this->config_loader;
    }

    /**
     * Check if SVG generation dependencies are available.
     *
     * @return array{ready: bool, issues: array}
     */
    public function check_dependencies(): array {
        $issues = array();

        // Check tc-lib-barcode (required for QR code generation).
        if ( ! class_exists( '\Com\Tecnick\Barcode\Barcode' ) ) {
            $issues[] = __( 'tc-lib-barcode library not installed. QR codes require this library.', 'qsa-engraving' );
        }

        return array(
            'ready'  => empty( $issues ),
            'issues' => $issues,
        );
    }
}
