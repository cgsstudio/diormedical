<?php
/**
 * Departments block — server render (Tier B, Phase 3).
 *
 * Static server render of the `wpddb_doctor_department` taxonomy in the chosen
 * layout (grid / pill-cloud / icon-cards). No AJAX — the only frontend script is
 * the optional pill-cloud client-side name filter (view.js). Per-instance CSS is
 * injected on singular views by the blocks bootstrap.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/utils/departments-data.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'grid';
$allowed  = array( 'grid', 'pill-cloud', 'icon-cards' );
if ( ! in_array( $layout, $allowed, true ) ) {
	$layout = 'grid';
}
$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$search = wpddbp_departments_render_search( $attributes );
$body   = wpddbp_departments_render( $attributes );

$root_class = 'wpddbp-departments wpddbp-departments--' . $layout . $align_class;

echo '<div id="' . $block_id . '" class="' . esc_attr( $root_class ) . '" data-block="departments">'
	. $search // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*() in the render helper.
	. $body   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*() in the render helpers.
	. '</div>';
