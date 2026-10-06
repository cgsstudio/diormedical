<section class="dior-tab-panel" id="tab-questionnaire">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Clinical Intake & Medical Questionnaires</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Clinical Intake & Medical Questionnaires</span></li>
            </ul>
        </div>
    </div>

                    <div class="dior-page-header-box">
                        <div>
                            <h2>Clinical Intake & Medical Questionnaires</h2>
                        </div>
                        <!-- Official HIPAAtizer Questionnaire Embed Card -->
                        <div class="dior-section-block dior-ic-8a06bb1bf2">
                            <?php
                            $has_intake = !empty($hipaa_intake) && is_array($hipaa_intake);
                            ?>

                            <?php if (!$is_prof_complete): ?>
                            <!-- 🔒 Incomplete Profile Lock Notice in Questionnaire Tab -->
                            <div class="dior-card dior-ic-c696d3ffa4"
                               >
                                <div
                                    class="dior-ic-ff43736ffd">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <h3 class="dior-ic-4285f57078">Personal
                                    Profile Required</h3>
                                <p
                                    class="dior-ic-8534e9be0b">
                                    Please complete your required personal information (Name, Phone, Date of Birth,
                                    Gender, Address) in your profile before filling out medical questionnaires or
                                    booking appointments.
                                </p>
                                <button type="button" class="dior-btn-gold-primary dior-ic-2b79c76f5b" data-switch-tab="settings"
                                   >
                                    <i class="fas fa-user-pen"></i> Complete Personal Information First
                                </button>
                            </div>
                            <?php endif; ?>

                            <?php if ($has_intake): ?>
                            <!-- ✅ Top Status Banner When Intake is Completed -->
                            <div class="dior-intake-completed-notice dior-ic-d1ca225dc9" id="dior-intake-completed-notice"
                               >
                                <div class="dior-ic-29bdac4ec4">
                                    <div
                                        class="dior-ic-37258c726e">
                                        <i class="fas fa-circle-check"></i>
                                    </div>
                                    <div>
                                        <strong class="dior-ic-a6c6cc4d52">Clinical Intake
                                            Questionnaire Completed</strong>
                                        <span class="dior-ic-7c0e3bda5e">Submitted on
                                            <?php echo esc_html(date('M j, Y g:i A', strtotime($hipaa_intake['submitted_at'] ?? 'now'))); ?>
                                            &bull; Data verified &amp; ready for physician review</span>
                                    </div>
                                </div>
                                <div class="dior-ic-669f60ce02">
                                    <button type="button" class="dior-btn-gold-secondary dior-ic-603a8f0148"
                                        onclick="diorToggleIntakeReview()"
                                       >
                                        <i class="fas fa-file-medical"></i> <span id="toggle-intake-btn-text">View
                                            Submitted Answers</span>
                                    </button>
                                    <button type="button" class="dior-btn-gold-secondary dior-ic-603a8f0148"
                                        onclick="diorReopenHipaaForm()"
                                       >
                                        <i class="fas fa-arrows-rotate"></i> Re-open Questionnaire
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- ℹ️ Step 1 Guidance Banner When Intake Is NOT Yet Completed -->
                            <div class="dior-intake-step-notice dior-ic-2de3535f34" id="dior-intake-step-notice"
                               >
                                <div
                                    class="dior-ic-f0fa331c40">
                                    <i class="fas fa-clipboard-question"></i>
                                </div>
                                <div>
                                    <strong class="dior-ic-6b8a18039f">Step 1 of 2: Complete
                                        Your Clinical Intake Questionnaire</strong>
                                    <span class="dior-ic-586fd3a719">Please complete the medical
                                        questionnaire below. Once you submit, your <strong>Doctor Scheduling
                                            Calendar</strong> will automatically unlock on this page!</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($has_intake): ?>
                            <!-- ✅ Submitted Intake Data Card (Collapsible) -->
                            <div class="dior-card dior-ic-6ed7352fd1" id="dior-intake-review-card"
                               >
                                <div class="dior-card-header dior-ic-f51d57bb28"
                                   >
                                    <h3 class="dior-ic-c7bbe9b97b"><i class="fas fa-circle-check"></i> HIPAA Intake
                                        Form — Submitted</h3>
                                    <span class="dior-st ok"><i class="fas fa-calendar"></i>
                                        <?php echo esc_html(!empty($hipaa_intake['submitted_at']) ? date('M j, Y g:i A', strtotime($hipaa_intake['submitted_at'])) : date('M j, Y')); ?></span>
                                </div>
                                <div class="dior-card-body">
                                    <div class="dior-form-row-2 dior-ic-e0e28a337c">
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fas fa-user"></i> Full Name</span>
                                            <span
                                                class="pf-value"><?php echo esc_html(trim(($hipaa_intake['first_name'] ?? '') . ' ' . ($hipaa_intake['last_name'] ?? '')) ?: ($profile['full_name'] ?? '—')); ?></span>
                                        </div>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fas fa-cake-candles"></i> Date of
                                                Birth</span>
                                            <span
                                                class="pf-value"><?php echo esc_html(!empty($hipaa_intake['dob']) ? $hipaa_intake['dob'] : (!empty($profile['dob']) ? $profile['dob'] : '—')); ?></span>
                                        </div>
                                    </div>
                                    <div class="dior-form-row-2 dior-ic-e0e28a337c">
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fas fa-phone"></i> Phone</span>
                                            <span
                                                class="pf-value"><?php echo esc_html(!empty($hipaa_intake['phone']) ? $hipaa_intake['phone'] : (!empty($profile['phone']) ? $profile['phone'] : '—')); ?></span>
                                        </div>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fas fa-envelope"></i> Email</span>
                                            <span
                                                class="pf-value"><?php echo esc_html(!empty($hipaa_intake['email']) ? $hipaa_intake['email'] : (!empty($profile['email']) ? $profile['email'] : ($current_user->user_email ?? '—'))); ?></span>
                                        </div>
                                    </div>
                                    <?php
                                    $intake_address_parts = array_filter([
                                        $hipaa_intake['address'] ?? '',
                                        $hipaa_intake['city'] ?? '',
                                        $hipaa_intake['state'] ?? '',
                                        $hipaa_intake['zip'] ?? ''
                                    ]);
                                    $formatted_intake_address = !empty($intake_address_parts) ? implode(' ', $intake_address_parts) : (!empty($profile['address']) ? $profile['address'] : '');
                                    if (!empty($formatted_intake_address)):
                                        ?>
                                    <div class="dior-profile-field-box dior-ic-e0e28a337c">
                                        <span class="pf-label"><i class="fas fa-location-dot"></i> Address</span>
                                        <span class="pf-value"><?php echo esc_html($formatted_intake_address); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($hipaa_intake['symptoms'])): ?>
                                    <div
                                        class="dior-ic-dd9456ac71">
                                        <strong class="dior-ic-f2ba68561c"><i
                                                class="fas fa-stethoscope"></i> Chief Symptoms /
                                            Complaint:</strong>
                                        <span><?php echo nl2br(esc_html($hipaa_intake['symptoms'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($hipaa_intake['allergies'])): ?>
                                    <div
                                        class="dior-ic-dad648367c">
                                        <strong class="dior-ic-e8f5bfad42"><i
                                                class="fas fa-triangle-exclamation"></i> Known Allergies:</strong>
                                        <span><?php echo esc_html($hipaa_intake['allergies']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($hipaa_intake['medications'])): ?>
                                    <div
                                        class="dior-ic-dad648367c">
                                        <strong class="dior-ic-5f80400f91"><i
                                                class="fas fa-pills"></i> Current Medications (Legacy):</strong>
                                        <span><?php echo esc_html($hipaa_intake['medications']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($hipaa_intake['medical_history'])): ?>
                                    <div
                                        class="dior-ic-426be80846">
                                        <strong class="dior-ic-d244c256b3"><i
                                                class="fas fa-notes-medical"></i> Medical History
                                            (Legacy):</strong>
                                        <span><?php echo nl2br(esc_html($hipaa_intake['medical_history'])); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <!-- New comprehensive display -->
                                    <?php if (!empty($hipaa_intake['service_condition'])): ?>
                                    <div class="dior-profile-field-box dior-ic-e0e28a337c">
                                        <span class="pf-label"><i class="fas fa-stethoscope"></i> Service
                                            Condition</span>
                                        <span
                                            class="pf-value"><?php echo esc_html($hipaa_intake['service_condition']); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="dior-form-row-2 dior-ic-e0e28a337c">
                                        <?php if (!empty($hipaa_intake['is_18_plus'])): ?>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fas fa-user-shield"></i> 18+
                                                Verified</span>
                                            <span
                                                class="pf-value"><?php echo esc_html($hipaa_intake['is_18_plus']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($hipaa_intake['is_pregnant'])): ?>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fas fa-baby"></i> Pregnancy
                                                Status</span>
                                            <span
                                                class="pf-value"><?php echo esc_html($hipaa_intake['is_pregnant']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($hipaa_intake['symptoms_description'])): ?>
                                    <div
                                        class="dior-ic-dd9456ac71">
                                        <strong class="dior-ic-f2ba68561c"><i
                                                class="fas fa-stethoscope"></i> Symptoms Description:</strong>
                                        <span><?php echo nl2br(esc_html($hipaa_intake['symptoms_description'])); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="dior-form-row-2 dior-ic-e0e28a337c">
                                        <?php if (!empty($hipaa_intake['symptom_duration'])): ?>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fas fa-clock"></i> Duration</span>
                                            <span
                                                class="pf-value"><?php echo esc_html($hipaa_intake['symptom_duration']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($hipaa_intake['symptom_severity'])): ?>
                                        <div class="dior-profile-field-box">
                                            <span class="pf-label"><i class="fas fa-exclamation-triangle"></i>
                                                Severity</span>
                                            <span
                                                class="pf-value"><?php echo esc_html($hipaa_intake['symptom_severity']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($hipaa_intake['uti_burning_pain']) || !empty($hipaa_intake['uti_frequency'])): ?>
                                    <div
                                        class="dior-ic-5997cd2304">
                                        <h5 class="dior-ic-9f8a287aa0"><i
                                                class="fas fa-droplet"></i> UTI Details</h5>
                                        <div class="dior-ic-98884f8aff">
                                            <?php if (!empty($hipaa_intake['uti_burning_pain'])): ?>
                                            <div><strong>Burning/Pain:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_burning_pain']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['uti_frequency'])): ?>
                                            <div><strong>Frequency:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_frequency']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['uti_appearance'])): ?>
                                            <div><strong>Urine Appearance:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_appearance']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['uti_blood'])): ?>
                                            <div><strong>Blood in Urine:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_blood']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['uti_previous'])): ?>
                                            <div><strong>Previous UTI:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_previous']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['uti_last_date'])): ?>
                                            <div class="dior-ic-ef39ab073b"><strong>Last UTI Date:</strong>
                                                <?php echo esc_html($hipaa_intake['uti_last_date']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['allergy_symptoms']) || !empty($hipaa_intake['allergy_triggers'])): ?>
                                    <div
                                        class="dior-ic-efe1f95ed6">
                                        <h5 class="dior-ic-98721629e4"><i
                                                class="fas fa-allergies"></i> Allergy Details</h5>
                                        <div class="dior-ic-98884f8aff">
                                            <?php if (!empty($hipaa_intake['allergy_symptoms'])): ?>
                                            <div class="dior-ic-ef39ab073b"><strong>Symptoms:</strong>
                                                <?php echo esc_html($hipaa_intake['allergy_symptoms']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['allergy_seasonal'])): ?>
                                            <div><strong>Pattern:</strong>
                                                <?php echo esc_html($hipaa_intake['allergy_seasonal']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['allergy_triggers'])): ?>
                                            <div><strong>Triggers:</strong>
                                                <?php echo esc_html($hipaa_intake['allergy_triggers']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['allergy_medication_taken'])): ?>
                                            <div><strong>Medication Taken:</strong>
                                                <?php echo esc_html($hipaa_intake['allergy_medication_taken']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['allergy_medication_name'])): ?>
                                            <div class="dior-ic-ef39ab073b"><strong>Medication Name:</strong>
                                                <?php echo esc_html($hipaa_intake['allergy_medication_name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['diagnosed_conditions']) || !empty($hipaa_intake['other_conditions'])): ?>
                                    <div
                                        class="dior-ic-608bc341d4">
                                        <h5 class="dior-ic-6d898ecf3e"><i
                                                class="fas fa-notes-medical"></i> Medical History</h5>
                                        <?php if (!empty($hipaa_intake['diagnosed_conditions'])): ?>
                                        <div class="dior-ic-952f9e597f"><strong>Diagnosed
                                                Conditions:</strong>
                                            <?php echo esc_html($hipaa_intake['diagnosed_conditions']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($hipaa_intake['other_conditions'])): ?>
                                        <div class="dior-ic-f4b1a2f418"><strong>Other Conditions:</strong>
                                            <?php echo nl2br(esc_html($hipaa_intake['other_conditions'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['taking_medications']) || !empty($hipaa_intake['medications_list'])): ?>
                                    <div
                                        class="dior-ic-7bc2800f3c">
                                        <h5 class="dior-ic-ab7c698ca4"><i
                                                class="fas fa-pills"></i> Current Medications</h5>
                                        <?php if (!empty($hipaa_intake['taking_medications'])): ?>
                                        <div class="dior-ic-952f9e597f"><strong>Taking
                                                Medications:</strong>
                                            <?php echo esc_html($hipaa_intake['taking_medications']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($hipaa_intake['medications_list'])): ?>
                                        <div class="dior-ic-f4b1a2f418"><strong>Medication List:</strong>
                                            <?php echo nl2br(esc_html($hipaa_intake['medications_list'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['drug_allergies']) || !empty($hipaa_intake['drug_allergies_list'])): ?>
                                    <div
                                        class="dior-ic-efe1f95ed6">
                                        <h5 class="dior-ic-98721629e4"><i
                                                class="fas fa-triangle-exclamation"></i> Drug Allergies</h5>
                                        <?php if (!empty($hipaa_intake['drug_allergies'])): ?>
                                        <div class="dior-ic-952f9e597f"><strong>Has Drug
                                                Allergies:</strong>
                                            <?php echo esc_html($hipaa_intake['drug_allergies']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($hipaa_intake['drug_allergies_list'])): ?>
                                        <div class="dior-ic-f4b1a2f418"><strong>Allergy Details:</strong>
                                            <?php echo nl2br(esc_html($hipaa_intake['drug_allergies_list'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['appointment_type']) || !empty($hipaa_intake['preferred_date'])): ?>
                                    <div
                                        class="dior-ic-e6cf659743">
                                        <h5 class="dior-ic-a45468e51f"><i
                                                class="fas fa-calendar"></i> Appointment Preferences</h5>
                                        <div class="dior-ic-98884f8aff">
                                            <?php if (!empty($hipaa_intake['appointment_type'])): ?>
                                            <div><strong>Type:</strong>
                                                <?php echo esc_html($hipaa_intake['appointment_type']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['preferred_date'])): ?>
                                            <div><strong>Date:</strong>
                                                <?php echo esc_html($hipaa_intake['preferred_date']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['preferred_time'])): ?>
                                            <div><strong>Time:</strong>
                                                <?php echo esc_html($hipaa_intake['preferred_time']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($hipaa_intake['telemedicine_consent']) || !empty($hipaa_intake['legal_acknowledgement'])): ?>
                                    <div
                                        class="dior-ic-44e184414e">
                                        <h5 class="dior-ic-78699bc6d3"><i
                                                class="fas fa-file-signature"></i> Consent & Signature</h5>
                                        <div class="dior-ic-98884f8aff">
                                            <?php if (!empty($hipaa_intake['telemedicine_consent'])): ?>
                                            <div><strong>Telemedicine Consent:</strong>
                                                <?php echo esc_html($hipaa_intake['telemedicine_consent']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['legal_acknowledgement'])): ?>
                                            <div><strong>Legal Acknowledgement:</strong>
                                                <?php echo esc_html($hipaa_intake['legal_acknowledgement']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['full_legal_name'])): ?>
                                            <div class="dior-ic-ef39ab073b"><strong>Legal Name:</strong>
                                                <?php echo esc_html($hipaa_intake['full_legal_name']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($hipaa_intake['signature_date'])): ?>
                                            <div><strong>Signature Date:</strong>
                                                <?php echo esc_html($hipaa_intake['signature_date']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="dior-hipaa-embed-wrap" id="dior-hipaa-form-container"
                                class="dior-intake-empty-panel <?php echo $has_intake ? 'dior-hidden' : ''; ?>">
                                <div class="hipaa-form-container-box dior-ic-d8fcd416f1"
                                   >
                                    <?php if (shortcode_exists('hipaatizer')): ?>
                                    <div class="hipaatizer-shortcode-wrapper">
                                        <?php
                                        // Render HIPAAtizer official form if plugin is active
                                        echo do_shortcode('[hipaatizer id="01a07aa2-d931-728d-bfe9-8d7b3bc0389d"]');
                                        ?>
                                    </div>
                                    <?php else: ?>
                                    <iframe id="hipaatizer-intake-iframe"
                                        src="https://app.hipaatizer.com/workflow/01a07aa2-d931-728d-bfe9-8d7b3bc0389d"
                                       
                                        allow="microphone; camera; payment"
                                        title="HIPAAtizer Clinical Intake &amp; Questionnaire Form" class="dior-ic-c5f1e2481d">
                                    </iframe>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Step-by-Step Embedded Appointment Booking Wizard (Unlocks automatically after Intake is completed) -->
                            <div class="dior-card dior-in-dashboard-booking-card" id="dior-dashboard-booking-section"
                                class="dior-intake-existing-panel <?php echo !$has_intake ? 'dior-hidden' : ''; ?>">
                                <div class="dior-card-header dior-ic-dbcac1c9c2"
                                   >
                                    <div>
                                        <h3
                                            class="dior-ic-0cab9b93d2">
                                            <i class="fas fa-calendar-check dior-ic-c416c351c1"></i>
                                            <span>Step 2: Schedule Telehealth Consultation with Physician</span>
                                        </h3>
                                        <p class="dior-ic-302f7d6005">Pre-filled from your
                                            intake form. Select your physician &amp; appointment time slot.</p>
                                    </div>
                                    <span class="dior-badge-luxury dior-ic-212d0017b3"
                                       >
                                        <i class="fas fa-shield-halved"></i> Intake Data Verified
                                    </span>
                                </div>

                                <div class="dior-card-body dior-ic-269bafc628">
                                    <!-- Wizard Step Pills -->
                                    <div class="dior-wizard-steps-indicator dior-ic-80f61378b8"
                                       >
                                        <div class="dior-step-node active dior-ic-bc50131783" id="wizard-node-1"
                                           >
                                            <span class="step-num dior-ic-b0abfda768"
                                               >1</span>
                                            <span class="step-lbl dior-ic-31e360b83f"
                                               >Select
                                                Physician</span>
                                        </div>
                                        <div class="dior-step-node dior-ic-2642a720ca" id="wizard-node-2"
                                           >
                                            <span class="step-num dior-ic-35b653b9f1"
                                               >2</span>
                                            <span class="step-lbl dior-ic-5765adbfa0"
                                               >Date &amp; Time
                                                Slot</span>
                                        </div>
                                        <div class="dior-step-node dior-ic-2642a720ca" id="wizard-node-3"
                                           >
                                            <span class="step-num dior-ic-35b653b9f1"
                                               >3</span>
                                            <span class="step-lbl dior-ic-5765adbfa0"
                                               >Confirm &amp;
                                                Book</span>
                                        </div>
                                    </div>

                                    <!-- Pre-filled Patient Intake Summary Banner -->
                                    <?php
                                    $pat_name = !empty($profile['full_name']) ? $profile['full_name'] : ($current_user && !empty($current_user->display_name) ? $current_user->display_name : 'Patient');
                                    $pat_email = !empty($profile['email']) ? $profile['email'] : ($current_user && !empty($current_user->user_email) ? $current_user->user_email : '');
                                    $pat_phone = !empty($profile['phone']) ? $profile['phone'] : ($hipaa_intake['phone'] ?? 'Phone on file');
                                    $pat_service = !empty($hipaa_intake['service_condition']) ? $hipaa_intake['service_condition'] : (!empty($hipaa_intake['symptoms']) ? $hipaa_intake['symptoms'] : 'Telehealth Urgent Care');
                                    ?>
                                    <div class="dior-intake-prefill-banner dior-ic-e583ba8700"
                                       >
                                        <div class="dior-ic-29bdac4ec4">
                                            <div
                                                class="dior-ic-435b456ef9">
                                                <i class="fas fa-user-check"></i>
                                            </div>
                                            <div>
                                                <strong
                                                    class="dior-ic-ad4079afc4"><?php echo esc_html($pat_name); ?></strong>
                                                <span
                                                    class="dior-ic-569fdd953a">
                                                    <?php if (!empty($pat_email)): ?>
                                                    <?php echo esc_html($pat_email); ?> &bull;
                                                    <?php endif; ?>
                                                    <?php echo esc_html($pat_phone); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div
                                            class="dior-ic-d09da58e1e">
                                            <div
                                                class="dior-ic-05af00f625">
                                                <i class="fas fa-stethoscope"></i>
                                            </div>
                                            <div>
                                                <span
                                                    class="dior-ic-a3631cfb25">Service
                                                    / Reason</span>
                                                <strong
                                                    class="dior-ic-8c623ed0ad"><?php echo esc_html($pat_service); ?></strong>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- WIZARD STEP 1: SELECT DOCTOR -->
                                    <div class="dior-in-booking-step" id="in-step-doctor">
                                        <h4 class="dior-ic-b46e313c98">
                                            <i class="fas fa-user-doctor dior-ic-5d59a9a58e"></i> Available
                                            Telehealth Physicians:
                                        </h4>
                                        <div id="in-dashboard-doctors-grid" class="dior-doctors-grid-cards dior-ic-4109a0209f"
                                           >
                                            <?php
                                            $docbooker_doctors = get_posts([
                                                'post_type' => 'wpddb_doctor',
                                                'post_status' => 'publish',
                                                'numberposts' => -1,
                                                'orderby' => 'title',
                                                'order' => 'ASC'
                                            ]);

                                            if (!empty($docbooker_doctors)):
                                                foreach ($docbooker_doctors as $doc_item):
                                                    $d_id = $doc_item->ID;
                                                    $d_name = $doc_item->post_title;
                                                    $d_spec = get_post_meta($d_id, 'wpddb_doctor_speciality', true) ?: 'Telehealth Physician';
                                                    ?>
                                            <div class="dior-doc-select-card" data-doc-id="<?php echo (int) $d_id; ?>"
                                                data-doc-name="<?php echo esc_attr($d_name); ?>"
                                                data-doc-spec="<?php echo esc_attr($d_spec); ?>"
                                                onclick="diorInSelectDoctor(<?php echo (int) $d_id; ?>, '<?php echo esc_js($d_name); ?>', '<?php echo esc_js($d_spec); ?>')"
                                                class="dior-ic-63abecac29">
                                                <div
                                                    class="dior-ic-b4deef6f98">
                                                    <div
                                                        class="dior-ic-7bd92c469d">
                                                        <i class="fas fa-user-md"></i>
                                                    </div>
                                                    <div>
                                                        <h5
                                                            class="dior-ic-b8390d3bcb">
                                                            <?php echo esc_html($d_name); ?>
                                                        </h5>
                                                        <span
                                                            class="dior-ic-56fdd6b556"><?php echo esc_html($d_spec); ?></span>
                                                    </div>
                                                </div>
                                                <div
                                                    class="dior-ic-181e1087c6">
                                                    <span><i class="far fa-clock"></i> Available via
                                                        DocBooker</span>
                                                    <span class="dior-ic-a669b3acb9">Select &rarr;</span>
                                                </div>
                                            </div>
                                            <?php
                                                endforeach;
                                            else:
                                                ?>
                                            <div class="dior-doc-select-card dior-ic-5c149fb4c3" data-doc-id="1695"
                                                data-doc-name="Dr. James Chen, DO"
                                                data-doc-spec="Primary Care &amp; Urgent Care"
                                                onclick="diorInSelectDoctor(1695, 'Dr. James Chen, DO', 'Primary Care &amp; Urgent Care')"
                                               >
                                                <div
                                                    class="dior-ic-b4deef6f98">
                                                    <div
                                                        class="dior-ic-7bd92c469d">
                                                        <i class="fas fa-user-md"></i>
                                                    </div>
                                                    <div>
                                                        <h5
                                                            class="dior-ic-b8390d3bcb">
                                                            Dr. James Chen, DO</h5>
                                                        <span
                                                            class="dior-ic-56fdd6b556">Primary
                                                            Care &amp; Urgent Care</span>
                                                    </div>
                                                </div>
                                                <div
                                                    class="dior-ic-181e1087c6">
                                                    <span><i class="far fa-clock"></i> Next Available:
                                                        Today</span>
                                                    <span class="dior-ic-a669b3acb9">Select &rarr;</span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- WIZARD STEP 2: SELECT DATE & TIME -->
                                    <div class="dior-in-booking-step dior-ic-44a70a0420" id="in-step-time">
                                        <div
                                            class="dior-ic-603a3ffb8a">
                                            <h4 class="dior-ic-04a54a7f2f">
                                                <i class="fas fa-calendar-day dior-ic-5d59a9a58e"></i> Select
                                                Consultation Date &amp; Time Slot:
                                            </h4>
                                            <button type="button" class="dior-btn-text-back dior-ic-f4f3cceddb"
                                                onclick="diorInBookingGoToStep('doctor')"
                                               >
                                                &larr; Switch Physician
                                            </button>
                                        </div>
                                        <div
                                            class="dior-schedule-pick-row dior-ic-40527f6e46">
                                            <div
                                                class="dior-ic-87c94397c4">
                                                <label
                                                    class="dior-ic-95f70d4d96">
                                                    <i class="far fa-calendar"></i> Select Date:
                                                </label>
                                                <input type="date" id="in-booking-date" class="dior-form-input"
                                                    min="<?php echo date('Y-m-d'); ?>"
                                                    value="<?php echo date('Y-m-d'); ?>"
                                                    onchange="diorInFetchAvailability()"
                                                    class="dior-ic-b39d99028a">
                                                <div id="in-selected-doctor-summary-pill"
                                                    class="dior-ic-627a4f4555">
                                                    <!-- Doctor Details -->
                                                </div>
                                            </div>
                                            <div>
                                                <label
                                                    class="dior-ic-95f70d4d96">
                                                    <i class="far fa-clock"></i> Available Video Consultation
                                                    Slots:
                                                </label>
                                                <div id="in-time-slots-container" class="dior-ic-4a3ecf7f98">
                                                    <p class="dior-ic-794116ec9b">Please select a physician to view live
                                                        availability slots.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- WIZARD STEP 3: CONFIRMATION -->
                                    <div class="dior-in-booking-step dior-ic-44a70a0420" id="in-step-confirm">
                                        <div
                                            class="dior-ic-603a3ffb8a">
                                            <h4 class="dior-ic-04a54a7f2f">
                                                <i class="fas fa-clipboard-check dior-ic-a727565283"></i>
                                                Review &amp; Confirm Consultation:
                                            </h4>
                                            <button type="button" class="dior-btn-text-back dior-ic-f4f3cceddb"
                                                onclick="diorInBookingGoToStep('time')"
                                               >
                                                &larr; Change Time
                                            </button>
                                        </div>

                                        <div id="in-booking-confirm-summary"
                                            class="dior-ic-4092883ec1">
                                            <!-- Summary generated dynamically -->
                                        </div>

                                        <div class="dior-ic-78c1dc59f7">
                                            <button type="button" class="dior-btn-gold-primary dior-ic-4a76c84446"
                                                id="btn-in-confirm-booking" onclick="diorInSubmitBooking()"
                                               >
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#22c55e" class="bi bi-camera-reels" viewBox="0 0 16 16" style="background:transparent;"><path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0M1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M9 6h.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 7.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 16H2a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm6 8.73V7.27l-3.5 1.555v4.35zM1 8v6a1 1 0 0 0 1 1h7.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1"/><path d="M9 6a3 3 0 1 0 0-6 3 3 0 0 0 0 6M7 3a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/></svg> Confirm &amp; Schedule Telehealth
                                                Visit
                                            </button>
                                            <span class="dior-ic-ed08d70728"><i class="fas fa-lock dior-ic-a727565283"
                                                   ></i> Instant Zoom room link &amp;
                                                notifications will be generated for you &amp; your doctor.</span>
                                        </div>

                                        <div id="in-booking-status-msg"
                                            class="dior-ic-ac3585e14b">
                                        </div>
                                    </div>

                                </div>
                            </div>

                </section>


                <!-- ============================================================== -->
                <!-- 6. NOTIFICATIONS TAB -->
                <!-- ============================================================== -->
