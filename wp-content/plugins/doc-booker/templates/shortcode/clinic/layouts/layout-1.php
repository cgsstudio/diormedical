<?php
/**
 * @package WPDDocBooker
 * @var array $args
 * @var int   $wpddbclinic_id
 * @var array $custom_image_size
 * @version 1.0.0
 */


use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Clinic;


defined( 'ABSPATH' ) || exit;

?>
<div class="wpddb-shortcode-item clinic-item posts-item">
	<div class="clinic-thumb">
		<?php if (has_post_thumbnail()){ ?>
			<a href="<?php echo esc_url(get_the_permalink()); ?>" class="wpddb-media">
				<?php
				$img = Helper::getFeatureImage($wpddbclinic_id,'wpddb_custom',$custom_image_size);
				Helper::print_html($img);
				?>
			</a>
		<?php } else {
			// No photo: the clinic's initials on a coloured tile, the card image's shape.
			$ratio = ( ! empty( $custom_image_size['width'] ) && ! empty( $custom_image_size['height'] ) ) ? (int) $custom_image_size['width'] . ' / ' . (int) $custom_image_size['height'] : '570 / 400';
			echo '<a href="' . esc_url( get_the_permalink() ) . '" class="wpddb-media" tabindex="-1" aria-hidden="true"><span class="default-media no-image clinic-initials" style="aspect-ratio:' . esc_attr( $ratio ) . ';background:' . esc_attr( Helper::avatar_gradient( get_the_ID() ) ) . '">' . esc_html( \WpDreamers\WPDDB\Controllers\Model\Doctor::initials( get_the_ID() ) ) . '</span></a>';
		} ?>
	</div>
	<div class="clinic-content">
		<h3 class="wpddb-clinic-title"><a href="<?php echo esc_url(get_the_permalink()); ?>"><?php the_title(); ?></a></h3>
        <?php
            Clinic::clinic_hotline(get_the_ID());
            do_action('wpddb_clinic_shortcode_content', $args);
            do_action('wpddb_clinic_shortcode_map_btn', $args);
        ?>
	</div>
</div>
