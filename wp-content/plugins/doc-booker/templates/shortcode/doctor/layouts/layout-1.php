<?php
/**
 * Doctor shortcode — portrait card. Same card as the Doctors page; the
 * shortcode's own hooks and settings (show department, colours, image size,
 * Pro fonts and pagination) apply to it.
 *
 * @package WPDDocBooker
 * @var array $args
 * @var int   $wpddbdoctor_id
 * @var array $custom_image_size
 * @version 2.0.0
 */

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Hooks\TemplateHooks;
use WpDreamers\WPDDB\Controllers\Model\Doctor;

defined( 'ABSPATH' ) || exit;

TemplateHooks::$card = 'shortcode';
$wpddb_w    = absint( $custom_image_size['width'] ?? 0 ) ?: 570;
$wpddb_h    = absint( $custom_image_size['height'] ?? 0 ) ?: 400;
$wpddb_crop = $custom_image_size['crop'] ?? 'hard';
$wpddb_frame = in_array( $wpddb_crop, [ 'off', 'soft' ], true ) ? '' : ' style="aspect-ratio:' . $wpddb_w . ' / ' . $wpddb_h . '"';
?>
<div class="wpddb-shortcode-item doctor-item posts-item">
	<div class="doctor-thumb-wrapper">
		<div class="doctor-thumb"<?php echo $wpddb_frame; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from absint() values above. ?>>
			<?php if ( has_post_thumbnail( $wpddbdoctor_id ) ) { ?>
				<a href="<?php echo esc_url( get_the_permalink( $wpddbdoctor_id ) ); ?>" class="wpddb-media">
					<?php Helper::print_html( Helper::getFeatureImage( $wpddbdoctor_id, 'wpddb_custom', $custom_image_size ) ); ?>
				</a>
			<?php } else { ?>
				<a href="<?php echo esc_url( get_the_permalink( $wpddbdoctor_id ) ); ?>" class="wpddb-media" tabindex="-1" aria-hidden="true">
					<span class="default-media no-image doctor-initials" style="background:<?php echo esc_attr( Helper::avatar_gradient( $wpddbdoctor_id ) ); ?>"><?php echo esc_html( Doctor::initials( $wpddbdoctor_id ) ); ?></span>
				</a>
			<?php } ?>
			<?php
			// The department label sits on the photo in this card.
			do_action( 'wpddb_doctor_shortcode_before_title', $args );
			do_action( 'wpddb_doctor_card_media', $wpddbdoctor_id );
			?>
		</div>
	</div>
	<div class="doctor-content">
		<h3 class="wpddb-doctor-title"><a href="<?php echo esc_url( get_the_permalink( $wpddbdoctor_id ) ); ?>"><?php echo esc_html( get_the_title( $wpddbdoctor_id ) ); ?></a></h3>
		<?php do_action( 'wpddb_doctor_shortcode_after_title', $args ); ?>
		<?php do_action( 'wpddb_doctor_social_icons', $args ); ?>
	</div>
	<?php do_action( 'wpddb_doctor_card_footer', $wpddbdoctor_id ); ?>
</div>
<?php
TemplateHooks::$card = false;
