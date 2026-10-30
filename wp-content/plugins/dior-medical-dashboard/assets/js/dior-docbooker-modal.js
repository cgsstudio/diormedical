/**
 * Dior Medical - DocBooker Interactive Appointment Booking Modal Controller
 * Automatically detects HIPAAtizer form completions, manages service selection
 * (Video Visit / Phone Consultation), live slot availability, and dual-dashboard synchronization.
 */
(function() {
    'use strict';

    // State management
    window.diorDocBookerState = {
        doctorId: 1957,
        doctorName: 'Dr. Marcus Sterling, DO',
        serviceType: 'Video Visit (HD)',
        date: '',
        time: '09:00 AM - 10:00 AM',
        isSubmitting: false,
        cachedAvailability: {}
    };

    // Intake submission tracking flag for mandatory Step 1 enforcement
    window.diorBookingIntakeCompleted = false;
    window.diorLastBookedAppointment = null;

    /**
     * Stepper Navigation: Go to specified step in 3-Step Horizontal Timeline
     * Step 1: HIPAAtizer
     * Step 2: DocBooker
     * Step 3: Thank You Message
     */
    window.diorGoToBookingStep = function(step, apptData) {
        step = parseInt(step, 10) || 1;

        // Stepper Header Widgets
        var stepHeading = document.getElementById('dior-stepper-step-heading');
        var estTime = document.getElementById('dior-stepper-est-time');
        var circleSvg = document.getElementById('dior-stepper-circle-svg');
        var circleText = document.getElementById('dior-stepper-circle-text');

        // Step Connectors and Badges
        var line1 = document.getElementById('dior-step-line-1');
        var line2 = document.getElementById('dior-step-line-2');
        var tag1 = document.getElementById('dior-step-tag-1');
        var tag2 = document.getElementById('dior-step-tag-2');
        var tag3 = document.getElementById('dior-step-tag-3');
        var badge1 = document.getElementById('dior-step-badge-1');
        var badge2 = document.getElementById('dior-step-badge-2');
        var badge3 = document.getElementById('dior-step-badge-3');
        var title1 = document.getElementById('dior-step-title-1');
        var title2 = document.getElementById('dior-step-title-2');
        var title3 = document.getElementById('dior-step-title-3');

        var item1 = document.getElementById('dior-step-item-1');
        var item2 = document.getElementById('dior-step-item-2');
        var item3 = document.getElementById('dior-step-item-3');
        var icon1 = document.getElementById('dior-step-icon-1');
        var icon2 = document.getElementById('dior-step-icon-2');
        var icon3 = document.getElementById('dior-step-icon-3');

        var pane1 = document.getElementById('dior-step-pane-1');
        var pane2 = document.getElementById('dior-step-pane-2');
        var pane3 = document.getElementById('dior-step-pane-3');

        var stepText = document.getElementById('dior-stepper-step-text');

        if (step === 1) {
            if (stepHeading) stepHeading.textContent = 'STEP 1 OF 3';
            if (estTime) estTime.textContent = 'Est. 4 mins remaining';
            if (circleText) circleText.textContent = '1/3';
            if (circleSvg) {
                circleSvg.style.stroke = '#0D6EFD';
                circleSvg.setAttribute('stroke-dashoffset', '75');
            }
            if (stepText) stepText.textContent = 'Step 1 of 3';

            // Step 1 Active
            if (icon1) {
                icon1.className = 'rounded-circle d-flex align-items-center justify-content-center bg-primary text-white fw-bold shadow-sm flex-shrink-0';
                icon1.innerHTML = '1';
                icon1.style.backgroundColor = '';
                icon1.style.color = '';
            }
            if (line1) {
                line1.style.width = '50%';
                line1.className = 'h-100 position-absolute start-0 top-0 rounded-pill bg-primary';
                line1.style.backgroundColor = '';
            }
            if (tag1) tag1.className = 'small text-uppercase fw-bold text-primary';
            if (badge1) {
                badge1.className = 'badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5';
                badge1.textContent = 'Current';
                badge1.classList.remove('d-none');
            }
            if (title1) title1.className = 'fw-bold text-dark fs-6 mb-1';

            // Step 2 Inactive
            if (icon2) {
                icon2.className = 'rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0';
                icon2.style.backgroundColor = '#E2E8F0';
                icon2.style.color = '#64748B';
                icon2.innerHTML = '2';
            }
            if (line2) {
                line2.style.width = '0%';
                line2.className = 'h-100 position-absolute start-0 top-0 rounded-pill bg-primary';
                line2.style.backgroundColor = '';
            }
            if (tag2) tag2.className = 'small text-uppercase fw-semibold text-secondary';
            if (badge2) badge2.classList.add('d-none');
            if (title2) title2.className = 'fw-bold text-secondary fs-6 mb-1';

            // Step 3 Inactive
            if (icon3) {
                icon3.className = 'rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0';
                icon3.style.backgroundColor = '#E2E8F0';
                icon3.style.color = '#64748B';
                icon3.innerHTML = '3';
            }
            if (tag3) tag3.className = 'small text-uppercase fw-semibold text-secondary';
            if (badge3) badge3.classList.add('d-none');
            if (title3) title3.className = 'fw-bold text-secondary fs-6 mb-1';

            if (pane1) pane1.classList.remove('d-none');
            if (pane2) pane2.classList.add('d-none');
            if (pane3) pane3.classList.add('d-none');

        } else if (step === 2) {
            if (stepHeading) stepHeading.textContent = 'STEP 2 OF 3';
            if (estTime) estTime.textContent = 'Est. 2 mins remaining';
            if (circleText) circleText.textContent = '2/3';
            if (circleSvg) {
                circleSvg.style.stroke = '#0D6EFD';
                circleSvg.setAttribute('stroke-dashoffset', '38');
            }
            if (stepText) stepText.textContent = 'Step 2 of 3';

            // Step 1 Completed
            if (icon1) {
                icon1.className = 'rounded-circle d-flex align-items-center justify-content-center bg-success text-white fw-bold shadow-sm flex-shrink-0';
                icon1.style.backgroundColor = '';
                icon1.style.color = '';
                icon1.innerHTML = '<i class="fas fa-check small"></i>';
            }
            if (line1) {
                line1.style.width = '100%';
                line1.className = 'h-100 position-absolute start-0 top-0 rounded-pill bg-success';
                line1.style.backgroundColor = '';
            }
            if (tag1) tag1.className = 'small text-uppercase fw-semibold text-success';
            if (badge1) {
                badge1.className = 'badge bg-success-subtle text-success rounded-pill px-2 py-0.5';
                badge1.textContent = 'Done';
            }
            if (title1) title1.className = 'fw-bold text-dark fs-6 mb-1';

            // Step 2 Active
            if (icon2) {
                icon2.className = 'rounded-circle d-flex align-items-center justify-content-center bg-primary text-white fw-bold shadow-sm flex-shrink-0';
                icon2.style.backgroundColor = '';
                icon2.style.color = '';
                icon2.innerHTML = '2';
            }
            if (line2) {
                line2.style.width = '50%';
                line2.className = 'h-100 position-absolute start-0 top-0 rounded-pill bg-primary';
                line2.style.backgroundColor = '';
            }
            if (tag2) tag2.className = 'small text-uppercase fw-bold text-primary';
            if (badge2) {
                badge2.className = 'badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5';
                badge2.textContent = 'Current';
            }
            if (title2) title2.className = 'fw-bold text-dark fs-6 mb-1';

            // Step 3 Inactive
            if (icon3) {
                icon3.className = 'rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0';
                icon3.style.backgroundColor = '#E2E8F0';
                icon3.style.color = '#64748B';
                icon3.innerHTML = '3';
            }
            if (tag3) tag3.className = 'small text-uppercase fw-semibold text-secondary';
            if (badge3) badge3.classList.add('d-none');
            if (title3) title3.className = 'fw-bold text-secondary fs-6 mb-1';

            if (pane1) pane1.classList.add('d-none');
            if (pane2) pane2.classList.remove('d-none');
            if (pane3) pane3.classList.add('d-none');

            // Trigger slot check if date is set
            var step2Date = document.getElementById('dior-step2-date-input');
            if (step2Date && step2Date.value) {
                window.diorFetchDocBookerAvailability(step2Date.value);
            }

        } else if (step === 3) {
            if (stepHeading) stepHeading.textContent = 'STEP 3 OF 3';
            if (estTime) estTime.textContent = 'Completed';
            if (circleText) circleText.textContent = '3/3';
            if (circleSvg) {
                circleSvg.style.stroke = '#198754';
                circleSvg.setAttribute('stroke-dashoffset', '0');
            }
            if (stepText) stepText.textContent = 'Step 3 of 3';

            // Step 1 Completed
            if (icon1) {
                icon1.className = 'rounded-circle d-flex align-items-center justify-content-center bg-success text-white fw-bold shadow-sm flex-shrink-0';
                icon1.style.backgroundColor = '';
                icon1.style.color = '';
                icon1.innerHTML = '<i class="fas fa-check small"></i>';
            }
            if (line1) {
                line1.style.width = '100%';
                line1.className = 'h-100 position-absolute start-0 top-0 rounded-pill bg-success';
                line1.style.backgroundColor = '';
            }
            if (tag1) tag1.className = 'small text-uppercase fw-semibold text-success';
            if (badge1) {
                badge1.className = 'badge bg-success-subtle text-success rounded-pill px-2 py-0.5';
                badge1.textContent = 'Done';
            }
            if (title1) title1.className = 'fw-bold text-dark fs-6 mb-1';

            // Step 2 Completed
            if (icon2) {
                icon2.className = 'rounded-circle d-flex align-items-center justify-content-center bg-success text-white fw-bold shadow-sm flex-shrink-0';
                icon2.style.backgroundColor = '';
                icon2.style.color = '';
                icon2.innerHTML = '<i class="fas fa-check small"></i>';
            }
            if (line2) {
                line2.style.width = '100%';
                line2.className = 'h-100 position-absolute start-0 top-0 rounded-pill bg-success';
                line2.style.backgroundColor = '';
            }
            if (tag2) tag2.className = 'small text-uppercase fw-semibold text-success';
            if (badge2) {
                badge2.className = 'badge bg-success-subtle text-success rounded-pill px-2 py-0.5';
                badge2.textContent = 'Done';
            }
            if (title2) title2.className = 'fw-bold text-dark fs-6 mb-1';

            // Step 3 Confirmed
            if (icon3) {
                icon3.className = 'rounded-circle d-flex align-items-center justify-content-center bg-success text-white fw-bold shadow-sm flex-shrink-0';
                icon3.style.backgroundColor = '';
                icon3.style.color = '';
                icon3.innerHTML = '<i class="fas fa-check small"></i>';
            }
            if (tag3) tag3.className = 'small text-uppercase fw-bold text-success';
            if (badge3) {
                badge3.className = 'badge bg-success-subtle text-success rounded-pill px-2 py-0.5';
                badge3.textContent = 'Confirmed';
            }
            if (title3) title3.className = 'fw-bold text-dark fs-6 mb-1';

            if (pane1) pane1.classList.add('d-none');
            if (pane2) pane2.classList.add('d-none');
            if (pane3) pane3.classList.remove('d-none');

            // Populate Thank You details
            if (apptData) {
                window.diorLastBookedAppointment = apptData;
                var tyDoc = document.getElementById('dior-ty-doctor');
                var tyType = document.getElementById('dior-ty-type');
                var tyDt = document.getElementById('dior-ty-datetime');
                var tyUid = document.getElementById('dior-ty-uid');

                if (tyDoc && apptData.doctor) tyDoc.textContent = apptData.doctor;
                if (tyType && apptData.type) tyType.textContent = apptData.type;
                if (tyDt) {
                    var dtStr = (apptData.date || '') + (apptData.time ? ' at ' + apptData.time : '');
                    if (dtStr.trim()) tyDt.textContent = dtStr;
                }
                if (tyUid && (apptData.uid || apptData.appt_uid || apptData.id)) {
                    tyUid.textContent = apptData.uid || apptData.appt_uid || apptData.id;
                }
            }
        }

        // Scroll smoothly to stepper card
        var stepperCard = document.getElementById('dior-booking-stepper-card');
        if (stepperCard) {
            stepperCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    };

    /**
     * Stepper Header Click Navigation with Mandatory Validation
     */
    window.diorNavigateBookingStep = function(targetStep) {
        targetStep = parseInt(targetStep, 10) || 1;

        if (targetStep === 1) {
            window.diorGoToBookingStep(1);
            return;
        }

        if (targetStep === 2) {
            // Strictly mandatory: Must complete Step 1 clinical intake questionnaire
            if (!window.diorBookingIntakeCompleted) {
                var alertEl = document.getElementById('dior-intake-mandatory-alert');
                if (alertEl) {
                    alertEl.classList.remove('d-none');
                    try { alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch(e) {}
                    setTimeout(function() {
                        if (alertEl) alertEl.classList.add('d-none');
                    }, 5000);
                }
                window.diorGoToBookingStep(1);
                return;
            }
            window.diorGoToBookingStep(2);
            return;
        }

        if (targetStep === 3) {
            if (!window.diorLastBookedAppointment) {
                // Cannot jump to Step 3 until appointment is booked
                if (!window.diorBookingIntakeCompleted) {
                    window.diorNavigateBookingStep(2);
                }
                return;
            }
            window.diorGoToBookingStep(3, window.diorLastBookedAppointment);
            return;
        }
    };

    /**
     * Reset Booking Flow for subsequent appointments
     * Enforces that every new appointment requires a new HIPAAtizer intake
     */
    window.diorResetBookingFlow = function() {
        window.diorBookingIntakeCompleted = false;
        window.diorLastBookedAppointment = null;

        // Reset Step 2 form
        var form = document.getElementById('dior-step2-docbooker-form');
        if (form) form.reset();

        // Refresh HIPAAtizer iframe so user starts with a fresh form
        var iframe = document.getElementById('dior-hipaatizer-tab-iframe');
        if (iframe && iframe.src) {
            iframe.src = iframe.src;
        }

        window.diorGoToBookingStep(1);
    };

    /**
     * Inline Step 2 Service Format Selection (Video vs Phone)
     */
    window.diorSelectInlineFormat = function(serviceType, el) {
        window.diorDocBookerState.serviceType = serviceType;

        var input = document.getElementById('dior-step2-service-type');
        var inputFinal = document.getElementById('dior-step2-final-type');
        if (input) input.value = serviceType;
        if (inputFinal) inputFinal.value = serviceType;

        var videoCard = document.getElementById('dior-fmt-video');
        var phoneCard = document.getElementById('dior-fmt-phone');

        if (serviceType.indexOf('Video') !== -1) {
            if (videoCard) {
                videoCard.className = 'p-3 rounded-4 border border-2 border-primary bg-white shadow-sm d-flex align-items-center justify-content-between cursor-pointer dior-service-card-item active';
                var vIcon = videoCard.querySelector('.dior-fmt-check');
                if (vIcon) vIcon.className = 'fas fa-circle-check text-primary fs-5 dior-fmt-check';
            }
            if (phoneCard) {
                phoneCard.className = 'p-3 rounded-4 border bg-white d-flex align-items-center justify-content-between cursor-pointer dior-service-card-item opacity-75';
                var pIcon = phoneCard.querySelector('.dior-fmt-check');
                if (pIcon) pIcon.className = 'far fa-circle text-secondary fs-5 dior-fmt-check';
            }
        } else {
            if (phoneCard) {
                phoneCard.className = 'p-3 rounded-4 border border-2 border-primary bg-white shadow-sm d-flex align-items-center justify-content-between cursor-pointer dior-service-card-item active';
                var pIcon = phoneCard.querySelector('.dior-fmt-check');
                if (pIcon) pIcon.className = 'fas fa-circle-check text-primary fs-5 dior-fmt-check';
            }
            if (videoCard) {
                videoCard.className = 'p-3 rounded-4 border bg-white d-flex align-items-center justify-content-between cursor-pointer dior-service-card-item opacity-75';
                var vIcon = videoCard.querySelector('.dior-fmt-check');
                if (vIcon) vIcon.className = 'far fa-circle text-secondary fs-5 dior-fmt-check';
            }
        }
    };

    /**
     * Inline Step 2 Slot Selection
     */
    window.diorSelectStep2Slot = function(time, el) {
        if (el && el.disabled) return;
        window.diorDocBookerState.time = time;

        var timeInput = document.getElementById('dior-step2-selected-time');
        var timeFinal = document.getElementById('dior-step2-final-time');
        if (timeInput) timeInput.value = time;
        if (timeFinal) timeFinal.value = time;

        var buttons = document.querySelectorAll('.dior-step2-slot-btn');
        buttons.forEach(function(b) {
            b.classList.remove('btn-primary', 'active');
            b.classList.add('btn-outline-primary');
        });
        if (el) {
            el.classList.remove('btn-outline-primary');
            el.classList.add('btn-primary', 'active');
        }
    };

    /**
     * Inline Step 2 Date Changed Callback
     */
    window.diorOnStep2DateChanged = function(newDate) {
        window.diorDocBookerState.date = newDate;
        window.diorFetchDocBookerAvailability(newDate);
    };

    /**
     * Submit Step 2 Inline DocBooker Booking Form
     */
    window.diorSubmitInlineBooking = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        if (window.diorDocBookerState.isSubmitting) return false;

        // Strict validation: Step 1 intake mandatory (no message display)
        if (!window.diorBookingIntakeCompleted) {
            window.diorGoToBookingStep(1);
            return false;
        }

        var form = document.getElementById('dior-step2-docbooker-form');
        var submitBtn = document.getElementById('dior-step2-submit-btn');
        var statusBox = document.getElementById('dior-step2-status-box');

        var nameInput = document.getElementById('dior-step2-patient-name');
        var emailInput = document.getElementById('dior-step2-patient-email');
        var phoneInput = document.getElementById('dior-step2-patient-phone');
        var dateInput = document.getElementById('dior-step2-date-input');

        if (!nameInput.value.trim() || !emailInput.value.trim() || !phoneInput.value.trim()) {
            if (statusBox) {
                statusBox.className = 'alert alert-danger border-0 rounded-4 p-3 mb-3 d-flex align-items-center gap-2';
                statusBox.innerHTML = '<i class="fas fa-triangle-exclamation"></i> Please provide your full name, email address, and contact phone number.';
                statusBox.classList.remove('d-none');
            }
            return false;
        }

        var inlineSelectedTime = window.diorDocBookerState.time || (document.getElementById('dior-step2-final-time') ? document.getElementById('dior-step2-final-time').value : '');
        if (!window.diorIsSlotBookable15Min(dateInput.value, inlineSelectedTime, 15)) {
            if (statusBox) {
                statusBox.className = 'alert alert-danger border-0 rounded-4 p-3 mb-3 d-flex align-items-center gap-2';
                statusBox.innerHTML = '<i class="fas fa-triangle-exclamation"></i> This appointment time slot is no longer available (must be booked at least 15 minutes in advance). Please choose another time slot.';
                statusBox.classList.remove('d-none');
            }
            if (typeof window.diorRefreshSlotsLive === 'function') {
                window.diorRefreshSlotsLive();
            }
            return false;
        }

        window.diorDocBookerState.isSubmitting = true;
        if (statusBox) statusBox.classList.add('d-none');

        if (submitBtn) {
            submitBtn.disabled = true;
            var btnText = submitBtn.querySelector('.dior-step2-btn-text');
            var btnSpinner = submitBtn.querySelector('.dior-step2-btn-spinner');
            if (btnText) btnText.classList.add('d-none');
            if (btnSpinner) btnSpinner.classList.remove('d-none');
        }

        var formData = new FormData(form);
        formData.set('type', window.diorDocBookerState.serviceType);
        formData.set('visit_type', window.diorDocBookerState.serviceType);
        formData.set('time', window.diorDocBookerState.time);
        formData.set('appt_time', window.diorDocBookerState.time);
        formData.set('date', dateInput.value);
        formData.set('appt_date', dateInput.value);

        var ajaxUrl = (typeof dior_vars !== 'undefined' && dior_vars.ajax_url) || '/wp-admin/admin-ajax.php';

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            window.diorDocBookerState.isSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                var btnText = submitBtn.querySelector('.dior-step2-btn-text');
                var btnSpinner = submitBtn.querySelector('.dior-step2-btn-spinner');
                if (btnText) btnText.classList.remove('d-none');
                if (btnSpinner) btnSpinner.classList.add('d-none');
            }

            if (res.success) {
                var apptData = (res.data && res.data.appointment) || {};
                var apptUid = apptData.appt_uid || apptData.id || ('APT-' + Math.floor(1000 + Math.random() * 9000));

                var record = {
                    uid: apptUid,
                    doctor: window.diorDocBookerState.doctorName,
                    spec: 'General Practice & Internal Medicine',
                    date: dateInput.value,
                    time: window.diorDocBookerState.time,
                    type: window.diorDocBookerState.serviceType,
                    patientName: nameInput.value,
                    phone: phoneInput.value
                };

                // Advance Timeline to Step 3: Thank You Message!
                window.diorGoToBookingStep(3, record);

                // Inject into Today/Upcoming table
                window.diorInjectAppointmentIntoDashboards(record);

            } else {
                if (statusBox) {
                    statusBox.className = 'alert alert-danger border-0 rounded-4 p-3 mb-3 d-flex align-items-center gap-2';
                    statusBox.innerHTML = '<i class="fas fa-triangle-exclamation"></i> ' + ((res.data && res.data.message) || 'Booking failed. Please try again.');
                    statusBox.classList.remove('d-none');
                }
            }
        })
        .catch(function(err) {
            window.diorDocBookerState.isSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                var btnText = submitBtn.querySelector('.dior-step2-btn-text');
                var btnSpinner = submitBtn.querySelector('.dior-step2-btn-spinner');
                if (btnText) btnText.classList.remove('d-none');
                if (btnSpinner) btnSpinner.classList.add('d-none');
            }
            if (statusBox) {
                statusBox.className = 'alert alert-danger border-0 rounded-4 p-3 mb-3 d-flex align-items-center gap-2';
                statusBox.innerHTML = '<i class="fas fa-triangle-exclamation"></i> Connection issue while booking appointment. Please try again.';
                statusBox.classList.remove('d-none');
            }
            console.error('[Dior Medical] Booking error:', err);
        });

        return false;
    };

    /**
     * Open the DocBooker Booking Modal
     * Strictly verifies mandatory Step 1 HIPAAtizer intake before opening
     */
    window.diorOpenDocBookerModal = function(options) {
        options = options || {};

        // Mandatory Step 1 check (no message display)
        if (!window.diorBookingIntakeCompleted) {
            console.log('[Dior Medical] Step 1 Intake required before booking appointment.');
            if (typeof diorSwitchTab === 'function') diorSwitchTab('appointments');
            if (typeof window.diorSwitchApptSubTab === 'function') window.diorSwitchApptSubTab('book');
            window.diorGoToBookingStep(1);
            return false;
        }

        var modal = document.getElementById('dior-docbooker-modal');
        if (!modal) {
            // If modal DOM isn't present, switch to inline Step 2
            if (typeof diorSwitchTab === 'function') diorSwitchTab('appointments');
            if (typeof window.diorSwitchApptSubTab === 'function') window.diorSwitchApptSubTab('book');
            window.diorGoToBookingStep(2);
            return;
        }

        // Reset views
        var body = document.getElementById('dior-db-modal-body');
        var success = document.getElementById('dior-db-modal-success');
        var statusBox = document.getElementById('dior-db-status-box');
        if (body) body.style.display = 'block';
        if (success) success.style.display = 'none';
        if (statusBox) statusBox.style.display = 'none';

        // Pre-fill / update date
        var dateInput = document.getElementById('dior-db-date-input');
        var today = new Date().toISOString().split('T')[0];
        if (dateInput) {
            dateInput.min = today;
            if (!dateInput.value) {
                dateInput.value = today;
            }
            window.diorDocBookerState.date = dateInput.value;
        }

        // Pre-fill reason if passed or from options
        if (options.reason) {
            var notesInput = document.getElementById('dior-db-patient-notes');
            if (notesInput && !notesInput.value) {
                notesInput.value = options.reason;
            }
        }

        // Pre-fill patient details from global vars if available
        if (typeof dior_vars !== 'undefined' && dior_vars.patient_profile) {
            var p = dior_vars.patient_profile;
            var nameInput = document.getElementById('dior-db-patient-name');
            var emailInput = document.getElementById('dior-db-patient-email');
            var phoneInput = document.getElementById('dior-db-patient-phone');
            if (nameInput && !nameInput.value && p.full_name) nameInput.value = p.full_name;
            if (emailInput && !emailInput.value && p.email) emailInput.value = p.email;
            if (phoneInput && !phoneInput.value && p.phone) phoneInput.value = p.phone;
        }

        // Fetch live availability for current date
        if (window.diorDocBookerState.date) {
            window.diorFetchDocBookerAvailability(window.diorDocBookerState.date);
        }

        modal.hidden = false;
        modal.style.display = 'flex';
        void modal.offsetHeight;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    /**
     * Close the DocBooker Booking Modal
     */
    window.diorCloseDocBookerModal = function() {
        var modal = document.getElementById('dior-docbooker-modal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    /**
     * Service selection: Video Visit vs Phone Consultation
     */
    window.diorSelectServiceType = function(serviceType, el) {
        window.diorDocBookerState.serviceType = serviceType;

        var input = document.getElementById('dior-db-service-type');
        var inputFinal = document.getElementById('dior-db-final-type');
        if (input) input.value = serviceType;
        if (inputFinal) inputFinal.value = serviceType;

        // Toggle visual active state
        var cards = document.querySelectorAll('.dior-db-service-card');
        cards.forEach(function(c) { c.classList.remove('active'); });
        if (el) el.classList.add('active');
    };

    /**
     * Time slot selection
     */
    window.diorSelectTimeSlot = function(time, el) {
        if (el && el.disabled) return;
        window.diorDocBookerState.time = time;

        var timeInput = document.getElementById('dior-db-selected-time');
        var timeFinal = document.getElementById('dior-db-final-time');
        if (timeInput) timeInput.value = time;
        if (timeFinal) timeFinal.value = time;

        var buttons = document.querySelectorAll('.dior-db-slot-btn');
        buttons.forEach(function(b) { b.classList.remove('active'); });
        if (el) el.classList.add('active');
    };

    /**
     * Date changed callback
     */
    window.diorOnDateChanged = function(newDate) {
        window.diorDocBookerState.date = newDate;
        window.diorFetchDocBookerAvailability(newDate);
    };

    /**
     * Parse slot start time from string (e.g. "09:00 AM - 10:00 AM", "02:00 PM", "14:00")
     */
    window.diorParseSlotStartTime = function(timeStr) {
        if (!timeStr) return null;
        var parts = String(timeStr).split(/[-–to]/i);
        var startStr = (parts[0] || '').trim();
        var m = startStr.match(/(\d{1,2}):(\d{2})(?:\s*([AP]M))?/i);
        if (!m) return null;
        var hours = parseInt(m[1], 10);
        var minutes = parseInt(m[2], 10);
        var ampm = m[3] ? m[3].toUpperCase() : null;
        if (ampm === 'PM' && hours < 12) hours += 12;
        if (ampm === 'AM' && hours === 12) hours = 0;
        return { hours: hours, minutes: minutes };
    };

    /**
     * Determine if a slot is bookable with minimum advance notice (15 minutes default)
     * - Past dates: blocked (false)
     * - Future dates: available (true)
     * - Today: slot start time must be >= minAdvanceMins ahead of current clock
     */
    window.diorIsSlotBookable15Min = function(dateStr, timeStr, minAdvanceMins) {
        if (minAdvanceMins === undefined) minAdvanceMins = 15;
        if (!dateStr || !timeStr) return false;

        var dateParts = String(dateStr).trim().split('-');
        if (dateParts.length !== 3) return true;
        var sYear = parseInt(dateParts[0], 10);
        var sMonth = parseInt(dateParts[1], 10) - 1;
        var sDay = parseInt(dateParts[2], 10);

        var now = new Date();
        var tYear = now.getFullYear();
        var tMonth = now.getMonth();
        var tDay = now.getDate();

        var slotDateOnly = new Date(sYear, sMonth, sDay);
        var todayDateOnly = new Date(tYear, tMonth, tDay);

        if (slotDateOnly < todayDateOnly) {
            return false; // past date
        }
        if (slotDateOnly > todayDateOnly) {
            return true; // future date is always bookable (> 15 mins)
        }

        // Today: parse start time of the slot
        var parsed = window.diorParseSlotStartTime(timeStr);
        if (!parsed) return false;

        var slotDateTime = new Date(sYear, sMonth, sDay, parsed.hours, parsed.minutes, 0, 0);
        var diffMs = slotDateTime.getTime() - now.getTime();
        var minDiffMs = minAdvanceMins * 60 * 1000;

        return diffMs >= minDiffMs;
    };

    /**
     * Query DocBooker live availability for given date
     */
    window.diorFetchDocBookerAvailability = function(dateStr) {
        if (!dateStr) return;
        var spinner = document.getElementById('dior-db-slots-loading');
        var spinnerStep2 = document.getElementById('dior-step2-slots-spinner');
        if (spinner) spinner.style.display = 'inline-block';
        if (spinnerStep2) spinnerStep2.classList.remove('d-none');

        var restUrl = (typeof dior_vars !== 'undefined' && dior_vars.rest_url) || '/wp-json/';
        var restNonce = (typeof dior_vars !== 'undefined' && dior_vars.rest_nonce) || '';
        var apiUrl = restUrl + 'doctor-details-booking/v1/doctors/' + window.diorDocBookerState.doctorId + '/availability?date=' + encodeURIComponent(dateStr);

        fetch(apiUrl, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': restNonce
            }
        })
        .then(function(res) {
            if (!res.ok) throw new Error('API returned ' + res.status);
            return res.json();
        })
        .then(function(data) {
            if (spinner) spinner.style.display = 'none';
            if (spinnerStep2) spinnerStep2.classList.add('d-none');
            if (data && data.clinics && data.clinics.length > 0 && data.clinics[0].timings) {
                var timings = data.clinics[0].timings;
                window.diorRenderAvailableSlots(timings, dateStr);
            }
        })
        .catch(function(err) {
            if (spinner) spinner.style.display = 'none';
            if (spinnerStep2) spinnerStep2.classList.add('d-none');
            console.log('[Dior Medical] Using default DocBooker slots for ' + dateStr, err);
            // Default slots
            var defaultSlots = [
                { time: '09:00 AM - 10:00 AM', is_available: true },
                { time: '10:00 AM - 11:00 AM', is_available: true },
                { time: '11:00 AM - 12:00 PM', is_available: true },
                { time: '01:00 PM - 02:00 PM', is_available: true },
                { time: '02:00 PM - 03:00 PM', is_available: true },
                { time: '03:00 PM - 04:00 PM', is_available: true },
                { time: '04:00 PM - 05:00 PM', is_available: true }
            ];
            window.diorRenderAvailableSlots(defaultSlots, dateStr);
        });
    };

    /**
     * Render slot buttons inside both modal grid (#dior-db-slots-grid)
     * and inline step 2 grid (#dior-step2-slots-grid) with 15-minute advance filter
     */
    window.diorRenderAvailableSlots = function(timings, targetDate) {
        if (!targetDate) {
            var dbDateInput = document.getElementById('dior-db-date-input');
            var s2DateInput = document.getElementById('dior-step2-date-input');
            targetDate = (dbDateInput && dbDateInput.value) || (s2DateInput && s2DateInput.value) || window.diorDocBookerState.date;
        }

        // Filter out slots that have expired or have < 15 minutes remaining for today
        var bookableSlots = (timings || []).filter(function(item) {
            var isTimeBookable = window.diorIsSlotBookable15Min(targetDate, item.time, 15);
            return isTimeBookable && item.is_available !== false;
        });

        var firstAvailable = bookableSlots.length > 0 ? bookableSlots[0].time : null;

        // 1. Render in Modal Grid (#dior-db-slots-grid)
        var dbGrid = document.getElementById('dior-db-slots-grid');
        if (dbGrid) {
            dbGrid.innerHTML = '';
            if (bookableSlots.length === 0) {
                dbGrid.innerHTML = '<div class="w-100 text-center py-3 px-2 text-muted small"><i class="fa-solid fa-clock me-1 text-warning"></i> No further consultation slots are available for today. Please select a future date to book an appointment.</div>';
            } else {
                bookableSlots.forEach(function(item, idx) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'dior-db-slot-btn btn' + (idx === 0 ? ' active' : '');
                    btn.setAttribute('data-time', item.time);
                    btn.textContent = item.time;
                    btn.onclick = function() {
                        window.diorSelectTimeSlot(item.time, btn);
                    };
                    dbGrid.appendChild(btn);
                });
            }

            var timeInput = document.getElementById('dior-db-selected-time');
            var timeFinal = document.getElementById('dior-db-final-time');
            if (timeInput) timeInput.value = firstAvailable || '';
            if (timeFinal) timeFinal.value = firstAvailable || '';
        }

        // 2. Render in Inline Step 2 Grid (#dior-step2-slots-grid)
        var s2Grid = document.getElementById('dior-step2-slots-grid');
        if (s2Grid) {
            s2Grid.innerHTML = '';
            if (bookableSlots.length === 0) {
                s2Grid.innerHTML = '<div class="w-100 text-center py-3 px-2 text-muted small"><i class="fas fa-clock me-1 text-warning"></i> No further consultation slots are available for today. Please select a future date to book an appointment.</div>';
            } else {
                bookableSlots.forEach(function(item, idx) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn ' + (idx === 0 ? 'btn-primary active' : 'btn-outline-primary') + ' rounded-3 btn-sm py-2 px-3 dior-step2-slot-btn';
                    btn.setAttribute('data-time', item.time);
                    btn.textContent = item.time;
                    btn.onclick = function() {
                        window.diorSelectStep2Slot(item.time, btn);
                    };
                    s2Grid.appendChild(btn);
                });
            }

            var s2TimeInput = document.getElementById('dior-step2-selected-time');
            var s2TimeFinal = document.getElementById('dior-step2-final-time');
            if (s2TimeInput) s2TimeInput.value = firstAvailable || '';
            if (s2TimeFinal) s2TimeFinal.value = firstAvailable || '';
        }

        if (firstAvailable) {
            window.diorDocBookerState.time = firstAvailable;
        }
    };

    /**
     * Real-time slot updater: dynamically removes/hides slots as current time progresses
     */
    window.diorRefreshSlotsLive = function() {
        var todayStr = (function() {
            var n = new Date();
            var y = n.getFullYear();
            var m = String(n.getMonth() + 1).padStart(2, '0');
            var d = String(n.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        })();

        // Refresh Modal Grid if selected date is today
        var dbDateInput = document.getElementById('dior-db-date-input');
        var dbDate = (dbDateInput && dbDateInput.value) || todayStr;
        if (dbDate === todayStr) {
            var dbGrid = document.getElementById('dior-db-slots-grid');
            if (dbGrid) {
                var dbButtons = dbGrid.querySelectorAll('.dior-db-slot-btn');
                var dbRemovedActive = false;
                dbButtons.forEach(function(b) {
                    var t = b.getAttribute('data-time');
                    if (!window.diorIsSlotBookable15Min(dbDate, t, 15)) {
                        if (b.classList.contains('active')) dbRemovedActive = true;
                        b.remove();
                    }
                });
                var remainingDb = dbGrid.querySelectorAll('.dior-db-slot-btn');
                if (remainingDb.length === 0) {
                    if (!dbGrid.querySelector('.text-muted')) {
                        dbGrid.innerHTML = '<div class="w-100 text-center py-3 px-2 text-muted small"><i class="fa-solid fa-clock me-1 text-warning"></i> No further consultation slots are available for today. Please select a future date to book an appointment.</div>';
                    }
                } else if (dbRemovedActive && remainingDb[0]) {
                    window.diorSelectTimeSlot(remainingDb[0].getAttribute('data-time'), remainingDb[0]);
                }
            }
        }

        // Refresh Step 2 Grid if selected date is today
        var s2DateInput = document.getElementById('dior-step2-date-input');
        var s2Date = (s2DateInput && s2DateInput.value) || todayStr;
        if (s2Date === todayStr) {
            var s2Grid = document.getElementById('dior-step2-slots-grid');
            if (s2Grid) {
                var s2Buttons = s2Grid.querySelectorAll('.dior-step2-slot-btn');
                var s2RemovedActive = false;
                s2Buttons.forEach(function(b) {
                    var t = b.getAttribute('data-time');
                    if (!window.diorIsSlotBookable15Min(s2Date, t, 15)) {
                        if (b.classList.contains('active')) s2RemovedActive = true;
                        b.remove();
                    }
                });
                var remainingS2 = s2Grid.querySelectorAll('.dior-step2-slot-btn');
                if (remainingS2.length === 0) {
                    if (!s2Grid.querySelector('.text-muted')) {
                        s2Grid.innerHTML = '<div class="w-100 text-center py-3 px-2 text-muted small"><i class="fas fa-clock me-1 text-warning"></i> No further consultation slots are available for today. Please select a future date to book an appointment.</div>';
                    }
                } else if (s2RemovedActive && remainingS2[0]) {
                    window.diorSelectStep2Slot(remainingS2[0].getAttribute('data-time'), remainingS2[0]);
                }
            }
        }
    };

    // Live update polling (every 20 seconds) and tab focus
    if (!window.diorSlotsTimerInitialized) {
        window.diorSlotsTimerInitialized = true;
        setInterval(function() {
            if (typeof window.diorRefreshSlotsLive === 'function') {
                window.diorRefreshSlotsLive();
            }
        }, 20000);
        window.addEventListener('focus', function() {
            if (typeof window.diorRefreshSlotsLive === 'function') {
                window.diorRefreshSlotsLive();
            }
        });
    }

    /**
     * Submit Booking Form
     */
    window.diorSubmitDocBookerBooking = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        if (window.diorDocBookerState.isSubmitting) return false;

        var form = document.getElementById('dior-docbooker-form');
        var submitBtn = document.getElementById('dior-db-submit-btn');
        var statusBox = document.getElementById('dior-db-status-box');

        // Validation
        var nameInput = document.getElementById('dior-db-patient-name');
        var emailInput = document.getElementById('dior-db-patient-email');
        var phoneInput = document.getElementById('dior-db-patient-phone');
        var dateInput = document.getElementById('dior-db-date-input');

        if (!nameInput.value.trim() || !emailInput.value.trim() || !phoneInput.value.trim()) {
            if (statusBox) {
                statusBox.className = 'dior-db-alert alert-error';
                statusBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Please provide your full name, email address, and contact phone number.';
                statusBox.style.display = 'flex';
            }
            return false;
        }

        var modalSelectedTime = window.diorDocBookerState.time || (document.getElementById('dior-db-final-time') ? document.getElementById('dior-db-final-time').value : '');
        if (!window.diorIsSlotBookable15Min(dateInput.value, modalSelectedTime, 15)) {
            if (statusBox) {
                statusBox.className = 'dior-db-alert alert-error';
                statusBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> This appointment time slot is no longer available (must be booked at least 15 minutes in advance). Please choose another time slot.';
                statusBox.style.display = 'flex';
            }
            if (typeof window.diorRefreshSlotsLive === 'function') {
                window.diorRefreshSlotsLive();
            }
            return false;
        }

        window.diorDocBookerState.isSubmitting = true;
        if (statusBox) statusBox.style.display = 'none';

        if (submitBtn) {
            submitBtn.disabled = true;
            var btnText = submitBtn.querySelector('.dior-db-btn-text');
            var btnSpinner = submitBtn.querySelector('.dior-db-btn-spinner');
            if (btnText) btnText.style.display = 'none';
            if (btnSpinner) btnSpinner.style.display = 'inline-block';
        }

        var formData = new FormData(form);
        // Ensure values match current state
        formData.set('type', window.diorDocBookerState.serviceType);
        formData.set('visit_type', window.diorDocBookerState.serviceType);
        formData.set('time', window.diorDocBookerState.time);
        formData.set('appt_time', window.diorDocBookerState.time);
        formData.set('date', dateInput.value);
        formData.set('appt_date', dateInput.value);
        formData.set('doctor_id', window.diorDocBookerState.doctorId);
        formData.set('doctorId', window.diorDocBookerState.doctorId);
        formData.set('provider', window.diorDocBookerState.doctorName);
        formData.set('doctor_name', window.diorDocBookerState.doctorName);

        var ajaxUrl = (typeof dior_vars !== 'undefined' && dior_vars.ajax_url) || '/wp-admin/admin-ajax.php';

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            window.diorDocBookerState.isSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                var btnText = submitBtn.querySelector('.dior-db-btn-text');
                var btnSpinner = submitBtn.querySelector('.dior-db-btn-spinner');
                if (btnText) btnText.style.display = 'inline-block';
                if (btnSpinner) btnSpinner.style.display = 'none';
            }

            if (res.success) {
                // Populate Success screen
                var body = document.getElementById('dior-db-modal-body');
                var success = document.getElementById('dior-db-modal-success');
                if (body) body.style.display = 'none';
                if (success) success.style.display = 'block';

                var apptData = (res.data && res.data.appointment) || {};
                var apptUid = apptData.appt_uid || apptData.id || ('APT-' + Math.floor(1000 + Math.random() * 9000));

                var successUid = document.getElementById('dior-success-uid');
                var successType = document.getElementById('dior-success-service-type');
                var successDt = document.getElementById('dior-success-datetime');
                var successDoc = document.getElementById('dior-success-doc-name');

                if (successUid) successUid.textContent = apptUid;
                if (successType) successType.textContent = window.diorDocBookerState.serviceType;
                if (successDt) successDt.textContent = dateInput.value + ' at ' + window.diorDocBookerState.time;
                if (successDoc) successDoc.textContent = window.diorDocBookerState.doctorName;

                var record = {
                    uid: apptUid,
                    doctor: window.diorDocBookerState.doctorName,
                    spec: 'General Practice & Internal Medicine',
                    date: dateInput.value,
                    time: window.diorDocBookerState.time,
                    type: window.diorDocBookerState.serviceType,
                    patientName: nameInput.value,
                    phone: phoneInput.value
                };

                // Advance Timeline to Step 3: Thank You Message!
                window.diorGoToBookingStep(3, record);

                // Dynamically update dashboard elements if present on page
                window.diorInjectAppointmentIntoDashboards(record);

            } else {
                if (statusBox) {
                    statusBox.className = 'dior-db-alert alert-error';
                    statusBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + ((res.data && res.data.message) || 'Booking failed. Please try again.');
                    statusBox.style.display = 'flex';
                }
            }
        })
        .catch(function(err) {
            window.diorDocBookerState.isSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                var btnText = submitBtn.querySelector('.dior-db-btn-text');
                var btnSpinner = submitBtn.querySelector('.dior-db-btn-spinner');
                if (btnText) btnText.style.display = 'inline-block';
                if (btnSpinner) btnSpinner.style.display = 'none';
            }
            if (statusBox) {
                statusBox.className = 'dior-db-alert alert-error';
                statusBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Connection issue while booking appointment. Please try again.';
                statusBox.style.display = 'flex';
            }
            console.error('[Dior Medical] Booking error:', err);
        });

        return false;
    };

    /**
     * Injects the newly booked appointment into Patient Dashboard tables & cards in real-time
     */
    window.diorInjectAppointmentIntoDashboards = function(appt) {
        try {
            // 1. Update the appointment tables with the correct column structure.
            // Use the browser's local calendar date (toISOString() can shift the day by timezone).
            var now = new Date();
            var todayYmd = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            var apptYmd = String(appt.date || '').slice(0, 10);
            var isToday = (apptYmd === todayYmd);
            var isUpcoming = (apptYmd > todayYmd);
            var tbodyToday = document.getElementById('dior-today-appointments-tbody');
            var tbodyUpcoming = document.getElementById('dior-upcoming-appointments-tbody');
            var apptId = String(appt.uid || appt.appt_uid || appt.id || '');
            var escapeHtml = function(value) {
                return String(value == null ? '' : value).replace(/[&<>"']/g, function(char) {
                    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[char];
                });
            };
            var doctor = escapeHtml(appt.doctor || '—');
            var spec = escapeHtml(appt.spec || '—');
            var dateLabel = escapeHtml(appt.date || '—');
            var time = escapeHtml(appt.time || '—');
            var type = escapeHtml(appt.type || 'Consultation');
            var phone = escapeHtml(appt.phone || 'On file');
            var safeId = escapeHtml(apptId);

            var rowHtmlToday = `
                <tr class="dior-newly-added-row">
                    <td class="ps-3 py-3"><input type="checkbox" class="form-check-input" aria-label="Select appointment"></td>
                    <td class="py-3"><span class="dior-appointment-doctor-name">${doctor}</span></td>
                    <td class="py-3"><span class="text-secondary small fw-medium">${spec}</span></td>
                    <td class="py-3"><div class="d-flex align-items-center gap-1 text-secondary"><i class="far fa-calendar text-primary"></i><span>${dateLabel}</span></div></td>
                    <td class="py-3"><span class="fw-semibold">${time}</span></td>
                    <td class="py-3"><span>${type}</span></td>
                    <td class="py-3"><span>${phone}</span></td>
                    <td class="py-3"><span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">Confirmed</span></td>
                    <td class="text-end pe-3 py-3">
                        <div class="cell-actions d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                            <button type="button" class="action-icon-btn btn" title="Join Video Consultation" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('consultation');"><i class="fas fa-video text-primary"></i></button>
                            <button type="button" class="action-icon-btn btn" title="View Details" onclick="if(typeof diorViewAppointmentDetails==='function') diorViewAppointmentDetails('${safeId}');"><i class="fas fa-eye text-secondary"></i></button>
                            <button type="button" class="action-icon-btn btn" title="Cancel Appointment" onclick="if(typeof diorCancelPatientAppointment==='function') diorCancelPatientAppointment('${safeId}', this);"><i class="fas fa-trash text-danger"></i></button>
                        </div>
                    </td>
                </tr>`;
            var rowHtmlUpcoming = `
                <tr class="dior-newly-added-row">
                    <td class="ps-3 py-3"><input type="checkbox" class="form-check-input" aria-label="Select appointment"></td>
                    <td class="py-3"><span class="dior-appointment-doctor-name">${doctor}</span></td>
                    <td class="py-3"><span>${dateLabel}</span></td>
                    <td class="py-3"><span class="fw-semibold">${time}</span></td>
                    <td class="py-3"><span>${type}</span></td>
                    <td class="py-3"><span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">Confirmed</span></td>
                    <td class="py-3"><span>—</span></td>
                    <td class="text-end pe-3 py-3">
                        <div class="cell-actions d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                            <button type="button" class="action-icon-btn btn" title="View Details" onclick="if(typeof diorViewAppointmentDetails==='function') diorViewAppointmentDetails('${safeId}');"><i class="fas fa-eye text-secondary"></i></button>
                            <button type="button" class="action-icon-btn btn" title="Cancel Appointment" onclick="if(typeof diorCancelPatientAppointment==='function') diorCancelPatientAppointment('${safeId}', this);"><i class="fas fa-trash text-danger"></i></button>
                        </div>
                    </td>
                </tr>`;

            if (isToday && tbodyToday) {
                var emptyToday = tbodyToday.querySelector('.dior-empty-state');
                if (emptyToday) emptyToday.remove();
                if (!tbodyToday.querySelector('[data-appointment-id="' + safeId + '"]')) {
                    // Tag the row so a refresh/live callback cannot duplicate it.
                    var taggedTodayRow = rowHtmlToday.replace('class="dior-newly-added-row"', 'class="dior-newly-added-row" data-appointment-id="' + safeId + '"');
                    tbodyToday.insertAdjacentHTML('afterbegin', taggedTodayRow);
                }
                var countToday = document.getElementById('dior-today-page-count');
                if (countToday) {
                    var matchToday = countToday.textContent.match(/\/\s*(\d+)\s*total/i);
                    var currentTotal = matchToday ? parseInt(matchToday[1], 10) : tbodyToday.querySelectorAll('tr:not(.dior-empty-state)').length - 1;
                    countToday.textContent = '0 selected / ' + Math.max(1, currentTotal + 1) + ' total';
                }
            }

            if ((isUpcoming || isToday) && tbodyUpcoming) {
                var emptyUpcoming = tbodyUpcoming.querySelector('.dior-empty-state');
                if (emptyUpcoming) emptyUpcoming.remove();
                if (!tbodyUpcoming.querySelector('[data-appointment-id="' + safeId + '"]')) {
                    var taggedUpcomingRow = rowHtmlUpcoming.replace('class="dior-newly-added-row"', 'class="dior-newly-added-row" data-appointment-id="' + safeId + '"');
                    tbodyUpcoming.insertAdjacentHTML('afterbegin', taggedUpcomingRow);
                }
                var countUpcoming = document.getElementById('dior-upcoming-page-count');
                if (countUpcoming) {
                    var matchUpcoming = countUpcoming.textContent.match(/\/\s*(\d+)\s*total/i);
                    var currentUpTotal = matchUpcoming ? parseInt(matchUpcoming[1], 10) : tbodyUpcoming.querySelectorAll('tr:not(.dior-empty-state)').length - 1;
                    countUpcoming.textContent = '0 selected / ' + Math.max(1, currentUpTotal + 1) + ' total';
                }
            }

            // 2. Increment Overview Counters
            var statCards = document.querySelectorAll('.stat-card .stat-num');
            if (statCards.length >= 2) {
                var totalEl = statCards[0];
                var upEl = statCards[1];
                if (totalEl) {
                    var curT = parseInt(totalEl.textContent, 10) || 0;
                    totalEl.textContent = String(curT + 1).padStart(2, '0');
                }
                if (upEl) {
                    var curU = parseInt(upEl.textContent, 10) || 0;
                    upEl.textContent = String(curU + 1).padStart(2, '0');
                }
            }

            // 3. Update Next Appointment Card in Overview
            var nextDocName = document.querySelector('.hero-appointment-card .doctor-name');
            var nextDocDate = document.querySelector('.hero-appointment-card .appointment-date');
            if (nextDocName) nextDocName.textContent = appt.doctor;
            if (nextDocDate) nextDocDate.textContent = appt.date + ' at ' + appt.time;

        } catch (err) {
            console.error('[Dior Medical] Live DOM update warning:', err);
        }
    };

    /**
     * Called when user clicks "View in Appointments Tab" from success screen
     */
    window.diorOnBookingSuccessDone = function() {
        window.diorCloseDocBookerModal();
        if (typeof diorSwitchTab === 'function') {
            diorSwitchTab('appointments');
            if (typeof diorSwitchApptSubTab === 'function') {
                diorSwitchApptSubTab('today');
            }
        } else {
            window.location.hash = '#tab=appointments';
            window.location.reload();
        }
    };

    /**
     * Automatic HIPAAtizer Listener
     * Listens to cross-origin iframe postMessage events from HIPAAtizer renderer
     */
    window.addEventListener('message', function(event) {
        if (!event || !event.data) return;
        var d = event.data;

        // Catch stringified JSON if passed as string
        if (typeof d === 'string') {
            try { d = JSON.parse(d); } catch (e) {}
        }

        // HIPAAtizer triggers 'submitSuccess', 'redirect', or similar on intake completion
        var isHipaatizerSubmit = false;
        if (d) {
            if (d.type === 'submitSuccess' || d.type === 'submitted' || d.type === 'redirect' || d.type === 'workflowSubmitted') {
                isHipaatizerSubmit = true;
            } else if (typeof d === 'string' && (d.indexOf('submitSuccess') !== -1 || d.indexOf('submitted') !== -1)) {
                isHipaatizerSubmit = true;
            } else if (d.event === 'submitSuccess' || d.action === 'submitSuccess') {
                isHipaatizerSubmit = true;
            }
        }

        if (isHipaatizerSubmit) {
            console.log('[Dior Medical] ✨ HIPAAtizer Intake Completion detected:', d);
            // Mark intake as completed for this booking session
            window.diorBookingIntakeCompleted = true;

            // 1. Close any open intake popup / modal first
            if (typeof window.diorCloseHipaaModal === 'function') {
                window.diorCloseHipaaModal();
            }
            if (typeof window.diorCloseModal === 'function') {
                window.diorCloseModal('modal-book-appointment');
            }
            if (typeof Swal !== 'undefined' && Swal.isVisible && Swal.isVisible()) {
                Swal.close();
            }

            // 2. Ensure we are on appointments -> book tab
            if (typeof diorSwitchTab === 'function') {
                try { diorSwitchTab('appointments'); } catch (e) {}
            }
            if (typeof window.diorSwitchApptSubTab === 'function') {
                try { window.diorSwitchApptSubTab('book'); } catch (e) {}
            }

            // 3. Advance Horizontal Stepper to Step 2: DocBooker
            setTimeout(function() {
                window.diorGoToBookingStep(2);
            }, 350);
        }
    });

    /**
     * Check if current URL/hash indicates the stepper should advance to Step 2 (e.g. from HIPAAtizer redirection)
     */
    function shouldAutoOpenBookingModal() {
        var s = (window.location.search || '').toLowerCase();
        var h = (window.location.hash || '').toLowerCase();
        var combined = s + '&' + h;
        return (
            combined.indexOf('intake_submitted=1') !== -1 ||
            combined.indexOf('intake_completed=1') !== -1 ||
            combined.indexOf('step=2') !== -1 ||
            combined.indexOf('intake_done=1') !== -1 ||
            combined.indexOf('open_booking=1') !== -1 ||
            combined.indexOf('book=1') !== -1 ||
            combined.indexOf('booking=1') !== -1 ||
            combined.indexOf('open_docbooker=1') !== -1 ||
            combined.indexOf('docbooker=1') !== -1 ||
            h.indexOf('book-appointment') !== -1 ||
            h.indexOf('open-booking') !== -1 ||
            h.indexOf('book-visit-modal') !== -1
        );
    }

    function checkAndAutoOpenModal() {
        if (shouldAutoOpenBookingModal()) {
            // Mark intake completed since landed from HIPAAtizer redirection
            window.diorBookingIntakeCompleted = true;

            if (typeof diorSwitchTab === 'function') {
                try { diorSwitchTab('appointments'); } catch (e) {}
            }
            if (typeof window.diorSwitchApptSubTab === 'function') {
                try { window.diorSwitchApptSubTab('book'); } catch (e) {}
            }

            // Advance Horizontal Stepper to Step 2: DocBooker
            setTimeout(function() {
                window.diorGoToBookingStep(2);
            }, 350);
        }
    }

    // Helper functions for Step 1 Intake Form toggle
    window.diorOpenIntakeForm = function() {
        var sec = document.getElementById('dior-hipaa-embed-section');
        var btn = document.getElementById('dior-start-intake-btn');
        if (sec) {
            sec.classList.remove('d-none');
            sec.style.setProperty('display', 'block', 'important');
            sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        if (btn) {
            btn.innerHTML = '<i class="fas fa-arrow-down me-2"></i> Clinical Intake Form Opened Below';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-primary');
        }
    };

    window.diorToggleHipaaIframe = function() {
        var sec = document.getElementById('dior-hipaa-embed-section');
        if (sec) {
            if (sec.classList.contains('d-none') || sec.style.display === 'none') {
                sec.classList.remove('d-none');
                sec.style.setProperty('display', 'block', 'important');
                sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                sec.classList.add('d-none');
                sec.style.setProperty('display', 'none', 'important');
            }
        }
    };

    // Initialize auto-listeners on DOM ready and on hashchange
    document.addEventListener('DOMContentLoaded', function() {
        checkAndAutoOpenModal();

        // Delegate clicks for any button with data-action="open-docbooker" or .dior-open-docbooker-btn
        document.body.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-action="open-docbooker"], .dior-open-docbooker-btn');
            if (btn) {
                e.preventDefault();
                window.diorOpenDocBookerModal();
            }
        });
    });

    window.addEventListener('hashchange', function() {
        checkAndAutoOpenModal();
    });

})();
