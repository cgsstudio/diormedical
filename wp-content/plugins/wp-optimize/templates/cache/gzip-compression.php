<?php if (!defined('ABSPATH')) die('No direct access allowed'); ?>

<div class="wpo_section wpo_group">

	<h3 class="wpo-first-child"><?php esc_html_e('Gzip compression settings', 'wp-optimize');?></h3>
	<p>
		<span class="dashicons dashicons-info"></span>
		<span><?php
			esc_html_e("This option improves the performance of your website and decreases its loading time.", 'wp-optimize');
			esc_html_e('When a visitor makes a request, the server compresses the requested resource before sending it leading to smaller file sizes and faster loading.', 'wp-optimize');
			?>
			<?php printf('<a href="%s" target="_blank">%s</a>', esc_url($info_link), esc_html__('Follow this link to get more information about Gzip compression.', 'wp-optimize')); ?>
		</span>
	</p>

	<div id="wpo_gzip_control_panel">
	<?php echo wp_kses_post($gzip_compression_control_panel); ?>
	</div>

	<div class="wpo-fieldgroup">
		<strong><?php esc_html_e('Gzip compression test', 'wp-optimize'); ?></strong> <span tabindex="0" data-tooltip="<?php esc_attr_e('This section displays the current compression status for different resources such as HTML, CSS, and JS.', 'wp-optimize');?>"><span class="dashicons dashicons-editor-help"></span></span>
		<a href="javascript:;" id="wpo_gzip_compression_test_btn" class="wpo-refresh-button"><span class="dashicons dashicons-image-rotate"></span><?php esc_html_e('Test again', 'wp-optimize'); ?></a>
		<br>
		<p id="wpo_gzip_compression_details">
			<?php echo $compression_test_results_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped ?>
		</p>
	</div>	

	<div id="wpo_gzip_compression_error_message">
		<?php
		if (is_wp_error($wpo_gzip_compression_enabled)) {
			echo esc_html($wpo_gzip_compression_enabled->get_error_message());
		}
		?>
	</div>
	<pre id="wpo_gzip_compression_output" style="display: none;"></pre>

</div>
