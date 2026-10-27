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
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrite_rules' ], 99 );
		add_filter( 'gutenberg_can_edit_post_type', [ __CLASS__, 'gutenberg_can_edit_post_type' ], 10, 2 );
		add_filter( 'use_block_editor_for_post_type', [ __CLASS__, 'gutenberg_can_edit_post_type' ], 10, 2 );
	}

	public static function rest_api_allowed_post_types( $post_types ) {
		$post_types[] = wpddb()->post_type_doctor;
		$post_types[] = wpddb()->post_type_clinic;

		return $post_types;
	}

	/**
	 * Ask for the rewrite rules to be rebuilt on the next request.
	 *
	 * Flushing on the spot is too early wherever this is called from: on
	 * activation the post types are not registered yet, so the rules would be
	 * saved without them and every doctor and clinic URL would 404.
	 */
	public static function flush_rewrite_rules() {
		update_option( 'wpddb_flush_rewrite_rules', 'yes' );
	}

	/**
	 * Rebuild the rules once, after the post types and taxonomy have registered.
	 */
	public static function maybe_flush_rewrite_rules() {
		if ( 'yes' !== get_option( 'wpddb_flush_rewrite_rules' ) ) {
			return;
		}

		delete_option( 'wpddb_flush_rewrite_rules' );
		// Soft: the doctor and clinic rules live in the database, so .htaccess is left alone.
		flush_rewrite_rules( false );
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
			'name'                       => esc_html__( 'Departments', 'doc-booker' ),
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
			// The logo (calendar, medical cross, stethoscope) as SVG, so WordPress
			// paints it in the admin colour scheme, on hover and when open.
			'menu_icon'           => 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" fill-rule="evenodd" d="M3.6 4h10a2 2 0 0 1 2 2v3.2h-1.6V8.4H3.2v7.2c0 .5.4.9.9.9h5.4V18H3.6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><rect fill="#a7aaad" x="4.8" y="2" width="1.6" height="4" rx=".8"/><rect fill="#a7aaad" x="10.8" y="2" width="1.6" height="4" rx=".8"/><path fill="#a7aaad" d="M7.6 9.4h1.6c.2 0 .4.2.4.4v1.4H11c.2 0 .4.2.4.4v1.6c0 .2-.2.4-.4.4H9.6V15c0 .2-.2.4-.4.4H7.6c-.2 0-.4-.2-.4-.4v-1.4H5.8c-.2 0-.4-.2-.4-.4v-1.6c0-.2.2-.4.4-.4h1.4V9.8c0-.2.2-.4.4-.4z"/><path fill="#a7aaad" d="M15.2 13.2h1.4v1.5a4 4 0 0 1-4 4h-.4a4 4 0 0 1-4-4v-.3h1.4v.3a2.6 2.6 0 0 0 2.6 2.6h.4a2.6 2.6 0 0 0 2.6-2.6z"/><circle fill="#a7aaad" cx="15.9" cy="11.6" r="2.1"/></svg>' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- an inline SVG icon.
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
			'add_new'            => esc_html__( 'Add New', 'doc-booker' ),
		);
		// Whole sentences, like the doctor labels: pieces glued around the
		// label ("No " + "clinic" + "s found") can't be translated. The
		// clinic label has no setting of its own, so it is "Clinic" unless a
		// site stored another word, which then keeps the old wording.
		if ( in_array( $clinic_post_type_label, [ 'Clinic', __( 'Clinic', 'doc-booker' ) ], true ) ) {
			$clinic_labels = array_merge( $clinic_labels, [
				'name'               => esc_html__( 'Clinics', 'doc-booker' ),
				'singular_name'      => esc_html__( 'Clinic', 'doc-booker' ),
				'menu_name'          => esc_html__( 'Clinics', 'doc-booker' ),
				'name_admin_bar'     => esc_html__( 'Clinic', 'doc-booker' ),
				'all_items'          => esc_html__( 'Clinics', 'doc-booker' ),
				'add_new_item'       => esc_html__( 'Add New Clinic', 'doc-booker' ),
				'new_item'           => esc_html__( 'New Clinic', 'doc-booker' ),
				'edit_item'          => esc_html__( 'Edit Clinic', 'doc-booker' ),
				'update_item'        => esc_html__( 'Update Clinic', 'doc-booker' ),
				'view_item'          => esc_html__( 'View Clinic', 'doc-booker' ),
				'search_items'       => esc_html__( 'Search Clinics', 'doc-booker' ),
				'not_found'          => esc_html__( 'No clinics found', 'doc-booker' ),
				'not_found_in_trash' => esc_html__( 'No clinics found in Trash', 'doc-booker' ),
			] );
		} else {
			$clinic_labels = array_merge( $clinic_labels, [
				'all_items'          => esc_html( $clinic_post_type_label ),
				'add_new_item'       => esc_html( $clinic_post_type_label ),
				'new_item'           => esc_html( $clinic_post_type_label ),
				'edit_item'          => esc_html( $clinic_post_type_label ),
				'update_item'        => esc_html( $clinic_post_type_label ),
				'view_item'          => esc_html( $clinic_post_type_label ),
				'search_items'       => esc_html( $clinic_post_type_label ),
				'not_found'          => esc_html__( 'Nothing found.', 'doc-booker' ),
				'not_found_in_trash' => esc_html__( 'Nothing found in Trash.', 'doc-booker' ),
			] );
		}

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