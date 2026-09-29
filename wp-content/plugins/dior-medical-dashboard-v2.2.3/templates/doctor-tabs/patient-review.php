<?php
defined('ABSPATH') || exit;

$review_doctor_id = get_current_user_id();
$review_appointments = class_exists('Dior_Appointment_Service')
    ? Dior_Appointment_Service::get_doctor_appointments($review_doctor_id)
    : [];
?>
<section class="dior-tab-panel" id="tab-doc-patient-review">
    <div class="dior-content-pad">
        <div class="dior-source-group-head">
            <div>
                <div class="dior-source-kicker"><i class="fa-solid fa-star"></i> Doctor Workspace</div>
                <h2>Patient Review</h2>
                <p>Patient feedback associated with your practice.</p>
            </div>
        </div>
        <div class="master-table-wrapper">
            <div class="master-table-container">
                <div class="master-table-card">
                    <div class="master-table-header">
                        <div class="header-content">
                            <div class="table-title-section">
                                <h2 class="table-title">Patient Review</h2>
                                <div class="title-accent"></div>
                            </div>
                        </div>
                    </div>
                    <div class="section-body">
                        <div class="dior-empty-state">
                            <i class="fa-solid fa-star"></i>
                            <h3>Patient reviews</h3>
                            <p>Review data will appear here when patient feedback is recorded.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
