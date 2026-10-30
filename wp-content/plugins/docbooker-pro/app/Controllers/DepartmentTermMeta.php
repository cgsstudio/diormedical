<?php
/**
 * Department term-meta extension (Pro-only) — Phase 3, Task 8.
 *
 * Adds two profile fields to the `wpddb_doctor_department` taxonomy:
 *   - wpddb_doctor_department_icon          (icon-library name OR raw <svg>)
 *   - wpddb_doctor_department_accent_color  (hex; empty → palette rotation)
 * The native term `description` is reused as the department tagline.
 *
 * This controller is instantiated only from DocBookerPro::init(), so when Pro is
 * inactive the term-edit screen stays vanilla, the meta is never registered, and
 * the archive falls back to WordPress defaults — i.e. the Free plugin is unchanged
 * (architecture § 10a, tier policy: Pro-only).
 *
 * Defaults are resolved at READ time (name-based smart icon + palette colour),
 * so no migration/seeding is required — terms created before OR after Pro is
 * active always render a sensible medallion, and authors can override per term.
 *
 * @package WpDreamers\WPDDBP\Controllers
 */

namespace WpDreamers\WPDDBP\Controllers;

use WpDreamers\WPDDBP\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class DepartmentTermMeta {

	use SingletonTrait;

	const ICON_KEY   = 'wpddb_doctor_department_icon';
	const ACCENT_KEY = 'wpddb_doctor_department_accent_color';

	/** @var string Cached taxonomy slug. */
	private $taxonomy;

	public function __construct() {
		$this->taxonomy = function_exists( 'wpddb' ) ? wpddb()->doctor_category : 'wpddb_doctor_department';

		add_action( 'init', array( $this, 'register_meta' ) );

		// Admin: term add / edit form fields + save.
		add_action( "{$this->taxonomy}_add_form_fields", array( $this, 'render_add_fields' ), 20 );
		add_action( "{$this->taxonomy}_edit_form_fields", array( $this, 'render_edit_fields' ), 20, 2 );
		add_action( "created_{$this->taxonomy}", array( $this, 'save_fields' ), 10 );
		add_action( "edited_{$this->taxonomy}", array( $this, 'save_fields' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend: department archive hero (the single-page render contract).
		add_filter( 'wpddb_show_page_title', array( $this, 'maybe_hide_default_title' ) );
		add_action( 'wpddb_before_main_content', array( $this, 'render_archive_hero' ), 20 );
	}

	/* ---------------------------------------------------------------------
	 * Meta registration (REST-exposed)
	 * ------------------------------------------------------------------- */

	public function register_meta() {
		register_term_meta(
			$this->taxonomy,
			self::ICON_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_icon' ),
				'auth_callback'     => function () {
					return current_user_can( 'manage_categories' );
				},
			)
		);

		register_term_meta(
			$this->taxonomy,
			self::ACCENT_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_hex_color',
				'auth_callback'     => function () {
					return current_user_can( 'manage_categories' );
				},
			)
		);
	}

	/** Icon value is either a library name (alnum + hyphen) or sanitized raw SVG. */
	public function sanitize_icon( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( false !== strpos( $value, '<' ) ) {
			return $this->sanitize_svg( $value );
		}
		return sanitize_key( $value );
	}

	/** Allow a tight subset of SVG markup for the author raw-SVG escape hatch. */
	private function sanitize_svg( $svg ) {
		$allowed = array(
			'svg'      => array(
				'xmlns'        => array(),
				'viewbox'      => array(),
				'fill'         => array(),
				'stroke'       => array(),
				'stroke-width' => array(),
				'width'        => array(),
				'height'       => array(),
				'aria-hidden'  => array(),
				'class'        => array(),
			),
			'path'     => array( 'd' => array(), 'fill' => array(), 'stroke' => array(), 'stroke-width' => array(), 'stroke-linecap' => array(), 'stroke-linejoin' => array() ),
			'circle'   => array( 'cx' => array(), 'cy' => array(), 'r' => array(), 'fill' => array(), 'stroke' => array(), 'stroke-width' => array() ),
			'rect'     => array( 'x' => array(), 'y' => array(), 'width' => array(), 'height' => array(), 'rx' => array(), 'ry' => array(), 'fill' => array(), 'stroke' => array(), 'stroke-width' => array() ),
			'g'        => array( 'fill' => array(), 'stroke' => array() ),
			'line'     => array( 'x1' => array(), 'y1' => array(), 'x2' => array(), 'y2' => array(), 'stroke' => array(), 'stroke-width' => array() ),
			'polyline' => array( 'points' => array(), 'fill' => array(), 'stroke' => array(), 'stroke-width' => array() ),
			'polygon'  => array( 'points' => array(), 'fill' => array(), 'stroke' => array(), 'stroke-width' => array() ),
			'ellipse'  => array( 'cx' => array(), 'cy' => array(), 'rx' => array(), 'ry' => array(), 'fill' => array(), 'stroke' => array() ),
		);
		return wp_kses( $svg, $allowed );
	}

	/* ---------------------------------------------------------------------
	 * Admin — term add / edit form fields + save
	 * ------------------------------------------------------------------- */

	public function render_add_fields() {
		wp_nonce_field( 'wpddbp_dept_meta', 'wpddbp_dept_meta_nonce' );
		?>
		<div class="form-field wpddbp-dept-field">
			<label for="wpddbp_dept_icon"><?php esc_html_e( 'Department icon', 'doc-booker-pro' ); ?></label>
			<?php $this->icon_select( '' ); ?>
			<p><?php esc_html_e( 'Pick a medical icon, or paste your own SVG below. Leave blank to auto-pick from the department name.', 'doc-booker-pro' ); ?></p>
			<textarea name="wpddbp_dept_icon_svg" id="wpddbp_dept_icon_svg" rows="3" class="large-text code" placeholder="<?php esc_attr_e( '…or paste raw <svg>…</svg> markup', 'doc-booker-pro' ); ?>"></textarea>
		</div>
		<div class="form-field wpddbp-dept-field">
			<label for="wpddbp_dept_accent"><?php esc_html_e( 'Accent color', 'doc-booker-pro' ); ?></label>
			<input type="text" name="wpddbp_dept_accent" id="wpddbp_dept_accent" value="" class="wpddbp-color-field" data-default-color="" />
			<p><?php esc_html_e( 'Leave empty to auto-pick from the palette.', 'doc-booker-pro' ); ?></p>
		</div>
		<div class="form-field">
			<label><?php esc_html_e( 'Preview', 'doc-booker-pro' ); ?></label>
			<span class="wpddbp-dept-preview" aria-hidden="true"></span>
		</div>
		<?php
	}

	public function render_edit_fields( $term, $taxonomy = '' ) {
		$icon   = get_term_meta( $term->term_id, self::ICON_KEY, true );
		$accent = get_term_meta( $term->term_id, self::ACCENT_KEY, true );
		$is_svg = ( '' !== $icon && false !== strpos( $icon, '<' ) );
		$resolved_color = $this->resolve_accent_color( $accent, $term );
		?>
		<tr class="form-field wpddbp-dept-field">
			<th scope="row"><label for="wpddbp_dept_icon"><?php esc_html_e( 'Department icon', 'doc-booker-pro' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'wpddbp_dept_meta', 'wpddbp_dept_meta_nonce' ); ?>
				<?php $this->icon_select( $is_svg ? '' : $icon ); ?>
				<p class="description"><?php esc_html_e( 'Pick a medical icon, or paste your own SVG below. Leave blank to auto-pick from the department name.', 'doc-booker-pro' ); ?></p>
				<textarea name="wpddbp_dept_icon_svg" id="wpddbp_dept_icon_svg" rows="3" class="large-text code" placeholder="<?php esc_attr_e( '…or paste raw <svg>…</svg> markup', 'doc-booker-pro' ); ?>"><?php echo $is_svg ? esc_textarea( $icon ) : ''; ?></textarea>
			</td>
		</tr>
		<tr class="form-field wpddbp-dept-field">
			<th scope="row"><label for="wpddbp_dept_accent"><?php esc_html_e( 'Accent color', 'doc-booker-pro' ); ?></label></th>
			<td>
				<input type="text" name="wpddbp_dept_accent" id="wpddbp_dept_accent" value="<?php echo esc_attr( $accent ); ?>" class="wpddbp-color-field" data-default-color="" />
				<p class="description"><?php esc_html_e( 'Leave empty to auto-pick from the palette.', 'doc-booker-pro' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Preview', 'doc-booker-pro' ); ?></th>
			<td>
				<span class="wpddbp-dept-preview" data-color="<?php echo esc_attr( $resolved_color ); ?>" aria-hidden="true"><?php echo $this->resolve_icon_svg( $icon, $term ); // phpcs:ignore — trusted/sanitized SVG. ?></span>
				<p class="description"><?php esc_html_e( 'The tagline shown on the department page uses the "Description" field above.', 'doc-booker-pro' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/** The shared <select> of icon-library names. */
	private function icon_select( $selected ) {
		?>
		<select name="wpddbp_dept_icon" id="wpddbp_dept_icon" class="postform">
			<option value=""><?php esc_html_e( '— Auto (from name) —', 'doc-booker-pro' ); ?></option>
			<?php foreach ( array_keys( self::icon_library() ) as $name ) : ?>
				<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $selected, $name ); ?>><?php echo esc_html( ucwords( str_replace( '-', ' ', $name ) ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function save_fields( $term_id ) {
		if ( ! isset( $_POST['wpddbp_dept_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpddbp_dept_meta_nonce'] ) ), 'wpddbp_dept_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// Icon: raw SVG (if pasted) wins over the library select.
		$raw_svg = isset( $_POST['wpddbp_dept_icon_svg'] ) ? trim( (string) wp_unslash( $_POST['wpddbp_dept_icon_svg'] ) ) : '';
		$name    = isset( $_POST['wpddbp_dept_icon'] ) ? sanitize_key( wp_unslash( $_POST['wpddbp_dept_icon'] ) ) : '';

		if ( '' !== $raw_svg ) {
			$icon_value = $this->sanitize_svg( $raw_svg );
		} else {
			$icon_value = $name;
		}

		if ( '' !== $icon_value ) {
			update_term_meta( $term_id, self::ICON_KEY, $icon_value );
		} else {
			delete_term_meta( $term_id, self::ICON_KEY );
		}

		$accent = isset( $_POST['wpddbp_dept_accent'] ) ? sanitize_hex_color( wp_unslash( $_POST['wpddbp_dept_accent'] ) ) : '';
		if ( $accent ) {
			update_term_meta( $term_id, self::ACCENT_KEY, $accent );
		} else {
			delete_term_meta( $term_id, self::ACCENT_KEY );
		}
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}
		$tax = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : '';
		if ( $tax !== $this->taxonomy ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Build a JS map of name → inner SVG markup so the preview updates live.
		$icons_js = wp_json_encode( self::icon_library() );
		$palette  = wp_json_encode( self::palette() );

		$inline = <<<JS
( function ( \$ ) {
	var ICONS = {$icons_js};
	var PALETTE = {$palette};
	function wrap( inner ) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>'; }
	function autoIcon() {
		// Mirror the PHP smart default loosely from the page title field if present.
		return ICONS['stethoscope'] ? wrap( ICONS['stethoscope'] ) : '';
	}
	function render() {
		var \$prev = \$( '.wpddbp-dept-preview' );
		if ( ! \$prev.length ) { return; }
		var raw = \$.trim( \$( '#wpddbp_dept_icon_svg' ).val() || '' );
		var name = \$( '#wpddbp_dept_icon' ).val() || '';
		var color = \$.trim( \$( '#wpddbp_dept_accent' ).val() || '' ) || \$prev.data( 'color' ) || '#0a4b78';
		var svg;
		if ( raw ) { svg = raw; }
		else if ( name && ICONS[ name ] ) { svg = wrap( ICONS[ name ] ); }
		else { svg = autoIcon(); }
		\$prev.html( svg ).css( { color: color, '--accent': color } );
	}
	\$( function () {
		\$( '.wpddbp-color-field' ).wpColorPicker( { change: render, clear: render } );
		\$( document ).on( 'input change keyup', '#wpddbp_dept_icon, #wpddbp_dept_icon_svg', render );
		render();
	} );
}( jQuery ) );
JS;
		wp_add_inline_script( 'wp-color-picker', $inline );

		$css = '.wpddbp-dept-preview{display:inline-grid;place-items:center;width:54px;height:54px;border-radius:14px;background:color-mix(in srgb, currentColor 12%, #fff);box-shadow:inset 0 0 0 1px color-mix(in srgb, currentColor 30%, #fff)}.wpddbp-dept-preview svg{width:28px;height:28px}.wpddbp-dept-field textarea.code{font-family:Menlo,Consolas,monospace;font-size:12px}';
		wp_add_inline_style( 'wp-color-picker', $css );
	}

	/* ---------------------------------------------------------------------
	 * Frontend — department archive hero (single-page render contract)
	 * ------------------------------------------------------------------- */

	/** Suppress the Free template's plain page title on the department archive (the hero carries the name). */
	public function maybe_hide_default_title( $show ) {
		if ( is_tax( $this->taxonomy ) ) {
			return false;
		}
		return $show;
	}

	public function render_archive_hero() {
		if ( ! is_tax( $this->taxonomy ) ) {
			return;
		}
		$term = get_queried_object();
		if ( ! $term || empty( $term->term_id ) ) {
			return;
		}

		$icon_meta = get_term_meta( $term->term_id, self::ICON_KEY, true );
		$accent_meta = get_term_meta( $term->term_id, self::ACCENT_KEY, true );
		$accent    = $this->resolve_accent_color( $accent_meta, $term );
		$icon_svg  = $this->resolve_icon_svg( $icon_meta, $term );
		$tagline   = term_description( $term->term_id );
		$count     = (int) $term->count;

		$this->print_hero_styles();
		?>
		<header class="wpddbp-dept-hero" style="--accent: <?php echo esc_attr( $accent ); ?>;">
			<span class="wpddbp-dept-hero__medallion" aria-hidden="true"><?php echo $icon_svg; // phpcs:ignore — trusted/sanitized SVG. ?></span>
			<div class="wpddbp-dept-hero__body">
				<p class="wpddbp-dept-hero__eyebrow"><?php esc_html_e( 'Department', 'doc-booker-pro' ); ?></p>
				<h1 class="wpddbp-dept-hero__title"><?php echo esc_html( $term->name ); ?></h1>
				<?php if ( '' !== trim( wp_strip_all_tags( (string) $tagline ) ) ) : ?>
					<div class="wpddbp-dept-hero__tagline"><?php echo wp_kses_post( $tagline ); ?></div>
				<?php endif; ?>
				<p class="wpddbp-dept-hero__count">
					<?php
					/* translators: %s: number of doctors in the department. */
					echo esc_html( sprintf( _n( '%s specialist in this department', '%s specialists in this department', $count, 'doc-booker-pro' ), number_format_i18n( $count ) ) );
					?>
				</p>
			</div>
		</header>
		<?php
	}

	/** Hero styling — neutral so it blends with the active theme + Free archive page. One-time. */
	private function print_hero_styles() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		<style id="wpddbp-dept-hero-css">
			.wpddbp-dept-hero{display:flex;align-items:center;gap:22px;margin:0 0 28px;padding:24px 26px;border:1px solid rgba(0,0,0,.08);border-radius:16px;background:linear-gradient(120deg,color-mix(in srgb, var(--accent,#0a4b78) 9%, #fff),#fff 70%)}
			.wpddbp-dept-hero__medallion{flex:0 0 auto;display:grid;place-items:center;width:74px;height:74px;border-radius:50%;color:var(--accent,#0a4b78);background:color-mix(in srgb, var(--accent,#0a4b78) 14%, #fff);box-shadow:inset 0 0 0 1px color-mix(in srgb, var(--accent,#0a4b78) 26%, #fff)}
			.wpddbp-dept-hero__medallion svg{width:38px;height:38px}
			.wpddbp-dept-hero__body{min-width:0}
			.wpddbp-dept-hero__eyebrow{margin:0 0 2px;font-size:11px;letter-spacing:.18em;text-transform:uppercase;font-weight:700;color:var(--accent,#0a4b78)}
			.wpddbp-dept-hero__title{margin:0;line-height:1.1}
			.wpddbp-dept-hero__tagline{margin:6px 0 0;font-style:italic;opacity:.8;max-width:60ch}
			.wpddbp-dept-hero__tagline p{margin:0}
			.wpddbp-dept-hero__count{margin:10px 0 0;font-size:13px;font-weight:600;opacity:.7}
			@media (max-width:600px){.wpddbp-dept-hero{flex-direction:column;text-align:center;align-items:center}.wpddbp-dept-hero__tagline{margin-inline:auto}}
		</style>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Resolvers (read-time defaults — supersede the optional migration)
	 * ------------------------------------------------------------------- */

	/** Return a full inline <svg> for a term: stored name/SVG → smart name default → generic. */
	public function resolve_icon_svg( $stored, $term ) {
		$stored = trim( (string) $stored );
		if ( '' !== $stored && false !== strpos( $stored, '<' ) ) {
			// Raw SVG already sanitized on save; wrap bare inner markup if needed.
			return ( false !== strpos( $stored, '<svg' ) ) ? $stored : $this->wrap_svg( $stored );
		}

		$library = self::icon_library();
		$name    = ( '' !== $stored && isset( $library[ $stored ] ) ) ? $stored : $this->smart_default_icon( $term );
		$inner   = isset( $library[ $name ] ) ? $library[ $name ] : $library['stethoscope'];
		return $this->wrap_svg( $inner );
	}

	private function wrap_svg( $inner ) {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
	}

	/** Author hex if valid, else a deterministic palette colour indexed by term ID. */
	public function resolve_accent_color( $stored, $term ) {
		$hex = sanitize_hex_color( (string) $stored );
		if ( $hex ) {
			return $hex;
		}
		$palette = self::palette();
		$index   = ( $term && ! empty( $term->term_id ) ) ? ( (int) $term->term_id % count( $palette ) ) : 0;
		return $palette[ $index ];
	}

	/** Guess an icon-library name from the department term name. */
	private function smart_default_icon( $term ) {
		$name = $term && ! empty( $term->name ) ? strtolower( $term->name ) : '';
		$map  = array(
			'cardio'        => 'heart-pulse',
			'heart'         => 'heart-pulse',
			'neuro'         => 'brain',
			'ortho'         => 'bone',
			'bone'          => 'bone',
			'joint'         => 'bone',
			'ophthal'       => 'eye',
			'eye'           => 'eye',
			'optom'         => 'eye',
			'vision'        => 'eye',
			'dent'          => 'tooth',
			'oral'          => 'tooth',
			'pulmon'        => 'lung',
			'respir'        => 'lung',
			'lung'          => 'lung',
			'chest'         => 'lung',
			'pediatr'       => 'baby',
			'paediatr'      => 'baby',
			'child'         => 'baby',
			'neonat'        => 'baby',
			'obstet'        => 'pregnancy',
			'gyne'          => 'pregnancy',
			'gynae'         => 'pregnancy',
			'women'         => 'pregnancy',
			'matern'        => 'pregnancy',
			'derm'          => 'skin',
			'skin'          => 'skin',
			'ent'           => 'ear',
			'otolar'        => 'ear',
			'ear'           => 'ear',
			'nephro'        => 'kidney',
			'kidney'        => 'kidney',
			'urolog'        => 'kidney',
			'gastro'        => 'stomach',
			'stomach'       => 'stomach',
			'digest'        => 'stomach',
			'hepat'         => 'liver',
			'liver'         => 'liver',
			'patho'         => 'microscope',
			'lab'           => 'microscope',
			'oncolog'       => 'microscope',
			'pharm'         => 'pill',
			'immun'         => 'syringe',
			'vaccin'        => 'syringe',
			'physio'        => 'wheelchair',
			'rehab'         => 'wheelchair',
			'ortho-rehab'   => 'wheelchair',
			'geriatr'       => 'family',
			'family'        => 'family',
			'general'       => 'stethoscope',
			'internal'      => 'stethoscope',
			'primary'       => 'stethoscope',
			'psych'         => 'brain',
			'mental'        => 'brain',
		);
		foreach ( $map as $needle => $icon ) {
			if ( '' !== $name && false !== strpos( $name, $needle ) ) {
				return $icon;
			}
		}
		return 'stethoscope';
	}

	/** Palette rotation for terms with no author-set accent colour (term_id % 8). */
	public static function palette() {
		return array( '#c54a52', '#3f7a64', '#a26a3a', '#0a4b78', '#6c3a5f', '#14b8a6', '#b88a2c', '#c47d6a' );
	}

	/**
	 * The built-in medical icon library: name → inner SVG markup (24×24, stroke).
	 * Wrapped by wrap_svg() at render time. Authors may override per term with
	 * raw <svg> markup via the term-edit screen.
	 */
	public static function icon_library() {
		return array(
			'heart-pulse' => '<path d="M20.4 9.4a4.3 4.3 0 0 0-7.6-2.7A4.3 4.3 0 0 0 5.2 9.4c0 .5.05.9.15 1.3"/><path d="M4.6 11.6c1 2.6 3.6 5 7.4 7.7 1.7-1.2 3.2-2.3 4.4-3.4"/><path d="M14 12h2.2l1-1.6 1.6 3.1 1-1.9H22"/>',
			'heart'       => '<path d="M12 20s-7-4.35-7-9.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 7 3.5C19 15.65 12 20 12 20z"/>',
			'brain'       => '<path d="M9.5 4.6A2.6 2.6 0 0 0 7 7.2 2.6 2.6 0 0 0 5.6 12 3 3 0 0 0 9 16.8M14.5 4.6A2.6 2.6 0 0 1 17 7.2 2.6 2.6 0 0 1 18.4 12 3 3 0 0 1 15 16.8"/><path d="M12 4.4v14"/>',
			'bone'        => '<path d="M7.5 16.5 16.5 7.5"/><path d="M6.7 6.2a1.9 1.9 0 1 0 1.5 1.7 1.9 1.9 0 1 0-1.5-1.7z"/><path d="M17.3 17.8a1.9 1.9 0 1 0-1.5-1.7 1.9 1.9 0 1 0 1.5 1.7z"/>',
			'eye'         => '<path d="M2.6 12S6 5.8 12 5.8 21.4 12 21.4 12 18 18.2 12 18.2 2.6 12 2.6 12z"/><circle cx="12" cy="12" r="2.6"/>',
			'tooth'       => '<path d="M8.2 3.6C6.2 3.6 4.7 5 4.7 7.4c0 1.4.5 2.9.8 4.8.4 2.2.6 6 1.8 6 1 0 1-2.8 1.5-4.4.3-.9.6-1.4 1.2-1.4s.9.5 1.2 1.4c.5 1.6.5 4.4 1.5 4.4 1.2 0 1.4-3.8 1.8-6 .3-1.9.8-3.4.8-4.8 0-2.4-1.5-3.8-3.5-3.8-1.2 0-1.7.6-2.8.6s-1.6-.6-2.8-.6z"/>',
			'lung'        => '<path d="M12 4v6"/><path d="M9.6 8.4c0 1.7-1.6 2.3-2.5 3.9-.8 1.4-1.1 3.3-1.1 5 0 1.2 1.4 1.6 2.3.8.9-.8 1.3-1.8 1.3-3.4V9.6c0-.8-.2-1.2-1.2-1.2z"/><path d="M14.4 8.4c0 1.7 1.6 2.3 2.5 3.9.8 1.4 1.1 3.3 1.1 5 0 1.2-1.4 1.6-2.3.8-.9-.8-1.3-1.8-1.3-3.4V9.6c0-.8.2-1.2 1.2-1.2z"/>',
			'baby'        => '<circle cx="12" cy="8" r="3.6"/><path d="M6.4 20a5.6 5.6 0 0 1 11.2 0"/><path d="M10.4 7.6h.01M13.6 7.6h.01"/>',
			'pregnancy'   => '<circle cx="12" cy="4.8" r="1.9"/><path d="M12 7.2v4.4"/><path d="M12 8.6c2.4 0 3.9 1.8 3.9 4.4 0 1.5-1 2.5-2.5 2.5H12"/><path d="M12 12.4v8.4"/>',
			'stethoscope' => '<path d="M6 3v4.5a4 4 0 0 0 8 0V3"/><path d="M5 3h2M13 3h2"/><path d="M10 15.5v-4"/><path d="M10 15.5a6.5 6.5 0 0 0 6.5 6.5 4 4 0 0 0 4-4v-2.6"/><circle cx="20.4" cy="11.4" r="2.1"/>',
			'pill'        => '<path d="M10.7 3.8 3.8 10.7a4.9 4.9 0 0 0 6.9 6.9l6.9-6.9a4.9 4.9 0 0 0-6.9-6.9z"/><path d="M7.3 7.3 14.2 14.2"/>',
			'syringe'     => '<path d="M18.5 5.5 19.8 4.2M19.2 4.9l-7 7"/><path d="M16.4 8.3 6.8 17.9l-3.1 1 1-3.1L14.3 6.2z"/><path d="M11.2 9.2l1.6 1.6M9 11.4l1.6 1.6"/>',
			'microscope'  => '<path d="M6 19h11"/><path d="M9 19v-2.5"/><path d="M8 16.5h3l1-1.6-3.2-3.2L7.4 13.3z"/><path d="M10.8 9.6 14.4 6"/><path d="M13.3 4.9l3.2 3.2"/><path d="M13.4 18.5A6.4 6.4 0 0 0 18 8.6"/>',
			'ear'         => '<path d="M8.2 18.5C6.6 17 6 15.4 6 12.4a6 6 0 0 1 12 0c0 2.1-1.6 3.1-3.1 3.7-1 .4-1.4 1-1.4 2A2.1 2.1 0 0 1 11 19.5"/><path d="M9.5 9.2a2.5 2.5 0 0 1 5 .1"/>',
			'skin'        => '<path d="M4.5 14.5A8 8 0 0 1 16 7.2"/><path d="M9.2 21a6.4 6.4 0 0 1-3.2-5"/><circle cx="16" cy="15" r="4"/><path d="M19 18l2.4 2.4"/>',
			'kidney'      => '<path d="M14.4 4.6c2.6 0 4.1 2.4 4.1 5.8s-2 7.6-4.6 7.6c-1.3 0-1.7-1.3-1.7-2.7s-.4-2-1.3-2.4C9.4 11.9 8.5 10.2 8.5 8.3c0-2.1 1.4-3.7 3.2-3.7 1.4 0 1.5 1 2.7 1z"/>',
			'stomach'     => '<path d="M9.4 4v3.6c0 1.4.9 2.3.9 3.7 0 2.8-1.8 3.7-1.8 6"/><path d="M9.4 7.6c1.9 0 3.8.5 5.2 2.4s2.3 3.2 2.3 5.1a2.9 2.9 0 0 1-5.8 0"/>',
			'liver'       => '<path d="M4 8.2c4-1.4 11.8-1.4 15.8 0 1 .4 0 3.8-1.9 5.2-1.4 1-1.9 1.9-2.4 3.3-.4 1.1-1 1.8-2.4 1.8-1.9 0-1.9-1.9-3.3-3.3S5 12.9 4.5 11 4 8.2 4 8.2z"/><path d="M11 9.4c.6 1.3 1.8 1.9 3.3 1.7"/>',
			'family'      => '<circle cx="8" cy="7.2" r="2.4"/><circle cx="16.2" cy="7.6" r="2"/><path d="M3.6 19.6c0-2.8 1.9-4.6 4.4-4.6s4.4 1.8 4.4 4.6"/><path d="M13.6 19.2c0-2.3 1.4-3.9 3.3-3.9 1.6 0 3 1.1 3.5 3"/>',
			'wheelchair'  => '<circle cx="11" cy="17.8" r="4"/><path d="M11 13.8 9.4 9h-2"/><path d="M9.6 11.4H15l2.2 4.2H19"/><circle cx="13" cy="5.4" r="1.3"/>',
			'clipboard'   => '<rect x="6" y="4.5" width="12" height="16.5" rx="2"/><path d="M9.2 4.5a1.4 1.4 0 0 1 1.4-1.5h2.8a1.4 1.4 0 0 1 1.4 1.5V5.5H9.2z"/><path d="M9.2 11h6M9.2 14.6h6M9.2 18.2h3.5"/>',
			'nose'        => '<path d="M12 4v7.2c0 1.4-1.4 1.9-1.4 3.3"/><path d="M14.4 16c0 1.5-1.1 2.6-2.6 2.6S9 17.5 9 16c0-1 .6-1.6 1.6-1.9"/><path d="M14.4 16c.9-.2 1.4-.9 1.4-1.7"/>',
			'microscope-lab' => '<path d="M6 19h11"/><path d="M9 19v-2.5"/><path d="M8 16.5h3l1-1.6-3.2-3.2L7.4 13.3z"/><path d="M10.8 9.6 14.4 6"/><path d="M13.3 4.9l3.2 3.2"/>',
		);
	}
}
