<?php
/**
 * @package DocBooker
 */
namespace WpDreamers\WPDDB\Controllers\Abstracts\Discount;



if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

abstract class Discount {
    /**
     * @var array
     */
    protected $options = [];

    /**
     * Class Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'show_notice' ] );
    }

    /**
     * @return array
     */
    abstract public function the_options();
    /**
     * @return void
     */
    public function show_notice() {
        $defaults      = [
            'download_link'  => 'https://wpdreamers.com/',
            'global_check'   => false,
            'plugin_name'    => 'DocBooker',
            'image_url'      => WPDDB_URL . '/assets/admin/img/doctime-logo-96x96.png',
            'option_name'    => '',
            'start_date'     => '',
            'end_date'       => '',
            'notice_for'     => 'Cyber Monday Deal!!',
            'notice_message' => '',
        ];
        $options       = apply_filters( 'docbooker_offer_notice', $this->the_options() );
        $this->options = wp_parse_args( $options, $defaults );
        $current       = time();
        $start         = strtotime( $this->options['start_date'] );
        $end           = strtotime( $this->options['end_date'] );

        if ( $this->options['global_check'] ) {
            return;
        }

        // Black Friday Notice.
        if ( ! wpddb()->has_pro() && $start <= $current && $current <= $end ) {
            if ( get_option( $this->options['option_name'] ) != '1' ) {
                if ( ! isset( $GLOBALS['docbooker__notice'] ) ) {
                    $GLOBALS['docbooker__notice'] = 'docbooker__notice';
                    $this->offer_notice();
                }
            }
        }
    }
    /**
     * Black Friday Notice.
     *
     * @return void
     */
    private function offer_notice() {
        add_action(
            'admin_enqueue_scripts',
            function () {
                wp_enqueue_script( 'jquery' );
            }
        );

        add_action(
            'admin_notices',
            function () {
                ?>
                <style>
                    .notice.docbooker-offer-notice {
                        --e-button-context-color: #0a4b78;
                        --e-button-context-color-dark: #005dd0;
                        --e-button-context-tint: rgb(75 47 157/4%);
                        --e-focus-color: rgb(75 47 157/40%);
                        display:grid;
                        grid-template-columns: 100px auto;
                        padding-top: 15px;
                        padding-bottom: 12px;
                        column-gap: 15px;
                    }

                    .docbooker-offer-notice img {
                        grid-row: 1 / 4;
                        align-self: center;
                        justify-self: center;
                    }

                    .docbooker-offer-notice h3,
                    .docbooker-offer-notice p {
                        margin: 0 !important;
                    }

                    .docbooker-offer-notice .notice-text {
                        margin: 0 0 2px;
                        padding: 5px 0;
                        max-width: 100%;
                        font-size: 14px;
                    }

                    .docbooker-offer-notice .button-primary,
                    .docbooker-offer-notice .button-dismiss {
                        display: inline-block;
                        border: 0;
                        border-radius: 3px;
                        background: var(--e-button-context-color-dark);
                        color: #fff;
                        vertical-align: middle;
                        text-align: center;
                        text-decoration: none;
                        white-space: nowrap;
                        margin-right: 5px;
                        transition: all 0.3s;
                    }

                    .docbooker-offer-notice .button-primary:hover,
                    .docbooker-offer-notice .button-dismiss:hover {
                        background: var(--e-button-context-color);
                        color: #fff;
                    }

                    .docbooker-offer-notice .button-primary:focus,
                    .docbooker-offer-notice .button-dismiss:focus {
                        box-shadow: 0 0 0 1px #fff, 0 0 0 3px var(--e-button-context-color);
                        background: var(--e-button-context-color);
                        color: #fff;
                    }

                    .docbooker-offer-notice .button-dismiss {
                        border: 1px solid;
                        background: 0 0;
                        color: var(--e-button-context-color);
                        background: #fff;
                    }
                </style>

                <div class="docbooker-offer-notice notice notice-info is-dismissible"
                     data-docbookerdismissable="docbooker_offer">
                    <img alt="<?php echo esc_attr( $this->options['plugin_name'] ); ?>"
                         src="<?php echo esc_url( $this->options['image_url'] ); ?>"
                         width="100px"
                         height="100px"/>
                    <h3><?php echo sprintf( '%s – %s', esc_html( $this->options['plugin_name'] ), esc_html( $this->options['notice_for'] ) ); ?></h3>

                    <p class="notice-text">
                        <?php echo wp_kses_post( $this->options['notice_message'] ); ?>
                    </p>
                    <p>
                        <a class="button button-primary"
                           href="<?php echo esc_url( $this->options['download_link'] ); ?>" target="_blank">Buy Now</a>
                        <a class="button button-dismiss" href="#">Dismiss</a>
                    </p>
                </div>

                <?php
            }
        );

        add_action(
            'admin_footer',
            function () {
                ?>
                <script type="text/javascript">
                    (function ($) {
                        $(function () {
                            setTimeout(function () {
                                $('div[data-docbookerdismissable] .notice-dismiss, div[data-docbookerdismissable] .button-dismiss')
                                    .on('click', function (e) {
                                        e.preventDefault();
                                        $.post(ajaxurl, {
                                            'action': 'docbooker_dismiss_offer_admin_notice',
                                            'nonce': <?php echo wp_json_encode( wp_create_nonce( 'docbooker-offer-dismissible-notice' ) ); ?>
                                        });
                                        $(e.target).closest('.is-dismissible').remove();
                                    });
                            }, 1000);
                        });
                    })(jQuery);
                </script>
                <?php
            }
        );

        add_action(
            'wp_ajax_docbooker_dismiss_offer_admin_notice',
            function () {
                check_ajax_referer( 'docbooker-offer-dismissible-notice', 'nonce' );
                if ( ! empty( $this->options['option_name'] ) ) {
                    update_option( $this->options['option_name'], '1' );
                }
                wp_die();
            }
        );
    }
}