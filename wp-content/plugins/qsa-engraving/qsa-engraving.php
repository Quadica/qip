<?php
/**
 * Plugin Name: QSA Engraving
 * Plugin URI: https://quadica.com
 * Description: Generates SVG files for UV laser engraving of Quadica Standard Array (QSA) LED modules.
 * Version: 1.0.0
 * Author: Quadica Developments
 * Author URI: https://quadica.com
 * License: Proprietary
 * License URI: https://quadica.com/license
 * Text Domain: qsa-engraving
 * Domain Path: /languages
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * WC requires at least: 9.9
 *
 * @package QSA_Engraving
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'QSA_ENGRAVING_VERSION', '1.0.0' );
define( 'QSA_ENGRAVING_PLUGIN_FILE', __FILE__ );
define( 'QSA_ENGRAVING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QSA_ENGRAVING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QSA_ENGRAVING_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Required PHP version.
define( 'QSA_ENGRAVING_MIN_PHP_VERSION', '8.1' );

// Required WordPress version.
define( 'QSA_ENGRAVING_MIN_WP_VERSION', '6.8' );

// Required WooCommerce version.
define( 'QSA_ENGRAVING_MIN_WC_VERSION', '9.9' );

/**
 * Main plugin class implementing singleton pattern.
 *
 * @since 1.0.0
 */
final class Plugin {

    /**
     * Single instance of the plugin.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Admin menu handler.
     *
     * @var Admin\Admin_Menu|null
     */
    private ?Admin\Admin_Menu $admin_menu = null;

    /**
     * Serial Repository instance.
     *
     * @var Database\Serial_Repository|null
     */
    private ?Database\Serial_Repository $serial_repository = null;

    /**
     * Batch Repository instance.
     *
     * @var Database\Batch_Repository|null
     */
    private ?Database\Batch_Repository $batch_repository = null;

    /**
     * Config Repository instance.
     *
     * @var Database\Config_Repository|null
     */
    private ?Database\Config_Repository $config_repository = null;

    /**
     * QSA Identifier Repository instance.
     *
     * @var Database\QSA_Identifier_Repository|null
     */
    private ?Database\QSA_Identifier_Repository $qsa_identifier_repository = null;

    /**
     * Module Selector Service instance.
     *
     * @var Services\Module_Selector|null
     */
    private ?Services\Module_Selector $module_selector = null;

    /**
     * Batch Sorter Service instance.
     *
     * @var Services\Batch_Sorter|null
     */
    private ?Services\Batch_Sorter $batch_sorter = null;

    /**
     * LED Code Resolver Service instance.
     *
     * @var Services\LED_Code_Resolver|null
     */
    private ?Services\LED_Code_Resolver $led_code_resolver = null;

    /**
     * Legacy SKU Resolver Service instance.
     *
     * @var Services\Legacy_SKU_Resolver|null
     */
    private ?Services\Legacy_SKU_Resolver $legacy_sku_resolver = null;

    /**
     * Batch AJAX Handler instance.
     *
     * @var Ajax\Batch_Ajax_Handler|null
     */
    private ?Ajax\Batch_Ajax_Handler $batch_ajax_handler = null;

    /**
     * Queue AJAX Handler instance.
     *
     * @var Ajax\Queue_Ajax_Handler|null
     */
    private ?Ajax\Queue_Ajax_Handler $queue_ajax_handler = null;

    /**
     * LightBurn AJAX Handler instance.
     *
     * @var Ajax\LightBurn_Ajax_Handler|null
     */
    private ?Ajax\LightBurn_Ajax_Handler $lightburn_ajax_handler = null;

    /**
     * History AJAX Handler instance.
     *
     * @var Ajax\History_Ajax_Handler|null
     */
    private ?Ajax\History_Ajax_Handler $history_ajax_handler = null;

    /**
     * QSA Landing Handler instance.
     *
     * @var Frontend\QSA_Landing_Handler|null
     */
    private ?Frontend\QSA_Landing_Handler $qsa_landing_handler = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        // Empty constructor - initialization happens in init().
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {
        // Prevent cloning.
    }

    /**
     * Prevent unserialization.
     *
     * @throws \Exception If attempted.
     */
    public function __wakeup(): void {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Get the single instance of the plugin.
     *
     * @return Plugin
     */
    public static function get_instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin.
     *
     * @return void
     */
    public function init(): void {
        // Check requirements before proceeding.
        if ( ! $this->check_requirements() ) {
            return;
        }

        // Load the autoloader.
        $this->load_autoloader();

        // Initialize components.
        $this->init_repositories();
        $this->init_services();

        // Hook into WordPress.
        add_action( 'admin_init', array( $this, 'check_woocommerce' ) );
        add_action( 'admin_init', array( $this, 'check_serial_capacity' ) );
        add_action( 'admin_menu', array( $this, 'init_admin_menu' ), 99 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Register scheduled cleanup for old SVG files.
        add_action( 'qsa_engraving_cleanup_svg_files', array( $this, 'cleanup_old_svg_files' ) );
        $this->schedule_svg_cleanup();
    }

    /**
     * Schedule the recurring SVG cleanup task.
     *
     * Uses Action Scheduler if available, otherwise falls back to WP-Cron.
     *
     * @return void
     */
    private function schedule_svg_cleanup(): void {
        // Check if Action Scheduler is available (WooCommerce includes it).
        if ( function_exists( 'as_next_scheduled_action' ) ) {
            // Use Action Scheduler.
            if ( false === as_next_scheduled_action( 'qsa_engraving_cleanup_svg_files' ) ) {
                as_schedule_recurring_action(
                    time() + HOUR_IN_SECONDS,
                    DAY_IN_SECONDS,
                    'qsa_engraving_cleanup_svg_files',
                    array(),
                    'qsa-engraving'
                );
            }
        } else {
            // Fall back to WP-Cron.
            if ( ! wp_next_scheduled( 'qsa_engraving_cleanup_svg_files' ) ) {
                wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'qsa_engraving_cleanup_svg_files' );
            }
        }
    }

    /**
     * Clean up old SVG files based on age.
     *
     * SVG files are ephemeral and should not persist beyond their immediate use.
     * This cleanup runs daily and removes files older than 24 hours.
     *
     * @return void
     */
    public function cleanup_old_svg_files(): void {
        $file_manager = new Services\SVG_File_Manager();
        $deleted      = $file_manager->cleanup_old_files_by_age( 24 ); // 24 hours max age.

        if ( $deleted > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( 'QSA Engraving: Cleaned up %d old SVG files.', $deleted ) );
        }
    }

    /**
     * Check if all requirements are met.
     *
     * @return bool True if all requirements are met.
     */
    private function check_requirements(): bool {
        // Check PHP version.
        if ( version_compare( PHP_VERSION, QSA_ENGRAVING_MIN_PHP_VERSION, '<' ) ) {
            add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
            return false;
        }

        // Check WordPress version.
        global $wp_version;
        if ( version_compare( $wp_version, QSA_ENGRAVING_MIN_WP_VERSION, '<' ) ) {
            add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
            return false;
        }

        return true;
    }

    /**
     * Check if WooCommerce is active and meets version requirements.
     *
     * @return void
     */
    public function check_woocommerce(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Check WooCommerce version.
        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, QSA_ENGRAVING_MIN_WC_VERSION, '<' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_version_notice' ) );
        }
    }

    /**
     * Check serial number capacity and display warning notices if low.
     *
     * Only displays notices to users with manage_woocommerce capability.
     *
     * @return void
     */
    public function check_serial_capacity(): void {
        // Only check for users who can access QSA Engraving.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Only check if serial repository is initialized and table exists.
        if ( null === $this->serial_repository || ! $this->serial_repository->table_exists() ) {
            return;
        }

        $capacity = $this->serial_repository->get_capacity();

        // Display appropriate notice based on capacity level.
        if ( $capacity['critical'] ) {
            add_action( 'admin_notices', array( $this, 'serial_capacity_critical_notice' ) );
        } elseif ( $capacity['warning'] ) {
            add_action( 'admin_notices', array( $this, 'serial_capacity_warning_notice' ) );
        }
    }

    /**
     * Display critical serial capacity notice.
     *
     * @return void
     */
    public function serial_capacity_critical_notice(): void {
        $capacity = $this->serial_repository->get_capacity();
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e( 'QSA Engraving - Critical:', 'qsa-engraving' ); ?></strong>
                <?php
                printf(
                    /* translators: 1: Remaining count, 2: Total capacity */
                    esc_html__( 'Serial number capacity critically low! Only %1$s of %2$s serial numbers remaining. Contact support immediately.', 'qsa-engraving' ),
                    '<strong>' . esc_html( number_format( $capacity['remaining'] ) ) . '</strong>',
                    esc_html( number_format( $capacity['total'] ) )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display warning serial capacity notice.
     *
     * @return void
     */
    public function serial_capacity_warning_notice(): void {
        $capacity = $this->serial_repository->get_capacity();
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'QSA Engraving - Warning:', 'qsa-engraving' ); ?></strong>
                <?php
                printf(
                    /* translators: 1: Remaining count, 2: Percentage remaining */
                    esc_html__( 'Serial number capacity is running low. %1$s serial numbers remaining (%2$s%% of capacity).', 'qsa-engraving' ),
                    '<strong>' . esc_html( number_format( $capacity['remaining'] ) ) . '</strong>',
                    esc_html( $capacity['percentage_remaining'] )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Load the PSR-4 autoloader and Composer dependencies.
     *
     * @return void
     */
    private function load_autoloader(): void {
        // Load custom PSR-4 autoloader for plugin classes.
        require_once QSA_ENGRAVING_PLUGIN_DIR . 'includes/Autoloader.php';
        Autoloader::register();

        // Load Composer autoloader for dependencies (e.g., tc-lib-barcode).
        // The vendor directory must be committed since production lacks Composer.
        $composer_autoload = QSA_ENGRAVING_PLUGIN_DIR . 'vendor/autoload.php';
        if ( file_exists( $composer_autoload ) ) {
            require_once $composer_autoload;
        }
    }

    /**
     * Initialize repository classes.
     *
     * @return void
     */
    private function init_repositories(): void {
        $this->serial_repository         = new Database\Serial_Repository();
        $this->batch_repository          = new Database\Batch_Repository();
        $this->config_repository         = new Database\Config_Repository();
        $this->qsa_identifier_repository = new Database\QSA_Identifier_Repository();
    }

    /**
     * Initialize service classes.
     *
     * @return void
     */
    private function init_services(): void {
        $this->module_selector   = new Services\Module_Selector( $this->batch_repository );
        $this->batch_sorter      = new Services\Batch_Sorter();
        $this->led_code_resolver = new Services\LED_Code_Resolver();

        // Initialize AJAX handlers.
        $this->batch_ajax_handler = new Ajax\Batch_Ajax_Handler(
            $this->module_selector,
            $this->batch_sorter,
            $this->batch_repository,
            $this->serial_repository,
            $this->led_code_resolver
        );
        $this->batch_ajax_handler->register();

        // Initialize Queue AJAX handler.
        $this->queue_ajax_handler = new Ajax\Queue_Ajax_Handler(
            $this->batch_sorter,
            $this->batch_repository,
            $this->serial_repository
        );
        $this->queue_ajax_handler->register();

        // Initialize LightBurn AJAX handler (Phase 7).
        $this->lightburn_ajax_handler = new Ajax\LightBurn_Ajax_Handler(
            $this->batch_repository,
            $this->serial_repository,
            $this->led_code_resolver,
            $this->qsa_identifier_repository
        );
        $this->lightburn_ajax_handler->register();

        // Initialize History AJAX handler (Phase 8).
        $this->history_ajax_handler = new Ajax\History_Ajax_Handler(
            $this->batch_repository,
            $this->serial_repository
        );
        $this->history_ajax_handler->register();

        // Initialize QSA Landing Handler (Frontend - handles quadi.ca redirects).
        $this->qsa_landing_handler = new Frontend\QSA_Landing_Handler(
            $this->qsa_identifier_repository
        );
        $this->qsa_landing_handler->register();
    }

    /**
     * Initialize the admin menu.
     *
     * @return void
     */
    public function init_admin_menu(): void {
        $this->admin_menu = new Admin\Admin_Menu();
        $this->admin_menu->register();
    }

    /**
     * Enqueue admin CSS and JavaScript.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     * @return void
     */
    public function enqueue_admin_assets( string $hook_suffix ): void {
        // Only load on our plugin pages.
        if ( strpos( $hook_suffix, 'qsa-engraving' ) === false ) {
            return;
        }

        // Admin CSS.
        wp_enqueue_style(
            'qsa-engraving-admin',
            QSA_ENGRAVING_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            QSA_ENGRAVING_VERSION
        );

        // Determine which page we're on and load appropriate assets.
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        // Get LightBurn settings.
        $settings = get_option( 'qsa_engraving_settings', array() );

        // Localization data for all scripts.
        $localization_data = array(
            'nonce'            => wp_create_nonce( 'qsa_engraving_nonce' ),
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'restUrl'          => rest_url( 'qsa-engraving/v1/' ),
            'lightburnEnabled' => (bool) ( $settings['lightburn_enabled'] ?? false ),
            'lightburnAutoLoad' => (bool) ( $settings['lightburn_auto_load'] ?? true ),
            'keepSvgFiles'     => (bool) ( $settings['keep_svg_files'] ?? false ),
        );

        // Batch Creator page.
        if ( 'qsa-engraving-batch-creator' === $page ) {
            $this->enqueue_react_bundle( 'batch-creator', $localization_data );
        }

        // Engraving Queue page (Phase 6).
        if ( 'qsa-engraving-queue' === $page ) {
            $this->enqueue_react_bundle( 'engraving-queue', $localization_data );
        }

        // Batch History page (Phase 8).
        if ( 'qsa-engraving-history' === $page ) {
            $this->enqueue_react_bundle( 'batch-history', $localization_data );
        }
    }

    /**
     * Enqueue a React bundle with its dependencies.
     *
     * @param string $bundle_name      The bundle name (e.g., 'batch-creator').
     * @param array  $localization_data Data to pass to the script.
     * @return void
     */
    private function enqueue_react_bundle( string $bundle_name, array $localization_data ): void {
        $js_path    = QSA_ENGRAVING_PLUGIN_DIR . "assets/js/build/{$bundle_name}.js";
        $asset_path = QSA_ENGRAVING_PLUGIN_DIR . "assets/js/build/{$bundle_name}.asset.php";

        if ( ! file_exists( $js_path ) ) {
            return;
        }

        // Load dependencies from asset file if available.
        $dependencies = array( 'wp-element', 'wp-i18n', 'wp-api-fetch' );
        $version      = QSA_ENGRAVING_VERSION;

        if ( file_exists( $asset_path ) ) {
            $asset        = require $asset_path;
            $dependencies = $asset['dependencies'] ?? $dependencies;
            $version      = $asset['version'] ?? $version;
        }

        wp_enqueue_script(
            "qsa-engraving-{$bundle_name}",
            QSA_ENGRAVING_PLUGIN_URL . "assets/js/build/{$bundle_name}.js",
            $dependencies,
            $version,
            true
        );

        // Enqueue the CSS if available.
        // @wordpress/scripts outputs CSS as style-{bundle}.css, not {bundle}.css.
        $css_filename = "style-{$bundle_name}.css";
        $css_path     = QSA_ENGRAVING_PLUGIN_DIR . "assets/js/build/{$css_filename}";
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style(
                "qsa-engraving-{$bundle_name}",
                QSA_ENGRAVING_PLUGIN_URL . "assets/js/build/{$css_filename}",
                array(),
                $version
            );
        }

        // Localize script.
        wp_localize_script(
            "qsa-engraving-{$bundle_name}",
            'qsaEngraving',
            $localization_data
        );
    }

    /**
     * Display PHP version notice.
     *
     * @return void
     */
    public function php_version_notice(): void {
        $message = sprintf(
            /* translators: 1: Required PHP version, 2: Current PHP version */
            esc_html__( 'QSA Engraving requires PHP %1$s or higher. You are running PHP %2$s.', 'qsa-engraving' ),
            QSA_ENGRAVING_MIN_PHP_VERSION,
            PHP_VERSION
        );
        printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
    }

    /**
     * Display WordPress version notice.
     *
     * @return void
     */
    public function wp_version_notice(): void {
        global $wp_version;
        $message = sprintf(
            /* translators: 1: Required WordPress version, 2: Current WordPress version */
            esc_html__( 'QSA Engraving requires WordPress %1$s or higher. You are running WordPress %2$s.', 'qsa-engraving' ),
            QSA_ENGRAVING_MIN_WP_VERSION,
            $wp_version
        );
        printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
    }

    /**
     * Display WooCommerce missing notice.
     *
     * @return void
     */
    public function woocommerce_missing_notice(): void {
        $message = esc_html__( 'QSA Engraving requires WooCommerce to be installed and activated.', 'qsa-engraving' );
        printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
    }

    /**
     * Display WooCommerce version notice.
     *
     * @return void
     */
    public function woocommerce_version_notice(): void {
        $message = sprintf(
            /* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
            esc_html__( 'QSA Engraving requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.', 'qsa-engraving' ),
            QSA_ENGRAVING_MIN_WC_VERSION,
            defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown'
        );
        printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
    }

    /**
     * Get the Serial Repository instance.
     *
     * @return Database\Serial_Repository
     */
    public function get_serial_repository(): Database\Serial_Repository {
        return $this->serial_repository;
    }

    /**
     * Get the Batch Repository instance.
     *
     * @return Database\Batch_Repository
     */
    public function get_batch_repository(): Database\Batch_Repository {
        return $this->batch_repository;
    }

    /**
     * Get the Config Repository instance.
     *
     * @return Database\Config_Repository
     */
    public function get_config_repository(): Database\Config_Repository {
        return $this->config_repository;
    }

    /**
     * Get the QSA Identifier Repository instance.
     *
     * @return Database\QSA_Identifier_Repository
     */
    public function get_qsa_identifier_repository(): Database\QSA_Identifier_Repository {
        return $this->qsa_identifier_repository;
    }

    /**
     * Get the Module Selector Service instance.
     *
     * @return Services\Module_Selector
     */
    public function get_module_selector(): Services\Module_Selector {
        return $this->module_selector;
    }

    /**
     * Get the Legacy SKU Resolver Service instance.
     *
     * @return Services\Legacy_SKU_Resolver|null
     */
    public function get_legacy_sku_resolver(): ?Services\Legacy_SKU_Resolver {
        return $this->legacy_sku_resolver;
    }

    /**
     * Get the Queue AJAX Handler instance.
     *
     * @return Ajax\Queue_Ajax_Handler|null
     */
    public function get_queue_ajax_handler(): ?Ajax\Queue_Ajax_Handler {
        return $this->queue_ajax_handler;
    }
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function activate(): void {
    // Check requirements on activation.
    if ( version_compare( PHP_VERSION, QSA_ENGRAVING_MIN_PHP_VERSION, '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            sprintf(
                /* translators: %s: Required PHP version */
                esc_html__( 'QSA Engraving requires PHP %s or higher.', 'qsa-engraving' ),
                QSA_ENGRAVING_MIN_PHP_VERSION
            )
        );
    }

    // Flush rewrite rules on activation.
    flush_rewrite_rules();

    // Set a transient to show activation notice.
    set_transient( 'qsa_engraving_activated', true, 30 );
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function deactivate(): void {
    // Flush rewrite rules on deactivation.
    flush_rewrite_rules();

    // Unschedule the SVG cleanup task.
    // Check for Action Scheduler first (WooCommerce includes it).
    if ( function_exists( 'as_unschedule_all_actions' ) ) {
        as_unschedule_all_actions( 'qsa_engraving_cleanup_svg_files' );
    }

    // Also clear WP-Cron (fallback or if Action Scheduler wasn't used).
    $timestamp = wp_next_scheduled( 'qsa_engraving_cleanup_svg_files' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'qsa_engraving_cleanup_svg_files' );
    }
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

/**
 * Initialize the plugin.
 *
 * @return Plugin
 */
function qsa_engraving(): Plugin {
    return Plugin::get_instance();
}

// Initialize on plugins_loaded to ensure all dependencies are available.
add_action( 'plugins_loaded', function (): void {
    qsa_engraving()->init();
} );
