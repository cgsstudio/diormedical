<?php
/**
 * Templates page (Phase 6 — freemium Template Importer, FREE side).
 *
 * Adds a "Templates" submenu under the Doctor CPT menu that shows a catalog of
 * pre-composed medical-niche landing pages. Every template carries a Pro badge +
 * an Import button. Clicking Import:
 *   • Pro INACTIVE → an upsell popup (advertise Pro + Upgrade button) opens.
 *   • Pro ACTIVE   → the real import runs (Pro REST endpoint — built Pro-side).
 *
 * Pure FREE-plugin UI + catalog + gate. The actual import + template block-markup
 * live in doc-booker-pro. Detection via wpddb()->has_pro() (function_exists('wpddbp')).
 */

namespace WpDreamers\WPDDB\Controllers\Admin;

use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class TemplatesPage {
	use SingletonTrait;

	/** Screen hook suffix this page renders on (parent CPT + submenu slug). */
	const SLUG = 'wpddb-templates';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_submenu' ], 11 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ], 100 );
	}

	public function register_submenu() {
		add_submenu_page(
			'edit.php?post_type=' . wpddb()->post_type_doctor,
			esc_html__( 'Prebuilt Templates', 'doc-booker' ),
			esc_html__( 'Prebuilt Templates', 'doc-booker' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render_page' ],
			55
		);
	}

	public function render_page() {
		echo '<div class="wrap wpddb-templates-wrap"><div id="wpddb_templates_root"></div></div>';
	}

	public function enqueue( $hook ) {
		// Submenu of the wpddb_doctor CPT → hook "wpddb_doctor_page_wpddb-templates".
		if ( $hook !== wpddb()->post_type_doctor . '_page_' . self::SLUG ) {
			return;
		}
		$version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : WPDDB_VERSION;
		wp_enqueue_script(
			'wpd-templates-page-js',
			wpddb()->get_assets_uri( 'admin/js/wpd-templates-page.js' ),
			[ 'wp-element' ],
			$version,
			true
		);
		wp_enqueue_style(
			'wpd-templates-page-css',
			wpddb()->get_assets_uri( 'admin/css/wpd-templates-page.css' ),
			[],
			$version
		);
		wp_localize_script(
			'wpd-templates-page-js',
			'wpddbTemplatesParams',
			[
				'hasPro'     => wpddb()->has_pro(),
				'templates'  => $this->catalog(),
				// Pro-side import endpoint (registered by doc-booker-pro, Phase 6 Task 3).
				'importUrl'  => rest_url( 'doc-booker-blocks/v1/templates/import/start' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'upgradeUrl' => 'https://docbooker.wpdreamers.com/',
				// Step labels mirror the Pro import pipeline (animated in the modal).
				'steps'      => [
					[ 'key' => 'validate', 'label' => esc_html__( 'Validating template', 'doc-booker' ) ],
					[ 'key' => 'prepare',  'label' => esc_html__( 'Loading block markup', 'doc-booker' ) ],
					[ 'key' => 'images',   'label' => esc_html__( 'Processing images', 'doc-booker' ) ],
					[ 'key' => 'create',   'label' => esc_html__( 'Creating draft page', 'doc-booker' ) ],
					[ 'key' => 'finalize', 'label' => esc_html__( 'Finalising', 'doc-booker' ) ],
				],
				'i18n'       => [
					'eyebrow'        => esc_html__( '10 medical niche templates', 'doc-booker' ),
					'headingA'       => esc_html__( 'One click. ', 'doc-booker' ),
					'headingB'       => esc_html__( 'A whole site.', 'doc-booker' ),
					'subheading'     => esc_html__( 'Pick a niche, click Import, and a fully-editable draft page is created from the DocBooker block library. Doctors, clinics and departments pull your real content; everything else uses curated demo imagery.', 'doc-booker' ),
					'import'         => esc_html__( 'Import template', 'doc-booker' ),
					'hoverPreview'   => esc_html__( 'Hover to preview', 'doc-booker' ),
					'pro'            => esc_html__( 'Pro', 'doc-booker' ),
					'cancel'         => esc_html__( 'Cancel', 'doc-booker' ),
					'close'          => esc_html__( 'Close', 'doc-booker' ),
					'retry'          => esc_html__( 'Try again', 'doc-booker' ),
					'startImport'    => esc_html__( 'Start import', 'doc-booker' ),
					'importImages'   => esc_html__( 'Download demo images into the media library (slower)', 'doc-booker' ),
					'importingTitle' => esc_html__( 'Importing', 'doc-booker' ),
					'importingHint'  => esc_html__( 'Please keep this tab open until all steps complete.', 'doc-booker' ),
					'importingSteps' => esc_html__( 'Building your page', 'doc-booker' ),
					'successTitle'   => esc_html__( 'Template imported', 'doc-booker' ),
					'successSub'     => esc_html__( 'A draft page was created with every block ready to edit.', 'doc-booker' ),
					'editPage'       => esc_html__( 'Edit the page', 'doc-booker' ),
					'viewPage'       => esc_html__( 'Preview', 'doc-booker' ),
					'importAnother'  => esc_html__( 'Import another template', 'doc-booker' ),
					'errorTitle'     => esc_html__( 'Import failed', 'doc-booker' ),
					'errorBody'      => esc_html__( 'Something went wrong. Please try again.', 'doc-booker' ),
					'upsellTitle'    => esc_html__( 'Unlock page templates with Pro', 'doc-booker' ),
					'upsellBody'     => esc_html__( 'Page templates are a DocBooker Pro feature. Upgrade to import polished, niche-specific landing pages built from the DocBooker block library — then customise everything in the editor.', 'doc-booker' ),
					'feat1'          => esc_html__( '10 ready-made medical niche pages', 'doc-booker' ),
					'feat2'          => esc_html__( 'One-click import — a real, editable draft page', 'doc-booker' ),
					'feat3'          => esc_html__( 'Built from the full DocBooker Pro block library', 'doc-booker' ),
					'upgrade'        => esc_html__( 'Upgrade to Pro', 'doc-booker' ),
					'later'          => esc_html__( 'Maybe later', 'doc-booker' ),
				],
			]
		);
	}

	/** The 10 niche templates (all Pro). Thumbnails are accent gradients for now. */
	private function catalog() {
		$niches = [
			[ 'id' => 'general-hospital',   'name' => 'General Hospital',     'niche' => 'Multi-specialty',   'accent' => '#0d8a7d', 'excerpt' => 'Comprehensive care, world-class facilities — departments, doctors, clinics & booking.' ],
			[ 'id' => 'dental-clinic',      'name' => 'Dental Clinic',        'niche' => 'Dentistry',         'accent' => '#1a73e8', 'excerpt' => 'Brighter smiles every day — treatments, smile gallery & inline booking.' ],
			[ 'id' => 'pediatrics',         'name' => 'Pediatrics',           'niche' => 'Children\'s health', 'accent' => '#e8852a', 'excerpt' => 'Caring for your child\'s tomorrow — what-to-expect, schedule & quick book.' ],
			[ 'id' => 'dermatology',        'name' => 'Dermatology',          'niche' => 'Skin care',         'accent' => '#c54a52', 'excerpt' => 'Healthy skin, healthy you — treatments, results gallery & booking.' ],
			[ 'id' => 'eye-care',           'name' => 'Eye Care',             'niche' => 'Ophthalmology',     'accent' => '#3f7a64', 'excerpt' => 'See the world more clearly — technology, LASIK & screening countdown.' ],
			[ 'id' => 'cardiology',         'name' => 'Cardiology',           'niche' => 'Heart care',        'accent' => '#a3263b', 'excerpt' => 'Expert heart care when it matters most — success stories & screening.' ],
			[ 'id' => 'orthopedics',        'name' => 'Orthopedics',          'niche' => 'Bone & joint',      'accent' => '#5a6b7a', 'excerpt' => 'Move without limits — recovery journey, specialists & booking.' ],
			[ 'id' => 'mental-health',      'name' => 'Mental Health',        'niche' => 'Wellness',          'accent' => '#6a3aa0', 'excerpt' => 'Care that listens first — calm, supportive, judgement-free.' ],
			[ 'id' => 'womens-health',      'name' => 'Women\'s Health',      'niche' => 'OB-GYN',            'accent' => '#b8568a', 'excerpt' => 'Care through every stage — stages of care, specialists & booking.' ],
			[ 'id' => 'diagnostic-imaging', 'name' => 'Diagnostic Imaging',   'niche' => 'Radiology',         'accent' => '#2a6b8a', 'excerpt' => 'Clarity you can count on — modalities, technology & report lookup.' ],
		];
		foreach ( $niches as &$n ) {
			$n['isPro']     = true;
			$n['palette']   = [ $n['accent'], '#14b8a6', '#a26a3a', '#0b1727', '#f5f1e8' ];
			// Full-page demo screenshot (scrolls on hover). Missing files fall back to
			// the accent gradient in the card UI. Generated into assets/admin/templates/previews/.
			$n['thumbnail'] = wpddb()->get_assets_uri( 'admin/templates/previews/' . $n['id'] . '.jpg' );
		}
		unset( $n );
		return $niches;
	}
}
