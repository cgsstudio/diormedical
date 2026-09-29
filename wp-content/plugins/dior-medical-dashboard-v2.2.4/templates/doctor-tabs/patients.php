<?php defined('ABSPATH') || exit; ?>
<section class="dior-tab-panel dior-source-group" id="tab-doc-patients">
<div class="dior-content-pad">
<div class="dior-source-group-head"><div><div class="dior-source-kicker"><i class="fa-solid fa-users"></i> Doctor Workspace</div><h2>Patients</h2><p>Structured workspace using the same visual language as the Patient Dashboard.</p></div></div>
<div class="dior-source-subtabs" role="tablist"><button type="button" class="dior-source-subtab-btn active" data-source-target="all-patients"><i class="fa-solid fa-users"></i><span>All Patients</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="add-patient"><i class="fa-solid fa-user-plus"></i><span>Add Patient</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="edit-patient"><i class="fa-solid fa-user-pen"></i><span>Edit Patient</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="patient-records"><i class="fa-solid fa-folder-open"></i><span>Patient Records</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="patient-profile"><i class="fa-solid fa-id-card"></i><span>Patient Profile</span></button></div>
<div class="dior-source-subcontent"><?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/all-patients.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/add-patient.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/edit-patient.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/patient-records.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/patient-profile.php"; ?>
</div>
</div></section>
