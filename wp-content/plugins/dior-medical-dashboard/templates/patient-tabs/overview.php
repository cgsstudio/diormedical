<section class="dior-tab-panel active custom-patient-overview" id="tab-overview">
  <div class="content-head">
    <div>
      <?php $display_first_name = !empty($profile['first_name']) ? ucfirst(strtolower($profile['first_name'])) : 'Mia'; ?>
      <h1>Welcome Back, <?php echo esc_html(strtoupper($display_first_name)); ?></h1>
      <p class="subtitle">Manage your telehealth visits, prescriptions, and medical records.</p>
    </div>
    <a href="#tab=appointments" data-appt-subtab="book" class="gold-btn btn" onclick="event.preventDefault(); if(typeof diorSwitchTab==='function') diorSwitchTab('appointments'); if(typeof diorSwitchApptSubTab==='function') diorSwitchApptSubTab('book');" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px; font-size:16px !important; cursor:pointer;">
      <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>
      Book An Appointment
    </a>
  </div>

  <div class="dashboard-grid">

    <div class="left-col">
      <div class="stats">
        <div class="card stat-card"><div class="stat-num"><?php echo str_pad(count($appointments), 2, '0', STR_PAD_LEFT); ?></div><div class="stat-label">Total Consultations</div></div>
        <div class="card stat-card"><div class="stat-num"><?php echo str_pad(count($prescriptions), 2, '0', STR_PAD_LEFT); ?></div><div class="stat-label">Active Prescriptions</div></div>
        <div class="card stat-card"><div class="stat-num"><?php echo str_pad(count($upcoming_list), 2, '0', STR_PAD_LEFT); ?></div><div class="stat-label">Upcoming Appointments</div></div>
      </div>

      <div class="mid-grid">
        <div class="card patient-card">
          <div class="patient-top">
            <div class="patient-photo" id="dior-patient-photo-box">
                <?php if (!empty($profile['avatar_url'])): ?>
                <img id="dior-overview-patient-img" src="<?php echo esc_url($profile['avatar_url']); ?>" alt="Profile">
                <?php else: ?>
                <img id="dior-overview-patient-img" src="<?php echo esc_url(get_avatar_url(get_current_user_id(), ['size' => 200])); ?>" alt="<?php echo esc_attr($profile['full_name'] ?? 'Profile'); ?>">
                <?php endif; ?>
            </div>
            <div class="patient-info">
              <h2><?php echo esc_html(!empty($profile['full_name']) ? $profile['full_name'] : 'Mia Song'); ?> <span class="patient-id">PAT-00123</span></h2>
                <?php
                $dob_raw = !empty($profile['dob']) ? $profile['dob'] : '';
                $age_str = '';
                if (!empty($dob_raw)) {
                    try {
                        $age_calc = (new DateTime('today'))->diff(new DateTime($dob_raw))->y;
                        $age_str = $age_calc . ' Years Old';
                    } catch(Exception $e) { $age_str = ''; }
                }
                ?>
              <div class="age"><?php echo esc_html($age_str ?: '25 Years Old'); ?></div>
              <div class="metrics">
                <?php
                $p_height = !empty($profile['height']) ? trim($profile['height']) : '170';
                $p_weight = !empty($profile['weight']) ? trim($profile['weight']) : '60';

                $h_display = (is_numeric($p_height)) ? $p_height . ' cm' : str_ireplace('cm', 'cm', $p_height);
                $w_display = (is_numeric($p_weight)) ? $p_weight . ' kg' : str_ireplace('kg', 'kg', $p_weight);
                ?>
                <div class="metric"><strong id="dior-patient-metric-height"><?php echo esc_html($h_display); ?></strong><span>Height</span></div>
                <div class="metric"><strong id="dior-patient-metric-weight"><?php echo esc_html($w_display); ?></strong><span>Weight</span></div>
              </div>
            </div>
          </div>
          <button class="edit-btn btn" data-switch-tab="settings">Edit Profile</button>
        </div>

        <div class="card goals-card" id="dior-patient-goals-card">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
            <h3 class="section-title" style="margin:0;">Health Goals</h3>
            <button type="button" class="btn btn-sm" id="dior-open-goals-modal-btn" onclick="diorOpenHealthGoalsModal()" title="Add / Update Health Goals" style="border:none; background:transparent; padding:0; cursor:pointer; color:#2C6CB1; font-size:16px;">
              <i class="fas fa-plus"></i>
            </button>
          </div>
          <div id="dior-goals-card-body">
          <h3 class="section-title">Health Goals</h3>
            <?php
            $health_goals = get_user_meta($user_id, 'dior_health_goals', true);
            $health_goals = is_array($health_goals) ? $health_goals : [];

            if (empty($health_goals)) {
                $display_goals = [
                    ['name' => 'Weight Loss', 'val' => '75.5 / 60 Kg', 'pct' => 40],
                    ['name' => 'Water Intake', 'val' => '1500 / 2500 ml', 'pct' => 50],
                    ['name' => 'Sleep', 'val' => '7 / 8 hrs', 'pct' => 78]
                ];
            } else {
                $display_goals = $health_goals;
            }
            ?>
            <?php foreach($display_goals as $goal): ?>
          <div class="goal"><div class="goal-row"><span><?php echo esc_html($goal['name']); ?></span><span class="goal-value"><?php echo esc_html($goal['val']); ?></span></div><div class="progress"><i style="width:<?php echo esc_attr(min(100, (int)($goal['pct'] ?? 50))); ?>%"></i></div></div>
          <?php endforeach; ?>
        </div>
        </div>
      </div>

      <div class="card table-card">
        <div class="table-head">
          <h3 class="section-title">Previous Consultation</h3>
          <a class="view-all" href="#" data-switch-tab="appointments">View All &rarr;</a>
        </div>
        <div class="table-wrap">
        <table>
          <thead><tr><th style="width:29%">Consultation Name</th><th style="width:31%">Doctor Name</th><th style="width:20%">Date</th><th style="width:14%">Time</th><th style="width:16%">Status</th></tr></thead>
          <tbody>
            <?php 
            $prev_apts = array_slice($past_list, 0, 4);
            if (empty($prev_apts)) {
                $prev_apts = [
                    ['condition' => 'General Consultation', 'provider' => 'Dr. Diorca Aquino de la Cruz', 'date' => 'Sep 10, 2026', 'time' => '03:00 PM', 'status' => 'Cancelled'],
                    ['condition' => 'Cold & Flu', 'provider' => 'Dr. Diorca Aquino de la Cruz', 'date' => 'Sep 5, 2026', 'time' => '11:30 AM', 'status' => 'In Progress'],
                    ['condition' => 'Allergy Consultation', 'provider' => 'Dr. Diorca Aquino de la Cruz', 'date' => 'Aug 28, 2026', 'time' => '04:00 PM', 'status' => 'Completed'],
                    ['condition' => 'General Consultation', 'provider' => 'Dr. Diorca Aquino de la Cruz', 'date' => 'Aug 15, 2026', 'time' => '02:00 PM', 'status' => 'Completed']
                ];
            }
            foreach($prev_apts as $prev_apt):
                $pa_cond = !empty($prev_apt['condition']) ? $prev_apt['condition'] : (!empty($prev_apt['condition_name']) ? $prev_apt['condition_name'] : 'General Consultation');
                $pa_doc  = !empty($prev_apt['provider']) ? $prev_apt['provider'] : (!empty($prev_apt['doctor_name']) ? $prev_apt['doctor_name'] : 'Dr. Diorca Aquino de la Cruz');
                $pa_date = !empty($prev_apt['date']) ? $prev_apt['date'] : (!empty($prev_apt['appt_date']) ? $prev_apt['appt_date'] : 'Sep 10, 2026');
                $pa_time = !empty($prev_apt['time']) ? $prev_apt['time'] : (!empty($prev_apt['appt_time']) ? $prev_apt['appt_time'] : '03:00 PM');
                $pa_status = !empty($prev_apt['status']) ? strtolower($prev_apt['status']) : 'completed';
                $s_cls = 'done';
                if ($pa_status === 'cancelled') $s_cls = 'cancel';
                if ($pa_status === 'in progress') $s_cls = 'progressing';
                if ($pa_status === 'pending') $s_cls = 'progressing';
            ?>
            <tr><td><?php echo esc_html($pa_cond); ?></td><td><?php echo esc_html($pa_doc); ?></td><td><?php echo esc_html($pa_date); ?></td><td><?php echo esc_html($pa_time); ?></td><td><span class="status <?php echo $s_cls; ?>"><?php echo esc_html(ucfirst($pa_status)); ?></span></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>

      <div class="card lab-card">
        <div class="table-head">
          <h3 class="section-title">Latest Lab Results</h3>
        </div>
        <div class="table-wrap">
        <table class="lab-table table">
          <thead><tr><th style="width:29%">Test Name</th><th style="width:20%">Date</th><th style="width:18%">Status</th><th style="width:29%">Ordered By</th><th style="width:8%">Action</th></tr></thead>
          <tbody>
            <?php
            $lab_docs = [];
            foreach ($documents as $doc) {
                $dtype = strtolower($doc['type'] ?? ($doc['document_type'] ?? ''));
                if (strpos($dtype, 'lab') !== false || strpos($dtype, 'result') !== false || strpos($dtype, 'test') !== false) {
                    $lab_docs[] = $doc;
                }
            }
            $lab_docs = array_slice($lab_docs, 0, 4);
            if(empty($lab_docs)) {
                $lab_docs = [
                    ['title' => 'Renal Function Test', 'date' => 'Sep 10, 2026', 'status' => 'Normal', 'ordered_by' => 'Dr. Diorca Aquino de la Cruz'],
                    ['title' => 'Thyroid Function Test', 'date' => 'Sep 05, 2026', 'status' => 'Abnormal', 'ordered_by' => 'Dr. Diorca Aquino de la Cruz'],
                    ['title' => 'Vitamin D Test', 'date' => 'Aug 26, 2026', 'status' => 'Normal', 'ordered_by' => 'Dr. Diorca Aquino de la Cruz'],
                    ['title' => 'Thyroid Function Test', 'date' => 'Aug 11, 2026', 'status' => 'Normal', 'ordered_by' => 'Dr. Diorca Aquino de la Cruz']
                ];
            }
            foreach($lab_docs as $lab):
                $lab_name   = !empty($lab['title']) ? $lab['title'] : (!empty($lab['name']) ? $lab['name'] : 'Lab Test');
                $lab_date   = !empty($lab['date']) ? $lab['date'] : 'Sep 10, 2026';
                $lab_status = !empty($lab['status']) ? $lab['status'] : 'Normal';
                $lab_by     = !empty($lab['ordered_by']) ? $lab['ordered_by'] : (!empty($lab['doctor_name']) ? $lab['doctor_name'] : 'Dr. Diorca Aquino de la Cruz');
                $lab_url    = !empty($lab['file_url']) ? $lab['file_url'] : '#';
                $s_cls = (strtolower($lab_status) === 'abnormal') ? 'abnormal' : 'normal';
            ?>
            <tr><td><?php echo esc_html($lab_name); ?></td><td><?php echo esc_html($lab_date); ?></td><td><span class="status <?php echo $s_cls; ?>"><?php echo esc_html($lab_status); ?></span></td><td><?php echo esc_html($lab_by); ?></td><td><a href="<?php echo esc_url($lab_url); ?>" class="download"><svg viewBox="0 0 24 24"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg></a></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div class="right-col">
      <div class="card doctor-card" id="dior-overview-doctor-card">
        <?php 
        $ha_name = 'Dr. Diorca Aquino de la Cruz';
        $ha_spec = 'Board Certified Urgent Care Physician';
        $ha_date = 'Sep 09, 2026';
        $ha_time = '2:00 PM';
        $ha_avatar = 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300';
        
        if (!empty($next_appointment)) {
            $ha_name = !empty($next_appointment['doctor_name']) ? $next_appointment['doctor_name'] : (!empty($next_appointment['provider']) ? $next_appointment['provider'] : $ha_name);
            $ha_spec = !empty($next_appointment['provider_spec']) ? $next_appointment['provider_spec'] : $ha_spec;
            $ha_date = !empty($next_appointment['date']) ? $next_appointment['date'] : $ha_date;
            $ha_time = !empty($next_appointment['time']) ? $next_appointment['time'] : $ha_time;
            
            $doc_id = !empty($next_appointment['doctor_id']) ? $next_appointment['doctor_id'] : (!empty($next_appointment['provider_id']) ? $next_appointment['provider_id'] : 0);
            if ($doc_id) {
                $saved_avatar = get_user_meta($doc_id, 'dior_profile_image', true)
                    ?: get_user_meta($doc_id, 'doctor_avatar', true)
                    ?: get_user_meta($doc_id, 'profile_picture', true)
                    ?: get_user_meta($doc_id, 'profile_image', true)
                    ?: get_user_meta($doc_id, 'avatar_url', true);
                if (!empty($saved_avatar)) {
                    $ha_avatar = $saved_avatar;
                }
            }
        }
        ?>
        <div class="doctor-top">
          <div class="doctor-photo"><img src="<?php echo esc_url($ha_avatar); ?>" alt="Doctor"></div>
          <div class="doctor-copy">
            <div class="doctor-type">Telehealth Medical Care</div>
            <div class="doctor-role"><?php echo esc_html($ha_spec); ?></div>
            <div class="doctor-name"><?php echo esc_html($ha_name); ?></div>
            <div class="meta">
              <span><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg><?php echo esc_html($ha_date); ?></span>
              <span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/></svg><?php echo esc_html($ha_time); ?></span>
            </div>
          </div>
        </div>
        <div class="doctor-actions">
          <button class="primary-btn btn" data-switch-tab="consultation">Join Video Consultation Room</button>
          <button class="outline-btn btn" data-switch-tab="appointments">Reschedule</button>
        </div>
      </div>

      <div class="card upcoming" id="dior-overview-upcoming-card">
        <h3 class="section-title">Upcoming Consultation</h3>
        <?php 
        $display_list = !empty($upcoming_list) ? array_slice($upcoming_list, 0, 2) : [];
        if (empty($display_list)) {
            $display_list = [
                [
                    'doctor_name' => 'Dr. Diorca Aquino de la Cruz',
                    'provider_spec' => 'Board Certified Urgent Care Physician',
                    'date' => 'Sep 29, 2026',
                    'time' => '05:30 PM',
                    'avatar' => 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300'
                ],
                [
                    'doctor_name' => 'Dr. Diorca Aquino de la Cruz',
                    'provider_spec' => 'Board Certified Urgent Care Physician',
                    'date' => 'Oct 2, 2026',
                    'time' => '11:00 AM',
                    'avatar' => 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300'
                ]
            ];
        }
        foreach($display_list as $apt): 
            $doc_name = !empty($apt['doctor_name']) ? $apt['doctor_name'] : (!empty($apt['provider']) ? $apt['provider'] : 'Provider');
            $doc_spec = !empty($apt['provider_spec']) ? $apt['provider_spec'] : 'Board Certified Urgent Care Physician';
            
            $doc_id = !empty($apt['doctor_id']) ? $apt['doctor_id'] : (!empty($apt['provider_id']) ? $apt['provider_id'] : 0);
            $doc_avatar = !empty($apt['avatar']) ? $apt['avatar'] : 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=300';
            if ($doc_id) {
                $saved_doc_avatar = get_user_meta($doc_id, 'dior_profile_image', true)
                    ?: get_user_meta($doc_id, 'doctor_avatar', true)
                    ?: get_user_meta($doc_id, 'profile_picture', true)
                    ?: get_user_meta($doc_id, 'profile_image', true)
                    ?: get_user_meta($doc_id, 'avatar_url', true);
                if (!empty($saved_doc_avatar)) {
                    $doc_avatar = $saved_doc_avatar;
                }
            }
        ?>
        <div class="consult">
          <div class="consult-photo"><img src="<?php echo esc_url($doc_avatar); ?>" alt="Doctor"></div>
          <div>
            <div class="consult-role"><?php echo esc_html($doc_spec); ?></div>
            <div class="consult-name"><?php echo esc_html($doc_name); ?></div>
            <div class="consult-meta"><span>Γûª <?php echo esc_html($apt['date']); ?></span><span>Γù╖ <?php echo esc_html($apt['time']); ?></span></div>
            <button class="reschedule btn" data-switch-tab="appointments">Reschedule</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="card prescriptions">
        <div class="table-head">
          <h3 class="section-title">Prescriptions</h3>
          <a class="view-all" href="#" data-switch-tab="docs_meds">View All &rarr;</a>
        </div>
        <?php
        $rx_display = array_slice($prescriptions, 0, 4);
        if (empty($rx_display)):
        ?>
            <div style="padding: 28px 16px; text-align: center; color: #94A3B8; font-size: 13px;">
                <i class="fas fa-prescription-bottle-medical" style="font-size:24px;color:#CBD5E1;margin-bottom:8px;display:block;"></i>
                No active prescriptions yet.
            </div>
        <?php
        else:
            foreach($rx_display as $rx):
                $rx_name   = !empty($rx['title']) && $rx['title'] !== 'General Medical Prescription' ? $rx['title'] : (!empty($rx['name']) ? $rx['name'] : (!empty($rx['medication']) ? $rx['medication'] : 'Medication'));
                $rx_sub    = !empty($rx['medication']) && $rx['medication'] !== $rx_name ? $rx['medication'] : '';
                $rx_by     = !empty($rx['prescribed_by']) ? $rx['prescribed_by'] : 'Provider';
                $rx_status = !empty($rx['status']) ? $rx['status'] : 'Active';
                $rx_badge  = (strtolower($rx_status) === 'active') ? 'rx-active' : 'rx-inactive';
            ?>
            <div class="rx">
                <div>
                    <div class="rx-name"><?php echo esc_html($rx_name); ?></div>
                    <?php if ($rx_sub !== ''): ?>
                        <?php
                        $sub_lines = preg_split('/;\s*|\r\n|\n/', trim($rx_sub));
                        $sub_lines = array_filter(array_map('trim', (array)$sub_lines));
                        if (empty($sub_lines)) { $sub_lines = [$rx_sub]; }
                        ?>
                        <div style="font-size:11px;color:#64748B;display:flex;flex-direction:column;gap:2px;margin-top:2px;">
                            <?php foreach ($sub_lines as $sl): ?>
                                <div><?php echo esc_html($sl); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="rx-doctor"><?php echo esc_html($rx_by); ?></div>
                </div>
                <span class="rx-status <?php echo esc_attr($rx_badge); ?>"><?php echo esc_html(ucfirst($rx_status)); ?></span>
            </div>
            <?php endforeach;
        endif;
        ?>
      </div>
    </div>

  </div>


  <!-- Bootstrap 5 Pure Modal: Health Goals & Vitals -->
  <div class="modal fade" id="diorHealthGoalsModal" tabindex="-1" aria-labelledby="diorHealthGoalsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
        
        <div class="modal-header border-bottom py-3 px-4 bg-white">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
              <i class="fas fa-heart-pulse fs-6"></i>
            </div>
            <div>
              <h5 class="modal-title fw-bold text-dark mb-0 fs-6" id="diorHealthGoalsModalLabel">Health Goals &amp; Vitals</h5>
              <p class="text-muted small mb-0" style="font-size: 12px;">Update your body measurements (Height &amp; Weight) and track personalized health goals.</p>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="diorHealthGoalsForm" onsubmit="return diorSaveHealthGoalsAndVitals(event);" class="m-0">
          <div class="modal-body p-4 bg-white" style="max-height: calc(85vh - 140px); overflow-y: auto;">

            <!-- Section 1: Body Measurements -->
            <div class="card border border-light-subtle bg-light bg-opacity-50 rounded-3 mb-4">
              <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                  <span class="badge bg-primary text-white rounded-pill px-2 py-1"><i class="fas fa-ruler-combined"></i></span>
                  <span class="text-uppercase fw-bold text-primary small">Body Measurements</span>
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="dior-goal-input-height" class="form-label small fw-semibold text-secondary mb-1">
                      Height <span class="fw-normal text-muted">(cm or ft/in)</span>
                    </label>
                    <input type="text" id="dior-goal-input-height" class="form-control" placeholder="e.g. 175 cm or 5'9&quot;" value="<?php echo esc_attr($p_height); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="dior-goal-input-weight" class="form-label small fw-semibold text-secondary mb-1">
                      Weight <span class="fw-normal text-muted">(kg or lbs)</span>
                    </label>
                    <input type="text" id="dior-goal-input-weight" class="form-control" placeholder="e.g. 70 kg or 154 lbs" value="<?php echo esc_attr($p_weight); ?>">
                  </div>
                </div>
              </div>
            </div>

            <!-- Section 2: Health Goals -->
            <div>
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-primary text-white rounded-pill px-2 py-1"><i class="fas fa-bullseye"></i></span>
                  <span class="text-uppercase fw-bold text-primary small">Goals &amp; Progress</span>
                </div>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <span class="small text-muted me-1">Presets:</span>
                  <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" style="font-size: 11px;" onclick="diorAddPresetGoal('Weight Loss Target', '65 kg', 60)">+ Weight</button>
                  <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" style="font-size: 11px;" onclick="diorAddPresetGoal('Daily Steps', '10,000 steps', 75)">+ Steps</button>
                  <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" style="font-size: 11px;" onclick="diorAddPresetGoal('Water Intake', '3.0 L / day', 80)">+ Water</button>
                  <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" style="font-size: 11px;" onclick="diorAddPresetGoal('Sleep Target', '8 Hours', 85)">+ Sleep</button>
                </div>
              </div>

              <!-- Dynamic Goal Items Container -->
              <div id="dior-goals-inputs-container" class="d-flex flex-column gap-3 mb-3">
                <!-- Rendered via Bootstrap JavaScript -->
              </div>

              <div>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="diorAddGoalRow('', '', 50)">
                  <i class="fas fa-plus me-1"></i> Add Another Goal
                </button>
              </div>
            </div>

          </div>

          <div class="modal-footer border-top py-3 px-4 bg-light d-flex justify-content-end align-items-center gap-2">
            <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" id="dior-btn-save-goals-submit" class="btn btn-primary px-4 fw-bold rounded-3">
              <i class="fas fa-check me-1"></i> Save Goals &amp; Vitals
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>

  <script>
  (function() {
    window.diorInitialHealthGoals = <?php echo wp_json_encode(!empty($display_goals) ? array_values($display_goals) : []); ?>;

    function diorEscapeHtml(str) {
      return String(str || '').replace(/[&<>'"]/g, function(c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c];
      });
    }

    function diorRenderGoalItemRow(name, val, pct) {
      name = name || '';
      val = val || '';
      pct = (typeof pct !== 'undefined' && pct !== '') ? parseInt(pct, 10) : 50;
      if (isNaN(pct)) pct = 50;
      pct = Math.max(0, Math.min(100, pct));

      var div = document.createElement('div');
      div.className = 'card border border-light-subtle shadow-sm p-3 bg-white rounded-3 dior-goal-item-row';
      div.innerHTML = '<div class="row g-2 align-items-center mb-2">' +
        '<div class="col-12 col-md-5">' +
          '<input type="text" class="form-control form-control-sm fw-semibold dior-goal-item-name" placeholder="Goal Name (e.g. Daily Water)" value="' + diorEscapeHtml(name) + '" required>' +
        '</div>' +
        '<div class="col-10 col-md-5">' +
          '<input type="text" class="form-control form-control-sm dior-goal-item-val" placeholder="Target / Status (e.g. 3.0 L)" value="' + diorEscapeHtml(val) + '">' +
        '</div>' +
        '<div class="col-2 col-md-2 text-end">' +
          '<button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.closest(\'.dior-goal-item-row\').remove()" title="Delete Goal">' +
            '<i class="fas fa-trash-can"></i>' +
          '</button>' +
        '</div>' +
      '</div>' +
      '<div class="d-flex align-items-center gap-3">' +
        '<span class="small fw-semibold text-muted text-nowrap" style="font-size: 11.5px;">Progress:</span>' +
        '<input type="range" class="form-range flex-grow-1 dior-goal-item-range" min="0" max="100" value="' + pct + '" oninput="this.nextElementSibling.textContent = this.value + \'%\'">' +
        '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small fw-bold" style="width: 46px; text-align: center;">' + pct + '%</span>' +
      '</div>';
      return div;
    }

    window.diorAddGoalRow = function(name, val, pct) {
      var container = document.getElementById('dior-goals-inputs-container');
      if (!container) return;
      container.appendChild(diorRenderGoalItemRow(name, val, pct));
    };

    window.diorAddPresetGoal = function(name, val, pct) {
      var container = document.getElementById('dior-goals-inputs-container');
      if (!container) return;
      var existing = container.querySelectorAll('.dior-goal-item-name');
      for (var i = 0; i < existing.length; i++) {
        if (existing[i].value.trim().toLowerCase() === name.toLowerCase()) {
          existing[i].focus();
          return;
        }
      }
      container.appendChild(diorRenderGoalItemRow(name, val, pct));
    };

    window.diorOpenHealthGoalsModal = function() {
      var container = document.getElementById('dior-goals-inputs-container');
      if (!container) return;
      container.innerHTML = '';

      var hInput = document.getElementById('dior-goal-input-height');
      var wInput = document.getElementById('dior-goal-input-weight');
      var hElem = document.getElementById('dior-patient-metric-height');
      var wElem = document.getElementById('dior-patient-metric-weight');
      if (hInput && (!hInput.value || hInput.value.trim() === '') && hElem) {
        var hText = hElem.textContent.trim();
        if (hText !== '—') hInput.value = hText;
      }
      if (wInput && (!wInput.value || wInput.value.trim() === '') && wElem) {
        var wText = wElem.textContent.trim();
        if (wText !== '—') wInput.value = wText;
      }

      var goals = window.diorInitialHealthGoals || [];
      if (goals && goals.length > 0) {
        goals.forEach(function(g) {
          container.appendChild(diorRenderGoalItemRow(g.name, g.val, g.pct));
        });
      } else {
        container.appendChild(diorRenderGoalItemRow('', '', 50));
      }

      var modalEl = document.getElementById('diorHealthGoalsModal');
      if (!modalEl) return;
      if (window.bootstrap && window.bootstrap.Modal) {
        var bsModal = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
        bsModal.show();
      } else if (typeof jQuery !== 'undefined' && typeof jQuery(modalEl).modal === 'function') {
        jQuery(modalEl).modal('show');
      } else {
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
      }
    };

    window.diorCloseHealthGoalsModal = function() {
      var modalEl = document.getElementById('diorHealthGoalsModal');
      if (!modalEl) return;
      if (window.bootstrap && window.bootstrap.Modal) {
        var bsModal = window.bootstrap.Modal.getInstance(modalEl) || window.bootstrap.Modal.getOrCreateInstance(modalEl);
        if (bsModal) {
          bsModal.hide();
        }
      }
      if (typeof jQuery !== 'undefined' && typeof jQuery(modalEl).modal === 'function') {
        jQuery(modalEl).modal('hide');
      }
      modalEl.classList.remove('show');
      modalEl.style.display = 'none';
      modalEl.setAttribute('aria-hidden', 'true');
      modalEl.removeAttribute('aria-modal');
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('overflow');
      document.body.style.removeProperty('padding-right');
      var backdrops = document.querySelectorAll('.modal-backdrop');
      backdrops.forEach(function(b) { b.remove(); });
    };

    window.diorSaveHealthGoalsAndVitals = function(event) {
      if (event) event.preventDefault();

      var submitBtn = document.getElementById('dior-btn-save-goals-submit');
      var originalHtml = submitBtn ? submitBtn.innerHTML : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
      }

      var heightVal = (document.getElementById('dior-goal-input-height') ? document.getElementById('dior-goal-input-height').value : '').trim();
      var weightVal = (document.getElementById('dior-goal-input-weight') ? document.getElementById('dior-goal-input-weight').value : '').trim();

      var rows = document.querySelectorAll('#dior-goals-inputs-container .dior-goal-item-row');
      var goals = [];
      rows.forEach(function(row) {
        var nameInput = row.querySelector('.dior-goal-item-name');
        var valInput = row.querySelector('.dior-goal-item-val');
        var rangeInput = row.querySelector('.dior-goal-item-range');
        if (nameInput && nameInput.value.trim() !== '') {
          goals.push({
            name: nameInput.value.trim(),
            val: valInput ? valInput.value.trim() : '',
            pct: rangeInput ? parseInt(rangeInput.value, 10) : 50
          });
        }
      });

      var ajaxUrl = (typeof dior_vars !== 'undefined' && dior_vars.ajax_url) || 
                    (typeof dior_patient_dashboard !== 'undefined' && dior_patient_dashboard.ajax_url) || 
                    (typeof dior_patient_live_vars !== 'undefined' && dior_patient_live_vars.ajax_url) || 
                    <?php echo json_encode(admin_url('admin-ajax.php')); ?>;

      var nonce = (typeof dior_vars !== 'undefined' && dior_vars.nonce) || 
                  (typeof dior_patient_dashboard !== 'undefined' && dior_patient_dashboard.portal_nonce) || 
                  (typeof dior_patient_live_vars !== 'undefined' && dior_patient_live_vars.nonce) || 
                  <?php echo json_encode(wp_create_nonce('dior_portal_nonce')); ?>;

      var formData = new FormData();
      formData.append('action', 'dior_patient_save_health_goals_and_vitals');
      formData.append('nonce', nonce);
      formData.append('height', heightVal);
      formData.append('weight', weightVal);
      formData.append('goals', JSON.stringify(goals));

      fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      })
      .then(function(res) {
        return res.text().then(function(text) {
          try {
            return JSON.parse(text);
          } catch(e) {
            console.error('Non-JSON server response:', text);
            throw new Error('Server returned invalid response. Please try again.');
          }
        });
      })
      .then(function(data) {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalHtml;
        }

        if (data && data.success) {
          // 1. Update height & weight in DOM
          var hElem = document.getElementById('dior-patient-metric-height');
          var wElem = document.getElementById('dior-patient-metric-weight');
          if (hElem && data.data && typeof data.data.height_display !== 'undefined') {
            hElem.textContent = data.data.height_display;
          }
          if (wElem && data.data && typeof data.data.weight_display !== 'undefined') {
            wElem.textContent = data.data.weight_display;
          }

          // 2. Update Goals Card body
          var goalsBody = document.getElementById('dior-goals-card-body');
          if (goalsBody && data.data && typeof data.data.goals_html !== 'undefined') {
            goalsBody.innerHTML = data.data.goals_html;
          }

          // 3. Update cached state
          window.diorInitialHealthGoals = (data.data && data.data.goals) ? data.data.goals : goals;

          // 4. AUTOMATIC CLOSE MODAL (ONLY ON SUCCESS)
          diorCloseHealthGoalsModal();

          // 5. SHOW SUCCESS MESSAGE
          var msg = (data.data && data.data.message) ? data.data.message : 'Health goals & vitals saved successfully!';
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'success',
              title: 'Saved Successfully!',
              text: msg,
              timer: 2000,
              showConfirmButton: false
            });
          } else {
            alert(msg);
          }
        } else {
          // On failure, DO NOT close modal so user can correct and retry
          var failMsg = (data && data.data && data.data.message) ? data.data.message : 'Failed to update health goals.';
          if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Error', text: failMsg });
          } else {
            alert(failMsg);
          }
        }
      })
      .catch(function(err) {
        // On error, DO NOT close modal
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalHtml;
        }
        var errMsg = err && err.message ? err.message : 'An unexpected error occurred. Please try again.';
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'error', title: 'Error', text: errMsg });
        } else {
          alert(errMsg);
        }
      });

      return false;
    };
  })();
  </script>
</section>
