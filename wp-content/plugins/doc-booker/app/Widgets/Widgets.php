<?php

namespace WpDreamers\WPDDB\Widgets;



use WpDreamers\WPDDB\Traits\Constants;
use WpDreamers\WPDDB\Traits\SingletonTrait;
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

class Widgets {
	use SingletonTrait;
	use Constants;
	public function __construct() {
		add_action( 'widgets_init', [$this, 'register_sidebar'] );
        add_action( 'widgets_init', [__CLASS__, 'register_widget'] );
		add_action( 'init', [$this, 'widget_support'] );
	}
	public  function register_sidebar() {
		
		if ( !is_registered_sidebar( $this->doctor_single_sidebar ) ) {
			register_sidebar( [
				'name'          => apply_filters( 'wpddb_single_sidebar_title', esc_html__( 'DocBooker - Doctor Single Sidebar', 'doc-booker' ) ),
				'id'            => $this->doctor_single_sidebar,
				'description'   => esc_html__( 'Add widgets on doctor single page', 'doc-booker' ),
				'before_widget' => '<div class="widget wpddb-widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<div class="wpddb-widget-heading"><h2>',
				'after_title'   => '</h2></div>',
			] );
		}
		if ( !is_registered_sidebar( $this->doctor_archive_sidebar ) ) {
			register_sidebar( [
				'name'          => apply_filters( 'wpddb_archive_sidebar_title', esc_html__( 'DocBooker - Doctor Archive Sidebar', 'doc-booker' ) ),
				'id'            => $this->doctor_archive_sidebar,
				'description'   => esc_html__( 'Add widgets on doctor archive page', 'doc-booker' ),
				'before_widget' => '<div class="widget wpddb-widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<div class="wpddb-widget-heading"><h2>',
				'after_title'   => '</h2></div>',
			] );
		}
		if ( !is_registered_sidebar( $this->clinic_single_sidebar ) ) {
			register_sidebar( [
				'name'          => apply_filters( 'wpddb_single_sidebar_title', esc_html__( 'DocBooker - Clinic Single Sidebar', 'doc-booker' ) ),
				'id'            => $this->clinic_single_sidebar,
				'description'   => esc_html__( 'Add widgets on clinic single page', 'doc-booker' ),
				'before_widget' => '<div class="widget wpddb-widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<div class="wpddb-widget-heading"><h2>',
				'after_title'   => '</h2></div>',
			] );
		}
		if ( !is_registered_sidebar( $this->clinic_archive_sidebar ) ) {
			register_sidebar( [
				'name'          => apply_filters( 'wpddb_archive_sidebar_title', esc_html__( 'DocBooker - Clinic Archive Sidebar', 'doc-booker' ) ),
				'id'            => $this->clinic_archive_sidebar,
				'description'   => esc_html__( 'Add widgets on clinic archive page', 'doc-booker' ),
				'before_widget' => '<div class="widget wpddb-widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<div class="wpddb-widget-heading"><h2>',
				'after_title'   => '</h2></div>',
			] );
		}

	}
	public function widget_support() {
		add_filter( 'elementor/widgets/wordpress/widget_args', [$this, 'elementor_wordpress_widget_support'] );
	}
    public static function register_widget(  ) {
        $instanceObj = new CustomWidgetsInit();
        $instanceObj->custom_widgets();
    }
	public  function elementor_wordpress_widget_support() {

		$args['before_widget'] = '<div class="wpddb-widget">';
		$args['after_widget'] = '</div>';
		$args['before_title'] = '<h2>';
		$args['after_title'] = '</h2>';

		return $args;
	}
}