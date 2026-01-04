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
        // Add as submenu under WooCommerce.
        add_submenu_page(
            'woocommerce',
            __( 'QSA Engraving', 'qsa-engraving' ),
            __( 'QSA Engraving', 'qsa-engraving' ),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG,
            array( $this, 'render_main_page' )
        );

        // Add submenu pages.
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
            $lightburn_enabled = ! empty( $system_settings['lightburn_enabled'] );
            $keep_svg_files = ! empty( $system_settings['keep_svg_files'] );
            ?>
            <div class="qsa-widget qsa-status-widget">
                <h2><?php esc_html_e( 'System Status', 'qsa-engraving' ); ?></h2>
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <td><?php esc_html_e( 'Plugin Version', 'qsa-engraving' ); ?></td>
                            <td><code><?php echo esc_html( QSA_ENGRAVING_VERSION ); ?></code></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Database Tables', 'qsa-engraving' ); ?></td>
                            <td>
                                <span class="status-ok">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e( 'Installed', 'qsa-engraving' ); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'WooCommerce', 'qsa-engraving' ); ?></td>
                            <td>
                                <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                                    <span class="status-ok">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php echo esc_html( WC_VERSION ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="status-error">
                                        <span class="dashicons dashicons-dismiss"></span>
                                        <?php esc_html_e( 'Not installed', 'qsa-engraving' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="qsa-toggle-lightburn"><?php esc_html_e( 'LightBurn Integration', 'qsa-engraving' ); ?></label>
                                <p class="description" style="margin: 4px 0 0; font-size: 11px; color: #666;">
                                    <?php esc_html_e( 'Send SVG files to laser engraver', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                            <td>
                                <label class="qsa-toggle-switch">
                                    <input type="checkbox" id="qsa-toggle-lightburn" <?php checked( $lightburn_enabled ); ?>>
                                    <span class="qsa-toggle-slider"></span>
                                </label>
                                <span class="qsa-toggle-status" id="qsa-lightburn-status"></span>
                            </td>
                        </tr>
                        <tr id="qsa-lightburn-connection-row" <?php echo $lightburn_enabled ? '' : 'style="display:none;"'; ?>>
                            <td>
                                <?php esc_html_e( 'LightBurn Connection', 'qsa-engraving' ); ?>
                                <p class="description" style="margin: 4px 0 0; font-size: 11px; color: #666;">
                                    <?php
                                    printf(
                                        /* translators: %s: IP address */
                                        esc_html__( 'Target: %s', 'qsa-engraving' ),
                                        '<span id="qsa-target-host-display">' . esc_html( $system_settings['lightburn_host'] ?? '127.0.0.1' ) . '</span>'
                                    );
                                    ?>
                                </p>
                            </td>
                            <td>
                                <span id="qsa-connection-status" class="qsa-connection-indicator">
                                    <span class="qsa-connection-dot checking"></span>
                                    <span class="qsa-connection-text"><?php esc_html_e( 'Checking...', 'qsa-engraving' ); ?></span>
                                </span>
                                <button type="button" id="qsa-check-connection" class="button button-small" style="margin-left: 10px;">
                                    <?php esc_html_e( 'Check', 'qsa-engraving' ); ?>
                                </button>
                                <button type="button" id="qsa-toggle-config" class="button button-small" style="margin-left: 5px;">
                                    <span class="dashicons dashicons-admin-generic" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom;"></span>
                                    <?php esc_html_e( 'Configure', 'qsa-engraving' ); ?>
                                </button>
                            </td>
                        </tr>
                        <?php
                        // Get current settings with defaults for configuration panel.
                        $lb_defaults = array(
                            'lightburn_host'        => '127.0.0.1',
                            'lightburn_out_port'    => 19840,
                            'lightburn_in_port'     => 19841,
                            'lightburn_timeout'     => 2,
                            'lightburn_auto_load'   => true,
                            'svg_output_dir'        => '',
                            'lightburn_path_prefix' => '',
                        );
                        $lb_settings = wp_parse_args( $system_settings, $lb_defaults );
                        ?>
                        <tr id="qsa-lightburn-config-row" style="display: none;">
                            <td colspan="2" style="padding: 15px; background: #f9f9f9;">
                                <div class="qsa-config-panel">
                                    <h4 style="margin: 0 0 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                                        <?php esc_html_e( 'LightBurn Connection Settings', 'qsa-engraving' ); ?>
                                    </h4>
                                    <div class="qsa-config-grid">
                                        <div class="qsa-config-field">
                                            <label for="qsa-lb-host"><?php esc_html_e( 'Host IP Address', 'qsa-engraving' ); ?></label>
                                            <input type="text" id="qsa-lb-host" value="<?php echo esc_attr( $lb_settings['lightburn_host'] ); ?>" placeholder="127.0.0.1" class="regular-text">
                                            <p class="description"><?php esc_html_e( 'IP address of the LightBurn workstation', 'qsa-engraving' ); ?></p>
                                        </div>
                                        <div class="qsa-config-field">
                                            <label for="qsa-lb-out-port"><?php esc_html_e( 'Output Port', 'qsa-engraving' ); ?></label>
                                            <input type="number" id="qsa-lb-out-port" value="<?php echo esc_attr( $lb_settings['lightburn_out_port'] ); ?>" min="1" max="65535" class="small-text">
                                            <p class="description"><?php esc_html_e( 'Default: 19840', 'qsa-engraving' ); ?></p>
                                        </div>
                                        <div class="qsa-config-field">
                                            <label for="qsa-lb-in-port"><?php esc_html_e( 'Input Port', 'qsa-engraving' ); ?></label>
                                            <input type="number" id="qsa-lb-in-port" value="<?php echo esc_attr( $lb_settings['lightburn_in_port'] ); ?>" min="1" max="65535" class="small-text">
                                            <p class="description"><?php esc_html_e( 'Default: 19841', 'qsa-engraving' ); ?></p>
                                        </div>
                                        <div class="qsa-config-field">
                                            <label for="qsa-lb-timeout"><?php esc_html_e( 'Timeout (seconds)', 'qsa-engraving' ); ?></label>
                                            <input type="number" id="qsa-lb-timeout" value="<?php echo esc_attr( $lb_settings['lightburn_timeout'] ); ?>" min="1" max="30" class="small-text">
                                            <p class="description"><?php esc_html_e( 'Default: 2', 'qsa-engraving' ); ?></p>
                                        </div>
                                        <div class="qsa-config-field qsa-config-full-width">
                                            <label>
                                                <input type="checkbox" id="qsa-lb-auto-load" <?php checked( $lb_settings['lightburn_auto_load'] ); ?>>
                                                <?php esc_html_e( 'Auto-load SVG when starting a row', 'qsa-engraving' ); ?>
                                            </label>
                                        </div>
                                        <div class="qsa-config-field qsa-config-full-width">
                                            <label for="qsa-lb-svg-dir"><?php esc_html_e( 'SVG Output Directory', 'qsa-engraving' ); ?></label>
                                            <input type="text" id="qsa-lb-svg-dir" value="<?php echo esc_attr( $lb_settings['svg_output_dir'] ); ?>" placeholder="<?php esc_attr_e( 'Leave empty for WordPress uploads', 'qsa-engraving' ); ?>" class="large-text">
                                            <p class="description"><?php esc_html_e( 'Absolute path where SVG files are saved (e.g., Q:\\Production\\SVG)', 'qsa-engraving' ); ?></p>
                                        </div>
                                        <div class="qsa-config-field qsa-config-full-width">
                                            <label for="qsa-lb-path-prefix"><?php esc_html_e( 'LightBurn Path Prefix', 'qsa-engraving' ); ?></label>
                                            <input type="text" id="qsa-lb-path-prefix" value="<?php echo esc_attr( $lb_settings['lightburn_path_prefix'] ); ?>" placeholder="<?php esc_attr_e( 'Leave empty if same machine', 'qsa-engraving' ); ?>" class="large-text">
                                            <p class="description"><?php esc_html_e( 'How LightBurn accesses the SVG directory (if different from server path)', 'qsa-engraving' ); ?></p>
                                        </div>
                                    </div>
                                    <div class="qsa-config-actions" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                                        <button type="button" id="qsa-save-lb-config" class="button button-primary">
                                            <?php esc_html_e( 'Save Settings', 'qsa-engraving' ); ?>
                                        </button>
                                        <button type="button" id="qsa-cancel-lb-config" class="button" style="margin-left: 10px;">
                                            <?php esc_html_e( 'Cancel', 'qsa-engraving' ); ?>
                                        </button>
                                        <span id="qsa-config-save-status" style="margin-left: 10px;"></span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="qsa-toggle-keep-svg"><?php esc_html_e( 'Keep SVG Files', 'qsa-engraving' ); ?></label>
                                <p class="description" style="margin: 4px 0 0; font-size: 11px; color: #666;">
                                    <?php esc_html_e( 'Retain generated SVG files for inspection', 'qsa-engraving' ); ?>
                                </p>
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
                /* Connection status indicator */
                .qsa-connection-indicator {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                }
                .qsa-connection-dot {
                    display: inline-block;
                    width: 10px;
                    height: 10px;
                    border-radius: 50%;
                    background-color: #ccc;
                }
                .qsa-connection-dot.checking {
                    background-color: #dba617;
                    animation: pulse 1s infinite;
                }
                .qsa-connection-dot.connected {
                    background-color: #00a32a;
                }
                .qsa-connection-dot.disconnected {
                    background-color: #d63638;
                }
                .qsa-connection-text {
                    font-size: 13px;
                }
                .qsa-connection-text.connected {
                    color: #00a32a;
                }
                .qsa-connection-text.disconnected {
                    color: #d63638;
                }
                @keyframes pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.5; }
                }
                /* Configuration panel */
                .qsa-config-panel {
                    max-width: 600px;
                }
                .qsa-config-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                }
                .qsa-config-field {
                    display: flex;
                    flex-direction: column;
                }
                .qsa-config-field label {
                    font-weight: 600;
                    margin-bottom: 4px;
                }
                .qsa-config-field .description {
                    margin: 4px 0 0;
                    font-size: 11px;
                    color: #666;
                }
                .qsa-config-field input[type="text"],
                .qsa-config-field input[type="number"] {
                    width: 100%;
                    max-width: 100%;
                }
                .qsa-config-field.qsa-config-full-width {
                    grid-column: 1 / -1;
                }
                .qsa-config-field input.large-text {
                    width: 100%;
                }
                #qsa-config-save-status {
                    font-size: 13px;
                }
                #qsa-config-save-status.saving {
                    color: #666;
                }
                #qsa-config-save-status.saved {
                    color: #00a32a;
                }
                #qsa-config-save-status.error {
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

                // LightBurn connection status check
                function checkLightBurnConnection() {
                    var $dot = $('.qsa-connection-dot');
                    var $text = $('.qsa-connection-text');
                    var $btn = $('#qsa-check-connection');

                    // Set checking state
                    $dot.removeClass('connected disconnected').addClass('checking');
                    $text.removeClass('connected disconnected').text('<?php echo esc_js( __( 'Checking...', 'qsa-engraving' ) ); ?>');
                    $btn.prop('disabled', true);

                    $.post(ajaxUrl, {
                        action: 'qsa_get_lightburn_status',
                        nonce: nonce
                    }, function(response) {
                        $btn.prop('disabled', false);
                        $dot.removeClass('checking');

                        if (response.success && response.data) {
                            if (response.data.connected) {
                                $dot.addClass('connected');
                                $text.addClass('connected').text('<?php echo esc_js( __( 'Connected', 'qsa-engraving' ) ); ?>');
                            } else {
                                $dot.addClass('disconnected');
                                $text.addClass('disconnected').text('<?php echo esc_js( __( 'Not Connected', 'qsa-engraving' ) ); ?>');
                            }
                        } else {
                            $dot.addClass('disconnected');
                            $text.addClass('disconnected').text('<?php echo esc_js( __( 'Check Failed', 'qsa-engraving' ) ); ?>');
                        }
                    }).fail(function() {
                        $btn.prop('disabled', false);
                        $dot.removeClass('checking').addClass('disconnected');
                        $text.addClass('disconnected').text('<?php echo esc_js( __( 'Check Failed', 'qsa-engraving' ) ); ?>');
                    });
                }

                $('#qsa-toggle-lightburn').on('change', function() {
                    var isEnabled = $(this).is(':checked');
                    saveToggle('lightburn_enabled', isEnabled, $('#qsa-lightburn-status'));

                    // Show/hide connection row and config row
                    if (isEnabled) {
                        $('#qsa-lightburn-connection-row').show();
                        // Check connection after enabling
                        setTimeout(checkLightBurnConnection, 500);
                    } else {
                        $('#qsa-lightburn-connection-row').hide();
                        $('#qsa-lightburn-config-row').hide();
                    }
                });

                $('#qsa-toggle-keep-svg').on('change', function() {
                    saveToggle('keep_svg_files', $(this).is(':checked'), $('#qsa-keep-svg-status'));
                });

                // Manual check button
                $('#qsa-check-connection').on('click', function() {
                    checkLightBurnConnection();
                });

                // Toggle configuration panel
                $('#qsa-toggle-config').on('click', function() {
                    var $row = $('#qsa-lightburn-config-row');
                    if ($row.is(':visible')) {
                        $row.slideUp(200);
                    } else {
                        $row.slideDown(200);
                    }
                });

                // Cancel configuration
                $('#qsa-cancel-lb-config').on('click', function() {
                    $('#qsa-lightburn-config-row').slideUp(200);
                });

                // Save configuration
                $('#qsa-save-lb-config').on('click', function() {
                    var $btn = $(this);
                    var $status = $('#qsa-config-save-status');

                    $btn.prop('disabled', true);
                    $status.removeClass('saved error').addClass('saving').text('<?php echo esc_js( __( 'Saving...', 'qsa-engraving' ) ); ?>');

                    var data = {
                        action: 'qsa_save_lightburn_settings',
                        nonce: nonce,
                        lightburn_host: $('#qsa-lb-host').val(),
                        lightburn_out_port: $('#qsa-lb-out-port').val(),
                        lightburn_in_port: $('#qsa-lb-in-port').val(),
                        lightburn_timeout: $('#qsa-lb-timeout').val(),
                        lightburn_auto_load: $('#qsa-lb-auto-load').is(':checked') ? 1 : 0,
                        svg_output_dir: $('#qsa-lb-svg-dir').val(),
                        lightburn_path_prefix: $('#qsa-lb-path-prefix').val()
                    };

                    $.post(ajaxUrl, data, function(response) {
                        $btn.prop('disabled', false);
                        if (response.success) {
                            $status.removeClass('saving').addClass('saved').text('<?php echo esc_js( __( 'Saved!', 'qsa-engraving' ) ); ?>');

                            // Update the host display
                            $('#qsa-target-host-display').text($('#qsa-lb-host').val() || '127.0.0.1');

                            // Re-check connection with new settings
                            setTimeout(checkLightBurnConnection, 500);

                            // Hide config panel after brief delay
                            setTimeout(function() {
                                $('#qsa-lightburn-config-row').slideUp(200);
                                $status.text('');
                            }, 1500);
                        } else {
                            $status.removeClass('saving').addClass('error').text(response.message || '<?php echo esc_js( __( 'Save failed', 'qsa-engraving' ) ); ?>');
                        }
                    }).fail(function() {
                        $btn.prop('disabled', false);
                        $status.removeClass('saving').addClass('error').text('<?php echo esc_js( __( 'Request failed', 'qsa-engraving' ) ); ?>');
                    });
                });

                // Auto-check on page load if LightBurn is enabled
                <?php if ( $lightburn_enabled ) : ?>
                setTimeout(checkLightBurnConnection, 1000);
                <?php endif; ?>
            });
            </script>
        </div>
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
            'lightburn_enabled'     => false,
            'lightburn_host'        => '127.0.0.1',
            'lightburn_out_port'    => 19840,
            'lightburn_in_port'     => 19841,
            'lightburn_timeout'     => 2,
            'lightburn_auto_load'   => true,
            'svg_output_dir'        => '',
            'lightburn_path_prefix' => '',
        );

        $settings = wp_parse_args( $settings, $defaults );
        ?>
        <div class="qsa-settings-wrap">
            <form method="post" action="options.php" id="qsa-settings-form">
                <?php wp_nonce_field( 'qsa_engraving_settings_nonce', 'qsa_settings_nonce' ); ?>

                <!-- LightBurn Integration Settings -->
                <div class="qsa-settings-section">
                    <h2><?php esc_html_e( 'LightBurn Integration', 'qsa-engraving' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Configure the connection to LightBurn software for automatic SVG loading.', 'qsa-engraving' ); ?>
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="lightburn_enabled"><?php esc_html_e( 'Enable LightBurn Integration', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="lightburn_enabled" id="lightburn_enabled" value="1" <?php checked( $settings['lightburn_enabled'] ); ?>>
                                    <?php esc_html_e( 'Enable UDP communication with LightBurn', 'qsa-engraving' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'When enabled, SVG files will be automatically sent to LightBurn during engraving.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lightburn_host"><?php esc_html_e( 'LightBurn Host IP', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="lightburn_host" id="lightburn_host" value="<?php echo esc_attr( $settings['lightburn_host'] ); ?>" class="regular-text" placeholder="127.0.0.1">
                                <p class="description">
                                    <?php esc_html_e( 'IP address of the machine running LightBurn. Use 127.0.0.1 for localhost.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lightburn_out_port"><?php esc_html_e( 'Output Port', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="lightburn_out_port" id="lightburn_out_port" value="<?php echo esc_attr( $settings['lightburn_out_port'] ); ?>" min="1" max="65535" class="small-text">
                                <p class="description">
                                    <?php esc_html_e( 'Port for sending commands to LightBurn. Default: 19840', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lightburn_in_port"><?php esc_html_e( 'Input Port', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="lightburn_in_port" id="lightburn_in_port" value="<?php echo esc_attr( $settings['lightburn_in_port'] ); ?>" min="1" max="65535" class="small-text">
                                <p class="description">
                                    <?php esc_html_e( 'Port for receiving responses from LightBurn. Default: 19841', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lightburn_timeout"><?php esc_html_e( 'Connection Timeout', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="lightburn_timeout" id="lightburn_timeout" value="<?php echo esc_attr( $settings['lightburn_timeout'] ); ?>" min="1" max="30" class="small-text">
                                <span><?php esc_html_e( 'seconds', 'qsa-engraving' ); ?></span>
                                <p class="description">
                                    <?php esc_html_e( 'Time to wait for LightBurn to respond before timing out. Default: 2', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lightburn_auto_load"><?php esc_html_e( 'Auto-Load SVG', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="lightburn_auto_load" id="lightburn_auto_load" value="1" <?php checked( $settings['lightburn_auto_load'] ); ?>>
                                    <?php esc_html_e( 'Automatically load SVG in LightBurn when starting a row', 'qsa-engraving' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'When enabled, the SVG will be sent to LightBurn automatically. Otherwise, use the "Load" button manually.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Connection Test', 'qsa-engraving' ); ?></th>
                            <td>
                                <button type="button" id="qsa-test-lightburn" class="button button-secondary">
                                    <span class="dashicons dashicons-admin-plugins"></span>
                                    <?php esc_html_e( 'Test Connection', 'qsa-engraving' ); ?>
                                </button>
                                <span id="qsa-lightburn-test-result" class="qsa-test-result"></span>
                                <p class="description">
                                    <?php esc_html_e( 'Test the connection to LightBurn. Make sure LightBurn is running before testing.', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- SVG Output Settings -->
                <div class="qsa-settings-section">
                    <h2><?php esc_html_e( 'SVG File Settings', 'qsa-engraving' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Configure where SVG files are saved and how LightBurn accesses them.', 'qsa-engraving' ); ?>
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="svg_output_dir"><?php esc_html_e( 'SVG Output Directory', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="svg_output_dir" id="svg_output_dir" value="<?php echo esc_attr( $settings['svg_output_dir'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Leave empty to use WordPress uploads directory', 'qsa-engraving' ); ?>">
                                <p class="description">
                                    <?php esc_html_e( 'Absolute path where SVG files will be saved. For network shares, use the full path (e.g., Q:\\Shared drives\\Production\\SVG).', 'qsa-engraving' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lightburn_path_prefix"><?php esc_html_e( 'LightBurn Path Prefix', 'qsa-engraving' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="lightburn_path_prefix" id="lightburn_path_prefix" value="<?php echo esc_attr( $settings['lightburn_path_prefix'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g., Q:\\Production\\SVG', 'qsa-engraving' ); ?>">
                                <p class="description">
                                    <?php esc_html_e( 'If LightBurn runs on a different machine, specify how it accesses the SVG directory. Leave empty if using the same machine.', 'qsa-engraving' ); ?>
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

                <p class="submit">
                    <button type="submit" id="qsa-save-settings" class="button button-primary">
                        <?php esc_html_e( 'Save Settings', 'qsa-engraving' ); ?>
                    </button>
                    <span id="qsa-save-result" class="qsa-save-result"></span>
                </p>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( 'qsa_engraving_nonce' ) ); ?>';
            var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

            // Test LightBurn connection
            $('#qsa-test-lightburn').on('click', function() {
                var $btn = $(this);
                var $result = $('#qsa-lightburn-test-result');

                $btn.prop('disabled', true);
                $result.removeClass('success error').text('<?php echo esc_js( __( 'Testing...', 'qsa-engraving' ) ); ?>');

                $.post(ajaxUrl, {
                    action: 'qsa_test_lightburn',
                    nonce: nonce
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $result.addClass('success').text('<?php echo esc_js( __( 'Connected!', 'qsa-engraving' ) ); ?>');
                    } else {
                        $result.addClass('error').text(response.message || '<?php echo esc_js( __( 'Connection failed', 'qsa-engraving' ) ); ?>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $result.addClass('error').text('<?php echo esc_js( __( 'Request failed', 'qsa-engraving' ) ); ?>');
                });
            });

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
                    lightburn_host: $('#lightburn_host').val(),
                    lightburn_out_port: $('#lightburn_out_port').val(),
                    lightburn_in_port: $('#lightburn_in_port').val(),
                    lightburn_timeout: $('#lightburn_timeout').val(),
                    lightburn_auto_load: $('#lightburn_auto_load').is(':checked') ? 1 : 0,
                    svg_output_dir: $('#svg_output_dir').val(),
                    lightburn_path_prefix: $('#lightburn_path_prefix').val()
                };

                $.post(ajaxUrl, data, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $result.addClass('success').text('<?php echo esc_js( __( 'Settings saved!', 'qsa-engraving' ) ); ?>');
                    } else {
                        $result.addClass('error').text(response.message || '<?php echo esc_js( __( 'Save failed', 'qsa-engraving' ) ); ?>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $result.addClass('error').text('<?php echo esc_js( __( 'Request failed', 'qsa-engraving' ) ); ?>');
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
