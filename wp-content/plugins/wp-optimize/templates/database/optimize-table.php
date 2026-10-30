<?php
if (!defined('ABSPATH')) die('No direct access allowed');

?>
<div class="wpo_shade hidden">
	<div class="wpo_shade_inner">
			<span class="dashicons dashicons-update-alt wpo-rotate"></span>
		<h4><?php esc_html_e('Loading data...', 'wp-optimize'); ?></h4>
	</div>
</div>

<div class="wpo_section wpo_group">

	<?php
		if (!empty($optimization_results)) {
			echo '<div id="message" class="updated below-h2"><strong>';
			foreach ($optimization_results as $optimization_result) {
				if (!empty($optimization_result->output)) {
					foreach ($optimization_result->output as $line) {
						echo wp_kses_post($line)."<br>";
					}
				}
			}
			echo '</strong></div>';
		}
	?>

	<?php WP_Optimize()->include_template('database/status-box-contents.php', false, $status_data); ?>
	<?php
		$message = __('Warning: This operation is permanent.', 'wp-optimize');
		$message .= ' ';
		$message .= __('Continue?', 'wp-optimize');
	?>
	<form onsubmit="return confirm('<?php echo esc_js($message); ?>')" action="#" method="post" enctype="multipart/form-data" name="optimize_form" id="optimize_form">
	
	<?php wp_nonce_field('wpo_optimization'); ?>
	
		<h3><?php esc_html_e('Optimizations', 'wp-optimize'); ?></h3>

		<div class="wpo-run-optimizations__container">
			<?php if ($should_show_load_in_batches_button) : ?>
			<button type="button" id="wpo-load-tables-in-batches" class="button button-primary button-large"><?php echo esc_html__('Reload database tables in batches', 'wp-optimize'); ?></button>
			<?php endif; ?>
			<?php $button_caption = apply_filters('wpo_run_button_caption', __('Run all selected optimizations', 'wp-optimize')); ?>
			<input class="button button-primary button-large" type="submit" id="wp-optimize" name="wp-optimize" value="<?php echo esc_attr($button_caption); ?>"><?php WP_Optimize()->include_template('take-a-backup.php', false, array('checkbox_name' => 'enable-auto-backup')); ?>
		</div>
		
		<?php do_action('wpo_additional_options'); ?>

		<?php
		if ($load_data) {
			WP_Optimize()->include_template('database/optimizations-table.php', false, $optimizations_table_data);
		} else {
		?>
			<div class="wp-optimize-optimizations-table-placeholder">
			</div>
		<?php } ?>
		
		<p class="wp-optimize-sensitive-tables-warning">
			<span style="color: #E07575;">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e('Warning:', 'wp-optimize'); ?>
			</span>
			<?php
				$message = __('Items marked with this icon perform more intensive database operations.', 'wp-optimize');
				$message .= ' ';
				$message .= __('In very rare cases, if your database server happened to crash or be forcibly powered down at the same time as an optimization operation was running, data might be corrupted.', 'wp-optimize');
				$message .= ' ';
				$message .= __('You may wish to run a backup before optimizing.', 'wp-optimize');
				echo esc_html($message);
			?>
		</p>
	</form>
</div>
<?php if ($should_show_load_in_batches_button) : ?>
<?php
// Determine scan offset
$scan_job_offset = ($total_table_count === $done_table_count) ? 0 : $done_table_count;

// Escape values once for reuse
$total_table_count_esc = esc_html($total_table_count);
$scan_job_offset_esc   = esc_js($scan_job_offset);
?>
<div id="wpo-too-many-tables-popup" class="wpo-modal--container wpo-too-many-tables" style="display:none;">
	<div class="wpo-modal--bg"></div>
	<div class="wpo-modal" style="text-align:center; max-width:600px;">
		<button type="button" class="wpo-modal--close wpo-too-many-tables-close">
			<span class="dashicons dashicons-no"></span>
			<span class="screen-reader-text"><?php esc_html_e('Close', 'wp-optimize'); ?></span>
		</button>
		<div class="wpo-modal--content">
			<p>
				<?php
				printf(
				/* translators: %s = total number of tables */
						esc_html__(
								'You have a total of %s tables which will take some time to load.',
								'wp-optimize'
						) . ' ' . esc_html__(
								'Click the button below and please wait while it loads all the tables.',
								'wp-optimize'
						),
						$total_table_count_esc // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output already escaped
				);
				?>
			</p>
			<p>
				<button id="wpo-load-tables-in-batches-button" class="button button-primary button-large">
					<?php
						echo (0 === $scan_job_offset) ? esc_html__('Load Tables', 'wp-optimize') : esc_html__('Continue Loading Tables', 'wp-optimize');
					?>
				</button>
			</p>
			<div id="wpo-load-tables-in-batches-progress"></div>
			<div id="wpo-load-tables-eta"></div>
		</div>
	</div>
</div>
<script>
	var wpo_table_scan_offset = <?php echo $scan_job_offset_esc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output already escaped ?>;
</script>
<?php endif; ?>
<script>
	var wpo_too_many_tables = <?php echo $too_many_tables ? 'true' : 'false'; ?>;
	var wpo_onboarding_active = <?php echo $onboarding_active ? 'true' : 'false'; ?>;
</script>