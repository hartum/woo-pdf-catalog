<?php
/**
 * Frontend shortcode [woo_pdf_download].
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Shortcode
 *
 * Registers [woo_pdf_download] and renders a styled download button.
 *
 * Attributes:
 *   - text  (string)  Button label. Default: "Download Product Catalog".
 *   - class (string)  Extra CSS class for the button wrapper.
 */
class WPC_Shortcode {

	/** @var WPC_Settings */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param WPC_Settings $settings Plugin settings.
	 */
	public function __construct( WPC_Settings $settings ) {
		$this->settings = $settings;
	}

	// ─── Assets ──────────────────────────────────────────────────────────────

	/**
	 * Enqueue frontend assets.
	 *
	 * Hooked to: wp_enqueue_scripts
	 */
	public function enqueue_assets() {
		// Only enqueue when the shortcode is actually present on the page.
		// We register always and enqueue lazily via the render method.
		wp_register_style(
			'wpc-frontend',
			WPC_PLUGIN_URL . 'frontend/assets/css/wpc-frontend.css',
			array(),
			WPC_VERSION
		);

		wp_register_script(
			'wpc-frontend',
			WPC_PLUGIN_URL . 'frontend/assets/js/wpc-frontend.js',
			array( 'jquery' ),
			WPC_VERSION,
			true
		);
	}

	// ─── Render shortcode ─────────────────────────────────────────────────────

	/**
	 * Render the download button shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts ) {
		// Enqueue assets now that we know the shortcode is used.
		wp_enqueue_style( 'wpc-frontend' );
		wp_enqueue_script( 'wpc-frontend' );

		$atts = shortcode_atts(
			array(
				'text'  => __( 'Download Product Catalog', 'woo-pdf-catalog' ),
				'class' => '',
			),
			$atts,
			'woo_pdf_download'
		);

		$url        = WPC_Download_Handler::get_frontend_download_url();
		$extra_class = ! empty( $atts['class'] ) ? ' ' . sanitize_html_class( $atts['class'] ) : '';

		ob_start();
		?>
		<div class="wpc-download-wrap<?php echo esc_attr( $extra_class ); ?>">
			<a
				href="<?php echo esc_url( $url ); ?>"
				class="wpc-btn wpc-btn--download"
				id="wpc-frontend-download-btn"
				rel="noopener noreferrer"
			>
				<svg class="wpc-btn__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
					<polyline points="7 10 12 15 17 10"/>
					<line x1="12" y1="15" x2="12" y2="3"/>
				</svg>
				<span class="wpc-btn__text"><?php echo esc_html( $atts['text'] ); ?></span>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}
}
