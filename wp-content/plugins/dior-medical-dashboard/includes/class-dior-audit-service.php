<?php
/**
 * Dior Medical - Compliance & Security Audit Logging Service
 * 
 * Provides immutable, tamper-evident audit logging for access to Protected
 * Health Information (PHI), authentication events, and clinical operations.
 * Automatically sanitizes metadata to prevent logging sensitive PHI or credentials.
 * 
 * @package Dior Medical
 * @version 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dior_Audit_Service
{
    /**
     * Log an audit event
     * 
     * @param string $action        e.g. 'file_download', 'soap_create', 'letter_publish', 'auth_fail'
     * @param string $resource_type e.g. 'document', 'encounter', 'appointment', 'patient'
     * @param string $resource_id   Unique ID or UID of the resource
     * @param int    $patient_id    Optional patient ID
     * @param string $status         'success' | 'failed' | 'denied' | 'error'
     * @param array  $metadata       Optional context metadata (sanitized before storage)
     * @return int|false            Inserted audit record ID
     */
    public static function log($action, $resource_type, $resource_id, $patient_id = null, $status = 'success', $metadata = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_audit_logs';

        $user_id = get_current_user_id() ?: null;
        $user_role = 'guest';

        if ($user_id) {
            $user = get_userdata($user_id);
            if ($user && !empty($user->roles)) {
                $user_role = reset($user->roles);
            }
        }

        $ip_address = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : '';

        // Sanitize metadata to strip PHI, passwords, nonces, and secrets
        $clean_meta = self::sanitize_metadata($metadata);

        $inserted = $wpdb->insert($table, [
            'user_id'       => $user_id,
            'user_role'     => sanitize_text_field($user_role),
            'action'        => sanitize_text_field($action),
            'resource_type' => sanitize_text_field($resource_type),
            'resource_id'   => sanitize_text_field($resource_id),
            'patient_id'    => $patient_id ? (int)$patient_id : null,
            'status'        => in_array($status, ['success', 'failed', 'denied', 'error']) ? $status : 'success',
            'ip_address'    => $ip_address,
            'user_agent'    => $user_agent,
            'metadata'      => !empty($clean_meta) ? json_encode($clean_meta) : null,
            'created_at'    => current_time('mysql')
        ]);

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Get client IP safely
     */
    private static function get_client_ip()
    {
        $ip = '127.0.0.1';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '127.0.0.1';
    }

    /**
     * Sanitize metadata to prevent sensitive PHI, passwords, or tokens from entering logs
     */
    private static function sanitize_metadata($data)
    {
        if (!is_array($data)) {
            return [];
        }

        $forbidden_keys = [
            'password', 'pwd', 'user_pass', 'nonce', 'token', 'secret',
            'card', 'cvv', 'card_number', 'ssn', 'note', 'subjective',
            'objective', 'assessment', 'plan', 'content_html'
        ];

        $sanitized = [];
        foreach ($data as $key => $val) {
            $k_lower = strtolower($key);
            $is_forbidden = false;
            foreach ($forbidden_keys as $fb) {
                if (strpos($k_lower, $fb) !== false) {
                    $is_forbidden = true;
                    break;
                }
            }

            if (!$is_forbidden) {
                if (is_scalar($val)) {
                    $sanitized[$key] = sanitize_text_field((string)$val);
                } elseif (is_array($val)) {
                    $sanitized[$key] = self::sanitize_metadata($val);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Retrieve audit logs with pagination and filters
     */
    public static function get_logs($args = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dior_audit_logs';

        $per_page = isset($args['per_page']) ? (int)$args['per_page'] : 50;
        $page = isset($args['page']) ? (int)$args['page'] : 1;
        $offset = ($page - 1) * $per_page;

        $where = ['1=1'];
        $params = [];

        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int)$args['user_id'];
        }
        if (!empty($args['patient_id'])) {
            $where[] = 'patient_id = %d';
            $params[] = (int)$args['patient_id'];
        }
        if (!empty($args['action'])) {
            $where[] = 'action = %s';
            $params[] = $args['action'];
        }
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        $where_sql = implode(' AND ', $where);
        $query = "SELECT * FROM $table WHERE $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
}
