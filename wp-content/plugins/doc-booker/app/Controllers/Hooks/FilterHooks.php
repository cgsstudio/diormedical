<?php

namespace WpDreamers\WPDDB\Controllers\Hooks;

use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Traits\SingletonTrait;
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class FilterHooks {
	use SingletonTrait;
	public function __construct() {
		add_filter( 'wp_kses_allowed_html', [ __CLASS__, 'custom_post_tags' ], 10, 2 );
		add_filter( 'display_post_states', array( $this, 'add_display_post_states' ), 10, 2 );
	}
	public static function custom_post_tags( $tags, $context ) {

		$tags['input']  = [
			'type'        => true,
			'class'       => true,
			'name'        => true,
			'step'        => true,
			'min'         => true,
			'title'       => true,
			'size'        => true,
			'pattern'     => true,
			'inputmode'   => true,
			'checked'     => true,
			'value'       => true,
			'id'          => true,
			'placeholder' => true,
		];
		$tags['select'] = array(
			'name'     => true,
			'label'    => true,
			'class'    => true,
			'id'       => true,
			'multiple' => true,
			'desc'     => true,
			'type'     => true,
			'default'  => true,
		);
		$tags['option'] = [
			'value'    => true,
			'selected' => true,
		];

		return $tags;
	}
	public function add_display_post_states( $post_states, $post ) {
		$page_settings = Helper::get_page_ids();
		$pList         = Helper::get_custom_page_list();
		foreach ( $page_settings as $type => $id ) {
			if ( $post->ID == $id ) {
				$post_states[] = $pList[ $type ]['title'] . " " . esc_html__( "Page", "doc-booker" );
			}
		}

		return $post_states;
	}
}