<?php
/**
 * Admin integration with WooCommerce Settings.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Admin
 *
 * Registers the WooCommerce settings tab, saves settings and provides
 * the AJAX product search endpoint for Select2.
 */
class WPC_Admin {

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

	// ─── WooCommerce Settings Tab ────────────────────────────────────────────

	/**
	 * Add "PDF Catalog" tab to the WooCommerce Settings navigation.
	 *
	 * Hooked to: woocommerce_settings_tabs_array
	 *
	 * @param  array $tabs Existing WooCommerce settings tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['wpc_pdf_catalog'] = __( 'PDF Catalog', 'woo-pdf-catalog' );
		return $tabs;
	}

	// ─── Save settings ───────────────────────────────────────────────────────

	/**
	 * Save settings submitted from the settings page form.
	 *
	 * Hooked to: woocommerce_settings_save_wpc_pdf_catalog
	 *
	 * @since 1.0.0
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'woo-pdf-catalog' ) );
		}

		check_admin_referer( 'woocommerce-settings' );

		// phpcs:ignore WordPress.Security.NonceVerification -- verified above via check_admin_referer.
		$this->settings->save_from_post( $_POST );

		WC_Admin_Settings::add_message( __( 'PDF Catalog settings saved.', 'woo-pdf-catalog' ) );
	}

	// ─── Assets ──────────────────────────────────────────────────────────────

	/**
	 * Enqueue admin CSS and JS only on the WooCommerce PDF Catalog tab.
	 *
	 * Hooked to: admin_enqueue_scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_GET['tab'] ) || 'wpc_pdf_catalog' !== sanitize_key( $_GET['tab'] ) ) {
			return;
		}

		// WooCommerce already loads Select2; we just need to initialise it.
		wp_enqueue_script( 'select2' );
		wp_enqueue_style( 'select2' );

		wp_enqueue_style(
			'wpc-admin',
			WPC_PLUGIN_URL . 'admin/assets/css/wpc-admin.css',
			array(),
			WPC_VERSION
		);

		wp_enqueue_script(
			'wpc-admin',
			WPC_PLUGIN_URL . 'admin/assets/js/wpc-admin.js',
			array( 'jquery', 'select2' ),
			WPC_VERSION,
			true
		);

		wp_localize_script(
			'wpc-admin',
			'wpcAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'searchNonce'    => wp_create_nonce( 'wpc_search_products' ),
				'csvImportNonce' => wp_create_nonce( 'wpc_import_csv_products' ),
				'downloadUrl'    => WPC_Download_Handler::get_admin_download_url(),
				'i18n'           => array(
					'searching'      => __( 'Searching…', 'woo-pdf-catalog' ),
					'noResults'      => __( 'No products found.', 'woo-pdf-catalog' ),
					'generating'     => __( 'Generating PDF…', 'woo-pdf-catalog' ),
					'downloadReady'  => __( 'Download PDF', 'woo-pdf-catalog' ),
					'errorOccurred'  => __( 'An error occurred. Please try again.', 'woo-pdf-catalog' ),
					'csvChoose'      => __( 'Choose CSV file…', 'woo-pdf-catalog' ),
					'csvImporting'   => __( 'Importing…', 'woo-pdf-catalog' ),
					'csvImported'    => __( '%d product(s) imported successfully.', 'woo-pdf-catalog' ),
					'csvNotFound'    => __( 'IDs not found: %s', 'woo-pdf-catalog' ),
					'csvNoIds'       => __( 'No valid product IDs found in the CSV.', 'woo-pdf-catalog' ),
					'csvError'       => __( 'Error importing CSV. Please try again.', 'woo-pdf-catalog' ),
				),
			)
		);
	}

	// ─── AJAX: product search ────────────────────────────────────────────────

	/**
	 * AJAX handler for Select2 product search.
	 *
	 * Hooked to: wp_ajax_wpc_search_products
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_products() {
		check_ajax_referer( 'wpc_search_products', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'woo-pdf-catalog' ) ), 403 );
		}

		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		require_once WPC_PLUGIN_DIR . 'includes/class-wpc-product-query.php';
		$query   = new WPC_Product_Query( $this->settings );
		$results = $query->search_products( $search );

		wp_send_json( array( 'results' => $results ) );
	}

	/**
	 * AJAX handler for CSV product ID import.
	 *
	 * Receives an array of product IDs, validates each one exists as a
	 * WooCommerce product, and returns found / not-found lists.
	 *
	 * Hooked to: wp_ajax_wpc_import_csv_products
	 *
	 * @since 1.1.0
	 */
	public function ajax_import_csv_products() {
		check_ajax_referer( 'wpc_import_csv_products', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'woo-pdf-catalog' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$raw_ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$raw_ids = array_unique( array_filter( $raw_ids ) );

		if ( empty( $raw_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid IDs found in the CSV file.', 'woo-pdf-catalog' ) ) );
		}

		$found     = array();
		$not_found = array();

		foreach ( $raw_ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$found[] = array(
					'id'   => (int) $id,
					'text' => sprintf( '#%d – %s', $id, $product->get_name() ),
				);
			} else {
				$not_found[] = (int) $id;
			}
		}

		wp_send_json_success( array(
			'found'     => $found,
			'not_found' => $not_found,
		) );
	}

	// ─── Custom Product Fields ───────────────────────────────────────────────

	/**
	 * Render the "Precio Catálogo" field in the product data meta box (General tab).
	 *
	 * Hooked to: woocommerce_product_options_pricing
	 *
	 * @since 1.0.0
	 */
	public function render_precio_catalogo_field() {
		woocommerce_wp_text_input(
			array(
				'id'                => 'precio_catalogo',
				'label'             => __( 'Precio Catálogo (€)', 'woo-pdf-catalog' ),
				'placeholder'       => __( 'Precio para negocios', 'woo-pdf-catalog' ),
				'desc_tip'          => 'true',
				'description'       => __( 'Este precio aparecerá en el catálogo digital PDF', 'woo-pdf-catalog' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '0',
				),
			)
		);
	}

	/**
	 * Save the "Precio Catálogo" field when the product is saved.
	 *
	 * Hooked to: woocommerce_process_product_meta
	 *
	 * @param int $post_id The product ID.
	 * @since 1.0.0
	 */
	public function save_precio_catalogo_field( $post_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['precio_catalogo'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$precio = sanitize_text_field( wp_unslash( $_POST['precio_catalogo'] ) );
			update_post_meta( $post_id, 'precio_catalogo', $precio );
		}
	}

	/**
	 * Register "Precio Catálogo" as a selectable column in WooCommerce's product CSV exporter.
	 *
	 * Hooked to: woocommerce_product_export_product_default_columns
	 *
	 * @param  array $columns Existing export columns.
	 * @return array
	 * @since 1.0.0
	 */
	public function register_export_column( $columns ) {
		$columns['precio_catalogo'] = __( 'Precio Catálogo', 'woo-pdf-catalog' );
		return $columns;
	}

	/**
	 * Supply the "Precio Catálogo" value for each product row in the CSV export.
	 *
	 * Hooked to: woocommerce_product_export_row_data
	 *
	 * @param  array      $row     Row data being built for the CSV.
	 * @param  WC_Product $product Current product object.
	 * @return array
	 * @since 1.0.0
	 */
	public function populate_export_column( $row, $product ) {
		$row['precio_catalogo'] = get_post_meta( $product->get_id(), 'precio_catalogo', true );
		return $row;
	}

	// ─── CSV Import support ──────────────────────────────────────────────────

	/**
	 * Register "Precio Catálogo" as a mappable field in WooCommerce's product CSV importer.
	 *
	 * Hooked to: woocommerce_csv_product_import_mapping_options
	 *
	 * @param  array $options Existing mapping options.
	 * @return array
	 * @since 1.0.0
	 */
	public function register_import_column( $options ) {
		$options['precio_catalogo'] = __( 'Precio Catálogo', 'woo-pdf-catalog' );
		return $options;
	}

	/**
	 * Auto-map the CSV column header to our field so the user does not have
	 * to configure the mapping manually every time.
	 *
	 * Hooked to: woocommerce_csv_product_import_mapping_default_columns
	 *
	 * @param  array $columns Default column map [ header => field_id ].
	 * @return array
	 * @since 1.0.0
	 */
	public function automap_import_column( $columns ) {
		// Map both the translated label and the raw key so it works regardless
		// of whether the CSV was generated by our exporter or filled manually.
		$columns[ __( 'Precio Catálogo', 'woo-pdf-catalog' ) ] = 'precio_catalogo';
		$columns['precio_catalogo']                               = 'precio_catalogo';
		return $columns;
	}

	/**
	 * Save the "Precio Catálogo" value after a product is inserted or updated
	 * via the CSV importer.
	 *
	 * Hooked to: woocommerce_product_import_inserted_product_object
	 *
	 * @param WC_Product $product The product that was just saved.
	 * @param array      $data    Parsed row data from the CSV.
	 * @since 1.0.0
	 */
	public function process_import_column( $product, $data ) {
		if ( isset( $data['precio_catalogo'] ) && '' !== (string) $data['precio_catalogo'] ) {
			update_post_meta(
				$product->get_id(),
				'precio_catalogo',
				sanitize_text_field( $data['precio_catalogo'] )
			);
		}
	}
}
