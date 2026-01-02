<?php
/**
 * History AJAX Handler.
 *
 * Handles AJAX requests for batch history operations.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Ajax;

use Quadica\QSA_Engraving\Database\Batch_Repository;
use Quadica\QSA_Engraving\Database\Serial_Repository;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles AJAX requests for batch history operations.
 *
 * @since 1.0.0
 */
class History_Ajax_Handler {

    /**
     * Batch repository instance.
     *
     * @var Batch_Repository
     */
    private Batch_Repository $batch_repo;

    /**
     * Serial repository instance.
     *
     * @var Serial_Repository
     */
    private Serial_Repository $serial_repo;

    /**
     * Constructor.
     *
     * @param Batch_Repository  $batch_repo  Batch repository instance.
     * @param Serial_Repository $serial_repo Serial repository instance.
     */
    public function __construct( Batch_Repository $batch_repo, Serial_Repository $serial_repo ) {
        $this->batch_repo  = $batch_repo;
        $this->serial_repo = $serial_repo;
    }

    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'wp_ajax_qsa_get_batch_history', array( $this, 'handle_get_batch_history' ) );
        add_action( 'wp_ajax_qsa_get_batch_details', array( $this, 'handle_get_batch_details' ) );
        add_action( 'wp_ajax_qsa_get_batch_for_reengraving', array( $this, 'handle_get_batch_for_reengraving' ) );
    }

    /**
     * Verify the AJAX nonce.
     *
     * @return bool
     */
    private function verify_nonce(): bool {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        // wp_verify_nonce returns 1, 2 (valid) or false (invalid), cast to bool for strict typing.
        return (bool) wp_verify_nonce( $nonce, 'qsa_engraving_nonce' );
    }

    /**
     * Check if user has access.
     *
     * @return bool
     */
    private function user_has_access(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Send JSON error response.
     *
     * @param string $message Error message.
     * @param string $code    Error code.
     * @return void
     */
    private function send_error( string $message, string $code = 'error' ): void {
        wp_send_json_error(
            array(
                'message' => $message,
                'code'    => $code,
            )
        );
    }

    /**
     * Handle get batch history request.
     *
     * Returns paginated list of completed batches with search/filter support.
     *
     * @return void
     */
    public function handle_get_batch_history(): void {
        if ( ! $this->verify_nonce() || ! $this->user_has_access() ) {
            $this->send_error( __( 'Permission denied.', 'qsa-engraving' ), 'permission_denied' );
            return;
        }

        // Parse parameters.
        $page        = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
        $per_page    = isset( $_POST['per_page'] ) ? min( 50, max( 5, intval( $_POST['per_page'] ) ) ) : 20;
        $search      = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $filter_type = isset( $_POST['filter_type'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_type'] ) ) : 'all';
        $status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'completed';

        $offset = ( $page - 1 ) * $per_page;

        // Get batches with enhanced filtering.
        $batches = $this->get_batch_history(
            array(
                'status'      => $status,
                'search'      => $search,
                'filter_type' => $filter_type,
                'limit'       => $per_page,
                'offset'      => $offset,
            )
        );

        // Get total count for pagination.
        $total = $this->get_batch_history_count(
            array(
                'status'      => $status,
                'search'      => $search,
                'filter_type' => $filter_type,
            )
        );

        wp_send_json_success(
            array(
                'batches'    => $batches,
                'pagination' => array(
                    'page'       => $page,
                    'per_page'   => $per_page,
                    'total'      => $total,
                    'total_pages' => (int) ceil( $total / $per_page ),
                ),
            )
        );
    }

    /**
     * Get batch history with enhanced filtering.
     *
     * @param array $args Query arguments.
     * @return array
     */
    private function get_batch_history( array $args ): array {
        global $wpdb;

        $batches_table = $this->batch_repo->get_batches_table_name();
        $modules_table = $this->batch_repo->get_modules_table_name();

        $where_clauses = array( '1=1' );
        $params        = array();

        // Status filter.
        if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
            $where_clauses[] = 'b.status = %s';
            $params[]        = $args['status'];
        }

        // Search filter.
        if ( ! empty( $args['search'] ) ) {
            $search_term     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where_clauses[] = '(
                b.id LIKE %s
                OR b.batch_name LIKE %s
                OR EXISTS (
                    SELECT 1 FROM ' . $modules_table . ' m2
                    WHERE m2.engraving_batch_id = b.id
                    AND (m2.module_sku LIKE %s OR m2.order_id LIKE %s)
                )
            )';
            $params[]        = $search_term;
            $params[]        = $search_term;
            $params[]        = $search_term;
            $params[]        = $search_term;
        }

        // Module type filter.
        if ( ! empty( $args['filter_type'] ) && 'all' !== $args['filter_type'] ) {
            $type_prefix     = $wpdb->esc_like( $args['filter_type'] ) . '%';
            $where_clauses[] = 'EXISTS (
                SELECT 1 FROM ' . $modules_table . ' m3
                WHERE m3.engraving_batch_id = b.id
                AND m3.module_sku LIKE %s
            )';
            $params[]        = $type_prefix;
        }

        $where_sql = implode( ' AND ', $where_clauses );

        // Build the query.
        $sql = "SELECT
                b.id,
                b.batch_name,
                b.module_count,
                b.qsa_count,
                b.status,
                b.created_at,
                b.completed_at,
                b.created_by
            FROM {$batches_table} b
            WHERE {$where_sql}
            ORDER BY b.created_at DESC
            LIMIT %d OFFSET %d";

        $params[] = $args['limit'];
        $params[] = $args['offset'];

        // Prepare and execute.
        $prepared = $wpdb->prepare( $sql, ...$params );
        $results  = $wpdb->get_results( $prepared, ARRAY_A );

        if ( empty( $results ) ) {
            return array();
        }

        // Enhance each batch with additional data.
        $batches = array();
        foreach ( $results as $batch ) {
            $batches[] = $this->enhance_batch_data( $batch );
        }

        return $batches;
    }

    /**
     * Get total batch history count for pagination.
     *
     * @param array $args Query arguments.
     * @return int
     */
    private function get_batch_history_count( array $args ): int {
        global $wpdb;

        $batches_table = $this->batch_repo->get_batches_table_name();
        $modules_table = $this->batch_repo->get_modules_table_name();

        $where_clauses = array( '1=1' );
        $params        = array();

        // Status filter.
        if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
            $where_clauses[] = 'b.status = %s';
            $params[]        = $args['status'];
        }

        // Search filter.
        if ( ! empty( $args['search'] ) ) {
            $search_term     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where_clauses[] = '(
                b.id LIKE %s
                OR b.batch_name LIKE %s
                OR EXISTS (
                    SELECT 1 FROM ' . $modules_table . ' m2
                    WHERE m2.engraving_batch_id = b.id
                    AND (m2.module_sku LIKE %s OR m2.order_id LIKE %s)
                )
            )';
            $params[]        = $search_term;
            $params[]        = $search_term;
            $params[]        = $search_term;
            $params[]        = $search_term;
        }

        // Module type filter.
        if ( ! empty( $args['filter_type'] ) && 'all' !== $args['filter_type'] ) {
            $type_prefix     = $wpdb->esc_like( $args['filter_type'] ) . '%';
            $where_clauses[] = 'EXISTS (
                SELECT 1 FROM ' . $modules_table . ' m3
                WHERE m3.engraving_batch_id = b.id
                AND m3.module_sku LIKE %s
            )';
            $params[]        = $type_prefix;
        }

        $where_sql = implode( ' AND ', $where_clauses );

        $sql = "SELECT COUNT(*) FROM {$batches_table} b WHERE {$where_sql}";

        if ( ! empty( $params ) ) {
            $prepared = $wpdb->prepare( $sql, ...$params );
            return (int) $wpdb->get_var( $prepared );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Enhance batch data with module types, order IDs, and serial ranges.
     *
     * @param array $batch Raw batch data.
     * @return array Enhanced batch data.
     */
    private function enhance_batch_data( array $batch ): array {
        global $wpdb;

        $modules_table = $this->batch_repo->get_modules_table_name();
        $batch_id      = (int) $batch['id'];

        // Get distinct module types (base types like CORE, SOLO, EDGE, STAR).
        $module_types = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT SUBSTRING_INDEX(module_sku, '-', 1) as base_type
                FROM {$modules_table}
                WHERE engraving_batch_id = %d",
                $batch_id
            )
        );

        // Get distinct order IDs.
        $order_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT order_id FROM {$modules_table}
                WHERE engraving_batch_id = %d
                ORDER BY order_id",
                $batch_id
            )
        );

        // Get modules grouped by SKU with serial ranges.
        $modules = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    module_sku,
                    order_id,
                    COUNT(*) as qty,
                    MIN(serial_number) as serial_start,
                    MAX(serial_number) as serial_end
                FROM {$modules_table}
                WHERE engraving_batch_id = %d
                GROUP BY module_sku, order_id
                ORDER BY MIN(serial_number)",
                $batch_id
            ),
            ARRAY_A
        );

        // Get creator name.
        $created_by_name = '';
        if ( ! empty( $batch['created_by'] ) ) {
            $user = get_user_by( 'id', $batch['created_by'] );
            if ( $user ) {
                $created_by_name = $user->display_name;
            }
        }

        return array(
            'id'              => (int) $batch['id'],
            'batch_name'      => $batch['batch_name'],
            'module_count'    => (int) $batch['module_count'],
            'qsa_count'       => (int) $batch['qsa_count'],
            'status'          => $batch['status'],
            'created_at'      => $batch['created_at'],
            'completed_at'    => $batch['completed_at'],
            'created_by'      => (int) $batch['created_by'],
            'created_by_name' => $created_by_name,
            'module_types'    => $module_types ?: array(),
            'order_ids'       => array_map( 'intval', $order_ids ?: array() ),
            'modules'         => $modules ?: array(),
        );
    }

    /**
     * Handle get batch details request.
     *
     * Returns full details for a specific batch.
     *
     * @return void
     */
    public function handle_get_batch_details(): void {
        if ( ! $this->verify_nonce() || ! $this->user_has_access() ) {
            $this->send_error( __( 'Permission denied.', 'qsa-engraving' ), 'permission_denied' );
            return;
        }

        $batch_id = isset( $_POST['batch_id'] ) ? intval( $_POST['batch_id'] ) : 0;

        if ( $batch_id < 1 ) {
            $this->send_error( __( 'Invalid batch ID.', 'qsa-engraving' ), 'invalid_batch_id' );
            return;
        }

        $batch = $this->batch_repo->get_batch( $batch_id );

        if ( ! $batch ) {
            $this->send_error( __( 'Batch not found.', 'qsa-engraving' ), 'batch_not_found' );
            return;
        }

        // Get all modules with full details.
        $modules = $this->batch_repo->get_modules_for_batch( $batch_id );

        // Enhance batch with additional data.
        $enhanced_batch = $this->enhance_batch_data( $batch );

        // Add individual module details.
        $enhanced_batch['module_details'] = array_map(
            function ( $module ) {
                return array(
                    'id'             => (int) $module['id'],
                    'module_sku'     => $module['module_sku'],
                    'order_id'       => (int) $module['order_id'],
                    'serial_number'  => $module['serial_number'],
                    'qsa_sequence'   => (int) $module['qsa_sequence'],
                    'array_position' => (int) $module['array_position'],
                    'row_status'     => $module['row_status'],
                    'engraved_at'    => $module['engraved_at'],
                );
            },
            $modules
        );

        wp_send_json_success( array( 'batch' => $enhanced_batch ) );
    }

    /**
     * Handle get batch for re-engraving request.
     *
     * Returns batch modules formatted for use in the Batch Creator.
     * Only completed batches can be loaded for re-engraving.
     *
     * @return void
     */
    public function handle_get_batch_for_reengraving(): void {
        if ( ! $this->verify_nonce() || ! $this->user_has_access() ) {
            $this->send_error( __( 'Permission denied.', 'qsa-engraving' ), 'permission_denied' );
            return;
        }

        $batch_id = isset( $_POST['batch_id'] ) ? intval( $_POST['batch_id'] ) : 0;

        if ( $batch_id < 1 ) {
            $this->send_error( __( 'Invalid batch ID.', 'qsa-engraving' ), 'invalid_batch_id' );
            return;
        }

        $batch = $this->batch_repo->get_batch( $batch_id );

        if ( ! $batch ) {
            $this->send_error( __( 'Batch not found.', 'qsa-engraving' ), 'batch_not_found' );
            return;
        }

        // Only completed batches can be loaded for re-engraving.
        if ( 'completed' !== $batch['status'] ) {
            $this->send_error(
                sprintf(
                    /* translators: %s: batch status */
                    __( 'Only completed batches can be loaded for re-engraving. This batch has status: %s', 'qsa-engraving' ),
                    $batch['status']
                ),
                'batch_not_completed'
            );
            return;
        }

        // Get modules grouped for re-engraving selection.
        $modules = $this->get_modules_for_reengraving( $batch_id );

        wp_send_json_success(
            array(
                'batch_id'    => $batch_id,
                'batch_name'  => $batch['batch_name'],
                'source_type' => 'history',
                'modules'     => $modules,
            )
        );
    }

    /**
     * Get modules formatted for re-engraving selection.
     *
     * Groups modules by base type, order, and SKU for the Batch Creator UI.
     * Only returns serial ranges (start-end) to avoid GROUP_CONCAT truncation
     * issues with large batches (MySQL group_concat_max_len limit).
     *
     * @param int $batch_id The batch ID.
     * @return array Modules grouped for selection.
     */
    private function get_modules_for_reengraving( int $batch_id ): array {
        global $wpdb;

        $modules_table = $this->batch_repo->get_modules_table_name();

        // Get modules grouped by SKU and order.
        // Note: We intentionally avoid GROUP_CONCAT for serials to prevent
        // truncation issues with large batches. Serial ranges are sufficient
        // for re-engraving since new serials will be assigned anyway.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    module_sku,
                    order_id,
                    production_batch_id,
                    COUNT(*) as qty,
                    MIN(serial_number) as serial_start,
                    MAX(serial_number) as serial_end
                FROM {$modules_table}
                WHERE engraving_batch_id = %d
                GROUP BY module_sku, order_id, production_batch_id
                ORDER BY module_sku, order_id",
                $batch_id
            ),
            ARRAY_A
        );

        if ( empty( $results ) ) {
            return array();
        }

        // Group by base type.
        // Use substr to extract first 4 characters, consistent with Module_Selector.
        // This ensures "STARa-34924" becomes "STAR" (not "STARa" as strtok would yield).
        $grouped = array();
        foreach ( $results as $row ) {
            $base_type = substr( $row['module_sku'], 0, 4 );

            if ( ! isset( $grouped[ $base_type ] ) ) {
                $grouped[ $base_type ] = array(
                    'base_type' => $base_type,
                    'orders'    => array(),
                );
            }

            $order_id = (int) $row['order_id'];
            if ( ! isset( $grouped[ $base_type ]['orders'][ $order_id ] ) ) {
                $grouped[ $base_type ]['orders'][ $order_id ] = array(
                    'order_id' => $order_id,
                    'modules'  => array(),
                );
            }

            $grouped[ $base_type ]['orders'][ $order_id ]['modules'][] = array(
                'sku'                 => $row['module_sku'],
                'production_batch_id' => (int) $row['production_batch_id'],
                'qty'                 => (int) $row['qty'],
                'serial_range'        => $row['serial_start'] . ' - ' . $row['serial_end'],
                'source_batch_id'     => $batch_id,
            );
        }

        // Convert to indexed arrays.
        $result = array();
        foreach ( $grouped as $type_data ) {
            $type_data['orders'] = array_values( $type_data['orders'] );
            $result[]            = $type_data;
        }

        return $result;
    }
}
