<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use Hostinger\AiTheme\Constants\ApiRoutes;

defined( 'ABSPATH' ) || exit;

class Fonts {

	use SoftwareIdTrait;

    // Heading -> Body
    public const FONT_COMBINATION = array(
        "Caudex" => "Roboto",
        "Cormorant" => "Montserrat",
        "DM Serif Display" => "Poppins",
        "Fira Sans" => "Montserrat",
        "Gruppo" => "Montserrat",
        "Junge" => "Montserrat",
        "Lato" => "Lato",
        "Nunito Sans" => "Nunito Sans",
        "Playfair Display" => "Montserrat",
        "Poppins" => "Poppins",
        "Prompt" => "Lato",
        "Roboto" => "Lato",
        "Montserrat" => "IBM Plex Mono",
        "Prata" => "Montserrat",
        "Prosto One" => "Catamaran",
        "Titillium Web" => "Open Sans",
        "Trirong" => "Manrope",
    );

	private RequestClient $wh_api_client;

	public function setWhApiClient( RequestClient $wh_api_client ): void {
		$this->wh_api_client = $wh_api_client;
	}

	public function generate_font_options(): array {
		$software_id = $this->get_software_id();
		if ( empty( $software_id ) ) {
			return array();
		}

		$global_settings    = wp_get_global_settings();
		$theme_fonts        = $global_settings['typography']['fontFamilies']['theme'] ?? array();
		$untranslated_names = $this->get_untranslated_font_names();

		$font_family_map  = array();
		$display_name_map = array();
		foreach ( $theme_fonts as $font ) {
			if ( empty( $font['name'] ) ) {
				continue;
			}

			$source_name                      = $untranslated_names[ $font['slug'] ?? '' ] ?? $font['name'];
			$display_name_map[ $source_name ] = $font['name'];

			if ( ! empty( $font['fontFamily'] ) ) {
				$font_family_map[ $source_name ] = $font['fontFamily'];
			}
		}

		$available_fonts = array_keys( $display_name_map );

		$params = array(
			'brandName'      => get_option( 'hostinger_ai_brand_name', '' ),
			'websiteTypes'   => WebsiteTypeHelper::get_website_types(),
			'description'    => get_option( 'hostinger_ai_description', '' ),
			'availableFonts' => $available_fonts,
            'count'          => 4,
		);

		$font_pairs = $this->wh_api_client->post( ApiRoutes::INSTALLATIONS_BASE . $software_id . '/content/fonts', $params );

		if ( ! empty( $font_pairs ) ) {
			foreach ( $font_pairs as &$pair ) {
				$heading_name = $pair['fonts']['heading']['family'] ?? '';
				$body_name    = $pair['fonts']['body']['family'] ?? '';

				$pair['fonts']['heading'] = array(
					'family'     => $display_name_map[ $heading_name ] ?? $heading_name,
					'fontFamily' => $font_family_map[ $heading_name ] ?? $heading_name,
				);
				$pair['fonts']['body'] = array(
					'family'     => $display_name_map[ $body_name ] ?? $body_name,
					'fontFamily' => $font_family_map[ $body_name ] ?? $body_name,
				);

				unset( $pair['rationale'], $pair['cssVars'] );
			}
			unset( $pair );

			update_option( 'hostinger_ai_font_options', $font_pairs, true );

			$first_pair = $font_pairs[0];
			update_option( 'hostinger_ai_font', $first_pair['fonts']['heading']['fontFamily'], true );
			update_option( 'hostinger_ai_body_font_override', $first_pair['fonts']['body']['fontFamily'], true );

			return $font_pairs;
		}

		return array();
	}

	public function get_body_font( array $theme_json_data, string $main_font_family ): string {
		$override = get_option( 'hostinger_ai_body_font_override', false );
		if ( $override ) {
			return $override;
		}

		$font = $this->get_body_font_by_heading_font( $theme_json_data, $main_font_family );
		return $font['fontFamily'];
	}

	public function get_main_font( array $theme_json_data ): string {
		$theme_fonts = $this->get_theme_fonts( $theme_json_data );
		$font_keys = $this->get_font_keys( $theme_json_data );
		$current_font = get_option( 'hostinger_ai_font', false );

		// Attempt to get the already selected font, otherwise select a random one from the available fonts.
		if ( $current_font && in_array( $current_font, array_column( $theme_fonts, 'fontFamily' ) ) ) {
			$selected_font = $current_font;
		} elseif ( ! empty( $font_keys ) ) {
			$random_key = array_rand( $font_keys );
			$selected_font = $theme_fonts[$random_key]['fontFamily'];
			update_option( 'hostinger_ai_font', $selected_font );
		} elseif ( ! empty( $theme_fonts ) ) {
			$selected_font = $theme_fonts[0]['fontFamily'];
			update_option( 'hostinger_ai_font', $selected_font );
		} else {
			$selected_font = 'system-ui';
		}

		return $selected_font;
	}

	protected function get_body_font_by_heading_font( array $theme_json_data, string $font_family_name ): array {
		$theme_fonts = $this->get_theme_fonts( $theme_json_data );
		$font = $this->get_font_by_font_family( $theme_json_data, $font_family_name );
		$font_name = $font['name'] ?? '';
		$body_font = self::FONT_COMBINATION[$font_name] ?? '';

		// If the body font is not found, use the main font as heading font as fallback.
		if ( empty( $body_font ) || ! in_array( $body_font, array_column( $theme_fonts, 'name' ) ) ) {
			return array(
				'fontFamily' => $font_family_name,
			);
		}

		return $this->get_theme_font_by_name( $theme_json_data, $body_font );
	}

	protected function get_font_keys( array $theme_json_data ): array {
		$theme_fonts = array_column( $this->get_theme_fonts( $theme_json_data ), 'name' );
		return array_intersect( $theme_fonts, array_keys( self::FONT_COMBINATION ) );
	}

	protected function get_theme_font_by_name( array $theme_json_data, string $font_name ): array {
		$theme_fonts = $this->get_theme_fonts( $theme_json_data );
		$font = array_filter( $theme_fonts, function( $font ) use ( $font_name ) {
			return $font['name'] === $font_name;
		});

		reset( $font );
		return current( $font ) ?? array();
	}

	protected function get_font_by_font_family( array $theme_json_data, string $font_family_name ): array {
		$theme_fonts = $this->get_theme_fonts( $theme_json_data );
		$font = array_filter( $theme_fonts, function( $font ) use ( $font_family_name ) {
			return $font['fontFamily'] === $font_family_name;
		});

		reset( $font );
		return current( $font ) ?? array();
	}

	protected function get_theme_fonts( array $theme_json_data ): array {
		return $theme_json_data['settings']['typography']['fontFamilies']['theme'] ?? array();
	}

	private function get_untranslated_font_names(): array {
		$theme_json = wp_json_file_decode( dirname( __DIR__, 2 ) . '/theme.json', array( 'associative' => true ) );
		$fonts      = $theme_json['settings']['typography']['fontFamilies'] ?? array();

		return array_column( $fonts, 'name', 'slug' );
	}
}

