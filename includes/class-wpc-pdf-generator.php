<?php
/**
 * PDF generation orchestrator.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_PDF_Generator
 *
 * Coordinates the full PDF generation pipeline:
 *  1. Load TCPDF vendor library.
 *  2. Query products via WPC_Product_Query.
 *  3. Delegate page rendering to WPC_PDF_Builder.
 *  4. Return binary string or force browser download.
 */
class WPC_PDF_Generator {

	/** @var WPC_Settings */
	private $settings;

	/** @var WPC_Product_Query */
	private $product_query;

	/**
	 * Constructor.
	 *
	 * @param WPC_Settings      $settings      Plugin settings.
	 * @param WPC_Product_Query $product_query Product query service.
	 */
	public function __construct( WPC_Settings $settings, WPC_Product_Query $product_query ) {
		$this->settings      = $settings;
		$this->product_query = $product_query;
	}

	// ─── TCPDF check ────────────────────────────────────────────────────────

	/**
	 * Ensure TCPDF is available, loading the bundled vendor copy if needed.
	 *
	 * @return bool True if TCPDF is available, false otherwise.
	 */
	public function is_tcpdf_available() {
		if ( class_exists( 'TCPDF' ) ) {
			return true;
		}

		$vendor_path = WPC_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';
		if ( file_exists( $vendor_path ) ) {
			require_once $vendor_path;
			return class_exists( 'TCPDF' );
		}

		return false;
	}

	// ─── Generate ────────────────────────────────────────────────────────────

	/**
	 * Generate the PDF and optionally force a browser download.
	 *
	 * @param bool        $force_download  True sends the file to the browser; false returns the binary string.
	 * @param array|null  $settings_override Optional settings array to override stored settings.
	 * @return string|void Binary PDF string when $force_download is false.
	 *
	 * @throws RuntimeException If TCPDF is not available.
	 */
	public function generate( $force_download = true, $settings_override = null ) {
		if ( ! $this->is_tcpdf_available() ) {
			throw new RuntimeException(
				esc_html__( 'TCPDF library not found. Please check the vendor/tcpdf directory inside the Woo PDF Catalog plugin.', 'woo-pdf-catalog' )
			);
		}

		// Ensure PDF builder and template classes are loaded.
		require_once WPC_PLUGIN_DIR . 'pdf/class-wpc-pdf-builder.php';
		require_once WPC_PLUGIN_DIR . 'pdf/class-wpc-pdf-template.php';

		$s        = $settings_override
			? wp_parse_args( $settings_override, $this->settings->get_all() )
			: $this->settings->get_all();
		$products = $this->product_query->get_products( $s );

		if ( empty( $products ) ) {
			throw new RuntimeException(
				esc_html__( 'No products found with the current settings. The PDF could not be generated.', 'woo-pdf-catalog' )
			);
		}

		// Build the PDF.
		$builder  = new WPC_PDF_Builder( $s );
		$template = new WPC_PDF_Template( $builder, $s );

		$builder->SetCreator( 'Woo PDF Catalog ' . WPC_VERSION );
		$builder->SetAuthor( get_bloginfo( 'name' ) );
		$builder->SetTitle( get_bloginfo( 'name' ) . ' – ' . __( 'Product Catalog', 'woo-pdf-catalog' ) );
		$builder->SetSubject( __( 'Product Catalog', 'woo-pdf-catalog' ) );

		$total = count( $products );
		foreach ( $products as $index => $product ) {
			$builder->AddPage();
			$template->render_product( $product, $index + 1, $total );
		}

		$filename = sanitize_file_name( $s['pdf_filename'] ) . '.pdf';

		if ( $force_download ) {
			// Clean any buffered output before sending binary.
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			// 'D' = force download, 'I' = inline browser display.
			$builder->Output( $filename, 'D' );
			exit;
		}

		// Return binary string for further processing.
		return $builder->Output( $filename, 'S' );
	}
}
