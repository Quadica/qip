<?php
/**
 * Config Repository.
 *
 * Handles CRUD operations for the quad_qsa_config table.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Database;

use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repository class for QSA configuration operations.
 *
 * @since 1.0.0
 */
class Config_Repository {

    /**
     * Valid element types.
     *
     * Note: 'datamatrix' was removed in Phase 2 of QR code implementation.
     * 'qr_code' is a design-level element (position=0) added in Phase 4.
     *
     * @var array
     */
    public const ELEMENT_TYPES = array(
        'micro_id',
        'qr_code',
        'module_id',
        'serial_url',
        'led_code_1',
        'led_code_2',
        'led_code_3',
        'led_code_4',
        'led_code_5',
        'led_code_6',
        'led_code_7',
        'led_code_8',
        'led_code_9',
    );

    /**
     * Design-level element types that use position 0.
     *
     * These elements appear once per SVG at the design level,
     * not per module position (1-8).
     *
     * @var array
     */
    public const DESIGN_LEVEL_ELEMENTS = array(
        'qr_code',
    );

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Table name with prefix.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Runtime cache for configuration lookups.
     *
     * @var array
     */
    private array $cache = array();

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb       = $wpdb;
        $this->table_name = $wpdb->prefix . 'quad_qsa_config';
    }

    /**
     * Get the table name.
     *
     * @return string
     */
    public function get_table_name(): string {
        return $this->table_name;
    }

    /**
     * Check if the table exists.
     *
     * @return bool
     */
    public function table_exists(): bool {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->table_name
            )
        );
        return $result === $this->table_name;
    }

    /**
     * Get configuration for a QSA design.
     *
     * Retrieves all element positions for a design, with revision-specific
     * configurations taking precedence over default (null revision) configs.
     * Falls back to any available revision if no exact match is found.
     *
     * WARNING: If fallback is used, a warning is logged via error_log() to
     * alert administrators that the requested configuration was not found.
     *
     * @param string      $qsa_design The design name (e.g., "CORE", "STAR").
     * @param string|null $revision The revision letter (e.g., "a") or null.
     * @return array Configuration data indexed by position and element type.
     */
    public function get_config( string $qsa_design, ?string $revision = null ): array {
        $cache_key = $qsa_design . '_' . ( $revision ?? 'default' );

        if ( isset( $this->cache[ $cache_key ] ) ) {
            return $this->cache[ $cache_key ];
        }

        $used_fallback    = false;
        $fallback_revision = null;

        // Try to get specific revision config, or fall back to any available revision.
        // Priority: exact revision match > null revision > any revision (alphabetically first).
        if ( null !== $revision && '' !== $revision ) {
            // Specific revision requested.
            $sql = "SELECT position, element_type, origin_x, origin_y, rotation, text_height, element_size
                    FROM {$this->table_name}
                    WHERE qsa_design = %s
                      AND revision = %s
                      AND is_active = 1
                    ORDER BY position, element_type";
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare( $sql, $qsa_design, $revision ),
                ARRAY_A
            ) ?: array();

            // If specific revision not found, fall back to any available revision.
            if ( empty( $results ) ) {
                $sql = "SELECT position, element_type, origin_x, origin_y, rotation, text_height, element_size, revision
                        FROM {$this->table_name}
                        WHERE qsa_design = %s
                          AND is_active = 1
                        ORDER BY revision, position, element_type";
                $results = $this->wpdb->get_results(
                    $this->wpdb->prepare( $sql, $qsa_design ),
                    ARRAY_A
                ) ?: array();

                if ( ! empty( $results ) ) {
                    $used_fallback     = true;
                    $fallback_revision = $results[0]['revision'] ?? 'unknown';
                }
            }
        } else {
            // No specific revision - try NULL first, then fall back to first available.
            $sql = "SELECT position, element_type, origin_x, origin_y, rotation, text_height, element_size
                    FROM {$this->table_name}
                    WHERE qsa_design = %s
                      AND revision IS NULL
                      AND is_active = 1
                    ORDER BY position, element_type";
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare( $sql, $qsa_design ),
                ARRAY_A
            ) ?: array();

            // Fall back to first available revision if no NULL revision config.
            if ( empty( $results ) ) {
                $sql = "SELECT position, element_type, origin_x, origin_y, rotation, text_height, element_size, revision
                        FROM {$this->table_name}
                        WHERE qsa_design = %s
                          AND is_active = 1
                        ORDER BY revision, position, element_type";
                $results = $this->wpdb->get_results(
                    $this->wpdb->prepare( $sql, $qsa_design ),
                    ARRAY_A
                ) ?: array();

                if ( ! empty( $results ) ) {
                    $used_fallback     = true;
                    $fallback_revision = $results[0]['revision'] ?? 'unknown';
                }
            }
        }

        // Log warning if fallback was used.
        if ( $used_fallback ) {
            error_log(
                sprintf(
                    'QSA Engraving: Config revision fallback - requested %s revision %s, using revision %s instead. Verify QSA coordinates are correct.',
                    $qsa_design,
                    $revision ?? 'NULL',
                    $fallback_revision
                )
            );
        }

        // Organize by position and element type.
        // Revision-specific configs override defaults due to ORDER BY revision DESC.
        $config = array();
        foreach ( $results as $row ) {
            $pos  = (int) $row['position'];
            $type = $row['element_type'];

            // Only set if not already set (revision-specific comes first).
            if ( ! isset( $config[ $pos ][ $type ] ) ) {
                $config[ $pos ][ $type ] = array(
                    'origin_x'     => (float) $row['origin_x'],
                    'origin_y'     => (float) $row['origin_y'],
                    'rotation'     => (int) $row['rotation'],
                    'text_height'  => $row['text_height'] !== null ? (float) $row['text_height'] : null,
                    'element_size' => $row['element_size'] !== null ? (float) $row['element_size'] : null,
                );
            }
        }

        $this->cache[ $cache_key ] = $config;
        return $config;
    }

    /**
     * Get configuration for a specific element at a position.
     *
     * @param string      $qsa_design The design name.
     * @param int         $position The position (1-8).
     * @param string      $element_type The element type.
     * @param string|null $revision The revision letter or null.
     * @return array|null Element configuration or null if not found.
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
     * Get all available QSA designs.
     *
     * @return array Array of unique design names.
     */
    public function get_designs(): array {
        $results = $this->wpdb->get_col(
            "SELECT DISTINCT qsa_design FROM {$this->table_name} WHERE is_active = 1 ORDER BY qsa_design"
        );

        return $results ?: array();
    }

    /**
     * Get all revisions for a design.
     *
     * @param string $qsa_design The design name.
     * @return array Array of revision letters (null represented as 'default').
     */
    public function get_revisions( string $qsa_design ): array {
        $results = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT COALESCE(revision, 'default') as rev
                 FROM {$this->table_name}
                 WHERE qsa_design = %s AND is_active = 1
                 ORDER BY rev",
                $qsa_design
            )
        );

        return $results ?: array();
    }

    /**
     * Set configuration for an element.
     *
     * @param string      $qsa_design The design name.
     * @param string|null $revision The revision letter or null.
     * @param int         $position The position (0 for design-level elements, 1-8 for module positions).
     * @param string      $element_type The element type.
     * @param float       $origin_x The X coordinate.
     * @param float       $origin_y The Y coordinate.
     * @param int         $rotation The rotation in degrees.
     * @param float|null  $text_height The text height for text elements.
     * @return int|WP_Error The config ID or WP_Error on failure.
     */
    public function set_element_config(
        string $qsa_design,
        ?string $revision,
        int $position,
        string $element_type,
        float $origin_x,
        float $origin_y,
        int $rotation = 0,
        ?float $text_height = null
    ): int|WP_Error {
        // Validate element type.
        if ( ! in_array( $element_type, self::ELEMENT_TYPES, true ) ) {
            return new WP_Error(
                'invalid_element_type',
                sprintf(
                    /* translators: %s: Element type */
                    __( 'Invalid element type: %s', 'qsa-engraving' ),
                    $element_type
                )
            );
        }

        // Validate position based on element type.
        // Design-level elements (e.g., qr_code) use position 0.
        // Module-level elements use positions 1-8.
        $is_design_level = in_array( $element_type, self::DESIGN_LEVEL_ELEMENTS, true );

        if ( $is_design_level ) {
            // Design-level elements must be at position 0.
            if ( 0 !== $position ) {
                return new WP_Error(
                    'invalid_position',
                    sprintf(
                        /* translators: %s: Element type */
                        __( 'Element type %s must be at position 0 (design-level).', 'qsa-engraving' ),
                        $element_type
                    )
                );
            }
        } else {
            // Module-level elements must be at positions 1-8.
            if ( $position < 1 || $position > 8 ) {
                return new WP_Error(
                    'invalid_position',
                    __( 'Position must be between 1 and 8 for module-level elements.', 'qsa-engraving' )
                );
            }
        }

        // Check if exists (upsert).
        // Build revision clause separately to handle NULL correctly.
        if ( is_null( $revision ) ) {
            $existing = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->table_name}
                     WHERE qsa_design = %s
                       AND revision IS NULL
                       AND position = %d
                       AND element_type = %s",
                    $qsa_design,
                    $position,
                    $element_type
                )
            );
        } else {
            $existing = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->table_name}
                     WHERE qsa_design = %s
                       AND revision = %s
                       AND position = %d
                       AND element_type = %s",
                    $qsa_design,
                    $revision,
                    $position,
                    $element_type
                )
            );
        }

        if ( $existing ) {
            // Update existing.
            // Build data and format arrays conditionally to handle NULL text_height.
            $update_data = array(
                'origin_x'  => $origin_x,
                'origin_y'  => $origin_y,
                'rotation'  => $rotation,
                'is_active' => 1,
            );
            $update_format = array( '%f', '%f', '%d', '%d' );

            // Handle text_height: use raw SQL for NULL, or add to data array.
            if ( is_null( $text_height ) ) {
                // Set text_height to NULL explicitly via raw query update.
                $this->wpdb->query(
                    $this->wpdb->prepare(
                        "UPDATE {$this->table_name} SET text_height = NULL WHERE id = %d",
                        $existing
                    )
                );
            } else {
                $update_data['text_height'] = $text_height;
                // Insert format at position 3 (after rotation).
                array_splice( $update_format, 3, 0, '%f' );
            }

            $result = $this->wpdb->update(
                $this->table_name,
                $update_data,
                array( 'id' => $existing ),
                $update_format,
                array( '%d' )
            );

            if ( false === $result ) {
                return new WP_Error( 'update_failed', $this->wpdb->last_error );
            }

            // Clear cache.
            $this->cache = array();

            return (int) $existing;
        }

        // Insert new.
        // Build data and format arrays conditionally to handle NULL values.
        $insert_data = array(
            'qsa_design'   => $qsa_design,
            'revision'     => $revision,
            'position'     => $position,
            'element_type' => $element_type,
            'origin_x'     => $origin_x,
            'origin_y'     => $origin_y,
            'rotation'     => $rotation,
            'is_active'    => 1,
            'created_by'   => get_current_user_id(),
        );
        $insert_format = array( '%s', '%s', '%d', '%s', '%f', '%f', '%d', '%d', '%d' );

        // Handle text_height: only add if not NULL.
        if ( ! is_null( $text_height ) ) {
            $insert_data['text_height'] = $text_height;
            // Insert format at position 7 (after rotation).
            array_splice( $insert_format, 7, 0, '%f' );
        }
        // If text_height is NULL, omit from insert - database column default handles it.

        $result = $this->wpdb->insert(
            $this->table_name,
            $insert_data,
            $insert_format
        );

        if ( false === $result ) {
            return new WP_Error( 'insert_failed', $this->wpdb->last_error );
        }

        // Clear cache.
        $this->cache = array();

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Deactivate a configuration (soft delete).
     *
     * @param int $config_id The configuration ID.
     * @return bool
     */
    public function deactivate( int $config_id ): bool {
        $result = $this->wpdb->update(
            $this->table_name,
            array( 'is_active' => 0 ),
            array( 'id' => $config_id ),
            array( '%d' ),
            array( '%d' )
        );

        if ( false !== $result ) {
            $this->cache = array();
        }

        return false !== $result;
    }

    /**
     * Transform CAD coordinates (bottom-left origin) to SVG coordinates (top-left origin).
     *
     * @param float $cad_y The Y coordinate in CAD format.
     * @param float $canvas_height The canvas height (default 113.7mm for QSA).
     * @return float The Y coordinate in SVG format.
     */
    public static function cad_to_svg_y( float $cad_y, float $canvas_height = 113.7 ): float {
        return $canvas_height - $cad_y;
    }

    /**
     * Clear the runtime cache.
     *
     * @return void
     */
    public function clear_cache(): void {
        $this->cache = array();
    }
}
