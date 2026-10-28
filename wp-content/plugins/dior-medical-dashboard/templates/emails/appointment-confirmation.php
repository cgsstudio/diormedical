<?php
/**
 * Event-specific Dior Medical email template: Appointment confirmation.
 * The notification service prepares escaped event content and includes this view.
 */
if (!defined('ABSPATH')) { exit; }
$email_event_label = 'Appointment confirmation';
include __DIR__ . '/layout.php';
