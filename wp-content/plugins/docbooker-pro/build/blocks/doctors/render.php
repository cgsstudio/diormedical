<?php
/**
 * Doctors block — server render (Tier B, Phase 3).
 *
 * Initial server paint: applies the §11a filter from the URL (?wpddbp_clinic /
 * ?wpddbp_dept) so the first paint is already filtered (no JS flicker), renders the
 * grid + the filter bar + the load-more button, and emits the wrapper the view.js
 * reads (data-rest-base + base64 data-attrs so AJAX re-renders use the same args).
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/utils/doctors-data.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'list';
$allowed  = array( 'card', 'hover-reveal', 'feature', 'mini', 'list' );
if ( ! in_array( $layout, $allowed, true ) ) {
	$layout = 'list';
}
$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$enable_filter = ! empty( $attributes['enableFilter'] );

// Initial filter from the URL (shareable filtered links — server-side first paint).
$filter = array( 'clinic_id' => 0, 'department_id' => 0 );
if ( $enable_filter ) {
	$filter['clinic_id']     = isset( $_GET['wpddbp_clinic'] ) ? absint( wp_unslash( $_GET['wpddbp_clinic'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$filter['department_id'] = isset( $_GET['wpddbp_dept'] ) ? absint( wp_unslash( $_GET['wpddbp_dept'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

$grid = wpddbp_doctors_render_grid( $attributes, $filter, 1 );

$filter_bar = $enable_filter ? wpddbp_doctors_render_filter_bar( $attributes, $block_id ) : '';
$filter_pos = ( 'below' === ( $attributes['filterPosition'] ?? 'above' ) ) ? 'below' : 'above';

$load_more = '';
if ( ! empty( $attributes['showLoadMore'] ) && $grid['has_more'] ) {
	$load_more = '<div class="wpddbp-doctors-loadmore-wrap"><button type="button" class="wpddbp-doctors-loadmore" data-loadtext="' . esc_attr( $attributes['loadingText'] ?? __( 'Loading…', 'doc-booker-pro' ) ) . '">' . esc_html( $attributes['loadMoreText'] ?? __( 'Load more', 'doc-booker-pro' ) ) . '</button></div>';
}

$grid_el    = '<div class="wpddbp-doctors-grid" data-loaded="true">' . $grid['html'] . '</div>';
$data_attrs = base64_encode( wp_json_encode( $attributes ) );
$rest_base  = esc_url_raw( rest_url( 'doc-booker-blocks/v1' ) );

$inner = '';
if ( $enable_filter && 'above' === $filter_pos ) {
	$inner .= $filter_bar;
}
$inner .= $grid_el;
if ( $enable_filter && 'below' === $filter_pos ) {
	$inner .= $filter_bar;
}
$inner .= $load_more;

$root_class = 'wpddbp-doctors wpddbp-doctors--' . $layout . $align_class;

echo '<div id="' . $block_id . '" class="' . esc_attr( $root_class ) . '"'
	. ' data-block="doctors" data-block-id="' . $block_id . '"'
	. ' data-rest-base="' . esc_url( $rest_base ) . '"'
	. ' data-per-page="' . (int) ( $attributes['perPage'] ?? 9 ) . '"'
	. ' data-found="' . (int) $grid['found'] . '"'
	. ' data-max-pages="' . (int) $grid['max_pages'] . '"'
	. ' data-attrs="' . esc_attr( $data_attrs ) . '">'
	. $inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*() in the render helpers above.
	. '</div>';
