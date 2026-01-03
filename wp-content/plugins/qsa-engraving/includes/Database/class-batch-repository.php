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
     * Updates modules with row_status of 'pending' or 'in_progress' to 'done'.
     * This handles the normal workflow where rows transition from pending -> in_progress -> done.
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
                  AND row_status IN ('pending', 'in_progress')",
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
                WHERE engraving_batch_id = %d AND row_status != 'done'",
                $batch_id
            )
        );

        return 0 === $pending_count;
    }

    /**
     * Update row status for all modules in a QSA.
     *
     * @param int    $batch_id     The batch ID.
     * @param int    $qsa_sequence The QSA sequence number.
     * @param string $status       The new status (pending, in_progress, done).
     * @return int|WP_Error Number of updated rows or WP_Error on failure.
     */
    public function update_row_status( int $batch_id, int $qsa_sequence, string $status ): int|WP_Error {
        $valid_statuses = array( 'pending', 'in_progress', 'done' );
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return new WP_Error(
                'invalid_status',
                sprintf( 'Invalid status: %s. Valid statuses are: %s', $status, implode( ', ', $valid_statuses ) )
            );
        }

        $update_data = array( 'row_status' => $status );
        $update_format = array( '%s' );

        if ( 'done' === $status ) {
            $update_data['engraved_at'] = current_time( 'mysql' );
            $update_format[] = '%s';
        }

        $result = $this->wpdb->update(
            $this->modules_table,
            $update_data,
            array(
                'engraving_batch_id' => $batch_id,
                'qsa_sequence'       => $qsa_sequence,
            ),
            $update_format,
            array( '%d', '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'update_failed',
                __( 'Failed to update row status.', 'qsa-engraving' )
            );
        }

        return (int) $result;
    }

    /**
     * Reset row status to pending for a QSA.
     *
     * @param int $batch_id     The batch ID.
     * @param int $qsa_sequence The QSA sequence number.
     * @return int|WP_Error Number of updated rows or WP_Error on failure.
     */
    public function reset_row_status( int $batch_id, int $qsa_sequence ): int|WP_Error {
        $result = $this->wpdb->update(
            $this->modules_table,
            array(
                'row_status'  => 'pending',
                'engraved_at' => null,
            ),
            array(
                'engraving_batch_id' => $batch_id,
                'qsa_sequence'       => $qsa_sequence,
            ),
            array( '%s', '%s' ),
            array( '%d', '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'update_failed',
                __( 'Failed to reset row status.', 'qsa-engraving' )
            );
        }

        return (int) $result;
    }

    /**
     * Reopen a completed batch.
     *
     * @param int $batch_id The batch ID.
     * @return bool
     */
    public function reopen_batch( int $batch_id ): bool {
        $result = $this->wpdb->update(
            $this->batches_table,
            array(
                'status'       => 'in_progress',
                'completed_at' => null,
            ),
            array( 'id' => $batch_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Update start position for all modules in a QSA.
     *
     * Stores the starting position (1-8). When the total modules exceed what fits
     * in a single array from the start position, the frontend dynamically calculates
     * how many physical QSA arrays are needed and displays "Arrays: X".
     *
     * The position calculation for multi-array support:
     * - First array: positions from start_position to min(8, start_position + modules - 1)
     * - Subsequent arrays: positions 1-8 (full arrays) or partial
     *
     * Note: Positions wrap around when exceeding 8. The `array_position` stores the
     * logical position (1-8) within the current physical array. The frontend calculates
     * which physical array each module belongs to based on start position and index.
     *
     * @param int $batch_id       The batch ID.
     * @param int $qsa_sequence   The QSA sequence number.
     * @param int $start_position The new start position (1-8).
     * @return int|WP_Error Number of updated rows or WP_Error on failure.
     */
    public function update_start_position( int $batch_id, int $qsa_sequence, int $start_position ): int|WP_Error {
        // Get all modules in this QSA ordered by ID to preserve insertion order.
        $modules = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->modules_table}
                WHERE engraving_batch_id = %d AND qsa_sequence = %d
                ORDER BY id ASC",
                $batch_id,
                $qsa_sequence
            ),
            ARRAY_A
        );

        if ( empty( $modules ) ) {
            return new WP_Error( 'no_modules', __( 'No modules found for this QSA.', 'qsa-engraving' ) );
        }

        // Update positions with array-aware assignment.
        // Positions wrap: first array uses start_position to 8, then 1-8 for subsequent arrays.
        $updated = 0;
        $current_position = $start_position;

        foreach ( $modules as $module ) {
            $result = $this->wpdb->update(
                $this->modules_table,
                array( 'array_position' => $current_position ),
                array( 'id' => $module['id'] ),
                array( '%d' ),
                array( '%d' )
            );

            if ( false !== $result ) {
                $updated++;
            }

            // Advance position, wrapping from 8 back to 1.
            $current_position++;
            if ( $current_position > 8 ) {
                $current_position = 1;
            }
        }

        return $updated;
    }

    /**
     * Get queue statistics for a batch.
     *
     * @param int $batch_id The batch ID.
     * @return array Statistics including counts by status.
     */
    public function get_queue_stats( int $batch_id ): array {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT row_status, COUNT(*) as count
                FROM {$this->modules_table}
                WHERE engraving_batch_id = %d
                GROUP BY row_status",
                $batch_id
            ),
            ARRAY_A
        );

        $stats = array(
            'pending'     => 0,
            'in_progress' => 0,
            'done'        => 0,
            'total'       => 0,
        );

        foreach ( $results as $row ) {
            if ( isset( $stats[ $row['row_status'] ] ) ) {
                $stats[ $row['row_status'] ] = (int) $row['count'];
            }
            $stats['total'] += (int) $row['count'];
        }

        // Calculate QSA counts.
        $qsa_counts = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT qsa_sequence) as total_qsas,
                    COUNT(DISTINCT CASE WHEN row_status = 'done' THEN qsa_sequence END) as done_qsas
                FROM {$this->modules_table}
                WHERE engraving_batch_id = %d",
                $batch_id
            ),
            ARRAY_A
        );

        $stats['total_qsas'] = (int) ( $qsa_counts['total_qsas'] ?? 0 );
        $stats['done_qsas']  = (int) ( $qsa_counts['done_qsas'] ?? 0 );

        return $stats;
    }

    /**
     * Update serial numbers for modules in a QSA after serial reservation.
     *
     * Links the reserved serial numbers to their corresponding engraved_modules records.
     *
     * @param int   $batch_id     The batch ID.
     * @param int   $qsa_sequence The QSA sequence number.
     * @param array $serials      Array of reserved serial data with 'serial_number' and 'array_position'.
     * @return int|WP_Error Number of updated rows or WP_Error on failure.
     */
    public function link_serials_to_modules( int $batch_id, int $qsa_sequence, array $serials ): int|WP_Error {
        if ( empty( $serials ) ) {
            return 0;
        }

        $updated = 0;

        foreach ( $serials as $serial ) {
            $serial_number  = $serial['serial_number'] ?? '';
            $array_position = $serial['array_position'] ?? 0;

            if ( empty( $serial_number ) || $array_position <= 0 ) {
                continue;
            }

            $result = $this->wpdb->update(
                $this->modules_table,
                array( 'serial_number' => $serial_number ),
                array(
                    'engraving_batch_id' => $batch_id,
                    'qsa_sequence'       => $qsa_sequence,
                    'array_position'     => $array_position,
                ),
                array( '%s' ),
                array( '%d', '%d', '%d' )
            );

            if ( false !== $result ) {
                $updated += (int) $result;
            }
        }

        return $updated;
    }
}
