<?php
/**
 * Independent mobile-only Patient Dashboard overview.
 * Reuses the existing server-side patient data; does not alter desktop markup.
 */
$mobile_first_name = !empty($profile['first_name']) ? $profile['first_name'] : (!empty($profile['full_name']) ? strtok($profile['full_name'], ' ') : 'Patient');
$mobile_avatar = !empty($profile['avatar_url']) ? $profile['avatar_url'] : get_avatar_url($user_id, ['size' => 160]);
$mobile_next_name = !empty($next_appointment['doctor_name']) ? $next_appointment['doctor_name'] : (!empty($next_appointment['provider']) ? $next_appointment['provider'] : 'Your care team');
$mobile_next_date = !empty($next_appointment['date']) ? $next_appointment['date'] : 'Date to be confirmed';
$mobile_next_time = !empty($next_appointment['time']) ? $next_appointment['time'] : 'Time to be confirmed';
?>
<div class="dior-patient-overview-mobile" aria-label="Patient dashboard mobile overview">
  <header class="dior-mob-welcome">
    <div class="dior-mob-welcome-copy">
      <span class="dior-mob-eyebrow">PATIENT PORTAL</span>
      <h1>Hello, <?php echo esc_html($mobile_first_name); ?></h1>
      <p>Your health journey, all in one place.</p>
    </div>
    <img class="dior-mob-avatar" src="<?php echo esc_url($mobile_avatar); ?>" alt="<?php echo esc_attr($profile['full_name'] ?? 'Patient'); ?>">
  </header>

  <a class="dior-mob-primary-action" href="<?php echo esc_url(home_url('/patient-dashboard/#tab=appointments&subtab=book')); ?>">
    <span class="dior-mob-action-icon"><i class="fas fa-calendar-plus" aria-hidden="true"></i></span>
    <span><strong>Book an appointment</strong><small>Schedule your next consultation</small></span>
    <i class="fas fa-arrow-right" aria-hidden="true"></i>
  </a>

  <div class="row g-2 dior-mob-stats" aria-label="Patient statistics">
    <div class="col-4"><div class="dior-mob-stat"><span class="dior-mob-stat-icon"><i class="fas fa-stethoscope"></i></span><strong><?php echo esc_html(count($appointments)); ?></strong><small>Visits</small></div></div>
    <div class="col-4"><div class="dior-mob-stat"><span class="dior-mob-stat-icon"><i class="fas fa-prescription-bottle-medical"></i></span><strong><?php echo esc_html(count($prescriptions)); ?></strong><small>Prescriptions</small></div></div>
    <div class="col-4"><div class="dior-mob-stat"><span class="dior-mob-stat-icon"><i class="fas fa-calendar-check"></i></span><strong><?php echo esc_html(count($upcoming_list)); ?></strong><small>Upcoming</small></div></div>
  </div>

  <section class="dior-mob-section">
    <div class="dior-mob-section-heading"><div><span class="dior-mob-eyebrow">YOUR NEXT VISIT</span><h2>Upcoming appointment</h2></div><span class="dior-mob-heading-icon"><i class="far fa-calendar-check"></i></span></div>
    <div class="dior-mob-next-card">
      <?php if (!empty($next_appointment)): ?>
        <div class="dior-mob-provider">
          <img src="<?php echo esc_url($ha_avatar ?: $mobile_avatar); ?>" alt="<?php echo esc_attr($mobile_next_name); ?>" onerror="this.onerror=null;this.src='<?php echo esc_url($mobile_avatar); ?>';">
          <div><strong><?php echo esc_html($mobile_next_name); ?></strong><small><?php echo esc_html($ha_spec ?? 'Telehealth consultation'); ?></small></div>
        </div>
        <div class="dior-mob-visit-meta"><span><i class="far fa-calendar"></i><?php echo esc_html($mobile_next_date); ?></span><span><i class="far fa-clock"></i><?php echo esc_html($mobile_next_time); ?></span></div>
        <button type="button" class="dior-mob-outline-action" data-dior-mobile-tab="consultation">Open consultation room <i class="fas fa-arrow-right"></i></button>
      <?php else: ?>
        <div class="dior-mob-empty"><span><i class="far fa-calendar-xmark"></i></span><strong>No upcoming appointment</strong><small>Book a visit with your care team when you are ready.</small></div>
        <a class="dior-mob-outline-action" href="<?php echo esc_url(home_url('/patient-dashboard/#tab=appointments&subtab=book')); ?>">Book an appointment <i class="fas fa-arrow-right"></i></a>
      <?php endif; ?>
    </div>
  </section>

  <section class="dior-mob-section">
    <div class="dior-mob-section-heading"><div><span class="dior-mob-eyebrow">PATIENT DETAILS</span><h2>Health snapshot</h2></div><button type="button" class="dior-mob-text-action" data-dior-mobile-tab="settings">Edit profile</button></div>
    <div class="dior-mob-health-card">
      <div class="dior-mob-patient-line"><img src="<?php echo esc_url($mobile_avatar); ?>" alt=""><div><strong><?php echo esc_html($profile['full_name'] ?? 'Patient'); ?></strong><small><?php echo esc_html($patient_identifier ?? ''); ?><?php echo !empty($age_str) ? ' · ' . esc_html($age_str) : ''; ?></small></div></div>
      <div class="row g-0 dior-mob-vitals">
        <div class="col-6"><span>Height</span><strong><?php echo esc_html($h_display ?? '—'); ?></strong></div>
        <div class="col-6"><span>Weight</span><strong><?php echo esc_html($w_display ?? '—'); ?></strong></div>
      </div>
    </div>
  </section>

  <section class="dior-mob-section">
    <div class="dior-mob-section-heading"><div><span class="dior-mob-eyebrow">YOUR CARE PLAN</span><h2>Health goals</h2></div><button type="button" class="dior-mob-icon-action" data-bs-toggle="modal" data-bs-target="#diorHealthGoalsModal" aria-label="Manage health goals"><i class="fas fa-plus"></i></button></div>
    <div class="dior-mob-list-card">
      <?php if (empty($display_goals)): ?>
        <div class="dior-mob-empty"><span><i class="fas fa-bullseye"></i></span><strong>No health goals yet</strong><small>Add a goal to start tracking your progress.</small></div>
      <?php else: foreach (array_slice($display_goals, 0, 4) as $mobile_goal): $mobile_pct = min(100, max(0, (int)($mobile_goal['pct'] ?? 0))); ?>
        <div class="dior-mob-goal"><div><strong><?php echo esc_html($mobile_goal['name'] ?? 'Health goal'); ?></strong><span><?php echo esc_html($mobile_goal['val'] ?? ''); ?></span></div><div class="dior-mob-progress"><span style="width:<?php echo esc_attr($mobile_pct); ?>%"></span></div></div>
      <?php endforeach; endif; ?>
      <button type="button" class="dior-mob-list-link" data-bs-toggle="modal" data-bs-target="#diorHealthGoalsModal">Manage health goals <i class="fas fa-arrow-right"></i></button>
    </div>
  </section>

  <section class="dior-mob-section">
    <div class="dior-mob-section-heading"><div><span class="dior-mob-eyebrow">CARE SCHEDULE</span><h2>Upcoming consultations</h2></div><button type="button" class="dior-mob-text-action" data-dior-mobile-tab="appointments">View all</button></div>
    <div class="dior-mob-list-card">
      <?php if (empty($display_list)): ?>
        <div class="dior-mob-empty"><span><i class="far fa-calendar"></i></span><strong>No consultations scheduled</strong><small>Your upcoming visits will appear here.</small></div>
      <?php else: foreach (array_slice($display_list, 0, 3) as $mobile_apt): ?>
        <div class="dior-mob-appointment"><span class="dior-mob-appointment-icon"><i class="fas fa-user-doctor"></i></span><div><strong><?php echo esc_html($mobile_apt['doctor_name'] ?? $mobile_apt['provider'] ?? 'Provider'); ?></strong><small><?php echo esc_html($mobile_apt['provider_spec'] ?? 'Medical consultation'); ?></small><small><i class="far fa-calendar"></i> <?php echo esc_html($mobile_apt['date'] ?? '—'); ?> &nbsp; <i class="far fa-clock"></i> <?php echo esc_html($mobile_apt['time'] ?? '—'); ?></small></div></div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <section class="dior-mob-section">
    <div class="dior-mob-section-heading"><div><span class="dior-mob-eyebrow">MEDICAL HISTORY</span><h2>Previous consultations</h2></div><button type="button" class="dior-mob-text-action" data-dior-mobile-tab="appointments">View all</button></div>
    <div class="dior-mob-list-card">
      <?php if (empty($prev_apts)): ?>
        <div class="dior-mob-empty"><span><i class="fas fa-clock-rotate-left"></i></span><strong>No previous consultations</strong><small>Completed visits will appear here.</small></div>
      <?php else: foreach (array_slice($prev_apts, 0, 3) as $mobile_prev): ?>
        <div class="dior-mob-history"><div><strong><?php echo esc_html($mobile_prev['condition'] ?? $mobile_prev['condition_name'] ?? 'General Consultation'); ?></strong><small><?php echo esc_html($mobile_prev['provider'] ?? $mobile_prev['doctor_name'] ?? 'Provider not recorded'); ?></small><small><?php echo esc_html($mobile_prev['date'] ?? $mobile_prev['appt_date'] ?? '—'); ?> · <?php echo esc_html($mobile_prev['time'] ?? $mobile_prev['appt_time'] ?? '—'); ?></small></div><span><?php echo esc_html(ucfirst($mobile_prev['status'] ?? 'Completed')); ?></span></div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <section class="dior-mob-section">
    <div class="dior-mob-section-heading"><div><span class="dior-mob-eyebrow">TESTS & REPORTS</span><h2>Latest lab results</h2></div><button type="button" class="dior-mob-text-action" data-dior-mobile-tab="documents">View all</button></div>
    <div class="dior-mob-list-card">
      <?php if (empty($lab_docs)): ?>
        <div class="dior-mob-empty"><span><i class="fas fa-flask"></i></span><strong>No lab results available</strong><small>New reports will appear here when uploaded.</small></div>
      <?php else: foreach (array_slice($lab_docs, 0, 3) as $mobile_lab): ?>
        <div class="dior-mob-lab"><div><strong><?php echo esc_html($mobile_lab['title'] ?? $mobile_lab['name'] ?? 'Lab Test'); ?></strong><small><?php echo esc_html($mobile_lab['date'] ?? '—'); ?> · <?php echo esc_html($mobile_lab['status'] ?? 'Completed'); ?></small></div><a href="<?php echo esc_url($mobile_lab['file_url'] ?? '#'); ?>" aria-label="Download lab result"><i class="fas fa-download"></i></a></div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <section class="dior-mob-section">
    <div class="dior-mob-section-heading"><div><span class="dior-mob-eyebrow">MEDICATION</span><h2>Prescriptions</h2></div><button type="button" class="dior-mob-text-action" data-dior-mobile-tab="docs_meds">View all</button></div>
    <div class="dior-mob-list-card">
      <?php if (empty($rx_display)): ?>
        <div class="dior-mob-empty"><span><i class="fas fa-capsules"></i></span><strong>No prescriptions yet</strong><small>Your active prescriptions will appear here.</small></div>
      <?php else: foreach (array_slice($rx_display, 0, 3) as $mobile_rx):
        $mobile_rx_id = $mobile_rx['id'] ?? $mobile_rx['order_id'] ?? 'RX-001';
        $mobile_rx_name = (!empty($mobile_rx['title']) && $mobile_rx['title'] !== 'General Medical Prescription') ? $mobile_rx['title'] : ($mobile_rx['name'] ?? $mobile_rx['medication'] ?? 'Medication');
        $mobile_rx_status = $mobile_rx['status'] ?? 'Status unavailable';
      ?>
        <div class="dior-mob-prescription"><div><strong><?php echo esc_html($mobile_rx_name); ?></strong><small><?php echo esc_html($mobile_rx['prescribed_by'] ?? 'Provider'); ?> · <?php echo esc_html($mobile_rx_status); ?></small></div><button type="button" aria-label="View prescription details" onclick="if(typeof diorViewRxDetails==='function'){diorViewRxDetails('<?php echo esc_js($mobile_rx_id); ?>','<?php echo esc_js($mobile_rx_name); ?>','<?php echo esc_js($mobile_rx['instructions'] ?? ''); ?>');}"><i class="fas fa-chevron-right"></i></button></div>
      <?php endforeach; endif; ?>
    </div>
  </section>
</div>
