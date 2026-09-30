<?php
$doc_ov_patients = is_array($patients ?? []) ? array_values($patients) : [];
$doc_ov_appts = is_array($appointments ?? []) ? array_values($appointments) : [];
$doc_ov_p = static function($index) use ($doc_ov_patients) { return $doc_ov_patients[$index] ?? []; };
$doc_ov_a = static function($index) use ($doc_ov_appts) { return $doc_ov_appts[$index] ?? []; };
?>
<style>
#tab-doc-overview {
    font-family: 'Montserrat', 'DMSans', 'DM Sans', sans-serif;
    color: #334155;
    background: #F4F7FE;
    padding: 0;
}
#tab-doc-overview h3, #tab-doc-overview h4 {
    margin: 0;
    font-family: 'Montserrat', 'DMSans', 'DM Sans', sans-serif;
    font-weight: 700;
    color: #1E293B;
}
.doc-ov-grid {
    display: flex;
    flex-direction: column;
    gap: 24px;
}
.doc-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
.doc-stat-card {
    background: #FFFFFF;
    border-radius: 20px;
    padding: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.doc-stat-info h3 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 4px;
}
.doc-stat-info p {
    margin: 0;
    color: #64748B;
    font-size: 14px;
    font-weight: 500;
}
.doc-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.icon-blue { background: #EEF2FF; color: #4F46E5; }
.icon-orange { background: #FFF7ED; color: #EA580C; }
.icon-green { background: #F0FDF4; color: #16A34A; }
.icon-cyan { background: #ECFEFF; color: #0891B2; }

/* Main Layout */
.doc-main-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}

/* Common Card */
.doc-card {
    background: #FFFFFF;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    margin-bottom: 24px;
}
.doc-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.doc-card-title {
    font-size: 18px;
    color: #2C6CB1;
}

/* Badges */
.badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.badge-completed { background: #DCFCE7; color: #166534; }
.badge-upcoming { background: #FEF9C3; color: #854D0E; }
.badge-missed { background: #FEE2E2; color: #991B1B; }
.badge-inprogress { background: #FFEDD5; color: #C2410C; }
.badge-consult { background: #FCE7F3; color: #9D174D; }

/* Timeline */
.doc-timeline {
    position: relative;
    padding-left: 20px;
}
.doc-timeline::before {
    content: '';
    position: absolute;
    left: 80px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #E2E8F0;
}
.timeline-item {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 24px;
    position: relative;
}
.timeline-time {
    width: 60px;
    font-size: 13px;
    font-weight: 600;
    color: #64748B;
    text-align: right;
}
.timeline-dot {
    width: 12px;
    height: 12px;
    background: #2C6CB1;
    border-radius: 50%;
    position: absolute;
    left: 75px;
    z-index: 2;
    border: 3px solid #FFF;
}
.timeline-content {
    flex: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.timeline-user {
    display: flex;
    align-items: center;
    gap: 12px;
}
.timeline-user img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}
.timeline-user h4 { font-size: 15px; margin-bottom: 2px; }
.timeline-user p { font-size: 12px; color: #64748B; margin: 0; }
.timeline-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: #EEF2FF;
    color: #4F46E5;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 14px;
}
.btn-join {
    background: #2C6CB1;
    color: #FFF;
    border: none;
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

/* Tables */
.doc-table {
    width: 100%;
    border-collapse: collapse;
}
.doc-table th {
    text-align: left;
    padding: 12px;
    font-size: 13px;
    color: #64748B;
    font-weight: 600;
    border-bottom: 1px solid #E2E8F0;
}
.doc-table td {
    padding: 16px 12px;
    font-size: 14px;
    font-weight: 500;
    color: #1E293B;
    border-bottom: 1px solid #F1F5F9;
}
.btn-outline {
    border: 1px solid #2C6CB1;
    color: #2C6CB1;
    background: transparent;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.btn-outline.active {
    background: #EEF2FF;
    border-color: #EEF2FF;
}
.doc-table td .btn-icon { width: 28px; height: 28px; }

/* Split Cards Bottom */
.doc-split-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

/* Profile Card */
.profile-card { text-align: center; }
.profile-img-wrap {
    width: 80px;
    height: 80px;
    margin: 0 auto 16px;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid #EEF2FF;
}
.profile-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.profile-card h3 { font-size: 18px; margin-bottom: 4px; }
.profile-card p { color: #64748B; font-size: 14px; margin-bottom: 16px; }
.profile-details {
    text-align: left;
    background: #F8FAFC;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 16px;
    font-size: 13px;
    color: #475569;
}
.profile-details div { margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.profile-details i { color: #2C6CB1; width: 16px; }

/* Pending Tasks */
.task-item {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #F1F5F9;
}
.task-item:last-child { border: none; padding-bottom: 0; }
.task-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #2C6CB1;
    margin-top: 2px;
}
.task-content h4 { font-size: 14px; margin-bottom: 4px; }
.task-content p { font-size: 12px; color: #64748B; margin-bottom: 4px; }
.task-content span { font-size: 11px; color: #94A3B8; }

/* Follow up */
.followup-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    background: #F8FAFC;
    border-radius: 12px;
    margin-bottom: 12px;
}
.followup-info { display: flex; align-items: center; gap: 12px; }
.followup-info img { width: 40px; height: 40px; border-radius: 50%; }
.followup-info h4 { font-size: 14px; margin-bottom: 2px; }
.followup-info p { font-size: 11px; color: #64748B; margin: 0; }
.followup-actions { display: flex; gap: 6px; }

/* Review Item */
.review-item {
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #F1F5F9;
}
.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.review-user { display: flex; align-items: center; gap: 8px; }
.review-user img { width: 32px; height: 32px; border-radius: 50%; }
.review-user h4 { font-size: 14px; }
.stars { color: #F59E0B; font-size: 12px; }
.review-text { font-size: 13px; color: #475569; margin: 0; }

@media (max-width: 1280px) {
    .doc-stats-row { gap: 14px; }
    .doc-stat-card { padding: 18px; }
    .doc-stat-icon { width: 44px; height: 44px; font-size: 18px; }
    .doc-main-layout { gap: 16px; }
}
@media (max-width: 1200px) {
    .doc-stats-row { gap: 12px; }
    .doc-stat-card { padding: 16px; }
    .doc-stat-info h3 { font-size: 24px; }
    .doc-stat-info p { font-size: 12px; }
}
@media (max-width: 768px) {
    .doc-stats-row { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .doc-main-layout { grid-template-columns: 1fr; }
    .doc-stat-info h3 { font-size: 20px; }
}

</style>

<section class="dior-tab-panel active" id="tab-doc-overview">
    <div class="doc-ov-grid">
        
        <!-- Top Stats Row -->
        <div class="doc-stats-row">
            <div class="doc-stat-card">
                <div class="doc-stat-info">
                    <h3 style="color:#4F46E5;">20</h3>
                    <p>Appointments</p>
                </div>
                <div class="doc-stat-icon icon-blue">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
            </div>
            
            <div class="doc-stat-card">
                <div class="doc-stat-info">
                    <h3 style="color:#EA580C;">90</h3>
                    <p>Upcoming Appointments</p>
                </div>
                <div class="doc-stat-icon icon-orange">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>

            <div class="doc-stat-card">
                <div class="doc-stat-info">
                    <h3 style="color:#16A34A;">23</h3>
                    <p>New Patients</p>
                </div>
                <div class="doc-stat-icon icon-green">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
            </div>

            <div class="doc-stat-card">
                <div class="doc-stat-info">
                    <h3 style="color:#0891B2;">$500.00</h3>
                    <p>Total Earning</p>
                </div>
                <div class="doc-stat-icon icon-cyan">
                    <i class="fa-solid fa-dollar-sign"></i>
                </div>
            </div>
        </div>

        <!-- Main Layout -->
        <div class="doc-main-layout">
            
            <!-- LEFT COLUMN -->
            <div class="doc-left-col">
                
                <!-- Today's Schedule -->
                <div class="doc-card">
                    <div class="doc-card-header">
                        <h3 class="doc-card-title">Today's Schedule</h3>
                        <div style="display:flex; gap:8px;">
                            <span class="badge badge-completed">2 Completed</span>
                            <span class="badge badge-upcoming">3 Upcoming</span>
                            <span class="badge badge-missed">1 Missed</span>
                        </div>
                    </div>
                    
                    <div class="doc-timeline">
                        
                        <div class="timeline-item">
                            <div class="timeline-time">2:00 PM</div>
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-user">
                                    <img src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff&color=4f46e5" alt="User">
                                    <div>
                                        <h4>Mia Song</h4>
                                        <p>PAT0012</p>
                                    </div>
                                </div>
                                <div class="timeline-actions">
                                    <span class="badge badge-inprogress">In Progress</span>
                                </div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-time">1:00 PM</div>
                            <div class="timeline-dot" style="background:#10B981;"></div>
                            <div class="timeline-content">
                                <div class="timeline-user">
                                    <img src="https://ui-avatars.com/api/?name=John+Johnson&background=f0fdf4&color=166534" alt="User">
                                    <div>
                                        <h4>John Johnson</h4>
                                        <p>PAT0014</p>
                                    </div>
                                </div>
                                <div class="timeline-actions">
                                    <span class="badge badge-completed">Completed</span>
                                </div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-time">12:30 PM</div>
                            <div class="timeline-dot" style="background:#F43F5E;"></div>
                            <div class="timeline-content">
                                <div class="timeline-user">
                                    <img src="https://ui-avatars.com/api/?name=Richard+Davis&background=fff1f2&color=be123c" alt="User">
                                    <div>
                                        <h4>Richard Davis</h4>
                                        <p>PAT0024</p>
                                    </div>
                                </div>
                                <div class="timeline-actions">
                                    <span class="badge badge-consult">Consultant</span>
                                </div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-time">3:00 PM</div>
                            <div class="timeline-dot" style="background:#F59E0B;"></div>
                            <div class="timeline-content">
                                <div class="timeline-user">
                                    <img src="https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7&color=b45309" alt="User">
                                    <div>
                                        <h4>Elizabeth Brown</h4>
                                        <p>PAT0010</p>
                                    </div>
                                </div>
                                <div class="timeline-actions">
                                    <button class="btn-icon"><i class="fa-solid fa-video"></i></button>
                                    <button class="btn-icon"><i class="fa-solid fa-envelope"></i></button>
                                    <button class="btn-join">Join Now</button>
                                    <span class="badge badge-upcoming" style="margin-left:8px;">Upcoming</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Latest Appointments -->
                <div class="doc-card">
                    <div class="doc-card-header">
                        <h3 class="doc-card-title">Latest Appointments</h3>
                    </div>
                    <table class="doc-table">
                        <thead>
                            <tr>
                                <th>Appointments Id</th>
                                <th>Patient Name</th>
                                <th>Date</th>
                                <th>Disease</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>APP00123</td>
                                <td>Mia Song</td>
                                <td>Sept 12, 2026</td>
                                <td>Fever</td>
                                <td>
                                    <button class="btn-outline">Prescription</button>
                                    <button class="btn-outline active">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td>APP00111</td>
                                <td>John Johnson</td>
                                <td>Sept 10, 2026</td>
                                <td>Cholera</td>
                                <td>
                                    <button class="btn-outline">Prescription</button>
                                    <button class="btn-outline active">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td>APP00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Aug 26, 2026</td>
                                <td>Jaundice</td>
                                <td>
                                    <button class="btn-outline">Prescription</button>
                                    <button class="btn-outline active">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td>APP00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Aug 26, 2026</td>
                                <td>Typhoid</td>
                                <td>
                                    <button class="btn-outline">Prescription</button>
                                    <button class="btn-outline active">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td>APP00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Aug 25, 2026</td>
                                <td>Malaria</td>
                                <td>
                                    <button class="btn-outline">Prescription</button>
                                    <button class="btn-outline active">Details</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Prescriptions Overview -->
                <div class="doc-card">
                    <div class="doc-card-header">
                        <h3 class="doc-card-title">Prescriptions Overview</h3>
                        <div style="display:flex; gap:8px;">
                            <span class="badge badge-completed">45 Active</span>
                            <span class="badge badge-upcoming">12 Inactive</span>
                            <span class="badge badge-missed">6 Expired</span>
                        </div>
                    </div>
                    <table class="doc-table">
                        <thead>
                            <tr>
                                <th>Prescriptions Id</th>
                                <th>Patient Name</th>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>PRS00123</td>
                                <td>Mia Song</td>
                                <td>Amlodipine</td>
                                <td>5mg</td>
                                <td><span class="badge badge-completed">Active</span></td>
                            </tr>
                            <tr>
                                <td>PRS00111</td>
                                <td>John Johnson</td>
                                <td>Amoxicillin</td>
                                <td>500mg</td>
                                <td><span class="badge badge-upcoming">Inactive</span></td>
                            </tr>
                            <tr>
                                <td>PRS00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Atorvastatin</td>
                                <td>20mg</td>
                                <td><span class="badge badge-completed">Active</span></td>
                            </tr>
                            <tr>
                                <td>PRS00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Ibuprofen</td>
                                <td>400mg</td>
                                <td><span class="badge badge-completed">Active</span></td>
                            </tr>
                            <tr>
                                <td>PRS00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Lisinopril</td>
                                <td>10mg</td>
                                <td><span class="badge badge-completed">Active</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Latest Lab Results -->
                <div class="doc-card">
                    <div class="doc-card-header">
                        <h3 class="doc-card-title">Latest Lab Results</h3>
                    </div>
                    <table class="doc-table">
                        <thead>
                            <tr>
                                <th>Patient Id</th>
                                <th>Patient Name</th>
                                <th>Test Name</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>PAF00121</td>
                                <td>Mia Song</td>
                                <td>Blood Work</td>
                                <td>Sept 12, 2026</td>
                                <td><span class="badge badge-completed">Ready</span></td>
                                <td>
                                    <button class="btn-icon"><i class="fa-solid fa-info"></i></button>
                                    <button class="btn-icon"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>PAF00111</td>
                                <td>John Johnson</td>
                                <td>Renal Function Test</td>
                                <td>Sept 10, 2026</td>
                                <td><span class="badge badge-upcoming">Pending</span></td>
                                <td>
                                    <button class="btn-icon"><i class="fa-solid fa-info"></i></button>
                                    <button class="btn-icon"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>PAF00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Thyroid Function Test</td>
                                <td>Aug 26, 2026</td>
                                <td><span class="badge badge-inprogress">In Progress</span></td>
                                <td>
                                    <button class="btn-icon"><i class="fa-solid fa-info"></i></button>
                                    <button class="btn-icon"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>PAF00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Vitamin D Test</td>
                                <td>Aug 26, 2026</td>
                                <td><span class="badge badge-completed">Normal</span></td>
                                <td>
                                    <button class="btn-icon"><i class="fa-solid fa-info"></i></button>
                                    <button class="btn-icon"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>PAF00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Lipid Panel</td>
                                <td>Aug 25, 2026</td>
                                <td><span class="badge badge-completed">Normal</span></td>
                                <td>
                                    <button class="btn-icon"><i class="fa-solid fa-info"></i></button>
                                    <button class="btn-icon"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Split Cards -->
                <div class="doc-split-cards">
                    <!-- Patient Review -->
                    <div class="doc-card">
                        <div class="doc-card-header">
                            <h3 class="doc-card-title">Patient Review</h3>
                        </div>
                        
                        <div class="review-item">
                            <div class="review-header">
                                <div class="review-user">
                                    <img src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff" alt="User">
                                    <h4>Mia Song</h4>
                                </div>
                                <div class="stars">
                                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                </div>
                            </div>
                            <p class="review-text">Excellent doctor! Very professional and caring.</p>
                        </div>
                        
                        <div class="review-item">
                            <div class="review-header">
                                <div class="review-user">
                                    <img src="https://ui-avatars.com/api/?name=John+Johnson&background=f0fdf4" alt="User">
                                    <h4>John Johnson</h4>
                                </div>
                                <div class="stars">
                                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                </div>
                            </div>
                            <p class="review-text">Great experience. Highly recommended!</p>
                        </div>

                        <div class="review-item">
                            <div class="review-header">
                                <div class="review-user">
                                    <img src="https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7" alt="User">
                                    <h4>Elizabeth Brown</h4>
                                </div>
                                <div class="stars">
                                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                </div>
                            </div>
                            <p class="review-text">Very knowledgeable and patient.</p>
                        </div>

                    </div>

                    <!-- Upcoming Sessions -->
                    <div class="doc-card">
                        <div class="doc-card-header">
                            <h3 class="doc-card-title">Upcoming Sessions</h3>
                        </div>
                        
                        <div class="followup-item" style="background:#FFF; border:1px solid #E2E8F0; padding:16px; margin-bottom:16px;">
                            <div class="followup-info">
                                <img src="https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7" alt="User" style="width:50px; height:50px;">
                                <div>
                                    <h4 style="font-size:15px; margin-bottom:4px;">General Consultation</h4>
                                    <p style="margin-bottom:6px;">Elizabeth Brown | PAT0010</p>
                                    <p><i class="fa-regular fa-calendar" style="color:#2C6CB1"></i> Sep 29, 2026 &nbsp;&nbsp; <i class="fa-regular fa-clock" style="color:#2C6CB1"></i> 04:30 PM</p>
                                </div>
                            </div>
                            <div class="followup-actions" style="flex-direction:column; gap:8px;">
                                <button class="btn-outline" style="width:100%;">Reschedule</button>
                                <button class="btn-join" style="width:100%;">Join Now</button>
                            </div>
                        </div>
                        
                        <div class="followup-item" style="background:#FFF; border:1px solid #E2E8F0; padding:16px; margin-bottom:16px;">
                            <div class="followup-info">
                                <img src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff" alt="User" style="width:50px; height:50px;">
                                <div>
                                    <h4 style="font-size:15px; margin-bottom:4px;">Follow-up Appointment</h4>
                                    <p style="margin-bottom:6px;">Mia Song | PAT0012</p>
                                    <p><i class="fa-regular fa-calendar" style="color:#2C6CB1"></i> Oct 2, 2026 &nbsp;&nbsp; <i class="fa-regular fa-clock" style="color:#2C6CB1"></i> 11:30 AM</p>
                                </div>
                            </div>
                            <div class="followup-actions" style="flex-direction:column; gap:8px;">
                                <button class="btn-outline" style="width:100%;">Reschedule</button>
                                <button class="btn-join" style="width:100%;">Join Now</button>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
            
            <!-- RIGHT COLUMN -->
            <div class="doc-right-col">
                
                <!-- Profile Card -->
                <div class="doc-card profile-card">
                    <div class="profile-img-wrap">
                        <img src="https://ui-avatars.com/api/?name=Dr+Diorca&background=random" alt="Doctor">
                    </div>
                    <h3 style="color:#2C6CB1;">Dr. Diorca Aquino De La Cruz</h3>
                    <p>Cardiologist</p>
                    
                    <div class="profile-details">
                        <div><i class="fa-solid fa-envelope"></i> abc@gmail.com</div>
                        <div><i class="fa-solid fa-phone"></i> +1 234 567 8900</div>
                        <div><i class="fa-solid fa-briefcase"></i> 12 years experience</div>
                    </div>
                    
                    <button class="btn-join" style="width:100%;">Edit Profile</button>
                </div>

                <!-- Pending Tasks -->
                <div class="doc-card">
                    <div class="doc-card-header">
                        <h3 class="doc-card-title">Pending Tasks</h3>
                    </div>
                    
                    <div class="task-list">
                        <div class="task-item">
                            <input type="checkbox">
                            <div class="task-content">
                                <h4>Review lab reports</h4>
                                <p>Check blood test results for 3 patients</p>
                                <span><i class="fa-regular fa-clock"></i> Oct 2, 2026</span>
                            </div>
                        </div>
                        
                        <div class="task-item">
                            <input type="checkbox">
                            <div class="task-content">
                                <h4>Sign prescriptions</h4>
                                <p>5 prescriptions pending signature</p>
                                <span><i class="fa-regular fa-clock"></i> Oct 2, 2026</span>
                            </div>
                        </div>

                        <div class="task-item">
                            <input type="checkbox">
                            <div class="task-content">
                                <h4>Approve medical notes</h4>
                                <p>Review and approve consultation notes</p>
                                <span><i class="fa-regular fa-clock"></i> Oct 3, 2026</span>
                            </div>
                        </div>

                        <div class="task-item">
                            <input type="checkbox">
                            <div class="task-content">
                                <h4>Update patient records</h4>
                                <p>Complete EMR updates for recent visits</p>
                                <span><i class="fa-regular fa-clock"></i> Oct 4, 2026</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Follow-up Reminders -->
                <div class="doc-card">
                    <div class="doc-card-header">
                        <h3 class="doc-card-title">Follow-up Reminders</h3>
                    </div>
                    
                    <div class="followup-list">
                        <div class="followup-item">
                            <div class="followup-info">
                                <img src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff" alt="User">
                                <div>
                                    <h4>Mia Song | PAT0015</h4>
                                    <p>Routine Checkup</p>
                                    <p style="margin-top:2px;"><i class="fa-regular fa-calendar"></i> Oct 5, 2026</p>
                                </div>
                            </div>
                            <div class="followup-actions">
                                <button class="btn-icon" style="background:#FFF; border:1px solid #E2E8F0;"><i class="fa-solid fa-envelope"></i></button>
                                <button class="btn-icon" style="background:#FFF; border:1px solid #E2E8F0;"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>
                        
                        <div class="followup-item">
                            <div class="followup-info">
                                <img src="https://ui-avatars.com/api/?name=John+Johnson&background=f0fdf4" alt="User">
                                <div>
                                    <h4>John Johnson | PAT0011</h4>
                                    <p>Routine Checkup</p>
                                    <p style="margin-top:2px;"><i class="fa-regular fa-calendar"></i> Oct 6, 2026</p>
                                </div>
                            </div>
                            <div class="followup-actions">
                                <button class="btn-icon" style="background:#FFF; border:1px solid #E2E8F0;"><i class="fa-solid fa-envelope"></i></button>
                                <button class="btn-icon" style="background:#FFF; border:1px solid #E2E8F0;"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>

                        <div class="followup-item">
                            <div class="followup-info">
                                <img src="https://ui-avatars.com/api/?name=Richard+Davis&background=fff1f2" alt="User">
                                <div>
                                    <h4>Richard Davis | PAT00120</h4>
                                    <p>Routine Checkup</p>
                                    <p style="margin-top:2px;"><i class="fa-regular fa-calendar"></i> Oct 7, 2026</p>
                                </div>
                            </div>
                            <div class="followup-actions">
                                <button class="btn-icon" style="background:#FFF; border:1px solid #E2E8F0;"><i class="fa-solid fa-envelope"></i></button>
                                <button class="btn-icon" style="background:#FFF; border:1px solid #E2E8F0;"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>

                        <div class="followup-item">
                            <div class="followup-info">
                                <img src="https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7" alt="User">
                                <div>
                                    <h4>Elizabeth Brown | PAT00112</h4>
                                    <p>Routine Checkup</p>
                                    <p style="margin-top:2px;"><i class="fa-regular fa-calendar"></i> Oct 7, 2026</p>
                                </div>
                            </div>
                            <div class="followup-actions">
                                <button class="btn-icon" style="background:#FFF; border:1px solid #E2E8F0;"><i class="fa-solid fa-envelope"></i></button>
                                <button class="btn-icon" style="background:#FFF; border:1px solid #E2E8F0;"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
