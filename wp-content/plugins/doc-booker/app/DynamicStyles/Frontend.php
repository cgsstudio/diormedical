<?php

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
$primary_color            = Helper::get_primary_color();
$secondary_color          = Helper::get_secondary_color();
$border_color             = Helper::get_border_color();
$doctor_title_color       = WpddbOptions::get_option( 'wpddb_doctor_title_color', 'wpddb_style_settings' ) ?: '';
$doctor_content_color     = WpddbOptions::get_option( 'wpddb_doctor_content_color', 'wpddb_style_settings' ) ?: '';
$doctor_designation_color = WpddbOptions::get_option( 'wpddb_doctor_designation_color', 'wpddb_style_settings' ) ?: '';
$doctor_speciality_color  = WpddbOptions::get_option( 'wpddb_doctor_speciality_color', 'wpddb_style_settings' ) ?: '';
$doctor_workplace_color   = WpddbOptions::get_option( 'wpddb_doctor_workplace_color', 'wpddb_style_settings' ) ?: '';
$doctor_degree_color      = WpddbOptions::get_option( 'wpddb_doctor_degree_color', 'wpddb_style_settings' ) ?: '';
$clinic_title_color       = WpddbOptions::get_option( 'wpddb_clinic_title_color', 'wpddb_style_settings' ) ?: '';
$clinic_content_color     = WpddbOptions::get_option( 'wpddb_clinic_content_color', 'wpddb_style_settings' ) ?: '';
$clinic_bg_color          = WpddbOptions::get_option( 'wpddb_clinic_bg_color', 'wpddb_style_settings' ) ?: '';
$doctor_bg_color          = WpddbOptions::get_option( 'wpddb_doctor_bg_color', 'wpddb_style_settings' ) ?: '';
?>
:root {
--wpddb-primary-color: <?php echo esc_html( $primary_color ? $primary_color : '#5d3dfd' ); ?>;
--wpddb-secondary-color: <?php echo esc_html( $secondary_color ? $secondary_color : '#ebf3fc' ); ?>;
--wpddb-border-color: <?php echo esc_html( $border_color ? $border_color : '#dedede' ); ?>;
}
<?php if ( $doctor_title_color ) {
    ?>
    .wpddb-single-doctor-wrapper .doctor-info .entry-title,
    .wpddb-doctor-items .wpddb-doctor-title a,
    .wpddbdoctor-items .wpddb-doctor-title a {
        color:<?php echo esc_html( $doctor_title_color ); ?>!important;
    }
<?php } ?>
<?php if ( $doctor_content_color ) {
    ?>
    .wpddbdoctor-items  .wpddb-doctor-des,
    .wpddb-doctor-items  p{
        color:<?php echo esc_html( $doctor_content_color ); ?>;
    }
<?php } ?>
<?php if ( $doctor_designation_color ) {
    ?>
    .wpddb-doctor-items .doctor-designation,
    .wpddbdoctor-items .doctor-designation{
        color:<?php echo esc_html( $doctor_designation_color ); ?>;
    }
<?php } ?>
<?php if ( $doctor_speciality_color ) {
    ?>
    .wpddb-single-doctor-wrapper .doctor-info .wpddb-doctor-speciality{
        color:<?php echo esc_html( $doctor_speciality_color ); ?>;
    }
<?php } ?>
<?php if ( $doctor_workplace_color ) {
    ?>
    .wpddb-single-doctor-wrapper .doctor-info .wpddb-doctor-workplace{
        color:<?php echo esc_html( $doctor_workplace_color ); ?>;
    }
<?php } ?>
<?php if ( $doctor_degree_color ) {
    ?>
    .wpddb-single-doctor-wrapper .doctor-info .wpddb-doctor-degree{
        color:<?php echo esc_html( $doctor_degree_color ); ?>;
    }
<?php } ?>
<?php if ( $doctor_bg_color ) {
    ?>
    .wpddb-doctor-items .doctor-item,
    .wpddbdoctor-items .doctor-item{
        background-color:<?php echo esc_html( $doctor_bg_color ); ?>;
    }
<?php } ?>
<?php if ( $clinic_title_color ) {
    ?>
    .wpddb-single-clinic-wrapper .single-clinic-inner .entry-title,
    .wpddb-clinic-items .wpddb-clinic-title a,
    .wpddbclinic-items .wpddb-clinic-title a {
        color:<?php echo esc_html( $clinic_title_color ); ?>!important;
    }
<?php } ?>
<?php if ( $clinic_content_color ) {
    ?>
    .wpddbclinic-items  .wpddb-clinic-des,
    .wpddb-clinic-items p{
        color:<?php echo esc_html( $clinic_content_color ); ?>;
    }
<?php } ?>
<?php if ( $clinic_bg_color ) {
    ?>
    .wpddb-clinic-items .clinic-item,
    .wpddbclinic-items .clinic-item{
        background-color:<?php echo esc_html( $clinic_bg_color ); ?>;
    }
<?php } ?>
