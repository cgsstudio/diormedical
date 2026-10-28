<?php
/**
 * Event-specific Dior Medical email template: Missed appointment.
 * The notification service prepares escaped event content and includes this view.
 */
if (!defined('ABSPATH')) { exit; }
$email_event_label = 'Missed appointment';
include __DIR__ . '/layout.php';
