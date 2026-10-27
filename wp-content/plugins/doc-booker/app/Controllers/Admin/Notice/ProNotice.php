<?php
namespace WpDreamers\WPDDB\Controllers\Admin\Notice;

use WpDreamers\WPDDB\Controllers\Abstracts\Discount\Discount;
use WpDreamers\WPDDB\Traits\SingletonTrait;

class ProNotice extends Discount {
    use SingletonTrait;
    public function the_options() {
        return [
            'option_name'    => 'pro-plugin-notice',
            'global_check'   => isset( $GLOBALS['pro_plugin_notice'] ) || get_transient( ReviewNotice::QUIET ),
            'plugin_name'    => 'DocBooker',
            'notice_for'     => __( 'Do more with DocBooker Pro', 'doc-booker' ),
            'download_link'  => 'https://docbooker.wpdreamers.com/',
            'start_date'     => '10 December 2023',
            'end_date'       => '30 January 2029',
            'notice_message' => __( 'Take payments online with Stripe or Razorpay, give your front desk and clinic managers their own dashboards, print bills, share lab reports with patients and start from ready-made clinic website designs.', 'doc-booker' ),
        ];
    }
}