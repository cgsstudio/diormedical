<?php
/**
 * Event-specific Dior Medical email template: Payment receipt.
 * The notification service prepares escaped event content and includes this view.
 */
if (!defined('ABSPATH')) { exit; }
$email_event_label = 'Payment receipt';
include __DIR__ . '/layout.php';
