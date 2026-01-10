<?php
/**
 * SKU Mapping Repository.
 *
 * Handles CRUD operations for the quad_sku_mappings table.
 * Maps legacy SKU patterns to canonical 4-letter QSA design codes.
 *
 * @package QSA_Engraving
 * @since 1.1.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Database;

use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repository class for SKU mapping operations.
 *
 * Provides methods for:
 * - Finding mappings that match a given SKU
 * - CRUD operations for managing mappings
 * - Testing pattern matches
 *
 * @since 1.1.0
 */
class SKU_Mapping_Repository {

    /**
     * Valid match types.
     *
     * @var array
     */
    public const MATCH_TYPES = array( 'exact', 'prefix', 'suffix', 'regex' );

    /**
     * Maximum length for legacy_pattern column.
     *
     * @var int
     */
    public const MAX_PATTERN_LENGTH = 50;

    /**
     * Maximum priority value (SMALLINT UNSIGNED).
     *
     * @var int
     */
    public const MAX_PRIORITY = 65535;

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
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb       = $wpdb;
        $this->table_name = $wpdb->prefix . 'quad_sku_mappings';
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
     * Uses esc_like() to escape underscores in table prefix to prevent
     * false positives from wildcard matching.
     *
     * @return bool
     */
    public function table_exists(): bool {
        $escaped_name = $this->wpdb->esc_like( $this->table_name );
        $result       = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $escaped_name
            )
        );
        return $result === $this->table_name;
    }

    /**
     * Find the matching mapping for a SKU.
     *
     * Returns the mapping with the lowest priority (highest precedence).
     * Returns null if no mapping matches.
     *
     * The matching is done in priority order:
     * 1. Exact matches first (case-insensitive per database collation)
     * 2. Prefix matches (SKU starts with pattern, case-insensitive)
     * 3. Suffix matches (SKU ends with pattern, case-insensitive)
     * 4. Regex matches (case sensitivity depends on pattern flags)
     *
     * Note: Pattern matching uses the database's utf8mb4_unicode_ci collation,
     * which is case-insensitive. The test_pattern() method mirrors this behavior.
     *
     * @param string $sku The SKU to match.
     * @return array|null The mapping data or null if no match.
     */
    public function find_mapping( string $sku ): ?array {
        if ( empty( $sku ) ) {
            return null;
        }

        // First, try exact match (most specific).
        $exact = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE legacy_pattern = %s
                  AND match_type = 'exact'
                  AND is_active = 1
                ORDER BY priority ASC
                LIMIT 1",
                $sku
            ),
            ARRAY_A
        );

        if ( $exact ) {
            return $exact;
        }

        // Try prefix match.
        $prefix = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE match_type = 'prefix'
                  AND is_active = 1
                  AND %s LIKE CONCAT(legacy_pattern, '%%')
                ORDER BY priority ASC, LENGTH(legacy_pattern) DESC
                LIMIT 1",
                $sku
            ),
            ARRAY_A
        );

        if ( $prefix ) {
            return $prefix;
        }

        // Try suffix match.
        $suffix = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE match_type = 'suffix'
                  AND is_active = 1
                  AND %s LIKE CONCAT('%%', legacy_pattern)
                ORDER BY priority ASC, LENGTH(legacy_pattern) DESC
                LIMIT 1",
                $sku
            ),
            ARRAY_A
        );

        if ( $suffix ) {
            return $suffix;
        }

        // Try regex match (most expensive, done last).
        // Get all active regex patterns ordered by priority.
        // Note: No user input in this query, so prepare() is not needed.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $regexes = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name}
            WHERE match_type = 'regex'
              AND is_active = 1
            ORDER BY priority ASC",
            ARRAY_A
        );

        foreach ( $regexes as $regex ) {
            // Test each regex pattern in PHP for safety.
            // Using preg_match instead of MySQL REGEXP for better control.
            $pattern = $regex['legacy_pattern'];

            // Ensure pattern has delimiters for preg_match.
            if ( ! str_starts_with( $pattern, '/' ) && ! str_starts_with( $pattern, '#' ) ) {
                $pattern = '/' . $pattern . '/';
            }

            // Suppress warnings from invalid regex patterns.
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ( @preg_match( $pattern, $sku ) === 1 ) {
                return $regex;
            }
        }

        return null;
    }

    /**
     * Get all mappings.
     *
     * @param bool        $active_only Whether to return only active mappings.
     * @param string|null $order_by    Column to order by (default: priority).
     * @param string      $order       Order direction (ASC or DESC).
     * @return array
     */
    public function get_all( bool $active_only = true, ?string $order_by = 'priority', string $order = 'ASC' ): array {
        $sql = "SELECT * FROM {$this->table_name}";

        if ( $active_only ) {
            $sql .= ' WHERE is_active = 1';
        }

        // Validate and apply ordering.
        $valid_columns = array( 'id', 'legacy_pattern', 'canonical_code', 'priority', 'created_at', 'updated_at' );
        $order_by      = in_array( $order_by, $valid_columns, true ) ? $order_by : 'priority';
        $order         = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';
        $sql          .= " ORDER BY {$order_by} {$order}";

        return $this->wpdb->get_results( $sql, ARRAY_A ) ?: array();
    }

    /**
     * Get a single mapping by ID.
     *
     * @param int $id The mapping ID.
     * @return array|null The mapping data or null if not found.
     */
    public function get( int $id ): ?array {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return $result ?: null;
    }

    /**
     * Get mappings by canonical code.
     *
     * @param string $canonical_code The 4-letter canonical code.
     * @param bool   $active_only    Whether to return only active mappings.
     * @return array
     */
    public function get_by_canonical_code( string $canonical_code, bool $active_only = true ): array {
        $sql    = "SELECT * FROM {$this->table_name} WHERE canonical_code = %s";
        $params = array( strtoupper( $canonical_code ) );

        if ( $active_only ) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' ORDER BY priority ASC';

        return $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, ...$params ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Create a new mapping.
     *
     * @param array $data The mapping data. Required keys:
     *                    - legacy_pattern: The pattern to match.
     *                    - canonical_code: 4-letter design code.
     *                    Optional keys:
     *                    - match_type: exact|prefix|suffix|regex (default: exact).
     *                    - revision: Single letter or null.
     *                    - description: Human-readable description.
     *                    - priority: Lower = higher precedence (default: 100).
     *                    - is_active: 1 or 0 (default: 1).
     * @return int|WP_Error The mapping ID on success, WP_Error on failure.
     */
    public function create( array $data ): int|WP_Error {
        // Validate required fields.
        $validation = $this->validate_mapping_data( $data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Prepare data with defaults.
        $insert_data = array(
            'legacy_pattern' => $data['legacy_pattern'],
            'match_type'     => $data['match_type'] ?? 'exact',
            'canonical_code' => strtoupper( $data['canonical_code'] ),
            'revision'       => isset( $data['revision'] ) && ! empty( $data['revision'] )
                ? strtolower( substr( $data['revision'], 0, 1 ) )
                : null,
            'description'    => $data['description'] ?? null,
            'priority'       => $data['priority'] ?? 100,
            'is_active'      => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
            'created_by'     => get_current_user_id(),
        );

        $format = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' );

        // Check for duplicate pattern + match_type combination.
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_name}
                WHERE legacy_pattern = %s AND match_type = %s",
                $insert_data['legacy_pattern'],
                $insert_data['match_type']
            )
        );

        if ( $existing ) {
            return new WP_Error(
                'duplicate_pattern',
                sprintf(
                    /* translators: 1: pattern, 2: match type */
                    __( 'A mapping with pattern "%1$s" and match type "%2$s" already exists.', 'qsa-engraving' ),
                    $insert_data['legacy_pattern'],
                    $insert_data['match_type']
                )
            );
        }

        $result = $this->wpdb->insert( $this->table_name, $insert_data, $format );

        if ( false === $result ) {
            return new WP_Error(
                'insert_failed',
                sprintf(
                    /* translators: %s: Database error message */
                    __( 'Failed to create SKU mapping: %s', 'qsa-engraving' ),
                    $this->wpdb->last_error
                )
            );
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update an existing mapping.
     *
     * @param int   $id   The mapping ID.
     * @param array $data The fields to update.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function update( int $id, array $data ): bool|WP_Error {
        // Check if mapping exists.
        $existing = $this->get( $id );
        if ( ! $existing ) {
            return new WP_Error(
                'not_found',
                __( 'SKU mapping not found.', 'qsa-engraving' )
            );
        }

        // Merge with existing data for validation.
        $merged = array_merge( $existing, $data );

        // Validate if updating pattern-related fields.
        if ( isset( $data['legacy_pattern'] ) || isset( $data['match_type'] ) || isset( $data['canonical_code'] ) ) {
            $validation = $this->validate_mapping_data( $merged );
            if ( is_wp_error( $validation ) ) {
                return $validation;
            }
        }

        // Prepare update data.
        $update_data   = array();
        $update_format = array();

        if ( isset( $data['legacy_pattern'] ) ) {
            if ( strlen( $data['legacy_pattern'] ) > self::MAX_PATTERN_LENGTH ) {
                return new WP_Error(
                    'pattern_too_long',
                    sprintf(
                        /* translators: %d: maximum pattern length */
                        __( 'Legacy pattern exceeds maximum length of %d characters.', 'qsa-engraving' ),
                        self::MAX_PATTERN_LENGTH
                    )
                );
            }
            $update_data['legacy_pattern'] = $data['legacy_pattern'];
            $update_format[]               = '%s';
        }

        if ( isset( $data['match_type'] ) ) {
            if ( ! in_array( $data['match_type'], self::MATCH_TYPES, true ) ) {
                return new WP_Error(
                    'invalid_match_type',
                    sprintf(
                        /* translators: %s: comma-separated list of valid match types */
                        __( 'Invalid match type. Valid types are: %s', 'qsa-engraving' ),
                        implode( ', ', self::MATCH_TYPES )
                    )
                );
            }
            $update_data['match_type'] = $data['match_type'];
            $update_format[]           = '%s';
        }

        if ( isset( $data['canonical_code'] ) ) {
            $update_data['canonical_code'] = strtoupper( $data['canonical_code'] );
            $update_format[]               = '%s';
        }

        if ( array_key_exists( 'revision', $data ) ) {
            if ( ! empty( $data['revision'] ) ) {
                if ( ! preg_match( '/^[a-z]$/i', $data['revision'] ) ) {
                    return new WP_Error(
                        'invalid_revision',
                        __( 'Revision must be a single letter (a-z).', 'qsa-engraving' )
                    );
                }
                $update_data['revision'] = strtolower( substr( $data['revision'], 0, 1 ) );
            } else {
                $update_data['revision'] = null;
            }
            $update_format[] = '%s';
        }

        if ( array_key_exists( 'description', $data ) ) {
            $update_data['description'] = $data['description'];
            $update_format[]            = '%s';
        }

        if ( isset( $data['priority'] ) ) {
            $priority = (int) $data['priority'];
            if ( $priority < 0 || $priority > self::MAX_PRIORITY ) {
                return new WP_Error(
                    'invalid_priority',
                    sprintf(
                        /* translators: %d: maximum priority value */
                        __( 'Priority must be between 0 and %d.', 'qsa-engraving' ),
                        self::MAX_PRIORITY
                    )
                );
            }
            $update_data['priority'] = $priority;
            $update_format[]         = '%d';
        }

        if ( isset( $data['is_active'] ) ) {
            $is_active = (int) $data['is_active'];
            if ( $is_active !== 0 && $is_active !== 1 ) {
                return new WP_Error(
                    'invalid_is_active',
                    __( 'is_active must be 0 or 1.', 'qsa-engraving' )
                );
            }
            $update_data['is_active'] = $is_active;
            $update_format[]          = '%d';
        }

        if ( empty( $update_data ) ) {
            return true; // Nothing to update.
        }

        // Check for duplicate pattern + match_type if updating those fields.
        if ( isset( $update_data['legacy_pattern'] ) || isset( $update_data['match_type'] ) ) {
            $check_pattern = $update_data['legacy_pattern'] ?? $existing['legacy_pattern'];
            $check_type    = $update_data['match_type'] ?? $existing['match_type'];

            $duplicate = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->table_name}
                    WHERE legacy_pattern = %s AND match_type = %s AND id != %d",
                    $check_pattern,
                    $check_type,
                    $id
                )
            );

            if ( $duplicate ) {
                return new WP_Error(
                    'duplicate_pattern',
                    sprintf(
                        /* translators: 1: pattern, 2: match type */
                        __( 'A mapping with pattern "%1$s" and match type "%2$s" already exists.', 'qsa-engraving' ),
                        $check_pattern,
                        $check_type
                    )
                );
            }
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array( 'id' => $id ),
            $update_format,
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'update_failed',
                sprintf(
                    /* translators: %s: Database error message */
                    __( 'Failed to update SKU mapping: %s', 'qsa-engraving' ),
                    $this->wpdb->last_error
                )
            );
        }

        return true;
    }

    /**
     * Delete a mapping.
     *
     * @param int $id The mapping ID.
     * @return bool True on success, false on failure.
     */
    public function delete( int $id ): bool {
        $result = $this->wpdb->delete(
            $this->table_name,
            array( 'id' => $id ),
            array( '%d' )
        );

        return false !== $result && $result > 0;
    }

    /**
     * Toggle the active status of a mapping.
     *
     * @param int $id The mapping ID.
     * @return bool|WP_Error The new active status on success, WP_Error on failure.
     */
    public function toggle_active( int $id ): bool|WP_Error {
        $existing = $this->get( $id );
        if ( ! $existing ) {
            return new WP_Error(
                'not_found',
                __( 'SKU mapping not found.', 'qsa-engraving' )
            );
        }

        $new_status = $existing['is_active'] ? 0 : 1;

        $result = $this->wpdb->update(
            $this->table_name,
            array( 'is_active' => $new_status ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'update_failed',
                __( 'Failed to toggle mapping status.', 'qsa-engraving' )
            );
        }

        return (bool) $new_status;
    }

    /**
     * Test if a pattern matches a test SKU.
     *
     * Useful for the admin UI to verify patterns before saving.
     * Uses case-insensitive matching to mirror database collation behavior.
     *
     * @param string $pattern    The pattern to test.
     * @param string $match_type The match type (exact|prefix|suffix|regex).
     * @param string $test_sku   The SKU to test against.
     * @return bool True if the pattern matches the SKU.
     */
    public function test_pattern( string $pattern, string $match_type, string $test_sku ): bool {
        if ( empty( $pattern ) || empty( $test_sku ) ) {
            return false;
        }

        switch ( $match_type ) {
            case 'exact':
                // Case-insensitive to match database collation (utf8mb4_unicode_ci).
                return strcasecmp( $pattern, $test_sku ) === 0;

            case 'prefix':
                // Case-insensitive prefix match.
                return stripos( $test_sku, $pattern ) === 0;

            case 'suffix':
                // Case-insensitive suffix match.
                $pattern_len = strlen( $pattern );
                $sku_len     = strlen( $test_sku );
                if ( $pattern_len > $sku_len ) {
                    return false;
                }
                return strcasecmp( substr( $test_sku, -$pattern_len ), $pattern ) === 0;

            case 'regex':
                // Ensure pattern has delimiters.
                if ( ! str_starts_with( $pattern, '/' ) && ! str_starts_with( $pattern, '#' ) ) {
                    // Add case-insensitive flag by default to match database behavior.
                    $pattern = '/' . $pattern . '/i';
                }
                // Suppress warnings from invalid regex patterns.
                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                return @preg_match( $pattern, $test_sku ) === 1;

            default:
                return false;
        }
    }

    /**
     * Get the count of mappings.
     *
     * @param bool $active_only Whether to count only active mappings.
     * @return int
     */
    public function count( bool $active_only = false ): int {
        $sql = "SELECT COUNT(*) FROM {$this->table_name}";

        if ( $active_only ) {
            $sql .= ' WHERE is_active = 1';
        }

        return (int) $this->wpdb->get_var( $sql );
    }

    /**
     * Search mappings by pattern or description.
     *
     * @param string $search      The search term.
     * @param bool   $active_only Whether to search only active mappings.
     * @return array
     */
    public function search( string $search, bool $active_only = true ): array {
        $like = '%' . $this->wpdb->esc_like( $search ) . '%';

        $sql = "SELECT * FROM {$this->table_name}
                WHERE (legacy_pattern LIKE %s OR canonical_code LIKE %s OR description LIKE %s)";

        $params = array( $like, $like, $like );

        if ( $active_only ) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' ORDER BY priority ASC';

        return $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, ...$params ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Validate mapping data.
     *
     * @param array $data The mapping data to validate.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    private function validate_mapping_data( array $data ): true|WP_Error {
        // Check required fields.
        if ( empty( $data['legacy_pattern'] ) ) {
            return new WP_Error(
                'missing_pattern',
                __( 'Legacy pattern is required.', 'qsa-engraving' )
            );
        }

        // Validate legacy_pattern length to prevent silent truncation.
        if ( strlen( $data['legacy_pattern'] ) > self::MAX_PATTERN_LENGTH ) {
            return new WP_Error(
                'pattern_too_long',
                sprintf(
                    /* translators: %d: maximum pattern length */
                    __( 'Legacy pattern exceeds maximum length of %d characters.', 'qsa-engraving' ),
                    self::MAX_PATTERN_LENGTH
                )
            );
        }

        if ( empty( $data['canonical_code'] ) ) {
            return new WP_Error(
                'missing_canonical_code',
                __( 'Canonical code is required.', 'qsa-engraving' )
            );
        }

        // Validate canonical code format (4 uppercase letters).
        $canonical = strtoupper( $data['canonical_code'] );
        if ( ! preg_match( '/^[A-Z0-9]{4}$/', $canonical ) ) {
            return new WP_Error(
                'invalid_canonical_code',
                __( 'Canonical code must be exactly 4 uppercase letters or digits.', 'qsa-engraving' )
            );
        }

        // Validate match type if provided.
        $match_type = $data['match_type'] ?? 'exact';
        if ( ! in_array( $match_type, self::MATCH_TYPES, true ) ) {
            return new WP_Error(
                'invalid_match_type',
                sprintf(
                    /* translators: %s: comma-separated list of valid match types */
                    __( 'Invalid match type. Valid types are: %s', 'qsa-engraving' ),
                    implode( ', ', self::MATCH_TYPES )
                )
            );
        }

        // Validate regex pattern if match type is regex.
        if ( 'regex' === $match_type ) {
            $pattern = $data['legacy_pattern'];
            // Ensure pattern has delimiters.
            if ( ! str_starts_with( $pattern, '/' ) && ! str_starts_with( $pattern, '#' ) ) {
                $pattern = '/' . $pattern . '/';
            }
            // Test if the regex is valid.
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ( @preg_match( $pattern, '' ) === false ) {
                return new WP_Error(
                    'invalid_regex',
                    __( 'Invalid regular expression pattern.', 'qsa-engraving' )
                );
            }
        }

        // Validate revision if provided.
        if ( isset( $data['revision'] ) && ! empty( $data['revision'] ) ) {
            if ( ! preg_match( '/^[a-z]$/i', $data['revision'] ) ) {
                return new WP_Error(
                    'invalid_revision',
                    __( 'Revision must be a single letter (a-z).', 'qsa-engraving' )
                );
            }
        }

        // Validate priority if provided.
        if ( isset( $data['priority'] ) ) {
            $priority = (int) $data['priority'];
            if ( $priority < 0 || $priority > 65535 ) {
                return new WP_Error(
                    'invalid_priority',
                    __( 'Priority must be between 0 and 65535.', 'qsa-engraving' )
                );
            }
        }

        return true;
    }
}
