<?php
/**
 * Front-end page handler for the Patient Online Report Portal. Swaps the
 * configured page's content for a React mount when visited.
 */

namespace WpDreamers\WPDDBP\Controllers\PatientPortal;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class Page {
	use SingletonTrait;

	public function __construct() {
		add_filter( 'the_content', [ $this, 'inject_mount' ], 999 );
		add_filter( 'body_class',  [ $this, 'body_class' ] );
	}

	public static function get_page_id() {
		$page_settings = get_option( 'wpddb_page_settings', [] );
		return isset( $page_settings['patient_report_portal'] ) ? (int) $page_settings['patient_report_portal'] : 0;
	}

	public function is_portal_page() {
		if ( ! is_singular( 'page' ) ) return false;
		$page_id = self::get_page_id();
		return $page_id > 0 && (int) get_the_ID() === $page_id;
	}

	public function inject_mount( $content ) {
		if ( ! $this->is_portal_page() ) return $content;
		if ( strpos( $content, 'wpddb-patient-portal-root' ) !== false ) return $content;
		return '<div id="wpddb-patient-portal-root"></div>';
	}

	public function body_class( $classes ) {
		if ( $this->is_portal_page() ) {
			$classes[] = 'wpddb-patient-portal-page';
		}
		return $classes;
	}
}
