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
echo "====================================================\n";
echo "QSA Engraving Plugin - Phase 1, 2, 3 & 4 Smoke Tests\n";
echo "====================================================\n\n";

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
// PHASE 3: Micro-ID Encoding Tests
// ============================================

echo "-------------------------------------------\n";
echo "Phase 3: Micro-ID Encoding Tests\n";
echo "-------------------------------------------\n\n";

run_test(
    'TC-MID-001: Minimum value (00000001) encoding',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Test encoding serial 1.
        $binary = $encoder::encode_binary( 1 );
        if ( is_wp_error( $binary ) ) {
            return $binary;
        }

        // Should be 20 zeros followed by 1.
        $expected = '00000000000000000001';
        if ( $binary !== $expected ) {
            return new WP_Error( 'binary_fail', "Expected '{$expected}', got '{$binary}'." );
        }

        // Parity should be 1 (one bit set, odd count).
        $parity = $encoder::calculate_parity( $binary );
        if ( $parity !== 1 ) {
            return new WP_Error( 'parity_fail', "Expected parity 1, got {$parity}." );
        }

        // Count dots: 1 orientation + 4 anchors + 1 data bit + 1 parity = 7.
        $dot_count = $encoder::count_dots( 1 );
        if ( is_wp_error( $dot_count ) ) {
            return $dot_count;
        }
        if ( $dot_count !== 7 ) {
            return new WP_Error( 'dot_count_fail', "Expected 7 dots, got {$dot_count}." );
        }

        echo "  Serial 1: binary={$binary}, parity={$parity}, dots={$dot_count}\n";

        return true;
    },
    'Minimum serial (1) should encode correctly with 7 dots total.'
);

run_test(
    'TC-MID-002: Maximum value (01048575) encoding',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Test encoding max serial.
        $binary = $encoder::encode_binary( 1048575 );
        if ( is_wp_error( $binary ) ) {
            return $binary;
        }

        // Should be all 1s (20 bits).
        $expected = '11111111111111111111';
        if ( $binary !== $expected ) {
            return new WP_Error( 'binary_fail', "Expected '{$expected}', got '{$binary}'." );
        }

        // Parity should be 0 (20 bits set, even count).
        $parity = $encoder::calculate_parity( $binary );
        if ( $parity !== 0 ) {
            return new WP_Error( 'parity_fail', "Expected parity 0, got {$parity}." );
        }

        // Count dots: 1 orientation + 4 anchors + 20 data bits + 0 parity = 25.
        $dot_count = $encoder::count_dots( 1048575 );
        if ( is_wp_error( $dot_count ) ) {
            return $dot_count;
        }
        if ( $dot_count !== 25 ) {
            return new WP_Error( 'dot_count_fail', "Expected 25 dots, got {$dot_count}." );
        }

        echo "  Serial 1048575: binary={$binary}, parity={$parity}, dots={$dot_count}\n";

        return true;
    },
    'Maximum serial (1048575) should encode correctly with 25 dots total.'
);

run_test(
    'TC-MID-003: Medium density (00600001) encoding',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Test 600001 per specification example.
        $binary = $encoder::encode_binary( 600001 );
        if ( is_wp_error( $binary ) ) {
            return $binary;
        }

        // Per spec: 600001 = binary 10010010011111000001 (verify by converting 600001).
        // Let's verify: 600001 in binary.
        $expected = str_pad( decbin( 600001 ), 20, '0', STR_PAD_LEFT );
        if ( $binary !== $expected ) {
            return new WP_Error( 'binary_fail', "Binary mismatch. Got '{$binary}'." );
        }

        // Count 1s: 10010010011111000001 has 9 ones.
        $ones_count = substr_count( $binary, '1' );
        // 9 is odd, so parity should be 1.
        $parity = $encoder::calculate_parity( $binary );
        $expected_parity = ( $ones_count % 2 === 0 ) ? 0 : 1;
        if ( $parity !== $expected_parity ) {
            return new WP_Error(
                'parity_fail',
                "Binary has {$ones_count} ones. Expected parity {$expected_parity}, got {$parity}."
            );
        }

        echo "  Serial 600001: binary={$binary}, ones={$ones_count}, parity={$parity}\n";

        return true;
    },
    'Medium density serial (600001) encoding matches specification example.'
);

run_test(
    'TC-MID-004: Sample SVG (00123454) encoding',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Test 123454 from stara-qsa-sample.svg.
        $serial = 123454;
        $binary = $encoder::encode_binary( $serial );
        if ( is_wp_error( $binary ) ) {
            return $binary;
        }

        // Expected binary for 123454.
        $expected = str_pad( decbin( 123454 ), 20, '0', STR_PAD_LEFT );
        if ( $binary !== $expected ) {
            return new WP_Error( 'binary_fail', "Expected '{$expected}', got '{$binary}'." );
        }

        // Per sample SVG comment: binary 00011110001000111110, parity 0.
        // Let's verify: 123454 = 0x1E23E = 00011110001000111110.
        $expected_binary = '00011110001000111110';
        if ( $binary !== $expected_binary ) {
            return new WP_Error(
                'binary_verify_fail',
                "Expected sample binary '{$expected_binary}', got '{$binary}'."
            );
        }

        // Parity check: count 1s in binary.
        $ones_count = substr_count( $binary, '1' );
        $parity = $encoder::calculate_parity( $binary );
        // 10 ones = even, parity = 0.
        if ( $ones_count !== 10 || $parity !== 0 ) {
            return new WP_Error(
                'parity_verify_fail',
                "Expected 10 ones with parity 0. Got {$ones_count} ones, parity {$parity}."
            );
        }

        // Get grid and verify structure.
        $grid = $encoder::get_grid( $serial );
        if ( is_wp_error( $grid ) ) {
            return $grid;
        }

        // Verify corners are ON (anchors).
        if ( $grid[0][0] !== 1 || $grid[0][4] !== 1 || $grid[4][0] !== 1 || $grid[4][4] !== 1 ) {
            return new WP_Error( 'anchor_fail', 'Corner anchors should all be ON.' );
        }

        // Parity position (4,3) should be OFF since parity = 0.
        if ( $grid[4][3] !== 0 ) {
            return new WP_Error( 'parity_grid_fail', 'Parity bit at (4,3) should be OFF.' );
        }

        echo "  Serial 123454: binary={$binary}, parity={$parity}\n";
        echo "  Grid matches sample SVG structure.\n";

        return true;
    },
    'Sample SVG serial (123454) encoding matches stara-qsa-sample.svg.'
);

run_test(
    'TC-MID-005: Alternating bits (00699050) encoding',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // 699050 tests bit distribution across all rows.
        $serial = 699050;
        $binary = $encoder::encode_binary( $serial );
        if ( is_wp_error( $binary ) ) {
            return $binary;
        }

        $expected = str_pad( decbin( $serial ), 20, '0', STR_PAD_LEFT );
        if ( $binary !== $expected ) {
            return new WP_Error( 'binary_fail', "Binary mismatch. Got '{$binary}'." );
        }

        // Get the grid.
        $grid = $encoder::get_grid( $serial );
        if ( is_wp_error( $grid ) ) {
            return $grid;
        }

        // Verify grid is 5x5.
        if ( count( $grid ) !== 5 ) {
            return new WP_Error( 'grid_fail', 'Grid should have 5 rows.' );
        }
        foreach ( $grid as $row ) {
            if ( count( $row ) !== 5 ) {
                return new WP_Error( 'grid_fail', 'Each row should have 5 columns.' );
            }
        }

        // Count total ON bits in grid (excluding orientation marker).
        $on_count = 0;
        foreach ( $grid as $row ) {
            $on_count += array_sum( $row );
        }

        echo "  Serial 699050: binary={$binary}\n";
        echo "  Grid has {$on_count} ON bits (including anchors).\n";

        return true;
    },
    'Alternating bit pattern exercises all grid rows correctly.'
);

run_test(
    'TC-MID-006: Boundary value (01048574) parity flip',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Test 1048574 (one less than max) vs 1048575 (max).
        // They differ by 1 bit, so parity should flip.

        $binary1 = $encoder::encode_binary( 1048574 );
        $binary2 = $encoder::encode_binary( 1048575 );

        if ( is_wp_error( $binary1 ) || is_wp_error( $binary2 ) ) {
            return new WP_Error( 'encode_fail', 'Failed to encode boundary values.' );
        }

        $parity1 = $encoder::calculate_parity( $binary1 );
        $parity2 = $encoder::calculate_parity( $binary2 );

        // 1048575 is all 1s (20 bits), parity = 0.
        // 1048574 is 19 ones and 1 zero, parity = 1.
        if ( $parity2 !== 0 ) {
            return new WP_Error( 'parity_fail', "1048575 should have parity 0, got {$parity2}." );
        }
        if ( $parity1 !== 1 ) {
            return new WP_Error( 'parity_fail', "1048574 should have parity 1, got {$parity1}." );
        }

        echo "  Serial 1048574: parity={$parity1} (19 ones)\n";
        echo "  Serial 1048575: parity={$parity2} (20 ones)\n";
        echo "  Parity correctly flips at boundary.\n";

        return true;
    },
    'Boundary values demonstrate correct parity bit behavior.'
);

run_test(
    'TC-MID-007: Invalid input above maximum',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Test value above maximum.
        $result = $encoder::encode_binary( 1048576 );

        if ( ! is_wp_error( $result ) ) {
            return new WP_Error( 'validation_fail', '1048576 should return WP_Error.' );
        }

        if ( $result->get_error_code() !== 'serial_too_high' ) {
            return new WP_Error(
                'error_code_fail',
                "Expected error code 'serial_too_high', got '{$result->get_error_code()}'."
            );
        }

        echo "  Correctly rejected 1048576 with error: {$result->get_error_message()}\n";

        return true;
    },
    'Values above maximum return WP_Error with meaningful message.'
);

run_test(
    'TC-MID-008: Invalid input zero',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Test zero (below minimum of 1).
        $result = $encoder::encode_binary( 0 );

        if ( ! is_wp_error( $result ) ) {
            return new WP_Error( 'validation_fail', '0 should return WP_Error.' );
        }

        if ( $result->get_error_code() !== 'serial_too_low' ) {
            return new WP_Error(
                'error_code_fail',
                "Expected error code 'serial_too_low', got '{$result->get_error_code()}'."
            );
        }

        echo "  Correctly rejected 0 with error: {$result->get_error_message()}\n";

        return true;
    },
    'Zero value returns WP_Error (minimum is 1).'
);

run_test(
    'TC-MID-009: String validation',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Valid string format.
        $result = $encoder::validate_serial_string( '00123456' );
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'validation_fail', '00123456 should be valid.' );
        }

        // Invalid: wrong length.
        $result = $encoder::validate_serial_string( '123456' );
        if ( ! is_wp_error( $result ) ) {
            return new WP_Error( 'validation_fail', '123456 (6 chars) should be invalid.' );
        }
        if ( $result->get_error_code() !== 'invalid_length' ) {
            return new WP_Error( 'error_code_fail', "Expected 'invalid_length' error code." );
        }

        // Invalid: contains letters.
        $result = $encoder::validate_serial_string( '0012345A' );
        if ( ! is_wp_error( $result ) ) {
            return new WP_Error( 'validation_fail', '0012345A should be invalid.' );
        }
        if ( $result->get_error_code() !== 'invalid_characters' ) {
            return new WP_Error( 'error_code_fail', "Expected 'invalid_characters' error code." );
        }

        // Invalid: above max when converted.
        $result = $encoder::validate_serial_string( '99999999' );
        if ( ! is_wp_error( $result ) ) {
            return new WP_Error( 'validation_fail', '99999999 should be invalid (exceeds max).' );
        }

        echo "  String validation correctly handles format and range.\n";

        return true;
    },
    'String input validation catches format and range errors.'
);

run_test(
    'TC-MID-010: Grid coordinates mathematically correct',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Verify coordinate calculation formula: X = 0.05 + (col × 0.225), Y = 0.05 + (row × 0.225).

        // Top-left (0,0): X=0.05, Y=0.05.
        $coords = $encoder::get_grid_coordinates( 0, 0 );
        if ( abs( $coords['x'] - 0.05 ) > 0.0001 || abs( $coords['y'] - 0.05 ) > 0.0001 ) {
            return new WP_Error(
                'coord_fail',
                "Position (0,0) should be (0.05, 0.05), got ({$coords['x']}, {$coords['y']})."
            );
        }

        // Bottom-right (4,4): X = 0.05 + (4 × 0.225) = 0.95, Y = 0.95.
        $coords = $encoder::get_grid_coordinates( 4, 4 );
        if ( abs( $coords['x'] - 0.95 ) > 0.0001 || abs( $coords['y'] - 0.95 ) > 0.0001 ) {
            return new WP_Error(
                'coord_fail',
                "Position (4,4) should be (0.95, 0.95), got ({$coords['x']}, {$coords['y']})."
            );
        }

        // Center (2,2): X = 0.05 + (2 × 0.225) = 0.5, Y = 0.5.
        $coords = $encoder::get_grid_coordinates( 2, 2 );
        if ( abs( $coords['x'] - 0.5 ) > 0.0001 || abs( $coords['y'] - 0.5 ) > 0.0001 ) {
            return new WP_Error(
                'coord_fail',
                "Position (2,2) should be (0.5, 0.5), got ({$coords['x']}, {$coords['y']})."
            );
        }

        // Position (1,3): X = 0.05 + (3 × 0.225) = 0.725, Y = 0.05 + (1 × 0.225) = 0.275.
        $coords = $encoder::get_grid_coordinates( 1, 3 );
        if ( abs( $coords['x'] - 0.725 ) > 0.0001 || abs( $coords['y'] - 0.275 ) > 0.0001 ) {
            return new WP_Error(
                'coord_fail',
                "Position (1,3) should be (0.725, 0.275), got ({$coords['x']}, {$coords['y']})."
            );
        }

        echo "  Coordinate formula verified at corners and center.\n";

        return true;
    },
    'Grid coordinate calculations match specification formula.'
);

run_test(
    'TC-PAR-001: Even bit count produces parity 0',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Test cases with even number of 1s.
        $test_cases = array(
            '00000000000000000000' => 0, // 0 ones.
            '00000000000000000011' => 0, // 2 ones.
            '11111111111111111111' => 0, // 20 ones.
            '10101010101010101010' => 0, // 10 ones.
        );

        foreach ( $test_cases as $binary => $expected ) {
            $parity = $encoder::calculate_parity( $binary );
            if ( $parity !== $expected ) {
                $count = substr_count( $binary, '1' );
                return new WP_Error(
                    'parity_fail',
                    "Binary with {$count} ones should have parity {$expected}, got {$parity}."
                );
            }
        }

        echo "  Even bit counts correctly produce parity 0.\n";

        return true;
    },
    'Even number of 1-bits produces parity bit = 0.'
);

run_test(
    'TC-PAR-002: Odd bit count produces parity 1',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Test cases with odd number of 1s.
        $test_cases = array(
            '00000000000000000001' => 1, // 1 one.
            '00000000000000000111' => 1, // 3 ones.
            '11111111111111111110' => 1, // 19 ones.
            '10101010101010101011' => 1, // 11 ones.
        );

        foreach ( $test_cases as $binary => $expected ) {
            $parity = $encoder::calculate_parity( $binary );
            if ( $parity !== $expected ) {
                $count = substr_count( $binary, '1' );
                return new WP_Error(
                    'parity_fail',
                    "Binary with {$count} ones should have parity {$expected}, got {$parity}."
                );
            }
        }

        echo "  Odd bit counts correctly produce parity 1.\n";

        return true;
    },
    'Odd number of 1-bits produces parity bit = 1.'
);

run_test(
    'TC-MID-011: SVG rendering produces valid output',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Render SVG for a test serial.
        $svg = $encoder::render_svg( 123456, 'test-micro-id' );
        if ( is_wp_error( $svg ) ) {
            return $svg;
        }

        // Verify it's a string.
        if ( ! is_string( $svg ) ) {
            return new WP_Error( 'render_fail', 'render_svg() should return string.' );
        }

        // Verify it contains expected elements.
        if ( strpos( $svg, '<g id="test-micro-id">' ) === false ) {
            return new WP_Error( 'render_fail', 'SVG should contain group with ID.' );
        }

        if ( strpos( $svg, '<circle' ) === false ) {
            return new WP_Error( 'render_fail', 'SVG should contain circle elements.' );
        }

        if ( strpos( $svg, 'fill="#000000"' ) === false ) {
            return new WP_Error( 'render_fail', 'Circles should have black fill.' );
        }

        // Count circle elements.
        $circle_count = substr_count( $svg, '<circle' );
        $expected_dots = $encoder::count_dots( 123456 );
        if ( is_wp_error( $expected_dots ) ) {
            return $expected_dots;
        }

        if ( $circle_count !== $expected_dots ) {
            return new WP_Error(
                'render_fail',
                "Expected {$expected_dots} circles, found {$circle_count}."
            );
        }

        echo "  SVG rendering produces valid output with {$circle_count} circles.\n";

        return true;
    },
    'SVG rendering produces valid group with correct number of circles.'
);

run_test(
    'TC-MID-012: Encode-decode roundtrip',
    function (): bool {
        $encoder = \Quadica\QSA_Engraving\SVG\Micro_ID_Encoder::class;

        // Test roundtrip for several values.
        $test_values = array( 1, 123456, 600001, 1048575 );

        foreach ( $test_values as $original ) {
            // Encode to grid.
            $grid = $encoder::get_grid( $original );
            if ( is_wp_error( $grid ) ) {
                return $grid;
            }

            // Decode back.
            $decoded = $encoder::decode_grid( $grid );
            if ( is_wp_error( $decoded ) ) {
                return new WP_Error(
                    'decode_fail',
                    "Failed to decode grid for serial {$original}: {$decoded->get_error_message()}"
                );
            }

            if ( $decoded !== $original ) {
                return new WP_Error(
                    'roundtrip_fail',
                    "Roundtrip failed: encoded {$original}, decoded {$decoded}."
                );
            }
        }

        echo "  Roundtrip verified for: " . implode( ', ', $test_values ) . "\n";

        return true;
    },
    'Encoding then decoding produces original serial number.'
);

// ============================================
// PHASE 4: SVG Generation Core Tests
// ============================================

echo "-------------------------------------------\n";
echo "Phase 4: SVG Generation Core Tests\n";
echo "-------------------------------------------\n\n";

run_test(
    'TC-SVG-001: Coordinate Transformer CAD to SVG Y-axis',
    function (): bool {
        $transformer = new \Quadica\QSA_Engraving\SVG\Coordinate_Transformer();

        // Canvas height is 113.7mm.
        // CAD origin is bottom-left, SVG origin is top-left.
        // Formula: svg_y = canvas_height - cad_y.

        // Test bottom edge (CAD Y=0 -> SVG Y=113.7).
        $result = $transformer->cad_to_svg_y( 0.0 );
        if ( abs( $result - 113.7 ) > 0.001 ) {
            return new WP_Error( 'transform_fail', "cad_to_svg_y(0) should be 113.7, got {$result}." );
        }

        // Test top edge (CAD Y=113.7 -> SVG Y=0).
        $result = $transformer->cad_to_svg_y( 113.7 );
        if ( abs( $result - 0.0 ) > 0.001 ) {
            return new WP_Error( 'transform_fail', "cad_to_svg_y(113.7) should be 0, got {$result}." );
        }

        // Test middle (CAD Y=50 -> SVG Y=63.7).
        $result = $transformer->cad_to_svg_y( 50.0 );
        if ( abs( $result - 63.7 ) > 0.001 ) {
            return new WP_Error( 'transform_fail', "cad_to_svg_y(50) should be 63.7, got {$result}." );
        }

        // Test inverse transformation.
        $svg_y = 63.7;
        $cad_y = $transformer->svg_to_cad_y( $svg_y );
        if ( abs( $cad_y - 50.0 ) > 0.001 ) {
            return new WP_Error( 'transform_fail', "svg_to_cad_y(63.7) should be 50, got {$cad_y}." );
        }

        echo "  CAD to SVG Y-axis transformation verified.\n";

        return true;
    },
    'CAD to SVG Y-axis transformation works correctly.'
);

run_test(
    'TC-SVG-002: Coordinate Transformer with calibration',
    function (): bool {
        $transformer = new \Quadica\QSA_Engraving\SVG\Coordinate_Transformer();

        // Set calibration offsets.
        $transformer->set_calibration( 0.5, -0.25 );

        // X should be offset by 0.5mm.
        $x = $transformer->cad_to_svg_x( 10.0 );
        if ( abs( $x - 10.5 ) > 0.001 ) {
            return new WP_Error( 'calibration_fail', "X with 0.5 offset should be 10.5, got {$x}." );
        }

        // Y should be offset by -0.25mm after transformation.
        $y = $transformer->cad_to_svg_y( 50.0 );
        // 113.7 - 50 + (-0.25) = 63.45.
        if ( abs( $y - 63.45 ) > 0.001 ) {
            return new WP_Error( 'calibration_fail', "Y with -0.25 offset should be 63.45, got {$y}." );
        }

        // Get calibration.
        $calibration = $transformer->get_calibration();
        if ( abs( $calibration['x'] - 0.5 ) > 0.001 || abs( $calibration['y'] - (-0.25) ) > 0.001 ) {
            return new WP_Error( 'calibration_fail', 'get_calibration() returned wrong values.' );
        }

        echo "  Calibration offsets applied correctly.\n";

        return true;
    },
    'Calibration offsets are applied during transformation.'
);

run_test(
    'TC-SVG-003: Coordinate Transformer bounds checking',
    function (): bool {
        $transformer = new \Quadica\QSA_Engraving\SVG\Coordinate_Transformer();

        // Test is_within_bounds.
        if ( ! $transformer->is_within_bounds( 74.0, 56.85 ) ) {
            return new WP_Error( 'bounds_fail', 'Center point should be within bounds.' );
        }

        if ( $transformer->is_within_bounds( -1.0, 50.0 ) ) {
            return new WP_Error( 'bounds_fail', 'Negative X should be out of bounds.' );
        }

        if ( $transformer->is_within_bounds( 50.0, 150.0 ) ) {
            return new WP_Error( 'bounds_fail', 'Y > 113.7 should be out of bounds.' );
        }

        // Test clamp_to_bounds.
        $clamped = $transformer->clamp_to_bounds( -5.0, 200.0 );
        if ( $clamped['x'] !== 0.0 || abs( $clamped['y'] - 113.7 ) > 0.001 ) {
            return new WP_Error( 'clamp_fail', 'clamp_to_bounds() should clamp to canvas edges.' );
        }

        echo "  Bounds checking and clamping verified.\n";

        return true;
    },
    'Coordinate bounds checking works correctly.'
);

run_test(
    'TC-SVG-004: Text Renderer character spacing',
    function (): bool {
        $renderer = \Quadica\QSA_Engraving\SVG\Text_Renderer::class;

        // Test character spacing.
        $spaced = $renderer::add_character_spacing( 'ABC' );
        $expected = "A\u{200A}B\u{200A}C";
        if ( $spaced !== $expected ) {
            return new WP_Error( 'spacing_fail', "Expected hair-spaced text, got: '{$spaced}'." );
        }

        // Test with single character.
        $single = $renderer::add_character_spacing( 'X' );
        if ( $single !== 'X' ) {
            return new WP_Error( 'spacing_fail', 'Single character should not have spaces.' );
        }

        echo "  Character spacing with hair-space U+200A verified.\n";

        return true;
    },
    'Text renderer adds hair-space between characters.'
);

run_test(
    'TC-SVG-005: Text Renderer font size calculation',
    function (): bool {
        $renderer = \Quadica\QSA_Engraving\SVG\Text_Renderer::class;

        // Font size = height × 1.4056.
        $font_size = $renderer::calculate_font_size( 1.5 );
        $expected = 1.5 * 1.4056;
        if ( abs( $font_size - $expected ) > 0.001 ) {
            return new WP_Error( 'fontsize_fail', "Expected {$expected}, got {$font_size}." );
        }

        // Verify default heights.
        $heights = $renderer::DEFAULT_HEIGHTS;
        if ( $heights['module_id'] !== 1.5 ) {
            return new WP_Error( 'height_fail', 'module_id height should be 1.5mm.' );
        }
        if ( $heights['serial_url'] !== 1.2 ) {
            return new WP_Error( 'height_fail', 'serial_url height should be 1.2mm.' );
        }
        if ( $heights['led_code'] !== 1.0 ) {
            return new WP_Error( 'height_fail', 'led_code height should be 1.0mm.' );
        }

        echo "  Font size calculation verified (height × 1.4056).\n";

        return true;
    },
    'Font size calculated correctly from text height.'
);

run_test(
    'TC-SVG-006: Text Renderer SVG output',
    function (): bool {
        $renderer = \Quadica\QSA_Engraving\SVG\Text_Renderer::class;

        // Render text element.
        $svg = $renderer::render( 'TEST', 50.0, 60.0, 1.5, 'middle', 0, 'test-text' );

        // Verify structure.
        if ( strpos( $svg, '<text' ) === false ) {
            return new WP_Error( 'render_fail', 'Should contain <text element.' );
        }

        if ( strpos( $svg, 'font-family="Roboto Thin, sans-serif"' ) === false ) {
            return new WP_Error( 'render_fail', 'Should have Roboto Thin font family.' );
        }

        if ( strpos( $svg, 'text-anchor="middle"' ) === false ) {
            return new WP_Error( 'render_fail', 'Should have middle text anchor.' );
        }

        if ( strpos( $svg, 'id="test-text"' ) === false ) {
            return new WP_Error( 'render_fail', 'Should have ID attribute.' );
        }

        // Check for hair-spaced text.
        if ( strpos( $svg, "T\u{200A}E\u{200A}S\u{200A}T" ) === false ) {
            return new WP_Error( 'render_fail', 'Text should be hair-spaced.' );
        }

        echo "  Text SVG rendering produces valid output.\n";

        return true;
    },
    'Text renderer generates valid SVG text element.'
);

run_test(
    'TC-SVG-007: Text Renderer with rotation',
    function (): bool {
        $renderer = \Quadica\QSA_Engraving\SVG\Text_Renderer::class;

        // Render rotated text.
        $svg = $renderer::render( 'ROT', 50.0, 60.0, 1.0, 'middle', 90 );

        // Verify rotation transform.
        if ( strpos( $svg, 'transform="rotate(90' ) === false ) {
            return new WP_Error( 'rotation_fail', 'Should contain rotate(90 transform.' );
        }

        // Verify rotation center matches text position.
        if ( strpos( $svg, 'rotate(90 50.0000 60.0000)' ) === false ) {
            return new WP_Error( 'rotation_fail', 'Rotation center should match text position.' );
        }

        echo "  Text rotation transform verified.\n";

        return true;
    },
    'Text renderer applies rotation transform correctly.'
);

run_test(
    'TC-SVG-008: LED code validation',
    function (): bool {
        $renderer = \Quadica\QSA_Engraving\SVG\Text_Renderer::class;

        // Valid LED codes (3 chars from: 1234789CEFHJKLPRT).
        $valid_codes = array( 'K7P', '4T9', 'GF4', 'AF3', '34T', 'C1E', 'HJL' );
        foreach ( $valid_codes as $code ) {
            if ( ! $renderer::validate_led_code( $code ) ) {
                return new WP_Error( 'validation_fail', "'{$code}' should be valid LED code." );
            }
        }

        // Invalid: wrong characters.
        if ( $renderer::validate_led_code( 'ABC' ) ) {
            return new WP_Error( 'validation_fail', "'ABC' should be invalid (A,B not allowed)." );
        }

        // Invalid: wrong length.
        if ( $renderer::validate_led_code( 'K7' ) ) {
            return new WP_Error( 'validation_fail', "'K7' should be invalid (2 chars)." );
        }

        if ( $renderer::validate_led_code( 'K7P9' ) ) {
            return new WP_Error( 'validation_fail', "'K7P9' should be invalid (4 chars)." );
        }

        // Get charset.
        $charset = $renderer::get_led_code_charset();
        if ( $charset !== '1234789CEFHJKLPRT' ) {
            return new WP_Error( 'charset_fail', 'LED code charset incorrect.' );
        }

        echo "  LED code validation verified (17-char set, 3-char codes).\n";

        return true;
    },
    'LED code validation enforces character set and length.'
);

run_test(
    'TC-DM-001: Data Matrix renderer availability check',
    function (): bool {
        $renderer = \Quadica\QSA_Engraving\SVG\Datamatrix_Renderer::class;

        // Check library status.
        $status = $renderer::get_library_status();

        if ( ! is_array( $status ) ) {
            return new WP_Error( 'status_fail', 'get_library_status() should return array.' );
        }

        if ( ! array_key_exists( 'available', $status ) ) {
            return new WP_Error( 'status_fail', 'Status should have available key.' );
        }

        if ( $status['available'] ) {
            echo "  tc-lib-barcode library is available.\n";
        } else {
            echo "  tc-lib-barcode not installed - using placeholder mode.\n";
            echo "  Run: composer install in plugin directory.\n";
        }

        return true;
    },
    'Data Matrix renderer checks library availability.'
);

run_test(
    'TC-DM-002: Data Matrix placeholder rendering',
    function (): bool {
        $renderer = \Quadica\QSA_Engraving\SVG\Datamatrix_Renderer::class;

        // Render Data Matrix (will use placeholder if library not available).
        $svg = $renderer::render( '00123456' );
        if ( is_wp_error( $svg ) ) {
            return $svg;
        }

        // Should return string.
        if ( ! is_string( $svg ) ) {
            return new WP_Error( 'render_fail', 'render() should return string.' );
        }

        // Should contain group element.
        if ( strpos( $svg, '<g>' ) === false ) {
            return new WP_Error( 'render_fail', 'Should contain group element.' );
        }

        // Test positioned rendering.
        $positioned = $renderer::render_positioned( '00123456', 10.0, 20.0, 14.0, 6.5, 'dm-1' );
        if ( is_wp_error( $positioned ) ) {
            return $positioned;
        }

        if ( strpos( $positioned, 'id="dm-1"' ) === false ) {
            return new WP_Error( 'render_fail', 'Positioned should have ID.' );
        }

        if ( strpos( $positioned, 'translate(10.0000, 20.0000)' ) === false ) {
            return new WP_Error( 'render_fail', 'Positioned should have translate transform.' );
        }

        echo "  Data Matrix rendering produces valid output.\n";

        return true;
    },
    'Data Matrix renderer generates SVG output.'
);

run_test(
    'TC-DM-003: Data Matrix serial validation',
    function (): bool {
        $renderer = \Quadica\QSA_Engraving\SVG\Datamatrix_Renderer::class;

        // Valid serial.
        $result = $renderer::validate_serial( '00123456' );
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'validation_fail', '00123456 should be valid.' );
        }

        // Invalid: wrong length.
        $result = $renderer::validate_serial( '123456' );
        if ( ! is_wp_error( $result ) ) {
            return new WP_Error( 'validation_fail', '123456 (6 chars) should be invalid.' );
        }

        // Invalid: contains letters.
        $result = $renderer::validate_serial( '0012345A' );
        if ( ! is_wp_error( $result ) ) {
            return new WP_Error( 'validation_fail', '0012345A should be invalid.' );
        }

        // Get URL.
        $url = $renderer::get_url( '00123456' );
        if ( $url !== 'https://quadi.ca/00123456' ) {
            return new WP_Error( 'url_fail', "Expected 'https://quadi.ca/00123456', got '{$url}'." );
        }

        echo "  Data Matrix serial validation and URL generation verified.\n";

        return true;
    },
    'Data Matrix validates serial format and generates URLs.'
);

run_test(
    'TC-SVG-GEN-001: SVG Document structure',
    function (): bool {
        $doc = new \Quadica\QSA_Engraving\SVG\SVG_Document();

        // Set options.
        $doc->set_title( 'Test Document' );
        $doc->set_include_boundary( true );
        $doc->set_include_crosshair( true );

        // Render empty document.
        $svg = $doc->render();
        if ( is_wp_error( $svg ) ) {
            return $svg;
        }

        // Check XML declaration.
        if ( strpos( $svg, '<?xml version="1.0" encoding="UTF-8"?>' ) === false ) {
            return new WP_Error( 'structure_fail', 'Should have XML declaration.' );
        }

        // Check SVG element with dimensions.
        if ( strpos( $svg, 'width="148.0mm"' ) === false ) {
            return new WP_Error( 'structure_fail', 'Should have width="148.0mm".' );
        }

        if ( strpos( $svg, 'height="113.7mm"' ) === false ) {
            return new WP_Error( 'structure_fail', 'Should have height="113.7mm".' );
        }

        if ( strpos( $svg, 'viewBox="0 0 148.0 113.7"' ) === false ) {
            return new WP_Error( 'structure_fail', 'Should have correct viewBox.' );
        }

        // Check namespace.
        if ( strpos( $svg, 'xmlns="http://www.w3.org/2000/svg"' ) === false ) {
            return new WP_Error( 'structure_fail', 'Should have SVG namespace.' );
        }

        // Check title comment.
        if ( strpos( $svg, '<!-- Test Document -->' ) === false ) {
            return new WP_Error( 'structure_fail', 'Should have title comment.' );
        }

        // Check boundary rectangle.
        if ( strpos( $svg, 'stroke="#FF0000"' ) === false ) {
            return new WP_Error( 'structure_fail', 'Should have red boundary rectangle.' );
        }

        echo "  SVG document structure verified.\n";

        return true;
    },
    'SVG Document generates valid structure with mm units.'
);

run_test(
    'TC-SVG-GEN-002: SVG Document module count',
    function (): bool {
        $doc = new \Quadica\QSA_Engraving\SVG\SVG_Document();

        // Verify initial count.
        if ( $doc->get_module_count() !== 0 ) {
            return new WP_Error( 'count_fail', 'Initial count should be 0.' );
        }

        // Add a module (with minimal config).
        $module_data = array(
            'serial_number' => '00123456',
            'module_id'     => 'STAR-38546',
            'led_codes'     => array( 'GF4' ),
        );

        $config = array(
            'micro_id' => array( 'origin_x' => 32.0, 'origin_y' => 63.7, 'rotation' => 0 ),
        );

        $result = $doc->add_module( 1, $module_data, $config );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( $doc->get_module_count() !== 1 ) {
            return new WP_Error( 'count_fail', 'Count should be 1 after adding module.' );
        }

        // Clear modules.
        $doc->clear_modules();
        if ( $doc->get_module_count() !== 0 ) {
            return new WP_Error( 'count_fail', 'Count should be 0 after clear.' );
        }

        echo "  Module counting and clearing verified.\n";

        return true;
    },
    'SVG Document tracks module count correctly.'
);

run_test(
    'TC-SVG-GEN-003: Config Loader SKU parsing',
    function (): bool {
        $loader = new \Quadica\QSA_Engraving\Services\Config_Loader();

        // Valid SKU with revision.
        $result = $loader->parse_sku( 'STARa-38546' );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( $result['design'] !== 'STAR' ) {
            return new WP_Error( 'parse_fail', "Expected design 'STAR', got '{$result['design']}'." );
        }

        if ( $result['revision'] !== 'a' ) {
            return new WP_Error( 'parse_fail', "Expected revision 'a', got '{$result['revision']}'." );
        }

        if ( $result['config'] !== '38546' ) {
            return new WP_Error( 'parse_fail', "Expected config '38546', got '{$result['config']}'." );
        }

        // Valid SKU without revision.
        $result = $loader->parse_sku( 'CORE-91247' );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( $result['design'] !== 'CORE' ) {
            return new WP_Error( 'parse_fail', "Expected design 'CORE', got '{$result['design']}'." );
        }

        if ( $result['revision'] !== null ) {
            return new WP_Error( 'parse_fail', 'Expected null revision for CORE-91247.' );
        }

        // Invalid SKU.
        $result = $loader->parse_sku( 'SP-01-WW' );
        if ( ! is_wp_error( $result ) ) {
            return new WP_Error( 'parse_fail', 'SP-01-WW should be invalid.' );
        }

        echo "  SKU parsing for QSA designs verified.\n";

        return true;
    },
    'Config Loader parses QSA SKU format correctly.'
);

run_test(
    'TC-SVG-GEN-004: SVG Generator dependencies check',
    function (): bool {
        $generator = new \Quadica\QSA_Engraving\Services\SVG_Generator();

        $deps = $generator->check_dependencies();

        if ( ! is_array( $deps ) ) {
            return new WP_Error( 'deps_fail', 'check_dependencies() should return array.' );
        }

        if ( ! array_key_exists( 'ready', $deps ) || ! array_key_exists( 'issues', $deps ) ) {
            return new WP_Error( 'deps_fail', 'Should have ready and issues keys.' );
        }

        if ( $deps['ready'] ) {
            echo "  All dependencies available.\n";
        } else {
            echo "  Issues: " . implode( '; ', $deps['issues'] ) . "\n";
        }

        return true;
    },
    'SVG Generator checks dependencies correctly.'
);

run_test(
    'TC-SVG-GEN-005: SVG Generator array breakdown',
    function (): bool {
        $generator = new \Quadica\QSA_Engraving\Services\SVG_Generator();

        // 8 modules starting at position 1 = 1 array.
        $breakdown = $generator->calculate_array_breakdown( 8, 1 );
        if ( $breakdown['array_count'] !== 1 ) {
            return new WP_Error( 'breakdown_fail', '8 modules from pos 1 should be 1 array.' );
        }

        // 9 modules starting at position 1 = 2 arrays.
        $breakdown = $generator->calculate_array_breakdown( 9, 1 );
        if ( $breakdown['array_count'] !== 2 ) {
            return new WP_Error( 'breakdown_fail', '9 modules from pos 1 should be 2 arrays.' );
        }

        // 8 modules starting at position 3 = 2 arrays (6 + 2).
        $breakdown = $generator->calculate_array_breakdown( 8, 3 );
        if ( $breakdown['array_count'] !== 2 ) {
            return new WP_Error( 'breakdown_fail', '8 modules from pos 3 should be 2 arrays.' );
        }
        if ( $breakdown['last_array_count'] !== 2 ) {
            return new WP_Error( 'breakdown_fail', 'Last array should have 2 modules.' );
        }

        // 19 modules starting at position 1 = 3 arrays (8 + 8 + 3).
        $breakdown = $generator->calculate_array_breakdown( 19, 1 );
        if ( $breakdown['array_count'] !== 3 ) {
            return new WP_Error( 'breakdown_fail', '19 modules from pos 1 should be 3 arrays.' );
        }
        if ( $breakdown['last_array_count'] !== 3 ) {
            return new WP_Error( 'breakdown_fail', 'Last array should have 3 modules.' );
        }

        echo "  Array breakdown calculations verified.\n";

        return true;
    },
    'SVG Generator calculates array breakdown correctly.'
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
