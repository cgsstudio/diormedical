<?php
/**
 * Plugin Name: Dedicato — Self-Discovery and Recovery Workbook
 * Description: Adds Dr. Marshall's Self-Discovery and Recovery Workbook to the books section on his biography page.
 * Version: 1.0.0
 * Author: Dedicato Treatment Center
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The book assets are kept tied to their original client-provided Drive files so
 * the front and back covers cannot be confused with another workbook.
 */
final class Dedicato_Self_Discovery_Workbook {
	const PAGE_ID   = 8611;
	const PAGE_SLUG = 'about-dr-keith-marshall-fullstory';

	const SYNOPSIS_SOURCE_ID = '1z_UdjEsIiTJnCBbwCU4_UCk7f0KNxRcu';
	const FRONT_COVER_ID     = '1JfmP4Y2UwhBsamNpPDd4wlsUE7KaSEpp';
	const BACK_COVER_ID      = '1XUkP2l08FA8HSwOoSdOmPhH28VJoRLVV';

	public static function init() {
		add_filter( 'elementor/frontend/the_content', array( __CLASS__, 'append_to_elementor_content' ), 20 );
		add_filter( 'the_content', array( __CLASS__, 'append_to_content' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
	}

	/**
	 * Load the book's styles only on Dr. Marshall's biography/books page.
	 */
	public static function enqueue_styles() {
		if ( ! self::is_books_page() ) {
			return;
		}

		wp_enqueue_style(
			'dedicato-self-discovery-workbook',
			plugin_dir_url( __FILE__ ) . 'dedicato-self-discovery-workbook.css',
			array(),
			'1.0.0'
		);
	}

	/**
	 * Elementor renders the page through its own content filter. This hook keeps
	 * the new book next to the existing books without changing either existing
	 * book's content.
	 */
	public static function append_to_elementor_content( $content ) {
		if ( ! self::is_books_page() ) {
			return $content;
		}

		return self::insert_book( $content );
	}

	/**
	 * Fallback for a classic-editor or non-Elementor version of the same page.
	 */
	public static function append_to_content( $content ) {
		if ( ! self::is_books_page() ) {
			return $content;
		}

		return self::insert_book( $content );
	}

	private static function is_books_page() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		$page_id = (int) get_queried_object_id();
		if ( self::PAGE_ID === $page_id ) {
			return true;
		}

		return is_page( self::PAGE_SLUG );
	}

	private static function insert_book( $content ) {
		$marker = 'data-dedicato-book="self-discovery-recovery-workbook"';
		if ( false !== strpos( $content, $marker ) ) {
			return $content;
		}

		$book = self::book_markup();

		// Keep the new workbook in the existing books area, immediately before
		// the reviews that follow it. If that heading is changed later, the
		// fallback still keeps the book visible at the end of the page content.
		$review_heading_pattern = '/(<h[1-6]\b[^>]*>.*?Reviewed\s+by\s+the\s+Industry.*?<\/h[1-6]>)/is';
		$updated_content        = preg_replace( $review_heading_pattern, $book . '$1', $content, 1, $replacements );

		if ( 1 === $replacements ) {
			return $updated_content;
		}

		return $content . $book;
	}

	private static function cover_preview_url( $file_id ) {
		// The client supplied PDF files, so use Drive's embeddable PDF viewer
		// rather than treating them as raster images.
		return 'https://drive.google.com/file/d/' . rawurlencode( $file_id ) . '/preview';
	}

	private static function cover_view_url( $file_id ) {
		return 'https://drive.google.com/file/d/' . rawurlencode( $file_id ) . '/view?usp=sharing';
	}

	private static function book_markup() {
		$front_preview = esc_url( self::cover_preview_url( self::FRONT_COVER_ID ) );
		$back_preview  = esc_url( self::cover_preview_url( self::BACK_COVER_ID ) );
		$front_view    = esc_url( self::cover_view_url( self::FRONT_COVER_ID ) );
		$back_view     = esc_url( self::cover_view_url( self::BACK_COVER_ID ) );

		$book = <<<'HTML'
<section class="dior-self-discovery-book" data-dedicato-book="self-discovery-recovery-workbook" aria-labelledby="dior-self-discovery-book-title">
	<div class="dior-self-discovery-book__grid">
		<div class="dior-self-discovery-book__covers" aria-label="Self-Discovery and Recovery Workbook covers">
			<figure class="dior-self-discovery-book__cover">
				<div class="dior-self-discovery-book__cover-frame">
					<iframe src="{{FRONT_PREVIEW}}" title="Self-Discovery and Recovery Workbook front cover" loading="lazy" allowfullscreen></iframe>
				</div>
				<figcaption><a href="{{FRONT_VIEW}}" target="_blank" rel="noopener noreferrer">View front cover</a></figcaption>
			</figure>
			<figure class="dior-self-discovery-book__cover">
				<div class="dior-self-discovery-book__cover-frame">
					<iframe src="{{BACK_PREVIEW}}" title="Self-Discovery and Recovery Workbook back cover" loading="lazy" allowfullscreen></iframe>
				</div>
				<figcaption><a href="{{BACK_VIEW}}" target="_blank" rel="noopener noreferrer">View back cover</a></figcaption>
			</figure>
		</div>

		<div class="dior-self-discovery-book__details">
			<p class="dior-self-discovery-book__status">COMING SOON</p>
			<h3 id="dior-self-discovery-book-title">Self-Discovery and Recovery Workbook</h3>
			<p class="dior-self-discovery-book__subtitle">An Evidence-Based Guide to Substance Use Prevention, Recovery, Healthy Coping, and Sustainable Change</p>

			<details class="dior-self-discovery-book__synopsis">
				<summary>Click Here to Read the Synopsis</summary>
				<div class="dior-self-discovery-book__synopsis-content">
					<h4>BOOK SYNOPSIS</h4>
					<h5>Self-Discovery and Recovery Workbook</h5>

					<p><strong>Self-Discovery and Recovery Workbook</strong> is an evidence-informed, structured tool designed for counselors, therapists, peer-support professionals, substance use professionals, and other helping professionals to work collaboratively with individuals recovering from substance use disorders, strengthening substance use prevention, addressing negative behavioral patterns, and challenging unhealthy patterns of thinking. The workbook may also be used independently by individuals who are prepared to engage in meaningful self-examination and have the capacity to be <strong>rigorously honest with themselves</strong>. Although working with another person can be extremely valuable—because others can often help us recognize what we may be unable or unwilling to see in ourselves—an individual who is willing to engage honestly in the process can also complete this work independently.</p>

					<p>At the heart of this workbook is a fundamental principle: <strong>honesty is the taproot of recovery</strong> <strong>and meaningful change.</strong> Recovery requires more than simply stopping substance use or making intermittent, precarious, negative behavioral changes. Sustainable change requires a willingness to examine oneself honestly, recognize unhealthy patterns and distorted thinking, accept personal responsibility, develop healthier coping skills, and learn to deal with <strong>life on life’s terms without</strong> <strong>returning to substances or destructive behaviors as the solution.</strong></p>

					<p>A central framework of the workbook is <strong>Introspection, Retrospection, and Correction</strong>— looking honestly within ourselves, looking back to understand the experiences, thinking, choices, behaviors, and patterns that have influenced our lives, and then using that understanding to make meaningful corrections moving forward. The purpose is not self-blame. It is to develop greater self-awareness, accountability, emotional regulation, healthier coping, and responsibility for those areas of our lives that we have the ability to change.</p>

					<p>The workbook can be incorporated into treatment and recovery planning because it addresses core areas of sustainable recovery and behavioral change, including relapse prevention; identification of internal and external triggers; <strong>breaking patterns of denial, such as</strong> <strong>minimization, avoidance, and rationalization</strong>; recognizing cognitive distortions and unhealthy thinking; developing healthy coping skills; strengthening self-esteem and personal responsibility; building social support; understanding sponsorship and mentorship; examining powerlessness and restoration to sanity; and exploring faith and spiritual awareness. Through guided reading, writing, self-assessment, reflection, discussion, and practical assignments, individuals are encouraged to move beyond passive participation and become <strong>proactive participants in their</strong> <strong>own recovery, personal growth, and human development.</strong></p>

					<p>This workbook also reflects <strong>Dr. Marshall’s own perspective from both sides of recovery—his</strong> <strong>lived experience with severe addiction and recovery and his subsequent education and</strong> <strong>professional career in behavioral health and addiction treatment.</strong> Readers can learn more about his journey in the <strong>“About the Author &amp; Personal Journey”</strong> section at the back of the workbook. After years of developing and utilizing these tools, Dr. Marshall has personally witnessed, alongside other clinicians, the profound impact that structured and honest self-examination can have in helping individuals recognize unhealthy patterns, move beyond blame, take greater responsibility for their lives, and develop the courage to make meaningful and sustainable changes.</p>

					<p><strong>The purpose of this workbook is to provide structure, direction, accountability, and</strong> <strong>meaningful work that helps move an individual from merely talking about change to</strong> <strong>actively participating in it—through Introspection, Retrospection, and Correction.</strong></p>
				</div>
			</details>
		</div>
	</div>
</section>
HTML;

		return strtr(
			$book,
			array(
				'{{FRONT_PREVIEW}}' => $front_preview,
				'{{BACK_PREVIEW}}'  => $back_preview,
				'{{FRONT_VIEW}}'    => $front_view,
				'{{BACK_VIEW}}'     => $back_view,
			)
		);
	}
}

Dedicato_Self_Discovery_Workbook::init();
