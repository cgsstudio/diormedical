<section class="dior-tab-panel appointment-form-wrapper" id="tab-doc-appointments-book" style="display: none;">
    <!-- Standardized appointment page header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="mb-1 text-dark fw-bold fs-5 d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="fas fa-calendar-plus fs-6"></i>
                </span>
                Book Appointment
            </h4>
            <div class="text-secondary small mb-1">Schedule new patient consultations and clinical appointments.</div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small text-secondary">
                    <li class="breadcrumb-item"><i class="fas fa-house me-1"></i> Dashboard</li>
                    <li class="breadcrumb-item">Appointments</li>
                    <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Book Appointment</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-appointments" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments');">
            <i class="fas fa-calendar-days"></i> Appointment Calendar
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-appointments-view" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-view');">
            <i class="fas fa-calendar-check"></i> View Appointment
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-appointments-book" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-book');">
            <i class="fas fa-calendar-plus"></i> Book Appointment
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-appointments-edit" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-appointments-edit');">
            <i class="fas fa-pen-to-square"></i> Edit Appointment
        </button>
    </div>

    <!-- Main Content -->
    <div class="section-body">
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-none bg-transparent p-0 m-0 w-100">
                    <div class="card-body p-0 m-0 w-100">
                        <div class="dior-hipaatizer-booking-wrapper w-100 p-0 m-0">
                            <?php
                            // Reuse the configured HIPAAtizer workflow; keep the existing dashboard card and page layout intact.
                            if (shortcode_exists('hipaatizer')) {
                                echo do_shortcode('[hipaatizer id="01a12050-38e5-74ba-967b-60dacc2bd7f7"]');
                            } else {
                                echo '<p class="text-muted">The HIPAAtizer form is currently unavailable. Please ensure the HIPAAtizer plugin is active.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
