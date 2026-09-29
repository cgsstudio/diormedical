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

}