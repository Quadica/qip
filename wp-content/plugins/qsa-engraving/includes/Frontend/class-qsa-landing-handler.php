<?php
/**
 * QSA Landing Page Handler
 *
 * Handles routing for QSA ID URLs (e.g., /CUBE00001) and displays
 * a landing page with product/module information.
 *
 * @package QSA_Engraving
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Quadica\QSA_Engraving\Frontend;

use Quadica\QSA_Engraving\Database\QSA_Identifier_Repository;

/**
 * Class QSA_Landing_Handler
 *
 * Registers WordPress rewrite rules to capture QSA ID patterns
 * and displays a landing page when accessed.
 */
class QSA_Landing_Handler {

	/**
	 * Query variable name for QSA lookups.
	 *
	 * @var string
	 */
	public const QUERY_VAR = 'qsa_lookup';

	/**
	 * Regex pattern to match QSA IDs.
	 *
	 * Format: 4 uppercase letters + optional lowercase revision + 5 digits
	 * Examples: CUBE00001, STARa00042, PICOb12345
	 *
	 * @var string
	 */
	public const QSA_ID_PATTERN = '([A-Za-z]{4}[A-Za-z]?[0-9]{5})';

	/**
	 * QSA Identifier Repository instance.
	 *
	 * @var QSA_Identifier_Repository
	 */
	private QSA_Identifier_Repository $qsa_repository;

	/**
	 * Constructor.
	 *
	 * @param QSA_Identifier_Repository $qsa_repository QSA Identifier Repository instance.
	 */
	public function __construct( QSA_Identifier_Repository $qsa_repository ) {
		$this->qsa_repository = $qsa_repository;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_qsa_lookup' ) );
	}

	/**
	 * Add rewrite rules for QSA ID patterns.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		// Match QSA ID pattern at root level.
		// Pattern: 4 letters + optional letter + 5 digits (case-insensitive).
		add_rewrite_rule(
			'^' . self::QSA_ID_PATTERN . '/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Register the QSA lookup query variable.
	 *
	 * @param array $vars Existing query variables.
	 * @return array Modified query variables.
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Handle QSA lookup requests.
	 *
	 * When a QSA ID URL is accessed, this displays the landing page
	 * with information about the module/product.
	 *
	 * @return void
	 */
	public function handle_qsa_lookup(): void {
		$qsa_id = get_query_var( self::QUERY_VAR );

		if ( empty( $qsa_id ) ) {
			return;
		}

		// Normalize to uppercase for database lookup.
		$qsa_id = strtoupper( $qsa_id );

		// Look up the QSA ID in the database.
		$qsa_record = $this->qsa_repository->get_by_qsa_id( $qsa_id );

		// Render the landing page.
		$this->render_landing_page( $qsa_id, $qsa_record );
		exit;
	}

	/**
	 * Render the QSA landing page.
	 *
	 * @param string     $qsa_id     The QSA ID being looked up.
	 * @param array|null $qsa_record The database record, or null if not found.
	 * @return void
	 */
	private function render_landing_page( string $qsa_id, ?array $qsa_record ): void {
		// Set appropriate HTTP status.
		if ( null === $qsa_record ) {
			status_header( 404 );
		}

		// Get site info for branding.
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );

		// Start output.
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $qsa_id ); ?> - <?php echo esc_html( $site_name ); ?></title>
	<style>
		:root {
			--primary-color: #0073aa;
			--success-color: #00a32a;
			--error-color: #d63638;
			--text-color: #1d2327;
			--text-muted: #646970;
			--bg-color: #f0f0f1;
			--card-bg: #ffffff;
			--border-color: #c3c4c7;
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

		.qsa-landing-header {
			background: var(--card-bg);
			border-bottom: 1px solid var(--border-color);
			padding: 20px;
			text-align: center;
		}

		.qsa-landing-header a {
			color: var(--primary-color);
			text-decoration: none;
			font-size: 24px;
			font-weight: 600;
		}

		.qsa-landing-main {
			flex: 1;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 40px 20px;
		}

		.qsa-landing-card {
			background: var(--card-bg);
			border: 1px solid var(--border-color);
			border-radius: 8px;
			padding: 40px;
			max-width: 500px;
			width: 100%;
			text-align: center;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
		}

		.qsa-id-badge {
			display: inline-block;
			font-family: 'SF Mono', 'Consolas', 'Monaco', monospace;
			font-size: 28px;
			font-weight: 700;
			padding: 12px 24px;
			background: linear-gradient(135deg, var(--success-color) 0%, #00875a 100%);
			color: #fff;
			border-radius: 8px;
			letter-spacing: 2px;
			margin-bottom: 24px;
		}

		.qsa-id-badge.not-found {
			background: linear-gradient(135deg, var(--error-color) 0%, #a32424 100%);
		}

		.qsa-landing-card h1 {
			font-size: 20px;
			font-weight: 600;
			margin-bottom: 16px;
			color: var(--text-color);
		}

		.qsa-landing-card p {
			color: var(--text-muted);
			margin-bottom: 12px;
		}

		.qsa-info-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 16px;
			margin-top: 24px;
			text-align: left;
		}

		.qsa-info-item {
			padding: 12px;
			background: var(--bg-color);
			border-radius: 4px;
		}

		.qsa-info-label {
			font-size: 12px;
			color: var(--text-muted);
			text-transform: uppercase;
			letter-spacing: 0.5px;
			margin-bottom: 4px;
		}

		.qsa-info-value {
			font-size: 16px;
			font-weight: 600;
			color: var(--text-color);
		}

		.qsa-landing-footer {
			background: var(--card-bg);
			border-top: 1px solid var(--border-color);
			padding: 20px;
			text-align: center;
			font-size: 14px;
			color: var(--text-muted);
		}

		.qsa-landing-footer a {
			color: var(--primary-color);
			text-decoration: none;
		}

		@media (max-width: 480px) {
			.qsa-landing-card {
				padding: 24px;
			}

			.qsa-id-badge {
				font-size: 22px;
				padding: 10px 18px;
			}

			.qsa-info-grid {
				grid-template-columns: 1fr;
			}
		}
	</style>
</head>
<body>
	<header class="qsa-landing-header">
		<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
	</header>

	<main class="qsa-landing-main">
		<div class="qsa-landing-card">
			<div class="qsa-id-badge <?php echo null === $qsa_record ? 'not-found' : ''; ?>">
				<?php echo esc_html( $qsa_id ); ?>
			</div>

			<?php if ( null === $qsa_record ) : ?>
				<h1><?php esc_html_e( 'Product Not Found', 'qsa-engraving' ); ?></h1>
				<p><?php esc_html_e( 'This product identifier was not found in our system.', 'qsa-engraving' ); ?></p>
				<p><?php esc_html_e( 'Please verify the code and try again, or contact customer support for assistance.', 'qsa-engraving' ); ?></p>
			<?php else : ?>
				<h1><?php esc_html_e( 'Product Information', 'qsa-engraving' ); ?></h1>
				<p><?php esc_html_e( 'This LED module array has been verified as an authentic Quadica product.', 'qsa-engraving' ); ?></p>

				<div class="qsa-info-grid">
					<div class="qsa-info-item">
						<div class="qsa-info-label"><?php esc_html_e( 'Design', 'qsa-engraving' ); ?></div>
						<div class="qsa-info-value"><?php echo esc_html( $qsa_record['design'] ?? 'N/A' ); ?></div>
					</div>
					<div class="qsa-info-item">
						<div class="qsa-info-label"><?php esc_html_e( 'Sequence', 'qsa-engraving' ); ?></div>
						<div class="qsa-info-value">#<?php echo esc_html( number_format( (int) ( $qsa_record['sequence_number'] ?? 0 ) ) ); ?></div>
					</div>
					<div class="qsa-info-item">
						<div class="qsa-info-label"><?php esc_html_e( 'Batch ID', 'qsa-engraving' ); ?></div>
						<div class="qsa-info-value"><?php echo esc_html( $qsa_record['batch_id'] ?? 'N/A' ); ?></div>
					</div>
					<div class="qsa-info-item">
						<div class="qsa-info-label"><?php esc_html_e( 'Created', 'qsa-engraving' ); ?></div>
						<div class="qsa-info-value">
							<?php
							if ( ! empty( $qsa_record['created_at'] ) ) {
								echo esc_html( wp_date( 'M j, Y', strtotime( $qsa_record['created_at'] ) ) );
							} else {
								echo 'N/A';
							}
							?>
						</div>
					</div>
				</div>

				<p style="margin-top: 24px; font-size: 14px;">
					<?php esc_html_e( 'Additional product details coming soon.', 'qsa-engraving' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</main>

	<footer class="qsa-landing-footer">
		<p>
			&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
			<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
		</p>
	</footer>
</body>
</html>
		<?php
	}
}
