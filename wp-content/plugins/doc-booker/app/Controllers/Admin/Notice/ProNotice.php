<?php
namespace WpDreamers\WPDDB\Controllers\Admin\Notice;

use WpDreamers\WPDDB\Controllers\Abstracts\Discount\Discount;
use WpDreamers\WPDDB\Traits\SingletonTrait;

class ProNotice extends Discount {
    use SingletonTrait;
    public function the_options() {
        return [
            'option_name'    => 'pro-plugin-notice',
            'global_check'   => isset( $GLOBALS['pro_plugin_notice'] ),
            'plugin_name'    => 'DocBooker',
            'notice_for'     => 'Exclusive Pro Plugin Offer!',
            'download_link'  => 'https://docbooker.wpdreamers.com/',
            'start_date'     => '10 December 2023',
            'end_date'       => '30 January 2029',
            'notice_message' => 'Unlock the full potential of DocBooker with our Pro Plugins! Enjoy advanced features and premium support to take your booking system to the next level. <a href="https://docbooker.wpdreamers.com/" target="_blank" style="font-weight: bold; color: #008000;">Get Pro Now!</a>',
        ];
    }
}