<?php

namespace WpDreamers\WPDDB\Controllers\Model;
use WpDreamers\WPDDB\Controllers\Helper\Helper;

defined( 'ABSPATH' ) || exit;
class Clinic {
	/**
	 * Whether a clinic takes bookings: it exists and is published (not a
	 * draft or in the Trash).
	 */
	public static function is_open( $clinic_id ) {
		$clinic_id = (int) $clinic_id;
		return $clinic_id > 0 && get_post_type( $clinic_id ) === wpddb()->post_type_clinic && get_post_status( $clinic_id ) === 'publish';
	}

	public static function the_title( $post_id ) {
		echo esc_html( get_the_title( $post_id ) );
	}

	public static function get_the_title( $post_id ) {
		return get_the_title( $post_id );
	}

	public static function the_content( $post_id ) {
		echo esc_html( get_the_content( $post_id ) );
	}

	public static function get_the_content( $post_id ) {
		return get_the_content( $post_id );
	}
	public static function is_clinic(){
		return apply_filters( 'wpddb_is_clinic', self::is_clinics() || self::is_single_clinic() );
	}
	public static function is_clinics() {
		return apply_filters( 'wpddb_is_clinic_page', is_post_type_archive( wpddb()->post_type_clinic ) || is_page( Helper::get_page_id( 'clinics' ) ) );
	}
	public static function is_single_clinic() {
		return is_singular( [ wpddb()->post_type_clinic ] );
	}
	public static function get_clinics() {
		$clinic_list = [];
		$clinics =  get_posts(
			[
				'post_type'     => wpddb()->post_type_clinic,
				'post_status'   =>'publish',
				'posts_per_page' => -1
			]
		);
		if (!empty($clinics)){
			foreach ($clinics as $clinic){
				$clinic_list[]=[
					'id' => $clinic->ID,
					'name' => ! empty( $clinic->post_title ) ? $clinic->post_title : '#' . $clinic->ID
				];
			}
		}
		return $clinic_list;
	}
    public static function clinic_hotline( $post_id ) {
        $clinic_hotline = get_post_meta( $post_id, 'wpddb_clinic_hotline', true );
        if ($clinic_hotline){
            ?>
            <div class="wpddb-clinic-hotline">
                <span class="label"><?php esc_html_e('Hotline:','doc-booker'); ?></span>
                <a class="item" href="tel:<?php echo esc_attr($clinic_hotline); ?>"><?php Helper::print_html($clinic_hotline);?></a>
            </div>
        <?php }
    }

    public static function clinic_details_button( $post_id,$btn_text = 'View Location Map' ) {
        $details_link=get_the_permalink($post_id);
        $address         = get_post_meta( $post_id, 'wpddb_clinic_address', true );
        if($address){
            ?>
            <div class="clinic-details-btn">
                <a id="wpddb-primary-btn" class="wpddb-primary-btn" href="<?php echo esc_url($details_link); ?>"><?php echo esc_html($btn_text); ?></a>
            </div>
        <?php }
    }
}