<?php

namespace WpDreamers\WPDDB\Controllers\Helper;

use DateTime;
use DateTimeZone;
use WpDreamers\WPDDB\Controllers\Model\Booking;
use WpDreamers\WPDDB\Controllers\Model\ThumbnailSizeGenerator;
use WpDreamers\WPDDB\Controllers\WpddbOptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}
class Helper{
    /**
     * True on the plugin's own list screens in wp-admin: doctors, clinics,
     * departments and the two shortcode lists. Optionally the Dashboard too.
     * The plugin's ?page= screens are left out: they clear all notices.
     *
     * @param bool $with_dashboard Also count the Dashboard.
     *
     * @return bool
     */
    public static function is_plugin_list_screen( $with_dashboard = false ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return false;
        }
        if ( $with_dashboard && 'dashboard' === $screen->id ) {
            return true;
        }
        if ( 'edit-tags' === $screen->base ) {
            return wpddb()->doctor_category === $screen->taxonomy;
        }
        return 'edit' === $screen->base && in_array( $screen->post_type, [
            wpddb()->post_type_doctor,
            wpddb()->post_type_clinic,
            wpddb()->post_type_doctor_shortcode,
            wpddb()->post_type_clinic_shortcode,
        ], true );
    }

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
            // Post-level HTML plus the form fields the plugin's own markup uses.
            echo wp_kses( stripslashes_deep( $html ), 'wpddb' );
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
        // A template name is a path inside templates/, never a way out of it.
        $template_name = (string) $template_name;
        if ( ! preg_match( '#^[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*$#', $template_name ) ) {
            _doing_it_wrong( __FUNCTION__, esc_html__( 'Invalid template name.', 'doc-booker' ), '2.1.2' );
            return $return ? '' : null;
        }

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

            if ( ! file_exists( $template_file ) && wpddb()->has_pro() ) {
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
	/**
	 * Gradient behind a doctor's initials when there is no photo. Rotates by ID
	 * so neighbouring cards differ.
	 *
	 * @param int $id Post ID.
	 *
	 * @return string
	 */
	public static function avatar_gradient( $id ) {
		$gradients = [
			'linear-gradient(135deg,#635bff 0%,#9333ea 100%)',
			'linear-gradient(135deg,#06b6d4 0%,#3b82f6 100%)',
			'linear-gradient(135deg,#10b981 0%,#059669 100%)',
			'linear-gradient(135deg,#f59e0b 0%,#ef4444 100%)',
			'linear-gradient(135deg,#ec4899 0%,#db2777 100%)',
		];
		return $gradients[ absint( $id ) % count( $gradients ) ];
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
			// The settings save "on"/"off"; older code passed "hard"/"soft".
			$c      = ! ( ! empty( $customImgSize['crop'] ) && in_array( $customImgSize['crop'], [ 'soft', 'off' ], true ) );

			if ( $w && $h && $actual_w && $actual_h ) {
				// A photo smaller than the asked size used to become a 150x150 thumbnail.
				// Shrink the asked size to fit inside the photo instead, keeping its shape,
				// so the card frame and the image still match.
				if ( $w > $actual_w || $h > $actual_h ) {
					$ratio = min( $actual_w / $w, $actual_h / $h );
					$w     = max( 1, (int) floor( $w * $ratio ) );
					$h     = max( 1, (int) floor( $h * $ratio ) );
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
    /**
     * Email the patient, the doctor and the admin about a booking.
     *
     * @param string      $booking_type 'success', 'cancel' or 'reminder' (patient only).
     * @param array       $extra {
     *     @type string $payment_by   'clinic' for pay at the clinic, a gateway when paid online.
     *     @type float  $fee          The doctor's fee.
     *     @type float  $amount_paid  What was paid online.
     *     @type string $manage_url   The patient's cancel link.
     *     @type bool   $can_cancel   False when it is too late to cancel online (reminder).
     *     @type bool   $to_patient   Reminder: send the patient's (default true).
     *     @type bool   $to_doctor    Reminder: send the doctor's (default true).
     *     @type bool   $to_admin     Send the admin's copy (default true).
     *     @type string $cancelled_by 'patient' when the patient cancelled from the link.
     * }
     */
    public static function send_booking_notifications($booking_id, $doctor_id,$day,$time,$clinic_id, $patient,$booking_type='success', $booking_date = null, $extra = []) {

        $doctor = get_post($doctor_id);
        $doctor_email = get_post_meta($doctor_id, 'wpddb_doctor_email', true);
        $doctor_sending_mail= true;

        // The date and time as the site shows them (Settings > General).
        $weekday_en = $day;
        if ( ! empty( $booking_date ) && strtotime( $booking_date ) ) {
            $day = self::site_date_label( $booking_date );
        } else {
            $day = self::weekday_label( $day );
        }
        $time = self::site_time_label( $time );

        // Clinic name from the doctor's schedule, falling back to the clinic post.
        $clinic_name = '';
        $schedule    = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );
        foreach ( is_array( $schedule ) ? $schedule : [] as $day_item ) {
            if ( strcasecmp( $day_item['day'], $weekday_en ) === 0 || ( ! empty( $booking_date ) && strcasecmp( $day_item['day'], self::get_weekday_name( $booking_date ) ) === 0 ) ) {
                foreach ( (array) ( $day_item['clinics'] ?? [] ) as $clinic ) {
                    if ( $clinic['id'] == $clinic_id ) {
                        $clinic_name = $clinic['name'];
                        break;
                    }
                }
                break;
            }
        }
        if ( '' === $clinic_name && $clinic_id ) {
            $clinic_name = get_the_title( $clinic_id );
        }

        // Doctor titles often already start with "Dr."; don't print "Dr. Dr.".
        $doctor_name = $doctor ? $doctor->post_title : '';
        if ( $doctor_name && ! preg_match( '/^dr\.?\s/i', $doctor_name ) ) {
            /* translators: %s: doctor name */
            $doctor_name = sprintf( __( 'Dr. %s', 'doc-booker' ), $doctor_name );
        }

        /* translators: %s: patient email address */
        $email_line = ! empty( $patient['email'] ) ? sprintf( __( 'Email: %s', 'doc-booker' ), $patient['email'] ) . "\n" : '';
        /* translators: %s: patient notes */
        $notes_line = ! empty( $patient['note'] ) ? sprintf( __( 'Notes: %s', 'doc-booker' ), $patient['note'] ) . "\n" : '';

        // The fee: to pay at the clinic, or what was paid online.
        $fee         = (float) ( $extra['fee'] ?? 0 );
        $amount_paid = (float) ( $extra['amount_paid'] ?? 0 );
        $payment_line = '';
        if ( 'clinic' === ( $extra['payment_by'] ?? '' ) && $fee > 0 ) {
            /* translators: %s: the fee with its currency, e.g. USD 45 */
            $payment_line = "\n" . sprintf( __( 'Fee: %s, to pay at the clinic', 'doc-booker' ), Booking::money( $fee ) );
        } elseif ( $amount_paid > 0 ) {
            /* translators: %s: the amount with its currency, e.g. USD 45 */
            $payment_line = "\n" . sprintf( __( 'Paid online: %s', 'doc-booker' ), Booking::money( $amount_paid ) );
        }

        // How the patient can cancel: their link, or the clinic.
        $manage_url = (string) ( $extra['manage_url'] ?? '' );
        if ( $manage_url && Booking::patient_cancel_enabled() && false === ( $extra['can_cancel'] ?? true ) ) {
            // A reminder arrives about a day before; the link may already be closed.
            $link_line = __( 'It is too late to cancel online now. If you cannot come, please call the clinic as soon as you can.', 'doc-booker' );
        } elseif ( $manage_url && Booking::patient_cancel_enabled() ) {
            $cutoff    = Booking::cancel_cutoff_hours();
            $link_line = $cutoff > 0
                /* translators: 1: number of hours, 2: link to the patient's booking page */
                ? sprintf( _n( 'Need to cancel? You can do it up to %1$d hour before your appointment here: %2$s', 'Need to cancel? You can do it up to %1$d hours before your appointment here: %2$s', $cutoff, 'doc-booker' ), $cutoff, $manage_url )
                /* translators: %s: link to the patient's booking page */
                : sprintf( __( 'Need to cancel? You can do it here: %s', 'doc-booker' ), $manage_url );
        } else {
            $link_line = __( 'If you need to cancel or reschedule, please contact us at least 24 hours before your appointment.', 'doc-booker' );
        }

        $calendar_url  = (string) ( $extra['calendar_url'] ?? '' );
        /* translators: %s: link that adds the appointment to a calendar */
        $calendar_line = $calendar_url ? "\n\n" . sprintf( __( 'Add it to your calendar: %s', 'doc-booker' ), $calendar_url ) : '';

        $clinic_address = $clinic_id ? (string) get_post_meta( $clinic_id, 'wpddb_clinic_address', true ) : '';
        /* translators: %s: clinic address */
        $address_line = '' !== trim( $clinic_address ) ? "\n" . sprintf( __( 'Address: %s', 'doc-booker' ), $clinic_address ) : '';

        // Every message takes the same numbered values:
        // 1 patient, 2 doctor, 3 booking ID, 4 day/date, 5 time, 6 clinic,
        // 7 site name, 8 patient phone, 9 email line, 10 notes line,
        // 11 fee line, 12 cancel line, 13 clinic address line, 14 calendar line.
        $booking_messages = [
            'success' => [
                'patient_subject' => __( 'Your appointment booking confirmation', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 3: booking ID, 4: day or date, 5: time, 6: clinic, 7: site name, 11: fee line, 12: how to cancel, 14: add-to-calendar line */
                'patient_message' => __( "Dear %1\$s,\n\nThank you for booking an appointment with %2\$s.\n\nAppointment Details:\nBooking ID: %3\$s\nDay: %4\$s\nTime: %5\$s\nClinic: %6\$s%11\$s%14\$s\n\n%12\$s\n\nBest regards,\n%7\$s", 'doc-booker' ),
                'doctor_subject'  => __( 'New appointment booking', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 4: day or date, 5: time, 6: clinic, 8: phone, 9: email line, 10: notes line, 11: fee line */
                'doctor_message'  => __( "Dear %2\$s,\n\nA new appointment has been booked with you.\n\nAppointment Details:\nPatient: %1\$s\nPhone: %8\$s\n%9\$sDay: %4\$s\nTime: %5\$s\nClinic: %6\$s%11\$s\n%10\$s", 'doc-booker' ),
                'admin_subject'   => __( 'New doctor appointment booking', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 3: booking ID, 4: day or date, 5: time, 6: clinic, 8: phone, 9: email line, 10: notes line, 11: fee line */
                'admin_message'   => __( "A new appointment has been booked.\n\nBooking ID: %3\$s\n\nDoctor: %2\$s\nPatient: %1\$s\nPhone: %8\$s\n%9\$sDay: %4\$s\nTime: %5\$s\nClinic: %6\$s%11\$s\n%10\$s", 'doc-booker' ),
            ],
            'reminder' => [
                /* translators: 4: day or date, 5: time */
                'patient_subject' => __( 'Reminder: your appointment on %4$s at %5$s', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 3: booking ID, 4: day or date, 5: time, 6: clinic, 7: site name, 11: fee line, 12: how to cancel, 13: clinic address line */
                'patient_message' => __( "Dear %1\$s,\n\nThis is a reminder of your appointment with %2\$s.\n\nAppointment Details:\nBooking ID: %3\$s\nDay: %4\$s\nTime: %5\$s\nClinic: %6\$s%13\$s%11\$s\n\n%12\$s\n\nBest regards,\n%7\$s", 'doc-booker' ),
                /* translators: 1: patient, 4: day or date, 5: time */
                'doctor_subject'  => __( 'Reminder: %1$s on %4$s at %5$s', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 3: booking ID, 4: day or date, 5: time, 6: clinic, 8: phone, 9: email line, 10: notes line, 11: fee line */
                'doctor_message'  => __( "Dear %2\$s,\n\nA reminder of your appointment with %1\$s.\n\nAppointment Details:\nBooking ID: %3\$s\nPatient: %1\$s\nPhone: %8\$s\n%9\$sDay: %4\$s\nTime: %5\$s\nClinic: %6\$s%11\$s\n%10\$s", 'doc-booker' ),
            ],
            'cancel_patient' => [
                'patient_subject' => __( 'Your appointment has been cancelled', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 3: booking ID, 4: day or date, 5: time, 6: clinic, 7: site name */
                'patient_message' => __( "Dear %1\$s,\n\nYour appointment with %2\$s has been cancelled, as you asked.\n\nAppointment Details:\nBooking ID: %3\$s\nDay: %4\$s\nTime: %5\$s\nClinic: %6\$s\n\nBest regards,\n%7\$s", 'doc-booker' ),
                'doctor_subject'  => __( 'Appointment cancelled by the patient', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 4: day or date, 5: time, 6: clinic, 8: phone, 9: email line */
                'doctor_message'  => __( "Dear %2\$s,\n\nA patient cancelled their appointment with you. The time is free again.\n\nAppointment Details:\nPatient: %1\$s\nPhone: %8\$s\n%9\$sDay: %4\$s\nTime: %5\$s\nClinic: %6\$s\n", 'doc-booker' ),
                'admin_subject'   => __( 'A patient cancelled their appointment', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 3: booking ID, 4: day or date, 5: time, 6: clinic, 8: phone, 9: email line */
                'admin_message'   => __( "A patient cancelled their appointment from the link in their email. The time is free again.\n\nBooking ID: %3\$s\n\nDoctor: %2\$s\nPatient: %1\$s\nPhone: %8\$s\n%9\$sDay: %4\$s\nTime: %5\$s\nClinic: %6\$s\n", 'doc-booker' ),
            ],
            'cancel'  => [
                'patient_subject' => __( 'Your appointment has been cancelled', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 3: booking ID, 4: day or date, 5: time, 6: clinic, 7: site name */
                'patient_message' => __( "Dear %1\$s,\n\nSorry, your appointment with %2\$s has been cancelled.\n\nAppointment Details:\nBooking ID: %3\$s\nDay: %4\$s\nTime: %5\$s\nClinic: %6\$s\n\nBest regards,\n%7\$s", 'doc-booker' ),
                'doctor_subject'  => __( 'Appointment cancelled', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 4: day or date, 5: time, 6: clinic, 8: phone, 9: email line, 10: notes line */
                'doctor_message'  => __( "Dear %2\$s,\n\nAn appointment with you has been cancelled.\n\nAppointment Details:\nPatient: %1\$s\nPhone: %8\$s\n%9\$sDay: %4\$s\nTime: %5\$s\nClinic: %6\$s\n%10\$s", 'doc-booker' ),
                'admin_subject'   => __( 'A doctor appointment was cancelled', 'doc-booker' ),
                /* translators: 1: patient, 2: doctor, 3: booking ID, 4: day or date, 5: time, 6: clinic, 8: phone, 9: email line, 10: notes line */
                'admin_message'   => __( "An appointment has been cancelled.\n\nBooking ID: %3\$s\n\nDoctor: %2\$s\nPatient: %1\$s\nPhone: %8\$s\n%9\$sDay: %4\$s\nTime: %5\$s\nClinic: %6\$s\n%10\$s", 'doc-booker' ),
            ],
        ];
        if ( 'cancel' === $booking_type && 'patient' === ( $extra['cancelled_by'] ?? '' ) ) {
            $booking_type = 'cancel_patient';
        }
        $messages = $booking_messages[ $booking_type ] ?? $booking_messages['cancel'];
        $values   = [
            (string) ( $patient['name'] ?? '' ),
            $doctor_name,
            (string) $booking_id,
            (string) $day,
            (string) $time,
            (string) $clinic_name,
            get_bloginfo( 'name' ),
            (string) ( $patient['phone'] ?? '' ),
            $email_line,
            $notes_line,
            $payment_line,
            $link_line,
            $address_line,
            $calendar_line,
        ];

        if ( ! empty( $patient['email'] ) && ( $extra['to_patient'] ?? true ) ) {
            wp_mail( $patient['email'], vsprintf( $messages['patient_subject'], $values ), vsprintf( $messages['patient_message'], $values ) );
        }
        if ( ! empty( $doctor_email ) && $doctor_sending_mail && ( $extra['to_doctor'] ?? true ) ) {
            wp_mail( $doctor_email, vsprintf( $messages['doctor_subject'], $values ), vsprintf( $messages['doctor_message'], $values ) );
        }
        // Reminders have no admin copy; nor do bookings staff made themselves.
        if ( ! empty( $messages['admin_message'] ) && ( $extra['to_admin'] ?? true ) ) {
            wp_mail( get_option( 'admin_email' ), $messages['admin_subject'], vsprintf( $messages['admin_message'], $values ) );
        }
    }
	/**
	 * A booking id no other booking has. Four digits used to give only 9,000
	 * possibilities, so ids collided after a few hundred bookings and the
	 * insert failed on the unique key.
	 */
	/**
	 * A patient code no other patient has, in the same "P" + yymmdd + 5-digit
	 * form the patients migration and Pro's reception use.
	 */
	public static function generate_patient_code() {
		global $wpdb;
		$table = $wpdb->prefix . 'wpddb_patients';
		for ( $i = 0; $i < 5; $i++ ) {
			$code = 'P' . gmdate( 'ymd' ) . str_pad( (string) wp_rand( 0, 99999 ), 5, '0', STR_PAD_LEFT );
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE patient_code = %s", $code ) ) ) {
				return $code;
			}
		}
		return 'P' . gmdate( 'ymd' ) . substr( (string) microtime( true ) * 10000, -5 );
	}

	/**
	 * The last bookable date (Y-m-d) for a window of $days days starting today,
	 * counted on the site's calendar. strtotime() of the site's date gave UTC
	 * midnight, which wp_date() could move back a day on sites west of UTC.
	 */
	public static function booking_window_end( $days ) {
		$today = new \DateTimeImmutable( 'now', wp_timezone() );
		return $today->modify( '+' . max( 0, (int) $days - 1 ) . ' days' )->format( 'Y-m-d' );
	}

	public static function generate_booking_id(  ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpddb_bookings';
		for ( $i = 0; $i < 5; $i++ ) {
			$booking_id = '#WPDB-' . wp_rand( 10000000, 99999999 );
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE booking_id = %s", $booking_id ) ) ) {
				return $booking_id;
			}
		}
		return '#WPDB-' . substr( (string) time(), -8 ) . wp_rand( 0, 9 );
	}
    public static function generate_patient_visit_id(  ) {
        $four_digit_random = wp_rand( 1000, 9999 );
        return '#WPDV-' . $four_digit_random;
    }

    /**
     * Sort order of the schedule's day names. Schedules store English names,
     * whatever the site's language, so the keys are English too.
     */
    public static function week_days_order() {
        return [ 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7 ];
    }

    /**
     * A stored time ("9:00 AM" or "09:00") as the site shows times
     * (Settings > General > Time Format).
     *
     * @param string $time
     * @return string
     */
    public static function site_time_label( $time ) {
        $at = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', '2000-01-03 ' . self::convert_time_format( (string) $time ), wp_timezone() );

        return $at ? wp_date( get_option( 'time_format' ) ?: 'g:i a', $at->getTimestamp() ) : (string) $time;
    }

    /**
     * A Y-m-d date as the site shows dates (Date Format), with the weekday in
     * front unless the format already has one. Month and day names follow the
     * site's language.
     *
     * @param string $ymd
     * @param bool   $weekday
     * @return string
     */
    public static function site_date_label( $ymd, $weekday = true ) {
        $at = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $ymd, wp_timezone() );
        if ( ! $at ) {
            return (string) $ymd;
        }
        $format = get_option( 'date_format' ) ?: 'F j, Y';
        if ( $weekday && ! preg_match( '/(?<!\\\\)[lD]/', $format ) ) {
            /* translators: 1: weekday name, 2: date in the site's date format */
            $format = sprintf( _x( '%1$s, %2$s', 'weekday, date', 'doc-booker' ), 'l', $format );
        }

        // Midday, so a daylight-saving change can't move it to another day.
        return wp_date( $format, $at->getTimestamp() + 12 * HOUR_IN_SECONDS );
    }

    /**
     * An English schedule day name ("Monday") in the site's language.
     *
     * @param string $english
     * @return string
     */
    public static function weekday_label( $english ) {
        global $wp_locale;
        $index = array_search( ucfirst( strtolower( (string) $english ) ), [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ], true );

        return false !== $index && $wp_locale ? $wp_locale->get_weekday( $index ) : (string) $english;
    }

    /**
     * What the booking scripts need to show dates and times like the site:
     * its today and current time (not the visitor's clock), and translated
     * month and day names.
     *
     * @return array
     */
    public static function site_clock() {
        global $wp_locale;
        $months = [];
        $days   = [];
        for ( $m = 1; $m <= 12; $m++ ) {
            $months[] = $wp_locale ? $wp_locale->get_month( $m ) : gmdate( 'F', gmmktime( 0, 0, 0, $m, 1 ) );
        }
        for ( $d = 0; $d < 7; $d++ ) {
            $days[] = [
                'full'  => $wp_locale ? $wp_locale->get_weekday( $d ) : '',
                'short' => $wp_locale ? $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $d ) ) : '',
            ];
        }

        return [
            'now'        => current_time( 'Y-m-d H:i' ),
            'today'      => current_time( 'Y-m-d' ),
            'months'     => $months,
            'weekdays'   => $days,
            'weekStarts' => (int) get_option( 'start_of_week', 1 ),
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




	/**
	 * The colour darkened just enough to read as text on the plugin's light
	 * backgrounds (4.5:1 on #f4f7fb). Already-dark colours come back unchanged.
	 *
	 * @param string $color #rgb or #rrggbb.
	 * @return string #rrggbb, or the input when it isn't a hex colour.
	 */
	public static function readable_text_color( $color ) {
		$c = strtolower( trim( (string) $color ) );
		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $c, $m ) ) {
			$rgb = [ hexdec( $m[1] . $m[1] ), hexdec( $m[2] . $m[2] ), hexdec( $m[3] . $m[3] ) ];
		} elseif ( preg_match( '/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/', $c, $m ) ) {
			$rgb = [ hexdec( $m[1] ), hexdec( $m[2] ), hexdec( $m[3] ) ];
		} else {
			return $color;
		}
		$lum = static function ( $c ) {
			$f = static function ( $v ) {
				$v /= 255;
				return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
			};
			return 0.2126 * $f( $c[0] ) + 0.7152 * $f( $c[1] ) + 0.0722 * $f( $c[2] );
		};
		for ( $i = 0; $i < 15 && $lum( $rgb ) > 0.16; $i++ ) {
			$rgb = array_map( static function ( $v ) {
				return $v * 0.9;
			}, $rgb );
		}
		return sprintf( '#%02x%02x%02x', ...array_map( 'intval', array_map( 'round', $rgb ) ) );
	}
}