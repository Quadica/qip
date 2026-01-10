<?php
/**
 * SKU Mapping AJAX Handler.
 *
 * Handles AJAX requests for SKU mapping management operations.
 *
 * @package QSA_Engraving
 * @since 1.1.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Ajax;

use Quadica\QSA_Engraving\Database\SKU_Mapping_Repository;
use Quadica\QSA_Engraving\Services\Legacy_SKU_Resolver;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles AJAX requests for SKU mapping management.
 *
 * Provides endpoints for:
 * - Listing mappings (with pagination and search)
 * - Creating new mappings
 * - Updating existing mappings
 * - Deleting mappings
 * - Testing SKU resolution
 *
 * @since 1.1.0
 */
class SKU_Mapping_Ajax_Handler {

    /**
     * SKU Mapping repository instance.
     *
     * @var SKU_Mapping_Repository
     */
    private SKU_Mapping_Repository $repository;

    /**
     * Legacy SKU resolver instance.
     *
     * @var Legacy_SKU_Resolver|null
     */
    private ?Legacy_SKU_Resolver $resolver;

    /**
     * Constructor.
     *
     * @param SKU_Mapping_Repository   $repository SKU mapping repository instance.
     * @param Legacy_SKU_Resolver|null $resolver   Optional resolver for testing resolution.
     */
    public function __construct(
        SKU_Mapping_Repository $repository,
        ?Legacy_SKU_Resolver $resolver = null
    ) {
        $this->repository = $repository;
        $this->resolver   = $resolver;
    }

    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'wp_ajax_qsa_get_sku_mappings', array( $this, 'handle_get_mappings' ) );
        add_action( 'wp_ajax_qsa_add_sku_mapping', array( $this, 'handle_add_mapping' ) );
        add_action( 'wp_ajax_qsa_update_sku_mapping', array( $this, 'handle_update_mapping' ) );
        add_action( 'wp_ajax_qsa_delete_sku_mapping', array( $this, 'handle_delete_mapping' ) );
        add_action( 'wp_ajax_qsa_toggle_sku_mapping', array( $this, 'handle_toggle_mapping' ) );
        add_action( 'wp_ajax_qsa_test_sku_resolution', array( $this, 'handle_test_resolution' ) );
    }

    /**
     * Verify the AJAX nonce.
     *
     * @return bool
     */
    private function verify_nonce(): bool {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
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
     * Send JSON success response.
     *
     * @param array $data Response data.
     * @return void
     */
    private function send_success( array $data ): void {
        wp_send_json_success( $data );
    }

    /**
     * Check if the mapping table exists and send error if not.
     *
     * @return bool True if table exists, false if error was sent.
     */
    private function require_table_exists(): bool {
        if ( ! $this->repository->table_exists() ) {
            $this->send_error(
                __( 'SKU mappings table does not exist. Please run the database installation script.', 'qsa-engraving' ),
                'table_missing'
            );
            return false;
        }
        return true;
    }

    /**
     * Handle get mappings request.
     *
     * @return void
     */
    public function handle_get_mappings(): void {
        if ( ! $this->verify_nonce() ) {
            $this->send_error( __( 'Security check failed.', 'qsa-engraving' ), 'invalid_nonce' );
            return;
        }

        if ( ! $this->user_has_access() ) {
            $this->send_error( __( 'You do not have permission to access this resource.', 'qsa-engraving' ), 'unauthorized' );
            return;
        }

        if ( ! $this->require_table_exists() ) {
            return;
        }

        // Get search parameter.
        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        // Get include_inactive parameter.
        $include_inactive = isset( $_POST['include_inactive'] ) && 'true' === $_POST['include_inactive'];
        $active_only      = ! $include_inactive;

        if ( ! empty( $search ) ) {
            $mappings = $this->repository->search( $search, $active_only );
        } else {
            $mappings = $this->repository->get_all( $active_only, 'priority', 'ASC' );
        }

        // Get total count for display.
        $total_count  = $this->repository->count( false );
        $active_count = $this->repository->count( true );

        $this->send_success(
            array(
                'mappings'     => $mappings,
                'total_count'  => $total_count,
                'active_count' => $active_count,
            )
        );
    }

    /**
     * Handle add mapping request.
     *
     * @return void
     */
    public function handle_add_mapping(): void {
        if ( ! $this->verify_nonce() ) {
            $this->send_error( __( 'Security check failed.', 'qsa-engraving' ), 'invalid_nonce' );
            return;
        }

        if ( ! $this->user_has_access() ) {
            $this->send_error( __( 'You do not have permission to perform this action.', 'qsa-engraving' ), 'unauthorized' );
            return;
        }

        if ( ! $this->require_table_exists() ) {
            return;
        }

        // Sanitize input.
        $data = array(
            'legacy_pattern' => isset( $_POST['legacy_pattern'] ) ? sanitize_text_field( wp_unslash( $_POST['legacy_pattern'] ) ) : '',
            'match_type'     => isset( $_POST['match_type'] ) ? sanitize_text_field( wp_unslash( $_POST['match_type'] ) ) : 'exact',
            'canonical_code' => isset( $_POST['canonical_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['canonical_code'] ) ) ) : '',
            'revision'       => isset( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( $_POST['revision'] ) ) : '',
            'description'    => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'priority'       => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 100,
            'is_active'      => isset( $_POST['is_active'] ) ? ( $_POST['is_active'] === 'true' || $_POST['is_active'] === '1' ? 1 : 0 ) : 1,
        );

        $result = $this->repository->create( $data );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), $result->get_error_code() );
            return;
        }

        // Get the created mapping to return.
        $mapping = $this->repository->get( $result );

        $this->send_success(
            array(
                'id'      => $result,
                'mapping' => $mapping,
                'message' => __( 'SKU mapping created successfully.', 'qsa-engraving' ),
            )
        );
    }

    /**
     * Handle update mapping request.
     *
     * @return void
     */
    public function handle_update_mapping(): void {
        if ( ! $this->verify_nonce() ) {
            $this->send_error( __( 'Security check failed.', 'qsa-engraving' ), 'invalid_nonce' );
            return;
        }

        if ( ! $this->user_has_access() ) {
            $this->send_error( __( 'You do not have permission to perform this action.', 'qsa-engraving' ), 'unauthorized' );
            return;
        }

        if ( ! $this->require_table_exists() ) {
            return;
        }

        // Get mapping ID.
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( 0 === $id ) {
            $this->send_error( __( 'Mapping ID is required.', 'qsa-engraving' ), 'missing_id' );
            return;
        }

        // Sanitize input - only include provided fields.
        $data = array();

        if ( isset( $_POST['legacy_pattern'] ) ) {
            $data['legacy_pattern'] = sanitize_text_field( wp_unslash( $_POST['legacy_pattern'] ) );
        }
        if ( isset( $_POST['match_type'] ) ) {
            $data['match_type'] = sanitize_text_field( wp_unslash( $_POST['match_type'] ) );
        }
        if ( isset( $_POST['canonical_code'] ) ) {
            $data['canonical_code'] = strtoupper( sanitize_text_field( wp_unslash( $_POST['canonical_code'] ) ) );
        }
        if ( isset( $_POST['revision'] ) ) {
            $data['revision'] = sanitize_text_field( wp_unslash( $_POST['revision'] ) );
        }
        if ( isset( $_POST['description'] ) ) {
            $data['description'] = sanitize_textarea_field( wp_unslash( $_POST['description'] ) );
        }
        if ( isset( $_POST['priority'] ) ) {
            $data['priority'] = absint( $_POST['priority'] );
        }
        if ( isset( $_POST['is_active'] ) ) {
            $data['is_active'] = $_POST['is_active'] === 'true' || $_POST['is_active'] === '1' ? 1 : 0;
        }

        $result = $this->repository->update( $id, $data );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), $result->get_error_code() );
            return;
        }

        // Get the updated mapping to return.
        $mapping = $this->repository->get( $id );

        $this->send_success(
            array(
                'mapping' => $mapping,
                'message' => __( 'SKU mapping updated successfully.', 'qsa-engraving' ),
            )
        );
    }

    /**
     * Handle delete mapping request.
     *
     * @return void
     */
    public function handle_delete_mapping(): void {
        if ( ! $this->verify_nonce() ) {
            $this->send_error( __( 'Security check failed.', 'qsa-engraving' ), 'invalid_nonce' );
            return;
        }

        if ( ! $this->user_has_access() ) {
            $this->send_error( __( 'You do not have permission to perform this action.', 'qsa-engraving' ), 'unauthorized' );
            return;
        }

        if ( ! $this->require_table_exists() ) {
            return;
        }

        // Get mapping ID.
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( 0 === $id ) {
            $this->send_error( __( 'Mapping ID is required.', 'qsa-engraving' ), 'missing_id' );
            return;
        }

        // Check if mapping exists.
        $existing = $this->repository->get( $id );
        if ( ! $existing ) {
            $this->send_error( __( 'SKU mapping not found.', 'qsa-engraving' ), 'not_found' );
            return;
        }

        $result = $this->repository->delete( $id );

        if ( ! $result ) {
            $this->send_error( __( 'Failed to delete SKU mapping.', 'qsa-engraving' ), 'delete_failed' );
            return;
        }

        $this->send_success(
            array(
                'message' => __( 'SKU mapping deleted successfully.', 'qsa-engraving' ),
            )
        );
    }

    /**
     * Handle toggle mapping active status.
     *
     * @return void
     */
    public function handle_toggle_mapping(): void {
        if ( ! $this->verify_nonce() ) {
            $this->send_error( __( 'Security check failed.', 'qsa-engraving' ), 'invalid_nonce' );
            return;
        }

        if ( ! $this->user_has_access() ) {
            $this->send_error( __( 'You do not have permission to perform this action.', 'qsa-engraving' ), 'unauthorized' );
            return;
        }

        if ( ! $this->require_table_exists() ) {
            return;
        }

        // Get mapping ID.
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( 0 === $id ) {
            $this->send_error( __( 'Mapping ID is required.', 'qsa-engraving' ), 'missing_id' );
            return;
        }

        $result = $this->repository->toggle_active( $id );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message(), $result->get_error_code() );
            return;
        }

        // Get the updated mapping.
        $mapping = $this->repository->get( $id );

        $this->send_success(
            array(
                'is_active' => $result,
                'mapping'   => $mapping,
                'message'   => $result
                    ? __( 'SKU mapping activated.', 'qsa-engraving' )
                    : __( 'SKU mapping deactivated.', 'qsa-engraving' ),
            )
        );
    }

    /**
     * Handle test SKU resolution request.
     *
     * Tests how a SKU would be resolved using the current mappings.
     *
     * @return void
     */
    public function handle_test_resolution(): void {
        if ( ! $this->verify_nonce() ) {
            $this->send_error( __( 'Security check failed.', 'qsa-engraving' ), 'invalid_nonce' );
            return;
        }

        if ( ! $this->user_has_access() ) {
            $this->send_error( __( 'You do not have permission to perform this action.', 'qsa-engraving' ), 'unauthorized' );
            return;
        }

        if ( ! $this->require_table_exists() ) {
            return;
        }

        // Get test SKU.
        $sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
        if ( empty( $sku ) ) {
            $this->send_error( __( 'SKU is required for testing.', 'qsa-engraving' ), 'missing_sku' );
            return;
        }

        // Use the resolver if available, otherwise use repository directly.
        if ( null !== $this->resolver ) {
            $resolution = $this->resolver->resolve( $sku );

            if ( null === $resolution ) {
                $this->send_success(
                    array(
                        'matched'    => false,
                        'sku'        => $sku,
                        'message'    => __( 'No mapping matches this SKU.', 'qsa-engraving' ),
                        'resolution' => null,
                    )
                );
                return;
            }

            // Check if config exists for this design.
            $config_exists = $this->check_config_exists(
                $resolution['canonical_code'],
                $resolution['revision']
            );

            $this->send_success(
                array(
                    'matched'       => true,
                    'sku'           => $sku,
                    'resolution'    => $resolution,
                    'config_exists' => $config_exists,
                    'message'       => $resolution['is_legacy']
                        ? __( 'Matched via legacy mapping.', 'qsa-engraving' )
                        : __( 'Matched as native QSA format.', 'qsa-engraving' ),
                )
            );
        } else {
            // Fallback: Use repository directly for mapping lookup.
            $mapping = $this->repository->find_mapping( $sku );

            if ( null === $mapping ) {
                $this->send_success(
                    array(
                        'matched' => false,
                        'sku'     => $sku,
                        'message' => __( 'No mapping matches this SKU.', 'qsa-engraving' ),
                        'mapping' => null,
                    )
                );
                return;
            }

            $config_exists = $this->check_config_exists(
                $mapping['canonical_code'],
                $mapping['revision'] ?? null
            );

            $this->send_success(
                array(
                    'matched'       => true,
                    'sku'           => $sku,
                    'mapping'       => $mapping,
                    'config_exists' => $config_exists,
                    'message'       => __( 'Matched via legacy mapping.', 'qsa-engraving' ),
                )
            );
        }
    }

    /**
     * Check if configuration exists for a design.
     *
     * @param string      $canonical_code The design code.
     * @param string|null $revision       Optional revision letter.
     * @return bool
     */
    private function check_config_exists( string $canonical_code, ?string $revision ): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'quad_qsa_config';

        // Check if table exists.
        $table_check = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
        );

        if ( $table_check !== $table_name ) {
            return false;
        }

        // Check for matching config.
        if ( $revision ) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name}
                    WHERE qsa_design = %s AND revision = %s AND is_active = 1",
                    $canonical_code,
                    $revision
                )
            );
        } else {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name}
                    WHERE qsa_design = %s AND (revision IS NULL OR revision = '') AND is_active = 1",
                    $canonical_code
                )
            );
        }

        return (int) $count > 0;
    }
}
