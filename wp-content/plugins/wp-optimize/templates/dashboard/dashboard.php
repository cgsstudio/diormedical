<?php
/**
 * Dashboard main layout template for WP-Optimize.
 *
 * All variables are passed via include_template() from WP_Optimize_Admin::output_dashboard().
 * See WP_Optimize_Dashboard::get_dashboard_data() for the full variable list.
 */

if (!defined('ABSPATH')) die('No direct access allowed');

$partials = __DIR__ . '/partials/';
?>
<div class="wpo-dash">
	<div class="wpo-dash-toolbar">
		<button type="button" class="wpo-refresh-btn" id="wpo-dashboard-refresh">
			<span class="wpo-dash-icon dashicons dashicons-update"></span>
			<?php esc_html_e('Refresh', 'wp-optimize'); ?>
		</button>
	</div>
	<div class="wpo-top-row">
		<?php require $partials . 'top-row.php'; ?>
	</div>
	<div class="wpo-modules-label">
		<?php esc_html_e('Your modules', 'wp-optimize'); ?>
	</div>
	<div class="wpo-mod-grid">
		<?php require $partials . 'mod-grid.php'; ?>
	</div>
	<?php require $partials . 'promo-bar.php'; ?>
</div>
