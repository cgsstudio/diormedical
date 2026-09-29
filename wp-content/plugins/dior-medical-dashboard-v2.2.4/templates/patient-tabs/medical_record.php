<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-medical_record">
                    <div class="dior-page-header-box">
                        <div>
                            <h2>Medical Record</h2>
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
                                <div class="row align-items-center g-3 dior-ic-5f76b1419d">
                                    <div class="col-lg-8 dior-ic-aea598fd73">
                                        <div class="d-flex flex-wrap gap-2 dior-ic-c10bc232ce">
                                            <button type="button" class="btn btn-sm btn-primary dior-ic-b83363301a"><i class="fas fa-th-large me-1"></i> All Events (<?php echo esc_html(count($appointments) + count($documents) + count($questionnaires)); ?>) </button>
                                            <button type="button" class="btn btn-sm btn-light dior-ic-acadeac91e"><i class="fas fa-stethoscope me-1 text-primary dior-ic-0c27d9541a"></i> Consultations </button>
                                            <button type="button" class="btn btn-sm btn-light dior-ic-acadeac91e"><i class="fas fa-pills me-1 text-purple dior-ic-2552c485b6"></i> Prescriptions </button>
                                            <button type="button" class="btn btn-sm btn-light dior-ic-acadeac91e"><i class="fas fa-file-medical-alt me-1 text-info dior-ic-93cffbc604"></i> Diagnostics &amp; Scans </button>
                                            <button type="button" class="btn btn-sm btn-light dior-ic-acadeac91e"><i class="fas fa-user-md me-1 text-danger dior-ic-961ef1396d"></i> Procedures </button>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 d-flex gap-2 dior-ic-04e1c988f3">
                                        <div class="input-group search-input-group flex-grow-1 dior-ic-81029c50e5">
                                            <span class="input-group-text dior-ic-8ae62c593e"><i class="fas fa-search"></i></span>
                                            <input type="text" placeholder="Search medical history..." class="form-control dior-ic-c87dae7386">
                                        </div>
                                        <button type="button" title="Print Care History" class="btn btn-sm btn-outline-secondary px-3 dior-ic-30bc658af7"><i class="fas fa-print"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-16 dior-ic-18f15f6b14">
                            <div class="card-header bg-transparent py-3 px-4 d-flex align-items-center justify-content-between dior-ic-a6b4a26983">
                                <h5 class="card-title-text mb-0 dior-ic-e6680bba01"><i class="fas fa-history me-2 text-primary dior-ic-9e5c8d5e64"></i>Patient Treatment &amp; Clinical Timeline Log</h5>
                                <span class="patient-id-badge text-xs px-3 py-1 font-weight-bold dior-ic-90ce95a680">Patient: <?php echo esc_html($profile['full_name']); ?> (#<?php echo esc_html($profile['patient_id'] ?? ''); ?>)</span>
                            </div>
                            <div class="card-body p-4 p-md-5 dior-ic-4315250082">
                                <div class="modern-treatment-timeline position-relative dior-ic-3bc278421b">
                                    
                                    <?php
                                    $timeline_events = [];
                                    foreach (($encounters ?? []) as $encounter) {
                                        $timeline_events[] = [
                                            'date' => $encounter['created_at'] ?? current_time('mysql'),
                                            'type' => 'CONSULTATION',
                                            'title' => $encounter['encounter_type'] ?? 'Clinical Consultation',
                                            'doctor' => $encounter['attestation_author'] ?? 'Attending Physician',
                                            'description' => $encounter['assessment'] ?? ($encounter['subjective'] ?? 'Clinical encounter recorded in the medical record.'),
                                            'icon' => 'fa-stethoscope',
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
                                        ];
                                    }
                                    usort($timeline_events, static function ($a, $b) {
                                        return strtotime((string)$b['date']) <=> strtotime((string)$a['date']);
                                    });
                                    foreach (array_slice($timeline_events, 0, 20) as $event):
                                        $event_ts = strtotime((string)$event['date']) ?: current_time('timestamp');
                                    ?>
                                    <div class="mr-timeline-item">
                                        <div class="mr-timeline-time-col">
                                            <span class="dior-ic-14731f6c49"><?php echo esc_html(wp_date('d M Y', $event_ts)); ?></span>
                                            <span class="dior-ic-07e1c2794e"><i class="far fa-clock me-1"></i><?php echo esc_html(wp_date('g:i A', $event_ts)); ?></span>
                                            <span class="dior-ic-81a2aacd66"><?php echo esc_html(human_time_diff($event_ts, current_time('timestamp'))); ?> ago</span>
                                        </div>
                                        <div class="dior-ic-f69dfb2b50">
                                            <div class="mr-timeline-vertical-line"></div>
                                            <div class="mr-timeline-icon-node"><i class="fas text-white <?php echo esc_attr($event['icon']); ?> dior-ic-31bb4f1b09"></i></div>
                                        </div>
                                        <div class="mr-timeline-content-card">
                                            <div class="dior-ic-eba8b27042">
                                                <div class="dior-ic-33aac0e461"><span class="dior-ic-b382a6228c"> <?php echo esc_html($event['type']); ?> </span><h5 class="dior-ic-fceca4a64a"><?php echo esc_html($event['title']); ?></h5></div>
                                                <div class="dior-ic-b1d844752a"><div class="dior-ic-2061ceb509" aria-hidden="true"><i class="fas fa-user-doctor"></i></div><div><span class="dior-ic-9bbdb950cc"><?php echo esc_html($event['doctor']); ?></span><small class="dior-ic-3a4133fe41">Clinical Record</small></div></div>
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
