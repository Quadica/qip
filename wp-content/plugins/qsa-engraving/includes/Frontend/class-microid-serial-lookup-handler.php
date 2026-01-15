<?php
/**
 * Micro-ID Serial Lookup Handler
 *
 * Handles routing for /id URL and displays serial number lookup results.
 * This is a simplified page that accepts ?serial= parameter and shows
 * product information for the matching serial number.
 *
 * @package QSA_Engraving
 * @since 1.2.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Frontend;

use Quadica\QSA_Engraving\Database\Serial_Repository;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MicroID_Serial_Lookup_Handler
 *
 * Registers WordPress rewrite rules to capture /id URL
 * and displays product information for Micro-ID serial numbers.
 */
class MicroID_Serial_Lookup_Handler {

	/**
	 * Query variable name for serial lookup.
	 *
	 * @var string
	 */
	public const QUERY_VAR = 'microid_lookup';

	/**
	 * Serial Repository instance.
	 *
	 * @var Serial_Repository
	 */
	private Serial_Repository $serial_repository;

	/**
	 * Constructor.
	 *
	 * @param Serial_Repository $serial_repository Serial repository instance.
	 */
	public function __construct( Serial_Repository $serial_repository ) {
		$this->serial_repository = $serial_repository;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_lookup_request' ) );
	}

	/**
	 * Add rewrite rules for /id URL.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^id/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Register the query variable.
	 *
	 * @param array<string> $vars Existing query variables.
	 * @return array<string> Modified query variables.
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Handle serial lookup requests.
	 *
	 * @return void
	 */
	public function handle_lookup_request(): void {
		$lookup = get_query_var( self::QUERY_VAR );

		if ( empty( $lookup ) ) {
			return;
		}

		// Check if the decoder is enabled in settings.
		if ( ! $this->is_decoder_enabled() ) {
			$this->render_disabled_page();
			exit;
		}

		// Render the lookup page.
		$this->render_lookup_page();
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
	<title><?php esc_html_e( 'Micro-ID Lookup', 'qsa-engraving' ); ?> - <?php echo esc_html( $site_name ); ?></title>
	<meta name="robots" content="noindex, nofollow">
	<style>
		* { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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
		}
		h1 { font-size: 24px; font-weight: 600; margin-bottom: 12px; }
		.message { color: #646970; margin-bottom: 20px; }
		.btn {
			display: inline-block;
			padding: 10px 20px;
			background: #0073aa;
			color: #fff;
			text-decoration: none;
			border-radius: 4px;
		}
		.footer {
			background: #fff;
			border-top: 1px solid #c3c4c7;
			padding: 16px 20px;
			text-align: center;
			font-size: 13px;
			color: #646970;
		}
	</style>
</head>
<body>
	<header class="header">
		<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
	</header>
	<main class="main">
		<div class="card">
			<h1><?php esc_html_e( 'Lookup Unavailable', 'qsa-engraving' ); ?></h1>
			<p class="message">
				<?php esc_html_e( 'The Micro-ID lookup is currently not available. Please contact us if you need assistance identifying your LED module.', 'qsa-engraving' ); ?>
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
	 * Validate a serial number parameter.
	 *
	 * Serial must be exactly 8 numeric digits in valid range (1 to 1048575).
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
	 * Get basic product info for a serial number.
	 *
	 * @param string $serial The serial number.
	 * @return array{found: bool, sku: ?string, product_name: ?string, engraved_at: ?string}
	 */
	private function get_serial_info( string $serial ): array {
		$result = array(
			'found'        => false,
			'sku'          => null,
			'product_name' => null,
			'engraved_at'  => null,
		);

		$serial_record = $this->serial_repository->get_by_serial_number( $serial );

		if ( ! $serial_record ) {
			return $result;
		}

		$result['found'] = true;
		$result['sku']   = $serial_record['module_sku'] ?? null;

		// Get product name from SKU.
		if ( ! empty( $result['sku'] ) && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$product_id = wc_get_product_id_by_sku( $result['sku'] );
			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$result['product_name'] = $product->get_name();
				}
			}
		}

		// Format engraved date.
		if ( ! empty( $serial_record['engraved_at'] ) ) {
			$result['engraved_at'] = wp_date( get_option( 'date_format' ), strtotime( $serial_record['engraved_at'] ) );
		}

		return $result;
	}

	/**
	 * Render the serial lookup page.
	 *
	 * @return void
	 */
	private function render_lookup_page(): void {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );

		// Check for serial parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$serial = isset( $_GET['serial'] )
			? $this->validate_serial_param( sanitize_text_field( wp_unslash( $_GET['serial'] ) ) )
			: null;

		// If serial provided, look it up.
		$serial_info = null;
		if ( $serial ) {
			$serial_info = $this->get_serial_info( $serial );
		}
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Micro-ID Lookup', 'qsa-engraving' ); ?> - <?php echo esc_html( $site_name ); ?></title>
	<meta name="robots" content="noindex, nofollow">
	<style>
		:root {
			--primary: #0073aa;
			--primary-hover: #005a87;
			--success: #00a32a;
			--success-bg: #edfaef;
			--error: #d63638;
			--text: #1d2327;
			--muted: #646970;
			--bg: #f0f0f1;
			--card: #fff;
			--border: #c3c4c7;
		}
		* { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			background: var(--bg);
			color: var(--text);
			line-height: 1.6;
			min-height: 100vh;
			display: flex;
			flex-direction: column;
		}
		.header {
			background: var(--card);
			border-bottom: 1px solid var(--border);
			padding: 16px 20px;
			text-align: center;
		}
		.header a { color: var(--primary); text-decoration: none; font-size: 20px; font-weight: 600; }
		.main {
			flex: 1;
			display: flex;
			align-items: flex-start;
			justify-content: center;
			padding: 40px 20px;
		}
		.card {
			background: var(--card);
			border: 1px solid var(--border);
			border-radius: 8px;
			padding: 32px;
			max-width: 480px;
			width: 100%;
		}
		h1 {
			font-size: 24px;
			font-weight: 600;
			margin-bottom: 8px;
			text-align: center;
		}
		.subtitle {
			color: var(--muted);
			text-align: center;
			margin-bottom: 24px;
			font-size: 14px;
		}
		.serial-badge {
			display: block;
			font-family: 'SF Mono', Consolas, monospace;
			font-size: 28px;
			font-weight: 700;
			padding: 16px;
			background: linear-gradient(135deg, var(--success) 0%, #00875a 100%);
			color: #fff;
			border-radius: 8px;
			letter-spacing: 3px;
			margin-bottom: 24px;
			text-align: center;
		}
		.info-grid {
			display: grid;
			gap: 12px;
			margin-bottom: 24px;
		}
		.info-item {
			padding: 12px;
			background: var(--bg);
			border-radius: 6px;
		}
		.info-label {
			font-size: 11px;
			color: var(--muted);
			text-transform: uppercase;
			letter-spacing: 0.5px;
			margin-bottom: 4px;
		}
		.info-value {
			font-size: 15px;
			font-weight: 600;
		}
		.info-value.not-found {
			color: var(--muted);
			font-style: italic;
			font-weight: 400;
		}
		.not-found-message {
			text-align: center;
			padding: 24px;
			background: #fcf0f1;
			border: 1px solid var(--error);
			border-radius: 8px;
			margin-bottom: 24px;
		}
		.not-found-message h2 {
			color: var(--error);
			font-size: 18px;
			margin-bottom: 8px;
		}
		.not-found-message p {
			color: var(--muted);
		}
		.actions {
			display: flex;
			gap: 12px;
			justify-content: center;
		}
		.btn {
			display: inline-block;
			padding: 10px 20px;
			font-size: 14px;
			font-weight: 500;
			border-radius: 4px;
			text-decoration: none;
			cursor: pointer;
			border: none;
		}
		.btn-primary {
			background: var(--primary);
			color: #fff;
		}
		.btn-primary:hover {
			background: var(--primary-hover);
		}
		.btn-secondary {
			background: var(--card);
			color: var(--text);
			border: 1px solid var(--border);
		}
		.footer {
			background: var(--card);
			border-top: 1px solid var(--border);
			padding: 16px 20px;
			text-align: center;
			font-size: 13px;
			color: var(--muted);
		}
		.footer a { color: var(--primary); text-decoration: none; }
		@media (max-width: 480px) {
			.card { padding: 24px 20px; }
			.serial-badge { font-size: 22px; }
			.actions { flex-direction: column; }
			.btn { width: 100%; text-align: center; }
		}
	</style>
</head>
<body>
	<header class="header">
		<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
	</header>

	<main class="main">
		<div class="card">
			<h1><?php esc_html_e( 'Micro-ID Lookup', 'qsa-engraving' ); ?></h1>
			<p class="subtitle"><?php esc_html_e( 'Product information for your LED module', 'qsa-engraving' ); ?></p>

			<?php if ( $serial && $serial_info ) : ?>
				<div class="serial-badge"><?php echo esc_html( $serial ); ?></div>

				<?php if ( $serial_info['found'] ) : ?>
					<div class="info-grid">
						<div class="info-item">
							<div class="info-label"><?php esc_html_e( 'Product SKU', 'qsa-engraving' ); ?></div>
							<div class="info-value <?php echo empty( $serial_info['sku'] ) ? 'not-found' : ''; ?>">
								<?php echo esc_html( $serial_info['sku'] ?: __( 'Not available', 'qsa-engraving' ) ); ?>
							</div>
						</div>
						<div class="info-item">
							<div class="info-label"><?php esc_html_e( 'Product Name', 'qsa-engraving' ); ?></div>
							<div class="info-value <?php echo empty( $serial_info['product_name'] ) ? 'not-found' : ''; ?>">
								<?php echo esc_html( $serial_info['product_name'] ?: __( 'Not available', 'qsa-engraving' ) ); ?>
							</div>
						</div>
						<div class="info-item">
							<div class="info-label"><?php esc_html_e( 'Engraved Date', 'qsa-engraving' ); ?></div>
							<div class="info-value <?php echo empty( $serial_info['engraved_at'] ) ? 'not-found' : ''; ?>">
								<?php echo esc_html( $serial_info['engraved_at'] ?: __( 'Not available', 'qsa-engraving' ) ); ?>
							</div>
						</div>
					</div>
				<?php else : ?>
					<div class="not-found-message">
						<h2><?php esc_html_e( 'Serial Not Found', 'qsa-engraving' ); ?></h2>
						<p><?php esc_html_e( 'This serial number was not found in our system. It may be from a module that predates our tracking system.', 'qsa-engraving' ); ?></p>
					</div>
				<?php endif; ?>

				<div class="actions">
					<a href="<?php echo esc_url( home_url( '/decode/' ) ); ?>" class="btn btn-primary">
						<?php esc_html_e( 'Decode Another', 'qsa-engraving' ); ?>
					</a>
					<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn-secondary">
						<?php esc_html_e( 'Contact Us', 'qsa-engraving' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="not-found-message">
					<h2><?php esc_html_e( 'No Serial Provided', 'qsa-engraving' ); ?></h2>
					<p><?php esc_html_e( 'Please use the decoder tool to look up your module information.', 'qsa-engraving' ); ?></p>
				</div>
				<div class="actions">
					<a href="<?php echo esc_url( home_url( '/decode/' ) ); ?>" class="btn btn-primary">
						<?php esc_html_e( 'Go to Decoder', 'qsa-engraving' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</main>

	<footer class="footer">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></p>
	</footer>
</body>
</html>
		<?php
	}
}
