<?php

namespace WpDreamers\WPDDB\Controllers\Hooks;


use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Clinic;
use WpDreamers\WPDDB\Controllers\Model\Doctor;
use WpDreamers\WPDDB\Controllers\WpddbOptions;
use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class ActionHooks {
	use SingletonTrait;

	public function __construct() {
		add_action( 'wpddb_doctor_schedule', [ __CLASS__, 'render_doctor_schedule' ], 10 );
		add_action( 'wpddb_doctor_info_meta', [ __CLASS__, 'render_doctor_info_meta' ], 10 );
		add_action( 'wpddb_clinic_after_title', [ __CLASS__, 'render_clinic_meta' ], 10 );
		add_action( 'wpddb_clinic_after_content', [ __CLASS__, 'render_clinic_map' ], 10 );

		add_action( 'wpddb_doctor_details_booking', [ __CLASS__, 'render_doctor_booking_form' ], 10 );

        add_action( 'wpddb_doctor_shortcode_after_title', [ __CLASS__, 'doctor_shortcode_render_after_title' ],10 );
        add_action( 'wpddb_doctor_shortcode_before_title',[$this,'render_doctor_shortcode_before_title'],10);

        add_action( 'wpddb_clinic_shortcode_content',[$this,'render_clinic_shortcode_content'],10);
        add_action( 'wpddb_clinic_shortcode_map_btn',[$this,'render_pro_clinic_shortcode_map_btn'],10);
        add_action( 'wpddb_clinic_thumbnail',[__CLASS__,'render_clinic_thumbnail'],10,2);
	}


	public static function render_doctor_schedule( $doctor_id ) {
		if ( ! $doctor_id ) {
			return;
		}
		$doctor_schedule         = get_post_meta( $doctor_id, 'wpddb_doctor_schedule', true );
		$clinics_info            = get_post_meta( $doctor_id, 'wpddb_clinics_info', true );
		$showing_bookable_text   = WpddbOptions::get_option( 'showing_bookable_text', 'wpddb_doctor_settings' ) ?: 'on';
		$showing_bookable_text   = $showing_bookable_text === 'on';
		$call_for_booking        = WpddbOptions::get_option( 'call_for_booking', 'wpddb_doctor_settings' ) ?: 'on';
		$call_for_booking        = $call_for_booking === 'on';
		$display_doctor_schedule = WpddbOptions::get_option( 'display_doctor_schedule', 'wpddb_doctor_settings' ) ?: 'on';
		$display_doctor_schedule = $display_doctor_schedule === 'on';
		if ( empty( $doctor_schedule ) ) {
			echo "<p>" . esc_html_e( 'No schedule available', 'doc-booker' ) . "</p>";

			return;
		}

		// Group schedules by clinic first
		$clinic_schedules = [];
		if ( $display_doctor_schedule ) {
			foreach ( $doctor_schedule as $day_data ) {
				if ( ! isset( $day_data['clinics'] ) ) {
					continue;
				}
				foreach ( $day_data['clinics'] as $clinic ) {
					$clinic_id    = $clinic['id'];
					$clinic_name  = $clinic['name'] ?? "Unknown Clinic";
					$clinic_phone = $clinics_info[ $clinic_id ]['phone_number'] ?? "N/A";
					foreach ( $clinic['timings'] as $timing ) {
						$clinic_schedules[ $clinic_id ]['name']                           = $clinic_name;
						$clinic_schedules[ $clinic_id ]['phone']                          = $clinic_phone;
						$clinic_schedules[ $clinic_id ]['schedule'][ $day_data['day'] ][] = [
							'time'        => $timing['time'],
							'is_bookable' => $timing['is_bookable'],
						];
					}
				}
			}

			$week_days_order = Helper::week_days_order();

			foreach ( $clinic_schedules as &$clinic ) {
				uksort( $clinic['schedule'], function ( $a, $b ) use ( $week_days_order ) {
					return $week_days_order[ $a ] - $week_days_order[ $b ];
				} );

				foreach ( $clinic['schedule'] as &$slots ) {
					usort( $slots, function ( $a, $b ) {
						return strtotime( $a['time'] ) - strtotime( $b['time'] );
					} );
				}
				unset( $slots );
			}
			unset( $clinic );
			echo "<h3 class='wpddb-doctor-inner-title'>" . esc_html__( "Doctor Schedule", "doc-booker" ) . "</h3>";
			echo '<div class="wpddb-clinic-schedule-wrapper">';
			foreach ( $clinic_schedules as $clinic_id => $clinic ) {
				echo "<div class='wpddb-clinic-schedule'>";
				echo '<div class="wpddb-clinic-header">';
				printf( '<h3 class="wpddb-clinic-name"><a href="%s">%s</a></h3>', esc_url( get_the_permalink( $clinic_id ) ), esc_html( $clinic['name'] ) );
				echo '</div>';
				echo '<div class="content-wrap">';
				foreach ( $clinic['schedule'] as $day => $slots ) {
					$day_times = array_map( function ( $slot ) use ( $showing_bookable_text ) {
						$status_class = $slot['is_bookable'] ? 'has-bookable' : 'no-available';
						if ( $showing_bookable_text ) {
							return sprintf(
								'<span class="each-time"><span class="time">%s</span> <span class="status ' . esc_attr( $status_class ) . '">%s</span><span class="separator"></span></span>',
								esc_html( $slot['time'] ),
								esc_html( $slot['is_bookable'] ? "( Available )" : "( Booked )" )
							);
						} else {
							return sprintf(
								'<span class="each-time"><span class="time">%s</span><span class="separator"></span></span>',
								esc_html( $slot['time'] ) );

						}


					}, $slots );

					printf(
						'<div class="wpddb-time-wrapper"><strong>%s:</strong> %s</div>',
						esc_html( $day ),
						implode( ' ', $day_times )
					);

				}
				echo "</div>";
				if ( $clinic['phone'] && $call_for_booking ) {
					echo '<div class="clinic-footer">';
					echo '<span class="wpddb-booking-title">';
					esc_html_e( 'Call For Booking:', 'doc-booker' );
					echo '</span>';
					echo '<span class="wpddb-booking-number"><a href="tel:' . esc_attr( $clinic['phone'] ) . '">';
					Helper::print_html( $clinic['phone'] );
					echo '</a></span>';
					echo '</div>';
				}
				echo "</div>";
			}
			echo '</div>';
		}
		if ( $clinics_info ) {
			$has_holiday = ! empty( array_filter( $clinics_info, function ( $clinic ) {
				return ! empty( $clinic['is_holiday'] ) && ! empty( $clinic['holiday_dates'] );
			} ) );
		}

		if ( ! empty( $clinics_info && $has_holiday ) ) {
			echo "<h3 class='wpddb-doctor-inner-title'>" . esc_html__( "Clinic Holiday Schedule", "doc-booker" ) . "</h3>";
			foreach ( $clinics_info as $clinic_id => $clinic ) {
				$clinic_name = get_the_title( $clinic_id );
				if ( ! empty( $clinic['is_holiday'] ) && ! empty( $clinic['holiday_dates'] ) ) {
					printf(
						'<div class="holiday-clinic"><span class="clinic-name">%s</span> %s <strong>%s</strong> %s <strong>%s</strong></div>',
						esc_html( $clinic_name ),
						__( "is on Holiday from", 'doc-booker' ),
						date( "F j, Y", strtotime( $clinic['holiday_dates']['start_date'] ) ),
						__( "to", "doc-booker" ),
						date( "F j, Y", strtotime( $clinic['holiday_dates']['end_date'] ) )
					);

				}
			}
		}
	}

	public static function render_doctor_info_meta( $post_id ) {
		$doctor_degree      = get_post_meta( $post_id, 'wpddb_doctor_degree', true );
		$doctor_designation = get_post_meta( $post_id, 'wpddb_doctor_designation', true );
		$doctor_speciality  = get_post_meta( $post_id, 'wpddb_doctor_speciality', true );
		$doctor_workplace   = get_post_meta( $post_id, 'wpddb_doctor_workplace', true );
		if ( $doctor_degree || $doctor_designation || $doctor_speciality || $doctor_workplace ) {
			Helper::print_html( '<div class="doctor-info-wrapper" >' );
			if ( ! empty( $doctor_degree ) ) {
				Helper::print_html( '<div class="wpddb-doctor-degree">' );
				Helper::print_html( $doctor_degree );
				Helper::print_html( '</div>' );
			}
			if ( ! empty( $doctor_speciality ) ) {
				Helper::print_html( '<div class="wpddb-doctor-speciality">' );
				Helper::print_html( $doctor_speciality );
				Helper::print_html( '</div>' );
			}
			if ( ! empty( $doctor_designation ) ) {
				Helper::print_html( '<div class="wpddb-doctor-designation">' );
				Helper::print_html( $doctor_designation );
				Helper::print_html( '</div>' );
			}
			if ( ! empty( $doctor_workplace ) ) {
				Helper::print_html( '<div class="wpddb-doctor-workplace">' );
				Helper::print_html( $doctor_workplace );
				Helper::print_html( '</div>' );
			}
			Helper::print_html( '</div>' );
		}
	}

	public static function render_clinic_meta( $post_id ) {
		$clinic_hotline = get_post_meta( $post_id, 'wpddb_clinic_hotline', true );
		$clinic_email   = get_post_meta( $post_id, 'wpddb_clinic_email', true );
		if ( $clinic_email || $clinic_hotline ) {
			Helper::print_html( '<div class="wpddb-clinic-meta">' );
			if ( $clinic_hotline ) {
				?>
                <div class="clinic-hotline">
                    <div class="label"><?php esc_html_e( 'Our Hotline:', 'doc-booker' ); ?></div>
                    <div class="hotline-number"><?php Helper::print_html( $clinic_hotline ); ?></div>
                </div>
			<?php }
			if ( $clinic_email ) {
				?>
                <div class="clinic-email">
                    <div class="label"><?php esc_html_e( 'Our Email:', 'doc-booker' ); ?></div>
                    <div class="email"><a
                                href="mailto:<?php echo esc_attr( $clinic_email ); ?>"><?php Helper::print_html( $clinic_email ); ?></a>
                    </div>
                </div>
			<?php }
			Helper::print_html( '</div>' );
		}
	}

	public static function render_doctor_booking_form( $doctor_id ) {
		$detail_booking_form = true;
		if ( $detail_booking_form ) {
			?>
            <div id="wpddb-doctor-details-booking" data-doctor-id="<?php echo esc_attr( $doctor_id ); ?>"></div>
		<?php }
	}

	public static function render_clinic_map( $post_id ) {
		$address         = get_post_meta( $post_id, 'wpddb_clinic_address', true );
		$latitude        = get_post_meta( $post_id, 'wpddb_clinic_latitude', true );
		$longitude       = get_post_meta( $post_id, 'wpddb_clinic_longitude', true );
		$marker_icon_url = wpddb()->get_assets_uri( 'public/img/marker-icon.png' );
		$shadow_icon_url = wpddb()->get_assets_uri( 'public/img/marker-shadow.png' );
		if ( empty( $address ) ) {
			return;
		}
		?>
        <div class="clinic-address">
            <div class="label"><?php esc_html_e( 'Our Address:', 'doc-booker' ); ?></div>
            <div class="address"><?php Helper::print_html( $address ); ?></div>
        </div>
        <div id="wpddb-clinic-location-map" style="height: 500px;"></div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const map = L.map('wpddb-clinic-location-map').setView([<?php echo esc_js( $latitude ); ?>, <?php echo esc_js( $longitude ); ?>], 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                }).addTo(map);

                // Define Custom Marker Icon
                const customIcon = L.icon({
                    iconUrl: '<?php echo esc_url( $marker_icon_url ); ?>',
                    shadowUrl: '<?php echo esc_url( $shadow_icon_url ); ?>',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                });

                // Add Custom Marker
                L.marker([<?php echo esc_js( $latitude ); ?>, <?php echo esc_js( $longitude ); ?>], {icon: customIcon}).addTo(map);
            });
        </script>

	<?php }

    public static function doctor_shortcode_render_after_title( $args ) {
        $content = get_the_content();

        $show_designation = true;
        $show_content = true;

        if ( function_exists( 'wpddbp' ) ) {
            $show_designation = (
                isset( $args['show_designation'] ) && $args['show_designation'] == 'on'
            );
            $show_content = (
                isset( $args['show_content'] ) && $args['show_content'] == 'on'
            );
        }

        if ( $show_designation ) {
            Doctor::doctor_designation( get_the_ID() );
        }

        do_action( 'wpddb_doctor_shortcode_render_after_designation',$args );

        if ( empty( $content ) ) {
            return;
        }

        if ( function_exists( 'wpddbp' ) && $show_content ) {
            the_content();
            return;
        }
        if ( !function_exists( 'wpddbp' ) && $show_content){
            $content = Doctor::get_the_content( get_the_ID() );

            $trimmed = wp_trim_words( $content, 20, '..' );
            echo '<p>' . wp_kses_post( $trimmed ) . '</p>';
        }

    }

    public static function render_doctor_shortcode_before_title($args) {
        $show_department = true;

        if ( function_exists( 'wpddbp' ) ) {
            $show_department = (
                isset( $args['show_department'] ) && $args['show_department'] == 'on'
            );
        }
        if ( $show_department ) {
            Doctor::get_category_html_format(get_the_ID());
        }
    }

    public static function render_clinic_shortcode_content($args) {
        $content = get_the_content();
        $show_content = true;
        if ( empty( $content ) ) {
            return;
        }
        if ( function_exists( 'wpddbp' ) ) {
            $show_content = (
                isset( $args['show_content'] ) && $args['show_content'] == 'on'
            );
        }
        if ( function_exists( 'wpddbp' ) && $show_content ) {
            the_content();
            return;
        }
        if ( !function_exists( 'wpddbp' ) && $show_content){
            $content = Clinic::get_the_content( get_the_ID() );
            $trimmed = wp_trim_words( $content, 20, '..' );
            echo '<p>' . wp_kses_post( $trimmed ) . '</p>';
        }

    }
    public static function render_pro_clinic_shortcode_map_btn($args) {
        $show_map_btn = true;
        if ( function_exists( 'wpddbp' ) ) {
            $show_map_btn = (
                isset( $args['show_map_btn'] ) && $args['show_map_btn'] == 'on'
            );
            $map_btn_text           = WpddbOptions::get_option( 'map_btn_text','wpddb_clinic_settings') ?:'';
            if ($show_map_btn && $map_btn_text) {
                Clinic::clinic_details_button(get_the_ID(),$map_btn_text);
            }
        }
        if (!function_exists( 'wpddbp' ) && $show_map_btn){
            Clinic::clinic_details_button(get_the_ID());
        }

    }

    public static function render_clinic_thumbnail($post_id, $thumb_size) {
        if ( has_post_thumbnail( $post_id ) ) {
            echo get_the_post_thumbnail( $post_id, $thumb_size );
        } else {
            echo '<img class="wp-post-image wpddb-media no-image" src="' . esc_url( wpddb()->get_assets_uri('public/img/no-image-1210x600.jpg') ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '">';
        }
    }



}