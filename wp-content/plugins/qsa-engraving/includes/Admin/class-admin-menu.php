<?php
/**
 * Admin Menu Handler.
 *
 * Registers and manages the QSA Engraving admin menu items.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin menu registration and page rendering.
 *
 * @since 1.0.0
 */
class Admin_Menu {

    /**
     * Required capability for accessing the plugin.
     *
     * Note: Per discovery doc, access is granted to Administrator, Manager, and
     * Shop Manager roles. All three roles have the 'manage_woocommerce' capability:
     * - Administrator: Core WP role with all capabilities
     * - Manager: Custom role typically configured with manage_woocommerce
     * - Shop Manager: WooCommerce's built-in role with manage_woocommerce
     *
     * @var string
     */
    public const REQUIRED_CAPABILITY = 'manage_woocommerce';

    /**
     * Menu slug for the main page.
     *
     * @var string
     */
    public const MENU_SLUG = 'qsa-engraving';

    /**
     * Register the admin menu.
     *
     * @return void
     */
    public function register(): void {
        // Add QSA Engraving as a top-level menu, positioned after WooCommerce (position 55).
        add_menu_page(
            __( 'QSA Engraving', 'qsa-engraving' ),
            __( 'QSA Engraving', 'qsa-engraving' ),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG,
            array( $this, 'render_main_page' ),
            'dashicons-art',
            56
        );

        // Add submenu pages under QSA Engraving top-level menu.
        // First submenu repeats the parent to set "Dashboard" as the first item.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Dashboard', 'qsa-engraving' ),
            __( 'Dashboard', 'qsa-engraving' ),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG,
            array( $this, 'render_main_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Batch Creator', 'qsa-engraving' ),
            __( 'Batch Creator', 'qsa-engraving' ),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-batch-creator',
            array( $this, 'render_batch_creator_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Engraving Queue', 'qsa-engraving' ),
            __( 'Engraving Queue', 'qsa-engraving' ),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-queue',
            array( $this, 'render_queue_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Batch History', 'qsa-engraving' ),
            __( 'Batch History', 'qsa-engraving' ),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-history',
            array( $this, 'render_history_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'SKU Mappings', 'qsa-engraving' ),
            __( 'SKU Mappings', 'qsa-engraving' ),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-sku-mappings',
            array( $this, 'render_sku_mappings_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Tweak Coords', 'qsa-engraving' ),
            __( 'Tweak Coords', 'qsa-engraving' ),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-tweak-coords',
            array( $this, 'render_tweak_coords_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Settings', 'qsa-engraving' ),
            __( 'Settings', 'qsa-engraving' ),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Check if the current user has the required capability.
     *
     * @return bool
     */
    public function user_has_access(): bool {
        return current_user_can( self::REQUIRED_CAPABILITY );
    }

    /**
     * Check if we're in a development/staging environment.
     *
     * Returns true if:
     * - WP_DEBUG is enabled, OR
     * - Host contains 'env-', 'staging', 'dev', or 'test'
     *
     * @return bool
     */
    private function is_development_environment(): bool {
        // Check WP_DEBUG.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return true;
        }

        // Check for staging/dev hostnames.
        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
        $dev_patterns = array( 'env-', 'staging', '.dev', '.test', 'localhost', '.local' );

        foreach ( $dev_patterns as $pattern ) {
            if ( stripos( $host, $pattern ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render the main admin page (Dashboard).
     *
     * @return void
     */
    public function render_main_page(): void {
        if ( ! $this->user_has_access() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'qsa-engraving' ) );
        }

        $this->render_page_header( __( 'QSA Engraving Dashboard', 'qsa-engraving' ) );
        $this->render_dashboard_content();
        $this->render_page_footer();
    }

    /**
     * Render the Batch Creator page.
     *
     * This page uses a headerless layout - the React component provides
     * its own header that matches the mockup design specification.
     *
     * @return void
     */
    public function render_batch_creator_page(): void {
        if ( ! $this->user_has_access() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'qsa-engraving' ) );
        }

        // Minimal wrapper - React component provides its own header.
        ?>
        <div class="wrap qsa-engraving-wrap">
            <hr class="wp-header-end">
        <?php
        $this->render_react_container( 'batch-creator' );
        $this->render_page_footer();
    }

    /**
     * Render the Engraving Queue page.
     *
     * @return void
     */
    public function render_queue_page(): void {
        if ( ! $this->user_has_access() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'qsa-engraving' ) );
        }

        // Queue page has its own header in the React component, skip WordPress header.
        ?>
        <div class="wrap qsa-engraving-wrap">
        <?php
        $this->render_react_container( 'engraving-queue' );
        $this->render_page_footer();
    }

    /**
     * Render the Batch History page.
     *
     * @return void
     */
    public function render_history_page(): void {
        if ( ! $this->user_has_access() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'qsa-engraving' ) );
        }

        // History page has its own header in the React component, skip WordPress header.
        ?>
        <div class="wrap qsa-engraving-wrap">
        <?php
        $this->render_react_container( 'batch-history' );
        $this->render_page_footer();
    }

    /**
     * Render the SKU Mappings page.
     *
     * @return void
     */
    public function render_sku_mappings_page(): void {
        if ( ! $this->user_has_access() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'qsa-engraving' ) );
        }

        $this->render_page_header( __( 'SKU Mappings', 'qsa-engraving' ) );
        $this->render_sku_mappings_content();
        $this->render_page_footer();
    }

    /**
     * Render the Tweak Coords page.
     *
     * @return void
     */
    public function render_tweak_coords_page(): void {
        if ( ! $this->user_has_access() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'qsa-engraving' ) );
        }

        $this->render_page_header( __( 'Tweak Coords', 'qsa-engraving' ) );
        $this->render_tweak_coords_content();
        $this->render_page_footer();
    }

    /**
     * Render the Settings page.
     *
     * @return void
     */
    public function render_settings_page(): void {
        if ( ! $this->user_has_access() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'qsa-engraving' ) );
        }

        $this->render_page_header( __( 'QSA Engraving Settings', 'qsa-engraving' ) );
        $this->render_settings_content();
        $this->render_page_footer();
    }

    /**
     * Render the page header.
     *
     * @param string $title The page title.
     * @return void
     */
    private function render_page_header( string $title ): void {
        ?>
        <div class="wrap qsa-engraving-wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
            <hr class="wp-header-end">
        <?php
    }

    /**
     * Render the page footer.
     *
     * @return void
     */
    private function render_page_footer(): void {
        ?>
        </div><!-- .wrap -->
        <?php
    }

    /**
     * Render a React container for a specific app.
     *
     * @param string $app_id The React app identifier.
     * @return void
     */
    private function render_react_container( string $app_id ): void {
        ?>
        <div id="qsa-engraving-<?php echo esc_attr( $app_id ); ?>" class="qsa-engraving-app">
            <div class="qsa-engraving-loading">
                <span class="spinner is-active"></span>
                <p><?php esc_html_e( 'Loading...', 'qsa-engraving' ); ?></p>
            </div>
        </div>
        <noscript>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'JavaScript is required to use QSA Engraving. Please enable JavaScript in your browser.', 'qsa-engraving' ); ?></p>
            </div>
        </noscript>
        <?php
    }

    /**
     * Render the dashboard content.
     *
     * @return void
     */
    private function render_dashboard_content(): void {
        // Get capacity info.
        $plugin          = \Quadica\QSA_Engraving\qsa_engraving();
        $serial_repo     = $plugin->get_serial_repository();
        $batch_repo      = $plugin->get_batch_repository();

        // Check if tables exist.
        $tables_exist = $serial_repo->table_exists()
            && $batch_repo->batches_table_exists()
            && $batch_repo->modules_table_exists();

        if ( ! $tables_exist ) {
            $this->render_database_setup_notice();
            return;
        }

        $capacity = $serial_repo->get_capacity();
        ?>
        <div class="qsa-engraving-dashboard">
            <!-- Capacity Widget -->
            <div class="qsa-widget qsa-capacity-widget <?php echo $capacity['critical'] ? 'critical' : ( $capacity['warning'] ? 'warning' : '' ); ?>">
                <h2><?php esc_html_e( 'Serial Number Capacity', 'qsa-engraving' ); ?></h2>
                <div class="capacity-stats">
                    <div class="stat">
                        <span class="stat-value"><?php echo esc_html( number_format( $capacity['remaining'] ) ); ?></span>
                        <span class="stat-label"><?php esc_html_e( 'Remaining', 'qsa-engraving' ); ?></span>
                    </div>
                    <div class="stat">
                        <span class="stat-value"><?php echo esc_html( $capacity['percentage_remaining'] ); ?>%</span>
                        <span class="stat-label"><?php esc_html_e( 'Available', 'qsa-engraving' ); ?></span>
                    </div>
                    <div class="stat">
                        <span class="stat-value"><?php echo esc_html( number_format( $capacity['highest_assigned'] ) ); ?></span>
                        <span class="stat-label"><?php esc_html_e( 'Highest Assigned', 'qsa-engraving' ); ?></span>
                    </div>
                </div>
                <?php if ( $capacity['critical'] ) : ?>
                    <div class="notice notice-error inline">
                        <p><strong><?php esc_html_e( 'Critical:', 'qsa-engraving' ); ?></strong>
                        <?php esc_html_e( 'Serial number capacity is critically low. Contact support.', 'qsa-engraving' ); ?></p>
                    </div>
                <?php elseif ( $capacity['warning'] ) : ?>
                    <div class="notice notice-warning inline">
                        <p><strong><?php esc_html_e( 'Warning:', 'qsa-engraving' ); ?></strong>
                        <?php esc_html_e( 'Serial number capacity is running low.', 'qsa-engraving' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="qsa-widget qsa-actions-widget">
                <h2><?php esc_html_e( 'Quick Actions', 'qsa-engraving' ); ?></h2>
                <div class="action-buttons">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-batch-creator' ) ); ?>" class="button button-primary button-hero">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Create Engraving Batch', 'qsa-engraving' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-queue' ) ); ?>" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e( 'View Engraving Queue', 'qsa-engraving' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-history' ) ); ?>" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-backup"></span>
                        <?php esc_html_e( 'Batch History', 'qsa-engraving' ); ?>
                    </a>
                </div>
                <?php if ( $this->is_development_environment() ) : ?>
                <div class="action-buttons" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <button type="button" id="qsa-clear-test-data" class="button button-secondary" style="color: #a00;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e( 'Clear All Test Data', 'qsa-engraving' ); ?>
                    </button>
                    <span id="qsa-clear-result" style="margin-left: 10px;"></span>
                </div>
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#qsa-clear-test-data').on('click', function() {
                        if (!confirm('<?php echo esc_js( __( 'This will DELETE all engraving batches, modules, and serial numbers. This cannot be undone!\n\nAre you sure?', 'qsa-engraving' ) ); ?>')) {
                            return;
                        }
                        var $btn = $(this);
                        var $result = $('#qsa-clear-result');
                        $btn.prop('disabled', true);
                        $result.text('<?php echo esc_js( __( 'Clearing...', 'qsa-engraving' ) ); ?>');
                        $.post('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
                            action: 'qsa_clear_test_data',
                            nonce: '<?php echo esc_js( wp_create_nonce( 'qsa_engraving_nonce' ) ); ?>'
                        }, function(response) {
                            $btn.prop('disabled', false);
                            if (response.success) {
                                $result.css('color', 'green').text(response.message || '<?php echo esc_js( __( 'Cleared!', 'qsa-engraving' ) ); ?>');
                                setTimeout(function() { location.reload(); }, 1000);
                            } else {
                                $result.css('color', 'red').text(response.message || '<?php echo esc_js( __( 'Failed', 'qsa-engraving' ) ); ?>');
                            }
                        }).fail(function() {
                            $btn.prop('disabled', false);
                            $result.css('color', 'red').text('<?php echo esc_js( __( 'Request failed', 'qsa-engraving' ) ); ?>');
                        });
                    });
                });
                </script>
                <?php endif; ?>
            </div>

            <!-- System Status -->
            <?php
            $system_settings = get_option( 'qsa_engraving_settings', array() );
            $svg_enabled     = ! empty( $system_settings['lightburn_enabled'] );
            $keep_svg_files  = ! empty( $system_settings['keep_svg_files'] );
            ?>
            <div class="qsa-widget qsa-status-widget">
                <h2><?php esc_html_e( 'System Status', 'qsa-engraving' ); ?></h2>
                <table class="widefat striped qsa-status-table">
                    <tbody>
                        <tr>
                            <td class="qsa-status-label"><?php esc_html_e( 'Plugin Version', 'qsa-engraving' ); ?></td>
                            <td><code><?php echo esc_html( QSA_ENGRAVING_VERSION ); ?></code></td>
                        </tr>
                        <tr>
                            <td class="qsa-status-label">
                                <label for="qsa-toggle-svg-generation"><?php esc_html_e( 'SVG Generation', 'qsa-engraving' ); ?></label>
                            </td>
                            <td>
                                <label class="qsa-toggle-switch">
                                    <input type="checkbox" id="qsa-toggle-svg-generation" <?php checked( $svg_enabled ); ?>>
                                    <span class="qsa-toggle-slider"></span>
                                </label>
                                <span class="qsa-toggle-status" id="qsa-svg-generation-status"></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="qsa-status-label">
                                <label for="qsa-toggle-keep-svg"><?php esc_html_e( 'Keep SVG Files', 'qsa-engraving' ); ?></label>
                            </td>
                            <td>
                                <label class="qsa-toggle-switch">
                                    <input type="checkbox" id="qsa-toggle-keep-svg" <?php checked( $keep_svg_files ); ?>>
                                    <span class="qsa-toggle-slider"></span>
                                </label>
                                <span class="qsa-toggle-status" id="qsa-keep-svg-status"></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <style>
                .qsa-status-table .qsa-status-label {
                    white-space: nowrap;
                    width: 120px;
                }
                .qsa-toggle-switch {
                    position: relative;
                    display: inline-block;
                    width: 44px;
                    height: 24px;
                    vertical-align: middle;
                }
                .qsa-toggle-switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                .qsa-toggle-slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: .3s;
                    border-radius: 24px;
                }
                .qsa-toggle-slider:before {
                    position: absolute;
                    content: "";
                    height: 18px;
                    width: 18px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: .3s;
                    border-radius: 50%;
                }
                .qsa-toggle-switch input:checked + .qsa-toggle-slider {
                    background-color: #2271b1;
                }
                .qsa-toggle-switch input:checked + .qsa-toggle-slider:before {
                    transform: translateX(20px);
                }
                .qsa-toggle-status {
                    margin-left: 8px;
                    font-size: 12px;
                    color: #666;
                }
                .qsa-toggle-status.saving {
                    color: #666;
                }
                .qsa-toggle-status.saved {
                    color: #00a32a;
                }
                .qsa-toggle-status.error {
                    color: #d63638;
                }
            </style>
            <script>
            jQuery(function($) {
                var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
                var nonce = '<?php echo esc_js( wp_create_nonce( 'qsa_engraving_nonce' ) ); ?>';

                function saveToggle(settingName, value, $status) {
                    $status.removeClass('saved error').addClass('saving').text('<?php echo esc_js( __( 'Saving...', 'qsa-engraving' ) ); ?>');

                    var data = {
                        action: 'qsa_save_lightburn_settings',
                        nonce: nonce
                    };
                    data[settingName] = value ? 1 : 0;

                    $.post(ajaxUrl, data, function(response) {
                        if (response.success) {
                            $status.removeClass('saving').addClass('saved').text('<?php echo esc_js( __( 'Saved', 'qsa-engraving' ) ); ?>');
                            setTimeout(function() { $status.text(''); }, 2000);
                        } else {
                            $status.removeClass('saving').addClass('error').text('<?php echo esc_js( __( 'Error', 'qsa-engraving' ) ); ?>');
                        }
                    }).fail(function() {
                        $status.removeClass('saving').addClass('error').text('<?php echo esc_js( __( 'Failed', 'qsa-engraving' ) ); ?>');
                    });
                }

                // SVG Generation toggle
                $('#qsa-toggle-svg-generation').on('change', function() {
                    saveToggle('lightburn_enabled', $(this).is(':checked'), $('#qsa-svg-generation-status'));
                });

                // Keep SVG Files toggle
                $('#qsa-toggle-keep-svg').on('change', function() {
                    saveToggle('keep_svg_files', $(this).is(':checked'), $('#qsa-keep-svg-status'));
                });
            });
            </script>

            <!-- Help, Troubleshooting & Setup Panels Row -->
            <div class="qsa-help-panels-row">
                <?php $this->render_help_panel(); ?>
                <?php $this->render_qsa_positions_panel(); ?>
                <?php $this->render_troubleshooting_panel(); ?>
                <?php $this->render_setup_guide_panel(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Help & Information Panel.
     *
     * @return void
     */
    private function render_help_panel(): void {
        ?>
        <div class="qsa-widget qsa-help-panel">
            <h2><?php esc_html_e( 'Help & Information', 'qsa-engraving' ); ?></h2>
            <ol class="qsa-process-steps">
                <li><strong><?php esc_html_e( 'Select Modules', 'qsa-engraving' ); ?></strong> &ndash; <?php esc_html_e( 'Use the Batch Creator to select modules from the "Awaiting Engraving" list.', 'qsa-engraving' ); ?></li>
                <li><strong><?php esc_html_e( 'Create Batch', 'qsa-engraving' ); ?></strong> &ndash; <?php esc_html_e( 'Click "Create Batch" to assign serial numbers and group modules into arrays.', 'qsa-engraving' ); ?></li>
                <li><strong><?php esc_html_e( 'Go to Engraving Queue', 'qsa-engraving' ); ?></strong> &ndash; <?php esc_html_e( 'Navigate to the queue to see your batch ready for engraving.', 'qsa-engraving' ); ?></li>
                <li><strong><?php esc_html_e( 'Start Engraving', 'qsa-engraving' ); ?></strong> &ndash; <?php esc_html_e( 'Click "Engrave" to generate the SVG and send it to LightBurn.', 'qsa-engraving' ); ?></li>
                <li><strong><?php esc_html_e( 'Engrave Array', 'qsa-engraving' ); ?></strong> &ndash; <?php esc_html_e( 'Use the foot switch on the laser to engrave the array.', 'qsa-engraving' ); ?></li>
                <li><strong><?php esc_html_e( 'Advance', 'qsa-engraving' ); ?></strong> &ndash; <?php esc_html_e( 'Click "Next Array" to load the next SVG.', 'qsa-engraving' ); ?></li>
                <li><strong><?php esc_html_e( 'Complete', 'qsa-engraving' ); ?></strong> &ndash; <?php esc_html_e( 'After the final array, click "Complete" to finish the batch.', 'qsa-engraving' ); ?></li>
            </ol>
        </div>
        <?php
    }

    /**
     * Render the QSA Positions Panel.
     *
     * @return void
     */
    private function render_qsa_positions_panel(): void {
        ?>
        <div class="qsa-widget qsa-positions-panel">
            <h2><?php esc_html_e( 'Defining/Tweaking QSA Engraving Positions', 'qsa-engraving' ); ?></h2>

            <div class="qsa-positions-section">
                <h4><?php esc_html_e( 'Step 1: Generate CSV from AutoCAD', 'qsa-engraving' ); ?></h4>
                <ol>
                    <li><?php esc_html_e( 'Open the QSA design drawing in AutoCAD.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Ensure all engraving elements are on the "Q-Engrave" layer as MTEXT.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Place a 5-character design ID (e.g., "SZ04a") at UCS origin (0,0).', 'qsa-engraving' ); ?></li>
                    <li>
                        <?php esc_html_e( 'In AutoCAD click on the', 'qsa-engraving' ); ?>
                        <code>LISP > QSA Export</code>
                        <?php esc_html_e( 'option.', 'qsa-engraving' ); ?>
                    </li>
                    <li><?php esc_html_e( 'A CSV file will be created in the drawing folder.', 'qsa-engraving' ); ?></li>
                </ol>
            </div>

            <div class="qsa-positions-section">
                <h4><?php esc_html_e( 'Step 2: Upload CSV to WordPress', 'qsa-engraving' ); ?></h4>
                <ol>
                    <li>
                        <?php
                        printf(
                            /* translators: %s: Settings link */
                            esc_html__( 'Go to %s.', 'qsa-engraving' ),
                            '<a href="' . esc_url( admin_url( 'admin.php?page=qsa-engraving-settings' ) ) . '">' .
                            esc_html__( 'QSA Engraving > Settings', 'qsa-engraving' ) . '</a>'
                        );
                        ?>
                    </li>
                    <li><?php esc_html_e( 'Scroll to "QSA Configuration Import" section.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Click "Select CSV File" and choose the exported file.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Review the preview (new/updated/deleted counts).', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Click "Apply Import" to save the configuration.', 'qsa-engraving' ); ?></li>
                </ol>
            </div>

            <div class="qsa-positions-section">
                <h4><?php esc_html_e( 'Step 3: Fine-Tune with Tweak Coords', 'qsa-engraving' ); ?></h4>
                <ol>
                    <li>
                        <?php
                        printf(
                            /* translators: %s: Tweak Coords link */
                            esc_html__( 'Go to %s.', 'qsa-engraving' ),
                            '<a href="' . esc_url( admin_url( 'admin.php?page=qsa-engraving-tweak' ) ) . '">' .
                            esc_html__( 'QSA Engraving > Tweak Coords', 'qsa-engraving' ) . '</a>'
                        );
                        ?>
                    </li>
                    <li><?php esc_html_e( 'Select the design and revision to adjust.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Modify X/Y coordinates or rotation for any element.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Use for small adjustments (< 1mm) after test engraving.', 'qsa-engraving' ); ?></li>
                </ol>
            </div>

            <p class="description">
                <strong><?php esc_html_e( 'Tip:', 'qsa-engraving' ); ?></strong>
                <?php esc_html_e( 'Use QSADEBUG in AutoCAD to verify element positions before exporting.', 'qsa-engraving' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render the Troubleshooting Guide Panel.
     *
     * @return void
     */
    private function render_troubleshooting_panel(): void {
        ?>
        <div class="qsa-widget qsa-troubleshooting-panel">
            <h2><?php esc_html_e( 'Troubleshooting Guide', 'qsa-engraving' ); ?></h2>

            <div class="qsa-troubleshoot-item">
                <h4><?php esc_html_e( 'SVG Not Appearing in LightBurn', 'qsa-engraving' ); ?></h4>
                <ul>
                    <li><?php esc_html_e( 'Check that LightBurn is running on the production computer.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Verify the LightBurn SFTP Watcher is running (tasklist | findstr node).', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Check the log file at C:\\Users\\Production\\LightBurn\\lightburn-watcher.log for errors.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Try clicking "Resend" to re-send the current SVG file.', 'qsa-engraving' ); ?></li>
                </ul>
            </div>

            <div class="qsa-troubleshoot-item">
                <h4><?php esc_html_e( 'No Modules Available to Engrave', 'qsa-engraving' ); ?></h4>
                <ul>
                    <li><?php esc_html_e( 'Verify modules exist in active production batches.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Check that the modules haven\'t already been engraved.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Refresh the module list using the refresh button.', 'qsa-engraving' ); ?></li>
                </ul>
            </div>

            <div class="qsa-troubleshoot-item">
                <h4><?php esc_html_e( 'LED Code Errors', 'qsa-engraving' ); ?></h4>
                <ul>
                    <li><?php esc_html_e( 'Ensure the Order BOM has LED information populated.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Verify LED products have the led_shortcode_3 field set.', 'qsa-engraving' ); ?></li>
                </ul>
            </div>

            <div class="qsa-troubleshoot-item">
                <h4><?php esc_html_e( 'Batch Creation Fails', 'qsa-engraving' ); ?></h4>
                <ul>
                    <li><?php esc_html_e( 'Check for validation errors in the error message.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Ensure all selected modules have valid configurations.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Verify the QSA design has coordinate data configured.', 'qsa-engraving' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Setup Guide Panel.
     *
     * @return void
     */
    private function render_setup_guide_panel(): void {
        ?>
        <div class="qsa-widget qsa-setup-panel">
            <h2><?php esc_html_e( 'Setup Guide', 'qsa-engraving' ); ?></h2>

            <div class="qsa-setup-item">
                <h4><?php esc_html_e( 'System Overview', 'qsa-engraving' ); ?></h4>
                <ul>
                    <li><?php esc_html_e( 'The QSA Engraving plugin generates SVG files for UV laser engraving.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'SVG files are saved to the server and delivered to LightBurn via the SFTP watcher.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'The watcher is a Node.js script that auto-starts on the production workstation.', 'qsa-engraving' ); ?></li>
                    <li><?php esc_html_e( 'Serial numbers are stored in WordPress database tables.', 'qsa-engraving' ); ?></li>
                </ul>
            </div>

            <div class="qsa-setup-item">
                <h4><?php esc_html_e( 'LightBurn SFTP Watcher', 'qsa-engraving' ); ?></h4>
                <table class="qsa-setup-table">
                    <tr>
                        <td><strong><?php esc_html_e( 'Install Location:', 'qsa-engraving' ); ?></strong></td>
                        <td><code>C:\Users\Production\LightBurn\lightburn-watcher\</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Delivery Path:', 'qsa-engraving' ); ?></strong></td>
                        <td><code>C:\Users\Production\LightBurn\Incoming</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Log File:', 'qsa-engraving' ); ?></strong></td>
                        <td><code>C:\Users\Production\LightBurn\lightburn-watcher.log</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'State File:', 'qsa-engraving' ); ?></strong></td>
                        <td><code>C:\Users\Production\.lightburn-watcher-state.json</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Poll Interval:', 'qsa-engraving' ); ?></strong></td>
                        <td><?php esc_html_e( '3 seconds', 'qsa-engraving' ); ?></td>
                    </tr>
                </table>
            </div>

            <div class="qsa-setup-item">
                <h4><?php esc_html_e( 'Managing the Watcher', 'qsa-engraving' ); ?></h4>
                <p class="description"><?php esc_html_e( 'The watcher is a Node.js script that auto-starts via Windows Startup folder:', 'qsa-engraving' ); ?></p>
                <table class="qsa-setup-table">
                    <tr>
                        <td><strong><?php esc_html_e( 'Check Status:', 'qsa-engraving' ); ?></strong></td>
                        <td><code>tasklist | findstr node</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Start:', 'qsa-engraving' ); ?></strong></td>
                        <td><?php esc_html_e( 'Double-click', 'qsa-engraving' ); ?> <code>start-watcher-hidden.vbs</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Stop:', 'qsa-engraving' ); ?></strong></td>
                        <td><code>wmic process where "commandline like '%%lightburn-watcher-service%%'" call terminate</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'View Logs:', 'qsa-engraving' ); ?></strong></td>
                        <td><code>Get-Content ~\LightBurn\lightburn-watcher.log -Tail 50 -Wait</code></td>
                    </tr>
                </table>
            </div>

            <div class="qsa-setup-item">
                <h4><?php esc_html_e( 'Service Configuration', 'qsa-engraving' ); ?></h4>
                <p class="description"><?php esc_html_e( 'Edit lightburn-watcher-service.js to adjust these values:', 'qsa-engraving' ); ?></p>
                <table class="qsa-setup-table">
                    <tr>
                        <td><code>CONFIG.pollInterval</code></td>
                        <td><?php esc_html_e( 'How often to check for new files (default: 3000ms)', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>CONFIG.remoteDir</code></td>
                        <td><?php esc_html_e( 'Path on server for SVG files', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>CONFIG.localDir</code></td>
                        <td><?php esc_html_e( 'Where files are placed for LightBurn', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>CONFIG.sftp.*</code></td>
                        <td><?php esc_html_e( 'SFTP connection details (host, port, username)', 'qsa-engraving' ); ?></td>
                    </tr>
                </table>
                <p class="description" style="margin-top: 8px;">
                    <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                    <?php esc_html_e( 'After changing configuration, restart the watcher for changes to take effect.', 'qsa-engraving' ); ?>
                </p>
            </div>

            <div class="qsa-setup-item">
                <h4><?php esc_html_e( 'Database Tables', 'qsa-engraving' ); ?></h4>
                <table class="qsa-setup-table">
                    <tr>
                        <td><code>lw_quad_serial_numbers</code></td>
                        <td><?php esc_html_e( 'Serial number lifecycle tracking', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>lw_quad_engraving_batches</code></td>
                        <td><?php esc_html_e( 'Batch metadata', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>lw_quad_engraved_modules</code></td>
                        <td><?php esc_html_e( 'Module-to-batch linkage', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>lw_quad_qsa_config</code></td>
                        <td><?php esc_html_e( 'Element coordinates per QSA design', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>lw_quad_qsa_identifiers</code></td>
                        <td><?php esc_html_e( 'QSA identifier records', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>lw_quad_qsa_design_sequences</code></td>
                        <td><?php esc_html_e( 'Sequence tracking per QSA design', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>lw_quad_sku_mappings</code></td>
                        <td><?php esc_html_e( 'Legacy SKU to canonical code mappings', 'qsa-engraving' ); ?></td>
                    </tr>
                </table>
            </div>

            <div class="qsa-setup-item">
                <h4><?php esc_html_e( 'LED Shortcodes', 'qsa-engraving' ); ?></h4>
                <p class="description"><?php esc_html_e( 'Each LED product requires a 3-character shortcode for engraving identification.', 'qsa-engraving' ); ?></p>
                <table class="qsa-setup-table">
                    <tr>
                        <td><strong><?php esc_html_e( 'Length:', 'qsa-engraving' ); ?></strong></td>
                        <td><?php esc_html_e( 'Exactly 3 characters', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Allowed:', 'qsa-engraving' ); ?></strong></td>
                        <td><code>1 2 3 4 7 8 9 C E F H J K L P R T</code> <?php esc_html_e( '(17 characters)', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Excluded:', 'qsa-engraving' ); ?></strong></td>
                        <td><?php esc_html_e( '0/O, 5/S, 6/G, 1/I look similar when engraved', 'qsa-engraving' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Examples:', 'qsa-engraving' ); ?></strong></td>
                        <td><code>K7P</code>, <code>C2L</code>, <code>R14</code>, <code>H3T</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Field Name:', 'qsa-engraving' ); ?></strong></td>
                        <td><code>led_shortcode_3</code> <?php esc_html_e( '(on WooCommerce LED products)', 'qsa-engraving' ); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Tweaker Panel.
     *
     * @return void
     */
    private function render_tweaker_panel(): void {
        // Get available QSA designs from database.
        $plugin      = \Quadica\QSA_Engraving\qsa_engraving();
        $config_repo = $plugin->get_config_repository();

        // Get distinct design/revision combinations.
        global $wpdb;
        $table_name = $config_repo->get_table_name();
        $designs    = $wpdb->get_results(
            "SELECT DISTINCT qsa_design, revision,
                    CONCAT(qsa_design, COALESCE(revision, '')) as display_name
             FROM {$table_name}
             WHERE is_active = 1
             ORDER BY qsa_design, revision"
        );
        ?>
        <div class="qsa-widget qsa-tweaker-panel">
            <h2><?php esc_html_e( 'Tweaker', 'qsa-engraving' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Fine-tune element coordinates for laser alignment calibration.', 'qsa-engraving' ); ?></p>

            <div class="qsa-tweaker-controls">
                <div class="qsa-tweaker-row">
                    <label for="qsa-tweaker-design"><?php esc_html_e( 'QSA Design:', 'qsa-engraving' ); ?></label>
                    <select id="qsa-tweaker-design" class="qsa-tweaker-select">
                        <option value=""><?php esc_html_e( '— Select QSA —', 'qsa-engraving' ); ?></option>
                        <?php foreach ( $designs as $design ) : ?>
                            <option value="<?php echo esc_attr( $design->qsa_design . '|' . ( $design->revision ?? '' ) ); ?>">
                                <?php echo esc_html( $design->display_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="qsa-tweaker-row" id="qsa-tweaker-position-row" style="display: none;">
                    <label for="qsa-tweaker-position"><?php esc_html_e( 'Position:', 'qsa-engraving' ); ?></label>
                    <select id="qsa-tweaker-position" class="qsa-tweaker-select">
                        <option value=""><?php esc_html_e( '— Select Position —', 'qsa-engraving' ); ?></option>
                        <option value="0"><?php esc_html_e( '0 (QR Code)', 'qsa-engraving' ); ?></option>
                        <?php for ( $i = 1; $i <= 8; $i++ ) : ?>
                            <option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div id="qsa-tweaker-elements" class="qsa-tweaker-elements" style="display: none;">
                <!-- Elements will be loaded here via AJAX -->
            </div>

            <div id="qsa-tweaker-actions" class="qsa-tweaker-actions" style="display: none;">
                <button type="button" id="qsa-tweaker-save" class="button button-primary">
                    <?php esc_html_e( 'Save Changes', 'qsa-engraving' ); ?>
                </button>
                <span id="qsa-tweaker-status" class="qsa-tweaker-status"></span>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(function($) {
            var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
            var nonce = '<?php echo esc_js( wp_create_nonce( 'qsa_engraving_nonce' ) ); ?>';
            var currentDesign = '';
            var currentRevision = '';
            var currentPosition = 0;

            // Design selection changed
            $('#qsa-tweaker-design').on('change', function() {
                var value = $(this).val();
                $('#qsa-tweaker-elements').hide().empty();
                $('#qsa-tweaker-actions').hide();
                $('#qsa-tweaker-position').val('');

                if (value) {
                    var parts = value.split('|');
                    currentDesign = parts[0];
                    currentRevision = parts[1] || '';
                    $('#qsa-tweaker-position-row').show();
                } else {
                    currentDesign = '';
                    currentRevision = '';
                    $('#qsa-tweaker-position-row').hide();
                }
            });

            // Position selection changed
            $('#qsa-tweaker-position').on('change', function() {
                var position = $(this).val();
                $('#qsa-tweaker-elements').hide().empty();
                $('#qsa-tweaker-actions').hide();

                if (position && currentDesign) {
                    currentPosition = parseInt(position, 10);
                    loadElements();
                }
            });

            // Load elements for current design/position
            function loadElements() {
                var $container = $('#qsa-tweaker-elements');
                $container.html('<p class="loading"><span class="spinner is-active"></span> <?php echo esc_js( __( 'Loading...', 'qsa-engraving' ) ); ?></p>').show();

                $.post(ajaxUrl, {
                    action: 'qsa_get_tweaker_elements',
                    nonce: nonce,
                    design: currentDesign,
                    revision: currentRevision,
                    position: currentPosition
                }, function(response) {
                    if (response.success && response.data.elements) {
                        renderElements(response.data.elements);
                        $('#qsa-tweaker-actions').show();
                    } else {
                        $container.html('<p class="error"><?php echo esc_js( __( 'No configuration found for this design/position.', 'qsa-engraving' ) ); ?></p>');
                    }
                }).fail(function() {
                    $container.html('<p class="error"><?php echo esc_js( __( 'Failed to load configuration.', 'qsa-engraving' ) ); ?></p>');
                });
            }

            // Render element fields
            function renderElements(elements) {
                var $container = $('#qsa-tweaker-elements');
                $container.empty();

                elements.forEach(function(el) {
                    var hasTextHeight = (el.element_type !== 'micro_id' && el.element_type !== 'qr_code');
                    var hasElementSize = (el.element_type === 'qr_code');
                    var elementLabel = el.element_type.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });

                    var html = '<div class="qsa-element-group" data-element="' + el.element_type + '">';
                    html += '<h4 class="qsa-element-title">' + elementLabel + '</h4>';
                    html += '<div class="qsa-element-fields">';

                    // X Position
                    html += '<div class="qsa-field">';
                    html += '<label><?php echo esc_js( __( 'X Position', 'qsa-engraving' ) ); ?></label>';
                    html += '<input type="number" class="qsa-tweaker-input" name="origin_x" value="' + el.origin_x.toFixed(3) + '" step="0.001" min="-50" max="200">';
                    html += '<span class="qsa-unit">mm</span>';
                    html += '</div>';

                    // Y Position
                    html += '<div class="qsa-field">';
                    html += '<label><?php echo esc_js( __( 'Y Position', 'qsa-engraving' ) ); ?></label>';
                    html += '<input type="number" class="qsa-tweaker-input" name="origin_y" value="' + el.origin_y.toFixed(3) + '" step="0.001" min="-50" max="200">';
                    html += '<span class="qsa-unit">mm</span>';
                    html += '</div>';

                    // Rotation
                    html += '<div class="qsa-field">';
                    html += '<label><?php echo esc_js( __( 'Rotation', 'qsa-engraving' ) ); ?></label>';
                    html += '<input type="number" class="qsa-tweaker-input" name="rotation" value="' + el.rotation.toFixed(1) + '" step="0.1" min="-360" max="360">';
                    html += '<span class="qsa-unit">&deg;</span>';
                    html += '</div>';

                    // Text Height (only for text elements)
                    if (hasTextHeight) {
                        var textHeight = el.text_height !== null ? el.text_height.toFixed(2) : '1.20';
                        html += '<div class="qsa-field">';
                        html += '<label><?php echo esc_js( __( 'Text Height', 'qsa-engraving' ) ); ?></label>';
                        html += '<input type="number" class="qsa-tweaker-input" name="text_height" value="' + textHeight + '" step="0.01" min="0.1" max="10">';
                        html += '<span class="qsa-unit">mm</span>';
                        html += '</div>';
                    }

                    // Element Size (for QR code)
                    if (hasElementSize) {
                        var elementSize = el.element_size !== null ? el.element_size.toFixed(2) : '10.00';
                        html += '<div class="qsa-field">';
                        html += '<label><?php echo esc_js( __( 'QR Size', 'qsa-engraving' ) ); ?></label>';
                        html += '<input type="number" class="qsa-tweaker-input" name="element_size" value="' + elementSize + '" step="0.1" min="5" max="30">';
                        html += '<span class="qsa-unit">mm</span>';
                        html += '</div>';
                    }

                    html += '</div>'; // .qsa-element-fields
                    html += '</div>'; // .qsa-element-group

                    $container.append(html);
                });

                $container.show();
            }

            // Save changes
            $('#qsa-tweaker-save').on('click', function() {
                var $btn = $(this);
                var $status = $('#qsa-tweaker-status');
                var elements = [];

                // Gather all element data
                $('#qsa-tweaker-elements .qsa-element-group').each(function() {
                    var $group = $(this);
                    var elementType = $group.data('element');
                    var data = {
                        element_type: elementType,
                        origin_x: parseFloat($group.find('input[name="origin_x"]').val()) || 0,
                        origin_y: parseFloat($group.find('input[name="origin_y"]').val()) || 0,
                        rotation: parseFloat($group.find('input[name="rotation"]').val()) || 0
                    };

                    var $textHeight = $group.find('input[name="text_height"]');
                    if ($textHeight.length) {
                        data.text_height = parseFloat($textHeight.val()) || null;
                    }

                    var $elementSize = $group.find('input[name="element_size"]');
                    if ($elementSize.length) {
                        data.element_size = parseFloat($elementSize.val()) || null;
                    }

                    elements.push(data);
                });

                $btn.prop('disabled', true);
                $status.removeClass('success error').addClass('saving').text('<?php echo esc_js( __( 'Saving...', 'qsa-engraving' ) ); ?>');

                $.post(ajaxUrl, {
                    action: 'qsa_save_tweaker_elements',
                    nonce: nonce,
                    design: currentDesign,
                    revision: currentRevision,
                    position: currentPosition,
                    elements: JSON.stringify(elements)
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $status.removeClass('saving').addClass('success').text('<?php echo esc_js( __( 'Saved!', 'qsa-engraving' ) ); ?>');
                        setTimeout(function() { $status.text('').removeClass('success'); }, 3000);
                    } else {
                        $status.removeClass('saving').addClass('error').text(response.data || '<?php echo esc_js( __( 'Save failed', 'qsa-engraving' ) ); ?>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $status.removeClass('saving').addClass('error').text('<?php echo esc_js( __( 'Request failed', 'qsa-engraving' ) ); ?>');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render the Tweak Coords page content.
     *
     * @return void
     */
    private function render_tweak_coords_content(): void {
        ?>
        <div class="qsa-tweak-coords-wrap">
            <p class="description">
                <?php esc_html_e( 'Fine-tune element coordinates for laser alignment calibration. Select a QSA design and position to adjust X/Y coordinates, rotation, and text height.', 'qsa-engraving' ); ?>
            </p>
            <?php $this->render_tweaker_panel(); ?>
        </div>
        <?php
    }

    /**
     * Render the SKU mappings content.
     *
     * @return void
     */
    private function render_sku_mappings_content(): void {
        $nonce = wp_create_nonce( 'qsa_engraving_nonce' );
        ?>
        <div class="qsa-sku-mappings-wrap">
            <p class="description">
                <?php esc_html_e( 'Manage legacy SKU to canonical 4-letter design code mappings. These mappings enable legacy module SKUs to work with the QSA engraving system.', 'qsa-engraving' ); ?>
            </p>

            <!-- Test Resolution Tool -->
            <div class="qsa-widget qsa-test-resolution">
                <h2><?php esc_html_e( 'Test SKU Resolution', 'qsa-engraving' ); ?></h2>
                <div class="qsa-test-form">
                    <input type="text" id="qsa-test-sku" placeholder="<?php esc_attr_e( 'Enter SKU to test...', 'qsa-engraving' ); ?>" class="regular-text">
                    <button type="button" id="qsa-test-btn" class="button button-secondary">
                        <?php esc_html_e( 'Test Resolution', 'qsa-engraving' ); ?>
                    </button>
                    <span id="qsa-test-status" class="qsa-test-status"></span>
                </div>
                <div id="qsa-test-result" class="qsa-test-result" style="display: none;"></div>
            </div>

            <!-- Add/Edit Form -->
            <div class="qsa-widget qsa-mapping-form-widget">
                <h2 id="qsa-form-title"><?php esc_html_e( 'Add New Mapping', 'qsa-engraving' ); ?></h2>
                <form id="qsa-mapping-form" class="qsa-mapping-form">
                    <input type="hidden" id="qsa-mapping-id" value="">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="qsa-legacy-pattern"><?php esc_html_e( 'Legacy Pattern', 'qsa-engraving' ); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="qsa-legacy-pattern" name="legacy_pattern" class="regular-text" required maxlength="50">
                                <p class="description"><?php esc_html_e( 'The legacy SKU pattern to match (e.g., "SP-01", "SZ-").', 'qsa-engraving' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="qsa-match-type"><?php esc_html_e( 'Match Type', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <select id="qsa-match-type" name="match_type">
                                    <option value="exact"><?php esc_html_e( 'Exact Match', 'qsa-engraving' ); ?></option>
                                    <option value="prefix"><?php esc_html_e( 'Prefix Match', 'qsa-engraving' ); ?></option>
                                    <option value="suffix"><?php esc_html_e( 'Suffix Match', 'qsa-engraving' ); ?></option>
                                    <option value="regex"><?php esc_html_e( 'Regular Expression', 'qsa-engraving' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'How to match SKUs against this pattern.', 'qsa-engraving' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="qsa-canonical-code"><?php esc_html_e( 'Canonical Code', 'qsa-engraving' ); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="qsa-canonical-code" name="canonical_code" class="small-text" required maxlength="4" pattern="[A-Za-z0-9]{4}" style="text-transform: uppercase;">
                                <p class="description"><?php esc_html_e( 'The 4-letter QSA design code (e.g., "SP01", "STAR").', 'qsa-engraving' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="qsa-revision"><?php esc_html_e( 'Revision', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <select id="qsa-revision" name="revision">
                                    <option value=""><?php esc_html_e( '— None —', 'qsa-engraving' ); ?></option>
                                    <?php foreach ( range( 'a', 'z' ) as $letter ) : ?>
                                        <option value="<?php echo esc_attr( $letter ); ?>"><?php echo esc_html( $letter ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Optional revision letter (a-z).', 'qsa-engraving' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="qsa-description"><?php esc_html_e( 'Description', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <textarea id="qsa-description" name="description" rows="2" class="large-text"></textarea>
                                <p class="description"><?php esc_html_e( 'Optional description for this mapping.', 'qsa-engraving' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="qsa-priority"><?php esc_html_e( 'Priority', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="qsa-priority" name="priority" value="100" min="0" max="65535" class="small-text">
                                <p class="description"><?php esc_html_e( 'Lower numbers are matched first (default: 100).', 'qsa-engraving' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="qsa-is-active"><?php esc_html_e( 'Active', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="qsa-is-active" name="is_active" value="1" checked>
                                    <?php esc_html_e( 'Enable this mapping', 'qsa-engraving' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" id="qsa-save-mapping" class="button button-primary">
                            <?php esc_html_e( 'Save Mapping', 'qsa-engraving' ); ?>
                        </button>
                        <button type="button" id="qsa-cancel-edit" class="button button-secondary" style="display: none;">
                            <?php esc_html_e( 'Cancel', 'qsa-engraving' ); ?>
                        </button>
                        <span id="qsa-form-status" class="qsa-form-status"></span>
                    </p>
                </form>
            </div>

            <!-- Mapping List -->
            <div class="qsa-widget qsa-mapping-list-widget">
                <h2><?php esc_html_e( 'Existing Mappings', 'qsa-engraving' ); ?></h2>
                <div class="qsa-mapping-controls">
                    <input type="text" id="qsa-mapping-search" placeholder="<?php esc_attr_e( 'Search mappings...', 'qsa-engraving' ); ?>" class="regular-text">
                    <label>
                        <input type="checkbox" id="qsa-show-inactive">
                        <?php esc_html_e( 'Show inactive', 'qsa-engraving' ); ?>
                    </label>
                    <button type="button" id="qsa-refresh-mappings" class="button button-secondary">
                        <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                        <?php esc_html_e( 'Refresh', 'qsa-engraving' ); ?>
                    </button>
                    <span id="qsa-mapping-count" class="qsa-mapping-count"></span>
                </div>
                <div id="qsa-mapping-list" class="qsa-mapping-list">
                    <p class="loading"><span class="spinner is-active"></span> <?php esc_html_e( 'Loading mappings...', 'qsa-engraving' ); ?></p>
                </div>
            </div>
        </div>

        <style>
            .qsa-sku-mappings-wrap {
                max-width: 1200px;
            }
            .qsa-widget {
                background: #fff;
                border: 1px solid #c3c4c7;
                padding: 15px 20px;
                margin-bottom: 20px;
            }
            .qsa-widget h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .qsa-test-form {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
            }
            .qsa-test-form input {
                flex: 0 0 300px;
            }
            .qsa-test-result {
                background: #f6f7f7;
                padding: 15px;
                border-radius: 4px;
                margin-top: 10px;
            }
            .qsa-test-result.matched {
                background: #edfaef;
                border-left: 4px solid #00a32a;
            }
            .qsa-test-result.not-matched {
                background: #fef7f1;
                border-left: 4px solid #d63638;
            }
            .qsa-test-status {
                font-size: 13px;
            }
            .qsa-test-status.loading { color: #666; }
            .qsa-test-status.error { color: #d63638; }
            .qsa-mapping-form .required { color: #d63638; }
            .qsa-form-status {
                margin-left: 10px;
                font-size: 13px;
            }
            .qsa-form-status.success { color: #00a32a; }
            .qsa-form-status.error { color: #d63638; }
            .qsa-mapping-controls {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 15px;
                flex-wrap: wrap;
            }
            .qsa-mapping-count {
                margin-left: auto;
                color: #666;
                font-size: 13px;
            }
            .qsa-mapping-table {
                width: 100%;
                border-collapse: collapse;
            }
            .qsa-mapping-table th,
            .qsa-mapping-table td {
                padding: 10px 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .qsa-mapping-table th {
                background: #f6f7f7;
                font-weight: 600;
            }
            .qsa-mapping-table tr:hover {
                background: #f9f9f9;
            }
            .qsa-mapping-table tr.inactive {
                opacity: 0.6;
            }
            .qsa-mapping-table .actions {
                white-space: nowrap;
            }
            .qsa-mapping-table .actions button {
                margin-right: 5px;
            }
            .qsa-match-type {
                background: #f0f0f1;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 12px;
            }
            .qsa-status-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 12px;
            }
            .qsa-status-badge.active {
                background: #edfaef;
                color: #00a32a;
            }
            .qsa-status-badge.inactive {
                background: #f0f0f1;
                color: #666;
            }
            .qsa-empty-state {
                text-align: center;
                padding: 40px 20px;
                color: #666;
            }
            .qsa-empty-state .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #ddd;
            }
        </style>

        <script type="text/javascript">
        jQuery(function($) {
            var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
            var nonce = '<?php echo esc_js( $nonce ); ?>';
            var editingId = null;

            // Load mappings on page load
            loadMappings();

            // Test Resolution
            $('#qsa-test-btn').on('click', function() {
                var sku = $('#qsa-test-sku').val().trim();
                if (!sku) {
                    $('#qsa-test-status').addClass('error').text('<?php echo esc_js( __( 'Please enter a SKU to test.', 'qsa-engraving' ) ); ?>');
                    return;
                }

                var $status = $('#qsa-test-status');
                var $result = $('#qsa-test-result');

                $status.removeClass('error').addClass('loading').text('<?php echo esc_js( __( 'Testing...', 'qsa-engraving' ) ); ?>');
                $result.hide();

                $.post(ajaxUrl, {
                    action: 'qsa_test_sku_resolution',
                    nonce: nonce,
                    sku: sku
                }, function(response) {
                    $status.removeClass('loading');
                    if (response.success) {
                        var data = response.data;
                        var html = '';

                        if (data.matched) {
                            $result.removeClass('not-matched').addClass('matched');
                            html += '<strong><?php echo esc_js( __( 'Match Found!', 'qsa-engraving' ) ); ?></strong><br>';
                            html += '<?php echo esc_js( __( 'Message:', 'qsa-engraving' ) ); ?> ' + escapeHtml(data.message) + '<br>';

                            if (data.resolution) {
                                html += '<?php echo esc_js( __( 'Canonical Code:', 'qsa-engraving' ) ); ?> <code>' + escapeHtml(data.resolution.canonical_code) + '</code>';
                                if (data.resolution.revision) {
                                    html += ' <?php echo esc_js( __( 'Revision:', 'qsa-engraving' ) ); ?> <code>' + escapeHtml(data.resolution.revision) + '</code>';
                                }
                                html += '<br>';
                                html += '<?php echo esc_js( __( 'Canonical SKU:', 'qsa-engraving' ) ); ?> <code>' + escapeHtml(data.resolution.canonical_sku) + '</code><br>';
                                html += '<?php echo esc_js( __( 'Legacy:', 'qsa-engraving' ) ); ?> ' + (data.resolution.is_legacy ? '<?php echo esc_js( __( 'Yes', 'qsa-engraving' ) ); ?>' : '<?php echo esc_js( __( 'No (native QSA)', 'qsa-engraving' ) ); ?>') + '<br>';
                            }

                            if (typeof data.config_exists !== 'undefined') {
                                html += '<?php echo esc_js( __( 'Config Exists:', 'qsa-engraving' ) ); ?> ' + (data.config_exists ? '<span style="color:#00a32a;"><?php echo esc_js( __( 'Yes', 'qsa-engraving' ) ); ?></span>' : '<span style="color:#d63638;"><?php echo esc_js( __( 'No', 'qsa-engraving' ) ); ?></span>');
                            }
                        } else {
                            $result.removeClass('matched').addClass('not-matched');
                            html = '<strong><?php echo esc_js( __( 'No Match', 'qsa-engraving' ) ); ?></strong><br>' + escapeHtml(data.message);
                        }

                        $result.html(html).show();
                    } else {
                        $status.addClass('error').text(response.data.message || '<?php echo esc_js( __( 'Test failed', 'qsa-engraving' ) ); ?>');
                    }
                }).fail(function() {
                    $status.removeClass('loading').addClass('error').text('<?php echo esc_js( __( 'Request failed', 'qsa-engraving' ) ); ?>');
                });
            });

            // Enter key in test input
            $('#qsa-test-sku').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#qsa-test-btn').click();
                }
            });

            // Save Mapping Form
            $('#qsa-mapping-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $status = $('#qsa-form-status');
                var $btn = $('#qsa-save-mapping');

                var data = {
                    nonce: nonce,
                    legacy_pattern: $('#qsa-legacy-pattern').val(),
                    match_type: $('#qsa-match-type').val(),
                    canonical_code: $('#qsa-canonical-code').val().toUpperCase(),
                    revision: $('#qsa-revision').val(),
                    description: $('#qsa-description').val(),
                    priority: $('#qsa-priority').val(),
                    is_active: $('#qsa-is-active').is(':checked') ? '1' : '0'
                };

                if (editingId) {
                    data.action = 'qsa_update_sku_mapping';
                    data.id = editingId;
                } else {
                    data.action = 'qsa_add_sku_mapping';
                }

                $btn.prop('disabled', true);
                $status.removeClass('success error').text('<?php echo esc_js( __( 'Saving...', 'qsa-engraving' ) ); ?>');

                $.post(ajaxUrl, data, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $status.addClass('success').text(response.data.message);
                        resetForm();
                        loadMappings();
                        setTimeout(function() { $status.text(''); }, 3000);
                    } else {
                        $status.addClass('error').text(response.data.message || '<?php echo esc_js( __( 'Save failed', 'qsa-engraving' ) ); ?>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $status.addClass('error').text('<?php echo esc_js( __( 'Request failed', 'qsa-engraving' ) ); ?>');
                });
            });

            // Cancel Edit
            $('#qsa-cancel-edit').on('click', function() {
                resetForm();
            });

            // Refresh button
            $('#qsa-refresh-mappings').on('click', function() {
                loadMappings();
            });

            // Search
            var searchTimeout;
            $('#qsa-mapping-search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    loadMappings();
                }, 300);
            });

            // Show inactive toggle
            $('#qsa-show-inactive').on('change', function() {
                loadMappings();
            });

            // Load mappings function
            function loadMappings() {
                var $list = $('#qsa-mapping-list');
                var search = $('#qsa-mapping-search').val();
                var includeInactive = $('#qsa-show-inactive').is(':checked');

                $list.html('<p class="loading"><span class="spinner is-active"></span> <?php echo esc_js( __( 'Loading...', 'qsa-engraving' ) ); ?></p>');

                $.post(ajaxUrl, {
                    action: 'qsa_get_sku_mappings',
                    nonce: nonce,
                    search: search,
                    include_inactive: includeInactive ? 'true' : 'false'
                }, function(response) {
                    if (response.success) {
                        renderMappings(response.data.mappings, response.data.total_count, response.data.active_count);
                    } else {
                        $list.html('<div class="qsa-empty-state"><span class="dashicons dashicons-warning"></span><p>' + (response.data.message || '<?php echo esc_js( __( 'Failed to load mappings.', 'qsa-engraving' ) ); ?>') + '</p></div>');
                    }
                }).fail(function() {
                    $list.html('<div class="qsa-empty-state"><span class="dashicons dashicons-warning"></span><p><?php echo esc_js( __( 'Request failed.', 'qsa-engraving' ) ); ?></p></div>');
                });
            }

            // Render mappings table
            function renderMappings(mappings, totalCount, activeCount) {
                var $list = $('#qsa-mapping-list');
                var $count = $('#qsa-mapping-count');

                $count.text(activeCount + ' <?php echo esc_js( __( 'active', 'qsa-engraving' ) ); ?> / ' + totalCount + ' <?php echo esc_js( __( 'total', 'qsa-engraving' ) ); ?>');

                if (!mappings || mappings.length === 0) {
                    $list.html('<div class="qsa-empty-state"><span class="dashicons dashicons-admin-generic"></span><p><?php echo esc_js( __( 'No mappings found. Add one above.', 'qsa-engraving' ) ); ?></p></div>');
                    return;
                }

                var html = '<table class="qsa-mapping-table">';
                html += '<thead><tr>';
                html += '<th><?php echo esc_js( __( 'Pattern', 'qsa-engraving' ) ); ?></th>';
                html += '<th><?php echo esc_js( __( 'Type', 'qsa-engraving' ) ); ?></th>';
                html += '<th><?php echo esc_js( __( 'Canonical', 'qsa-engraving' ) ); ?></th>';
                html += '<th><?php echo esc_js( __( 'Description', 'qsa-engraving' ) ); ?></th>';
                html += '<th><?php echo esc_js( __( 'Priority', 'qsa-engraving' ) ); ?></th>';
                html += '<th><?php echo esc_js( __( 'Status', 'qsa-engraving' ) ); ?></th>';
                html += '<th><?php echo esc_js( __( 'Actions', 'qsa-engraving' ) ); ?></th>';
                html += '</tr></thead><tbody>';

                mappings.forEach(function(m) {
                    var isActive = m.is_active === '1' || m.is_active === 1;
                    html += '<tr class="' + (isActive ? '' : 'inactive') + '" data-id="' + m.id + '">';
                    html += '<td><code>' + escapeHtml(m.legacy_pattern) + '</code></td>';
                    html += '<td><span class="qsa-match-type">' + escapeHtml(m.match_type) + '</span></td>';
                    html += '<td><strong>' + escapeHtml(m.canonical_code) + '</strong>' + (m.revision ? ' <small>(' + escapeHtml(m.revision) + ')</small>' : '') + '</td>';
                    html += '<td>' + (m.description ? escapeHtml(m.description) : '<span style="color:#999;">—</span>') + '</td>';
                    html += '<td>' + m.priority + '</td>';
                    html += '<td><span class="qsa-status-badge ' + (isActive ? 'active' : 'inactive') + '">' + (isActive ? '<?php echo esc_js( __( 'Active', 'qsa-engraving' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'qsa-engraving' ) ); ?>') + '</span></td>';
                    html += '<td class="actions">';
                    html += '<button type="button" class="button button-small qsa-edit-btn" data-id="' + m.id + '"><?php echo esc_js( __( 'Edit', 'qsa-engraving' ) ); ?></button>';
                    html += '<button type="button" class="button button-small qsa-toggle-btn" data-id="' + m.id + '">' + (isActive ? '<?php echo esc_js( __( 'Disable', 'qsa-engraving' ) ); ?>' : '<?php echo esc_js( __( 'Enable', 'qsa-engraving' ) ); ?>') + '</button>';
                    html += '<button type="button" class="button button-small qsa-delete-btn" data-id="' + m.id + '" style="color:#d63638;"><?php echo esc_js( __( 'Delete', 'qsa-engraving' ) ); ?></button>';
                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                $list.html(html);

                // Bind row action handlers
                bindRowActions();
            }

            // Bind row action handlers
            function bindRowActions() {
                // Edit button
                $('.qsa-edit-btn').on('click', function() {
                    var id = $(this).data('id');
                    var $row = $(this).closest('tr');
                    editMapping(id, $row);
                });

                // Toggle button
                $('.qsa-toggle-btn').on('click', function() {
                    var id = $(this).data('id');
                    toggleMapping(id);
                });

                // Delete button
                $('.qsa-delete-btn').on('click', function() {
                    var id = $(this).data('id');
                    deleteMapping(id);
                });
            }

            // Edit mapping
            function editMapping(id, $row) {
                // Find the mapping data from the row
                $.post(ajaxUrl, {
                    action: 'qsa_get_sku_mappings',
                    nonce: nonce,
                    include_inactive: 'true'
                }, function(response) {
                    if (response.success) {
                        var mapping = response.data.mappings.find(function(m) { return parseInt(m.id) === parseInt(id); });
                        if (mapping) {
                            editingId = id;
                            $('#qsa-form-title').text('<?php echo esc_js( __( 'Edit Mapping', 'qsa-engraving' ) ); ?>');
                            $('#qsa-mapping-id').val(id);
                            $('#qsa-legacy-pattern').val(mapping.legacy_pattern);
                            $('#qsa-match-type').val(mapping.match_type);
                            $('#qsa-canonical-code').val(mapping.canonical_code);
                            $('#qsa-revision').val(mapping.revision || '');
                            $('#qsa-description').val(mapping.description || '');
                            $('#qsa-priority').val(mapping.priority);
                            $('#qsa-is-active').prop('checked', mapping.is_active === '1' || mapping.is_active === 1);
                            $('#qsa-save-mapping').text('<?php echo esc_js( __( 'Update Mapping', 'qsa-engraving' ) ); ?>');
                            $('#qsa-cancel-edit').show();

                            // Scroll to form
                            $('html, body').animate({ scrollTop: $('.qsa-mapping-form-widget').offset().top - 50 }, 300);
                        }
                    }
                });
            }

            // Toggle mapping
            function toggleMapping(id) {
                $.post(ajaxUrl, {
                    action: 'qsa_toggle_sku_mapping',
                    nonce: nonce,
                    id: id
                }, function(response) {
                    if (response.success) {
                        loadMappings();
                    } else {
                        alert(response.data.message || '<?php echo esc_js( __( 'Failed to toggle mapping.', 'qsa-engraving' ) ); ?>');
                    }
                });
            }

            // Delete mapping
            function deleteMapping(id) {
                if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this mapping? This cannot be undone.', 'qsa-engraving' ) ); ?>')) {
                    return;
                }

                $.post(ajaxUrl, {
                    action: 'qsa_delete_sku_mapping',
                    nonce: nonce,
                    id: id
                }, function(response) {
                    if (response.success) {
                        loadMappings();
                    } else {
                        alert(response.data.message || '<?php echo esc_js( __( 'Failed to delete mapping.', 'qsa-engraving' ) ); ?>');
                    }
                });
            }

            // Reset form to add mode
            function resetForm() {
                editingId = null;
                $('#qsa-form-title').text('<?php echo esc_js( __( 'Add New Mapping', 'qsa-engraving' ) ); ?>');
                $('#qsa-mapping-form')[0].reset();
                $('#qsa-mapping-id').val('');
                $('#qsa-is-active').prop('checked', true);
                $('#qsa-priority').val('100');
                $('#qsa-save-mapping').text('<?php echo esc_js( __( 'Save Mapping', 'qsa-engraving' ) ); ?>');
                $('#qsa-cancel-edit').hide();
                $('#qsa-form-status').text('');
            }

            // Escape HTML helper
            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            }
        });
        </script>
        <?php
    }

    /**
     * Render the database setup notice.
     *
     * @return void
     */
    private function render_database_setup_notice(): void {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Database Setup Required', 'qsa-engraving' ); ?></strong>
            </p>
            <p>
                <?php esc_html_e( 'The QSA Engraving database tables have not been created. Please run the database installation script via phpMyAdmin.', 'qsa-engraving' ); ?>
            </p>
            <p>
                <strong><?php esc_html_e( 'Script location:', 'qsa-engraving' ); ?></strong>
                <code>docs/database/install/01-qsa-engraving-schema.sql</code>
            </p>
            <p>
                <?php esc_html_e( 'Remember to replace {prefix} with your WordPress table prefix (e.g., lw_ or wp_) before running.', 'qsa-engraving' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render the settings content.
     *
     * @return void
     */
    private function render_settings_content(): void {
        $settings = get_option( 'qsa_engraving_settings', array() );

        // Default values.
        $defaults = array(
            'lightburn_enabled'  => false,
            'keep_svg_files'     => true,
            'svg_output_dir'     => '',
            'svg_rotation'       => 0,
            'svg_top_offset'     => 0.0,
            'led_code_tracking'  => 1.0,
        );

        $settings = wp_parse_args( $settings, $defaults );
        ?>
        <div class="qsa-settings-wrap">
            <form method="post" action="options.php" id="qsa-settings-form">
                <?php wp_nonce_field( 'qsa_engraving_settings_nonce', 'qsa_settings_nonce' ); ?>

                <!-- SVG Generation Settings -->
                <div class="qsa-settings-section">
                    <h2><?php esc_html_e( 'SVG Generation', 'qsa-engraving' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Configure SVG file generation for laser engraving.', 'qsa-engraving' ); ?>
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="lightburn_enabled"><?php esc_html_e( 'Enable SVG Generation', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="lightburn_enabled" id="lightburn_enabled" value="1" <?php checked( $settings['lightburn_enabled'] ); ?>>
                                    <?php esc_html_e( 'Generate SVG files when engraving batches', 'qsa-engraving' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'When enabled, SVG files will be generated and saved for pickup by the LightBurn watcher.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="keep_svg_files"><?php esc_html_e( 'Keep SVG Files', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="keep_svg_files" id="keep_svg_files" value="1" <?php checked( $settings['keep_svg_files'] ); ?>>
                                    <?php esc_html_e( 'Retain SVG files after engraving is complete', 'qsa-engraving' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'When disabled, SVG files are deleted after the batch is completed.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="svg_rotation"><?php esc_html_e( 'SVG Rotation', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <select name="svg_rotation" id="svg_rotation">
                                    <option value="0" <?php selected( $settings['svg_rotation'], 0 ); ?>>0° (No rotation)</option>
                                    <option value="90" <?php selected( $settings['svg_rotation'], 90 ); ?>>90° Clockwise</option>
                                    <option value="180" <?php selected( $settings['svg_rotation'], 180 ); ?>>180°</option>
                                    <option value="270" <?php selected( $settings['svg_rotation'], 270 ); ?>>270° Clockwise</option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Rotate the SVG output to match laser bed orientation.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="svg_top_offset"><?php esc_html_e( 'Top Offset', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="svg_top_offset" id="svg_top_offset"
                                    value="<?php echo esc_attr( number_format( (float) $settings['svg_top_offset'], 2, '.', '' ) ); ?>"
                                    min="-5" max="5" step="0.02" style="width: 80px;">
                                <span class="description">mm</span>
                                <p class="description">
                                    <?php esc_html_e( 'Vertical offset adjustment for laser alignment (-5 to +5 mm).', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="led_code_tracking"><?php esc_html_e( 'LED Code Tracking', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="led_code_tracking" id="led_code_tracking"
                                    value="<?php echo esc_attr( number_format( (float) $settings['led_code_tracking'], 2, '.', '' ) ); ?>"
                                    min="0.5" max="3.0" step="0.05" style="width: 80px;">
                                <span class="description"><?php esc_html_e( '(multiplier)', 'qsa-engraving' ); ?></span>
                                <p class="description">
                                    <?php esc_html_e( 'Character spacing for 3-letter LED codes. 1.0 = normal, 1.3 = 30% wider spacing. Matches AutoCAD tracking value.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="svg_output_dir"><?php esc_html_e( 'SVG Output Directory', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="svg_output_dir" id="svg_output_dir" value="<?php echo esc_attr( $settings['svg_output_dir'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Leave empty to use WordPress uploads directory', 'qsa-engraving' ); ?>">
                                <p class="description">
                                    <?php esc_html_e( 'Server path where SVG files are saved. Default: wp-content/uploads/qsa-engraving/svg/', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Directory Status', 'qsa-engraving' ); ?></th>
                            <td>
                                <?php $this->render_directory_status(); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- QSA Configuration Import -->
                <div class="qsa-settings-section">
                    <h2><?php esc_html_e( 'QSA Configuration Import', 'qsa-engraving' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Import QSA element positions from a CSV file generated by the AutoCAD QSAEXPORT script.', 'qsa-engraving' ); ?>
                    </p>

                    <div id="qsa-config-import-area">
                        <div class="qsa-import-upload">
                            <input type="file" id="qsa-config-csv-file" accept=".csv" style="display: none;">
                            <button type="button" id="qsa-config-select-file" class="button">
                                <?php esc_html_e( 'Select CSV File', 'qsa-engraving' ); ?>
                            </button>
                            <span id="qsa-config-file-name" class="qsa-file-name"></span>
                        </div>

                        <div id="qsa-config-preview" class="qsa-import-preview" style="display: none;">
                            <h4><?php esc_html_e( 'Import Preview', 'qsa-engraving' ); ?></h4>
                            <div id="qsa-config-preview-content"></div>
                            <div class="qsa-import-actions" style="margin-top: 15px;">
                                <button type="button" id="qsa-config-apply-import" class="button button-primary">
                                    <?php esc_html_e( 'Apply Import', 'qsa-engraving' ); ?>
                                </button>
                                <button type="button" id="qsa-config-cancel-import" class="button">
                                    <?php esc_html_e( 'Cancel', 'qsa-engraving' ); ?>
                                </button>
                            </div>
                        </div>

                        <div id="qsa-config-import-result" class="qsa-import-result" style="display: none;"></div>
                    </div>
                </div>

                <!-- LightBurn Watcher Information -->
                <div class="qsa-settings-section">
                    <h2><?php esc_html_e( 'LightBurn SFTP Watcher', 'qsa-engraving' ); ?></h2>
                    <div class="qsa-info-box">
                        <p>
                            <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                            <?php esc_html_e( 'The LightBurn Watcher is a Node.js script running on the production workstation. It monitors this server via SFTP for new SVG files and automatically loads them into LightBurn.', 'qsa-engraving' ); ?>
                        </p>
                        <table class="qsa-info-table">
                            <tr>
                                <td><strong><?php esc_html_e( 'Polling Interval:', 'qsa-engraving' ); ?></strong></td>
                                <td><?php esc_html_e( '3 seconds', 'qsa-engraving' ); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Install Location:', 'qsa-engraving' ); ?></strong></td>
                                <td><code>C:\Users\Production\LightBurn\lightburn-watcher\</code></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Log File:', 'qsa-engraving' ); ?></strong></td>
                                <td><code>C:\Users\Production\LightBurn\lightburn-watcher.log</code></td>
                            </tr>
                        </table>
                        <p class="description" style="margin-top: 10px;">
                            <?php esc_html_e( 'The watcher auto-starts via Windows Startup folder. To check status: tasklist | findstr node', 'qsa-engraving' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Micro-ID Decoder Settings -->
                <div class="qsa-settings-section">
                    <h2><?php esc_html_e( 'Micro-ID Decoder', 'qsa-engraving' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Configure the Micro-ID decoder which allows customers to scan their LED modules at /id.', 'qsa-engraving' ); ?>
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="microid_decoder_enabled"><?php esc_html_e( 'Enable Decoder', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="microid_decoder_enabled" id="microid_decoder_enabled" value="1" <?php checked( $settings['microid_decoder_enabled'] ?? false ); ?>>
                                    <?php esc_html_e( 'Enable the /id URL for Micro-ID decoding', 'qsa-engraving' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'When enabled, customers can upload photos of their LED modules to decode the Micro-ID code and view product information.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="claude_api_key"><?php esc_html_e( 'Claude API Key', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <?php
                                $has_api_key      = ! empty( $settings['claude_api_key'] ?? '' );
                                $api_key_display  = $has_api_key ? '**********' : '';
                                ?>
                                <input type="password" name="claude_api_key" id="claude_api_key"
                                    value="<?php echo esc_attr( $api_key_display ); ?>"
                                    class="regular-text"
                                    autocomplete="new-password"
                                    placeholder="<?php esc_attr_e( 'Enter your Claude API key', 'qsa-engraving' ); ?>">
                                <?php if ( $has_api_key ) : ?>
                                    <span class="qsa-api-key-status configured">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e( 'Configured', 'qsa-engraving' ); ?>
                                    </span>
                                <?php endif; ?>
                                <p class="description">
                                    <?php
                                    printf(
                                        /* translators: %s: Anthropic Console URL */
                                        esc_html__( 'Get your API key from the %s. The key is encrypted before storage.', 'qsa-engraving' ),
                                        '<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Anthropic Console', 'qsa-engraving' ) . '</a>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="claude_model"><?php esc_html_e( 'Claude Model', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <?php
                                $current_model = $settings['claude_model'] ?? \Quadica\QSA_Engraving\Services\Claude_Vision_Client::DEFAULT_MODEL;
                                ?>
                                <select name="claude_model" id="claude_model">
                                    <option value="claude-sonnet-4-5-20250929" <?php selected( $current_model, 'claude-sonnet-4-5-20250929' ); ?>>Claude Sonnet 4.5 (Recommended)</option>
                                    <option value="claude-sonnet-4-20250514" <?php selected( $current_model, 'claude-sonnet-4-20250514' ); ?>>Claude Sonnet 4 (Legacy)</option>
                                    <option value="claude-haiku-4-5-20251001" <?php selected( $current_model, 'claude-haiku-4-5-20251001' ); ?>>Claude Haiku 4.5 (Faster/Cheaper)</option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Select the Claude model to use for image analysis. Sonnet offers the best balance of speed and accuracy.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="microid_log_retention_days"><?php esc_html_e( 'Log Retention', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="microid_log_retention_days" id="microid_log_retention_days"
                                    value="<?php echo esc_attr( $settings['microid_log_retention_days'] ?? 90 ); ?>"
                                    min="7" max="365" step="1" style="width: 80px;">
                                <span class="description"><?php esc_html_e( 'days', 'qsa-engraving' ); ?></span>
                                <p class="description">
                                    <?php esc_html_e( 'How long to keep decode logs. Logs older than this are automatically deleted. Minimum 7 days.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Connection Test', 'qsa-engraving' ); ?></th>
                            <td>
                                <button type="button" id="qsa-test-claude-api" class="button" <?php disabled( ! $has_api_key ); ?>>
                                    <?php esc_html_e( 'Test Connection', 'qsa-engraving' ); ?>
                                </button>
                                <span id="qsa-claude-test-result" class="qsa-test-result"></span>
                                <p class="description">
                                    <?php esc_html_e( 'Test the connection to Claude API. Save settings first if you have changed the API key.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Decoder URL', 'qsa-engraving' ); ?></th>
                            <td>
                                <code><?php echo esc_url( home_url( '/id' ) ); ?></code>
                                <?php if ( $settings['microid_decoder_enabled'] ?? false ) : ?>
                                    <a href="<?php echo esc_url( home_url( '/id' ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-small" style="margin-left: 10px;">
                                        <?php esc_html_e( 'Open', 'qsa-engraving' ); ?>
                                    </a>
                                <?php endif; ?>
                                <p class="description">
                                    <?php esc_html_e( 'This is the public URL where customers can upload photos to decode their Micro-ID codes.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <button type="submit" id="qsa-save-settings" class="button button-primary">
                        <?php esc_html_e( 'Save Settings', 'qsa-engraving' ); ?>
                    </button>
                    <span id="qsa-save-result" class="qsa-save-result"></span>
                </p>
            </form>
        </div>

        <style>
            .qsa-info-box {
                background: #f0f6fc;
                border: 1px solid #c3c4c7;
                border-left: 4px solid #2271b1;
                padding: 12px 15px;
                margin: 10px 0;
            }
            .qsa-info-box p {
                margin: 0 0 10px;
            }
            .qsa-info-box .dashicons {
                vertical-align: text-bottom;
                margin-right: 5px;
            }
            .qsa-info-table {
                margin: 10px 0;
            }
            .qsa-info-table td {
                padding: 4px 10px 4px 0;
                vertical-align: top;
            }
            .qsa-info-table code {
                font-size: 12px;
            }
            .qsa-save-result {
                margin-left: 10px;
            }
            .qsa-save-result.success {
                color: #00a32a;
            }
            .qsa-save-result.error {
                color: #d63638;
            }

            /* Micro-ID Decoder Styles */
            .qsa-api-key-status {
                margin-left: 10px;
                font-weight: 500;
            }
            .qsa-api-key-status.configured {
                color: #00a32a;
            }
            .qsa-api-key-status .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
                vertical-align: text-bottom;
            }
            .qsa-test-result {
                margin-left: 10px;
                font-style: italic;
            }
            .qsa-test-result.success {
                color: #00a32a;
            }
            .qsa-test-result.error {
                color: #d63638;
            }
            .qsa-test-result.loading {
                color: #666;
            }

            /* QSA Config Import Styles */
            .qsa-import-upload {
                margin: 15px 0;
            }
            .qsa-file-name {
                margin-left: 10px;
                font-style: italic;
                color: #50575e;
            }
            .qsa-import-preview {
                background: #f9f9f9;
                border: 1px solid #c3c4c7;
                padding: 15px;
                margin: 15px 0;
                border-radius: 4px;
            }
            .qsa-import-preview h4 {
                margin: 0 0 10px 0;
                padding: 0;
            }
            .qsa-preview-summary {
                display: flex;
                gap: 20px;
                margin-bottom: 15px;
                flex-wrap: wrap;
            }
            .qsa-preview-stat {
                padding: 10px 15px;
                border-radius: 4px;
                min-width: 100px;
                text-align: center;
            }
            .qsa-preview-stat.additions {
                background: #d4edda;
                border: 1px solid #28a745;
            }
            .qsa-preview-stat.updates {
                background: #fff3cd;
                border: 1px solid #ffc107;
            }
            .qsa-preview-stat.deletions {
                background: #f8d7da;
                border: 1px solid #dc3545;
            }
            .qsa-preview-stat.unchanged {
                background: #e9ecef;
                border: 1px solid #6c757d;
            }
            .qsa-preview-stat .count {
                font-size: 24px;
                font-weight: bold;
                display: block;
            }
            .qsa-preview-stat .label {
                font-size: 12px;
                text-transform: uppercase;
                color: #50575e;
            }
            .qsa-preview-design-info {
                margin-bottom: 15px;
                padding: 10px;
                background: #f0f6fc;
                border-left: 3px solid #2271b1;
            }
            .qsa-import-result {
                padding: 10px 15px;
                margin: 15px 0;
                border-radius: 4px;
            }
            .qsa-import-result.success {
                background: #d4edda;
                border: 1px solid #28a745;
                color: #155724;
            }
            .qsa-import-result.error {
                background: #f8d7da;
                border: 1px solid #dc3545;
                color: #721c24;
            }
            .qsa-import-actions button {
                margin-right: 10px;
            }
            .qsa-import-loading {
                display: inline-block;
                margin-left: 10px;
            }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( 'qsa_engraving_nonce' ) ); ?>';
            var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

            // Save settings via AJAX
            $('#qsa-settings-form').on('submit', function(e) {
                e.preventDefault();

                var $btn = $('#qsa-save-settings');
                var $result = $('#qsa-save-result');

                $btn.prop('disabled', true);
                $result.removeClass('success error').text('<?php echo esc_js( __( 'Saving...', 'qsa-engraving' ) ); ?>');

                var data = {
                    action: 'qsa_save_lightburn_settings',
                    nonce: nonce,
                    lightburn_enabled: $('#lightburn_enabled').is(':checked') ? 1 : 0,
                    keep_svg_files: $('#keep_svg_files').is(':checked') ? 1 : 0,
                    svg_output_dir: $('#svg_output_dir').val(),
                    svg_rotation: $('#svg_rotation').val(),
                    svg_top_offset: $('#svg_top_offset').val(),
                    led_code_tracking: $('#led_code_tracking').val(),
                    // Micro-ID Decoder settings
                    microid_decoder_enabled: $('#microid_decoder_enabled').is(':checked') ? 1 : 0,
                    claude_model: $('#claude_model').val(),
                    microid_log_retention_days: $('#microid_log_retention_days').val()
                };

                // Only include API key if it has been changed from the masked placeholder
                var apiKeyVal = $('#claude_api_key').val();
                if (apiKeyVal && apiKeyVal !== '**********') {
                    data.claude_api_key = apiKeyVal;
                }

                $.post(ajaxUrl, data, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $result.addClass('success').text('<?php echo esc_js( __( 'Settings saved!', 'qsa-engraving' ) ); ?>');
                        setTimeout(function() { $result.text(''); }, 3000);

                        // Update UI elements based on new state
                        var hasApiKey = response.data && response.data.claude_api_key;
                        $('#qsa-test-claude-api').prop('disabled', !hasApiKey);

                        // If API key was just saved, update the field to show it's configured
                        if (apiKeyVal && apiKeyVal !== '**********') {
                            $('#claude_api_key').val('**********');
                            if (!$('.qsa-api-key-status.configured').length) {
                                $('#claude_api_key').after('<span class="qsa-api-key-status configured"><span class="dashicons dashicons-yes-alt"></span> <?php echo esc_js( __( 'Configured', 'qsa-engraving' ) ); ?></span>');
                            }
                        }
                    } else {
                        $result.addClass('error').text(response.message || '<?php echo esc_js( __( 'Save failed', 'qsa-engraving' ) ); ?>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $result.addClass('error').text('<?php echo esc_js( __( 'Request failed', 'qsa-engraving' ) ); ?>');
                });
            });

            // =====================================================
            // Claude API Connection Test
            // =====================================================
            $('#qsa-test-claude-api').on('click', function() {
                var $btn = $(this);
                var $result = $('#qsa-claude-test-result');

                $btn.prop('disabled', true);
                $result.removeClass('success error').addClass('loading').text('<?php echo esc_js( __( 'Testing...', 'qsa-engraving' ) ); ?>');

                $.post(ajaxUrl, {
                    action: 'qsa_test_claude_connection',
                    nonce: nonce
                }, function(response) {
                    $btn.prop('disabled', false);
                    $result.removeClass('loading');

                    if (response.success) {
                        $result.addClass('success').text(response.message || '<?php echo esc_js( __( 'Connection successful!', 'qsa-engraving' ) ); ?>');
                    } else {
                        $result.addClass('error').text(response.message || '<?php echo esc_js( __( 'Connection failed', 'qsa-engraving' ) ); ?>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $result.removeClass('loading').addClass('error').text('<?php echo esc_js( __( 'Request failed', 'qsa-engraving' ) ); ?>');
                });
            });

            // =====================================================
            // QSA Configuration Import Functionality
            // =====================================================
            var parsedData = null;

            // Click handler for "Select CSV File" button
            $('#qsa-config-select-file').on('click', function() {
                $('#qsa-config-csv-file').click();
            });

            // File selection handler
            $('#qsa-config-csv-file').on('change', function() {
                var file = this.files[0];
                if (!file) {
                    return;
                }

                // Show filename
                $('#qsa-config-file-name').text(file.name);

                // Reset previous state
                $('#qsa-config-preview').hide();
                $('#qsa-config-import-result').hide().removeClass('success error');
                parsedData = null;

                // Upload and preview
                var formData = new FormData();
                formData.append('action', 'qsa_config_import_preview');
                formData.append('nonce', nonce);
                formData.append('csv_file', file);

                $('#qsa-config-select-file').prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'qsa-engraving' ) ); ?>');

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#qsa-config-select-file').prop('disabled', false).text('<?php echo esc_js( __( 'Select CSV File', 'qsa-engraving' ) ); ?>');

                        if (response.success) {
                            parsedData = response.data.parsed_data;
                            showPreview(response.data);
                        } else {
                            showError(response.message || '<?php echo esc_js( __( 'Failed to parse CSV file.', 'qsa-engraving' ) ); ?>');
                        }
                    },
                    error: function() {
                        $('#qsa-config-select-file').prop('disabled', false).text('<?php echo esc_js( __( 'Select CSV File', 'qsa-engraving' ) ); ?>');
                        showError('<?php echo esc_js( __( 'Request failed. Please try again.', 'qsa-engraving' ) ); ?>');
                    }
                });
            });

            // Show preview
            function showPreview(data) {
                var preview = data.preview;
                var summary = preview.summary;

                var html = '<div class="qsa-preview-design-info">';
                html += '<strong><?php echo esc_js( __( 'Design:', 'qsa-engraving' ) ); ?></strong> ' + escapeHtml(data.qsa_design);
                html += ' <strong><?php echo esc_js( __( 'Revision:', 'qsa-engraving' ) ); ?></strong> ' + escapeHtml(data.revision);
                html += ' <strong><?php echo esc_js( __( 'Rows in CSV:', 'qsa-engraving' ) ); ?></strong> ' + data.csv_rows;
                html += '</div>';

                html += '<div class="qsa-preview-summary">';
                html += '<div class="qsa-preview-stat additions"><span class="count">' + summary.additions + '</span><span class="label"><?php echo esc_js( __( 'New', 'qsa-engraving' ) ); ?></span></div>';
                html += '<div class="qsa-preview-stat updates"><span class="count">' + summary.updates + '</span><span class="label"><?php echo esc_js( __( 'Updated', 'qsa-engraving' ) ); ?></span></div>';
                html += '<div class="qsa-preview-stat deletions"><span class="count">' + summary.deletions + '</span><span class="label"><?php echo esc_js( __( 'Deleted', 'qsa-engraving' ) ); ?></span></div>';
                html += '<div class="qsa-preview-stat unchanged"><span class="count">' + summary.unchanged + '</span><span class="label"><?php echo esc_js( __( 'Unchanged', 'qsa-engraving' ) ); ?></span></div>';
                html += '</div>';

                // Show details if there are changes
                if (summary.additions > 0 || summary.updates > 0 || summary.deletions > 0) {
                    html += '<p><em><?php echo esc_js( __( 'Review the changes above. Click "Apply Import" to proceed or "Cancel" to abort.', 'qsa-engraving' ) ); ?></em></p>';
                } else {
                    html += '<p><em><?php echo esc_js( __( 'No changes detected. The database already matches the CSV file.', 'qsa-engraving' ) ); ?></em></p>';
                }

                $('#qsa-config-preview-content').html(html);
                $('#qsa-config-preview').show();

                // Disable apply button if no changes
                $('#qsa-config-apply-import').prop('disabled', summary.additions === 0 && summary.updates === 0 && summary.deletions === 0);
            }

            // Show error
            function showError(message) {
                $('#qsa-config-import-result')
                    .removeClass('success')
                    .addClass('error')
                    .html('<strong><?php echo esc_js( __( 'Error:', 'qsa-engraving' ) ); ?></strong> ' + escapeHtml(message))
                    .show();
            }

            // Show success
            function showSuccess(message) {
                $('#qsa-config-import-result')
                    .removeClass('error')
                    .addClass('success')
                    .html('<strong><?php echo esc_js( __( 'Success:', 'qsa-engraving' ) ); ?></strong> ' + escapeHtml(message))
                    .show();
            }

            // Escape HTML
            function escapeHtml(text) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            }

            // Cancel import
            $('#qsa-config-cancel-import').on('click', function() {
                $('#qsa-config-preview').hide();
                $('#qsa-config-csv-file').val('');
                $('#qsa-config-file-name').text('');
                parsedData = null;
            });

            // Apply import
            $('#qsa-config-apply-import').on('click', function() {
                if (!parsedData) {
                    showError('<?php echo esc_js( __( 'No data to import.', 'qsa-engraving' ) ); ?>');
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Importing...', 'qsa-engraving' ) ); ?>');

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'qsa_config_import_apply',
                        nonce: nonce,
                        parsed_data: JSON.stringify(parsedData)
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Apply Import', 'qsa-engraving' ) ); ?>');

                        if (response.success) {
                            $('#qsa-config-preview').hide();
                            showSuccess(response.message);

                            // Reset file input
                            $('#qsa-config-csv-file').val('');
                            $('#qsa-config-file-name').text('');
                            parsedData = null;
                        } else {
                            showError(response.message || '<?php echo esc_js( __( 'Import failed.', 'qsa-engraving' ) ); ?>');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Apply Import', 'qsa-engraving' ) ); ?>');
                        showError('<?php echo esc_js( __( 'Request failed. Please try again.', 'qsa-engraving' ) ); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render the SVG directory status.
     *
     * @return void
     */
    private function render_directory_status(): void {
        $file_manager = new \Quadica\QSA_Engraving\Services\SVG_File_Manager();
        $status       = $file_manager->get_status();
        ?>
        <div class="qsa-directory-status">
            <p>
                <strong><?php esc_html_e( 'Path:', 'qsa-engraving' ); ?></strong>
                <code><?php echo esc_html( $status['path'] ); ?></code>
            </p>
            <p>
                <strong><?php esc_html_e( 'Status:', 'qsa-engraving' ); ?></strong>
                <?php if ( $status['exists'] && $status['writable'] ) : ?>
                    <span class="status-ok">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'Ready', 'qsa-engraving' ); ?>
                    </span>
                <?php elseif ( $status['exists'] ) : ?>
                    <span class="status-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'Not writable', 'qsa-engraving' ); ?>
                    </span>
                <?php else : ?>
                    <span class="status-info">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e( 'Will be created on first use', 'qsa-engraving' ); ?>
                    </span>
                <?php endif; ?>
            </p>
            <?php if ( $status['file_count'] > 0 ) : ?>
            <p>
                <strong><?php esc_html_e( 'Files:', 'qsa-engraving' ); ?></strong>
                <?php
                printf(
                    /* translators: %d: Number of SVG files */
                    esc_html__( '%d SVG file(s) in directory', 'qsa-engraving' ),
                    $status['file_count']
                );
                ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
