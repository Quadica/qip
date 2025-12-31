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
     * @return void
     */
    public function render_batch_creator_page(): void {
        if ( ! $this->user_has_access() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'qsa-engraving' ) );
        }

        $this->render_page_header( __( 'Module Engraving Batch Creator', 'qsa-engraving' ) );
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

        $this->render_page_header( __( 'Engraving Queue', 'qsa-engraving' ) );
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

        $this->render_page_header( __( 'Engraving Batch History', 'qsa-engraving' ) );
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
                        <span class="stat-value"><?php echo esc_html( number_format( $capacity['used'] ) ); ?></span>
                        <span class="stat-label"><?php esc_html_e( 'Used', 'qsa-engraving' ); ?></span>
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
            </div>

            <!-- System Status -->
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
                    </tbody>
                </table>
            </div>
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
        ?>
        <form method="post" action="options.php">
            <p><?php esc_html_e( 'Settings page will be implemented in Phase 8.', 'qsa-engraving' ); ?></p>
        </form>
        <?php
    }
}
