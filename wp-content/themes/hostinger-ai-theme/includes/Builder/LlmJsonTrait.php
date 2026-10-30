<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

trait LlmJsonTrait {

    private function decode_llm_json( $content ): array {
        if ( ! is_string( $content ) ) {
            return array();
        }

        $content = trim( $content );

        if ( preg_match( '/^```[a-zA-Z]*\s*(.*?)\s*```$/s', $content, $matches ) ) {
            $content = $matches[1];
        }

        $decoded = json_decode( $content, true );

        return is_array( $decoded ) ? $decoded : array();
    }
}
