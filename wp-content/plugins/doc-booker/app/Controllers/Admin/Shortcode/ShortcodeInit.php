<?php

namespace WpDreamers\WPDDB\Controllers\Admin\Shortcode;



use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class ShortcodeInit{
    use SingletonTrait;
    public function __construct() {
        //doctor shortcode
        add_action( 'edit_form_after_title', [ $this, 'after_title_text' ] );
        add_filter('manage_edit-'.wpddb()->post_type_doctor_shortcode.'_columns' ,[$this,'wpddb_doctor_shortcode_columns']);
        add_action( 'manage_'.wpddb()->post_type_doctor_shortcode.'_posts_custom_column', [ $this, 'wpddb_doctor_shortcode_posts_custom_column' ], 10, 2 );

        //clinic shortcode
        add_action( 'edit_form_after_title', [ $this, 'clinic_after_title_text' ] );
        add_filter('manage_edit-'.wpddb()->post_type_clinic_shortcode.'_columns' ,[$this,'wpddb_clinic_shortcode_columns']);
        add_action( 'manage_'.wpddb()->post_type_clinic_shortcode.'_posts_custom_column', [ $this, 'wpddb_clinic_shortcode_posts_custom_column' ], 10, 2 );
    }
    public  function wpddb_doctor_shortcode_columns($columns) {
        $shortcode = [ wpddb()->post_type_doctor_shortcode => esc_html__( 'Shortcode', 'doc-booker' ) ];

        return array_slice( $columns, 0, 2, true ) + $shortcode + array_slice( $columns, 1, null, true );
    }
    public  function wpddb_clinic_shortcode_columns($columns) {
        $shortcode = [ wpddb()->post_type_clinic_shortcode => esc_html__( 'Shortcode', 'doc-booker' ) ];

        return array_slice( $columns, 0, 2, true ) + $shortcode + array_slice( $columns, 1, null, true );
    }
    public  function after_title_text( $post ) {

        if ( wpddb()->post_type_doctor_shortcode !== $post->post_type ) {
            return;
        }

        $html  = null;
        $html .= '<div class="postbox" style="margin-bottom: 0;"><div class="inside">';
        $html .= '<p><input type="text" onfocus="this.select();" readonly="readonly" value="[wpddbdoctor id=&quot;' . $post->ID . '&quot; title=&quot;' . $post->post_title . '&quot;]" class="large-text code wpd-doc-booker-sc-input">
		<input type="text" onfocus="this.select();" readonly="readonly" value="&#60;&#63;php echo do_shortcode( &#39;[wpddbdoctor id=&quot;' . $post->ID . '&quot; title=&quot;' . $post->post_title . '&quot;]&#39; ); &#63;&#62;" class="large-text code wpd-doc-booker-sc-input">
		</p>';
        $html .= '</div></div>';

        Helper::print_html($html,true);
    }
    public  function clinic_after_title_text( $post ) {

        if ( wpddb()->post_type_clinic_shortcode !== $post->post_type ) {
            return;
        }

        $html  = null;
        $html .= '<div class="postbox" style="margin-bottom: 0;"><div class="inside">';
        $html .= '<p><input type="text" onfocus="this.select();" readonly="readonly" value="[wpddbclinic id=&quot;' . $post->ID . '&quot; title=&quot;' . $post->post_title . '&quot;]" class="large-text code wpd-doc-booker-sc-input">
		<input type="text" onfocus="this.select();" readonly="readonly" value="&#60;&#63;php echo do_shortcode( &#39;[wpddbclinic id=&quot;' . $post->ID . '&quot; title=&quot;' . $post->post_title . '&quot;]&#39; ); &#63;&#62;" class="large-text code wpd-doc-booker-sc-input">
		</p>';
        $html .= '</div></div>';

        Helper::print_html($html,true);
    }
    public  function wpddb_doctor_shortcode_posts_custom_column( $column ) {

        switch ( $column ) {
            case wpddb()->post_type_doctor_shortcode:
                echo '<input type="text" onfocus="this.select();" readonly="readonly" value="[wpddbdoctor id=&quot;' . esc_attr(get_the_ID()) . '&quot; title=&quot;' . esc_html( get_the_title() ) . '&quot;]" class="large-text code wpd-doc-booker-sc-input">';
                break;
            default:
                break;
        }

    }
    public  function wpddb_clinic_shortcode_posts_custom_column( $column ) {

        switch ( $column ) {
            case wpddb()->post_type_clinic_shortcode:
                echo '<input type="text" onfocus="this.select();" readonly="readonly" value="[wpddbclinic id=&quot;' . esc_attr(get_the_ID()) . '&quot; title=&quot;' . esc_html( get_the_title() ) . '&quot;]" class="large-text code wpd-doc-booker-sc-input">';
                break;
            default:
                break;
        }

    }
}