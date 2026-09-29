<?php
defined('ABSPATH') || exit;

$pharmacy_appointments = class_exists('Dior_Appointment_Service')
    ? Dior_Appointment_Service::get_doctor_appointments(get_current_user_id())
    : [];
?>
<section class="dior-tab-panel" id="tab-doc-pharmacy">
    <div class="dior-content-pad">
        <div class="dior-source-group-head">
            <div>
                <div class="dior-source-kicker"><i class="fa-solid fa-pills"></i> Doctor Workspace</div>
                <h2>Pharmacy</h2>
                <p>Prescription and medication activity for your patients.</p>
            </div>
        </div>
        <div class="master-table-wrapper">
            <div class="master-table-container">
                <div class="master-table-card">
                    <div class="master-table-header">
                        <div class="header-content">
                            <div class="table-title-section">
                                <h2 class="table-title">Pharmacy</h2>
                                <div class="title-accent"></div>
                            </div>
                        </div>
                    </div>
                    <div class="section-body">
                        <div class="dior-empty-state">
                            <i class="fa-solid fa-pills"></i>
                            <h3>Medication activity</h3>
                            <p><?php echo esc_html(count($pharmacy_appointments)); ?> appointment record(s) are currently associated with your practice.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
