<?php
namespace WpDreamers\WPDDB\Controllers\Admin\Setup;

use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * The first-run setup wizard: a full-screen page that gets a new owner from
 * "just activated" to a working booking page in a couple of minutes.
 *
 * It opens once, right after the first activation on a site. It can always be
 * opened again from the setup checklist on the Doctors list.
 */
class SetupWizard {
	use SingletonTrait;

	const PAGE     = 'wpddb-setup';
	const REDIRECT = 'wpddb_setup_redirect';
	const NONCE    = 'wpddb_setup';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
		add_action( 'admin_init', [ $this, 'activation_redirect' ], 1 );
		add_action( 'admin_init', [ $this, 'maybe_render' ], 2 );
		add_action( 'wp_ajax_wpddb_setup_task', [ $this, 'ajax_task' ] );
		add_action( 'wp_ajax_wpddb_setup_skip', [ $this, 'ajax_skip' ] );
	}

	/**
	 * Called from activation: offer the wizard only on a site that never had
	 * DocBooker before. Updates and re-activations are left alone.
	 *
	 * @param bool $fresh Whether this is the first activation.
	 */
	public static function offer( $fresh ) {
		if ( ! $fresh ) {
			return;
		}
		SetupInstaller::save_state( [ 'offered' => true, 'offered_version' => WPDDB_VERSION ] );
		set_transient( self::REDIRECT, 1, 60 );
	}

	/**
	 * WordPress refuses admin.php?page= addresses it doesn't know, before
	 * admin_init runs, so the page is registered without a menu entry: a child
	 * of options.php, which is not itself in the menu.
	 */
	public function register_page() {
		add_submenu_page( 'options.php', __( 'Set Up DocBooker', 'doc-booker' ), __( 'Set Up DocBooker', 'doc-booker' ), 'manage_options', self::PAGE, '__return_null' );
	}

	public static function url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * @return bool Whether the owner finished or skipped the wizard.
	 */
	public static function answered() {
		$state = SetupInstaller::state();
		return ! empty( $state['completed'] ) || ! empty( $state['skipped'] );
	}

	public function activation_redirect() {
		if ( ! get_transient( self::REDIRECT ) ) {
			return;
		}
		delete_transient( self::REDIRECT );
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| ! current_user_can( 'manage_options' ) || self::answered() ) {
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * The wizard is its own page, outside the wp-admin frame, so it is drawn and
	 * ended here, before WordPress builds the admin screen.
	 */
	public function maybe_render() {
		if ( empty( $_GET['page'] ) || self::PAGE !== $_GET['page'] || wp_doing_ajax() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to set up DocBooker.', 'doc-booker' ), 403 );
		}
		SetupInstaller::save_state( [ 'started' => true ] );
		$config = $this->config();
		include __DIR__ . '/wizard-page.php';
		exit;
	}

	public function ajax_task() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sorry, you are not allowed to set up DocBooker.', 'doc-booker' ) ], 403 );
		}
		$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
		$raw  = isset( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, every field is sanitized in SetupInstaller::sanitize_answers().
		if ( ! in_array( $task, SetupInstaller::TASKS, true ) || strlen( $raw ) > 20000 ) {
			wp_send_json_error( [ 'message' => __( 'Unknown setup step.', 'doc-booker' ) ], 400 );
		}
		$answers = json_decode( $raw, true );
		$result  = SetupInstaller::run( $task, is_array( $answers ) ? $answers : [] );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error( [
				'message' => $result->get_error_message(),
				'step'    => is_array( $data ) && isset( $data['step'] ) ? sanitize_key( $data['step'] ) : '',
			], 400 );
		}
		wp_send_json_success( $result );
	}

	public function ajax_skip() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [], 403 );
		}
		SetupInstaller::save_state( [ 'skipped' => true, 'skipped_at' => time() ] );
		wp_send_json_success( [ 'redirect' => admin_url( 'edit.php?post_type=' . wpddb()->post_type_doctor ) ] );
	}

	/**
	 * Everything the page's script needs, including all of its text.
	 *
	 * @return array
	 */
	private function config() {
		$user       = wp_get_current_user();
		$name       = $user->first_name ?: $user->display_name;
		$previews   = WPDDB_URL . '/assets/admin/templates/previews/';
		$practices  = [];
		foreach ( SetupSamples::practices() as $key => $p ) {
			$practices[ $key ] = [
				'label'   => $p['label'],
				'icon'    => $p['icon'],
				'image'   => $previews . $p['image'] . '.jpg',
				'clinic'  => $p['clinic'],
				'clinics' => $p['clinics'],
				'depts'   => $p['depts'],
				'more'    => $p['more'],
				'doctors' => $p['doctors'],
			];
		}
		$week = [];
		foreach ( SetupInstaller::DAY_NAMES as $i => $day ) {
			// 2024-01-01 was a Monday.
			$week[] = wp_date( 'D', strtotime( '2024-01-0' . ( $i + 1 ) . ' 12:00 UTC' ), new \DateTimeZone( 'UTC' ) );
		}
		$now   = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local calendar date for the preview.
		$state = SetupInstaller::state();

		return [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( self::NONCE ),
			'exitUrl'   => admin_url( 'edit.php?post_type=' . wpddb()->post_type_doctor ),
			'proUrl'    => 'https://docbooker.wpdreamers.com/',
			'hasPro'    => wpddb()->has_pro(),
			'rerun'     => ! empty( $state['completed'] ),
			'email'     => get_option( 'admin_email' ),
			'practices' => $practices,
			'schedules' => SetupSamples::SCHEDULES,
			'slotLen'   => SetupSamples::SLOT_MINUTES,
			'calendar'  => [
				'year'      => (int) gmdate( 'Y', $now ),
				'month'     => (int) gmdate( 'n', $now ) - 1,
				'today'     => (int) gmdate( 'j', $now ),
				'title'     => wp_date( 'F Y' ),
				'week'      => $week,
				'monthName' => wp_date( 'F' ),
			],
			'i18n'      => $this->strings( $name ),
		];
	}

	private function strings( $name ) {
		return [
			/* translators: %s: the admin's first name. */
			'hello'           => sprintf( __( 'Hi %s 👋', 'doc-booker' ), $name ),
			'stepOf'          => __( 'Step %1$d of %2$d', 'doc-booker' ),
			'settingUp'       => __( 'Setting Up', 'doc-booker' ),
			'done'            => __( 'Done', 'doc-booker' ),
			'skipSetup'       => __( 'Skip Setup', 'doc-booker' ),
			'skipStep'        => __( 'Skip This Step', 'doc-booker' ),
			// Whole sentences per kind of practice, so every language can word them its own way.
			'startLeadGeneral'   => __( 'Set up your general hospital with your own details, or see it working first with ready-made sample data.', 'doc-booker' ),
			/* translators: %s: specialty, e.g. "Dermatology". */
			'startLeadSpecialty' => __( 'Set up your %s clinic with your own details, or see it working first with ready-made sample data.', 'doc-booker' ),
			'sampleHospital'  => __( 'Try It With a Sample Hospital', 'doc-booker' ),
			/* translators: %s: specialty, e.g. "Dermatology". */
			'sampleClinic'    => __( 'Try It With a Sample %s Clinic', 'doc-booker' ),
			/* translators: %d: number of departments. */
			'sampleText'      => __( '2 clinics, %d departments and 4 doctors with working hours. Remove them with one click later.', 'doc-booker' ),
			'yourSampleHosp'  => __( 'Your Sample Hospital', 'doc-booker' ),
			/* translators: %s: specialty, e.g. "Dermatology". */
			'yourSampleClin'  => __( 'Your Sample %s Clinic', 'doc-booker' ),
			/* translators: %d: number of departments. */
			'sampleReady'     => __( '✓ 2 clinics, %d departments and 4 doctors ready', 'doc-booker' ),
			'oneDoctor'       => __( '1 doctor available', 'doc-booker' ),
			/* translators: %d: number of doctors (2 or more). */
			'manyDoctors'     => __( '%d doctors available', 'doc-booker' ),
			'addDoctorsLater' => __( 'Add doctors later', 'doc-booker' ),
			'deptLeadGeneral'    => __( "These are the departments patients choose from when they book. We've ticked the usual ones for a general hospital.", 'doc-booker' ),
			/* translators: %s: specialty, e.g. "Dermatology". */
			'deptLeadSpecialty'  => __( "These are the departments patients choose from when they book. We've ticked the usual ones for a %s clinic.", 'doc-booker' ),
			'pickDept'        => __( 'Pick at least one department.', 'doc-booker' ),
			'pickDay'         => __( 'Pick at least one working day.', 'doc-booker' ),
			'tooShort'        => __( 'The working hours are shorter than one appointment. Make them longer or the appointments shorter.', 'doc-booker' ),
			'taskCheck'       => __( 'Checking your answers', 'doc-booker' ),
			'taskNoDoctor'    => __( 'No doctor yet: you skipped that step', 'doc-booker' ),
			'goFix'           => __( 'Go Back and Fix It', 'doc-booker' ),
			'doneNoDoctorH'   => __( 'Your Booking Page Is Ready 🎉', 'doc-booker' ),
			'doneNoDoctor'    => __( 'Add a doctor with working hours and patients can start booking right away.', 'doc-booker' ),
			'addDoctorBtn'    => __( 'Add My First Doctor', 'doc-booker' ),
			'addDoctorUrl'    => admin_url( 'post-new.php?post_type=' . wpddb()->post_type_doctor ),
			'nextFirstDoctor' => __( '👩‍⚕️ Add your first doctor under DocBooker → Add New Doctor. Patients can book as soon as a doctor has working hours.', 'doc-booker' ),
			'yourClinic'      => __( 'Your Clinic', 'doc-booker' ),
			'yourDoctor'      => __( 'Your Doctor', 'doc-booker' ),
			'department'      => __( 'Department', 'doc-booker' ),
			/* translators: %s: clinic name. */
			'workingDaysAt'   => __( 'Working Days at %s', 'doc-booker' ),
			'oneAppt'         => __( '1 appointment a day', 'doc-booker' ),
			/* translators: %d: appointments per day. */
			'manyAppts'       => __( '%d appointments a day', 'doc-booker' ),
			'oneDay'          => __( '1 day a week', 'doc-booker' ),
			/* translators: %d: working days per week. */
			'manyDays'        => __( '%d days a week', 'doc-booker' ),
			'noTime'          => __( 'The end time must be later than the start time.', 'doc-booker' ),
			'needName'        => __( "Please enter the doctor's name.", 'doc-booker' ),
			'bookAppt'        => __( 'Book Appointment', 'doc-booker' ),
			'available'       => __( 'Available', 'doc-booker' ),
			'holiday'         => __( 'Holiday', 'doc-booker' ),
			'selected'        => __( 'Selected', 'doc-booker' ),
			'unavailable'     => __( 'Unavailable', 'doc-booker' ),
			/* translators: %s: a date, e.g. "Fri, October 9". */
			'timesOn'         => __( 'Available Times on %s', 'doc-booker' ),
			'pickDayCal'      => __( 'Pick at least one working day to see bookable times.', 'doc-booker' ),
			'noDaysLeft'      => __( 'No working days are left this month. Patients will see next month.', 'doc-booker' ),
			/* translators: %s: doctor's name. */
			'calendarOf'      => __( 'Booking Calendar · %s', 'doc-booker' ),
			/* translators: %s: clinic name. */
			'taskClinic'      => __( 'Adding %s', 'doc-booker' ),
			'taskClinics'     => __( 'Adding 2 sample clinics', 'doc-booker' ),
			'taskDept'        => __( 'Adding 1 department', 'doc-booker' ),
			/* translators: %d: number of departments. */
			'taskDepts'       => __( 'Adding %d departments', 'doc-booker' ),
			/* translators: %s: doctor's name. */
			'taskDoctor'      => __( 'Adding %s and their working hours', 'doc-booker' ),
			'taskDoctors'     => __( 'Adding 4 sample doctors and their working hours', 'doc-booker' ),
			/* translators: %s: page title. */
			'taskPage'        => __( 'Creating your "%s" page', 'doc-booker' ),
			'taskFinish'      => __( 'Finishing up', 'doc-booker' ),
			'failed'          => __( 'This step did not work.', 'doc-booker' ),
			'retry'           => __( 'Try Again', 'doc-booker' ),
			'expired'         => __( 'Your session has expired. Reload the page and run the setup again; nothing is lost.', 'doc-booker' ),
			'reload'          => __( 'Reload', 'doc-booker' ),
			'nextPhotos'      => __( '📷 Add a photo for each doctor. Profiles with photos get more bookings.', 'doc-booker' ),
			'nextDoctors'     => __( '➕ Add your other doctors under DocBooker → Add New Doctor.', 'doc-booker' ),
			'nextHolidays'    => __( "🗓️ Mark holidays so patients can't book days you're closed.", 'doc-booker' ),
			'nextSamples'     => __( '🧹 Swap the sample doctors for your own, or remove them all from the Doctors list with one click.', 'doc-booker' ),
			'menuAdded'       => __( 'Your booking page is also in your site menu.', 'doc-booker' ),
			'menuMissing'     => __( 'Your site has no menu yet, so the page was not added to one. Add it under Appearance → Menus.', 'doc-booker' ),
			'readyHospital'   => __( 'A Ready-Made Hospital Website', 'doc-booker' ),
			/* translators: %s: specialty, e.g. "Dermatology". */
			'readyNiche'      => __( 'A Ready-Made %s Website', 'doc-booker' ),
			'readyText'       => __( 'A full site design for your kind of practice, imported in one click. Nine more included.', 'doc-booker' ),
			'seeWhat'         => __( 'See What It Does ↗', 'doc-booker' ),
			'pro'             => [
				[ '💳', __( 'Online Payments', 'doc-booker' ), __( 'Patients pay when they book, with Stripe or Razorpay.', 'doc-booker' ) ],
				[ '🏥', __( 'Front Desk & Clinic Dashboards', 'doc-booker' ), __( 'Receptionists and clinic managers get their own screens.', 'doc-booker' ) ],
				[ '🧾', __( 'Bills & Invoices', 'doc-booker' ), __( 'Running bills, daily payments and printable invoices.', 'doc-booker' ) ],
				[ '🧪', __( 'Lab Tests & Reports', 'doc-booker' ), __( 'Order tests, upload reports, print receipts with a QR code.', 'doc-booker' ) ],
				[ '📱', __( 'Patient Report Portal', 'doc-booker' ), __( 'Patients open their own reports with a code sent by SMS.', 'doc-booker' ) ],
			],
			'skipTitle'       => __( 'Skip the setup?', 'doc-booker' ),
			'skipText'        => __( 'You can add clinics and doctors yourself, or run the setup again any time from the Doctors list.', 'doc-booker' ),
			'skipYes'         => __( 'Yes, Skip Setup', 'doc-booker' ),
			'skipNo'          => __( 'Keep Going', 'doc-booker' ),
		];
	}
}
