<?php
/**
 * Partial: TeamUpdraft promo bar (bottom strip).
 * Shown on all tiers — free and premium.
 */

if (!defined('ABSPATH')) die('No direct access allowed');
?>

<div class="wpo-promo">
	<div class="wpo-promo-label">
		<?php esc_html_e('Explore more tools', 'wp-optimize'); ?><br>
		<?php esc_html_e('from TeamUpdraft', 'wp-optimize'); ?>
	</div>
	<div class="wpo-promo-apps">
		<a href="https://wordpress.org/plugins/updraftplus/" target="_blank" class="wpo-promo-app">
			<div class="wpo-promo-app-img">
				<img src="<?php echo esc_url(WPO_PLUGIN_URL . 'images/notices/updraft_logo.png'); ?>" alt="UpdraftPlus" width="40" height="40">
			</div>
			<div class="wpo-promo-app-detail">
				<strong class="wpo-promo-app-name">UpdraftPlus</strong>
				<span class="wpo-promo-app-subtitle"><?php esc_html_e('Backups & staging', 'wp-optimize'); ?></span>
			</div>
		</a>
		<a href="https://wordpress.org/plugins/all-in-one-wp-security-and-firewall/" target="_blank" class="wpo-promo-app">
			<div class="wpo-promo-app-img">
				<img src="<?php echo esc_url(WPO_PLUGIN_URL . 'images/notices/aios_logo.png'); ?>" alt="<?php esc_attr_e('All-In-One Security', 'wp-optimize'); ?>" width="40" height="40">
			</div>
			<div class="wpo-promo-app-detail">
				<strong class="wpo-promo-app-name"><?php esc_html_e('AIOS', 'wp-optimize'); ?></strong>
				<span class="wpo-promo-app-subtitle"><?php esc_html_e('Best-in-class security', 'wp-optimize'); ?></span>
			</div>
		</a>
		<a href="https://wordpress.org/plugins/updraftcentral/" target="_blank" class="wpo-promo-app">
			<div class="wpo-promo-app-img">
				<img src="<?php echo esc_url(WPO_PLUGIN_URL . 'images/notices/updraft_central_logo.png'); ?>" alt="UpdraftCentral" width="40" height="40">
			</div>
			<div class="wpo-promo-app-detail">
				<strong class="wpo-promo-app-name">UpdraftCentral</strong>
				<span class="wpo-promo-app-subtitle"><?php esc_html_e('Easy multi-site dashboard', 'wp-optimize'); ?></span>
			</div>
		</a>
	</div>
	<a href="<?php echo esc_url('https://teamupdraft.com/?utm_source=wpo-plugin&utm_medium=referral&utm_campaign=dashboard-promo'); ?>"
	   target="_blank"
	   class="wpo-promo-btn">
		<?php esc_html_e('Learn more', 'wp-optimize'); ?> <span class="dashicons dashicons-arrow-right-alt wpo-promo-btn-icon"></span>
	</a>
</div>