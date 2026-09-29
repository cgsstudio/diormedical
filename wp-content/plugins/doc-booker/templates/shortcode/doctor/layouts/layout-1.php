<?php
/**
 * @package WPDDocBooker
 * @var array $args
 * @var int   $wpddbdoctor_id
 * @var array $custom_image_size
 * @version 1.0.0
 */


use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Doctor;

defined( 'ABSPATH' ) || exit;

?>
<div class="wpddb-shortcode-item doctor-item posts-item">
	<div class="doctor-thumb">
		<?php if (has_post_thumbnail()){ ?>
			<a href="<?php echo esc_url(get_the_permalink()); ?>" class="wpddb-media">
				<?php
				$img = Helper::getFeatureImage($wpddbdoctor_id,'wpddb_custom',$custom_image_size);
				Helper::print_html($img);
				?>
			</a>
		<?php } else {
            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
			echo '<a href="'. esc_url(get_the_permalink()) .'"><img class="wpddb-media" src="' . esc_url(wpddb()->get_assets_uri('public/img/no-image-570x400.jpg')) . '" alt="'. the_title_attribute( array( 'echo'=> false ) ) .'"/></a>';
		} ?>
	</div>
	<div class="doctor-content">
        <?php do_action('wpddb_doctor_shortcode_before_title',$args); ?>
        <h3 class="wpddb-doctor-title"><a href="<?php echo esc_url(get_the_permalink()); ?>"><?php the_title(); ?></a></h3>
        <?php do_action('wpddb_doctor_shortcode_after_title',$args); ?>
        <?php do_action('wpddb_doctor_social_icons',$args); ?>
	</div>
</div>
