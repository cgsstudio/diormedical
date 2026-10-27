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

// The new cards (Layout 1 portrait, Layout 2 directory row) show initials when
// there is no photo. The portrait card also gives the photo a frame shaped like
// the image size setting, so every card in a row lines up, and puts the
// department and Pro's labels on it; the row's round photo needs neither.
$is_card     = \WpDreamers\WPDDB\Controllers\Hooks\TemplateHooks::$card;
$is_portrait = in_array( $is_card, [ 'archive', 'shortcode' ], true );
$frame       = $is_portrait && ! in_array( $doctor_thumbnail_crop, [ 'off', 'soft' ], true )
	? ' style="aspect-ratio:' . $doctor_thumbnail_width . ' / ' . $doctor_thumbnail_height . '"'
	: '';
?>
<div class="doctor-thumb-wrapper">
	<div class="doctor-thumb"<?php echo $frame; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from absint() values above. ?>>
		<?php if ( $is_card && ! has_post_thumbnail( $doctor_id ) ) { ?>
			<a href="<?php echo esc_url( get_the_permalink( $doctor_id ) ); ?>" class="wpddb-media" tabindex="-1" aria-hidden="true">
				<span class="default-media no-image doctor-initials" style="background:<?php echo esc_attr( Helper::avatar_gradient( $doctor_id ) ); ?>"><?php echo esc_html( \WpDreamers\WPDDB\Controllers\Model\Doctor::initials( $doctor_id ) ); ?></span>
			</a>
		<?php } elseif (has_post_thumbnail($doctor_id)){ ?>
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
		<?php
		if ( $is_portrait ) {
			/**
			 * Fires inside the photo frame of a portrait card: the department label,
			 * and with Pro the featured label and rating.
			 *
			 * @param int $doctor_id Doctor.
			 */
			do_action( 'wpddb_doctor_card_media', $doctor_id );
		}
		?>
	</div>
</div>
