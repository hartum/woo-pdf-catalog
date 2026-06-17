<?php
/**
 * TCPDF subclass that configures the PDF document.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_PDF_Builder
 *
 * Extends TCPDF to add custom header/footer and helper methods.
 * All dimensions are in millimetres (A4 = 210 × 297 mm).
 */
class WPC_PDF_Builder extends TCPDF {

	/** @var array Plugin settings snapshot. */
	private $wpc_settings = array();

	/** @var string Shop name cached from get_bloginfo(). */
	private $shop_name = '';

	/** @var string Shop URL cached from home_url(). */
	private $shop_url = '';

	// ─── Construction & setup ────────────────────────────────────────────────

	/**
	 * Constructor. Configures the document with plugin settings.
	 *
	 * @param array $settings Plugin settings array from WPC_Settings::get_all().
	 */
	public function __construct( array $settings ) {
		$this->wpc_settings = $settings;
		$this->shop_name    = get_bloginfo( 'name' );
		$this->shop_url     = home_url();

		parent::__construct(
			'P',       // Portrait
			'mm',      // Millimetres
			'A4',      // A4 page
			true,      // Unicode
			'UTF-8',   // Encoding
			false      // Disk cache
		);

		// ── Document properties ──────────────────────────────────────────────
		$this->SetMargins(
			(int) $settings['pdf_margin_sides'],
			(int) $settings['pdf_margin_top'],
			(int) $settings['pdf_margin_sides']
		);

		// Reserve space for header/footer only if enabled.
		$header_enabled = ! empty( $settings['pdf_show_header'] );
		$footer_enabled = ! empty( $settings['pdf_show_footer'] );

		$this->setPrintHeader( $header_enabled );
		$this->setPrintFooter( $footer_enabled );

		if ( $header_enabled ) {
			$this->SetHeaderMargin( 8 );
		}
		if ( $footer_enabled ) {
			$this->SetFooterMargin( 10 );
		}

		$this->SetAutoPageBreak( false );

		// ── Font ─────────────────────────────────────────────────────────────
		$font = isset( $settings['pdf_font_family'] ) ? $settings['pdf_font_family'] : 'dejavusans';
		$this->SetFont( $font, '', 10 );

		// ── Colors ───────────────────────────────────────────────────────────
		list( $r, $g, $b ) = $this->hex_to_rgb( $settings['pdf_color_primary'] );
		$this->SetDrawColor( $r, $g, $b );
		$this->SetTextColor( 30, 30, 30 );
	}

	// ─── Header ──────────────────────────────────────────────────────────────

	/**
	 * {@inheritdoc}
	 */
	public function Header() {
		if ( empty( $this->wpc_settings['pdf_show_header'] ) ) {
			return;
		}

		list( $r, $g, $b ) = $this->hex_to_rgb( $this->wpc_settings['pdf_color_primary'] );

		// Background bar.
		$this->SetFillColor( $r, $g, $b );
		$this->Rect( 0, 0, $this->getPageWidth(), 14, 'F' );

		// Shop name.
		$this->SetTextColor( 255, 255, 255 );
		$this->SetFont( $this->wpc_settings['pdf_font_family'], 'B', 11 );
		$this->SetY( 3 );
		$this->SetX( $this->wpc_settings['pdf_margin_sides'] );
		$this->Cell( 0, 8, $this->shop_name, 0, 0, 'L' );

		// Page counter.
		$this->SetFont( $this->wpc_settings['pdf_font_family'], '', 9 );
		$this->SetX( -50 );
		$this->Cell( 40, 8, sprintf( '%d / %d', $this->getPage(), $this->getAliasNbPages() ), 0, 0, 'R' );

		// Reset colours.
		$this->SetTextColor( 30, 30, 30 );
		$this->SetFont( $this->wpc_settings['pdf_font_family'], '', 10 );
	}

	// ─── Footer ──────────────────────────────────────────────────────────────

	/**
	 * {@inheritdoc}
	 */
	public function Footer() {
		if ( empty( $this->wpc_settings['pdf_show_footer'] ) ) {
			return;
		}

		list( $r, $g, $b ) = $this->hex_to_rgb( $this->wpc_settings['pdf_color_secondary'] );

		$y = $this->getPageHeight() - 12;

		// Thin accent line.
		$this->SetDrawColor( $r, $g, $b );
		$this->SetLineWidth( 0.4 );
		$this->Line(
			$this->wpc_settings['pdf_margin_sides'],
			$y,
			$this->getPageWidth() - $this->wpc_settings['pdf_margin_sides'],
			$y
		);

		// URL on left, page number on right.
		$this->SetTextColor( 120, 120, 120 );
		$this->SetFont( $this->wpc_settings['pdf_font_family'], '', 8 );
		$this->SetY( $y + 2 );

		$this->SetX( -50 );
		$this->Cell( 40, 6, (string) $this->getPage(), 0, 0, 'R' );

		// Reset.
		$this->SetTextColor( 30, 30, 30 );
		$this->SetLineWidth( 0.2 );
	}

	// ─── Colour helpers ───────────────────────────────────────────────────────

	/**
	 * Convert a hex colour string to an [R, G, B] integer array.
	 *
	 * @param  string $hex Hex colour, e.g. '#1e3a5f'.
	 * @return int[]
	 */
	public function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Apply a primary colour (heading colour) to the current text.
	 */
	public function set_primary_color() {
		list( $r, $g, $b ) = $this->hex_to_rgb( $this->wpc_settings['pdf_color_primary'] );
		$this->SetTextColor( $r, $g, $b );
	}

	/**
	 * Apply a secondary / accent colour.
	 */
	public function set_secondary_color() {
		list( $r, $g, $b ) = $this->hex_to_rgb( $this->wpc_settings['pdf_color_secondary'] );
		$this->SetDrawColor( $r, $g, $b );
		$this->SetFillColor( $r, $g, $b );
	}

	/**
	 * Reset text to default dark colour.
	 */
	public function set_default_text_color() {
		$this->SetTextColor( 30, 30, 30 );
	}

	/**
	 * Expose wpc_settings to the template class.
	 *
	 * @return array
	 */
	public function get_wpc_settings() {
		return $this->wpc_settings;
	}
}
