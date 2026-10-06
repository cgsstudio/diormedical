<<<<<<< HEAD
<section class="dior-tab-panel active custom-patient-overview" id="tab-overview">
<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/patient-overview-custom.css?v=' . time()); ?>">
  <div class="content-head">
    <div>
      <?php $display_first_name = !empty($profile['first_name']) ? ucfirst(strtolower($profile['first_name'])) : 'Mia'; ?>
      <h1>Welcome Back, <?php echo esc_html(strtoupper($display_first_name)); ?></h1>
      <p class="subtitle">Manage your telehealth visits, prescriptions, and medical records.</p>
    </div>
    <button class="gold-btn" data-switch-tab="appointments">
      <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>
      Book An Appointment
    </button>
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
            <div class="patient-photo">
                <?php if (!empty($profile['avatar_url'])): ?>
                <img src="<?php echo esc_url($profile['avatar_url']); ?>" alt="Profile">
                <?php else: ?>
                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200" alt="Mia Song">
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
                <div class="metric"><strong><?php echo esc_html($h_display); ?></strong><span>Height</span></div>
                <div class="metric"><strong><?php echo esc_html($w_display); ?></strong><span>Weight</span></div>
              </div>
            </div>
          </div>
          <button class="edit-btn" data-switch-tab="profile">Edit Profile</button>
        </div>

        <div class="card goals-card">
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
        <table class="lab-table">
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
      <div class="card doctor-card">
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
          <button class="primary-btn" data-switch-tab="consultation">Join Video Consultation Room</button>
          <button class="outline-btn" data-switch-tab="appointments">Reschedule</button>
        </div>
      </div>

      <div class="card upcoming">
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
            <div class="consult-meta"><span>▦ <?php echo esc_html($apt['date']); ?></span><span>◷ <?php echo esc_html($apt['time']); ?></span></div>
            <button class="reschedule" data-switch-tab="appointments">Reschedule</button>
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
        if (empty($rx_display)) {
            $rx_display = [
                ['name' => 'Cetirizine 10 mg', 'prescribed_by' => 'Dr. Diorca Aquino de la Cruz', 'status' => 'Active'],
                ['name' => 'Vitamin D3 1,000 IU', 'prescribed_by' => 'Dr. Diorca Aquino de la Cruz', 'status' => 'Active'],
                ['name' => 'Ibuprofen 200 mg', 'prescribed_by' => 'Dr. Diorca Aquino de la Cruz', 'status' => 'Inactive'],
                ['name' => 'Omeprazole 20 mg', 'prescribed_by' => 'Dr. Diorca Aquino de la Cruz', 'status' => 'Inactive'],
            ];
        }
        foreach($rx_display as $rx):
            $rx_name   = !empty($rx['medication_name']) ? $rx['medication_name'] : (!empty($rx['drug_name']) ? $rx['drug_name'] : (!empty($rx['name']) ? $rx['name'] : 'Medication'));
            $rx_dosage = !empty($rx['dosage']) ? ' ' . $rx['dosage'] : '';
            $rx_by     = !empty($rx['prescribed_by']) ? $rx['prescribed_by'] : 'Provider';
            $rx_status = !empty($rx['status']) ? $rx['status'] : 'Active';
            $rx_badge  = (strtolower($rx_status) === 'active') ? 'rx-active' : 'rx-inactive';
        ?>
        <div class="rx"><div><div class="rx-name"><?php echo esc_html($rx_name . $rx_dosage); ?></div><div class="rx-doctor"><?php echo esc_html($rx_by); ?></div></div><span class="rx-status <?php echo esc_attr($rx_badge); ?>"><?php echo esc_html(ucfirst($rx_status)); ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</section>
=======
<section class="dior-tab-panel active" id="tab-overview">

                    <!-- Sub-Header Bar: Page Title & Actions -->
                    <div class="dior-page-title-bar new-design">
                        <div class="title-left">
                            <?php $display_first_name = !empty($profile['first_name']) ? ucfirst(strtolower($profile['first_name'])) : 'Mia'; ?>
                            <h2>Welcome Back, <?php echo esc_html($display_first_name); ?></h2>
                            <p>Manage your telehealth visits, prescriptions, and medical records.</p>
                        </div>
                        <a href="#" class="dior-btn-book-appointment" data-switch-tab="appointments">
                            <i class="fa-solid fa-calendar-plus"></i> Book An Appointment
                        </a>
                    </div>

                    <!-- Layout: Left Column (65%) & Right Column (35%) -->
                    <div class="dior-dashboard-grid-layout">
                        
                        <!-- LEFT COLUMN -->
                        <div class="dior-dash-col-left">
                            
                            <!-- 3 Stat Cards Row -->
                            <div class="dior-dash-stats-row">
                                <div class="dior-dash-stat-card">
                                    <div class="stat-card-header">
                                        <div class="stat-icon-box blue-tint">
                                            <i class="fa-solid fa-user-doctor"></i>
                                        </div>
                                    </div>
                                    <div class="stat-card-body">
                                        <h3><?php echo esc_html(count($appointments)); ?></h3>
                                        <p>Total Consultations</p>
                                    </div>
                                </div>
                                <div class="dior-dash-stat-card">
                                    <div class="stat-card-header">
                                        <div class="stat-icon-box green-tint">
                                            <i class="fa-solid fa-prescription-bottle-medical"></i>
                                        </div>
                                    </div>
                                    <div class="stat-card-body">
                                        <h3><?php echo esc_html(count($prescriptions)); ?></h3>
                                        <p>Active Prescriptions</p>
                                    </div>
                                </div>
                                <div class="dior-dash-stat-card">
                                    <div class="stat-card-header">
                                        <div class="stat-icon-box purple-tint">
                                            <i class="fa-regular fa-calendar-check"></i>
                                        </div>
                                    </div>
                                    <div class="stat-card-body">
                                        <h3><?php echo esc_html(count($upcoming_list)); ?></h3>
                                        <p>Upcoming Appointments</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Profile & Health Goals Row -->
                            <div class="dior-dash-profile-goals-row">
                                <!-- Profile Card -->
                                <div class="dior-dash-profile-card dior-box-card">
                                    <div class="profile-card-header">
                                        <div class="profile-avatar-large">
                                            <?php if (!empty($profile['avatar_url'])): ?>
                                            <img src="<?php echo esc_url($profile['avatar_url']); ?>" alt="Profile">
                                            <?php else: ?>
                                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200" alt="Mia Song">
                                            <?php endif; ?>
                                        </div>
                                        <div class="profile-info-large">
                                            <h4><?php echo esc_html(!empty($profile['full_name']) ? $profile['full_name'] : 'Mia Song'); ?></h4>
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
                                            <span class="profile-age-text"><?php echo esc_html($age_str ?: '25 Years Old'); ?></span>
                                            
                                            <div class="profile-card-stats">
                                                <?php
                                                $p_height = !empty($profile['height']) ? trim($profile['height']) : '170';
                                                $p_weight = !empty($profile['weight']) ? trim($profile['weight']) : '60';

                                                $h_display = (is_numeric($p_height)) ? $p_height . ' Cm' : (stristr($p_height, 'cm') ? str_ireplace('cm', 'Cm', $p_height) : $p_height . ' Cm');
                                                $w_display = (is_numeric($p_weight)) ? $p_weight . 'kg' : (stristr($p_weight, 'kg') ? str_ireplace('kg', 'kg', $p_weight) : $p_weight . 'kg');
                                                ?>
                                                <div class="pc-stat">
                                                    <strong><?php echo esc_html($h_display); ?></strong>
                                                    <span>Height</span>
                                                </div>
                                                <div class="pc-divider"></div>
                                                <div class="pc-stat">
                                                    <strong><?php echo esc_html($w_display); ?></strong>
                                                    <span>Weight</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="profile-card-action">
                                        <button type="button" class="dior-btn-outline-pill" data-switch-tab="profile">
                                            Edit Profile
                                        </button>
                                    </div>
                                </div>

                                <!-- Health Goals -->
                                <div class="dior-dash-goals-card dior-box-card">
                                    <div class="box-title-row">
                                        <h3 class="box-title">Health Goals</h3>
                                    </div>
                                    <?php
                                    $health_goals = get_user_meta($user_id, 'dior_health_goals', true);
                                    $health_goals = is_array($health_goals) ? $health_goals : [];

                                    if (empty($health_goals)) {
                                        $display_goals = [
                                            ['name' => 'Weight Loss', 'val' => '75.5 / 60 kg', 'pct' => 70, 'color' => 'blue'],
                                            ['name' => 'Water Intake', 'val' => '1,500 / 2,500 ml', 'pct' => 60, 'color' => 'teal'],
                                            ['name' => 'Sleep', 'val' => '7 / 8 hrs', 'pct' => 87, 'color' => 'indigo']
                                        ];
                                    } else {
                                        $display_goals = $health_goals;
                                    }
                                    ?>
                                    <div class="goals-list">
                                        <?php foreach($display_goals as $goal): ?>
                                        <div class="goal-item">
                                            <div class="goal-label-row">
                                                <span class="g-name"><?php echo esc_html($goal['name']); ?></span>
                                                <span class="g-val"><?php echo esc_html($goal['val']); ?></span>
                                            </div>
                                            <div class="goal-progress-track">
                                                <div class="goal-progress-fill <?php echo esc_attr($goal['color'] ?? 'blue'); ?>" data-progress="<?php echo esc_attr(min(100, (int)($goal['pct'] ?? 50))); ?>"></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Previous Consultation Table -->
                            <div class="dior-dash-table-card dior-box-card">
                                <div class="box-title-row">
                                    <h3 class="box-title">Previous Consultation</h3>
                                </div>
                                <div class="table-responsive">
                                    <table class="dior-clean-table">
                                        <thead>
                                            <tr>
                                                <th class="dior-ic-3727071611">Consultation Name</th>
                                                <th class="dior-ic-3727071611">Doctor Name</th>
                                                <th class="dior-ic-3727071611">Date</th>
                                                <th class="dior-ic-3727071611">Time</th>
                                                <th class="dior-ic-3d3f5f8b0f">View All</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $prev_apts = array_slice($past_list, 0, 4);
                                            foreach($prev_apts as $prev_apt):
                                                $pa_cond = !empty($prev_apt['condition']) ? $prev_apt['condition'] : (!empty($prev_apt['condition_name']) ? $prev_apt['condition_name'] : 'General Consultation');
                                                $pa_doc  = !empty($prev_apt['provider']) ? $prev_apt['provider'] : (!empty($prev_apt['doctor_name']) ? $prev_apt['doctor_name'] : 'Dr. Evelyn Vance, MD');
                                                $pa_date = !empty($prev_apt['date']) ? $prev_apt['date'] : (!empty($prev_apt['appt_date']) ? $prev_apt['appt_date'] : 'Sep 10, 2026');
                                                $pa_time = !empty($prev_apt['time']) ? $prev_apt['time'] : (!empty($prev_apt['appt_time']) ? $prev_apt['appt_time'] : '03:00 PM');
                                            ?>
                                            <tr>
                                                <td class="dior-ic-c9bc719962"><?php echo esc_html($pa_cond); ?></td>
                                                <td class="dior-ic-fac1cedef0"><?php echo esc_html($pa_doc); ?></td>
                                                <td class="dior-ic-36218bab0a"><?php echo esc_html($pa_date); ?></td>
                                                <td class="dior-ic-36218bab0a"><?php echo esc_html($pa_time); ?></td>
                                                <td class="dior-ic-d85d3af1e2">
                                                    <a href="#" class="view-details-link" data-switch-tab="appointments">View Details &rarr;</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Latest Lab Results -->
                            <div class="dior-dash-table-card dior-box-card">
                                <div class="box-title-row">
                                    <h3 class="box-title">Latest Lab Results</h3>
                                </div>
                                <div class="table-responsive">
                                    <table class="dior-clean-table">
                                        <thead>
                                            <tr>
                                                <th class="dior-ic-3727071611">Test Name</th>
                                                <th class="dior-ic-3727071611">Date</th>
                                                <th class="dior-ic-d5c209e67c">Status</th>
                                                <th class="dior-ic-3727071611">Ordered By</th>
                                                <th class="dior-ic-d5c209e67c">Action</th>
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
                                            $lab_docs = array_slice($lab_docs, 0, 4);
                                            foreach($lab_docs as $lab):
                                                $lab_name   = !empty($lab['title']) ? $lab['title'] : (!empty($lab['name']) ? $lab['name'] : 'Lab Test');
                                                $lab_date   = !empty($lab['date']) ? $lab['date'] : 'Sep 10, 2026';
                                                $lab_status = !empty($lab['status']) ? $lab['status'] : 'Normal';
                                                $lab_by     = !empty($lab['ordered_by']) ? $lab['ordered_by'] : (!empty($lab['doctor_name']) ? $lab['doctor_name'] : 'Dr. Evelyn Vance, MD');
                                                $lab_url    = !empty($lab['file_url']) ? $lab['file_url'] : '#';
                                                $badge_cls  = (strtolower($lab_status) === 'normal') ? 'badge-normal' : 'badge-abnormal';
                                            ?>
                                            <tr>
                                                <td class="dior-ic-c9bc719962"><?php echo esc_html($lab_name); ?></td>
                                                <td class="dior-ic-36218bab0a"><?php echo esc_html($lab_date); ?></td>
                                                <td class="dior-ic-d5c209e67c"><span class="badge <?php echo esc_attr($badge_cls); ?>"><?php echo esc_html($lab_status); ?></span></td>
                                                <td class="dior-ic-fac1cedef0"><?php echo esc_html($lab_by); ?></td>
                                                <td class="dior-ic-d5c209e67c">
                                                    <a href="<?php echo esc_url($lab_url); ?>" target="_blank" class="dior-action-download" title="Download Report"><i class="fa-solid fa-download"></i></a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div> <!-- /LEFT COLUMN -->


                        <!-- RIGHT COLUMN -->
                        <div class="dior-dash-col-right">
                            
                            <!-- Highlighted Next Appointment Hero Card -->
                            <?php 
                            $ha_name = 'Dr. Evelyn Vance, MD';
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
                            <div class="dior-highlight-appt-card dior-box-card">
                                <div class="ha-header">
                                    <div class="ha-avatar-wrap">
                                        <img src="<?php echo esc_url($ha_avatar); ?>" alt="Doctor" class="ha-avatar">
                                    </div>
                                    <div class="ha-doc-info">
                                        <span class="ha-clinic">Telehealth Medical Care</span>
                                        <h4 class="ha-doc-name"><?php echo esc_html($ha_name); ?></h4>
                                        <span class="ha-doc-spec"><?php echo esc_html($ha_spec); ?></span>
                                        <div class="ha-datetime-row">
                                            <span><i class="fa-regular fa-calendar-days"></i> <?php echo esc_html($ha_date); ?></span>
                                            <span><i class="fa-regular fa-clock"></i> <?php echo esc_html($ha_time); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="ha-actions">
                                    <button type="button" class="dior-btn-join" data-switch-tab="consultation">Join Video Consultation Room</button>
                                    <button type="button" class="dior-btn-reschedule" data-switch-tab="appointments">Reschedule</button>
                                </div>
                            </div>

                            <!-- Upcoming Consultation List Card -->
                            <div class="dior-upcoming-list-card dior-box-card">
                                <div class="box-title-row">
                                    <h3 class="box-title">Upcoming Consultation</h3>
                                </div>
                                <div class="uc-list">
                                    <?php 
                                    $display_list = !empty($upcoming_list) ? array_slice($upcoming_list, 0, 2) : [];
                                    if (empty($display_list)) {
                                        $display_list = [
                                            [
                                                'doctor_name' => 'Dr. James Wilson, MD',
                                                'provider_spec' => 'Board Certified Urgent Care Physician',
                                                'date' => 'Sep 29, 2026',
                                                'time' => '05:30 PM',
                                                'avatar' => 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&q=80&w=300'
                                            ],
                                            [
                                                'doctor_name' => 'Dr. Emily Brooks, MD',
                                                'provider_spec' => 'Board Certified Urgent Care Physician',
                                                'date' => 'Oct 2, 2026',
                                                'time' => '11:00 AM',
                                                'avatar' => 'https://images.unsplash.com/photo-1594824813566-78a93272d3e9?auto=format&fit=crop&q=80&w=300'
                                            ]
                                        ];
                                    }
                                    
                                    foreach($display_list as $apt): 
                                        $doc_name = !empty($apt['doctor_name']) ? $apt['doctor_name'] : (!empty($apt['provider']) ? $apt['provider'] : 'Provider');
                                        $doc_spec = !empty($apt['provider_spec']) ? $apt['provider_spec'] : 'Board Certified Urgent Care Physician';
                                        
                                        $doc_id = !empty($apt['doctor_id']) ? $apt['doctor_id'] : (!empty($apt['provider_id']) ? $apt['provider_id'] : 0);
                                        $doc_avatar = !empty($apt['avatar']) ? $apt['avatar'] : 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&q=80&w=300';
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
                                    <div class="uc-item">
                                        <img src="<?php echo esc_url($doc_avatar); ?>" alt="Doctor" class="uc-avatar">
                                        <div class="uc-info">
                                            <h4 class="uc-doc-name"><?php echo esc_html($doc_name); ?></h4>
                                            <span class="uc-doc-spec"><?php echo esc_html($doc_spec); ?></span>
                                            <div class="uc-datetime-row">
                                                <span><i class="fa-regular fa-calendar-days"></i> <?php echo esc_html($apt['date']); ?></span>
                                                <span><i class="fa-regular fa-clock"></i> <?php echo esc_html($apt['time']); ?></span>
                                            </div>
                                            <div class="uc-action">
                                                <button type="button" class="dior-btn-reschedule-sm" data-switch-tab="appointments">Reschedule</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Prescriptions List Card -->
                            <div class="dior-prescriptions-card dior-box-card">
                                <div class="box-title-row">
                                    <h3 class="box-title">Prescriptions</h3>
                                    <a href="#" class="view-all-link" data-switch-tab="docs_meds">View All &rarr;</a>
                                </div>
                                <div class="rx-list">
                                    <?php
                                    $rx_display = array_slice($prescriptions, 0, 4);
                                    if (empty($rx_display)) {
                                        $rx_display = [
                                            ['name' => 'Cetirizine 10 mg', 'prescribed_by' => 'Dr. Evelyn Vance, MD', 'status' => 'Active'],
                                            ['name' => 'Vitamin D3 1,000 IU', 'prescribed_by' => 'Dr. James Wilson, MD', 'status' => 'Active'],
                                            ['name' => 'Ibuprofen 200 mg', 'prescribed_by' => 'Dr. Emily Brooks, MD', 'status' => 'Inactive'],
                                            ['name' => 'Omeprazole 20 mg', 'prescribed_by' => 'Dr. Evelyn Vance, MD', 'status' => 'Inactive'],
                                        ];
                                    }
                                    foreach($rx_display as $rx):
                                        $rx_name   = !empty($rx['medication_name']) ? $rx['medication_name'] : (!empty($rx['drug_name']) ? $rx['drug_name'] : (!empty($rx['name']) ? $rx['name'] : 'Medication'));
                                        $rx_dosage = !empty($rx['dosage']) ? ' ' . $rx['dosage'] : '';
                                        $rx_by     = !empty($rx['prescribed_by']) ? $rx['prescribed_by'] : 'Provider';
                                        $rx_status = !empty($rx['status']) ? $rx['status'] : 'Active';
                                        $rx_badge  = (strtolower($rx_status) === 'active') ? 'badge-active' : 'badge-inactive';
                                    ?>
                                    <div class="rx-item">
                                        <div class="rx-info">
                                            <h4><?php echo esc_html($rx_name . $rx_dosage); ?></h4>
                                            <span><?php echo esc_html($rx_by); ?></span>
                                        </div>
                                        <span class="badge <?php echo esc_attr($rx_badge); ?>"><?php echo esc_html($rx_status); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div> <!-- /RIGHT COLUMN -->

                    </div>

                </section>


                <!-- ============================================================== -->
                <!-- 1. PROFILE & ELIGIBILITY TAB -->
                <!-- ============================================================== -->
>>>>>>> fa0e02d91376b068a5cd18ba25d29811366c5101
