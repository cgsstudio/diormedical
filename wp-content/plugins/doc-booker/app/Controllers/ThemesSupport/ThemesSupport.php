<?php
/**
 * @package WpdDocBooker
 */
namespace WpDreamers\WPDDB\Controllers\ThemesSupport;



use WpDreamers\WPDDB\Traits\SingletonTrait;
use WpDreamers\WPDDB\Controllers\ThemesSupport\Astra\ThemeSupport as AstraThemeSupport;
use WpDreamers\WPDDB\Controllers\ThemesSupport\Kadence\ThemeSupport as KadenceThemeSupport;
use WpDreamers\WPDDB\Controllers\ThemesSupport\GeneratePress\ThemeSupport as GeneratePressThemeSupport;
use WpDreamers\WPDDB\Controllers\ThemesSupport\Blocksy\ThemeSupport as BlocksyThemeSupport;
use WpDreamers\WPDDB\Controllers\ThemesSupport\Neve\ThemeSupport as NeveThemeSupport;
use WpDreamers\WPDDB\Controllers\ThemesSupport\Storefront\ThemeSupport as StorefrontThemeSupport;
use WpDreamers\WPDDB\Controllers\ThemesSupport\Divi\ThemeSupport as DiviThemeSupport;


if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
class ThemesSupport{

	use SingleTonTrait;
	public $current_theme;

	public function __construct() {

		$theme = $this->get_theme_name();
		if ( 'Astra' === $theme ) {
			AstraThemeSupport::instance();
		} elseif ( 'Kadence' === $theme ) {
			KadenceThemeSupport::instance();
		} elseif ( 'GeneratePress' === $theme ) {
			GeneratePressThemeSupport::instance();
		} elseif ( 'Blocksy' === $theme ) {
			BlocksyThemeSupport::instance();
		} elseif ( 'Neve' === $theme ) {
			NeveThemeSupport::instance();
		} elseif ( 'Storefront' === $theme ) {
			StorefrontThemeSupport::instance();
		} elseif ( 'Divi' === $theme || 'Divi' === get_template() ) {
			// get_template() catches Divi child themes (e.g. "Divi Child"), whose
			// theme name is not "Divi" but whose parent template slug is.
			DiviThemeSupport::instance();
		}

	}

	public  function get_theme_name(  ) {
		$theme = wp_get_theme();
		$this->current_theme = $theme->name;
		return $this->current_theme;
	}

}
