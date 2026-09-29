<?php
/**
 * @package DocBooker
 * @var array $pages
 * @var integer $paged
 * @var boolean $showItems
 */

 global $wp_query;

 if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( 1 != $pages ) : ?>
    <nav aria-label="Page navigation" role="navigation">
        <ul class="wpddb-pagination">
			<?php if ( $paged > 1 && $showItems < $pages ) : ?>
                <li class="page-item"><a class="page-link" href="<?php esc_url(get_pagenum_link( $paged - 1 ));  ?>"
                                         aria-label="Previous Page"><span>&laquo;</span></a></li>
			<?php endif; ?>

			<?php for ( $i = 1; $i <= $pages; $i ++ ): ?>
				<?php if ( $paged == $i ) : ?>
                    <li class="page-item active"><span class="page-link"><?php echo absint($i); ?></span>
                    </li>
				<?php else: ?>
                    <li class="page-item"><a class="page-link" href="<?php echo esc_url(get_pagenum_link( $i )); ?>"><?php echo absint($i); ?></a></li>
				<?php endif; ?>
			<?php endfor; ?>

			<?php if ( $paged < $pages && $showItems < $pages ) : ?>
                <li class="page-item"><a class="page-link" href="<?php echo esc_url(get_pagenum_link( $paged + 1 )); ?>"
                                         aria-label="Next Page"><span>&raquo;</span></a></li>
			<?php endif; ?>

        </ul>
    </nav>
<?php endif;