<?php
/**
 * Doctor Awards block — server render (Tier B, Phase 3).
 *
 * Static server render of doctor awards in the chosen layout (trophy-wall masonry
 * or per-doctor credential-list). No AJAX — the only frontend script is the
 * optional credential-list print button (view.js). Per-instance CSS is injected
 * on singular views by the blocks bootstrap.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/utils/awards-data.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$layout   = $attributes['layoutType'] ?? 'trophy-wall';
$allowed  = array( 'trophy-wall', 'credential-list' );
if ( ! in_array( $layout, $allowed, true ) ) {
	$layout = 'trophy-wall';
}
$align       = $attributes['align'] ?? '';
$align_class = $align ? ' align' . preg_replace( '/[^a-z]/', '', $align ) : '';

$body = wpddbp_awards_render( $attributes );

$root_class = 'wpddbp-awards wpddbp-awards--' . $layout . $align_class;

echo '<div id="' . $block_id . '" class="' . esc_attr( $root_class ) . '" data-block="doctor-awards">'
	. $body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*() in the render helpers.
	. '</div>';
