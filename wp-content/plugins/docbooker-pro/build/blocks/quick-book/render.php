<?php
/**
 * Quick Book block — server render (Phase 5).
 *
 * Resolves a single doctor (picked / current page / ?doctor= URL override) and
 * renders one of 3 compact booking widgets (sidebar / sticky / hero). The booking
 * CTA routes to the live Pro flow via the shared booking-button helper. Per-instance
 * CSS is injected on singular views by the blocks bootstrap.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/utils/quick-book-data.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$align    = in_array( ( $attributes['alignment'] ?? 'left' ), array( 'left', 'center', 'right' ), true ) ? $attributes['alignment'] : 'left';
$layout   = in_array( ( $attributes['layoutType'] ?? 'sidebar' ), array( 'sidebar', 'sticky', 'hero' ), true ) ? $attributes['layoutType'] : 'sidebar';

$widget = wpddbp_quickbook_render( $attributes, $block_id );

if ( '' === $widget ) {
	return;
}

echo '<div id="' . $block_id . '" class="wpddbp-qb-wrap wpddbp-qb-wrap--' . esc_attr( $layout ) . ' wpddbp-qb-wrap--' . esc_attr( $align ) . '">'
	. $widget // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*() in the render helper.
	. '</div>';
