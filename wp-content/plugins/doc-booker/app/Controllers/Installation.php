<?php

namespace WpDreamers\WPDDB\Controllers;
// Do not allow directly accessing this file.
use WpDreamers\WPDDB\Controllers\Admin\CustomUserRole;
use WpDreamers\WPDDB\Controllers\Admin\RegisterPostType;
use WpDreamers\WPDDB\Controllers\Admin\Setup\SetupWizard;
use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Helper\SchemaUtil;
use WpDreamers\WPDDB\Controllers\Model\DatabaseTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

class Installation {
	public static function activate() {
		// No stored version means DocBooker has never run on this site.
		$fresh = ! get_option( 'wpddb_version' );
		if ( $fresh ) {
			self::create_options();
		}
		DatabaseTable::create_custom_db_table();
		CustomUserRole::create_custom_user_roles();
		self::update_wpddb_version();
		// The listener for wpddb_flush_rewrite_rules is not attached yet while a
		// plugin is being activated, so the request is left as an option instead.
		RegisterPostType::flush_rewrite_rules();
		SetupWizard::offer( $fresh );
		do_action( 'wpddb_installed' );
	}

	private static function update_wpddb_version() {
		update_option( 'wpddb_version', WPDDB_VERSION );

	}

	private static function create_options() {

		$options = [
			'wpddb_permalinks_settings' => [
				'doctor_base'          => 'wpddb_doctor',
				'doctor_category_base' => 'wpddb_doctor_category',
				'clinic_base'          => 'wpddb_clinic',
			],
			'wpddb_doctor_settings'     => [
				'doctor_archive_style'       => 'layout-1',
				'doctor_posts_per_page'      => '9',
				'doctor_grid_columns'        => '3',
				'doctor_page_layout'         => 'full-width',
				'doctor_single_page_layout'  => 'full-width',
				'doctor_orderBy'             => 'none',
				'doctor_order'               => 'ASC',
				'doctor_thumbnail_width'     => '570',
				'doctor_thumbnail_height'    => '400',
				'doctor_thumbnail_hard_crop' => 'on',
				'slider_autoplay'            => 'on',
				'slider_loop'                => 'on',
				'centered_slider'            => 'on',
				'slides_per_view'            => '3',
				'display_doctor_schedule'    => 'on',
				'call_for_booking'           => 'on',
				'showing_bookable_text'      => 'on',
				'pre_booking_window'         => '0',
				'min_notice_hours'           => '0',
				'reminder_email'             => 'on',
				'doctor_reminder_email'      => 'on',
				'patient_cancel'             => 'on',
				'cancel_cutoff_hours'        => '24',
			],
			'wpddb_clinic_settings'     => [
				'clinic_posts_per_page'      => '9',
				'clinic_grid_columns'        => '3',
				'clinic_archive_style'       => 'layout-1',
				'clinic_page_layout'         => 'full-width',
				'clinic_single_page_layout'  => 'full-width',
				'clinic_post_label'          => __( 'Clinic', 'doc-booker' ),
				'clinic_orderBy'             => 'none',
				'clinic_order'               => 'ASC',
				'clinic_thumbnail_width'     => '570',
				'clinic_thumbnail_height'    => '400',
				'clinic_thumbnail_hard_crop' => 'on',
			],
			'wpddb_style_settings'      => [
				'wpddb_primary_color'   => '#5d3dfd',
				'wpddb_secondary_color' => '#ebf3fc',
			],
            'wpddb_global_settings'      => [
                'online_booking_payment'   => 'on',
                'payment_gateway' => 'stripe',
                'stripe_mode' => 'test',
                'razorpay_mode' => 'test',
                'currency' => 'USD',
                'currency_position' => 'left',
            ],

		];

		foreach ( $options as $option_name => $defaults ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $defaults );
			}
		}

		$pages = Helper::insert_custom_pages();

		if ( is_array( $pages ) && ! empty( $pages ) ) {
			$pSettings = get_option( 'wpddb_page_settings' ) ?: [];
			foreach ( $pages as $pSlug => $pId ) {
				$pSettings[ $pSlug ] = $pId;
			}
			update_option( 'wpddb_page_settings', $pSettings );
		}

	}

	public static function deactivation() {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( \WpDreamers\WPDDB\Controllers\BookingReminders::CRON_HOOK );
	}

	/**
	 * Schema changes that must not wait for a release: each step runs once,
	 * on every site, when the stored schema number is lower.
	 */
	const SCHEMA = 2;

	public static function update_schema() {
		$current = (int) get_option( 'wpddb_schema_version', 0 );
		if ( $current >= self::SCHEMA ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'wpddb_bookings';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return; // Created complete on activation.
		}
		if ( $current < 1 ) {
			// 1: reminder emails remember which bookings were reminded.
			SchemaUtil::safe_add_column( 'wpddb_bookings', 'reminder_sent', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER status' );
		}
		if ( $current < 2 ) {
			// 2: the admin app marks bookings nobody has opened yet as new.
			// Existing bookings count as seen (the default), so an update
			// does not flood the list with old "new" rows.
			SchemaUtil::safe_add_column( 'wpddb_bookings', 'admin_seen', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER reminder_sent' );
			SchemaUtil::safe_add_column( 'wpddb_bookings', 'booked_via', 'VARCHAR(10) NULL DEFAULT NULL AFTER admin_seen' );
			SchemaUtil::safe_add_index( 'wpddb_bookings', 'idx_admin_seen', '(admin_seen)' );
		}
		update_option( 'wpddb_schema_version', self::SCHEMA );
	}

	/**
	 * Record the first-activation timestamp once, so we can report plugin
	 * "install age" (days active) in the deactivation feedback survey.
	 * Mirrors Gym Builder's Install::plugin_activation_time(). Hooked on
	 * plugins_loaded so it also backfills existing installs on first run.
	 */
	public static function plugin_activation_time() {
		if ( ! get_option( 'wpddb_plugin_activation_time' ) ) {
			update_option( 'wpddb_plugin_activation_time', time() );
		}
	}

	/**
	 * Cancelling used to write status 'cancel', which the status column cannot
	 * hold, so those rows were stored with an empty status and dropped out of
	 * the "Cancelled" filter. Repair them once.
	 */
	public static function repair_cancelled_status() {
		if ( get_option( 'wpddb_cancelled_status_repaired' ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'wpddb_bookings';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$wpdb->query( "UPDATE $table SET status = 'cancelled' WHERE status = '' OR status IS NULL" );
		}
		update_option( 'wpddb_cancelled_status_repaired', 1 );
	}

	public static function check_and_update_plugin_version() {
		$installed_version = get_option( 'wpddb_version' );

		if ( $installed_version !== WPDDB_VERSION ) {
            self::database_migration();
			update_option( 'wpddb_version', WPDDB_VERSION );
			// Sites activated before this fix may still be missing the doctor and clinic rules.
			RegisterPostType::flush_rewrite_rules();
		}
	}
    public static function database_migration(  ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpddb_bookings';
        $transaction_id_column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table_name` LIKE 'transaction_id'");
        $amount_column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table_name` LIKE 'amount_paid'");
        $payment_column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table_name` LIKE 'payment_status'");
        $payment_by_column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table_name` LIKE 'payment_by'");

        if (empty($transaction_id_column_exists)) {
            $wpdb->query("ALTER TABLE `$table_name` ADD `transaction_id` VARCHAR(200) DEFAULT NULL");
        }
        if (empty($amount_column_exists)){
            $wpdb->query("ALTER TABLE `$table_name`
            ADD `amount_paid` DECIMAL(10, 2) DEFAULT 0.00");
        }
        if (empty($payment_column_exists)) {
            $wpdb->query("ALTER TABLE `$table_name` ADD `payment_status` VARCHAR(50) DEFAULT NULL");
        }
        if (empty($payment_by_column_exists)) {
            $wpdb->query("ALTER TABLE `$table_name` ADD `payment_by` VARCHAR(50) DEFAULT NULL");
        }

        $booking_date_column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table_name` LIKE 'booking_date'");
        if (empty($booking_date_column_exists)) {
            $wpdb->query("ALTER TABLE `$table_name` ADD `booking_date` DATE DEFAULT NULL AFTER `booking_present_status`");
            $wpdb->query("ALTER TABLE `$table_name` ADD INDEX `idx_doctor_date_time` (`doctor_id`, `booking_date`, `time`, `status`)");
        }

        self::migrate_patients_schema();
    }

    /**
     * Idempotent migration for the patients table — drops the email UNIQUE
     * constraint (walk-in patients have no email), makes email nullable, and
     * adds dob/gender/address/patient_code columns. Backfills patient_code
     * for existing rows so they're accessible via the patient portal.
     */
    private static function migrate_patients_schema() {
        global $wpdb;
        $tbl = $wpdb->prefix . 'wpddb_patients';

        // Drop the email unique key. WP names the constraint via the column itself
        // when declared inline; older installs used `unique_email`, newer ones may
        // bear `email`. Try both to cover the matrix.
        SchemaUtil::safe_drop_index( 'wpddb_patients', 'unique_email' );
        SchemaUtil::safe_drop_index( 'wpddb_patients', 'email' );

        // email column: NOT NULL → NULL
        $is_nullable = SchemaUtil::get_column_is_nullable( 'wpddb_patients', 'email' );
        if ( $is_nullable && strtoupper( $is_nullable ) === 'NO' ) {
            $wpdb->query( "ALTER TABLE `{$tbl}` MODIFY `email` VARCHAR(100) NULL" );
        }

        SchemaUtil::safe_add_column( 'wpddb_patients', 'dob',          'DATE NULL AFTER phone' );
        SchemaUtil::safe_add_column( 'wpddb_patients', 'gender',       "ENUM('male','female','other') NULL AFTER dob" );
        SchemaUtil::safe_add_column( 'wpddb_patients', 'address',      'TEXT NULL AFTER gender' );
        SchemaUtil::safe_add_column( 'wpddb_patients', 'patient_code', 'VARCHAR(30) NULL AFTER address' );
        // The clinic whose front desk registered a walk-in (Pro); empty for
        // patients who came through online booking.
        SchemaUtil::safe_add_column( 'wpddb_patients', 'registered_clinic_id', 'BIGINT(20) UNSIGNED NULL AFTER patient_code' );

        SchemaUtil::safe_add_unique( 'wpddb_patients', 'uq_patient_code', '(patient_code)' );
        SchemaUtil::safe_add_index(  'wpddb_patients', 'idx_phone',       '(phone)' );

        // Backfill patient_code for existing rows so the portal lookup works.
        $wpdb->query(
            "UPDATE `{$tbl}`
             SET patient_code = CONCAT('P', DATE_FORMAT(NOW(),'%y%m%d'), LPAD(id, 5, '0'))
             WHERE patient_code IS NULL OR patient_code = ''"
        );
    }
}
