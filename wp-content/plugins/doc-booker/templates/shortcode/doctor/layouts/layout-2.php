<?php
/**
 * Doctor shortcode — directory row. Same row as the Doctors page; the
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

TemplateHooks::$card = 'row';
?>
<div class="wpddb-shortcode-item doctor-item posts-item">
	<div class="doctor-row">
		<div class="doctor-thumb-wrapper">
			<div class="doctor-thumb">
				<?php if ( has_post_thumbnail( $wpddbdoctor_id ) ) { ?>
					<a href="<?php echo esc_url( get_the_permalink( $wpddbdoctor_id ) ); ?>" class="wpddb-media">
						<?php Helper::print_html( Helper::getFeatureImage( $wpddbdoctor_id, 'wpddb_custom', $custom_image_size ) ); ?>
					</a>
				<?php } else { ?>
					<a href="<?php echo esc_url( get_the_permalink( $wpddbdoctor_id ) ); ?>" class="wpddb-media" tabindex="-1" aria-hidden="true">
						<span class="default-media no-image doctor-initials" style="background:<?php echo esc_attr( Helper::avatar_gradient( $wpddbdoctor_id ) ); ?>"><?php echo esc_html( Doctor::initials( $wpddbdoctor_id ) ); ?></span>
					</a>
				<?php } ?>
			</div>
		</div>
		<div class="doctor-content">
			<div class="doctor-card-tags"><?php
				do_action( 'wpddb_doctor_shortcode_before_title', $args );
				do_action( 'wpddb_doctor_card_media', $wpddbdoctor_id );
			?></div>
			<h3 class="wpddb-doctor-title"><a href="<?php echo esc_url( get_the_permalink( $wpddbdoctor_id ) ); ?>"><?php echo esc_html( get_the_title( $wpddbdoctor_id ) ); ?></a></h3>
			<?php do_action( 'wpddb_doctor_shortcode_after_title', $args ); ?>
			<?php do_action( 'wpddb_doctor_social_icons', $args ); ?>
			<?php TemplateHooks::doctor_days( $wpddbdoctor_id ); ?>
		</div>
		<?php do_action( 'wpddb_doctor_card_footer', $wpddbdoctor_id ); ?>
	</div>
</div>
<?php
TemplateHooks::$card = false;
