<?php
/**
 * Micro-ID Landing Page Handler
 *
 * Handles routing for /id URL and displays the Micro-ID decoder
 * landing page with image upload capability.
 *
 * @package QSA_Engraving
 * @since 1.1.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Frontend;

use Quadica\QSA_Engraving\Ajax\MicroID_Decoder_Ajax_Handler;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MicroID_Landing_Handler
 *
 * Registers WordPress rewrite rules to capture /id URL
 * and displays a landing page for Micro-ID decoding.
 */
class MicroID_Landing_Handler {

	/**
	 * Query variable name for Micro-ID lookup.
	 *
	 * @var string
	 */
	public const QUERY_VAR = 'microid_lookup';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_microid_lookup' ) );
	}

	/**
	 * Add rewrite rules for /id URL.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		// Match /id or /id/ at root level.
		add_rewrite_rule(
			'^id/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Query variable name for serial lookup.
	 *
	 * @var string
	 */
	public const SERIAL_QUERY_VAR = 'serial';

	/**
	 * Register the Micro-ID lookup query variable.
	 *
	 * @param array<string> $vars Existing query variables.
	 * @return array<string> Modified query variables.
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Handle Micro-ID lookup requests.
	 *
	 * When /id URL is accessed, display the decoder landing page.
	 * If the decoder is disabled in settings, shows a disabled message instead.
	 *
	 * @return void
	 */
	public function handle_microid_lookup(): void {
		$microid_lookup = get_query_var( self::QUERY_VAR );

		if ( empty( $microid_lookup ) ) {
			return;
		}

		// Check if the decoder is enabled in settings.
		if ( ! $this->is_decoder_enabled() ) {
			$this->render_disabled_page();
			exit;
		}

		// Render the landing page.
		$this->render_landing_page();
		exit;
	}

	/**
	 * Check if the Micro-ID decoder is enabled in settings.
	 *
	 * @return bool True if decoder is enabled.
	 */
	private function is_decoder_enabled(): bool {
		$settings = get_option( 'qsa_engraving_settings', array() );
		return ! empty( $settings['microid_decoder_enabled'] );
	}

	/**
	 * Render a page indicating the decoder is disabled.
	 *
	 * @return void
	 */
	private function render_disabled_page(): void {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Micro-ID Decoder', 'qsa-engraving' ); ?> - <?php echo esc_html( $site_name ); ?></title>
	<meta name="robots" content="noindex, nofollow">
	<style>
		* { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background: #f0f0f1;
			color: #1d2327;
			line-height: 1.6;
			min-height: 100vh;
			display: flex;
			flex-direction: column;
		}
		.header {
			background: #fff;
			border-bottom: 1px solid #c3c4c7;
			padding: 16px 20px;
			text-align: center;
		}
		.header a { color: #0073aa; text-decoration: none; font-size: 20px; font-weight: 600; }
		.main {
			flex: 1;
			display: flex;
			align-items: flex-start;
			justify-content: center;
			padding: 40px 20px;
		}
		.card {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
			padding: 32px;
			max-width: 480px;
			width: 100%;
			text-align: center;
			box-shadow: 0 2px 4px rgba(0,0,0,0.05);
		}
		.icon { font-size: 48px; margin-bottom: 16px; }
		h1 { font-size: 24px; font-weight: 600; margin-bottom: 12px; }
		.message { color: #646970; margin-bottom: 20px; }
		.btn {
			display: inline-block;
			padding: 10px 20px;
			background: #0073aa;
			color: #fff;
			text-decoration: none;
			border-radius: 4px;
			font-weight: 500;
		}
		.btn:hover { background: #005a87; }
		.footer {
			background: #fff;
			border-top: 1px solid #c3c4c7;
			padding: 16px 20px;
			text-align: center;
			font-size: 13px;
			color: #646970;
		}
		.footer a { color: #0073aa; text-decoration: none; }
	</style>
</head>
<body>
	<header class="header">
		<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
	</header>
	<main class="main">
		<div class="card">
			<div class="icon">🔒</div>
			<h1><?php esc_html_e( 'Decoder Unavailable', 'qsa-engraving' ); ?></h1>
			<p class="message">
				<?php esc_html_e( 'The Micro-ID decoder is currently not available. Please contact us if you need assistance identifying your LED module.', 'qsa-engraving' ); ?>
			</p>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn">
				<?php esc_html_e( 'Contact Us', 'qsa-engraving' ); ?>
			</a>
		</div>
	</main>
	<footer class="footer">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></p>
	</footer>
</body>
</html>
		<?php
	}

	/**
	 * Check if the current user is staff (has manage_woocommerce capability).
	 *
	 * @return bool
	 */
	private function is_staff_user(): bool {
		return current_user_can( MicroID_Decoder_Ajax_Handler::STAFF_CAPABILITY );
	}

	/**
	 * Get the login URL that redirects back to /id page.
	 *
	 * @return string
	 */
	private function get_staff_login_url(): string {
		return wp_login_url( home_url( '/id/' ) );
	}

	/**
	 * Validate a serial number parameter.
	 *
	 * Serial must be 8 numeric digits in valid range (1 to 1048575).
	 *
	 * @param string $serial The serial to validate.
	 * @return string|null Valid serial or null if invalid.
	 */
	private function validate_serial_param( string $serial ): ?string {
		// Must be exactly 8 digits.
		if ( ! preg_match( '/^[0-9]{8}$/', $serial ) ) {
			return null;
		}

		// Convert to integer and validate range.
		$serial_int = (int) $serial;

		// Must be in range 1-1048575 (20-bit Micro-ID capacity).
		if ( $serial_int < 1 || $serial_int > 1048575 ) {
			return null;
		}

		return $serial;
	}

	/**
	 * Render the Micro-ID decoder landing page.
	 *
	 * @return void
	 */
	private function render_landing_page(): void {
		// Get site info for branding.
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		$ajax_url  = admin_url( 'admin-ajax.php' );
		$nonce     = MicroID_Decoder_Ajax_Handler::create_nonce();
		$is_staff  = $this->is_staff_user();

		// Check for serial parameter (from manual decoder redirect).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$initial_serial = isset( $_GET[ self::SERIAL_QUERY_VAR ] )
			? $this->validate_serial_param( sanitize_text_field( wp_unslash( $_GET[ self::SERIAL_QUERY_VAR ] ) ) )
			: null;

		// Get constraints for display.
		$max_size_mb    = MicroID_Decoder_Ajax_Handler::MAX_IMAGE_SIZE / ( 1024 * 1024 );
		$min_dimension  = MicroID_Decoder_Ajax_Handler::MIN_IMAGE_DIMENSION;
		$allowed_types  = 'JPEG, PNG, WebP';

		// Start output.
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Micro-ID Decoder', 'qsa-engraving' ); ?> - <?php echo esc_html( $site_name ); ?></title>
	<meta name="robots" content="noindex, nofollow">
	<style>
		:root {
			--primary-color: #0073aa;
			--primary-hover: #005a87;
			--success-color: #00a32a;
			--success-bg: #edfaef;
			--error-color: #d63638;
			--error-bg: #fcf0f1;
			--warning-color: #dba617;
			--warning-bg: #fcf9e8;
			--text-color: #1d2327;
			--text-muted: #646970;
			--bg-color: #f0f0f1;
			--card-bg: #ffffff;
			--border-color: #c3c4c7;
			--drop-zone-border: #8c8f94;
		}

		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background: var(--bg-color);
			color: var(--text-color);
			line-height: 1.6;
			min-height: 100vh;
			display: flex;
			flex-direction: column;
		}

		.microid-header {
			background: var(--card-bg);
			border-bottom: 1px solid var(--border-color);
			padding: 16px 20px;
			text-align: center;
		}

		.microid-header a {
			color: var(--primary-color);
			text-decoration: none;
			font-size: 20px;
			font-weight: 600;
		}

		.microid-main {
			flex: 1;
			display: flex;
			align-items: flex-start;
			justify-content: center;
			padding: 40px 20px;
		}

		.microid-card {
			background: var(--card-bg);
			border: 1px solid var(--border-color);
			border-radius: 8px;
			padding: 32px;
			max-width: 480px;
			width: 100%;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
		}

		.microid-card h1 {
			font-size: 24px;
			font-weight: 600;
			margin-bottom: 8px;
			text-align: center;
		}

		.microid-card .subtitle {
			color: var(--text-muted);
			text-align: center;
			margin-bottom: 24px;
			font-size: 14px;
		}

		/* Drop Zone Styles */
		.drop-zone {
			border: 2px dashed var(--drop-zone-border);
			border-radius: 8px;
			padding: 40px 20px;
			text-align: center;
			cursor: pointer;
			transition: all 0.2s ease;
			background: var(--bg-color);
		}

		.drop-zone:hover,
		.drop-zone.dragover {
			border-color: var(--primary-color);
			background: #f0f6fc;
		}

		.drop-zone.disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}

		.drop-zone-icon {
			font-size: 48px;
			margin-bottom: 16px;
			color: var(--text-muted);
		}

		.drop-zone-text {
			font-size: 16px;
			font-weight: 500;
			margin-bottom: 8px;
		}

		.drop-zone-hint {
			font-size: 13px;
			color: var(--text-muted);
		}

		.file-input {
			display: none;
		}

		/* Loading State */
		.loading-overlay {
			display: none;
			padding: 40px 20px;
			text-align: center;
		}

		.loading-overlay.active {
			display: block;
		}

		.spinner {
			width: 48px;
			height: 48px;
			border: 4px solid var(--border-color);
			border-top-color: var(--primary-color);
			border-radius: 50%;
			animation: spin 1s linear infinite;
			margin: 0 auto 16px;
		}

		@keyframes spin {
			to { transform: rotate(360deg); }
		}

		.loading-text {
			font-size: 16px;
			color: var(--text-muted);
		}

		/* Result Display */
		.result-container {
			display: none;
		}

		.result-container.active {
			display: block;
		}

		.result-header {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 20px;
			padding-bottom: 16px;
			border-bottom: 1px solid var(--border-color);
		}

		.result-icon {
			width: 40px;
			height: 40px;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 20px;
		}

		.result-icon.success {
			background: var(--success-bg);
			color: var(--success-color);
		}

		.result-icon.error {
			background: var(--error-bg);
			color: var(--error-color);
		}

		.result-title {
			font-size: 18px;
			font-weight: 600;
		}

		.result-subtitle {
			font-size: 13px;
			color: var(--text-muted);
		}

		/* Serial Badge */
		.serial-badge {
			display: inline-block;
			font-family: 'SF Mono', 'Consolas', 'Monaco', monospace;
			font-size: 24px;
			font-weight: 700;
			padding: 12px 20px;
			background: linear-gradient(135deg, var(--success-color) 0%, #00875a 100%);
			color: #fff;
			border-radius: 8px;
			letter-spacing: 2px;
			margin-bottom: 20px;
			text-align: center;
			width: 100%;
		}

		/* Info Grid */
		.info-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 12px;
			margin-bottom: 20px;
		}

		.info-item {
			padding: 12px;
			background: var(--bg-color);
			border-radius: 6px;
		}

		.info-item.full-width {
			grid-column: 1 / -1;
		}

		.info-label {
			font-size: 11px;
			color: var(--text-muted);
			text-transform: uppercase;
			letter-spacing: 0.5px;
			margin-bottom: 4px;
		}

		.info-value {
			font-size: 15px;
			font-weight: 600;
			color: var(--text-color);
			word-break: break-word;
		}

		.info-value.not-found {
			color: var(--text-muted);
			font-style: italic;
			font-weight: 400;
		}

		/* Staff Details Section */
		.staff-details {
			margin-top: 16px;
			padding-top: 16px;
			border-top: 1px solid var(--border-color);
		}

		.staff-details-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 12px;
		}

		.staff-badge {
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
			color: var(--primary-color);
			background: #f0f6fc;
			padding: 4px 8px;
			border-radius: 4px;
		}

		/* Error Display */
		.error-container {
			display: none;
			text-align: center;
			padding: 20px;
		}

		.error-container.active {
			display: block;
		}

		.error-icon {
			font-size: 48px;
			color: var(--error-color);
			margin-bottom: 16px;
		}

		.error-message {
			font-size: 16px;
			color: var(--text-color);
			margin-bottom: 8px;
		}

		.error-detail {
			font-size: 13px;
			color: var(--text-muted);
			margin-bottom: 20px;
		}

		/* Buttons */
		.btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			padding: 10px 20px;
			font-size: 14px;
			font-weight: 500;
			border-radius: 4px;
			border: none;
			cursor: pointer;
			text-decoration: none;
			transition: all 0.2s ease;
		}

		.btn-primary {
			background: var(--primary-color);
			color: #fff;
		}

		.btn-primary:hover {
			background: var(--primary-hover);
		}

		.btn-secondary {
			background: var(--card-bg);
			color: var(--text-color);
			border: 1px solid var(--border-color);
		}

		.btn-secondary:hover {
			background: var(--bg-color);
		}

		.btn-link {
			background: none;
			border: none;
			color: var(--primary-color);
			padding: 0;
			font-size: 14px;
			cursor: pointer;
			text-decoration: underline;
		}

		.btn-link:hover {
			color: var(--primary-hover);
		}

		.action-buttons {
			display: flex;
			gap: 12px;
			justify-content: center;
			margin-top: 20px;
		}

		/* Staff Login Notice */
		.staff-login-notice {
			background: var(--warning-bg);
			border: 1px solid var(--warning-color);
			border-radius: 6px;
			padding: 12px;
			margin-top: 16px;
			text-align: center;
			font-size: 13px;
		}

		.staff-login-notice a {
			color: var(--primary-color);
			font-weight: 500;
		}

		/* Footer */
		.microid-footer {
			background: var(--card-bg);
			border-top: 1px solid var(--border-color);
			padding: 16px 20px;
			text-align: center;
			font-size: 13px;
			color: var(--text-muted);
		}

		.microid-footer a {
			color: var(--primary-color);
			text-decoration: none;
		}

		/* Responsive */
		@media (max-width: 480px) {
			.microid-main {
				padding: 20px 16px;
			}

			.microid-card {
				padding: 24px 20px;
			}

			.drop-zone {
				padding: 30px 16px;
			}

			.info-grid {
				grid-template-columns: 1fr;
			}

			.serial-badge {
				font-size: 20px;
				padding: 10px 16px;
			}

			.action-buttons {
				flex-direction: column;
			}

			.btn {
				width: 100%;
			}
		}
	</style>
</head>
<body>
	<header class="microid-header">
		<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
	</header>

	<main class="microid-main">
		<div class="microid-card">
			<h1><?php esc_html_e( 'Micro-ID Decoder', 'qsa-engraving' ); ?></h1>
			<p class="subtitle"><?php esc_html_e( 'Upload a photo of your LED module to retrieve product information', 'qsa-engraving' ); ?></p>

			<!-- Noscript Fallback for JavaScript-disabled browsers -->
			<noscript>
				<div class="noscript-notice">
					<div class="noscript-icon">⚠️</div>
					<h2><?php esc_html_e( 'JavaScript Required', 'qsa-engraving' ); ?></h2>
					<p><?php esc_html_e( 'This Micro-ID decoder requires JavaScript to process images.', 'qsa-engraving' ); ?></p>
					<p><?php esc_html_e( 'Please enable JavaScript in your browser settings, or contact our support team for assistance with decoding your LED module serial number.', 'qsa-engraving' ); ?></p>
					<p style="margin-top: 16px;">
						<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn-primary">
							<?php esc_html_e( 'Contact Support', 'qsa-engraving' ); ?>
						</a>
					</p>
				</div>
				<style>
					.noscript-notice {
						text-align: center;
						padding: 24px;
						background: #fcf9e8;
						border: 1px solid #dba617;
						border-radius: 8px;
					}
					.noscript-icon { font-size: 48px; margin-bottom: 16px; }
					.noscript-notice h2 { font-size: 18px; margin-bottom: 12px; }
					.noscript-notice p { color: #646970; margin-bottom: 8px; }
					/* Hide JS-dependent sections when JS is disabled */
					#upload-section, #loading-section, #result-section, #error-section { display: none !important; }
				</style>
			</noscript>

			<!-- Upload Section -->
			<div id="upload-section">
				<div class="drop-zone" id="drop-zone">
					<div class="drop-zone-icon">📷</div>
					<div class="drop-zone-text"><?php esc_html_e( 'Drop image here or tap to upload', 'qsa-engraving' ); ?></div>
					<div class="drop-zone-hint">
						<?php
						printf(
							/* translators: 1: Allowed file types, 2: Maximum file size in MB */
							esc_html__( '%1$s • Max %2$sMB', 'qsa-engraving' ),
							esc_html( $allowed_types ),
							esc_html( (string) $max_size_mb )
						);
						?>
					</div>
				</div>
				<input type="file"
				       id="file-input"
				       class="file-input"
				       accept="image/jpeg,image/png,image/webp"
				       capture="environment">
			</div>

			<!-- Loading State -->
			<div class="loading-overlay" id="loading-section">
				<div class="spinner"></div>
				<div class="loading-text"><?php esc_html_e( 'Analyzing image...', 'qsa-engraving' ); ?></div>
			</div>

			<!-- Success Result -->
			<div class="result-container" id="result-section">
				<div class="result-header">
					<div class="result-icon success">✓</div>
					<div>
						<div class="result-title"><?php esc_html_e( 'Serial Decoded', 'qsa-engraving' ); ?></div>
						<div class="result-subtitle" id="result-subtitle"></div>
					</div>
				</div>

				<div class="serial-badge" id="serial-badge"></div>

				<div class="info-grid" id="basic-info">
					<div class="info-item">
						<div class="info-label"><?php esc_html_e( 'Product SKU', 'qsa-engraving' ); ?></div>
						<div class="info-value" id="info-sku"></div>
					</div>
					<div class="info-item">
						<div class="info-label"><?php esc_html_e( 'Engraved', 'qsa-engraving' ); ?></div>
						<div class="info-value" id="info-date"></div>
					</div>
					<div class="info-item full-width">
						<div class="info-label"><?php esc_html_e( 'Product', 'qsa-engraving' ); ?></div>
						<div class="info-value" id="info-product"></div>
					</div>
				</div>

				<!-- Staff Details (hidden for non-staff) -->
				<div class="staff-details" id="staff-details" style="display: none;">
					<div class="staff-details-header">
						<span class="staff-badge"><?php esc_html_e( 'Staff Details', 'qsa-engraving' ); ?></span>
					</div>
					<div class="info-grid" id="staff-info">
						<div class="info-item">
							<div class="info-label"><?php esc_html_e( 'Order ID', 'qsa-engraving' ); ?></div>
							<div class="info-value" id="info-order"></div>
						</div>
						<div class="info-item">
							<div class="info-label"><?php esc_html_e( 'Customer', 'qsa-engraving' ); ?></div>
							<div class="info-value" id="info-customer"></div>
						</div>
						<div class="info-item">
							<div class="info-label"><?php esc_html_e( 'Batch ID', 'qsa-engraving' ); ?></div>
							<div class="info-value" id="info-batch"></div>
						</div>
						<div class="info-item">
							<div class="info-label"><?php esc_html_e( 'Array Position', 'qsa-engraving' ); ?></div>
							<div class="info-value" id="info-position"></div>
						</div>
					</div>
				</div>

				<?php if ( ! $is_staff ) : ?>
				<div class="staff-login-notice" id="staff-login-notice">
					<?php
					printf(
						/* translators: %s: Login URL */
						wp_kses(
							__( 'Staff? <a href="%s">Log in</a> to view full traceability details.', 'qsa-engraving' ),
							array( 'a' => array( 'href' => array() ) )
						),
						esc_url( $this->get_staff_login_url() )
					);
					?>
				</div>
				<?php endif; ?>

				<div class="action-buttons">
					<button type="button" class="btn btn-primary" id="decode-another">
						<?php esc_html_e( 'Decode Another', 'qsa-engraving' ); ?>
					</button>
				</div>
			</div>

			<!-- Error State -->
			<div class="error-container" id="error-section">
				<div class="error-icon">⚠️</div>
				<div class="error-message" id="error-message"></div>
				<div class="error-detail" id="error-detail"></div>
				<div class="action-buttons">
					<button type="button" class="btn btn-primary" id="try-again">
						<?php esc_html_e( 'Try Again', 'qsa-engraving' ); ?>
					</button>
				</div>
			</div>
		</div>
	</main>

	<footer class="microid-footer">
		<p>
			&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
			<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
		</p>
	</footer>

	<script>
	(function() {
		'use strict';

		// Configuration
		const config = {
			ajaxUrl: <?php echo wp_json_encode( $ajax_url ); ?>,
			nonce: <?php echo wp_json_encode( $nonce ); ?>,
			isStaff: <?php echo $is_staff ? 'true' : 'false'; ?>,
			maxSize: <?php echo (int) MicroID_Decoder_Ajax_Handler::MAX_IMAGE_SIZE; ?>,
			minDimension: <?php echo (int) $min_dimension; ?>,
			initialSerial: <?php echo wp_json_encode( $initial_serial ); ?>,
			strings: {
				decoding: <?php echo wp_json_encode( __( 'Analyzing image...', 'qsa-engraving' ) ); ?>,
				loadingDetails: <?php echo wp_json_encode( __( 'Loading details...', 'qsa-engraving' ) ); ?>,
				lookingUp: <?php echo wp_json_encode( __( 'Looking up serial...', 'qsa-engraving' ) ); ?>,
				fileTooLarge: <?php echo wp_json_encode( __( 'File is too large. Maximum size is 10MB.', 'qsa-engraving' ) ); ?>,
				invalidType: <?php echo wp_json_encode( __( 'Invalid file type. Please upload a JPEG, PNG, or WebP image.', 'qsa-engraving' ) ); ?>,
				imageTooSmall: <?php echo wp_json_encode( sprintf( __( 'Image is too small. Minimum dimension is %dpx.', 'qsa-engraving' ), $min_dimension ) ); ?>,
				uploadError: <?php echo wp_json_encode( __( 'Failed to upload image. Please try again.', 'qsa-engraving' ) ); ?>,
				networkError: <?php echo wp_json_encode( __( 'Network error. Please check your connection and try again.', 'qsa-engraving' ) ); ?>,
				cached: <?php echo wp_json_encode( __( 'Cached result', 'qsa-engraving' ) ); ?>,
				fresh: <?php echo wp_json_encode( __( 'Fresh decode', 'qsa-engraving' ) ); ?>,
				manualDecode: <?php echo wp_json_encode( __( 'Manual decode', 'qsa-engraving' ) ); ?>,
				notInSystem: <?php echo wp_json_encode( __( 'Not in system', 'qsa-engraving' ) ); ?>,
				serialNotFound: <?php echo wp_json_encode( __( 'Serial number not found in system.', 'qsa-engraving' ) ); ?>,
				invalidSerial: <?php echo wp_json_encode( __( 'Invalid serial number format.', 'qsa-engraving' ) ); ?>
			}
		};

		// DOM Elements
		const dropZone = document.getElementById('drop-zone');
		const fileInput = document.getElementById('file-input');
		const uploadSection = document.getElementById('upload-section');
		const loadingSection = document.getElementById('loading-section');
		const resultSection = document.getElementById('result-section');
		const errorSection = document.getElementById('error-section');
		const loadingText = loadingSection.querySelector('.loading-text');

		// State
		let currentSerial = null;

		/**
		 * Show a specific section, hide others.
		 */
		function showSection(section) {
			uploadSection.style.display = section === 'upload' ? 'block' : 'none';
			loadingSection.classList.toggle('active', section === 'loading');
			resultSection.classList.toggle('active', section === 'result');
			errorSection.classList.toggle('active', section === 'error');
		}

		/**
		 * Validate file before upload.
		 */
		function validateFile(file) {
			// Check file type
			const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
			if (!allowedTypes.includes(file.type)) {
				return { valid: false, error: config.strings.invalidType };
			}

			// Check file size
			if (file.size > config.maxSize) {
				return { valid: false, error: config.strings.fileTooLarge };
			}

			return { valid: true };
		}

		/**
		 * Handle file selection.
		 */
		function handleFile(file) {
			const validation = validateFile(file);
			if (!validation.valid) {
				showError(validation.error, '');
				return;
			}

			// Check image dimensions before upload to save bandwidth.
			checkImageDimensions(file).then(dimensionResult => {
				if (!dimensionResult.valid) {
					showError(dimensionResult.error, '');
					return;
				}
				uploadFile(file);
			}).catch(() => {
				// If dimension check fails, proceed with upload and let server validate.
				uploadFile(file);
			});
		}

		/**
		 * Check image dimensions using the Image API.
		 * Returns a promise that resolves with validation result.
		 */
		function checkImageDimensions(file) {
			return new Promise((resolve, reject) => {
				const img = new Image();
				const objectUrl = URL.createObjectURL(file);

				img.onload = function() {
					URL.revokeObjectURL(objectUrl);
					const minDim = Math.min(img.width, img.height);
					if (minDim < config.minDimension) {
						resolve({ valid: false, error: config.strings.imageTooSmall });
					} else {
						resolve({ valid: true });
					}
				};

				img.onerror = function() {
					URL.revokeObjectURL(objectUrl);
					reject(new Error('Failed to load image'));
				};

				img.src = objectUrl;
			});
		}

		/**
		 * Upload file and decode.
		 */
		async function uploadFile(file) {
			showSection('loading');
			loadingText.textContent = config.strings.decoding;

			const formData = new FormData();
			formData.append('action', 'qsa_microid_decode');
			formData.append('nonce', config.nonce);
			formData.append('image', file);

			try {
				const response = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const data = await response.json();

				if (data.success) {
					currentSerial = data.data.serial;
					showResult(data.data);

					// If staff, automatically fetch full details
					if (config.isStaff && currentSerial) {
						fetchFullDetails(currentSerial);
					}
				} else {
					showError(data.message || config.strings.uploadError, data.code || '');
				}
			} catch (error) {
				console.error('Decode error:', error);
				showError(config.strings.networkError, '');
			}
		}

		/**
		 * Fetch full details for staff users.
		 */
		async function fetchFullDetails(serial) {
			const formData = new FormData();
			formData.append('action', 'qsa_microid_full_details');
			formData.append('nonce', config.nonce);
			formData.append('serial', serial);

			try {
				const response = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const data = await response.json();

				if (data.success) {
					showStaffDetails(data.data);
				}
			} catch (error) {
				console.error('Full details error:', error);
				// Non-critical, don't show error
			}
		}

		/**
		 * Look up a serial number directly (without image upload).
		 * Used when visiting /id?serial=XXXXXXXX from the manual decoder.
		 */
		async function lookupSerial(serial) {
			showSection('loading');
			loadingText.textContent = config.strings.lookingUp;

			const formData = new FormData();
			formData.append('action', 'qsa_microid_serial_lookup');
			formData.append('nonce', config.nonce);
			formData.append('serial', serial);

			try {
				const response = await fetch(config.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const data = await response.json();

				if (data.success) {
					currentSerial = data.data.serial;
					showResult(data.data);

					// If staff, automatically fetch full details
					if (config.isStaff && currentSerial) {
						fetchFullDetails(currentSerial);
					}
				} else {
					showError(data.message || config.strings.serialNotFound, data.code || '');
				}
			} catch (error) {
				console.error('Serial lookup error:', error);
				showError(config.strings.networkError, '');
			}
		}

		/**
		 * Display decode result.
		 */
		function showResult(data) {
			showSection('result');

			// Update serial badge
			document.getElementById('serial-badge').textContent = data.serial;

			// Update subtitle - handle manual decode source
			let subtitle;
			if (data.source === 'manual') {
				subtitle = config.strings.manualDecode;
			} else if (data.cached) {
				subtitle = config.strings.cached;
			} else {
				subtitle = config.strings.fresh;
			}
			document.getElementById('result-subtitle').textContent = subtitle;

			// Update basic info
			const product = data.product || {};
			document.getElementById('info-sku').textContent = product.sku || config.strings.notInSystem;
			document.getElementById('info-sku').classList.toggle('not-found', !product.sku);

			document.getElementById('info-date').textContent = product.engraved_at || config.strings.notInSystem;
			document.getElementById('info-date').classList.toggle('not-found', !product.engraved_at);

			document.getElementById('info-product').textContent = product.product_name || config.strings.notInSystem;
			document.getElementById('info-product').classList.toggle('not-found', !product.product_name);
		}

		/**
		 * Display staff details.
		 */
		function showStaffDetails(data) {
			const staffDetails = document.getElementById('staff-details');
			staffDetails.style.display = 'block';

			// Hide the login notice if present
			const loginNotice = document.getElementById('staff-login-notice');
			if (loginNotice) {
				loginNotice.style.display = 'none';
			}

			// Order ID with link
			const orderEl = document.getElementById('info-order');
			if (data.order_id && data.order_url) {
				orderEl.innerHTML = '<a href="' + escapeHtml(data.order_url) + '" target="_blank" rel="noopener noreferrer">#' + escapeHtml(data.order_id) + '</a>';
			} else if (data.order_id) {
				orderEl.textContent = '#' + data.order_id;
			} else {
				orderEl.textContent = config.strings.notInSystem;
				orderEl.classList.add('not-found');
			}

			// Customer
			const customerEl = document.getElementById('info-customer');
			customerEl.textContent = data.customer_name || config.strings.notInSystem;
			customerEl.classList.toggle('not-found', !data.customer_name);

			// Batch ID
			const batchEl = document.getElementById('info-batch');
			batchEl.textContent = data.engraving_batch_id || config.strings.notInSystem;
			batchEl.classList.toggle('not-found', !data.engraving_batch_id);

			// Array Position
			const positionEl = document.getElementById('info-position');
			positionEl.textContent = data.array_position || config.strings.notInSystem;
			positionEl.classList.toggle('not-found', !data.array_position);
		}

		/**
		 * Show error message.
		 */
		function showError(message, detail) {
			showSection('error');
			document.getElementById('error-message').textContent = message;
			document.getElementById('error-detail').textContent = detail;
		}

		/**
		 * Reset to upload state.
		 */
		function reset() {
			currentSerial = null;
			fileInput.value = '';
			showSection('upload');

			// Reset staff details visibility
			const staffDetails = document.getElementById('staff-details');
			if (staffDetails) {
				staffDetails.style.display = 'none';
			}

			// Show login notice again if not staff
			const loginNotice = document.getElementById('staff-login-notice');
			if (loginNotice) {
				loginNotice.style.display = 'block';
			}
		}

		/**
		 * Escape HTML for safe insertion.
		 */
		function escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}

		// Event Listeners

		// Click on drop zone opens file picker
		dropZone.addEventListener('click', function() {
			fileInput.click();
		});

		// File input change
		fileInput.addEventListener('change', function(e) {
			if (e.target.files && e.target.files[0]) {
				handleFile(e.target.files[0]);
			}
		});

		// Drag and drop
		dropZone.addEventListener('dragover', function(e) {
			e.preventDefault();
			dropZone.classList.add('dragover');
		});

		dropZone.addEventListener('dragleave', function(e) {
			e.preventDefault();
			dropZone.classList.remove('dragover');
		});

		dropZone.addEventListener('drop', function(e) {
			e.preventDefault();
			dropZone.classList.remove('dragover');

			if (e.dataTransfer.files && e.dataTransfer.files[0]) {
				handleFile(e.dataTransfer.files[0]);
			}
		});

		// Decode Another button
		document.getElementById('decode-another').addEventListener('click', function() {
			reset();
			// Clear the serial param from URL without page reload
			if (window.history.replaceState) {
				const url = new URL(window.location.href);
				url.searchParams.delete('serial');
				window.history.replaceState({}, '', url.toString());
			}
		});

		// Try Again button
		document.getElementById('try-again').addEventListener('click', function() {
			reset();
			// Clear the serial param from URL without page reload
			if (window.history.replaceState) {
				const url = new URL(window.location.href);
				url.searchParams.delete('serial');
				window.history.replaceState({}, '', url.toString());
			}
		});

		// Initialize
		if (config.initialSerial) {
			// Auto-lookup serial from URL parameter (from manual decoder redirect)
			lookupSerial(config.initialSerial);
		} else {
			showSection('upload');
		}
	})();
	</script>
</body>
</html>
		<?php
	}
}
