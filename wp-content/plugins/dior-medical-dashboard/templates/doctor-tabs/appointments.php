<?php defined('ABSPATH') || exit; ?>
<section class="dior-tab-panel dior-source-group" id="tab-doc-appointments">
<div class="dior-content-pad">
<div class="dior-source-group-head"><div><div class="dior-source-kicker"><i class="fa-solid fa-calendar-check"></i> Doctor Workspace</div><h2>Appointments</h2><p>Structured workspace using the same visual language as the Patient Dashboard.</p></div></div>
<div class="dior-source-subtabs" role="tablist"><button type="button" class="dior-source-subtab-btn active" data-source-target="appointment-calendar"><i class="fa-solid fa-calendar-days"></i><span>Appointment Calendar</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="view-appointment"><i class="fa-solid fa-list-check"></i><span>View Appointment</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="edit-appointment"><i class="fa-solid fa-pen-to-square"></i><span>Edit Appointment</span></button><button type="button" class="dior-source-subtab-btn" data-source-target="new-appointment"><i class="fa-solid fa-calendar-plus"></i><span>New Appointment</span></button></div>
<div class="dior-source-subcontent"><?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/appointment-calendar.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/view-appointment.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/edit-appointment.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/new-appointment.php"; ?>
</div>
</div></section>
