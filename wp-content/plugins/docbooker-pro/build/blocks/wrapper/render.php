<?php
/**
 * Wrapper — server render.
 *
 * Emits the wrapper element (tag + id + align/width classes) and echoes the
 * already-parsed inner-block markup ($content) verbatim. The per-instance CSS
 * (bg / padding / margin / inner-width cap) is injected separately by the
 * bootstrap's inline-CSS walker via wpddbp_generate_wrapper_block_css().
 *
 * @var array  $attributes Block attributes (merged with block.json defaults).
 * @var string $content    Rendered inner-blocks HTML (already parsed by WP).
 */

defined( 'ABSPATH' ) || exit;

$block_id      = isset( $attributes['blockId'] ) ? esc_attr( $attributes['blockId'] ) : '';
$wrapper_tag   = $attributes['wrapperTag'] ?? 'div';
$content_width = $attributes['contentWidth'] ?? 'fullWidth';
$align         = $attributes['align'] ?? 'full';

$allowed_tags = array( 'div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav' );
if ( ! in_array( $wrapper_tag, $allowed_tags, true ) ) {
	$wrapper_tag = 'div';
}

$align_class = $align ? ' align' . $align : '';
$class_list  = 'wpddbp-wrapper' . $align_class;
if ( 'custom' === $content_width ) {
	$class_list .= ' wpddbp-wrapper--custom-width';
}
?>
<<?php echo $wrapper_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted above. ?> id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( $class_list ); ?>">
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner-block markup already parsed/escaped by WP. ?>
</<?php echo $wrapper_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted above. ?>>
