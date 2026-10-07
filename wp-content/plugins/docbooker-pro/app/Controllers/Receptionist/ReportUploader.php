<?php
/**
 * Multi-file PDF report uploader. Shared by the receptionist and clinic-admin
 * REST endpoints. Strict validation: MIME + extension + finfo magic bytes;
 * 10MB cap per file; sha256 de-dup per order.
 */

namespace WpDreamers\WPDDBP\Controllers\Receptionist;

use WP_Error;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestReport;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestOrder;

defined( 'ABSPATH' ) || exit;

class ReportUploader {

	const MAX_BYTES = 10485760; // 10 MB

	/**
	 * Process a single $_FILES entry (with possibly multiple files inside).
	 * Returns ['uploaded' => [{report_id, filename, attachment_id}], 'errors' => [{filename, reason}]].
	 */
	public static function handle_files( $order_id ) {
		$order = TestOrder::find( (int) $order_id );
		if ( ! $order ) {
			return new WP_Error( 'wpddbp_order_not_found', __( 'Order not found.', 'doc-booker-pro' ), [ 'status' => 404 ] );
		}

		if ( empty( $_FILES['files'] ) ) {
			return new WP_Error( 'wpddbp_no_files', __( 'No files received.', 'doc-booker-pro' ), [ 'status' => 400 ] );
		}

		$files     = self::normalize_files_array( $_FILES['files'] );
		$uploaded  = [];
		$errors    = [];

		foreach ( $files as $file ) {
			$result = self::handle_one( $file, (int) $order_id );
			if ( is_wp_error( $result ) ) {
				$errors[] = [
					'filename' => $file['name'] ?? '',
					'reason'   => $result->get_error_message(),
				];
			} else {
				$uploaded[] = $result;
			}
		}

		return [
			'uploaded' => $uploaded,
			'errors'   => $errors,
		];
	}

	/**
	 * Validate + persist a single uploaded PDF.
	 */
	private static function handle_one( array $file, $order_id ) {
		$err = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_OK;
		if ( $err !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'wpddbp_upload_error', sprintf( __( 'Upload failed (code %d)', 'doc-booker-pro' ), $err ) );
		}
		$tmp  = $file['tmp_name'];
		$name = (string) ( $file['name'] ?? '' );
		$size = (int) ( $file['size'] ?? 0 );

		if ( ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'wpddbp_not_uploaded', __( 'File was not uploaded via HTTP.', 'doc-booker-pro' ) );
		}
		if ( $size <= 0 || $size > self::MAX_BYTES ) {
			return new WP_Error( 'wpddbp_size', __( 'File is empty or exceeds 10MB.', 'doc-booker-pro' ) );
		}

		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( $ext !== 'pdf' ) {
			return new WP_Error( 'wpddbp_ext', __( 'Only PDF files are allowed.', 'doc-booker-pro' ) );
		}
		$reported_mime = strtolower( (string) ( $file['type'] ?? '' ) );
		if ( $reported_mime !== 'application/pdf' ) {
			return new WP_Error( 'wpddbp_mime', __( 'Only PDF files are allowed (mime).', 'doc-booker-pro' ) );
		}
		if ( function_exists( 'finfo_open' ) ) {
			$finfo  = finfo_open( FILEINFO_MIME_TYPE );
			$detect = finfo_file( $finfo, $tmp );
			finfo_close( $finfo );
			if ( strtolower( (string) $detect ) !== 'application/pdf' ) {
				return new WP_Error( 'wpddbp_magic', __( 'File contents are not a valid PDF.', 'doc-booker-pro' ) );
			}
		}

		$hash = hash_file( 'sha256', $tmp );
		if ( $hash && TestReport::exists_for_order_and_hash( $order_id, $hash ) ) {
			return new WP_Error( 'wpddbp_dup', __( 'A report with identical contents was already uploaded for this order.', 'doc-booker-pro' ) );
		}

		// Tighten upload_mimes to PDF for the duration of this call.
		$mimes_filter = function ( $mimes ) {
			return [ 'pdf' => 'application/pdf' ];
		};
		add_filter( 'upload_mimes', $mimes_filter, 999 );

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_insert_attachment' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$movefile = wp_handle_upload(
			$file,
			[
				'test_form' => false,
				'mimes'     => [ 'pdf' => 'application/pdf' ],
			]
		);
		remove_filter( 'upload_mimes', $mimes_filter, 999 );

		if ( ! $movefile || ! empty( $movefile['error'] ) ) {
			$message = $movefile['error'] ?? __( 'Upload failed.', 'doc-booker-pro' );
			return new WP_Error( 'wpddbp_move_failed', $message );
		}

		$attachment    = [
			'guid'           => $movefile['url'],
			'post_mime_type' => $movefile['type'],
			'post_title'     => sanitize_text_field( pathinfo( $name, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'private',
		];
		$attachment_id = wp_insert_attachment( $attachment, $movefile['file'] );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			@unlink( $movefile['file'] );
			return new WP_Error( 'wpddbp_attachment_failed', __( 'Could not create attachment record.', 'doc-booker-pro' ) );
		}
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $movefile['file'] ) );

		$report_id = TestReport::create( [
			'order_id'      => $order_id,
			'attachment_id' => $attachment_id,
			'uploaded_by'   => get_current_user_id(),
			'file_hash'     => $hash,
		] );
		if ( ! $report_id ) {
			wp_delete_attachment( $attachment_id, true );
			return new WP_Error( 'wpddbp_report_insert_failed', __( 'Failed to record report.', 'doc-booker-pro' ) );
		}

		return [
			'report_id'     => $report_id,
			'attachment_id' => $attachment_id,
			'filename'      => $name,
			'size'          => $size,
		];
	}

	/**
	 * Convert the awkward $_FILES['files'] (array of names, tmp_names, etc.)
	 * into a flat list of single-file entries.
	 */
	private static function normalize_files_array( $entry ) {
		// Already a single-file entry?
		if ( ! is_array( $entry['name'] ?? null ) ) {
			return [ $entry ];
		}
		$count = count( $entry['name'] );
		$out   = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$out[] = [
				'name'     => $entry['name'][ $i ]     ?? '',
				'type'     => $entry['type'][ $i ]     ?? '',
				'tmp_name' => $entry['tmp_name'][ $i ] ?? '',
				'error'    => $entry['error'][ $i ]    ?? UPLOAD_ERR_NO_FILE,
				'size'     => $entry['size'][ $i ]     ?? 0,
			];
		}
		return $out;
	}
}
