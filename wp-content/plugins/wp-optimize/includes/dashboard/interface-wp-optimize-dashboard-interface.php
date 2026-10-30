<?php

if (!defined('ABSPATH')) die('Access denied.');

if (!interface_exists('WP_Optimize_Dashboard_Interface')) :

/**
 * Contract for all dashboard module data classes.
 *
 * Each implementation collects the data needed to render one module card.
 * The returned array must include it at minimum: bool $active Whether the module is enabled/running & string $label Human-readable module name (translated).
 */
interface WP_Optimize_Dashboard_Interface {

	/**
	 * Collects and returns the module's data payload.
	 *
	 * @return WpoDashboardCacheData|WpoDashboardImagesData|WpoDashboardMinifyData|WpoDashboardDatabaseData
	 */
	public function collect(): array;
}

endif;
