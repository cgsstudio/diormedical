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

    /**
     * Open the DocBooker Booking Modal
     */
    window.diorOpenDocBookerModal = function(options) {
        options = options || {};
        var modal = document.getElementById('dior-docbooker-modal');
        if (!modal) {
            console.warn('[Dior Medical] Booking modal DOM not found.');
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

        modal.style.display = 'flex';
        // Trigger reflow for CSS transition
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
        setTimeout(function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
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
     * Query DocBooker live availability for given date
     */
    window.diorFetchDocBookerAvailability = function(dateStr) {
        if (!dateStr) return;
        var spinner = document.getElementById('dior-db-slots-loading');
        var grid = document.getElementById('dior-db-slots-grid');
        if (spinner) spinner.style.display = 'inline-block';

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
            if (data && data.clinics && data.clinics.length > 0 && data.clinics[0].timings) {
                var timings = data.clinics[0].timings;
                window.diorRenderAvailableSlots(timings);
            }
        })
        .catch(function(err) {
            if (spinner) spinner.style.display = 'none';
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
            window.diorRenderAvailableSlots(defaultSlots);
        });
    };

    /**
     * Render slot buttons inside the grid
     */
    window.diorRenderAvailableSlots = function(timings) {
        var grid = document.getElementById('dior-db-slots-grid');
        if (!grid) return;
        grid.innerHTML = '';

        var firstAvailable = null;
        timings.forEach(function(item, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dior-db-slot-btn';
            btn.setAttribute('data-time', item.time);
            btn.textContent = item.time;

            if (item.is_available) {
                if (!firstAvailable) {
                    firstAvailable = item.time;
                    btn.classList.add('active');
                }
                btn.onclick = function() {
                    window.diorSelectTimeSlot(item.time, btn);
                };
            } else {
                btn.disabled = true;
                btn.classList.add('is-booked');
                btn.title = 'Slot Booked';
            }
            grid.appendChild(btn);
        });

        if (firstAvailable) {
            window.diorDocBookerState.time = firstAvailable;
            var timeInput = document.getElementById('dior-db-selected-time');
            var timeFinal = document.getElementById('dior-db-final-time');
            if (timeInput) timeInput.value = firstAvailable;
            if (timeFinal) timeFinal.value = firstAvailable;
        }
    };

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

                // Dynamically update dashboard elements if present on page
                window.diorInjectAppointmentIntoDashboards({
                    uid: apptUid,
                    doctor: window.diorDocBookerState.doctorName,
                    spec: 'Emergency Medicine & Telehealth Urgent Care',
                    date: dateInput.value,
                    time: window.diorDocBookerState.time,
                    type: window.diorDocBookerState.serviceType,
                    patientName: nameInput.value,
                    phone: phoneInput.value
                });

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
            // 1. Update Today's table if date is today
            var todayYmd = new Date().toISOString().split('T')[0];
            var isToday = (appt.date === todayYmd);
            var tbodyToday = document.getElementById('dior-today-appointments-tbody');
            var tbodyUpcoming = document.getElementById('dior-upcoming-appointments-tbody');

            var rowHtml = `
                <tr class="dior-newly-added-row" style="background: #f0fdf4; transition: background 1.5s ease;">
                    <td>
                        <div class="cell-content cell-image-name">
                            <span class="cell-text doctor-name" style="font-weight:700;">${appt.doctor}</span>
                        </div>
                    </td>
                    <td><span class="cell-text">${appt.spec}</span></td>
                    <td>
                        <div class="cell-content cell-icon-text">
                            <i class="far fa-calendar cell-icon"></i>
                            <span class="cell-text">${appt.date}</span>
                        </div>
                    </td>
                    <td><span class="cell-text" style="font-weight:600;">${appt.time}</span></td>
                    <td><span class="cell-text" style="color:#d4af37; font-weight:600;">${appt.type}</span></td>
                    <td><span class="cell-text">${appt.phone || 'On file'}</span></td>
                    <td>
                        <span class="status-cell-text status-confirm" style="background:#d1fae5; color:#047857; font-weight:700; padding:4px 8px; border-radius:6px;">Confirmed</span>
                    </td>
                </tr>
            `;

            if (isToday && tbodyToday) {
                tbodyToday.insertAdjacentHTML('afterbegin', rowHtml);
                var countToday = document.getElementById('dior-today-page-count');
                if (countToday) {
                    var currentTotal = parseInt(countToday.textContent.replace(/[^0-9]/g, ''), 10) || 0;
                    countToday.textContent = '0 selected / ' + (currentTotal + 1) + ' total';
                }
            }

            if (tbodyUpcoming) {
                tbodyUpcoming.insertAdjacentHTML('afterbegin', rowHtml);
                var countUpcoming = document.getElementById('dior-upcoming-page-count');
                if (countUpcoming) {
                    var currentUpTotal = parseInt(countUpcoming.textContent.replace(/[^0-9]/g, ''), 10) || 0;
                    countUpcoming.textContent = '0 selected / ' + (currentUpTotal + 1) + ' total';
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

            // 2. Open DocBooker Appointment Booking Modal
            setTimeout(function() {
                window.diorOpenDocBookerModal({
                    reason: 'Telehealth Intake Completed'
                });
            }, 450);
        }
    });

    /**
     * Check if current URL/hash indicates the modal should auto-open (e.g. from HIPAAtizer redirection)
     */
    function shouldAutoOpenBookingModal() {
        var s = (window.location.search || '').toLowerCase();
        var h = (window.location.hash || '').toLowerCase();
        var combined = s + '&' + h;
        return (
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
            if (typeof diorSwitchTab === 'function') {
                try { diorSwitchTab('appointments'); } catch (e) {}
            }
            setTimeout(function() {
                window.diorOpenDocBookerModal({
                    reason: 'HIPAAtizer Intake Completed'
                });
            }, 400);
        }
    }

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
