<?php
/**
 * Template Importer — shared utilities (DocBooker Pro, Phase 6).
 *
 * Mirrors gym-builder-block-tools' `template-utils.php` 1:1, renamed to the
 * `wpddbp_` namespace + `doc-booker-blocks` REST namespace (architecture §3:
 * duplicate + rename, never import the reference plugin).
 *
 * No runtime hooks here — just functions reused by:
 *   - the build-time composer scripts (`templates/_build/*.composer.php`)
 *   - the runtime importer (`template-importer.php`)
 *
 * At BUILD time the composers run from the CLI with `WPDDBP_BUILD` defined and
 * no WordPress loaded, so every WP-dependent branch is guarded.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPDDBP_BUILD' ) ) {
	exit;
}

if ( ! function_exists( 'wpddbp_serialize_block' ) ) {
	/**
	 * Build a serialized Gutenberg block comment.
	 *
	 *   wpddbp_serialize_block('doc-booker-pro/clinics', ['layoutType'=>'feature'])
	 *   → "<!-- wp:doc-booker-pro/clinics {\"layoutType\":\"feature\"} /-->\n"
	 *
	 * For wrapper blocks with inner content, pass the already-serialized inner
	 * blocks as a string in $inner_blocks_html.
	 */
	function wpddbp_serialize_block( $name, $attrs = array(), $inner_blocks_html = '' ) {
		$attrs_json = '';
		if ( ! empty( $attrs ) ) {
			// JSON_UNESCAPED_SLASHES keeps URLs readable; UNESCAPED_UNICODE preserves accents in titles.
			$encoded    = wp_json_encode_compat( $attrs );
			$attrs_json = ' ' . $encoded;
		}

		if ( $inner_blocks_html === '' || $inner_blocks_html === null ) {
			return "<!-- wp:{$name}{$attrs_json} /-->\n";
		}

		return "<!-- wp:{$name}{$attrs_json} -->\n{$inner_blocks_html}<!-- /wp:{$name} -->\n";
	}
}

if ( ! function_exists( 'wp_json_encode_compat' ) ) {
	/**
	 * json_encode that works both with WP loaded (runtime) and standalone (CLI build).
	 * Always uses UNESCAPED_SLASHES + UNESCAPED_UNICODE so the .html stays readable.
	 */
	function wp_json_encode_compat( $data ) {
		return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}

if ( ! function_exists( 'wpddbp_core_columns' ) ) {
	/**
	 * Build a `core/columns` block wrapping N child blocks (one per column).
	 *
	 *   wpddbp_core_columns([$blockA, $blockB])                      // equal columns
	 *   wpddbp_core_columns([$blockA, $blockB], ['widths'=>['42%','58%']])
	 *
	 * Each entry in $children_html is the already-serialized inner-block markup
	 * for that column. Adds the `wp-block-columns` / `wp-block-column` wrapper
	 * divs that core's static save() expects so the front-end picks up the layout
	 * CSS. `verticalAlignment` (top|center|bottom) + per-column widths optional.
	 */
	function wpddbp_core_columns( array $children_html, array $opts = array() ) {
		$widths  = $opts['widths'] ?? array();
		$valign  = $opts['verticalAlignment'] ?? '';
		$is_stk  = ! empty( $opts['isStackedOnMobile'] ) ? 'true' : 'false';

		$cols = '';
		$i    = 0;
		foreach ( $children_html as $child ) {
			$col_attrs = array();
			if ( isset( $widths[ $i ] ) && $widths[ $i ] !== '' ) {
				$col_attrs['width'] = $widths[ $i ];
			}
			if ( $valign ) {
				$col_attrs['verticalAlignment'] = $valign;
			}
			$col_json  = empty( $col_attrs ) ? '' : ' ' . wp_json_encode_compat( $col_attrs );
			$style_css = isset( $widths[ $i ] ) && $widths[ $i ] !== '' ? ' style="flex-basis:' . $widths[ $i ] . '"' : '';
			$valign_cl = $valign ? ' is-vertically-aligned-' . $valign : '';
			$cols     .= "<!-- wp:column{$col_json} -->\n<div class=\"wp-block-column{$valign_cl}\"{$style_css}>\n{$child}</div>\n<!-- /wp:column -->\n";
			$i++;
		}

		$cols_attrs = array();
		if ( $valign ) {
			$cols_attrs['verticalAlignment'] = $valign;
		}
		if ( ! empty( $opts['isStackedOnMobile'] ) === false ) {
			$cols_attrs['isStackedOnMobile'] = (bool) ( $opts['isStackedOnMobile'] ?? true );
		}
		$cols_json  = empty( $cols_attrs ) ? '' : ' ' . wp_json_encode_compat( $cols_attrs );
		$valign_cls = $valign ? ' are-vertically-aligned-' . $valign : '';
		return "<!-- wp:columns{$cols_json} -->\n<div class=\"wp-block-columns{$valign_cls}\">\n{$cols}</div>\n<!-- /wp:columns -->\n";
	}
}

if ( ! function_exists( 'wpddbp_core_image' ) ) {
	/**
	 * Build a `core/image` block. $opts: className, radius (px), alt, sizeSlug.
	 * Used for hero / feature imagery that isn't owned by a data block.
	 */
	function wpddbp_core_image( $url, $opts = array() ) {
		$class    = $opts['className'] ?? '';
		$radius   = $opts['radius']    ?? '';
		$alt      = $opts['alt']       ?? '';
		$size     = $opts['sizeSlug']  ?? 'large';

		$attrs = array( 'sizeSlug' => $size, 'linkDestination' => 'none' );
		if ( $class !== '' ) {
			$attrs['className'] = $class;
		}
		$style_attr = '';
		$fig_class  = 'wp-block-image size-' . $size;
		if ( $radius !== '' ) {
			$attrs['style'] = array( 'border' => array( 'radius' => $radius ) );
			$fig_class     .= ' has-custom-border';
			$style_attr     = ' style="border-radius:' . esc_attr_compat( $radius ) . '"';
		}
		if ( $class !== '' ) {
			$fig_class .= ' ' . $class;
		}
		$json = wp_json_encode_compat( $attrs );
		return "<!-- wp:image {$json} -->\n"
			. "<figure class=\"{$fig_class}\"{$style_attr}><img src=\"" . $url . "\" alt=\"" . esc_attr_compat( $alt ) . "\"/></figure>\n"
			. "<!-- /wp:image -->\n";
	}
}

if ( ! function_exists( 'esc_attr_compat' ) ) {
	/** esc_attr that no-ops at build time (WP not loaded). */
	function esc_attr_compat( $s ) {
		if ( function_exists( 'esc_attr' ) ) {
			return esc_attr( $s );
		}
		return str_replace( array( '"', '<', '>' ), array( '&quot;', '&lt;', '&gt;' ), (string) $s );
	}
}

if ( ! function_exists( 'wpddbp_section' ) ) {
	/**
	 * Wrap inner block markup in a full-bleed `doc-booker-pro/wrapper` band.
	 *
	 * This is THE workhorse for the 100%-match section pattern: each mockup
	 * `<section class="sec bg-*">` becomes a full-width wrapper that paints the
	 * section background edge-to-edge and caps + centers its inner content to
	 * the `.shell` width (≈1240px). Inside go the section heading
	 * (advanced-heading) + the data block(s).
	 *
	 * @param string $block_id   Wrapper id (for #id CSS scoping). REQUIRED.
	 * @param string $inner_html Already-serialized inner-block markup.
	 * @param array  $opts {
	 *   @type string $width     Inner content cap, px. Default '1240'.
	 *   @type string $padding   lg padding "T R B L". Default '96 48 96 48' (.sec).
	 *   @type string $paddingMd md padding. Default '72 40 72 40'.
	 *   @type string $paddingSm sm padding. Default '56 24 56 24'.
	 *   @type string $bg        Solid background hex (mainWrapBG classic).
	 *   @type string $bgCss     Background shorthand value (e.g. a gradient) → customCSS.
	 *   @type string $css       Extra raw customCSS appended verbatim.
	 *   @type string $tag       Wrapper tag. Default 'section'.
	 * }
	 */
	function wpddbp_section( $block_id, $inner_html, $opts = array() ) {
		$width   = $opts['width']     ?? '1240';
		$pad     = $opts['padding']   ?? '96 48 96 48';
		$pad_md  = $opts['paddingMd'] ?? '72 40 72 40';
		$pad_sm  = $opts['paddingSm'] ?? '56 24 56 24';
		$bg      = $opts['bg']        ?? '';
		$bg_css  = $opts['bgCss']     ?? '';
		$extra   = $opts['css']       ?? '';
		$tag     = $opts['tag']       ?? 'section';
		$gap     = $opts['headGap']   ?? 48; // vertical rhythm between section children (heading → content → …)

		$custom = '';
		if ( $bg_css !== '' ) {
			$custom .= "#{$block_id}{background:{$bg_css};}";
		}
		if ( '' !== $gap && 0 !== $gap ) {
			$gap_val = is_numeric( $gap ) ? $gap . 'px' : $gap;
			$custom .= "#{$block_id} > * + *{margin-top:{$gap_val};}";
		}
		$custom .= $extra;

		$attrs = array(
			'blockId'           => $block_id,
			'align'             => 'full',
			'contentWidth'      => 'custom',
			'innerContentWidth' => array( 'lg' => $width, 'unit' => 'px' ),
			'wrapperTag'        => $tag,
			'wrapperPadding'    => array(
				'lg' => array( 'value' => $pad,    'unit' => 'px', 'isLinked' => false ),
				'md' => array( 'value' => $pad_md, 'unit' => 'px', 'isLinked' => false ),
				'sm' => array( 'value' => $pad_sm, 'unit' => 'px', 'isLinked' => false ),
			),
		);
		if ( $bg !== '' ) {
			$attrs['mainWrapBG'] = array( 'openBGColor' => 1, 'type' => 'classic', 'classic' => array( 'color' => $bg ) );
		}
		if ( $custom !== '' ) {
			$attrs['customCSS'] = $custom;
		}
		return wpddbp_serialize_block( 'doc-booker-pro/wrapper', $attrs, $inner_html );
	}
}

if ( ! function_exists( 'wpddbp_theme_layout_meta' ) ) {
	/**
	 * Theme-specific post meta that ask popular themes to hide the page title,
	 * use a no-sidebar / full-width content area, and disable featured-image
	 * displays. Set on every imported page — themes ignore keys they don't
	 * recognise, so applying all unconditionally is safe (and a page may only
	 * ever match one theme's keys at a time anyway).
	 *
	 * Meta-driven full-width + hidden-title: Astra · GeneratePress · Kadence ·
	 * OceanWP · Neve · Blocksy · Sydney · Page Builder Framework · Zakra.
	 * Block themes (Twenty Twenty-*, etc.) + any theme we don't have keys for
	 * are handled by the scoped CSS catch-all in imported-template-fixes.php
	 * (title hide + full-bleed alignfull), so the design still renders correctly
	 * everywhere — the meta just makes the cleanest themes pixel-perfect.
	 */
	function wpddbp_theme_layout_meta() {
		return array(
			// Astra
			'site-post-title'                => 'disabled',
			'site-sidebar-layout'            => 'no-sidebar',
			'site-content-layout'            => 'page-builder',
			'ast-featured-img'               => 'disabled',
			'theme-transparent-header-meta'  => 'default',
			// GeneratePress
			'_generate-disable-headline'     => '1',
			'_generate-sidebar-layout-meta'  => 'no-sidebar',
			'_generate-full-width-content'   => 'true',
			// Kadence
			'_kad_post_title'                => 'hide',
			'_kad_post_layout'               => 'fullwidth',
			'_kad_post_sidebar_id'           => 'disable',
			'_kad_post_content_style'        => 'unboxed',
			// OceanWP
			'ocean_disable_title'            => 'on',
			'ocean_post_layout'              => 'full-width',
			'ocean_display_featured_image'   => 'no',
			'ocean_disable_margins'          => 'enable',
			// Neve
			'neve_meta_sidebar'              => 'off',
			'neve_meta_container'            => 'full-width',
			'neve_meta_enable_content_width' => 'on',
			'neve_meta_content_width'        => 100,
			'neve_meta_disable_title'        => 'on',
			'neve_meta_disable_featured_image' => 'on',
			// Blocksy
			'blocksy_post_structure'         => 'type-1',
			'blocksy_content_area_spacing'   => 'none',
			'blocksy_post_meta_elements'     => array(),
			// Sydney
			'sidebar_layout'                 => 'no-sidebar',
			'sydney_page_layout'             => 'fullwidth',
			// Page Builder Framework (wpbf)
			'wpbf_sidebar_position'          => 'none',
			'wpbf_page_title'                => 'off',
			// Zakra
			'zakra_sidebar_layout'           => 'no-sidebar',
			'zakra_page_title'               => 'disable',
		);
	}
}

if ( ! function_exists( 'wpddbp_template_image_url' ) ) {
	/**
	 * Placeholder URL for a bundled template image.
	 *
	 * Composers run from the CLI (no WP context) so this emits the placeholder
	 * `__WPDDBP_IMAGES_URL__/<filename>`. The placeholder is resolved to the real
	 * plugin URL in `wpddbp_load_template_html()` at import time, so the static
	 * .html files stay portable across installs (any domain / wp-content path).
	 *
	 * Image files live at `src/admin/templates/images/`; the webpack
	 * CopyWebpackPlugin pattern mirrors them to `build/admin/templates/images/`.
	 */
	function wpddbp_template_image_url( $filename ) {
		return '__WPDDBP_IMAGES_URL__/' . ltrim( $filename, '/' );
	}
}

if ( ! function_exists( 'wpddbp_get_registry' ) ) {
	function wpddbp_get_registry() {
		return require __DIR__ . '/templates/_registry.php';
	}
}

if ( ! function_exists( 'wpddbp_load_template_html' ) ) {
	/**
	 * Read the raw block-markup .html for a given template slug, resolving the
	 * `__WPDDBP_IMAGES_URL__` placeholder to the real plugin URL.
	 *
	 * Returns the content string, or WP_Error if the slug / file is missing.
	 */
	function wpddbp_load_template_html( $slug ) {
		$slug     = preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $slug ) );
		$registry = wpddbp_get_registry();

		if ( ! isset( $registry[ $slug ] ) ) {
			return new WP_Error( 'wpddbp_no_template', sprintf( 'Unknown template: %s', $slug ) );
		}

		// File path is stored relative to the plugin root (e.g. build/admin/templates/general-hospital.html).
		$plugin_root = defined( 'WPDDBP_PATH' ) ? WPDDBP_PATH : trailingslashit( dirname( __DIR__, 3 ) );
		$file_rel    = $registry[ $slug ]['file'];
		$path        = trailingslashit( $plugin_root ) . ltrim( $file_rel, '/' );

		if ( ! file_exists( $path ) ) {
			return new WP_Error(
				'wpddbp_no_file',
				__( 'This template is coming soon — its markup has not been generated yet.', 'doc-booker-pro' )
			);
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $content === false ) {
			return new WP_Error( 'wpddbp_read_failed', __( 'Failed to read the template file.', 'doc-booker-pro' ) );
		}

		if ( function_exists( 'plugins_url' ) && defined( 'WPDDBP_FILE' ) ) {
			$images_url = plugins_url( 'build/admin/templates/images', WPDDBP_FILE );
			$content    = str_replace( '__WPDDBP_IMAGES_URL__', $images_url, $content );
		}

		return $content;
	}
}

if ( ! function_exists( 'wpddbp_compose_theme_tip' ) ) {
	/**
	 * Detect the active theme's parent and return a friendly tip string
	 * (or '' if no theme-specific advice applies).
	 */
	function wpddbp_compose_theme_tip() {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return '';
		}
		$theme = wp_get_theme();
		$slug  = strtolower( $theme->get_template() );
		$tips  = array(
			'astra'           => __( 'Theme detected: Astra. Full-width + no title are pre-applied. If a container still wraps the page, set Page Attributes → Template → "Full Width / Stretched".', 'doc-booker-pro' ),
			'generatepress'   => __( 'Theme detected: GeneratePress. Full-width + no title are pre-applied. For an edge-to-edge hero, the "GeneratePress Full Width" template also works.', 'doc-booker-pro' ),
			'kadence'         => __( 'Theme detected: Kadence. Full-width (unboxed) + no title are pre-applied. If needed, set Page Attributes → Template → "Fullwidth Stretched".', 'doc-booker-pro' ),
			'oceanwp'         => __( 'Theme detected: OceanWP. Full-width + no title are pre-applied. The "100% Full Width" template also works.', 'doc-booker-pro' ),
			'neve'            => __( 'Theme detected: Neve. Full-width + no title are pre-applied via page meta. If a container remains, set the page Container to "Full Width".', 'doc-booker-pro' ),
			'blocksy'         => __( 'Theme detected: Blocksy. The title is hidden and sections render full-bleed. If a max-width container remains, set the page Content Area to "Narrow/Normal → Full Width".', 'doc-booker-pro' ),
			'hello-elementor' => __( 'Theme detected: Hello Elementor. The page renders flush by default — keep editing in Gutenberg (not Elementor) so the DocBooker blocks render.', 'doc-booker-pro' ),
		);
		if ( isset( $tips[ $slug ] ) ) {
			return $tips[ $slug ];
		}
		// Block themes (FSE) — full-bleed alignfull + title hide are handled by CSS.
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return __( 'Block theme detected. The template renders full-bleed automatically; no template change needed.', 'doc-booker-pro' );
		}
		return __( 'Imported as a full-width draft page. If your theme shows a page title or a narrow container, switch this page to your theme\'s "Full Width" template.', 'doc-booker-pro' );
	}
}

if ( ! function_exists( 'wpddbp_extract_image_urls' ) ) {
	/**
	 * Find unique http(s) image URLs in a serialized block-markup string.
	 * Used by the optional image-sideload step.
	 */
	function wpddbp_extract_image_urls( $content ) {
		$found = array();
		if ( preg_match_all( '#https?://[^\s"\\\\]+\.(?:jpe?g|png|webp)(?:\?[^\s"\\\\]*)?#i', $content, $m ) ) {
			$found = array_values( array_unique( $m[0] ) );
		}
		return $found;
	}
}

if ( ! function_exists( 'wpddbp_sideload_images_into_content' ) ) {
	/**
	 * Download external images, register them in the media library, and rewrite
	 * the block markup to point at the local URLs. Best-effort — failures leave
	 * the original URL inline. Returns the (possibly rewritten) content.
	 */
	function wpddbp_sideload_images_into_content( $content ) {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$urls = wpddbp_extract_image_urls( $content );
		if ( empty( $urls ) ) {
			return $content;
		}

		foreach ( $urls as $url ) {
			$local = media_sideload_image( $url, 0, null, 'src' );
			if ( is_string( $local ) && $local !== '' ) {
				$content = str_replace( $url, $local, $content );
			}
		}
		return $content;
	}
}

if ( ! function_exists( 'wpddbp_uuid' ) ) {
	function wpddbp_uuid() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return bin2hex( random_bytes( 16 ) );
	}
}
