<?php
/**
 * Front-end fixes for pages imported via the DocBooker Templates importer.
 *
 * Two layers of cross-theme support (mirrors gym-builder-block-tools, broadened):
 *   1. Per-theme post meta (wpddbp_theme_layout_meta(), applied at import) asks
 *      Astra/GeneratePress/Kadence/OceanWP/Neve/Blocksy/Sydney/PBF/Zakra for a
 *      full-width, no-sidebar, no-title page.
 *   2. THIS file — a scoped <head> stylesheet + body class that work on ANY
 *      theme (including ones without meta support and block/FSE themes):
 *        • hide the theme-rendered page title across ~12 popular themes
 *        • cancel block-theme auto styles that fight full-bleed sections
 *          (theme.json blockGap, .has-global-padding, spacing-preset margins)
 *        • guarantee align:full sections break out edge-to-edge
 *        • neutralise common content containers / sidebars on classic themes
 *
 * All CSS is scoped to `body.wpddbp-imported-template`, present only when the
 * page has `_wpddbp_template_source` meta (set by the importer's create step).
 * Every other page on the site is untouched.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpddbp_imported_template_body_class' ) ) {
	function wpddbp_imported_template_body_class( $classes ) {
		if ( ! is_singular( 'page' ) ) {
			return $classes;
		}
		$post = get_post();
		if ( ! $post ) {
			return $classes;
		}
		if ( get_post_meta( $post->ID, '_wpddbp_template_source', true ) ) {
			$classes[] = 'wpddbp-imported-template';
		}
		return $classes;
	}
}
add_filter( 'body_class', 'wpddbp_imported_template_body_class' );

if ( ! function_exists( 'wpddbp_imported_template_head' ) ) {
	function wpddbp_imported_template_head() {
		if ( ! is_singular( 'page' ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		if ( ! get_post_meta( $post->ID, '_wpddbp_template_source', true ) ) {
			return;
		}
		?>
<style id="wpddbp-imported-page-fixes">
/* ── 1. Hide the theme-rendered page title (cross-theme) ── */
.wpddbp-imported-template .entry-header .entry-title,
.wpddbp-imported-template .page .entry-title,
.wpddbp-imported-template .wp-block-post-title,
.wpddbp-imported-template .entry-header.alignwide,
.wpddbp-imported-template .entry-header.alignfull,
.wpddbp-imported-template header.entry-header,
.wpddbp-imported-template .page-header,
.wpddbp-imported-template .page-title,
.wpddbp-imported-template .single-featured-image-header,
.wpddbp-imported-template .ast-single-post .entry-header,        /* Astra */
.wpddbp-imported-template .ast-container > .entry-header,
.wpddbp-imported-template .generate-page-header,                 /* GeneratePress */
.wpddbp-imported-template .page-hero,
.wpddbp-imported-template .entry-header-wrap,                    /* Kadence */
.wpddbp-imported-template .kb-title,
.wpddbp-imported-template .owp-title,                            /* OceanWP */
.wpddbp-imported-template #title-container,
.wpddbp-imported-template .nv-page-title-wrap,                   /* Neve */
.wpddbp-imported-template .nv-single-post-wrap .entry-title,
.wpddbp-imported-template .ct-page-title,                        /* Blocksy */
.wpddbp-imported-template .page-hero-section,
.wpddbp-imported-template .hero-section .entry-title,
.wpddbp-imported-template .header-title,                         /* Hestia */
.wpddbp-imported-template .page-header-content,                  /* generic / Twenty Twenty */
.wpddbp-imported-template .wpbf-page-title,                      /* Page Builder Framework */
.wpddbp-imported-template .zak-page-header,                      /* Zakra */
.wpddbp-imported-template .sydney-hero-area .page-title,         /* Sydney */
.wpddbp-imported-template .elementor-page-title{display:none !important;}
.wpddbp-imported-template .entry-header,
.wpddbp-imported-template .page-header{margin:0 !important;padding:0 !important;border:0 !important;}
.wpddbp-imported-template .entry-content,
.wpddbp-imported-template .site-main .entry-content{margin-top:0 !important;padding-top:0 !important;}

/* ── 1b. Responsive safety net: never let a single block force horizontal page
   scroll on tablet/mobile. `clip` (not `hidden`) so position:sticky still works. ── */
body.wpddbp-imported-template{overflow-x:clip;}

/* ── 2. Block/FSE themes: cancel auto styles that fight full-bleed sections ── */
/* theme.json blockGap between post-content children */
body.wpddbp-imported-template :where(.entry-content,.wp-block-post-content).is-layout-constrained > *{margin-block-start:0;}
/* useRootPaddingAwareAlignments horizontal padding (would double our wrapper padding) */
body.wpddbp-imported-template :where(.entry-content,.wp-block-post-content).has-global-padding,
body.wpddbp-imported-template :where(.entry-content,.wp-block-post-content) .has-global-padding{padding-left:0;padding-right:0;}
/* inline spacing-preset margin-top on <main>/post content */
body.wpddbp-imported-template main,
body.wpddbp-imported-template main.wp-block-group,
body.wpddbp-imported-template :where(.entry-content,.wp-block-post-content){margin-top:0 !important;}
/* guarantee our align:full sections reach the viewport edges inside a constrained content area */
body.wpddbp-imported-template :where(.entry-content,.wp-block-post-content) > .alignfull{
	max-width:none;width:auto;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);}

/* ── 3. Classic-theme content containers / sidebars (full-width fallback) ── */
/* Kadence content container padding */
body.wpddbp-imported-template .entry-content-wrap{padding:0 !important;}
/* Make the main content column full-width + drop the sidebar on common classic themes
   (safe on a full-page landing template). */
body.wpddbp-imported-template #primary,
body.wpddbp-imported-template #main > .content-area,
body.wpddbp-imported-template .content-area,
body.wpddbp-imported-template .nv-single-post-wrap,
body.wpddbp-imported-template .ct-container{max-width:none !important;width:100% !important;float:none !important;margin-left:0 !important;margin-right:0 !important;}
body.wpddbp-imported-template #secondary,
body.wpddbp-imported-template .widget-area.sidebar,
body.wpddbp-imported-template .sidebar-primary{display:none !important;}
/* Storefront / Twenty Twenty content max-width */
body.wpddbp-imported-template .hentry,
body.wpddbp-imported-template .post-inner{max-width:none !important;padding:0 !important;}
</style>
		<?php
	}
}
add_action( 'wp_head', 'wpddbp_imported_template_head', 99 );
