<?php
/**
 * Handles all PDF download requests (admin and frontend).
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Download_Handler
 *
 * Routes download requests coming from:
 *   - Admin: admin-post.php?action=wpc_download_pdf  (requires manage_woocommerce)
 *   - Frontend shortcode button: same admin-post.php endpoint but verified with
 *     a public nonce (capability check relaxed to any logged-in user or public).
 */
class WPC_Download_Handler {

	/** @var WPC_PDF_Generator */
	private $generator;

	/** @var WPC_Settings */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param WPC_PDF_Generator $generator PDF generation service.
	 * @param WPC_Settings      $settings  Plugin settings.
	 */
	public function __construct( WPC_PDF_Generator $generator, WPC_Settings $settings ) {
		$this->generator = $generator;
		$this->settings  = $settings;
	}

	// ─── Admin download ──────────────────────────────────────────────────────

	/**
	 * Handle download triggered from the WooCommerce admin settings page.
	 *
	 * Hooked to: admin_post_wpc_download_pdf
	 *
	 * @since 1.0.0
	 */
	public function handle_admin_download() {
		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpc_admin_download' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'woo-pdf-catalog' ), 403 );
		}

		// Verify capability.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to download the PDF catalog.', 'woo-pdf-catalog' ), 403 );
		}

		$this->stream_pdf();
	}

	// ─── Frontend download ───────────────────────────────────────────────────

	/**
	 * Handle download triggered from the frontend shortcode button.
	 *
	 * Hooked to: admin_post_nopriv_wpc_download_pdf (non-logged-in users)
	 * Also serves logged-in non-admin users via admin_post_wpc_download_pdf
	 * when the referrer is the frontend.
	 *
	 * @since 1.0.0
	 */
	public function handle_frontend_download() {
		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpc_frontend_download' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'woo-pdf-catalog' ), 403 );
		}

		// Frontend always generates with published products only.
		$this->stream_pdf( array( 'publication_status' => 'publish' ) );
	}

	// ─── Shared streaming ────────────────────────────────────────────────────

	/**
	 * Stream the generated PDF to the browser.
	 *
	 * @param array|null $settings_override Optional partial settings override.
	 */
	private function stream_pdf( $settings_override = null ) {
		try {
			$this->generator->generate( true, $settings_override );
		} catch ( RuntimeException $e ) {
			wp_die(
				esc_html( $e->getMessage() ),
				esc_html__( 'PDF Generation Error', 'woo-pdf-catalog' ),
				array( 'back_link' => true )
			);
		}
	}

	// ─── URL helpers ─────────────────────────────────────────────────────────

	/**
	 * Build the admin download URL (for the settings page button).
	 *
	 * @return string Escaped URL.
	 */
	public static function get_admin_download_url() {
		return esc_url(
			wp_nonce_url(
				admin_url( 'admin-post.php?action=wpc_download_pdf' ),
				'wpc_admin_download'
			)
		);
	}

	/**
	 * Build the frontend download URL (for the shortcode button).
	 *
	 * @return string Escaped URL.
	 */
	public static function get_frontend_download_url() {
		return esc_url(
			wp_nonce_url(
				admin_url( 'admin-post.php?action=wpc_download_pdf' ),
				'wpc_frontend_download'
			)
		);
	}
}
