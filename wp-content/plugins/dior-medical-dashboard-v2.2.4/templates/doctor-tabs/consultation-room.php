<?php
defined('ABSPATH') || exit;

// Legacy compatibility template. The active dashboard uses consultation.php.
if (file_exists(DIOR_PORTAL_PATH . 'templates/doctor-tabs/consultation.php')) {
    include DIOR_PORTAL_PATH . 'templates/doctor-tabs/consultation.php';
}
