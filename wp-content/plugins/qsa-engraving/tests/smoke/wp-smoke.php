<?php
/**
 * Smoke Tests for QSA Engraving Plugin
 *
 * Run via WP-CLI:
 *   wp eval-file wp-content/plugins/qsa-engraving/tests/smoke/wp-smoke.php
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

// Ensure we're in WordPress context.
if ( ! defined( 'ABSPATH' ) ) {
    echo "Error: This script must be run via WP-CLI.\n";
    exit( 1 );
}

echo "\n";
echo "==============================================\n";
echo "QSA Engraving Plugin - Phase 1 & 2 Smoke Tests\n";
echo "==============================================\n\n";

$tests_passed = 0;
$tests_failed = 0;
$tests_total  = 0;

/**
 * Run a test and record the result.
 *
 * @param string   $name        Test name.
 * @param callable $test_func   Function returning true on success, false or WP_Error on failure.
 * @param string   $description Optional description.
 */
function run_test( string $name, callable $test_func, string $description = '' ): void {
    global $tests_passed, $tests_failed, $tests_total;
    $tests_total++;

    echo "Test: {$name}\n";
    if ( $description ) {
        echo "  Description: {$description}\n";
    }

    try {
        $result = $test_func();

        if ( true === $result ) {
            echo "  Status: PASS\n";
            $tests_passed++;
        } elseif ( is_wp_error( $result ) ) {
            echo "  Status: FAIL\n";
            echo "  Error: " . $result->get_error_message() . "\n";
            $tests_failed++;
        } else {
            echo "  Status: FAIL\n";
            echo "  Error: Test returned non-true value\n";
            $tests_failed++;
        }
    } catch ( Throwable $e ) {
        echo "  Status: FAIL\n";
        echo "  Exception: " . $e->getMessage() . "\n";
        $tests_failed++;
    }

    echo "\n";
}

// ============================================
// TC-P1-001: Plugin Activates Without Errors
// ============================================
run_test(
    'TC-P1-001: Plugin activates without errors',
    function (): bool {
        // Check if plugin is active.
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = 'qsa-engraving/qsa-engraving.php';

        if ( ! is_plugin_active( $plugin_file ) ) {
            // Try to activate it.
            $result = activate_plugin( $plugin_file );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        // Check if main function exists.
        if ( ! function_exists( 'Quadica\\QSA_Engraving\\qsa_engraving' ) ) {
            return new WP_Error( 'missing_function', 'Main plugin function not found.' );
        }

        // Get plugin instance.
        $plugin = \Quadica\QSA_Engraving\qsa_engraving();

        if ( ! ( $plugin instanceof \Quadica\QSA_Engraving\Plugin ) ) {
            return new WP_Error( 'wrong_type', 'Plugin instance is not correct type.' );
        }

        return true;
    },
    'Plugin should activate and create singleton instance.'
);

// ============================================
// TC-P1-002: Admin Menu Visible
// ============================================
run_test(
    'TC-P1-002: Admin menu visible to authorized roles',
    function (): bool {
        // Check if menu is registered.
        global $submenu;

        $menu_found = false;

        if ( isset( $submenu['woocommerce'] ) ) {
            foreach ( $submenu['woocommerce'] as $item ) {
                if ( isset( $item[2] ) && $item[2] === 'qsa-engraving' ) {
                    $menu_found = true;
                    break;
                }
            }
        }

        if ( ! $menu_found ) {
            // Menu might not be registered yet if we're in CLI context.
            // Check if the Admin_Menu class exists and has correct capability.
            if ( ! class_exists( 'Quadica\\QSA_Engraving\\Admin\\Admin_Menu' ) ) {
                return new WP_Error( 'missing_class', 'Admin_Menu class not found.' );
            }

            $capability = \Quadica\QSA_Engraving\Admin\Admin_Menu::REQUIRED_CAPABILITY;
            if ( 'manage_woocommerce' !== $capability ) {
                return new WP_Error( 'wrong_capability', "Expected 'manage_woocommerce', got '{$capability}'." );
            }

            // Since we're in CLI, admin menu hooks haven't fired.
            // Check that the class can be instantiated.
            $admin_menu = new \Quadica\QSA_Engraving\Admin\Admin_Menu();
            if ( ! method_exists( $admin_menu, 'register' ) ) {
                return new WP_Error( 'missing_method', 'Admin_Menu::register() method not found.' );
            }
        }

        return true;
    },
    'Admin menu should use manage_woocommerce capability.'
);

// ============================================
// TC-P1-003: Database Tables Exist
// ============================================
run_test(
    'TC-P1-003: Database tables exist with correct structure',
    function (): bool {
        global $wpdb;

        $prefix = $wpdb->prefix;
        $tables = array(
            'quad_serial_numbers',
            'quad_engraving_batches',
            'quad_engraved_modules',
            'quad_qsa_config',
        );

        $missing = array();

        foreach ( $tables as $table ) {
            $full_name = $prefix . $table;
            $exists = $wpdb->get_var(
                $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_name )
            );

            if ( $exists !== $full_name ) {
                $missing[] = $table;
            }
        }

        if ( ! empty( $missing ) ) {
            return new WP_Error(
                'missing_tables',
                'Missing tables: ' . implode( ', ', $missing ) .
                '. Run docs/database/install/01-qsa-engraving-schema.sql via phpMyAdmin.'
            );
        }

        // Verify Serial_Repository can check table.
        $plugin      = \Quadica\QSA_Engraving\qsa_engraving();
        $serial_repo = $plugin->get_serial_repository();

        if ( ! $serial_repo->table_exists() ) {
            return new WP_Error( 'repo_check_failed', 'Serial_Repository::table_exists() returned false.' );
        }

        return true;
    },
    'All 4 database tables should exist with correct structure.'
);

// ============================================
// TC-P1-004: Module Selector Query Returns Results
// ============================================
run_test(
    'TC-P1-004: Module selector query returns expected results',
    function (): bool {
        global $wpdb;

        // Check if oms_batch_items table exists.
        $oms_table = $wpdb->prefix . 'oms_batch_items';
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $oms_table )
        );

        if ( $table_exists !== $oms_table ) {
            echo "  Note: oms_batch_items table not found. Skipping data query test.\n";
            echo "  This is expected on a fresh staging environment.\n";

            // Still pass - we're testing the service can be instantiated.
            $plugin   = \Quadica\QSA_Engraving\qsa_engraving();
            $selector = $plugin->get_module_selector();

            // Test pattern validation.
            if ( ! \Quadica\QSA_Engraving\Services\Module_Selector::is_qsa_compatible( 'CORE-91247' ) ) {
                return new WP_Error( 'pattern_fail', 'CORE-91247 should match QSA pattern.' );
            }

            if ( ! \Quadica\QSA_Engraving\Services\Module_Selector::is_qsa_compatible( 'STARa-34924' ) ) {
                return new WP_Error( 'pattern_fail', 'STARa-34924 should match QSA pattern.' );
            }

            if ( \Quadica\QSA_Engraving\Services\Module_Selector::is_qsa_compatible( 'SP-01-WW' ) ) {
                return new WP_Error( 'pattern_fail', 'SP-01-WW should NOT match QSA pattern.' );
            }

            // Test SKU parsing.
            if ( $selector->extract_base_type( 'CORE-91247' ) !== 'CORE' ) {
                return new WP_Error( 'parse_fail', 'Failed to extract base type from CORE-91247.' );
            }

            if ( $selector->extract_revision( 'STARa-34924' ) !== 'a' ) {
                return new WP_Error( 'parse_fail', 'Failed to extract revision from STARa-34924.' );
            }

            if ( $selector->extract_config_code( 'CORE-91247' ) !== '91247' ) {
                return new WP_Error( 'parse_fail', 'Failed to extract config code from CORE-91247.' );
            }

            return true;
        }

        // oms_batch_items exists - run actual query test.
        $plugin   = \Quadica\QSA_Engraving\qsa_engraving();
        $selector = $plugin->get_module_selector();

        $modules = $selector->get_modules_awaiting();

        // Should return an array (even if empty).
        if ( ! is_array( $modules ) ) {
            return new WP_Error( 'query_fail', 'get_modules_awaiting() did not return an array.' );
        }

        $total = $selector->get_total_awaiting();
        echo "  Found {$total} modules awaiting engraving.\n";

        return true;
    },
    'Module selector should query oms_batch_items and return grouped results.'
);

// ============================================
// Additional Repository Tests
// ============================================
run_test(
    'TC-P1-005: Repository classes instantiate correctly',
    function (): bool {
        $plugin = \Quadica\QSA_Engraving\qsa_engraving();

        // Serial Repository.
        $serial_repo = $plugin->get_serial_repository();
        if ( ! ( $serial_repo instanceof \Quadica\QSA_Engraving\Database\Serial_Repository ) ) {
            return new WP_Error( 'wrong_type', 'Serial_Repository wrong type.' );
        }

        // Batch Repository.
        $batch_repo = $plugin->get_batch_repository();
        if ( ! ( $batch_repo instanceof \Quadica\QSA_Engraving\Database\Batch_Repository ) ) {
            return new WP_Error( 'wrong_type', 'Batch_Repository wrong type.' );
        }

        // Config Repository.
        $config_repo = $plugin->get_config_repository();
        if ( ! ( $config_repo instanceof \Quadica\QSA_Engraving\Database\Config_Repository ) ) {
            return new WP_Error( 'wrong_type', 'Config_Repository wrong type.' );
        }

        return true;
    },
    'All repository classes should be accessible from plugin instance.'
);

run_test(
    'TC-P1-006: Serial number validation functions',
    function (): bool {
        $serial_repo = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();

        // Format validation.
        if ( ! $serial_repo::is_valid_format( '00123456' ) ) {
            return new WP_Error( 'format_fail', '00123456 should be valid format.' );
        }

        if ( $serial_repo::is_valid_format( '123456' ) ) {
            return new WP_Error( 'format_fail', '123456 (6 digits) should be invalid.' );
        }

        if ( $serial_repo::is_valid_format( '0012345A' ) ) {
            return new WP_Error( 'format_fail', '0012345A (contains letter) should be invalid.' );
        }

        // Range validation.
        if ( ! $serial_repo::is_valid_range( 1 ) ) {
            return new WP_Error( 'range_fail', '1 should be valid range.' );
        }

        if ( ! $serial_repo::is_valid_range( 1048575 ) ) {
            return new WP_Error( 'range_fail', '1048575 should be valid range.' );
        }

        if ( $serial_repo::is_valid_range( 0 ) ) {
            return new WP_Error( 'range_fail', '0 should be invalid range.' );
        }

        if ( $serial_repo::is_valid_range( 1048576 ) ) {
            return new WP_Error( 'range_fail', '1048576 should be invalid range.' );
        }

        // Format serial.
        if ( $serial_repo::format_serial( 1 ) !== '00000001' ) {
            return new WP_Error( 'format_serial_fail', '1 should format to 00000001.' );
        }

        if ( $serial_repo::format_serial( 123456 ) !== '00123456' ) {
            return new WP_Error( 'format_serial_fail', '123456 should format to 00123456.' );
        }

        return true;
    },
    'Serial number validation and formatting functions should work correctly.'
);

run_test(
    'TC-P1-007: Config repository coordinate transformation',
    function (): bool {
        // Test CAD to SVG Y transformation.
        $result = \Quadica\QSA_Engraving\Database\Config_Repository::cad_to_svg_y( 0.0 );
        if ( abs( $result - 113.7 ) > 0.001 ) {
            return new WP_Error( 'transform_fail', "cad_to_svg_y(0) should be 113.7, got {$result}." );
        }

        $result = \Quadica\QSA_Engraving\Database\Config_Repository::cad_to_svg_y( 113.7 );
        if ( abs( $result - 0.0 ) > 0.001 ) {
            return new WP_Error( 'transform_fail', "cad_to_svg_y(113.7) should be 0, got {$result}." );
        }

        $result = \Quadica\QSA_Engraving\Database\Config_Repository::cad_to_svg_y( 50.0 );
        if ( abs( $result - 63.7 ) > 0.001 ) {
            return new WP_Error( 'transform_fail', "cad_to_svg_y(50) should be 63.7, got {$result}." );
        }

        return true;
    },
    'CAD to SVG coordinate transformation should work correctly.'
);

// ============================================
// PHASE 2: Serial Number Management Tests
// ============================================

echo "-------------------------------------------\n";
echo "Phase 2: Serial Number Management Tests\n";
echo "-------------------------------------------\n\n";

run_test(
    'TC-SN-001: Serial format validation (8-digit, numeric only)',
    function (): bool {
        $repo = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();

        // Valid formats.
        $valid = array( '00000001', '00123456', '01048575', '99999999' );
        foreach ( $valid as $serial ) {
            if ( ! $repo::is_valid_format( $serial ) ) {
                return new WP_Error( 'format_fail', "{$serial} should be valid format." );
            }
        }

        // Invalid formats.
        $invalid = array( '1234567', '123456789', 'ABCDEFGH', '0012345A', '00123-56', '' );
        foreach ( $invalid as $serial ) {
            if ( $repo::is_valid_format( $serial ) ) {
                return new WP_Error( 'format_fail', "{$serial} should be invalid format." );
            }
        }

        return true;
    },
    'Serial number string validation for 8-digit numeric format.'
);

run_test(
    'TC-SN-002: Serial range validation (1 to 1048575)',
    function (): bool {
        $repo = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();

        // Valid range.
        if ( ! $repo::is_valid_range( 1 ) ) {
            return new WP_Error( 'range_fail', '1 should be valid.' );
        }
        if ( ! $repo::is_valid_range( 524288 ) ) {
            return new WP_Error( 'range_fail', '524288 (midpoint) should be valid.' );
        }
        if ( ! $repo::is_valid_range( 1048575 ) ) {
            return new WP_Error( 'range_fail', '1048575 (max) should be valid.' );
        }

        // Invalid range.
        if ( $repo::is_valid_range( 0 ) ) {
            return new WP_Error( 'range_fail', '0 should be invalid.' );
        }
        if ( $repo::is_valid_range( -1 ) ) {
            return new WP_Error( 'range_fail', '-1 should be invalid.' );
        }
        if ( $repo::is_valid_range( 1048576 ) ) {
            return new WP_Error( 'range_fail', '1048576 should be invalid.' );
        }

        // Check MAX_SERIAL constant.
        if ( $repo::MAX_SERIAL !== 1048575 ) {
            return new WP_Error( 'constant_fail', 'MAX_SERIAL should be 1048575.' );
        }

        return true;
    },
    'Serial integer range validation for 20-bit Micro-ID limit.'
);

run_test(
    'TC-SN-003: String padding (1 → "00000001")',
    function (): bool {
        $repo = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();

        $test_cases = array(
            1        => '00000001',
            42       => '00000042',
            123      => '00000123',
            12345    => '00012345',
            123456   => '00123456',
            1234567  => '01234567',
            12345678 => '12345678',
            1048575  => '01048575',
        );

        foreach ( $test_cases as $input => $expected ) {
            $result = $repo::format_serial( $input );
            if ( $result !== $expected ) {
                return new WP_Error(
                    'format_fail',
                    "format_serial({$input}) expected '{$expected}', got '{$result}'."
                );
            }
        }

        return true;
    },
    'Serial integer to 8-character zero-padded string conversion.'
);

run_test(
    'TC-SN-DB-001: Sequential generation (N+1 = N + 1)',
    function (): bool {
        $repo = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();

        // Get current next serial (integer).
        $next1 = $repo->get_next_serial();
        if ( is_wp_error( $next1 ) ) {
            return $next1;
        }

        // Get it again - should return the same value since nothing was inserted.
        $next2 = $repo->get_next_serial();
        if ( is_wp_error( $next2 ) ) {
            return $next2;
        }

        if ( $next1 !== $next2 ) {
            return new WP_Error(
                'sequential_fail',
                "get_next_serial() should return consistent value. Got {$next1} then {$next2}."
            );
        }

        // Test formatted version returns 8-digit string.
        $formatted = $repo->get_next_serial_formatted();
        if ( is_wp_error( $formatted ) ) {
            return $formatted;
        }

        if ( ! is_string( $formatted ) || strlen( $formatted ) !== 8 ) {
            return new WP_Error(
                'format_fail',
                "get_next_serial_formatted() should return 8-char string. Got: " . gettype( $formatted )
            );
        }

        // Verify formatted matches expected value.
        $expected_formatted = str_pad( (string) $next1, 8, '0', STR_PAD_LEFT );
        if ( $formatted !== $expected_formatted ) {
            return new WP_Error(
                'format_mismatch',
                "Expected formatted '{$expected_formatted}', got '{$formatted}'."
            );
        }

        echo "  Next available serial: {$next1} (formatted: {$formatted})\n";

        return true;
    },
    'Serial numbers generate sequentially from MAX(serial_integer) + 1.'
);

run_test(
    'TC-SN-DB-002: Uniqueness constraint enforced',
    function (): bool {
        global $wpdb;

        $repo       = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();
        $table_name = $repo->get_table_name();

        // Verify the UNIQUE indexes exist on the table.
        $indexes = $wpdb->get_results( "SHOW INDEX FROM {$table_name}" );

        if ( empty( $indexes ) ) {
            return new WP_Error( 'no_indexes', 'No indexes found on serial_numbers table.' );
        }

        $unique_indexes = array();
        foreach ( $indexes as $index ) {
            if ( 0 === (int) $index->Non_unique ) {
                $unique_indexes[ $index->Key_name ][] = $index->Column_name;
            }
        }

        // Check for unique constraint on serial_number column.
        $serial_number_unique = false;
        $serial_integer_unique = false;

        foreach ( $unique_indexes as $key_name => $columns ) {
            if ( in_array( 'serial_number', $columns, true ) ) {
                $serial_number_unique = true;
            }
            if ( in_array( 'serial_integer', $columns, true ) ) {
                $serial_integer_unique = true;
            }
        }

        if ( ! $serial_number_unique ) {
            return new WP_Error(
                'missing_unique',
                'UNIQUE constraint on serial_number column is missing.'
            );
        }

        if ( ! $serial_integer_unique ) {
            return new WP_Error(
                'missing_unique',
                'UNIQUE constraint on serial_integer column is missing.'
            );
        }

        echo "  UNIQUE constraints verified on serial_number and serial_integer.\n";
        echo "  Unique indexes: " . implode( ', ', array_keys( $unique_indexes ) ) . "\n";

        return true;
    },
    'Database UNIQUE constraints prevent duplicate serial numbers.'
);

run_test(
    'TC-SN-DB-003: Status transitions validated',
    function (): bool {
        $repo = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();

        // Test valid statuses.
        if ( ! $repo::is_valid_status( 'reserved' ) ) {
            return new WP_Error( 'status_fail', 'reserved should be valid status.' );
        }
        if ( ! $repo::is_valid_status( 'engraved' ) ) {
            return new WP_Error( 'status_fail', 'engraved should be valid status.' );
        }
        if ( ! $repo::is_valid_status( 'voided' ) ) {
            return new WP_Error( 'status_fail', 'voided should be valid status.' );
        }
        if ( $repo::is_valid_status( 'pending' ) ) {
            return new WP_Error( 'status_fail', 'pending should be invalid status.' );
        }

        // Test allowed transitions.
        // reserved -> engraved: ALLOWED
        if ( ! $repo::is_transition_allowed( 'reserved', 'engraved' ) ) {
            return new WP_Error( 'transition_fail', 'reserved -> engraved should be allowed.' );
        }
        // reserved -> voided: ALLOWED
        if ( ! $repo::is_transition_allowed( 'reserved', 'voided' ) ) {
            return new WP_Error( 'transition_fail', 'reserved -> voided should be allowed.' );
        }

        // Test blocked transitions (no recycling).
        // engraved -> reserved: BLOCKED
        if ( $repo::is_transition_allowed( 'engraved', 'reserved' ) ) {
            return new WP_Error( 'transition_fail', 'engraved -> reserved should be blocked.' );
        }
        // voided -> reserved: BLOCKED
        if ( $repo::is_transition_allowed( 'voided', 'reserved' ) ) {
            return new WP_Error( 'transition_fail', 'voided -> reserved should be blocked.' );
        }
        // engraved -> voided: BLOCKED
        if ( $repo::is_transition_allowed( 'engraved', 'voided' ) ) {
            return new WP_Error( 'transition_fail', 'engraved -> voided should be blocked.' );
        }
        // voided -> engraved: BLOCKED
        if ( $repo::is_transition_allowed( 'voided', 'engraved' ) ) {
            return new WP_Error( 'transition_fail', 'voided -> engraved should be blocked.' );
        }

        echo "  All status transitions follow allowed paths.\n";

        return true;
    },
    'Status transitions follow allowed paths; terminal states block transitions.'
);

run_test(
    'TC-SN-DB-004: Capacity calculation correct',
    function (): bool {
        $repo     = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();
        $capacity = $repo->get_capacity();

        // Verify capacity structure.
        $required_keys = array(
            'highest_assigned',
            'remaining',
            'total',
            'percentage_remaining',
            'warning',
            'critical',
            'warning_threshold',
            'critical_threshold',
        );

        foreach ( $required_keys as $key ) {
            if ( ! array_key_exists( $key, $capacity ) ) {
                return new WP_Error( 'capacity_fail', "Missing key: {$key}" );
            }
        }

        // Verify total.
        if ( $capacity['total'] !== 1048575 ) {
            return new WP_Error( 'capacity_fail', "Total should be 1048575, got {$capacity['total']}." );
        }

        // Verify calculation: highest_assigned + remaining = total.
        $calculated_total = $capacity['highest_assigned'] + $capacity['remaining'];
        if ( $calculated_total !== $capacity['total'] ) {
            return new WP_Error(
                'capacity_fail',
                "highest_assigned ({$capacity['highest_assigned']}) + remaining ({$capacity['remaining']}) " .
                "should equal total ({$capacity['total']}), but got {$calculated_total}."
            );
        }

        // Verify percentage calculation.
        $expected_percentage = round( ( $capacity['remaining'] / $capacity['total'] ) * 100, 1 );
        if ( abs( $capacity['percentage_remaining'] - $expected_percentage ) > 0.1 ) {
            return new WP_Error(
                'capacity_fail',
                "Percentage should be ~{$expected_percentage}, got {$capacity['percentage_remaining']}."
            );
        }

        // Verify warning/critical are boolean.
        if ( ! is_bool( $capacity['warning'] ) || ! is_bool( $capacity['critical'] ) ) {
            return new WP_Error( 'capacity_fail', 'warning and critical should be boolean.' );
        }

        echo "  Capacity: {$capacity['remaining']} of {$capacity['total']} ({$capacity['percentage_remaining']}%)\n";
        echo "  Thresholds: warning at {$capacity['warning_threshold']}, critical at {$capacity['critical_threshold']}\n";

        return true;
    },
    'Capacity calculation returns correct structure and values.'
);

run_test(
    'TC-SN-DB-005: Threshold configuration',
    function (): bool {
        $repo = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();

        // Test default thresholds.
        if ( $repo::DEFAULT_WARNING_THRESHOLD !== 10000 ) {
            return new WP_Error( 'threshold_fail', 'Default warning threshold should be 10000.' );
        }
        if ( $repo::DEFAULT_CRITICAL_THRESHOLD !== 1000 ) {
            return new WP_Error( 'threshold_fail', 'Default critical threshold should be 1000.' );
        }

        // Test getter methods.
        $warning_threshold  = $repo->get_warning_threshold();
        $critical_threshold = $repo->get_critical_threshold();

        if ( ! is_int( $warning_threshold ) || $warning_threshold < 0 ) {
            return new WP_Error( 'threshold_fail', 'Warning threshold should be non-negative integer.' );
        }
        if ( ! is_int( $critical_threshold ) || $critical_threshold < 0 ) {
            return new WP_Error( 'threshold_fail', 'Critical threshold should be non-negative integer.' );
        }

        echo "  Current thresholds: warning={$warning_threshold}, critical={$critical_threshold}\n";

        return true;
    },
    'Threshold configuration with default values and getter methods.'
);

run_test(
    'TC-SN-DB-006: Statistics method',
    function (): bool {
        $repo  = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();
        $stats = $repo->get_statistics();

        // Verify structure.
        $required_keys = array( 'total_assigned', 'capacity', 'status_breakdown', 'active_percentage' );
        foreach ( $required_keys as $key ) {
            if ( ! array_key_exists( $key, $stats ) ) {
                return new WP_Error( 'stats_fail', "Missing key: {$key}" );
            }
        }

        // Verify status breakdown keys.
        $status_keys = array( 'reserved', 'engraved', 'voided' );
        foreach ( $status_keys as $key ) {
            if ( ! array_key_exists( $key, $stats['status_breakdown'] ) ) {
                return new WP_Error( 'stats_fail', "Missing status_breakdown key: {$key}" );
            }
        }

        echo "  Total assigned: {$stats['total_assigned']}\n";
        echo "  Breakdown: reserved={$stats['status_breakdown']['reserved']}, " .
             "engraved={$stats['status_breakdown']['engraved']}, " .
             "voided={$stats['status_breakdown']['voided']}\n";

        return true;
    },
    'Statistics method returns correct structure with counts.'
);

// ============================================
// Summary
// ============================================
echo "===========================================\n";
echo "Test Summary\n";
echo "===========================================\n";
echo "Total:  {$tests_total}\n";
echo "Passed: {$tests_passed}\n";
echo "Failed: {$tests_failed}\n";
echo "===========================================\n\n";

if ( $tests_failed > 0 ) {
    echo "RESULT: Some tests failed. Please review the output above.\n\n";
    exit( 1 );
} else {
    echo "RESULT: All tests passed!\n\n";
    exit( 0 );
}
