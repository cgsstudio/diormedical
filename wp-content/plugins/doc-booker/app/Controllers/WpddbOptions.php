<?php

namespace WpDreamers\WPDDB\Controllers;

class WpddbOptions {
	public static function get_option( $option, $section, $default = '' ) {

		$options = get_option( $section );

		if ( isset( $options[$option] ) ) {
			return $options[$option];
		}

		return $default;
	}

    public static function wpddb_get_all_settings_options() {
        $options_keys = apply_filters('wpddb_get_all_settings_options', [
            'wpddb_page_settings',
            'wpddb_permalinks_settings',
            'wpddb_doctor_settings',
            'wpddb_clinic_settings',
            'wpddb_style_settings',
        ]);
        $settings = ['options' => []];

        foreach ($options_keys as $key) {
            $settings['options'][$key] = get_option($key) ?? [];
        }

        return $settings;
    }
}