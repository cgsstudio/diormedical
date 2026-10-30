<?php
/**
 * @package WpdDocBooker
 */


use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

defined('ABSPATH') || exit;
global $post;

Helper::get_header($wp_version);

$page_layout            = WpddbOptions::get_option('doctor_single_page_layout','wpddb_doctor_settings') ?:'full-width';
$single_page_sidebar    = Doctor::is_doctor() ? 'doctor-sidebar':'';
$sidebar                = wpddb()->doctor_single_sidebar;

$thumb_size      ='wpddb_size1';


/**
 * Hook: wpddb_main_content_wrapper.
 *
 * @hooked output_main_wrapper_start - 10 (outputs opening divs for the content)
 */
do_action('wpddb_before_main_content_wrapper');

/**
 * Hook: wpddb_before_main_content.
 *
 * @hooked output_content_wrapper - 10 (outputs opening divs for the content)
 */
do_action('wpddb_before_main_content');

?>
    <div class="wpddb-single-doctor-wrapper <?php echo esc_attr($page_layout); ?>">
		<?php if('left-sidebar'==$page_layout){ ?>
            <aside <?php Helper::sidebar_class($single_page_sidebar); ?>>
				<?php
				/**
				 * Hook: wpddb_doctor_details_booking.
				 *
				 * @hooked render_doctor_booking_form - 10
				 */
				do_action('wpddb_doctor_details_booking',get_the_ID());
				dynamic_sidebar($sidebar);
				?>
            </aside>
		<?php } ?>
        <div class="post-wrapper">
            <div id="post-<?php the_ID(); ?>" <?php post_class( 'doctor-single' ); ?>>
                <div class="single-doctor-inner">
                    <div class="doctor-top-content-wrapper">
                        <div class="doctor-thumb">
                            <?php
                            if ( has_post_thumbnail() ){
                                the_post_thumbnail($thumb_size);
                            } else {
                                // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                                echo '<img class="wp-post-image wpddb-media no-image" src="' . esc_url(wpddb()->get_assets_uri('public/img/no-image-1210x600.jpg')) . '" alt="'. the_title_attribute( array( 'echo'=> false ) ) .'">';
                            }
                            ?>
                        </div>
                        <div class="doctor-info">
                            <h2 class="entry-title"><?php the_title(); ?></h2>
                            <?php do_action('wpddb_doctor_info_meta',get_the_ID()); ?>
                        </div>
                    </div>

                    <div class="entry-content">
						<?php
                            the_content();
                            /**
                             * Hook: wpddb_doctor_after_content.
                             *
                             */
                            do_action('wpddb_doctor_after_content');
                            /**
                             * Hook: wpddb_doctor_schedule.
                             *
                             * @hooked render_doctor_schedule - 10
                             */
                            do_action('wpddb_doctor_schedule',get_the_ID());

                            do_action('wpddb_after_doctor_schedule',get_the_ID());
                            if ('left-sidebar' !==$page_layout && 'right-sidebar' !== $page_layout){
                                /**
                                 * Hook: wpddb_doctor_details_booking.
                                 *
                                 * @hooked render_doctor_booking_form - 10
                                 */
                                do_action('wpddb_doctor_details_booking', get_the_ID());
                            }

                        ?>
                    </div>
                </div>
            </div>
        </div>
		<?php if('right-sidebar'==$page_layout){ ?>
            <aside <?php Helper::sidebar_class($single_page_sidebar); ?>>
				<?php
				/**
				 * Hook: wpddb_doctor_details_booking.
				 *
				 * @hooked render_doctor_booking_form - 10
				 */
				do_action('wpddb_doctor_details_booking',get_the_ID());
				dynamic_sidebar($sidebar);
				?>
            </aside>
		<?php } ?>
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

