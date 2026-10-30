<?php
/**
 * Partial: File optimization / Minify module card.
 */

if (!defined('ABSPATH')) die('No direct access allowed');

$active                   = !empty($minify['active']);
$is_premium               = !empty($minify['is_premium']);
$js_enabled               = !empty($minify['js_enabled']);
$css_enabled              = !empty($minify['css_enabled']);
$js_count                 = (int) ($minify['js_file_count'] ?? 0);
$css_count                = (int) ($minify['css_file_count'] ?? 0);
$total_files              = $js_count + $css_count;
$fonts_cached_locally     = !empty($minify['fonts_cached_locally']);
$analytics_local          = !empty($minify['analytics_hosted_locally']);
$last_rebuilt_ts          = (int) ($minify['last_rebuilt_ts'] ?? 0);
$last_rebuilt_human       = empty($minify['last_rebuilt_human']) ? null : $minify['last_rebuilt_human'];
$saved_human              = empty($minify['saved_human']) ? '' : $minify['saved_human'];
?>

<div class="wpo-mod-card<?php echo !$active ? ' wpo-inactive-card' : ''; ?>">

	<div class="wpo-mod-header">
		<div class="wpo-mod-icon-title">
			<span class="wpo-dash-icon dashicons dashicons-editor-contract"></span>
			<?php esc_html_e('File optimisation', 'wp-optimize'); ?>
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
				<?php echo esc_html(number_format_i18n($total_files)); ?>
				<span><?php esc_html_e('files minified', 'wp-optimize'); ?></span>
			</div>
		<?php else : ?>
			<div class="wpo-mod-big"><?php esc_html_e('Not set up yet', 'wp-optimize'); ?></div>
		<?php endif; ?>
		<p class="wpo-mod-desc">
			<?php esc_html_e('Strips unnecessary code from JavaScript and CSS files, like removing packaging so only the product gets delivered.', 'wp-optimize'); ?>
		</p>
	</div>

	<div class="wpo-feature-list">

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo ($active && $js_enabled) ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('Optimise JavaScript', 'wp-optimize'); ?>
			</span>
			<span class="wpo-feat-val files">
				<?php
					if ($active && $js_enabled && $js_count > 0) :
						/* translators: %d = number of files */
						printf(esc_html(_n('%d file', '%d files', $js_count, 'wp-optimize')), (int) $js_count);
					else :
						echo '–';
					endif;
				?>
			</span>
		</div>

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo ($active && $css_enabled) ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('Optimise CSS', 'wp-optimize'); ?>
			</span>
			<span class="wpo-feat-val files">
				<?php
					if ($active && $css_enabled && $css_count > 0) :
						/* translators: %d = number of files */
						printf(esc_html(_n('%d file', '%d files', $css_count, 'wp-optimize')), (int) $css_count);
					else :
						echo '–';
					endif;
				?>
			</span>
		</div>

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo $fonts_cached_locally ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('Fonts cached locally', 'wp-optimize'); ?>
			</span>
			<?php if ($is_premium) : ?>
				<span class="wpo-feat-val <?php echo $fonts_cached_locally ? 'on' : 'off'; ?>">
					<?php echo $fonts_cached_locally ? esc_html__('Active', 'wp-optimize') : '–'; ?>
				</span>
			<?php else : ?>
				<span class="wpo-feat-val premium"><?php esc_html_e('Premium', 'wp-optimize'); ?></span>
			<?php endif; ?>
		</div>

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo $analytics_local ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('Analytics hosted locally', 'wp-optimize'); ?>
			</span>
			<?php if ($is_premium) : ?>
				<span class="wpo-feat-val <?php echo $analytics_local ? 'on' : 'off'; ?>">
					<?php echo $analytics_local ? esc_html__('Active', 'wp-optimize') : '–'; ?>
				</span>
			<?php else : ?>
				<span class="wpo-feat-val premium"><?php esc_html_e('Premium', 'wp-optimize'); ?></span>
			<?php endif; ?>
		</div>

	</div>

	<div class="wpo-mod-footer">
		<span>
			<?php
				if ($active && $last_rebuilt_ts > 0) :
					if ($saved_human) :
						/* translators: %1$s = bytes saved, %2$s = time ago */
						printf(esc_html__('%1$s saved · %2$s', 'wp-optimize'), esc_html($saved_human), esc_html($last_rebuilt_human));
					else :
						/* translators: %s = time ago */
						printf(esc_html__('Last rebuilt: %s', 'wp-optimize'), esc_html($last_rebuilt_human));
					endif;
				endif;
			?>
		</span>
		<a href="<?php echo esc_url(WP_Optimize()->get_options()->admin_page_url('wpo_minify')); ?>" class="wpo-dash-link">
			<?php esc_html_e('Manage minification', 'wp-optimize'); ?><span class="dashicons dashicons-arrow-right-alt"></span>
		</a>
	</div>
</div>