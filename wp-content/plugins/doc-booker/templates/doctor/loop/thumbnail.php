<?php
/**
 * @package doc-booker/templates/doctor/loop
 * @var array $args
 * @var int   $doctor_id
 * @version 1.0.0
 */

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}
$doctor_thumbnail_width  = absint(WpddbOptions::get_option('doctor_thumbnail_width', 'wpddb_doctor_settings')) ?: 570;
$doctor_thumbnail_height = absint(WpddbOptions::get_option('doctor_thumbnail_height', 'wpddb_doctor_settings')) ?: 400;
$doctor_thumbnail_crop   = esc_attr(WpddbOptions::get_option('doctor_thumbnail_hard_crop', 'wpddb_doctor_settings')) ?: 'hard';

$custom_thumb_size = [
	'width'  => $doctor_thumbnail_width,
	'height' => $doctor_thumbnail_height,
	'crop'   => $doctor_thumbnail_crop,
];

?>
<div class="doctor-thumb-wrapper">
	<div class="doctor-thumb">
		<?php if (has_post_thumbnail($doctor_id)){ ?>
			<a href="<?php echo esc_url(get_the_permalink($doctor_id)); ?>" class="wpddb-media">
				<?php
				$img = Helper::getFeatureImage($doctor_id,'wpddb_custom',$custom_thumb_size);
				Helper::print_html($img);
				?>
			</a>
		<?php } else {
            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
			echo '<a href="'. esc_url(get_the_permalink()) .'"><img class="wpddb-media default-media no-image" src="' . esc_url(wpddb()->get_assets_uri('public/img/no-image-570x400.jpg')) . '" alt="'. the_title_attribute( array( 'echo'=> false ) ) .'"/></a>';
		} ?>
	</div>
</div>