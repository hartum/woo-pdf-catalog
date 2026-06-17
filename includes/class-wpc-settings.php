<?php
/**
 * Settings manager for Woo PDF Catalog.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Settings
 *
 * Single access point for all plugin options.
 * Data is stored as a serialised array under the 'wpc_settings' option key.
 */
class WPC_Settings {

	/** @var string WordPress option key. */
	const OPTION_KEY = 'wpc_settings';

	/** @var array Cached settings array. */
	private $settings = null;

	// ─── Available fields catalogue ──────────────────────────────────────────

	/**
	 * Return the full list of product fields that can appear in the PDF.
	 *
	 * Each entry: [ 'id' => string, 'label' => translatable string, 'default' => bool ]
	 *
	 * @return array
	 */
	public function get_available_fields() {
		return array(
			array( 'id' => 'sku',              'label' => __( 'SKU / Reference',    'woo-pdf-catalog' ), 'default' => true  ),
			array( 'id' => 'price',            'label' => __( 'Price',              'woo-pdf-catalog' ), 'default' => true  ),
			array( 'id' => 'regular_price',    'label' => __( 'Regular Price',      'woo-pdf-catalog' ), 'default' => false ),
			array( 'id' => 'sale_price',       'label' => __( 'Sale Price',         'woo-pdf-catalog' ), 'default' => false ),
			array( 'id' => 'precio_catalogo',  'label' => __( 'Precio Catálogo',    'woo-pdf-catalog' ), 'default' => true  ),
			array( 'id' => 'stock_status',     'label' => __( 'Stock Status',       'woo-pdf-catalog' ), 'default' => true  ),
			array( 'id' => 'stock_quantity',   'label' => __( 'Stock Quantity',     'woo-pdf-catalog' ), 'default' => false ),
			array( 'id' => 'weight',           'label' => __( 'Weight',             'woo-pdf-catalog' ), 'default' => true  ),
			array( 'id' => 'dimensions',       'label' => __( 'Dimensions',         'woo-pdf-catalog' ), 'default' => true  ),
			array( 'id' => 'categories',       'label' => __( 'Categories',         'woo-pdf-catalog' ), 'default' => true  ),
			array( 'id' => 'tags',             'label' => __( 'Tags',               'woo-pdf-catalog' ), 'default' => false ),
			array( 'id' => 'attributes',       'label' => __( 'Attributes',         'woo-pdf-catalog' ), 'default' => true  ),
			array( 'id' => 'custom_fields',    'label' => __( 'Custom Fields (meta)','woo-pdf-catalog' ), 'default' => false ),
			array( 'id' => 'short_description','label' => __( 'Short Description',  'woo-pdf-catalog' ), 'default' => true  ),
			array( 'id' => 'description',      'label' => __( 'Long Description',   'woo-pdf-catalog' ), 'default' => true  ),
		);
	}

	// ─── Default values ──────────────────────────────────────────────────────

	/**
	 * Return default settings.
	 *
	 * @return array
	 */
	public function get_defaults() {
		$default_fields = array();
		foreach ( $this->get_available_fields() as $field ) {
			if ( $field['default'] ) {
				$default_fields[] = $field['id'];
			}
		}

		return array(
			'enabled_fields'         => $default_fields,
			'enabled_custom_fields'  => array(), // empty = all keys selected by default on first run
			'publication_status'     => 'publish',
			'sort_by'                => 'menu_order',
			'sort_tag_prefix'        => 'D.O.',
			'sort_tag_order'         => array(), // user-defined ordering of tag values
			'forced_products'        => array(),
			'excluded_products'      => array(),
			'pdf_color_primary'      => '#1e3a5f',
			'pdf_color_secondary'    => '#4a90d9',
			'pdf_font_family'        => 'dejavusans',
			'pdf_margin_top'         => 20,
			'pdf_margin_sides'       => 15,
			'pdf_show_header'        => true,
			'pdf_show_footer'        => true,
			'pdf_filename'           => 'catalog',
		);
	}

	// ─── Accessors ───────────────────────────────────────────────────────────

	/**
	 * Load settings from DB (cached after first call).
	 *
	 * @return array
	 */
	private function load() {
		if ( null === $this->settings ) {
			$saved          = get_option( self::OPTION_KEY, array() );
			$this->settings = wp_parse_args( $saved, $this->get_defaults() );
		}
		return $this->settings;
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function get_all() {
		return $this->load();
	}

	/**
	 * Get a single setting by key.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Fallback if key is not found.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$all = $this->load();
		return isset( $all[ $key ] ) ? $all[ $key ] : ( null !== $default ? $default : ( isset( $this->get_defaults()[ $key ] ) ? $this->get_defaults()[ $key ] : null ) );
	}

	// ─── Persistence ─────────────────────────────────────────────────────────

	/**
	 * Return all public meta keys available for products.
	 *
	 * Sources (merged and deduplicated):
	 *  1. ACF field group definitions — includes fields even if they have no
	 *     saved value yet in any product.
	 *  2. wp_postmeta — catches any non-ACF or legacy custom fields that DO
	 *     have values in the database.
	 *
	 * @return array[] Array of [ 'key' => string, 'label' => string ] sorted by label.
	 */
	public function get_public_meta_keys() {
		global $wpdb;

		// ── 1. ACF field group definitions ───────────────────────────────────
		// These exist regardless of whether any product has a value saved.
		$acf_labels = array(); // key => label
		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			$groups = acf_get_field_groups();
			foreach ( $groups as $group ) {
				$fields = acf_get_fields( $group['key'] );
				if ( is_array( $fields ) ) {
					foreach ( $fields as $field ) {
						if ( ! empty( $field['name'] ) ) {
							$acf_labels[ $field['name'] ] = isset( $field['label'] ) ? $field['label'] : $field['name'];
						}
					}
				}
			}
		}

		// ── 2. wp_postmeta — keys that actually have values in the DB ─────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$db_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_type = %s
				   AND pm.meta_key NOT LIKE %s
				   AND pm.meta_key != ''
				 ORDER BY pm.meta_key ASC",
				'product',
				'\_%'
			)
		);

		// ── 3. Merge: start from ACF fields, add any DB-only extras ──────────
		$merged = $acf_labels; // key => label

		if ( is_array( $db_keys ) ) {
			foreach ( $db_keys as $key ) {
				if ( ! isset( $merged[ $key ] ) ) {
					// Not an ACF field — use the raw key as label.
					$merged[ $key ] = $key;
				}
			}
		}

		if ( empty( $merged ) ) {
			return array();
		}

		// ── 4. Build output array and sort by label ───────────────────────────
		$result = array();
		foreach ( $merged as $key => $label ) {
			$result[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}

		usort( $result, function ( $a, $b ) {
			return strcasecmp( $a['label'], $b['label'] );
		} );

		return $result;
	}


	/**
	 * Sanitise and persist settings from $_POST.
	 *
	 * Call this inside a properly nonce-verified context.
	 *
	 * @param array $raw Raw POST data to sanitise and save.
	 * @return bool True on update, false if value unchanged.
	 */
	public function save_from_post( array $raw ) {
		$defaults = $this->get_defaults();
		$clean    = array();

		// Multiselect fields (arrays of IDs / field names).
		$clean['enabled_fields']        = isset( $raw['enabled_fields'] ) && is_array( $raw['enabled_fields'] )
			? array_map( 'sanitize_key', $raw['enabled_fields'] )
			: array();

		$clean['enabled_custom_fields'] = isset( $raw['enabled_custom_fields'] ) && is_array( $raw['enabled_custom_fields'] )
			? array_map( 'sanitize_text_field', $raw['enabled_custom_fields'] )
			: array();

		$clean['forced_products']       = isset( $raw['forced_products'] ) && is_array( $raw['forced_products'] )
			? array_map( 'absint', $raw['forced_products'] )
			: array();

		$clean['excluded_products']     = isset( $raw['excluded_products'] ) && is_array( $raw['excluded_products'] )
			? array_map( 'absint', $raw['excluded_products'] )
			: array();

		// Radio / select.
		$clean['publication_status'] = ( isset( $raw['publication_status'] ) && 'any' === $raw['publication_status'] ) ? 'any' : 'publish';

		// Sort options.
		$valid_sort = array( 'menu_order', 'title', 'date', 'tag' );
		$clean['sort_by'] = ( isset( $raw['sort_by'] ) && in_array( $raw['sort_by'], $valid_sort, true ) )
			? $raw['sort_by']
			: 'menu_order';
		$clean['sort_tag_prefix'] = isset( $raw['sort_tag_prefix'] )
			? sanitize_text_field( $raw['sort_tag_prefix'] )
			: $defaults['sort_tag_prefix'];
		$clean['sort_tag_order'] = isset( $raw['sort_tag_order'] ) && is_array( $raw['sort_tag_order'] )
			? array_map( 'sanitize_text_field', $raw['sort_tag_order'] )
			: array();

		// Style options.
		$clean['pdf_color_primary']   = isset( $raw['pdf_color_primary'] )   ? sanitize_hex_color( $raw['pdf_color_primary'] )   : $defaults['pdf_color_primary'];
		$clean['pdf_color_secondary'] = isset( $raw['pdf_color_secondary'] ) ? sanitize_hex_color( $raw['pdf_color_secondary'] ) : $defaults['pdf_color_secondary'];
		$clean['pdf_font_family']     = isset( $raw['pdf_font_family'] )     ? sanitize_key( $raw['pdf_font_family'] )           : $defaults['pdf_font_family'];
		$clean['pdf_margin_top']      = isset( $raw['pdf_margin_top'] )      ? absint( $raw['pdf_margin_top'] )                  : $defaults['pdf_margin_top'];
		$clean['pdf_margin_sides']    = isset( $raw['pdf_margin_sides'] )    ? absint( $raw['pdf_margin_sides'] )                : $defaults['pdf_margin_sides'];
		$clean['pdf_show_header']     = ! empty( $raw['pdf_show_header'] );
		$clean['pdf_show_footer']     = ! empty( $raw['pdf_show_footer'] );
		$clean['pdf_filename']        = isset( $raw['pdf_filename'] )        ? sanitize_file_name( $raw['pdf_filename'] )        : $defaults['pdf_filename'];

		// Reset cache.
		$this->settings = wp_parse_args( $clean, $defaults );

		return update_option( self::OPTION_KEY, $clean );
	}
}
