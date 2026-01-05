<?php
/**
 * PSR-4 Autoloader for QSA Engraving plugin.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PSR-4 compatible autoloader.
 *
 * Maps the Quadica\QSA_Engraving namespace to the includes/ directory.
 *
 * @since 1.0.0
 */
class Autoloader {

    /**
     * The base namespace for the plugin.
     *
     * @var string
     */
    private const NAMESPACE_PREFIX = 'Quadica\\QSA_Engraving\\';

    /**
     * The base directory for the namespace.
     *
     * @var string
     */
    private static string $base_dir = '';

    /**
     * Register the autoloader.
     *
     * @return void
     */
    public static function register(): void {
        self::$base_dir = QSA_ENGRAVING_PLUGIN_DIR . 'includes/';
        spl_autoload_register( array( self::class, 'autoload' ) );
    }

    /**
     * Autoload callback.
     *
     * @param string $class The fully-qualified class name.
     * @return void
     */
    public static function autoload( string $class ): void {
        // Check if the class uses our namespace prefix.
        $prefix_length = strlen( self::NAMESPACE_PREFIX );
        if ( strncmp( self::NAMESPACE_PREFIX, $class, $prefix_length ) !== 0 ) {
            // Not our namespace, let other autoloaders handle it.
            return;
        }

        // Get the relative class name.
        $relative_class = substr( $class, $prefix_length );

        // Replace namespace separators with directory separators.
        // Replace underscores with hyphens for WordPress-style filenames.
        $file_parts = explode( '\\', $relative_class );
        $class_name = array_pop( $file_parts );

        // Convert class name to filename: Admin_Menu -> class-admin-menu.php
        $file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

        // Build the directory path from namespace parts.
        $directory = '';
        if ( ! empty( $file_parts ) ) {
            $directory = implode( DIRECTORY_SEPARATOR, $file_parts ) . DIRECTORY_SEPARATOR;
        }

        // Build the full file path.
        $file = self::$base_dir . $directory . $file_name;

        // If the file exists, require it.
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
