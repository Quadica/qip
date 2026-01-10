<?php
/**
 * Module Selector Service.
 *
 * Queries and filters modules that need engraving from the legacy OM system.
 * Supports both native QSA SKU format and legacy SKUs via mapping resolution.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Services;

use Quadica\QSA_Engraving\Database\Batch_Repository;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Service for selecting modules that need engraving.
 *
 * @since 1.0.0
 */
class Module_Selector {

    /**
     * Legacy OMS batch items table name.
     *
     * Note: This table is from the legacy Order Management System and intentionally
     * does NOT use the WordPress table prefix. It will eventually be deprecated
     * but is required for the current engraving workflow integration.
     *
     * Important: The `order_no` column in this table contains WooCommerce order IDs.
     * Despite the different column name, these are the same ID space used by
     * WooCommerce orders, Order BOM CPT, and LED_Code_Resolver. Verified via
     * data inspection - order_no values match wp_posts.ID for shop_order posts.
     *
     * @var string
     */
    public const OMS_BATCH_ITEMS_TABLE = 'oms_batch_items';

    /**
     * Regex pattern for QSA-compatible module SKUs.
     *
     * Pattern: 4 uppercase letters + optional lowercase revision + hyphen + 5 digits
     * Examples: CORE-91247, STARa-34924, SOLO-12345
     *
     * Note: The discovery doc (qsa-engraving-discovery.md) uses the simpler pattern
     * "^[A-Z]{4}-" for identification purposes. This stricter pattern is used for
     * database queries to ensure we match valid QSA SKUs only. The optional lowercase
     * revision letter accommodates both original designs (e.g., "CORE-") and revised
     * designs (e.g., "STARa-").
     *
     * @var string
     */
    public const QSA_SKU_PATTERN = '^[A-Z]{4}[a-z]?-[0-9]{5}$';

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Batch repository instance.
     *
     * @var Batch_Repository
     */
    private Batch_Repository $batch_repository;

    /**
     * Legacy SKU Resolver instance.
     *
     * Resolves both QSA-format and legacy SKUs to canonical form.
     *
     * @var Legacy_SKU_Resolver|null
     */
    private ?Legacy_SKU_Resolver $legacy_resolver;

    /**
     * Constructor.
     *
     * @param Batch_Repository        $batch_repository The batch repository.
     * @param Legacy_SKU_Resolver|null $legacy_resolver  Optional legacy SKU resolver.
     */
    public function __construct( Batch_Repository $batch_repository, ?Legacy_SKU_Resolver $legacy_resolver = null ) {
        global $wpdb;
        $this->wpdb             = $wpdb;
        $this->batch_repository = $batch_repository;
        $this->legacy_resolver  = $legacy_resolver;
    }

    /**
     * Get modules awaiting engraving.
     *
     * Queries the oms_batch_items table for modules that:
     * - Match the QSA SKU pattern OR have a legacy SKU mapping (when resolver is available)
     * - Have build_qty > qty_received (need to be built)
     * - Haven't already been engraved
     *
     * When a Legacy_SKU_Resolver is injected, the query fetches all modules with
     * positive qty_to_engrave and filters in PHP using the resolver. This allows
     * both native QSA SKUs and mapped legacy SKUs to be included.
     *
     * When no resolver is available, falls back to REGEXP-based filtering for
     * backward compatibility (only native QSA SKUs).
     *
     * @return array Array of modules grouped by base type.
     */
    public function get_modules_awaiting(): array {
        // Check if legacy OMS table exists (no WordPress prefix - see class constant).
        if ( ! $this->oms_table_exists() ) {
            return array();
        }

        $oms_table = self::OMS_BATCH_ITEMS_TABLE;

        // Check if quad_engraved_modules table exists (used in LEFT JOIN).
        // If the table doesn't exist, return empty to avoid SQL errors.
        if ( ! $this->batch_repository->modules_table_exists() ) {
            return array();
        }

        // Build the query.
        // We need two LEFT JOINs:
        // 1. Count modules that are completed (row_status = 'done') - these reduce qty_to_engrave
        // 2. Count modules in ANY batch (any status) - these should be excluded entirely
        $modules_table = $this->batch_repository->get_modules_table_name();

        // When resolver is available, fetch all modules and filter in PHP.
        // This allows legacy SKU mappings to be included alongside native QSA SKUs.
        // When resolver is not available, use REGEXP for backward compatibility.
        $use_resolver = null !== $this->legacy_resolver;

        if ( $use_resolver ) {
            // No SKU filter in SQL - filter in PHP after resolution.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $sql = "SELECT
                        bi.batch_id AS production_batch_id,
                        bi.assembly_sku AS module_sku,
                        bi.order_no AS order_id,
                        bi.build_qty,
                        COALESCE(bi.qty_received, 0) AS qty_received,
                        (bi.build_qty - COALESCE(bi.qty_received, 0)) AS qty_needed,
                        COALESCE(em_done.qty_engraved, 0) AS qty_engraved,
                        COALESCE(em_all.qty_in_batch, 0) AS qty_in_batch,
                        (bi.build_qty - COALESCE(bi.qty_received, 0) - COALESCE(em_all.qty_in_batch, 0)) AS qty_to_engrave
                    FROM {$oms_table} bi
                    LEFT JOIN (
                        SELECT
                            production_batch_id,
                            module_sku,
                            order_id,
                            COUNT(*) AS qty_engraved
                        FROM {$modules_table}
                        WHERE row_status = 'done'
                        GROUP BY production_batch_id, module_sku, order_id
                    ) em_done ON bi.batch_id = em_done.production_batch_id
                        AND bi.assembly_sku = em_done.module_sku
                        AND bi.order_no = em_done.order_id
                    LEFT JOIN (
                        SELECT
                            production_batch_id,
                            module_sku,
                            order_id,
                            COUNT(*) AS qty_in_batch
                        FROM {$modules_table}
                        GROUP BY production_batch_id, module_sku, order_id
                    ) em_all ON bi.batch_id = em_all.production_batch_id
                        AND bi.assembly_sku = em_all.module_sku
                        AND bi.order_no = em_all.order_id
                    WHERE (bi.build_qty - COALESCE(bi.qty_received, 0) - COALESCE(em_all.qty_in_batch, 0)) > 0
                    ORDER BY bi.batch_id, bi.assembly_sku";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input, table names are constants/trusted.
            $results = $this->wpdb->get_results( $sql, ARRAY_A );
        } else {
            // Fallback: REGEXP filter for backward compatibility.
            $sql = "SELECT
                        bi.batch_id AS production_batch_id,
                        bi.assembly_sku AS module_sku,
                        bi.order_no AS order_id,
                        bi.build_qty,
                        COALESCE(bi.qty_received, 0) AS qty_received,
                        (bi.build_qty - COALESCE(bi.qty_received, 0)) AS qty_needed,
                        COALESCE(em_done.qty_engraved, 0) AS qty_engraved,
                        COALESCE(em_all.qty_in_batch, 0) AS qty_in_batch,
                        (bi.build_qty - COALESCE(bi.qty_received, 0) - COALESCE(em_all.qty_in_batch, 0)) AS qty_to_engrave
                    FROM {$oms_table} bi
                    LEFT JOIN (
                        SELECT
                            production_batch_id,
                            module_sku,
                            order_id,
                            COUNT(*) AS qty_engraved
                        FROM {$modules_table}
                        WHERE row_status = 'done'
                        GROUP BY production_batch_id, module_sku, order_id
                    ) em_done ON bi.batch_id = em_done.production_batch_id
                        AND bi.assembly_sku = em_done.module_sku
                        AND bi.order_no = em_done.order_id
                    LEFT JOIN (
                        SELECT
                            production_batch_id,
                            module_sku,
                            order_id,
                            COUNT(*) AS qty_in_batch
                        FROM {$modules_table}
                        GROUP BY production_batch_id, module_sku, order_id
                    ) em_all ON bi.batch_id = em_all.production_batch_id
                        AND bi.assembly_sku = em_all.module_sku
                        AND bi.order_no = em_all.order_id
                    WHERE bi.assembly_sku REGEXP %s
                      AND (bi.build_qty - COALESCE(bi.qty_received, 0) - COALESCE(em_all.qty_in_batch, 0)) > 0
                    ORDER BY bi.batch_id, bi.assembly_sku";

            $results = $this->wpdb->get_results(
                $this->wpdb->prepare( $sql, self::QSA_SKU_PATTERN ),
                ARRAY_A
            );
        }

        if ( empty( $results ) ) {
            return array();
        }

        // When resolver is available, filter and augment with resolution data.
        if ( $use_resolver ) {
            $results = $this->resolve_and_filter_modules( $results );

            if ( empty( $results ) ) {
                return array();
            }
        }

        // Group by base type.
        return $this->group_by_base_type( $results );
    }

    /**
     * Resolve and filter modules using the Legacy SKU Resolver.
     *
     * Filters out modules that cannot be resolved (unknown format, unmapped legacy).
     * Augments remaining modules with resolution data for downstream processing.
     *
     * @param array $modules Raw module records from database.
     * @return array Filtered and augmented module records.
     */
    private function resolve_and_filter_modules( array $modules ): array {
        $resolved = array();

        foreach ( $modules as $module ) {
            $resolution = $this->legacy_resolver->resolve( $module['module_sku'] );

            // Skip unknown formats (unmapped legacy or invalid SKUs).
            if ( null === $resolution ) {
                continue;
            }

            // Augment with resolution data for downstream processing.
            $module['original_sku']   = $resolution['original_sku'];
            $module['canonical_code'] = $resolution['canonical_code'];
            $module['canonical_sku']  = $resolution['canonical_sku'];
            $module['revision']       = $resolution['revision'];
            $module['is_legacy']      = $resolution['is_legacy'];
            $module['config_number']  = $resolution['config_number'];

            $resolved[] = $module;
        }

        return $resolved;
    }

    /**
     * Get modules grouped by base type.
     *
     * When modules have resolution data (canonical_code, revision), uses those
     * for grouping. Otherwise falls back to extracting base type from SKU string.
     *
     * @param array $modules Array of module records (may include resolution data).
     * @return array Grouped modules.
     */
    private function group_by_base_type( array $modules ): array {
        $grouped = array();

        foreach ( $modules as $module ) {
            // Use canonical data if available (from resolver), otherwise extract from SKU.
            $base_type = $this->get_module_base_type( $module );

            if ( ! isset( $grouped[ $base_type ] ) ) {
                $grouped[ $base_type ] = array(
                    'base_type'    => $base_type,
                    'total_qty'    => 0,
                    'order_count'  => 0,
                    'modules'      => array(),
                );
            }

            // Group by order within base type.
            $order_id = (int) $module['order_id'];
            if ( ! isset( $grouped[ $base_type ]['modules'][ $order_id ] ) ) {
                $grouped[ $base_type ]['modules'][ $order_id ] = array(
                    'order_id'   => $order_id,
                    'items'      => array(),
                    'total_qty'  => 0,
                );
                $grouped[ $base_type ]['order_count']++;
            }

            $qty_to_engrave = (int) $module['qty_to_engrave'];

            // Build item data, including resolution fields if present.
            $item = array(
                'production_batch_id' => (int) $module['production_batch_id'],
                'module_sku'          => $module['module_sku'],
                'order_id'            => $order_id,
                'build_qty'           => (int) $module['build_qty'],
                'qty_received'        => (int) $module['qty_received'],
                'qty_needed'          => (int) $module['qty_needed'],
                'qty_engraved'        => (int) $module['qty_engraved'],
                'qty_to_engrave'      => $qty_to_engrave,
            );

            // Include resolution data if available (for downstream processing).
            if ( isset( $module['canonical_code'] ) ) {
                $item['original_sku']   = $module['original_sku'];
                $item['canonical_code'] = $module['canonical_code'];
                $item['canonical_sku']  = $module['canonical_sku'];
                $item['revision']       = $module['revision'];
                $item['is_legacy']      = $module['is_legacy'];
                $item['config_number']  = $module['config_number'];
            }

            $grouped[ $base_type ]['modules'][ $order_id ]['items'][] = $item;

            $grouped[ $base_type ]['modules'][ $order_id ]['total_qty'] += $qty_to_engrave;
            $grouped[ $base_type ]['total_qty'] += $qty_to_engrave;
        }

        // Convert modules from associative to indexed array.
        foreach ( $grouped as $base_type => $data ) {
            $grouped[ $base_type ]['modules'] = array_values( $data['modules'] );
        }

        return $grouped;
    }

    /**
     * Get the base type for a module record.
     *
     * Uses canonical_code + revision if resolution data is present,
     * otherwise extracts from the SKU string.
     *
     * @param array $module Module record (may include resolution data).
     * @return string Base type string (e.g., "STAR", "STARa", "SP01").
     */
    private function get_module_base_type( array $module ): string {
        // Use canonical data if available (from resolver).
        if ( isset( $module['canonical_code'] ) ) {
            $base = $module['canonical_code'];
            if ( ! empty( $module['revision'] ) ) {
                $base .= $module['revision'];
            }
            return $base;
        }

        // Fallback: extract from SKU string.
        return $this->extract_base_type( $module['module_sku'] );
    }

    /**
     * Extract the base type from a module SKU.
     *
     * Includes the revision letter because different revisions have different
     * physical layouts and SVG configurations.
     *
     * Examples:
     * - "CORE-91247" -> "CORE"
     * - "STARa-34924" -> "STARa"
     *
     * @param string $sku The module SKU.
     * @return string The base type with revision (4-5 characters).
     */
    public function extract_base_type( string $sku ): string {
        // Get everything before the hyphen (design + optional revision).
        return strtok( $sku, '-' ) ?: substr( $sku, 0, 4 );
    }

    /**
     * Extract the revision from a module SKU.
     *
     * Examples:
     * - "CORE-91247" -> null (no revision)
     * - "STARa-34924" -> "a"
     *
     * @param string $sku The module SKU.
     * @return string|null The revision letter or null.
     */
    public function extract_revision( string $sku ): ?string {
        if ( strlen( $sku ) > 4 && ctype_lower( $sku[4] ) ) {
            return $sku[4];
        }
        return null;
    }

    /**
     * Extract the config code from a module SKU.
     *
     * Examples:
     * - "CORE-91247" -> "91247"
     * - "STARa-34924" -> "34924"
     *
     * @param string $sku The module SKU.
     * @return string The 5-digit config code.
     */
    public function extract_config_code( string $sku ): string {
        $parts = explode( '-', $sku );
        return $parts[1] ?? '';
    }

    /**
     * Validate that a SKU matches the QSA pattern.
     *
     * @param string $sku The SKU to validate.
     * @return bool
     */
    public static function is_qsa_compatible( string $sku ): bool {
        return (bool) preg_match( '/' . self::QSA_SKU_PATTERN . '/', $sku );
    }

    /**
     * Get modules for a specific order.
     *
     * When resolver is available, includes both QSA-format and mapped legacy SKUs.
     * Otherwise falls back to QSA-format only for backward compatibility.
     *
     * @param int $order_id The WooCommerce order ID.
     * @return array Array of modules for the order (with resolution data if resolver available).
     */
    public function get_modules_for_order( int $order_id ): array {
        // Check if legacy OMS table exists before querying.
        if ( ! $this->oms_table_exists() ) {
            return array();
        }

        // Use legacy OMS table (no WordPress prefix).
        $oms_table     = self::OMS_BATCH_ITEMS_TABLE;
        $use_resolver  = null !== $this->legacy_resolver;

        if ( $use_resolver ) {
            // Fetch all modules for order, filter in PHP.
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT
                        batch_id AS production_batch_id,
                        assembly_sku AS module_sku,
                        order_no AS order_id,
                        build_qty,
                        COALESCE(qty_received, 0) AS qty_received
                    FROM {$oms_table}
                    WHERE order_no = %d
                    ORDER BY assembly_sku",
                    $order_id
                ),
                ARRAY_A
            );

            if ( empty( $results ) ) {
                return array();
            }

            // Filter and augment with resolution data.
            return $this->resolve_and_filter_modules( $results );
        }

        // Fallback: REGEXP filter for backward compatibility.
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    batch_id AS production_batch_id,
                    assembly_sku AS module_sku,
                    order_no AS order_id,
                    build_qty,
                    COALESCE(qty_received, 0) AS qty_received
                FROM {$oms_table}
                WHERE order_no = %d
                  AND assembly_sku REGEXP %s
                ORDER BY assembly_sku",
                $order_id,
                self::QSA_SKU_PATTERN
            ),
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * Get module count by base type.
     *
     * @return array Array of counts keyed by base type.
     */
    public function get_counts_by_base_type(): array {
        $modules = $this->get_modules_awaiting();

        $counts = array();
        foreach ( $modules as $base_type => $data ) {
            $counts[ $base_type ] = $data['total_qty'];
        }

        return $counts;
    }

    /**
     * Get total modules awaiting engraving.
     *
     * @return int
     */
    public function get_total_awaiting(): int {
        $counts = $this->get_counts_by_base_type();
        return array_sum( $counts );
    }

    /**
     * Check if the legacy OMS batch items table exists.
     *
     * Uses esc_like() to properly escape underscores in the table name,
     * which are SQL wildcards in LIKE queries.
     *
     * @return bool True if table exists.
     */
    private function oms_table_exists(): bool {
        $oms_table = self::OMS_BATCH_ITEMS_TABLE;

        // Escape underscores for LIKE query (underscores are SQL wildcards).
        $escaped_table = $this->wpdb->esc_like( $oms_table );

        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $escaped_table )
        );

        return $table_exists === $oms_table;
    }
}
