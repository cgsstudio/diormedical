<?php
/**
 * Idempotent schema-migration helpers. Safe to call on every plugin load.
 * All methods accept the table name WITHOUT the wp_ prefix; the helpers prepend
 * $wpdb->prefix internally so multisite / custom-prefix sites work.
 */

namespace WpDreamers\WPDDB\Controllers\Helper;

defined( 'ABSPATH' ) || exit;

class SchemaUtil {

	public static function column_exists( $table_no_prefix, $column ) {
		global $wpdb;
		$full = $wpdb->prefix . $table_no_prefix;
		$row  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				$full,
				$column
			)
		);
		return (int) $row > 0;
	}

	public static function index_exists( $table_no_prefix, $index_name ) {
		global $wpdb;
		$full = $wpdb->prefix . $table_no_prefix;
		$row  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.STATISTICS
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s",
				$full,
				$index_name
			)
		);
		return (int) $row > 0;
	}

	public static function safe_add_column( $table_no_prefix, $column, $definition ) {
		if ( self::column_exists( $table_no_prefix, $column ) ) {
			return false;
		}
		global $wpdb;
		$full = $wpdb->prefix . $table_no_prefix;
		// $column + $definition are developer-controlled; not user input.
		return $wpdb->query( "ALTER TABLE `{$full}` ADD COLUMN `{$column}` {$definition}" );
	}

	public static function safe_add_index( $table_no_prefix, $index_name, $definition ) {
		if ( self::index_exists( $table_no_prefix, $index_name ) ) {
			return false;
		}
		global $wpdb;
		$full = $wpdb->prefix . $table_no_prefix;
		return $wpdb->query( "ALTER TABLE `{$full}` ADD INDEX `{$index_name}` {$definition}" );
	}

	public static function safe_add_unique( $table_no_prefix, $index_name, $columns ) {
		if ( self::index_exists( $table_no_prefix, $index_name ) ) {
			return false;
		}
		global $wpdb;
		$full = $wpdb->prefix . $table_no_prefix;
		return $wpdb->query( "ALTER TABLE `{$full}` ADD UNIQUE KEY `{$index_name}` {$columns}" );
	}

	public static function safe_drop_index( $table_no_prefix, $index_name ) {
		if ( ! self::index_exists( $table_no_prefix, $index_name ) ) {
			return false;
		}
		global $wpdb;
		$full = $wpdb->prefix . $table_no_prefix;
		return $wpdb->query( "ALTER TABLE `{$full}` DROP INDEX `{$index_name}`" );
	}

	public static function get_column_is_nullable( $table_no_prefix, $column ) {
		global $wpdb;
		$full = $wpdb->prefix . $table_no_prefix;
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT IS_NULLABLE FROM information_schema.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				$full,
				$column
			)
		);
	}
}
