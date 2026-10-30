<?php

namespace Hostinger\AiTheme\Builder\Envelopes;

defined( 'ABSPATH' ) || exit;

class BlockInnerContent {

    // serialize_blocks() pairs each child with a null in innerContent.
    public static function prune( array $inner_content, array $survives ): array {
        $child = 0;
        $kept  = array();

        foreach ( $inner_content as $chunk ) {
            if ( $chunk !== null ) {
                $kept[] = $chunk;
                continue;
            }

            if ( $survives[ $child ] ?? true ) {
                $kept[] = null;
            }

            ++$child;
        }

        return array_values( $kept );
    }
}
