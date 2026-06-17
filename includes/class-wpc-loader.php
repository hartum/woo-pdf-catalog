<?php
/**
 * Register all hooks for the plugin.
 *
 * @package Woo_PDF_Catalog
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Loader
 *
 * Maintains and registers all hooks that power the plugin.
 * Inspired by the standard WordPress Plugin Boilerplate pattern.
 */
class WPC_Loader {

	/**
	 * Array of registered WordPress actions.
	 *
	 * @var array
	 */
	private $actions = array();

	/**
	 * Array of registered WordPress filters.
	 *
	 * @var array
	 */
	private $filters = array();

	// ─── Registration helpers ────────────────────────────────────────────────

	/**
	 * Queue a WordPress action hook.
	 *
	 * @param string $hook          WordPress hook name.
	 * @param object $component     Class instance that owns the callback.
	 * @param string $callback      Name of the method to call.
	 * @param int    $priority      Hook priority. Default 10.
	 * @param int    $accepted_args Number of arguments. Default 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Queue a WordPress filter hook.
	 *
	 * @param string $hook          WordPress hook name.
	 * @param object $component     Class instance that owns the callback.
	 * @param string $callback      Name of the method to call.
	 * @param int    $priority      Hook priority. Default 10.
	 * @param int    $accepted_args Number of arguments. Default 1.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	// ─── Run ────────────────────────────────────────────────────────────────

	/**
	 * Register all collected actions and filters with WordPress.
	 *
	 * Should be called once, after all add_action/add_filter calls.
	 */
	public function run() {
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
