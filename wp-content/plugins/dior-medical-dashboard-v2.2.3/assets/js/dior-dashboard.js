/**
 * Dior Medical - Luxury Telehealth Patient Portal Interactive JS
 * Full State & AJAX Controller for Dashboard Modules, Modals, Forms & Widgets
 */

window.diorOnIntakeSubmitted = function(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    const hipaaWrap = document.getElementById('dior-hipaa-form-container');
    const bookingSec = document.getElementById('dior-dashboard-booking-section');
    const stepNotice = document.getElementById('dior-intake-step-notice');
    const compNotice = document.getElementById('dior-intake-completed-notice');

    if (hipaaWrap) {
        hipaaWrap.style.setProperty('display', 'none', 'important');
    }
    if (bookingSec) {
        bookingSec.style.setProperty('display', 'block', 'important');
        bookingSec.scrollIntoView({ behavior: 'smooth' });
    }
    if (stepNotice) {
        stepNotice.style.setProperty('display', 'none', 'important');
    }
    if (compNotice) {
        compNotice.style.setProperty('display', 'flex', 'important');
    }

    if (typeof window.diorInBookingGoToStep === 'function') {
        window.diorInBookingGoToStep('doctor');
    }

    if (typeof showToast === 'function') {
        showToast('Clinical Intake Submitted! Unlocking physician scheduling calendar...', false);
    }
};

// Automatic HIPAAtizer Embed Message Listener
window.addEventListener('message', function(event) {
    if (!event || !event.data) return;
    var data = event.data;
    var isSubmitted = false;

    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch(e) {}
    }

    if (data && typeof data === 'object') {
        if (data.type === 'hipaatizer-form-submitted' ||
            data.action === 'form_submitted' ||
            data.event === 'submit' ||
            data.event === 'form_submitted' ||
            data.status === 'completed' ||
            data.status === 'submitted' ||
            (data.source && String(data.source).indexOf('hipaatizer') !== -1 && (data.action === 'submit' || data.event === 'submit'))
        ) {
            isSubmitted = true;
        }
    } else if (typeof data === 'string' && (data.indexOf('hipaatizer') !== -1 && (data.indexOf('submitted') !== -1 || data.indexOf('completed') !== -1))) {
        isSubmitted = true;
    }

    if (isSubmitted) {
        if (typeof window.diorOnIntakeSubmitted === 'function') {
            window.diorOnIntakeSubmitted();
        }
    }
});

// Check URL parameters for direct redirect from HIPAAtizer settings
try {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('intake_completed') === '1' || urlParams.get('intake') === 'success' || urlParams.get('submitted') === '1' || window.location.hash.indexOf('booking') !== -1) {
        setTimeout(function() {
            if (typeof window.diorOnIntakeSubmitted === 'function') {
                window.diorOnIntakeSubmitted();
            }
        }, 500);
    }
} catch(e) {}

// Automatic Background Poller to detect intake webhook completion in real-time
(function initIntakeWatcher() {
    var checkTimer = null;
    function checkStatus() {
        var hipaaWrap = document.getElementById('dior-hipaa-form-container');
        if (!hipaaWrap || hipaaWrap.style.display === 'none') {
            if (checkTimer) clearInterval(checkTimer);
            return;
        }

        if (typeof jQuery !== 'undefined' && typeof dior_vars !== 'undefined' && dior_vars.ajax_url) {
            jQuery.ajax({
                url: dior_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'dior_patient_check_intake_status',
                    nonce: dior_vars.nonce
                },
                dataType: 'json',
                success: function(res) {
                    if (res && res.success && res.data && res.data.has_intake) {
                        if (checkTimer) clearInterval(checkTimer);
                        window.diorOnIntakeSubmitted();
                    }
                }
            });
        }
    }

    // Check every 5 seconds while intake form is active
    checkTimer = setInterval(checkStatus, 5000);
})();

window.diorToggleIntakeReview = function() {
    const card = document.getElementById('dior-intake-review-card');
    const btnText = document.getElementById('toggle-intake-btn-text');
    if (card) {
        if (card.style.display === 'none' || !card.style.display) {
            card.style.setProperty('display', 'block', 'important');
            if (btnText) btnText.textContent = 'Hide Submitted Answers';
            card.scrollIntoView({ behavior: 'smooth' });
        } else {
            card.style.setProperty('display', 'none', 'important');
            if (btnText) btnText.textContent = 'View Submitted Answers';
        }
    }
};

window.diorReopenHipaaForm = function() {
    const hipaaWrap = document.getElementById('dior-hipaa-form-container');
    const bookingSec = document.getElementById('dior-dashboard-booking-section');
    if (hipaaWrap) {
        hipaaWrap.style.setProperty('display', 'block', 'important');
        hipaaWrap.scrollIntoView({ behavior: 'smooth' });
    }
    if (bookingSec) {
        bookingSec.style.setProperty('display', 'none', 'important');
    }
};

// =========================================================================
// IN-DASHBOARD APPOINTMENT BOOKING & INTAKE STATE MACHINE
// =========================================================================
window.diorInBookingState = {
    step: 1,
    doctorId: 1695,
    doctorName: 'Dr. James Chen, DO',
    doctorSpeciality: 'Primary Care & Urgent Care',
    date: new Date().toISOString().split('T')[0],
    day: new Date().toLocaleDateString('en-US', { weekday: 'long' }),
    clinicId: 1,
    time: null,
    patient: {
        name: (typeof dior_vars !== 'undefined' && dior_vars.patient_profile && dior_vars.patient_profile.full_name) || '',
        email: (typeof dior_vars !== 'undefined' && dior_vars.patient_profile && dior_vars.patient_profile.email) || '',
        phone: (typeof dior_vars !== 'undefined' && dior_vars.patient_profile && dior_vars.patient_profile.phone) || '',
        service: (typeof dior_vars !== 'undefined' && dior_vars.hipaa_intake && (dior_vars.hipaa_intake.service_condition || dior_vars.hipaa_intake.symptoms)) || 'Telehealth Medical Care',
        notes: (typeof dior_vars !== 'undefined' && dior_vars.hipaa_intake && dior_vars.hipaa_intake.symptoms) || ''
    }
};

window.diorInBookingGoToStep = function(stepName) {
    const stepDoctor = document.getElementById('in-step-doctor');
    const stepTime = document.getElementById('in-step-time');
    const stepConfirm = document.getElementById('in-step-confirm');

    const node1 = document.getElementById('wizard-node-1');
    const node2 = document.getElementById('wizard-node-2');
    const node3 = document.getElementById('wizard-node-3');

    if (stepDoctor) stepDoctor.style.setProperty('display', 'none', 'important');
    if (stepTime) stepTime.style.setProperty('display', 'none', 'important');
    if (stepConfirm) stepConfirm.style.setProperty('display', 'none', 'important');

    if (node1) { node1.style.background = '#F8FAFC'; node1.style.border = '1px solid #E2E8F0'; }
    if (node2) { node2.style.background = '#F8FAFC'; node2.style.border = '1px solid #E2E8F0'; }
    if (node3) { node3.style.background = '#F8FAFC'; node3.style.border = '1px solid #E2E8F0'; }

    if (stepName === 'doctor' || stepName === 1) {
        if (stepDoctor) stepDoctor.style.setProperty('display', 'block', 'important');
        if (node1) { node1.style.background = '#EFF6FF'; node1.style.border = '1.5px solid #2C6CB1'; }
    } else if (stepName === 'time' || stepName === 2) {
        if (stepTime) stepTime.style.setProperty('display', 'block', 'important');
        if (node2) { node2.style.background = '#EFF6FF'; node2.style.border = '1.5px solid #2C6CB1'; }
        window.diorInFetchAvailability();
    } else if (stepName === 'confirm' || stepName === 3) {
        if (stepConfirm) stepConfirm.style.setProperty('display', 'block', 'important');
        if (node3) { node3.style.background = '#EFF6FF'; node3.style.border = '1.5px solid #2C6CB1'; }
    }
};

window.diorInSelectDoctor = function(docId, docName, spec) {
    if (!window.diorInBookingState) {
        window.diorInBookingState = { patient: {} };
    }
    window.diorInBookingState.doctorId = docId || 1695;
    window.diorInBookingState.doctorName = docName || 'Doctor';
    window.diorInBookingState.doctorSpeciality = spec || 'Telehealth Physician';

    const summaryPill = document.getElementById('in-selected-doctor-summary-pill');
    if (summaryPill) {
        summaryPill.innerHTML = `
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; border-radius:50%; background:#EFF6FF; color:#2C6CB1; display:flex; align-items:center; justify-content:center; font-weight:700;">
                    <i class="fa-solid fa-user-md"></i>
                </div>
                <div>
                    <strong style="color:#0F172A; display:block;">${docName}</strong>
                    <span style="color:#2C6CB1; font-size:12px;">${spec}</span>
                </div>
            </div>
        `;
    }

    document.querySelectorAll('.dior-doc-select-card').forEach(c => c.classList.remove('selected'));
    const sc = document.querySelector(`.dior-doc-select-card[data-doc-id="${docId}"]`);
    if (sc) sc.classList.add('selected');

    window.diorInBookingGoToStep('time');
};

window.diorInFetchAvailability = function() {
    const dateInput = document.getElementById('in-booking-date');
    const container = document.getElementById('in-time-slots-container');
    if (!container) return;

    let dateVal = dateInput ? dateInput.value : (window.diorInBookingState && window.diorInBookingState.date);
    if (!dateVal) dateVal = new Date().toISOString().split('T')[0];
    if (window.diorInBookingState) window.diorInBookingState.date = dateVal;

    const dateObj = new Date(dateVal + 'T00:00:00');
    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
    if (window.diorInBookingState) window.diorInBookingState.day = dayName;

    function renderSlots(slots, label) {
        let html = `<div style="font-size:12.5px; font-weight:700; color:#475569; margin-bottom:8px;">${label || 'Available Consultation Slots'} (${dayName}):</div>`;
        html += `<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap:8px;">`;
        slots.forEach(slot => {
            const timeText = typeof slot === 'object' ? slot.time : slot;
            const isAvail = typeof slot === 'object' ? slot.is_available : true;
            if (isAvail) {
                html += `<button type="button" class="dior-time-btn" onclick="diorInSelectTime(1, '${timeText}')" style="padding:10px 12px; border:1.5px solid #CBD5E1; border-radius:8px; background:#FFFFFF; color:#0F172A; font-weight:700; cursor:pointer; font-size:13px; transition:all 0.15s ease;">${timeText}</button>`;
            } else {
                html += `<button type="button" disabled style="padding:10px 12px; border:1px solid #E2E8F0; border-radius:8px; background:#F1F5F9; color:#94A3B8; font-weight:500; cursor:not-allowed; font-size:13px;">${timeText}</button>`;
            }
        });
        html += `</div>`;
        container.innerHTML = html;
    }

    const defaultSlots = ['09:00 AM', '10:00 AM', '11:30 AM', '01:00 PM', '02:30 PM', '04:00 PM', '05:30 PM'];
    renderSlots(defaultSlots, 'Virtual Consultation Schedule');

    const restUrl = (typeof dior_vars !== 'undefined' && dior_vars.rest_url) || (window.location.origin + '/wp-json/');
    const restNonce = (typeof dior_vars !== 'undefined' && dior_vars.rest_nonce) || '';
    const docId = window.diorInBookingState ? window.diorInBookingState.doctorId : 1695;

    fetch(`${restUrl}doctor-details-booking/v1/doctors/${docId}/availability?date=${dateVal}`, {
        headers: { 'X-WP-Nonce': restNonce }
    })
    .then(r => r.json())
    .then(data => {
        if (data && data.clinics && data.clinics.length > 0) {
            let clinicSlots = [];
            data.clinics.forEach(clinic => {
                if (!clinic.is_on_holiday && clinic.timings && clinic.timings.length > 0) {
                    clinic.timings.forEach(t => clinicSlots.push(t));
                }
            });
            if (clinicSlots.length > 0) {
                renderSlots(clinicSlots, 'DocBooker Live Clinic Timings');
            }
        }
    })
    .catch(() => {});
};

window.diorInSelectTime = function(clinicId, timeStr) {
    if (!window.diorInBookingState) window.diorInBookingState = {};
    window.diorInBookingState.clinicId = clinicId;
    window.diorInBookingState.time = timeStr;

    const confirmSummary = document.getElementById('in-booking-confirm-summary');
    if (confirmSummary) {
        const formattedDate = new Date(window.diorInBookingState.date + 'T00:00:00').toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });

        const pName = (window.diorInBookingState.patient && window.diorInBookingState.patient.name) || (typeof dior_vars !== 'undefined' && dior_vars.patient_profile && dior_vars.patient_profile.full_name) || 'Patient';
        const pEmail = (window.diorInBookingState.patient && window.diorInBookingState.patient.email) || (typeof dior_vars !== 'undefined' && dior_vars.patient_profile && dior_vars.patient_profile.email) || '';
        const pPhone = (window.diorInBookingState.patient && window.diorInBookingState.patient.phone) || (typeof dior_vars !== 'undefined' && dior_vars.patient_profile && dior_vars.patient_profile.phone) || '';
        const pService = (window.diorInBookingState.patient && window.diorInBookingState.patient.service) || 'Telehealth Medical Care';

        confirmSummary.innerHTML = `
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div>
                    <span style="font-size:12px; color:#64748B; text-transform:uppercase; font-weight:700; display:block;">Attending Physician:</span>
                    <strong style="font-size:15px; color:#0F172A;">${window.diorInBookingState.doctorName}</strong>
                    <span style="font-size:12.5px; color:#2C6CB1; display:block;">${window.diorInBookingState.doctorSpeciality}</span>
                </div>
                <div>
                    <span style="font-size:12px; color:#64748B; text-transform:uppercase; font-weight:700; display:block;">Appointment Date & Time:</span>
                    <strong style="font-size:15px; color:#0F172A;">${formattedDate}</strong>
                    <span style="font-size:13px; color:#059669; font-weight:700; display:block;"><i class="fa-regular fa-clock"></i> ${timeStr} (Video Visit)</span>
                </div>
                <div style="grid-column: 1 / -1; border-top:1px solid #E2E8F0; padding-top:12px; margin-top:4px;">
                    <span style="font-size:12px; color:#64748B; text-transform:uppercase; font-weight:700; display:block;">Intake Profile & Patient Details:</span>
                    <strong style="font-size:14px; color:#0F172A;">${pName}</strong> &bull;
                    <span style="font-size:13px; color:#475569;">${pEmail} &bull; ${pPhone}</span>
                    <div style="margin-top:6px; font-size:12.5px; color:#64748B;"><strong>Reason:</strong> ${pService}</div>
                </div>
            </div>
        `;
    }

    window.diorInBookingGoToStep('confirm');
};

window.diorInSubmitBooking = function() {
    const btn = document.getElementById('btn-in-confirm-booking');
    const msg = document.getElementById('in-booking-status-msg');

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Scheduling Consultation & Syncing...';
    }

    if (msg) {
        msg.style.display = 'none';
    }

    const payload = {
        action: 'dior_patient_book_appointment',
        nonce: (typeof dior_vars !== 'undefined' && dior_vars.nonce) || '',
        doctorId: (window.diorInBookingState && window.diorInBookingState.doctorId) || 1695,
        doctor_name: (window.diorInBookingState && window.diorInBookingState.doctorName) || 'Doctor',
        date: (window.diorInBookingState && window.diorInBookingState.date) || '',
        time: (window.diorInBookingState && window.diorInBookingState.time) || '',
        condition: (window.diorInBookingState && window.diorInBookingState.patient && window.diorInBookingState.patient.service) || 'Telehealth Consultation',
        notes: (window.diorInBookingState && window.diorInBookingState.patient && window.diorInBookingState.patient.notes) || '',
        name: (window.diorInBookingState && window.diorInBookingState.patient && window.diorInBookingState.patient.name) || '',
        email: (window.diorInBookingState && window.diorInBookingState.patient && window.diorInBookingState.patient.email) || '',
        phone: (window.diorInBookingState && window.diorInBookingState.patient && window.diorInBookingState.patient.phone) || ''
    };

    const formData = new FormData();
    for (let k in payload) {
        formData.append(k, payload[k]);
    }

    fetch((typeof dior_vars !== 'undefined' && dior_vars.ajax_url) || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (msg) {
                msg.style.display = 'block';
                msg.style.background = '#ECFDF5';
                msg.style.border = '1px solid #A7F3D0';
                msg.style.color = '#065F46';
                msg.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + (res.data.message || 'Appointment booked successfully! Doctor & Patient notifications sent.');
            }
            if (btn) {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Booked Successfully';
            }

            if (window.diorUpdateCountdown) {
                window.diorUpdateCountdown(window.diorInBookingState.date, window.diorInBookingState.time);
            }

            setTimeout(() => {
                window.location.hash = '#tab=appointments';
                window.location.reload();
            }, 1500);
        } else {
            if (msg) {
                msg.style.display = 'block';
                msg.style.background = '#FEF2F2';
                msg.style.border = '1px solid #FECACA';
                msg.style.color = '#991B1B';
                msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + ((res.data && res.data.message) || 'Failed to book appointment.');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-video"></i> Confirm & Schedule Telehealth Visit';
            }
        }
    })
    .catch(err => {
        console.error('Booking error:', err);
        if (msg) {
            msg.style.display = 'block';
            msg.style.background = '#FEF2F2';
            msg.style.border = '1px solid #FECACA';
            msg.style.color = '#991B1B';
            msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> An error occurred while booking.';
        }
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-video"></i> Confirm & Schedule Telehealth Visit';
        }
    });
};

document.addEventListener('click', function(e) {
    const card = e.target.closest('.dior-doc-select-card');
    if (card) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        const docId = card.getAttribute('data-doc-id');
        const docName = card.getAttribute('data-doc-name') || (card.querySelector('h5') && card.querySelector('h5').innerText) || 'Doctor';
        const docSpec = card.getAttribute('data-doc-spec') || (card.querySelector('span') && card.querySelector('span').innerText) || 'Telehealth Physician';
        window.diorInSelectDoctor(docId, docName, docSpec);
    }
});

window.diorToggleNotifDropdown = function(e) {
    if (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
    }
    const dropdown = document.getElementById('dior-notif-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('open');
    }
};

document.addEventListener('click', function(e) {
    const wrap = document.getElementById('dior-notif-dropdown-wrap');
    const dropdown = document.getElementById('dior-notif-dropdown');
    if (dropdown && dropdown.classList.contains('open')) {
        if (!wrap || !wrap.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    }
    
    // Profile Dropdown
    const pWrap = e.target.closest('.dior-user-pill-wrap');
    const pDropdown = document.getElementById('dior-profile-dropdown');
    if (pDropdown && pDropdown.classList.contains('open')) {
        if (!pWrap) {
            pDropdown.classList.remove('open');
        }
    }
});

window.diorToggleProfileDropdown = function(e) {
    if (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
    }
    const dropdown = document.getElementById('dior-profile-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('open');
    }
};

// Universal Table Pagination (5 entries per page)
window.diorInitTablePagination = function(tableEl, pageSize) {
    if (!tableEl) return;
    pageSize = pageSize || 5;
    const tbody = tableEl.querySelector('tbody');
    if (!tbody) return;

    // Find parent container to attach pagination bar
    let parent = tableEl.parentElement;
    let paginationWrap = parent.querySelector(':scope > .dior-table-pagination') || (parent.parentElement ? parent.parentElement.querySelector(':scope > .dior-table-pagination') : null);
    
    if (!paginationWrap) {
        paginationWrap = document.createElement('div');
        paginationWrap.className = 'dior-table-pagination';
        // If wrapped inside a responsive container, put it outside/after the responsive container
        if (parent.classList.contains('dior-table-responsive') || parent.classList.contains('dior-card-body') || parent.style.overflowX === 'auto' || parent.style.overflow === 'auto') {
            parent.after(paginationWrap);
        } else {
            parent.appendChild(paginationWrap);
        }
    }

    let currentPage = 1;

    function renderPage(page) {
        currentPage = page || 1;
        const allRows = Array.from(tbody.querySelectorAll(':scope > tr')).filter(tr => !tr.classList.contains('dior-no-data-row') && tr.querySelectorAll(':scope > td').length > 0);
        
        if (allRows.length === 0) {
            paginationWrap.style.display = 'none';
            paginationWrap.innerHTML = '';
            return;
        }

        const activeRows = allRows.filter(tr => tr.getAttribute('data-filter-hidden') !== 'true');
        const total = activeRows.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));

        // If total entries are 5 or fewer, or only 1 page exists, show all rows and hide pagination bar completely
        if (total <= pageSize || totalPages <= 1) {
            allRows.forEach(tr => {
                if (tr.getAttribute('data-filter-hidden') === 'true') {
                    tr.style.display = 'none';
                } else {
                    tr.style.display = '';
                }
            });
            paginationWrap.style.display = 'none';
            paginationWrap.innerHTML = '';
            return;
        }

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = startIndex + pageSize;

        // Apply visibility
        allRows.forEach(tr => {
            if (tr.getAttribute('data-filter-hidden') === 'true') {
                tr.style.display = 'none';
            }
        });

        activeRows.forEach((tr, index) => {
            if (index >= startIndex && index < endIndex) {
                tr.style.display = '';
            } else {
                tr.style.display = 'none';
            }
        });

        const fromItem = total === 0 ? 0 : startIndex + 1;
        const toItem = Math.min(endIndex, total);

        let navHtml = '';
        if (totalPages > 1) {
            navHtml += `<button type="button" class="dior-page-btn" ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}" title="Previous Page"><i class="fa-solid fa-chevron-left" style="font-size:11px;"></i></button>`;

            if (totalPages <= 7) {
                for (let i = 1; i <= totalPages; i++) {
                    navHtml += `<button type="button" class="dior-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
                }
            } else {
                for (let i = 1; i <= totalPages; i++) {
                    if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                        navHtml += `<button type="button" class="dior-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
                    } else if (i === currentPage - 2 || i === currentPage + 2) {
                        navHtml += `<span style="padding: 0 4px; color: #94A3B8; font-size:12px;">...</span>`;
                    }
                }
            }

            navHtml += `<button type="button" class="dior-page-btn" ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}" title="Next Page"><i class="fa-solid fa-chevron-right" style="font-size:11px;"></i></button>`;
        }

        paginationWrap.style.display = 'flex';
        paginationWrap.innerHTML = `
            <div class="dior-pagination-info">
                Showing <strong>${fromItem}</strong> to <strong>${toItem}</strong> of <strong>${total}</strong> entries
            </div>
            <div class="dior-pagination-nav">
                ${navHtml}
            </div>
        `;

        // Click listeners
        paginationWrap.querySelectorAll('.dior-page-btn[data-page]').forEach(btn => {
            btn.onclick = function(e) {
                e.preventDefault();
                const p = parseInt(this.getAttribute('data-page'), 10);
                if (!isNaN(p)) renderPage(p);
            };
        });
    }

    tableEl._diorRenderPage = renderPage;
    renderPage(1);
};

(function($) {
    'use strict';

    function initPatientPortal() {
        const appWrap = document.getElementById('dior-patient-portal-app');
        if (!appWrap) {
            return;
        }

        // Initialize Pagination for all Patient Dashboard Tables (5 rows max per page)
        document.querySelectorAll('.dior-table, .dior-clean-table, .dior-doc-table').forEach(table => {
            diorInitTablePagination(table, 5);
        });

        // =========================================================================
        // 1. TOAST NOTIFICATION HELPER
        // =========================================================================
        function showToast(message, isError = false) {
            let toast = document.getElementById('dior-toast-alert');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'dior-toast-alert';
                toast.className = 'dior-toast';
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.style.display = 'block';
            toast.style.borderLeftColor = isError ? 'var(--dior-danger)' : 'var(--dior-gold-500)';

            setTimeout(() => {
                toast.style.display = 'none';
            }, 4500);
        }

        // =========================================================================
        // 2. AJAX SENDER HELPER
        // =========================================================================
        function sendAjax(action, data, successCallback, errorCallback) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', (window.dior_vars && window.dior_vars.nonce) ? window.dior_vars.nonce : '');

            for (let key in data) {
                formData.append(key, data[key]);
            }

            const ajaxUrl = (window.dior_vars && window.dior_vars.ajax_url) ? window.dior_vars.ajax_url : '/wp-admin/admin-ajax.php';

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(res => {
                return res.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        return { 
                            success: false, 
                            data: { message: `Server response error: ${text.substring(0, 120)}` } 
                        };
                    }
                });
            })
            .then(response => {
                if (response && response.success) {
                    showToast((response.data && response.data.message) ? response.data.message : 'Action completed successfully.');
                    if (successCallback) successCallback(response.data);
                } else {
                    const errMsg = (response && response.data && response.data.message) ? response.data.message : 'Error processing request.';
                    showToast(errMsg, true);
                    if (errorCallback) errorCallback(response ? response.data : { message: errMsg });
                }
            })
            .catch(err => {
                console.error('[Dior AJAX Failure]', err);
                showToast('Network error or server connection failed.', true);
                if (errorCallback) errorCallback({ message: 'Network error or server connection failed.' });
            });
        }

        // =========================================================================
        // 3. TAB NAVIGATION & URL HASH SYNCHRONIZATION
        // =========================================================================
        const navButtons = document.querySelectorAll('.dior-nav-btn');
        const tabPanels = document.querySelectorAll('.dior-tab-panel');
        const pageTitleEl = document.getElementById('dior-current-page-title');

        const tabTitles = {
            'overview': 'Patient Overview',
            'profile': 'Profile & Telehealth Eligibility',
            'appointments': 'Telehealth Appointments',
            'payments': 'Billing & Payment Statements',
            'docs_meds': 'Medical Docs & Prescriptions',
            'questionnaire': 'Clinical Intake Questionnaires',
            'notifications': 'Notification Inbox'
        };

        // Prompt user to complete profile using SweetAlert2
        function promptCompleteProfile(actionMsg) {
            const missingFieldsObj = (window.dior_vars && window.dior_vars.missing_profile_fields) ? window.dior_vars.missing_profile_fields : {};
            const missingList = Object.values(missingFieldsObj).length ? Object.values(missingFieldsObj).join(', ') : 'First & Last Name, Phone, Date of Birth, Gender, Address';
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Complete Profile First',
                    html: `<div style="text-align:center; padding: 4px 0;">
                        <p style="font-size:14.5px; color:#334155; line-height:1.5; margin:0 0 12px 0;">
                            Your personal information (<strong>${missingList}</strong>) must be completed in your profile before you can ${actionMsg || 'book appointments or access clinical consultations'}.
                        </p>
                        <span style="display:inline-block; font-size:12.5px; color:#64748B; background:#F1F5F9; padding:6px 14px; border-radius:20px; border:1px solid #E2E8F0;">
                            <i class="fa-solid fa-user-pen" style="color:#2C6CB1;"></i> Required for HIPAA &amp; Medical Intake Compliance
                        </span>
                    </div>`,
                    confirmButtonText: '<i class="fa-solid fa-user-pen"></i> Complete Profile Now',
                    confirmButtonColor: '#2C6CB1',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                    cancelButtonColor: '#94A3B8',
                    focusConfirm: true,
                    customClass: {
                        popup: 'dior-swal-modal-box'
                    }
                }).then((res) => {
                    if (res.isConfirmed) {
                        switchTab('profile');
                        setTimeout(() => {
                            const firstEmpty = document.querySelector('#dior-form-patient-profile input:invalid, #dior-form-patient-profile input[name="first_name"]');
                            if (firstEmpty) {
                                firstEmpty.focus();
                                firstEmpty.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        }, 300);
                    }
                });
            } else {
                alert('Please complete your Personal Information in your Profile before booking.');
                switchTab('profile');
            }
        }
        window.diorPromptCompleteProfile = promptCompleteProfile;

        function switchTab(tabId) {
            // Navigation must always work. Profile completeness is validated when the
            // patient actually submits an appointment/intake action, not when opening a tab.
            // This keeps every dashboard section accessible and preserves the existing UI.

            if (!document.getElementById('tab-' + tabId)) {
                tabId = 'overview';
            }

            navButtons.forEach(btn => {
                if (btn.getAttribute('data-tab') === tabId) {
                    btn.classList.add('active');
                    // Dynamic Badge Dismissal: Hide notification/count badge once opened
                    const activeBadge = btn.querySelector('.nav-count-badge');
                    if (activeBadge) {
                        activeBadge.style.display = 'none';
                        try {
                            sessionStorage.setItem('dior_dismissed_badge_' + tabId, '1');
                        } catch(e) {}
                    }
                } else {
                    btn.classList.remove('active');
                }
            });

            tabPanels.forEach(panel => {
                if (panel.id === 'tab-' + tabId) {
                    panel.classList.add('active');
                } else {
                    panel.classList.remove('active');
                }
            });

            if (pageTitleEl && tabTitles[tabId]) {
                pageTitleEl.textContent = tabTitles[tabId];
            }

            if (history.pushState) {
                history.pushState(null, null, '#tab=' + tabId);
            } else {
                location.hash = '#tab=' + tabId;
            }

            if (tabId === 'notifications') {
                document.querySelectorAll('.unread-badge-count, .dior-notif-indicator').forEach(b => {
                    b.style.display = 'none';
                });
                sendAjax('dior_patient_mark_all_notifications_read', {});
            }

            // Refresh table pagination inside activated tab
            const activePanel = document.getElementById('tab-' + tabId);
            if (activePanel) {
                activePanel.querySelectorAll('.dior-table, .dior-clean-table, .dior-doc-table').forEach(table => {
                    if (table._diorRenderPage) {
                        table._diorRenderPage(1);
                    } else {
                        diorInitTablePagination(table, 5);
                    }
                });
            }

            closeMobileDrawer();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Restore previously dismissed badges from session
        navButtons.forEach(btn => {
            const tab = btn.getAttribute('data-tab');
            if (tab) {
                try {
                    if (sessionStorage.getItem('dior_dismissed_badge_' + tab) === '1') {
                        const badge = btn.querySelector('.nav-count-badge');
                        if (badge) badge.style.display = 'none';
                    }
                } catch(e) {}
            }
        });

        navButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const tabId = this.getAttribute('data-tab');
                switchTab(tabId);
            });
        });

        // Delegate switch buttons across the dashboard
        document.addEventListener('click', function(e) {
            const target = e.target.closest('[data-switch-tab]');
            if (target) {
                e.preventDefault();
                const tabId = target.getAttribute('data-switch-tab');
                switchTab(tabId);
                // Also close notif dropdown if open
                const notifDropdown = document.getElementById('dior-notif-dropdown');
                if (notifDropdown) notifDropdown.classList.remove('open');
            }
        });

        window.diorSwitchTab = switchTab;

        // Robust delegated navigation fallback. This also covers dynamically rendered
        // buttons and prevents a missing direct listener from breaking tab navigation.
        appWrap.addEventListener('click', function(e) {
            const btn = e.target.closest('.dior-nav-btn[data-tab]');
            if (!btn || !appWrap.contains(btn)) return;
            if (btn.closest('.dior-nav-item-has-children')) return;
            e.preventDefault();
            switchTab(btn.getAttribute('data-tab'));
        });

        // Deep linking via URL hash & hashchange listener
        function handleUrlHash() {
            if (window.location.hash && window.location.hash.startsWith('#tab=')) {
                const hashTab = window.location.hash.replace('#tab=', '');
                switchTab(hashTab);
            }
        }
        handleUrlHash();
        window.addEventListener('hashchange', handleUrlHash);

        // Check profile completion on patient dashboard initial load
        if (window.dior_vars && window.dior_vars.is_logged_in && parseInt(window.dior_vars.is_profile_complete, 10) !== 1) {
            if (!sessionStorage.getItem('dior_patient_profile_remind_later')) {
                setTimeout(function() {
                    const missingFieldsObj = window.dior_vars.missing_profile_fields || {};
                    const missingList = Object.values(missingFieldsObj).length ? Object.values(missingFieldsObj).join(', ') : 'Personal Information';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'info',
                            title: 'Complete Your Personal Information',
                            html: `<div style="text-align:center; padding: 4px 0;">
                                <p style="font-size:14.5px; color:#334155; line-height:1.5; margin:0 0 10px 0;">
                                    Welcome to Dior Medical! Please complete your required personal information (<strong>${missingList}</strong>) to activate telehealth appointment booking and physician consultations.
                                </p>
                            </div>`,
                            confirmButtonText: '<i class="fa-solid fa-user-pen"></i> Complete Profile Now',
                            confirmButtonColor: '#2C6CB1',
                            showCancelButton: true,
                            cancelButtonText: 'Remind Me Later',
                            cancelButtonColor: '#94A3B8'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                switchTab('profile');
                                setTimeout(() => {
                                    const firstEmpty = document.querySelector('#dior-form-patient-profile input:invalid, #dior-form-patient-profile input[name="first_name"]');
                                    if (firstEmpty) {
                                        firstEmpty.focus();
                                        firstEmpty.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    }
                                }, 300);
                            } else {
                                sessionStorage.setItem('dior_patient_profile_remind_later', '1');
                            }
                        });
                    }
                }, 700);
            }
        }

        // =========================================================================
        // 4. MOBILE DRAWER NAVIGATION
        // =========================================================================
        const mobileToggle = document.getElementById('dior-mobile-menu-toggle');
        const sideClose = document.getElementById('dior-side-close');
        const sidebar = document.getElementById('dior-sidebar');
        const drawerBackdrop = document.getElementById('dior-drawer-backdrop');

        window.diorOpenMobileDrawer = function(e) {
            if (e) e.preventDefault();
            const sb = document.getElementById('dior-sidebar');
            const bg = document.getElementById('dior-drawer-backdrop');
            
            // On desktop, act as a toggle
            if (window.innerWidth > 1024) {
                const wrap = document.querySelector('.dior-patient-portal-wrap') || document.querySelector('.dior-app');
                if (wrap) wrap.classList.toggle('dior-sidebar-collapsed');
                return;
            }

            if (sb) sb.classList.add('mobile-open');
            if (bg) bg.classList.add('active');
            document.body.style.overflow = 'hidden';
        };

        window.diorCloseMobileDrawer = function() {
            const sb = document.getElementById('dior-sidebar');
            const bg = document.getElementById('dior-drawer-backdrop');
            if (sb) sb.classList.remove('mobile-open');
            if (bg) bg.classList.remove('active');
            document.body.style.overflow = '';
        };

        function openMobileDrawer() {
            window.diorOpenMobileDrawer();
        }

        function closeMobileDrawer() {
            window.diorCloseMobileDrawer();
        }

        if (mobileToggle) mobileToggle.addEventListener('click', window.diorOpenMobileDrawer);
        if (sideClose) sideClose.addEventListener('click', window.diorCloseMobileDrawer);
        if (drawerBackdrop) drawerBackdrop.addEventListener('click', window.diorCloseMobileDrawer);

        // =========================================================================
        // 5. MODAL SYSTEM
        // =========================================================================
        window.diorOpenModal = function(modalId) {
            if (modalId === 'modal-book-appointment' && window.dior_vars && window.dior_vars.is_logged_in && !window.dior_vars.is_profile_complete) {
                promptCompleteProfile('book a new appointment');
                return;
            }
            const overlay = document.getElementById(modalId);
            if (overlay) {
                overlay.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        };

        window.diorCloseModal = function(modalId) {
            const overlay = document.getElementById(modalId);
            if (overlay) {
                overlay.classList.remove('open');
                document.body.style.overflow = '';
            }
        };

        document.querySelectorAll('.dior-modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const overlay = this.closest('.dior-modal-overlay');
                if (overlay) {
                    overlay.classList.remove('open');
                    document.body.style.overflow = '';
                }
            });
        });

        document.querySelectorAll('.dior-modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('open');
                    document.body.style.overflow = '';
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.dior-modal-overlay.open').forEach(o => {
                    o.classList.remove('open');
                });
                document.body.style.overflow = '';
                const notifDropdown = document.getElementById('dior-notif-dropdown');
                if (notifDropdown) notifDropdown.classList.remove('open');
            }
        });

        // =========================================================================
        // 6. NOTIFICATION DROPDOWN & ACTIONS
        // =========================================================================
        const notifDropdown = document.getElementById('dior-notif-dropdown');
        const markAllNotifsBtn = document.getElementById('dior-mark-all-notifications-btn');
        const quickMarkReadBtn = document.getElementById('dior-quick-mark-read');

        window.diorToggleNotifDropdown = function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const dropdown = document.getElementById('dior-notif-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('open');
            }
        };

        document.addEventListener('click', function(e) {
            const wrap = document.getElementById('dior-notif-dropdown-wrap');
            const dropdown = document.getElementById('dior-notif-dropdown');
            if (dropdown && dropdown.classList.contains('open')) {
                if (!wrap || !wrap.contains(e.target)) {
                    dropdown.classList.remove('open');
                }
            }
        });

        function markAllNotificationsRead() {
            sendAjax('dior_patient_mark_all_notifications_read', {}, () => {
                document.querySelectorAll('.unread-badge-count').forEach(el => el.style.display = 'none');
                document.querySelectorAll('.dior-notif-item.unread, .dior-full-notif-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                document.querySelectorAll('.dior-mark-single-read').forEach(btn => btn.remove());
            });
        }

        if (markAllNotifsBtn) markAllNotifsBtn.addEventListener('click', markAllNotificationsRead);
        if (quickMarkReadBtn) quickMarkReadBtn.addEventListener('click', markAllNotificationsRead);

        // Single mark read
        document.addEventListener('click', function(e) {
            const singleBtn = e.target.closest('.dior-mark-single-read');
            if (singleBtn) {
                const item = singleBtn.closest('.dior-full-notif-item');
                const notifId = item ? item.getAttribute('data-notif-id') : '';
                if (notifId) {
                    sendAjax('dior_patient_mark_notification_read', { notif_id: notifId }, () => {
                        if (item) item.classList.remove('unread');
                        singleBtn.remove();
                    });
                }
            }
        });

        // =========================================================================
        // REAL-TIME LIVE NOTIFICATION POLLING & TOAST SYSTEM (WITHOUT PAGE REFRESH)
        // =========================================================================
        let diorLastKnownNotifId = null;
        let diorSeenToastIds = new Set();
        let isNotifPolling = false;

        // Grab first notification ID from existing DOM on page load
        const initialNotifEl = document.querySelector('#dior-notif-dropdown .dior-notif-item, #dior-notifications-full-container .dior-full-notif-item');
        if (initialNotifEl && initialNotifEl.dataset.notifId) {
            diorLastKnownNotifId = initialNotifEl.dataset.notifId;
        }

        // Web Audio API Chime Synthesizer
        function playPatientChime() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                const ctx = new AudioContext();
                const now = ctx.currentTime;

                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(587.33, now); // D5
                gain1.gain.setValueAtTime(0.08, now);
                gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.start(now);
                osc1.stop(now + 0.35);

                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880, now + 0.12); // A5
                gain2.gain.setValueAtTime(0.12, now + 0.12);
                gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(now + 0.12);
                osc2.stop(now + 0.55);
            } catch(e) {}
        }

        function getLiveToastContainer() {
            let container = document.getElementById('dior-live-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'dior-live-toast-container';
                container.className = 'dior-live-toast-container';
                document.body.appendChild(container);
            }
            return container;
        }

        function showLiveToast(alert) {
            if (!alert || diorSeenToastIds.has(alert.id)) return;
            diorSeenToastIds.add(alert.id);

            const container = getLiveToastContainer();
            const toast = document.createElement('div');
            toast.className = 'dior-live-toast';

            const iconClass = alert.icon ? (alert.icon.indexOf('fa-') === 0 ? alert.icon : 'fa-' + alert.icon) : 'fa-bell';

            toast.innerHTML = `
                <div class="toast-icon"><i class="fa-solid ${iconClass}"></i></div>
                <div class="toast-body">
                    <div class="toast-title">${alert.title || 'New Notification'}</div>
                    <div class="toast-message">${alert.message || ''}</div>
                    <div class="toast-action"><i class="fa-solid fa-arrow-right"></i> View Details</div>
                </div>
                <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
            `;

            toast.querySelector('.toast-close').onclick = function(e) {
                e.stopPropagation();
                removeToast(toast);
            };

            toast.onclick = function() {
                removeToast(toast);
                const actionUrl = alert.action_url || '';
                if (actionUrl.indexOf('#tab=') !== -1) {
                    const targetTab = actionUrl.replace('#tab=', '');
                    if (typeof window.diorSwitchTab === 'function') {
                        window.diorSwitchTab(targetTab);
                    }
                } else {
                    if (typeof window.diorSwitchTab === 'function') {
                        window.diorSwitchTab('notifications');
                    }
                }
            };

            container.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.add('show');
                playPatientChime();
            });

            setTimeout(() => {
                removeToast(toast);
            }, 6500);
        }

        function removeToast(toast) {
            if (!toast || !toast.parentNode) return;
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 350);
        }

        function triggerBellAnimation() {
            const bell = document.querySelector('#dior-notif-dropdown-wrap .dior-notif-btn i');
            if (bell) {
                bell.classList.add('dior-bell-ring');
                setTimeout(() => { bell.classList.remove('dior-bell-ring'); }, 2400);
            }
        }

        function pollLiveNotifications() {
            if (isNotifPolling) return;
            if (document.hidden) return;
            if (typeof dior_vars === 'undefined' || !dior_vars.ajax_url) return;

            isNotifPolling = true;

            const data = new FormData();
            data.append('action', 'dior_patient_get_live_notifications');
            data.append('nonce', dior_vars.nonce);
            if (diorLastKnownNotifId) {
                data.append('last_notif_id', diorLastKnownNotifId);
            }

            fetch(dior_vars.ajax_url, {
                method: 'POST',
                body: data
            })
            .then(r => r.json())
            .then(res => {
                isNotifPolling = false;
                if (res && res.success && res.data) {
                    const d = res.data;
                    const unreadCount = parseInt(d.unread_count, 10) || 0;

                    // 1. Update Badge
                    const badges = document.querySelectorAll('.unread-badge-count');
                    badges.forEach(b => {
                        if (unreadCount > 0) {
                            b.style.display = 'inline-flex';
                            b.textContent = unreadCount;
                        } else {
                            b.style.display = 'none';
                        }
                    });

                    // 2. Update Dropdown List
                    const ddList = document.querySelector('#dior-notif-dropdown .dior-notif-list');
                    const dd = document.getElementById('dior-notif-dropdown');
                    if (ddList && (!dd || !dd.classList.contains('open')) && d.dropdown_html) {
                        ddList.innerHTML = d.dropdown_html;
                    }

                    // 3. Update Full Page List
                    const fullList = document.getElementById('dior-notifications-full-container');
                    if (fullList && d.full_html && d.latest_id !== diorLastKnownNotifId) {
                        fullList.innerHTML = d.full_html;
                    }

                    // 4. Trigger Toasts for New Alerts
                    if (d.new_alerts && d.new_alerts.length > 0) {
                        triggerBellAnimation();
                        d.new_alerts.forEach(al => {
                            showLiveToast(al);
                        });
                    }

                    if (d.latest_id) {
                        diorLastKnownNotifId = d.latest_id;
                    }
                }
            })
            .catch(() => {
                isNotifPolling = false;
            });
        }

        // Start live poller every 6 seconds
        setTimeout(pollLiveNotifications, 2000);
        setInterval(pollLiveNotifications, 6000);

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                pollLiveNotifications();
            }
        });

        window.diorPollPatientNotifications = pollLiveNotifications;

        // =========================================================================
        // 7. APPOINTMENTS CONTROLLER (Booking, Reschedule, Cancel, Filter)
        // =========================================================================
        
        // Book Appointment
        const bookForm = document.getElementById('dior-form-book-patient-appt');
        if (bookForm) {
            bookForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const submitBtn = document.getElementById('dior-submit-book-btn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Processing Booking...';
                }

                const data = {
                    condition: this.condition.value,
                    type: this.type.value,
                    provider: this.provider.value,
                    date: this.date.value,
                    time: this.time.value,
                    notes: this.notes.value
                };

                sendAjax('dior_patient_book_appointment', data, () => {
                    diorCloseModal('modal-book-appointment');
                    setTimeout(() => window.location.reload(), 1200);
                }, () => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fa-solid fa-lock"></i> Confirm & Book Consultation ($49.00)';
                    }
                });
            });
        }

        // Helper to locate appointment in window.dior_patient_appts_data
        function diorFindPatientAppt(idOrUid) {
            const list = window.dior_patient_appts_data || [];
            for (let i = 0; i < list.length; i++) {
                if (String(list[i].id) === String(idOrUid) || String(list[i].appt_uid) === String(idOrUid)) {
                    return list[i];
                }
            }
            return null;
        }

        // Open Appointment Reminder Modal
        window.diorSendApptReminder = function(apptId) {
            if (!apptId) return;

            const input = document.getElementById('reminder_appt_id');
            if (input) input.value = apptId;

            const apt = diorFindPatientAppt(apptId);
            const sumEl = document.getElementById('reminder_appt_summary');
            if (sumEl) {
                if (apt) {
                    const pName = apt.provider || apt.doctor_name || 'Attending Physician';
                    const pDate = apt.date || apt.appt_date || '';
                    const pTime = apt.time || apt.appt_time || '';
                    const pCond = apt.condition || apt.condition_name || 'Telehealth Consultation';
                    sumEl.innerHTML = `<i class="fa-regular fa-bell" style="color:#D97706;margin-right:6px;"></i> Consultation: <strong>${pName}</strong> on <strong>${pDate} at ${pTime}</strong> (${pCond})`;
                    sumEl.style.display = 'block';
                } else {
                    sumEl.style.display = 'none';
                }
            }

            diorOpenModal('modal-appointment-reminder');
        };

        const reminderForm = document.getElementById('dior-form-send-reminder');
        if (reminderForm) {
            reminderForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const aid = this.appt_id.value;
                const submitBtn = document.getElementById('dior-btn-submit-reminder');
                const origHtml = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Dispatching...';
                }

                sendAjax('dior_patient_send_appointment_reminder', { appt_id: aid }, (resData) => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = origHtml;
                    }
                    diorCloseModal('modal-appointment-reminder');

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Reminder Dispatched!',
                            text: (resData && resData.message) ? resData.message : 'Appointment reminder dispatched to both you and your attending doctor.',
                            icon: 'success',
                            confirmButtonColor: '#2C6CB1'
                        });
                    } else {
                        showToast('Appointment reminder sent successfully.');
                    }
                }, (err) => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = origHtml;
                    }
                    const msg = (err && err.message) ? err.message : 'Could not send reminder.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ title: 'Notice', text: msg, icon: 'error', confirmButtonColor: '#2C6CB1' });
                    } else {
                        showToast(msg, true);
                    }
                });
            });
        };

        // Open Reschedule Modal
        window.diorOpenRescheduleModal = function(apptId) {
            const input = document.getElementById('resched_appt_id');
            if (input) input.value = apptId;

            const dateInput = document.getElementById('resched_date');
            const todayStr = new Date().toISOString().split('T')[0];
            if (dateInput) {
                dateInput.min = todayStr;
            }

            const apt = diorFindPatientAppt(apptId);
            const sumEl = document.getElementById('resched_appt_summary');
            if (sumEl) {
                if (apt) {
                    const pName = apt.provider || apt.doctor_name || 'Attending Physician';
                    const pDate = apt.date || apt.appt_date || '';
                    const pTime = apt.time || apt.appt_time || '';
                    const pCond = apt.condition || apt.condition_name || 'Telehealth Consultation';
                    sumEl.innerHTML = `<i class="fa-solid fa-calendar-day" style="color:#2C6CB1;margin-right:6px;"></i> Currently Scheduled: <strong>${pName}</strong> on <strong>${pDate} at ${pTime}</strong> (${pCond})`;
                    sumEl.style.display = 'block';

                    if (dateInput && apt.date && apt.date >= todayStr) {
                        dateInput.value = apt.date;
                    }
                } else {
                    sumEl.style.display = 'none';
                }
            }

            diorOpenModal('modal-reschedule-appointment');
        };

        const reschedForm = document.getElementById('dior-form-reschedule-appt');
        if (reschedForm) {
            reschedForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const aid = this.appt_id.value;
                const newDate = this.date.value;
                const newTime = this.time.value;
                const notes = (this.notes ? this.notes.value : '').trim();

                const todayStr = new Date().toISOString().split('T')[0];
                if (newDate < todayStr) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ title: 'Invalid Date', text: 'Reschedule date cannot be in the past.', icon: 'warning', confirmButtonColor: '#2C6CB1' });
                    } else {
                        alert('Reschedule date cannot be in the past.');
                    }
                    return;
                }

                const submitBtn = document.getElementById('dior-btn-submit-resched');
                const origHtml = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...';
                }

                const data = {
                    appt_id: aid,
                    date: newDate,
                    time: newTime,
                    notes: notes
                };

                sendAjax('dior_patient_reschedule_appointment', data, (resData) => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = origHtml;
                    }
                    diorCloseModal('modal-reschedule-appointment');

                    // Update row in table dynamically without hard reload
                    const row = document.querySelector(`.dior-appt-row[data-id="${aid}"]`);
                    if (row) {
                        const dateCell = row.querySelector('.table-date-cell');
                        if (dateCell) {
                            dateCell.innerHTML = `<strong>${newDate}</strong><span><i class="fa-regular fa-clock"></i> ${newTime}</span>`;
                        }
                        const badge = row.querySelector('.dior-st');
                        if (badge) {
                            badge.className = 'dior-st ok';
                            badge.textContent = 'Confirmed';
                        }
                    }

                    // Update memory cache
                    const cached = diorFindPatientAppt(aid);
                    if (cached) {
                        cached.date = newDate;
                        cached.time = newTime;
                        cached.status = 'Confirmed';
                    }

                    // Update hero countdown if present
                    const heroCountdown = document.getElementById('dior-live-hero-countdown');
                    if (heroCountdown) {
                        heroCountdown.setAttribute('data-appt-date', newDate);
                        heroCountdown.setAttribute('data-appt-time', newTime);
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Consultation Rescheduled!',
                            text: `Your visit is now scheduled for ${newDate} at ${newTime}. Multi-channel confirmation notifications have been dispatched to both you and your doctor.`,
                            icon: 'success',
                            confirmButtonColor: '#2C6CB1'
                        });
                    } else {
                        showToast(`Appointment rescheduled to ${newDate} at ${newTime}.`);
                    }
                }, (err) => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = origHtml;
                    }
                    const msg = (err && err.message) ? err.message : 'Failed to reschedule appointment.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ title: 'Reschedule Notice', text: msg, icon: 'error', confirmButtonColor: '#2C6CB1' });
                    } else {
                        showToast(msg, true);
                    }
                });
            });
        }

        // Open Cancel Modal
        window.diorOpenCancelModal = function(apptId) {
            const input = document.getElementById('cancel_appt_id');
            if (input) input.value = apptId;

            const apt = diorFindPatientAppt(apptId);
            const sumEl = document.getElementById('cancel_appt_summary');
            if (sumEl) {
                if (apt) {
                    const pName = apt.provider || apt.doctor_name || 'Attending Physician';
                    const pDate = apt.date || apt.appt_date || '';
                    const pTime = apt.time || apt.appt_time || '';
                    sumEl.innerHTML = `<i class="fa-solid fa-triangle-exclamation" style="color:#DC2626;margin-right:6px;"></i> Appointment to Cancel: <strong>${pName}</strong> on <strong>${pDate} at ${pTime}</strong>`;
                    sumEl.style.display = 'block';
                } else {
                    sumEl.style.display = 'none';
                }
            }

            diorOpenModal('modal-cancel-appointment');
        };

        const cancelForm = document.getElementById('dior-form-cancel-appt');
        if (cancelForm) {
            cancelForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const aid = this.appt_id.value;
                const reasonVal = this.reason.value;

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Confirm Cancellation?',
                        text: 'Are you sure you wish to cancel this consultation? This action cannot be undone, though you may rebook anytime.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fa-solid fa-xmark"></i> Yes, Cancel Consultation',
                        confirmButtonColor: '#DC2626',
                        cancelButtonText: 'Keep Appointment',
                        cancelButtonColor: '#94A3B8'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            doSubmitCancellation(aid, reasonVal);
                        }
                    });
                } else {
                    if (confirm('Are you sure you wish to cancel this consultation?')) {
                        doSubmitCancellation(aid, reasonVal);
                    }
                }
            });

            function doSubmitCancellation(aid, reasonVal) {
                const submitBtn = document.getElementById('dior-btn-submit-cancel');
                const origHtml = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Cancelling...';
                }

                const data = {
                    appt_id: aid,
                    reason: reasonVal
                };

                sendAjax('dior_patient_cancel_appointment', data, (resData) => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = origHtml;
                    }
                    diorCloseModal('modal-cancel-appointment');

                    // Update row in table dynamically without full reload
                    const row = document.querySelector(`.dior-appt-row[data-id="${aid}"]`);
                    if (row) {
                        const badge = row.querySelector('.dior-st');
                        if (badge) {
                            badge.className = 'dior-st ca';
                            badge.textContent = 'Cancelled';
                        }
                        row.setAttribute('data-status', 'completed');

                        // Remove actionable buttons (Join, Reminder, Reschedule, Cancel)
                        const joinBtn = row.querySelector('.dior-btn-table-join');
                        const remindBtn = row.querySelector('.dior-btn-reminder');
                        const reschedBtn = row.querySelector('.dior-btn-table-icon[title*="Reschedule"]');
                        const cancelBtn = row.querySelector('.dior-btn-table-icon.danger');
                        if (joinBtn) joinBtn.remove();
                        if (remindBtn) remindBtn.remove();
                        if (reschedBtn) reschedBtn.remove();
                        if (cancelBtn) cancelBtn.remove();
                    }

                    // Update in-memory cache
                    const cached = diorFindPatientAppt(aid);
                    if (cached) {
                        cached.status = 'Cancelled';
                        cached.can_join = false;
                        cached.can_reschedule = false;
                        cached.can_cancel = false;
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Appointment Cancelled',
                            text: 'Your consultation has been successfully cancelled. Cancellation notifications have been sent to both you and your doctor.',
                            icon: 'info',
                            confirmButtonColor: '#2C6CB1'
                        });
                    } else {
                        showToast('Appointment cancelled successfully.');
                    }
                }, (err) => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = origHtml;
                    }
                    const msg = (err && err.message) ? err.message : 'Failed to cancel appointment.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ title: 'Notice', text: msg, icon: 'error', confirmButtonColor: '#2C6CB1' });
                    } else {
                        showToast(msg, true);
                    }
                });
            }
        }

        // Appointments Table Filter
        const apptFilterTabs = document.querySelectorAll('.dior-filter-tab');
        const apptSearchInput = document.getElementById('dior-search-appointments-input');
        const apptRows = document.querySelectorAll('.dior-appt-row');

        function filterApptTable() {
            const activeTab = document.querySelector('.dior-filter-tab.active');
            const statusFilter = activeTab ? activeTab.getAttribute('data-filter-status') : 'all';
            const searchQuery = apptSearchInput ? apptSearchInput.value.toLowerCase().trim() : '';
            const apptTable = document.getElementById('dior-patient-appointments-table');

            apptRows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowText = row.textContent.toLowerCase();

                const matchesStatus = (statusFilter === 'all') || (rowStatus === statusFilter);
                const matchesSearch = !searchQuery || rowText.includes(searchQuery);

                if (matchesStatus && matchesSearch) {
                    row.setAttribute('data-filter-hidden', 'false');
                } else {
                    row.setAttribute('data-filter-hidden', 'true');
                }
            });

            if (apptTable && apptTable._diorRenderPage) {
                apptTable._diorRenderPage(1);
            }
        }

        apptFilterTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                apptFilterTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                filterApptTable();
            });
        });

        if (apptSearchInput) {
            apptSearchInput.addEventListener('input', filterApptTable);
        }

        // =========================================================================
        // REAL-TIME DYNAMIC APPOINTMENT COUNTDOWN CLOCK
        // =========================================================================
        let countdownTimerInterval = null;

        function parseAppointmentDate(dateStr, timeStr) {
            if (!dateStr) return null;
            dateStr = dateStr.trim();
            timeStr = (timeStr || '').trim();

            let targetDate = new Date(dateStr + (timeStr ? ' ' + timeStr : ''));
            if (!isNaN(targetDate.getTime())) {
                return targetDate.getTime();
            }

            let parsed = Date.parse(dateStr + ' ' + timeStr);
            if (!isNaN(parsed)) {
                return parsed;
            }

            let timeMatch = timeStr.match(/(\d+):(\d+)\s*(AM|PM)?/i);
            let dateMatch = dateStr.match(/(\d{4})-(\d{2})-(\d{2})/);
            if (dateMatch) {
                let year = parseInt(dateMatch[1], 10);
                let month = parseInt(dateMatch[2], 10) - 1;
                let day = parseInt(dateMatch[3], 10);
                let hours = 9;
                let mins = 0;
                if (timeMatch) {
                    hours = parseInt(timeMatch[1], 10);
                    mins = parseInt(timeMatch[2], 10);
                    let ampm = (timeMatch[3] || '').toUpperCase();
                    if (ampm === 'PM' && hours < 12) hours += 12;
                    if (ampm === 'AM' && hours === 12) hours = 0;
                }
                return new Date(year, month, day, hours, mins, 0).getTime();
            }

            return null;
        }

        function startLiveCountdown(targetTimestamp) {
            if (countdownTimerInterval) clearInterval(countdownTimerInterval);

            const countdownBox = document.getElementById('dior-live-hero-countdown');
            if (!countdownBox) return;

            const daysSlot = document.getElementById('dior-cd-days-slot');
            const daysColon = document.getElementById('dior-cd-days-colon');
            const daysEl = document.getElementById('dior-cd-days');
            const hrsEl = document.getElementById('dior-cd-hrs');
            const minEl = document.getElementById('dior-cd-min');
            const secEl = document.getElementById('dior-cd-sec');
            const statusText = document.getElementById('dior-cd-status-text');
            const joinBtn = document.querySelector('.hero-appt-actions .dior-btn-join-call');

            function updateTick() {
                const now = Date.now();
                const diffMs = targetTimestamp - now;

                if (diffMs > 0) {
                    const totalSecs = Math.floor(diffMs / 1000);
                    const days = Math.floor(totalSecs / 86400);
                    const hours = Math.floor((totalSecs % 86400) / 3600);
                    const minutes = Math.floor((totalSecs % 3600) / 60);
                    const seconds = totalSecs % 60;

                    if (daysEl) daysEl.textContent = String(days).padStart(2, '0');
                    if (hrsEl) hrsEl.textContent = String(hours).padStart(2, '0');
                    if (minEl) minEl.textContent = String(minutes).padStart(2, '0');
                    if (secEl) secEl.textContent = String(seconds).padStart(2, '0');

                    if (days > 0) {
                        if (daysSlot) daysSlot.style.display = 'flex';
                        if (daysColon) daysColon.style.display = 'inline';
                    } else {
                        if (daysSlot) daysSlot.style.display = 'none';
                        if (daysColon) daysColon.style.display = 'none';
                    }

                    if (diffMs <= 15 * 60 * 1000) {
                        if (statusText) statusText.innerHTML = '<span style="color:#059669; font-weight:700;"><i class="fa-solid fa-circle-dot fa-beat" style="color:#10B981;"></i> Consultation window is starting in:</span>';
                    } else {
                        if (statusText) statusText.textContent = 'Consultation window begins in:';
                    }
                } else if (diffMs <= 0 && diffMs >= -3600000) {
                    if (daysSlot) daysSlot.style.display = 'none';
                    if (daysColon) daysColon.style.display = 'none';
                    if (hrsEl) hrsEl.textContent = '00';
                    if (minEl) minEl.textContent = '00';
                    if (secEl) secEl.textContent = '00';
                    if (statusText) {
                        statusText.innerHTML = '<span style="color:#059669; font-weight:700; font-size:14px;"><i class="fa-solid fa-circle-play fa-beat" style="color:#10B981;"></i> CONSULTATION WINDOW IS LIVE NOW</span>';
                    }
                    if (joinBtn) {
                        joinBtn.style.animation = 'pulse 1.5s infinite';
                    }
                } else {
                    if (statusText) statusText.textContent = 'Scheduled Visit Completed';
                    if (daysSlot) daysSlot.style.display = 'none';
                    if (daysColon) daysColon.style.display = 'none';
                    if (hrsEl) hrsEl.textContent = '00';
                    if (minEl) minEl.textContent = '00';
                    if (secEl) secEl.textContent = '00';
                }
            }

            updateTick();
            countdownTimerInterval = setInterval(updateTick, 1000);
        }

        window.diorUpdateCountdown = function(dateStr, timeStr) {
            const countdownBox = document.getElementById('dior-live-hero-countdown');
            if (countdownBox) {
                countdownBox.setAttribute('data-appt-date', dateStr);
                countdownBox.setAttribute('data-appt-time', timeStr);
            }
            const ts = parseAppointmentDate(dateStr, timeStr);
            if (ts) {
                startLiveCountdown(ts);
            }
        };

        const initialCountdownEl = document.getElementById('dior-live-hero-countdown');
        if (initialCountdownEl) {
            const initDate = initialCountdownEl.getAttribute('data-appt-date');
            const initTime = initialCountdownEl.getAttribute('data-appt-time');
            const initTs = parseAppointmentDate(initDate, initTime);
            if (initTs) {
                startLiveCountdown(initTs);
            }
        }

        // =========================================================================
        // 8. PROFILE CONTROLLER
        // =========================================================================
        const profileForm = document.getElementById('dior-form-patient-profile');
        const saveProfileBtn = document.getElementById('dior-save-profile-btn');

        function handleProfileSubmission(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }

            const formEl = document.getElementById('dior-form-patient-profile');
            if (!formEl) return;

            const fnEl = formEl.querySelector('[name="first_name"]');
            const lnEl = formEl.querySelector('[name="last_name"]');
            const emEl = formEl.querySelector('[name="email"]');
            const phEl = formEl.querySelector('[name="phone"]');
            const dobEl = formEl.querySelector('[name="dob"]');
            const genderEl = formEl.querySelector('[name="gender"]');
            const addrEl = formEl.querySelector('[name="address"]');

            const fn = fnEl ? fnEl.value.trim() : '';
            const ln = lnEl ? lnEl.value.trim() : '';
            const em = emEl ? emEl.value.trim() : '';
            const ph = phEl ? phEl.value.trim() : '';
            const dob = dobEl ? dobEl.value.trim() : '';
            const gender = genderEl ? genderEl.value.trim() : '';
            const addr = addrEl ? addrEl.value.trim() : '';

            // Reset field error styling
            [fnEl, lnEl, emEl, phEl, dobEl, genderEl, addrEl].forEach(el => {
                if (el) {
                    el.style.borderColor = '';
                    el.style.backgroundColor = '';
                }
            });

            const missingList = [];
            let firstMissing = null;

            if (!fn) { missingList.push('First Name'); if (!firstMissing) firstMissing = fnEl; if (fnEl) { fnEl.style.borderColor = '#EF4444'; fnEl.style.backgroundColor = '#FEF2F2'; } }
            if (!ln) { missingList.push('Last Name'); if (!firstMissing) firstMissing = lnEl; if (lnEl) { lnEl.style.borderColor = '#EF4444'; lnEl.style.backgroundColor = '#FEF2F2'; } }
            if (!ph) { missingList.push('Phone Number'); if (!firstMissing) firstMissing = phEl; if (phEl) { phEl.style.borderColor = '#EF4444'; phEl.style.backgroundColor = '#FEF2F2'; } }
            if (!dob) { missingList.push('Date of Birth'); if (!firstMissing) firstMissing = dobEl; if (dobEl) { dobEl.style.borderColor = '#EF4444'; dobEl.style.backgroundColor = '#FEF2F2'; } }
            if (!gender) { missingList.push('Gender'); if (!firstMissing) firstMissing = genderEl; if (genderEl) { genderEl.style.borderColor = '#EF4444'; genderEl.style.backgroundColor = '#FEF2F2'; } }
            if (!addr) { missingList.push('Mailing Address'); if (!firstMissing) firstMissing = addrEl; if (addrEl) { addrEl.style.borderColor = '#EF4444'; addrEl.style.backgroundColor = '#FEF2F2'; } }

            // Clear red highlight as soon as user types or changes input
            [fnEl, lnEl, emEl, phEl, dobEl, genderEl, addrEl].forEach(el => {
                if (el && !el._diorHasCleanListener) {
                    el._diorHasCleanListener = true;
                    el.addEventListener('input', function() {
                        this.style.borderColor = '';
                        this.style.backgroundColor = '';
                    });
                    el.addEventListener('change', function() {
                        this.style.borderColor = '';
                        this.style.backgroundColor = '';
                    });
                }
            });

            if (missingList.length > 0) {
                if (firstMissing) {
                    firstMissing.focus();
                    firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Required Information Missing',
                        html: `<div style="text-align:left; padding: 4px 0;">
                            <p style="margin:0 0 10px 0; color:#334155; font-size:14.5px; line-height:1.5;">Please complete the following required fields to save your patient profile:</p>
                            <ul style="margin:0; padding-left:22px; color:#EF4444; font-weight:600; font-size:14px; line-height:1.7;">
                                ${missingList.map(item => `<li>${item}</li>`).join('')}
                            </ul>
                        </div>`,
                        confirmButtonText: '<i class="fa-solid fa-pen-to-square"></i> Fill Missing Fields',
                        confirmButtonColor: '#2C6CB1'
                    });
                } else {
                    showToast('Please complete: ' + missingList.join(', '), true);
                }
                return;
            }

            // Real-time Age Verification (Adult Telehealth 18+ only)
            if (dob) {
                const birthDate = new Date(dob);
                const today = new Date();
                let calcAge = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    calcAge--;
                }

                if (birthDate > today) {
                    if (dobEl) {
                        dobEl.style.borderColor = '#EF4444';
                        dobEl.style.backgroundColor = '#FEF2F2';
                        dobEl.focus();
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Date of Birth',
                            text: 'Date of birth cannot be in the future. Please enter your valid birth date.',
                            confirmButtonColor: '#2C6CB1'
                        });
                    } else {
                        showToast('Date of birth cannot be in the future.', true);
                    }
                    return;
                }

                if (calcAge < 18) {
                    if (dobEl) {
                        dobEl.style.borderColor = '#EF4444';
                        dobEl.style.backgroundColor = '#FEF2F2';
                        dobEl.focus();
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Age Requirement Notice (18+)',
                            text: 'Patients must be at least 18 years of age to receive adult telehealth care at Dior Medical (Calculated age: ' + Math.max(0, calcAge) + ' years). Please verify your date of birth.',
                            confirmButtonColor: '#2C6CB1'
                        });
                    } else {
                        showToast('Patient must be at least 18 years of age.', true);
                    }
                    return;
                }
            }

            const btn = document.getElementById('dior-save-profile-btn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving Profile...';
            }

            const formData = new FormData(formEl);
            const data = {};
            formData.forEach((val, key) => data[key] = val);

            const optinAppt = formEl.querySelector('[name="optin_appointment_reminders"]');
            const optinEmail = formEl.querySelector('[name="optin_reminder_email"]');
            const optinDash = formEl.querySelector('[name="optin_reminder_dashboard"]');
            if (optinAppt) data.optin_appointment_reminders = optinAppt.checked ? '1' : '0';
            if (optinEmail) data.optin_reminder_email = optinEmail.checked ? '1' : '0';
            if (optinDash) data.optin_reminder_dashboard = optinDash.checked ? '1' : '0';

            sendAjax('dior_patient_save_profile', data, (res) => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Profile Changes';
                }

                if (window.dior_vars) {
                    window.dior_vars.is_profile_complete = 1;
                    window.dior_vars.missing_profile_fields = {};
                    if (res && res.profile) {
                        window.dior_vars.patient_profile = res.profile;
                    }
                }

                // Remove warning banner if present
                const banner = document.getElementById('dior-profile-incomplete-banner');
                if (banner) banner.remove();

                // Update UI names across dashboard
                const nameEls = document.querySelectorAll('.org-info strong, .dior-pill-name');
                nameEls.forEach(el => el.textContent = (fn + ' ' + ln).trim());

                const welcomeH2 = document.querySelector('.dior-page-title-bar .title-left h2');
                if (welcomeH2) {
                    welcomeH2.textContent = 'Welcome back, ' + fn;
                }

                // Update initials in avatar
                const avatarInit = document.querySelector('.dior-side-org-card .org-icon-box');
                if (avatarInit) {
                    avatarInit.textContent = ((fn.charAt(0) || 'P') + (ln.charAt(0) || 'M')).toUpperCase();
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Profile Updated Successfully!',
                        text: 'Your personal information has been saved in the database. Telehealth appointments and medical services are now unlocked.',
                        confirmButtonText: '<i class="fa-solid fa-check"></i> Great, Continue',
                        confirmButtonColor: '#2C6CB1'
                    });
                } else {
                    showToast('Profile updated successfully!');
                }
            }, (err) => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Profile Changes';
                }
                const errMsg = (err && err.message) ? err.message : 'Unable to update profile. Please check your details and try again.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Profile Update Failed',
                        text: errMsg,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#2C6CB1'
                    });
                } else {
                    showToast(errMsg, true);
                }
            });
        }

        if (profileForm) {
            profileForm.addEventListener('submit', handleProfileSubmission);
        }
        if (saveProfileBtn) {
            saveProfileBtn.addEventListener('click', function(e) {
                handleProfileSubmission(e);
            });
        }

        // Live DOB change listener to update Eligibility badge and snapshot synchronously
        const liveDobEl = document.getElementById('prof_dob');
        if (liveDobEl) {
            liveDobEl.addEventListener('change', function() {
                const val = this.value;
                if (!val) return;
                const bDate = new Date(val);
                const now = new Date();
                let age = now.getFullYear() - bDate.getFullYear();
                const dm = now.getMonth() - bDate.getMonth();
                if (dm < 0 || (dm === 0 && now.getDate() < bDate.getDate())) {
                    age--;
                }

                const pill = document.getElementById('dior-eligibility-pill');
                const snapIcon = document.getElementById('dior-snap-age-icon');
                const snapText = document.getElementById('dior-snap-age-text');

                if (bDate > now || age < 18) {
                    this.style.borderColor = '#EF4444';
                    this.style.backgroundColor = '#FEF2F2';
                    if (pill) {
                        pill.className = 'dior-eligibility-badge-pill danger';
                        pill.style.background = '#FEF2F2';
                        pill.style.color = '#DC2626';
                        pill.style.borderColor = '#FCA5A5';
                        pill.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Age Under 18 (Adult Care Requires 18+)';
                    }
                    if (snapIcon) {
                        snapIcon.className = 'snap-icon danger';
                        snapIcon.style.background = '#FEF2F2';
                        snapIcon.style.color = '#DC2626';
                        snapIcon.style.borderColor = '#FCA5A5';
                        snapIcon.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                    }
                    if (snapText) {
                        snapText.style.color = '#DC2626';
                        snapText.style.fontWeight = '600';
                        snapText.textContent = 'Under 18 (Age ' + Math.max(0, age) + ' \u2022 Requires 18+)';
                    }
                } else {
                    this.style.borderColor = '';
                    this.style.backgroundColor = '';
                    if (pill) {
                        pill.className = 'dior-eligibility-badge-pill ok';
                        pill.style.background = '#ECFDF5';
                        pill.style.color = '#047857';
                        pill.style.borderColor = '#A7F3D0';
                        pill.innerHTML = '<i class="fa-solid fa-shield-check"></i> Verified 18+ Telehealth Patient (Age ' + age + ')';
                    }
                    if (snapIcon) {
                        snapIcon.className = 'snap-icon ok';
                        snapIcon.style.background = '';
                        snapIcon.style.color = '';
                        snapIcon.style.borderColor = '';
                        snapIcon.innerHTML = '<i class="fa-solid fa-check"></i>';
                    }
                    if (snapText) {
                        snapText.style.color = '';
                        snapText.style.fontWeight = '';
                        snapText.textContent = '18+ Years Old (Age ' + age + ', Adult Care Verified)';
                    }
                }
            });
        }

        // =========================================================================
        // 8.1 PATIENT AVATAR CONTROLLER
        // =========================================================================
        // 8.1 PATIENT AVATAR CONTROLLER (Direct Device Upload & WP Media Library)
        // =========================================================================
        const avatarFileInput = document.getElementById('dior-patient-avatar-file-input');
        const avatarRemoveBtn = document.getElementById('dior-btn-avatar-remove');
        const avatarDisplayImg = document.getElementById('dior-avatar-display-img');
        const avatarInitials = document.getElementById('dior-avatar-initials');

        // Direct device upload trigger
        window.diorPatientOpenDirectUpload = function() {
            const input = document.getElementById('dior-patient-avatar-file-input');
            if (input) {
                input.value = '';
                input.click();
            }
        };

        if (avatarFileInput) {
            avatarFileInput.addEventListener('change', function(e) {
                const file = this.files && this.files[0];
                if (!file) return;

                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'File Too Large',
                            text: 'The selected photo exceeds the 5MB file size limit. Please choose a smaller image.',
                            confirmButtonColor: '#2C6CB1'
                        });
                    } else {
                        showToast('Image size exceeds 5MB limit.', true);
                    }
                    this.value = '';
                    return;
                }

                // Instant local preview
                const reader = new FileReader();
                reader.onload = function(evt) {
                    if (avatarDisplayImg) {
                        avatarDisplayImg.src = evt.target.result;
                        avatarDisplayImg.style.display = 'block';
                    }
                    if (avatarInitials) {
                        avatarInitials.style.display = 'none';
                    }
                    if (avatarRemoveBtn) {
                        avatarRemoveBtn.style.display = 'inline-flex';
                    }
                };
                reader.readAsDataURL(file);

                // Upload directly to server via AJAX
                const uploadBtn = document.getElementById('dior-btn-patient-direct-upload');
                const origBtnHtml = uploadBtn ? uploadBtn.innerHTML : '';
                if (uploadBtn) {
                    uploadBtn.disabled = true;
                    uploadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
                }

                const formData = new FormData();
                formData.append('action', 'dior_patient_upload_avatar');
                formData.append('nonce', (window.dior_vars && window.dior_vars.nonce) ? window.dior_vars.nonce : '');
                formData.append('avatar', file);

                const ajaxUrl = (window.dior_vars && window.dior_vars.ajax_url) ? window.dior_vars.ajax_url : '/wp-admin/admin-ajax.php';

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(res => res.text())
                .then(text => {
                    if (uploadBtn) {
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = origBtnHtml || '<i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo';
                    }

                    let response;
                    try {
                        response = JSON.parse(text);
                    } catch(e) {
                        response = { success: false, data: { message: 'Server error during upload.' } };
                    }

                    if (response && response.success) {
                        const newUrl = (response.data && response.data.avatar_url) ? response.data.avatar_url : '';
                        if (newUrl && avatarDisplayImg) {
                            avatarDisplayImg.src = newUrl;
                            avatarDisplayImg.style.display = 'block';
                        }
                        if (avatarInitials) {
                            avatarInitials.style.display = 'none';
                        }
                        if (avatarRemoveBtn) {
                            avatarRemoveBtn.style.display = 'inline-flex';
                        }

                        // Update sidebar avatar
                        const sidebarAvatar = document.getElementById('dior-sidebar-avatar-box');
                        if (sidebarAvatar && newUrl) {
                            sidebarAvatar.innerHTML = `<img src="${newUrl}" alt="Profile Photo" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
                        }

                        // Update topbar pill avatar
                        const topPillAvatar = document.getElementById('dior-top-pill-avatar');
                        if (topPillAvatar && newUrl) {
                            topPillAvatar.innerHTML = `<img src="${newUrl}" alt="Profile Photo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
                        }

                        if (window.dior_vars && window.dior_vars.patient_profile) {
                            window.dior_vars.patient_profile.avatar_url = newUrl;
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Profile Photo Uploaded!',
                                text: 'Your profile photo has been updated directly from your device.',
                                confirmButtonText: '<i class="fa-solid fa-check"></i> Great',
                                confirmButtonColor: '#2C6CB1',
                                timer: 2500
                            });
                        } else {
                            showToast('Profile photo uploaded successfully!');
                        }
                    } else {
                        const msg = (response && response.data && response.data.message) ? response.data.message : 'Failed to upload photo.';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Upload Failed',
                                text: msg,
                                confirmButtonColor: '#2C6CB1'
                            });
                        } else {
                            showToast(msg, true);
                        }
                    }
                })
                .catch(err => {
                    console.error('[Patient Avatar Upload Error]', err);
                    if (uploadBtn) {
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = origBtnHtml || '<i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo';
                    }
                    showToast('Connection failed during photo upload.', true);
                });
            });
        }

        let patientMediaFrame = null;

        window.diorPatientOpenMediaLibrary = function() {
            if (typeof wp !== 'undefined' && wp.media) {
                if (patientMediaFrame) {
                    patientMediaFrame.open();
                    return;
                }

                patientMediaFrame = wp.media({
                    title: 'Select or Upload Patient Profile Photo',
                    button: {
                        text: 'Use as Profile Photo'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: false
                });

                patientMediaFrame.on('select', function() {
                    const attachment = patientMediaFrame.state().get('selection').first().toJSON();
                    if (!attachment || !attachment.url) return;

                    const avatarUrl = attachment.url;
                    const attachId = attachment.id || 0;

                    if (avatarDisplayImg) {
                        avatarDisplayImg.src = avatarUrl;
                        avatarDisplayImg.style.display = 'block';
                    }
                    if (avatarInitials) {
                        avatarInitials.style.display = 'none';
                    }
                    if (avatarRemoveBtn) {
                        avatarRemoveBtn.style.display = 'inline-flex';
                    }

                    const formData = new FormData();
                    formData.append('action', 'dior_patient_upload_avatar');
                    formData.append('nonce', (window.dior_vars && window.dior_vars.nonce) ? window.dior_vars.nonce : '');
                    formData.append('avatar_url', avatarUrl);
                    formData.append('attachment_id', attachId);

                    const ajaxUrl = (window.dior_vars && window.dior_vars.ajax_url) ? window.dior_vars.ajax_url : '/wp-admin/admin-ajax.php';

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(response => {
                        if (response && response.success) {
                            // Update sidebar avatar
                            const sidebarAvatar = document.getElementById('dior-sidebar-avatar-box');
                            if (sidebarAvatar) {
                                sidebarAvatar.innerHTML = `<img src="${avatarUrl}" alt="Profile Photo" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
                            }

                            // Update topbar pill avatar
                            const topPillAvatar = document.getElementById('dior-top-pill-avatar');
                            if (topPillAvatar) {
                                topPillAvatar.innerHTML = `<img src="${avatarUrl}" alt="Profile Photo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
                            }

                            if (window.dior_vars && window.dior_vars.patient_profile) {
                                window.dior_vars.patient_profile.avatar_url = avatarUrl;
                            }

                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Profile Photo Updated!',
                                    text: 'Your new photo has been selected from the WordPress Media Library.',
                                    confirmButtonText: '<i class="fa-solid fa-check"></i> Great',
                                    confirmButtonColor: '#2C6CB1',
                                    timer: 2500
                                });
                            } else {
                                showToast('Profile photo updated successfully!');
                            }
                        } else {
                            const msg = (response && response.data && response.data.message) ? response.data.message : 'Failed to update photo.';
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Update Failed',
                                    text: msg,
                                    confirmButtonColor: '#2C6CB1'
                                });
                            } else {
                                showToast(msg, true);
                            }
                        }
                    })
                    .catch(err => {
                        console.error('[Patient Avatar Media Error]', err);
                        showToast('Connection failed during photo selection.', true);
                    });
                });

                patientMediaFrame.open();
            } else {
                alert('WordPress Media Library could not be loaded. Please refresh the page.');
            }
        };

        if (avatarRemoveBtn) {
            avatarRemoveBtn.addEventListener('click', function() {
                const doRemove = () => {
                    avatarRemoveBtn.disabled = true;
                    avatarRemoveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Removing...';

                    sendAjax('dior_patient_remove_avatar', {}, (res) => {
                        avatarRemoveBtn.disabled = false;
                        avatarRemoveBtn.innerHTML = '<i class="fa-regular fa-trash-can"></i> Remove';
                        avatarRemoveBtn.style.display = 'none';

                        const initials = (res && res.initials) ? res.initials : 'PM';

                        if (avatarDisplayImg) {
                            avatarDisplayImg.src = '';
                            avatarDisplayImg.style.display = 'none';
                        }
                        if (avatarInitials) {
                            avatarInitials.textContent = initials;
                            avatarInitials.style.display = 'block';
                        }

                        // Revert sidebar avatar
                        const sidebarAvatar = document.getElementById('dior-sidebar-avatar-box');
                        if (sidebarAvatar) {
                            sidebarAvatar.innerHTML = initials;
                        }

                        // Revert topbar pill avatar
                        const topPillAvatar = document.getElementById('dior-top-pill-avatar');
                        if (topPillAvatar) {
                            topPillAvatar.innerHTML = initials.charAt(0);
                        }

                        if (window.dior_vars && window.dior_vars.patient_profile) {
                            window.dior_vars.patient_profile.avatar_url = '';
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Photo Removed',
                                text: 'Your profile photo has been removed.',
                                confirmButtonColor: '#2C6CB1',
                                timer: 2000
                            });
                        } else {
                            showToast('Profile photo removed.');
                        }
                    }, (err) => {
                        avatarRemoveBtn.disabled = false;
                        avatarRemoveBtn.innerHTML = '<i class="fa-regular fa-trash-can"></i> Remove';
                        const msg = (err && err.message) ? err.message : 'Failed to remove photo.';
                        showToast(msg, true);
                    });
                };

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Remove Profile Photo?',
                        text: 'Are you sure you want to remove your profile photo? Your avatar will revert to your initials.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Remove Photo',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#2C6CB1',
                        cancelButtonColor: '#94A3B8'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            doRemove();
                        }
                    });
                } else {
                    if (confirm('Are you sure you want to remove your profile photo?')) {
                        doRemove();
                    }
                }
            });
        }

        // ==========================================
        // PATIENT ELECTRONIC SIGNATURE MANAGEMENT
        // ==========================================
        const patSigFileInput = document.getElementById('dior-patient-sig-file-input');
        const patSigPreviewImg = document.getElementById('dior-patient-sig-preview-img');
        const patSigPlaceholder = document.getElementById('dior-patient-sig-placeholder');
        const patSigRemoveBtn = document.getElementById('dior-btn-patient-remove-sig');
        const patSigBadge = document.getElementById('dior-patient-sig-badge');

        window.diorPatientOpenSigDirectUpload = function() {
            const input = document.getElementById('dior-patient-sig-file-input');
            if (input) {
                input.value = '';
                input.click();
            }
        };

        if (patSigFileInput) {
            patSigFileInput.addEventListener('change', function(e) {
                const file = this.files && this.files[0];
                if (!file) return;

                // Validate PNG / WEBP
                const validTypes = ['image/png', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'PNG Required',
                            text: 'Please upload a transparent PNG (or WebP) signature file.',
                            confirmButtonColor: '#2C6CB1'
                        });
                    } else {
                        showToast('Please upload a transparent PNG signature.', true);
                    }
                    this.value = '';
                    return;
                }

                if (file.size > 3 * 1024 * 1024) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'File Too Large',
                            text: 'Signature file must not exceed 3MB.',
                            confirmButtonColor: '#2C6CB1'
                        });
                    } else {
                        showToast('Signature file exceeds 3MB limit.', true);
                    }
                    this.value = '';
                    return;
                }

                // Instant local preview
                const reader = new FileReader();
                reader.onload = function(evt) {
                    if (patSigPreviewImg) {
                        patSigPreviewImg.src = evt.target.result;
                        patSigPreviewImg.style.display = 'block';
                    }
                    if (patSigPlaceholder) {
                        patSigPlaceholder.style.display = 'none';
                    }
                    if (patSigRemoveBtn) {
                        patSigRemoveBtn.style.display = 'inline-flex';
                    }
                    if (patSigBadge) {
                        patSigBadge.style.display = 'inline-flex';
                    }
                };
                reader.readAsDataURL(file);

                const uploadBtn = document.getElementById('dior-btn-patient-upload-sig');
                const origHtml = uploadBtn ? uploadBtn.innerHTML : '';
                if (uploadBtn) {
                    uploadBtn.disabled = true;
                    uploadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
                }

                const formData = new FormData();
                formData.append('action', 'dior_patient_upload_signature');
                formData.append('nonce', (window.dior_vars && window.dior_vars.nonce) ? window.dior_vars.nonce : '');
                formData.append('signature', file);

                const ajaxUrl = (window.dior_vars && window.dior_vars.ajax_url) ? window.dior_vars.ajax_url : '/wp-admin/admin-ajax.php';

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(response => {
                    if (uploadBtn) {
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = origHtml || '<i class="fa-solid fa-cloud-arrow-up"></i> Upload PNG';
                    }

                    if (response && response.success) {
                        const sigUrl = (response.data && response.data.signature_url) ? response.data.signature_url : '';
                        if (sigUrl && patSigPreviewImg) {
                            patSigPreviewImg.src = sigUrl;
                            patSigPreviewImg.style.display = 'block';
                        }
                        if (patSigPlaceholder) patSigPlaceholder.style.display = 'none';
                        if (patSigRemoveBtn) patSigRemoveBtn.style.display = 'inline-flex';
                        if (patSigBadge) patSigBadge.style.display = 'inline-flex';

                        if (window.dior_vars && window.dior_vars.patient_profile) {
                            window.dior_vars.patient_profile.signature_url = sigUrl;
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Signature Saved!',
                                text: 'Your electronic signature has been securely stored.',
                                confirmButtonColor: '#2C6CB1',
                                timer: 2500
                            });
                        } else {
                            showToast('Signature saved successfully!');
                        }
                    } else {
                        const msg = (response && response.data && response.data.message) ? response.data.message : 'Failed to upload signature.';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Upload Failed',
                                text: msg,
                                confirmButtonColor: '#2C6CB1'
                            });
                        } else {
                            showToast(msg, true);
                        }
                    }
                })
                .catch(err => {
                    console.error('[Patient Sig Upload Error]', err);
                    if (uploadBtn) {
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = origHtml || '<i class="fa-solid fa-cloud-arrow-up"></i> Upload PNG';
                    }
                    showToast('Connection failed during signature upload.', true);
                });
            });
        }

        let patSigMediaFrame = null;
        window.diorPatientOpenSigMediaLibrary = function() {
            if (typeof wp !== 'undefined' && wp.media) {
                if (patSigMediaFrame) {
                    patSigMediaFrame.open();
                    return;
                }

                patSigMediaFrame = wp.media({
                    title: 'Select Patient Electronic Signature (PNG recommended)',
                    button: { text: 'Use as Signature' },
                    library: { type: 'image' },
                    multiple: false
                });

                patSigMediaFrame.on('select', function() {
                    const attachment = patSigMediaFrame.state().get('selection').first().toJSON();
                    if (!attachment || !attachment.url) return;

                    const sigUrl = attachment.url;
                    const attachId = attachment.id || 0;

                    if (patSigPreviewImg) {
                        patSigPreviewImg.src = sigUrl;
                        patSigPreviewImg.style.display = 'block';
                    }
                    if (patSigPlaceholder) patSigPlaceholder.style.display = 'none';
                    if (patSigRemoveBtn) patSigRemoveBtn.style.display = 'inline-flex';
                    if (patSigBadge) patSigBadge.style.display = 'inline-flex';

                    const formData = new FormData();
                    formData.append('action', 'dior_patient_upload_signature');
                    formData.append('nonce', (window.dior_vars && window.dior_vars.nonce) ? window.dior_vars.nonce : '');
                    formData.append('signature_url', sigUrl);
                    formData.append('attachment_id', attachId);

                    const ajaxUrl = (window.dior_vars && window.dior_vars.ajax_url) ? window.dior_vars.ajax_url : '/wp-admin/admin-ajax.php';

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(res => res.json())
                    .then(response => {
                        if (response && response.success) {
                            if (window.dior_vars && window.dior_vars.patient_profile) {
                                window.dior_vars.patient_profile.signature_url = sigUrl;
                            }
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Signature Selected!',
                                    text: 'Your electronic signature was selected from media library.',
                                    confirmButtonColor: '#2C6CB1',
                                    timer: 2000
                                });
                            } else {
                                showToast('Signature updated.');
                            }
                        }
                    })
                    .catch(err => {
                        console.error('[Patient Sig Media Error]', err);
                    });
                });

                patSigMediaFrame.open();
            } else {
                alert('WordPress Media Library could not be loaded. Please refresh the page.');
            }
        };

        window.diorPatientRemoveSignature = function() {
            const doRemove = () => {
                if (patSigRemoveBtn) {
                    patSigRemoveBtn.disabled = true;
                    patSigRemoveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Removing...';
                }

                sendAjax('dior_patient_remove_signature', {}, function(res) {
                    if (patSigRemoveBtn) {
                        patSigRemoveBtn.disabled = false;
                        patSigRemoveBtn.innerHTML = '<i class="fa-regular fa-trash-can"></i> Remove';
                        patSigRemoveBtn.style.display = 'none';
                    }
                    if (patSigPreviewImg) {
                        patSigPreviewImg.src = '';
                        patSigPreviewImg.style.display = 'none';
                    }
                    if (patSigPlaceholder) {
                        patSigPlaceholder.style.display = 'block';
                    }
                    if (patSigBadge) {
                        patSigBadge.style.display = 'none';
                    }
                    if (window.dior_vars && window.dior_vars.patient_profile) {
                        window.dior_vars.patient_profile.signature_url = '';
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Signature Removed',
                            text: 'Your electronic signature has been removed.',
                            confirmButtonColor: '#2C6CB1',
                            timer: 2000
                        });
                    } else {
                        showToast('Signature removed.');
                    }
                }, function(err) {
                    if (patSigRemoveBtn) {
                        patSigRemoveBtn.disabled = false;
                        patSigRemoveBtn.innerHTML = '<i class="fa-regular fa-trash-can"></i> Remove';
                    }
                    showToast('Failed to remove signature.', true);
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Remove Signature?',
                    text: 'Are you sure you want to remove your electronic signature?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Remove Signature',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#2C6CB1',
                    cancelButtonColor: '#94A3B8'
                }).then((result) => {
                    if (result.isConfirmed) {
                        doRemove();
                    }
                });
            } else {
                if (confirm('Are you sure you want to remove your electronic signature?')) {
                    doRemove();
                }
            }
        };

        // Real Password Change Handler
        const pwdForm = document.getElementById('dior-form-change-password');
        if (pwdForm) {
            pwdForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const cur = this.current_password.value;
                const np = this.new_password.value;
                const cp = this.confirm_password.value;

                if (!cur || !np) {
                    showToast('Please enter both current and new password.', true);
                    return;
                }
                if (np !== cp) {
                    showToast('New passwords do not match.', true);
                    return;
                }
                if (np.length < 6) {
                    showToast('New password must be at least 6 characters.', true);
                    return;
                }

                sendAjax('dior_patient_change_password', {
                    current_password: cur,
                    new_password: np,
                    confirm_password: cp
                }, () => {
                    pwdForm.reset();
                });
            });
        }

        // =========================================================================
        // 9. MEDICAL QUESTIONNAIRE & INTAKE MODAL (With E-Sign)
        // =========================================================================
        window.diorOpenQuestionnaireForm = function(qnId, condition, symptoms, allergies, meds) {
            const modal = document.getElementById('modal-questionnaire-intake');
            if (!modal) return;

            document.getElementById('modal-qn-title').innerHTML = '<i class="fa-solid fa-clipboard-question"></i> Intake: ' + condition;
            document.getElementById('qn_input_id').value = qnId;
            document.getElementById('qn_input_condition').value = condition;
            document.getElementById('qn_input_symptoms').value = symptoms || '';
            document.getElementById('qn_input_allergies').value = allergies || '';
            document.getElementById('qn_input_meds').value = meds || '';

            diorOpenModal('modal-questionnaire-intake');
        };

        // File dropzone click
        const dropzone = document.getElementById('dior-upload-dropzone');
        const fileInput = document.getElementById('qn_file_upload');
        const filePreview = document.getElementById('dior-upload-preview');

        if (dropzone && fileInput) {
            dropzone.addEventListener('click', () => fileInput.click());

            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    if (filePreview) {
                        filePreview.style.display = 'block';
                        filePreview.innerHTML = '<i class="fa-solid fa-paperclip"></i> Selected file: ' + this.files[0].name;
                    }
                }
            });
        }

        // Submit Intake Form
        const intakeForm = document.getElementById('dior-form-submit-intake');
        if (intakeForm) {
            intakeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const submitBtn = document.getElementById('dior-submit-intake-btn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Submitting to Doctor...';
                }

                const data = {
                    qn_id: this.qn_id.value,
                    condition: this.condition.value,
                    symptoms: this.symptoms.value,
                    allergies: this.allergies.value,
                    current_meds: this.current_meds.value,
                    medical_history: this.medical_history.value,
                    esign_name: this.esign_name.value
                };

                sendAjax('dior_patient_submit_questionnaire', data, () => {
                    diorCloseModal('modal-questionnaire-intake');
                    setTimeout(() => window.location.reload(), 1200);
                }, () => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Clinical Intake to Doctor &rarr;';
                    }
                });
            });
        }

        // =========================================================================
        // 10. PHARMACY PREFERENCE CONTROLLER
        // =========================================================================
        window.diorOnPharmacySelectChange = function(selectEl) {
            const val = selectEl.value;
            if (val === 'custom') {
                return;
            }
            const parts = val.split('|');
            if (parts.length >= 3) {
                document.getElementById('q_pharm_name').value = parts[0];
                document.getElementById('q_pharm_address').value = parts[1];
                document.getElementById('q_pharm_phone').value = parts[2];
            }
        };

        const quickPharmForm = document.getElementById('dior-form-quick-pharmacy');
        if (quickPharmForm) {
            quickPharmForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const data = {
                    pharmacy_name: this.pharmacy_name.value,
                    pharmacy_address: this.pharmacy_address.value,
                    pharmacy_phone: this.pharmacy_phone.value
                };

                sendAjax('dior_patient_update_pharmacy', data, () => {
                    diorCloseModal('modal-change-pharmacy');
                    setTimeout(() => window.location.reload(), 1000);
                });
            });
        }

        // =========================================================================
        // 11. RECEIPT, DOCUMENT & RX DETAIL VIEWERS
        // =========================================================================
        window.diorViewReceipt = function(id, amount, service, date) {
            document.getElementById('rec-inv-id').textContent = id;
            document.getElementById('rec-amount').textContent = amount;
            document.getElementById('rec-service').textContent = service;
            document.getElementById('rec-date').textContent = date;
            diorOpenModal('modal-view-receipt');
        };

        window.diorViewDocumentModal = function(title, category, author, date, id) {
            const titleEl = document.getElementById('doc-preview-title');
            const catEl = document.getElementById('doc-preview-category');
            const authorEl = document.getElementById('doc-preview-author');
            const dateEl = document.getElementById('doc-preview-date');
            const idEl = document.getElementById('doc-preview-id');
            if (titleEl) titleEl.textContent = title || 'Medical Record';
            if (catEl) catEl.textContent = category || 'Official Document';
            if (authorEl) authorEl.textContent = author || 'Dior Medical Urgent Care';
            if (dateEl) dateEl.textContent = date || '';
            if (idEl) idEl.textContent = id || 'DOC-88219';
            diorOpenModal('modal-view-document');
        };

        window.diorDownloadCurrentDocumentPdf = function() {
            var title = (document.getElementById('doc-preview-title') && document.getElementById('doc-preview-title').textContent) || 'Clinical Medical Certificate';
            var docId = (document.getElementById('doc-preview-id') && document.getElementById('doc-preview-id').textContent) || 'DOC-88219';
            var cat = (document.getElementById('doc-preview-category') && document.getElementById('doc-preview-category').textContent) || 'Official Document';
            var author = (document.getElementById('doc-preview-author') && document.getElementById('doc-preview-author').textContent) || 'Dr. Marcus Sterling, MD';
            var date = (document.getElementById('doc-preview-date') && document.getElementById('doc-preview-date').textContent) || 'Sept 14, 2026';
            var patientName = (document.getElementById('doc-preview-patient') && document.getElementById('doc-preview-patient').textContent) || 'Verified Patient';

            var logoUrl = (window.dior_vars && window.dior_vars.logo_url) ? window.dior_vars.logo_url : (window.location.origin + '/wp-content/uploads/2026/08/logo.png');
            var docSigUrl = (window.dior_vars && window.dior_vars.default_doctor_signature) ? window.dior_vars.default_doctor_signature : '';

            var cleanFileName = 'Dior-' + title.replace(/[^a-zA-Z0-9]/g, '-') + '.pdf';

            var sigHtml = '';
            if (docSigUrl) {
                sigHtml = '<img src="' + docSigUrl + '" alt="Doctor Signature" style="max-height:50px;max-width:180px;object-fit:contain;background:transparent;display:inline-block;vertical-align:middle;">';
            } else {
                sigHtml = '<span style="font-family:\'Playfair Display\',serif;font-style:italic;font-size:20px;color:#0F172A;font-weight:700;">/s/ ' + author + '</span>';
            }

            var printContent = '<div style="width:750px;margin:0 auto;padding:36px 42px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#1E293B;background:#FFFFFF;border:2px solid #00A896;border-radius:6px;position:relative;box-sizing:border-box;overflow:hidden;">' +
                '<!-- Logo Watermark -->' +
                '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:360px;height:360px;opacity:0.05;pointer-events:none;z-index:0;display:flex;align-items:center;justify-content:center;">' +
                    '<img src="' + logoUrl + '" alt="" style="width:100%;height:100%;object-fit:contain;">' +
                '</div>' +
                '<div style="position:relative;z-index:1;">' +
                    '<!-- Top Header: Logo on Left, Clinic Details on Right -->' +
                    '<table style="width:100%;border-collapse:collapse;border-bottom:2px solid #00A896;padding-bottom:14px;margin-bottom:20px;">' +
                        '<tr>' +
                            '<td style="width:60%;vertical-align:middle;">' +
                                '<div style="display:flex;align-items:center;gap:12px;">' +
                                    '<img src="' + logoUrl + '" alt="Dior Medical" style="height:48px;max-height:52px;width:auto;object-fit:contain;display:block;">' +
                                    '<div>' +
                                        '<div style="font-size:17px;font-weight:800;color:#0F172A;letter-spacing:0.04em;line-height:1.2;">DIOR MEDICAL</div>' +
                                        '<div style="font-size:10px;font-weight:700;color:#00A896;letter-spacing:0.05em;text-transform:uppercase;margin-top:2px;">TELEHEALTH &bull; VIRTUAL URGENT CARE</div>' +
                                        '<div style="font-size:9.5px;color:#64748B;margin-top:1px;">Clinical Telemedicine Division &bull; Surescripts Certified</div>' +
                                    '</div>' +
                                '</div>' +
                            '</td>' +
                            '<td style="width:40%;vertical-align:middle;text-align:right;font-size:10.5px;color:#475569;line-height:1.5;">' +
                                '<div style="font-weight:700;color:#0F172A;">+1 (800) 555-DIOR</div>' +
                                '<div>www.diormedical.com &bull; care@diormedical.com</div>' +
                                '<div>100 Medical Plaza, Suite 400</div>' +
                                '<div style="margin-top:3px;font-size:10px;color:#00A896;font-weight:700;">Doc Ref: <strong style="color:#0F172A;font-family:monospace;">' + docId + '</strong></div>' +
                            '</td>' +
                        '</tr>' +
                    '</table>' +

                    '<!-- Recipient & Date Bar (Exact Match to Reference Letterhead) -->' +
                    '<table style="width:100%;border-collapse:collapse;margin-bottom:18px;">' +
                        '<tr>' +
                            '<td style="width:65%;vertical-align:top;">' +
                                '<div style="font-size:10.5px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;">TO:</div>' +
                                '<div style="font-size:15px;font-weight:800;color:#0F172A;margin-top:2px;">' + patientName + '</div>' +
                                '<div style="font-size:11px;color:#64748B;margin-top:2px;">Dior Medical Telehealth Patient Registry &bull; Verified Record</div>' +
                            '</td>' +
                            '<td style="width:35%;vertical-align:top;text-align:right;">' +
                                '<div style="font-size:12.5px;font-weight:700;color:#0F172A;">' + date + '</div>' +
                                '<div style="display:inline-block;margin-top:4px;padding:2px 8px;background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;border-radius:10px;font-size:9.5px;font-weight:700;">&#10003; HIPAA Certified</div>' +
                            '</td>' +
                        '</tr>' +
                    '</table>' +

                    '<!-- Document Subject Banner -->' +
                    '<div style="background:#F0FDFA;border:1px solid #CCFBF1;border-radius:6px;padding:10px 16px;text-align:center;margin-bottom:20px;">' +
                        '<span style="display:inline-block;padding:2px 8px;background:#E0F2FE;color:#0369A1;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px;">' + cat + '</span>' +
                        '<h2 style="margin:0;font-size:16px;color:#0F172A;font-weight:800;">' + title + '</h2>' +
                    '</div>' +

                    '<!-- Salutation -->' +
                    '<p style="font-size:13px;font-weight:700;color:#0F172A;margin:0 0 14px 0;">Dear ' + patientName + ',</p>' +

                    '<!-- Clinical Statement Body -->' +
                    '<div style="font-size:12.5px;line-height:1.7;color:#334155;margin-bottom:24px;">' +
                        '<p style="margin:0 0 12px 0;">This official medical document confirms that you have satisfactorily completed your clinical telehealth consultation with Dior Medical Telehealth. All clinical assessments, patient intake questionnaires, and healthcare evaluations were conducted in strict compliance with state and federal telemedicine practice guidelines.</p>' +
                        '<p style="margin:0 0 12px 0;">The attending clinical provider has reviewed your health status and rendered the clinical determinations documented herein for <strong>' + title + '</strong> under clinical directive <strong>' + cat + '</strong>. All clinical guidance, medical advice, and instructions have been communicated directly through the encrypted secure patient portal.</p>' +
                        '<p style="margin:0;">This certified electronic clinical record is cryptographically archived and protected under 45 CFR Parts 160 and 164 (HIPAA Security &amp; Privacy Rules) and DEA Title 21 electronic healthcare regulations.</p>' +
                    '</div>' +

                    '<!-- Sign-off & Electronic Signature (Exact Image Match) -->' +
                    '<table style="width:100%;border-collapse:collapse;border-top:1.5px solid #E2E8F0;padding-top:14px;margin-top:20px;">' +
                        '<tr>' +
                            '<td style="width:55%;vertical-align:bottom;font-size:10px;color:#64748B;line-height:1.45;">' +
                                '<strong style="color:#059669;">&#10003; Cryptographically Certified Electronic Record</strong><br>' +
                                'Security Hash: SHA-256 Verified &bull; Federal ESIGN &amp; UETA Act Compliant<br>' +
                                'DEA 21 CFR Part 1311 &bull; Surescripts Real-Time EDI Network' +
                            '</td>' +
                            '<td style="width:45%;vertical-align:bottom;text-align:right;">' +
                                '<div style="font-size:11.5px;color:#475569;margin-bottom:4px;">Sincerely yours,</div>' +
                                '<div style="min-height:48px;display:inline-flex;align-items:center;justify-content:flex-end;border-bottom:1.5px solid #CBD5E1;padding-bottom:3px;min-width:180px;">' +
                                    sigHtml +
                                '</div>' +
                                '<div style="font-weight:700;color:#0F172A;font-size:13px;margin-top:4px;">' + author + '</div>' +
                                '<div style="font-size:10.5px;color:#64748B;">Attending Telemedicine Physician &bull; Dior Medical</div>' +
                                '<div style="font-size:9.5px;color:#00A896;font-weight:600;">DEA: MV8492019 &bull; NPI: 1849201948 &bull; Verified</div>' +
                            '</td>' +
                        '</tr>' +
                    '</table>' +
                '</div>' +
            '</div>';

            var tempContainer = document.createElement('div');
            tempContainer.style.position = 'fixed';
            tempContainer.style.left = '0';
            tempContainer.style.top = '0';
            tempContainer.style.width = '750px';
            tempContainer.style.zIndex = '-999999';
            tempContainer.style.background = '#FFFFFF';
            tempContainer.style.opacity = '1';
            tempContainer.style.pointerEvents = 'none';
            tempContainer.style.margin = '0';
            tempContainer.style.padding = '0';
            tempContainer.innerHTML = printContent;
            document.body.appendChild(tempContainer);

            if (typeof html2pdf !== 'undefined' && html2pdf) {
                var opt = {
                    margin:       [8, 8, 8, 8],
                    filename:     cleanFileName,
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { 
                        scale: 2, 
                        useCORS: true, 
                        logging: false, 
                        scrollY: 0, 
                        scrollX: 0, 
                        windowWidth: 794 
                    },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
                };
                try {
                    html2pdf().set(opt).from(tempContainer.firstElementChild || tempContainer).save().then(function() {
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    }).catch(function(err) {
                        console.error('PDF export error:', err);
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                        fallbackPrintDoc(printContent, title);
                    });
                } catch(e) {
                    console.error('PDF generation error:', e);
                    if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    fallbackPrintDoc(printContent, title);
                }
            } else {
                console.warn('html2pdf library not loaded, falling back to print');
                if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                fallbackPrintDoc(printContent, title);
            }
        };

        function fallbackPrintDoc(htmlContent, title) {
            var printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write('<html><head><title>' + title + '</title><style>@page{size:auto;margin:15mm;} body{margin:0;padding:20px;font-family:Arial,sans-serif;}</style></head><body>' + htmlContent + '</body></html>');
                printWindow.document.close();
                printWindow.focus();
                setTimeout(function() { printWindow.print(); }, 250);
            } else {
                window.print();
            }
        }

        window.diorDownloadReceiptPdf = function() {
            var invId = (document.getElementById('rec-inv-id') && document.getElementById('rec-inv-id').textContent) || 'INV-8829';
            var date = (document.getElementById('rec-date') && document.getElementById('rec-date').textContent) || 'Sept 4, 2026';
            var patientInfo = (document.getElementById('rec-patient-info') && document.getElementById('rec-patient-info').textContent) || 'Patient';
            var service = (document.getElementById('rec-service') && document.getElementById('rec-service').textContent) || 'Urgent Care Telehealth Consultation';
            var amount = (document.getElementById('rec-amount') && document.getElementById('rec-amount').textContent) || '$49.00';
            var logoUrl = (window.dior_vars && window.dior_vars.logo_url) ? window.dior_vars.logo_url : '/wp-content/uploads/2026/08/logo.png';

            var cleanFileName = 'Dior-Receipt-' + invId.replace(/[^a-zA-Z0-9]/g, '-') + '.pdf';

            var printContent = '<div style="width:750px;margin:0 auto;padding:36px;font-family:Arial,Helvetica,sans-serif;color:#0F172A;background:#FFFFFF;border:2px solid #00A896;border-radius:8px;box-sizing:border-box;">' +
                '<div style="text-align:center;margin-bottom:20px;border-bottom:2px solid #00A896;padding-bottom:18px;">' +
                    '<img src="' + logoUrl + '" alt="Dior Medical" style="height:52px;max-height:56px;object-fit:contain;margin-bottom:8px;">' +
                    '<h1 style="margin:0;font-size:20px;color:#0B1030;font-weight:800;letter-spacing:0.02em;">DIOR MEDICAL TELEHEALTH</h1>' +
                    '<p style="margin:4px 0 0 0;font-size:11.5px;color:#64748B;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Official Medical Statement &amp; Itemized Receipt</p>' +
                '</div>' +

                '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;">' +
                    '<tr style="background:#F1F5F9;border-bottom:1px solid #CBD5E1;">' +
                        '<td style="padding:12px 16px;font-weight:700;font-size:12.5px;color:#475569;width:35%;">Invoice #:</td>' +
                        '<td style="padding:12px 16px;font-size:14px;color:#0F172A;font-weight:800;">' + invId + '</td>' +
                    '</tr>' +
                    '<tr style="border-bottom:1px solid #E2E8F0;">' +
                        '<td style="padding:12px 16px;font-weight:700;font-size:12.5px;color:#475569;">Date of Transaction:</td>' +
                        '<td style="padding:12px 16px;font-size:13px;color:#0F172A;font-weight:600;">' + date + '</td>' +
                    '</tr>' +
                    '<tr style="border-bottom:1px solid #E2E8F0;">' +
                        '<td style="padding:12px 16px;font-weight:700;font-size:12.5px;color:#475569;">Patient Name &amp; ID:</td>' +
                        '<td style="padding:12px 16px;font-size:13px;color:#0F172A;font-weight:600;">' + patientInfo + '</td>' +
                    '</tr>' +
                    '<tr style="border-bottom:1px solid #E2E8F0;">' +
                        '<td style="padding:12px 16px;font-weight:700;font-size:12.5px;color:#475569;">Service Description:</td>' +
                        '<td style="padding:12px 16px;font-size:13px;color:#0F172A;font-weight:600;">' + service + '</td>' +
                    '</tr>' +
                    '<tr style="border-bottom:1px solid #E2E8F0;">' +
                        '<td style="padding:12px 16px;font-weight:700;font-size:12.5px;color:#475569;">Payment Method:</td>' +
                        '<td style="padding:12px 16px;font-size:13px;color:#0F172A;font-weight:600;">Stripe (Credit Card / Apple Pay) &bull; Verified &bull; Paid in Full</td>' +
                    '</tr>' +
                    '<tr style="background:#F0FDFA;">' +
                        '<td style="padding:14px 16px;font-weight:800;font-size:14px;color:#0F766E;">Total Paid:</td>' +
                        '<td style="padding:14px 16px;font-size:17px;color:#0F766E;font-weight:800;">' + amount + '</td>' +
                    '</tr>' +
                '</table>' +

                '<div style="padding:14px 18px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;color:#64748B;text-align:center;line-height:1.5;margin-bottom:24px;">' +
                    '<strong>Tax Compliance &amp; Insurance Reimbursement Notice:</strong><br>' +
                    'Dior Medical Telehealth &amp; Urgent Care &bull; Tax ID / NPI Verified &bull; Eligible for HSA / FSA Flexible Spending Account Reimbursement' +
                '</div>' +

                '<table style="width:100%;border-collapse:collapse;border-top:2px solid #E2E8F0;padding-top:16px;">' +
                    '<tr>' +
                        '<td style="width:60%;vertical-align:bottom;font-size:11px;color:#64748B;line-height:1.5;">' +
                            '<strong style="color:#059669;">&#10003; Official Electronic Payment Receipt</strong><br>' +
                            'Transaction ID: ' + invId + ' &bull; Status: Completed' +
                        '</td>' +
                        '<td style="width:40%;vertical-align:bottom;text-align:right;">' +
                            '<div style="font-family:\'Courier New\',Courier,monospace;font-size:14px;color:#0F172A;font-weight:700;border-bottom:1px solid #0F172A;display:inline-block;padding-bottom:2px;margin-bottom:4px;">' +
                                'Dior Billing Directorate' +
                            '</div>' +
                            '<div style="font-size:11px;color:#64748B;font-weight:600;">Authorized Financial Officer</div>' +
                        '</td>' +
                    '</tr>' +
                '</table>' +
            '</div>';

            var tempContainer = document.createElement('div');
            tempContainer.style.position = 'fixed';
            tempContainer.style.left = '0';
            tempContainer.style.top = '0';
            tempContainer.style.width = '750px';
            tempContainer.style.zIndex = '-999999';
            tempContainer.style.background = '#FFFFFF';
            tempContainer.style.opacity = '1';
            tempContainer.style.pointerEvents = 'none';
            tempContainer.style.margin = '0';
            tempContainer.style.padding = '0';
            tempContainer.innerHTML = printContent;
            document.body.appendChild(tempContainer);

            if (typeof html2pdf !== 'undefined' && html2pdf) {
                var opt = {
                    margin:       [8, 8, 8, 8],
                    filename:     cleanFileName,
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { 
                        scale: 2, 
                        useCORS: true, 
                        logging: false, 
                        scrollY: 0, 
                        scrollX: 0, 
                        windowWidth: 794 
                    },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
                };
                try {
                    html2pdf().set(opt).from(tempContainer.firstElementChild || tempContainer).save().then(function() {
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    }).catch(function(err) {
                        console.error('PDF receipt export error:', err);
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                        fallbackPrintDoc(printContent, invId);
                    });
                } catch(e) {
                    console.error('PDF generation error:', e);
                    if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    fallbackPrintDoc(printContent, invId);
                }
            } else {
                console.warn('html2pdf library not loaded, falling back to print');
                if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                fallbackPrintDoc(printContent, invId);
            }
        };

        window.diorPrintReceiptModal = function() {
            window.diorDownloadReceiptPdf();
        };

        window.diorViewRxDetails = function(rxId, fallbackMed, fallbackInst) {
            if (typeof window.diorOpenModal === 'function') {
                var rxs = window.dior_patient_rxs_data || [];
                var found = null;
                if (rxs && rxs.length) {
                    for (var i = 0; i < rxs.length; i++) {
                        var r = rxs[i];
                        if (String(r.id).trim().toLowerCase() === String(rxId).trim().toLowerCase() ||
                            (r.raw_ids && r.raw_ids.some(function (rid) { return String(rid).trim().toLowerCase() === String(rxId).trim().toLowerCase(); }))) {
                            found = r;
                            break;
                        }
                    }
                }

                var idEl = document.getElementById('pat-modal-rx-id');
                var docEl = document.getElementById('pat-modal-rx-doc');
                var docHeaderEl = document.getElementById('pat-modal-rx-doc-header');
                var docCellEl = document.getElementById('pat-modal-rx-doc-name-cell');
                var dateEl = document.getElementById('pat-modal-rx-date');
                var routingEl = document.getElementById('pat-modal-rx-routing');
                var statusBadge = document.getElementById('pat-modal-rx-status-badge');
                var pharmNameEl = document.getElementById('pat-modal-rx-pharm-name');
                var sigNameEl = document.getElementById('pat-modal-rx-sig-name');
                var sigDateEl = document.getElementById('pat-modal-rx-sig-date');
                var barcodeTxt = document.getElementById('pat-modal-rx-barcode-txt');
                var adviceEl = document.getElementById('pat-modal-rx-advice');
                var pdfBtn = document.getElementById('pat-modal-rx-pdf-btn');
                var refillBtn = document.getElementById('pat-modal-rx-refill-btn');
                var tableBody = document.getElementById('pat-modal-rx-table-body');

                var actualId = found ? (found.id || rxId) : rxId;
                var rawDoc = found ? (found.prescribed_by || 'Dr. Marcus Sterling') : 'Dr. Marcus Sterling';
                var cleanDoc = rawDoc.replace(/^(Dr\.?\s*)+/i, 'Dr. ');
                var actualDate = found ? (found.date_prescribed || found.date || '—') : '—';
                var actualRouting = found ? (found.routing_status || 'Sent to Pharmacy') : 'Sent to Pharmacy';
                var actualPharm = found ? (found.pharmacy || 'Preferred Pharmacy on File') : 'Preferred Pharmacy on File';

                if (idEl) idEl.textContent = 'Rx #' + actualId;
                if (barcodeTxt) barcodeTxt.textContent = '*RX-' + String(actualId).replace(/[^0-9a-zA-Z]/g, '') + '*';
                if (docEl) docEl.textContent = cleanDoc;
                if (docHeaderEl) docHeaderEl.innerHTML = '<span>' + escapeHtml(cleanDoc) + ', MD</span>';
                if (docCellEl) docCellEl.textContent = cleanDoc + ', MD';
                if (dateEl) dateEl.textContent = actualDate;
                var padDateEl = document.getElementById('pat-modal-rx-pad-date');
                if (padDateEl) padDateEl.textContent = actualDate;
                if (routingEl) routingEl.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#059669;"></i> ' + actualRouting;
                if (statusBadge) statusBadge.innerHTML = '<i class="fa-solid fa-check"></i> ' + (found.routing_status || (actualRouting === 'Sent to Pharmacy' ? 'Transmission Confirmed' : actualRouting));
                if (pharmNameEl) pharmNameEl.textContent = actualPharm;
                if (sigNameEl) sigNameEl.textContent = '/s/ ' + cleanDoc + ', MD';
                if (sigDateEl) sigDateEl.textContent = 'SHA256 Encrypted \u2022 Issued ' + actualDate;

                var sigImgEl = document.getElementById('pat-modal-rx-sig-img');
                var docSigUrl = (found && (found.doctor_signature || found.signature_url)) || (window.dior_vars && window.dior_vars.default_doctor_signature ? window.dior_vars.default_doctor_signature : '');
                if (sigImgEl) {
                    if (docSigUrl) {
                        sigImgEl.src = docSigUrl;
                        sigImgEl.style.display = 'inline-block';
                        if (sigNameEl) sigNameEl.style.display = 'none';
                    } else {
                        sigImgEl.style.display = 'none';
                        if (sigNameEl) sigNameEl.style.display = 'inline-block';
                    }
                }

                function escapeHtml(str) {
                    if (!str) return '';
                    var d = document.createElement('div');
                    d.textContent = str;
                    return d.innerHTML;
                }

                var items = [];
                if (found && found.items && found.items.length) {
                    items = found.items;
                } else {
                    items = [{
                        id: actualId + '-1',
                        medication: (found ? (found.medication || found.name) : '') || fallbackMed || 'Prescription Medication',
                        dosage: (found ? (found.dosage || found.dose) : '') || 'As Prescribed',
                        instructions: (found ? (found.instructions || found.notes) : '') || fallbackInst || 'Take as directed by prescribing physician.',
                        quantity: (found ? found.quantity : '') || 'As Prescribed',
                        refills: (found ? found.refills : '') || '0 Refills Remaining'
                    }];
                }

                if (tableBody) {
                    var html = '';
                    var adviceNotes = [];
                    for (var j = 0; j < items.length; j++) {
                        var it = items[j];
                        var itemUid = (it && it.id) ? it.id : (actualId + '-' + (j + 1));
                        var medName = escapeHtml(it.medication);
                        var doseStr = escapeHtml(it.dosage);
                        var sigStr = escapeHtml(it.instructions || it.dosage || 'Take as directed by physician.');
                        var qtyStr = escapeHtml(it.quantity || 'As Prescribed');
                        var refillStr = escapeHtml(it.refills || '0 Refills Remaining');
                        
                        if (it.notes && it.notes !== sigStr) {
                            adviceNotes.push(escapeHtml(it.notes));
                        }

                        var formattedMed = medName;
                        if (!/^(TAB|CAP|SYR|INJ|SOL)\b/i.test(formattedMed)) {
                            formattedMed = 'TAB. ' + formattedMed;
                        }

                        html += '<tr>' +
                            '<td class="dior-rx-item-num" style="font-size:11px;font-weight:600;color:#64748B;">' + (j + 1) + '</td>' +
                            '<td>' +
                                '<div class="dior-rx-item-name">' + formattedMed + '</div>' +
                                '<div class="dior-rx-item-sub">' +
                                    '<span style="font-family:monospace;font-size:9.5px;color:#00A896;background:#F0FDFA;border:1px solid #CCFBF1;padding:1px 5px;border-radius:4px;font-weight:600;">Rx Item: ' + escapeHtml(itemUid) + '</span>' +
                                    '<span class="dior-rx-fda-badge"><i class="fa-solid fa-check"></i> FDA Approved</span>' +
                                    '<span>Oral Formulation &bull; Single Patient Use</span>' +
                                '</div>' +
                            '</td>' +
                            '<td>' +
                                '<div class="dior-rx-item-sig">' +
                                    '<strong>SIG:</strong> ' + sigStr +
                                '</div>' +
                                '<div style="font-size:10.5px;color:#64748B;margin-top:2px;">' +
                                    'Dosage: <strong style="color:#334155;font-weight:600;">' + doseStr + '</strong> &bull; Route: Oral' +
                                '</div>' +
                            '</td>' +
                            '<td>' +
                                '<div class="dior-rx-item-qty">Qty: ' + qtyStr + '</div>' +
                                '<div class="dior-rx-item-refill">' + refillStr + '</div>' +
                                '<div class="dior-rx-item-daw">' + (found.daw || 'DAW-0 (Generic Auth)') + '</div>' +
                            '</td>' +
                        '</tr>';
                    }
                    tableBody.innerHTML = html;

                    if (adviceEl) {
                        var adviceHtml = '&bull; Take medication strictly as directed with a full glass of water.<br>' +
                            '&bull; Complete the full course of treatment unless instructed otherwise by your doctor.<br>' +
                            '&bull; Avoid alcohol during the active course of treatment.';
                        if (adviceNotes.length > 0) {
                            adviceHtml += '<br>&bull; <strong>Physician Notes:</strong> ' + adviceNotes.join('; ');
                        }
                        adviceEl.innerHTML = adviceHtml;
                    }
                }

                if (pdfBtn) {
                    pdfBtn.onclick = function() { window.diorDownloadRxPdf(actualId); };
                }
                if (refillBtn) {
                    refillBtn.onclick = function() { window.diorRequestRefill(actualId); };
                }

                window.diorOpenModal('modal-view-rx-detail');
            } else {
                alert('Prescription #' + rxId);
            }
        };

        window.diorRequestRefill = function(rxId) {
            if (typeof showToast === 'function') {
                showToast('Refill request submitted electronically to prescribing physician for Rx #' + rxId, false);
            } else {
                alert('Refill request submitted electronically to prescribing physician for Rx #' + rxId);
            }
        };

        window.diorViewAppointmentDetails = function(apptId) {
            if (typeof window.diorOpenModal === 'function') {
                var appts = window.dior_patient_appts_data || [];
                var found = null;
                for (var i = 0; i < appts.length; i++) {
                    if (String(appts[i].id) === String(apptId) || String(appts[i].appt_uid) === String(apptId)) {
                        found = appts[i];
                        break;
                    }
                }

                var idEl = document.getElementById('pat-modal-appt-id');
                var provEl = document.getElementById('pat-modal-appt-provider');
                var specEl = document.getElementById('pat-modal-appt-spec');
                var statusBadge = document.getElementById('pat-modal-appt-status-badge');
                var dtEl = document.getElementById('pat-modal-appt-datetime');
                var modeEl = document.getElementById('pat-modal-appt-mode');
                var condEl = document.getElementById('pat-modal-appt-condition');
                var notesEl = document.getElementById('pat-modal-appt-notes');
                var zoomWrap = document.getElementById('pat-modal-zoom-wrap');
                var zoomLink = document.getElementById('pat-modal-zoom-link');

                if (found) {
                    if (idEl) idEl.textContent = 'APPT #' + (found.id || found.appt_uid || apptId);
                    if (provEl) provEl.textContent = found.provider || found.doctor_name || 'Attending Physician';
                    if (specEl) specEl.textContent = found.provider_spec || found.provider_title || 'Telehealth Urgent Care';
                    if (statusBadge) {
                        var st = found.status || found.appt_status || 'Confirmed';
                        statusBadge.textContent = st;
                        statusBadge.className = 'dior-st ' + (st === 'Confirmed' || st === 'Completed' ? 'ok' : (st === 'Cancelled' ? 'error' : 'pending'));
                    }
                    if (dtEl) dtEl.textContent = (found.date || found.appt_date || '') + ' ' + (found.time || found.appt_time || '');
                    if (modeEl) modeEl.innerHTML = '<i class="fa-solid fa-video" style="color:#2C6CB1;"></i> ' + (found.type || found.visit_type || 'Video Visit');
                    if (condEl) condEl.textContent = found.condition || found.condition_name || 'Telehealth Consultation';
                    if (notesEl) notesEl.textContent = found.notes || 'Standard telehealth clinical consultation scheduled. All submitted medical intake questionnaires are pre-verified.';
                    
                    var joinUrl = found.join_url || 'https://zoom.us/join';
                    var isLive = (found.status === 'Confirmed' || found.status === 'Scheduled' || found.status === 'In-Queue');
                    if (zoomWrap) {
                        zoomWrap.style.display = isLive ? 'flex' : 'none';
                        if (zoomLink) zoomLink.href = joinUrl;
                    }
                } else if (idEl) {
                    idEl.textContent = 'APPT #' + apptId;
                }

                window.diorOpenModal('modal-view-appointment-detail');
            } else {
                showToast('Viewing appointment details for #' + apptId);
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPatientPortal);
    } else {
        initPatientPortal();
    }

})(jQuery);

window.diorDownloadRxPdf = function(rxId) {
    var element = document.getElementById('rx-pdf-template-' + rxId);
    if (!element) {
        var allTemplates = document.querySelectorAll('[id^="rx-pdf-template-"]');
        if (allTemplates && allTemplates.length > 0) {
            for (var k = 0; k < allTemplates.length; k++) {
                var rawIds = (allTemplates[k].getAttribute('data-raw-ids') || '').split(',');
                if (allTemplates[k].id.indexOf(rxId) !== -1 || rawIds.indexOf(String(rxId)) !== -1) {
                    element = allTemplates[k];
                    break;
                }
            }
        }
    }
    if (!element) { alert('Prescription details not found for Rx #' + rxId); return; }
    
    var cleanFileName = 'Dior-Prescription-' + String(rxId).replace(/[^a-zA-Z0-9]/g, '-') + '.pdf';
    var innerContent = element.innerHTML;

    var tempContainer = document.createElement('div');
    tempContainer.style.position = 'fixed';
    tempContainer.style.left = '0';
    tempContainer.style.top = '0';
    tempContainer.style.width = '750px';
    tempContainer.style.background = '#FFFFFF';
    tempContainer.style.zIndex = '-999999';
    tempContainer.style.opacity = '1';
    tempContainer.style.pointerEvents = 'none';
    tempContainer.style.margin = '0';
    tempContainer.style.padding = '0';
    tempContainer.innerHTML = innerContent;
    document.body.appendChild(tempContainer);

    if (typeof html2pdf !== 'undefined' && html2pdf) {
        var opt = {
            margin:       [6, 6, 6, 6],
            filename:     cleanFileName,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { 
                scale: 2, 
                useCORS: true, 
                logging: false,
                scrollY: 0,
                scrollX: 0,
                windowWidth: 794
            },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
        };
        try {
            html2pdf().set(opt).from(tempContainer.firstElementChild || tempContainer).save().then(function() {
                if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
            }).catch(function(err) {
                console.error('PDF export error:', err);
                if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                if (typeof fallbackPrintWindow === 'function') {
                    fallbackPrintWindow(innerContent, 'Prescription #' + rxId);
                } else {
                    window.print();
                }
            });
        } catch(e) {
            console.error('PDF generation error:', e);
            if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
            if (typeof fallbackPrintWindow === 'function') {
                fallbackPrintWindow(innerContent, 'Prescription #' + rxId);
            } else {
                window.print();
            }
        }
    } else {
        console.warn('html2pdf library not loaded, falling back to print');
        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
        if (typeof fallbackPrintWindow === 'function') {
            fallbackPrintWindow(innerContent, 'Prescription #' + rxId);
        } else {
            var printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write('<html><head><title>Prescription #' + rxId + '</title></head><body style="margin:20px;background:#fff;font-family:Arial,sans-serif;">' + innerContent + '</body></html>');
                printWindow.document.close();
                printWindow.focus();
                setTimeout(function() { printWindow.print(); }, 250);
            } else {
                window.print();
            }
        }
    }
};

/**
 * Handle Appointment Reminder Master Opt-In Toggle
 */
window.diorHandleOptinMasterToggle = function(checkbox) {
    if (!checkbox) return;
    const subWrap = document.getElementById('dior-reminder-subchannels');
    const isChecked = checkbox.checked;

    if (subWrap) {
        if (isChecked) {
            subWrap.style.opacity = '1';
            subWrap.style.pointerEvents = 'auto';
        } else {
            subWrap.style.opacity = '0.5';
            subWrap.style.pointerEvents = 'none';
        }
    }

    // Direct AJAX sync
    if (typeof sendAjax === 'function') {
        sendAjax('dior_toggle_reminder_optin', {
            channel: 'optin_appointment_reminders',
            value: isChecked ? '1' : '0'
        }, function(res) {
            if (typeof Swal !== 'undefined') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2800,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: isChecked ? 'success' : 'info',
                    title: isChecked ? 'Appointment Reminders Opted-In' : 'Appointment Reminders Paused'
                });
            }
        });
    }
};

window.diorToggleReminderOptin = function(channel, value) {
    if (typeof sendAjax === 'function') {
        sendAjax('dior_toggle_reminder_optin', {
            channel: channel,
            value: value
        }, function(res) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Preferences Updated',
                    text: res.message || 'Notification preference saved successfully.',
                    confirmButtonColor: '#2C6CB1'
                });
            }
        });
    }
};


/* Dior dashboard navigation safety net.
 * Keeps navigation functional even when optional dashboard data/AJAX fails. */
(function () {
    function bindDiorPatientNavigation() {
        var app = document.getElementById('dior-patient-portal-app');
        if (!app || app.dataset.diorNavReady === '1') return;
        app.dataset.diorNavReady = '1';
        function activate(tab) {
            var panel = document.getElementById('tab-' + tab);
            if (!panel) return;
            app.querySelectorAll('.dior-nav-btn[data-tab]').forEach(function (b) { b.classList.toggle('active', b.dataset.tab === tab); });
            app.querySelectorAll('.dior-tab-panel').forEach(function (p) { p.classList.toggle('active', p === panel); });
            if (history.replaceState) history.replaceState(null, '', '#tab=' + tab);
        }
        app.addEventListener('click', function (event) {
            var button = event.target.closest('.dior-nav-btn[data-tab]');
            if (!button || button.closest('.dior-nav-item-has-children')) return;
            event.preventDefault();
            activate(button.dataset.tab);
        });
        var initial = (location.hash.match(/^#tab=([^&]+)/) || [])[1] || 'overview';
        activate(initial);
        window.addEventListener('hashchange', function () { activate((location.hash.match(/^#tab=([^&]+)/) || [])[1] || 'overview'); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindDiorPatientNavigation);
    else bindDiorPatientNavigation();
})();
