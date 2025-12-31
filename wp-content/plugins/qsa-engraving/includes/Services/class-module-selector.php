<?php
/**
 * Module Selector Service.
 *
 * Queries and filters modules that need engraving from the legacy OM system.
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
     * Regex pattern for QSA-compatible module SKUs.
     *
     * Pattern: 4 uppercase letters + optional lowercase revision + hyphen + 5 digits
     * Examples: CORE-91247, STARa-34924, SOLO-12345
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
     * Constructor.
     *
     * @param Batch_Repository $batch_repository The batch repository.
     */
    public function __construct( Batch_Repository $batch_repository ) {
        global $wpdb;
        $this->wpdb             = $wpdb;
        $this->batch_repository = $batch_repository;
    }

    /**
     * Get modules awaiting engraving.
     *
     * Queries the oms_batch_items table for modules that:
     * - Match the QSA SKU pattern
     * - Have build_qty > qty_received (need to be built)
     * - Haven't already been engraved
     *
     * @return array Array of modules grouped by base type.
     */
    public function get_modules_awaiting(): array {
        // Check if oms_batch_items table exists.
        $oms_table = $this->wpdb->prefix . 'oms_batch_items';
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $oms_table )
        );

        if ( $table_exists !== $oms_table ) {
            return array();
        }

        // Build the query.
        $sql = "SELECT
                    bi.batch_id AS production_batch_id,
                    bi.assembly_sku AS module_sku,
                    bi.order_no AS order_id,
                    bi.build_qty,
                    COALESCE(bi.qty_received, 0) AS qty_received,
                    (bi.build_qty - COALESCE(bi.qty_received, 0)) AS qty_needed,
                    COALESCE(em.qty_engraved, 0) AS qty_engraved,
                    (bi.build_qty - COALESCE(bi.qty_received, 0) - COALESCE(em.qty_engraved, 0)) AS qty_to_engrave
                FROM {$oms_table} bi
                LEFT JOIN (
                    SELECT
                        production_batch_id,
                        module_sku,
                        order_id,
                        COUNT(*) AS qty_engraved
                    FROM {$this->batch_repository->get_modules_table_name()}
                    WHERE row_status = 'done'
                    GROUP BY production_batch_id, module_sku, order_id
                ) em ON bi.batch_id = em.production_batch_id
                    AND bi.assembly_sku = em.module_sku
                    AND bi.order_no = em.order_id
                WHERE bi.assembly_sku REGEXP %s
                  AND (bi.build_qty - COALESCE(bi.qty_received, 0) - COALESCE(em.qty_engraved, 0)) > 0
                ORDER BY bi.batch_id, bi.assembly_sku";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, self::QSA_SKU_PATTERN ),
            ARRAY_A
        );

        if ( empty( $results ) ) {
            return array();
        }

        // Group by base type.
        return $this->group_by_base_type( $results );
    }

    /**
     * Get modules grouped by base type.
     *
     * @param array $modules Array of module records.
     * @return array Grouped modules.
     */
    private function group_by_base_type( array $modules ): array {
        $grouped = array();

        foreach ( $modules as $module ) {
            $base_type = $this->extract_base_type( $module['module_sku'] );

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

            $grouped[ $base_type ]['modules'][ $order_id ]['items'][] = array(
                'production_batch_id' => (int) $module['production_batch_id'],
                'module_sku'          => $module['module_sku'],
                'order_id'            => $order_id,
                'build_qty'           => (int) $module['build_qty'],
                'qty_received'        => (int) $module['qty_received'],
                'qty_needed'          => (int) $module['qty_needed'],
                'qty_engraved'        => (int) $module['qty_engraved'],
                'qty_to_engrave'      => $qty_to_engrave,
            );

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
     * Extract the base type from a module SKU.
     *
     * Examples:
     * - "CORE-91247" -> "CORE"
     * - "STARa-34924" -> "STAR"
     *
     * @param string $sku The module SKU.
     * @return string The base type (4 letters).
     */
    public function extract_base_type( string $sku ): string {
        return substr( $sku, 0, 4 );
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
     * @param int $order_id The WooCommerce order ID.
     * @return array Array of modules for the order.
     */
    public function get_modules_for_order( int $order_id ): array {
        $oms_table = $this->wpdb->prefix . 'oms_batch_items';

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
}
