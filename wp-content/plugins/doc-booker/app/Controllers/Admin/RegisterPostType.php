<?php

namespace WpDreamers\WPDDB\Controllers\Admin;

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class RegisterPostType {
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_taxonomies' ], 5 );
		add_action( 'init', [ __CLASS__, 'register_post_types' ], 5 );
		add_filter( 'rest_api_allowed_post_types', [ __CLASS__, 'rest_api_allowed_post_types' ] );
		add_action( 'wpddb_flush_rewrite_rules', [ __CLASS__, 'flush_rewrite_rules' ] );
		add_filter( 'gutenberg_can_edit_post_type', [ __CLASS__, 'gutenberg_can_edit_post_type' ], 10, 2 );
		add_filter( 'use_block_editor_for_post_type', [ __CLASS__, 'gutenberg_can_edit_post_type' ], 10, 2 );
	}

	public static function rest_api_allowed_post_types( $post_types ) {
		$post_types[] = wpddb()->post_type_doctor;
		$post_types[] = wpddb()->post_type_clinic;

		return $post_types;
	}

	/**
	 * Flush rewrite rules.
	 */
	public static function flush_rewrite_rules() {
		flush_rewrite_rules();
	}


	public static function gutenberg_can_edit_post_type( $can_edit, $post_type ) {
		return ( wpddb()->post_type_doctor === $post_type || wpddb()->post_type_clinic === $post_type ) ? false : $can_edit;
	}

	public static function register_taxonomies() {
		if ( ! is_blog_installed() ) {
			return;
		}
		do_action( 'wpddb_register_taxonomy' );

        $doctor_category_base = WpddbOptions::get_option( 'doctor_category_base','wpddb_permalinks_settings');
        $doctor_category_base = $doctor_category_base ?:'wpddb_doctor_category';

		$cat_labels = [
			'name'                       => esc_html_x( 'Doctor Department', 'Taxonomy General Name', 'doc-booker' ),
			'singular_name'              => esc_html_x( 'Department', 'Taxonomy Singular Name', 'doc-booker' ),
			'menu_name'                  => esc_html__( 'Departments', 'doc-booker' ),
			'all_items'                  => esc_html__( 'All Departments', 'doc-booker' ),
			'parent_item'                => esc_html__( 'Parent Department', 'doc-booker' ),
			'parent_item_colon'          => esc_html__( 'Parent Department:', 'doc-booker' ),
			'new_item_name'              => esc_html__( 'New Department Name', 'doc-booker' ),
			'add_new_item'               => esc_html__( 'Add New Department', 'doc-booker' ),
			'edit_item'                  => esc_html__( 'Edit Department', 'doc-booker' ),
			'update_item'                => esc_html__( 'Update Department', 'doc-booker' ),
			'view_item'                  => esc_html__( 'View Department', 'doc-booker' ),
			'separate_items_with_commas' => esc_html__( 'Separate Departments with commas', 'doc-booker' ),
			'add_or_remove_items'        => esc_html__( 'Add or remove Departments', 'doc-booker' ),
			'choose_from_most_used'      => esc_html__( 'Choose from the most used', 'doc-booker' ),
			'popular_items'              => null,
			'search_items'               => esc_html__( 'Search Departments', 'doc-booker' ),
			'not_found'                  => esc_html__( 'Not Found', 'doc-booker' ),
		];

		$cat_args = [
			'labels'            => $cat_labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => false,
			'query_var'         => true,
			'rewrite'           => [
				'slug'         => $doctor_category_base,
				'with_front'   => false,
				'hierarchical' => true,
			]
		];

		register_taxonomy( wpddb()->doctor_category, wpddb()->post_type_doctor, apply_filters( 'wpddb_register_doctor_category_args', $cat_args ) );

		do_action( 'wpddb_after_register_taxonomy' );
	}

	public static function register_post_types() {

		if ( ! is_blog_installed() ) {
			return;
		}

		do_action( 'wpddb_register_post_type' );

        $doctor_base = WpddbOptions::get_option( 'doctor_base', 'wpddb_permalinks_settings' );
        $doctor_base = $doctor_base ?: 'wpddb_doctor';

        $clinic_base = WpddbOptions::get_option( 'clinic_base', 'wpddb_permalinks_settings' );
        $clinic_base = $clinic_base ?: 'wpddb_clinic';

        $doctors_page_id = Helper::get_page_id( 'doctors' );

        $clinics_page_id = Helper::get_page_id( 'clinics' );

        if ( current_theme_supports( 'wpddb' ) ) {
            $doctor_has_archive = $doctors_page_id && get_post( $doctors_page_id ) ? urldecode( get_page_uri( $doctors_page_id ) ) : 'doctors';
        } else {
            $doctor_has_archive = false;
        }

        if ( current_theme_supports( 'wpddb' ) ) {
            $clinic_has_archive = $clinics_page_id && get_post( $clinics_page_id ) ? urldecode( get_page_uri( $clinics_page_id ) ) : 'clinics';
        } else {
            $clinic_has_archive = false;
        }


		$labels         = [
			'name'               => esc_html_x( 'DocBooker', 'post type general name', 'doc-booker' ),
			'singular_name'      => esc_html_x( 'DocBooker', 'post type singular name', 'doc-booker' ),
			'add_new'            => esc_html_x( 'Add New', 'post', 'doc-booker' ),
			'add_new_item'       => esc_html__( 'Add New Doctor', 'doc-booker' ),
			'edit_item'          => esc_html__( 'Edit Doctor', 'doc-booker' ),
			'new_item'           => esc_html__( 'New Doctor', 'doc-booker' ),
			'all_items'          => esc_html__( 'All Doctors', 'doc-booker' ),
			'view_item'          => esc_html__( 'View Doctor', 'doc-booker' ),
			'search_items'       => esc_html__( 'Search Doctor', 'doc-booker' ),
			'not_found'          => esc_html__( 'No Doctors found', 'doc-booker' ),
			'not_found_in_trash' => esc_html__( 'No Doctor found in the Trash', 'doc-booker' ),
			'name_admin_bar'     => esc_html__( 'Doctor', 'doc-booker' ),
			'update_item'        => esc_html__( 'Update Doctor', 'doc-booker' ),
			'parent_item_colon'  => '',
			'menu_name'          => esc_html__( 'DocBooker', 'doc-booker' )
		];
		$doctor_support = [ 'title', 'thumbnail', 'editor', 'excerpt', 'page-attributes' ];


		$doctor_args = [
			'labels'              => $labels,
			'public'              => true,
			'menu_icon'           => WPDDB_URL . '/assets/admin/img/doctime-logo-22x22.png',
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'supports'            => $doctor_support,
			'hierarchical'        => false,
			'rewrite'             => [
				'slug'       => $doctor_base,
				'with_front' => false,
				'feeds'      => true,
			],
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => $doctor_has_archive,
			'show_in_rest'        => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
		];

		register_post_type( wpddb()->post_type_doctor, apply_filters( 'wpddb_register_doctor_post_type_args', $doctor_args ) );


        $clinic_post_type_label = WpddbOptions::get_option( 'clinic_post_label', 'wpddb_clinic_settings' );
        $clinic_post_type_label = $clinic_post_type_label ?: __( 'Clinic', 'doc-booker' );

		$clinic_support         = [ 'title', 'thumbnail', 'editor', 'excerpt', 'page-attributes' ];
		$clinic_labels          = array(
		    // Translators: %s is the post type name (e.g., "Clinic").
            'name' => sprintf( esc_html_x( '%s', 'post type general name', 'doc-booker' ), $clinic_post_type_label ), // phpcs:ignore WordPress.WP.I18n.NoEmptyStrings
            // Translators: %s is the post type name (e.g., "Clinic").
            'singular_name' => sprintf( esc_html_x( '%s', 'post type singular name', 'doc-booker' ), $clinic_post_type_label ), // phpcs:ignore WordPress.WP.I18n.NoEmptyStrings
			'menu_name'          => esc_html($clinic_post_type_label),
			'name_admin_bar'     => esc_html($clinic_post_type_label),
			'all_items'          => esc_html($clinic_post_type_label.''.__( 's', 'doc-booker' )),
			'add_new_item'       => esc_html( __('Add New ','doc-booker') . $clinic_post_type_label ),
			'add_new'            => esc_html__( 'Add New', 'doc-booker' ),
			'new_item'           => esc_html( __('New ','doc-booker') . $clinic_post_type_label),
			'edit_item'          => esc_html( __('Edit ','doc-booker') . $clinic_post_type_label ),
			'update_item'        => esc_html( __('Update ','doc-booker') . $clinic_post_type_label ),
			'view_item'          => esc_html( __('View ','doc-booker') . $clinic_post_type_label ),
			'search_items'       => esc_html( __('Search ','doc-booker')  . $clinic_post_type_label ),
			'not_found'          => esc_html( __('No ','doc-booker')  . strtolower( $clinic_post_type_label ) . __('s found','doc-booker') ),
			'not_found_in_trash' => esc_html(__( 'No ','doc-booker') . strtolower( $clinic_post_type_label ) . __('s found in Trash','doc-booker') ),
		);

		$clinic_args = array(
			'label'               => sprintf( "%s", esc_html( $clinic_post_type_label ) ),
			'description'         => sprintf( "%s %s", esc_html( $clinic_post_type_label ),__('Description','doc-booker') ),
			'labels'              => $clinic_labels,
			'supports'            => $clinic_support,
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . wpddb()->post_type_doctor,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => $clinic_has_archive,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'rewrite'             => [
				'slug'       => $clinic_base,
				'with_front' => false,
				'feeds'      => true,
			]
		);

		register_post_type( wpddb()->post_type_clinic, apply_filters( 'wpddb_clinic_register_post_type_args', $clinic_args ) );

		$doctor_shortcode_labels = [
			'all_items'          => esc_html__( 'Doctor Shortcode', 'doc-booker' ),
			'menu_name'          => esc_html__( 'Doctor Shortcode', 'doc-booker' ),
			'singular_name'      => esc_html__( 'Shortcode', 'doc-booker' ),
			'edit_item'          => esc_html__( 'Edit Shortcode', 'doc-booker' ),
			'new_item'           => esc_html__( 'New Shortcode', 'doc-booker' ),
			'add_new_item'       => esc_html__( 'Add New Shortcode', 'doc-booker' ),
			'view_item'          => esc_html__( 'View Shortcode', 'doc-booker' ),
			'search_items'       => esc_html__( 'Shortcode Locations', 'doc-booker' ),
			'not_found'          => esc_html__( 'No Shortcode found.', 'doc-booker' ),
			'not_found_in_trash' => esc_html__( 'No Shortcode found in trash.', 'doc-booker' ),
		];
		$doctor_shortcode_args   = array(
			'label'               => esc_html__( 'Doctor Shortcode', 'doc-booker' ),
			'description'         => esc_html__( 'Doctor Shortcode Generator', 'doc-booker' ),
			'labels'              => $doctor_shortcode_labels,
			'supports'            => [ 'title' ],
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . wpddb()->post_type_doctor,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => false,
			'capability_type'     => 'page',
		);

		register_post_type( wpddb()->post_type_doctor_shortcode, apply_filters( 'wpddb_doctor_shortcode_register_post_type_args', $doctor_shortcode_args ) );

		$clinic_shortcode_labels = [
			'all_items'          => esc_html__( 'Clinic Shortcode', 'doc-booker' ),
			'menu_name'          => esc_html__( 'Clinic Shortcode', 'doc-booker' ),
			'singular_name'      => esc_html__( 'Shortcode', 'doc-booker' ),
			'edit_item'          => esc_html__( 'Edit Shortcode', 'doc-booker' ),
			'new_item'           => esc_html__( 'New Shortcode', 'doc-booker' ),
			'add_new_item'       => esc_html__( 'Add New Shortcode', 'doc-booker' ),
			'view_item'          => esc_html__( 'View Shortcode', 'doc-booker' ),
			'search_items'       => esc_html__( 'Shortcode Locations', 'doc-booker' ),
			'not_found'          => esc_html__( 'No Shortcode found.', 'doc-booker' ),
			'not_found_in_trash' => esc_html__( 'No Shortcode found in trash.', 'doc-booker' ),
		];
		$clinic_shortcode_args   = array(
			'label'               => esc_html__( 'Clinic Shortcode', 'doc-booker' ),
			'description'         => esc_html__( 'Clinic Shortcode Generator', 'doc-booker' ),
			'labels'              => $clinic_shortcode_labels,
			'supports'            => [ 'title' ],
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . wpddb()->post_type_doctor,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => false,
			'capability_type'     => 'page',
		);

		register_post_type( wpddb()->post_type_clinic_shortcode, apply_filters( 'wpddb_clinic_shortcode_register_post_type_args', $clinic_shortcode_args ) );

		do_action( 'wpddb_after_register_post_type' );
	}
}