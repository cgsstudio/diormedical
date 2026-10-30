<?php
/**
 * Partial: Page Cache module card.
 */

if (!defined('ABSPATH')) die('No direct access allowed');

$active             = $cache['active'];
$is_premium         = !empty($cache['is_premium']);
$files_cached_raw   = (int) $cache['files_cached'];
$files_cached       = number_format_i18n($files_cached_raw);
$cache_size_human   = $cache['cache_size_human'];
$last_cleared_human = $cache['last_cleared_human'];
$gzip_active        = $cache['gzip_active'];
$country_caching    = $cache['country_caching']; // always false in free
?>

<div class="wpo-mod-card<?php echo !$active ? ' wpo-inactive-card' : ''; ?>">

	<div class="wpo-mod-header">
		<div class="wpo-mod-icon-title">
			<span class="wpo-dash-icon dashicons dashicons-cloud"></span>
			<?php esc_html_e('Page cache', 'wp-optimize'); ?>
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
				<?php echo esc_html($files_cached); ?>
				<?php /* translators: singular/plural label next to the cached files count */ ?>
				<span><?php echo esc_html(_n('cached file', 'cached files', $files_cached_raw, 'wp-optimize')); ?></span>
			</div>
		<?php else : ?>
			<div class="wpo-mod-big"><?php esc_html_e('No cached files yet', 'wp-optimize'); ?></div>
		<?php endif; ?>
		<p class="wpo-mod-desc">
			<?php echo $is_premium ? esc_html__('Visitors get pre-built pages, so repeat visits feel instant.', 'wp-optimize') : esc_html__('Serves a saved version of your pages to repeat visitors, like photocopying instead of retyping.', 'wp-optimize'); ?>
		</p>
	</div>

	<div class="wpo-feature-list">

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo $active ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('Page caching', 'wp-optimize'); ?>
			</span>
			<span class="wpo-feat-val <?php echo $active ? 'on' : 'off'; ?>">
				<?php
					if ($active) :
						/* translators: %s = number of files */
						echo esc_html(sprintf(_n('%s file', '%s files', $files_cached_raw, 'wp-optimize'), $files_cached));
					else :
						echo '–';
					endif;
				?>
			</span>
		</div>

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo $gzip_active ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('Gzip compression', 'wp-optimize'); ?>
			</span>
			<span class="wpo-feat-val <?php echo $gzip_active ? 'on' : 'off'; ?>">
				<?php echo $gzip_active ? esc_html__('Active', 'wp-optimize') : '–'; ?>
			</span>
		</div>

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo $country_caching ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('Country-based caching', 'wp-optimize'); ?>
			</span>
			<?php if ($is_premium) : ?>
				<span class="wpo-feat-val <?php echo $country_caching ? 'on' : 'off'; ?>">
					<?php echo $country_caching ? esc_html__('Active', 'wp-optimize') : '–'; ?>
				</span>
			<?php else : ?>
				<span class="wpo-feat-val premium"><?php esc_html_e('Premium', 'wp-optimize'); ?></span>
			<?php endif; ?>
		</div>

	</div>

	<?php if (!$is_premium) : ?>
		<div class="wpo-notice">
			<span class="wpo-dash-icon dashicons dashicons-admin-site"></span>
			<span>
				<?php esc_html_e('Have visitors from different regions? Serve faster, localised pages with Premium.', 'wp-optimize'); ?>
				<a href="<?php echo esc_url(WP_Optimize()->premium_version_link . '&utm_content=dashboard-cache-country'); ?>" target="_blank">
					<?php esc_html_e('Learn more', 'wp-optimize'); ?>
				</a>.
			</span>
		</div>
	<?php endif; ?>

	<div class="wpo-mod-footer">
		<span>
			<?php
				if ($active) :
					/* translators: %1$s = cache size, %2$s = time cleared */
					printf(esc_html__('%1$s cached · %2$s', 'wp-optimize'), esc_html($cache_size_human), esc_html($last_cleared_human));
				endif;
			?>
		</span>
		<a href="<?php echo esc_url(WP_Optimize()->get_options()->admin_page_url('wpo_cache')); ?>" class="wpo-dash-link">
			<?php esc_html_e('Manage cache', 'wp-optimize'); ?><span class="dashicons dashicons-arrow-right-alt"></span>
		</a>
	</div>
</div>