<?php
/**
 * Partial: Savings summary card (top-right).
 */

if (!defined('ABSPATH')) die('No direct access allowed');

$total_human = $savings['total_human'] ?? '0 B';
$has_savings = ($savings['total_bytes'] ?? 0) > 0;

$image_bytes  = (int) ($savings['image_bytes']    ?? 0);
$db_bytes     = (int) ($savings['database_bytes'] ?? 0);
$cache_bytes  = (int) ($savings['cache_bytes']    ?? 0);
$files_bytes  = (int) ($savings['files_bytes']    ?? 0);

$image_pct    = (int) ($savings['image_pct']    ?? 0);
$db_pct       = (int) ($savings['database_pct'] ?? 0);
$cache_pct    = (int) ($savings['cache_pct']    ?? 0);
$files_pct    = (int) ($savings['files_pct']    ?? 0);

$image_human  = $savings['image_human']    ?? '0 B';
$db_human     = $savings['database_human'] ?? '0 B';
$cache_human  = $savings['cache_human']    ?? '0 B';
$files_human  = $savings['files_human']    ?? '0 B';

// Bar widths: zero-byte segments get a 1% sliver so all 4 colours always show.
$image_bar  = $has_savings ? ($image_bytes > 0 ? max(2, $image_pct) : 1) : 0;
$db_bar     = $has_savings ? ($db_bytes > 0 ? max(2, $db_pct) : 1) : 0;
$cache_bar  = $has_savings ? ($cache_bytes > 0 ? max(2, $cache_pct) : 1) : 0;
$files_bar  = $has_savings ? ($files_bytes > 0 ? max(2, $files_pct) : 1) : 0;
?>

<div class="wpo-card">
	<div class="wpo-card-saving-bar">

		<?php if ($has_savings) : ?>
			<div class="wpo-big-num wpo-big-num--green"><?php echo wp_kses($total_human, array('span' => array('class' => true))); ?></div>
			<p class="wpo-savings-sub">
				<?php esc_html_e('Freed so far across all optimisations', 'wp-optimize'); ?>
			</p>
		<?php else : ?>
			<div class="wpo-big-num wpo-big-num--zero grey-out"><b>0</b> <span>MB</span></div>
			<p class="wpo-savings-sub">
				<?php esc_html_e('No savings yet — we\'ll track them here.', 'wp-optimize'); ?>
				<?php esc_html_e('Typical sites free up 50–200 MB', 'wp-optimize'); ?>
			</p>
		<?php endif; ?>

		<div class="wpo-savings-bar-label">
			<?php esc_html_e('Where savings come from', 'wp-optimize'); ?>
		</div>

		<?php if ($has_savings) : ?>
			<div class="wpo-bar-wrap">
				<div class="wpo-bar-seg" style="width:<?php echo esc_attr($image_bar); ?>%;background:#1A80BB"></div>
				<div class="wpo-bar-seg" style="width:<?php echo esc_attr($db_bar);    ?>%;background:#EAC11C"></div>
				<div class="wpo-bar-seg" style="width:<?php echo esc_attr($cache_bar); ?>%;background:#B8B8B8"></div>
				<div class="wpo-bar-seg" style="width:<?php echo esc_attr($files_bar); ?>%;background:#605F69"></div>
			</div>
		<?php else : ?>
			<div class="wpo-bar-wrap wpo-bar-wrap--empty">
				<div class="wpo-bar-seg"></div>
			</div>
		<?php endif; ?>

		<div class="wpo-bar-legend">
			<div class="wpo-legend-item">
				<div class="wpo-legend-square" style="background:#1A80BB"></div>
				<?php
				/* translators: %s = human size e.g. "31.6 MB" */
				printf(esc_html__('Media: %s', 'wp-optimize'), esc_html($image_human));
				?>
			</div>
			<div class="wpo-legend-item">
				<div class="wpo-legend-square" style="background:#EAC11C"></div>
				<?php
				/* translators: %s = human size */
				printf(esc_html__('Database: %s', 'wp-optimize'), esc_html($db_human));
				?>
			</div>
			<div class="wpo-legend-item">
				<div class="wpo-legend-square" style="background:#B8B8B8"></div>
				<?php
				/* translators: %s = human size */
				printf(esc_html__('Cache: %s', 'wp-optimize'), esc_html($cache_human));
				?>
			</div>
			<div class="wpo-legend-item">
				<div class="wpo-legend-square" style="background:#605F69"></div>
				<?php
				/* translators: %s = human size */
				printf(esc_html__('Files: %s', 'wp-optimize'), esc_html($files_human));
				?>
			</div>
		</div>

	</div>
</div>
