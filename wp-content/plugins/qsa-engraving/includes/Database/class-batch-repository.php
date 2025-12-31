<?php
/**
 * Batch Repository.
 *
 * Handles CRUD operations for the quad_engraving_batches and
 * quad_engraved_modules tables.
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
 * Repository class for engraving batch operations.
 *
 * @since 1.0.0
 */
class Batch_Repository {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Batches table name with prefix.
     *
     * @var string
     */
    private string $batches_table;

    /**
     * Engraved modules table name with prefix.
     *
     * @var string
     */
    private string $modules_table;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb          = $wpdb;
        $this->batches_table = $wpdb->prefix . 'quad_engraving_batches';
        $this->modules_table = $wpdb->prefix . 'quad_engraved_modules';
    }

    /**
     * Get the batches table name.
     *
     * @return string
     */
    public function get_batches_table_name(): string {
        return $this->batches_table;
    }

    /**
     * Get the modules table name.
     *
     * @return string
     */
    public function get_modules_table_name(): string {
        return $this->modules_table;
    }

    /**
     * Check if the batches table exists.
     *
     * @return bool
     */
    public function batches_table_exists(): bool {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->batches_table
            )
        );
        return $result === $this->batches_table;
    }

    /**
     * Check if the modules table exists.
     *
     * @return bool
     */
    public function modules_table_exists(): bool {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->modules_table
            )
        );
        return $result === $this->modules_table;
    }

    /**
     * Create a new engraving batch.
     *
     * @param string|null $batch_name Optional batch name.
     * @return int|WP_Error The batch ID or WP_Error on failure.
     */
    public function create_batch( ?string $batch_name = null ): int|WP_Error {
        $result = $this->wpdb->insert(
            $this->batches_table,
            array(
                'batch_name'   => $batch_name,
                'module_count' => 0,
                'qsa_count'    => 0,
                'status'       => 'in_progress',
                'created_by'   => get_current_user_id(),
            ),
            array( '%s', '%d', '%d', '%s', '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'insert_failed',
                sprintf(
                    /* translators: %s: Database error message */
                    __( 'Failed to create engraving batch: %s', 'qsa-engraving' ),
                    $this->wpdb->last_error
                )
            );
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Get a batch by ID.
     *
     * @param int $batch_id The batch ID.
     * @return array|null
     */
    public function get_batch( int $batch_id ): ?array {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->batches_table} WHERE id = %d",
                $batch_id
            ),
            ARRAY_A
        );

        return $result ?: null;
    }

    /**
     * Get batches with optional filtering.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_batches( array $args = array() ): array {
        $defaults = array(
            'status'   => null,
            'limit'    => 20,
            'offset'   => 0,
            'order_by' => 'created_at',
            'order'    => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $sql    = "SELECT * FROM {$this->batches_table}";
        $params = array();

        // Status filter.
        if ( null !== $args['status'] ) {
            $sql     .= ' WHERE status = %s';
            $params[] = $args['status'];
        }

        // Ordering.
        $order_by = in_array( $args['order_by'], array( 'created_at', 'completed_at', 'id' ), true )
            ? $args['order_by']
            : 'created_at';
        $order    = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $sql     .= " ORDER BY {$order_by} {$order}";

        // Pagination.
        $sql     .= ' LIMIT %d OFFSET %d';
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        if ( ! empty( $params ) ) {
            $sql = $this->wpdb->prepare( $sql, ...$params );
        }

        return $this->wpdb->get_results( $sql, ARRAY_A ) ?: array();
    }

    /**
     * Update batch counts.
     *
     * @param int $batch_id The batch ID.
     * @param int $module_count The total module count.
     * @param int $qsa_count The QSA count.
     * @return bool
     */
    public function update_batch_counts( int $batch_id, int $module_count, int $qsa_count ): bool {
        $result = $this->wpdb->update(
            $this->batches_table,
            array(
                'module_count' => $module_count,
                'qsa_count'    => $qsa_count,
            ),
            array( 'id' => $batch_id ),
            array( '%d', '%d' ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Complete a batch.
     *
     * @param int $batch_id The batch ID.
     * @return bool
     */
    public function complete_batch( int $batch_id ): bool {
        $result = $this->wpdb->update(
            $this->batches_table,
            array(
                'status'       => 'completed',
                'completed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $batch_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Add a module to an engraving batch.
     *
     * @param array $module_data The module data.
     * @return int|WP_Error The module record ID or WP_Error on failure.
     */
    public function add_module( array $module_data ): int|WP_Error {
        $result = $this->wpdb->insert(
            $this->modules_table,
            array(
                'engraving_batch_id'  => $module_data['engraving_batch_id'],
                'production_batch_id' => $module_data['production_batch_id'],
                'module_sku'          => $module_data['module_sku'],
                'order_id'            => $module_data['order_id'],
                'serial_number'       => $module_data['serial_number'],
                'qsa_sequence'        => $module_data['qsa_sequence'],
                'array_position'      => $module_data['array_position'],
                'row_status'          => 'pending',
            ),
            array( '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'insert_failed',
                sprintf(
                    /* translators: %s: Database error message */
                    __( 'Failed to add module to batch: %s', 'qsa-engraving' ),
                    $this->wpdb->last_error
                )
            );
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Get modules for a batch.
     *
     * @param int         $batch_id The batch ID.
     * @param string|null $row_status Optional status filter.
     * @return array
     */
    public function get_modules_for_batch( int $batch_id, ?string $row_status = null ): array {
        $sql    = "SELECT * FROM {$this->modules_table} WHERE engraving_batch_id = %d";
        $params = array( $batch_id );

        if ( null !== $row_status ) {
            $sql     .= ' AND row_status = %s';
            $params[] = $row_status;
        }

        $sql .= ' ORDER BY qsa_sequence ASC, array_position ASC';

        return $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, ...$params ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Mark modules as done for a QSA.
     *
     * @param int $batch_id The batch ID.
     * @param int $qsa_sequence The QSA sequence number.
     * @return int|WP_Error Number of updated rows or WP_Error on failure.
     */
    public function mark_qsa_done( int $batch_id, int $qsa_sequence ): int|WP_Error {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->modules_table}
                SET row_status = 'done', engraved_at = NOW()
                WHERE engraving_batch_id = %d
                  AND qsa_sequence = %d
                  AND row_status = 'pending'",
                $batch_id,
                $qsa_sequence
            )
        );

        if ( false === $result ) {
            return new WP_Error(
                'update_failed',
                __( 'Failed to mark QSA modules as done.', 'qsa-engraving' )
            );
        }

        return (int) $result;
    }

    /**
     * Get the count of modules already engraved for a production batch.
     *
     * @param int    $production_batch_id The production batch ID.
     * @param string $module_sku The module SKU.
     * @param int    $order_id The order ID.
     * @return int
     */
    public function get_engraved_count( int $production_batch_id, string $module_sku, int $order_id ): int {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->modules_table}
                WHERE production_batch_id = %d
                  AND module_sku = %s
                  AND order_id = %d
                  AND row_status = 'done'",
                $production_batch_id,
                $module_sku,
                $order_id
            )
        );
    }

    /**
     * Check if batch is complete (all modules done).
     *
     * @param int $batch_id The batch ID.
     * @return bool
     */
    public function is_batch_complete( int $batch_id ): bool {
        $pending_count = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->modules_table}
                WHERE engraving_batch_id = %d AND row_status = 'pending'",
                $batch_id
            )
        );

        return 0 === $pending_count;
    }
}
