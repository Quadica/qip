<?php
/**
 * QSA Identifier Repository.
 *
 * Handles CRUD operations for the quad_qsa_identifiers and quad_qsa_design_sequences tables.
 * Provides atomic QSA ID generation with concurrency-safe sequence allocation.
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
 * Repository class for QSA identifier operations.
 *
 * This repository manages QSA-level identifiers that link arrays to QR codes.
 * Each QSA array receives a unique identifier (e.g., CUBE00076) that is:
 *   - Encoded in the QR code as quadi.ca/{qsa_id}
 *   - Assigned at SVG generation time (Start Row click)
 *   - Persistent across regenerations (same batch/sequence keeps same ID)
 *   - Sequential per design (CUBE00001, CUBE00002... separate from STAR00001...)
 *
 * @since 1.0.0
 */
class QSA_Identifier_Repository {

    /**
     * Maximum sequence number supported (5 digits = 99999).
     *
     * @var int
     */
    public const MAX_SEQUENCE = 99999;

    /**
     * Sequence number format width (5 digits).
     *
     * @var int
     */
    public const SEQUENCE_DIGITS = 5;

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Main identifiers table name with prefix.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Sequence counter table name with prefix.
     *
     * @var string
     */
    private string $sequence_table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb                = $wpdb;
        $this->table_name          = $wpdb->prefix . 'quad_qsa_identifiers';
        $this->sequence_table_name = $wpdb->prefix . 'quad_qsa_design_sequences';
    }

    /**
     * Get the main identifiers table name.
     *
     * @return string
     */
    public function get_table_name(): string {
        return $this->table_name;
    }

    /**
     * Get the sequence counter table name.
     *
     * @return string
     */
    public function get_sequence_table_name(): string {
        return $this->sequence_table_name;
    }

    /**
     * Check if the main identifiers table exists.
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
     * Check if the sequence counter table exists.
     *
     * @return bool
     */
    public function sequence_table_exists(): bool {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->sequence_table_name
            )
        );
        return $result === $this->sequence_table_name;
    }

    /**
     * Get existing QSA ID or create new one for a batch/sequence.
     *
     * This is the primary method for QSA ID assignment. It ensures that:
     * - If a QSA ID already exists for this batch/sequence, it returns the existing ID
     * - If no ID exists, it allocates a new sequence number and creates the ID
     * - Regeneration of an SVG always gets the same QSA ID
     *
     * @param int    $batch_id     The engraving batch ID.
     * @param int    $qsa_sequence The QSA sequence within the batch (1, 2, 3...).
     * @param string $design       The design name (e.g., 'CUBE', 'STAR', 'PICO').
     * @return string|WP_Error The QSA ID (e.g., 'CUBE00076') or WP_Error on failure.
     */
    public function get_or_create( int $batch_id, int $qsa_sequence, string $design ): string|WP_Error {
        // Validate inputs.
        $validation_error = $this->validate_inputs( $batch_id, $qsa_sequence, $design );
        if ( is_wp_error( $validation_error ) ) {
            return $validation_error;
        }

        // Normalize design to uppercase.
        $design = strtoupper( $design );

        // Check for existing QSA ID.
        $existing = $this->get_by_batch( $batch_id, $qsa_sequence );
        if ( null !== $existing ) {
            return $existing['qsa_id'];
        }

        // No existing ID - create a new one.
        return $this->create_qsa_id( $batch_id, $qsa_sequence, $design );
    }

    /**
     * Get QSA ID record by batch and sequence.
     *
     * @param int $batch_id     The engraving batch ID.
     * @param int $qsa_sequence The QSA sequence within the batch.
     * @return array|null The QSA ID record array or null if not found.
     */
    public function get_by_batch( int $batch_id, int $qsa_sequence ): ?array {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, qsa_id, design, sequence_number, batch_id, qsa_sequence, created_at
                 FROM {$this->table_name}
                 WHERE batch_id = %d AND qsa_sequence = %d",
                $batch_id,
                $qsa_sequence
            ),
            ARRAY_A
        );

        if ( null === $result ) {
            return null;
        }

        // Cast numeric fields.
        $result['id']              = (int) $result['id'];
        $result['sequence_number'] = (int) $result['sequence_number'];
        $result['batch_id']        = (int) $result['batch_id'];
        $result['qsa_sequence']    = (int) $result['qsa_sequence'];

        return $result;
    }

    /**
     * Get QSA ID record by the QSA ID string.
     *
     * @param string $qsa_id The QSA ID (e.g., 'CUBE00076').
     * @return array|null The QSA ID record array or null if not found.
     */
    public function get_by_qsa_id( string $qsa_id ): ?array {
        $qsa_id = strtoupper( $qsa_id );

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, qsa_id, design, sequence_number, batch_id, qsa_sequence, created_at
                 FROM {$this->table_name}
                 WHERE qsa_id = %s",
                $qsa_id
            ),
            ARRAY_A
        );

        if ( null === $result ) {
            return null;
        }

        // Cast numeric fields.
        $result['id']              = (int) $result['id'];
        $result['sequence_number'] = (int) $result['sequence_number'];
        $result['batch_id']        = (int) $result['batch_id'];
        $result['qsa_sequence']    = (int) $result['qsa_sequence'];

        return $result;
    }

    /**
     * Get all QSA IDs for a batch.
     *
     * @param int $batch_id The engraving batch ID.
     * @return array Array of QSA ID records, ordered by qsa_sequence.
     */
    public function get_all_for_batch( int $batch_id ): array {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, qsa_id, design, sequence_number, batch_id, qsa_sequence, created_at
                 FROM {$this->table_name}
                 WHERE batch_id = %d
                 ORDER BY qsa_sequence ASC",
                $batch_id
            ),
            ARRAY_A
        );

        if ( empty( $results ) ) {
            return array();
        }

        // Cast numeric fields for each record.
        foreach ( $results as &$result ) {
            $result['id']              = (int) $result['id'];
            $result['sequence_number'] = (int) $result['sequence_number'];
            $result['batch_id']        = (int) $result['batch_id'];
            $result['qsa_sequence']    = (int) $result['qsa_sequence'];
        }

        return $results;
    }

    /**
     * Get all modules linked to a QSA ID (for future reporting).
     *
     * This retrieves all serial numbers associated with a QSA ID by joining
     * through the batch_id and qsa_sequence.
     *
     * @param string $qsa_id The QSA ID (e.g., 'CUBE00076').
     * @return array Array of module serial number records.
     */
    public function get_modules_for_qsa( string $qsa_id ): array {
        global $wpdb;

        $qsa_id = strtoupper( $qsa_id );

        // Get the QSA identifier record first.
        $qsa_record = $this->get_by_qsa_id( $qsa_id );
        if ( null === $qsa_record ) {
            return array();
        }

        // Join with serial_numbers table to get all modules in this QSA.
        $serial_table = $wpdb->prefix . 'quad_serial_numbers';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT sn.id, sn.serial_number, sn.module_id, sn.array_position, sn.status, sn.created_at
                 FROM {$serial_table} sn
                 WHERE sn.batch_id = %d
                   AND sn.qsa_sequence = %d
                 ORDER BY sn.array_position ASC",
                $qsa_record['batch_id'],
                $qsa_record['qsa_sequence']
            ),
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * Get the current sequence number for a design (read-only, for display purposes).
     *
     * @param string $design The design name.
     * @return int The current sequence number (0 if none allocated yet).
     */
    public function get_current_sequence( string $design ): int {
        $design = strtoupper( $design );

        $current = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT current_sequence FROM {$this->sequence_table_name} WHERE design = %s",
                $design
            )
        );

        return (int) ( $current ?? 0 );
    }

    /**
     * Get statistics for a design.
     *
     * @param string $design The design name.
     * @return array Statistics array with counts.
     */
    public function get_design_statistics( string $design ): array {
        $design = strtoupper( $design );

        $count = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE design = %s",
                $design
            )
        );

        $current_sequence = $this->get_current_sequence( $design );

        return array(
            'design'           => $design,
            'total_allocated'  => $count,
            'current_sequence' => $current_sequence,
            'remaining'        => self::MAX_SEQUENCE - $current_sequence,
        );
    }

    /**
     * Get all designs with allocated QSA IDs.
     *
     * @return array Array of design names.
     */
    public function get_designs(): array {
        $results = $this->wpdb->get_col(
            "SELECT DISTINCT design FROM {$this->table_name} ORDER BY design"
        );

        return $results ?: array();
    }

    /**
     * Format QSA ID from design and sequence number.
     *
     * @param string $design   The design name (e.g., 'CUBE').
     * @param int    $sequence The sequence number.
     * @return string The formatted QSA ID (e.g., 'CUBE00076').
     */
    public function format_qsa_id( string $design, int $sequence ): string {
        return strtoupper( $design ) . str_pad( (string) $sequence, self::SEQUENCE_DIGITS, '0', STR_PAD_LEFT );
    }

    /**
     * Parse a QSA ID into its components.
     *
     * @param string $qsa_id The QSA ID (e.g., 'CUBE00076').
     * @return array|null Array with 'design' and 'sequence' keys, or null if invalid.
     */
    public function parse_qsa_id( string $qsa_id ): ?array {
        $qsa_id = strtoupper( $qsa_id );

        // Match 1-10 uppercase letters followed by exactly 5 digits.
        if ( ! preg_match( '/^([A-Z]{1,10})([0-9]{5})$/', $qsa_id, $matches ) ) {
            return null;
        }

        return array(
            'design'   => $matches[1],
            'sequence' => (int) $matches[2],
        );
    }

    /**
     * Validate a QSA ID format.
     *
     * @param string $qsa_id The QSA ID to validate.
     * @return bool True if valid, false otherwise.
     */
    public function is_valid_qsa_id( string $qsa_id ): bool {
        return null !== $this->parse_qsa_id( $qsa_id );
    }

    /**
     * Create a new QSA ID for a batch/sequence.
     *
     * This method handles the atomic sequence allocation using the counter table pattern.
     *
     * @param int    $batch_id     The engraving batch ID.
     * @param int    $qsa_sequence The QSA sequence within the batch.
     * @param string $design       The design name.
     * @return string|WP_Error The new QSA ID or WP_Error on failure.
     */
    private function create_qsa_id( int $batch_id, int $qsa_sequence, string $design ): string|WP_Error {
        // Allocate next sequence number atomically.
        $next_sequence = $this->get_next_sequence( $design );
        if ( is_wp_error( $next_sequence ) ) {
            return $next_sequence;
        }

        // Format the QSA ID.
        $qsa_id = $this->format_qsa_id( $design, $next_sequence );

        // Insert the identifier record.
        $result = $this->wpdb->insert(
            $this->table_name,
            array(
                'qsa_id'          => $qsa_id,
                'design'          => $design,
                'sequence_number' => $next_sequence,
                'batch_id'        => $batch_id,
                'qsa_sequence'    => $qsa_sequence,
            ),
            array( '%s', '%s', '%d', '%d', '%d' )
        );

        if ( false === $result ) {
            // Check if this was a duplicate key error (race condition edge case).
            // The counter table should prevent this, but unique constraints catch edge cases.
            if ( strpos( $this->wpdb->last_error, 'Duplicate' ) !== false ) {
                // Try to retrieve the existing record (another process may have created it).
                $existing = $this->get_by_batch( $batch_id, $qsa_sequence );
                if ( null !== $existing ) {
                    return $existing['qsa_id'];
                }
            }

            return new WP_Error(
                'insert_failed',
                sprintf(
                    /* translators: %s: Database error message */
                    __( 'Failed to create QSA ID: %s', 'qsa-engraving' ),
                    $this->wpdb->last_error
                )
            );
        }

        return $qsa_id;
    }

    /**
     * Get the next available sequence number for a design.
     *
     * Uses the INSERT...ON DUPLICATE KEY UPDATE pattern with LAST_INSERT_ID()
     * to atomically allocate a sequence number in a concurrency-safe manner.
     *
     * Pattern from session 056:
     *   INSERT INTO lw_quad_qsa_design_sequences (design, current_sequence)
     *   VALUES ('CUBE', LAST_INSERT_ID(1))
     *   ON DUPLICATE KEY UPDATE current_sequence = LAST_INSERT_ID(current_sequence + 1);
     *   SELECT LAST_INSERT_ID() AS next_sequence;
     *
     * @param string $design The design name.
     * @return int|WP_Error The next sequence number or WP_Error on failure.
     */
    private function get_next_sequence( string $design ): int|WP_Error {
        $design = strtoupper( $design );

        // Check if we've reached the maximum sequence number.
        $current = $this->get_current_sequence( $design );
        if ( $current >= self::MAX_SEQUENCE ) {
            return new WP_Error(
                'sequence_exhausted',
                sprintf(
                    /* translators: 1: Design name, 2: Maximum sequence number */
                    __( 'Sequence numbers exhausted for design %1$s. Maximum is %2$d.', 'qsa-engraving' ),
                    $design,
                    self::MAX_SEQUENCE
                )
            );
        }

        // Atomic sequence allocation using INSERT...ON DUPLICATE KEY UPDATE.
        // LAST_INSERT_ID(expr) sets the value to return from LAST_INSERT_ID().
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->sequence_table_name} (design, current_sequence)
             VALUES (%s, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE current_sequence = LAST_INSERT_ID(current_sequence + 1)",
            $design
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Already prepared above.
        $result = $this->wpdb->query( $sql );

        if ( false === $result ) {
            return new WP_Error(
                'sequence_allocation_failed',
                sprintf(
                    /* translators: %s: Database error message */
                    __( 'Failed to allocate sequence number: %s', 'qsa-engraving' ),
                    $this->wpdb->last_error
                )
            );
        }

        // Retrieve the allocated sequence number.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $next_sequence = (int) $this->wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

        if ( $next_sequence < 1 ) {
            return new WP_Error(
                'sequence_retrieval_failed',
                __( 'Failed to retrieve allocated sequence number.', 'qsa-engraving' )
            );
        }

        return $next_sequence;
    }

    /**
     * Validate inputs for get_or_create.
     *
     * @param int    $batch_id     The engraving batch ID.
     * @param int    $qsa_sequence The QSA sequence within the batch.
     * @param string $design       The design name.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    private function validate_inputs( int $batch_id, int $qsa_sequence, string $design ): true|WP_Error {
        // Validate batch_id is positive.
        if ( $batch_id < 1 ) {
            return new WP_Error(
                'invalid_batch_id',
                __( 'Batch ID must be a positive integer.', 'qsa-engraving' )
            );
        }

        // Validate qsa_sequence is positive.
        if ( $qsa_sequence < 1 ) {
            return new WP_Error(
                'invalid_qsa_sequence',
                __( 'QSA sequence must be a positive integer.', 'qsa-engraving' )
            );
        }

        // Validate design is uppercase letters only.
        $design = trim( $design );
        if ( ! preg_match( '/^[A-Za-z]+$/', $design ) ) {
            return new WP_Error(
                'invalid_design',
                __( 'Design name must contain only letters.', 'qsa-engraving' )
            );
        }

        // Validate design length (1-10 characters to match CHECK constraint).
        if ( strlen( $design ) < 1 || strlen( $design ) > 10 ) {
            return new WP_Error(
                'invalid_design_length',
                __( 'Design name must be 1-10 characters.', 'qsa-engraving' )
            );
        }

        return true;
    }

    /**
     * Delete all QSA IDs for a batch.
     *
     * WARNING: This permanently deletes QSA ID records. Use only for cleanup
     * of cancelled batches or testing purposes.
     *
     * @param int $batch_id The batch ID to delete QSA IDs for.
     * @return int|WP_Error Number of deleted records or WP_Error on failure.
     */
    public function delete_for_batch( int $batch_id ): int|WP_Error {
        if ( $batch_id < 1 ) {
            return new WP_Error(
                'invalid_batch_id',
                __( 'Batch ID must be a positive integer.', 'qsa-engraving' )
            );
        }

        $result = $this->wpdb->delete(
            $this->table_name,
            array( 'batch_id' => $batch_id ),
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error(
                'delete_failed',
                sprintf(
                    /* translators: %s: Database error message */
                    __( 'Failed to delete QSA IDs: %s', 'qsa-engraving' ),
                    $this->wpdb->last_error
                )
            );
        }

        return (int) $result;
    }
}
