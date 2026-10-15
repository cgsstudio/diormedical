<?php
$appointments = is_array($appointments ?? null) ? $appointments : [];
$documents = is_array($documents ?? null) ? $documents : [];
$questionnaires = is_array($questionnaires ?? null) ? $questionnaires : [];
$prescriptions = is_array($prescriptions ?? null) ? $prescriptions : [];
$encounters = is_array($encounters ?? null) ? $encounters : [];
$profile = is_array($profile ?? null) ? $profile : [];
?>
<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-medical_record">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Medical Record</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Medical Record</span></li>
            </ul>
        </div>
    </div>
 
                    <div class="section-body dior-ic-de85b14db8">
                        <div class="row g-3 mb-4 no-print dior-ic-57fbdcec40">
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 dior-ic-0fcdb8259f">
                                <div class="card kpi-mini-card border-0 shadow-sm p-3 h-100 dior-ic-702f6c09ec">
                                    <div class="d-flex align-items-center gap-3 dior-ic-b7f55b7e4a">
                                        <div class="kpi-icon-box bg-blue-gradient text-white rounded-14 flex-shrink-0 shadow-sm dior-ic-7e3bf34afe">
                                            <i class="fas fa-stream"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden dior-ic-aea598fd73">
                                            <div class="d-flex align-items-center justify-content-between mb-1 dior-ic-bce91fe330">
                                                <span class="text-xs text-muted font-weight-600 text-uppercase text-truncate dior-ic-d48d9220c2">Milestones</span>
                                                <span class="badge bg-primary-subtle text-primary rounded-pill text-2xs dior-ic-b9bd20e3e0">Active</span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-dark text-truncate dior-ic-c4e98ce9b3"><?php echo esc_html(count($appointments) + count($documents) + count($questionnaires)); ?> Care Events</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 dior-ic-0fcdb8259f">
                                <div class="card kpi-mini-card border-0 shadow-sm p-3 h-100 dior-ic-702f6c09ec">
                                    <div class="d-flex align-items-center gap-3 dior-ic-b7f55b7e4a">
                                        <div class="kpi-icon-box bg-cyan-gradient text-white rounded-14 flex-shrink-0 shadow-sm dior-ic-95ecf5b0d8">
                                            <i class="fas fa-heartbeat"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden dior-ic-aea598fd73">
                                            <div class="d-flex align-items-center justify-content-between mb-1 dior-ic-bce91fe330">
                                                <span class="text-xs text-muted font-weight-600 text-uppercase text-truncate dior-ic-d48d9220c2">Care Phase</span>
                                                <span class="badge bg-info-subtle text-info rounded-pill text-2xs dior-ic-c37f987d84">Live Care</span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-dark text-truncate dior-ic-c4e98ce9b3"><?php echo esc_html($appointments[0]['condition'] ?? 'Active Care'); ?></h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 dior-ic-0fcdb8259f">
                                <div class="card kpi-mini-card border-0 shadow-sm p-3 h-100 dior-ic-702f6c09ec">
                                    <div class="d-flex align-items-center gap-3 dior-ic-b7f55b7e4a">
                                        <div class="kpi-icon-box bg-purple-gradient text-white rounded-14 flex-shrink-0 shadow-sm dior-ic-2a23691ffa">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden dior-ic-aea598fd73">
                                            <div class="d-flex align-items-center justify-content-between mb-1 dior-ic-bce91fe330">
                                                <span class="text-xs text-muted font-weight-600 text-uppercase text-truncate dior-ic-d48d9220c2">Lead Physician</span>
                                                <span class="badge bg-purple-subtle text-purple rounded-pill text-2xs dior-ic-f6785667b0">Cardiology</span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-dark text-truncate dior-ic-c4e98ce9b3"><?php echo esc_html($appointments[0]['provider'] ?? 'Attending Physician'); ?></h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 dior-ic-0fcdb8259f">
                                <div class="card kpi-mini-card border-0 shadow-sm p-3 h-100 dior-ic-702f6c09ec">
                                    <div class="d-flex align-items-center gap-3 dior-ic-b7f55b7e4a">
                                        <div class="kpi-icon-box bg-emerald-gradient text-white rounded-14 flex-shrink-0 shadow-sm dior-ic-f20b23555a">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden dior-ic-aea598fd73">
                                            <div class="d-flex align-items-center justify-content-between mb-1 dior-ic-bce91fe330">
                                                <span class="text-xs text-muted font-weight-600 text-uppercase text-truncate dior-ic-d48d9220c2">EMR Security</span>
                                                <span class="badge bg-success-subtle text-success rounded-pill text-2xs dior-ic-4db3afa214">HIPAA</span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-success text-truncate dior-ic-c2f54c19e7"><i class="fas fa-lock me-1 text-xs"></i> Encrypted</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-16 mb-4 no-print dior-ic-18f15f6b14">
                            <div class="card-body p-3 dior-ic-30853ea1b7">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <div class="d-flex flex-wrap gap-2 flex-grow-1">
                                        <button type="button" class="btn btn-sm rounded-pill btn-primary dior-medical-record-filter is-active px-3 shadow-sm" data-filter="ALL"><i class="fas fa-th-large me-1"></i> All Events (<?php echo esc_html(count($appointments) + count($documents) + count($questionnaires) + count($prescriptions)); ?>)</button>
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-primary dior-medical-record-filter px-3 bg-white" data-filter="CONSULTATION"><i class="fas fa-stethoscope me-1 text-primary"></i> Consultations</button>
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-primary dior-medical-record-filter px-3 bg-white" data-filter="PRESCRIPTION"><i class="fas fa-pills me-1 text-purple"></i> Prescriptions</button>
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-primary dior-medical-record-filter px-3 bg-white" data-filter="DIAGNOSTIC"><i class="fas fa-file-medical-alt me-1 text-info"></i> Diagnostics &amp; Scans</button>
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-primary dior-medical-record-filter px-3 bg-white" data-filter="PROCEDURE"><i class="fas fa-user-md me-1 text-danger"></i> Procedures</button>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-start justify-content-md-end">
                                        <div class="input-group search-input-group dior-bs-search-group shadow-sm" style="border-radius: 20px; overflow: hidden; width: 220px;">
                                             
                                            <input type="text" placeholder="Search history..." class="form-control border-start-0 ps-0 dior-ic-c87dae7386" style="box-shadow: none;">
                                        </div>
                                        <button type="button" title="Add Medical Record" class="btn btn-sm btn-primary px-3 rounded-pill shadow-sm" onclick="if(typeof diorDocOpenUploadModal === 'function') diorDocOpenUploadModal(); else Swal.fire({ icon: 'info', title: 'Provider Access Only', text: 'Only authorized providers can upload clinical documents here.' });"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg></button>
                                        <button type="button" title="Print Care History" class="btn btn-sm btn-outline-secondary px-3 rounded-pill bg-white shadow-sm dior-ic-30bc658af7"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16" style="background:transparent;"><path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/><path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/></svg></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-16 dior-ic-18f15f6b14">
                            <div class="card-header bg-transparent py-3 px-4 d-flex align-items-center justify-content-between dior-ic-a6b4a26983">
                                <h5 class="card-title-text mb-0 dior-ic-e6680bba01"><i class="fas fa-history me-2 text-primary dior-ic-9e5c8d5e64"></i>Patient Treatment &amp; Clinical Timeline Log</h5>
                                <span class="patient-id-badge text-xs px-3 py-1 font-weight-bold dior-ic-90ce95a680">Patient: <?php echo esc_html($profile['full_name'] ?? 'Patient Record'); ?> (#<?php echo esc_html($profile['patient_id'] ?? ''); ?>)</span>
                            </div>
                            <div class="card-body p-4 p-md-5 dior-ic-4315250082">
                                <div class="modern-treatment-timeline position-relative dior-ic-3bc278421b">
                                    
                                    <?php
                                    if (!function_exists('dior_get_timeline_event_image')) {
                                        function dior_get_timeline_event_image($event) {
                                            $type = strtoupper((string)($event['type'] ?? ''));
                                            $title = strtolower((string)($event['title'] ?? ''));
                                            $desc = strtolower((string)($event['description'] ?? ''));
                                            $base_url = defined('DIOR_PORTAL_URL') ? DIOR_PORTAL_URL : plugins_url('/', __FILE__);

                                            // Blood pressure & cardiovascular review
                                            if (strpos($title, 'blood pressure') !== false || strpos($title, 'hypertension') !== false || strpos($title, 'cardio') !== false || strpos($desc, 'blood pressure') !== false) {
                                                return [
                                                    'url' => $base_url . 'assets/images/timeline/blood-pressure.jpg',
                                                    'badge_color' => '#E11D48',
                                                    'badge_bg' => '#FFE4E6',
                                                    'badge_border' => '#FDA4AF',
                                                    'icon' => 'fa-heart-pulse',
                                                    'alt' => 'Blood Pressure Review'
                                                ];
                                            }

                                            // Telehealth / Video Consultation / Appointments
                                            if ($type === 'APPOINTMENT' || strpos($title, 'telehealth') !== false || strpos($title, 'telemedicine') !== false || strpos($title, 'video') !== false || strpos($title, 'follow-up') !== false || strpos($title, 'routine') !== false) {
                                                return [
                                                    'url' => $base_url . 'assets/images/timeline/telehealth.webp',
                                                    'badge_color' => '#0284C7',
                                                    'badge_bg' => '#E0F2FE',
                                                    'badge_border' => '#7DD3FC',
                                                    'icon' => 'fa-video',
                                                    'alt' => 'Telehealth Consultation'
                                                ];
                                            }

                                            // Prescriptions & Medications
                                            if ($type === 'PRESCRIPTION' || strpos($title, 'prescription') !== false || strpos($title, 'medication') !== false || strpos($title, 'refill') !== false) {
                                                return [
                                                    'url' => $base_url . 'assets/images/timeline/prescription.webp',
                                                    'badge_color' => '#7C3AED',
                                                    'badge_bg' => '#EDE9FE',
                                                    'badge_border' => '#C4B5FD',
                                                    'icon' => 'fa-pills',
                                                    'alt' => 'Prescription Record'
                                                ];
                                            }

                                            // Diagnostic Scans & Lab Documents
                                            if ($type === 'DOCUMENT' || strpos($title, 'document') !== false || strpos($title, 'lab') !== false || strpos($title, 'scan') !== false || strpos($title, 'report') !== false) {
                                                return [
                                                    'url' => $base_url . 'assets/images/timeline/document.webp',
                                                    'badge_color' => '#0D9488',
                                                    'badge_bg' => '#CCFBF1',
                                                    'badge_border' => '#5EEAD4',
                                                    'icon' => 'fa-file-waveform',
                                                    'alt' => 'Clinical Document'
                                                ];
                                            }

                                            // Default Consultation / Appointment
                                            return [
                                                'url' => $base_url . 'assets/images/timeline/consultation.jpg',
                                                'badge_color' => '#2563EB',
                                                'badge_bg' => '#DBEAFE',
                                                'badge_border' => '#93C5FD',
                                                'icon' => 'fa-stethoscope',
                                                'alt' => 'Clinical Consultation'
                                            ];
                                        }
                                    }

                                    if (!function_exists('dior_get_timeline_doctor_avatar')) {
                                        function dior_get_timeline_doctor_avatar($doctor_name, $event = []) {
                                            $base_url = defined('DIOR_PORTAL_URL') ? DIOR_PORTAL_URL : plugins_url('/', __FILE__);
                                            $default_avatar = $base_url . 'assets/images/doctors/default-doctor.webp';

                                            if (!empty($event['avatar']) && filter_var($event['avatar'], FILTER_VALIDATE_URL)) {
                                                return $event['avatar'];
                                            }
                                            if (!empty($event['doctor_avatar']) && filter_var($event['doctor_avatar'], FILTER_VALIDATE_URL)) {
                                                return $event['doctor_avatar'];
                                            }

                                            $name = strtolower(trim((string)$doctor_name));

                                            if (strpos($name, 'marcus') !== false || strpos($name, 'sterling') !== false) {
                                                return $base_url . 'assets/images/doctors/dr-marcus-sterling.jpg';
                                            }
                                            if (strpos($name, 'ethan') !== false || strpos($name, 'walker') !== false) {
                                                return $base_url . 'assets/images/doctors/dr-ethan-walker.jpg';
                                            }
                                            if (strpos($name, 'evelyn') !== false || strpos($name, 'vance') !== false) {
                                                return $base_url . 'assets/images/doctors/dr-evelyn-vance.jpg';
                                            }
                                            if (strpos($name, 'sophia') !== false || strpos($name, 'reyes') !== false) {
                                                return $base_url . 'assets/images/doctors/dr-sophia-reyes.jpg';
                                            }
                                            if (strpos($name, 'james') !== false || strpos($name, 'chen') !== false) {
                                                return $base_url . 'assets/images/doctors/dr-james-chen.jpg';
                                            }
                                            if (strpos($name, 'sarah') !== false || strpos($name, 'smith') !== false) {
                                                return $base_url . 'assets/images/doctors/dr-sophia-reyes.jpg';
                                            }
                                            if (strpos($name, 'helen') !== false || strpos($name, 'miller') !== false) {
                                                return $base_url . 'assets/images/doctors/dr-evelyn-vance.jpg';
                                            }

                                            $clean_name = preg_replace('/^(dr\.?|doctor)\s+/i', '', trim((string)$doctor_name));
                                            if (!empty($clean_name)) {
                                                $users = get_users(['search' => '*' . $clean_name . '*', 'number' => 1]);
                                                if (!empty($users)) {
                                                    $u_id = $users[0]->ID;
                                                    $user_avatar = get_user_meta($u_id, 'dior_profile_image', true)
                                                        ?: get_user_meta($u_id, 'doctor_avatar', true)
                                                        ?: get_user_meta($u_id, 'profile_picture', true)
                                                        ?: get_user_meta($u_id, 'profile_image', true);
                                                    if (!empty($user_avatar)) {
                                                        return $user_avatar;
                                                    }
                                                }
                                            }

                                            return $default_avatar;
                                        }
                                    }

                                    $timeline_events = [];
                                    foreach (($encounters ?? []) as $encounter) {
                                        $timeline_events[] = [
                                            'date' => $encounter['created_at'] ?? current_time('mysql'),
                                            'type' => 'CONSULTATION',
                                            'title' => $encounter['encounter_type'] ?? 'Clinical Consultation',
                                            'doctor' => $encounter['attestation_author'] ?? 'Attending Physician',
                                            'description' => $encounter['assessment'] ?? ($encounter['subjective'] ?? 'Clinical encounter recorded in the medical record.'),
                                            'icon' => 'fa-stethoscope',
                                            'avatar' => $encounter['avatar'] ?? '',
                                        ];
                                    }
                                    foreach (($documents ?? []) as $document) {
                                        $timeline_events[] = [
                                            'date' => $document['created_at'] ?? ($document['date'] ?? current_time('mysql')),
                                            'type' => 'DOCUMENT',
                                            'title' => $document['title'] ?? 'Medical Document',
                                            'doctor' => $document['author'] ?? 'Clinical Staff',
                                            'description' => $document['category'] ?? 'Medical document added to the secure record.',
                                            'icon' => 'fa-file-medical-alt',
                                            'avatar' => $document['avatar'] ?? '',
                                        ];
                                    }
                                    foreach (($appointments ?? []) as $appointment) {
                                        $timeline_events[] = [
                                            'date' => $appointment['appt_date'] ?? ($appointment['date'] ?? current_time('mysql')),
                                            'type' => 'APPOINTMENT',
                                            'title' => $appointment['condition'] ?? ($appointment['condition_name'] ?? 'Telehealth Consultation'),
                                            'doctor' => $appointment['provider'] ?? ($appointment['doctor_name'] ?? 'Attending Physician'),
                                            'description' => 'Appointment status: ' . ($appointment['status'] ?? 'Confirmed') . '.',
                                            'icon' => 'fa-calendar-check',
                                            'status' => $appointment['status'] ?? 'Confirmed',
                                            'avatar' => $appointment['avatar'] ?? ($appointment['doctor_avatar'] ?? ''),
                                        ];
                                    }
                                    foreach (($prescriptions ?? []) as $prescription) {
                                        $timeline_events[] = [
                                            'date' => $prescription['date_prescribed'] ?? ($prescription['date'] ?? current_time('mysql')),
                                            'type' => 'PRESCRIPTION',
                                            'title' => $prescription['name'] ?? ($prescription['medication'] ?? 'Prescription'),
                                            'doctor' => $prescription['prescribed_by'] ?? 'Attending Physician',
                                            'description' => $prescription['condition'] ?? ($prescription['diagnosis'] ?? 'Prescription recorded in the medical record.'),
                                            'icon' => 'fa-pills',
                                            'status' => $prescription['status'] ?? 'Active',
                                            'avatar' => $prescription['avatar'] ?? '',
                                        ];
                                    }
                                    usort($timeline_events, static function ($a, $b) {
                                        return strtotime((string)$b['date']) <=> strtotime((string)$a['date']);
                                    });
                                    $default_doc_img = (defined('DIOR_PORTAL_URL') ? DIOR_PORTAL_URL : plugins_url('/', __FILE__)) . 'assets/images/doctors/default-doctor.webp';
                                    foreach (array_slice($timeline_events, 0, 20) as $event):
                                        $event_ts = strtotime((string)$event['date']) ?: current_time('timestamp');
                                        $event_meta = dior_get_timeline_event_image($event);
                                        $doc_avatar = dior_get_timeline_doctor_avatar($event['doctor'], $event);
                                    ?>
                                    <div class="mr-timeline-item" data-record-type="<?php echo esc_attr($event['type']); ?>">
                                        <div class="mr-timeline-time-col">
                                            <span class="dior-ic-14731f6c49"><?php echo esc_html(wp_date('d M Y', $event_ts)); ?></span>
                                            <span class="dior-ic-07e1c2794e"><i class="far fa-clock me-1"></i><?php echo esc_html(wp_date('g:i A', $event_ts)); ?></span>
                                            <span class="dior-ic-81a2aacd66"><?php echo esc_html(human_time_diff($event_ts, current_time('timestamp'))); ?> ago</span>
                                        </div>
                                        <div class="dior-ic-f69dfb2b50">
                                            <div class="mr-timeline-vertical-line"></div>
                                            <div class="mr-timeline-icon-node" style="width: 50px; height: 50px; border-radius: 50%; position: relative; z-index: 3; background: <?php echo esc_attr($event_meta['badge_bg']); ?>; border: 3px solid #ffffff; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12); display: flex; align-items: center; justify-content: center; overflow: hidden; transition: transform 0.25s ease, box-shadow 0.25s ease;" title="<?php echo esc_attr($event_meta['alt']); ?>">
                                                <img src="<?php echo esc_url($event_meta['url']); ?>" alt="<?php echo esc_attr($event_meta['alt']); ?>" class="mr-timeline-node-img" style="width: 100%; height: 100%; object-fit: cover; display: block;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="mr-timeline-node-fallback" style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: <?php echo esc_attr($event_meta['badge_color']); ?>;">
                                                    <i class="fas text-white <?php echo esc_attr($event_meta['icon']); ?>" style="font-size: 18px;"></i>
                                                </div>
                                                <span class="mr-timeline-node-indicator" style="position: absolute; bottom: 1px; right: 1px; width: 12px; height: 12px; border-radius: 50%; background: <?php echo esc_attr($event_meta['badge_color']); ?>; border: 2px solid #ffffff;" title="<?php echo esc_attr($event['type']); ?>"></span>
                                            </div>
                                        </div>
                                        <div class="mr-timeline-content-card" style="transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease; border-radius: 16px; border: 1px solid #E2E8F0; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);">
                                            <div class="dior-ic-eba8b27042" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 12px;">
                                                <div class="dior-ic-33aac0e461" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                                    <span class="dior-ic-b382a6228c" style="background: <?php echo esc_attr($event_meta['badge_bg']); ?> !important; color: <?php echo esc_attr($event_meta['badge_color']); ?> !important; border: 1px solid <?php echo esc_attr($event_meta['badge_border']); ?> !important; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; padding: 4px 10px; border-radius: 12px;"> <?php echo esc_html($event['type']); ?> </span>
                                                    <h5 class="dior-ic-fceca4a64a" style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0;"><?php echo esc_html($event['title']); ?></h5>
                                                </div>
                                                <div class="dior-ic-b1d844752a" style="display: inline-flex; align-items: center; gap: 10px; background: #F8FAFC; padding: 5px 14px 5px 6px; border-radius: 30px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);">
                                                    <div style="position: relative; width: 36px; height: 36px; flex-shrink: 0;">
                                                        <img src="<?php echo esc_url($doc_avatar); ?>" alt="<?php echo esc_attr($event['doctor']); ?>" class="dior-ic-2061ceb509" style="width: 36px !important; height: 36px !important; border-radius: 50% !important; object-fit: cover !important; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.08); display: block;" onerror="this.src='<?php echo esc_url($default_doc_img); ?>';">
                                                        <span style="position: absolute; bottom: -1px; right: -1px; width: 9px; height: 9px; border-radius: 50%; background: #10B981; border: 2px solid #ffffff;" title="Authorized Provider"></span>
                                                    </div>
                                                    <div class="d-flex flex-column text-start" style="line-height: 1.2;">
                                                        <span class="dior-ic-9bbdb950cc" style="font-size: 13.5px; font-weight: 700; color: #1E293B; margin-bottom: 2px;"><?php echo esc_html($event['doctor']); ?></span>
                                                        <small class="dior-ic-3a4133fe41" style="font-size: 11px; color: #64748B; font-weight: 500;"><i class="fas fa-stethoscope text-primary me-1" style="font-size: 9px;"></i>Clinical Record</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php
                                            $raw_desc = $event['description'] ?? '';
                                            if (preg_match('/^Appointment status:\s*([A-Za-z\s\-]+)\.?$/i', trim($raw_desc), $st_match)) {
                                                $st_val = trim($st_match[1]);
                                                $st_badge_cls = 'bg-secondary-subtle text-secondary';
                                                $st_icon = 'fa-info-circle';
                                                if (strcasecmp($st_val, 'Completed') === 0) {
                                                    $st_badge_cls = 'bg-success-subtle text-success';
                                                    $st_icon = 'fa-check-circle';
                                                } elseif (strcasecmp($st_val, 'Confirmed') === 0 || strcasecmp($st_val, 'Scheduled') === 0) {
                                                    $st_badge_cls = 'bg-primary-subtle text-primary';
                                                    $st_icon = 'fa-calendar-check';
                                                } elseif (strcasecmp($st_val, 'Cancelled') === 0) {
                                                    $st_badge_cls = 'bg-danger-subtle text-danger';
                                                    $st_icon = 'fa-times-circle';
                                                } elseif (strcasecmp($st_val, 'In Progress') === 0 || strcasecmp($st_val, 'In-Queue') === 0) {
                                                    $st_badge_cls = 'bg-warning-subtle text-warning';
                                                    $st_icon = 'fa-clock';
                                                }
                                                echo '<p class="dior-ic-972dc92ce3 mb-0 d-flex align-items-center gap-2">';
                                                echo '<span class="text-muted" style="font-size: 13.5px; font-weight: 500;">Appointment status:</span> ';
                                                echo '<span class="badge ' . esc_attr($st_badge_cls) . ' rounded-pill px-3 py-1 font-weight-600" style="font-size: 12px; letter-spacing: 0.3px;"><i class="fas ' . esc_attr($st_icon) . ' me-1"></i>' . esc_html($st_val) . '</span>';
                                                echo '</p>';
                                            } else {
                                                echo '<p class="dior-ic-972dc92ce3 mb-0" style="font-size: 14px; color: #475569; line-height: 1.6;">' . nl2br(esc_html($raw_desc)) . '</p>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($timeline_events)): ?>
                                        <div class="mr-timeline-item"><div class="mr-timeline-content-card"><p class="dior-ic-972dc92ce3">No clinical events are available yet.</p></div></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>


                <!-- ============================================================== -->
                <!-- 6. BILLING & PAYMENT TAB -->
