<?php
/**
 * Plugin uninstall handler.
 *
 * This file is called automatically by WordPress when the plugin is deleted
 * from the Plugins screen. It removes all data added by the plugin.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

// If uninstall is not called from WordPress, exit immediately.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin settings from the database.
delete_option( 'wpc_settings' );
