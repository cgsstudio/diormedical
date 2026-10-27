<?php
/**
 * Where lab report PDFs live on disk.
 *
 * Reports are patient health data, so they never sit in the dated, public
 * uploads folders. They go into uploads/wpddb-reports/ under a random name:
 * Apache refuses the folder outright through .htaccess, and on servers that
 * ignore .htaccess (nginx) the 128-bit names cannot be guessed. Patients
 * reach a report only through the signed, session-checked portal download.
 */

namespace WpDreamers\WPDDBP\Controllers\Receptionist;

defined( 'ABSPATH' ) || exit;

class ReportStorage {

	const FOLDER = 'wpddb-reports';

	/**
	 * The folder's path and URL, creating it (with its guards) when needed.
	 *
	 * @return array{path:string, url:string}|null Null when uploads are not writable.
	 */
	public static function location() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}

		$path = trailingslashit( $uploads['basedir'] ) . self::FOLDER;
		if ( ! wp_mkdir_p( $path ) ) {
			return null;
		}
		self::guard( $path );

		return [
			'path' => $path,
			'url'  => trailingslashit( $uploads['baseurl'] ) . self::FOLDER,
		];
	}

	/**
	 * A fresh, unguessable file name.
	 *
	 * @return string
	 */
	public static function random_name() {
		return bin2hex( random_bytes( 16 ) ) . '.pdf';
	}

	/**
	 * Point wp_handle_upload() at the reports folder for one upload.
	 *
	 * @param array $dirs wp_upload_dir() values.
	 * @return array
	 */
	public static function upload_dir_filter( $dirs ) {
		$dirs['subdir'] = '/' . self::FOLDER;
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

		return $dirs;
	}

	/**
	 * Whether an attached file already lives in the reports folder.
	 *
	 * @param string $file Absolute path.
	 * @return bool
	 */
	public static function is_protected( $file ) {
		$here = self::location();

		return $here && 0 === strpos( wp_normalize_path( (string) $file ), wp_normalize_path( trailingslashit( $here['path'] ) ) );
	}

	/**
	 * Move an existing report attachment into the reports folder.
	 *
	 * @param int $attachment_id
	 * @return bool True when it is (now) protected.
	 */
	public static function protect_attachment( $attachment_id ) {
		$file = get_attached_file( (int) $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return false;
		}
		if ( self::is_protected( $file ) ) {
			return true;
		}

		$here = self::location();
		if ( ! $here ) {
			return false;
		}

		$target = trailingslashit( $here['path'] ) . self::random_name();
		if ( ! @rename( $file, $target ) ) {
			return false;
		}

		update_attached_file( (int) $attachment_id, $target );
		global $wpdb;
		$wpdb->update( $wpdb->posts, [ 'guid' => trailingslashit( $here['url'] ) . basename( $target ) ], [ 'ID' => (int) $attachment_id ] );
		clean_post_cache( (int) $attachment_id );

		return true;
	}

	/**
	 * Keep listings and direct access out of the folder.
	 *
	 * @param string $path
	 */
	private static function guard( $path ) {
		$htaccess = $path . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "# DocBooker Pro: lab reports are served only through the patient portal.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n" );
		}
		$index = $path . '/index.php';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}
}
