<?php

namespace WpDreamers\WPDDB\Widgets;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class CustomWidgetsInit{
    use SingleTonTrait;

    public array $widgets;

    public function __construct() {

        $this->widgets =  array(
            'DoctorFilterWidget',
        );

    }

    public function custom_widgets() {

        foreach ( $this->widgets as $widget ) {

            $class = __NAMESPACE__ . '\\' . $widget;
            if ( class_exists( $class ) ) {
                register_widget( $class );
            }

        }
    }
}