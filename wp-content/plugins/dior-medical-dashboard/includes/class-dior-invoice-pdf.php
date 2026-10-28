<?php
/**
 * Dior Medical invoice PDF route and data resolver.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dior_Invoice_Pdf_Service
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'handle_request']);
    }

    public static function handle_request()
    {
        $action = isset($_GET['dior_action']) ? sanitize_key(wp_unslash($_GET['dior_action'])) : '';
        if (!in_array($action, ['view_invoice_pdf', 'download_invoice_pdf'], true)) {
            return;
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            wp_die('Authentication required. Please sign in to view or download invoices.', 'Unauthorized', ['response' => 401]);
        }

        $invoice_id = isset($_GET['inv_id']) ? sanitize_text_field(wp_unslash($_GET['inv_id'])) : '';
        if ($invoice_id === '') {
            wp_die('Invoice identifier is required.', 'Bad Request', ['response' => 400]);
        }

        global $wpdb;
        $table_invoices = $wpdb->prefix . 'dior_invoices';
        $invoice = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_invoices} WHERE invoice_uid = %s OR id = %d LIMIT 1",
                $invoice_id,
                absint($invoice_id)
            ),
            ARRAY_A
        );

        if (!$invoice) {
            wp_die('Invoice record not found. Please open the invoice from the billing records and try again.', 'Not Found', ['response' => 404]);
        }

        $patient_id = absint($invoice['patient_id'] ?? 0);
        $doctor_id = absint($invoice['doctor_id'] ?? 0);
        $is_authorized = current_user_can('manage_options')
            || $current_user_id === $patient_id
            || ($doctor_id > 0 && $current_user_id === $doctor_id);

        if (!$is_authorized && class_exists('Dior_Auth_Service') && method_exists('Dior_Auth_Service', 'can_access_patient')) {
            $is_authorized = (bool) Dior_Auth_Service::can_access_patient($patient_id, $current_user_id);
        }

        if (!$is_authorized) {
            wp_die('You do not have authorization to access this invoice.', 'Forbidden', ['response' => 403]);
        }

        $patient_user = $patient_id ? get_userdata($patient_id) : false;
        $patient_profile = class_exists('Dior_Patient_Portal_Data') && $patient_id
            ? (array) Dior_Patient_Portal_Data::get_patient_profile($patient_id)
            : [];

        $patient_row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}dior_patients WHERE user_id = %d LIMIT 1", $patient_id),
            ARRAY_A
        );
        $patient_row = is_array($patient_row) ? $patient_row : [];
        $patient_name = trim((string) ($patient_profile['full_name'] ?? ''));
        if ($patient_name === '') {
            $patient_name = trim((string) (($patient_row['first_name'] ?? '') . ' ' . ($patient_row['last_name'] ?? '')));
        }
        if ($patient_name === '') {
            $patient_name = $patient_user ? $patient_user->display_name : 'Patient';
        }

        $patient_email = (string) ($patient_profile['email'] ?? ($patient_row['email'] ?? ($patient_user ? $patient_user->user_email : '')));
        $patient_phone = (string) ($patient_profile['phone'] ?? ($patient_row['phone'] ?? ''));
        $patient_address = (string) ($patient_profile['address'] ?? ($patient_row['address'] ?? ''));
        $patient_city = (string) ($patient_profile['city'] ?? ($patient_row['city'] ?? ''));
        $patient_state = (string) ($patient_profile['state'] ?? ($patient_row['state'] ?? ''));
        $patient_country = (string) ($patient_profile['country'] ?? ($patient_row['country'] ?? ''));
        $patient_address = trim(implode(', ', array_filter([$patient_address, $patient_city, $patient_state, $patient_country])));

        $doctor_user = $doctor_id ? get_userdata($doctor_id) : get_userdata($current_user_id);
        $doctor_name = $doctor_user ? $doctor_user->display_name : get_bloginfo('name');
        $doctor_email = $doctor_user ? $doctor_user->user_email : '';
        $doctor_phone = $doctor_user ? (string) get_user_meta($doctor_user->ID, 'phone', true) : '';

        $amount = (float) ($invoice['amount'] ?? 0);
        $currency = strtoupper(sanitize_text_field($invoice['currency'] ?? 'USD'));
        $issued_raw = $invoice['issued_at'] ?: ($invoice['created_at'] ?? current_time('mysql'));
        $due_raw = $invoice['due_at'] ?? '';
        $payment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dior_payments WHERE invoice_uid = %s ORDER BY paid_at DESC, created_at DESC LIMIT 1",
                (string) ($invoice['invoice_uid'] ?? '')
            ),
            ARRAY_A
        );
        $payment = is_array($payment) ? $payment : [];

        $logo_url = '';
        $custom_logo_id = (int) get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = (string) wp_get_attachment_image_url($custom_logo_id, 'full');
        }
        if ($logo_url === '') {
            $logo_url = (string) get_option('dior_medical_logo_url', '');
        }
        if ($logo_url === '') {
            $logo_attachments = get_posts([
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => 1,
                'meta_query' => [[
                    'key' => '_wp_attached_file',
                    'value' => '/logo\\.png$/',
                    'compare' => 'REGEXP',
                ]],
                'fields' => 'ids',
            ]);
            if (!empty($logo_attachments[0])) {
                $logo_url = (string) wp_get_attachment_image_url((int) $logo_attachments[0], 'full');
            }
        }

        $site_name = get_bloginfo('name') ?: 'Dior Medical';
        $site_address = (string) get_option('dior_medical_address', '');
        $site_phone = (string) get_option('dior_medical_phone', '');
        $site_email = (string) get_option('admin_email');
        $site_url = home_url('/');

        $invoice_pdf = [
            'id' => (string) ($invoice['invoice_uid'] ?: $invoice['id']),
            'status' => (string) ($invoice['status'] ?? 'Pending'),
            'currency' => $currency,
            'amount' => $amount,
            'subtotal' => $amount,
            'discount' => 0,
            'tax' => 0,
            'total' => $amount,
            'description' => (string) ($invoice['description'] ?? 'Medical services'),
            'issued_at' => $issued_raw,
            'due_at' => $due_raw,
            'appointment_uid' => (string) ($invoice['appointment_uid'] ?? ''),
            'patient' => [
                'id' => (string) ($patient_row['patient_uid'] ?? $patient_id),
                'name' => $patient_name,
                'email' => $patient_email,
                'phone' => $patient_phone,
                'dob' => (string) ($patient_profile['dob'] ?? ($patient_row['dob'] ?? '')),
                'gender' => (string) ($patient_profile['gender'] ?? ($patient_row['gender'] ?? '')),
                'address' => $patient_address,
            ],
            'doctor' => [
                'name' => $doctor_name,
                'email' => $doctor_email,
                'phone' => $doctor_phone,
            ],
            'payment' => [
                'method' => (string) ($payment['payment_method'] ?? ''),
                'transaction_id' => (string) ($payment['transaction_id'] ?? ''),
                'paid_at' => (string) ($payment['paid_at'] ?? ''),
                'amount' => (float) ($payment['amount'] ?? 0),
            ],
            'company' => [
                'name' => $site_name,
                'address' => $site_address,
                'phone' => $site_phone,
                'email' => $site_email,
                'url' => $site_url,
                'logo' => $logo_url,
            ],
        ];

        $auto_download = $action === 'download_invoice_pdf';
        $template_path = DIOR_PORTAL_PATH . 'templates/pdf/invoice.php';
        if (is_readable($template_path)) {
            include $template_path;
            exit;
        }

        wp_die('Invoice PDF template file is missing.', 'Error', ['response' => 500]);
    }
}

Dior_Invoice_Pdf_Service::init();
