<?php
/**
 * @package WpdDocBooker
 */


use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

defined('ABSPATH') || exit;

Helper::get_header($wp_version);

$grid_columns           = WpddbOptions::get_option( 'clinic_grid_columns','wpddb_clinic_settings') ?:'3';
$content_limit_class = function_exists('wpddbp')
    ? ' wpddb-limit lines-' . ( WpddbOptions::get_option( 'content_limit', 'wpddb_clinic_settings' ) ?: 3 )
    : '';
$page_layout            = WpddbOptions::get_option('clinic_page_layout','wpddb_clinic_settings') ?:'full-width';
$archive_page_sidebar   = Clinic::is_clinic() ? 'clinic-sidebar':'';
$clinic_layout         = WpddbOptions::get_option( 'clinic_archive_style', 'wpddb_clinic_settings' ) ?: 'layout-1';
$sidebar                = wpddb()->clinic_archive_sidebar;

/**
 * Hook: wpddb_before_main_content_wrapper.
 *
 * @hooked output_main_wrapper_start - 10 (outputs opening divs for the wrapper content)
 */
do_action('wpddb_before_main_content_wrapper');

/**
 * Hook: wpddb_before_main_content.
 *
 * @hooked output_content_wrapper - 10 (outputs opening divs for the content)
 */
do_action('wpddb_before_main_content');

?>

	<header class="wpddb-header">
		<?php if (apply_filters('wpddb_show_page_title', true)) : ?>
			<h2 class="wpddb-clinic-header-title page-title"><?php Helper::page_title(true,'clinic'); ?></h2>
		<?php endif; ?>
	</header>
	<div class="wpddb-clinic-wrapper">
		<div class="clinic-items-wrapper <?php echo esc_attr($page_layout); ?>">
			<?php if('left-sidebar'==$page_layout && Helper::is_active_sidebar($sidebar)===true){ ?>
				<aside <?php Helper::sidebar_class($archive_page_sidebar); ?>>
					<?php
					dynamic_sidebar($sidebar);
					?>
				</aside>
			<?php } ?>
			<div class="wpddb-clinic-items <?php echo esc_attr($clinic_layout.$content_limit_class); ?>">
				<div class="clinic-items-inner columns-<?php echo esc_attr($grid_columns); ?>">
					<?php
					$args['layout'] = $clinic_layout;
					if ( have_posts() ) {
						while ( have_posts() ) {
							the_post();
							$args['id'] = get_the_id();
							Helper::render_template('content-clinic',$args);
						}
					}
					?>
				</div>
				<?php
				/**
				 * Hook: wpddb_clinic_loop_item_after_content.
				 *
				 * @hooked wpddb_clinic_pagination-10
				 */

				do_action('wpddb_clinic_loop_item_after_content');
				?>
			</div>
			<?php if('right-sidebar'== $page_layout && Helper::is_active_sidebar($sidebar ) === true){ ?>
				<aside <?php Helper::sidebar_class($archive_page_sidebar); ?>>
					<?php
					dynamic_sidebar($sidebar);
					?>
				</aside>
			<?php } ?>
		</div>

	</div>
<?php
/**
 * Hook: wpddb_after_main_content.
 *
 * @hooked output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action('wpddb_after_main_content');

/**
 * Hook: wpddb_after_main_content_wrapper.
 *
 * @hooked output_main_wrapper_end - 10 (outputs closing divs for the wrapper content)
 */
do_action('wpddb_after_main_content_wrapper');


Helper::get_footer($wp_version);