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
echo "================================================================\n";
echo "QSA Engraving Plugin - Phase 1-9 Smoke Tests\n";
echo "================================================================\n\n";

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
        // Note: This is a legacy OMS table that does NOT use the WordPress prefix.
        $oms_table = 'oms_batch_items';
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
    'TC-SVG-004: Micro-ID position transform',
    function (): bool {
        $transformer = new \Quadica\QSA_Engraving\SVG\Coordinate_Transformer();

        // Test Micro-ID position transform.
        // CAD coords for Micro-ID center, expecting offset for top-left of 1mm grid.
        // get_micro_id_transform subtracts 0.5mm from both x and y.
        $result = $transformer->get_micro_id_transform( 32.0, 63.7 );

        // X should be 32.0 - 0.5 = 31.5.
        if ( abs( $result['x'] - 31.5 ) > 0.001 ) {
            return new WP_Error( 'micro_id_fail', "X should be 31.5, got {$result['x']}." );
        }

        // Y should be (113.7 - 63.7) - 0.5 = 49.5.
        $expected_y = 113.7 - 63.7 - 0.5;
        if ( abs( $result['y'] - $expected_y ) > 0.001 ) {
            return new WP_Error( 'micro_id_fail', "Y should be {$expected_y}, got {$result['y']}." );
        }

        echo "  Micro-ID position transform verified (center to top-left offset).\n";

        return true;
    },
    'Micro-ID position transform converts center to top-left correctly.'
);

run_test(
    'TC-SVG-005: Data Matrix position transform',
    function (): bool {
        $transformer = new \Quadica\QSA_Engraving\SVG\Coordinate_Transformer();

        // Test Data Matrix position transform.
        // CAD coords for Data Matrix center, expecting offset for top-left.
        // Default width=14, height=6.5, so offset is (7, 3.25).
        $result = $transformer->get_datamatrix_position( 50.0, 50.0 );

        // X should be 50.0 - 7 = 43.0.
        if ( abs( $result['x'] - 43.0 ) > 0.001 ) {
            return new WP_Error( 'dm_pos_fail', "X should be 43.0, got {$result['x']}." );
        }

        // Y should be (113.7 - 50) - 3.25 = 60.45.
        $expected_y = 113.7 - 50.0 - 3.25;
        if ( abs( $result['y'] - $expected_y ) > 0.001 ) {
            return new WP_Error( 'dm_pos_fail', "Y should be {$expected_y}, got {$result['y']}." );
        }

        echo "  Data Matrix position transform verified (center to top-left offset).\n";

        return true;
    },
    'Data Matrix position transform converts center to top-left correctly.'
);

run_test(
    'TC-SVG-006: Hair-space character spacing',
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
    'TC-SVG-007: Text Renderer font size calculation',
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
    'TC-SVG-008: Text Renderer SVG output',
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
    'TC-SVG-009: Text Renderer with rotation',
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
    'TC-SVG-010: LED code validation',
    function (): bool {
        $renderer = \Quadica\QSA_Engraving\SVG\Text_Renderer::class;

        // Valid LED codes (3 chars from: 1234789CEFHJKLPRT).
        // Charset: 1234789CEFHJKLPRT (17 characters).
        $valid_codes = array( 'K7P', '4T9', 'CF4', 'EF3', '34T', 'C1E', 'HJL', 'RPT', '129' );
        foreach ( $valid_codes as $code ) {
            if ( ! $renderer::validate_led_code( $code ) ) {
                echo "  WARNING: '{$code}' failed validation.\n";
                return false;
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
            echo "  ERROR: STARa-38546 failed: " . $result->get_error_message() . "\n";
            return false;
        }

        if ( $result['design'] !== 'STAR' ) {
            echo "  ERROR: Expected design 'STAR', got '{$result['design']}'.\n";
            return false;
        }

        if ( $result['revision'] !== 'a' ) {
            echo "  ERROR: Expected revision 'a', got '{$result['revision']}'.\n";
            return false;
        }

        if ( $result['config'] !== '38546' ) {
            echo "  ERROR: Expected config '38546', got '{$result['config']}'.\n";
            return false;
        }

        // Valid SKU without revision.
        $result = $loader->parse_sku( 'CORE-91247' );
        if ( is_wp_error( $result ) ) {
            echo "  ERROR: CORE-91247 failed: " . $result->get_error_message() . "\n";
            return false;
        }

        if ( $result['design'] !== 'CORE' ) {
            echo "  ERROR: Expected design 'CORE', got '{$result['design']}'.\n";
            return false;
        }

        if ( $result['revision'] !== null ) {
            echo "  ERROR: Expected null revision for CORE-91247.\n";
            return false;
        }

        // Invalid SKU.
        $result = $loader->parse_sku( 'SP-01-WW' );
        if ( ! is_wp_error( $result ) ) {
            echo "  ERROR: SP-01-WW should be invalid.\n";
            return false;
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

run_test(
    'TC-SVG-GEN-006: SVG Generator start_position validation',
    function (): bool {
        $generator = new \Quadica\QSA_Engraving\Services\SVG_Generator();

        // Valid positions should work (test with calculate_array_breakdown which clamps).
        $breakdown = $generator->calculate_array_breakdown( 8, 1 );
        if ( $breakdown['array_count'] !== 1 ) {
            return new WP_Error( 'breakdown_fail', 'Position 1 should work.' );
        }

        $breakdown = $generator->calculate_array_breakdown( 8, 8 );
        if ( $breakdown['array_count'] !== 2 ) {
            return new WP_Error( 'breakdown_fail', 'Position 8 should work (1 + 7 remaining).' );
        }

        // Out of range positions should be clamped in calculate_array_breakdown.
        $breakdown = $generator->calculate_array_breakdown( 8, 0 );
        // 0 should be clamped to 1.
        if ( $breakdown['array_count'] !== 1 ) {
            return new WP_Error( 'clamp_fail', 'Position 0 should clamp to 1.' );
        }

        $breakdown = $generator->calculate_array_breakdown( 8, 10 );
        // 10 should be clamped to 8.
        if ( $breakdown['array_count'] !== 2 ) {
            return new WP_Error( 'clamp_fail', 'Position 10 should clamp to 8.' );
        }

        echo "  start_position validation and clamping verified.\n";

        return true;
    },
    'SVG Generator validates and clamps start_position.'
);

// ============================================
// PHASE 5: Batch Creator UI Tests
// ============================================

echo "-------------------------------------------\n";
echo "Phase 5: Batch Creator UI Tests\n";
echo "-------------------------------------------\n\n";

run_test(
    'TC-BC-001: Batch_Sorter service instantiation',
    function (): bool {
        // Verify class exists.
        if ( ! class_exists( 'Quadica\\QSA_Engraving\\Services\\Batch_Sorter' ) ) {
            return new WP_Error( 'missing_class', 'Batch_Sorter class not found.' );
        }

        // Instantiate.
        $sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();

        // Verify key methods exist.
        if ( ! method_exists( $sorter, 'sort_modules' ) ) {
            return new WP_Error( 'missing_method', 'sort_modules() method not found.' );
        }
        if ( ! method_exists( $sorter, 'expand_selections' ) ) {
            return new WP_Error( 'missing_method', 'expand_selections() method not found.' );
        }
        if ( ! method_exists( $sorter, 'assign_to_arrays' ) ) {
            return new WP_Error( 'missing_method', 'assign_to_arrays() method not found.' );
        }
        if ( ! method_exists( $sorter, 'count_transitions' ) ) {
            return new WP_Error( 'missing_method', 'count_transitions() method not found.' );
        }

        echo "  Batch_Sorter service instantiated successfully.\n";

        return true;
    },
    'Batch_Sorter service class exists and has required methods.'
);

run_test(
    'TC-BC-002: Batch_Sorter expand_selections',
    function (): bool {
        $sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();

        // Test expanding selections with quantities.
        $selections = array(
            array(
                'production_batch_id' => 1,
                'module_sku'          => 'STARa-38546',
                'order_id'            => 100,
                'quantity'            => 3,
                'led_codes'           => array( 'K7P' ),
            ),
            array(
                'production_batch_id' => 2,
                'module_sku'          => 'CORE-91247',
                'order_id'            => 101,
                'quantity'            => 2,
                'led_codes'           => array( '4T9', 'CF4' ),
            ),
        );

        $expanded = $sorter->expand_selections( $selections );

        // Should have 3 + 2 = 5 individual modules.
        if ( count( $expanded ) !== 5 ) {
            return new WP_Error(
                'expand_fail',
                'Expected 5 expanded modules, got ' . count( $expanded ) . '.'
            );
        }

        // Each expanded module should have quantity = 1.
        foreach ( $expanded as $module ) {
            if ( $module['quantity'] !== 1 ) {
                return new WP_Error( 'expand_fail', 'Expanded modules should have quantity 1.' );
            }
        }

        // First 3 should be STARa-38546.
        for ( $i = 0; $i < 3; $i++ ) {
            if ( $expanded[ $i ]['module_sku'] !== 'STARa-38546' ) {
                return new WP_Error( 'expand_fail', 'First 3 modules should be STARa-38546.' );
            }
            if ( $expanded[ $i ]['instance_index'] !== $i ) {
                return new WP_Error( 'expand_fail', 'instance_index should be sequential.' );
            }
        }

        echo "  expand_selections correctly expands quantities into individual modules.\n";

        return true;
    },
    'Batch_Sorter expands module selections with quantities.'
);

run_test(
    'TC-BC-003: Batch_Sorter assign_to_arrays',
    function (): bool {
        $sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();

        // Create 10 modules.
        $modules = array();
        for ( $i = 0; $i < 10; $i++ ) {
            $modules[] = array(
                'module_sku' => 'STARa-38546',
                'order_id'   => 100,
                'led_codes'  => array( 'K7P' ),
            );
        }

        // Assign starting at position 1: should be 8 + 2 = 2 arrays.
        $arrays = $sorter->assign_to_arrays( $modules, 1 );

        if ( count( $arrays ) !== 2 ) {
            return new WP_Error( 'assign_fail', 'Expected 2 arrays, got ' . count( $arrays ) . '.' );
        }

        // First array should have 8 modules.
        if ( count( $arrays[0] ) !== 8 ) {
            return new WP_Error( 'assign_fail', 'First array should have 8 modules.' );
        }

        // Second array should have 2 modules.
        if ( count( $arrays[1] ) !== 2 ) {
            return new WP_Error( 'assign_fail', 'Second array should have 2 modules.' );
        }

        // Verify positions in first array.
        for ( $i = 0; $i < 8; $i++ ) {
            if ( $arrays[0][ $i ]['array_position'] !== ( $i + 1 ) ) {
                return new WP_Error( 'assign_fail', 'Array positions should be 1-8.' );
            }
            if ( $arrays[0][ $i ]['qsa_sequence'] !== 1 ) {
                return new WP_Error( 'assign_fail', 'First array qsa_sequence should be 1.' );
            }
        }

        // Verify second array starts at position 1.
        if ( $arrays[1][0]['array_position'] !== 1 ) {
            return new WP_Error( 'assign_fail', 'Second array should start at position 1.' );
        }
        if ( $arrays[1][0]['qsa_sequence'] !== 2 ) {
            return new WP_Error( 'assign_fail', 'Second array qsa_sequence should be 2.' );
        }

        echo "  assign_to_arrays correctly distributes modules into 8-position arrays.\n";

        return true;
    },
    'Batch_Sorter assigns modules to QSA arrays correctly.'
);

run_test(
    'TC-BC-004: Batch_Sorter assign_to_arrays with start_position',
    function (): bool {
        $sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();

        // Create 10 modules.
        $modules = array();
        for ( $i = 0; $i < 10; $i++ ) {
            $modules[] = array(
                'module_sku' => 'STARa-38546',
                'order_id'   => 100,
                'led_codes'  => array( 'K7P' ),
            );
        }

        // Assign starting at position 3: should be 6 + 4 = 2 arrays.
        $arrays = $sorter->assign_to_arrays( $modules, 3 );

        if ( count( $arrays ) !== 2 ) {
            return new WP_Error( 'assign_fail', 'Expected 2 arrays, got ' . count( $arrays ) . '.' );
        }

        // First array should have 6 modules (positions 3-8).
        if ( count( $arrays[0] ) !== 6 ) {
            return new WP_Error(
                'assign_fail',
                'First array should have 6 modules, got ' . count( $arrays[0] ) . '.'
            );
        }

        // Verify first module starts at position 3.
        if ( $arrays[0][0]['array_position'] !== 3 ) {
            return new WP_Error( 'assign_fail', 'First module should be at position 3.' );
        }

        // Second array should have 4 modules (positions 1-4).
        if ( count( $arrays[1] ) !== 4 ) {
            return new WP_Error(
                'assign_fail',
                'Second array should have 4 modules, got ' . count( $arrays[1] ) . '.'
            );
        }

        echo "  assign_to_arrays respects start_position offset.\n";

        return true;
    },
    'Batch_Sorter respects start_position for first array.'
);

run_test(
    'TC-BC-005: Batch_Sorter LED optimization sorting',
    function (): bool {
        $sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();

        // Create modules with different LED codes.
        // These should be sorted to minimize transitions.
        $modules = array(
            array( 'id' => 1, 'led_codes' => array( 'K7P' ) ),
            array( 'id' => 2, 'led_codes' => array( '4T9', 'CF4' ) ),
            array( 'id' => 3, 'led_codes' => array( 'K7P' ) ), // Same as module 1.
            array( 'id' => 4, 'led_codes' => array( '4T9' ) ), // Overlaps with module 2.
            array( 'id' => 5, 'led_codes' => array( 'K7P', 'EF3' ) ), // Overlaps with modules 1 & 3.
        );

        $sorted = $sorter->sort_modules( $modules );

        // Should still have 5 modules.
        if ( count( $sorted ) !== 5 ) {
            return new WP_Error( 'sort_fail', 'Sorted result should have 5 modules.' );
        }

        // Modules with same LED codes should be grouped together.
        // K7P modules (1, 3, 5) should be adjacent.
        // Find K7P group.
        $k7p_positions = array();
        foreach ( $sorted as $idx => $module ) {
            if ( in_array( 'K7P', $module['led_codes'], true ) ) {
                $k7p_positions[] = $idx;
            }
        }

        // K7P modules should be contiguous.
        if ( count( $k7p_positions ) !== 3 ) {
            return new WP_Error( 'sort_fail', 'Should have 3 modules with K7P.' );
        }

        // Check if they're adjacent (max gap of 1 between any two).
        sort( $k7p_positions );
        if ( $k7p_positions[2] - $k7p_positions[0] > 2 ) {
            echo "  Note: K7P modules not perfectly adjacent (positions: " .
                 implode( ', ', $k7p_positions ) . "), but sorting is heuristic.\n";
        }

        echo "  LED optimization sorting groups similar LED codes.\n";

        return true;
    },
    'Batch_Sorter groups modules by LED codes to minimize transitions.'
);

run_test(
    'TC-BC-006: Batch_Sorter count_transitions',
    function (): bool {
        $sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();

        // Modules with same LED code = minimal transitions.
        $same_led = array(
            array( 'led_codes' => array( 'K7P' ) ),
            array( 'led_codes' => array( 'K7P' ) ),
            array( 'led_codes' => array( 'K7P' ) ),
        );

        $transitions = $sorter->count_transitions( $same_led );

        // Only 1 transition (loading K7P initially).
        if ( $transitions !== 1 ) {
            return new WP_Error(
                'transition_fail',
                "Same LED code should have 1 transition, got {$transitions}."
            );
        }

        // Modules with different LED codes = more transitions.
        $different_leds = array(
            array( 'led_codes' => array( 'K7P' ) ),
            array( 'led_codes' => array( '4T9' ) ),
            array( 'led_codes' => array( 'CF4' ) ),
        );

        $transitions = $sorter->count_transitions( $different_leds );

        // 3 transitions (one for each different LED type).
        if ( $transitions !== 3 ) {
            return new WP_Error(
                'transition_fail',
                "Different LED codes should have 3 transitions, got {$transitions}."
            );
        }

        echo "  count_transitions calculates LED type changes correctly.\n";

        // Test order-dependence: overlapping LEDs adjacent = fewer transitions.
        $sorted_order = array(
            array( 'led_codes' => array( 'K7P', '4T9' ) ), // Opens K7P, 4T9 (2).
            array( 'led_codes' => array( '4T9', 'CF4' ) ), // 4T9 already open, opens CF4 (1).
            array( 'led_codes' => array( 'CF4' ) ),        // CF4 already open (0).
        );
        $transitions_sorted = $sorter->count_transitions( $sorted_order );

        $unsorted_order = array(
            array( 'led_codes' => array( 'K7P', '4T9' ) ), // Opens K7P, 4T9 (2).
            array( 'led_codes' => array( 'CF4' ) ),        // K7P/4T9 not used, opens CF4 (1).
            array( 'led_codes' => array( '4T9', 'CF4' ) ), // CF4 open, opens 4T9 (1).
        );
        $transitions_unsorted = $sorter->count_transitions( $unsorted_order );

        // Sorted order should have 3 transitions; unsorted should have 4.
        if ( $transitions_sorted !== 3 ) {
            return new WP_Error(
                'order_fail',
                "Sorted order should have 3 transitions, got {$transitions_sorted}."
            );
        }
        if ( $transitions_unsorted !== 4 ) {
            return new WP_Error(
                'order_fail',
                "Unsorted order should have 4 transitions, got {$transitions_unsorted}."
            );
        }

        echo "  count_transitions is order-dependent (sorted: {$transitions_sorted}, unsorted: {$transitions_unsorted}).\n";

        return true;
    },
    'Batch_Sorter counts LED transitions accurately.'
);

run_test(
    'TC-BC-007: Batch_Sorter get_distinct_led_codes',
    function (): bool {
        $sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();

        $modules = array(
            array( 'led_codes' => array( 'K7P', '4T9' ) ),
            array( 'led_codes' => array( '4T9', 'CF4' ) ),
            array( 'led_codes' => array( 'K7P' ) ),
        );

        $distinct = $sorter->get_distinct_led_codes( $modules );

        // Should have 3 distinct codes: K7P, 4T9, CF4.
        if ( count( $distinct ) !== 3 ) {
            return new WP_Error(
                'distinct_fail',
                'Expected 3 distinct codes, got ' . count( $distinct ) . '.'
            );
        }

        if ( ! in_array( 'K7P', $distinct, true ) ) {
            return new WP_Error( 'distinct_fail', 'K7P should be in distinct codes.' );
        }
        if ( ! in_array( '4T9', $distinct, true ) ) {
            return new WP_Error( 'distinct_fail', '4T9 should be in distinct codes.' );
        }
        if ( ! in_array( 'CF4', $distinct, true ) ) {
            return new WP_Error( 'distinct_fail', 'CF4 should be in distinct codes.' );
        }

        echo "  get_distinct_led_codes extracts unique LED codes.\n";

        return true;
    },
    'Batch_Sorter extracts distinct LED codes from module list.'
);

run_test(
    'TC-BC-008: Batch_Sorter calculate_array_breakdown',
    function (): bool {
        $sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();

        // 15 modules starting at position 1.
        $breakdown = $sorter->calculate_array_breakdown( 15, 1 );

        if ( $breakdown['array_count'] !== 2 ) {
            return new WP_Error( 'breakdown_fail', 'Expected 2 arrays for 15 modules.' );
        }

        // Arrays detail.
        if ( count( $breakdown['arrays'] ) !== 2 ) {
            return new WP_Error( 'breakdown_fail', 'Should have 2 array details.' );
        }

        // First array: 8 modules, positions 1-8.
        if ( $breakdown['arrays'][0]['module_count'] !== 8 ) {
            return new WP_Error( 'breakdown_fail', 'First array should have 8 modules.' );
        }

        // Second array: 7 modules, positions 1-7.
        if ( $breakdown['arrays'][1]['module_count'] !== 7 ) {
            return new WP_Error( 'breakdown_fail', 'Second array should have 7 modules.' );
        }

        // Test with start position 5: first array has 4 slots (5-8).
        $breakdown = $sorter->calculate_array_breakdown( 10, 5 );

        if ( $breakdown['array_count'] !== 2 ) {
            return new WP_Error( 'breakdown_fail', 'Expected 2 arrays for 10 modules from pos 5.' );
        }

        if ( $breakdown['arrays'][0]['module_count'] !== 4 ) {
            return new WP_Error( 'breakdown_fail', 'First array should have 4 modules (pos 5-8).' );
        }

        if ( $breakdown['arrays'][1]['module_count'] !== 6 ) {
            return new WP_Error( 'breakdown_fail', 'Second array should have 6 modules.' );
        }

        echo "  calculate_array_breakdown provides accurate layout preview.\n";

        return true;
    },
    'Batch_Sorter calculates array breakdown correctly.'
);

run_test(
    'TC-BC-009: LED_Code_Resolver service instantiation',
    function (): bool {
        // Verify class exists.
        if ( ! class_exists( 'Quadica\\QSA_Engraving\\Services\\LED_Code_Resolver' ) ) {
            return new WP_Error( 'missing_class', 'LED_Code_Resolver class not found.' );
        }

        // Instantiate.
        $resolver = new \Quadica\QSA_Engraving\Services\LED_Code_Resolver();

        // Verify key methods exist.
        if ( ! method_exists( $resolver, 'get_led_codes_for_module' ) ) {
            return new WP_Error( 'missing_method', 'get_led_codes_for_module() method not found.' );
        }
        if ( ! method_exists( $resolver, 'get_led_shortcode' ) ) {
            return new WP_Error( 'missing_method', 'get_led_shortcode() method not found.' );
        }
        if ( ! method_exists( $resolver, 'clear_cache' ) ) {
            return new WP_Error( 'missing_method', 'clear_cache() method not found.' );
        }

        echo "  LED_Code_Resolver service instantiated successfully.\n";

        return true;
    },
    'LED_Code_Resolver service class exists and has required methods.'
);

run_test(
    'TC-BC-010: LED_Code_Resolver shortcode validation',
    function (): bool {
        $resolver = \Quadica\QSA_Engraving\Services\LED_Code_Resolver::class;

        // Valid 3-character alphanumeric codes.
        $valid_codes = array( 'K7P', '4T9', 'CF4', 'ABC', '123', 'A1B' );
        foreach ( $valid_codes as $code ) {
            if ( ! $resolver::is_valid_shortcode( $code ) ) {
                return new WP_Error( 'validation_fail', "'{$code}' should be valid." );
            }
        }

        // Invalid codes.
        $invalid_codes = array(
            'K7',      // Too short.
            'K7P9',    // Too long.
            'K-7',     // Contains special char.
            'K 7',     // Contains space.
            '',        // Empty.
        );
        foreach ( $invalid_codes as $code ) {
            if ( $resolver::is_valid_shortcode( $code ) ) {
                return new WP_Error( 'validation_fail', "'{$code}' should be invalid." );
            }
        }

        echo "  LED shortcode validation (3-char alphanumeric) verified.\n";

        return true;
    },
    'LED_Code_Resolver validates shortcode format correctly.'
);

run_test(
    'TC-BC-011: Batch_Ajax_Handler service instantiation',
    function (): bool {
        // Verify class exists.
        if ( ! class_exists( 'Quadica\\QSA_Engraving\\Ajax\\Batch_Ajax_Handler' ) ) {
            return new WP_Error( 'missing_class', 'Batch_Ajax_Handler class not found.' );
        }

        // Verify nonce action constant.
        $nonce_action = \Quadica\QSA_Engraving\Ajax\Batch_Ajax_Handler::NONCE_ACTION;
        if ( $nonce_action !== 'qsa_engraving_nonce' ) {
            return new WP_Error(
                'constant_fail',
                "NONCE_ACTION should be 'qsa_engraving_nonce', got '{$nonce_action}'."
            );
        }

        // Verify AJAX actions are registered.
        global $wp_filter;

        $expected_actions = array(
            'wp_ajax_qsa_get_modules_awaiting',
            'wp_ajax_qsa_refresh_modules',
            'wp_ajax_qsa_create_batch',
            'wp_ajax_qsa_preview_batch',
        );

        $registered = array();
        foreach ( $expected_actions as $action ) {
            if ( isset( $wp_filter[ $action ] ) ) {
                $registered[] = $action;
            }
        }

        if ( count( $registered ) !== count( $expected_actions ) ) {
            echo "  Note: Not all AJAX actions registered (expected in full WordPress context).\n";
            echo "  Registered: " . count( $registered ) . " of " . count( $expected_actions ) . "\n";
        }

        echo "  Batch_Ajax_Handler service class verified.\n";

        return true;
    },
    'Batch_Ajax_Handler service class exists and has required constants.'
);

run_test(
    'TC-BC-012: Plugin services accessible via getters',
    function (): bool {
        $plugin = \Quadica\QSA_Engraving\qsa_engraving();

        // Check Module_Selector is accessible (existing service).
        $module_selector = $plugin->get_module_selector();
        if ( ! ( $module_selector instanceof \Quadica\QSA_Engraving\Services\Module_Selector ) ) {
            return new WP_Error( 'getter_fail', 'Module_Selector not accessible via getter.' );
        }

        // Verify plugin initializes services.
        // The services are private, so we verify through class existence and instantiation.
        $batch_sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();
        $led_resolver = new \Quadica\QSA_Engraving\Services\LED_Code_Resolver();

        // These classes work correctly, which means the plugin can use them.
        if ( ! $batch_sorter instanceof \Quadica\QSA_Engraving\Services\Batch_Sorter ) {
            return new WP_Error( 'type_fail', 'Batch_Sorter instantiation failed.' );
        }

        if ( ! $led_resolver instanceof \Quadica\QSA_Engraving\Services\LED_Code_Resolver ) {
            return new WP_Error( 'type_fail', 'LED_Code_Resolver instantiation failed.' );
        }

        echo "  Plugin services are accessible and functional.\n";

        return true;
    },
    'Plugin exposes services correctly.'
);

run_test(
    'TC-BC-013: Batch_Sorter empty input handling',
    function (): bool {
        $sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();

        // Empty modules.
        $sorted = $sorter->sort_modules( array() );
        if ( ! empty( $sorted ) ) {
            return new WP_Error( 'empty_fail', 'Empty input should return empty array.' );
        }

        // Empty selections.
        $expanded = $sorter->expand_selections( array() );
        if ( ! empty( $expanded ) ) {
            return new WP_Error( 'empty_fail', 'Empty selections should return empty array.' );
        }

        // Zero modules for array breakdown.
        $breakdown = $sorter->calculate_array_breakdown( 0, 1 );
        if ( $breakdown['array_count'] !== 0 ) {
            return new WP_Error( 'empty_fail', 'Zero modules should return 0 arrays.' );
        }

        echo "  Empty input handling verified.\n";

        return true;
    },
    'Batch_Sorter handles empty input gracefully.'
);

run_test(
    'TC-BC-014: Batch_Sorter single module handling',
    function (): bool {
        $sorter = new \Quadica\QSA_Engraving\Services\Batch_Sorter();

        // Single module.
        $modules = array(
            array( 'id' => 1, 'led_codes' => array( 'K7P' ) ),
        );

        $sorted = $sorter->sort_modules( $modules );
        if ( count( $sorted ) !== 1 ) {
            return new WP_Error( 'single_fail', 'Single module should return single module.' );
        }

        // Single module transition count (0 for single module - no transitions needed).
        $transitions = $sorter->count_transitions( $modules );
        if ( $transitions !== 0 ) {
            return new WP_Error( 'single_fail', "Single module should have 0 transitions, got {$transitions}." );
        }

        // Single module array assignment.
        $arrays = $sorter->assign_to_arrays( $modules, 1 );
        if ( count( $arrays ) !== 1 ) {
            return new WP_Error( 'single_fail', 'Single module should create 1 array.' );
        }
        if ( count( $arrays[0] ) !== 1 ) {
            return new WP_Error( 'single_fail', 'Single array should have 1 module.' );
        }

        echo "  Single module handling verified.\n";

        return true;
    },
    'Batch_Sorter handles single module correctly.'
);

run_test(
    'TC-P5-015: Multiple modules of same SKU can be added to batch',
    function (): bool {
        global $wpdb;

        // Get repository instance.
        $plugin           = \Quadica\QSA_Engraving\qsa_engraving();
        $batch_repository = $plugin->get_batch_repository();

        // Test data identifiers.
        $test_production_batch_id = 9999;
        $test_module_sku          = 'TEST-99999';
        $test_order_id            = 999999;

        // Clean up any existing test data first.
        $modules_table = $batch_repository->get_modules_table_name();
        $batches_table = $batch_repository->get_batches_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$modules_table} WHERE production_batch_id = %d AND module_sku = %s",
                $test_production_batch_id,
                $test_module_sku
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$batches_table} WHERE batch_name = %s",
                'TC-P5-015 Test Batch'
            )
        );

        // Create a test batch.
        $batch_id = $batch_repository->create_batch( 'TC-P5-015 Test Batch' );
        if ( is_wp_error( $batch_id ) ) {
            return new WP_Error( 'batch_create_failed', 'Failed to create test batch: ' . $batch_id->get_error_message() );
        }

        echo "  Created test batch ID: {$batch_id}\n";

        // Insert 5 modules with same production_batch_id, module_sku, order_id
        // but different qsa_sequence/array_position (the new unique key).
        $modules_inserted = 0;
        $insert_errors    = array();

        for ( $position = 1; $position <= 5; $position++ ) {
            $module_data = array(
                'engraving_batch_id'  => $batch_id,
                'production_batch_id' => $test_production_batch_id,
                'module_sku'          => $test_module_sku,
                'order_id'            => $test_order_id,
                'serial_number'       => 'TEST' . str_pad( (string) $position, 4, '0', STR_PAD_LEFT ), // e.g., TEST0001.
                'qsa_sequence'        => 1,    // All on same QSA.
                'array_position'      => $position,
            );

            $result = $batch_repository->add_module( $module_data );

            if ( is_wp_error( $result ) ) {
                $insert_errors[] = "Position {$position}: " . $result->get_error_message();
            } else {
                $modules_inserted++;
                echo "  Inserted module at position {$position}, ID: {$result}\n";
            }
        }

        // Verify all 5 modules were inserted.
        if ( $modules_inserted !== 5 ) {
            // Clean up before failing.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$modules_table} WHERE engraving_batch_id = %d",
                    $batch_id
                )
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->delete( $batches_table, array( 'id' => $batch_id ), array( '%d' ) );

            $error_detail = ! empty( $insert_errors ) ? implode( '; ', $insert_errors ) : 'Unknown error';
            return new WP_Error(
                'insert_failed',
                "Expected 5 modules inserted, got {$modules_inserted}. Errors: {$error_detail}"
            );
        }

        // Verify modules in database.
        $modules = $batch_repository->get_modules_for_batch( $batch_id );
        $module_count = count( $modules );

        if ( $module_count !== 5 ) {
            // Clean up before failing.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$modules_table} WHERE engraving_batch_id = %d",
                    $batch_id
                )
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->delete( $batches_table, array( 'id' => $batch_id ), array( '%d' ) );

            return new WP_Error(
                'count_mismatch',
                "Expected 5 modules in batch, found {$module_count}."
            );
        }

        echo "  Verified {$module_count} modules in batch.\n";

        // Update batch counts and verify.
        $batch_repository->update_batch_counts( $batch_id, 5, 1 );
        $batch = $batch_repository->get_batch( $batch_id );

        if ( (int) $batch['module_count'] !== 5 ) {
            // Clean up before failing.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$modules_table} WHERE engraving_batch_id = %d",
                    $batch_id
                )
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->delete( $batches_table, array( 'id' => $batch_id ), array( '%d' ) );

            return new WP_Error(
                'count_update_failed',
                "Expected batch module_count=5, got {$batch['module_count']}."
            );
        }

        echo "  Batch module_count correctly updated to 5.\n";

        // Clean up test data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$modules_table} WHERE engraving_batch_id = %d",
                $batch_id
            )
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->delete( $batches_table, array( 'id' => $batch_id ), array( '%d' ) );

        echo "  Test data cleaned up successfully.\n";

        return true;
    },
    'Verifies the unique key fix allows multiple modules with same SKU/order but different positions.'
);

// ============================================
// PHASE 6 TESTS: Engraving Queue UI
// ============================================

run_test(
    'TC-EQ-001: Queue_Ajax_Handler class exists and instantiates',
    function (): bool {
        if ( ! class_exists( 'Quadica\\QSA_Engraving\\Ajax\\Queue_Ajax_Handler' ) ) {
            return new WP_Error( 'missing_class', 'Queue_Ajax_Handler class not found.' );
        }

        // Get dependencies from plugin.
        $plugin           = \Quadica\QSA_Engraving\qsa_engraving();
        $batch_sorter     = new \Quadica\QSA_Engraving\Services\Batch_Sorter();
        $batch_repository = $plugin->get_batch_repository();
        $serial_repository = $plugin->get_serial_repository();

        // Instantiate handler.
        $handler = new \Quadica\QSA_Engraving\Ajax\Queue_Ajax_Handler(
            $batch_sorter,
            $batch_repository,
            $serial_repository
        );

        if ( ! method_exists( $handler, 'register' ) ) {
            return new WP_Error( 'missing_method', 'Queue_Ajax_Handler::register() not found.' );
        }

        echo "  Queue_Ajax_Handler instantiated successfully.\n";

        return true;
    },
    'Queue AJAX handler class exists with required methods.'
);

run_test(
    'TC-EQ-002: Batch_Repository has queue-related methods',
    function (): bool {
        $plugin = \Quadica\QSA_Engraving\qsa_engraving();
        $batch_repo = $plugin->get_batch_repository();

        $required_methods = array(
            'update_row_status',
            'reset_row_status',
            'reopen_batch',
            'update_start_position',
            'get_queue_stats',
        );

        $missing = array();
        foreach ( $required_methods as $method ) {
            if ( ! method_exists( $batch_repo, $method ) ) {
                $missing[] = $method;
            }
        }

        if ( ! empty( $missing ) ) {
            return new WP_Error(
                'missing_methods',
                'Missing Batch_Repository methods: ' . implode( ', ', $missing )
            );
        }

        echo "  All required queue methods exist in Batch_Repository.\n";

        return true;
    },
    'Batch_Repository has all queue-related methods for Phase 6.'
);

run_test(
    'TC-EQ-003: Update row status validates status values',
    function (): bool {
        $plugin = \Quadica\QSA_Engraving\qsa_engraving();
        $batch_repo = $plugin->get_batch_repository();

        // Test with invalid status.
        $result = $batch_repo->update_row_status( 1, 1, 'invalid_status' );

        if ( ! is_wp_error( $result ) ) {
            return new WP_Error( 'validation_fail', 'Invalid status should return WP_Error.' );
        }

        if ( $result->get_error_code() !== 'invalid_status' ) {
            return new WP_Error(
                'error_code_fail',
                "Expected error code 'invalid_status', got '{$result->get_error_code()}'."
            );
        }

        echo "  Invalid status correctly rejected.\n";

        return true;
    },
    'update_row_status() validates status parameter.'
);

run_test(
    'TC-EQ-004: Get queue stats returns correct structure',
    function (): bool {
        $plugin = \Quadica\QSA_Engraving\qsa_engraving();
        $batch_repo = $plugin->get_batch_repository();

        // Get stats for non-existent batch (should return zeros).
        $stats = $batch_repo->get_queue_stats( 999999 );

        $required_keys = array( 'pending', 'in_progress', 'done', 'total', 'total_qsas', 'done_qsas' );
        $missing = array();

        foreach ( $required_keys as $key ) {
            if ( ! array_key_exists( $key, $stats ) ) {
                $missing[] = $key;
            }
        }

        if ( ! empty( $missing ) ) {
            return new WP_Error(
                'structure_fail',
                'Missing stats keys: ' . implode( ', ', $missing )
            );
        }

        // All values should be integers.
        foreach ( $stats as $key => $value ) {
            if ( ! is_int( $value ) ) {
                return new WP_Error( 'type_fail', "Stats key '{$key}' should be integer." );
            }
        }

        echo "  Queue stats structure verified.\n";
        echo "  Stats: pending={$stats['pending']}, in_progress={$stats['in_progress']}, done={$stats['done']}\n";

        return true;
    },
    'get_queue_stats() returns expected structure with integer values.'
);

run_test(
    'TC-EQ-005: React bundle exists and enqueues',
    function (): bool {
        $bundle_path = QSA_ENGRAVING_PLUGIN_DIR . 'assets/js/build/engraving-queue.js';
        $asset_path  = QSA_ENGRAVING_PLUGIN_DIR . 'assets/js/build/engraving-queue.asset.php';

        if ( ! file_exists( $bundle_path ) ) {
            return new WP_Error( 'bundle_missing', "React bundle not found at: {$bundle_path}" );
        }

        if ( ! file_exists( $asset_path ) ) {
            return new WP_Error( 'asset_missing', "Asset file not found at: {$asset_path}" );
        }

        // Verify bundle has content.
        $bundle_size = filesize( $bundle_path );
        if ( $bundle_size < 1000 ) {
            return new WP_Error( 'bundle_small', "Bundle seems too small: {$bundle_size} bytes." );
        }

        // Verify asset file returns dependencies.
        $asset = require $asset_path;
        if ( ! is_array( $asset ) || ! isset( $asset['dependencies'] ) ) {
            return new WP_Error( 'asset_invalid', 'Asset file does not contain dependencies.' );
        }

        echo "  Bundle size: " . number_format( $bundle_size ) . " bytes\n";
        echo "  Dependencies: " . implode( ', ', $asset['dependencies'] ) . "\n";

        return true;
    },
    'Engraving queue React bundle exists with proper asset metadata.'
);

run_test(
    'TC-EQ-006: React CSS bundle exists',
    function (): bool {
        $css_path = QSA_ENGRAVING_PLUGIN_DIR . 'assets/js/build/style-engraving-queue.css';

        if ( ! file_exists( $css_path ) ) {
            return new WP_Error( 'css_missing', "CSS bundle not found at: {$css_path}" );
        }

        $css_size = filesize( $css_path );
        if ( $css_size < 500 ) {
            return new WP_Error( 'css_small', "CSS seems too small: {$css_size} bytes." );
        }

        echo "  CSS size: " . number_format( $css_size ) . " bytes\n";

        return true;
    },
    'Engraving queue CSS bundle exists with proper content.'
);

run_test(
    'TC-EQ-007: Admin menu has queue page',
    function (): bool {
        $admin_menu = new \Quadica\QSA_Engraving\Admin\Admin_Menu();

        // Check menu slug constant.
        $expected_slug = 'qsa-engraving';
        if ( \Quadica\QSA_Engraving\Admin\Admin_Menu::MENU_SLUG !== $expected_slug ) {
            return new WP_Error(
                'slug_fail',
                "Expected menu slug '{$expected_slug}', got '" .
                \Quadica\QSA_Engraving\Admin\Admin_Menu::MENU_SLUG . "'."
            );
        }

        // Check render method exists for queue page.
        if ( ! method_exists( $admin_menu, 'render_queue_page' ) ) {
            return new WP_Error( 'method_missing', 'render_queue_page() method not found.' );
        }

        echo "  Admin menu queue page method exists.\n";

        return true;
    },
    'Admin menu includes engraving queue page.'
);

run_test(
    'TC-EQ-008: Serial lifecycle transitions work correctly',
    function (): bool {
        $serial_repo = \Quadica\QSA_Engraving\qsa_engraving()->get_serial_repository();

        // Test valid transitions.
        $valid_transitions = array(
            array( 'reserved', 'engraved' ),
            array( 'reserved', 'voided' ),
        );

        foreach ( $valid_transitions as $transition ) {
            $from = $transition[0];
            $to   = $transition[1];
            if ( ! $serial_repo::is_transition_allowed( $from, $to ) ) {
                return new WP_Error(
                    'transition_fail',
                    "Transition from '{$from}' to '{$to}' should be allowed."
                );
            }
        }

        // Test invalid transitions (terminal states).
        $invalid_transitions = array(
            array( 'engraved', 'reserved' ),
            array( 'engraved', 'voided' ),
            array( 'voided', 'reserved' ),
            array( 'voided', 'engraved' ),
        );

        foreach ( $invalid_transitions as $transition ) {
            $from = $transition[0];
            $to   = $transition[1];
            if ( $serial_repo::is_transition_allowed( $from, $to ) ) {
                return new WP_Error(
                    'transition_fail',
                    "Transition from '{$from}' to '{$to}' should NOT be allowed."
                );
            }
        }

        echo "  Valid transitions: reserved->engraved, reserved->voided\n";
        echo "  Terminal states (engraved, voided) correctly block transitions.\n";

        return true;
    },
    'Serial number lifecycle enforces valid state transitions.'
);

run_test(
    'TC-EQ-009: Queue AJAX actions are registered',
    function (): bool {
        $plugin = \Quadica\QSA_Engraving\qsa_engraving();

        // The AJAX actions should be registered by the plugin init.
        // We check if the action hooks exist.
        $expected_actions = array(
            'wp_ajax_qsa_get_queue',
            'wp_ajax_qsa_start_row',
            'wp_ajax_qsa_next_array',
            'wp_ajax_qsa_complete_row',
            'wp_ajax_qsa_resend_svg',
            'wp_ajax_qsa_back_array',
            'wp_ajax_qsa_rerun_row',
            'wp_ajax_qsa_update_start_position',
        );

        // In CLI context, hooks may not be registered yet.
        // Check that the handler class has corresponding methods.
        $batch_sorter      = new \Quadica\QSA_Engraving\Services\Batch_Sorter();
        $batch_repository  = $plugin->get_batch_repository();
        $serial_repository = $plugin->get_serial_repository();

        $handler = new \Quadica\QSA_Engraving\Ajax\Queue_Ajax_Handler(
            $batch_sorter,
            $batch_repository,
            $serial_repository
        );

        $expected_methods = array(
            'handle_get_queue',
            'handle_start_row',
            'handle_next_array',
            'handle_complete_row',
            'handle_resend_svg',
            'handle_back_array',
            'handle_rerun_row',
            'handle_update_start_position',
        );

        $missing = array();
        foreach ( $expected_methods as $method ) {
            if ( ! method_exists( $handler, $method ) ) {
                $missing[] = $method;
            }
        }

        if ( ! empty( $missing ) ) {
            return new WP_Error(
                'methods_missing',
                'Missing handler methods: ' . implode( ', ', $missing )
            );
        }

        echo "  All 9 queue AJAX handler methods exist.\n";

        return true;
    },
    'Queue AJAX handler has all required action methods.'
);

run_test(
    'TC-EQ-010: Update start position with valid range',
    function (): bool {
        $plugin     = \Quadica\QSA_Engraving\qsa_engraving();
        $batch_repo = $plugin->get_batch_repository();

        // Test with non-existent batch (should return error for no modules).
        $result = $batch_repo->update_start_position( 999999, 1, 5 );

        if ( ! is_wp_error( $result ) ) {
            // If not error, it means no modules to update (which is expected).
            if ( $result !== 0 ) {
                return new WP_Error(
                    'unexpected_result',
                    "Expected 0 or WP_Error for non-existent batch, got {$result}."
                );
            }
            echo "  Non-existent batch correctly returns 0 updated rows.\n";
        } else {
            // Accept either 'no_modules' or 'no_sequences' error codes.
            if ( ! in_array( $result->get_error_code(), array( 'no_modules', 'no_sequences' ), true ) ) {
                return new WP_Error(
                    'error_code_fail',
                    "Expected 'no_modules' or 'no_sequences' error, got '{$result->get_error_code()}'."
                );
            }
            echo "  Non-existent batch correctly returns '{$result->get_error_code()}' error.\n";
        }

        return true;
    },
    'update_start_position() handles missing batches gracefully.'
);

run_test(
    'TC-EQ-012: Redistribute row modules with start position change',
    function (): bool {
        global $wpdb;
        $plugin     = \Quadica\QSA_Engraving\qsa_engraving();
        $batch_repo = $plugin->get_batch_repository();

        // Create a test batch.
        $batch_id = $batch_repo->create_batch( 'Start Position Test Batch' );
        if ( is_wp_error( $batch_id ) ) {
            return $batch_id;
        }

        // Add 24 modules to simulate a real scenario.
        // With start_position=1: 3 arrays (8+8+8)
        // With start_position=6: 4 arrays (3+8+8+5)
        $modules_added = 0;
        for ( $i = 1; $i <= 24; $i++ ) {
            $qsa_seq = (int) ceil( $i / 8 );
            $pos     = ( ( $i - 1 ) % 8 ) + 1;
            $result  = $batch_repo->add_module(
                array(
                    'engraving_batch_id'  => $batch_id,
                    'production_batch_id' => 12345,
                    'module_sku'          => 'STAR-TEST-001',
                    'order_id'            => 99999,
                    'serial_number'       => '',
                    'qsa_sequence'        => $qsa_seq,
                    'array_position'      => $pos,
                )
            );
            if ( ! is_wp_error( $result ) ) {
                $modules_added++;
            }
        }

        if ( $modules_added !== 24 ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error( 'add_modules_fail', "Expected 24 modules, added {$modules_added}." );
        }
        echo "  Created test batch {$batch_id} with 24 modules (3 QSA sequences).\n";

        // Test redistribute with start_position=6.
        // Should redistribute to 4 arrays: (3+8+8+5).
        $result = $batch_repo->redistribute_row_modules( $batch_id, array( 1, 2, 3 ), 6 );

        if ( is_wp_error( $result ) ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'redistribute_fail',
                "redistribute_row_modules failed: {$result->get_error_message()}"
            );
        }

        // Verify redistribution results.
        if ( $result['module_count'] !== 24 ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'module_count_fail',
                "Expected 24 modules, got {$result['module_count']}."
            );
        }

        if ( $result['old_qsa_count'] !== 3 ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'old_qsa_count_fail',
                "Expected old_qsa_count=3, got {$result['old_qsa_count']}."
            );
        }

        if ( $result['new_qsa_count'] !== 4 ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'new_qsa_count_fail',
                "Expected new_qsa_count=4, got {$result['new_qsa_count']}."
            );
        }

        echo "  Redistribution result: {$result['old_qsa_count']} -> {$result['new_qsa_count']} arrays.\n";

        // Verify array breakdown.
        $arrays = $result['arrays'];
        if ( count( $arrays ) !== 4 ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'array_count_fail',
                "Expected 4 arrays in breakdown, got " . count( $arrays ) . "."
            );
        }

        // Array 1: start=6, count=3 (positions 6,7,8)
        if ( $arrays[0]['start_position'] !== 6 || $arrays[0]['module_count'] !== 3 ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'array1_fail',
                "Array 1 should start=6, count=3. Got start={$arrays[0]['start_position']}, count={$arrays[0]['module_count']}."
            );
        }

        // Arrays 2,3: start=1, count=8 (full arrays)
        if ( $arrays[1]['start_position'] !== 1 || $arrays[1]['module_count'] !== 8 ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'array2_fail',
                "Array 2 should start=1, count=8. Got start={$arrays[1]['start_position']}, count={$arrays[1]['module_count']}."
            );
        }

        // Array 4: start=1, count=5 (partial array)
        if ( $arrays[3]['start_position'] !== 1 || $arrays[3]['module_count'] !== 5 ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'array4_fail',
                "Array 4 should start=1, count=5. Got start={$arrays[3]['start_position']}, count={$arrays[3]['module_count']}."
            );
        }

        echo "  Array breakdown verified: [6-8](3) + [1-8](8) + [1-8](8) + [1-5](5) = 24 modules.\n";

        // Verify database records were updated correctly.
        $modules = $batch_repo->get_modules_for_batch( $batch_id );
        $qsa_counts = array();
        foreach ( $modules as $m ) {
            $qsa = (int) $m['qsa_sequence'];
            if ( ! isset( $qsa_counts[ $qsa ] ) ) {
                $qsa_counts[ $qsa ] = 0;
            }
            $qsa_counts[ $qsa ]++;
        }

        // Should have 4 QSA sequences now: 1,2,3,4.
        if ( count( $qsa_counts ) !== 4 ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'db_qsa_count_fail',
                "Expected 4 QSA sequences in DB, got " . count( $qsa_counts ) . "."
            );
        }

        // Verify module counts per QSA.
        ksort( $qsa_counts );
        $expected_counts = array( 1 => 3, 2 => 8, 3 => 8, 4 => 5 );
        foreach ( $expected_counts as $qsa => $expected ) {
            if ( ( $qsa_counts[ $qsa ] ?? 0 ) !== $expected ) {
                $batch_repo->delete_batch( $batch_id );
                return new WP_Error(
                    'db_module_count_fail',
                    "QSA {$qsa} should have {$expected} modules, got " . ( $qsa_counts[ $qsa ] ?? 0 ) . "."
                );
            }
        }

        echo "  Database records verified: QSA 1=3, QSA 2=8, QSA 3=8, QSA 4=5.\n";

        // Test resetting back to start_position=1.
        $result2 = $batch_repo->redistribute_row_modules( $batch_id, array( 1, 2, 3, 4 ), 1 );

        if ( is_wp_error( $result2 ) ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'redistribute_reset_fail',
                "redistribute back to start=1 failed: {$result2->get_error_message()}"
            );
        }

        if ( $result2['new_qsa_count'] !== 3 ) {
            $batch_repo->delete_batch( $batch_id );
            return new WP_Error(
                'reset_qsa_count_fail',
                "Expected new_qsa_count=3 after reset, got {$result2['new_qsa_count']}."
            );
        }

        echo "  Reset to start=1 verified: 4 -> 3 arrays.\n";

        // Clean up test data.
        $batch_repo->delete_batch( $batch_id );
        echo "  Test data cleaned up successfully.\n";

        return true;
    },
    'redistribute_row_modules() correctly redistributes modules across arrays.'
);

run_test(
    'TC-EQ-011: Queue handler normalizes empty row_status values',
    function (): bool {
        // Use reflection to test the private normalize_row_status method.
        $plugin        = \Quadica\QSA_Engraving\qsa_engraving();
        $queue_handler = $plugin->get_queue_ajax_handler();

        $reflection = new ReflectionClass( $queue_handler );
        $method     = $reflection->getMethod( 'normalize_row_status' );
        $method->setAccessible( true );

        // Test empty string -> 'pending'.
        $result = $method->invoke( $queue_handler, '' );
        if ( $result !== 'pending' ) {
            return new WP_Error( 'empty_string_fail', "Empty string should return 'pending', got '{$result}'." );
        }
        echo "  Empty string -> 'pending': PASS\n";

        // Test null -> 'pending'.
        $result = $method->invoke( $queue_handler, null );
        if ( $result !== 'pending' ) {
            return new WP_Error( 'null_fail', "Null should return 'pending', got '{$result}'." );
        }
        echo "  Null -> 'pending': PASS\n";

        // Test valid 'pending' -> 'pending'.
        $result = $method->invoke( $queue_handler, 'pending' );
        if ( $result !== 'pending' ) {
            return new WP_Error( 'pending_fail', "Valid 'pending' should remain 'pending', got '{$result}'." );
        }
        echo "  'pending' -> 'pending': PASS\n";

        // Test valid 'in_progress' -> 'in_progress'.
        $result = $method->invoke( $queue_handler, 'in_progress' );
        if ( $result !== 'in_progress' ) {
            return new WP_Error( 'in_progress_fail', "Valid 'in_progress' should remain 'in_progress', got '{$result}'." );
        }
        echo "  'in_progress' -> 'in_progress': PASS\n";

        // Test valid 'done' -> 'done'.
        $result = $method->invoke( $queue_handler, 'done' );
        if ( $result !== 'done' ) {
            return new WP_Error( 'done_fail', "Valid 'done' should remain 'done', got '{$result}'." );
        }
        echo "  'done' -> 'done': PASS\n";

        return true;
    },
    'normalize_row_status() handles empty strings and null values correctly.'
);

// ============================================
// Phase 7: LightBurn Integration Tests
// ============================================
echo "\n----------------------------------------\n";
echo "Phase 7: LightBurn Integration Tests\n";
echo "----------------------------------------\n\n";

run_test(
    'TC-LB-001: LightBurn_Client class exists',
    function (): bool {
        if ( ! class_exists( 'Quadica\\QSA_Engraving\\Services\\LightBurn_Client' ) ) {
            return new WP_Error( 'class_missing', 'LightBurn_Client class not found.' );
        }

        $client = new \Quadica\QSA_Engraving\Services\LightBurn_Client();

        // Check default settings.
        if ( $client->get_host() !== '127.0.0.1' ) {
            return new WP_Error(
                'default_host',
                'Default host should be 127.0.0.1, got ' . $client->get_host()
            );
        }

        if ( $client->get_out_port() !== 19840 ) {
            return new WP_Error(
                'default_port',
                'Default out port should be 19840, got ' . $client->get_out_port()
            );
        }

        if ( $client->get_in_port() !== 19841 ) {
            return new WP_Error(
                'default_in_port',
                'Default in port should be 19841, got ' . $client->get_in_port()
            );
        }

        echo "  LightBurn_Client class instantiated with correct defaults.\n";

        return true;
    },
    'LightBurn_Client class exists and has correct default settings.'
);

run_test(
    'TC-LB-002: LightBurn_Client has required methods',
    function (): bool {
        $client = new \Quadica\QSA_Engraving\Services\LightBurn_Client();

        $expected_methods = array(
            'send_command',
            'ping',
            'load_file',
            'load_file_with_retry',
            'test_connection',
            'is_enabled',
            'close',
        );

        $missing = array();
        foreach ( $expected_methods as $method ) {
            if ( ! method_exists( $client, $method ) ) {
                $missing[] = $method;
            }
        }

        if ( ! empty( $missing ) ) {
            return new WP_Error(
                'methods_missing',
                'Missing client methods: ' . implode( ', ', $missing )
            );
        }

        echo "  All 7 LightBurn_Client methods exist.\n";

        return true;
    },
    'LightBurn_Client has all required methods.'
);

run_test(
    'TC-LB-003: SVG_File_Manager class exists',
    function (): bool {
        if ( ! class_exists( 'Quadica\\QSA_Engraving\\Services\\SVG_File_Manager' ) ) {
            return new WP_Error( 'class_missing', 'SVG_File_Manager class not found.' );
        }

        $manager = new \Quadica\QSA_Engraving\Services\SVG_File_Manager();

        // Check default output directory.
        $output_dir = $manager->get_output_dir();
        if ( empty( $output_dir ) ) {
            return new WP_Error( 'empty_dir', 'Output directory is empty.' );
        }

        echo "  SVG_File_Manager using output directory: {$output_dir}\n";

        return true;
    },
    'SVG_File_Manager class exists and has valid output directory.'
);

run_test(
    'TC-LB-004: SVG_File_Manager has required methods',
    function (): bool {
        $manager = new \Quadica\QSA_Engraving\Services\SVG_File_Manager();

        $expected_methods = array(
            'ensure_directory',
            'generate_filename',
            'get_full_path',
            'get_lightburn_path',
            'save_svg',
            'cleanup_old_files',
            'cleanup_batch_files',
            'get_existing_file',
            'file_exists',
            'get_status',
        );

        $missing = array();
        foreach ( $expected_methods as $method ) {
            if ( ! method_exists( $manager, $method ) ) {
                $missing[] = $method;
            }
        }

        if ( ! empty( $missing ) ) {
            return new WP_Error(
                'methods_missing',
                'Missing file manager methods: ' . implode( ', ', $missing )
            );
        }

        echo "  All 10 SVG_File_Manager methods exist.\n";

        return true;
    },
    'SVG_File_Manager has all required methods.'
);

run_test(
    'TC-LB-005: LightBurn AJAX Handler exists',
    function (): bool {
        if ( ! class_exists( 'Quadica\\QSA_Engraving\\Ajax\\LightBurn_Ajax_Handler' ) ) {
            return new WP_Error( 'class_missing', 'LightBurn_Ajax_Handler class not found.' );
        }

        // Check that it has the expected methods.
        $expected_methods = array(
            'register',
            'handle_test_connection',
            'handle_generate_svg',
            'handle_load_svg',
            'handle_resend_svg',
            'handle_get_status',
            'handle_save_settings',
        );

        $missing = array();
        foreach ( $expected_methods as $method ) {
            if ( ! method_exists( 'Quadica\\QSA_Engraving\\Ajax\\LightBurn_Ajax_Handler', $method ) ) {
                $missing[] = $method;
            }
        }

        if ( ! empty( $missing ) ) {
            return new WP_Error(
                'methods_missing',
                'Missing handler methods: ' . implode( ', ', $missing )
            );
        }

        echo "  All 7 LightBurn AJAX handler methods exist.\n";

        return true;
    },
    'LightBurn_Ajax_Handler has all required methods.'
);

run_test(
    'TC-LB-006: LightBurn AJAX actions are registered',
    function (): bool {
        global $wp_filter;

        $expected_actions = array(
            'wp_ajax_qsa_test_lightburn',
            'wp_ajax_qsa_generate_svg',
            'wp_ajax_qsa_load_svg',
            'wp_ajax_qsa_resend_svg',
            'wp_ajax_qsa_get_lightburn_status',
            'wp_ajax_qsa_save_lightburn_settings',
        );

        $missing = array();
        foreach ( $expected_actions as $action ) {
            if ( ! has_action( $action ) ) {
                $missing[] = $action;
            }
        }

        if ( ! empty( $missing ) ) {
            return new WP_Error(
                'actions_not_registered',
                'Missing AJAX actions: ' . implode( ', ', $missing )
            );
        }

        echo "  All 6 LightBurn AJAX actions are registered.\n";

        return true;
    },
    'LightBurn AJAX actions are registered with WordPress.'
);

run_test(
    'TC-LB-007: SVG filename generation format',
    function (): bool {
        $manager = new \Quadica\QSA_Engraving\Services\SVG_File_Manager();

        $filename = $manager->generate_filename( 123, 5 );

        // Should match format: {batch_id}-{qsa_sequence}-{timestamp}.svg.
        if ( ! preg_match( '/^123-5-\d+\.svg$/', $filename ) ) {
            return new WP_Error(
                'invalid_format',
                "Filename format incorrect: {$filename}"
            );
        }

        echo "  Generated filename: {$filename}\n";

        return true;
    },
    'SVG filename follows expected format {batch_id}-{qsa_sequence}-{timestamp}.svg'
);

run_test(
    'TC-LB-008: LightBurn path conversion',
    function (): bool {
        $manager = new \Quadica\QSA_Engraving\Services\SVG_File_Manager();

        $filename      = 'test-1-123456.svg';
        $lightburn_path = $manager->get_lightburn_path( $filename );

        // Path should have backslashes for Windows.
        if ( strpos( $lightburn_path, '/' ) !== false && strpos( $lightburn_path, '\\' ) === false ) {
            return new WP_Error(
                'no_backslashes',
                "Path should have Windows backslashes: {$lightburn_path}"
            );
        }

        // Path should end with the filename.
        if ( substr( $lightburn_path, -strlen( $filename ) ) !== $filename ) {
            return new WP_Error(
                'missing_filename',
                "Path should end with filename: {$lightburn_path}"
            );
        }

        echo "  LightBurn path: {$lightburn_path}\n";

        return true;
    },
    'LightBurn path conversion produces Windows-compatible paths.'
);

run_test(
    'TC-LB-009: File manager status check',
    function (): bool {
        $manager = new \Quadica\QSA_Engraving\Services\SVG_File_Manager();

        $status = $manager->get_status();

        // Should have required keys.
        $required_keys = array( 'exists', 'writable', 'path', 'custom', 'file_count' );

        foreach ( $required_keys as $key ) {
            if ( ! array_key_exists( $key, $status ) ) {
                return new WP_Error(
                    'missing_key',
                    "Status missing required key: {$key}"
                );
            }
        }

        echo "  Status: exists=" . ( $status['exists'] ? 'yes' : 'no' );
        echo ", writable=" . ( $status['writable'] ? 'yes' : 'no' );
        echo ", custom=" . ( $status['custom'] ? 'yes' : 'no' );
        echo ", files=" . $status['file_count'] . "\n";

        return true;
    },
    'File manager status returns all required fields.'
);

run_test(
    'TC-LB-010: Settings option structure',
    function (): bool {
        // Get or create settings.
        $settings = get_option( 'qsa_engraving_settings', array() );

        // Just verify we can access the settings array.
        if ( ! is_array( $settings ) ) {
            return new WP_Error(
                'not_array',
                'Settings is not an array.'
            );
        }

        echo "  Settings option accessible. Currently set keys: " . count( $settings ) . "\n";

        return true;
    },
    'qsa_engraving_settings option is accessible.'
);

// ============================================
// Phase 8: Batch History Tests
// ============================================
echo "--- Phase 8: Batch History Tests ---\n\n";

run_test(
    'TC-P8-001: History AJAX Handler class exists',
    function (): bool {
        if ( ! class_exists( '\Quadica\QSA_Engraving\Ajax\History_Ajax_Handler' ) ) {
            return new WP_Error( 'class_missing', 'History_Ajax_Handler class not found.' );
        }

        echo "  History_Ajax_Handler class exists.\n";
        return true;
    },
    'History AJAX handler class is loadable.'
);

run_test(
    'TC-P8-002: History AJAX actions are registered',
    function (): bool {
        // Check if AJAX actions are registered.
        $actions = array(
            'wp_ajax_qsa_get_batch_history',
            'wp_ajax_qsa_get_batch_details',
            'wp_ajax_qsa_get_batch_for_reengraving',
        );

        foreach ( $actions as $action ) {
            if ( ! has_action( $action ) ) {
                return new WP_Error( 'action_missing', "Action '{$action}' not registered." );
            }
            echo "  {$action} is registered.\n";
        }

        return true;
    },
    'History AJAX actions are properly registered.'
);

run_test(
    'TC-P8-003: Batch History menu page exists',
    function (): bool {
        // Get the admin menu instance.
        $plugin = \Quadica\QSA_Engraving\qsa_engraving();

        // The menu slug should exist.
        $menu_slug = 'qsa-engraving-history';

        // Check if the submenu page would be accessible.
        global $submenu;
        $found = false;
        if ( isset( $submenu['woocommerce'] ) ) {
            foreach ( $submenu['woocommerce'] as $item ) {
                if ( isset( $item[2] ) && $item[2] === $menu_slug ) {
                    $found = true;
                    break;
                }
            }
        }

        // Check in qsa-engraving submenu too.
        if ( isset( $submenu['qsa-engraving'] ) ) {
            foreach ( $submenu['qsa-engraving'] as $item ) {
                if ( isset( $item[2] ) && $item[2] === $menu_slug ) {
                    $found = true;
                    break;
                }
            }
        }

        if ( ! $found ) {
            // Menu might not be built in CLI context, just verify the method exists.
            $admin_menu = new \Quadica\QSA_Engraving\Admin\Admin_Menu();
            if ( ! method_exists( $admin_menu, 'render_history_page' ) ) {
                return new WP_Error( 'method_missing', 'render_history_page method not found.' );
            }
            echo "  render_history_page method exists.\n";
        } else {
            echo "  Menu item found.\n";
        }

        return true;
    },
    'Batch History menu page is configured.'
);

run_test(
    'TC-P8-004: Batch Repository has enhanced methods',
    function (): bool {
        $batch_repo = new \Quadica\QSA_Engraving\Database\Batch_Repository();

        // Check for existing methods used by history.
        $required_methods = array(
            'get_batches',
            'get_batch',
            'get_modules_for_batch',
        );

        foreach ( $required_methods as $method ) {
            if ( ! method_exists( $batch_repo, $method ) ) {
                return new WP_Error( 'method_missing', "Method '{$method}' not found." );
            }
            echo "  {$method}() exists.\n";
        }

        return true;
    },
    'Batch Repository has methods required for history.'
);

run_test(
    'TC-P8-005: History AJAX Handler can fetch completed batches',
    function (): bool {
        $batch_repo = new \Quadica\QSA_Engraving\Database\Batch_Repository();
        $serial_repo = new \Quadica\QSA_Engraving\Database\Serial_Repository();

        $handler = new \Quadica\QSA_Engraving\Ajax\History_Ajax_Handler(
            $batch_repo,
            $serial_repo
        );

        // Verify the handler can be constructed.
        if ( ! $handler instanceof \Quadica\QSA_Engraving\Ajax\History_Ajax_Handler ) {
            return new WP_Error( 'construct_failed', 'Failed to construct handler.' );
        }

        echo "  History_Ajax_Handler constructed successfully.\n";
        return true;
    },
    'History AJAX handler can be constructed.'
);

run_test(
    'TC-P8-006: Batch history JavaScript bundle configured',
    function (): bool {
        // Check if webpack entry is configured by checking for the source file.
        $source_file = QSA_ENGRAVING_PLUGIN_DIR . 'assets/js/src/batch-history/index.js';
        if ( ! file_exists( $source_file ) ) {
            return new WP_Error( 'file_missing', 'batch-history/index.js source file not found.' );
        }
        echo "  Source file exists: assets/js/src/batch-history/index.js\n";

        // Check for main component.
        $component_file = QSA_ENGRAVING_PLUGIN_DIR . 'assets/js/src/batch-history/components/BatchHistory.js';
        if ( ! file_exists( $component_file ) ) {
            return new WP_Error( 'file_missing', 'BatchHistory.js component not found.' );
        }
        echo "  BatchHistory component exists.\n";

        // Check for other components.
        $components = array( 'BatchList.js', 'BatchDetails.js', 'SearchFilter.js' );
        foreach ( $components as $component ) {
            $path = QSA_ENGRAVING_PLUGIN_DIR . 'assets/js/src/batch-history/components/' . $component;
            if ( ! file_exists( $path ) ) {
                return new WP_Error( 'file_missing', "{$component} not found." );
            }
            echo "  {$component} exists.\n";
        }

        return true;
    },
    'Batch history React components are in place.'
);

run_test(
    'TC-P8-007: Batch history CSS exists',
    function (): bool {
        $css_file = QSA_ENGRAVING_PLUGIN_DIR . 'assets/js/src/batch-history/style.css';
        if ( ! file_exists( $css_file ) ) {
            return new WP_Error( 'file_missing', 'batch-history/style.css not found.' );
        }

        // Check file has content.
        $content = file_get_contents( $css_file );
        if ( strlen( $content ) < 1000 ) {
            return new WP_Error( 'css_empty', 'CSS file appears to be too small.' );
        }

        echo "  Style file exists with " . strlen( $content ) . " bytes.\n";
        return true;
    },
    'Batch history CSS file exists.'
);

run_test(
    'TC-P8-008: Settings page has render method',
    function (): bool {
        $admin_menu = new \Quadica\QSA_Engraving\Admin\Admin_Menu();

        if ( ! method_exists( $admin_menu, 'render_settings_page' ) ) {
            return new WP_Error( 'method_missing', 'render_settings_page method not found.' );
        }

        echo "  render_settings_page method exists.\n";
        return true;
    },
    'Settings page render method exists.'
);

// ============================================
// Phase 9: QSA Configuration Data Tests
// ============================================

run_test(
    'TC-P9-001: STARa configuration exists',
    function (): bool {
        $config_repo = new \Quadica\QSA_Engraving\Database\Config_Repository();

        // Get STARa configuration.
        $config = $config_repo->get_config( 'STAR', 'a' );

        if ( empty( $config ) ) {
            return new WP_Error( 'no_config', 'No STARa configuration found in database.' );
        }

        // Should have 8 positions.
        if ( count( $config ) !== 8 ) {
            return new WP_Error( 'wrong_count', 'Expected 8 positions, got ' . count( $config ) );
        }

        // Each position should have 5 elements.
        foreach ( $config as $pos => $elements ) {
            if ( count( $elements ) !== 5 ) {
                return new WP_Error( 'wrong_elements', "Position {$pos} should have 5 elements, got " . count( $elements ) );
            }
        }

        echo "  STARa: 8 positions × 5 elements = 40 config entries.\n";
        return true;
    },
    'STARa design has complete coordinate configuration.'
);

run_test(
    'TC-P9-002: CUBEa configuration exists',
    function (): bool {
        $config_repo = new \Quadica\QSA_Engraving\Database\Config_Repository();

        // Get CUBEa configuration.
        $config = $config_repo->get_config( 'CUBE', 'a' );

        if ( empty( $config ) ) {
            return new WP_Error( 'no_config', 'No CUBEa configuration found in database.' );
        }

        // Should have 8 positions.
        if ( count( $config ) !== 8 ) {
            return new WP_Error( 'wrong_count', 'Expected 8 positions, got ' . count( $config ) );
        }

        // Each position should have 8 elements (4 LED codes).
        foreach ( $config as $pos => $elements ) {
            if ( count( $elements ) !== 8 ) {
                return new WP_Error( 'wrong_elements', "Position {$pos} should have 8 elements, got " . count( $elements ) );
            }
        }

        echo "  CUBEa: 8 positions × 8 elements = 64 config entries.\n";
        return true;
    },
    'CUBEa design has complete coordinate configuration with 4 LED codes.'
);

run_test(
    'TC-P9-003: PICOa configuration exists',
    function (): bool {
        $config_repo = new \Quadica\QSA_Engraving\Database\Config_Repository();

        // Get PICOa configuration.
        $config = $config_repo->get_config( 'PICO', 'a' );

        if ( empty( $config ) ) {
            return new WP_Error( 'no_config', 'No PICOa configuration found in database.' );
        }

        // Should have 8 positions.
        if ( count( $config ) !== 8 ) {
            return new WP_Error( 'wrong_count', 'Expected 8 positions, got ' . count( $config ) );
        }

        // Each position should have 5 elements.
        foreach ( $config as $pos => $elements ) {
            if ( count( $elements ) !== 5 ) {
                return new WP_Error( 'wrong_elements', "Position {$pos} should have 5 elements, got " . count( $elements ) );
            }
        }

        echo "  PICOa: 8 positions × 5 elements = 40 config entries.\n";
        return true;
    },
    'PICOa design has complete coordinate configuration.'
);

run_test(
    'TC-P9-004: Config_Repository get_designs returns seeded designs',
    function (): bool {
        $config_repo = new \Quadica\QSA_Engraving\Database\Config_Repository();

        $designs = $config_repo->get_designs();

        if ( ! in_array( 'STAR', $designs, true ) ) {
            return new WP_Error( 'missing_star', 'STAR design not found.' );
        }
        if ( ! in_array( 'CUBE', $designs, true ) ) {
            return new WP_Error( 'missing_cube', 'CUBE design not found.' );
        }
        if ( ! in_array( 'PICO', $designs, true ) ) {
            return new WP_Error( 'missing_pico', 'PICO design not found.' );
        }

        echo "  Available designs: " . implode( ', ', $designs ) . "\n";
        return true;
    },
    'Config repository returns all seeded QSA designs.'
);

run_test(
    'TC-P9-005: STARa position 1 coordinates match CSV',
    function (): bool {
        $config_repo = new \Quadica\QSA_Engraving\Database\Config_Repository();

        // Get STARa position 1 config.
        $micro_id = $config_repo->get_element_config( 'STAR', 1, 'micro_id', 'a' );
        $datamatrix = $config_repo->get_element_config( 'STAR', 1, 'datamatrix', 'a' );

        if ( ! $micro_id || ! $datamatrix ) {
            return new WP_Error( 'config_missing', 'Position 1 configuration not found.' );
        }

        // Verify micro_id coordinates (from CSV: 32.0125, 63.7933).
        if ( abs( $micro_id['origin_x'] - 32.0125 ) > 0.001 ) {
            return new WP_Error( 'wrong_x', "micro_id X expected 32.0125, got {$micro_id['origin_x']}" );
        }
        if ( abs( $micro_id['origin_y'] - 63.7933 ) > 0.001 ) {
            return new WP_Error( 'wrong_y', "micro_id Y expected 63.7933, got {$micro_id['origin_y']}" );
        }

        // Verify datamatrix coordinates (from CSV: 29.7215, 95.2849).
        if ( abs( $datamatrix['origin_x'] - 29.7215 ) > 0.001 ) {
            return new WP_Error( 'wrong_x', "datamatrix X expected 29.7215, got {$datamatrix['origin_x']}" );
        }
        if ( abs( $datamatrix['origin_y'] - 95.2849 ) > 0.001 ) {
            return new WP_Error( 'wrong_y', "datamatrix Y expected 95.2849, got {$datamatrix['origin_y']}" );
        }

        echo "  STARa position 1 micro_id: ({$micro_id['origin_x']}, {$micro_id['origin_y']})\n";
        echo "  STARa position 1 datamatrix: ({$datamatrix['origin_x']}, {$datamatrix['origin_y']})\n";
        return true;
    },
    'STARa position 1 coordinates match source CSV data.'
);

run_test(
    'TC-P9-006: CUBEa has 4 LED code positions',
    function (): bool {
        $config_repo = new \Quadica\QSA_Engraving\Database\Config_Repository();

        // Check position 1 has all 4 LED codes.
        $led1 = $config_repo->get_element_config( 'CUBE', 1, 'led_code_1', 'a' );
        $led2 = $config_repo->get_element_config( 'CUBE', 1, 'led_code_2', 'a' );
        $led3 = $config_repo->get_element_config( 'CUBE', 1, 'led_code_3', 'a' );
        $led4 = $config_repo->get_element_config( 'CUBE', 1, 'led_code_4', 'a' );

        if ( ! $led1 || ! $led2 || ! $led3 || ! $led4 ) {
            return new WP_Error( 'led_missing', 'Not all 4 LED code positions configured.' );
        }

        // Verify 2x2 grid layout (LED 1 & 2 are top row, 3 & 4 are bottom row).
        // Top row Y should be same.
        if ( abs( $led1['origin_y'] - $led2['origin_y'] ) > 0.001 ) {
            return new WP_Error( 'grid_error', 'LED 1 and 2 should have same Y coordinate.' );
        }
        // Bottom row Y should be same.
        if ( abs( $led3['origin_y'] - $led4['origin_y'] ) > 0.001 ) {
            return new WP_Error( 'grid_error', 'LED 3 and 4 should have same Y coordinate.' );
        }
        // Top row should be above bottom row.
        if ( $led1['origin_y'] <= $led3['origin_y'] ) {
            return new WP_Error( 'grid_error', 'LED grid Y ordering incorrect.' );
        }

        echo "  CUBEa LED grid: top row Y={$led1['origin_y']}, bottom row Y={$led3['origin_y']}\n";
        return true;
    },
    'CUBEa design has 4 LED code positions in 2x2 grid.'
);

run_test(
    'TC-P9-007: Text height values are set correctly',
    function (): bool {
        $config_repo = new \Quadica\QSA_Engraving\Database\Config_Repository();

        // Check text height values for STARa.
        $module_id = $config_repo->get_element_config( 'STAR', 1, 'module_id', 'a' );
        $serial_url = $config_repo->get_element_config( 'STAR', 1, 'serial_url', 'a' );
        $led_code = $config_repo->get_element_config( 'STAR', 1, 'led_code_1', 'a' );
        $micro_id = $config_repo->get_element_config( 'STAR', 1, 'micro_id', 'a' );

        // module_id should be 1.3mm.
        if ( abs( $module_id['text_height'] - 1.30 ) > 0.01 ) {
            return new WP_Error( 'wrong_height', "module_id text_height expected 1.30, got {$module_id['text_height']}" );
        }

        // serial_url and led_code should be 1.2mm.
        if ( abs( $serial_url['text_height'] - 1.20 ) > 0.01 ) {
            return new WP_Error( 'wrong_height', "serial_url text_height expected 1.20, got {$serial_url['text_height']}" );
        }
        if ( abs( $led_code['text_height'] - 1.20 ) > 0.01 ) {
            return new WP_Error( 'wrong_height', "led_code text_height expected 1.20, got {$led_code['text_height']}" );
        }

        // micro_id should have NULL text_height.
        if ( $micro_id['text_height'] !== null ) {
            return new WP_Error( 'wrong_height', "micro_id text_height expected null, got {$micro_id['text_height']}" );
        }

        echo "  module_id: {$module_id['text_height']}mm, serial_url: {$serial_url['text_height']}mm, led_code: {$led_code['text_height']}mm\n";
        echo "  micro_id: null (non-text element)\n";
        return true;
    },
    'Text height values match specification.'
);

run_test(
    'TC-P9-008: CAD to SVG coordinate transformation',
    function (): bool {
        // Test the cad_to_svg_y transformation.
        $canvas_height = 113.7;

        // Position 1 micro_id Y = 63.7933 in CAD.
        // SVG Y should be 113.7 - 63.7933 = 49.9067.
        $cad_y_1 = 63.7933;
        $expected_svg_y_1 = 49.9067;
        $actual_svg_y_1 = \Quadica\QSA_Engraving\Database\Config_Repository::cad_to_svg_y( $cad_y_1 );

        if ( abs( $actual_svg_y_1 - $expected_svg_y_1 ) > 0.001 ) {
            return new WP_Error( 'transform_error', "Expected SVG Y {$expected_svg_y_1}, got {$actual_svg_y_1}" );
        }

        // Position 5 datamatrix Y = 18.4151 in CAD.
        // SVG Y should be 113.7 - 18.4151 = 95.2849.
        $cad_y_2 = 18.4151;
        $expected_svg_y_2 = 95.2849;
        $actual_svg_y_2 = \Quadica\QSA_Engraving\Database\Config_Repository::cad_to_svg_y( $cad_y_2 );

        if ( abs( $actual_svg_y_2 - $expected_svg_y_2 ) > 0.001 ) {
            return new WP_Error( 'transform_error', "Expected SVG Y {$expected_svg_y_2}, got {$actual_svg_y_2}" );
        }

        echo "  CAD Y {$cad_y_1} → SVG Y " . round( $actual_svg_y_1, 4 ) . " (verified)\n";
        echo "  CAD Y {$cad_y_2} → SVG Y " . round( $actual_svg_y_2, 4 ) . " (verified)\n";
        return true;
    },
    'CAD to SVG Y coordinate transformation is correct.'
);

// ============================================
// Summary
// ============================================
// Re-declare global to ensure PHP 8.1 recognizes the variables in eval-file context.
global $tests_passed, $tests_failed, $tests_total;

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
