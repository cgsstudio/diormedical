<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-medical_record">

<style>
/* Step 3B: Responsive Bootstrap-inspired medical record styling. */
#tab-medical_record{color:#334155}
#tab-medical_record h4{color:#142b4a!important;font-size:22px!important;font-weight:700!important;letter-spacing:-.025em}
#tab-medical_record .section-body{padding:0 18px 24px}
#tab-medical_record .kpi-mini-card,#tab-medical_record .card{border:1px solid #e7edf5!important;border-radius:16px!important;box-shadow:0 6px 22px rgba(22,52,91,.05)!important;background:#fff}
#tab-medical_record .card-header{border-bottom:1px solid #edf1f7!important}
#tab-medical_record .card-title-text{color:#183657!important;font-weight:700!important}
#tab-medical_record .btn{border-radius:9px!important;font-weight:600;transition:box-shadow .18s ease,transform .18s ease}
#tab-medical_record .btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(22,52,91,.1)}
#tab-medical_record .dior-medical-record-filter{padding:7px 13px!important}
#tab-medical_record .mr-timeline-content-card{border:1px solid #e7edf5!important;border-radius:12px!important;background:#fff}
@media(max-width:767.98px){#tab-medical_record h4{font-size:19px!important}#tab-medical_record .section-body{padding:0 10px 20px}#tab-medical_record .card-body,#tab-medical_record .card-body.p-4{padding:15px!important}#tab-medical_record .card-header{padding:14px!important}#tab-medical_record .dior-medical-record-filter{padding:7px 10px!important;font-size:12px}}
</style>
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

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-3 px-md-4 mb-4">
        <button type="button" class="dior-subtab-pill btn active" data-patient-tab="medical_record" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('medical_record');">
            <i class="fas fa-notes-medical"></i> Medical Record
        </button>
        <button type="button" class="dior-subtab-pill btn" data-patient-tab="questionnaire" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('questionnaire');">
            <i class="fas fa-clipboard-question"></i> Clinical Intake & Questionnaire
        </button>
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
                                            <h5 class="mb-0 font-weight-bold text-dark text-truncate dior-ic-c4e98ce9b3"><?php echo esc_html(count($appointments) + count($encounters ?? []) + count($documents) + count($questionnaires)); ?> Care Events</h5>
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
                                                <span class="badge bg-info-subtle text-info rounded-pill text-2xs dior-ic-c37f987d84">Record</span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-dark text-truncate dior-ic-c4e98ce9b3"><?php echo esc_html($appointments[0]['condition'] ?? $appointments[0]['condition_name'] ?? 'No recent appointment'); ?></h5>
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
                                                <span class="badge bg-purple-subtle text-purple rounded-pill text-2xs dior-ic-f6785667b0"><?php echo esc_html($appointments[0]['provider_spec'] ?? 'Specialty not recorded'); ?></span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-dark text-truncate dior-ic-c4e98ce9b3"><?php echo esc_html($appointments[0]['provider'] ?? $appointments[0]['doctor_name'] ?? 'Provider not recorded'); ?></h5>
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
                                                <span class="text-xs text-muted font-weight-600 text-uppercase text-truncate dior-ic-d48d9220c2">Record Details</span>
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill text-2xs dior-ic-4db3afa214">Clinical</span>
                                            </div>
                                            <h5 class="mb-0 font-weight-bold text-dark text-truncate dior-ic-c2f54c19e7"><i class="fas fa-file-medical me-1 text-xs"></i> Medical Record</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-16 mb-4 no-print dior-ic-18f15f6b14">
                            <div class="card-body p-3 dior-ic-30853ea1b7">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <div class="d-flex flex-wrap gap-2 flex-grow-1">
                                        <button type="button" class="btn btn-sm rounded-pill btn-primary dior-medical-record-filter is-active px-3 shadow-sm text-white" data-filter="ALL"><i class="fas fa-th-large me-1"></i> All Events (<?php echo esc_html(count($appointments) + count($encounters ?? []) + count($documents) + count($questionnaires) + count($prescriptions)); ?>)</button>
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-primary dior-medical-record-filter px-3" data-filter="CONSULTATION"><i class="fas fa-stethoscope me-1"></i> Consultations</button>
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-primary dior-medical-record-filter px-3" data-filter="PRESCRIPTION"><i class="fas fa-pills me-1"></i> Prescriptions</button>
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-primary dior-medical-record-filter px-3" data-filter="DIAGNOSTIC"><i class="fas fa-file-medical-alt me-1"></i> Diagnostics &amp; Scans</button>
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-primary dior-medical-record-filter px-3" data-filter="PROCEDURE"><i class="fas fa-user-md me-1"></i> Procedures</button>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-start justify-content-md-end">
                                        <div class="input-group search-input-group dior-bs-search-group shadow-sm" style="border-radius: 20px; overflow: hidden; width: 220px;">
                                        
                                            <input type="text" placeholder="Search history..." class="form-control border-start-0 ps-0 dior-ic-c87dae7386" style="box-shadow: none;">
                                        </div>
                                        <button type="button" title="Add Medical Record" class="btn btn-sm btn-primary px-3 rounded-pill shadow-sm" onclick="if(typeof diorDocOpenUploadModal === 'function') diorDocOpenUploadModal(); else Swal.fire({ icon: 'info', title: 'Provider Access Only', text: 'Only authorized providers can upload clinical documents here.' });"><i class="fas fa-plus"></i></button>
                                        <button type="button" title="Print Care History" class="btn btn-sm btn-outline-secondary px-3 rounded-pill bg-white shadow-sm dior-ic-30bc658af7"><i class="fas fa-print"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        $record_patient_user_id = !empty($user_id) ? absint($user_id) : get_current_user_id();
                        $record_patient_name = !empty($profile['full_name']) ? $profile['full_name'] : (wp_get_current_user()->display_name ?: 'Patient');
                        $record_patient_avatar = !empty($profile['avatar_url']) ? $profile['avatar_url'] : '';
                        if (empty($record_patient_avatar) && $record_patient_user_id) {
                            $record_patient_avatar = get_user_meta($record_patient_user_id, 'dior_profile_image', true)
                                ?: get_user_meta($record_patient_user_id, 'profile_image', true)
                                ?: get_avatar_url($record_patient_user_id, ['size' => 96]);
                        }
                        if (empty($record_patient_avatar)) {
                            $record_patient_avatar = get_avatar_url($record_patient_user_id, ['size' => 96]);
                        }
                        ?>
                        <div class="card border-0 shadow-sm rounded-16 dior-ic-18f15f6b14">
                            <div class="card-header bg-transparent py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-3 dior-ic-a6b4a26983">
                                <h5 class="card-title-text mb-0 dior-ic-e6680bba01"><i class="fas fa-history me-2 text-primary dior-ic-9e5c8d5e64"></i>Patient Treatment &amp; Clinical Timeline Log</h5>
                                <div class="patient-id-badge text-xs px-3 py-1 font-weight-bold d-flex align-items-center gap-2 dior-ic-90ce95a680">
                                    <img class="dior-patient-avatar-sync rounded-circle object-fit-cover" src="<?php echo esc_url($record_patient_avatar); ?>" alt="<?php echo esc_attr($record_patient_name); ?>" width="32" height="32" style="width:32px;height:32px;object-fit:cover;">
                                    <span>Patient: <?php echo esc_html($record_patient_name); ?> (#<?php echo esc_html($profile['patient_id'] ?? ''); ?>)</span>
                                </div>
                            </div>
                            <div class="card-body p-4 p-md-5 dior-ic-4315250082">
                                <div class="modern-treatment-timeline position-relative dior-ic-3bc278421b">
                                    
                                    <?php
                                    $timeline_events = [];
                                    foreach (($encounters ?? []) as $encounter) {
                                        $timeline_events[] = [
                                            'date' => $encounter['created_at'] ?? '',
                                            'type' => 'CONSULTATION',
                                            'title' => $encounter['encounter_type'] ?? 'Clinical Consultation',
                                            'doctor' => $encounter['attestation_author'] ?? 'Provider not recorded',
                                            'description' => $encounter['assessment'] ?? ($encounter['subjective'] ?? 'Clinical encounter recorded in the medical record.'),
                                            'icon' => 'fa-stethoscope',
                                        ];
                                    }
                                    foreach (($documents ?? []) as $document) {
                                        $timeline_events[] = [
                                            'date' => $document['created_at'] ?? ($document['date'] ?? ''),
                                            'type' => 'DOCUMENT',
                                            'title' => $document['title'] ?? 'Medical Document',
                                            'doctor' => $document['author'] ?? 'Clinical Staff',
                                            'description' => $document['category'] ?? 'Medical document added to the secure record.',
                                            'icon' => 'fa-file-medical-alt',
                                        ];
                                    }
                                    foreach (($appointments ?? []) as $appointment) {
                                        $timeline_events[] = [
                                            'date' => $appointment['appt_date'] ?? ($appointment['date'] ?? ''),
                                            'type' => 'APPOINTMENT',
                                            'title' => $appointment['condition'] ?? ($appointment['condition_name'] ?? 'Telehealth Consultation'),
                                            'doctor' => $appointment['provider'] ?? ($appointment['doctor_name'] ?? 'Provider not recorded'),
                                            'description' => 'Appointment status: ' . (!empty($appointment['status']) ? $appointment['status'] : 'Status unavailable') . '.',
                                            'icon' => 'fa-calendar-check',
                                        ];
                                    }
                                    foreach (($prescriptions ?? []) as $prescription) {
                                        $timeline_events[] = [
                                            'date' => $prescription['date_prescribed'] ?? ($prescription['date'] ?? ''),
                                            'type' => 'PRESCRIPTION',
                                            'title' => $prescription['name'] ?? ($prescription['medication'] ?? 'Prescription'),
                                            'doctor' => $prescription['prescribed_by'] ?? 'Provider not recorded',
                                            'description' => $prescription['condition'] ?? ($prescription['diagnosis'] ?? 'Prescription recorded in the medical record.'),
                                            'icon' => 'fa-pills',
                                        ];
                                    }
                                    foreach (($questionnaires ?? []) as $questionnaire) {
                                        $timeline_events[] = [
                                            'date' => $questionnaire['updated_at'] ?? ($questionnaire['submitted_at'] ?? ''),
                                            'type' => 'QUESTIONNAIRE',
                                            'title' => $questionnaire['title'] ?? 'Clinical Intake Form',
                                            'doctor' => 'Patient / Clinical Intake',
                                            'description' => 'Status: ' . (!empty($questionnaire['status']) ? $questionnaire['status'] : 'Status unavailable') . '.',
                                            'icon' => 'fa-clipboard-check',
                                        ];
                                    }
                                    usort($timeline_events, static function ($a, $b) {
                                        return (strtotime((string)($b['date'] ?? '')) ?: 0) <=> (strtotime((string)($a['date'] ?? '')) ?: 0);
                                    });
                                    foreach (array_slice($timeline_events, 0, 20) as $event):
                                        $event_ts = !empty($event['date']) ? strtotime((string) $event['date']) : false;
                                        $event_type = strtoupper((string)($event['type'] ?? 'APPOINTMENT'));

                                        $node_class = 'node-appointment';
                                        $badge_style = 'background: #e0f2fe !important; color: #0284c7 !important;';
                                        if ($event_type === 'CONSULTATION') {
                                            $node_class = 'node-consultation';
                                            $badge_style = 'background: #dbeafe !important; color: #1d4ed8 !important;';
                                        } elseif ($event_type === 'PRESCRIPTION') {
                                            $node_class = 'node-prescription';
                                            $badge_style = 'background: #d1fae5 !important; color: #047857 !important;';
                                        } elseif ($event_type === 'DOCUMENT') {
                                            $node_class = 'node-document';
                                            $badge_style = 'background: #fef3c7 !important; color: #b45309 !important;';
                                        }
                                    ?>
                                    <div class="mr-timeline-item" data-record-type="<?php echo esc_attr($event['type']); ?>">
                                        <div class="mr-timeline-time-col">
                                            <span class="dior-ic-14731f6c49"><?php echo $event_ts ? esc_html(wp_date('d M Y', $event_ts)) : 'Date unavailable'; ?></span>
                                            <span class="dior-ic-07e1c2794e"><i class="far fa-clock me-1"></i><?php echo $event_ts ? esc_html(wp_date('g:i A', $event_ts)) : '—'; ?></span>
                                            <span class="dior-ic-81a2aacd66"><?php echo $event_ts ? esc_html(human_time_diff($event_ts, current_time('timestamp'))) . ' ago' : ' '; ?></span>
                                        </div>
                                        <div class="dior-ic-f69dfb2b50">
                                            <div class="mr-timeline-vertical-line"></div>
                                            <div class="mr-timeline-icon-node <?php echo esc_attr($node_class); ?>">
                                                <i class="fas text-white <?php echo esc_attr($event['icon']); ?>"></i>
                                            </div>
                                        </div>
                                        <div class="mr-timeline-content-card">
                                            <div class="dior-ic-eba8b27042">
                                                <div class="dior-ic-33aac0e461">
                                                    <span class="dior-ic-b382a6228c" style="<?php echo esc_attr($badge_style); ?>"> <?php echo esc_html($event['type']); ?> </span>
                                                    <h5 class="dior-ic-fceca4a64a"><?php echo esc_html($event['title']); ?></h5>
                                                </div>
                                                <div class="dior-ic-b1d844752a">
                                                    <div>
                                                        <span class="dior-ic-9bbdb950cc"><?php echo esc_html($event['doctor']); ?></span>
                                                        <small class="dior-ic-3a4133fe41">Clinical Record</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="dior-ic-972dc92ce3"><?php echo nl2br(esc_html($event['description'])); ?></p>
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
