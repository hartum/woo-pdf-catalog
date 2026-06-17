<?php
/**
 * Plugin Name:       Woo PDF Catalog
 * Plugin URI:        https://github.com/example/woo-pdf-catalog
 * Description:       Generates a downloadable PDF product catalog from WooCommerce. Configure fields, filters, forced/excluded products and embed a download button with a shortcode.
 * Version:           1.0.3
 * Author:            Ivan
 * Text Domain:       woo-pdf-catalog
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Plugin constants ────────────────────────────────────────────────────────
define( 'WPC_VERSION',         '1.0.0' );
define( 'WPC_PLUGIN_FILE',     __FILE__ );
define( 'WPC_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'WPC_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'WPC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPC_TEXT_DOMAIN',     'woo-pdf-catalog' );

// ─── Activation / Deactivation hooks ─────────────────────────────────────────
// Must be registered before plugins_loaded so WordPress can fire them.
require_once WPC_PLUGIN_DIR . 'includes/class-wpc-activator.php';
register_activation_hook( __FILE__,   array( 'WPC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPC_Activator', 'deactivate' ) );

// ─── HPOS compatibility declaration ──────────────────────────────────────────
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

// ─── Bootstrap ───────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'wpc_init_plugin' );

/**
 * Bootstrap the Woo PDF Catalog plugin.
 *
 * Loads all required classes after WooCommerce is confirmed active, then
 * wires up every WordPress hook through WPC_Loader.
 *
 * @since 1.0.0
 */
function wpc_init_plugin() {

	// Bail if WooCommerce is not active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wpc_missing_woocommerce_notice' );
		return;
	}

	// ── i18n ────────────────────────────────────────────────────────────────
	load_plugin_textdomain(
		WPC_TEXT_DOMAIN,
		false,
		dirname( WPC_PLUGIN_BASENAME ) . '/languages/'
	);

	// ── Load core classes ────────────────────────────────────────────────────
	require_once WPC_PLUGIN_DIR . 'includes/class-wpc-loader.php';
	require_once WPC_PLUGIN_DIR . 'includes/class-wpc-settings.php';
	require_once WPC_PLUGIN_DIR . 'includes/class-wpc-product-query.php';
	require_once WPC_PLUGIN_DIR . 'includes/class-wpc-pdf-generator.php';
	require_once WPC_PLUGIN_DIR . 'includes/class-wpc-download-handler.php';

	// ── Load admin classes ───────────────────────────────────────────────────
	require_once WPC_PLUGIN_DIR . 'admin/class-wpc-admin.php';
	require_once WPC_PLUGIN_DIR . 'admin/class-wpc-settings-page.php';

	// ── Load frontend classes ────────────────────────────────────────────────
	require_once WPC_PLUGIN_DIR . 'frontend/class-wpc-shortcode.php';

	// NOTE: PDF classes (class-wpc-pdf-builder.php, class-wpc-pdf-template.php)
	// are NOT loaded here. They extend/use TCPDF and are lazy-loaded inside
	// WPC_PDF_Generator::generate() AFTER TCPDF itself is included.

	// ── Instantiate services ─────────────────────────────────────────────────
	$settings         = new WPC_Settings();
	$product_query    = new WPC_Product_Query( $settings );
	$pdf_generator    = new WPC_PDF_Generator( $settings, $product_query );
	$download_handler = new WPC_Download_Handler( $pdf_generator, $settings );
	$loader           = new WPC_Loader();

	// ── Admin hooks ──────────────────────────────────────────────────────────
	if ( is_admin() ) {
		$admin         = new WPC_Admin( $settings );
		$settings_page = new WPC_Settings_Page( $settings );

		$loader->add_filter( 'woocommerce_settings_tabs_array',          $admin,         'add_settings_tab',     50 );
		$loader->add_action( 'woocommerce_settings_wpc_pdf_catalog',     $settings_page, 'render' );
		$loader->add_action( 'woocommerce_settings_save_wpc_pdf_catalog', $admin,        'save_settings' );
		$loader->add_action( 'admin_enqueue_scripts',                     $admin,         'enqueue_assets' );
		$loader->add_action( 'wp_ajax_wpc_search_products',              $admin,         'ajax_search_products' );
		$loader->add_action( 'wp_ajax_wpc_import_csv_products',           $admin,         'ajax_import_csv_products' );
		$loader->add_action( 'woocommerce_product_options_pricing',      $admin,         'render_precio_catalogo_field' );
		$loader->add_action( 'woocommerce_process_product_meta',         $admin,         'save_precio_catalogo_field' );
		$loader->add_filter( 'woocommerce_product_export_product_default_columns',   $admin, 'register_export_column' );
		$loader->add_filter( 'woocommerce_product_export_row_data',                  $admin, 'populate_export_column',    10, 2 );
		$loader->add_filter( 'woocommerce_csv_product_import_mapping_options',       $admin, 'register_import_column' );
		$loader->add_filter( 'woocommerce_csv_product_import_mapping_default_columns', $admin, 'automap_import_column' );
		$loader->add_action( 'woocommerce_product_import_inserted_product_object',   $admin, 'process_import_column',     10, 2 );
	}

	// ── Download handler hooks ───────────────────────────────────────────────
	// admin-post.php handles both logged-in and logged-out POSTs cleanly.
	$loader->add_action( 'admin_post_wpc_download_pdf',        $download_handler, 'handle_admin_download' );
	$loader->add_action( 'admin_post_nopriv_wpc_download_pdf', $download_handler, 'handle_frontend_download' );

	// ── Frontend shortcode ───────────────────────────────────────────────────
	$shortcode = new WPC_Shortcode( $settings );
	$loader->add_action( 'wp_enqueue_scripts', $shortcode, 'enqueue_assets' );
	add_shortcode( 'woo_pdf_download', array( $shortcode, 'render' ) );

	$loader->run();
}

/**
 * Display an admin notice when WooCommerce is not active.
 *
 * @since 1.0.0
 */
function wpc_missing_woocommerce_notice() {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		sprintf(
			/* translators: %s: plugin name */
			esc_html__( 'Woo PDF Catalog requires %s to be installed and active.', 'woo-pdf-catalog' ),
			'<strong>WooCommerce</strong>'
		)
	);
}
