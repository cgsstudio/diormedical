<?php
/**
 * DocBooker Pro — Gutenberg blocks bootstrap.
 *
 * Runtime entry point for the blocks suite. Required (only when present) from the
 * Pro plugin's main file:
 *
 *     if ( file_exists( __DIR__ . '/build/blocks/bootstrap.php' ) ) {
 *         require_once __DIR__ . '/build/blocks/bootstrap.php';
 *     }
 *
 * Source lives at `blocks-src/bootstrap.php`; webpack's CopyWebpackPlugin copies
 * it to `build/blocks/bootstrap.php`, so at runtime:
 *   __DIR__              = .../doc-booker-pro/build/blocks
 *   dirname( __DIR__ )   = .../doc-booker-pro/build
 *
 * Standalone rule (architecture §3): everything here is namespaced `wpddbp_` /
 * `doc-booker-blocks` and never references the reference blocks plugin.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

/* ─────────────────────────────────────────────────────────────────────────
   Parent (free DocBooker) gate.

   The blocks are a Pro feature and Pro requires the free DocBooker plugin
   (`wpddb()` is its runtime helper). IMPORTANT: this file is required at
   Pro-plugin LOAD time, and the active-plugins order can place doc-booker-pro
   BEFORE doc-booker — so `wpddb()` is often NOT defined yet at this point.
   We therefore must NOT gate (early-return) at load time; instead the hooked
   callbacks below re-check the parent at `init` / `wp_enqueue_scripts`, by which
   point every active plugin has loaded. When the parent is genuinely absent the
   blocks simply don't register — DocBooker Pro's own Dependencies controller
   (app/Controllers/Dependencies.php) already surfaces the actionable
   "DocBooker (free) is required" admin notice, so we do NOT add a second one.
   The same goes for a DocBooker too old for Pro, or an inactive licence: Pro
   then never fires `wpddbp_loaded`, and the blocks stay off with it.
   ───────────────────────────────────────────────────────────────────────── */
if ( ! function_exists( 'wpddbp_blocks_parent_active' ) ) {
	function wpddbp_blocks_parent_active() {
		return function_exists( 'wpddb' ) && did_action( 'wpddbp_loaded' );
	}
}

/* ─────────────────────────────────────────────────────────────────────────
   Eager-load shared helpers so their REST/hook registrations fire and their
   functions are globally available before any block renders. Each require is
   guarded so a partial build (mid-phase) never fatals.
   ───────────────────────────────────────────────────────────────────────── */
$wpddbp_blocks_build = dirname( __DIR__ ); // .../build

foreach (
	array(
		$wpddbp_blocks_build . '/utils/wpddbp_generate_block_css.php', // CSS dispatcher (Phase 1, Task 4)
		$wpddbp_blocks_build . '/utils/doctors-book-button.php',       // per-doctor booking button (Phase 1, Task 5)
		$wpddbp_blocks_build . '/utils/doctors-rest.php',             // Doctors block data + REST routes (Phase 3)
		$wpddbp_blocks_build . '/utils/departments-rest.php',         // Departments block data + REST routes (Phase 3)
		$wpddbp_blocks_build . '/utils/awards-rest.php',             // Doctor Awards block data + REST routes (Phase 3)
		$wpddbp_blocks_build . '/utils/clinics-rest.php',            // Clinics block data + REST routes (Phase 4)
		$wpddbp_blocks_build . '/utils/imported-template-fixes.php',   // imported-page fixes (Phase 6)
		$wpddbp_blocks_build . '/admin/template-importer.php',         // Template Importer REST: start/step (Phase 6)
	) as $wpddbp_blocks_file
) {
	if ( file_exists( $wpddbp_blocks_file ) ) {
		require_once $wpddbp_blocks_file;
	}
}
unset( $wpddbp_blocks_file );

/* ─────────────────────────────────────────────────────────────────────────
   Register all blocks from the generated manifest.
   ───────────────────────────────────────────────────────────────────────── */
if ( ! function_exists( 'wpddbp_blocks_register_blocks' ) ) {
	function wpddbp_blocks_register_blocks() {
		if ( ! wpddbp_blocks_parent_active() ) {
			return; // free DocBooker not active — don't register (no duplicate notice).
		}
		$blocks_dir    = __DIR__;                                   // .../build/blocks
		$manifest_file = dirname( __DIR__ ) . '/blocks-manifest.php'; // .../build/blocks-manifest.php

		if ( ! file_exists( $manifest_file ) ) {
			return;
		}

		if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
			wp_register_block_types_from_metadata_collection( $blocks_dir, $manifest_file );
			return;
		}

		if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
			wp_register_block_metadata_collection( $blocks_dir, $manifest_file );
		}

		$manifest_data = require $manifest_file;
		foreach ( array_keys( $manifest_data ) as $block_type ) {
			register_block_type( $blocks_dir . "/{$block_type}" );
		}
	}
}
add_action( 'init', 'wpddbp_blocks_register_blocks' );

/* ─────────────────────────────────────────────────────────────────────────
   Editor-script translations.

   Make every block's JS strings (editor labels and front-end text)
   translatable: after the blocks register (init:10), loop the registered
   doc-booker-pro/* block types and point each editor script handle at the
   plugin's /languages .json files (built by `npm run makejson`). Mirrors the
   wp_set_script_translations wiring the React admin apps use.
   ───────────────────────────────────────────────────────────────────────── */
if ( ! function_exists( 'wpddbp_blocks_set_script_translations' ) ) {
	function wpddbp_blocks_set_script_translations() {
		if ( ! function_exists( 'wp_set_script_translations' ) || ! wpddbp_blocks_parent_active() ) {
			return;
		}
		$languages = dirname( __DIR__, 2 ) . '/languages'; // .../doc-booker-pro/languages
		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block_type ) {
			if ( 0 !== strpos( $name, 'doc-booker-pro/' ) ) {
				continue;
			}
			// Front-end view scripts print text to visitors too (booking steps,
			// trust lines), so they need the same translations as the editor.
			$handles = array_merge(
				(array) ( $block_type->editor_script_handles ?? array() ),
				(array) ( $block_type->view_script_handles ?? array() ),
				(array) ( $block_type->script_handles ?? array() )
			);
			foreach ( array_unique( $handles ) as $handle ) {
				wp_set_script_translations( $handle, 'doc-booker-pro', $languages );
			}
		}
	}
}
add_action( 'init', 'wpddbp_blocks_set_script_translations', 20 );

/* ─────────────────────────────────────────────────────────────────────────
   Register the editor block category so all DocBooker blocks group together.
   ───────────────────────────────────────────────────────────────────────── */
if ( ! function_exists( 'wpddbp_blocks_register_category' ) ) {
	function wpddbp_blocks_register_category( $categories ) {
		foreach ( $categories as $cat ) {
			if ( isset( $cat['slug'] ) && 'doc-booker-blocks' === $cat['slug'] ) {
				return $categories;
			}
		}
		array_unshift(
			$categories,
			array(
				'slug'  => 'doc-booker-blocks',
				'title' => __( 'DocBooker Blocks', 'doc-booker-pro' ),
				'icon'  => null,
			)
		);
		return $categories;
	}
}
add_filter( 'block_categories_all', 'wpddbp_blocks_register_category', 10, 1 );

/* ─────────────────────────────────────────────────────────────────────────
   Per-instance dynamic CSS + Google Fonts on singular views.

   parse_blocks() only returns attributes that DIFFER from defaults, so we merge
   the registered block.json defaults back in before handing them to the CSS
   generator. Only blocks with a non-empty blockId emit CSS (the selector hook).
   ───────────────────────────────────────────────────────────────────────── */
if ( ! function_exists( 'wpddbp_blocks_inline_css_on_singular' ) ) {
	function wpddbp_blocks_inline_css_on_singular() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$blocks      = parse_blocks( $post->post_content );
		// Raw customCSS is an escape hatch for people who may already post any
		// markup; for everyone else it is dropped.
		$author_may_style = user_can( (int) $post->post_author, 'unfiltered_html' );
		$css         = '';
		$fonts       = array();
		$brand_fonts = false;
		// Blocks that render in the editorial Fraunces + Manrope type system.
		$editorial_blocks = array( 'doc-booker-pro/advanced-heading', 'doc-booker-pro/call-to-action', 'doc-booker-pro/info-box', 'doc-booker-pro/countdown', 'doc-booker-pro/slider', 'doc-booker-pro/faq', 'doc-booker-pro/services', 'doc-booker-pro/process-steps', 'doc-booker-pro/office-hours', 'doc-booker-pro/hero', 'doc-booker-pro/doctors', 'doc-booker-pro/departments', 'doc-booker-pro/doctor-awards', 'doc-booker-pro/doctor-fee-badge', 'doc-booker-pro/clinics', 'doc-booker-pro/quick-book', 'doc-booker-pro/doctor-schedule', 'doc-booker-pro/booking-form', 'doc-booker-pro/doctor-stats', 'doc-booker-pro/testimonials' );

		$walk = function ( $blocks ) use ( &$walk, &$css, &$fonts, &$brand_fonts, $editorial_blocks, $author_may_style ) {
			foreach ( $blocks as $block ) {
				if ( ! empty( $block['blockName'] ) && 0 === strpos( $block['blockName'], 'doc-booker-pro/' ) ) {
					if ( in_array( $block['blockName'], $editorial_blocks, true ) ) {
						$brand_fonts = true;
					}
					$registry   = WP_Block_Type_Registry::get_instance();
					$btype      = $registry->get_registered( $block['blockName'] );
					$attrs_full = $block['attrs'] ?? array();

					if ( $btype && ! empty( $btype->attributes ) ) {
						foreach ( $btype->attributes as $attr_key => $attr_schema ) {
							if ( ! array_key_exists( $attr_key, $attrs_full ) && array_key_exists( 'default', $attr_schema ) ) {
								$attrs_full[ $attr_key ] = $attr_schema['default'];
							}
						}
					}

					// The id becomes a CSS selector: keep it to plain id characters.
					$attrs_full['blockId'] = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $attrs_full['blockId'] ?? '' ) );
					if ( ! $author_may_style ) {
						unset( $attrs_full['customCSS'] );
					}

					if ( function_exists( 'wpddbp_generate_block_css' ) && ! empty( $attrs_full['blockId'] ) ) {
						$css .= wpddbp_generate_block_css( $attrs_full, $attrs_full['blockId'], $block['blockName'] );
					}

					foreach ( $attrs_full as $attr_value ) {
						if ( is_array( $attr_value ) && ! empty( $attr_value['family'] ) ) {
							$family = trim( preg_replace( '/[^A-Za-z0-9 ]/', '', (string) $attr_value['family'] ) );
							if ( '' !== $family ) {
								$fonts[] = $family;
							}
						}
					}
				}

				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};

		$walk( $blocks );

		if ( '' !== $css ) {
			wp_register_style( 'wpddbp-block-style', false );
			wp_enqueue_style( 'wpddbp-block-style' );
			// Block attributes end up in this CSS. Valid CSS never needs "<", and
			// without it nothing can close the <style> element and start markup.
			wp_add_inline_style( 'wpddbp-block-style', str_replace( '<', '', wpddbp_optimized_css( $css ) ) );
		}

		if ( ! empty( $fonts ) ) {
			$fonts       = array_unique( $fonts );
			$font_query  = array();
			foreach ( $fonts as $font ) {
				$font_query[] = str_replace( ' ', '+', $font ) . ':wght@300;400;500;700;900';
			}
			wp_enqueue_style(
				'wpddbp-google-fonts',
				'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $font_query ) . '&display=swap',
				array(),
				null
			);
		}

		// Editorial brand fonts (Fraunces + Manrope) for the type-driven blocks.
		if ( $brand_fonts ) {
			wp_enqueue_style(
				'wpddbp-brand-fonts',
				'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..600;1,9..144,300..600&family=Manrope:wght@300..700&display=swap',
				array(),
				null
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'wpddbp_blocks_inline_css_on_singular', 20 );

/* Minify inline CSS: strip comments + collapse whitespace. */
if ( ! function_exists( 'wpddbp_optimized_css' ) ) {
	function wpddbp_optimized_css( $css ) {
		$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
		$css = str_replace( array( "\r\n", "\r", "\n", "\t" ), ' ', $css );
		return trim( $css );
	}
}
