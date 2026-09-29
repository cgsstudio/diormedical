<?php
/**
 * @package WpdDocBooker
 */


use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\WpddbOptions;


defined('ABSPATH') || exit;
global $post;

Helper::get_header($wp_version);

$page_layout            = WpddbOptions::get_option('clinic_single_page_layout','wpddb_clinic_settings') ?:'full-width';
$clinic_gallery_view    = WpddbOptions::get_option('details_gallery_view','wpddb_clinic_settings') ?:'slider';


$single_page_sidebar    = Clinic::is_clinic() ? 'clinic-sidebar':'';
$sidebar                = wpddb()->clinic_single_sidebar;
$gallery                = function_exists('wpddbp')
                            ? get_post_meta($post->ID, 'wpddb_clinic_gallery_images', true)
                            : [];
$thumb_size      ='wpddb_size1';
$wrapper_classes = [
                        'wpddb-single-clinic-wrapper',
                        $page_layout
                    ];

if ( ! empty($gallery) && is_array($gallery)  ) {
    if ( 'grid' == $clinic_gallery_view ) {
        $wrapper_classes[] = 'has-gallery-grid';
    }else{
        $wrapper_classes[] = 'has-gallery-slider';
    }
}

$wrapper_classes = apply_filters(
                        'wpddb_clinic_single_wrapper_classes',
                        $wrapper_classes,
                        $post,
                        $gallery
                    );

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
	<div class="<?php echo esc_attr(implode(' ', $wrapper_classes )); ?>">
		<?php if('left-sidebar'==$page_layout && Helper::is_active_sidebar($sidebar)===true){ ?>
			<aside <?php Helper::sidebar_class($single_page_sidebar); ?>>
				<?php
				dynamic_sidebar($sidebar);
				?>
			</aside>
		<?php } ?>
		<div class="post-wrapper">
			<div id="post-<?php the_ID(); ?>" <?php post_class( 'clinic-single' ); ?>>
				<div class="single-clinic-inner">
					<div class="clinic-thumb">
                     <?php
                        do_action( 'wpddb_clinic_thumbnail', get_the_ID(), $thumb_size );
					?>
					</div>
					<div class="entry-content">
						<h2 class="entry-title"><?php the_title(); ?></h2>
                        <?php
                        /**
                         * Hook: wpddb_clinic_after_title.
                         *
                         * @hooked render_clinic_meta - 10
                         */
                        do_action('wpddb_clinic_after_title',get_the_ID());
                        ?>
						<?php the_content(); ?>
                        <?php
                        /**
                         * Hook: wpddb_clinic_after_content.
                         *
                         * @hooked render_clinic_map - 10
                         */
                        do_action('wpddb_clinic_after_content',get_the_ID());
                        ?>
					</div>
				</div>
			</div>
		</div>
		<?php if('right-sidebar'==$page_layout && Helper::is_active_sidebar($sidebar)===true){ ?>
			<aside <?php Helper::sidebar_class($single_page_sidebar); ?>>
				<?php
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

