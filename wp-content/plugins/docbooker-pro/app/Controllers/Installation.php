<?php

namespace WpDreamers\WPDDBP\Controllers;

use WpDreamers\WPDDBP\Controllers\Receptionist\UserRole as ReceptionistRole;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit( 'This script cannot be accessed directly.' );
}

class Installation {

    const CRON_HOOK = 'wpddb_purge_old_reports';

    public static function activate() {
        self::maybe_upgrade();
        self::update_wpddbp_version();
        flush_rewrite_rules();
        do_action( 'wpddb_installed' );
    }

    public static function deactivation() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
        flush_rewrite_rules();
    }

    private static function update_wpddbp_version() {
        update_option( 'wpddbp_version', WPDDBP_VERSION );
    }

    /**
     * Version-gated migration entry point. Called on plugins_loaded as well as
     * on activation so WordPress auto-updates (which skip the activation hook)
     * still pick up schema changes.
     */
    public static function maybe_upgrade() {
        $stored = get_option( 'wpddbp_db_version', '0.0.0' );
        if ( version_compare( $stored, WPDDBP_DB_VERSION, '<' ) ) {
            self::run_migrations( $stored );
            update_option( 'wpddbp_db_version', WPDDBP_DB_VERSION );
        }
    }

    private static function run_migrations( $from ) {
        if ( version_compare( $from, '1.1.0', '<' ) ) {
            self::migrate_1_1_0();
        }
        if ( version_compare( $from, '1.1.1', '<' ) ) {
            self::migrate_1_1_1();
        }
        if ( version_compare( $from, '1.1.2', '<' ) ) {
            self::migrate_1_1_2();
        }
        if ( version_compare( $from, '1.1.3', '<' ) ) {
            self::migrate_1_1_3();
        }
    }

    /**
     * 1.1.3 — drops the hardcoded "Dr " prefix from the booking template so we
     * don't double-prefix when doctor post titles already contain "Dr.". Only
     * rewrites the value if it still matches the 1.1.2-seeded default — admin
     * customisations are preserved.
     */
    private static function migrate_1_1_3() {
        $sms = get_option( 'wpddb_sms_settings', null );
        if ( ! is_array( $sms ) ) {
            return;
        }
        $old_default = 'Hi {{patient}}, your booking with Dr {{doctor}} on {{time}} at {{clinic}} ({{clinic_address}}) is confirmed. Ref: {{order_code}}';
        $new_default = 'Hi {{patient}}, your booking with {{doctor}} on {{time}} at {{clinic}} ({{clinic_address}}) is confirmed. Ref: {{order_code}}';
        if ( isset( $sms['booking_message_template'] ) && $sms['booking_message_template'] === $old_default ) {
            $sms['booking_message_template'] = $new_default;
            update_option( 'wpddb_sms_settings', $sms );
        }
    }

    /**
     * 1.1.2 — adds the booking-confirmation SMS template + the separated
     * otp_message_template, and rewrites any payload_template still using
     * {{code}} as the body to use {{message}} so OTP and booking flows can
     * both work without surgery. Idempotent.
     */
    private static function migrate_1_1_2() {
        $sms = get_option( 'wpddb_sms_settings', null );
        if ( ! is_array( $sms ) ) {
            // First-install case is handled by migrate_1_1_1 which already
            // seeded the option, but be defensive.
            return;
        }
        $touched = false;

        if ( ! array_key_exists( 'otp_message_template', $sms ) || $sms['otp_message_template'] === '' ) {
            $sms['otp_message_template'] = 'Your DocBooker OTP is {{code}}';
            $touched = true;
        }
        if ( ! array_key_exists( 'booking_message_template', $sms ) || $sms['booking_message_template'] === '' ) {
            $sms['booking_message_template'] = 'Hi {{patient}}, your booking with {{doctor}} on {{time}} at {{clinic}} ({{clinic_address}}) is confirmed. Ref: {{order_code}}';
            $touched = true;
        }

        // Best-effort cleanup of the old "Body=Your+OTP+is+{{code}}" pattern so
        // booking SMS doesn't get prefixed with "Your OTP is". If we detect the
        // old shape we replace just the body segment with {{message}} and let
        // the OTP message-template own the user-facing prefix.
        if ( isset( $sms['payload_template'] ) && is_string( $sms['payload_template'] ) ) {
            $pt = $sms['payload_template'];
            // Form-urlencoded body like Body=Your+OTP+is+{{code}}
            $pt_new = preg_replace( '/Body=[^&]*\{\{code\}\}[^&]*/', 'Body={{message}}', $pt );
            // JSON body like "text":"Your OTP is {{code}}"
            $pt_new = preg_replace( '/"text"\s*:\s*"[^"]*\{\{code\}\}[^"]*"/', '"text":"{{message}}"', (string) $pt_new );
            if ( $pt_new !== null && $pt_new !== $pt ) {
                $sms['payload_template'] = $pt_new;
                $touched = true;
            }
        }

        if ( $touched ) {
            update_option( 'wpddb_sms_settings', $sms );
        }
    }

    /**
     * 1.1.1 — backfill wpddb_sms_settings keys that landed in builds before the
     * UI exposed only a subset (user_agent / sslverify / timeout). Idempotent.
     */
    private static function migrate_1_1_1() {
        $sms_defaults = [
            'enabled'                  => false,
            'provider_url'             => '',
            'method'                   => 'POST',
            'headers_json'             => '{"Content-Type":"application/json"}',
            'payload_template'         => '{"to":"{{phone}}","text":"{{message}}"}',
            'success_path'             => '',
            'user_agent'               => 'Mozilla/5.0 (compatible; DocBookerPro/1.0)',
            'sslverify'                => true,
            'timeout'                  => 15,
            'otp_message_template'     => 'Your DocBooker OTP is {{code}}',
            'booking_message_template' => 'Hi {{patient}}, your booking with {{doctor}} on {{time}} at {{clinic}} ({{clinic_address}}) is confirmed. Ref: {{order_code}}',
        ];
        $sms = get_option( 'wpddb_sms_settings', null );
        if ( ! is_array( $sms ) ) {
            add_option( 'wpddb_sms_settings', $sms_defaults, '', 'no' );
            return;
        }
        $touched = false;
        foreach ( $sms_defaults as $k => $v ) {
            if ( ! array_key_exists( $k, $sms ) ) {
                $sms[ $k ] = $v;
                $touched   = true;
            }
        }
        if ( $touched ) {
            update_option( 'wpddb_sms_settings', $sms );
        }
    }

    /**
     * Initial Pro 1.1.0 schema: 8 new tables (receipts + visits + reports +
     * portal OTP), receptionist role install, option defaults, daily cron.
     */
    private static function migrate_1_1_0() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p       = $wpdb->prefix;

        dbDelta( "CREATE TABLE `{$p}wpddb_test_orders` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_code VARCHAR(20) NOT NULL,
            patient_id BIGINT(20) UNSIGNED NOT NULL,
            clinic_id BIGINT(20) UNSIGNED NOT NULL,
            receptionist_id BIGINT(20) UNSIGNED NOT NULL,
            refd_by VARCHAR(120) DEFAULT NULL,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            extra_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
            received DECIMAL(10,2) NOT NULL DEFAULT 0,
            payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            delivery_at DATETIME DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_order_code (order_code),
            KEY idx_clinic (clinic_id),
            KEY idx_patient (patient_id),
            KEY idx_created (created_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE `{$p}wpddb_test_order_items` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            test_item_id BIGINT(20) UNSIGNED DEFAULT NULL,
            name_snapshot VARCHAR(160) NOT NULL,
            price_snapshot DECIMAL(10,2) NOT NULL,
            qty SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            disc_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY idx_order (order_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE `{$p}wpddb_test_items` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            clinic_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(160) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            category VARCHAR(80) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_clinic_name (clinic_id, name),
            KEY idx_clinic_status (clinic_id, status)
        ) {$charset};" );

        dbDelta( "CREATE TABLE `{$p}wpddb_test_reports` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            attachment_id BIGINT(20) UNSIGNED NOT NULL,
            uploaded_by BIGINT(20) UNSIGNED NOT NULL,
            file_hash CHAR(64) DEFAULT NULL,
            note VARCHAR(255) DEFAULT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_order (order_id),
            KEY idx_uploaded_at (uploaded_at),
            KEY idx_expires (expires_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE `{$p}wpddb_otp_codes` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            phone VARCHAR(50) NOT NULL,
            purpose VARCHAR(32) NOT NULL DEFAULT 'portal_login',
            order_code VARCHAR(20) DEFAULT NULL,
            code_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            verified_at DATETIME DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_phone_purpose (phone, purpose),
            KEY idx_expires (expires_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE `{$p}wpddb_patient_visits` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            patient_id BIGINT(20) UNSIGNED NOT NULL,
            clinic_id BIGINT(20) UNSIGNED NOT NULL,
            visit_type VARCHAR(10) NOT NULL DEFAULT 'opd',
            visit_date DATE NOT NULL,
            consult_doctor_id BIGINT(20) UNSIGNED DEFAULT NULL,
            admit_doctor_id BIGINT(20) UNSIGNED DEFAULT NULL,
            admitted_at DATETIME DEFAULT NULL,
            released_at DATETIME DEFAULT NULL,
            bed_no VARCHAR(40) DEFAULT NULL,
            total_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
            received DECIMAL(10,2) NOT NULL DEFAULT 0,
            doctor_advice TEXT DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_patient (patient_id),
            KEY idx_clinic_date (clinic_id, visit_date),
            KEY idx_status (status)
        ) {$charset};" );

        dbDelta( "CREATE TABLE `{$p}wpddb_visit_items` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visit_id BIGINT(20) UNSIGNED NOT NULL,
            item_type VARCHAR(20) NOT NULL DEFAULT 'service',
            name VARCHAR(160) NOT NULL,
            qty SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            disc_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            item_date DATE DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_visit (visit_id),
            KEY idx_type (item_type)
        ) {$charset};" );

        dbDelta( "CREATE TABLE `{$p}wpddb_visit_payments` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visit_id BIGINT(20) UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(40) NOT NULL DEFAULT 'cash',
            reference VARCHAR(120) DEFAULT NULL,
            received_by BIGINT(20) UNSIGNED NOT NULL,
            received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_visit (visit_id)
        ) {$charset};" );

        // Receptionist role + cap (idempotent).
        if ( class_exists( ReceptionistRole::class ) ) {
            ReceptionistRole::install();
        }

        // Option defaults — add_option only writes if absent, so user customizations survive re-runs.
        $global = get_option( 'wpddb_global_settings', [] );
        $touched = false;
        if ( ! isset( $global['receptionist_manage_enable'] ) ) {
            $global['receptionist_manage_enable'] = 'off';
            $touched = true;
        }
        if ( ! isset( $global['report_retention_days'] ) ) {
            $global['report_retention_days'] = 30;
            $touched = true;
        }
        if ( $touched ) {
            update_option( 'wpddb_global_settings', $global );
        }

        // SMS / OTP defaults — same idempotent pattern as $global above so an
        // upgrader who already had a partial wpddb_sms_settings (e.g. only the
        // 6 UI keys saved by an earlier build) gets the missing knobs filled in.
        $sms_defaults = [
            'enabled'          => false,
            'provider_url'     => '',
            'method'           => 'POST',
            'headers_json'     => '{"Content-Type":"application/json"}',
            'payload_template' => '{"to":"{{phone}}","text":"Your OTP is {{code}}"}',
            'success_path'     => '',
            'user_agent'       => 'Mozilla/5.0 (compatible; DocBookerPro/1.0)',
            'sslverify'        => true,
            'timeout'          => 15,
        ];
        $sms     = get_option( 'wpddb_sms_settings', null );
        if ( $sms === null || ! is_array( $sms ) ) {
            // First install — create the option.
            add_option( 'wpddb_sms_settings', $sms_defaults, '', 'no' );
        } else {
            // Existing install — fill any keys that are missing.
            $sms_touched = false;
            foreach ( $sms_defaults as $k => $v ) {
                if ( ! array_key_exists( $k, $sms ) ) {
                    $sms[ $k ] = $v;
                    $sms_touched = true;
                }
            }
            if ( $sms_touched ) {
                update_option( 'wpddb_sms_settings', $sms );
            }
        }

        // Daily retention cron — idempotent.
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }
}
