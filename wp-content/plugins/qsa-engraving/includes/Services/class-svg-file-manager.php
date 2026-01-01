<?php
/**
 * SVG File Manager.
 *
 * Handles SVG file creation, storage, and cleanup for engraving jobs.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Services;

use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SVG File Manager class.
 *
 * Manages SVG file lifecycle:
 * - Creates SVG files in the configured output directory
 * - Generates appropriate file names
 * - Handles file cleanup after batch completion
 * - Provides paths for LightBurn integration
 *
 * @since 1.0.0
 */
class SVG_File_Manager {

	/**
	 * Default output directory relative to WordPress uploads.
	 *
	 * @var string
	 */
	public const DEFAULT_UPLOAD_SUBDIR = 'qsa-engraving/svg';

	/**
	 * File name format: {batch_id}-{qsa_sequence}-{timestamp}.svg
	 *
	 * @var string
	 */
	public const FILENAME_FORMAT = '%d-%d-%d.svg';

	/**
	 * Output directory path (absolute).
	 *
	 * @var string|null
	 */
	private ?string $output_dir = null;

	/**
	 * Output directory URL.
	 *
	 * @var string|null
	 */
	private ?string $output_url = null;

	/**
	 * Whether using custom network path (for LightBurn on separate machine).
	 *
	 * @var bool
	 */
	private bool $using_custom_path = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_output_directory();
	}

	/**
	 * Initialize the output directory.
	 *
	 * Checks for custom path configuration, otherwise uses WordPress uploads.
	 *
	 * @return void
	 */
	private function init_output_directory(): void {
		$settings    = get_option( 'qsa_engraving_settings', array() );
		$custom_path = $settings['svg_output_dir'] ?? '';

		if ( ! empty( $custom_path ) && is_dir( $custom_path ) && is_writable( $custom_path ) ) {
			// Use custom path (likely a network share for LightBurn).
			$this->output_dir        = rtrim( $custom_path, '/\\' );
			$this->using_custom_path = true;
			$this->output_url        = ''; // Not web-accessible.
		} else {
			// Use WordPress uploads directory.
			$upload_dir       = wp_upload_dir();
			$this->output_dir = trailingslashit( $upload_dir['basedir'] ) . self::DEFAULT_UPLOAD_SUBDIR;
			$this->output_url = trailingslashit( $upload_dir['baseurl'] ) . self::DEFAULT_UPLOAD_SUBDIR;
		}
	}

	/**
	 * Ensure the output directory exists.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function ensure_directory(): bool|WP_Error {
		if ( is_dir( $this->output_dir ) ) {
			if ( ! is_writable( $this->output_dir ) ) {
				return new WP_Error(
					'dir_not_writable',
					sprintf(
						/* translators: %s: Directory path */
						__( 'SVG output directory is not writable: %s', 'qsa-engraving' ),
						$this->output_dir
					)
				);
			}
			return true;
		}

		// Create directory with proper permissions.
		$created = wp_mkdir_p( $this->output_dir );
		if ( ! $created ) {
			return new WP_Error(
				'dir_create_failed',
				sprintf(
					/* translators: %s: Directory path */
					__( 'Failed to create SVG output directory: %s', 'qsa-engraving' ),
					$this->output_dir
				)
			);
		}

		// Add index.php for security (prevent directory listing).
		$index_file = $this->output_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, '<?php // Silence is golden.' );
		}

		// Add .htaccess to control access.
		$htaccess_file = $this->output_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# Deny public access to SVG files\n";
			$htaccess_content .= "<FilesMatch \"\.svg$\">\n";
			$htaccess_content .= "    Order deny,allow\n";
			$htaccess_content .= "    Deny from all\n";
			$htaccess_content .= "</FilesMatch>\n";
			file_put_contents( $htaccess_file, $htaccess_content );
		}

		return true;
	}

	/**
	 * Generate filename for an SVG file.
	 *
	 * Format: {batch_id}-{qsa_sequence}-{timestamp}.svg
	 *
	 * @param int $batch_id     The batch ID.
	 * @param int $qsa_sequence The QSA sequence number.
	 * @return string The generated filename.
	 */
	public function generate_filename( int $batch_id, int $qsa_sequence ): string {
		return sprintf(
			self::FILENAME_FORMAT,
			$batch_id,
			$qsa_sequence,
			time()
		);
	}

	/**
	 * Get full path for an SVG file.
	 *
	 * @param string $filename The filename.
	 * @return string The full path.
	 */
	public function get_full_path( string $filename ): string {
		return $this->output_dir . DIRECTORY_SEPARATOR . $filename;
	}

	/**
	 * Get the LightBurn-accessible path for a file.
	 *
	 * This converts the path to Windows format if needed, since LightBurn
	 * typically runs on Windows.
	 *
	 * @param string $filename The filename.
	 * @return string The LightBurn-accessible path.
	 */
	public function get_lightburn_path( string $filename ): string {
		$settings = get_option( 'qsa_engraving_settings', array() );

		// Check if a LightBurn-specific path mapping is configured.
		$lightburn_base_path = $settings['lightburn_path_prefix'] ?? '';

		if ( ! empty( $lightburn_base_path ) ) {
			// Use the configured LightBurn path prefix.
			$path = rtrim( $lightburn_base_path, '/\\' ) . '\\' . $filename;
		} else {
			// Use the local path (convert to Windows format).
			$path = $this->get_full_path( $filename );
		}

		// Convert to Windows-style path.
		return str_replace( '/', '\\', $path );
	}

	/**
	 * Save SVG content to a file.
	 *
	 * @param string $svg_content  The SVG content to save.
	 * @param int    $batch_id     The batch ID.
	 * @param int    $qsa_sequence The QSA sequence number.
	 * @return array{success: bool, filename: string, path: string, lightburn_path: string}|WP_Error
	 */
	public function save_svg( string $svg_content, int $batch_id, int $qsa_sequence ): array|WP_Error {
		// Ensure directory exists.
		$dir_result = $this->ensure_directory();
		if ( is_wp_error( $dir_result ) ) {
			return $dir_result;
		}

		// Generate filename and path.
		$filename = $this->generate_filename( $batch_id, $qsa_sequence );
		$filepath = $this->get_full_path( $filename );

		// Delete any existing file with same batch/qsa prefix (cleanup old files).
		$this->cleanup_old_files( $batch_id, $qsa_sequence );

		// Write the file.
		$bytes_written = file_put_contents( $filepath, $svg_content );
		if ( false === $bytes_written ) {
			return new WP_Error(
				'file_write_failed',
				sprintf(
					/* translators: %s: File path */
					__( 'Failed to write SVG file: %s', 'qsa-engraving' ),
					$filepath
				)
			);
		}

		return array(
			'success'        => true,
			'filename'       => $filename,
			'path'           => $filepath,
			'lightburn_path' => $this->get_lightburn_path( $filename ),
			'size'           => $bytes_written,
		);
	}

	/**
	 * Clean up old SVG files for a batch/QSA combination.
	 *
	 * @param int $batch_id     The batch ID.
	 * @param int $qsa_sequence The QSA sequence number.
	 * @return int Number of files deleted.
	 */
	public function cleanup_old_files( int $batch_id, int $qsa_sequence ): int {
		$pattern = $this->output_dir . DIRECTORY_SEPARATOR . "{$batch_id}-{$qsa_sequence}-*.svg";
		$files   = glob( $pattern );
		$deleted = 0;

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( unlink( $file ) ) {
					$deleted++;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Clean up all SVG files for a batch.
	 *
	 * @param int $batch_id The batch ID.
	 * @return int Number of files deleted.
	 */
	public function cleanup_batch_files( int $batch_id ): int {
		$pattern = $this->output_dir . DIRECTORY_SEPARATOR . "{$batch_id}-*.svg";
		$files   = glob( $pattern );
		$deleted = 0;

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( unlink( $file ) ) {
					$deleted++;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Get existing SVG file for a batch/QSA combination.
	 *
	 * @param int $batch_id     The batch ID.
	 * @param int $qsa_sequence The QSA sequence number.
	 * @return array{exists: bool, filename: string, path: string, lightburn_path: string, modified: int}|null
	 */
	public function get_existing_file( int $batch_id, int $qsa_sequence ): ?array {
		$pattern = $this->output_dir . DIRECTORY_SEPARATOR . "{$batch_id}-{$qsa_sequence}-*.svg";
		$files   = glob( $pattern );

		if ( empty( $files ) ) {
			return null;
		}

		// Get the most recent file.
		usort( $files, function ( $a, $b ) {
			return filemtime( $b ) - filemtime( $a );
		});

		$filepath = $files[0];
		$filename = basename( $filepath );

		return array(
			'exists'         => true,
			'filename'       => $filename,
			'path'           => $filepath,
			'lightburn_path' => $this->get_lightburn_path( $filename ),
			'modified'       => filemtime( $filepath ),
		);
	}

	/**
	 * Check if an SVG file exists for a batch/QSA combination.
	 *
	 * @param int $batch_id     The batch ID.
	 * @param int $qsa_sequence The QSA sequence number.
	 * @return bool
	 */
	public function file_exists( int $batch_id, int $qsa_sequence ): bool {
		return null !== $this->get_existing_file( $batch_id, $qsa_sequence );
	}

	/**
	 * Get the output directory path.
	 *
	 * @return string
	 */
	public function get_output_dir(): string {
		return $this->output_dir ?? '';
	}

	/**
	 * Get the output URL (only available for WordPress uploads directory).
	 *
	 * @return string
	 */
	public function get_output_url(): string {
		return $this->output_url ?? '';
	}

	/**
	 * Check if using custom path configuration.
	 *
	 * @return bool
	 */
	public function is_using_custom_path(): bool {
		return $this->using_custom_path;
	}

	/**
	 * Get directory status information.
	 *
	 * @return array{exists: bool, writable: bool, path: string, custom: bool, file_count: int}
	 */
	public function get_status(): array {
		$exists   = is_dir( $this->output_dir );
		$writable = $exists && is_writable( $this->output_dir );

		$file_count = 0;
		if ( $exists ) {
			$files = glob( $this->output_dir . DIRECTORY_SEPARATOR . '*.svg' );
			$file_count = $files ? count( $files ) : 0;
		}

		return array(
			'exists'     => $exists,
			'writable'   => $writable,
			'path'       => $this->output_dir,
			'custom'     => $this->using_custom_path,
			'file_count' => $file_count,
		);
	}

	/**
	 * Clean up old SVG files based on age.
	 *
	 * @param int $max_age_hours Maximum file age in hours (default: 24).
	 * @return int Number of files deleted.
	 */
	public function cleanup_old_files_by_age( int $max_age_hours = 24 ): int {
		$files   = glob( $this->output_dir . DIRECTORY_SEPARATOR . '*.svg' );
		$deleted = 0;
		$cutoff  = time() - ( $max_age_hours * 3600 );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( filemtime( $file ) < $cutoff ) {
					if ( unlink( $file ) ) {
						$deleted++;
					}
				}
			}
		}

		return $deleted;
	}
}
