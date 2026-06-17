=== Woo PDF Catalog ===
Contributors: Ivan
Tags: woocommerce, pdf, catalog, products, export
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate a downloadable PDF catalog with all your WooCommerce products.

== Description ==

**Woo PDF Catalog** generates a professional, fully-configured PDF catalog
directly from your WooCommerce product database. Features include:

* **Field selector** – choose which product fields appear in the PDF.
* **Status filter** – include only published products or all statuses.
* **Forced products** – products that always appear regardless of other filters.
* **Excluded products** – products that never appear.
* **Shortcode** – insert `[woo_pdf_download]` on any page/post.
* **Admin download button** – download directly from the settings page.
* **Custom styles** – configure colors, fonts and margins.
* **Multilingual** – fully translated in English and Spanish (es_ES).

== Installation ==

1. Upload the `woo-pdf-catalog` folder to `/wp-content/plugins/`.
2. Install TCPDF in `vendor/tcpdf/` (see FAQ).
3. Activate the plugin through the **Plugins** screen in WordPress.
4. Go to **WooCommerce > Settings > PDF Catalog** to configure.

== Frequently Asked Questions ==

= How do I install TCPDF? =

Run the following command inside the plugin directory:

  cd wp-content/plugins/woo-pdf-catalog
  curl -L https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.7.7.tar.gz | tar xz
  mv TCPDF-6.7.7 vendor/tcpdf

= Can visitors download the catalog? =

Yes. Place `[woo_pdf_download]` on any public page. Visitor downloads always
use published products only.

= How do I use the shortcode? =

  [woo_pdf_download]
  [woo_pdf_download text="Get our catalog" class="my-button"]

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
