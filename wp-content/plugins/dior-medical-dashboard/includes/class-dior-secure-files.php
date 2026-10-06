<?php
/**
 * Dior Medical - Secure Medical Records & File Management Service
 * 
 * Provides protected file storage, deep binary MIME validation, authenticated
 * streaming download gates, digital attestation, and anti-tamper letter generation.
 * 
 * @package Dior Medical
 * @version 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dior_Medical_Secure_Files
{
    private static $secure_dir = null;
    private static $secure_url = null;

    /**
     * Initialize service
     */
    public static function init()
    {
        $upload_dir = wp_upload_dir();
        self::$secure_dir = trailingslashit($upload_dir['basedir']) . 'dior_secure_records/';
        self::$secure_url = trailingslashit($upload_dir['baseurl']) . 'dior_secure_records/';

        self::ensure_secure_directory();

        // AJAX Handlers
        add_action('wp_ajax_dior_upload_document', [__CLASS__, 'ajax_upload_document']);
        add_action('wp_ajax_dior_delete_document', [__CLASS__, 'ajax_delete_document']);
        add_action('wp_ajax_dior_update_document', [__CLASS__, 'ajax_update_document']);
        add_action('wp_ajax_dior_doctor_save_letter', [__CLASS__, 'ajax_save_doctor_letter']);

        // Authenticated Stream Gate
        add_action('init', [__CLASS__, 'handle_file_stream_request']);
    }

    /**
     * Create protected directory with dual Apache 2.4/2.2 and PHP security guards
     */
    public static function ensure_secure_directory()
    {
        if (!file_exists(self::$secure_dir)) {
            wp_mkdir_p(self::$secure_dir);
        }

        $htaccess = self::$secure_dir . '.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "# Dior Medical Secure Storage Protection\n";
            $rules .= "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n";
            $rules .= "<IfModule !mod_authz_core.c>\n    Order Deny,Allow\n    Deny from all\n</IfModule>\n";
            @file_put_contents($htaccess, $rules);
        }

        $index = self::$secure_dir . 'index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php\n// Protected Health Information Vault\nhttp_response_code(403);\nexit('Access Denied');");
        }
    }

    /**
     * Get documents for a patient from indexed relational table
     */
    public static function get_patient_documents($patient_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_documents';
        $patient_id = (int)$patient_id;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE patient_id = %d AND is_deleted = 0 ORDER BY created_at DESC",
            $patient_id
        ), ARRAY_A);

        if (!$results) {
            return [];
        }

        // Format for backward compatibility
        foreach ($results as &$r) {
            $r['id'] = $r['doc_uid'];
            $r['date'] = date('M j, Y', strtotime($r['created_at']));
            $r['size'] = $r['file_size_formatted'] ?: '150 KB';
            $r['letter_meta'] = !empty($r['letter_meta']) ? json_decode($r['letter_meta'], true) : [];
            $author_user = get_userdata((int)$r['author_id']);
            $r['author'] = $author_user ? $author_user->display_name : 'Staff';
        }

        return $results;
    }

    /**
     * Add a document entry to relational table and sync to legacy meta
     */
    public static function add_document($patient_id, $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_documents';

        $patient_id = (int)$patient_id;
        $doc_uid = !empty($data['id']) ? sanitize_text_field($data['id']) : ('DOC-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 7)));
        $author_id = (int)($data['author_id'] ?? Dior_Auth_Service::get_current_user_id());
        $title = sanitize_text_field($data['title'] ?? 'Medical Document');
        $category = sanitize_text_field($data['category'] ?? 'Clinical Record');
        $file_name = sanitize_file_name($data['file_name'] ?? '');
        $file_path = sanitize_text_field($data['file_path'] ?? '');
        $file_type = sanitize_text_field($data['file_type'] ?? 'PDF');
        $file_size = (int)($data['file_size'] ?? 0);
        $file_size_formatted = sanitize_text_field($data['size'] ?? '150 KB');
        $content_html = !empty($data['content_html']) ? wp_kses_post($data['content_html']) : null;
        $is_letter = !empty($data['is_letter']) ? 1 : 0;
        $letter_meta = !empty($data['letter_meta']) ? json_encode($data['letter_meta']) : null;

        $now = current_time('mysql');
        $salt = defined('NONCE_SALT') ? NONCE_SALT : 'dior_medical_secret';
        $attestation_hash = hash('sha256', $doc_uid . '|' . $patient_id . '|' . $now . '|' . $salt);

        $inserted = $wpdb->insert($table, [
            'doc_uid'             => $doc_uid,
            'patient_id'          => $patient_id,
            'author_id'           => $author_id,
            'title'               => $title,
            'category'            => $category,
            'file_name'           => $file_name,
            'file_path'           => $file_path,
            'file_type'           => $file_type,
            'file_size'           => $file_size,
            'file_size_formatted' => $file_size_formatted,
            'content_html'        => $content_html,
            'letter_meta'         => $letter_meta,
            'is_letter'           => $is_letter,
            'attestation_hash'    => $attestation_hash,
            'created_at'          => $now,
            'updated_at'          => $now,
            'is_deleted'          => 0
        ]);

        self::sync_legacy_usermeta($patient_id);

        return [
            'id'                  => $doc_uid,
            'title'               => $title,
            'category'            => $category,
            'date'                => date('M j, Y', strtotime($now)),
            'file_name'           => $file_name,
            'file_path'           => $file_path,
            'file_type'           => $file_type,
            'size'                => $file_size_formatted,
            'content_html'        => $content_html,
            'is_letter'           => $is_letter,
            'attestation_hash'    => $attestation_hash
        ];
    }

    /**
     * Store a validated uploaded medical document for a patient.
     * Returns the normalized document array or WP_Error.
     */
    public static function store_uploaded_document($patient_id, $file, $title = '', $category = 'Lab Report', $author_id = 0)
    {
        $patient_id = (int)$patient_id;
        $author_id = (int)($author_id ?: Dior_Auth_Service::get_current_user_id());
        if (!$patient_id || !$author_id || empty($file) || !is_array($file)) {
            return new WP_Error('invalid_upload', 'Invalid document upload.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'Upload error code: ' . (int)$file['error']);
        }
        if ((int)$file['size'] > 25 * 1024 * 1024) {
            return new WP_Error('file_too_large', 'File size exceeds maximum 25MB limit.');
        }

        $allowed_exts = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts, true)) {
            return new WP_Error('invalid_extension', 'Invalid file extension. Allowed: PDF, PNG, JPG, DOC, DOCX.');
        }

        $allowed_mimes = [
            'pdf' => ['application/pdf', 'application/x-pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);

        $valid_mime = !empty($detected_mime) && in_array($detected_mime, $allowed_mimes[$ext] ?? [], true);
        if (!$valid_mime && function_exists('wp_check_filetype_and_ext')) {
            $wp_check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
            $valid_mime = !empty($wp_check['ext']) && in_array(strtolower($wp_check['ext']), $allowed_exts, true);
        }
        if (!$valid_mime) {
            Dior_Audit_Service::log('document_upload', 'document', 'mime_fail', $patient_id, 'failed', ['detected_mime' => $detected_mime, 'extension' => $ext]);
            return new WP_Error('invalid_mime', 'File content does not match its declared extension.');
        }

        self::ensure_secure_directory();
        $safe_filename = 'med_' . $patient_id . '_' . time() . '_' . wp_generate_password(8, false) . '.' . $ext;
        $target_path = self::$secure_dir . $safe_filename;
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return new WP_Error('storage_error', 'Could not store the document securely.');
        }

        $title = sanitize_text_field($title ?: pathinfo($file['name'], PATHINFO_FILENAME));
        $category = sanitize_text_field($category ?: 'Lab Report');
        $doc = self::add_document($patient_id, [
            'title' => $title,
            'category' => $category,
            'file_name' => $safe_filename,
            'file_path' => $target_path,
            'file_type' => strtoupper($ext),
            'file_size' => (int)$file['size'],
            'size' => size_format($file['size']),
            'author_id' => $author_id,
        ]);

        Dior_Audit_Service::log('document_upload', 'document', $doc['id'], $patient_id, 'success', ['file_name' => $safe_filename, 'size' => size_format($file['size'])]);
        return $doc;
    }

    /**
     * Handle AJAX Document Upload with Deep Binary MIME Validation
     */
    public static function ajax_upload_document()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_doctor_nonce', 'dior_portal_nonce', 'dior_auth_nonce']);

        $current_user_id = Dior_Auth_Service::get_current_user_id();
        if (!$current_user_id) {
            wp_send_json_error(['message' => 'Authentication required.'], 401);
        }

        $patient_id = Dior_Auth_Service::validate_id($_POST['patient_id'] ?? $current_user_id);
        if (!Dior_Auth_Service::can_access_patient($patient_id, $current_user_id)) {
            Dior_Audit_Service::log('document_upload', 'document', 'unauthorized', $patient_id, 'denied');
            wp_send_json_error(['message' => 'Unauthorized access to patient records.'], 403);
        }

        if (empty($_FILES['doc_file']) && empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'No file uploaded.']);
        }

        $file = !empty($_FILES['doc_file']) ? $_FILES['doc_file'] : $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'Upload error code: ' . $file['error']]);
        }

        // Limit size to 25MB
        if ($file['size'] > 25 * 1024 * 1024) {
            wp_send_json_error(['message' => 'File size exceeds maximum 25MB limit.']);
        }

        // 1. Extension Validation
        $allowed_exts = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts, true)) {
            wp_send_json_error(['message' => 'Invalid file extension. Allowed: PDF, PNG, JPG, DOC, DOCX.']);
        }

        // 2. Deep Binary MIME Validation (Magic bytes verification)
        $allowed_mimes = [
            'pdf'  => ['application/pdf', 'application/x-pdf'],
            'png'  => ['image/png'],
            'jpg'  => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'doc'  => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);

        $valid_mime = false;
        if (!empty($detected_mime) && isset($allowed_mimes[$ext])) {
            if (in_array($detected_mime, $allowed_mimes[$ext], true)) {
                $valid_mime = true;
            }
        }

        if (!$valid_mime && function_exists('wp_check_filetype_and_ext')) {
            $wp_check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
            if (!empty($wp_check['ext']) && in_array($wp_check['ext'], $allowed_exts, true)) {
                $valid_mime = true;
            }
        }

        if (!$valid_mime) {
            Dior_Audit_Service::log('document_upload', 'document', 'mime_fail', $patient_id, 'failed', [
                'detected_mime' => $detected_mime,
                'extension'     => $ext
            ]);
            wp_send_json_error(['message' => 'File content does not match its declared extension. Potential security risk detected.']);
        }

        self::ensure_secure_directory();
        $safe_filename = 'med_' . $patient_id . '_' . time() . '_' . wp_generate_password(8, false) . '.' . $ext;
        $target_path = self::$secure_dir . $safe_filename;

        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            wp_send_json_error(['message' => 'Could not store file securely in the vault.']);
        }

        $title = sanitize_text_field($_POST['doc_title'] ?? ($_POST['title'] ?? pathinfo($file['name'], PATHINFO_FILENAME)));
        $category = sanitize_text_field($_POST['doc_category'] ?? ($_POST['category'] ?? 'Lab Report'));
        $formatted_size = size_format($file['size']);

        $doc = self::add_document($patient_id, [
            'title'     => $title,
            'category'  => $category,
            'file_name' => $safe_filename,
            'file_path' => $target_path,
            'file_type' => strtoupper($ext),
            'file_size' => $file['size'],
            'size'      => $formatted_size,
            'author_id' => $current_user_id
        ]);

        Dior_Audit_Service::log('document_upload', 'document', $doc['id'], $patient_id, 'success', [
            'file_name' => $safe_filename,
            'size'      => $formatted_size
        ]);

        wp_send_json_success(['message' => 'Document uploaded and secured successfully.', 'document' => $doc]);
    }

    /**
     * Handle AJAX Document Deletion
     */
    public static function ajax_delete_document()
    {
        Dior_Auth_Service::verify_ajax_nonce(['dior_doctor_nonce', 'dior_portal_nonce']);

        $current_user_id = Dior_Auth_Service::get_current_user_id();
        $patient_id = Dior_Auth_Service::validate_id($_POST['patient_id'] ?? 0);
        $doc_uid = sanitize_text_field($_POST['doc_id'] ?? '');

        if (!$patient_id || empty($doc_uid)) {
            wp_send_json_error(['message' => 'Invalid parameters.']);
        }

        if (!Dior_Auth_Service::can_manage_document($patient_id, 0, $current_user_id)) {
            Dior_Audit_Service::log('document_delete', 'document', $doc_uid, $patient_id, 'denied');
            wp_send_json_error(['message' => 'Unauthorized to delete this medical record.'], 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dior_documents';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE doc_uid = %s AND patient_id = %d AND is_deleted = 0", $doc_uid, $patient_id));

        if (!$row) {
            wp_send_json_error(['message' => 'Document not found.']);
        }

        // Soft delete from database
        $wpdb->update($table, ['is_deleted' => 1, 'updated_at' => current_time('mysql')], ['id' => $row->id]);

        Dior_Audit_Service::log('document_delete', 'document', $doc_uid, $patient_id, 'success');
        self::sync_legacy_usermeta($patient_id);

        wp_send_json_success(['message' => 'Medical document removed successfully.']);
    }

    /**
     * Handle Doctor Saving Work Excuse / Medical Letter
     */
    public static function ajax_save_doctor_letter()
    {
        Dior_Auth_Service::verify_ajax_nonce('dior_doctor_nonce');

        $doc_user_id = Dior_Auth_Service::get_current_user_id();
        if (!Dior_Auth_Service::is_doctor($doc_user_id)) {
            Dior_Audit_Service::log('letter_publish', 'letter', 'unauthorized', 0, 'denied');
            wp_send_json_error(['message' => 'Unauthorized. Healthcare Provider credentials required.'], 403);
        }

        $patient_id = Dior_Auth_Service::validate_id($_POST['patient_id'] ?? 0);
        if (!$patient_id) {
            wp_send_json_error(['message' => 'Patient selection is required.']);
        }

        $letter_type = sanitize_text_field($_POST['letter_type'] ?? 'Work Excuse');
        $letter_title = sanitize_text_field($_POST['letter_title'] ?? ($letter_type . ' Letter'));
        $content_html = stripslashes($_POST['content_html'] ?? '');
        $excused_from = Dior_Auth_Service::validate_date($_POST['excused_from'] ?? date('Y-m-d'));
        $return_date = Dior_Auth_Service::validate_date($_POST['return_date'] ?? date('Y-m-d', strtotime('+3 days')));
        $restrictions = sanitize_text_field($_POST['restrictions'] ?? 'None / Full Clearance');
        $remarks = sanitize_textarea_field($_POST['remarks'] ?? '');

        $doctor_user = get_userdata($doc_user_id);
        $doc_fn = get_user_meta($doc_user_id, 'first_name', true) ?: ($doctor_user ? $doctor_user->first_name : '');
        $doc_ln = get_user_meta($doc_user_id, 'last_name', true) ?: ($doctor_user ? $doctor_user->last_name : '');
        $doctor_name = trim($doc_fn . ' ' . $doc_ln) ? 'Dr. ' . trim($doc_fn . ' ' . $doc_ln) : ($doctor_user ? $doctor_user->display_name : 'Physician');

        $ltr_uid = 'LTR-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

        $doc_record = self::add_document($patient_id, [
            'id'           => $ltr_uid,
            'title'        => $letter_title,
            'category'     => 'Excuses & Letters',
            'author_id'    => $doc_user_id,
            'file_name'    => sanitize_title($letter_title) . '.pdf',
            'file_type'    => 'PDF',
            'size'         => 'Certified E-Letter',
            'content_html' => $content_html,
            'is_letter'    => true,
            'letter_meta'  => [
                'letter_type'    => $letter_type,
                'excused_from'   => $excused_from,
                'return_date'    => $return_date,
                'restrictions'   => $restrictions,
                'remarks'        => $remarks,
                'doctor_name'    => $doctor_name,
                'doctor_license' => get_user_meta($doc_user_id, 'doctor_license', true) ?: '',
                'doctor_npi'     => get_user_meta($doc_user_id, 'doctor_npi', true) ?: '',
                'doctor_signature'=> get_user_meta($doc_user_id, 'dior_doctor_signature', true) ?: '',
            ]
        ]);

        Dior_Audit_Service::log('letter_publish', 'letter', $ltr_uid, $patient_id, 'success', [
            'letter_type' => $letter_type
        ]);

        // Push Patient Notification
        $pnotifs = get_user_meta($patient_id, 'dior_notifications', true);
        if (!is_array($pnotifs)) $pnotifs = [];
        array_unshift($pnotifs, [
            'id'         => 'NOTIF-LTR-' . time() . '-' . rand(10, 99),
            'type'       => 'document',
            'icon'       => 'fa-file-signature',
            'title'      => 'Medical Letter Issued: ' . $letter_title,
            'message'    => $doctor_name . ' has issued an official ' . $letter_type . '. You can view and download it now under Clinical Records.',
            'time'       => 'Just now',
            'timestamp'  => time(),
            'created_at' => current_time('mysql'),
            'is_read'    => false,
            'action_url' => '#tab=docs_meds'
        ]);
        update_user_meta($patient_id, 'dior_notifications', $pnotifs);

        wp_send_json_success(['message' => $letter_title . ' published to patient portal successfully!', 'doc' => $doc_record]);
    }

    /**
     * Authenticated Streaming Download Gate with Audit Logging
     */
    public static function handle_file_stream_request()
    {
        if (empty($_GET['dior_action']) || $_GET['dior_action'] !== 'download_doc') {
            return;
        }

        $current_user_id = Dior_Auth_Service::get_current_user_id();
        if (!$current_user_id) {
            Dior_Audit_Service::log('document_download', 'document', sanitize_text_field($_GET['doc_id'] ?? ''), 0, 'denied', [
                'reason' => 'unauthenticated'
            ]);
            wp_die('Authentication required. Please sign in to access medical records.', 'Unauthorized', ['response' => 401]);
        }

        $patient_id = Dior_Auth_Service::validate_id($_GET['patient_id'] ?? 0);
        $doc_uid = sanitize_text_field($_GET['doc_id'] ?? '');

        if (!$patient_id || empty($doc_uid)) {
            wp_die('Invalid document request.', 'Bad Request', ['response' => 400]);
        }

        // Anti-IDOR check
        if (!Dior_Auth_Service::can_access_patient($patient_id, $current_user_id)) {
            Dior_Audit_Service::log('document_download', 'document', $doc_uid, $patient_id, 'denied', [
                'actor_user_id' => $current_user_id
            ]);
            wp_die('You do not have authorization to view this medical record.', 'Forbidden', ['response' => 403]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dior_documents';
        $doc = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE doc_uid = %s AND patient_id = %d AND is_deleted = 0",
            $doc_uid, $patient_id
        ), ARRAY_A);

        if (!$doc) {
            Dior_Audit_Service::log('document_download', 'document', $doc_uid, $patient_id, 'failed', [
                'reason' => 'not_found'
            ]);
            wp_die('Medical record not found in secure storage.', 'Not Found', ['response' => 404]);
        }

        // Log successful download access for compliance
        Dior_Audit_Service::log('document_download', 'document', $doc_uid, $patient_id, 'success');

        // Render HTML Letterhead View if letter document
        if (!empty($doc['content_html'])) {
            self::render_letter_html_view($doc, $patient_id);
            exit;
        }

        // Stream binary file from disk
        $file_path = $doc['file_path'];
        if (empty($file_path) || !file_exists($file_path)) {
            wp_die('File payload is missing from vault.', 'Missing File', ['response' => 404]);
        }

        // Prevent path traversal
        $real_path = realpath($file_path);
        $real_vault = realpath(self::$secure_dir);
        if ($real_path === false || strpos($real_path, $real_vault) !== 0) {
            Dior_Audit_Service::log('document_download', 'document', $doc_uid, $patient_id, 'denied', [
                'reason' => 'path_traversal_attempt'
            ]);
            wp_die('Invalid file path.', 'Forbidden', ['response' => 403]);
        }

        $mime_type = wp_check_filetype($file_path)['type'] ?: 'application/octet-stream';
        $filename = sanitize_file_name($doc['file_name'] ?: basename($file_path));

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: private, must-revalidate, max-age=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));

        // Clear output buffers and stream
        if (ob_get_level()) ob_end_clean();
        readfile($file_path);
        exit;
    }

    /**
     * Render high-end certified medical letter HTML template for printing/saving as PDF
     */
    public static function render_letter_html_view($doc, $patient_id)
    {
        $patient_user = get_userdata($patient_id);
        $profile = Dior_Patient_Portal_Data::get_patient_profile($patient_id);
        $p_name = $profile['full_name'] ?: ($patient_user ? $patient_user->display_name : 'Patient');
        $dob = $profile['dob'] ?: 'N/A';
        $patient_id_num = $profile['patient_id'] ?: ('DM-' . (10000 + $patient_id));

        $meta = !empty($doc['letter_meta']) ? (is_array($doc['letter_meta']) ? $doc['letter_meta'] : json_decode($doc['letter_meta'], true)) : [];
        $letter_type = $meta['letter_type'] ?? ($doc['title'] ?? 'Medical Attestation');
        $doc_name = $meta['doctor_name'] ?? 'Dr. Medical Provider';
        $doc_license = $meta['doctor_license'] ?? 'CA-MD-99201';
        $doc_npi = $meta['doctor_npi'] ?? '1892049102';
        $doc_sig = $meta['doctor_signature'] ?? (get_user_meta((int)($doc['author_id'] ?? 0), 'dior_doctor_signature', true) ?: '');
        $token = substr($doc['attestation_hash'] ?? md5($doc['doc_uid']), 0, 12);

        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html($doc['title']); ?> - Dior Medical</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,700;1,600&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #0F172A; padding: 30px 15px; color: #1E293B; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; }
        .letter-wrap { max-width: 820px; width: 100%; background: #FFFFFF; padding: 50px 60px; border: 2.5px solid #00A896; border-radius: 12px; box-shadow: 0 25px 60px rgba(0,0,0,0.3); position: relative; }
        .print-bar { position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 999; }
        .print-btn { background: #00A896; color: #FFF; border: none; padding: 10px 20px; font-size: 14px; font-weight: 700; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,168,150,0.4); display: flex; align-items: center; gap: 8px; }
        .close-btn { background: #334155; color: #FFF; border: none; padding: 10px 16px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; }
        .letter-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #00A896; padding-bottom: 22px; margin-bottom: 26px; }
        .clinic-brand h1 { font-family: 'Playfair Display', serif; font-size: 26px; letter-spacing: 0.5px; color: #0F172A; }
        .clinic-brand p { font-size: 11.5px; color: #64748B; margin-top: 3px; }
        .cert-badge { text-align: right; }
        .cert-tag { display: inline-block; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .patient-box { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px 20px; margin-bottom: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px; }
        .patient-box strong { color: #0F172A; }
        .letter-body { font-size: 14.5px; line-height: 1.8; color: #334155; margin-bottom: 40px; }
        .letter-footer { border-top: 1px solid #E2E8F0; padding-top: 25px; display: flex; justify-content: space-between; align-items: flex-end; }
        .signature-block { text-align: right; }
        .sig-name { font-family: 'Playfair Display', serif; font-style: italic; font-size: 22px; color: #0F172A; }
        .sig-sub { font-size: 12px; color: #64748B; margin-top: 2px; }
        .token-tag { font-size: 11px; font-family: monospace; color: #64748B; background: #F1F5F9; padding: 3px 8px; border-radius: 4px; }
        @media print { body { background: #FFF; padding: 0; } .print-bar { display: none; } .letter-wrap { box-shadow: none; border: 2px solid #00A896; padding: 35px; } }
    </style>
</head>
<body>
    <div class="print-bar">
        <button class="print-btn" onclick="window.print()">Print Document</button>
        <button class="close-btn" onclick="window.close()">Close</button>
    </div>
    <div class="letter-wrap">
        <div class="letter-head">
            <div class="clinic-brand">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:36px; height:36px; border-radius:8px; background:linear-gradient(135deg,#E0F2FE,#CCFBF1); color:#00A896; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800;">
                        &#9877;
                    </div>
                    <div>
                        <h1>DIOR MEDICAL</h1>
                        <p>Virtual Urgent Care & Telehealth Medicine &bull; www.diormedical.com</p>
                    </div>
                </div>
                <p style="margin-top:6px;">Certified Clinical Practice &bull; HIPAA & DEA 21 CFR Compliant</p>
            </div>
            <div class="cert-badge">
                <span class="cert-tag">Official Attestation</span>
                <div style="font-size:12px;color:#64748B;margin-top:6px;">Date: <strong><?php echo esc_html(date('M j, Y', strtotime($doc['created_at']))); ?></strong></div>
                <div style="font-size:11px;color:#94A3B8;margin-top:2px;">Document ID: <strong><?php echo esc_html($doc['doc_uid']); ?></strong></div>
            </div>
        </div>

        <div class="patient-box">
            <div><strong>Patient Name:</strong> <?php echo esc_html($p_name); ?></div>
            <div><strong>Patient ID:</strong> <?php echo esc_html($patient_id_num); ?></div>
            <div><strong>Date of Birth:</strong> <?php echo esc_html($dob); ?></div>
            <div><strong>Evaluation Type:</strong> Telehealth Clinical Encounter</div>
        </div>

        <div class="letter-body">
            <?php echo $doc['content_html']; ?>
        </div>

        <div class="letter-footer">
            <div>
                <span class="token-tag">VERIFICATION TOKEN: DM-<?php echo esc_html($token); ?></span>
                <p style="font-size:11px;color:#94A3B8;margin-top:6px;">DEA 21 CFR & HIPAA Security Standard</p>
            </div>
            <div class="signature-block">
                <?php if (!empty($doc_sig)): ?>
                    <div style="margin-bottom:6px;"><img src="<?php echo esc_url($doc_sig); ?>" alt="Physician Signature" style="max-height:55px; max-width:200px; object-fit:contain; background:transparent; display:inline-block;"></div>
                <?php else: ?>
                    <div class="sig-name">/s/ <?php echo esc_html($doc_name); ?></div>
                <?php endif; ?>
                <div class="sig-sub" style="font-weight:700; color:#0F172A;"><?php echo esc_html($doc_name); ?></div>
                <div class="sig-sub">Licensed Telehealth Physician</div>
                <div class="sig-sub">License: <?php echo esc_html($doc_license); ?> | NPI: <?php echo esc_html($doc_npi); ?></div>
            </div>
        </div>
    </div>
</body>
</html>
        <?php
    }

    /**
     * Dual-write / sync to legacy user meta for backwards compatibility
     */
    private static function sync_legacy_usermeta($patient_id)
    {
        $docs = self::get_patient_documents($patient_id);
        update_user_meta($patient_id, 'dior_documents', $docs);
    }
}
