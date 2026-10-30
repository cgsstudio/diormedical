<?php
namespace WpDreamers\WPDDB\Controllers\Admin\Setup;

use WpDreamers\WPDDB\Controllers\Admin\RegisterPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Creates what the setup wizard asked for, one task per request so the screen
 * can show real progress and retry a single failed step.
 *
 * Every post and term it makes is remembered in the setup state, so running the
 * wizard again updates those items instead of adding copies. Sample items also
 * carry a marker (post meta / term meta), which is what "Remove Sample Data"
 * looks for.
 */
class SetupInstaller {

	const OPTION      = 'wpddb_setup';
	const SAMPLE_META = '_wpddb_sample';
	const TASKS       = [ 'check', 'clinics', 'departments', 'doctors', 'page', 'finish' ];
	const DAY_NAMES   = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
	const MAX_DEPTS   = 15;

	/**
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::OPTION, [] );
		return is_array( $state ) ? $state : [];
	}

	/**
	 * Merge changes into the stored state.
	 *
	 * @param array $changes Keys to set.
	 */
	public static function save_state( array $changes ) {
		update_option( self::OPTION, array_merge( self::state(), $changes ), false );
	}

	/**
	 * Runs one task with the owner's answers.
	 *
	 * @param string $task    One of TASKS.
	 * @param array  $answers Raw answers from the wizard.
	 *
	 * @return array|\WP_Error
	 */
	public static function run( $task, array $answers ) {
		$answers = self::sanitize_answers( $answers );
		if ( is_wp_error( $answers ) ) {
			return $answers;
		}
		switch ( $task ) {
			case 'check':
				// Runs first: every answer is checked before anything is created.
				return [ 'ok' => true ];
			case 'clinics':
				return self::create_clinics( $answers );
			case 'departments':
				return self::create_departments( $answers );
			case 'doctors':
				return self::create_doctors( $answers );
			case 'page':
				return self::create_booking_page( $answers );
			case 'finish':
				return self::finish( $answers );
		}
		return new \WP_Error( 'wpddb_setup_task', __( 'Unknown setup step.', 'doc-booker' ) );
	}

	/**
	 * Whitelists and trims everything the browser sent, and checks the owner's
	 * own answers. Anything that is not plain text (a list where text belongs,
	 * and so on) is treated as empty rather than turned into "Array".
	 *
	 * @param array $raw Answers.
	 *
	 * @return array|\WP_Error Errors carry the wizard step to go back to as data['step'].
	 */
	public static function sanitize_answers( array $raw ) {
		$group = function ( $value ) {
			return is_array( $value ) ? $value : [];
		};
		$text  = function ( $value, $max ) {
			return is_scalar( $value ) ? mb_substr( sanitize_text_field( (string) $value ), 0, $max ) : '';
		};

		$practices = SetupSamples::practices();
		$practice  = isset( $raw['practice'] ) && is_string( $raw['practice'] ) ? sanitize_key( $raw['practice'] ) : 'general';
		if ( ! isset( $practices[ $practice ] ) ) {
			$practice = 'general';
		}
		$mode   = ( $raw['mode'] ?? '' ) === 'sample' ? 'sample' : 'own';
		$clinic = $group( $raw['clinic'] ?? null );
		$doctor = $group( $raw['doctor'] ?? null );
		$page   = $group( $raw['page'] ?? null );

		$depts = [];
		foreach ( $group( $raw['depts'] ?? null ) as $dept ) {
			$dept = $text( $dept, 60 );
			if ( '' !== $dept && ! in_array( $dept, $depts, true ) ) {
				$depts[] = $dept;
			}
		}
		$depts = array_slice( $depts, 0, self::MAX_DEPTS );
		if ( ! $depts ) {
			// The booking form lists doctors by department, so there is always one.
			$depts = SetupSamples::get( $practice )['depts'];
		}

		$days = [];
		foreach ( $group( $doctor['days'] ?? null ) as $day ) {
			if ( is_numeric( $day ) && (int) $day >= 0 && (int) $day <= 6 ) {
				$days[] = (int) $day;
			}
		}
		$days = array_values( array_unique( $days ) );
		sort( $days );
		$from = is_numeric( $doctor['from'] ?? null ) ? max( 0, min( 1439, (int) $doctor['from'] ) ) : 540;
		$to   = is_numeric( $doctor['to'] ?? null ) ? max( 1, min( 1440, (int) $doctor['to'] ) ) : 780;
		$len  = is_numeric( $doctor['len'] ?? null ) ? (int) $doctor['len'] : 30;
		if ( ! in_array( $len, [ 15, 20, 30, 60 ], true ) ) {
			$len = 30;
		}
		$dept = $text( $doctor['dept'] ?? '', 60 );
		if ( ! in_array( $dept, $depts, true ) ) {
			$dept = $depts[0];
		}

		$answers = [
			'practice' => $practice,
			'mode'     => $mode,
			'clinic'   => [
				'name'    => $text( $clinic['name'] ?? '', 100 ),
				'phone'   => $text( $clinic['phone'] ?? '', 40 ),
				'address' => $text( $clinic['address'] ?? '', 200 ),
			],
			'depts'    => $depts,
			'doctor'   => [
				// "Skip This Step" on the doctor screen: set up everything else, add no doctor.
				'skip'       => ! empty( $doctor['skip'] ),
				'name'       => $text( $doctor['name'] ?? '', 100 ),
				'dept'       => $dept,
				'speciality' => $text( $doctor['speciality'] ?? '', 100 ),
				'days'       => $days,
				'from'       => $from,
				'to'         => $to,
				'len'        => $len,
			],
			'page'     => [
				'title' => $text( $page['title'] ?? '', 100 ),
				'menu'  => ! empty( $page['menu'] ),
			],
		];

		if ( '' === $answers['clinic']['name'] ) {
			$answers['clinic']['name'] = SetupSamples::get( $practice )['clinic'];
		}
		if ( '' === $answers['page']['title'] ) {
			$answers['page']['title'] = __( 'Book an Appointment', 'doc-booker' );
		}

		if ( 'own' === $mode && ! $answers['doctor']['skip'] ) {
			$problem = '';
			if ( '' === $answers['doctor']['name'] ) {
				$problem = __( "Please enter the doctor's name.", 'doc-booker' );
			} elseif ( ! $days ) {
				$problem = __( 'Pick at least one working day.', 'doc-booker' );
			} elseif ( $to <= $from ) {
				$problem = __( 'The end time must be later than the start time.', 'doc-booker' );
			} elseif ( $from + $len > $to ) {
				$problem = __( 'The working hours are shorter than one appointment. Make them longer or the appointments shorter.', 'doc-booker' );
			}
			if ( $problem ) {
				return new \WP_Error( 'wpddb_setup_doctor', $problem, [ 'step' => 'doctor' ] );
			}
		}

		return $answers;
	}

	/* ------------------------------------------------------------------
	 * Tasks
	 * ---------------------------------------------------------------- */

	private static function create_clinics( array $a ) {
		$created = self::created();
		$sample  = 'sample' === $a['mode'];
		$rows    = $sample
			? array_map( function ( $c ) {
				return [ 'name' => $c[0], 'address' => $c[1], 'phone' => $c[2] ];
			}, SetupSamples::get( $a['practice'] )['clinics'] )
			: [ $a['clinic'] ];

		$key = $sample ? 'sample_clinics' : 'clinics';
		$ids = [];
		foreach ( $rows as $i => $row ) {
			$id = self::upsert_post( wpddb()->post_type_clinic, $created[ $key ][ $i ] ?? 0, $row['name'], '', $sample );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			update_post_meta( $id, 'wpddb_clinic_address', $row['address'] );
			update_post_meta( $id, 'wpddb_clinic_hotline', $row['phone'] );
			$ids[] = $id;
		}
		self::remember( $key, $ids );

		return [ 'ids' => $ids ];
	}

	private static function create_departments( array $a ) {
		$sample = 'sample' === $a['mode'];
		$names  = $sample ? SetupSamples::get( $a['practice'] )['depts'] : $a['depts'];
		$key    = $sample ? 'sample_terms' : 'terms';
		$made   = self::created()[ $key ] ?? [];
		$ids    = [];

		foreach ( $names as $name ) {
			$taxonomy = wpddb()->doctor_category;
			$existing = term_exists( $name, $taxonomy );
			if ( $existing ) {
				$ids[ $name ] = (int) $existing['term_id'];
				continue;
			}
			$term = wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $term ) ) {
				return $term;
			}
			$ids[ $name ] = (int) $term['term_id'];
			$made[]       = (int) $term['term_id'];
			if ( $sample ) {
				update_term_meta( (int) $term['term_id'], self::SAMPLE_META, 1 );
			}
		}
		self::remember( $key, array_values( array_unique( $made ) ) );

		return [ 'ids' => array_values( $ids ) ];
	}

	private static function create_doctors( array $a ) {
		$created = self::created();
		$tax     = wpddb()->doctor_category;

		if ( 'sample' === $a['mode'] ) {
			$practice = SetupSamples::get( $a['practice'] );
			$clinics  = $created['sample_clinics'] ?? [];
			if ( count( $clinics ) < 2 ) {
				return new \WP_Error( 'wpddb_setup_order', __( 'The clinics have to be added first.', 'doc-booker' ) );
			}
			$ids = [];
			foreach ( $practice['doctors'] as $i => $doc ) {
				$id = self::upsert_post( wpddb()->post_type_doctor, $created['sample_doctors'][ $i ] ?? 0, $doc[0], '', true );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$hours = SetupSamples::SCHEDULES[ $i % count( SetupSamples::SCHEDULES ) ];
				update_post_meta( $id, 'wpddb_doctor_speciality', $doc[2] );
				self::write_schedule( $id, (int) $clinics[ $i % 2 ], $hours['days'], $hours['from'], $hours['to'], SetupSamples::SLOT_MINUTES );
				$dept = $practice['depts'][ $doc[1] ] ?? '';
				if ( $dept ) {
					wp_set_object_terms( $id, [ $dept ], $tax );
				}
				$ids[] = $id;
			}
			self::remember( 'sample_doctors', $ids );
			return [ 'ids' => $ids ];
		}

		$clinic = (int) ( $created['clinics'][0] ?? 0 );
		if ( ! $clinic || ! get_post( $clinic ) ) {
			return new \WP_Error( 'wpddb_setup_order', __( 'The clinic has to be added first.', 'doc-booker' ) );
		}
		$d = $a['doctor'];
		if ( $d['skip'] ) {
			return [ 'skipped' => true ];
		}
		$id = self::upsert_post( wpddb()->post_type_doctor, $created['doctors'][0] ?? 0, $d['name'], '', false );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, 'wpddb_doctor_speciality', $d['speciality'] );
		self::write_schedule( $id, $clinic, $d['days'], $d['from'], $d['to'], $d['len'] );
		// Replaces any department an earlier run gave this doctor.
		wp_set_object_terms( $id, [ $d['dept'] ], $tax );
		self::remember( 'doctors', [ $id ] );

		return [ 'ids' => [ $id ] ];
	}

	/**
	 * Saves the weekly schedule in the same shape the doctor editor writes:
	 * English day names, "9:00 AM" times, every slot bookable.
	 */
	private static function write_schedule( $doctor_id, $clinic_id, array $days, $from, $to, $len ) {
		$timings = [];
		for ( $m = $from; $m + $len <= $to; $m += $len ) {
			$timings[] = [ 'time' => gmdate( 'g:i A', $m * 60 ), 'is_bookable' => true ];
		}
		$clinic_name = get_the_title( $clinic_id );
		$schedule    = [];
		foreach ( $days as $day ) {
			$schedule[] = [
				'available' => true,
				'day'       => self::DAY_NAMES[ $day ],
				'clinics'   => [ [ 'id' => (string) $clinic_id, 'name' => $clinic_name, 'timings' => $timings ] ],
			];
		}
		update_post_meta( $doctor_id, 'wpddb_doctor_schedule', $schedule );
		update_post_meta( $doctor_id, 'wpddb_doctor_available_clinic', $schedule ? [ (string) $clinic_id ] : [] );
		update_post_meta( $doctor_id, 'wpddb_clinics_info', [
			(string) $clinic_id => [
				'phone_number'  => (string) get_post_meta( $clinic_id, 'wpddb_clinic_hotline', true ),
				'is_holiday'    => false,
				'holiday_dates' => [ 'start_date' => '', 'end_date' => '' ],
			],
		] );
	}

	private static function create_booking_page( array $a ) {
		$created = self::created();
		$page_id = (int) ( $created['page'] ?? 0 );
		$block   = "<!-- wp:shortcode -->\n[wpddb_doctor_booking_form]\n<!-- /wp:shortcode -->";
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( $page && 'page' === $page->post_type && 'trash' !== $page->post_status ) {
			$update = [ 'ID' => $page_id, 'post_title' => $a['page']['title'], 'post_status' => 'publish' ];
			// Keep whatever the owner added; only put the form back if it was removed.
			if ( false === strpos( $page->post_content, '[wpddb_doctor_booking_form' ) ) {
				$update['post_content'] = trim( $page->post_content . "\n\n" . $block );
			}
			$result = wp_update_post( $update, true );
		} else {
			$result = wp_insert_post( [
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => $a['page']['title'],
				'post_content'   => $block,
				'comment_status' => 'closed',
			], true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::remember( 'page', (int) $result );

		$in_menu = $a['page']['menu'] ? self::add_to_menu( (int) $result ) : false;

		return [ 'id' => (int) $result, 'url' => get_permalink( $result ), 'in_menu' => $in_menu ];
	}

	private static function finish( array $a ) {
		self::save_state( [
			'completed'    => true,
			'completed_at' => time(),
			'practice'     => $a['practice'],
			'mode'         => $a['mode'],
		] );
		// New doctor, clinic and department links: rebuilt on the next page load.
		RegisterPostType::flush_rewrite_rules();

		$page = (int) ( self::created()['page'] ?? 0 );
		return [
			'booking_url' => $page ? get_permalink( $page ) : home_url( '/' ),
			'doctors_url' => admin_url( 'edit.php?post_type=' . wpddb()->post_type_doctor ),
		];
	}

	/* ------------------------------------------------------------------
	 * Sample data removal
	 * ---------------------------------------------------------------- */

	/**
	 * Deletes every sample doctor and clinic, and the sample departments no
	 * other doctor uses. The owner's own items are never touched.
	 *
	 * @return int Number of posts removed.
	 */
	public static function remove_samples() {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
			 WHERE p.post_type IN ( %s, %s )",
			self::SAMPLE_META,
			wpddb()->post_type_doctor,
			wpddb()->post_type_clinic
		) );
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}

		$terms = get_terms( [
			'taxonomy'   => wpddb()->doctor_category,
			'hide_empty' => false,
			'meta_key'   => self::SAMPLE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'fields'     => 'ids',
		] );
		foreach ( is_array( $terms ) ? $terms : [] as $term_id ) {
			$term = get_term( $term_id );
			if ( $term && ! is_wp_error( $term ) && 0 === (int) $term->count ) {
				wp_delete_term( $term_id, wpddb()->doctor_category );
			}
		}

		$created = self::created();
		unset( $created['sample_clinics'], $created['sample_doctors'], $created['sample_terms'] );
		self::save_state( [ 'created' => $created ] );

		return count( $ids );
	}

	/**
	 * @return bool Whether any sample doctor or clinic is still on the site.
	 */
	public static function has_samples() {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			 WHERE m.meta_key = %s AND p.post_type IN ( %s, %s ) LIMIT 1",
			self::SAMPLE_META,
			wpddb()->post_type_doctor,
			wpddb()->post_type_clinic
		) );
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	private static function created() {
		$created = self::state()['created'] ?? [];
		return is_array( $created ) ? $created : [];
	}

	private static function remember( $key, $value ) {
		$created         = self::created();
		$created[ $key ] = $value;
		self::save_state( [ 'created' => $created ] );
	}

	/**
	 * Updates a post the wizard made earlier, or creates it.
	 *
	 * @return int|\WP_Error
	 */
	private static function upsert_post( $type, $id, $title, $content, $sample ) {
		$id   = (int) $id;
		$post = $id ? get_post( $id ) : null;
		if ( $post && $type === $post->post_type && 'trash' !== $post->post_status ) {
			$result = wp_update_post( [ 'ID' => $id, 'post_title' => $title, 'post_status' => 'publish' ], true );
		} else {
			$result = wp_insert_post( [
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			], true );
		}
		if ( ! is_wp_error( $result ) && $sample ) {
			update_post_meta( (int) $result, self::SAMPLE_META, 1 );
		}
		return is_wp_error( $result ) ? $result : (int) $result;
	}

	/**
	 * Adds the page to the site's main menu. Only uses a menu that already
	 * exists: never creates one or changes which menu a location shows.
	 *
	 * @param int $page_id Page.
	 *
	 * @return bool Whether it is in the menu now.
	 */
	private static function add_to_menu( $page_id ) {
		$menu_id   = 0;
		$locations = get_nav_menu_locations();
		foreach ( [ 'primary', 'main', 'menu-1', 'header' ] as $slug ) {
			if ( ! empty( $locations[ $slug ] ) ) {
				$menu_id = (int) $locations[ $slug ];
				break;
			}
		}
		if ( ! $menu_id && $locations ) {
			$menu_id = (int) reset( $locations );
		}
		if ( ! $menu_id ) {
			$menus = wp_get_nav_menus();
			if ( 1 === count( $menus ) ) {
				$menu_id = (int) $menus[0]->term_id;
			}
		}
		if ( ! $menu_id || ! wp_get_nav_menu_object( $menu_id ) ) {
			return false;
		}
		foreach ( wp_get_nav_menu_items( $menu_id ) ?: [] as $item ) {
			if ( 'post_type' === $item->type && (int) $item->object_id === $page_id ) {
				return true;
			}
		}
		$result = wp_update_nav_menu_item( $menu_id, 0, [
			'menu-item-title'     => get_the_title( $page_id ),
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page_id,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		] );
		if ( ! is_wp_error( $result ) ) {
			self::remember( 'menu_item', (int) $result );
			return true;
		}
		return false;
	}
}
