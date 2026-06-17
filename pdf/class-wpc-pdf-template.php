<?php
/**
 * Renders one product per PDF page.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class WPC_PDF_Template
 *
 * Handles all drawing commands for a single product page:
 *   ┌───────────────────────────────────────────┐
 *   │  [HEADER]                       Page X/Y  │
 *   ├──────────────┬────────────────────────────┤
 *   │              │  TITLE                      │
 *   │   IMAGE      │  ───────────────────────    │
 *   │  (left col)  │  field: value               │
 *   │              │  …                          │
 *   │              │  ───────────────────────    │
 *   │              │  Short description          │
 *   │              │  ───────────────────────    │
 *   │              │  Long description           │
 *   ├──────────────┴────────────────────────────┤
 *   │  [FOOTER]                                  │
 *   └───────────────────────────────────────────┘
 *
 * A4 = 210 × 297 mm. Left column: 72 mm. Right column: rest.
 */
class WPC_PDF_Template
{

	/** @var WPC_PDF_Builder */
	private $pdf;

	/** @var array Plugin settings. */
	private $s;

	/** @var string TCPDF font name. */
	private $font;

	// Content area dimensions (computed in constructor).
	private $page_w;
	private $page_h;
	private $margin_l;
	private $margin_r;
	private $margin_t;
	private $content_top;    // Y after header
	private $content_bottom; // Y before footer
	private $content_h;      // usable height
	private $col_img_w = 70; // left column width in mm
	private $col_gap = 6;  // gap between columns
	private $col_txt_x;       // x-start of right column
	private $col_txt_w;       // width of right column

	/**
	 * Constructor.
	 *
	 * @param WPC_PDF_Builder $pdf      The builder instance.
	 * @param array           $settings Plugin settings.
	 */
	public function __construct(WPC_PDF_Builder $pdf, array $settings)
	{
		$this->pdf = $pdf;
		$this->s = $settings;
		$this->font = isset($settings['pdf_font_family']) ? $settings['pdf_font_family'] : 'dejavusans';

		// A4 dimensions.
		$this->page_w = 210;
		$this->page_h = 297;
		$this->margin_l = (int) $settings['pdf_margin_sides'];
		$this->margin_r = (int) $settings['pdf_margin_sides'];
		$this->margin_t = (int) $settings['pdf_margin_top'];

		// Adjust content top/bottom for header and footer bars.
		$header_h = !empty($settings['pdf_show_header']) ? 18 : $this->margin_t;
		$footer_h = !empty($settings['pdf_show_footer']) ? 16 : 10;
		$this->content_top = $header_h + 4;
		$this->content_bottom = $this->page_h - $footer_h - 4;
		$this->content_h = $this->content_bottom - $this->content_top;

		// Right column X and width.
		$this->col_txt_x = $this->margin_l + $this->col_img_w + $this->col_gap;
		$this->col_txt_w = $this->page_w - $this->col_txt_x - $this->margin_r;
	}

	// ─── Public entry point ───────────────────────────────────────────────────

	/**
	 * Render a full product page.
	 *
	 * @param WC_Product $product    WooCommerce product object.
	 * @param int        $page_num   Current page number (1-based).
	 * @param int        $total      Total number of pages.
	 */
	public function render_product(WC_Product $product, $page_num, $total)
	{
		$this->render_image($product);
		$this->render_right_column($product);
	}

	// ─── Left column: image ───────────────────────────────────────────────────

	/**
	 * Render the product image in the left column.
	 *
	 * @param WC_Product $product Product object.
	 */
	private function render_image(WC_Product $product)
	{
		$x = $this->margin_l;
		$y = $this->content_top;
		$width = $this->col_img_w;
		// Allow the image bounding box to be much taller so tall bottles can scale up
		$height = min($this->content_h, $this->col_img_w * 2.5);

		$image_id = $product->get_image_id();
		$image_path = $image_id ? get_attached_file($image_id) : '';

		if ($image_path && file_exists($image_path)) {
			// Fit image within the box preserving aspect ratio.
			$this->pdf->Image(
				$image_path,
				$x,
				$y,
				$width,
				$height,
				'',   // auto-detect type
				'',   // link
				'',   // align
				true, // resize
				300,  // dpi
				'',   // palign
				false,
				false,
				0,    // border (changed from 1 to 0 to remove the blue frame)
				'CM'  // fit
			);
		} else {
			// Placeholder rectangle with label.
			$placeholder_h = min($this->content_h, $this->col_img_w); // Keep placeholder square-ish
			list($r, $g, $b) = $this->pdf->hex_to_rgb($this->s['pdf_color_secondary']);
			$this->pdf->SetFillColor($r, $g, $b);
			$this->pdf->RoundedRect($x, $y, $width, $placeholder_h, 3, '1111', 'F');

			$this->pdf->SetTextColor(255, 255, 255);
			$this->pdf->SetFont($this->font, 'I', 9);
			$this->pdf->SetXY($x, $y + $placeholder_h / 2 - 4);
			$this->pdf->Cell($width, 8, __('No Image', 'woo-pdf-catalog'), 0, 0, 'C');
			$this->pdf->set_default_text_color();
		}
	}

	// ─── Right column ─────────────────────────────────────────────────────────

	/**
	 * Render title, fields, short description, long description in the right column.
	 *
	 * @param WC_Product $product Product object.
	 */
	private function render_right_column(WC_Product $product)
	{
		$x = $this->col_txt_x;
		$y = $this->content_top;
		$w = $this->col_txt_w;

		// ── Title ─────────────────────────────────────────────────────────────
		$this->pdf->set_primary_color();
		$this->pdf->SetFont($this->font, 'B', 16);
		$this->pdf->SetXY($x, $y);
		$this->pdf->MultiCell($w, 0, $product->get_name(), 0, 'L', false, 1);
		$y = $this->pdf->GetY() + 3;

		// ── Divider ───────────────────────────────────────────────────────────
		$y = $this->render_divider($x, $y, $w);

		// ── Fields ────────────────────────────────────────────────────────────
		$enabled = (array) $this->s['enabled_fields'];
		$y = $this->render_fields($product, $x, $y, $w, $enabled);

		// ── Short description ─────────────────────────────────────────────────
		if (in_array('short_description', $enabled, true)) {
			$short = $product->get_short_description();
			$short = $short ? wp_strip_all_tags($short) : '';
			if ($short) {
				$y = $this->render_divider($x, $y, $w);
				$this->pdf->set_primary_color();
				$this->pdf->SetFont($this->font, 'B', 10);
				$this->pdf->SetXY($x, $y);
				$this->pdf->Cell($w, 6, __('Description', 'woo-pdf-catalog'), 0, 1, 'L');
				$y = $this->pdf->GetY();
				$this->pdf->set_default_text_color();
				$this->pdf->SetFont($this->font, '', 9);
				$this->pdf->SetXY($x, $y);
				$this->pdf->MultiCell($w, 0, $short, 0, 'L', false, 1);
				$y = $this->pdf->GetY() + 3;
			}
		}

		// ── Long description ──────────────────────────────────────────────────
		if (in_array('description', $enabled, true)) {
			$long = $product->get_description();
			$long = $long ? wp_strip_all_tags($long) : '';
			if ($long) {
				// Remaining space check: if we are past 80% of the page, truncate.
				$max_y = $this->content_bottom - 4;
				$remaining = $max_y - $y;

				if ($remaining > 10) {
					$y = $this->render_divider($x, $y, $w);
					$this->pdf->set_primary_color();
					$this->pdf->SetFont($this->font, 'B', 10);
					$this->pdf->SetXY($x, $y);
					$this->pdf->Cell($w, 6, __('Full Description', 'woo-pdf-catalog'), 0, 1, 'L');
					$y = $this->pdf->GetY();
					$this->pdf->set_default_text_color();
					$this->pdf->SetFont($this->font, '', 8.5);
					$this->pdf->SetXY($x, $y);
					$this->pdf->MultiCell($w, 0, $long, 0, 'L', false, 1, $x, $y, true, 0, false, true, $remaining - 10);
				}
			}
		}
	}

	// ─── Field rows ──────────────────────────────────────────────────────────

	/**
	 * Render the enabled data fields as a two-column table (label | value).
	 *
	 * @param WC_Product $product Product object.
	 * @param float      $x       Left X position.
	 * @param float      $y       Current Y position.
	 * @param float      $w       Available width.
	 * @param array      $enabled Enabled field IDs.
	 * @return float Updated Y position.
	 */
	private function render_fields(WC_Product $product, $x, $y, $w, $enabled)
	{
		$label_w = 44;
		$value_w = $w - $label_w;
		$rows = $this->collect_field_rows($product, $enabled);

		if (empty($rows)) {
			return $y;
		}

		foreach ($rows as $row) {
			if ($y > $this->content_bottom - 8) {
				break; // overflow guard
			}

			// Label.
			$this->pdf->SetFont($this->font, 'B', 8.5);
			$this->pdf->set_primary_color();
			$this->pdf->SetXY($x, $y);
			$this->pdf->Cell($label_w, 6, $row['label'] . ':', 0, 0, 'L');

			// Value.
			$this->pdf->SetFont($this->font, '', 8.5);
			$this->pdf->set_default_text_color();
			$this->pdf->SetXY($x + $label_w, $y);
			$this->pdf->MultiCell($value_w, 6, $row['value'], 0, 'L', false, 1);

			$y = $this->pdf->GetY();
		}

		return $y + 2;
	}

	/**
	 * Collect field label/value pairs for the enabled fields.
	 *
	 * @param WC_Product $product Product object.
	 * @param array      $enabled Enabled field IDs.
	 * @return array  Array of [ 'label' => string, 'value' => string ]
	 */
	private function collect_field_rows(WC_Product $product, $enabled)
	{
		$rows = array();

		// SKU
		if (in_array('sku', $enabled, true) && $product->get_sku()) {
			$rows[] = array('label' => __('SKU', 'woo-pdf-catalog'), 'value' => $product->get_sku());
		}

		// Price
		if (in_array('price', $enabled, true)) {
			$price = html_entity_decode( strip_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' );
			if ($price) {
				$rows[] = array('label' => __('Price', 'woo-pdf-catalog'), 'value' => $price);
			}
		}

		// Regular price
		if (in_array('regular_price', $enabled, true) && $product->get_regular_price()) {
			$rows[] = array('label' => __('Regular Price', 'woo-pdf-catalog'), 'value' => html_entity_decode( strip_tags( wc_price( $product->get_regular_price() ) ), ENT_QUOTES, 'UTF-8' ));
		}

		// Sale price
		if (in_array('sale_price', $enabled, true) && $product->get_sale_price()) {
			$rows[] = array('label' => __('Sale Price', 'woo-pdf-catalog'), 'value' => html_entity_decode( strip_tags( wc_price( $product->get_sale_price() ) ), ENT_QUOTES, 'UTF-8' ));
		}

		// Precio Catálogo
		if (in_array('precio_catalogo', $enabled, true)) {
			$precio_cat = trim( get_post_meta($product->get_id(), 'precio_catalogo', true) );
			if ('' !== $precio_cat) {
				// Normalise: comma decimal → period, strip any stray currency symbols/spaces.
				$normalised = str_replace( ',', '.', $precio_cat );
				$normalised = preg_replace( '/[^\d.]/', '', $normalised );
				if ( is_numeric( $normalised ) && (float) $normalised > 0 ) {
					$formatted = number_format( (float) $normalised, 2, ',', '.' ) . '€ + IVA';
					$rows[] = array('label' => __('Precio Catálogo', 'woo-pdf-catalog'), 'value' => $formatted);
				} else {
					$rows[] = array('label' => __('Precio Catálogo', 'woo-pdf-catalog'), 'value' => $precio_cat);
				}
			}
		}

		// Stock status
		if (in_array('stock_status', $enabled, true)) {
			$status_labels = array(
				'instock' => __('In Stock', 'woo-pdf-catalog'),
				'outofstock' => __('Out of Stock', 'woo-pdf-catalog'),
				'onbackorder' => __('On Backorder', 'woo-pdf-catalog'),
			);
			$status = $product->get_stock_status();
			$rows[] = array('label' => __('Stock', 'woo-pdf-catalog'), 'value' => isset($status_labels[$status]) ? $status_labels[$status] : $status);
		}

		// Stock quantity
		if (in_array('stock_quantity', $enabled, true) && $product->managing_stock()) {
			$rows[] = array('label' => __('Qty', 'woo-pdf-catalog'), 'value' => (string) $product->get_stock_quantity());
		}

		// Weight
		if (in_array('weight', $enabled, true) && $product->get_weight()) {
			$rows[] = array('label' => __('Weight', 'woo-pdf-catalog'), 'value' => $product->get_weight() . ' ' . get_option('woocommerce_weight_unit', 'kg'));
		}

		// Dimensions
		if (in_array('dimensions', $enabled, true)) {
			$dims = wc_format_dimensions($product->get_dimensions(false));
			if ($dims && '-' !== $dims) {
				$rows[] = array('label' => __('Dimensions', 'woo-pdf-catalog'), 'value' => $dims . ' ' . get_option('woocommerce_dimension_unit', 'cm'));
			}
		}

		// Categories
		if (in_array('categories', $enabled, true)) {
			$cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
			if (!is_wp_error($cats) && !empty($cats)) {
				$rows[] = array('label' => __('Categories', 'woo-pdf-catalog'), 'value' => implode(', ', $cats));
			}
		}

		// Tags
		if (in_array('tags', $enabled, true)) {
			$tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
			if (!is_wp_error($tags) && !empty($tags)) {
				$rows[] = array('label' => __('Tags', 'woo-pdf-catalog'), 'value' => implode(', ', $tags));
			}
		}

		// Attributes
		if (in_array('attributes', $enabled, true)) {
			foreach ($product->get_attributes() as $attr) {
				/** @var WC_Product_Attribute $attr */
				$label = wc_attribute_label($attr->get_name());
				if ($attr->is_taxonomy()) {
					$terms = wc_get_product_terms($product->get_id(), $attr->get_name(), array('fields' => 'names'));
					$value = implode(', ', $terms);
				} else {
					$value = implode(', ', $attr->get_options());
				}
				if ($value) {
					$rows[] = array('label' => $label, 'value' => $value);
				}
			}
		}

		// Custom fields / ACF fields — filtered by enabled_custom_fields setting.
		if (in_array('custom_fields', $enabled, true)) {
			$allowed_keys = isset($this->s['enabled_custom_fields'])
				? (array) $this->s['enabled_custom_fields']
				: array();

			$all_meta = get_post_meta($product->get_id());

			foreach ($all_meta as $key => $values) {
				// Skip internal keys.
				if ('' === $key || '_' === $key[0]) {
					continue;
				}

				// If the admin has chosen specific keys, skip any not in the list.
				// An empty $allowed_keys means "not yet configured" — show all.
				if (!empty($allowed_keys) && !in_array($key, $allowed_keys, true)) {
					continue;
				}

				// The raw stored value is always wrapped in an array by get_post_meta().
				$raw = isset($values[0]) ? $values[0] : '';
				$val = maybe_unserialize($raw);

				if (is_array($val)) {
					$val = implode(', ', array_map('strval', $val));
				} else {
					$val = (string) $val;
				}

				if ('' === $val) {
					continue;
				}

				// Resolve ACF label if available.
				$label = $key;
				if (function_exists('get_field_object')) {
					$field_obj = get_field_object($key, $product->get_id());
					if (!empty($field_obj['label'])) {
						$label = $field_obj['label'];
					}
				}

				$rows[] = array(
					'label' => esc_html($label),
					'value' => wp_strip_all_tags($val),
				);
			}
		}

		return $rows;
	}

	// ─── Divider ─────────────────────────────────────────────────────────────

	/**
	 * Draw a thin horizontal divider line.
	 *
	 * @param float $x Left X.
	 * @param float $y Y position.
	 * @param float $w Width.
	 * @return float Y position after divider (with spacing).
	 */
	private function render_divider($x, $y, $w)
	{
		list($r, $g, $b) = $this->pdf->hex_to_rgb($this->s['pdf_color_secondary']);
		$this->pdf->SetDrawColor($r, $g, $b);
		$this->pdf->SetLineWidth(0.3);
		$this->pdf->Line($x, $y, $x + $w, $y);
		$this->pdf->SetLineWidth(0.2);
		return $y + 4;
	}
}
