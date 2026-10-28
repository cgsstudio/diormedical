<?php
/**
 * Dior Medical - Invoice PDF Generation & Download Service
 * Implements high-fidelity HTML-to-PDF invoice export matching the exact Dior Medical design.
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

    /**
     * Handle direct requests: ?dior_action=view_invoice_pdf&inv_id=...
     */
    public static function handle_request()
    {
        if (empty($_GET['dior_action']) || !in_array($_GET['dior_action'], ['view_invoice_pdf', 'download_invoice_pdf'], true)) {
            return;
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            wp_die('Authentication required. Please sign in to view or download invoices.', 'Unauthorized', ['response' => 401]);
        }

        $inv_id = sanitize_text_field($_GET['inv_id'] ?? 'INV-2026-001');

        // Security check: patients can only access their own records unless admin/doctor
        $patient_id = !empty($_GET['patient_id']) ? (int) $_GET['patient_id'] : $current_user_id;
        if ($patient_id !== $current_user_id && !current_user_can('manage_options')) {
            $is_doctor = false;
            if (class_exists('Dior_Auth_Service') && method_exists('Dior_Auth_Service', 'can_access_patient')) {
                $is_doctor = Dior_Auth_Service::can_access_patient($patient_id, $current_user_id);
            }
            if (!$is_doctor && !current_user_can('edit_posts')) {
                wp_die('You do not have authorization to access this invoice.', 'Forbidden', ['response' => 403]);
            }
        }

        // Render template
        $template_path = DIOR_PORTAL_PATH . 'templates/dior-invoice-pdf-template.php';
        if (file_exists($template_path)) {
            include $template_path;
            exit;
        }

        wp_die('Invoice template file is missing.', 'Error', ['response' => 500]);
    }
}

Dior_Invoice_Pdf_Service::init();

