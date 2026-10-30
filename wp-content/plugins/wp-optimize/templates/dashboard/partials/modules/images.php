<?php
/**
 * Partial: Media / Images module card.
 */

if (!defined('ABSPATH')) die('No direct access allowed');

$active           = !empty($images['active']);
$optimised_count  = number_format_i18n((int) ($images['optimised_count'] ?? 0));
$saved_human      = empty($images['saved_human']) ? '0 B' : $images['saved_human'];
$auto_optimize    = !empty($images['auto_optimize']);
$webp_conversion  = !empty($images['webp_conversion']);
$unused_count     = (int) ($images['unused_count'] ?? 0);
$is_premium       = !empty($images['is_premium']);
?>

<div class="wpo-mod-card<?php echo !$active ? ' wpo-inactive-card' : ''; ?>">

	<div class="wpo-mod-header">
		<div class="wpo-mod-icon-title">
			<span class="wpo-dash-icon dashicons dashicons-format-image"></span>
			<?php esc_html_e('Media', 'wp-optimize'); ?>
		</div>
		<?php if ($active) : ?>
			<span class="wpo-badge active">
				<?php esc_html_e('Active', 'wp-optimize'); ?>
			</span>
		<?php else : ?>
			<span class="wpo-badge inactive"><?php esc_html_e('Inactive', 'wp-optimize'); ?></span>
		<?php endif; ?>
	</div>

	<div>
		<?php if ($active) : ?>
			<div class="wpo-mod-big">
				<?php echo esc_html($optimised_count); ?>
				<span><?php esc_html_e('images compressed', 'wp-optimize'); ?></span>
			</div>
		<?php else : ?>
			<div class="wpo-mod-big"><?php esc_html_e('No images compressed yet', 'wp-optimize'); ?></div>
		<?php endif; ?>
		<p class="wpo-mod-desc">
			<?php esc_html_e('Shrinks images automatically without visible quality loss, like packing a suitcase more efficiently.', 'wp-optimize'); ?>
		</p>
	</div>

	<div class="wpo-feature-list">

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo $auto_optimize ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('Auto optimization', 'wp-optimize'); ?>
			</span>
			<span class="wpo-feat-val <?php echo $auto_optimize ? 'on' : 'off'; ?>">
				<?php echo $auto_optimize ? esc_html__('Active', 'wp-optimize') : '–'; ?>
			</span>
		</div>

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo $webp_conversion ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('WebP conversion', 'wp-optimize'); ?>
			</span>
			<span class="wpo-feat-val <?php echo $webp_conversion ? 'on' : 'off'; ?>">
				<?php echo $webp_conversion ? esc_html__('Active', 'wp-optimize') : '–'; ?>
			</span>
		</div>

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo $is_premium ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('Remove unused images', 'wp-optimize'); ?>
			</span>
			<?php if ($is_premium) : ?>
				<?php if ($unused_count > 0) : ?>
					<span class="wpo-feat-val wpo-unused-images-count">
						<?php
						/* translators: %d = number of unused images */
						printf(esc_html(_n('%d unused', '%d unused', $unused_count, 'wp-optimize')), (int) $unused_count);
						?>
					</span>
				<?php else : ?>
					<?php $unused_url = WP_Optimize()->get_options()->admin_page_url('wpo_images') . '&tab=wp_optimize_unused'; ?>
					<span class="wpo-feat-val on">
						<a href="<?php echo esc_url($unused_url); ?>" class="wpo-dash-link"><?php esc_html_e('Available', 'wp-optimize'); ?><span class="dashicons dashicons-arrow-right-alt"></span></a>
					</span>
				<?php endif; ?>
			<?php else : ?>
				<span class="wpo-feat-val premium"><?php esc_html_e('Premium', 'wp-optimize'); ?></span>
			<?php endif; ?>
		</div>

	</div>

	<?php if (!$is_premium) : ?>
		<div class="wpo-notice">
			<span class="wpo-dash-icon dashicons dashicons-images-alt2"></span>
			<span>
				<?php esc_html_e('Find and remove unused images to free up storage with Premium.', 'wp-optimize'); ?>
				<a href="<?php echo esc_url(WP_Optimize()->premium_version_link . '&utm_content=dashboard-images-unused'); ?>" target="_blank">
					<?php esc_html_e('Learn more', 'wp-optimize'); ?>
				</a>.
			</span>
		</div>
	<?php endif; ?>

	<div class="wpo-mod-footer">
		<span>
			<?php
				if ($active) :
					/* translators: %s = bytes saved */
					printf(esc_html__('%s saved', 'wp-optimize'), esc_html($saved_human));
				endif;
			?>
		</span>
		<a href="<?php echo esc_url(WP_Optimize()->get_options()->admin_page_url('wpo_images')); ?>" class="wpo-dash-link">
			<?php esc_html_e('Manage media', 'wp-optimize'); ?><span class="dashicons dashicons-arrow-right-alt"></span>
		</a>
	</div>
</div>