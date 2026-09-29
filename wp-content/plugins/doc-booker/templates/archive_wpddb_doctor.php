<?php
/**
 * @package WpdDocBooker
 */


use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

defined( 'ABSPATH' ) || exit;

Helper::get_header( $wp_version );

$grid_columns         = WpddbOptions::get_option( 'doctor_grid_columns', 'wpddb_doctor_settings' ) ?: '3';
$content_limit_class = function_exists('wpddbp')
    ? ' wpddb-limit lines-' . ( WpddbOptions::get_option( 'content_limit', 'wpddb_doctor_settings' ) ?: 3 )
    : '';

$page_layout          = WpddbOptions::get_option( 'doctor_page_layout', 'wpddb_doctor_settings' ) ?: 'full-width';
$doctor_layout        = WpddbOptions::get_option( 'doctor_archive_style', 'wpddb_doctor_settings' ) ?: 'layout-1';
$archive_page_sidebar = Doctor::is_doctor() ? 'doctor-sidebar' : '';
$sidebar              = wpddb()->doctor_archive_sidebar;

/**
 * Hook: wpddb_before_main_content_wrapper.
 *
 * @hooked output_main_wrapper_start - 10 (outputs opening divs for the wrapper content)
 */
do_action( 'wpddb_before_main_content_wrapper' );

/**
 * Hook: wpddb_before_main_content.
 *
 * @hooked output_content_wrapper - 10 (outputs opening divs for the content)
 */
do_action( 'wpddb_before_main_content' );

?>

	<header class="wpddb-header">
		<?php if ( apply_filters( 'wpddb_show_page_title', true ) ) { ?>
			<h2 class="wpddb-doctor-header-title page-title"><?php Helper::page_title( true, 'doctor' ); ?></h2>
		<?php } ?>
	</header>
	<div class="wpddb-doctor-wrapper">
		<div class="doctor-items-wrapper <?php echo esc_attr( $page_layout ); ?>">
			<?php if ( 'left-sidebar' == $page_layout && Helper::is_active_sidebar( $sidebar ) === true ) { ?>
				<aside <?php Helper::sidebar_class( $archive_page_sidebar ); ?>>
					<?php
					dynamic_sidebar( $sidebar );
					?>
				</aside>
			<?php } ?>
			<div class="wpddb-doctor-items <?php echo esc_attr( $doctor_layout.$content_limit_class ); ?>">
				<div class="doctor-items-inner columns-<?php echo esc_attr( $grid_columns ); ?>">
					<?php
					$args['layout'] = $doctor_layout;
					if ( have_posts() ) {
						while ( have_posts() ) {
							the_post();
							$args['id'] = get_the_id();
							Helper::render_template( 'content-doctor', $args );
						}
					}else{
                        echo '<p class="info">';
                        esc_html_e('No doctors were found.', 'doc-booker');
                        echo '</p>';
                    }
					?>
				</div>
				<?php
				/**
				 * Hook: wpddb_doctor_loop_item_after_content.
				 *
				 * @hooked wpddb_doctor_pagination-10
				 */

				do_action( 'wpddb_doctor_loop_item_after_content' );
				?>
			</div>
			<?php if ( 'right-sidebar' == $page_layout && Helper::is_active_sidebar( $sidebar ) === true ) { ?>
				<aside <?php Helper::sidebar_class( $archive_page_sidebar ); ?>>
					<?php
					dynamic_sidebar( $sidebar );
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
do_action( 'wpddb_after_main_content' );

/**
 * Hook: wpddb_after_main_content_wrapper.
 *
 * @hooked output_main_wrapper_end - 10 (outputs closing divs for the wrapper content)
 */
do_action( 'wpddb_after_main_content_wrapper' );


Helper::get_footer( $wp_version );