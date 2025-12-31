<?php
/**
 * Configuration Loader Service.
 *
 * Loads and caches QSA configuration data for SVG generation.
 * Bridges Config_Repository with SVG components.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Services;

use Quadica\QSA_Engraving\Database\Config_Repository;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Configuration Loader class.
 *
 * Provides configuration data for SVG generation:
 * - Loads element positions from database
 * - Parses QSA design codes from module SKUs
 * - Caches configurations per request
 * - Supports design revisions
 *
 * @since 1.0.0
 */
class Config_Loader {

    /**
     * Config Repository instance.
     *
     * @var Config_Repository
     */
    private Config_Repository $repository;

    /**
     * Runtime cache for loaded configurations.
     *
     * @var array
     */
    private array $cache = array();

    /**
     * Default text heights in millimeters.
     *
     * @var array
     */
    public const DEFAULT_TEXT_HEIGHTS = array(
        'module_id'  => 1.5,
        'serial_url' => 1.2,
        'led_code_1' => 1.0,
        'led_code_2' => 1.0,
        'led_code_3' => 1.0,
        'led_code_4' => 1.0,
        'led_code_5' => 1.0,
        'led_code_6' => 1.0,
        'led_code_7' => 1.0,
        'led_code_8' => 1.0,
        'led_code_9' => 1.0,
    );

    /**
     * Constructor.
     *
     * @param Config_Repository|null $repository Optional repository instance.
     */
    public function __construct( ?Config_Repository $repository = null ) {
        $this->repository = $repository ?? new Config_Repository();
    }

    /**
     * Get configuration for a QSA design.
     *
     * @param string      $qsa_design QSA design name (e.g., "STAR").
     * @param string|null $revision   Optional revision letter (e.g., "a").
     * @return array Configuration indexed by position and element type.
     */
    public function get_config( string $qsa_design, ?string $revision = null ): array {
        $cache_key = $qsa_design . '_' . ( $revision ?? 'default' );

        if ( isset( $this->cache[ $cache_key ] ) ) {
            return $this->cache[ $cache_key ];
        }

        $config = $this->repository->get_config( $qsa_design, $revision );

        // Apply default text heights where not specified.
        $config = $this->apply_default_text_heights( $config );

        $this->cache[ $cache_key ] = $config;
        return $config;
    }

    /**
     * Parse QSA design and revision from a module SKU.
     *
     * SKU format: 4 uppercase letters + lowercase revision + hyphen + 5 digits
     * Example: "STARa-38546" => design="STAR", revision="a"
     *
     * @param string $sku Module SKU.
     * @return array{design: string, revision: string|null}|WP_Error Parsed info or error.
     */
    public function parse_sku( string $sku ): array|WP_Error {
        // Pattern: 4 uppercase + 1 lowercase + hyphen + 5 digits.
        if ( ! preg_match( '/^([A-Z]{4})([a-z])?-(\d{5})$/', $sku, $matches ) ) {
            return new WP_Error(
                'invalid_sku_format',
                sprintf(
                    /* translators: %s: SKU */
                    __( 'SKU "%s" does not match QSA format (e.g., STARa-38546).', 'qsa-engraving' ),
                    $sku
                )
            );
        }

        // Note: When optional group doesn't match, it's an empty string, not missing.
        $revision = isset( $matches[2] ) && '' !== $matches[2] ? $matches[2] : null;

        return array(
            'design'   => $matches[1],
            'revision' => $revision,
            'config'   => $matches[3],
        );
    }

    /**
     * Get configuration for a module SKU.
     *
     * Convenience method that parses SKU and loads config.
     *
     * @param string $sku Module SKU.
     * @return array|WP_Error Configuration or error.
     */
    public function get_config_for_sku( string $sku ): array|WP_Error {
        $parsed = $this->parse_sku( $sku );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        return $this->get_config( $parsed['design'], $parsed['revision'] );
    }

    /**
     * Apply default text heights to configuration.
     *
     * @param array $config Configuration array.
     * @return array Configuration with defaults applied.
     */
    private function apply_default_text_heights( array $config ): array {
        foreach ( $config as $position => $elements ) {
            foreach ( $elements as $type => $element_config ) {
                // Only apply to text elements.
                if ( $this->is_text_element( $type ) && ! isset( $element_config['text_height'] ) ) {
                    $config[ $position ][ $type ]['text_height'] =
                        self::DEFAULT_TEXT_HEIGHTS[ $type ] ?? 1.0;
                }
            }
        }

        return $config;
    }

    /**
     * Check if element type is a text element.
     *
     * @param string $type Element type.
     * @return bool True if text element.
     */
    private function is_text_element( string $type ): bool {
        $text_types = array( 'module_id', 'serial_url' );

        // LED code elements are also text.
        if ( strpos( $type, 'led_code_' ) === 0 ) {
            return true;
        }

        return in_array( $type, $text_types, true );
    }

    /**
     * Get all available QSA designs.
     *
     * @return array Array of design names.
     */
    public function get_available_designs(): array {
        return $this->repository->get_designs();
    }

    /**
     * Get all revisions for a design.
     *
     * @param string $qsa_design Design name.
     * @return array Array of revision letters.
     */
    public function get_design_revisions( string $qsa_design ): array {
        return $this->repository->get_revisions( $qsa_design );
    }

    /**
     * Check if a design has configuration.
     *
     * @param string      $qsa_design Design name.
     * @param string|null $revision   Optional revision.
     * @return bool True if configuration exists.
     */
    public function has_config( string $qsa_design, ?string $revision = null ): bool {
        $config = $this->get_config( $qsa_design, $revision );
        return ! empty( $config );
    }

    /**
     * Get element configuration for a specific position.
     *
     * @param string      $qsa_design   Design name.
     * @param int         $position     Position (1-8).
     * @param string      $element_type Element type.
     * @param string|null $revision     Optional revision.
     * @return array|null Element config or null.
     */
    public function get_element_config(
        string $qsa_design,
        int $position,
        string $element_type,
        ?string $revision = null
    ): ?array {
        $config = $this->get_config( $qsa_design, $revision );
        return $config[ $position ][ $element_type ] ?? null;
    }

    /**
     * Validate configuration completeness for a design.
     *
     * Checks that all required elements have positions configured.
     *
     * @param string      $qsa_design Design name.
     * @param string|null $revision   Optional revision.
     * @param int         $positions  Number of positions to check (default 8).
     * @return array{valid: bool, missing: array} Validation result.
     */
    public function validate_config(
        string $qsa_design,
        ?string $revision = null,
        int $positions = 8
    ): array {
        $config  = $this->get_config( $qsa_design, $revision );
        $missing = array();

        // Required elements for each position.
        $required = array( 'micro_id', 'datamatrix', 'module_id', 'serial_url', 'led_code_1' );

        for ( $pos = 1; $pos <= $positions; $pos++ ) {
            if ( ! isset( $config[ $pos ] ) ) {
                $missing[] = sprintf( 'Position %d: all elements', $pos );
                continue;
            }

            foreach ( $required as $element ) {
                if ( ! isset( $config[ $pos ][ $element ] ) ) {
                    $missing[] = sprintf( 'Position %d: %s', $pos, $element );
                }
            }
        }

        return array(
            'valid'   => empty( $missing ),
            'missing' => $missing,
        );
    }

    /**
     * Clear the configuration cache.
     *
     * @return void
     */
    public function clear_cache(): void {
        $this->cache = array();
        $this->repository->clear_cache();
    }

    /**
     * Get calibration offsets for a design.
     *
     * Currently returns zeros; can be extended to read from settings.
     *
     * @param string      $qsa_design Design name.
     * @param string|null $revision   Optional revision.
     * @return array{x: float, y: float} Calibration offsets.
     */
    public function get_calibration( string $qsa_design, ?string $revision = null ): array {
        // Future: Read from options or config table.
        // For now, return zeros (no calibration offset).
        return array(
            'x' => 0.0,
            'y' => 0.0,
        );
    }

    /**
     * Get canvas dimensions for a design.
     *
     * Returns standard QSA canvas dimensions.
     *
     * @param string $qsa_design Design name.
     * @return array{width: float, height: float} Canvas dimensions in mm.
     */
    public function get_canvas_dimensions( string $qsa_design ): array {
        // Standard QSA canvas for all designs.
        return array(
            'width'  => 148.0,
            'height' => 113.7,
        );
    }

    /**
     * Get the Config Repository instance.
     *
     * @return Config_Repository
     */
    public function get_repository(): Config_Repository {
        return $this->repository;
    }
}
