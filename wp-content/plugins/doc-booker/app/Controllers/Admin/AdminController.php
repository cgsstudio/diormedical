<?php

namespace WpDreamers\WPDDB\Controllers\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class AdminController{
    public function __construct() {
        RegisterPostType::init();
    }
}