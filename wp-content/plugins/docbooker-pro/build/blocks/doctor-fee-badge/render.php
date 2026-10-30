<?php
/**
 * Doctor Fee Badge block — server render (Phase 3).
 *
 * Renders one fee badge (pill / starburst / flat) showing a doctor's real
 * booking fee. No frontend script. Per-instance CSS is injected on singular
 * views by the blocks bootstrap.
 *
 * @var array $attributes Block attributes (merged with block.json defaults).
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/utils/fee-badge-data.php';

$block_id = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$align    = in_array( ( $attributes['alignment'] ?? 'left' ), array( 'left', 'center', 'right' ), true ) ? $attributes['alignment'] : 'left';

$badge = wpddbp_feebadge_render( $attributes, $block_id );

echo '<div id="' . $block_id . '" class="wpddbp-feebadge-wrap wpddbp-feebadge-wrap--' . esc_attr( $align ) . '">'
	. $badge // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_*() in the render helper.
	. '</div>';
