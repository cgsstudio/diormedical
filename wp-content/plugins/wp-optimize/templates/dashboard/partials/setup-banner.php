<?php
/**
 * Partial: Setup / status banner (top-left card).
 *
 * Variables (all pre-computed by WP_Optimize_Dashboard::get_banner_view_data()):
 *   array $banner Keys: is_start, is_complete, eyebrow, title, subtitle,
 *                         running_label, cta_href, cta_label.
 */

if (!defined('ABSPATH')) die('No direct access allowed');
?>

<div class="wpo-card">

	<div class="wpo-setup-eyebrow <?php echo !empty($banner['is_start']) ? 'grey-out' : ''; ?>">
		<span class="wpo-dot">●</span>
		<?php echo esc_html($banner['eyebrow']); ?>
	</div>

	<h2 class="wpo-setup-title"><?php echo esc_html($banner['title']); ?></h2>
	<p class="wpo-setup-sub"><?php echo esc_html($banner['subtitle']); ?></p>

	<div class="wpo-setup-meta">
		<span class="wpo-running-label">
			<span class="wpo-dash-icon dashicons dashicons-clock icon-meta"></span>
			<?php echo esc_html($banner['running_label']); ?>
		</span>

		<?php if (!empty($banner['cta_href'])) : ?>
			<a class="<?php echo !empty($banner['is_start']) ? 'button button-setup' : 'wpo-dash-link'; ?>" href="<?php echo esc_url($banner['cta_href']); ?>">
				<?php echo esc_html($banner['cta_label']); ?><span class="dashicons dashicons-arrow-right-alt"></span>
			</a>
		<?php endif; ?>
	</div>

</div>
