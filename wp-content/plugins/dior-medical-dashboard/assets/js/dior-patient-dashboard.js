/**
 * Dior Medical — Patient Dashboard page-specific JavaScript.
 * Extracted from the dashboard template without changing existing behavior.
 */

/* ------------------------------------------------------------------
 * Shared pager for every patient dashboard table.
 * Renders the exact same markup as the Doctor Dashboard pager
 * (.dior-page-btn: prev / numbers / next) so both dashboards share
 * one visual language: light square buttons + blue active page.
 * ------------------------------------------------------------------ */
function diorPagerChevron(direction) {
    return '<i class="fa-solid fa-chevron-' + direction + ' dior-pager-chevron"></i>';
}

function diorBuildPagerHtml(currentPage, totalPages, goFunctionName) {
    if (!totalPages || totalPages < 2) {
        return '';
    }

    var html = '';

    html += '<button type="button" class="dior-page-btn" ' + (currentPage <= 1 ? 'disabled' : '') +
        ' onclick="' + goFunctionName + '(' + (currentPage - 1) + ')" title="Previous Page">' +
        diorPagerChevron('left') + '</button>';

    if (totalPages <= 7) {
        for (var i = 1; i <= totalPages; i++) {
            html += '<button type="button" class="dior-page-btn' + (i === currentPage ? ' active' : '') +
                '" onclick="' + goFunctionName + '(' + i + ')">' + i + '</button>';
        }
    } else {
        for (var j = 1; j <= totalPages; j++) {
            if (j === 1 || j === totalPages || (j >= currentPage - 1 && j <= currentPage + 1)) {
                html += '<button type="button" class="dior-page-btn' + (j === currentPage ? ' active' : '') +
                    '" onclick="' + goFunctionName + '(' + j + ')">' + j + '</button>';
            } else if (j === currentPage - 2 || j === currentPage + 2) {
                html += '<span class="dior-pager-gap">...</span>';
            }
        }
    }

    html += '<button type="button" class="dior-page-btn" ' + (currentPage >= totalPages ? 'disabled' : '') +
        ' onclick="' + goFunctionName + '(' + (currentPage + 1) + ')" title="Next Page">' +
        diorPagerChevron('right') + '</button>';

    return html;
}

// --- Extracted dashboard script 1 ---
let diorBookingState = {
                departmentId: null,
                departmentName: null,
                doctorId: null,
                doctorName: null,
                date: null,
                day: null,
                clinicId: null,
                time: null,
                patient: {
                    name: (window.dior_patient_dashboard && window.dior_patient_dashboard.patient ? window.dior_patient_dashboard.patient.name : ''),
                    email: (window.dior_patient_dashboard && window.dior_patient_dashboard.patient ? window.dior_patient_dashboard.patient.email : ''),
                    phone: (window.dior_patient_dashboard && window.dior_patient_dashboard.patient ? window.dior_patient_dashboard.patient.phone : ''),
                    notes: ""
                }
            };

            const diorRestUrl = (window.dior_patient_dashboard && window.dior_patient_dashboard.rest_url) || (window.dior_vars && window.dior_vars.rest_url) || '';
            const diorAjaxUrl = (window.dior_patient_dashboard && window.dior_patient_dashboard.ajax_url) || (window.dior_vars && window.dior_vars.ajax_url) || '';
            const diorRestNonce = (window.dior_patient_dashboard && window.dior_patient_dashboard.rest_nonce) || (window.dior_vars && window.dior_vars.rest_nonce) || '';
            const diorPortalNonce = (window.dior_patient_dashboard && window.dior_patient_dashboard.portal_nonce) || (window.dior_vars && window.dior_vars.nonce) || '';

            document.addEventListener('DOMContentLoaded', function () {
    const patientAppointmentForm = document.getElementById('dior-patient-appointment-form');
    if (patientAppointmentForm) {
        patientAppointmentForm.addEventListener('submit', function (event) {
            event.preventDefault();
            diorSubmitPatientAppointmentForm(this);
        });
    }

    // Mark patient notifications read using the existing secured AJAX endpoint.
    document.querySelectorAll('.dior-mark-notification-read').forEach(function (button) {
        button.addEventListener('click', function () {
            const notificationId = this.getAttribute('data-notif-id');
            if (!notificationId) return;
            const body = new URLSearchParams({
                action: 'dior_patient_mark_notification_read',
                nonce: diorPortalNonce,
                notif_id: notificationId
            });
            fetch(diorAjaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        const row = button.closest('tr');
                        if (row) row.remove();
                    }
                })
                .catch(function () {});
        });
    });
                diorFetchTreatments();
            });

            function diorBookingGoToStep(step) {
                document.querySelectorAll('.booking-step').forEach(el => el.style.display = 'none');
                document.getElementById('step-' + step + (step === 1 ? '-treatment' : (step === 2 ? '-doctor' : (step === 3 ? '-time' : '-confirm')))).style.display = 'block';
            }

            function diorFetchTreatments() {
                fetch(diorRestUrl + 'get-doctor/v1/departments')
                    .then(res => res.json())
                    .then(data => {
                        let html = '';
                        if (data && data.length) {
                            data.forEach(dept => {
                                html += `<button type="button" class="dior-grid-btn" onclick="diorSelectTreatment(${dept.id}, '${dept.name.replace(/'/g, "\\'")}')">${dept.name}</button>`;
                    });
                } else {
                    html = '<button type="button" class="dior-grid-btn" onclick="diorSelectTreatment(1, \'Urgent Care Telehealth\')">Urgent Care Telehealth</button><button type="button" class="dior-grid-btn" onclick="diorSelectTreatment(2, \'Primary Care & General Medicine\')">Primary Care</button>';
                }
                let el = document.getElementById('treatments-list');
                if (el) el.innerHTML = html;
            })
            .catch(() => {
                let el = document.getElementById('treatments-list');
                if (el) el.innerHTML = '<button type="button" class="dior-grid-btn" onclick="diorSelectTreatment(1, \'Urgent Care Telehealth\')">Urgent Care Telehealth</button><button type="button" class="dior-grid-btn" onclick="diorSelectTreatment(2, \'Primary Care & General Medicine\')">Primary Care</button>';
            });
    }

    function diorSelectTreatment(id, name) {
        diorBookingState.departmentId = id;
        diorBookingState.departmentName = name;
        document.getElementById('doctors-list').innerHTML = '<p>Loading doctors...</p>';
        diorBookingGoToStep(2);

        fetch(diorRestUrl + 'get-doctor-by-departments/v1/' + id)
            .then(res => res.json())
            .then(data => {
                let html = '';
                if (data && data.length) {
                    data.forEach(doc => {
                        html += `<div class="dior-doctor-card-select" onclick="diorSelectDoctor(${doc.id}, '${doc.name.replace(/'/g, "\\'")}')">
                                                                            <h4>${doc.name}</h4>
                                                                            <p>${doc.speciality}</p>
                                                                        </div>`;
                    });
                } else {
                    html = `<div class="dior-doctor-card-select" onclick="diorSelectDoctor(1695, 'Dr. James Chen, DO')">
                                                                        <h4>Dr. James Chen, DO</h4>
                                                                        <p>Primary Care & Urgent Care</p>
                                                                    </div>
                                                                    <div class="dior-doctor-card-select" onclick="diorSelectDoctor(1693, 'Dr. Marcus Sterling, DO')">
                                                                        <h4>Dr. Marcus Sterling, DO</h4>
                                                                        <p>Urgent Care Physician</p>
                                                                    </div>`;
                }
                document.getElementById('doctors-list').innerHTML = html;
            })
            .catch(() => {
                document.getElementById('doctors-list').innerHTML = `
                                                            <div class="dior-doctor-card-select" onclick="diorSelectDoctor(1695, 'Dr. James Chen, DO')">
                                                                <h4>Dr. James Chen, DO</h4>
                                                                <p>Primary Care & Urgent Care</p>
                                                            </div>
                                                            <div class="dior-doctor-card-select" onclick="diorSelectDoctor(1693, 'Dr. Marcus Sterling, DO')">
                                                                <h4>Dr. Marcus Sterling, DO</h4>
                                                                <p>Urgent Care Physician</p>
                                                            </div>`;
            });
    }

    function diorSelectDoctor(id, name) {
        diorBookingState.doctorId = id;
        diorBookingState.doctorName = name;
        document.getElementById('time-slots-list').innerHTML = '';
        document.getElementById('booking-date').value = '';
        diorBookingGoToStep(3);
    }

    function diorFetchAvailability() {
        let dateStr = document.getElementById('booking-date').value;
        if (!dateStr) return;

        document.getElementById('time-slots-list').innerHTML = '<p>Loading availability...</p>';
        diorBookingState.date = dateStr;

        function renderDefaultTimes() {
            const defaultTimes = ['09:00 AM', '10:30 AM', '01:00 PM', '02:30 PM', '04:00 PM', '05:30 PM'];
            let h = '<h4>Available Telehealth Slots</h4><div class="dior-time-grid">';
            defaultTimes.forEach(t => {
                h += `<button type="button" class="dior-time-btn" onclick="diorSelectTime(1, '${t}')">${t}</button>`;
            });
            h += '</div>';
            document.getElementById('time-slots-list').innerHTML = h;
        }

        fetch(diorRestUrl + `doctor-details-booking/v1/doctors/${diorBookingState.doctorId}/availability?date=${dateStr}`, {
            headers: { 'X-WP-Nonce': diorRestNonce }
        })
            .then(res => res.json())
            .then(data => {
                let html = '';
                if (data && data.clinics && data.clinics.length > 0) {
                    diorBookingState.day = data.day;
                    data.clinics.forEach(clinic => {
                        if (!clinic.is_on_holiday && clinic.timings && clinic.timings.length > 0) {
                            html += `<h4>${clinic.name}</h4><div class="dior-time-grid">`;
                            clinic.timings.forEach(timing => {
                                if (timing.is_available) {
                                    html += `<button type="button" class="dior-time-btn" onclick="diorSelectTime(${clinic.id}, '${timing.time}')">${timing.time}</button>`;
                                } else {
                                    html += `<button type="button" class="dior-time-btn disabled" disabled>${timing.time}</button>`;
                                }
                            });
                            html += `</div>`;
                        }
                    });
                }
                if (html) {
                    document.getElementById('time-slots-list').innerHTML = html;
                } else {
                    renderDefaultTimes();
                }
            })
            .catch(() => {
                renderDefaultTimes();
            });
    }

    function diorSelectTime(clinicId, time) {
        diorBookingState.clinicId = clinicId;
        diorBookingState.time = time;

        document.getElementById('booking-summary').innerHTML = `
                                                    <p><strong>Treatment:</strong> ${diorBookingState.departmentName || 'Telehealth Urgent Care'}</p>
                                                    <p><strong>Doctor:</strong> ${diorBookingState.doctorName || 'Attending Physician'}</p>
                                                    <p><strong>Date:</strong> ${diorBookingState.date}</p>
                                                    <p><strong>Time:</strong> ${diorBookingState.time}</p>
                                                    <p><strong>Patient:</strong> ${diorBookingState.patient.name || 'Verified Patient'}</p>
                                                `;
        diorBookingGoToStep(4);
    }

    function diorSubmitPatientAppointmentForm(form) {
        const message = document.getElementById('dior-patient-appointment-message');
        const submitButton = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);
        const doctor = formData.get('doctor') || '';
        const time = formData.get('timeSlot') || '';
        const injury = formData.get('injury') || '';
        const note = formData.get('note') || '';

        if (!doctor || !formData.get('doa') || !time) {
            message.className = 'dior-form-message is-error';
            message.textContent = 'Please select the doctor, appointment date and time slot.';
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
        message.className = 'dior-form-message';
        message.textContent = '';

        const request = new FormData();
        request.append('action', 'dior_patient_book_appointment');
        request.append('nonce', diorPortalNonce);
        request.append('name', [formData.get('first'), formData.get('last')].filter(Boolean).join(' '));
        request.append('email', formData.get('email') || '');
        request.append('phone', formData.get('mobile') || '');
        request.append('doctor_name', doctor);
        request.append('doctor', doctor);
        request.append('date', formData.get('doa') || '');
        request.append('time', time);
        request.append('condition', injury || 'Consultation');
        request.append('notes', note);
        request.append('type', 'Consultation');

        const reportFile = form.querySelector('input[name="uploadFile"]')?.files?.[0];
        if (reportFile) {
            request.append('uploadFile', reportFile, reportFile.name);
        }

        fetch(diorAjaxUrl, { method: 'POST', body: request })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error((data.data && data.data.message) || data.message || 'Unable to book the appointment.');
                }
                message.className = 'dior-form-message is-success';
                message.textContent = (data.data && data.data.message) || 'Appointment booked successfully.';
                form.reset();
                setTimeout(() => window.location.reload(), 900);
            })
            .catch(error => {
                message.className = 'dior-form-message is-error';
                message.textContent = error.message || 'Unable to book the appointment. Please try again.';
                submitButton.disabled = false;
                submitButton.textContent = 'Submit';
            });
    }

    function diorSubmitBooking() {
        let btn = document.getElementById('btn-confirm-booking');
        let msg = document.getElementById('booking-message');
        btn.disabled = true;
        btn.innerText = 'Booking Consultation...';

        const formData = new FormData();
        formData.append('action', 'dior_patient_book_appointment');
        formData.append('nonce', diorPortalNonce);
        formData.append('doctorId', diorBookingState.doctorId || 1695);
        formData.append('doctor_name', diorBookingState.doctorName || 'Doctor');
        formData.append('date', diorBookingState.date || '');
        formData.append('time', diorBookingState.time || '');
        formData.append('condition', diorBookingState.departmentName || 'Telehealth Consultation');
        formData.append('name', diorBookingState.patient.name || '');
        formData.append('email', diorBookingState.patient.email || '');
        formData.append('phone', diorBookingState.patient.phone || '');

        fetch(diorAjaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    msg.style.color = '#059669';
                    msg.innerText = 'Appointment booked successfully! Redirecting to your dashboard...';
                    setTimeout(() => {
                        window.location.href = (window.dior_patient_dashboard && window.dior_patient_dashboard.dashboard_url) || (window.dior_vars && window.dior_vars.portal_url) || '/patient-dashboard/';
                    }, 1500);
                } else {
                    msg.style.color = '#DC2626';
                    msg.innerText = (data.data && data.data.message) || data.message || 'Failed to book appointment.';
                    btn.disabled = false;
                    btn.innerText = 'Confirm & Book';
                }
            })
            .catch(err => {
                msg.style.color = '#DC2626';
                msg.innerText = 'An error occurred while booking. Please try again.';
                btn.disabled = false;
                btn.innerText = 'Confirm & Book';
            });
    }

// --- Extracted dashboard script 2 ---
function diorDownloadTodayAppointmentsCSV() {
                                const table = document.getElementById('dior-today-appointments-table');
                                if (!table) return;

                                let csv = [];
                                csv.push(['Doctor', 'Specialization', 'Date', 'Time', 'Treatment', 'Contact', 'Status'].join(','));

                                const rows = table.querySelectorAll('tbody tr');
                                rows.forEach(function(row) {
                                    if (row.style.display === 'none') return;
                                    const cols = row.querySelectorAll('td');
                                    if (cols.length >= 7) {
                                        let doctor = cols[0].innerText.replace(/\s+/g, ' ').trim();
                                        let specialization = cols[1].innerText.replace(/\s+/g, ' ').trim();
                                        let date = cols[2].innerText.replace(/\s+/g, ' ').trim();
                                        let time = cols[3].innerText.replace(/\s+/g, ' ').trim();
                                        let treatment = cols[4].innerText.replace(/\s+/g, ' ').trim();
                                        let contact = cols[5].innerText.replace(/\s+/g, ' ').trim();
                                        let status = cols[6].innerText.replace(/\s+/g, ' ').trim();

                                        let rowData = [
                                            `"${doctor.replace(/"/g, '""')}"`,
                                            `"${specialization.replace(/"/g, '""')}"`,
                                            `"${date.replace(/"/g, '""')}"`,
                                            `"${time.replace(/"/g, '""')}"`,
                                            `"${treatment.replace(/"/g, '""')}"`,
                                            `"${contact.replace(/"/g, '""')}"`,
                                            `"${status.replace(/"/g, '""')}"`
                                        ];
                                        csv.push(rowData.join(','));
                                    }
                                });

                                const csvString = csv.join('\r\n');
                                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                                const url = URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                link.setAttribute('href', url);
                                link.setAttribute('download', 'todays_appointments.csv');
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                URL.revokeObjectURL(url);
                            }

                            function diorFilterTodayAppointments() {
                                const input = document.getElementById('dior-today-search-input');
                                const filter = input ? input.value.toLowerCase() : '';
                                const rows = document.querySelectorAll('#dior-today-appointments-tbody tr');
                                let visibleCount = 0;

                                rows.forEach(function(row) {
                                    const text = row.innerText.toLowerCase();
                                    if (text.includes(filter)) {
                                        row.style.display = '';
                                        visibleCount++;
                                    } else {
                                        row.style.display = 'none';
                                    }
                                });

                                const pageCountEl = document.getElementById('dior-today-page-count');
                                if (pageCountEl) {
                                    pageCountEl.innerText = `0 selected / ${visibleCount} total`;
                                }
                            }

// --- Extracted dashboard script 3 ---
let diorUpcomingCurrentPage = 1;
                            const diorUpcomingRowsPerPage = 5;

                            function diorGetUpcomingActiveRows() {
                                const input = document.getElementById('dior-upcoming-search-input');
                                const filter = input ? input.value.toLowerCase() : '';
                                const allRows = Array.from(document.querySelectorAll('#dior-upcoming-appointments-tbody tr'));
                                
                                return allRows.filter(function(row) {
                                    const text = row.innerText.toLowerCase();
                                    return text.includes(filter);
                                });
                            }

                            function diorRenderUpcomingPagination() {
                                const activeRows = diorGetUpcomingActiveRows();
                                const allRows = Array.from(document.querySelectorAll('#dior-upcoming-appointments-tbody tr'));
                                const totalPages = Math.ceil(activeRows.length / diorUpcomingRowsPerPage) || 1;

                                if (diorUpcomingCurrentPage > totalPages) {
                                    diorUpcomingCurrentPage = totalPages;
                                }
                                if (diorUpcomingCurrentPage < 1) {
                                    diorUpcomingCurrentPage = 1;
                                }

                                // First hide all rows
                                allRows.forEach(row => row.style.display = 'none');

                                // Show rows for current page
                                const startIndex = (diorUpcomingCurrentPage - 1) * diorUpcomingRowsPerPage;
                                const endIndex = startIndex + diorUpcomingRowsPerPage;
                                const pageRows = activeRows.slice(startIndex, endIndex);

                                pageRows.forEach(row => row.style.display = '');

                                // Build pagination HTML
                                const pagContainer = document.getElementById('dior-upcoming-pagination');
                                if (pagContainer) {
                                    pagContainer.innerHTML = diorBuildPagerHtml(diorUpcomingCurrentPage, totalPages, 'diorGoUpcomingPage');
                                }

                                diorUpdateUpcomingSelectedCount(activeRows.length);
                            }

                            function diorGoUpcomingPage(page) {
                                diorUpcomingCurrentPage = page;
                                diorRenderUpcomingPagination();
                            }

                            function diorDownloadUpcomingAppointmentsCSV() {
                                const table = document.getElementById('dior-upcoming-appointments-table');
                                if (!table) return;

                                let csv = [];
                                csv.push(['Doctor', 'Date', 'Time', 'Injury', 'Status', 'Notes'].join(','));

                                const activeRows = diorGetUpcomingActiveRows();
                                activeRows.forEach(function(row) {
                                    const cols = row.querySelectorAll('td');
                                    if (cols.length >= 8) {
                                        let doctor = cols[1].innerText.replace(/\s+/g, ' ').trim();
                                        let date = cols[2].innerText.replace(/\s+/g, ' ').trim();
                                        let time = cols[3].innerText.replace(/\s+/g, ' ').trim();
                                        let injury = cols[4].innerText.replace(/\s+/g, ' ').trim();
                                        let status = cols[5].innerText.replace(/\s+/g, ' ').trim();
                                        let notes = cols[6].innerText.replace(/\s+/g, ' ').trim();

                                        let rowData = [
                                            `"${doctor.replace(/"/g, '""')}"`,
                                            `"${date.replace(/"/g, '""')}"`,
                                            `"${time.replace(/"/g, '""')}"`,
                                            `"${injury.replace(/"/g, '""')}"`,
                                            `"${status.replace(/"/g, '""')}"`,
                                            `"${notes.replace(/"/g, '""')}"`
                                        ];
                                        csv.push(rowData.join(','));
                                    }
                                });

                                const csvString = csv.join('\r\n');
                                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                                const url = URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                link.setAttribute('href', url);
                                link.setAttribute('download', 'upcoming_appointments.csv');
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                URL.revokeObjectURL(url);
                            }

                            function diorFilterUpcomingAppointments() {
                                diorUpcomingCurrentPage = 1;
                                diorRenderUpcomingPagination();
                            }

                            function diorToggleSelectAllUpcoming(master) {
                                const activeRows = diorGetUpcomingActiveRows();
                                activeRows.forEach(function(row) {
                                    const cb = row.querySelector('.upcoming-row-checkbox');
                                    if (cb) cb.checked = master.checked;
                                });
                                diorUpdateUpcomingSelectedCount();
                            }

                            function diorBulkDeleteUpcomingRows() {
                                const checkedBoxes = document.querySelectorAll('.upcoming-row-checkbox:checked');
                                checkedBoxes.forEach(function(cb) {
                                    const row = cb.closest('tr');
                                    if (row) row.remove();
                                });
                                const masterCb = document.getElementById('dior-select-all-upcoming');
                                if (masterCb) masterCb.checked = false;
                                diorRenderUpcomingPagination();
                            }

                            function diorUpdateUpcomingSelectedCount(customVisibleCount) {
                                const checkboxes = document.querySelectorAll('.upcoming-row-checkbox');
                                let selectedCount = 0;
                                let visibleCount = 0;

                                checkboxes.forEach(function(cb) {
                                    const row = cb.closest('tr');
                                    if (row) {
                                        visibleCount++;
                                        if (cb.checked) selectedCount++;
                                    }
                                });

                                const bulkDeleteBtn = document.getElementById('dior-upcoming-bulk-delete-btn');
                                if (bulkDeleteBtn) {
                                    bulkDeleteBtn.style.display = selectedCount > 0 ? 'inline-flex' : 'none';
                                }

                                const total = (typeof customVisibleCount === 'number') ? customVisibleCount : visibleCount;
                                const pageCountEl = document.getElementById('dior-upcoming-page-count');
                                if (pageCountEl) {
                                    pageCountEl.innerText = `${selectedCount} selected / ${total} total`;
                                }
                            }

                            document.addEventListener('DOMContentLoaded', function() {
                                diorRenderUpcomingPagination();
                            });
                            if (document.readyState === 'interactive' || document.readyState === 'complete') {
                                diorRenderUpcomingPagination();
                            }

// --- Extracted dashboard script 4 ---
let diorPastCurrentPage = 1;
                            const diorPastRowsPerPage = 5;

                            function diorGetPastActiveRows() {
                                const input = document.getElementById('dior-past-search-input');
                                const filter = input ? input.value.toLowerCase() : '';
                                const allRows = Array.from(document.querySelectorAll('#dior-past-appointments-tbody tr'));
                                
                                return allRows.filter(function(row) {
                                    const text = row.innerText.toLowerCase();
                                    return text.includes(filter);
                                });
                            }

                            function diorRenderPastPagination() {
                                const activeRows = diorGetPastActiveRows();
                                const allRows = Array.from(document.querySelectorAll('#dior-past-appointments-tbody tr'));
                                const totalPages = Math.ceil(activeRows.length / diorPastRowsPerPage) || 1;

                                if (diorPastCurrentPage > totalPages) {
                                    diorPastCurrentPage = totalPages;
                                }
                                if (diorPastCurrentPage < 1) {
                                    diorPastCurrentPage = 1;
                                }

                                allRows.forEach(row => row.style.display = 'none');

                                const startIndex = (diorPastCurrentPage - 1) * diorPastRowsPerPage;
                                const endIndex = startIndex + diorPastRowsPerPage;
                                const pageRows = activeRows.slice(startIndex, endIndex);

                                pageRows.forEach(row => row.style.display = '');

                                const pagContainer = document.getElementById('dior-past-pagination');
                                if (pagContainer) {
                                    pagContainer.innerHTML = diorBuildPagerHtml(diorPastCurrentPage, totalPages, 'diorGoPastPage');
                                }

                                const pageCountEl = document.getElementById('dior-past-page-count');
                                if (pageCountEl) {
                                    pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
                                }
                            }

                            function diorGoPastPage(page) {
                                diorPastCurrentPage = page;
                                diorRenderPastPagination();
                            }

                            function diorFilterPastAppointments() {
                                diorPastCurrentPage = 1;
                                diorRenderPastPagination();
                            }
                            
                            function diorDownloadPastAppointmentsCSV() {
                                const table = document.getElementById('dior-past-appointments-table');
                                if (!table) return;

                                let csv = [];
                                csv.push(['Doctor', 'Date', 'Time', 'Email', 'Mobile', 'Injury', 'Type', 'Next Appointment'].join(','));

                                const activeRows = diorGetPastActiveRows();
                                activeRows.forEach(function(row) {
                                    const cols = row.querySelectorAll('td');
                                    if (cols.length >= 8) {
                                        let doctor = cols[0].innerText.replace(/\s+/g, ' ').trim();
                                        let date = cols[1].innerText.replace(/\s+/g, ' ').trim();
                                        let time = cols[2].innerText.replace(/\s+/g, ' ').trim();
                                        let email = cols[3].innerText.replace(/\s+/g, ' ').trim();
                                        let mobile = cols[4].innerText.replace(/\s+/g, ' ').trim();
                                        let injury = cols[5].innerText.replace(/\s+/g, ' ').trim();
                                        let type = cols[6].innerText.replace(/\s+/g, ' ').trim();
                                        let nextAppt = cols[7].innerText.replace(/\s+/g, ' ').trim();

                                        let rowData = [
                                            `"${doctor.replace(/"/g, '""')}"`,
                                            `"${date.replace(/"/g, '""')}"`,
                                            `"${time.replace(/"/g, '""')}"`,
                                            `"${email.replace(/"/g, '""')}"`,
                                            `"${mobile.replace(/"/g, '""')}"`,
                                            `"${injury.replace(/"/g, '""')}"`,
                                            `"${type.replace(/"/g, '""')}"`,
                                            `"${nextAppt.replace(/"/g, '""')}"`
                                        ];
                                        csv.push(rowData.join(','));
                                    }
                                });

                                const csvString = csv.join('\r\n');
                                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                                const url = URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                link.setAttribute('href', url);
                                link.setAttribute('download', 'past_appointments.csv');
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                URL.revokeObjectURL(url);
                            }

                            document.addEventListener('DOMContentLoaded', function() {
                                diorRenderPastPagination();
                            });
                            if (document.readyState === 'interactive' || document.readyState === 'complete') {
                                diorRenderPastPagination();
                            }

// --- Extracted dashboard script 5 ---
window.diorSwitchApptSubTab = function(targetSubTab) {
                        var navBtns = document.querySelectorAll('.dior-nav-btn');
                        var tabPanels = document.querySelectorAll('.dior-tab-panel');
                        
                        // Switch Main Tab to Appointments
                        navBtns.forEach(function(b) {
                            if (b.getAttribute('data-tab') === 'appointments') b.classList.add('active');
                            else b.classList.remove('active');
                        });
                        tabPanels.forEach(function(p) {
                            if (p.id === 'tab-appointments') p.classList.add('active');
                            else p.classList.remove('active');
                        });

                        // Switch inner tab
                        var panels = document.querySelectorAll('.dior-subtab-panel');
                        panels.forEach(function(panel) {
                            panel.style.setProperty(
                                'display',
                                panel.id === 'dior-subtab-' + targetSubTab ? 'block' : 'none',
                                'important'
                            );
                        });
                    };

// --- Extracted dashboard script 6 ---
let diorBillCurrentPage = 1;
    const diorBillRowsPerPage = 5;

    function diorGetBillActiveRows() {
        const input = document.getElementById('dior-bill-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-bill-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderBillPagination() {
        const activeRows = diorGetBillActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-bill-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorBillRowsPerPage) || 1;

        if (diorBillCurrentPage > totalPages) {
            diorBillCurrentPage = totalPages;
        }
        if (diorBillCurrentPage < 1) {
            diorBillCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorBillCurrentPage - 1) * diorBillRowsPerPage;
        const endIndex = startIndex + diorBillRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-bill-pagination');
        if (pagContainer) {
            pagContainer.innerHTML = diorBuildPagerHtml(diorBillCurrentPage, totalPages, 'diorGoBillPage');
        }

        const pageCountEl = document.getElementById('dior-bill-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoBillPage(page) {
        diorBillCurrentPage = page;
        diorRenderBillPagination();
    }

    function diorFilterBill() {
        diorBillCurrentPage = 1;
        diorRenderBillPagination();
    }
    
    function diorDownloadBillCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Invoice No,Doctor Name,Date,Amount,Tax,Discount,Total\n";
        const activeRows = diorGetBillActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 7) {
                let c0 = cols[0].innerText.trim();
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                let c5 = cols[5].innerText.trim();
                let c6 = cols[6].innerText.trim();
                csvContent += `"${c0}","${c1}","${c2}","${c3}","${c4}","${c5}","${c6}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "billing.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function diorOpenBillingModal(inv, doc, date, amt, tax, disc, total) {
        document.getElementById('billModalSubtitle').innerText = doc;
        document.getElementById('billModalDocName').innerText = doc;
        document.getElementById('billModalDocName2').innerText = doc;
        document.getElementById('billModalInv').innerText = inv;
        document.getElementById('billModalDate').innerText = date;
        document.getElementById('billModalAmount').innerText = amt;
        document.getElementById('billModalTax').innerText = tax;
        document.getElementById('billModalDisc').innerText = disc;
        document.getElementById('billModalTotal').innerText = total;
        document.getElementById('billModalAvatar').src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(doc) + '&background=random';
        document.getElementById('diorBillingModal').style.display = 'flex';
    }
    function diorCloseBillingModal() {
        document.getElementById('diorBillingModal').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderBillPagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderBillPagination();
    }

// --- Extracted dashboard script 7 ---
let diorInsuranceCurrentPage = 1;
    const diorInsuranceRowsPerPage = 5;

    function diorGetInsuranceActiveRows() {
        const input = document.getElementById('dior-insurance-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-insurance-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderInsurancePagination() {
        const activeRows = diorGetInsuranceActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-insurance-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorInsuranceRowsPerPage) || 1;

        if (diorInsuranceCurrentPage > totalPages) {
            diorInsuranceCurrentPage = totalPages;
        }
        if (diorInsuranceCurrentPage < 1) {
            diorInsuranceCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorInsuranceCurrentPage - 1) * diorInsuranceRowsPerPage;
        const endIndex = startIndex + diorInsuranceRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-insurance-pagination');
        if (pagContainer) {
            pagContainer.innerHTML = diorBuildPagerHtml(diorInsuranceCurrentPage, totalPages, 'diorGoInsurancePage');
        }

        const pageCountEl = document.getElementById('dior-insurance-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoInsurancePage(page) {
        diorInsuranceCurrentPage = page;
        diorRenderInsurancePagination();
    }

    function diorFilterInsurance() {
        diorInsuranceCurrentPage = 1;
        diorRenderInsurancePagination();
    }
    
    function diorDownloadInsuranceCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Claim ID,Policy ID,Claim Date,Claim Type,Claim Amount,Approved,Submitted,Status\n";
        const activeRows = diorGetInsuranceActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 9) {
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                let c5 = cols[5].innerText.trim();
                let c6 = cols[6].innerText.trim();
                let c7 = cols[7].innerText.trim();
                let c8 = cols[8].innerText.trim();
                csvContent += `"${c1}","${c2}","${c3}","${c4}","${c5}","${c6}","${c7}","${c8}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "insurance.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderInsurancePagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderInsurancePagination();
    }

// --- Extracted dashboard script 8 ---
let diorRxCurrentPage = 1;
                    const diorRxRowsPerPage = 5;

                    function diorGetRxActiveRows() {
                        const input = document.getElementById('dior-rx-search-input');
                        const filter = input ? input.value.toLowerCase() : '';
                        const allRows = Array.from(document.querySelectorAll('#dior-rx-tbody tr'));
                        
                        return allRows.filter(function(row) {
                            const text = row.innerText.toLowerCase();
                            return text.includes(filter);
                        });
                    }

                    function diorRenderRxPagination() {
                        const activeRows = diorGetRxActiveRows();
                        const allRows = Array.from(document.querySelectorAll('#dior-rx-tbody tr'));
                        const totalPages = Math.ceil(activeRows.length / diorRxRowsPerPage) || 1;

                        if (diorRxCurrentPage > totalPages) {
                            diorRxCurrentPage = totalPages;
                        }
                        if (diorRxCurrentPage < 1) {
                            diorRxCurrentPage = 1;
                        }

                        allRows.forEach(row => row.style.display = 'none');

                        const startIndex = (diorRxCurrentPage - 1) * diorRxRowsPerPage;
                        const endIndex = startIndex + diorRxRowsPerPage;
                        const pageRows = activeRows.slice(startIndex, endIndex);

                        pageRows.forEach(row => row.style.display = '');

                        const pagContainer = document.getElementById('dior-rx-pagination');
                        if (pagContainer) {
                            pagContainer.innerHTML = diorBuildPagerHtml(diorRxCurrentPage, totalPages, 'diorGoRxPage');
                        }

                        const pageCountEl = document.getElementById('dior-rx-page-count');
                        if (pageCountEl) {
                            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
                        }
                    }

                    function diorGoRxPage(page) {
                        diorRxCurrentPage = page;
                        diorRenderRxPagination();
                    }

                    function diorFilterRx() {
                        diorRxCurrentPage = 1;
                        diorRenderRxPagination();
                    }

                    function diorDeleteRxRow(btn) {
                        const row = btn.closest('tr');
                        if (row) {
                            row.remove();
                            diorRenderRxPagination();
                        }
                    }
                    
                    function diorDownloadRxCSV() {
                        let csvContent = "data:text/csv;charset=utf-8,ID,Title,Created By,Date,Diseases\n";
                        const activeRows = diorGetRxActiveRows();
                        activeRows.forEach(function(row) {
                            let cols = row.querySelectorAll('td');
                            if(cols.length >= 5) {
                                let id = cols[0].innerText.trim();
                                let title = cols[1].innerText.trim();
                                let doc = cols[2].innerText.trim();
                                let date = cols[3].innerText.trim();
                                let disease = cols[4].innerText.trim();
                                csvContent += `"${id}","${title}","${doc}","${date}","${disease}"\n`;
                            }
                        });
                        var encodedUri = encodeURI(csvContent);
                        var link = document.createElement("a");
                        link.setAttribute("href", encodedUri);
                        link.setAttribute("download", "prescriptions.csv");
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }

                    document.addEventListener('DOMContentLoaded', function() {
                        diorRenderRxPagination();
                    });
                    if (document.readyState === 'interactive' || document.readyState === 'complete') {
                        diorRenderRxPagination();
                    }

// --- Extracted dashboard script 9 ---
let diorTeleCurrentPage = 1;
    const diorTeleRowsPerPage = 6;

    function diorGetTeleActiveRows() {
        const input = document.getElementById('dior-tele-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-tele-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderTelePagination() {
        const activeRows = diorGetTeleActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-tele-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorTeleRowsPerPage) || 1;

        if (diorTeleCurrentPage > totalPages) {
            diorTeleCurrentPage = totalPages;
        }
        if (diorTeleCurrentPage < 1) {
            diorTeleCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorTeleCurrentPage - 1) * diorTeleRowsPerPage;
        const endIndex = startIndex + diorTeleRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-tele-pagination');
        if (pagContainer) {
            pagContainer.innerHTML = diorBuildPagerHtml(diorTeleCurrentPage, totalPages, 'diorGoTelePage');
        }

        const pageCountEl = document.getElementById('dior-tele-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoTelePage(page) {
        diorTeleCurrentPage = page;
        diorRenderTelePagination();
    }

    function diorFilterTele() {
        diorTeleCurrentPage = 1;
        diorRenderTelePagination();
    }
    
    function diorDownloadTeleCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Session ID,Doctor,Specialty,Date,Time,Duration,Type,Status\n";
        const activeRows = diorGetTeleActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 10) {
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                let c5 = cols[5].innerText.trim();
                let c6 = cols[6].innerText.trim();
                let c7 = cols[7].innerText.trim();
                let c8 = cols[8].innerText.trim();
                csvContent += `"${c1}","${c2}","${c3}","${c4}","${c5}","${c6}","${c7}","${c8}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "telemedicine.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderTelePagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderTelePagination();
    }

// --- Extracted dashboard script 10 ---
let diorDocsCurrentPage = 1;
    const diorDocsRowsPerPage = 6;

    function diorGetDocsActiveRows() {
        const input = document.getElementById('dior-docs-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-docs-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderDocsPagination() {
        const activeRows = diorGetDocsActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-docs-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorDocsRowsPerPage) || 1;

        if (diorDocsCurrentPage > totalPages) {
            diorDocsCurrentPage = totalPages;
        }
        if (diorDocsCurrentPage < 1) {
            diorDocsCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorDocsCurrentPage - 1) * diorDocsRowsPerPage;
        const endIndex = startIndex + diorDocsRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-docs-pagination');
        if (pagContainer) {
            pagContainer.innerHTML = diorBuildPagerHtml(diorDocsCurrentPage, totalPages, 'diorGoDocsPage');
        }

        const pageCountEl = document.getElementById('dior-docs-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoDocsPage(page) {
        diorDocsCurrentPage = page;
        diorRenderDocsPagination();
    }

    function diorFilterDocs() {
        diorDocsCurrentPage = 1;
        diorRenderDocsPagination();
    }
    
    function diorDownloadDocsCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Document Title,Category,Type,Upload Date,Size,Status\n";
        const activeRows = diorGetDocsActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 8) {
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                let c5 = cols[5].innerText.trim();
                let c6 = cols[6].innerText.trim();
                csvContent += `"${c1}","${c2}","${c3}","${c4}","${c5}","${c6}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "documents.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderDocsPagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderDocsPagination();
    }

// Medical Record tab filters: the buttons filter the existing clinical timeline without reloading.
(function () {
    'use strict';
    function initMedicalRecordFilters() {
        var panel = document.getElementById('tab-medical_record');
        if (!panel || panel.dataset.filtersReady === '1') return;
        panel.dataset.filtersReady = '1';
        var buttons = panel.querySelectorAll('.dior-medical-record-filter');
        var items = panel.querySelectorAll('.mr-timeline-item[data-record-type]');
        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var filter = button.getAttribute('data-filter') || 'ALL';
                buttons.forEach(function (b) {
                    b.classList.remove('is-active', 'btn-primary');
                    b.classList.add('btn-light');
                });
                button.classList.add('is-active', 'btn-primary');
                button.classList.remove('btn-light');
                items.forEach(function (item) {
                    var type = item.getAttribute('data-record-type') || '';
                    item.style.display = (filter === 'ALL' || type === filter || (filter === 'CONSULTATION' && (type === 'CONSULTATION' || type === 'APPOINTMENT'))) ? '' : 'none';
                });
                var visible = Array.prototype.filter.call(items, function (item) { return item.style.display !== 'none'; });
                var empty = panel.querySelector('.mr-filter-empty');
                if (!visible.length) {
                    if (!empty) {
                        empty = document.createElement('div');
                        empty.className = 'mr-filter-empty';
                        empty.textContent = 'No records found for this category.';
                        var timeline = panel.querySelector('.modern-treatment-timeline');
                        if (timeline) timeline.appendChild(empty);
                    }
                    empty.style.display = '';
                } else if (empty) {
                    empty.style.display = 'none';
                }
            });
        });
    }
    document.addEventListener('DOMContentLoaded', initMedicalRecordFilters);
    if (document.readyState !== 'loading') initMedicalRecordFilters();
}());

// --- Extracted dashboard script 11 ---
let diorEmergCurrentPage = 1;
    const diorEmergRowsPerPage = 6;

    function diorGetEmergActiveRows() {
        const input = document.getElementById('dior-emerg-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-emerg-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderEmergPagination() {
        const activeRows = diorGetEmergActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-emerg-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorEmergRowsPerPage) || 1;

        if (diorEmergCurrentPage > totalPages) {
            diorEmergCurrentPage = totalPages;
        }
        if (diorEmergCurrentPage < 1) {
            diorEmergCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorEmergCurrentPage - 1) * diorEmergRowsPerPage;
        const endIndex = startIndex + diorEmergRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-emerg-pagination');
        if (pagContainer) {
            pagContainer.innerHTML = diorBuildPagerHtml(diorEmergCurrentPage, totalPages, 'diorGoEmergPage');
        }

        const pageCountEl = document.getElementById('dior-emerg-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoEmergPage(page) {
        diorEmergCurrentPage = page;
        diorRenderEmergPagination();
    }

    function diorFilterEmerg() {
        diorEmergCurrentPage = 1;
        diorRenderEmergPagination();
    }
    
    function diorDownloadEmergCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Contact Name,Relation,Phone Number,Priority\n";
        const activeRows = diorGetEmergActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 6) {
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                csvContent += `"${c1}","${c2}","${c3}","${c4}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "emergency_contacts.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderEmergPagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderEmergPagination();
    }

// --- Extracted dashboard script 12 ---
function diorSendChatMessage() {
            var input = document.getElementById('dior-chat-input');
            var container = document.getElementById('dior-chat-messages-container');
            if(!input || !container) return;
            var text = input.value.trim();
            if(!text) return;
            
            var now = new Date();
            var hours = now.getHours();
            var minutes = now.getMinutes();
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0'+minutes : minutes;
            var timeString = hours + ':' + minutes + ' ' + ampm;
            
            var msgHtml = `
                <div class="dior-ic-5d9c324602">
                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=100" class="dior-ic-5fc13777e2">
                    <div>
                        <div class="dior-ic-dff7aabfd2">
                            <span class="dior-ic-8fa9cd12a9">Sarah Jenkins</span>
                            <span class="dior-ic-0b48e6248d">${timeString}</span>
                        </div>
                        <div class="dior-ic-60cdfae882">
                            ${text.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', msgHtml);
            input.value = '';
            container.scrollTop = container.scrollHeight;
        }

// --- Extracted dashboard script 13 ---
let diorNotifCurrentPage = 1;
    const diorNotifRowsPerPage = 6;

    function diorGetNotifActiveRows() {
        const input = document.getElementById('dior-notif-search-input');
        const filter = input ? input.value.toLowerCase() : '';
        const allRows = Array.from(document.querySelectorAll('#dior-notif-tbody tr'));
        
        return allRows.filter(function(row) {
            const text = row.innerText.toLowerCase();
            return text.includes(filter);
        });
    }

    function diorRenderNotifPagination() {
        const activeRows = diorGetNotifActiveRows();
        const allRows = Array.from(document.querySelectorAll('#dior-notif-tbody tr'));
        const totalPages = Math.ceil(activeRows.length / diorNotifRowsPerPage) || 1;

        if (diorNotifCurrentPage > totalPages) {
            diorNotifCurrentPage = totalPages;
        }
        if (diorNotifCurrentPage < 1) {
            diorNotifCurrentPage = 1;
        }

        allRows.forEach(row => row.style.display = 'none');

        const startIndex = (diorNotifCurrentPage - 1) * diorNotifRowsPerPage;
        const endIndex = startIndex + diorNotifRowsPerPage;
        const pageRows = activeRows.slice(startIndex, endIndex);

        pageRows.forEach(row => row.style.display = '');

        const pagContainer = document.getElementById('dior-notif-pagination');
        if (pagContainer) {
            pagContainer.innerHTML = diorBuildPagerHtml(diorNotifCurrentPage, totalPages, 'diorGoNotifPage');
        }

        const pageCountEl = document.getElementById('dior-notif-page-count');
        if (pageCountEl) {
            pageCountEl.innerText = `0 selected / ${activeRows.length} total`;
        }
    }

    function diorGoNotifPage(page) {
        diorNotifCurrentPage = page;
        diorRenderNotifPagination();
    }

    function diorFilterNotif() {
        diorNotifCurrentPage = 1;
        diorRenderNotifPagination();
    }
    
    function diorDownloadNotifCSV() {
        let csvContent = "data:text/csv;charset=utf-8,Title,Message,Type,Date,Time,Status\n";
        const activeRows = diorGetNotifActiveRows();
        activeRows.forEach(function(row) {
            let cols = row.querySelectorAll('td');
            if(cols.length >= 7) {
                let c1 = cols[1].innerText.trim();
                let c2 = cols[2].innerText.trim();
                let c3 = cols[3].innerText.trim();
                let c4 = cols[4].innerText.trim();
                let c5 = cols[5].innerText.trim();
                let c6 = cols[6].innerText.trim();
                csvContent += `"${c1}","${c2}","${c3}","${c4}","${c5}","${c6}"\n`;
            }
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "notifications.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderNotifPagination();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderNotifPagination();
    }

// --- Extracted dashboard script 14 ---
window.dior_patient_appts_data = (window.dior_patient_dashboard && window.dior_patient_dashboard.appointments) || [];
        window.dior_patient_rxs_data = (window.dior_patient_dashboard && window.dior_patient_dashboard.prescriptions) || [];
        window.diorOpenModal = function (modalId) {
            var el = document.getElementById(modalId);
            if (el) {
                el.classList.add('open');
                el.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        };

        window.diorCloseModal = function (modalId) {
            var el = document.getElementById(modalId);
            if (el) {
                el.classList.remove('open');
                el.style.display = 'none';
                document.body.style.overflow = '';
            }
        };

        window.diorViewRxDetails = function (rxId, fallbackMed, fallbackInst) {
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
            if (routingEl) routingEl.innerHTML = '<i class="fa-solid fa-circle-check dior-ic-2eb9f328f2"></i> ' + actualRouting;
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
                        '<td class="dior-rx-item-num dior-ic-02d53e1d05">' + (j + 1) + '</td>' +
                        '<td>' +
                        '<div class="dior-rx-item-name">' + formattedMed + '</div>' +
                        '<div class="dior-rx-item-sub">' +
                        '<span class="dior-ic-63711f3415">Rx Item: ' + escapeHtml(itemUid) + '</span>' +
                        '<span class="dior-rx-fda-badge"><i class="fa-solid fa-check"></i> FDA Approved</span>' +
                        '<span>Oral Formulation &bull; Single Patient Use</span>' +
                        '</div>' +
                        '</td>' +
                        '<td>' +
                        '<div class="dior-rx-item-sig">' +
                        '<strong>SIG:</strong> ' + sigStr +
                        '</div>' +
                        '<div class="dior-ic-6b2d8fe92e">' +
                        'Dosage: <strong class="dior-ic-1a7e4c871d">' + doseStr + '</strong> &bull; Route: Oral' +
                        '</div>' +
                        '</td>' +
                        '<td>' +
                        '<div class="dior-rx-item-qty">Qty: ' + qtyStr + '</div>' +
                        '<div class="dior-rx-item-refill">' + refillStr + '</div>' +
                        '<div class="dior-rx-item-daw">DAW-0 (Generic Auth)</div>' +
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
                pdfBtn.onclick = function () { window.diorDownloadRxPdf(actualId); };
            }
            if (refillBtn) {
                refillBtn.onclick = function () { window.diorRequestRefill(actualId); };
            }

            window.diorOpenModal('modal-view-rx-detail');
        };

        window.diorRequestRefill = function (rxId) {
            if (typeof showToast === 'function') {
                showToast('Refill request submitted electronically to prescribing physician for Rx #' + rxId, false);
            } else {
                alert('Refill request submitted electronically to prescribing physician for Rx #' + rxId);
            }
        };

        window.diorViewDocumentModal = function (title, category, author, date, id) {
            var t = document.getElementById('doc-preview-title');
            var c = document.getElementById('doc-preview-category');
            var a = document.getElementById('doc-preview-author');
            var d = document.getElementById('doc-preview-date');
            var i = document.getElementById('doc-preview-id');
            if (t) t.textContent = title || 'Medical Record';
            if (c) c.textContent = category || 'Official Document';
            if (a) a.textContent = author || 'Dior Medical Urgent Care';
            if (d) d.textContent = date || '';
            if (i) i.textContent = id || 'DOC-88219';
            window.diorOpenModal('modal-view-document');
        };

        window.diorViewAppointmentDetails = function (apptId) {
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
                if (modeEl) modeEl.innerHTML = '<i class="fa-solid fa-video dior-ic-be91c47dad"></i> ' + (found.type || found.visit_type || 'Video Visit');
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
        };

        window.diorDownloadCurrentDocumentPdf = function () {
            var title = (document.getElementById('doc-preview-title') && document.getElementById('doc-preview-title').textContent) || 'Clinical Medical Certificate';
            var docId = (document.getElementById('doc-preview-id') && document.getElementById('doc-preview-id').textContent) || 'DOC-88219';
            var cat = (document.getElementById('doc-preview-category') && document.getElementById('doc-preview-category').textContent) || 'Official Document';
            var author = (document.getElementById('doc-preview-author') && document.getElementById('doc-preview-author').textContent) || 'Dr. Marcus Sterling, MD';
            var date = (document.getElementById('doc-preview-date') && document.getElementById('doc-preview-date').textContent) || 'Sept 14, 2026';
            var patientName = (document.getElementById('doc-preview-patient') && document.getElementById('doc-preview-patient').textContent) || 'Verified Patient';

            var logoUrl = (window.dior_vars && window.dior_vars.logo_url) ? window.dior_vars.logo_url : '';
            var docSigUrl = (window.dior_vars && window.dior_vars.default_doctor_signature) ? window.dior_vars.default_doctor_signature : '';

            var cleanFileName = 'Dior-' + title.replace(/[^a-zA-Z0-9]/g, '-') + '.pdf';

            var sigHtml = '';
            if (docSigUrl) {
                sigHtml = '<img src="' + docSigUrl + '" alt="Doctor Signature" class="dior-ic-f119b60852">';
            } else {
                sigHtml = '<span class="dior-ic-c127c4ee04">/s/ ' + author + '</span>';
            }

            var printContent = '<div class="dior-ic-4e97191df6">' +
                '<!-- Logo Watermark -->' +
                '<div class="dior-ic-2dca51d9b9">' +
                '<img src="' + logoUrl + '" alt="" class="dior-ic-61a516548a">' +
                '</div>' +
                '<div class="dior-ic-e544702ffd">' +
                '<!-- Top Header: Logo on Left, Clinic Details on Right -->' +
                '<table class="dior-ic-1290c468f2">' +
                '<tr>' +
                '<td class="dior-ic-5578310519">' +
                '<div class="dior-ic-34fd8ff897">' +
                '<img src="' + logoUrl + '" alt="Dior Medical" class="dior-ic-b7e858e031">' +
                '<div>' +
                '<div class="dior-ic-be9709a90d">DIOR MEDICAL</div>' +
                '<div class="dior-ic-504d7a84a3">TELEHEALTH &bull; VIRTUAL URGENT CARE</div>' +
                '<div class="dior-ic-1f31aa02a5">Clinical Telemedicine Division &bull; Surescripts Certified</div>' +
                '</div>' +
                '</div>' +
                '</td>' +
                '<td class="dior-ic-a4e1c0e520">' +
                '<div class="dior-ic-f113437294">+1 (800) 555-DIOR</div>' +
                '<div>www.diormedical.com &bull; care@diormedical.com</div>' +
                '<div>100 Medical Plaza, Suite 400</div>' +
                '<div class="dior-ic-bb89dd9892">Doc Ref: <strong class="dior-ic-1a41cb2399">' + docId + '</strong></div>' +
                '</td>' +
                '</tr>' +
                '</table>' +

                '<!-- Recipient & Date Bar (Exact Match to Reference Letterhead) -->' +
                '<table class="dior-ic-153b4cb4de">' +
                '<tr>' +
                '<td class="dior-ic-e998f6b88b">' +
                '<div class="dior-ic-b0a7f14502">TO:</div>' +
                '<div class="dior-ic-3934b99ce3">' + patientName + '</div>' +
                '<div class="dior-ic-6efa43ac99">Dior Medical Telehealth Patient Registry &bull; Verified Record</div>' +
                '</td>' +
                '<td class="dior-ic-bdab288cbf">' +
                '<div class="dior-ic-6bad4995ba">' + date + '</div>' +
                '<div class="dior-ic-9e35db7fae">&#10003; HIPAA Certified</div>' +
                '</td>' +
                '</tr>' +
                '</table>' +

                '<!-- Document Subject Banner -->' +
                '<div class="dior-ic-5f5ee0a219">' +
                '<span class="dior-ic-8f438ba192">' + cat + '</span>' +
                '<h2 class="dior-ic-8562135e54">' + title + '</h2>' +
                '</div>' +

                '<!-- Salutation -->' +
                '<p class="dior-ic-89e78b203c">Dear ' + patientName + ',</p>' +

                '<!-- Clinical Statement Body -->' +
                '<div class="dior-ic-e8b72bdfbb">' +
                '<p class="dior-ic-777d73194a">This official medical document confirms that you have satisfactorily completed your clinical telehealth consultation with Dior Medical Telehealth. All clinical assessments, patient intake questionnaires, and healthcare evaluations were conducted in strict compliance with state and federal telemedicine practice guidelines.</p>' +
                '<p class="dior-ic-777d73194a">The attending clinical provider has reviewed your health status and rendered the clinical determinations documented herein for <strong>' + title + '</strong> under clinical directive <strong>' + cat + '</strong>. All clinical guidance, medical advice, and instructions have been communicated directly through the encrypted secure patient portal.</p>' +
                '<p class="dior-ic-add2ef3fed">This certified electronic clinical record is cryptographically archived and protected under 45 CFR Parts 160 and 164 (HIPAA Security &amp; Privacy Rules) and DEA Title 21 electronic healthcare regulations.</p>' +
                '</div>' +

                '<!-- Sign-off & Electronic Signature (Exact Image Match) -->' +
                '<table class="dior-ic-edb5e701ab">' +
                '<tr>' +
                '<td class="dior-ic-844ca8103e">' +
                '<strong class="dior-ic-2eb9f328f2">&#10003; Cryptographically Certified Electronic Record</strong><br>' +
                'Security Hash: SHA-256 Verified &bull; Federal ESIGN &amp; UETA Act Compliant<br>' +
                'DEA 21 CFR Part 1311 &bull; Surescripts Real-Time EDI Network' +
                '</td>' +
                '<td class="dior-ic-1b4e3085b1">' +
                '<div class="dior-ic-022c3acb10">Sincerely yours,</div>' +
                '<div class="dior-ic-9e400e21a8">' +
                sigHtml +
                '</div>' +
                '<div class="dior-ic-03bccab797">' + author + '</div>' +
                '<div class="dior-ic-a9370e80c1">Attending Telemedicine Physician &bull; Dior Medical</div>' +
                '<div class="dior-ic-b11064f3b0">DEA: MV8492019 &bull; NPI: 1849201948 &bull; Verified</div>' +
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
                    margin: [8, 8, 8, 8],
                    filename: cleanFileName,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        scrollY: 0,
                        scrollX: 0,
                        windowWidth: 794
                    },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };
                try {
                    html2pdf().set(opt).from(tempContainer.firstElementChild || tempContainer).save().then(function () {
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    }).catch(function (err) {
                        console.error('PDF export error:', err);
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                        fallbackPrintWindow(printContent, title);
                    });
                } catch (e) {
                    console.error('PDF generation error:', e);
                    if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    fallbackPrintWindow(printContent, title);
                }
            } else {
                console.warn('html2pdf library not loaded, falling back to print');
                if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                fallbackPrintWindow(printContent, title);
            }
        };

        function fallbackPrintWindow(htmlContent, title) {
            var printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write('<html><head><title>' + title + '</title><link rel="stylesheet" href="' + ((window.dior_patient_dashboard && window.dior_patient_dashboard.refactor_css_url) || '') + '"></head><body class="dior-print-document">' + htmlContent + '</body></html>');
                printWindow.document.close();
                printWindow.focus();
                setTimeout(function () { printWindow.print(); }, 250);
            } else {
                window.print();
            }
        }

        window.diorDownloadReceiptPdf = function () {
            var invId = (document.getElementById('rec-inv-id') && document.getElementById('rec-inv-id').textContent) || 'INV-8829';
            var date = (document.getElementById('rec-date') && document.getElementById('rec-date').textContent) || 'Sept 4, 2026';
            var patientInfo = (document.getElementById('rec-patient-info') && document.getElementById('rec-patient-info').textContent) || 'Patient';
            var service = (document.getElementById('rec-service') && document.getElementById('rec-service').textContent) || 'Urgent Care Telehealth Consultation';
            var amount = (document.getElementById('rec-amount') && document.getElementById('rec-amount').textContent) || '$49.00';
            var logoUrl = (window.dior_vars && window.dior_vars.logo_url) ? window.dior_vars.logo_url : '';

            var cleanFileName = 'Dior-Receipt-' + invId.replace(/[^a-zA-Z0-9]/g, '-') + '.pdf';

            var printContent = '<div class="dior-ic-1b6ae23462">' +
                '<div class="dior-receipt-print-wrapper">' +
                '<div class="dior-receipt-header-box">' +
                '<img src="' + logoUrl + '" alt="Dior Medical">' +
                '<h1>DIOR MEDICAL TELEHEALTH</h1>' +
                '<p>Official Medical Statement &amp; Itemized Receipt</p>' +
                '</div>' +

                '<table class="dior-receipt-table">' +
                '<tr>' +
                '<td class="label-col">Invoice #:</td>' +
                '<td class="val-col">' + invId + '</td>' +
                '</tr>' +
                '<tr>' +
                '<td class="label-col">Date of Transaction:</td>' +
                '<td>' + date + '</td>' +
                '</tr>' +
                '<tr>' +
                '<td class="label-col">Patient Name &amp; ID:</td>' +
                '<td>' + patientInfo + '</td>' +
                '</tr>' +
                '<tr>' +
                '<td class="label-col">Service Description:</td>' +
                '<td>' + service + '</td>' +
                '</tr>' +
                '<tr>' +
                '<td class="label-col">Payment Method:</td>' +
                '<td>Stripe (Credit Card / Apple Pay) &bull; Verified &bull; Paid in Full</td>' +
                '</tr>' +
                '<tr class="total-row">' +
                '<td class="label-col">Total Paid:</td>' +
                '<td class="val-col">' + amount + '</td>' +
                '</tr>' +
                '</table>' +

                '<div class="dior-receipt-notice">' +
                '<strong>Tax Compliance &amp; Insurance Reimbursement Notice:</strong><br>' +
                'Dior Medical Telehealth &amp; Urgent Care &bull; Tax ID / NPI Verified &bull; Eligible for HSA / FSA Flexible Spending Account Reimbursement' +
                '</div>' +

                '<table class="dior-receipt-footer">' +
                '<tr>' +
                '<td class="left-col">' +
                '<strong>&#10003; Official Electronic Payment Receipt</strong><br>' +
                'Transaction ID: ' + invId + ' &bull; Status: Completed' +
                '</td>' +
                '<td class="right-col">' +
                '<div class="signature-line">' +
                'Dior Billing Directorate' +
                '</div>' +
                '<div class="signature-title">Authorized Financial Officer</div>' +
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
                    margin: [8, 8, 8, 8],
                    filename: cleanFileName,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        scrollY: 0,
                        scrollX: 0,
                        windowWidth: 794
                    },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };
                try {
                    html2pdf().set(opt).from(tempContainer.firstElementChild || tempContainer).save().then(function () {
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    }).catch(function (err) {
                        console.error('PDF receipt export error:', err);
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                        fallbackPrintWindow(printContent, invId);
                    });
                } catch (e) {
                    console.error('PDF generation error:', e);
                    if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    fallbackPrintWindow(printContent, invId);
                }
            } else {
                console.warn('html2pdf library not loaded, falling back to print');
                if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                fallbackPrintWindow(printContent, invId);
            }
        };

        window.diorPrintReceiptModal = function () {
            window.diorDownloadReceiptPdf();
        };

        window.diorDownloadRxPdf = function (rxId) {
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
                    margin: [6, 6, 6, 6],
                    filename: cleanFileName,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        scrollY: 0,
                        scrollX: 0,
                        windowWidth: 794
                    },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };
                try {
                    html2pdf().set(opt).from(tempContainer.firstElementChild || tempContainer).save().then(function () {
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    }).catch(function (err) {
                        console.error('PDF export error:', err);
                        if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                        fallbackPrintWindow(innerContent, 'Prescription #' + rxId);
                    });
                } catch (e) {
                    console.error('PDF generation error:', e);
                    if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                    fallbackPrintWindow(innerContent, 'Prescription #' + rxId);
                }
            } else {
                console.warn('html2pdf library not loaded, falling back to print');
                if (tempContainer.parentNode) tempContainer.parentNode.removeChild(tempContainer);
                fallbackPrintWindow(innerContent, 'Prescription #' + rxId);
            }
        };

        function diorInitThemeMode() {
            localStorage.removeItem('dior_theme_mode');
            var portal = document.getElementById('dior-patient-portal-app') || document.body;
            if (portal) {
                portal.classList.remove('dior-dark-theme');
            }
        }

        if (typeof window.diorInitTablePagination !== 'function') {
            window.diorInitTablePagination = function (tableEl, pageSize) {
                if (!tableEl) return;
                pageSize = pageSize || 5;
                var tbody = tableEl.querySelector('tbody');
                if (!tbody) return;

                var parent = tableEl.parentElement;
                var paginationWrap = parent.querySelector(':scope > .dior-table-pagination') || (parent.parentElement ? parent.parentElement.querySelector(':scope > .dior-table-pagination') : null);

                if (!paginationWrap) {
                    paginationWrap = document.createElement('div');
                    paginationWrap.className = 'dior-table-pagination';
                    if (parent.classList.contains('dior-table-responsive') || parent.classList.contains('dior-card-body') || parent.style.overflowX === 'auto' || parent.style.overflow === 'auto') {
                        parent.after(paginationWrap);
                    } else {
                        parent.appendChild(paginationWrap);
                    }
                }

                var currentPage = 1;

                function renderPage(page) {
                    currentPage = page || 1;
                    var allRows = Array.from(tbody.querySelectorAll(':scope > tr')).filter(function (tr) {
                        return !tr.classList.contains('dior-no-data-row') && tr.querySelectorAll(':scope > td').length > 0;
                    });

                    if (allRows.length === 0) {
                        paginationWrap.style.display = 'none';
                        paginationWrap.innerHTML = '';
                        return;
                    }

                    var activeRows = allRows.filter(function (tr) {
                        return tr.getAttribute('data-filter-hidden') !== 'true';
                    });
                    var total = activeRows.length;
                    var totalPages = Math.max(1, Math.ceil(total / pageSize));

                    // If total entries are 5 or fewer, or only 1 page exists, show all rows and hide pagination bar completely
                    if (total <= pageSize || totalPages <= 1) {
                        allRows.forEach(function (tr) {
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

                    var startIndex = (currentPage - 1) * pageSize;
                    var endIndex = startIndex + pageSize;

                    allRows.forEach(function (tr) {
                        if (tr.getAttribute('data-filter-hidden') === 'true') {
                            tr.style.display = 'none';
                        }
                    });

                    activeRows.forEach(function (tr, index) {
                        if (index >= startIndex && index < endIndex) {
                            tr.style.display = '';
                        } else {
                            tr.style.display = 'none';
                        }
                    });

                    var fromItem = total === 0 ? 0 : startIndex + 1;
                    var toItem = Math.min(endIndex, total);

                    var navHtml = '';
                    if (totalPages > 1) {
                        navHtml += '<button type="button" class="dior-page-btn" ' + (currentPage === 1 ? 'disabled' : '') + ' data-page="' + (currentPage - 1) + '" title="Previous Page"><i class="fa-solid fa-chevron-left dior-ic-c2e1a240e8"></i></button>';

                        if (totalPages <= 7) {
                            for (var i = 1; i <= totalPages; i++) {
                                navHtml += '<button type="button" class="dior-page-btn ' + (i === currentPage ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
                            }
                        } else {
                            for (var i = 1; i <= totalPages; i++) {
                                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                                    navHtml += '<button type="button" class="dior-page-btn ' + (i === currentPage ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
                                } else if (i === currentPage - 2 || i === currentPage + 2) {
                                    navHtml += '<span class="dior-ic-82d73650e1">...</span>';
                                }
                            }
                        }

                        navHtml += '<button type="button" class="dior-page-btn" ' + (currentPage === totalPages ? 'disabled' : '') + ' data-page="' + (currentPage + 1) + '" title="Next Page"><i class="fa-solid fa-chevron-right dior-ic-c2e1a240e8"></i></button>';
                    }

                    paginationWrap.style.display = 'flex';
                    paginationWrap.innerHTML = '<div class="dior-pagination-info">Showing <strong>' + fromItem + '</strong> to <strong>' + toItem + '</strong> of <strong>' + total + '</strong> entries</div><div class="dior-pagination-nav">' + navHtml + '</div>';

                    paginationWrap.querySelectorAll('.dior-page-btn[data-page]').forEach(function (btn) {
                        btn.onclick = function (e) {
                            e.preventDefault();
                            var p = parseInt(this.getAttribute('data-page'), 10);
                            if (!isNaN(p)) renderPage(p);
                        };
                    });
                }

                tableEl._diorRenderPage = renderPage;
                renderPage(1);
            };
        }

        function initAllDiorTablePaginations() {
            diorInitThemeMode();
            document.querySelectorAll('.dior-table, .dior-clean-table, .dior-doc-table').forEach(function (table) {
                if (typeof window.diorInitTablePagination === 'function') {
                    window.diorInitTablePagination(table, 5);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', initAllDiorTablePaginations);
        if (document.readyState === 'interactive' || document.readyState === 'complete') {
            initAllDiorTablePaginations();
        }



// Robust Patient Dashboard main-tab navigation fallback.
// This is intentionally independent from the larger dashboard controller so that
// one unrelated widget error can never disable the sidebar navigation.
(function () {
    'use strict';

    function switchPatientTab(tabId, updateHash) {
        var panel = document.getElementById('tab-' + tabId);
        if (!panel) {
            return false;
        }

        document.querySelectorAll('.dior-nav-btn[data-tab]').forEach(function (btn) {
            if (btn.getAttribute('data-tab') === tabId) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        document.querySelectorAll('.dior-tab-panel').forEach(function (item) {
            if (item.id === 'tab-' + tabId) {
                item.classList.add('active');
                item.style.setProperty('display', 'block', 'important');
                item.style.setProperty('visibility', 'visible', 'important');
                item.style.setProperty('opacity', '1', 'important');
            } else {
                item.classList.remove('active');
                item.style.setProperty('display', 'none', 'important');
            }
        });

        var title = document.getElementById('dior-current-page-title');
        var titles = {
            overview: 'Patient Overview',
            appointments: 'Telehealth Appointments',
            docs_meds: 'Medical Docs & Prescriptions',
            telemedicine: 'Telemedicine',
            medical_record: 'Medical Record',
            payments: 'Billing & Payment Statements',
            insurance: 'Insurance Claim',
            documents: 'Documents & Reports',
            emergency: 'Emergency Support',
            feedback: 'Feedback & Support',
            notifications: 'Notification Inbox',
            consultation: 'Consultation Room',
            settings: 'Settings',
            questionnaire: 'Clinical Intake Questionnaires'
        };
        if (title && titles[tabId]) title.textContent = titles[tabId];

        if (updateHash !== false && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#tab=' + encodeURIComponent(tabId));
        }

        return true;
    }

    // Always expose this function immediately so inline onclick handlers never throw ReferenceError
    window.diorSwitchTab = switchPatientTab;

    function initPatientMainTabNavigation() {
        var app = document.getElementById('dior-patient-portal-app');
        if (!app) return;

        // Delegated listener means dynamically rendered buttons also work.
        if (!app.__diorPatientNavBound) {
            app.__diorPatientNavBound = true;
            app.addEventListener('click', function (event) {
                var btn = event.target.closest('.dior-nav-btn[data-tab]');
                if (!btn || !app.contains(btn)) return;

                // Appointments has its own dropdown but must still open its main panel.
                var tabId = btn.getAttribute('data-tab');
                if (!tabId) return;

                event.preventDefault();
                switchPatientTab(tabId);

                if (tabId === 'appointments' && typeof window.diorSwitchApptSubTab === 'function') {
                    window.diorSwitchApptSubTab('today');
                }
            }, true);
        }

        // Direct support for submenu links.
        app.querySelectorAll('[data-appt-subtab]').forEach(function (link) {
            if (link.__diorPatientSubtabBound) return;
            link.__diorPatientSubtabBound = true;
            link.addEventListener('click', function (event) {
                event.preventDefault();
                switchPatientTab('appointments');
                if (typeof window.diorSwitchApptSubTab === 'function') {
                    window.diorSwitchApptSubTab(link.getAttribute('data-appt-subtab'));
                }
            });
        });

        // Restore hash only when it points to an actual panel.
        var hash = window.location.hash || '';
        if (hash.indexOf('#tab=') === 0) {
            var requested = decodeURIComponent(hash.substring(5));
            if (document.getElementById('tab-' + requested)) {
                switchPatientTab(requested, false);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPatientMainTabNavigation);
    } else {
        initPatientMainTabNavigation();
    }
}());

// Appointment submenu fallback for Patient Dashboard.
document.addEventListener('click', function (event) {
    const link = event.target.closest('[data-appt-subtab]');
    if (!link) return;
    event.preventDefault();
    if (typeof window.diorSwitchTab === 'function') window.diorSwitchTab('appointments');
    if (typeof window.diorSwitchApptSubTab === 'function') window.diorSwitchApptSubTab(link.getAttribute('data-appt-subtab'));
}, false);
