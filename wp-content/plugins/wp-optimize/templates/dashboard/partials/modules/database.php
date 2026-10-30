<?php
/**
 * Partial: Database module card.
 */

if (!defined('ABSPATH')) die('No direct access allowed');

$active             = !empty($database['active']);
$scheduled          = !empty($database['scheduled']);
$backup_before      = !empty($database['backup_before_cleanup']);
$schedule_count     = (int) ($database['schedule_count'] ?? 0);
$schedule_label     = !empty($database['schedule_label']) ? $database['schedule_label'] : __('Not scheduled', 'wp-optimize');
$next_run_human     = !empty($database['next_run_human']) ? $database['next_run_human'] : __('Not scheduled', 'wp-optimize');
$last_run_human     = empty($database['last_run_human']) ? '' : $database['last_run_human'];
$schedules_summary  = is_array($database['schedules_summary']) ? $database['schedules_summary'] : array();
$updraftplus_active = !empty($database['updraftplus_active']);
$last_cleaned_human = empty($database['last_cleaned_human']) ? '' : $database['last_cleaned_human'];

?>

<div class="wpo-mod-card">

	<div class="wpo-mod-header">
		<div class="wpo-mod-icon-title">
			<?php global $wp_version; ?>
			<span class="wpo-dash-icon dashicons <?php echo version_compare($wp_version, '5.5', '>=') ? 'dashicons-database' : 'dashicons-admin-tools'; ?>"></span>
			<?php esc_html_e('Database', 'wp-optimize'); ?>
		</div>
		<?php if ($scheduled) : ?>
			<span class="wpo-badge scheduled">
				<?php esc_html_e('Scheduled', 'wp-optimize'); ?>
			</span>
		<?php elseif ($active) : ?>
			<span class="wpo-badge active">
				<?php esc_html_e('Active', 'wp-optimize'); ?>
			</span>
		<?php else : ?>
			<span class="wpo-badge inactive"><?php esc_html_e('Inactive', 'wp-optimize'); ?></span>
		<?php endif; ?>
	</div>

	<div>
		<?php if ($scheduled) : ?>
			<div class="wpo-mod-big">
				<?php if ($schedule_count > 1) : ?>
					<span style="font-size:20px;font-weight:500"><?php echo esc_html(number_format_i18n($schedule_count)); ?></span>
					<span><?php esc_html_e('cleanups active', 'wp-optimize'); ?></span>
				<?php else : ?>
					<span style="font-size:20px;font-weight:500"><?php echo esc_html($schedule_label); ?></span>
					<span><?php esc_html_e('cleanup active', 'wp-optimize'); ?></span>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="wpo-mod-big"><?php esc_html_e('No cleanups run yet', 'wp-optimize'); ?></div>
		<?php endif; ?>
		<p class="wpo-mod-desc">
			<?php esc_html_e('Clears out old drafts, spam, and temporary data on a schedule, like taking out the trash with a backup first.', 'wp-optimize'); ?>
		</p>
	</div>

	<div class="wpo-feature-list">

		<div class="wpo-feat-row wpo-backup-before-cleanup">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo ($backup_before && $updraftplus_active) ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<a href="<?php echo esc_url(WP_Optimize()->get_options()->admin_page_url('wpo_database') . '&tab=wp_optimize_settings'); ?>" class="wpo-dash-link">
					<?php esc_html_e('Backup before cleanup', 'wp-optimize'); ?>
				</a>
			</span>
			<span class="wpo-feat-val <?php echo ($backup_before && $updraftplus_active) ? 'on' : 'off'; ?>">
				<?php
				if ($backup_before && $updraftplus_active) :
					esc_html_e('Active', 'wp-optimize');
				elseif (!$updraftplus_active) :
					$udp_status = WP_Optimize()->is_installed('UpdraftPlus - Backup/Restore');
					if (!empty($udp_status['installed'])) :
						$action_url = wp_nonce_url(network_admin_url('plugins.php?action=activate&plugin=updraftplus/updraftplus.php'), 'activate-plugin_updraftplus/updraftplus.php');
						$action_text = __('Activate UpdraftPlus', 'wp-optimize');
					else :
						$action_url = wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=updraftplus'), 'install-plugin_updraftplus');
						$action_text = __('Install UpdraftPlus', 'wp-optimize');
					endif;
					?>
					<a href="<?php echo esc_url($action_url); ?>" class="wpo-warn"><?php echo esc_html($action_text); ?><span class="dashicons dashicons-arrow-right-alt"></span></a>
					<?php
				else :
					echo '–';
				endif;
				?>
			</span>
		</div>

		<div class="wpo-feat-row">
			<span class="wpo-feat-name">
				<span class="wpo-dot <?php echo $scheduled ? 'wpo-dot-on' : 'wpo-dot-off'; ?>">●</span>
				<?php esc_html_e('Scheduled cleanup', 'wp-optimize'); ?>
			</span>
			<span class="wpo-feat-val <?php echo $scheduled ? 'on' : 'off'; ?>">
				<?php echo $scheduled ? esc_html($next_run_human) : '–'; ?>
			</span>
		</div>

		<?php foreach ($schedules_summary as $row) : ?>
			<div class="wpo-feat-row">
				<span class="wpo-feat-name wpo-feat-name--truncate" title="<?php echo esc_attr($row['frequency'] . ': ' . $row['tasks_label']); ?>">
					<span class="wpo-dot wpo-dot-on">●</span>
					<strong><?php echo esc_html($row['frequency']); ?>:</strong>
					<span class="wpo-feat-tasks"><?php echo esc_html($row['tasks_label'] ?: ''); ?></span>
				</span>
				<span class="wpo-feat-val on">
					<?php echo esc_html__('Active', 'wp-optimize'); ?>
				</span>
			</div>
		<?php endforeach; ?>

	</div>

	<div class="wpo-mod-footer">
		<span>
			<?php
				if (($active || $scheduled) && $last_run_human) :
					if ($last_cleaned_human) :
						/* translators: %1$s = size cleaned, %2$s = time ago */
						printf(esc_html__('%1$s cleaned · %2$s', 'wp-optimize'), esc_html($last_cleaned_human), esc_html($last_run_human));
					else :
						/* translators: %s = time ago */
						printf(esc_html__('Last cleaned: %s', 'wp-optimize'), esc_html($last_run_human));
					endif;
				endif;
			?>
		</span>
		<a href="<?php echo esc_url(WP_Optimize()->get_options()->admin_page_url('wpo_database')); ?>" class="wpo-dash-link">
			<?php esc_html_e('Manage database', 'wp-optimize'); ?><span class="dashicons dashicons-arrow-right-alt"></span>
		</a>
	</div>

</div>