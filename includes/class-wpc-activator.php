<?php
/**
 * Fired during plugin activation and deactivation.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Activator
 *
 * Handles tasks that must run at activation / deactivation time.
 *
 * IMPORTANT: The activation hook fires BEFORE plugins_loaded, so we cannot
 * rely on any class that is loaded inside wpc_init_plugin(). All logic here
 * must be self-contained (no WPC_Settings, no WooCommerce classes).
 */
class WPC_Activator {

	/**
	 * Run on plugin activation.
	 *
	 * Writes default settings to the database only when the option doesn't
	 * exist yet (preserves existing config on re-activation).
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Store default settings if not already set.
		// We define defaults inline here because WPC_Settings is not loaded yet.
		if ( false === get_option( 'wpc_settings' ) ) {
			$defaults = array(
				'enabled_fields'      => array( 'sku', 'price', 'stock_status', 'weight', 'dimensions', 'categories', 'attributes', 'short_description', 'description' ),
				'publication_status'  => 'publish',
				'forced_products'     => array(),
				'excluded_products'   => array(),
				'pdf_color_primary'   => '#1e3a5f',
				'pdf_color_secondary' => '#4a90d9',
				'pdf_font_family'     => 'dejavusans',
				'pdf_margin_top'      => 20,
				'pdf_margin_sides'    => 15,
				'pdf_show_header'     => true,
				'pdf_show_footer'     => true,
				'pdf_filename'        => 'catalog',
			);
			add_option( 'wpc_settings', $defaults );
		}

		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * Flushes rewrite rules. Settings are intentionally preserved.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
