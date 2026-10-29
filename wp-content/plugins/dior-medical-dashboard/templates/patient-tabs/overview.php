<?php
$user_id       = !empty($user_id) ? (int)$user_id : get_current_user_id();
$profile       = (!empty($profile) && is_array($profile)) ? $profile : [];
$appointments  = (!empty($appointments) && is_array($appointments)) ? $appointments : [];
$prescriptions = (!empty($prescriptions) && is_array($prescriptions)) ? $prescriptions : [];
$upcoming_list = (!empty($upcoming_list) && is_array($upcoming_list)) ? $upcoming_list : [];
$past_list     = (!empty($past_list) && is_array($past_list)) ? $past_list : [];
$documents     = (!empty($documents) && is_array($documents)) ? $documents : [];
$hipaa_intake  = (!empty($hipaa_intake) && is_array($hipaa_intake)) ? $hipaa_intake : Dior_Patient_Portal_Data::get_normalized_hipaa_intake($user_id);
$has_intake    = !empty($hipaa_intake) && (!empty($hipaa_intake['is_submitted']) || !empty($hipaa_intake['submitted_at']));
?>
<section class="dior-tab-panel active custom-patient-overview" id="tab-overview">
  <div
    class="content-head d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
      <?php $display_first_name = !empty($profile['first_name']) ? ucfirst(strtolower($profile['first_name'])) : ''; ?>
      <h1 class="h3 fw-bold text-dark mb-1">Welcome Back, <?php echo esc_html(strtoupper($display_first_name)); ?></h1>
      <p class="text-secondary small mb-0">Manage your telehealth visits, prescriptions, and medical records.</p>
    </div>
    <a href="<?php echo esc_url(home_url('/patient-dashboard/#tab=appointments&subtab=book')); ?>" data-appt-subtab="book"
      class="btn dior-btn-gold rounded-pill fw-semibold py-2 px-4 shadow-sm d-inline-flex align-items-center gap-2">
      <i class="fas fa-calendar-plus"></i>
      <span>Book An Appointment</span>
    </a>
  </div>

  <div class="row g-3 g-xl-4 align-items-start">

    <!-- Left Column (Grid Standard: col-12 col-xl-7 col-xxl-8) -->
    <div class="col-12 col-xl-7 col-xxl-8">

      <!-- 1. Stats Row -->
      <div class="row g-3 mb-3 mb-xl-4">
        <div class="col-12 col-sm-4">
          <div
            class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column justify-content-between">
            <div class="stat-num fs-1 fw-bold text-primary mb-1">
              <?php echo str_pad(count($appointments), 2, '0', STR_PAD_LEFT); ?>
            </div>
            <div class="stat-label text-dark fw-semibold small">Total Consultations</div>
          </div>
        </div>
        <div class="col-12 col-sm-4">
          <div
            class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column justify-content-between">
            <div class="stat-num fs-1 fw-bold text-primary mb-1">
              <?php echo str_pad(count($prescriptions), 2, '0', STR_PAD_LEFT); ?>
            </div>
            <div class="stat-label text-dark fw-semibold small">Active Prescriptions</div>
          </div>
        </div>
        <div class="col-12 col-sm-4">
          <div
            class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column justify-content-between">
            <div class="stat-num fs-1 fw-bold text-primary mb-1">
              <?php echo str_pad(count($upcoming_list), 2, '0', STR_PAD_LEFT); ?>
            </div>
            <div class="stat-label text-dark fw-semibold small">Upcoming Appointments</div>
          </div>
        </div>
      </div>

      <!-- 2. Mid-Grid Row (Grid Standard: col-xxl-6 side-by-side, col-12 on xl & below) -->
      <div class="row g-3 mb-3 mb-xl-4">

        <!-- Patient Profile Card -->
        <div class="col-12 col-xxl-6">
          <div class="card patient-card border-0 shadow-sm rounded-4 p-4 h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex flex-wrap flex-sm-nowrap align-items-center gap-3">
                <div class="patient-photo rounded-4 overflow-hidden flex-shrink-0" id="dior-patient-photo-box" style="width: 88px; height: 88px;">
                  <?php if (!empty($profile['avatar_url'])): ?>
                    <img id="dior-overview-patient-img" class="w-100 h-100 object-fit-cover"
                      src="<?php echo esc_url($profile['avatar_url']); ?>" alt="Profile"
                      onerror="this.src='https://ui-avatars.com/api/?name=<?php echo rawurlencode($profile['full_name'] ?? 'Patient'); ?>&background=eef2ff&color=2C6CB1';">
                  <?php else: ?>
                    <img id="dior-overview-patient-img" class="w-100 h-100 object-fit-cover"
                      src="<?php echo esc_url(get_avatar_url(get_current_user_id(), ['size' => 200])); ?>"
                      alt="<?php echo esc_attr($profile['full_name'] ?? 'Profile'); ?>"
                      onerror="this.src='https://ui-avatars.com/api/?name=<?php echo rawurlencode($profile['full_name'] ?? 'Patient'); ?>&background=eef2ff&color=2C6CB1';">
                  <?php endif; ?>
                </div>
                <div class="patient-info flex-grow-1 min-w-0">
                  <?php
                  $patient_identifier = !empty($profile['patient_id']) ? $profile['patient_id'] : get_user_meta($user_id, 'patient_id', true);
                  if (empty($patient_identifier))
                    $patient_identifier = 'DM-' . (10000 + (int) $user_id);
                  ?>
                  <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h2 class="h4 fw-bold mb-0 text-truncate text-primary">
                      <?php echo esc_html(!empty($profile['full_name']) ? $profile['full_name'] : 'Patient'); ?>
                    </h2>
                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small fw-medium"><?php echo esc_html($patient_identifier); ?></span>
                  </div>
                  <?php
                  $dob_raw = !empty($profile['dob']) ? $profile['dob'] : '';
                  $age_str = '';
                  if (!empty($dob_raw)) {
                    try {
                      $age_calc = (new DateTime('today'))->diff(new DateTime($dob_raw))->y;
                      $age_str = $age_calc . ' Years Old';
                    } catch (Exception $e) {
                      $age_str = '';
                    }
                  }
                  ?>
                  <div class="text-secondary small mb-2"><?php echo esc_html($age_str ?: '—'); ?></div>
                  <?php
                  $p_height = !empty($profile['height']) ? trim($profile['height']) : '';
                  $p_weight = !empty($profile['weight']) ? trim($profile['weight']) : '';

                  // Auto-sync fallback from goals if height/weight not directly saved in user meta yet
                  $health_goals = get_user_meta($user_id, 'dior_health_goals', true);
                  $health_goals = is_array($health_goals) ? $health_goals : [];

                  if (empty($p_height) && !empty($health_goals)) {
                    foreach ($health_goals as $g) {
                      if (!empty($g['name']) && strtolower(trim($g['name'])) === 'height' && !empty($g['val'])) {
                        $p_height = trim($g['val']);
                        update_user_meta($user_id, 'height', $p_height);
                        break;
                      }
                    }
                  }
                  if (empty($p_weight) && !empty($health_goals)) {
                    foreach ($health_goals as $g) {
                      if (!empty($g['name']) && (stripos($g['name'], 'weight') !== false) && !empty($g['val'])) {
                        $p_weight = trim($g['val']);
                        update_user_meta($user_id, 'weight', $p_weight);
                        break;
                      }
                    }
                  }

                  if (strpos($p_weight, '/') !== false) {
                    $parts = explode('/', $p_weight);
                    $p_weight = trim($parts[0]);
                  }
                  $p_height_num = preg_replace('/[^0-9.]/', '', $p_height);
                  $p_weight_num = preg_replace('/[^0-9.]/', '', $p_weight);
                  $h_display = !empty($p_height_num) ? $p_height_num . ' cm' : (!empty($p_height) ? $p_height : '—');
                  $w_display = !empty($p_weight_num) ? $p_weight_num . ' kg' : (!empty($p_weight) ? $p_weight : '—');
                  ?>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center flex-wrap gap-3 gap-sm-4">
                      <div>
                        <strong id="dior-patient-metric-height" class="d-block text-dark fs-5 fw-bold lh-1 mb-1 text-nowrap"><?php echo esc_html($h_display); ?></strong>
                        <span class="text-secondary small">Height</span>
                      </div>
                      <div class="border-start border-secondary-subtle ps-4">
                        <strong id="dior-patient-metric-weight" class="d-block text-dark fs-5 fw-bold lh-1 mb-1 text-nowrap"><?php echo esc_html($w_display); ?></strong>
                        <span class="text-secondary small">Weight</span>
                      </div>
                    </div>
                    <span role="button" tabindex="0" class="btn-sm rounded-circle d-inline-flex align-items-center justify-content-center p-0 border border-light-subtle bg-light text-secondary shadow-none" style="width: 28px; height: 28px; cursor: pointer;" data-bs-toggle="modal" data-bs-target="#diorHealthGoalsModal" onclick="if(window.bootstrap&&bootstrap.Modal){bootstrap.Modal.getOrCreateInstance(document.getElementById('diorHealthGoalsModal')).show();}" title="Edit Vitals">
                      <i class="fas fa-pencil-alt text-secondary" style="font-size: 11px;"></i>
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2 px-3 shadow-sm w-100 mt-3"
              data-switch-tab="settings" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('settings');">Edit Profile</button>
          </div>
        </div>

        <!-- Health Goals Card -->
        <div class="col-12 col-xxl-6">
          <div class="card goals-card border-0 shadow-sm rounded-4 p-4 h-100 d-flex flex-column"
            id="dior-patient-goals-card">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 border-secondary-subtle">
              <h3 class="section-title mb-0 fs-5 fw-bold text-primary">Health Goals</h3>
              <button type="button" class="btn btn-outline-primary btn-sm rounded-circle d-inline-flex align-items-center justify-content-center p-0 shadow-sm dior-icon-btn-32" data-bs-toggle="modal" data-bs-target="#diorHealthGoalsModal" onclick="if(window.bootstrap&&bootstrap.Modal){bootstrap.Modal.getOrCreateInstance(document.getElementById('diorHealthGoalsModal')).show();}" title="Manage Health Goals">
                <i class="fas fa-plus small"></i>
              </button>
            </div>
            <?php
            $health_goals = get_user_meta($user_id, 'dior_health_goals', true);
            $display_goals = !empty($health_goals) && is_array($health_goals) ? $health_goals : [];
            ?>
            <div id="dior-goals-card-body"
              class="goals-list-wrap dior-card-scrollable d-flex flex-column gap-3 flex-grow-1 dior-scroll-200 pe-1 justify-content-center">
              <?php if (empty($display_goals)): ?>
                <div class="text-center py-4 text-muted small my-auto">
                  <i class="fas fa-bullseye fs-4 text-secondary opacity-50 mb-2 d-block"></i>
                  No health goals set yet. Click <strong><i class="fas fa-plus"></i></strong> above to add your goals.
                </div>
              <?php else: ?>
                <?php foreach ($display_goals as $g_idx => $goal):
                  $pct = min(100, max(0, (int) ($goal['pct'] ?? 50)));
                  ?>
                  <div class="goal mb-2" data-goal-index="<?php echo esc_attr($g_idx); ?>">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <span class="fw-bold text-dark small"><?php echo esc_html($goal['name']); ?></span>
                      <span class="text-secondary small"><?php echo esc_html($goal['val']); ?></span>
                    </div>
                    <div class="progress dior-progress-sm rounded-pill bg-light">
                      <div class="progress-bar rounded-pill dior-progress-orange" role="progressbar"
                        style="width: <?php echo esc_attr($pct); ?>%;" aria-valuenow="<?php echo esc_attr($pct); ?>"
                        aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>

      <!-- 3. Previous Consultation Table Card -->
      <div class="card table-card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-3 mb-xl-4">
        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 border-secondary-subtle">
          <h3 class="section-title mb-0 fs-5 fw-bold text-dark"><i
              class="fas fa-clock-rotate-left text-primary me-2"></i>Previous Consultation</h3>
          <a class="view-all text-primary text-decoration-none fw-semibold small dior-fs-13" href="#" data-switch-tab="appointments"
            onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('appointments');">View All &rarr;</a>
        </div>
        <div class="table-responsive mb-0 dior-card-scrollable dior-scroll-275">
          <table class="table align-middle text-nowrap mb-0">
            <thead class="sticky-top bg-white">
              <tr>
                <th class="border-top-0 text-primary small fw-semibold">Consultation Name</th>
                <th class="border-top-0 text-primary small fw-semibold">Doctor Name</th>
                <th class="border-top-0 text-primary small fw-semibold text-nowrap">Date</th>
                <th class="border-top-0 text-primary small fw-semibold text-nowrap">Time</th>
                <th class="border-top-0 text-primary small fw-semibold text-end text-nowrap">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $prev_apts = !empty($past_list) ? $past_list : [];
              if (empty($prev_apts)):
                ?>
                <tr>
                  <td colspan="5" class="text-center py-4 text-muted small">
                    <i class="fas fa-calendar-xmark fs-4 text-secondary opacity-50 mb-2 d-block"></i>
                    No previous consultations recorded.
                  </td>
                </tr>
                <?php
              else:
                foreach ($prev_apts as $prev_apt):
                  $pa_cond = !empty($prev_apt['condition']) ? $prev_apt['condition'] : (!empty($prev_apt['condition_name']) ? $prev_apt['condition_name'] : 'General Consultation');
                  $pa_doc = !empty($prev_apt['provider']) ? $prev_apt['provider'] : (!empty($prev_apt['doctor_name']) ? $prev_apt['doctor_name'] : 'Provider not recorded');
                  $pa_date_raw = !empty($prev_apt['date']) ? $prev_apt['date'] : (!empty($prev_apt['appt_date']) ? $prev_apt['appt_date'] : '');
                  $pa_date = $pa_date_raw;
                  if (!empty($pa_date_raw)) {
                    $ts = strtotime($pa_date_raw);
                    if ($ts)
                      $pa_date = date('M d, Y', $ts);
                  }
                  $pa_time = !empty($prev_apt['time']) ? $prev_apt['time'] : (!empty($prev_apt['appt_time']) ? $prev_apt['appt_time'] : '—');
                  $pa_status = !empty($prev_apt['status']) ? strtolower($prev_apt['status']) : 'completed';
                  if ($pa_status === 'confirmed')
                    $pa_status = 'completed';
                  $s_badge = 'bg-success-subtle text-success border border-success-subtle';
                  if ($pa_status === 'cancelled' || $pa_status === 'canceled')
                    $s_badge = 'bg-danger-subtle text-danger border border-danger-subtle';
                  if ($pa_status === 'in progress' || $pa_status === 'pending')
                    $s_badge = 'bg-warning-subtle text-warning border border-warning-subtle';
                  ?>
                  <tr>
                    <td class="fw-semibold text-dark" data-label="Consultation"><?php echo esc_html($pa_cond); ?></td>
                    <td class="text-secondary" data-label="Doctor"><?php echo esc_html($pa_doc); ?></td>
                    <td class="text-secondary" data-label="Date"><?php echo esc_html($pa_date); ?></td>
                    <td class="text-secondary" data-label="Time"><?php echo esc_html($pa_time); ?></td>
                    <td class="text-end" data-label="Status"><span
                        class="badge <?php echo esc_attr($s_badge); ?> rounded-pill px-2 py-1 small"><?php echo esc_html(ucfirst($pa_status)); ?></span>
                    </td>
                  </tr>
                <?php endforeach;
              endif;
              ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 4. Latest Lab Results Table Card -->
      <div class="card lab-card border-0 shadow-sm rounded-4 p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 border-secondary-subtle">
          <h3 class="section-title mb-0 fs-5 fw-bold text-dark"><i class="fas fa-flask text-primary me-2"></i>Latest Lab
            Results</h3>
          <a class="view-all text-primary text-decoration-none fw-semibold small dior-fs-13" href="#" data-switch-tab="documents"
            onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('documents');">View All &rarr;</a>
        </div>
        <div class="table-responsive mb-0 dior-card-scrollable dior-scroll-235">
          <table class="lab-table table align-middle text-nowrap mb-0">
            <thead class="sticky-top bg-white">
              <tr>
                <th class="border-top-0 text-primary small fw-semibold">Test Name</th>
                <th class="border-top-0 text-primary small fw-semibold text-nowrap">Date</th>
                <th class="border-top-0 text-primary small fw-semibold text-nowrap">Status</th>
                <th class="border-top-0 text-primary small fw-semibold">Ordered By</th>
                <th class="border-top-0 text-primary small fw-semibold text-end text-nowrap">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $lab_docs = [];
              foreach ($documents as $doc) {
                $dtype = strtolower($doc['type'] ?? ($doc['document_type'] ?? ''));
                if (strpos($dtype, 'lab') !== false || strpos($dtype, 'result') !== false || strpos($dtype, 'test') !== false) {
                  $lab_docs[] = $doc;
                }
              }
              if (empty($lab_docs)):
                ?>
                <tr>
                  <td colspan="5" class="text-center py-4 text-muted small">
                    <i class="fas fa-flask fs-4 text-secondary opacity-50 mb-2 d-block"></i>
                    No lab test records found yet.
                  </td>
                </tr>
                <?php
              else:
                foreach ($lab_docs as $lab):
                  $lab_name = !empty($lab['title']) ? $lab['title'] : (!empty($lab['name']) ? $lab['name'] : 'Lab Test');
                  $lab_date = !empty($lab['date']) ? $lab['date'] : '—';
                  $lab_status = !empty($lab['status']) ? $lab['status'] : 'Completed';
                  $lab_by = !empty($lab['ordered_by']) ? $lab['ordered_by'] : (!empty($lab['doctor_name']) ? $lab['doctor_name'] : 'Physician');
                  $lab_url = !empty($lab['file_url']) ? $lab['file_url'] : '#';
                  $s_badge = (strtolower($lab_status) === 'abnormal') ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle';
                  ?>
                  <tr>
                    <td class="fw-semibold text-dark" data-label="Test Name"><?php echo esc_html($lab_name); ?></td>
                    <td class="text-secondary" data-label="Date"><?php echo esc_html($lab_date); ?></td>
                    <td data-label="Status"><span
                        class="badge <?php echo esc_attr($s_badge); ?> rounded-pill px-2 py-1 small"><?php echo esc_html($lab_status); ?></span>
                    </td>
                    <td class="text-secondary" data-label="Ordered By"><?php echo esc_html($lab_by); ?></td>
                    <td class="text-end" data-label="Action">
                      <a href="<?php echo esc_url($lab_url); ?>"
                        class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0 dior-icon-btn-30" title="Download Lab Report">
                        <i class="fas fa-download"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach;
              endif;
              ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Right Column (Grid Standard: col-12 col-xl-5 col-xxl-4) -->
    <div class="col-12 col-xl-5 col-xxl-4">
      <div class="row g-3">
        <?php
        if (!function_exists('dior_get_doctor_avatar_url')) {
          function dior_get_doctor_avatar_url($doc_id = 0, $doc_name = '')
          {
            $avatar = '';
            $doc_id = absint($doc_id);
            $doc_post = $doc_id ? get_post($doc_id) : null;
            $doctor_user_id = 0;

            // Resolve the avatar from the exact provider record first. Do not treat a
            // provider post ID as a WordPress user ID or borrow another provider's image.
            if ($doc_post && $doc_post->post_type === 'wpddb_doctor') {
              $avatar = get_post_meta($doc_id, '_wpddb_doctor_avatar', true)
                ?: get_post_meta($doc_id, 'wpddb_doctor_avatar', true)
                ?: get_the_post_thumbnail_url($doc_id, 'full');

              $doctor_user_id = (int) (
                get_post_meta($doc_id, '_doctor_user_id', true)
                ?: get_post_meta($doc_id, '_wpddb_doctor_user_id', true)
              );
              if (!$doctor_user_id && $doc_post->post_author) {
                $author = get_userdata((int) $doc_post->post_author);
                if ($author && (in_array('doctor', (array) $author->roles, true) || in_array('administrator', (array) $author->roles, true))) {
                  $doctor_user_id = (int) $author->ID;
                }
              }
            } else {
              $candidate = $doc_id ? get_userdata($doc_id) : null;
              if ($candidate && (in_array('doctor', (array) $candidate->roles, true) || in_array('administrator', (array) $candidate->roles, true))) {
                $doctor_user_id = (int) $candidate->ID;
              }
            }

            if (!$avatar && $doctor_user_id) {
              $avatar = get_user_meta($doctor_user_id, 'dior_profile_image', true)
                ?: get_user_meta($doctor_user_id, 'doctor_avatar', true)
                ?: get_user_meta($doctor_user_id, 'profile_picture', true)
                ?: get_user_meta($doctor_user_id, 'profile_image', true)
                ?: get_user_meta($doctor_user_id, 'avatar_url', true)
                ?: get_avatar_url($doctor_user_id, ['size' => 160]);
            }

            if ($avatar) {
              return esc_url($avatar);
            }

            $clean_name = preg_replace('/^(?:dr\\.?\\s*)+/i', '', trim((string) $doc_name));
            return 'https://ui-avatars.com/api/?name=' . rawurlencode($clean_name ?: 'Provider') . '&background=eef2ff&color=2C6CB1';
          }
        }
        ?>
        <div class="col-12 col-md-6 col-xl-12">
          <div
            class="card doctor-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column justify-content-between"
            id="dior-overview-doctor-card">
            <?php
            $ha_name = 'No upcoming consultation';
            $ha_spec = 'Your next appointment will appear here';
            $ha_date = '—';
            $ha_time = '—';
            $ha_avatar = '';

            if (!empty($next_appointment)) {
              $ha_name = !empty($next_appointment['doctor_name']) ? $next_appointment['doctor_name'] : (!empty($next_appointment['provider']) ? $next_appointment['provider'] : $ha_name);
              $ha_spec = !empty($next_appointment['provider_spec']) ? $next_appointment['provider_spec'] : $ha_spec;
              $ha_date = !empty($next_appointment['date']) ? $next_appointment['date'] : $ha_date;
              $ha_time = !empty($next_appointment['time']) ? $next_appointment['time'] : $ha_time;
              $ha_avatar = !empty($next_appointment['avatar']) ? $next_appointment['avatar'] : '';

              $doc_id = !empty($next_appointment['doctor_id']) ? $next_appointment['doctor_id'] : (!empty($next_appointment['provider_id']) ? $next_appointment['provider_id'] : 0);
              if (empty($ha_avatar)) {
                $ha_avatar = dior_get_doctor_avatar_url($doc_id, $ha_name);
              }
            }
            if (empty($ha_avatar)) {
              $ha_avatar = dior_get_doctor_avatar_url(0, $ha_name);
            }
            if (!empty($next_appointment)): ?>
              <div>
                <div class="d-flex align-items-start gap-3 mb-3">
                  <div class="doctor-photo rounded-circle overflow-hidden flex-shrink-0 dior-doctor-photo-md">
                    <img class="w-100 h-100 object-fit-cover" src="<?php echo esc_url($ha_avatar); ?>"
                      alt="<?php echo esc_attr($ha_name); ?>"
                      onerror="this.onerror=null;this.src='<?php echo esc_url(dior_get_doctor_avatar_url(0, $ha_name)); ?>';">
                  </div>
                  <div class="doctor-copy flex-grow-1 min-w-0">
                    <span
                      class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 small mb-1">Telehealth
                      Medical Care</span>
                    <h4 class="fs-6 fw-bold text-dark mb-0 text-truncate"><?php echo esc_html($ha_name); ?></h4>
                    <div class="text-secondary small text-truncate mb-2"><?php echo esc_html($ha_spec); ?></div>
                    <div class="d-flex align-items-center gap-3 text-secondary small flex-wrap">
                      <span><i class="far fa-calendar me-1 text-primary"></i><?php echo esc_html($ha_date); ?></span>
                      <span><i class="far fa-clock me-1 text-primary"></i><?php echo esc_html($ha_time); ?></span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="d-flex flex-column gap-2 mt-3">
                <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2 px-3 shadow-sm w-100"
                  data-switch-tab="consultation"
                  onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('consultation');">Join Video Consultation Room</button>
                <button type="button" class="btn btn-outline-primary rounded-3 fw-semibold py-2 px-3 w-100"
                  data-switch-tab="appointments"
                  onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('appointments');">Reschedule</button>
              </div>
            <?php else: ?>
              <div class="text-center py-4 my-auto">
                <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                  <i class="far fa-calendar-check fs-4"></i>
                </div>
                <h4 class="fs-6 fw-bold text-dark mb-1">No Upcoming Consultation</h4>
                <p class="text-secondary small mb-3">Schedule a telehealth consultation with a certified doctor.</p>
                <a href="<?php echo esc_url(home_url('/patient-dashboard/#tab=appointments&subtab=book')); ?>"
                  class="btn btn-primary rounded-3 fw-semibold py-2 px-3 shadow-sm w-100 d-inline-flex align-items-center justify-content-center gap-2"
                  data-switch-tab="appointments"
                  onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('appointments');">
                  <i class="fas fa-calendar-plus"></i>
                  <span>Book An Appointment</span>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- 2. Upcoming Consultations Card -->
        <div class="col-12 col-md-6 col-xl-12">
          <div class="card upcoming border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column"
            id="dior-overview-upcoming-card">
            <div class="border-bottom pb-2 mb-3 border-secondary-subtle">
              <h3 class="section-title mb-0 fs-5 fw-bold text-dark"><i
                  class="far fa-calendar-check text-primary me-2"></i>Upcoming Consultation</h3>
            </div>
            <div class="consult-list-wrap dior-card-scrollable d-flex flex-column gap-3 flex-grow-1 dior-scroll-240 pe-1">
              <?php
              $display_list = !empty($upcoming_list) ? $upcoming_list : [];
              if (empty($display_list)):
                ?>
                <div class="text-center py-4 text-muted small">
                  <i class="far fa-calendar-xmark fs-4 text-secondary opacity-50 mb-2 d-block"></i>
                  No upcoming consultations scheduled.
                </div>
                <?php
              else:
                foreach ($display_list as $apt):
                  $doc_name = !empty($apt['doctor_name']) ? $apt['doctor_name'] : (!empty($apt['provider']) ? $apt['provider'] : 'Provider');
                  $doc_spec = !empty($apt['provider_spec']) ? $apt['provider_spec'] : 'Board Certified Urgent Care Physician';

                  $doc_id = !empty($apt['doctor_id']) ? $apt['doctor_id'] : (!empty($apt['provider_id']) ? $apt['provider_id'] : 0);
                  $doc_avatar = !empty($apt['avatar']) ? $apt['avatar'] : dior_get_doctor_avatar_url($doc_id, $doc_name);
                  ?>
                  <div class="consult d-flex align-items-center gap-3 p-2 rounded-3 border bg-light-subtle">
                    <div class="consult-photo rounded-circle overflow-hidden flex-shrink-0 dior-consult-photo-sm">
                      <img class="w-100 h-100 object-fit-cover" src="<?php echo esc_url($doc_avatar); ?>"
                        alt="<?php echo esc_attr($doc_name); ?>"
                        onerror="this.onerror=null;this.src='<?php echo esc_url(dior_get_doctor_avatar_url(0, $doc_name)); ?>';">
                    </div>
                    <div class="consult-body flex-grow-1 min-w-0">
                      <div class="fw-bold text-dark small text-truncate"><?php echo esc_html($doc_name); ?></div>
                      <div class="text-secondary small text-truncate dior-fs-12">
                        <?php echo esc_html($doc_spec); ?>
                      </div>
                      <div class="d-flex align-items-center gap-2 text-muted small mt-1 dior-fs-11">
                        <span><i class="far fa-calendar me-1"></i><?php echo esc_html($apt['date']); ?></span>
                        <span><i class="far fa-clock me-1"></i><?php echo esc_html($apt['time']); ?></span>
                      </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3 flex-shrink-0"
                      data-switch-tab="appointments"
                      onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('appointments');" title="Reschedule">
                      <i class="fas fa-arrows-rotate"></i>
                    </button>
                  </div>
                <?php endforeach;
              endif;
              ?>
            </div>
          </div>
        </div>

        <!-- 3. Prescriptions Card -->
        <div class="col-12 col-md-12 col-xl-12">
          <div class="card prescriptions border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column"
            id="dior-overview-prescriptions-card">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 border-secondary-subtle">
              <h3 class="section-title mb-0 fs-5 fw-bold text-dark"><i
                  class="fas fa-prescription-bottle-medical text-primary me-2"></i>Prescriptions</h3>
              <a class="view-all text-primary text-decoration-none fw-semibold small dior-fs-13" href="#" data-switch-tab="docs_meds"
                onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('docs_meds');">View All &rarr;</a>
            </div>
            <div class="rx-list-wrap dior-card-scrollable d-flex flex-column gap-2 flex-grow-1 dior-scroll-290 pe-1">
              <?php
              $rx_display = !empty($prescriptions) ? $prescriptions : [];
              if (empty($rx_display)):
                ?>
                <div class="text-center py-4 text-muted small">
                  <i class="fas fa-capsules fs-4 text-secondary opacity-50 mb-2 d-block"></i>
                  No active prescriptions yet.
                </div>
                <?php
              else:
                foreach ($rx_display as $rx):
                  $rx_id = !empty($rx['id']) ? $rx['id'] : (!empty($rx['order_id']) ? $rx['order_id'] : 'RX-001');
                  $rx_name = !empty($rx['title']) && $rx['title'] !== 'General Medical Prescription' ? $rx['title'] : (!empty($rx['name']) ? $rx['name'] : (!empty($rx['medication']) ? $rx['medication'] : 'Medication'));
                  $rx_sub = !empty($rx['medication']) && $rx['medication'] !== $rx_name ? $rx['medication'] : (!empty($rx['instructions']) ? $rx['instructions'] : '');
                  $rx_by = !empty($rx['prescribed_by']) ? $rx['prescribed_by'] : 'Provider';
                  $rx_status = !empty($rx['status']) ? $rx['status'] : 'Status unavailable';
                  $is_active = (strtolower($rx_status) === 'active');
                  $rx_badge_cls = $is_active ? 'badge bg-success-subtle text-success border border-success-subtle' : 'badge bg-secondary-subtle text-secondary border';
                  ?>
                  <div class="rx d-flex align-items-center justify-content-between py-2 border-bottom">
                    <div class="me-2 min-w-0">
                      <div class="rx-name fw-semibold text-dark text-truncate dior-fs-13">
                        <?php echo esc_html($rx_name); ?>
                      </div>
                      <?php if ($rx_sub !== ''): ?>
                        <div class="small text-muted text-truncate dior-fs-11 text-truncate">
                          <?php echo esc_html($rx_sub); ?>
                        </div>
                      <?php endif; ?>
                      <div class="rx-doctor small text-muted dior-fs-11"><?php echo esc_html($rx_by); ?></div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                      <span
                        class="<?php echo esc_attr($rx_badge_cls); ?> px-2 py-1 small rounded-pill"><?php echo esc_html(ucfirst($rx_status)); ?></span>
                      <button type="button"
                        class="btn btn-sm btn-outline-primary rounded-3 d-inline-flex align-items-center gap-1"
                        title="View Prescription Details"
                        onclick="diorViewRxDetails('<?php echo esc_js($rx_id); ?>', '<?php echo esc_js($rx_name); ?>', '<?php echo esc_js($rx['instructions'] ?? ''); ?>')">
                        <i class="fas fa-eye me-1"></i> View
                      </button>
                    </div>
                  </div>
                <?php endforeach;
              endif;
              ?>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>

  <!-- ============================================================== -->
  <!-- ============================================================== -->
  <!-- ============================================================== -->
  <!-- HEALTH GOALS & VITALS MODAL (100% Bootstrap 5 - Simple Clean Standard) -->
  <!-- ============================================================== -->
  <div class="modal fade" id="diorHealthGoalsModal" tabindex="-1" aria-labelledby="diorHealthGoalsModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
          <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="diorHealthGoalsModalLabel">Health Goals &amp; Vitals</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="dior-form-add-health-goal">
          <div class="modal-body p-4 bg-white">
            <div class="row g-3">
              <!-- Vitals Section -->
              <div class="col-12">
                <h6 class="fw-bold text-dark mb-1 small text-uppercase">Body Measurements &amp; Vitals</h6>
              </div>

              <div class="col-12 col-sm-6">
                <label for="goal_height" class="form-label small text-secondary fw-semibold mb-1">Height</label>
                <div class="input-group">
                  <input type="text" class="form-control rounded-start-3 py-2" id="goal_height" name="height"
                    placeholder="e.g. 175 cm" value="<?php echo esc_attr($p_height); ?>">
                  <span class="input-group-text bg-light text-muted small rounded-end-3">cm / in</span>
                </div>
              </div>

              <div class="col-12 col-sm-6">
                <label for="goal_weight" class="form-label small text-secondary fw-semibold mb-1">Weight</label>
                <div class="input-group">
                  <input type="text" class="form-control rounded-start-3 py-2" id="goal_weight" name="weight"
                    placeholder="e.g. 70 kg" value="<?php echo esc_attr($p_weight); ?>">
                  <span class="input-group-text bg-light text-muted small rounded-end-3">kg / lbs</span>
                </div>
              </div>

              <div class="col-12">
                <hr class="my-2 border-secondary-subtle">
              </div>

              <!-- Current Goals Section -->
              <div class="col-12" id="dior-modal-current-goals-sec">
                <h6 class="fw-bold text-dark mb-2 small text-uppercase">Current Health Goals</h6>
                <div class="table-responsive border rounded-3 mb-0">
                  <table class="table table-sm align-middle mb-0">
                    <thead class="bg-light">
                      <tr>
                        <th class="ps-3 py-2 text-secondary small fw-semibold">Goal</th>
                        <th class="py-2 text-secondary small fw-semibold">Target</th>
                        <th class="py-2 text-secondary small fw-semibold text-center">Progress</th>
                        <th class="pe-3 py-2 text-end text-secondary small fw-semibold">Action</th>
                      </tr>
                    </thead>
                    <tbody id="dior-modal-goals-list">
                      <?php if (empty($display_goals)): ?>
                        <tr><td colspan="4" class="text-center py-3 text-muted small">No goals recorded yet.</td></tr>
                      <?php else: ?>
                        <?php foreach ($display_goals as $g_i => $g_item): ?>
                          <tr>
                            <td class="ps-3 py-2 fw-semibold text-dark small"><?php echo esc_html($g_item['name'] ?? ''); ?></td>
                            <td class="py-2 text-secondary small"><?php echo esc_html($g_item['val'] ?? ''); ?></td>
                            <td class="py-2 text-center text-secondary small"><?php echo (int)($g_item['pct'] ?? 0); ?>%</td>
                            <td class="pe-3 py-2 text-end">
                              <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0 text-decoration-none dior-modal-del-goal-btn fw-semibold small" data-goal-index="<?php echo esc_attr($g_i); ?>">Delete</button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="col-12">
                <hr class="my-2 border-secondary-subtle">
              </div>

              <!-- Add Goal Section -->
              <div class="col-12">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                  <h6 class="fw-bold text-dark mb-0 small text-uppercase">Add New Goal</h6>
                  <div class="d-flex align-items-center gap-1 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2 dior-quick-goal dior-fs-11" data-name="Target Weight" data-val="65 kg" data-pct="70">Target Weight</button>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2 dior-quick-goal dior-fs-11" data-name="Daily Steps" data-val="10,000 steps" data-pct="65">Daily Steps</button>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2 dior-quick-goal dior-fs-11" data-name="Water Intake" data-val="3.0 L" data-pct="80">Water Intake</button>
                  </div>
                </div>
              </div>

              <div class="col-12">
                <label for="goal_name" class="form-label small text-secondary fw-semibold mb-1">Goal Name / Category</label>
                <input type="text" class="form-control rounded-3 py-2" id="goal_name" name="goal_name"
                  list="dior-goal-suggestions" placeholder="e.g. Daily Water Intake, Target Weight">
                <datalist id="dior-goal-suggestions">
                  <option value="Target Weight"></option>
                  <option value="Daily Steps"></option>
                  <option value="Water Intake"></option>
                  <option value="Blood Pressure"></option>
                  <option value="Sleep Duration"></option>
                </datalist>
              </div>

              <div class="col-12 col-sm-6">
                <label for="goal_val" class="form-label small text-secondary fw-semibold mb-1">Target / Current Value</label>
                <input type="text" class="form-control rounded-3 py-2" id="goal_val" name="goal_val"
                  placeholder="e.g. 70 / 65 kg">
              </div>

              <div class="col-12 col-sm-6">
                <label for="goal_pct" class="form-label small text-secondary fw-semibold mb-1">Progress (%)</label>
                <input type="number" class="form-control rounded-3 py-2" id="goal_pct" name="goal_pct" min="0" max="100"
                  value="50" placeholder="50">
              </div>
            </div>

            <div id="dior-goal-form-alert" class="alert alert-danger d-none mt-3 mb-0 small" role="alert"></div>
          </div>
          <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-3 px-4 fw-semibold"
              data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm" id="dior-btn-save-goal">
              Save Vitals &amp; Goal
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

</section>