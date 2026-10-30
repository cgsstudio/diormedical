<?php

namespace WpDreamers\WPDDB\Traits;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

trait Constants {
	/**
	 * Main post_type.
	 *
	 * @var string
	 */

	public $post_type_doctor = 'wpddb_doctor';
	public $post_type_doctor_shortcode = 'wpd_doctor_shortcode';
	public $post_type_clinic = 'wpddb_clinic';
	public $post_type_clinic_shortcode = 'wpd_clinic_shortcode';

	/**
	 * @var string
	 */
	public $doctor_category = 'wpddb_doctor_department';
    public $doctor_endpoint_namespace = 'wpddb_get_doctors/v1';
    public $clinic_endpoint_namespace = 'wpddb_get_clinics/v1';
    public $wpddb_endpoint_namespace = 'wpddb_get_data/v1';
	/**
	 * Nonce id
	 *
	 * @var string
	 */
	public $nonceId = 'wpddb_wpnonce';
	/**
	 * Nonce Text
	 *
	 * @var string
	 */
	public $nonceText = 'wpddb_nonce';
	public $current_theme;
	public  $doctor_single_sidebar = 'wpddb-doctor-single-sidebar';
	public  $doctor_archive_sidebar = 'wpddb-doctor-archive-sidebar';
	public  $clinic_single_sidebar = 'wpddb-clinic-single-sidebar';
	public  $clinic_archive_sidebar = 'wpddb-clinic-archive-sidebar';
}