<?php

namespace WpDreamers\WPDDB\Traits;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
trait SingletonTrait{

    protected static $instance = null;


    final public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    final public function __clone() {
    }

    public function __sleep() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'doc-booker' ), '1.0' );
        die();
    }


    final public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'doc-booker' ), '1.0' );
        die();
    }
}