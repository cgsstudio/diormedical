<?php
namespace WpDreamers\WPDDB;

use WpDreamers\WPDDB\Controllers\Admin\AdminController;
use WpDreamers\WPDDB\Controllers\Admin\AdminInit;
use WpDreamers\WPDDB\Controllers\Admin\Api\RestApi;
use WpDreamers\WPDDB\Controllers\AjaxControllers;
use WpDreamers\WPDDB\Controllers\BookingManagement;
use WpDreamers\WPDDB\Controllers\Frontend\DocShortcode;
use WpDreamers\WPDDB\Controllers\Hooks\ActionHooks;
use WpDreamers\WPDDB\Controllers\Hooks\AfterSetupTheme;
use WpDreamers\WPDDB\Controllers\Hooks\FilterHooks;
use WpDreamers\WPDDB\Controllers\Hooks\TemplateHooks;
use WpDreamers\WPDDB\Controllers\Hooks\TemplateLoader;
use WpDreamers\WPDDB\Controllers\Model\QueryBuilder;
use WpDreamers\WPDDB\Controllers\ScriptsController;
use WpDreamers\WPDDB\Controllers\Installation;
use WpDreamers\WPDDB\Controllers\StripeCSPOverride;
use WpDreamers\WPDDB\Controllers\ThemesSupport\ThemesSupport;
use WpDreamers\WPDDB\Traits\Constants;
use WpDreamers\WPDDB\Traits\SingletonTrait;
use WpDreamers\WPDDB\Widgets\Widgets;



// Do not allow directly accessing this file.


if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}



final class DocBooker{
    /**
     * Singleton
     */
    use SingletonTrait;
	use Constants;

    /**
     * Class Constructor
     */
    private function __construct() {
        $this->current_theme = wp_get_theme()->get( 'TextDomain' );
        add_action( 'plugins_loaded', [ $this, 'init' ], 100 );
        add_action( 'plugins_loaded', [ Installation::class, 'plugin_activation_time' ] );
	    add_action( 'after_setup_theme', [ AfterSetupTheme::class, 'template_functions' ], 11 );
	    add_action( 'init', [ $this, 'init_hooks' ],0 );
	    add_action( 'init', [ TemplateLoader::class, 'init' ] );
        register_activation_hook( WPDDB_FILE, [ Installation::class, 'activate' ] );
        register_deactivation_hook( WPDDB_FILE, [ Installation::class, 'deactivation' ] );
    }
    public function load_language() {
        load_plugin_textdomain( 'doc-booker', false, WPDDB_ABSPATH . '/languages/' );
    }
    /**
     * Init
     *
     * @return void
     */
    public function init() {
        if ( is_admin() ) {
            AdminInit::instance();
            \WpDreamers\WPDDB\Controllers\Admin\TemplatesPage::instance();
        }else{
			TemplateHooks::init();
			DocShortcode::instance();
        }
        RestApi::instance();
		new QueryBuilder();
		Widgets::instance();
        ScriptsController::instance();
		ActionHooks::instance();
		AjaxControllers::instance();
		FilterHooks::instance();
    }
    public function init_hooks() {
        $this->load_language();
	    ThemesSupport::instance();
	    Installation::check_and_update_plugin_version();
		BookingManagement::instance();
        new AdminController();
    }
    /**
     * Assets url generate with given assets file
     *
     * @param string $file File.
     *
     * @return string
     */
    public function get_assets_uri( $file ) {
        $file = ltrim( $file, '/' );
        return trailingslashit( WPDDB_URL . '/assets' ) . $file;
    }

    /**
     * Get the template path.
     *
     * @return string
     */
    public function get_template_path() {
        return apply_filters( 'wpddb_template_path', 'doc-booker/' );
    }
    /**
     * et the plugin templates path.
     *
     * @return string
     */
    public function get_plugin_template_path() {
        return $this->plugin_path() . '/templates/';
    }

    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit( plugin_dir_path( WPDDB_FILE ) );
    }
    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_url() {
        return untrailingslashit( WPDDB_URL );
    }
    public function has_pro() {
        return function_exists( 'wpddbp' );
    }
}


