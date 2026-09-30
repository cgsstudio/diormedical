<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/patient-profile.css?v=' . time()); ?>">

<section class="dior-tab-panel patient-profile-wrapper" id="tab-doc-patients-profile" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700; background: #e2e8f0; display: inline-block; padding: 4px 10px; border-radius: 4px; color: #475569;">Patient Profile</h4>
        </div>
        <div>
            <ul class="breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)">Patients</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span>Patient Profile</span></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="section-body">
        
        <!-- Purple Banner -->
        <div class="pp-banner">
            <div class="pp-banner-left">
                <div class="pp-avatar-wrapper">
                    <img src="https://i.pravatar.cc/150?img=5" onerror="this.src='https://ui-avatars.com/api/?name=Sarah+Smith&background=random'" alt="Sarah Smith">
                </div>
                <div class="pp-info">
                    <h3>Sarah Smith</h3>
                    <p class="pid">Patient ID: P001</p>
                    <div class="pp-meta">
                        <span><i class="fa-solid fa-venus"></i> Female</span>
                        <span><i class="fa-solid fa-cake-candles"></i> 35 years</span>
                        <span><i class="fa-solid fa-droplet"></i> A+</span>
                        <span class="pp-badge-active">Active</span>
                    </div>
                </div>
            </div>
            <div>
                <button type="button" class="pp-ai-btn">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> AI Case Synthesis
                </button>
            </div>
        </div>

        <!-- Navigation Card -->
        <div class="pp-card">
            <ul class="pp-nav-tabs">
                <li><a href="javascript:void(0)" class="pp-tab-link active" data-target="pp-pane-ai"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Case Synthesis</a></li>
                <li><a href="javascript:void(0)" class="pp-tab-link" data-target="pp-pane-personal">Personal Info</a></li>
                <li><a href="javascript:void(0)" class="pp-tab-link" data-target="pp-pane-medical">Medical Info</a></li>
                <li><a href="javascript:void(0)" class="pp-tab-link" data-target="pp-pane-admission">Admission Details</a></li>
                <li><a href="javascript:void(0)" class="pp-tab-link" data-target="pp-pane-visits">Visit History</a></li>
            </ul>

            <!-- Tab Content area -->
            <div class="pp-tab-content">
                <!-- AI Case Synthesis Pane -->
                <div class="pp-tab-pane active" id="pp-pane-ai">
                    
                    <!-- AI Alert Box -->
                    <div class="pp-ai-alert">
                        <div class="pp-ai-alert-left">
                            <div class="pp-ai-icon">
                                <i class="fa-solid fa-brain"></i>
                            </div>
                            <div class="pp-ai-alert-text">
                                <h6>EHR Automated Case Intelligence</h6>
                                <p>Synthesizing clinical notes, multi-visit history, and active medications</p>
                            </div>
                        </div>
                        <div class="pp-ai-alert-actions">
                            <button type="button" class="pp-btn-outline primary">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Re-Synthesize Chart
                            </button>
                            <button type="button" class="pp-btn-outline">
                                <i class="fa-regular fa-copy"></i> Copy Summary
                            </button>
                        </div>
                    </div>

                    <!-- Highlight Cards -->
                    <div class="pp-highlight-cards">
                        <!-- Card 1 -->
                        <div class="pp-h-card">
                            <span class="pp-h-badge blue">Admission Rationale</span>
                            <h6>Severe acute asthma exacerbation with secondary bronchospasm</h6>
                            <p>Under active care of Dr. Jacob Ryan in W-103 (Room R-303).</p>
                        </div>

                        <!-- Card 2 -->
                        <div class="pp-h-card" style="border-left: 3px solid #DC2626;">
                            <span class="pp-h-badge red">⚠️ Safety & Allergy Alerts</span>
                            <div>
                                <span class="pp-tag">Penicillin</span>
                                <span class="pp-tag">Peanuts</span>
                            </div>
                            <p style="margin-top: 10px;">Cross-reactivity warning: Avoid cephalosporins and beta-lactams without prior skin challenge.</p>
                        </div>

                        <!-- Card 3 -->
                        <div class="pp-h-card" style="border-left: 3px solid #16A34A;">
                            <span class="pp-h-badge green">Care Trajectory</span>
                            <h6>Discharge Planning (Target: 48h)</h6>
                            <p>Taper IV corticosteroids to oral prednisone. Schedule home nebulizer delivery.</p>
                        </div>
                    </div>

                    <!-- Internal Table -->
                    <div class="pp-inner-box">
                        <div style="padding: 15px 20px; border-bottom: 1px solid #E2E8F0;">
                            <h6 style="margin: 0; font-size: 15px; font-weight: 700; color: #1E293B; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-pills" style="color: #4F46E5;"></i> Active Prescription & Interaction Analysis
                            </h6>
                        </div>
                        <table class="pp-table">
                            <thead>
                                <tr>
                                    <th>Medication</th>
                                    <th>Indication</th>
                                    <th>Safety / Monitoring Status</th>
                                    <th>AI Recommendation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Albuterol Inhaler</strong></td>
                                    <td>Acute Bronchospasm</td>
                                    <td><span class="pp-st-badge stable">Stable</span></td>
                                    <td>Titrate to 2 puffs Q4H PRN as respiratory wheeze resolves.</td>
                                </tr>
                                <tr>
                                    <td><strong>Lisinopril 10mg</strong></td>
                                    <td>Essential Hypertension</td>
                                    <td><span class="pp-st-badge monitor">Monitor Potassium</span></td>
                                    <td>Check BMP in 7 days. Note potential dry cough interaction.</td>
                                </tr>
                                <tr>
                                    <td><strong>Fluticasone Propionate</strong></td>
                                    <td>Asthma Maintenance</td>
                                    <td><span class="pp-st-badge maint">Maintenance</span></td>
                                    <td>Rinse mouth after inhalation to prevent oral candidiasis.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>

                <!-- Personal Info Pane -->
                <div class="pp-tab-pane" id="pp-pane-personal">
                    <div class="pp-info-grid">
                        <div class="pp-info-col">
                            <table class="pp-info-table">
                                <tr><th>Date of Birth:</th><td>1989-05-15</td></tr>
                                <tr><th>Marital Status:</th><td>Married</td></tr>
                                <tr><th>National ID:</th><td>NAT123456789</td></tr>
                                <tr><th>Email:</th><td>sarah.smith@example.com</td></tr>
                                <tr><th>Mobile:</th><td>+1 (123) 456-7890</td></tr>
                                <tr><th>Address:</th><td>123 Main Street, Anytown, CA 94538</td></tr>
                            </table>
                        </div>
                        <div class="pp-info-col">
                            <h5 class="pp-section-title">Emergency Contact</h5>
                            <table class="pp-info-table">
                                <tr><th>Name:</th><td>John Smith</td></tr>
                                <tr><th>Relation:</th><td>Husband</td></tr>
                                <tr><th>Phone:</th><td>+1 (987) 654-3210</td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Medical Info Pane -->
                <div class="pp-tab-pane" id="pp-pane-medical">
                    <div class="pp-info-grid">
                        <div class="pp-info-col">
                            <h5 class="pp-section-title">Allergies</h5>
                            <div style="margin-bottom: 20px;">
                                <span class="pp-tag">Penicillin</span>
                                <span class="pp-tag">Peanuts</span>
                            </div>
                            <h5 class="pp-section-title">Chronic Conditions</h5>
                            <div>
                                <span class="pp-tag" style="background-color: #A16207;">Asthma</span>
                                <span class="pp-tag" style="background-color: #A16207;">Hypertension</span>
                            </div>
                        </div>
                        <div class="pp-info-col">
                            <h5 class="pp-section-title">Current Medications</h5>
                            <ul class="pp-pill-list" style="list-style-type: disc; padding-left: 20px;">
                                <li style="display: list-item;">Albuterol Inhaler</li>
                                <li style="display: list-item;">Lisinopril 10mg</li>
                                <li style="display: list-item;">Fluticasone Propionate</li>
                            </ul>
                            <h5 class="pp-section-title">Past Medical History</h5>
                            <p style="font-size: 13px; color: #334155;">Appendectomy in 2015, Fractured wrist in 2018</p>
                        </div>
                    </div>
                </div>

                <!-- Admission Details Pane -->
                <div class="pp-tab-pane" id="pp-pane-admission">
                    <table class="pp-info-table">
                        <tr><th>Admission Date:</th><td>2023-11-10</td></tr>
                        <tr><th>Discharge Date:</th><td>2023-11-15</td></tr>
                        <tr><th>Doctor Assigned:</th><td>Dr. Jacob Ryan</td></tr>
                        <tr><th>Ward Number:</th><td>W-103</td></tr>
                        <tr><th>Room Number:</th><td>R-303</td></tr>
                        <tr><th>Reason for Admission:</th><td>Severe acute asthma exacerbation with secondary bronchospasm</td></tr>
                        <tr><th>Treatment:</th><td>Nebulized bronchodilators, systemic IV corticosteroid therapy, and continuous SpO2 monitoring</td></tr>
                    </table>
                </div>

                <!-- Visit History Pane -->
                <div class="pp-tab-pane" id="pp-pane-visits">
                    <div style="width: 100%; overflow-x: auto;">
                        <table class="pp-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Doctor</th>
                                    <th>Treatment</th>
                                    <th>Charges</th>
                                    <th>Outcome</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>2023-11-10</td><td>Dr. Jacob Ryan</td><td>Respiratory therapy</td><td>$250</td><td><span class="pp-st-badge solid-green">Improved</span></td></tr>
                                <tr><td>2023-08-22</td><td>Dr. Sophia Chen</td><td>Blood pressure check</td><td>$120</td><td><span class="pp-st-badge solid-green">Stable</span></td></tr>
                                <tr><td>2023-05-15</td><td>Dr. Jacob Ryan</td><td>Annual physical</td><td>$180</td><td><span class="pp-st-badge solid-green">Healthy</span></td></tr>
                                <tr><td>2023-02-03</td><td>Dr. Michael Lee</td><td>Flu symptoms</td><td>$150</td><td><span class="pp-st-badge solid-green">Recovered</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabLinks = document.querySelectorAll('.pp-tab-link');
    const tabPanes = document.querySelectorAll('.pp-tab-pane');

    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            
            // Remove active class from all links and panes
            tabLinks.forEach(l => l.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));
            
            // Add active class to clicked link and target pane
            this.classList.add('active');
            document.getElementById(targetId).classList.add('active');
        });
    });
});
</script>
