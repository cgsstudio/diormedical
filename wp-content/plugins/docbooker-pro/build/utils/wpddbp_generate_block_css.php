<?php
/**
 * DocBooker Pro blocks — dynamic CSS dispatcher.
 *
 * Duplicated + renamed from the reference blocks plugin's CSS dispatcher
 * (architecture §3 standalone rule — never imported). The six helper closures
 * (typography / spacing / color / border-radius / border / box-shadow) are
 * domain-agnostic and shared by every per-block generator. `wpddbp_generate_block_css()`
 * dispatches to the per-block generator by `$block_name`.
 *
 * @package WpDreamers\WPDDBP\Blocks
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/frontend-dynamic-css/advanced-heading-css.php';
require_once __DIR__ . '/frontend-dynamic-css/wrapper-css.php';
require_once __DIR__ . '/frontend-dynamic-css/cta-css.php';
require_once __DIR__ . '/frontend-dynamic-css/info-box-css.php';
require_once __DIR__ . '/frontend-dynamic-css/countdown-css.php';
require_once __DIR__ . '/frontend-dynamic-css/slider-css.php';
require_once __DIR__ . '/frontend-dynamic-css/faq-css.php';
require_once __DIR__ . '/frontend-dynamic-css/services-css.php';
require_once __DIR__ . '/frontend-dynamic-css/process-steps-css.php';
require_once __DIR__ . '/frontend-dynamic-css/office-hours-css.php';
require_once __DIR__ . '/frontend-dynamic-css/hero-css.php';
require_once __DIR__ . '/frontend-dynamic-css/doctors-css.php';
require_once __DIR__ . '/frontend-dynamic-css/departments-css.php';
require_once __DIR__ . '/frontend-dynamic-css/awards-css.php';
require_once __DIR__ . '/frontend-dynamic-css/fee-badge-css.php';
require_once __DIR__ . '/frontend-dynamic-css/clinics-css.php';
require_once __DIR__ . '/frontend-dynamic-css/quick-book-css.php';
require_once __DIR__ . '/frontend-dynamic-css/doctor-schedule-css.php';
require_once __DIR__ . '/frontend-dynamic-css/booking-form-css.php';
require_once __DIR__ . '/frontend-dynamic-css/doctor-stats-css.php';
require_once __DIR__ . '/frontend-dynamic-css/testimonials-css.php';
// Phase 3+ generators are appended here as those blocks ship.

/**
 * Parse a 1-to-4-side dimensional value like "10 0 5 0" or "10" into a CSS
 * shorthand. Drops empty entries, leaves bare "0" unitless, pads to 4 sides.
 * Returns '' when the input has no real values at all.
 *
 * @param string $raw  Space-separated side values.
 * @param string $unit CSS unit to append to non-zero values.
 * @return string
 */
function wpddbp_parse_dim_sides( $raw, $unit = 'px' ) {
	$parts = preg_split( '/\s+/', trim( (string) $raw ) );
	$parts = array_values( array_filter( $parts, function ( $p ) {
		return '' !== $p;
	} ) );
	if ( empty( $parts ) ) {
		return '';
	}
	$with_unit = array_map( function ( $p ) use ( $unit ) {
		return ( '0' === $p ) ? '0' : ( $p . $unit );
	}, $parts );
	if ( 1 === count( $with_unit ) ) {
		return $with_unit[0];
	}
	while ( count( $with_unit ) < 4 ) {
		$with_unit[] = '0';
	}
	return implode( ' ', array_slice( $with_unit, 0, 4 ) );
}

/**
 * Build the per-instance CSS string for a DocBooker block.
 *
 * @param array  $attributes Full attribute set (block.json defaults merged in).
 * @param string $unique_id  The block's `blockId` (becomes the `#id` selector).
 * @param string $block_name e.g. "doc-booker-pro/advanced-heading".
 * @return string
 */
function wpddbp_generate_block_css( $attributes, $unique_id, $block_name = '' ) {
	$css = '';

	// Typography helper. Returns ['base'=>..., 'md'=>..., 'sm'=>...] where 'base'
	// is bare declarations and md/sm are bare declarations for media-query wrapping.
	$generate_typography_css = function ( $selector, $typography ) {
		$base = '';
		$md   = '';
		$sm   = '';

		// RangeDevice writes scalar value per device + a sibling `unit` on the parent;
		// older data may hold per-device { value, unit } objects — accept both.
		$read_device = function ( $field, $device, $fallback_unit = 'px' ) {
			if ( ! is_array( $field ) ) {
				return null;
			}
			if ( ! array_key_exists( $device, $field ) ) {
				return null;
			}
			$v = $field[ $device ];
			if ( is_array( $v ) ) {
				if ( ! isset( $v['value'] ) || '' === $v['value'] || null === $v['value'] ) {
					return null;
				}
				$u = isset( $v['unit'] ) && '' !== $v['unit'] ? $v['unit'] : ( isset( $field['unit'] ) ? $field['unit'] : $fallback_unit );
				return array( 'value' => $v['value'], 'unit' => $u );
			}
			if ( '' === $v || null === $v ) {
				return null;
			}
			$u = isset( $field['unit'] ) && '' !== $field['unit'] ? $field['unit'] : $fallback_unit;
			return array( 'value' => $v, 'unit' => $u );
		};

		if ( ! empty( $typography['family'] ) ) {
			$base .= "font-family: '{$typography['family']}', sans-serif;";
		}
		if ( ! empty( $typography['weight'] ) ) {
			$base .= "font-weight: {$typography['weight']};";
		}
		if ( ! empty( $typography['style'] ) ) {
			$base .= "font-style: {$typography['style']};";
		}
		if ( ! empty( $typography['transform'] ) ) {
			$base .= "text-transform: {$typography['transform']};";
		}

		$emit = function ( $prop, $field ) use ( &$base, &$md, &$sm, $read_device ) {
			$lg  = $read_device( $field, 'lg' );
			$mdv = $read_device( $field, 'md' );
			$smv = $read_device( $field, 'sm' );
			if ( $lg ) {
				$base .= "{$prop}: {$lg['value']}{$lg['unit']};";
			}
			if ( $mdv ) {
				$md .= "{$prop}: {$mdv['value']}{$mdv['unit']};";
			}
			if ( $smv ) {
				$sm .= "{$prop}: {$smv['value']}{$smv['unit']};";
			}
		};

		if ( isset( $typography['size'] ) ) {
			$emit( 'font-size', $typography['size'] );
		}
		if ( isset( $typography['spacing'] ) ) {
			$emit( 'letter-spacing', $typography['spacing'] );
		}
		if ( isset( $typography['height'] ) ) {
			$emit( 'line-height', $typography['height'] );
		}

		return array( 'base' => $base, 'md' => $md, 'sm' => $sm );
	};

	// Spacing helper (margin/padding). Skips all-zero values so an ID selector
	// doesn't override theme rules (e.g. Astra's .alignfull breakout) needlessly.
	$generate_spacing_css = function ( $selector, $spacing, $property ) {
		$base = '';
		$md   = '';
		$sm   = '';

		$parse_spacing_value = function ( $value, $unit ) {
			return wpddbp_parse_dim_sides( $value, $unit );
		};
		$is_all_zero = function ( $parsed ) {
			if ( '' === $parsed ) {
				return true;
			}
			$tokens = preg_split( '/\s+/', trim( $parsed ) );
			foreach ( $tokens as $t ) {
				if ( '0' !== $t ) {
					return false;
				}
			}
			return true;
		};

		if ( ! empty( $spacing['lg']['value'] ) && ! empty( $spacing['lg']['unit'] ) ) {
			$parsed_value = $parse_spacing_value( $spacing['lg']['value'], $spacing['lg']['unit'] );
			if ( '' !== $parsed_value && ! $is_all_zero( $parsed_value ) ) {
				$base .= "{$property}: {$parsed_value};";
			}
		}
		if ( ! empty( $spacing['md']['value'] ) && ! empty( $spacing['md']['unit'] ) ) {
			$parsed_value = $parse_spacing_value( $spacing['md']['value'], $spacing['md']['unit'] );
			if ( '' !== $parsed_value && ! $is_all_zero( $parsed_value ) ) {
				$md .= "{$property}: {$parsed_value};";
			}
		}
		if ( ! empty( $spacing['sm']['value'] ) && ! empty( $spacing['sm']['unit'] ) ) {
			$parsed_value = $parse_spacing_value( $spacing['sm']['value'], $spacing['sm']['unit'] );
			if ( '' !== $parsed_value && ! $is_all_zero( $parsed_value ) ) {
				$sm .= "{$property}: {$parsed_value};";
			}
		}

		return array( 'base' => $base, 'md' => $md, 'sm' => $sm );
	};

	$generate_color_css = function ( $color, $type ) {
		if ( ! empty( $color ) ) {
			return $type . ': ' . $color . ';';
		}
		return '';
	};

	$generate_border_radius_css = function ( $border_radius ) {
		if ( empty( $border_radius['value'] ) ) {
			return '';
		}
		$unit      = isset( $border_radius['unit'] ) && '' !== $border_radius['unit'] ? $border_radius['unit'] : 'px';
		$formatted = wpddbp_parse_dim_sides( $border_radius['value'], $unit );
		if ( '' === $formatted ) {
			return '';
		}
		$tokens   = preg_split( '/\s+/', trim( $formatted ) );
		$all_zero = true;
		foreach ( $tokens as $t ) {
			if ( '0' !== $t ) {
				$all_zero = false;
				break;
			}
		}
		if ( $all_zero ) {
			return '';
		}
		return 'border-radius: ' . $formatted . ';';
	};

	$generate_border_css = function ( $border ) {
		if ( empty( $border['style'] ) || 'none' === $border['style'] ) {
			return '';
		}
		$u     = isset( $border['width']['unit'] ) && '' !== $border['width']['unit'] ? $border['width']['unit'] : 'px';
		$w_css = wpddbp_parse_dim_sides( $border['width']['value'] ?? '', $u );
		if ( '' === $w_css || '0' === preg_replace( '/\s+/', '', $w_css ) || '0000' === preg_replace( '/\s+/', '', $w_css ) ) {
			return '';
		}
		$color = ! empty( $border['color'] ) ? $border['color'] : 'transparent';
		return "border-width: {$w_css};border-style: {$border['style']};border-color: {$color};";
	};

	$generate_box_shadow_css = function ( $s ) {
		if ( empty( $s['preset'] ) || 'none' === $s['preset'] ) {
			return '';
		}
		$presets = array(
			'subtle' => '0 1px 3px rgba(0,0,0,0.08)',
			'medium' => '0 4px 12px rgba(0,0,0,0.12)',
			'strong' => '0 12px 32px rgba(0,0,0,0.18)',
		);
		if ( 'custom' !== $s['preset'] ) {
			return isset( $presets[ $s['preset'] ] ) ? "box-shadow: {$presets[ $s['preset'] ]};" : '';
		}
		$c     = isset( $s['custom'] ) ? $s['custom'] : array();
		$x     = isset( $c['x'] ) ? (int) $c['x'] : 0;
		$y     = isset( $c['y'] ) ? (int) $c['y'] : 0;
		$blur  = isset( $c['blur'] ) ? (int) $c['blur'] : 0;
		$sprd  = isset( $c['spread'] ) ? (int) $c['spread'] : 0;
		$color = ! empty( $c['color'] ) ? $c['color'] : 'rgba(0,0,0,0.1)';
		$inset = ! empty( $c['inset'] ) ? 'inset ' : '';
		return "box-shadow: {$inset}{$x}px {$y}px {$blur}px {$sprd}px {$color};";
	};

	if ( 'doc-booker-pro/advanced-heading' === $block_name ) {
		$css .= wpddbp_generate_heading_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css
		);
	} elseif ( 'doc-booker-pro/wrapper' === $block_name ) {
		$css .= wpddbp_generate_wrapper_block_css( $attributes, $unique_id, $generate_spacing_css );
	} elseif ( 'doc-booker-pro/call-to-action' === $block_name ) {
		$css .= wpddbp_generate_cta_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css
		);
	} elseif ( 'doc-booker-pro/info-box' === $block_name ) {
		$css .= wpddbp_generate_info_box_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_border_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/countdown' === $block_name ) {
		$css .= wpddbp_generate_countdown_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css
		);
	} elseif ( 'doc-booker-pro/slider' === $block_name ) {
		$css .= wpddbp_generate_slider_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/faq' === $block_name ) {
		$css .= wpddbp_generate_faq_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/services' === $block_name ) {
		$css .= wpddbp_generate_services_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/process-steps' === $block_name ) {
		$css .= wpddbp_generate_process_steps_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/office-hours' === $block_name ) {
		$css .= wpddbp_generate_office_hours_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/hero' === $block_name ) {
		$css .= wpddbp_generate_hero_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/doctors' === $block_name ) {
		$css .= wpddbp_generate_doctors_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/departments' === $block_name ) {
		$css .= wpddbp_generate_departments_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/doctor-awards' === $block_name ) {
		$css .= wpddbp_generate_awards_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/doctor-fee-badge' === $block_name ) {
		$css .= wpddbp_generate_fee_badge_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/clinics' === $block_name ) {
		$css .= wpddbp_generate_clinics_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/quick-book' === $block_name ) {
		$css .= wpddbp_generate_quick_book_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/doctor-schedule' === $block_name ) {
		$css .= wpddbp_generate_doctor_schedule_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/booking-form' === $block_name ) {
		$css .= wpddbp_generate_booking_form_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/doctor-stats' === $block_name ) {
		$css .= wpddbp_generate_doctor_stats_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} elseif ( 'doc-booker-pro/testimonials' === $block_name ) {
		$css .= wpddbp_generate_testimonials_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css,
			$generate_box_shadow_css
		);
	} else {
		// Default: advanced-heading (back-compat for calls that omit $block_name).
		$css .= wpddbp_generate_heading_block_css(
			$attributes,
			$unique_id,
			$generate_typography_css,
			$generate_spacing_css,
			$generate_color_css,
			$generate_border_radius_css
		);
	}

	return $css;
}
