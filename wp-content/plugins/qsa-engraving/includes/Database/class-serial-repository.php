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
     * Get the next available serial number.
     *
     * Uses a transaction to ensure atomicity.
     *
     * @return int|WP_Error The next serial integer or WP_Error if exhausted.
     */
    public function get_next_serial(): int|WP_Error {
        // Get current maximum.
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
     * Get remaining serial number capacity.
     *
     * @return array{used: int, remaining: int, total: int, percentage_remaining: float, warning: bool, critical: bool}
     */
    public function get_capacity(): array {
        $used = (int) $this->wpdb->get_var(
            "SELECT COALESCE(MAX(serial_integer), 0) FROM {$this->table_name}"
        );

        $remaining  = self::MAX_SERIAL - $used;
        $percentage = round( ( $remaining / self::MAX_SERIAL ) * 100, 1 );

        return array(
            'used'                 => $used,
            'remaining'            => $remaining,
            'total'                => self::MAX_SERIAL,
            'percentage_remaining' => $percentage,
            'warning'              => $remaining < 10000,
            'critical'             => $remaining < 1000,
        );
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

                $result = $this->wpdb->insert(
                    $this->table_name,
                    array(
                        'serial_number'       => $serial_number,
                        'serial_integer'      => $serial_integer,
                        'module_sku'          => $module['module_sku'],
                        'engraving_batch_id'  => $engraving_batch_id,
                        'production_batch_id' => $module['production_batch_id'],
                        'order_id'            => $module['order_id'] ?? null,
                        'qsa_sequence'        => $module['qsa_sequence'],
                        'array_position'      => $module['array_position'],
                        'status'              => 'reserved',
                        'created_by'          => $user_id,
                    ),
                    array( '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d' )
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
}
