<?php
/**
 * Event-specific Dior Medical email template: Appointment reminder.
 * The notification service prepares escaped event content and includes this view.
 */
if (!defined('ABSPATH')) { exit; }
$email_event_label = 'Appointment reminder';
include __DIR__ . '/layout.php';
