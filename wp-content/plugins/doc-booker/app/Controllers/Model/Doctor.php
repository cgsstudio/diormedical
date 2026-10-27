<?php

namespace WpDreamers\WPDDB\Controllers\Model;
use WpDreamers\WPDDB\Controllers\Helper\Helper;

defined( 'ABSPATH' ) || exit;
class Doctor {
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
	public static function get_categories_array(  ) {
		$categories_list = [];
		$terms = get_terms([
			'taxonomy' => wpddb()->doctor_category,
			'hide_empty' => false
		]);
		if (!empty($terms)){
			foreach ($terms as $term){
				$categories_list[$term->term_id] = $term->name;
			}
		}

		return apply_filters("wpddb_array_doctor_category_list",$categories_list);
	}
	public static function get_category_html_format( $post_id ) {
		$term_lists = get_the_terms( $post_id, wpddb()->doctor_category );
		$i          = 1;
		if ( $term_lists ) {
			?>
			<div class="doctor-department">
				<?php
				foreach ( $term_lists as $term_list ) {
					$link = get_term_link( $term_list->term_id, wpddb()->doctor_category ); ?>
					<?php if ( $i > 1 ) {
						echo esc_html( ', ' );
					} ?>
					<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $term_list->name ); ?></a>
					<?php $i ++;
				} ?>
			</div>
		<?php }
	}
	public static function is_doctor(){
		return apply_filters( 'wpddb_is_doctor', self::is_doctors() || self::is_doctor_taxonomy() || self::is_single_doctor() );
	}

	public static function is_doctors() {
		return apply_filters( 'wpddb_is_doctor_page', is_post_type_archive( wpddb()->post_type_doctor ) || is_page( Helper::get_page_id( 'doctors' ) ) );
	}

	public static function is_doctor_taxonomy() {
		return is_tax( get_object_taxonomies( wpddb()->post_type_doctor ) );
	}
	public static function is_single_doctor() {
		return is_singular( [ wpddb()->post_type_doctor ] );
	}
    public static function doctor_designation( $post_id ) {
        $doctor_designation = get_post_meta( $post_id, 'wpddb_doctor_designation', true );
        if ($doctor_designation){
            ?>
            <div class="doctor-designation"><?php Helper::print_html($doctor_designation); ?></div>
        <?php }
    }


	/**
	 * English names of the weekdays the doctor works, Monday first.
	 *
	 * @param int $doctor_id Doctor.
	 *
	 * @return string[]
	 */
	public static function working_days( $doctor_id ) {
		$schedule = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );
		$days     = [];
		foreach ( is_array( $schedule ) ? $schedule : [] as $day ) {
			$open = array_filter( is_array( $day['clinics'] ?? null ) ? $day['clinics'] : [], fn( $c ) => Clinic::is_open( $c['id'] ?? 0 ) );
			if ( ! empty( $day['available'] ) && $open && is_string( $day['day'] ?? null ) ) {
				$days[] = ucfirst( strtolower( $day['day'] ) );
			}
		}
		$order = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
		return array_values( array_intersect( $order, $days ) );
	}

	/**
	 * Short, translated day name for an English weekday ("Monday" -> "Mon").
	 *
	 * @param string $day English weekday.
	 *
	 * @return string
	 */
	public static function short_day( $day ) {
		$names = [
			'Monday'    => __( 'Mon', 'doc-booker' ),
			'Tuesday'   => __( 'Tue', 'doc-booker' ),
			'Wednesday' => __( 'Wed', 'doc-booker' ),
			'Thursday'  => __( 'Thu', 'doc-booker' ),
			'Friday'    => __( 'Fri', 'doc-booker' ),
			'Saturday'  => __( 'Sat', 'doc-booker' ),
			'Sunday'    => __( 'Sun', 'doc-booker' ),
		];
		return $names[ $day ] ?? $day;
	}

	/**
	 * Initials for a doctor without a photo ("Dr. Sarah Khan" -> "SK").
	 *
	 * @param int $doctor_id Doctor.
	 *
	 * @return string
	 */
	public static function initials( $doctor_id ) {
		$name  = preg_replace( '/^dr\.?\s*/i', '', get_the_title( $doctor_id ) );
		$parts = preg_split( '/\s+/', trim( $name ) );
		$out   = '';
		foreach ( array_slice( array_filter( $parts ), 0, 2 ) as $part ) {
			$out .= mb_substr( $part, 0, 1 );
		}
		return mb_strtoupper( $out ) ?: '?';
	}

}