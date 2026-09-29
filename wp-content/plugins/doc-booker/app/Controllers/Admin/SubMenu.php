<?php

namespace WpDreamers\WPDDB\Controllers\Admin;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class SubMenu{
    use SingletonTrait;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_sub_menu' ] );
    }
    public function register_sub_menu() {
        $parent_menu_slug = 'edit.php?post_type=' . wpddb()->post_type_doctor;
        $menu_capability = 'manage_options';
        add_submenu_page(
            $parent_menu_slug,
            esc_html__( 'Settings', 'doc-booker' ),
            esc_html__( 'Settings', 'doc-booker' ),
            $menu_capability,
            'settings',
            [ $this, 'wpddb_settings_page_callback' ],
            45
        );
	    if (current_user_can('wpddb_manage_booking_system')) {
		    add_submenu_page(
			    $parent_menu_slug,
			    esc_html__('Booking Management','doc-booker'),
			    esc_html__('Booking Management','doc-booker'),
			    'wpddb_manage_booking_system',
			    'booking-management',
			    [$this, 'render_booking_management_page'],
			    50
		    );
	    }
        do_action( 'wpddb/add/more/submenu', $parent_menu_slug, $menu_capability );
    }
    public function wpddb_settings_page_callback(){
        echo '<div class="wrap"><div id="wpddb_settings_page_root"></div></div>';
    }

	public function render_booking_management_page(  ) {
		echo '<div class="wrap"><div id="wpddb_booking_management"></div></div>';
	}
}