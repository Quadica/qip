<?php
/**
 * Micro-ID Manual Decoder Handler
 *
 * Handles routing for /decode URL and displays a human-in-the-loop
 * Micro-ID decoder interface. Users manually identify dots in a 5x5 grid
 * and the system performs validation and serial number conversion.
 *
 * @package QSA_Engraving
 * @since 1.2.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Frontend;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MicroID_Manual_Decoder_Handler
 *
 * Registers WordPress rewrite rules to capture /decode URL
 * and displays a landing page for human-assisted Micro-ID decoding.
 */
class MicroID_Manual_Decoder_Handler {

	/**
	 * Query variable name for manual decode page.
	 *
	 * @var string
	 */
	public const QUERY_VAR = 'qsa_manual_decode';

	/**
	 * Bit positions in the 5x5 grid (row-major order, excluding corners).
	 * Maps grid index (0-24) to bit index (0-20 for data+parity, -1 for anchors).
	 *
	 * Grid layout:
	 * Row 0: [ANCHOR] [Bit19] [Bit18] [Bit17] [ANCHOR]
	 * Row 1: [Bit16]  [Bit15] [Bit14] [Bit13] [Bit12]
	 * Row 2: [Bit11]  [Bit10] [Bit9]  [Bit8]  [Bit7]
	 * Row 3: [Bit6]   [Bit5]  [Bit4]  [Bit3]  [Bit2]
	 * Row 4: [ANCHOR] [Bit1]  [Bit0]  [PARITY][ANCHOR]
	 *
	 * @var array<int, int>
	 */
	private const GRID_TO_BIT_MAP = array(
		0  => -1, // Anchor (0,0)
		1  => 19, // Bit 19 (MSB)
		2  => 18,
		3  => 17,
		4  => -1, // Anchor (0,4)
		5  => 16,
		6  => 15,
		7  => 14,
		8  => 13,
		9  => 12,
		10 => 11,
		11 => 10,
		12 => 9,
		13 => 8,
		14 => 7,
		15 => 6,
		16 => 5,
		17 => 4,
		18 => 3,
		19 => 2,
		20 => -1, // Anchor (4,0)
		21 => 1,
		22 => 0,  // Bit 0 (LSB)
		23 => -2, // Parity bit
		24 => -1, // Anchor (4,4)
	);

	/**
	 * Anchor positions (grid indices that are always ON).
	 *
	 * @var array<int>
	 */
	private const ANCHOR_POSITIONS = array( 0, 4, 20, 24 );

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_decode_request' ) );
	}

	/**
	 * Add rewrite rules for /decode URL.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		// Match /decode or /decode/ at root level.
		add_rewrite_rule(
			'^decode/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Register the manual decode query variable.
	 *
	 * @param array<string> $vars Existing query variables.
	 * @return array<string> Modified query variables.
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Handle decode page requests.
	 *
	 * When /decode URL is accessed, display the manual decoder page.
	 * If the decoder is disabled in settings, shows a disabled message.
	 *
	 * @return void
	 */
	public function handle_decode_request(): void {
		$manual_decode = get_query_var( self::QUERY_VAR );

		if ( empty( $manual_decode ) ) {
			return;
		}

		// Check if the decoder is enabled in settings.
		if ( ! $this->is_decoder_enabled() ) {
			$this->render_disabled_page();
			exit;
		}

		// Render the manual decoder page.
		$this->render_decoder_page();
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
	<title><?php esc_html_e( 'Micro-ID Manual Decoder', 'qsa-engraving' ); ?> - <?php echo esc_html( $site_name ); ?></title>
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
	 * Render the manual Micro-ID decoder page.
	 *
	 * @return void
	 */
	private function render_decoder_page(): void {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		$id_url    = home_url( '/id/' );

		// Get grid mapping for JavaScript.
		$grid_to_bit_json   = wp_json_encode( self::GRID_TO_BIT_MAP );
		$anchor_positions   = wp_json_encode( self::ANCHOR_POSITIONS );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title><?php esc_html_e( 'Micro-ID Manual Decoder', 'qsa-engraving' ); ?> - <?php echo esc_html( $site_name ); ?></title>
	<meta name="robots" content="noindex, nofollow">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" integrity="sha512-hvNR0F/e2J7zPPfLC9auFe3/SE0yG4aJCOd/qxew74NN7eyiSKjr7xJJMu1Jy2wf7FXITpWS1E/RY8yzuXN7VA==" crossorigin="anonymous" referrerpolicy="no-referrer">
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
			--dot-color: #CD7F32;
			--dot-hover: #b06e2a;
			--anchor-color: #8B4513;
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
			-webkit-tap-highlight-color: transparent;
		}

		/* Header */
		.decode-header {
			background: var(--card-bg);
			border-bottom: 1px solid var(--border-color);
			padding: 12px 16px;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}

		.decode-header a {
			color: var(--primary-color);
			text-decoration: none;
			font-size: 18px;
			font-weight: 600;
		}

		.help-btn {
			background: none;
			border: 1px solid var(--border-color);
			border-radius: 50%;
			width: 32px;
			height: 32px;
			font-size: 16px;
			cursor: pointer;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.help-btn:hover {
			background: var(--bg-color);
		}

		/* Main Content */
		.decode-main {
			flex: 1;
			display: flex;
			flex-direction: column;
			padding: 16px;
			max-width: 600px;
			margin: 0 auto;
			width: 100%;
		}

		/* Page Title */
		.page-title {
			text-align: center;
			margin-bottom: 20px;
		}

		.page-title h1 {
			font-size: 22px;
			font-weight: 600;
			margin-bottom: 4px;
		}

		.page-title p {
			font-size: 14px;
			color: var(--text-muted);
		}

		/* Section Card */
		.section-card {
			background: var(--card-bg);
			border: 1px solid var(--border-color);
			border-radius: 8px;
			padding: 20px;
			margin-bottom: 16px;
		}

		.section-title {
			font-size: 14px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			color: var(--text-muted);
			margin-bottom: 12px;
		}

		/* Upload Section */
		.upload-area {
			border: 2px dashed var(--border-color);
			border-radius: 8px;
			padding: 30px 20px;
			text-align: center;
			cursor: pointer;
			transition: all 0.2s ease;
			background: var(--bg-color);
		}

		.upload-area:hover,
		.upload-area.dragover {
			border-color: var(--primary-color);
			background: #f0f6fc;
		}

		.upload-icon {
			font-size: 40px;
			margin-bottom: 12px;
		}

		.upload-text {
			font-size: 15px;
			font-weight: 500;
			margin-bottom: 6px;
		}

		.upload-hint {
			font-size: 12px;
			color: var(--text-muted);
		}

		.file-input {
			display: none;
		}

		/* Upload buttons for mobile */
		.upload-buttons {
			display: flex;
			gap: 12px;
			margin-bottom: 12px;
		}

		.upload-btn {
			flex: 1;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 20px 16px;
			border: 2px solid var(--border-color);
			border-radius: 8px;
			background: var(--bg-color);
			cursor: pointer;
			transition: all 0.2s ease;
			min-height: 100px;
		}

		.upload-btn:hover,
		.upload-btn:active {
			border-color: var(--primary-color);
			background: #f0f6fc;
		}

		.upload-btn-icon {
			font-size: 32px;
			margin-bottom: 8px;
		}

		.upload-btn-text {
			font-size: 14px;
			font-weight: 500;
			color: var(--text-color);
		}

		/* Camera Modal */
		.camera-modal {
			display: none;
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: #000;
			z-index: 10000;
		}

		.camera-modal.active {
			display: block;
		}

		.camera-container {
			position: relative;
			width: 100%;
			height: 100%;
		}

		#camera-video {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			object-fit: cover;
			background: #000;
		}

		.camera-controls {
			position: absolute;
			bottom: 0;
			left: 0;
			right: 0;
			display: flex;
			justify-content: space-around;
			align-items: center;
			padding: 20px;
			padding-bottom: max(20px, env(safe-area-inset-bottom));
			background: rgba(0, 0, 0, 0.7);
			z-index: 10001;
		}

		.camera-control-btn {
			padding: 12px 24px;
			font-size: 16px;
			color: #fff;
			background: rgba(255, 255, 255, 0.2);
			border: 1px solid #fff;
			border-radius: 8px;
			cursor: pointer;
			min-width: 80px;
		}

		.camera-control-btn:active {
			background: rgba(255, 255, 255, 0.4);
		}

		.camera-shutter-btn {
			width: 70px;
			height: 70px;
			font-size: 28px;
			background: #fff;
			border: 4px solid #ccc;
			border-radius: 50%;
			cursor: pointer;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.camera-shutter-btn:active {
			transform: scale(0.95);
			background: #eee;
		}

		/* Image Preview with Cropper */
		.image-preview-container {
			display: none;
			margin-bottom: 16px;
		}

		.image-preview-container.active {
			display: block;
		}

		.image-wrapper {
			max-height: 300px;
			overflow: hidden;
			border-radius: 6px;
			background: #000;
		}

		.image-wrapper img {
			display: block;
			max-width: 100%;
		}

		/* Cropper Controls */
		.cropper-controls {
			display: flex;
			gap: 8px;
			justify-content: center;
			margin-top: 12px;
			flex-wrap: wrap;
		}

		.crop-btn {
			padding: 8px 14px;
			font-size: 13px;
			font-weight: 500;
			border: 1px solid var(--border-color);
			border-radius: 4px;
			background: var(--card-bg);
			cursor: pointer;
			display: flex;
			align-items: center;
			gap: 6px;
			transition: all 0.15s ease;
		}

		.crop-btn:hover {
			background: var(--bg-color);
		}

		.crop-btn:active {
			transform: scale(0.97);
		}

		/* Grid Section */
		.grid-section {
			display: none;
		}

		.grid-section.active {
			display: block;
		}

		.grid-layout {
			display: flex;
			gap: 16px;
			align-items: flex-start;
			flex-wrap: wrap;
			justify-content: center;
		}

		/* Cropped Image Panel */
		.cropped-panel {
			flex: 1;
			min-width: 140px;
			max-width: 180px;
		}

		.cropped-image {
			width: 100%;
			aspect-ratio: 1;
			border: 2px solid var(--border-color);
			border-radius: 6px;
			overflow: hidden;
			background: #000;
		}

		.cropped-image img {
			width: 100%;
			height: 100%;
			object-fit: contain;
		}

		/* Interactive Grid Panel */
		.grid-panel {
			flex: 1;
			min-width: 200px;
			max-width: 280px;
		}

		.decode-grid {
			display: grid;
			grid-template-columns: repeat(5, 1fr);
			gap: 4px;
			aspect-ratio: 1;
			padding: 8px;
			background: #f5f5f5;
			border-radius: 8px;
		}

		.grid-cell {
			aspect-ratio: 1;
			border: 2px solid #ddd;
			border-radius: 6px;
			cursor: pointer;
			transition: all 0.15s ease;
			background: #fff;
			display: flex;
			align-items: center;
			justify-content: center;
			position: relative;
		}

		.grid-cell:hover:not(.anchor) {
			border-color: var(--primary-color);
			transform: scale(1.05);
		}

		.grid-cell:active:not(.anchor) {
			transform: scale(0.95);
		}

		.grid-cell.has-dot {
			background: var(--dot-color);
			border-color: var(--dot-hover);
		}

		.grid-cell.has-dot::after {
			content: '';
			width: 60%;
			height: 60%;
			border-radius: 50%;
			background: rgba(0, 0, 0, 0.3);
		}

		.grid-cell.anchor {
			background: var(--anchor-color);
			border-color: #6b3410;
			cursor: default;
			opacity: 0.8;
		}

		.grid-cell.anchor::after {
			content: '';
			width: 60%;
			height: 60%;
			border-radius: 50%;
			background: rgba(255, 255, 255, 0.3);
		}

		/* Status Display */
		.status-display {
			margin-top: 16px;
			padding: 16px;
			border-radius: 8px;
			text-align: center;
			transition: all 0.2s ease;
		}

		.status-display.neutral {
			background: var(--bg-color);
			color: var(--text-muted);
		}

		.status-display.invalid {
			background: var(--warning-bg);
			border: 1px solid var(--warning-color);
			color: #8a6d3b;
		}

		.status-display.valid {
			background: var(--success-bg);
			border: 1px solid var(--success-color);
			color: #155724;
		}

		.status-icon {
			font-size: 28px;
			margin-bottom: 8px;
		}

		.status-text {
			font-size: 14px;
			margin-bottom: 4px;
		}

		.serial-display {
			font-family: 'SF Mono', 'Consolas', 'Monaco', monospace;
			font-size: 28px;
			font-weight: 700;
			letter-spacing: 2px;
			color: var(--success-color);
			margin: 8px 0;
		}

		/* Action Buttons */
		.action-buttons {
			display: flex;
			gap: 12px;
			justify-content: center;
			margin-top: 16px;
			flex-wrap: wrap;
		}

		.btn {
			padding: 12px 24px;
			font-size: 15px;
			font-weight: 500;
			border-radius: 6px;
			border: none;
			cursor: pointer;
			text-decoration: none;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			transition: all 0.15s ease;
			min-width: 140px;
		}

		.btn:active {
			transform: scale(0.97);
		}

		.btn-primary {
			background: var(--primary-color);
			color: #fff;
		}

		.btn-primary:hover {
			background: var(--primary-hover);
		}

		.btn-success {
			background: var(--success-color);
			color: #fff;
		}

		.btn-success:hover {
			background: #008522;
		}

		.btn-secondary {
			background: var(--card-bg);
			color: var(--text-color);
			border: 1px solid var(--border-color);
		}

		.btn-secondary:hover {
			background: var(--bg-color);
		}

		/* Help Modal */
		.modal-overlay {
			display: none;
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.5);
			z-index: 1000;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}

		.modal-overlay.active {
			display: flex;
		}

		.modal-content {
			background: var(--card-bg);
			border-radius: 12px;
			max-width: 480px;
			width: 100%;
			max-height: 80vh;
			overflow-y: auto;
		}

		.modal-header {
			padding: 16px 20px;
			border-bottom: 1px solid var(--border-color);
			display: flex;
			align-items: center;
			justify-content: space-between;
		}

		.modal-header h2 {
			font-size: 18px;
			font-weight: 600;
		}

		.modal-close {
			background: none;
			border: none;
			font-size: 24px;
			cursor: pointer;
			color: var(--text-muted);
			padding: 4px;
		}

		.modal-body {
			padding: 20px;
		}

		.modal-body h3 {
			font-size: 15px;
			font-weight: 600;
			margin: 16px 0 8px;
		}

		.modal-body h3:first-child {
			margin-top: 0;
		}

		.modal-body p {
			font-size: 14px;
			color: var(--text-muted);
			margin-bottom: 8px;
		}

		.modal-body ol {
			font-size: 14px;
			color: var(--text-muted);
			padding-left: 20px;
		}

		.modal-body li {
			margin-bottom: 6px;
		}

		.grid-legend {
			display: flex;
			gap: 16px;
			margin-top: 12px;
			flex-wrap: wrap;
		}

		.legend-item {
			display: flex;
			align-items: center;
			gap: 8px;
			font-size: 13px;
		}

		.legend-swatch {
			width: 20px;
			height: 20px;
			border-radius: 4px;
		}

		.legend-swatch.anchor {
			background: var(--anchor-color);
		}

		.legend-swatch.dot {
			background: var(--dot-color);
		}

		.legend-swatch.empty {
			background: #fff;
			border: 2px solid #ddd;
		}

		/* Footer */
		.decode-footer {
			background: var(--card-bg);
			border-top: 1px solid var(--border-color);
			padding: 12px 16px;
			text-align: center;
			font-size: 12px;
			color: var(--text-muted);
		}

		.decode-footer a {
			color: var(--primary-color);
			text-decoration: none;
		}

		/* Noscript */
		.noscript-notice {
			text-align: center;
			padding: 32px 20px;
		}

		.noscript-notice h2 {
			font-size: 18px;
			margin: 16px 0 8px;
		}

		.noscript-notice p {
			color: var(--text-muted);
			margin-bottom: 8px;
		}

		/* Responsive */
		@media (max-width: 480px) {
			.decode-main {
				padding: 12px;
			}

			.page-title h1 {
				font-size: 20px;
			}

			.section-card {
				padding: 16px;
			}

			.grid-layout {
				flex-direction: column;
				align-items: center;
			}

			.cropped-panel,
			.grid-panel {
				max-width: 100%;
				width: 100%;
			}

			.decode-grid {
				max-width: 280px;
				margin: 0 auto;
			}

			.serial-display {
				font-size: 24px;
			}

			.btn {
				width: 100%;
			}

			.cropper-controls {
				flex-direction: column;
			}

			.crop-btn {
				justify-content: center;
			}
		}
	</style>
</head>
<body>
	<header class="decode-header">
		<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
		<button type="button" class="help-btn" id="help-btn" aria-label="Help">?</button>
	</header>

	<main class="decode-main">
		<div class="page-title">
			<h1><?php esc_html_e( 'Micro-ID Manual Decoder', 'qsa-engraving' ); ?></h1>
			<p><?php esc_html_e( 'Identify the dots to decode your LED module serial number', 'qsa-engraving' ); ?></p>
		</div>

		<!-- Noscript Fallback -->
		<noscript>
			<div class="section-card">
				<div class="noscript-notice">
					<div style="font-size: 48px;">⚠️</div>
					<h2><?php esc_html_e( 'JavaScript Required', 'qsa-engraving' ); ?></h2>
					<p><?php esc_html_e( 'This decoder requires JavaScript to function.', 'qsa-engraving' ); ?></p>
					<p><?php esc_html_e( 'Please enable JavaScript or contact support for assistance.', 'qsa-engraving' ); ?></p>
					<p style="margin-top: 16px;">
						<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn-primary">
							<?php esc_html_e( 'Contact Support', 'qsa-engraving' ); ?>
						</a>
					</p>
				</div>
			</div>
		</noscript>

		<!-- Step 1: Upload Image -->
		<div class="section-card" id="upload-section">
			<div class="section-title"><?php esc_html_e( 'Step 1: Upload Photo', 'qsa-engraving' ); ?></div>

			<!-- Two-button layout for mobile compatibility -->
			<div class="upload-buttons">
				<div class="upload-btn" id="camera-btn">
					<div class="upload-btn-icon">📷</div>
					<div class="upload-btn-text"><?php esc_html_e( 'Take Photo', 'qsa-engraving' ); ?></div>
				</div>
				<div class="upload-btn" id="library-btn">
					<div class="upload-btn-icon">🖼️</div>
					<div class="upload-btn-text"><?php esc_html_e( 'Choose Photo', 'qsa-engraving' ); ?></div>
				</div>
			</div>

			<!-- Drag and drop area for desktop -->
			<div class="upload-area" id="upload-area">
				<div class="upload-hint"><?php esc_html_e( 'Or drag and drop an image here', 'qsa-engraving' ); ?></div>
			</div>

			<!-- Hidden file input for library/gallery -->
			<input type="file"
			       id="file-input-library"
			       class="file-input"
			       accept="image/*">

			<!-- Camera modal for getUserMedia -->
			<div class="camera-modal" id="camera-modal">
				<div class="camera-container">
					<video id="camera-video" autoplay playsinline></video>
					<canvas id="camera-canvas" style="display: none;"></canvas>
					<div class="camera-controls">
						<button type="button" class="camera-control-btn" id="camera-cancel"><?php esc_html_e( 'Cancel', 'qsa-engraving' ); ?></button>
						<button type="button" class="camera-shutter-btn" id="camera-shutter">📷</button>
						<button type="button" class="camera-control-btn" id="camera-switch"><?php esc_html_e( 'Flip', 'qsa-engraving' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Image Preview with Cropper -->
			<div class="image-preview-container" id="image-preview-container">
				<div class="image-wrapper">
					<img id="preview-image" src="" alt="Preview">
				</div>
				<div class="cropper-controls">
					<button type="button" class="crop-btn" id="rotate-left">↺ <?php esc_html_e( 'Rotate', 'qsa-engraving' ); ?></button>
					<button type="button" class="crop-btn" id="rotate-right">↻ <?php esc_html_e( 'Rotate', 'qsa-engraving' ); ?></button>
					<button type="button" class="crop-btn" id="zoom-in">+ <?php esc_html_e( 'Zoom', 'qsa-engraving' ); ?></button>
					<button type="button" class="crop-btn" id="zoom-out">− <?php esc_html_e( 'Zoom', 'qsa-engraving' ); ?></button>
					<button type="button" class="crop-btn btn-primary" id="apply-crop"><?php esc_html_e( 'Apply Crop', 'qsa-engraving' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Step 2: Interactive Grid -->
		<div class="section-card grid-section" id="grid-section">
			<div class="section-title"><?php esc_html_e( 'Step 2: Match the Dots', 'qsa-engraving' ); ?></div>

			<div class="grid-layout">
				<!-- Cropped Reference Image -->
				<div class="cropped-panel">
					<div class="cropped-image">
						<img id="cropped-image" src="" alt="Cropped Micro-ID">
					</div>
				</div>

				<!-- Interactive 5x5 Grid -->
				<div class="grid-panel">
					<div class="decode-grid" id="decode-grid">
						<?php for ( $i = 0; $i < 25; $i++ ) : ?>
							<?php
							$is_anchor = in_array( $i, self::ANCHOR_POSITIONS, true );
							$classes   = 'grid-cell';
							if ( $is_anchor ) {
								$classes .= ' anchor has-dot';
							}
							?>
							<div class="<?php echo esc_attr( $classes ); ?>"
							     data-index="<?php echo esc_attr( (string) $i ); ?>"
							     <?php if ( $is_anchor ) : ?>aria-label="Anchor dot (fixed)"<?php endif; ?>>
							</div>
						<?php endfor; ?>
					</div>
				</div>
			</div>

			<!-- Status Display -->
			<div class="status-display neutral" id="status-display">
				<div class="status-icon">🎯</div>
				<div class="status-text"><?php esc_html_e( 'Tap cells to match the dots you see in your photo', 'qsa-engraving' ); ?></div>
			</div>

			<!-- Action Buttons -->
			<div class="action-buttons" id="action-buttons">
				<button type="button" class="btn btn-secondary" id="clear-grid">
					<?php esc_html_e( 'Clear Grid', 'qsa-engraving' ); ?>
				</button>
				<button type="button" class="btn btn-secondary" id="new-image">
					<?php esc_html_e( 'New Image', 'qsa-engraving' ); ?>
				</button>
				<a href="#" class="btn btn-success" id="view-module" style="display: none;">
					<?php esc_html_e( 'View Module Info', 'qsa-engraving' ); ?>
				</a>
			</div>
		</div>
	</main>

	<footer class="decode-footer">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></p>
	</footer>

	<!-- Help Modal -->
	<div class="modal-overlay" id="help-modal">
		<div class="modal-content">
			<div class="modal-header">
				<h2><?php esc_html_e( 'How to Use', 'qsa-engraving' ); ?></h2>
				<button type="button" class="modal-close" id="close-modal">×</button>
			</div>
			<div class="modal-body">
				<h3><?php esc_html_e( 'What is a Micro-ID?', 'qsa-engraving' ); ?></h3>
				<p><?php esc_html_e( 'A Micro-ID is a tiny 5x5 dot matrix engraved on your LED module. It encodes a unique serial number that links to your product information.', 'qsa-engraving' ); ?></p>

				<h3><?php esc_html_e( 'Steps', 'qsa-engraving' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Take a close-up photo of the Micro-ID on your LED module', 'qsa-engraving' ); ?></li>
					<li><?php esc_html_e( 'Upload the photo and crop to show just the Micro-ID area', 'qsa-engraving' ); ?></li>
					<li><?php esc_html_e( 'Look at your cropped photo and tap the grid cells to match the dots you see', 'qsa-engraving' ); ?></li>
					<li><?php esc_html_e( 'When the pattern is valid, click "View Module Info" to see your product details', 'qsa-engraving' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Grid Legend', 'qsa-engraving' ); ?></h3>
				<div class="grid-legend">
					<div class="legend-item">
						<div class="legend-swatch anchor"></div>
						<span><?php esc_html_e( 'Corner anchors (fixed)', 'qsa-engraving' ); ?></span>
					</div>
					<div class="legend-item">
						<div class="legend-swatch dot"></div>
						<span><?php esc_html_e( 'Dot present', 'qsa-engraving' ); ?></span>
					</div>
					<div class="legend-item">
						<div class="legend-swatch empty"></div>
						<span><?php esc_html_e( 'No dot', 'qsa-engraving' ); ?></span>
					</div>
				</div>

				<h3><?php esc_html_e( 'Tips', 'qsa-engraving' ); ?></h3>
				<p><?php esc_html_e( 'Look for a small dot outside the grid near one corner - this orientation marker helps you align the grid correctly. The four corners always have dots.', 'qsa-engraving' ); ?></p>
			</div>
		</div>
	</div>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js" integrity="sha512-9KkIqdfN7ipEW6B6k+Aq20PV31bjODg4AA52W+tYtAE0jE0kMx49bjJ3FgvS56wzmyfMUHbQ4Km2b7l9+Y/+Eg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script>
	(function() {
		'use strict';

		// Configuration from PHP
		const config = {
			idUrl: <?php echo wp_json_encode( $id_url ); ?>,
			gridToBitMap: <?php echo $grid_to_bit_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded array ?>,
			anchorPositions: <?php echo $anchor_positions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded array ?>,
			strings: {
				tapToMatch: <?php echo wp_json_encode( __( 'Tap cells to match the dots you see in your photo', 'qsa-engraving' ) ); ?>,
				parityError: <?php echo wp_json_encode( __( 'Parity check failed - please verify your entry', 'qsa-engraving' ) ); ?>,
				validCode: <?php echo wp_json_encode( __( 'Valid code detected!', 'qsa-engraving' ) ); ?>,
				serial: <?php echo wp_json_encode( __( 'Serial', 'qsa-engraving' ) ); ?>
			}
		};

		// DOM Elements
		const uploadArea = document.getElementById('upload-area');
		const cameraBtn = document.getElementById('camera-btn');
		const libraryBtn = document.getElementById('library-btn');
		const fileInputLibrary = document.getElementById('file-input-library');
		const imagePreviewContainer = document.getElementById('image-preview-container');

		// Camera modal elements
		const cameraModal = document.getElementById('camera-modal');
		const cameraVideo = document.getElementById('camera-video');
		const cameraCanvas = document.getElementById('camera-canvas');
		const cameraCancel = document.getElementById('camera-cancel');
		const cameraShutter = document.getElementById('camera-shutter');
		const cameraSwitch = document.getElementById('camera-switch');

		// Camera state
		let cameraStream = null;
		let facingMode = 'environment'; // 'environment' = back camera, 'user' = front camera
		const previewImage = document.getElementById('preview-image');
		const gridSection = document.getElementById('grid-section');
		const croppedImage = document.getElementById('cropped-image');
		const decodeGrid = document.getElementById('decode-grid');
		const statusDisplay = document.getElementById('status-display');
		const viewModuleBtn = document.getElementById('view-module');
		const clearGridBtn = document.getElementById('clear-grid');
		const newImageBtn = document.getElementById('new-image');
		const helpBtn = document.getElementById('help-btn');
		const helpModal = document.getElementById('help-modal');
		const closeModal = document.getElementById('close-modal');
		const applyCropBtn = document.getElementById('apply-crop');
		const rotateLeftBtn = document.getElementById('rotate-left');
		const rotateRightBtn = document.getElementById('rotate-right');
		const zoomInBtn = document.getElementById('zoom-in');
		const zoomOutBtn = document.getElementById('zoom-out');

		// State
		let cropper = null;
		let gridState = new Array(25).fill(0);

		// Initialize anchors as ON
		config.anchorPositions.forEach(pos => {
			gridState[pos] = 1;
		});

		/**
		 * Handle file selection.
		 */
		function handleFileSelect(file) {
			if (!file || !file.type.startsWith('image/')) {
				return;
			}

			const reader = new FileReader();
			reader.onload = function(e) {
				previewImage.src = e.target.result;
				imagePreviewContainer.classList.add('active');
				uploadArea.style.display = 'none';

				// Destroy existing cropper if any
				if (cropper) {
					cropper.destroy();
				}

				// Initialize Cropper.js
				cropper = new Cropper(previewImage, {
					aspectRatio: 1,
					viewMode: 1,
					dragMode: 'move',
					autoCropArea: 0.8,
					responsive: true,
					zoomable: true,
					rotatable: true,
					minCropBoxWidth: 50,
					minCropBoxHeight: 50,
					background: false
				});
			};
			reader.readAsDataURL(file);
		}

		/**
		 * Apply crop and show grid section.
		 */
		function applyCrop() {
			if (!cropper) return;

			const canvas = cropper.getCroppedCanvas({
				width: 300,
				height: 300,
				imageSmoothingQuality: 'high'
			});

			if (canvas) {
				croppedImage.src = canvas.toDataURL('image/jpeg', 0.9);
				gridSection.classList.add('active');
				imagePreviewContainer.classList.remove('active');

				// Scroll to grid section
				gridSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}

		/**
		 * Toggle a grid cell's state.
		 */
		function toggleCell(index) {
			// Don't toggle anchors
			if (config.anchorPositions.includes(index)) {
				return;
			}

			gridState[index] = gridState[index] === 1 ? 0 : 1;

			// Update visual
			const cell = decodeGrid.children[index];
			cell.classList.toggle('has-dot', gridState[index] === 1);

			// Validate and update status
			validateGrid();
		}

		/**
		 * Validate the current grid state.
		 */
		function validateGrid() {
			const result = decodeGridState();

			if (result.valid) {
				showValidState(result);
			} else if (result.hasInput) {
				showInvalidState(result.error);
			} else {
				showNeutralState();
			}
		}

		/**
		 * Decode the grid state to a serial number.
		 */
		function decodeGridState() {
			// Check if user has made any selections (beyond anchors)
			let hasUserInput = false;
			for (let i = 0; i < 25; i++) {
				if (!config.anchorPositions.includes(i) && gridState[i] === 1) {
					hasUserInput = true;
					break;
				}
			}

			if (!hasUserInput) {
				return { valid: false, hasInput: false };
			}

			// Extract bits according to the mapping
			// Bit positions 0-19 are data bits, -2 is parity
			const dataBits = new Array(20).fill(0);
			let parityBit = 0;

			for (let gridIndex = 0; gridIndex < 25; gridIndex++) {
				const bitIndex = config.gridToBitMap[gridIndex];

				if (bitIndex >= 0) {
					// Data bit
					dataBits[bitIndex] = gridState[gridIndex];
				} else if (bitIndex === -2) {
					// Parity bit
					parityBit = gridState[gridIndex];
				}
				// -1 is anchor, skip
			}

			// Count ones in data bits
			const dataOnes = dataBits.reduce((sum, bit) => sum + bit, 0);

			// Even parity check: total ones (data + parity) must be even
			const totalOnes = dataOnes + parityBit;
			const parityValid = (totalOnes % 2) === 0;

			if (!parityValid) {
				return {
					valid: false,
					hasInput: true,
					error: 'parity',
					dataOnes: dataOnes,
					parityBit: parityBit
				};
			}

			// Convert data bits to integer (MSB first)
			// dataBits[19] is MSB, dataBits[0] is LSB
			let serial = 0;
			for (let i = 19; i >= 0; i--) {
				serial = (serial << 1) | dataBits[i];
			}

			// Format as 8-digit string
			const serialFormatted = serial.toString().padStart(8, '0');

			return {
				valid: true,
				hasInput: true,
				serial: serial,
				serialFormatted: serialFormatted
			};
		}

		/**
		 * Show neutral status (no input yet).
		 */
		function showNeutralState() {
			statusDisplay.className = 'status-display neutral';
			statusDisplay.innerHTML = `
				<div class="status-icon">🎯</div>
				<div class="status-text">${escapeHtml(config.strings.tapToMatch)}</div>
			`;
			viewModuleBtn.style.display = 'none';
		}

		/**
		 * Show invalid status (parity error).
		 */
		function showInvalidState(error) {
			statusDisplay.className = 'status-display invalid';
			statusDisplay.innerHTML = `
				<div class="status-icon">⚠️</div>
				<div class="status-text">${escapeHtml(config.strings.parityError)}</div>
			`;
			viewModuleBtn.style.display = 'none';
		}

		/**
		 * Show valid status with decoded serial.
		 */
		function showValidState(result) {
			statusDisplay.className = 'status-display valid';
			statusDisplay.innerHTML = `
				<div class="status-icon">✓</div>
				<div class="status-text">${escapeHtml(config.strings.validCode)}</div>
				<div class="serial-display">${escapeHtml(result.serialFormatted)}</div>
			`;

			// Update and show view module button
			viewModuleBtn.href = config.idUrl + '?serial=' + encodeURIComponent(result.serialFormatted);
			viewModuleBtn.style.display = 'inline-flex';
		}

		/**
		 * Clear all non-anchor cells.
		 */
		function clearGrid() {
			for (let i = 0; i < 25; i++) {
				if (!config.anchorPositions.includes(i)) {
					gridState[i] = 0;
					decodeGrid.children[i].classList.remove('has-dot');
				}
			}
			showNeutralState();
		}

		/**
		 * Reset to initial state for new image.
		 */
		function resetForNewImage() {
			// Clear grid
			clearGrid();

			// Hide grid section
			gridSection.classList.remove('active');

			// Hide cropper preview
			imagePreviewContainer.classList.remove('active');

			// Show upload area
			uploadArea.style.display = 'block';

			// Destroy cropper
			if (cropper) {
				cropper.destroy();
				cropper = null;
			}

			// Clear file input
			fileInputLibrary.value = '';

			// Clear images
			previewImage.src = '';
			croppedImage.src = '';
		}

		/**
		 * Escape HTML for safe insertion.
		 */
		function escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}

		// ==========================================
		// Camera Functions (getUserMedia API)
		// ==========================================

		/**
		 * Open camera modal and start video stream.
		 */
		async function openCamera() {
			try {
				// Request camera access
				const constraints = {
					video: {
						facingMode: facingMode,
						width: { ideal: 1920 },
						height: { ideal: 1080 }
					},
					audio: false
				};

				cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
				cameraVideo.srcObject = cameraStream;
				cameraModal.classList.add('active');

			} catch (error) {
				console.error('Camera access error:', error);
				if (error.name === 'NotAllowedError') {
					alert('Camera access was denied. Please allow camera access in your browser settings.');
				} else if (error.name === 'NotFoundError') {
					alert('No camera found on this device.');
				} else {
					alert('Could not access camera: ' + error.message);
				}
			}
		}

		/**
		 * Close camera modal and stop video stream.
		 */
		function closeCamera() {
			if (cameraStream) {
				cameraStream.getTracks().forEach(track => track.stop());
				cameraStream = null;
			}
			cameraVideo.srcObject = null;
			cameraModal.classList.remove('active');
		}

		/**
		 * Capture photo from video stream.
		 */
		function capturePhoto() {
			// Set canvas size to video size
			cameraCanvas.width = cameraVideo.videoWidth;
			cameraCanvas.height = cameraVideo.videoHeight;

			// Draw video frame to canvas
			const ctx = cameraCanvas.getContext('2d');
			ctx.drawImage(cameraVideo, 0, 0);

			// Convert to blob and create file-like object
			cameraCanvas.toBlob(function(blob) {
				if (blob) {
					// Create a File object from the blob
					const file = new File([blob], 'camera-photo.jpg', { type: 'image/jpeg' });
					closeCamera();
					handleFileSelect(file);
				}
			}, 'image/jpeg', 0.92);
		}

		/**
		 * Switch between front and back camera.
		 */
		async function switchCamera() {
			facingMode = facingMode === 'environment' ? 'user' : 'environment';
			closeCamera();
			await openCamera();
		}

		// ==========================================
		// Event Listeners
		// ==========================================

		// Camera button click - opens getUserMedia camera
		cameraBtn.addEventListener('click', function() {
			openCamera();
		});

		// Camera modal controls
		cameraCancel.addEventListener('click', closeCamera);
		cameraShutter.addEventListener('click', capturePhoto);
		cameraSwitch.addEventListener('click', switchCamera);

		// Library button click - opens photo library
		libraryBtn.addEventListener('click', function() {
			fileInputLibrary.click();
		});

		// Upload area click (desktop drag-drop fallback) - opens library
		uploadArea.addEventListener('click', function() {
			fileInputLibrary.click();
		});

		// File input change handler
		fileInputLibrary.addEventListener('change', function(e) {
			if (e.target.files && e.target.files[0]) {
				handleFileSelect(e.target.files[0]);
			}
		});

		// Drag and drop
		uploadArea.addEventListener('dragover', function(e) {
			e.preventDefault();
			uploadArea.classList.add('dragover');
		});

		uploadArea.addEventListener('dragleave', function(e) {
			e.preventDefault();
			uploadArea.classList.remove('dragover');
		});

		uploadArea.addEventListener('drop', function(e) {
			e.preventDefault();
			uploadArea.classList.remove('dragover');
			if (e.dataTransfer.files && e.dataTransfer.files[0]) {
				handleFileSelect(e.dataTransfer.files[0]);
			}
		});

		// Cropper controls
		applyCropBtn.addEventListener('click', applyCrop);
		rotateLeftBtn.addEventListener('click', function() {
			if (cropper) cropper.rotate(-90);
		});
		rotateRightBtn.addEventListener('click', function() {
			if (cropper) cropper.rotate(90);
		});
		zoomInBtn.addEventListener('click', function() {
			if (cropper) cropper.zoom(0.1);
		});
		zoomOutBtn.addEventListener('click', function() {
			if (cropper) cropper.zoom(-0.1);
		});

		// Grid cell clicks
		decodeGrid.addEventListener('click', function(e) {
			const cell = e.target.closest('.grid-cell');
			if (cell && !cell.classList.contains('anchor')) {
				const index = parseInt(cell.dataset.index, 10);
				toggleCell(index);
			}
		});

		// Clear grid button
		clearGridBtn.addEventListener('click', clearGrid);

		// New image button
		newImageBtn.addEventListener('click', resetForNewImage);

		// Help modal
		helpBtn.addEventListener('click', function() {
			helpModal.classList.add('active');
		});

		closeModal.addEventListener('click', function() {
			helpModal.classList.remove('active');
		});

		helpModal.addEventListener('click', function(e) {
			if (e.target === helpModal) {
				helpModal.classList.remove('active');
			}
		});

		// Close modal on escape key
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && helpModal.classList.contains('active')) {
				helpModal.classList.remove('active');
			}
		});

		// Initialize
		showNeutralState();
	})();
	</script>
</body>
</html>
		<?php
	}
}
