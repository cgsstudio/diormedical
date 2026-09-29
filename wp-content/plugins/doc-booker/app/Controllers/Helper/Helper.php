<?php

namespace WpDreamers\WPDDB\Controllers\Helper;

use DateTime;
use DateTimeZone;
use WpDreamers\WPDDB\Controllers\Model\ThumbnailSizeGenerator;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class Helper{
    /**
     * Prints HTMl.
     *
     * @param string $html HTML.
     * @param bool   $allHtml All HTML.
     *
     * @return void
     */
    public static function print_html( $html, $allHtml = false ) {
        if ( ! $html ) {
            return;
        }
        if ( $allHtml ) {
            echo stripslashes_deep( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo wp_kses_post( stripslashes_deep( $html ) );
        }
    }
    /**
     * Allowed HTML for wp_kses.
     *
     * @param string $level Tag level.
     *
     * @return mixed
     */
    public static function allowedHtml( $level = 'basic' ) {
        $allowed_html = [];
        switch ( $level ) {
            case 'basic':
                $allowed_html = [
                    'b'      => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'i'      => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'u'      => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'br'     => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'em'     => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'span'   => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'strong' => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'hr'     => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'div'    => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'a'      => [
                        'href'   => [],
                        'title'  => [],
                        'class'  => [],
                        'id'     => [],
                        'target' => [],
                    ],
                ];
                break;

            case 'advanced':
                $allowed_html = [
                    'b'      => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'i'      => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'u'      => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'br'     => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'em'     => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'span'   => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'strong' => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'hr'     => [
                        'class' => [],
                        'id'    => [],
                    ],
                    'a'      => [
                        'href'   => [],
                        'title'  => [],
                        'class'  => [],
                        'id'     => [],
                        'target' => [],
                    ],
                    'input'  => [
                        'type'  => [],
                        'name'  => [],
                        'class' => [],
                        'value' => [],
                    ],
                ];
                break;

            case 'image':
                $allowed_html = [
                    'img' => [
                        'src'      => [],
                        'data-src' => [],
                        'alt'      => [],
                        'height'   => [],
                        'width'    => [],
                        'class'    => [],
                        'id'       => [],
                        'style'    => [],
                        'srcset'   => [],
                        'loading'  => [],
                        'sizes'    => [],
                    ],
                    'div' => [
                        'class' => [],
                    ],
                ];
                break;

            case 'anchor':
                $allowed_html = [
                    'a' => [
                        'href'  => [],
                        'title' => [],
                        'class' => [],
                        'id'    => [],
                        'style' => [],
                    ],
                ];
                break;

            default:
                // code...
                break;
        }

        return $allowed_html;
    }

    /**
     * Safe get a validated HTML tag.
     *
     * @param string $tag HTML tag.
     *
     * @return string
     */
    public static function get_validated_html_tag( $tag ) {
        $allowed_html_wrapper_tags = [
            'a',
            'article',
            'aside',
            'button',
            'div',
            'footer',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'header',
            'main',
            'nav',
            'p',
            'section',
            'span',
        ];

        return in_array( strtolower( $tag ), $allowed_html_wrapper_tags, true ) ? $tag : 'div';
    }

    /**
     * Safe print a validated HTML tag.
     *
     * @param string $tag HTML tag.
     *
     * @return void
     */
    public static function print_validated_html_tag( $tag ) {
        self::print_html( self::get_validated_html_tag( $tag ) );
    }
    /**
     * Render.
     *
     * @param string  $template_name View name.
     * @param array   $args View args.
     * @param boolean $return View return.
     * @return string|void
     */
    public static function render_template( $template_name, $args = [], $return = false ) {
        if ( ! empty( $args ) && is_array( $args ) ) {
            extract( $args );
        }

        $template = [
            $template_name . '.php',
            "doc-booker/{$template_name}.php",
            "doc-booker-pro/{$template_name}.php",
        ];

        if ( $located = locate_template( $template ) ) {
            $template_file = $located;
        } else {

            $template_file = wpddb()->get_plugin_template_path() . $template_name . '.php';

            if ( ! file_exists( $template_file ) && function_exists('wpddbp') ) {
                $pro_template = wpddbp()->get_plugin_template_path() . $template_name . '.php';
                if ( file_exists( $pro_template ) ) {
                    $template_file = $pro_template;
                }
            }
        }

        if ( ! file_exists( $template_file ) ) {
            _doing_it_wrong(
                __FUNCTION__,
                sprintf('<code>%s</code> does not exist.', esc_html( $template_file )),
                '1.7.0'
            );
            return;
        }

        if ( $return ) {
            ob_start();
            include $template_file;
            return ob_get_clean();
        } else {
            include $template_file;
        }
    }

    public static function get_theme_slug_for_templates() {
		return apply_filters( 'wpddb_theme_slug_for_templates', get_option( 'template' ) );
	}

	public static function is_blog_theme() {

		global $wp_version;

		if ( version_compare( $wp_version, '5.9', '>=' ) && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return true;
		}
		return false;
	}
    public static function doctorMetaScBuilder( $meta ) {
        
        $custom_thumb_size = [
            'width'  => ! empty( $meta['wpddb_doctor_img_width'][0] ) ? absint( $meta['wpddb_doctor_img_width'][0] ) : '570',
            'height' => ! empty( $meta['wpddb_doctor_img_height'][0] ) ? absint( $meta['wpddb_doctor_img_height'][0] ) : '400',
            'crop'   => ! empty( $meta['wpddb_doctor_img_hard_crop'][0] ) ? esc_html( $meta['wpddb_doctor_img_hard_crop'][0] ) : 'hard',
        ];
        $metas             = [
            'layout'            =>  ! empty( $meta['wpddb_doctor_layout'][0] ) ? esc_html( $meta['wpddb_doctor_layout'][0] ) : 'layout-1',
            'posts_per_page'    => ! empty( $meta['wpddb_doctor_post_limit'][0] ) ? absint( $meta['wpddb_doctor_post_limit'][0] ) : '12',
            'grid_columns'      => ! empty( $meta['wpddb_doctor_grid_columns'][0] ) ? absint( $meta['wpddb_doctor_grid_columns'][0] ) : '3',
            'custom_image_size' => $custom_thumb_size,
            'post_in'           =>  ! empty( $meta['wpddb_doctor_include'][0] ) ? maybe_unserialize ($meta['wpddb_doctor_include'][0]) : [],
            'post_not_in'       => ! empty( $meta['wpddb_doctor_exclude'][0] ) ? maybe_unserialize ($meta['wpddb_doctor_exclude'][0]) : [],
            'categories'        =>  ! empty( $meta['wpddb_doctor_categories'][0] ) ? maybe_unserialize ($meta['wpddb_doctor_categories'][0]) : [],
            'order_by'          =>  ! empty( $meta['wpddb_doctor_order_by'][0] ) ? $meta['wpddb_doctor_order_by'][0] : null,
            'order'             =>  ! empty( $meta['wpddb_doctor_order'][0] ) ? $meta['wpddb_doctor_order'][0] : null,
            'more_btn'          =>   ! empty( $meta['wpddb_doctor_more_btn'][0] ) ?  $meta['wpddb_doctor_more_btn'][0] : '',
            'more_btn_text'     =>  ! empty( $meta['wpddb_doctor_btn_text'][0] ) ?  $meta['wpddb_doctor_btn_text'][0] : __( 'More Doctors', 'doc-booker' ),
            'more_btn_url'      =>  ! empty( $meta['wpddb_doctor_btn_url'][0] ) ? esc_url( $meta['wpddb_doctor_btn_url'][0] ) : '#'
        ];

        return apply_filters( 'wpddb_doctor_meta_sc_builder', $metas, $meta );
    }
	public static function clinicMetaScBuilder( $meta ) {
		$custom_thumb_size = [
			'width'  => ! empty( $meta['wpddb_clinic_img_width'][0] ) ? absint( $meta['wpddb_clinic_img_width'][0] ) : '570',
			'height' => ! empty( $meta['wpddb_clinic_img_height'][0] ) ? absint( $meta['wpddb_clinic_img_height'][0] ) : '400',
			'crop'   => ! empty( $meta['wpddb_clinic_img_hard_crop'][0] ) ? esc_html( $meta['wpddb_clinic_img_hard_crop'][0] ) : 'hard',
		];
		$metas             = [
			'layout'            =>  ! empty( $meta['wpddb_clinic_layout'][0] ) ? esc_html( $meta['wpddb_clinic_layout'][0] ) : 'layout-1',
			'posts_per_page'    => ! empty( $meta['wpddb_clinic_post_limit'][0] ) ? absint( $meta['wpddb_clinic_post_limit'][0] ) : '12',
			'grid_columns'      => ! empty( $meta['wpddb_clinic_grid_columns'][0] ) ? absint( $meta['wpddb_clinic_grid_columns'][0] ) : '3',
			'custom_image_size' => $custom_thumb_size,
			'post_in'           =>  ! empty( $meta['wpddb_clinic_include'][0] ) ? maybe_unserialize ($meta['wpddb_clinic_include'][0]) : [],
			'post_not_in'       =>  ! empty( $meta['wpddb_clinic_exclude'][0] ) ? maybe_unserialize ($meta['wpddb_clinic_exclude'][0]) : [],
			'order_by'          =>  ! empty( $meta['wpddb_clinic_order_by'][0] ) ? $meta['wpddb_clinic_order_by'][0] : null,
			'order'             =>  ! empty( $meta['wpddb_clinic_order'][0] ) ? $meta['wpddb_clinic_order'][0] : null,
			'more_btn'          => ! empty( $meta['wpddb_clinic_more_btn'][0] ) ?  $meta['wpddb_clinic_more_btn'][0] : '',
			'more_btn_text'     => ! empty( $meta['wpddb_clinic_btn_text'][0] ) ?  $meta['wpddb_clinic_btn_text'][0] : __( 'More Clinics', 'doc-booker' ),
			'more_btn_url'      =>  ! empty( $meta['wpddb_clinic_btn_url'][0] ) ? esc_url( $meta['wpddb_clinic_btn_url'][0] ) : '#'

		];

		return apply_filters( 'wpddb_clinic_meta_sc_builder', $metas, $meta );
	}

    public static function get_more_btn_html( $url, $more_btn_text ){
        $html = null;
        $html .='<div class="wpddb-more-btn">';
        $html .= sprintf('<a href="%s" target="_blank">%s</a>',esc_url($url),esc_html($more_btn_text));
        $html .='</div>';
        return $html;
    }
    public static function wp_set_temp_query( $query ) {
        global $wp_query;
        global $post;
        $temp     = $wp_query;
        $wp_query = $query;

        return $temp;
    }
	public static function get_header($wp_version) {
		if ( version_compare( $wp_version, '5.9', '>=' ) && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) { ?>
			<!doctype html>
			<html <?php language_attributes(); ?>>
				<head>
					<meta charset="<?php bloginfo( 'charset' ); ?>">
					<?php wp_head(); ?>
				</head>
			<body <?php body_class(); ?>>
				<?php wp_body_open(); ?>
				<div class="wp-site-blocks">
				<?php
				$theme      = wp_get_theme();
				$theme_slug = $theme->get( 'TextDomain' );
				Helper::print_html(do_blocks( '<!-- wp:template-part {"slug":"header","theme":"' . esc_attr( $theme_slug ) . '","tagName":"header","className":"site-header"} /-->' ),true) ;
			} else {
				get_header();
			}
	}
	public static function get_footer($wp_version) {
		if ( version_compare( $wp_version, '5.9', '>=' ) && function_exists( 'wp_is_block_theme' ) && true === wp_is_block_theme() ) {
			$theme      = wp_get_theme();
			$theme_slug = $theme->get( 'TextDomain' );
			Helper::print_html(do_blocks('<!-- wp:template-part {"slug":"footer","theme":"' . esc_attr( $theme_slug ) . '","tagName":"footer","className":"site-footer"} /-->'),true) ;
			echo '</div>';
			wp_footer();
			echo '</body>';
			echo '</html>';
		} else {
			get_footer();
		}
	}
    public static function wp_reset_temp_query( $temp ) {
        global $wp_query;
        $wp_query = $temp;
        wp_reset_postdata();
    }
	public static function getFeatureImage( $post_id = null, $wpddbImgSize = 'medium', $customImgSize = [] ) {
		$imgHtml = $imgSrc = $attachment_id = null;
		$cSize   = false;

		if ( $wpddbImgSize == 'wpddb_custom' ) {
			$wpddbImgSize = 'full';
			$cSize     = true;
		}

		$aID        = get_post_thumbnail_id( $post_id );
		$post_title = get_the_title( $post_id );
		$img_alt    = trim( wp_strip_all_tags( get_post_meta( $aID, '_wp_attachment_image_alt', true ) ) );
		$alt_tag    = ! empty( $img_alt ) ? $img_alt : trim( wp_strip_all_tags( $post_title ) );

		$attr = [
			'class' => 'wpddb-feature-img ',
			'alt'   => $alt_tag,
		];

		$actual_dimension = wp_get_attachment_metadata( $aID, true );


		$actual_w = ! empty( $actual_dimension['width'] ) ? $actual_dimension['width'] : '';
		$actual_h = ! empty( $actual_dimension['height'] ) ? $actual_dimension['height'] : '';

		if ( $aID ) {
			$imgHtml       = wp_get_attachment_image( $aID, $wpddbImgSize, false, $attr );
			$attachment_id = $aID;
		}


		if ( $imgHtml && $cSize ) {
			preg_match( '@src="([^"]+)"@', $imgHtml, $match );

			$imgSrc = array_pop( $match );
			$w      = ! empty( $customImgSize['width'] ) ? absint( $customImgSize['width'] ) : null;
			$h      = ! empty( $customImgSize['height'] ) ? absint( $customImgSize['height'] ) : null;
			$c      = ! empty( $customImgSize['crop'] ) && $customImgSize['crop'] == 'soft' ? false : true;

			if ( $w && $h ) {
				if ( $w >= $actual_w || $h >= $actual_h ) {
					$w = 150;
					$h = 150;
					$c = true;
				}

				$image = self::CustomImageReSize( $imgSrc, $w, $h, $c, false );

				if ( ! empty( $image ) ) {

					list( $src, $width, $height ) = $image;

					$hwstring    = image_hwstring( $width, $height );
					$attachment  = get_post( $attachment_id );
					$attr        = apply_filters( 'wp_get_attachment_image_attributes', $attr, $attachment, $wpddbImgSize );
					$attr['src'] = $src;
					$attr        = array_map( 'esc_attr', $attr );
					$imgHtml     = rtrim( "<img $hwstring" );

					foreach ( $attr as $name => $value ) {
						$imgHtml .= " $name=" . '"' . $value . '"';
					}

					$imgHtml .= ' />';

				}
			}
		}

		return $imgHtml;
	}
	public static function CustomImageReSize( $url, $width = null, $height = null, $crop = null, $single = true, $upscale = false ) {
		$thumbResize = new ThumbnailSizeGenerator();

		return $thumbResize->process( $url, $width, $height, $crop, $single, $upscale );
	}
	public static function is_active_sidebar($sidebar) {
		if(is_active_sidebar($sidebar)){
			return true;
		}
		return false;

	}
	public static  function sidebar_class($archive_page=null) {
		$classes=[
			'wpddb-sidebar',
			$archive_page
		];
		$classes = apply_filters('wpddb_sidebar',$classes);
		if(!empty($classes)){
			echo 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
	}
	static function page_title($echo = true,$page=null) {
		$page_title='';
		if (is_search()) {
			/* translators: %s: search query */
			$page_title = sprintf(__('Search results: &ldquo;%s&rdquo;', 'doc-booker'), get_search_query());

			if (get_query_var('paged')) {
				/* translators: %s: page number */
				$page_title .= sprintf(__('&nbsp;&ndash; Page %s', 'doc-booker'), get_query_var('paged'));
			}
		} elseif (is_tax()) {

			$page_title = single_term_title('', false);

		} elseif('doctor'===$page) {
			$doctor_page_id = self::get_page_id('doctors');
			$page_title = get_the_title($doctor_page_id);
		}elseif ('clinic'===$page){
			$doctor_page_id = self::get_page_id('clinics');
			$page_title = get_the_title($doctor_page_id);
		}

		$page_title = apply_filters('wpddb_page_title', $page_title);

		if ($echo) {
			echo esc_html($page_title);
		} else {
			return $page_title;
		}
	}
	public static function get_page_id( $page ) {

		$page_id          = 0;
		$settings_page_id = WpddbOptions::get_option( $page, 'wpddb_page_settings');

		if ( $settings_page_id && get_post( $settings_page_id ) ) {
			$page_id = $settings_page_id;
		}

		return $page_id;

	}
	public static function get_page_ids(){
		$pages    = self::get_custom_page_list();
		$page_ids = [];
		foreach ( $pages as $page_key => $page_title ) {
			if ( $id = self::get_page_id( $page_key ) ) {
				$page_ids[ $page_key ] = $id;
			}
		}

		return $page_ids;
	}
	public static function get_custom_page_list() {
		$pages = array(
			'doctors'     => array(
				'title'   => esc_html__( 'Doctors', 'doc-booker' ),
				'content' => ''
			),
			'clinics'     => array(
				'title'   => esc_html__( 'Clinics', 'doc-booker' ),
				'content' => ''
			),
		);

		return apply_filters( 'wpddb_custom_pages_list', $pages );
	}
	public static function insert_custom_pages(){

		$page_settings    = self::get_page_ids();
		$page_definitions = self::get_custom_page_list();
		$pages = [];
		foreach ( $page_definitions as $slug => $page ) {

			$id = 0;
			if ( array_key_exists( $slug, $page_settings ) ) {
				$id = (int) $page_settings[ $slug ];
			}
			if ( ! $id ) {
				$id = wp_insert_post(
					[
						'post_title'     => $page['title'],
						'post_content'   => $page['content'],
						'post_status'    => 'publish',
						'post_author'    => 1,
						'post_type'      => 'page',
						'comment_status' => 'closed'
					]
				);
			}
			$pages[ $slug ] = $id;
		}

		return $pages;
	}
    public static function doctor_page_layout() {
        $layout = [
            'layout-1' => [
                'title'      => 'Layout 1',
                'img_source' => 'layout-1'
            ],
            'layout-2' => [
                'title'      => 'Layout 2',
                'img_source' => 'layout-2'
            ],

        ];

        return apply_filters( 'wpddb_doctor_page_layout', $layout );
    }
    public static function clinic_page_layout() {
        $layout = [
            'layout-1' => [
                'title'      => 'Layout 1',
                'img_source' => 'layout-1'
            ],
            'layout-2' => [
                'title'      => 'Layout 2',
                'img_source' => 'layout-2'
            ],
        ];

        return apply_filters( 'wpddb_clinic_page_layout', $layout );
    }
    public static function doctor_shortcode_layout() {
        $layout = [
            'layout-1' => [
                'title'      => 'Layout 1',
                'img_source' => 'layout-1'
            ],
            'layout-2' => [
                'title'      => 'Layout 2',
                'img_source' => 'layout-2'
            ],
        ];

        return apply_filters( 'wpddb_doctor_shortcode_layout', $layout );
    }
    public static function clinic_shortcode_layout() {
        $layout = [
            'layout-1' => [
                'title'      => 'Layout 1',
                'img_source' => 'layout-1'
            ],
            'layout-2' => [
                'title'      => 'Layout 2',
                'img_source' => 'layout-2'
            ],
        ];

        return apply_filters( 'wpddb_clinic_shortcode_layout', $layout );
    }
	public static function get_primary_color() {
		return apply_filters( 'wpddb_primary_color', WpddbOptions::get_option( 'wpddb_primary_color', 'wpddb_style_settings' ) ?: '#5d3dfd' );
	}

	public static function get_secondary_color() {
		return apply_filters( 'wpddb_secondary_color', WpddbOptions::get_option( 'wpddb_secondary_color', 'wpddb_style_settings' ) ?: '#ebf3fc' );
	}
	public static function get_border_color() {
		return apply_filters( 'wpddb_border_color', WpddbOptions::get_option( 'wpddb_border_color', 'wpddb_style_settings' ) ?: '#dedede' );
	}

	/**
	 * English weekday name ("Monday"…"Sunday") for a given date.
	 *
	 * Doctor schedules store weekday names in English, so every date→weekday
	 * comparison must be locale-independent. wp_date()/date_i18n() translate the
	 * name to the active site language (e.g. "lunes" on es_MX), which then never
	 * matches the stored "Monday" and silently breaks availability + booking on
	 * non-English sites. WordPress pins PHP's default timezone to UTC and a Y-m-d
	 * date parses to UTC midnight, so gmdate() returns the correct English name.
	 *
	 * @param string|int $date A date string (e.g. Y-m-d) or a Unix timestamp.
	 * @return string English weekday name, or '' when the date cannot be parsed.
	 */
	public static function get_weekday_name( $date ) {
		$timestamp = is_numeric( $date ) ? (int) $date : strtotime( (string) $date );

		return $timestamp ? gmdate( 'l', $timestamp ) : '';
	}
    public static function doctor_shortcode_css_generator( $ID, $scMeta ) {
        $css  = null;
        $css .= "<style type='text/css' media='all'>";

        $show_title = apply_filters( 'wpddb_doctor_shortcode_show_title', true, $scMeta );
        $show_department = apply_filters( 'wpddb_doctor_shortcode_show_department', true, $scMeta );
        $show_content = apply_filters( 'wpddb_doctor_shortcode_show_content', true, $scMeta );
        $doctor_bg = ( ! empty( $scMeta['wpddb_doctor_bg_color'][0] ) ? sanitize_hex_color($scMeta['wpddb_doctor_bg_color'][0])  : null );
        $doctor_title_color = ( ! empty( $scMeta['wpddb_doctor_title_color'][0] ) ? sanitize_hex_color($scMeta['wpddb_doctor_title_color'][0])  : null );
        $doctor_department_color = ( ! empty( $scMeta['wpddb_doctor_department_color'][0] ) ? sanitize_hex_color($scMeta['wpddb_doctor_department_color'][0])  : null );
        $doctor_content_color = ( ! empty( $scMeta['wpddb_doctor_content_color'][0] ) ? sanitize_hex_color($scMeta['wpddb_doctor_content_color'][0])  : null );
        if ($doctor_bg){
            $css .= "#{$ID}  .doctor-item{";
            $css .= 'background-color:' . esc_html($doctor_bg) . ';';
            $css .= '}';
        }

        if ($show_title) {
            $css .= "#{$ID} .doctor-item .wpddb-doctor-title a {";
            if ( $doctor_title_color ) {
                $css .= 'color:' . esc_html($doctor_title_color) . ';';
            }
            $css .= '}';
        }
        if ($show_department) {
            $css .= "#{$ID} .doctor-item .doctor-department a {";
            if ( $doctor_department_color ) {
                $css .= 'color:' . esc_html($doctor_department_color) . ';';
            }
            $css .= '}';
        }
        if ($show_content) {
            $css .= "#{$ID} .doctor-item p{";
            if ( $doctor_content_color ) {
                $css .= 'color:' . esc_html($doctor_content_color) . ';';
            }
            $css .= '}';
        }

        $css .= apply_filters( 'wpddb_doctor_shortcode_custom_css', '', $ID, $scMeta );

        $css .= '</style>';

        return $css;
    }
    public static function clinic_shortcode_css_generator( $ID, $scMeta ) {
        $css  = null;
        $css .= "<style type='text/css' media='all'>";

        $show_title = apply_filters( 'wpddb_clinic_shortcode_show_title', true, $scMeta );
        $show_content = apply_filters( 'wpddb_clinic_shortcode_show_content', true, $scMeta );
        $clinic_bg = ( ! empty( $scMeta['wpddb_clinic_bg_color'][0] ) ? $scMeta['wpddb_clinic_bg_color'][0]  : null );
        $clinic_title_color = ( ! empty( $scMeta['wpddb_clinic_title_color'][0] ) ? sanitize_hex_color($scMeta['wpddb_clinic_title_color'][0])  : null );
        $clinic_content_color = ( ! empty( $scMeta['wpddb_clinic_content_color'][0] ) ? sanitize_hex_color($scMeta['wpddb_clinic_content_color'][0])  : null );
        if ($clinic_bg){
            $css .= "#{$ID}  .clinic-item{";
            $css .= 'background-color:' . esc_html($clinic_bg) . ';';
            $css .= '}';
        }

        if ($show_title) {
            $css .= "#{$ID} .clinic-item .wpddb-clinic-title a {";
            if ( $clinic_title_color ) {
                $css .= 'color:' . esc_html($clinic_title_color) . ';';
            }
            $css .= '}';
        }

        if ($show_content) {
            $css .= "#{$ID} .clinic-item p{";
            if ( $clinic_content_color ) {
                $css .= 'color:' . esc_html($clinic_content_color) . ';';
            }
            $css .= '}';
        }

        $css .= apply_filters( 'wpddb_clinic_shortcode_custom_css', '', $ID, $scMeta );

        $css .= '</style>';

        return $css;
    }
    public static function send_booking_notifications($booking_id, $doctor_id,$day,$time,$clinic_id, $patient,$booking_type='success', $booking_date = null) {

        $doctor = get_post($doctor_id);
        $doctor_email = get_post_meta($doctor_id, 'wpddb_doctor_email', true);
        $doctor_sending_mail= true;

        // Format day display: show date if available, otherwise just weekday
        if ( ! empty( $booking_date ) && strtotime( $booking_date ) ) {
            $day = date_i18n( 'l, F j, Y', strtotime( $booking_date ) );
        }

        // Get clinic name
        $clinic_name = '';
        $schedule = get_post_meta($doctor_id, 'wpddb_doctor_schedule', true);
        foreach ($schedule as $day_item) {
            if ( strcasecmp( $day_item['day'], $day ) === 0 || ( ! empty( $booking_date ) && strcasecmp( $day_item['day'], self::get_weekday_name( $booking_date ) ) === 0 ) ) {
                foreach ($day_item['clinics'] as $clinic) {
                    if ($clinic['id'] == $clinic_id) {
                        $clinic_name = $clinic['name'];
                        break;
                    }
                }
                break;
            }
        }

	    // Define translatable messages
	    $booking_messages = [
		    'success' => [
			    'patient_subject' => __('Your appointment booking confirmation', 'doc-booker'),
			    'patient_message' => __("Dear %s,\n\nThank you for booking an appointment with Dr. %s.\n\nAppointment Details:\nBooking ID: %s\nDay: %s\nTime: %s\nClinic: %s\n\nIf you need to cancel or reschedule, please contact us at least 24 hours before your appointment.\n\nBest regards,\n%s", 'doc-booker'),
			    'doctor_subject' => __('New appointment booking', 'doc-booker'),
			    'doctor_message' => __("Dear Dr. %s,\n\nA new appointment has been booked with you.\n\nAppointment Details:\nPatient: %s\nPhone: %s\n%sDay: %s\nTime: %s\nClinic: %s\n%s", 'doc-booker'),
			    'admin_subject' => __('New doctor appointment booking', 'doc-booker'),
			    'admin_message' => __("A new appointment has been booked.\n\nBooking ID: %s\n\nDoctor: %s\nPatient: %s\nPhone: %s\n%sDay: %s\nTime: %s\nClinic: %s\n%s", 'doc-booker'),
		    ],
		    'cancel' => [
			    'patient_subject' => __('Your appointment booking has been canceled', 'doc-booker'),
			    'patient_message' => __("Dear %s,\n\nSorry, your appointment booking with Dr. %s has been canceled.\n\n%s", 'doc-booker'),
			    'doctor_subject' => __('Canceling booking', 'doc-booker'),
			    'doctor_message' => __("Dear Dr. %s,\n\nAn appointment has been canceled with you.\n\nAppointment Details:\nPatient: %s\nPhone: %s\n%sDay: %s\nTime: %s\nClinic: %s\n%s", 'doc-booker'),
			    'admin_subject' => __('A doctor appointment booking cancel', 'doc-booker'),
			    'admin_message' => __("An appointment has been canceled.\n\nBooking ID: %s\n\nDoctor: %s\nPatient: %s\nPhone: %s\n%sDay: %s\nTime: %s\nClinic: %s\n%s", 'doc-booker'),
		    ],
	    ];

	    $message_type = $booking_type === 'success' ? 'success' : 'cancel';

        // Send email to patient
	    if (!empty($patient['email'])) {
		    $patient_message = sprintf(
			    $booking_messages[$message_type]['patient_message'],
			    $patient['name'],
			    $doctor->post_title,
			    $booking_id ?? '',
			    $day ?? '',
			    $time ?? '',
			    $clinic_name ?? '',
			    get_bloginfo('name')
		    );

		    wp_mail($patient['email'], $booking_messages[$message_type]['patient_subject'], $patient_message);
	    }

        // Send email to doctor
	    if (!empty($doctor_email) && $doctor_sending_mail) {
		    $doctor_message = sprintf(
			    $booking_messages[$message_type]['doctor_message'],
			    $doctor->post_title,
			    $patient['name'],
			    $patient['phone'],
			    !empty($patient['email']) ? "Email: {$patient['email']}\n" : '',
			    $day ?? '',
			    $time ?? '',
			    $clinic_name ?? '',
			    !empty($patient['note']) ? "Notes: {$patient['note']}\n" : ''
		    );

		    wp_mail($doctor_email, $booking_messages[$message_type]['doctor_subject'], $doctor_message);
	    }

        // Send email to admin
	    $admin_email = get_option('admin_email');
	    $admin_message = sprintf(
		    $booking_messages[$message_type]['admin_message'],
		    $booking_id ?? '',
		    $doctor->post_title,
		    $patient['name'],
		    $patient['phone'],
		    !empty($patient['email']) ? "Email: {$patient['email']}\n" : '',
		    $day ?? '',
		    $time ?? '',
		    $clinic_name ?? '',
		    !empty($patient['note']) ? "Notes: {$patient['note']}\n" : ''
	    );

	    wp_mail($admin_email, $booking_messages[$message_type]['admin_subject'], $admin_message);

    }
	public static function generate_booking_id(  ) {
		$four_digit_random = wp_rand( 1000, 9999 );
		return '#WPDB-' . $four_digit_random;
	}
    public static function generate_patient_visit_id(  ) {
        $four_digit_random = wp_rand( 1000, 9999 );
        return '#WPDV-' . $four_digit_random;
    }

    public static function week_days_order() {
        return[
            __( "Monday", "doc-booker" )    => 1,
            __( "Tuesday", "doc-booker" )   => 2,
            __( "Wednesday", "doc-booker" ) => 3,
            __( "Thursday", "doc-booker" )  => 4,
            __( "Friday", "doc-booker" )    => 5,
            __( "Saturday", "doc-booker" )  => 6,
            __( "Sunday", "doc-booker" )    => 7
        ];
    }
	public static function convert_time_format($time, $to_format = '24') {

		$formats_to_try = [
			'H:i',      // 24-hour format without AM/PM
			'h:i A',    // 12-hour format with AM/PM
			'h:i a',    // 12-hour format with lowercase am/pm
			'H:i:s',    // 24-hour format with seconds
			'h:i:s A'   // 12-hour format with seconds and AM/PM
		];

		foreach ($formats_to_try as $format) {
			$datetime = DateTime::createFromFormat($format, $time);
			if ($datetime) {
				break;
			}
		}

		// If no valid format found, return original time
		if (!$datetime) {
			return $time;
		}

		// Convert to desired format
		if ($to_format === '12') {
			// Convert to 12-hour format
			return $datetime->format('h:i A');
		} else {
			// Convert to 24-hour format
			return $datetime->format('H:i');
		}
	}

    public static function update_doctor_booking_meta_data_time($doctor_id,$booking_day,$booking_time,$clinic_id) {
        $schedules = [];

        if (!isset($schedules[$doctor_id])) {
            $schedules[$doctor_id] = get_post_meta($doctor_id, 'wpddb_doctor_schedule', true);
        }

        $schedule = &$schedules[$doctor_id];

        if (!$schedule) {
            return;
        }

        $updated = false;
        foreach ($schedule as &$day_item) {
            if ($day_item['day'] === $booking_day) {
                foreach ($day_item['clinics'] as &$clinic) {
                    if ($clinic['id'] == $clinic_id) {
                        foreach ($clinic['timings'] as &$timing) {
                            if (Helper::convert_time_format($timing['time']) === $booking_time) {
                                $timing['is_bookable'] = true;
                                $updated = true;
                            }
                        }
                    }
                }
            }
        }
        if ($updated) {
            update_post_meta($doctor_id, 'wpddb_doctor_schedule', $schedule);
        }
        return $updated;
    }



}