<?php
/**
 * Serial Number Repository.
 *
 * Handles CRUD operations for the quad_serial_numbers table.
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
 * Repository class for serial number operations.
 *
 * @since 1.0.0
 */
class Serial_Repository {

    /**
     * Maximum serial number value (2^20 - 1).
     *
     * @var int
     */
    public const MAX_SERIAL = 1048575;

    /**
     * Valid serial statuses.
     *
     * @var array
     */
    public const VALID_STATUSES = array( 'reserved', 'engraved', 'voided' );

    /**
     * Allowed status transitions.
     *
     * Key is current status, value is array of allowed target statuses.
     * No recycling: engraved and voided are terminal states.
     *
     * @var array
     */
    public const ALLOWED_TRANSITIONS = array(
        'reserved' => array( 'engraved', 'voided' ),
        'engraved' => array(),  // Terminal state - no transitions allowed.
        'voided'   => array(),  // Terminal state - no transitions allowed.
    );

    /**
     * Option name for warning threshold setting.
     *
     * @var string
     */
    public const WARNING_THRESHOLD_OPTION = 'qsa_serial_warning_threshold';

    /**
     * Option name for critical threshold setting.
     *
     * @var string
     */
    public const CRITICAL_THRESHOLD_OPTION = 'qsa_serial_critical_threshold';

    /**
     * Default warning threshold (remaining serials).
     *
     * @var int
     */
    public const DEFAULT_WARNING_THRESHOLD = 10000;

    /**
     * Default critical threshold (remaining serials).
     *
     * @var int
     */
    public const DEFAULT_CRITICAL_THRESHOLD = 1000;

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
        $this->table_name = $wpdb->prefix . 'quad_serial_numbers';
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
     * Get the next available serial number (read-only query).
     *
     * IMPORTANT: This method performs a non-locking read to determine what the next
     * serial would be. It does NOT allocate the serial. For actual serial allocation
     * with proper transaction locking, use reserve_serials() instead.
     *
     * Use this method only for:
     * - Display purposes (showing "next serial" in UI)
     * - Capacity planning/estimation
     * - Testing/debugging
     *
     * @return int|WP_Error The next serial integer or WP_Error if exhausted.
     */
    public function get_next_serial(): int|WP_Error {
        // Non-locking read to get current maximum.
        $current_max = (int) $this->wpdb->get_var(
            "SELECT COALESCE(MAX(serial_integer), 0) FROM {$this->table_name}"
        );

        $next = $current_max + 1;

        if ( $next > self::MAX_SERIAL ) {
            return new WP_Error(
                'serial_exhausted',
                __( 'Serial number capacity exhausted. Maximum value (1,048,575) reached.', 'qsa-engraving' )
            );
        }

        return $next;
    }

    /**
     * Get the next available serial number as an 8-digit zero-padded string.
     *
     * Convenience wrapper around get_next_serial() that returns the formatted string.
     *
     * IMPORTANT: This is a read-only query. For actual serial allocation with
     * proper transaction locking, use reserve_serials() instead.
     *
     * @return string|WP_Error The next serial as 8-digit string or WP_Error if exhausted.
     */
    public function get_next_serial_formatted(): string|WP_Error {
        $next = $this->get_next_serial();

        if ( is_wp_error( $next ) ) {
            return $next;
        }

        return self::format_serial( $next );
    }

    /**
     * Get remaining serial number capacity.
     *
     * Note: 'highest_assigned' is the maximum serial integer allocated, which represents
     * the capacity consumed. This differs from a count of active serials due to voided
     * serials not being recycled.
     *
     * @return array{highest_assigned: int, remaining: int, total: int, percentage_remaining: float, warning: bool, critical: bool, warning_threshold: int, critical_threshold: int}
     */
    public function get_capacity(): array {
        $highest_assigned = (int) $this->wpdb->get_var(
            "SELECT COALESCE(MAX(serial_integer), 0) FROM {$this->table_name}"
        );

        $remaining  = self::MAX_SERIAL - $highest_assigned;
        $percentage = round( ( $remaining / self::MAX_SERIAL ) * 100, 1 );

        // Get configurable thresholds from WordPress options.
        $warning_threshold  = $this->get_warning_threshold();
        $critical_threshold = $this->get_critical_threshold();

        return array(
            'highest_assigned'     => $highest_assigned,
            'remaining'            => $remaining,
            'total'                => self::MAX_SERIAL,
            'percentage_remaining' => $percentage,
            'warning'              => $remaining <= $warning_threshold,
            'critical'             => $remaining <= $critical_threshold,
            'warning_threshold'    => $warning_threshold,
            'critical_threshold'   => $critical_threshold,
        );
    }

    /**
     * Get the warning threshold (remaining serials before warning).
     *
     * @return int
     */
    public function get_warning_threshold(): int {
        return (int) get_option( self::WARNING_THRESHOLD_OPTION, self::DEFAULT_WARNING_THRESHOLD );
    }

    /**
     * Get the critical threshold (remaining serials before critical alert).
     *
     * @return int
     */
    public function get_critical_threshold(): int {
        return (int) get_option( self::CRITICAL_THRESHOLD_OPTION, self::DEFAULT_CRITICAL_THRESHOLD );
    }

    /**
     * Set the warning threshold.
     *
     * @param int $threshold The number of remaining serials to trigger warning.
     * @return bool True if updated successfully.
     */
    public function set_warning_threshold( int $threshold ): bool {
        if ( $threshold < 0 || $threshold > self::MAX_SERIAL ) {
            return false;
        }
        return update_option( self::WARNING_THRESHOLD_OPTION, $threshold );
    }

    /**
     * Set the critical threshold.
     *
     * @param int $threshold The number of remaining serials to trigger critical alert.
     * @return bool True if updated successfully.
     */
    public function set_critical_threshold( int $threshold ): bool {
        if ( $threshold < 0 || $threshold > self::MAX_SERIAL ) {
            return false;
        }
        return update_option( self::CRITICAL_THRESHOLD_OPTION, $threshold );
    }

    /**
     * Reserve serial numbers for a batch of modules.
     *
     * @param int   $engraving_batch_id The engraving batch ID.
     * @param array $modules Array of module data to reserve serials for.
     * @return array|WP_Error Array of reserved serial records or WP_Error on failure.
     */
    public function reserve_serials( int $engraving_batch_id, array $modules ): array|WP_Error {
        $count = count( $modules );
        if ( $count === 0 ) {
            return array();
        }

        // Start transaction.
        $this->wpdb->query( 'START TRANSACTION' );

        try {
            // Lock the table to prevent race conditions.
            $this->wpdb->query( "SELECT MAX(serial_integer) FROM {$this->table_name} FOR UPDATE" );

            // Get starting serial.
            $start_serial = (int) $this->wpdb->get_var(
                "SELECT COALESCE(MAX(serial_integer), 0) + 1 FROM {$this->table_name}"
            );

            // Check capacity.
            if ( ( $start_serial + $count - 1 ) > self::MAX_SERIAL ) {
                $this->wpdb->query( 'ROLLBACK' );
                return new WP_Error(
                    'insufficient_capacity',
                    sprintf(
                        /* translators: 1: Required count, 2: Available count */
                        __( 'Cannot reserve %1$d serial numbers. Only %2$d remaining.', 'qsa-engraving' ),
                        $count,
                        self::MAX_SERIAL - $start_serial + 1
                    )
                );
            }

            $reserved  = array();
            $user_id   = get_current_user_id();

            foreach ( $modules as $index => $module ) {
                $serial_integer = $start_serial + $index;
                $serial_number  = str_pad( (string) $serial_integer, 8, '0', STR_PAD_LEFT );

                // Build data and format arrays conditionally to handle NULL order_id.
                $insert_data = array(
                    'serial_number'       => $serial_number,
                    'serial_integer'      => $serial_integer,
                    'module_sku'          => $module['module_sku'],
                    'engraving_batch_id'  => $engraving_batch_id,
                    'production_batch_id' => $module['production_batch_id'],
                    'qsa_sequence'        => $module['qsa_sequence'],
                    'array_position'      => $module['array_position'],
                    'status'              => 'reserved',
                    'created_by'          => $user_id,
                );
                $insert_format = array( '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%d' );

                // Only include order_id if it's not null.
                $order_id = $module['order_id'] ?? null;
                if ( null !== $order_id ) {
                    $insert_data['order_id'] = $order_id;
                    // Insert format at position 5 (after production_batch_id).
                    array_splice( $insert_format, 5, 0, '%d' );
                }
                // If order_id is NULL, omit from insert - database column default handles it.

                $result = $this->wpdb->insert(
                    $this->table_name,
                    $insert_data,
                    $insert_format
                );

                if ( false === $result ) {
                    $this->wpdb->query( 'ROLLBACK' );
                    return new WP_Error(
                        'insert_failed',
                        sprintf(
                            /* translators: %s: Database error message */
                            __( 'Failed to insert serial number: %s', 'qsa-engraving' ),
                            $this->wpdb->last_error
                        )
                    );
                }

                $reserved[] = array(
                    'id'            => $this->wpdb->insert_id,
                    'serial_number' => $serial_number,
                    'serial_integer' => $serial_integer,
                    'module_sku'    => $module['module_sku'],
                    'qsa_sequence'  => $module['qsa_sequence'],
                    'array_position' => $module['array_position'],
                );
            }

            $this->wpdb->query( 'COMMIT' );
            return $reserved;

        } catch ( \Exception $e ) {
            $this->wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'transaction_failed', $e->getMessage() );
        }
    }

    /**
     * Commit reserved serials as engraved.
     *
     * @param int $engraving_batch_id The engraving batch ID.
     * @param int $qsa_sequence The QSA sequence number.
     * @return int|WP_Error Number of updated rows or WP_Error on failure.
     */
    public function commit_serials( int $engraving_batch_id, int $qsa_sequence ): int|WP_Error {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table_name}
                SET status = 'engraved', engraved_at = NOW()
                WHERE engraving_batch_id = %d
                  AND qsa_sequence = %d
                  AND status = 'reserved'",
                $engraving_batch_id,
                $qsa_sequence
            )
        );

        if ( false === $result ) {
            return new WP_Error(
                'update_failed',
                __( 'Failed to commit serial numbers.', 'qsa-engraving' )
            );
        }

        return (int) $result;
    }

    /**
     * Void reserved serials.
     *
     * @param int $engraving_batch_id The engraving batch ID.
     * @param int $qsa_sequence The QSA sequence number.
     * @return int|WP_Error Number of updated rows or WP_Error on failure.
     */
    public function void_serials( int $engraving_batch_id, int $qsa_sequence ): int|WP_Error {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table_name}
                SET status = 'voided', voided_at = NOW()
                WHERE engraving_batch_id = %d
                  AND qsa_sequence = %d
                  AND status = 'reserved'",
                $engraving_batch_id,
                $qsa_sequence
            )
        );

        if ( false === $result ) {
            return new WP_Error(
                'update_failed',
                __( 'Failed to void serial numbers.', 'qsa-engraving' )
            );
        }

        return (int) $result;
    }

    /**
     * Get serials by engraving batch ID.
     *
     * @param int         $engraving_batch_id The engraving batch ID.
     * @param string|null $status Optional status filter.
     * @return array
     */
    public function get_by_batch( int $engraving_batch_id, ?string $status = null ): array {
        $sql = "SELECT * FROM {$this->table_name} WHERE engraving_batch_id = %d";
        $params = array( $engraving_batch_id );

        if ( null !== $status ) {
            $sql .= ' AND status = %s';
            $params[] = $status;
        }

        $sql .= ' ORDER BY serial_integer ASC';

        return $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, ...$params ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Get a serial by serial number string.
     *
     * @param string $serial_number The 8-character serial number.
     * @return array|null
     */
    public function get_by_serial_number( string $serial_number ): ?array {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE serial_number = %s",
                $serial_number
            ),
            ARRAY_A
        );

        return $result ?: null;
    }

    /**
     * Format an integer as an 8-character zero-padded serial string.
     *
     * @param int $serial_integer The serial integer.
     * @return string
     */
    public static function format_serial( int $serial_integer ): string {
        return str_pad( (string) $serial_integer, 8, '0', STR_PAD_LEFT );
    }

    /**
     * Validate a serial number string format.
     *
     * @param string $serial_number The serial number to validate.
     * @return bool
     */
    public static function is_valid_format( string $serial_number ): bool {
        return (bool) preg_match( '/^[0-9]{8}$/', $serial_number );
    }

    /**
     * Validate a serial integer is within valid range.
     *
     * @param int $serial_integer The serial integer to validate.
     * @return bool
     */
    public static function is_valid_range( int $serial_integer ): bool {
        return $serial_integer >= 1 && $serial_integer <= self::MAX_SERIAL;
    }

    /**
     * Validate a status value.
     *
     * @param string $status The status to validate.
     * @return bool
     */
    public static function is_valid_status( string $status ): bool {
        return in_array( $status, self::VALID_STATUSES, true );
    }

    /**
     * Check if a status transition is allowed.
     *
     * @param string $from_status The current status.
     * @param string $to_status The target status.
     * @return bool True if the transition is allowed.
     */
    public static function is_transition_allowed( string $from_status, string $to_status ): bool {
        // Validate both statuses.
        if ( ! self::is_valid_status( $from_status ) || ! self::is_valid_status( $to_status ) ) {
            return false;
        }

        // Check if transition is in the allowed list.
        return in_array( $to_status, self::ALLOWED_TRANSITIONS[ $from_status ] ?? array(), true );
    }

    /**
     * Transition a single serial number to a new status.
     *
     * @param int    $serial_id The serial record ID.
     * @param string $new_status The target status.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function transition_serial( int $serial_id, string $new_status ): bool|WP_Error {
        // Validate new status.
        if ( ! self::is_valid_status( $new_status ) ) {
            return new WP_Error(
                'invalid_status',
                sprintf(
                    /* translators: %s: Status value */
                    __( 'Invalid status: %s. Valid statuses are: reserved, engraved, voided.', 'qsa-engraving' ),
                    $new_status
                )
            );
        }

        // Get current serial record.
        $serial = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, serial_number, status FROM {$this->table_name} WHERE id = %d",
                $serial_id
            ),
            ARRAY_A
        );

        if ( ! $serial ) {
            return new WP_Error(
                'serial_not_found',
                __( 'Serial number record not found.', 'qsa-engraving' )
            );
        }

        $current_status = $serial['status'];

        // Check if transition is allowed.
        if ( ! self::is_transition_allowed( $current_status, $new_status ) ) {
            return new WP_Error(
                'invalid_transition',
                sprintf(
                    /* translators: 1: Current status, 2: Target status */
                    __( 'Cannot transition from "%1$s" to "%2$s". This transition is not allowed.', 'qsa-engraving' ),
                    $current_status,
                    $new_status
                )
            );
        }

        // Build update data based on target status.
        $update_data   = array( 'status' => $new_status );
        $update_format = array( '%s' );

        if ( 'engraved' === $new_status ) {
            $update_data['engraved_at'] = current_time( 'mysql' );
            $update_format[]            = '%s';
        } elseif ( 'voided' === $new_status ) {
            $update_data['voided_at'] = current_time( 'mysql' );
            $update_format[]          = '%s';
        }

        // Perform the update.
        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array( 'id' => $serial_id ),
            $update_format,
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'update_failed',
                sprintf(
                    /* translators: %s: Database error */
                    __( 'Failed to update serial status: %s', 'qsa-engraving' ),
                    $this->wpdb->last_error
                )
            );
        }

        return true;
    }

    /**
     * Get a serial by ID.
     *
     * @param int $serial_id The serial record ID.
     * @return array|null
     */
    public function get_by_id( int $serial_id ): ?array {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $serial_id
            ),
            ARRAY_A
        );

        return $result ?: null;
    }

    /**
     * Get count of serials by status.
     *
     * @return array Counts keyed by status.
     */
    public function get_counts_by_status(): array {
        $results = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status",
            ARRAY_A
        );

        $counts = array(
            'reserved' => 0,
            'engraved' => 0,
            'voided'   => 0,
        );

        foreach ( $results as $row ) {
            if ( isset( $counts[ $row['status'] ] ) ) {
                $counts[ $row['status'] ] = (int) $row['count'];
            }
        }

        return $counts;
    }

    /**
     * Get statistics for serial numbers.
     *
     * @return array Statistics including counts, capacity, and status breakdown.
     */
    public function get_statistics(): array {
        $capacity = $this->get_capacity();
        $counts   = $this->get_counts_by_status();
        $total    = array_sum( $counts );

        return array(
            'total_assigned'    => $total,
            'capacity'          => $capacity,
            'status_breakdown'  => $counts,
            'active_percentage' => $total > 0 ? round( ( $counts['engraved'] / $total ) * 100, 1 ) : 0.0,
        );
    }
}
