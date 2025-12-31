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
     * Module Selector Service instance.
     *
     * @var Services\Module_Selector|null
     */
    private ?Services\Module_Selector $module_selector = null;

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
        add_action( 'admin_menu', array( $this, 'init_admin_menu' ), 99 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
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
        $this->serial_repository = new Database\Serial_Repository();
        $this->batch_repository  = new Database\Batch_Repository();
        $this->config_repository = new Database\Config_Repository();
    }

    /**
     * Initialize service classes.
     *
     * @return void
     */
    private function init_services(): void {
        $this->module_selector = new Services\Module_Selector( $this->batch_repository );
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

        // Admin JavaScript (React bundle when available).
        $js_path = QSA_ENGRAVING_PLUGIN_DIR . 'assets/js/build/admin.js';
        if ( file_exists( $js_path ) ) {
            wp_enqueue_script(
                'qsa-engraving-admin',
                QSA_ENGRAVING_PLUGIN_URL . 'assets/js/build/admin.js',
                array( 'wp-element', 'wp-components', 'wp-api-fetch' ),
                QSA_ENGRAVING_VERSION,
                true
            );

            // Localize script with REST API info.
            wp_localize_script(
                'qsa-engraving-admin',
                'qsaEngraving',
                array(
                    'nonce'   => wp_create_nonce( 'qsa_engraving_nonce' ),
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'restUrl' => rest_url( 'qsa-engraving/v1/' ),
                )
            );
        }
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
     * Get the Module Selector Service instance.
     *
     * @return Services\Module_Selector
     */
    public function get_module_selector(): Services\Module_Selector {
        return $this->module_selector;
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
