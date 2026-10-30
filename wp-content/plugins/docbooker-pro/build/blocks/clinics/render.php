<?php
/**
 * Clinics block — server render (Tier B, Phase 4).
 *
 * Static server render of the `wpddb_clinic` CPT in the chosen layout (card /
 * feature / gallery / map / compact). The only frontend script (view.js) drives
 * the gallery slideshow + the map list↔pin sync. Per-instance CSS is injected on
 * singular views by the blocks bootstrap.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/utils/clinics-data.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'card';
$allowed  = array( 'card', 'feature', 'gallery', 'map', 'compact' );
if ( ! in_array( $layout, $allowed, true ) ) {
	$layout = 'card';
}
$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$body = wpddbp_clinics_render( $attributes );

$root_class = 'wpddbp-clinics wpddbp-clinics--' . $layout . $align_class;

echo '<div id="' . $block_id . '" class="' . esc_attr( $root_class ) . '" data-block="clinics">'
	. $body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*() in the render helpers.
	. '</div>';
