<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/book-appointment.css?v=' . time()); ?>">

<section class="dior-tab-panel appointment-form-wrapper" id="tab-doc-appointments-book" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Book Appointment</h4>
        </div>
        <div>
            <ul class="breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)">Appointments</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active">Book Appointment</li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="section-body">
        <div class="row">
            <div class="col-12 col-md-12 col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Book Appointment</h4>
                    </div>
                    <div class="card-body">
                        <div class="dior-hipaatizer-booking-wrapper">
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
