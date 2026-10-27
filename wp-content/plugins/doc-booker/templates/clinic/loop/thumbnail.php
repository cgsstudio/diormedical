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
			// No photo: a coloured tile with the clinic's initials, the same idea
			// as the doctor cards, at the card image's shape.
			?>
			<a href="<?php echo esc_url( get_the_permalink( $clinic_id ) ); ?>" class="wpddb-media" tabindex="-1" aria-hidden="true">
				<span class="default-media no-image clinic-initials" style="aspect-ratio:<?php echo (int) $clinic_thumbnail_width . ' / ' . (int) $clinic_thumbnail_height; ?>;background:<?php echo esc_attr( Helper::avatar_gradient( $clinic_id ) ); ?>"><?php echo esc_html( \WpDreamers\WPDDB\Controllers\Model\Doctor::initials( $clinic_id ) ); ?></span>
			</a>
		<?php } ?>
	</div>
</div>