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
            $system_settings   = get_option( 'qsa_engraving_settings', array() );
            $svg_enabled       = ! empty( $system_settings['lightburn_enabled'] );
            $keep_svg_files    = ! empty( $system_settings['keep_svg_files'] );
            $svg_rotation      = isset( $system_settings['svg_rotation'] ) ? (int) $system_settings['svg_rotation'] : 0;
            $svg_top_offset    = isset( $system_settings['svg_top_offset'] ) ? (float) $system_settings['svg_top_offset'] : 0.0;
            $file_manager      = new \Quadica\QSA_Engraving\Services\SVG_File_Manager();
            $svg_status        = $file_manager->get_status();
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
                            <td>
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
                            <td>
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
                        <tr id="qsa-svg-rotation-row" <?php echo $svg_enabled ? '' : 'style="display:none;"'; ?>>
                            <td>
                                <label for="qsa-svg-rotation"><?php esc_html_e( 'SVG Rotation', 'qsa-engraving' ); ?></label>
                            </td>
                            <td>
                                <select id="qsa-svg-rotation" class="qsa-rotation-select">
                                    <option value="0" <?php selected( $svg_rotation, 0 ); ?>>0° (No rotation)</option>
                                    <option value="90" <?php selected( $svg_rotation, 90 ); ?>>90° Clockwise</option>
                                    <option value="180" <?php selected( $svg_rotation, 180 ); ?>>180°</option>
                                    <option value="270" <?php selected( $svg_rotation, 270 ); ?>>270° Clockwise</option>
                                </select>
                                <span class="qsa-toggle-status" id="qsa-rotation-status"></span>
                            </td>
                        </tr>
                        <tr id="qsa-svg-top-offset-row" <?php echo $svg_enabled ? '' : 'style="display:none;"'; ?>>
                            <td>
                                <label for="qsa-svg-top-offset"><?php esc_html_e( 'Top Offset', 'qsa-engraving' ); ?></label>
                            </td>
                            <td>
                                <input type="number" id="qsa-svg-top-offset" class="qsa-offset-input"
                                    value="<?php echo esc_attr( number_format( $svg_top_offset, 2, '.', '' ) ); ?>"
                                    min="-5" max="5" step="0.02">
                                <span class="qsa-offset-unit">mm</span>
                                <span class="qsa-toggle-status" id="qsa-top-offset-status"></span>
                            </td>
                        </tr>
                        <tr id="qsa-svg-directory-row" <?php echo $svg_enabled ? '' : 'style="display:none;"'; ?>>
                            <td><?php esc_html_e( 'SVG Directory', 'qsa-engraving' ); ?></td>
                            <td>
                                <?php if ( $svg_status['exists'] && $svg_status['writable'] ) : ?>
                                    <span class="qsa-status-ok">
                                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                        <?php
                                        printf(
                                            /* translators: %d: Number of SVG files */
                                            esc_html__( 'Ready (%d files)', 'qsa-engraving' ),
                                            $svg_status['file_count']
                                        );
                                        ?>
                                    </span>
                                <?php else : ?>
                                    <span class="qsa-status-warning">
                                        <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                                        <?php esc_html_e( 'Directory not ready', 'qsa-engraving' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr id="qsa-watcher-info-row" <?php echo $svg_enabled ? '' : 'style="display:none;"'; ?>>
                            <td><?php esc_html_e( 'LightBurn Watcher', 'qsa-engraving' ); ?></td>
                            <td>
                                <span class="qsa-watcher-info">
                                    <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                                    <?php esc_html_e( 'SVG files are automatically picked up by the watcher on the production workstation', 'qsa-engraving' ); ?>
                                </span>
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
                .qsa-watcher-info {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    font-size: 13px;
                    color: #50575e;
                }
                .qsa-rotation-select {
                    min-width: 160px;
                    padding: 4px 8px;
                    border: 1px solid #8c8f94;
                    border-radius: 4px;
                    background: #fff;
                    font-size: 13px;
                    vertical-align: middle;
                }
                .qsa-rotation-select:focus {
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px #2271b1;
                    outline: none;
                }
                .qsa-offset-input {
                    width: 80px;
                    padding: 4px 8px;
                    border: 1px solid #8c8f94;
                    border-radius: 4px;
                    font-size: 13px;
                    text-align: right;
                }
                .qsa-offset-input:focus {
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px #2271b1;
                    outline: none;
                }
                .qsa-offset-unit {
                    margin-left: 4px;
                    color: #50575e;
                    font-size: 13px;
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
                    var isEnabled = $(this).is(':checked');
                    saveToggle('lightburn_enabled', isEnabled, $('#qsa-svg-generation-status'));

                    // Show/hide SVG-related rows
                    if (isEnabled) {
                        $('#qsa-svg-rotation-row').show();
                        $('#qsa-svg-top-offset-row').show();
                        $('#qsa-svg-directory-row').show();
                        $('#qsa-watcher-info-row').show();
                    } else {
                        $('#qsa-svg-rotation-row').hide();
                        $('#qsa-svg-top-offset-row').hide();
                        $('#qsa-svg-directory-row').hide();
                        $('#qsa-watcher-info-row').hide();
                    }
                });

                // Keep SVG Files toggle
                $('#qsa-toggle-keep-svg').on('change', function() {
                    saveToggle('keep_svg_files', $(this).is(':checked'), $('#qsa-keep-svg-status'));
                });

                // SVG Rotation dropdown
                $('#qsa-svg-rotation').on('change', function() {
                    var $status = $('#qsa-rotation-status');
                    var rotation = $(this).val();

                    $status.removeClass('saved error').addClass('saving').text('<?php echo esc_js( __( 'Saving...', 'qsa-engraving' ) ); ?>');

                    $.post(ajaxUrl, {
                        action: 'qsa_save_lightburn_settings',
                        nonce: nonce,
                        svg_rotation: rotation
                    }, function(response) {
                        if (response.success) {
                            $status.removeClass('saving').addClass('saved').text('<?php echo esc_js( __( 'Saved', 'qsa-engraving' ) ); ?>');
                            setTimeout(function() { $status.text(''); }, 2000);
                        } else {
                            $status.removeClass('saving').addClass('error').text('<?php echo esc_js( __( 'Error', 'qsa-engraving' ) ); ?>');
                        }
                    }).fail(function() {
                        $status.removeClass('saving').addClass('error').text('<?php echo esc_js( __( 'Failed', 'qsa-engraving' ) ); ?>');
                    });
                });

                // SVG Top Offset input
                $('#qsa-svg-top-offset').on('change', function() {
                    var $status = $('#qsa-top-offset-status');
                    var offset = parseFloat($(this).val()) || 0;

                    // Clamp to valid range
                    if (offset < -5) offset = -5;
                    if (offset > 5) offset = 5;
                    $(this).val(offset.toFixed(2));

                    $status.removeClass('saved error').addClass('saving').text('<?php echo esc_js( __( 'Saving...', 'qsa-engraving' ) ); ?>');

                    $.post(ajaxUrl, {
                        action: 'qsa_save_lightburn_settings',
                        nonce: nonce,
                        svg_top_offset: offset
                    }, function(response) {
                        if (response.success) {
                            $status.removeClass('saving').addClass('saved').text('<?php echo esc_js( __( 'Saved', 'qsa-engraving' ) ); ?>');
                            setTimeout(function() { $status.text(''); }, 2000);
                        } else {
                            $status.removeClass('saving').addClass('error').text('<?php echo esc_js( __( 'Error', 'qsa-engraving' ) ); ?>');
                        }
                    }).fail(function() {
                        $status.removeClass('saving').addClass('error').text('<?php echo esc_js( __( 'Failed', 'qsa-engraving' ) ); ?>');
                    });
                });
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
            'lightburn_enabled' => false,
            'keep_svg_files'    => true,
            'svg_output_dir'    => '',
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

                <!-- LightBurn Watcher Information -->
                <div class="qsa-settings-section">
                    <h2><?php esc_html_e( 'LightBurn Watcher', 'qsa-engraving' ); ?></h2>
                    <div class="qsa-info-box">
                        <p>
                            <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                            <?php esc_html_e( 'The LightBurn Watcher is a separate script that runs on the production workstation. It monitors this server via SFTP for new SVG files and automatically loads them into LightBurn.', 'qsa-engraving' ); ?>
                        </p>
                        <table class="qsa-info-table">
                            <tr>
                                <td><strong><?php esc_html_e( 'Polling Interval:', 'qsa-engraving' ); ?></strong></td>
                                <td><?php esc_html_e( '3 seconds', 'qsa-engraving' ); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Script Location:', 'qsa-engraving' ); ?></strong></td>
                                <td><code>C:\Users\Production\Documents\repos\qip\lightburn-watcher.js</code></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Process Manager:', 'qsa-engraving' ); ?></strong></td>
                                <td><?php esc_html_e( 'PM2 (auto-restart on crashes)', 'qsa-engraving' ); ?></td>
                            </tr>
                        </table>
                        <p class="description" style="margin-top: 10px;">
                            <?php esc_html_e( 'To restart the watcher on the production machine, run:', 'qsa-engraving' ); ?>
                            <code>pm2 restart lightburn-watcher</code>
                        </p>
                    </div>
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
                    svg_output_dir: $('#svg_output_dir').val()
                };

                $.post(ajaxUrl, data, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $result.addClass('success').text('<?php echo esc_js( __( 'Settings saved!', 'qsa-engraving' ) ); ?>');
                        setTimeout(function() { $result.text(''); }, 3000);
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
