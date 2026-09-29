<?php
defined('ABSPATH') || exit;

// Dashboard-native analytics. Data is scoped to the currently logged-in doctor.
$analytics_user_id = get_current_user_id();
$analytics_appointments = class_exists('Dior_Appointment_Service')
    ? Dior_Appointment_Service::get_doctor_appointments($analytics_user_id)
    : [];

$analytics_today = current_time('Y-m-d');
$analytics_stats = [
    'total' => count($analytics_appointments),
    'today' => 0,
    'upcoming' => 0,
    'completed' => 0,
];
foreach ($analytics_appointments as $analytics_appt) {
    $status = strtolower((string)($analytics_appt['status'] ?? ''));
    $date = (string)($analytics_appt['appt_date'] ?? ($analytics_appt['date'] ?? ''));
    if ($date === $analytics_today) {
        $analytics_stats['today']++;
    }
    if (in_array($status, ['pending', 'confirmed', 'scheduled', 'in-queue', 'in progress'], true) && $date >= $analytics_today) {
        $analytics_stats['upcoming']++;
    }
    if ($status === 'completed') {
        $analytics_stats['completed']++;
    }
}
?>
<section class="dior-tab-panel dior-source-group" id="tab-doc-analytics">
    <div class="dior-content-pad">
        <div class="dior-source-group-head">
            <div>
                <div class="dior-source-kicker"><i class="fa-solid fa-chart-line"></i> Doctor Workspace</div>
                <h2>Analytics</h2>
                <p>Live appointment activity for your provider account.</p>
            </div>
        </div>

        <div class="dior-ref-stats">
            <div class="dior-ref-stat"><div class="dior-ref-stat-icon"><i class="fa-solid fa-calendar-check"></i></div><div><span>Total Appointments</span><strong><?php echo esc_html($analytics_stats['total']); ?></strong></div></div>
            <div class="dior-ref-stat"><div class="dior-ref-stat-icon"><i class="fa-solid fa-calendar-day"></i></div><div><span>Today</span><strong><?php echo esc_html($analytics_stats['today']); ?></strong></div></div>
            <div class="dior-ref-stat"><div class="dior-ref-stat-icon"><i class="fa-solid fa-clock"></i></div><div><span>Upcoming</span><strong><?php echo esc_html($analytics_stats['upcoming']); ?></strong></div></div>
            <div class="dior-ref-stat"><div class="dior-ref-stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><span>Completed</span><strong><?php echo esc_html($analytics_stats['completed']); ?></strong></div></div>
        </div>

        <div class="dior-ref-card">
            <div class="dior-ref-card-head"><div><h4>Recent Appointment Activity</h4><p>Latest appointments assigned to this provider.</p></div></div>
            <div class="dior-ref-table-wrap">
                <table class="dior-ref-table">
                    <thead><tr><th>Patient</th><th>Date</th><th>Time</th><th>Reason</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($analytics_appointments, 0, 10) as $analytics_appt): ?>
                        <tr>
                            <td><?php echo esc_html($analytics_appt['patient_name'] ?? 'Patient'); ?></td>
                            <td><?php echo esc_html($analytics_appt['appt_date'] ?? ($analytics_appt['date'] ?? '—')); ?></td>
                            <td><?php echo esc_html($analytics_appt['appt_time'] ?? ($analytics_appt['time'] ?? '—')); ?></td>
                            <td><?php echo esc_html($analytics_appt['condition_name'] ?? ($analytics_appt['condition'] ?? 'Consultation')); ?></td>
                            <td><span class="dior-ref-badge success"><?php echo esc_html($analytics_appt['status'] ?? 'Confirmed'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($analytics_appointments)): ?>
                        <tr><td colspan="5">No appointment activity yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
