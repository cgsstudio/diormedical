<section class="dior-tab-panel appointment-form-wrapper" id="tab-doc-appointments-book" style="display: none;">
    <!-- Breadcrumb & Header (100% Native Bootstrap 5) -->
    <div class="row g-3 align-items-center justify-content-between mb-4">
        <div class="col-12 col-lg-auto">
            <div class="h6 mb-1 fw-bold text-dark">
                <i class="fas fa-calendar-plus text-primary me-2"></i>Book Appointment
            </div>
            <div class="text-secondary small">Schedule new patient consultations and clinical appointments.</div>
        </div>
        <div class="col-12 col-lg-auto">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small text-muted">
                    <li class="breadcrumb-item"><i class="fas fa-house me-1"></i> Dashboard</li>
                    <li class="breadcrumb-item">Appointments</li>
                    <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Book Appointment</li>
                </ol>
            </nav>
        </div>
    </div>\n\n<!-- Main Content -->
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
