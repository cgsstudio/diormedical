<?php
/**
 * @package doc-booker/templates/clinic/loop
 * @var array $args
 * @var int   $clinic_id
 * @version 1.0.0
 */

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}
$clinic_thumbnail_width  = absint(WpddbOptions::get_option('clinic_thumbnail_width', 'wpddb_clinic_settings')) ?: 570;
$clinic_thumbnail_height = absint(WpddbOptions::get_option('clinic_thumbnail_height', 'wpddb_clinic_settings')) ?: 400;
$clinic_thumbnail_crop   = esc_attr(WpddbOptions::get_option('clinic_thumbnail_hard_crop', 'wpddb_clinic_settings')) ?: 'hard';

$custom_thumb_size = [
	'width'  => $clinic_thumbnail_width,
	'height' => $clinic_thumbnail_height,
	'crop'   => $clinic_thumbnail_crop,
];

?>
<div class="clinic-thumb-wrapper">
	<div class="clinic-thumb">
		<?php if (has_post_thumbnail($clinic_id)){ ?>
			<a href="<?php echo esc_url(get_the_permalink($clinic_id)); ?>" class="wpddb-media">
				<?php
				$img = Helper::getFeatureImage($clinic_id,'wpddb_custom',$custom_thumb_size);
				Helper::print_html($img);
				?>
			</a>
		<?php } else {
			// phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
			echo '<a href="'. esc_url(get_the_permalink()) .'"><img class="wpddb-media no-image" src="' . esc_url(wpddb()->get_assets_uri('public/img/no-image-570x400.jpg')) . '" alt="'. the_title_attribute( array( 'echo'=> false ) ) .'"/></a>';
		} ?>
	</div>
</div>