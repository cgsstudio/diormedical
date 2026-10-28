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
    totalPages = Math.max(1, totalPages || 1);
    currentPage = Math.max(1, Math.min(currentPage || 1, totalPages));

    var html = '';

    html += '<button type="button" class="dior-page-btn" ' + (currentPage <= 1 ? 'disabled' : '') +
        ' onclick="' + goFunctionName + '(' + (currentPage - 1) + ')" title="Previous Page" aria-label="Previous Page">' +
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
        ' onclick="' + goFunctionName + '(' + (currentPage + 1) + ')" title="Next Page" aria-label="Next Page">' +
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
    document.addEventListener('click', function (e) {
        const button = e.target.closest('.dior-mark-notification-read');
        if (!button) return;
        const notificationId = button.getAttribute('data-notif-id');
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
                    if (row) {
                        const statusCell = row.querySelector('.dior-dyn-status');
                        if (statusCell) {
                            statusCell.textContent = 'Read';
                            statusCell.className = 'dior-dyn-status dior-dyn-bg-D1FAE5 dior-dyn-color-10B981';
                        }
                        button.remove();
                    }
                    if (typeof diorRenderNotifPagination === 'function') {
                        diorRenderNotifPagination();
                    }
                }
            })
            .catch(function () {});
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
                    html = '<p class="text-secondary">No services are currently available.</p>';
                }
                let el = document.getElementById('treatments-list');
                if (el) el.innerHTML = html;
            })
            .catch(() => {
                let el = document.getElementById('treatments-list');
                if (el) el.innerHTML = '<p class="text-danger">Services could not be loaded. Please try again later.</p>';
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
                    html = '<p class="text-secondary">No providers are currently available for this service.</p>';
                }
                document.getElementById('doctors-list').innerHTML = html;
            })
            .catch(() => {
                document.getElementById('doctors-list').innerHTML = '<p class="text-danger">Providers could not be loaded. Please try again later.</p>';
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

        function renderNoAvailability(message) {
            var slots = document.getElementById('time-slots-list');
            if (slots) slots.innerHTML = '<p class="text-secondary">' + message + '</p>';
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
                    renderNoAvailability('No available time slots were returned for this date. Please choose another date.');
                }
            })
            .catch(() => {
                renderNoAvailability('No available time slots were returned for this date. Please choose another date.');
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
                                                    <p><strong>Patient:</strong> ${diorBookingState.patient.name || '—'}</p>
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
        if (!btn || !msg) return;
        if (!diorBookingState || !diorBookingState.doctorId || !diorBookingState.date || !diorBookingState.time) {
            msg.style.color = '#DC2626';
            msg.innerText = 'Please select an available provider, date, and time slot before booking.';
            return;
        }
        btn.disabled = true;
        btn.innerText = 'Booking Consultation...';

        const formData = new FormData();
        formData.append('action', 'dior_patient_book_appointment');
        formData.append('nonce', diorPortalNonce);
        formData.append('doctorId', diorBookingState.doctorId || '');
        formData.append('doctor_name', diorBookingState.doctorName || '');
        formData.append('date', diorBookingState.date || '');
        formData.append('time', diorBookingState.time || '');
        formData.append('condition', diorBookingState.departmentName || '');
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
                                    // Empty-state placeholder is not an appointment record.
                                    if (row.classList.contains('dior-empty-state')) return false;
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

                            window.diorRenderUpcomingPagination = diorRenderUpcomingPagination;
                            window.diorGoUpcomingPage = diorGoUpcomingPage;
                            window.diorFilterUpcomingAppointments = diorFilterUpcomingAppointments;
                            window.diorToggleSelectAllUpcoming = diorToggleSelectAllUpcoming;
                            window.diorBulkDeleteUpcomingRows = diorBulkDeleteUpcomingRows;
                            window.diorUpdateUpcomingSelectedCount = diorUpdateUpcomingSelectedCount;

                            window.diorRenderPastPagination = diorRenderPastPagination;
                            window.diorGoPastPage = diorGoPastPage;
                            window.diorFilterPastAppointments = diorFilterPastAppointments;

// --- Extracted dashboard script 5 ---
window.diorSwitchApptSubTab = function(targetSubTab) {
                        if (['book', 'today', 'upcoming', 'past'].indexOf(targetSubTab) === -1) targetSubTab = 'today';
                        // The main navigation controller owns panel visibility. Never leave
                        // appointment-only inline styles on the main panel.
                        var appointmentPanel = document.getElementById('tab-appointments');
                        if (appointmentPanel && !appointmentPanel.classList.contains('active') &&
                            typeof window.diorSafeSwitchPatientTab === 'function') {
                            window.diorSafeSwitchPatientTab('appointments', false);
                        }

                        // Switch inner tab
                        var panels = document.querySelectorAll('.dior-subtab-panel');
                        panels.forEach(function(panel) {
                            var isTarget = panel.id === 'dior-subtab-' + targetSubTab;
                            panel.style.setProperty(
                                'display',
                                isTarget ? 'block' : 'none',
                                'important'
                            );
                            if (isTarget) {
                                panel.style.setProperty('visibility', 'visible', 'important');
                                panel.style.setProperty('opacity', '1', 'important');
                            }
                        });

                        // Update dynamic breadcrumb & headers
                        var titles = {
                            'book': 'Book Appointment',
                            'today': "Today's Appointments",
                            'upcoming': 'Upcoming Appointments',
                            'past': 'Past Appointments'
                        };
                        var t = titles[targetSubTab] || "Today's Appointments";
                        var mainTitleEl = document.getElementById('dior-appt-main-title');
                        var crumbTitleEl = document.getElementById('dior-appt-breadcrumb-title');
                        if (mainTitleEl) mainTitleEl.textContent = t;
                        if (crumbTitleEl) crumbTitleEl.textContent = t;

                        // Ensure dropdown is open and highlight active dropdown item
                        var dropdownWrap = document.getElementById('dior-appt-dropdown-wrap');
                        if (dropdownWrap) {
                            dropdownWrap.classList.add('open');
                            var chevron = dropdownWrap.querySelector('.fa-chevron-down');
                            if (chevron) chevron.style.transform = 'rotate(180deg)';
                        }
                        document.querySelectorAll('#dior-appt-dropdown-wrap .dior-nav-dropdown-item').forEach(function(item) {
                            if (item.getAttribute('data-appt-subtab') === targetSubTab) {
                                item.classList.add('active');
                            } else {
                                item.classList.remove('active');
                            }
                        });
                        document.querySelectorAll('#tab-appointments .dior-subtab-pill[data-appt-subtab]').forEach(function(item) {
                            if (item.getAttribute('data-appt-subtab') === targetSubTab) {
                                item.classList.add('active');
                            } else {
                                item.classList.remove('active');
                            }
                        });

                        // If switching to calendar, trigger render
                        if (targetSubTab === 'calendar' && typeof window.diorRenderBootstrapCalendar === 'function') {
                            setTimeout(window.diorRenderBootstrapCalendar, 50);
                        }
                        if (targetSubTab === 'upcoming' && typeof window.diorRenderUpcomingPagination === 'function') {
                            setTimeout(window.diorRenderUpcomingPagination, 30);
                        }
                        if (targetSubTab === 'past' && typeof window.diorRenderPastPagination === 'function') {
                            setTimeout(window.diorRenderPastPagination, 30);
                        }

                        // Remember active subtab in sessionStorage and URL hash so reload works reliably
                        try {
                            sessionStorage.setItem('dior_active_appt_subtab', targetSubTab);
                        } catch(e) {}
                        if (window.history && window.history.replaceState) {
                            window.history.replaceState(null, '', '#tab=appointments&subtab=' + encodeURIComponent(targetSubTab));
                        }

                        // Trigger resize for embedded forms / iframe layout recalculation
                        setTimeout(function() {
                            window.dispatchEvent(new Event('resize'));
                        }, 120);

                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    };

/* ==============================================================
 * DIOR MEDICAL — PURE BOOTSTRAP 5 APPOINTMENT CALENDAR ENGINE
 * ============================================================== */
(function() {
    var calCurrentDate = new Date(); // Month/year being viewed
    var calSelectedDate = null;      // 'YYYY-MM-DD'
    var calViewMode = 'month';       // 'month' or 'agenda'
    var calTypeFilter = 'all';       // 'all', 'video', 'phone', 'clinic'
    var calStatusFilter = 'all';     // 'all', 'confirmed', 'completed', 'cancelled'
    var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    function normalizeYmd(raw) {
        if (!raw) return '';
        var str = String(raw).trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(str)) return str;
        var parts;
        if (/^\d{2}[-\/.]\d{2}[-\/.]\d{4}$/.test(str)) {
            parts = str.split(/[-\/.]/);
            return parts[2] + '-' + parts[0].padStart(2, '0') + '-' + parts[1].padStart(2, '0');
        }
        var d = new Date(str);
        if (!isNaN(d.getTime())) {
            var y = d.getFullYear();
            var m = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + day;
        }
        return '';
    }

    function getAppointments() {
        var raw = [];
        if (Array.isArray(window.dior_patient_appts_data) && window.dior_patient_appts_data.length > 0) {
            raw = window.dior_patient_appts_data;
        } else if (Array.isArray(window.dior_inline_appts_data) && window.dior_inline_appts_data.length > 0) {
            raw = window.dior_inline_appts_data;
        } else if (window.dior_patient_dashboard && Array.isArray(window.dior_patient_dashboard.appointments)) {
            raw = window.dior_patient_dashboard.appointments;
        }
        
        return raw.map(function(item, idx) {
            var dateRaw = item.date || item.appt_date || '';
            var ymd = item.ymd || normalizeYmd(dateRaw);
            var id = item.id || item.appt_uid || '';
            var doc = item.doc || item.provider || item.doctor_name || 'Provider not recorded';
            var spec = item.spec || item.provider_spec || 'Specialty not recorded';
            var time = item.time || item.appt_time || '—';
            var status = item.status || item.appt_status || 'Status unavailable';
            var type = item.type || item.visit_type || 'Visit type not recorded';
            var join_url = item.join_url || '';
            var notes = item.notes || item.condition || 'No notes recorded.';
            return {
                id: id,
                doc: doc,
                spec: spec,
                date: dateRaw,
                ymd: ymd,
                time: time,
                status: status,
                type: type,
                join_url: join_url,
                notes: notes
            };
        });
    }

    function filterAppointments(list) {
        return list.filter(function(a) {
            if (calTypeFilter !== 'all') {
                var t = (a.type || '').toLowerCase();
                if (calTypeFilter === 'video' && t.indexOf('video') === -1 && t.indexOf('telehealth') === -1) return false;
                if (calTypeFilter === 'phone' && t.indexOf('phone') === -1 && t.indexOf('audio') === -1) return false;
                if (calTypeFilter === 'clinic' && t.indexOf('clinic') === -1 && t.indexOf('person') === -1) return false;
            }
            if (calStatusFilter !== 'all') {
                var s = (a.status || '').toLowerCase();
                if (calStatusFilter === 'confirmed' && s !== 'confirmed' && s !== 'scheduled' && s !== 'in-queue') return false;
                if (calStatusFilter === 'completed' && s !== 'completed' && s !== 'done' && s !== 'past') return false;
                if (calStatusFilter === 'cancelled' && s !== 'cancelled') return false;
            }
            return true;
        });
    }

    function formatFullDate(ymd) {
        if (!ymd) return '';
        var parts = ymd.split('-');
        if (parts.length !== 3) return ymd;
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return dayNames[d.getDay()] + ', ' + months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }

    function renderMonthGrid() {
        var gridBody = document.getElementById('dior-bs-cal-grid-body');
        var titleEl = document.getElementById('dior-bs-cal-title');
        if (!gridBody) return;

        var year = calCurrentDate.getFullYear();
        var month = calCurrentDate.getMonth();

        if (titleEl) {
            titleEl.textContent = monthNames[month] + ' ' + year;
        }

        var appts = filterAppointments(getAppointments());
        var apptsByYmd = {};
        appts.forEach(function(a) {
            if (a.ymd) {
                if (!apptsByYmd[a.ymd]) apptsByYmd[a.ymd] = [];
                apptsByYmd[a.ymd].push(a);
            }
        });

        var today = new Date();
        var todayYmd = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
        if (!calSelectedDate) {
            calSelectedDate = todayYmd;
        }

        var firstDayIndex = new Date(year, month, 1).getDay(); // 0 (Sun) to 6 (Sat)
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var daysInPrevMonth = new Date(year, month, 0).getDate();

        var html = '';
        var dayCounter = 1;
        var nextMonthDayCounter = 1;

        var totalCells = (firstDayIndex + daysInMonth > 35) ? 42 : 35;
        for (var i = 0; i < totalCells; i++) {
            if (i % 7 === 0) html += '<tr>';

            var cellYmd = '';
            var cellDayNum = 0;
            var isOtherMonth = false;
            var isToday = false;
            var isSelected = false;

            if (i < firstDayIndex) {
                isOtherMonth = true;
                cellDayNum = daysInPrevMonth - (firstDayIndex - i - 1);
                var prevM = month === 0 ? 12 : month;
                var prevY = month === 0 ? year - 1 : year;
                cellYmd = prevY + '-' + String(prevM).padStart(2, '0') + '-' + String(cellDayNum).padStart(2, '0');
            } else if (dayCounter <= daysInMonth) {
                cellDayNum = dayCounter;
                cellYmd = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(cellDayNum).padStart(2, '0');
                dayCounter++;
            } else {
                isOtherMonth = true;
                cellDayNum = nextMonthDayCounter++;
                var nextM = month === 11 ? 1 : month + 2;
                var nextY = month === 11 ? year + 1 : year;
                cellYmd = nextY + '-' + String(nextM).padStart(2, '0') + '-' + String(cellDayNum).padStart(2, '0');
            }

            if (cellYmd === todayYmd) isToday = true;
            if (cellYmd === calSelectedDate) isSelected = true;

            var cellEvents = apptsByYmd[cellYmd] || [];

            html += '<td class="dior-bs-cal-cell' + 
                    (isOtherMonth ? ' dior-bs-cal-other-month' : '') + 
                    (isToday ? ' dior-bs-cal-today' : '') + 
                    (isSelected ? ' dior-bs-cal-selected' : '') + 
                    '" data-date="' + cellYmd + '">';

            html += '<div class="dior-bs-cal-day-header">';
            html += '<span class="dior-bs-cal-day-num">' + cellDayNum + '</span>';
            if (cellEvents.length > 0) {
                html += '<span class="badge rounded-pill bg-primary" style="font-size: 10px; padding: 2px 5px;">' + cellEvents.length + '</span>';
            }
            html += '</div>';

            html += '<div class="dior-bs-cal-events-wrap">';
            var maxDisplay = 2;
            for (var evIdx = 0; evIdx < Math.min(cellEvents.length, maxDisplay); evIdx++) {
                var apt = cellEvents[evIdx];
                var typeLc = (apt.type || '').toLowerCase();
                var statusLc = (apt.status || '').toLowerCase();
                var evClass = 'event-video';
                var icon = 'fa-video';
                if (statusLc === 'completed') {
                    evClass = 'event-completed';
                    icon = 'fa-check';
                } else if (statusLc === 'cancelled') {
                    evClass = 'event-cancelled';
                    icon = 'fa-ban';
                } else if (typeLc.indexOf('phone') !== -1) {
                    evClass = 'event-phone';
                    icon = 'fa-phone';
                }
                var shortTime = (apt.time || '').split('-')[0].trim();
                html += '<div class="dior-bs-cal-event ' + evClass + '" onclick="event.stopPropagation(); if (typeof diorViewAppointmentDetails === \'function\') diorViewAppointmentDetails(\'' + apt.id + '\');" title="' + (apt.type || '') + ' - ' + (apt.doc || '') + '">';
                html += '<i class="fas ' + icon + ' me-1" style="font-size:10px;"></i>';
                html += '<strong style="font-size:10.5px;">' + shortTime + '</strong>';
                html += '</div>';
            }
            if (cellEvents.length > maxDisplay) {
                html += '<div class="small text-muted fw-bold px-1" style="font-size: 10px;">+' + (cellEvents.length - maxDisplay) + ' more</div>';
            }
            html += '</div>';

            html += '</td>';

            if (i % 7 === 6) html += '</tr>';
        }

        gridBody.innerHTML = html;

        gridBody.querySelectorAll('.dior-bs-cal-cell').forEach(function(cell) {
            cell.addEventListener('click', function() {
                var d = this.getAttribute('data-date');
                if (!d) return;
                calSelectedDate = d;
                gridBody.querySelectorAll('.dior-bs-cal-cell').forEach(function(c) {
                    c.classList.remove('dior-bs-cal-selected');
                });
                this.classList.add('dior-bs-cal-selected');
                renderDaySchedule();
            });
        });

        renderDaySchedule();
    }

    function renderAgendaList() {
        var agendaListEl = document.getElementById('dior-bs-cal-agenda-list');
        if (!agendaListEl) return;

        var appts = filterAppointments(getAppointments());
        appts.sort(function(a, b) {
            return (a.ymd || '').localeCompare(b.ymd || '');
        });

        if (appts.length === 0) {
            agendaListEl.innerHTML = '<div class="card border-0 bg-light p-4 text-center text-muted rounded-4">' +
                '<i class="far fa-calendar-xmark fs-2 text-secondary mb-2"></i>' +
                '<p class="mb-2 fw-medium">No appointments match the selected filters.</p>' +
                '<div><button type="button" class="btn btn-primary btn-sm rounded-pill" onclick="diorOpenDocBookerModal();"><i class="fas fa-calendar-plus me-1"></i> Book New Consultation</button></div>' +
                '</div>';
            return;
        }

        var html = '';
        var currentGroupDate = '';

        appts.forEach(function(apt) {
            if (apt.ymd !== currentGroupDate) {
                currentGroupDate = apt.ymd;
                html += '<div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top">';
                html += '<span class="badge bg-primary rounded-pill px-3 py-1.5 fw-semibold fs-7"><i class="far fa-calendar me-1"></i> ' + formatFullDate(apt.ymd) + '</span>';
                html += '<span class="text-muted small">(' + apt.ymd + ')</span>';
                html += '</div>';
            }

            var statusLc = (apt.status || '').toLowerCase();
            var statusBadge = 'bg-primary-subtle text-primary';
            if (statusLc === 'confirmed') statusBadge = 'bg-success-subtle text-success';
            else if (statusLc === 'completed') statusBadge = 'bg-secondary-subtle text-secondary';
            else if (statusLc === 'cancelled') statusBadge = 'bg-danger-subtle text-danger';

            var isVideo = (apt.type || '').toLowerCase().indexOf('video') !== -1;
            var canJoin = isVideo && (statusLc === 'confirmed' || statusLc === 'scheduled' || statusLc === 'in-queue');

            html += '<div class="card border-0 shadow-sm rounded-3 p-3 bg-white">';
            html += '<div class="d-flex flex-wrap align-items-center justify-content-between gap-3">';
            
            html += '<div class="d-flex align-items-center gap-3">';
            html += '<div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 44px; height: 44px; background: linear-gradient(135deg, #2865a5 0%, #1e4e80 100%); font-size: 14px;">';
            html += '<i class="fas ' + (isVideo ? 'fa-video' : 'fa-user-doctor') + '"></i>';
            html += '</div>';
            html += '<div>';
            html += '<div class="d-flex align-items-center gap-2">';
            html += '<h6 class="mb-0 fw-bold text-dark">' + apt.doc + '</h6>';
            html += '<span class="badge ' + statusBadge + ' rounded-pill px-2 py-0.5 small">' + apt.status + '</span>';
            html += '</div>';
            html += '<div class="text-muted small mt-0.5">' + apt.spec + ' &bull; <span class="text-dark fw-semibold">' + apt.type + '</span></div>';
            html += '<div class="text-primary small fw-semibold mt-1"><i class="far fa-clock me-1"></i>' + apt.time + '</div>';
            html += '</div>';
            html += '</div>';

            html += '<div class="d-flex flex-wrap align-items-center gap-2">';
            if (canJoin && apt.join_url) {
                html += '<a href="' + apt.join_url + '" target="_blank" class="btn btn-success btn-sm rounded-pill px-3 fw-bold d-inline-flex align-items-center gap-1 shadow-sm">';
                html += '<i class="fas fa-video"></i> Join Visit';
                html += '</a>';
            }
            html += '<button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="if (typeof diorViewAppointmentDetails === \'function\') diorViewAppointmentDetails(\'' + apt.id + '\');">';
            html += '<i class="fas fa-eye me-1"></i> Details';
            html += '</button>';
            html += '<button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="if (typeof diorOpenRescheduleModal === \'function\') diorOpenRescheduleModal(\'' + apt.id + '\', \'' + apt.date + '\');">';
            html += '<i class="fas fa-arrows-rotate me-1"></i> Reschedule';
            html += '</button>';
            html += '</div>';

            html += '</div>';
            html += '</div>';
        });

        agendaListEl.innerHTML = html;
    }

    function renderDaySchedule() {
        var headerEl = document.getElementById('dior-bs-cal-selected-header');
        var countEl = document.getElementById('dior-bs-cal-selected-count');
        var bodyEl = document.getElementById('dior-bs-cal-selected-body');
        if (!bodyEl) return;

        var ymd = calSelectedDate;
        if (headerEl) {
            headerEl.textContent = formatFullDate(ymd);
        }

        var appts = filterAppointments(getAppointments()).filter(function(a) {
            return a.ymd === ymd;
        });

        if (countEl) {
            countEl.textContent = appts.length + ' Visit' + (appts.length === 1 ? '' : 's');
        }

        if (appts.length === 0) {
            bodyEl.innerHTML = '<div class="text-center py-4 text-muted">' +
                '<div class="mb-3" style="font-size: 2.2rem; color: #cbd5e1;"><i class="far fa-calendar-xmark"></i></div>' +
                '<h6 class="text-dark fw-bold mb-1">No Consultations</h6>' +
                '<p class="small text-muted mb-3">There are no appointments scheduled for this date.</p>' +
                '<button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold shadow-sm" onclick="diorOpenDocBookerModal();">' +
                '<i class="fas fa-calendar-plus me-1"></i> Schedule Consultation' +
                '</button>' +
                '</div>';
            return;
        }

        var html = '<div class="d-flex flex-column gap-3">';
        appts.forEach(function(apt) {
            var statusLc = (apt.status || '').toLowerCase();
            var statusBadge = 'bg-primary-subtle text-primary';
            if (statusLc === 'confirmed') statusBadge = 'bg-success-subtle text-success';
            else if (statusLc === 'completed') statusBadge = 'bg-secondary-subtle text-secondary';
            else if (statusLc === 'cancelled') statusBadge = 'bg-danger-subtle text-danger';

            var isVideo = (apt.type || '').toLowerCase().indexOf('video') !== -1;
            var canJoin = isVideo && (statusLc === 'confirmed' || statusLc === 'scheduled' || statusLc === 'in-queue');

            html += '<div class="card border border-light-subtle rounded-3 p-3 bg-white shadow-sm">';
            
            html += '<div class="d-flex align-items-center justify-content-between mb-2">';
            html += '<span class="badge ' + statusBadge + ' rounded-pill px-2.5 py-1 small fw-bold">' + apt.status + '</span>';
            html += '<span class="text-muted small fw-medium" style="font-size:11px;"><i class="fas fa-hashtag me-0.5"></i>' + apt.id + '</span>';
            html += '</div>';

            html += '<div class="d-flex align-items-center gap-2 mb-2">';
            html += '<div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 38px; height: 38px; background: #2865a5; font-size: 13px; flex-shrink: 0;">';
            html += '<i class="fas ' + (isVideo ? 'fa-video' : 'fa-user-doctor') + '"></i>';
            html += '</div>';
            html += '<div class="lh-sm overflow-hidden">';
            html += '<h6 class="mb-0 text-dark fw-bold text-truncate" style="font-size: 13.5px;">' + apt.doc + '</h6>';
            html += '<small class="text-muted text-truncate d-block" style="font-size: 11.5px;">' + apt.spec + '</small>';
            html += '</div>';
            html += '</div>';

            html += '<div class="p-2 rounded-2 bg-light mb-3 text-secondary small" style="font-size: 12px;">';
            html += '<div class="d-flex align-items-center justify-content-between mb-1">';
            html += '<span><i class="far fa-clock me-1 text-primary"></i> <strong>Time:</strong></span>';
            html += '<span class="text-dark fw-bold">' + apt.time + '</span>';
            html += '</div>';
            html += '<div class="d-flex align-items-center justify-content-between">';
            html += '<span><i class="fas fa-stethoscope me-1 text-info"></i> <strong>Type:</strong></span>';
            html += '<span class="text-dark fw-medium">' + apt.type + '</span>';
            html += '</div>';
            html += '</div>';

            if (canJoin && apt.join_url) {
                html += '<a href="' + apt.join_url + '" target="_blank" class="btn btn-success btn-sm w-100 fw-bold mb-2 shadow-sm d-flex align-items-center justify-content-center gap-1.5 py-1.5">';
                html += '<i class="fas fa-video"></i> Join Telehealth Room';
                html += '</a>';
            }

            html += '<div class="d-flex gap-2">';
            html += '<button type="button" class="btn btn-outline-primary btn-sm flex-fill" onclick="if (typeof diorViewAppointmentDetails === \'function\') diorViewAppointmentDetails(\'' + apt.id + '\');">';
            html += '<i class="fas fa-eye me-1"></i> Details';
            html += '</button>';
            html += '<button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="if (typeof diorOpenRescheduleModal === \'function\') diorOpenRescheduleModal(\'' + apt.id + '\', \'' + apt.date + '\');">';
            html += '<i class="fas fa-arrows-rotate me-1"></i> Reschedule';
            html += '</button>';
            html += '</div>';

            html += '</div>';
        });
        html += '</div>';

        bodyEl.innerHTML = html;
    }

    window.diorInitBootstrapCalendar = function() {
        var container = document.getElementById('dior-subtab-calendar');
        if (!container) return;

        var prevBtn = document.getElementById('dior-bs-cal-prev');
        if (prevBtn && !prevBtn.__diorBound) {
            prevBtn.__diorBound = true;
            prevBtn.addEventListener('click', function() {
                calCurrentDate.setMonth(calCurrentDate.getMonth() - 1);
                renderMonthGrid();
            });
        }

        var nextBtn = document.getElementById('dior-bs-cal-next');
        if (nextBtn && !nextBtn.__diorBound) {
            nextBtn.__diorBound = true;
            nextBtn.addEventListener('click', function() {
                calCurrentDate.setMonth(calCurrentDate.getMonth() + 1);
                renderMonthGrid();
            });
        }

        var todayBtn = document.getElementById('dior-bs-cal-today');
        if (todayBtn && !todayBtn.__diorBound) {
            todayBtn.__diorBound = true;
            todayBtn.addEventListener('click', function() {
                calCurrentDate = new Date();
                var today = new Date();
                calSelectedDate = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
                renderMonthGrid();
            });
        }

        var typeSelect = document.getElementById('dior-bs-cal-type-filter');
        if (typeSelect && !typeSelect.__diorBound) {
            typeSelect.__diorBound = true;
            typeSelect.addEventListener('change', function() {
                calTypeFilter = this.value;
                if (calViewMode === 'month') renderMonthGrid();
                else renderAgendaList();
            });
        }

        var statusSelect = document.getElementById('dior-bs-cal-status-filter');
        if (statusSelect && !statusSelect.__diorBound) {
            statusSelect.__diorBound = true;
            statusSelect.addEventListener('change', function() {
                calStatusFilter = this.value;
                if (calViewMode === 'month') renderMonthGrid();
                else renderAgendaList();
            });
        }

        var viewMonthBtn = document.getElementById('dior-bs-cal-view-month');
        var viewAgendaBtn = document.getElementById('dior-bs-cal-view-agenda');
        var monthWrap = document.getElementById('dior-bs-cal-month-wrap');
        var agendaWrap = document.getElementById('dior-bs-cal-agenda-wrap');

        if (viewMonthBtn && !viewMonthBtn.__diorBound) {
            viewMonthBtn.__diorBound = true;
            viewMonthBtn.addEventListener('click', function() {
                calViewMode = 'month';
                viewMonthBtn.classList.add('btn-primary', 'active');
                viewMonthBtn.classList.remove('btn-outline-secondary');
                if (viewAgendaBtn) {
                    viewAgendaBtn.classList.remove('btn-primary', 'active');
                    viewAgendaBtn.classList.add('btn-outline-secondary');
                }
                if (monthWrap) monthWrap.style.display = 'block';
                if (agendaWrap) agendaWrap.style.display = 'none';
                renderMonthGrid();
            });
        }

        if (viewAgendaBtn && !viewAgendaBtn.__diorBound) {
            viewAgendaBtn.__diorBound = true;
            viewAgendaBtn.addEventListener('click', function() {
                calViewMode = 'agenda';
                viewAgendaBtn.classList.add('btn-primary', 'active');
                viewAgendaBtn.classList.remove('btn-outline-secondary');
                if (viewMonthBtn) {
                    viewMonthBtn.classList.remove('btn-primary', 'active');
                    viewMonthBtn.classList.add('btn-outline-secondary');
                }
                if (monthWrap) monthWrap.style.display = 'none';
                if (agendaWrap) agendaWrap.style.display = 'block';
                renderAgendaList();
            });
        }

        renderMonthGrid();
    };

    window.diorRenderBootstrapCalendar = function() {
        if (calViewMode === 'month') renderMonthGrid();
        else renderAgendaList();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.diorInitBootstrapCalendar);
    } else {
        setTimeout(window.diorInitBootstrapCalendar, 60);
    }
})();

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
                    b.classList.remove('is-active', 'btn-primary', 'shadow-sm', 'text-white', 'btn-light');
                    b.classList.add('btn-outline-primary');
                });
                button.classList.remove('btn-outline-primary', 'btn-light');
                button.classList.add('is-active', 'btn-primary', 'shadow-sm', 'text-white');
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
    // The current consultation UI has no persisted patient/provider messaging endpoint.
    // Never append a message only to the browser and imply it was delivered or saved.
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'info',
            title: 'Secure chat unavailable',
            text: 'Messaging is not connected to a saved consultation yet. Your message has not been sent.'
        });
    } else {
        window.alert('Messaging is not connected to a saved consultation yet. Your message has not been sent.');
    }
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
        diorUpdateNotifSelectionCount();
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

    function diorToggleSelectAllNotifs(masterCb) {
        const isChecked = masterCb.checked;
        const activeRows = diorGetNotifActiveRows();
        activeRows.forEach(row => {
            const cb = row.querySelector('.dior-notif-row-cb, input[type="checkbox"]');
            if (cb) cb.checked = isChecked;
        });
        diorUpdateNotifSelectionCount();
    }
    window.diorToggleSelectAllNotifs = diorToggleSelectAllNotifs;

    function diorUpdateNotifSelectionCount() {
        const activeRows = diorGetNotifActiveRows();
        const selected = activeRows.filter(row => {
            const cb = row.querySelector('.dior-notif-row-cb, input[type="checkbox"]');
            return cb && cb.checked;
        }).length;
        const countEl = document.getElementById('dior-notif-page-count');
        if (countEl) {
            countEl.innerText = `${selected} selected / ${activeRows.length} total`;
        }
        const masterCb = document.getElementById('dior-notif-select-all');
        if (masterCb) {
            masterCb.checked = activeRows.length > 0 && selected === activeRows.length;
        }
    }
    window.diorUpdateNotifSelectionCount = diorUpdateNotifSelectionCount;

    function diorBulkDeleteNotifications(btn) {
        const table = document.getElementById('dior-notif-table');
        if (!table) return;
        const checkedRows = Array.from(table.querySelectorAll('#dior-notif-tbody tr')).filter(row => {
            const cb = row.querySelector('.dior-notif-row-cb, input[type="checkbox"]');
            return cb && cb.checked;
        });

        if (checkedRows.length === 0) {
            Swal.fire({ icon: 'info', title: 'No Notifications Selected', text: 'Please select at least one notification to delete.' });
            return;
        }

        const notifIds = checkedRows.map(r => r.getAttribute('data-notif-id')).filter(Boolean);

        const doDelete = function() {
            const ajaxUrl = (window.dior_patient_dashboard && window.dior_patient_dashboard.ajax_url) || (window.dior_vars && window.dior_vars.ajax_url) || '';
            const nonce = (window.dior_patient_dashboard && window.dior_patient_dashboard.portal_nonce) || (window.dior_vars && window.dior_vars.nonce) || '';

            const body = new URLSearchParams({
                action: 'dior_patient_bulk_delete_notifications',
                nonce: nonce
            });
            notifIds.forEach(id => body.append('notif_ids[]', id));

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    checkedRows.forEach(r => r.remove());
                    const masterCb = document.getElementById('dior-notif-select-all');
                    if (masterCb) masterCb.checked = false;
                    diorRenderNotifPagination();
                    diorUpdateNotifSelectionCount();
                    if (window.Swal) {
                        Swal.fire({ position: 'top-end', icon: 'success', title: data.data?.message || 'Notifications Deleted', showConfirmButton: false, timer: 1500 });
                    }
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: (data && data.data && data.data.message) || 'Failed to delete notifications.' });
                }
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error deleting notifications.' });
            });
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Delete Notifications?',
                text: `You are about to delete ${checkedRows.length} selected notification(s).`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete!'
            }).then(result => {
                if (result.isConfirmed) {
                    doDelete();
                }
            });
        }
    }
    window.diorBulkDeleteNotifications = diorBulkDeleteNotifications;

    function diorMarkAllNotificationsRead() {
        const rows = Array.from(document.querySelectorAll('#dior-notif-tbody tr[data-notif-id]'));
        if (rows.length === 0) {
            Swal.fire({ icon: 'info', title: 'No Notifications', text: 'There are no notifications to mark as read.' });
            return;
        }

        const ajaxUrl = (window.dior_patient_dashboard && window.dior_patient_dashboard.ajax_url) || (window.dior_vars && window.dior_vars.ajax_url) || '';
        const nonce = (window.dior_patient_dashboard && window.dior_patient_dashboard.portal_nonce) || (window.dior_vars && window.dior_vars.nonce) || '';

        const body = new URLSearchParams({
            action: 'dior_patient_mark_all_notifications_read',
            nonce: nonce
        });

        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                rows.forEach(r => {
                    const statusCell = r.querySelector('.dior-dyn-status');
                    if (statusCell) {
                        statusCell.textContent = 'Read';
                        statusCell.className = 'dior-dyn-status dior-dyn-bg-D1FAE5 dior-dyn-color-10B981';
                    }
                    const readBtn = r.querySelector('.dior-mark-notification-read');
                    if (readBtn) readBtn.remove();
                });
                const badges = document.querySelectorAll('.unread-badge-count');
                badges.forEach(b => b.style.display = 'none');
                if (window.Swal) {
                    Swal.fire({ position: 'top-end', icon: 'success', title: 'All marked as read', showConfirmButton: false, timer: 1500 });
                }
            }
        })
        .catch(() => {});
    }
    window.diorMarkAllNotificationsRead = diorMarkAllNotificationsRead;

    function diorRefreshNotifications() {
        const tbody = document.getElementById('dior-notif-tbody');
        const refreshIcon = document.querySelector('.action-btn-info i');
        if (refreshIcon) refreshIcon.classList.add('fa-spin');

        const ajaxUrl = (window.dior_patient_dashboard && window.dior_patient_dashboard.ajax_url) || (window.dior_vars && window.dior_vars.ajax_url) || '';
        const nonce = (window.dior_patient_dashboard && window.dior_patient_dashboard.portal_nonce) || (window.dior_vars && window.dior_vars.nonce) || '';

        const body = new URLSearchParams({
            action: 'dior_patient_get_live_notifications',
            nonce: nonce
        });

        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (refreshIcon) refreshIcon.classList.remove('fa-spin');
            if (data && data.success && data.data && data.data.full_html && tbody) {
                tbody.innerHTML = data.data.full_html;
                diorNotifCurrentPage = 1;
                diorRenderNotifPagination();
                diorUpdateNotifSelectionCount();
                if (window.Swal) {
                    Swal.fire({ position: 'top-end', icon: 'success', title: 'Notifications Refreshed', showConfirmButton: false, timer: 1000 });
                }
            } else {
                window.location.reload();
            }
        })
        .catch(() => {
            if (refreshIcon) refreshIcon.classList.remove('fa-spin');
            window.location.reload();
        });
    }
    window.diorRefreshNotifications = diorRefreshNotifications;

    let notifSortDirection = {};
    function diorSortNotifTable(colIndex) {
        const tbody = document.getElementById('dior-notif-tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr:not(.dior-empty-state)'));
        if (rows.length === 0) return;

        notifSortDirection[colIndex] = !notifSortDirection[colIndex];
        const asc = notifSortDirection[colIndex];

        rows.sort((a, b) => {
            const cellA = (a.querySelectorAll('td')[colIndex]?.innerText || '').trim();
            const cellB = (b.querySelectorAll('td')[colIndex]?.innerText || '').trim();
            return asc ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
        });

        rows.forEach(r => tbody.appendChild(r));
        diorRenderNotifPagination();
    }
    window.diorSortNotifTable = diorSortNotifTable;

    // View Notification Details modal / alert
    document.addEventListener('click', function(e) {
        const viewBtn = e.target.closest('.dior-view-notif-btn');
        if (!viewBtn) return;
        const notifId = viewBtn.getAttribute('data-notif-id');
        const title = viewBtn.getAttribute('data-notif-title') || 'Notification';
        const message = viewBtn.getAttribute('data-notif-message') || '';
        const date = viewBtn.getAttribute('data-notif-date') || '';
        const type = viewBtn.getAttribute('data-notif-type') || 'Info';
        const actionUrl = viewBtn.getAttribute('data-notif-action') || '';

        // Auto mark as read on view
        if (notifId) {
            const row = viewBtn.closest('tr');
            const readBtn = row ? row.querySelector('.dior-mark-notification-read') : null;
            if (readBtn) readBtn.click();
        }

        const hasAction = actionUrl && actionUrl.trim() !== '';
        if (window.Swal) {
            Swal.fire({
                title: `<span style="font-size:18px;font-weight:700;color:#1e293b;">${title}</span>`,
                html: `
                    <div style="text-align:left;font-size:14px;color:#475569;line-height:1.6;margin-top:10px;">
                        <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
                            <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:12px;background:#e6f0fa;color:#1e4e80;">${type}</span>
                            <span style="font-size:12px;color:#94a3b8;">${date}</span>
                        </div>
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;color:#334155;white-space:pre-wrap;">${message}</div>
                    </div>
                `,
                icon: 'info',
                showCancelButton: hasAction,
                confirmButtonText: hasAction ? 'Go to Page' : 'Close',
                cancelButtonText: 'Close',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#64748b'
            }).then(result => {
                if (result.isConfirmed && hasAction) {
                    if (actionUrl.startsWith('#tab=')) {
                        const tabName = actionUrl.replace('#tab=', '');
                        if (typeof diorSwitchTab === 'function') {
                            diorSwitchTab(tabName);
                        } else {
                            window.location.hash = actionUrl;
                        }
                    } else {
                        window.location.href = actionUrl;
                    }
                }
            });
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        diorRenderNotifPagination();
        diorUpdateNotifSelectionCount();
    });
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        diorRenderNotifPagination();
        diorUpdateNotifSelectionCount();
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
            var rawDoc = found ? (found.prescribed_by || 'Provider not recorded') : 'Provider not recorded';
            var cleanDoc = rawDoc.replace(/^(Dr\.?\s*)+/i, 'Dr. ').replace(/\s*\(NPI:[^)]*\)/i, '').replace(/,\s*MD\b/i, '').replace(/\s*\bMD\b/i, '').trim();
            var actualDate = found ? (found.date_prescribed || found.date || '—') : '—';
            var actualRouting = found ? (found.routing_status || 'Status unavailable') : 'Status unavailable';
            var actualPharm = found ? (found.pharmacy || 'Pharmacy not recorded') : 'Pharmacy not recorded';

            if (idEl) idEl.textContent = 'Rx #' + actualId;
            if (barcodeTxt) barcodeTxt.textContent = '*RX-' + String(actualId).replace(/[^0-9a-zA-Z]/g, '') + '*';
            if (docEl) docEl.textContent = cleanDoc;
            if (docHeaderEl) docHeaderEl.textContent = cleanDoc;
            if (docCellEl) docCellEl.textContent = cleanDoc;
            if (dateEl) dateEl.textContent = actualDate;
            var padDateEl = document.getElementById('pat-modal-rx-pad-date');
            if (padDateEl) padDateEl.textContent = actualDate;

            // Dynamically populate patient demographics (Patient Name, Date, Age, Gender, Weight, Diagnosis)
            var patNameEl = document.getElementById('pat-modal-rx-pat-name');
            var patAgeEl = document.getElementById('pat-modal-rx-pat-age');
            var patGenderEl = document.getElementById('pat-modal-rx-pat-gender');
            var patWeightEl = document.getElementById('pat-modal-rx-pat-weight');
            var patDiagEl = document.getElementById('pat-modal-rx-diagnosis');

            var profileObj = (window.dior_vars && window.dior_vars.patient_profile) || {};
            var patName = (found && (found.patient_name || found.patient)) || profileObj.full_name || (window.dior_patient_dashboard && window.dior_patient_dashboard.patient && window.dior_patient_dashboard.patient.name) || 'Patient';
            var patGender = (found && found.patient_gender) || profileObj.gender || '—';
            var rawWeight = (found && found.patient_weight) || profileObj.weight || '';
            var patWeight = rawWeight ? (isFinite(rawWeight) ? rawWeight + ' kg' : rawWeight) : '—';
            var patDiag = (found && (found.diagnosis || found.condition || found.title)) || 'Diagnosis not recorded';
            if (patDiag === 'General Medical Prescription' || patDiag === 'Prescription') {
                patDiag = 'Diagnosis not recorded';
            }

            var patAge = '—';
            var dob = (found && found.patient_dob) || profileObj.dob;
            if (dob) {
                var d = new Date(dob);
                if (!isNaN(d.getTime())) {
                    var ageDiff = new Date().getFullYear() - d.getFullYear();
                    patAge = ageDiff + ' years';
                }
            } else if (found && found.patient_age) {
                patAge = found.patient_age;
            }

            if (patNameEl) patNameEl.textContent = patName;
            if (patAgeEl) patAgeEl.textContent = patAge;
            if (patGenderEl) patGenderEl.textContent = patGender ? (patGender.charAt(0).toUpperCase() + patGender.slice(1)) : '—';
            if (patWeightEl) patWeightEl.textContent = patWeight;
            if (patDiagEl) patDiagEl.textContent = patDiag;

            if (routingEl) routingEl.innerHTML = '<i class="fa-solid fa-circle-check dior-ic-2eb9f328f2"></i> ' + actualRouting;
            if (statusBadge) statusBadge.textContent = actualRouting;
            if (pharmNameEl) pharmNameEl.textContent = actualPharm;
            if (sigNameEl) sigNameEl.textContent = '/s/ ' + cleanDoc + ', MD';
            if (sigDateEl) sigDateEl.textContent = actualDate && actualDate !== '—' ? 'Issued ' + actualDate : 'Issue date unavailable';

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
                var rawMedText = (found ? (found.medication || found.name) : '') || fallbackMed || '';
                var rawDosageText = (found ? (found.dosage || found.dose) : '') || '';
                var rawInstText = (found ? (found.instructions || found.notes) : '') || fallbackInst || '';

                var medParts = rawMedText.split(/;\s*|\r\n|\n/).map(function(s) { return s.trim(); }).filter(Boolean);
                var doseParts = rawDosageText.split(/\|\s*|;\s*|\r\n|\n/).map(function(s) { return s.trim(); }).filter(Boolean);
                var instParts = rawInstText.split(/\|\|\s*|\r\n|\n/).map(function(s) { return s.trim(); }).filter(Boolean);

                if (medParts.length > 1) {
                    items = medParts.map(function(m, idx) {
                        return {
                            id: actualId + '-' + (idx + 1),
                            medication: m.replace(/^\d+\.\s*/, ''),
                            dosage: doseParts[idx] ? doseParts[idx].replace(/^\d+\.\s*/, '') : rawDosageText,
                            instructions: instParts[idx] ? instParts[idx].replace(/^\d+\.\s*/, '') : rawInstText,
                            quantity: (found ? found.quantity : '') || '—',
                            refills: (found ? found.refills : '') || '—'
                        };
                    });
                } else if (rawMedText || rawDosageText || rawInstText) {
                    items = [{
                        id: actualId + '-1',
                        medication: rawMedText,
                        dosage: rawDosageText,
                        instructions: rawInstText,
                        quantity: (found ? found.quantity : '') || '—',
                        refills: (found ? found.refills : '') || '—'
                    }];
                }
            }

            if (tableBody) {
                var html = '';
                var adviceNotes = [];
                for (var j = 0; j < items.length; j++) {
                    var it = items[j];
                    var itemUid = (it && it.id) ? it.id : (actualId + '-' + (j + 1));
                    var medName = escapeHtml(it.medication);
                    var doseStr = escapeHtml(it.dosage);
                    var sigStr = escapeHtml(it.instructions || it.dosage || 'Instructions not recorded.');
                    var qtyStr = escapeHtml(it.quantity || '—');
                    var refillStr = escapeHtml(it.refills || '—');

                    if (it.notes && it.notes !== sigStr) {
                        adviceNotes.push(escapeHtml(it.notes));
                    }

                    var formattedMed = medName || 'Medication name not recorded';

                    html += '<tr>' +
                        '<td class="text-secondary small fw-bold text-center align-top pt-3" style="width: 32px;">' + (j + 1) + '</td>' +
                        '<td class="align-top py-2.5">' +
                        '<div class="fw-bold text-dark fs-6 mb-1">' + formattedMed + '</div>' +
                        '<div class="d-flex align-items-center gap-2 flex-wrap small">' +
                        '<span class="badge bg-light text-secondary border">Rx Item: ' + escapeHtml(itemUid) + '</span>' +

                        '</div>' +
                        '</td>' +
                        '<td class="align-top py-2.5">' +
                        '<div class="small mb-1"><strong class="text-primary text-uppercase me-1">SIG:</strong> <span class="text-dark fw-medium">' + sigStr + '</span></div>' +
                        '<div class="small text-muted">Dosage: <strong class="text-dark">' + doseStr + '</strong></div>' +
                        '</td>' +
                        '<td class="align-top py-2.5 text-nowrap">' +
                        '<div class="fw-bold text-dark small mb-0.5">Qty: ' + qtyStr + '</div>' +
                        '<div class="small text-secondary mb-1">' + refillStr + '</div>' +

                        '</td>' +
                        '</tr>';
                }
                tableBody.innerHTML = html || '<tr><td colspan="4" class="text-center py-4 text-secondary">Prescription details are not available for this record.</td></tr>';

                if (adviceEl) {
                    var adviceText = (found && (found.instructions || found.notes)) || adviceNotes.join('\n');
                    adviceEl.textContent = adviceText || 'No prescription-specific instructions recorded.';
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
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'Refill request unavailable',
                    text: 'A saved refill-request endpoint is not connected to this dashboard. No refill request was submitted.'
                });
            } else {
                window.alert('A saved refill-request endpoint is not connected to this dashboard. No refill request was submitted.');
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
            if (a) a.textContent = author || 'Author not recorded';
            if (d) d.textContent = date || '';
            if (i) i.textContent = id || '—';
            window.diorOpenModal('modal-view-document');
        };

        window.diorCancelPatientAppointment = function (apptId, button) {
            apptId = String(apptId || '').trim();
            if (!apptId) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Unable to cancel', text: 'This appointment has no valid database ID.' });
                } else {
                    window.alert('This appointment has no valid database ID.');
                }
                return;
            }

            var sendCancellation = function () {
                if (button) button.disabled = true;
                var formData = new FormData();
                formData.append('action', 'dior_patient_cancel_appointment');
                formData.append('nonce', typeof getNonce === 'function' ? getNonce() : '');
                formData.append('appt_id', apptId);
                formData.append('reason', 'Cancelled by patient');

                fetch(typeof getAjaxUrl === 'function' ? getAjaxUrl() : '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                })
                .then(function (response) { return response.json(); })
                .then(function (response) {
                    if (!response || !response.success) {
                        if (button) button.disabled = false;
                        var message = response && response.data && response.data.message
                            ? response.data.message
                            : 'The appointment could not be cancelled. Please refresh and try again.';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Cancellation failed', text: message });
                        } else {
                            window.alert(message);
                        }
                        return;
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'success', title: 'Appointment cancelled', text: 'Your appointment status has been updated.', timer: 1400, showConfirmButton: false })
                            .then(function () { window.location.reload(); });
                    } else {
                        window.alert('Appointment cancelled successfully.');
                        window.location.reload();
                    }
                })
                .catch(function () {
                    if (button) button.disabled = false;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Request failed', text: 'The server could not confirm cancellation. Please check your connection and try again.' });
                    } else {
                        window.alert('The server could not confirm cancellation. Please try again.');
                    }
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cancel appointment?',
                    text: 'This will update the appointment record in the database.',
                    showCancelButton: true,
                    confirmButtonText: 'Cancel appointment',
                    cancelButtonText: 'Keep appointment',
                    confirmButtonColor: '#dc3545'
                }).then(function (result) {
                    if (result.isConfirmed) sendCancellation();
                });
            } else if (window.confirm('Cancel this appointment?')) {
                sendCancellation();
            }
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
                if (provEl) provEl.textContent = found.provider || found.doctor_name || 'Provider not recorded';
                if (specEl) specEl.textContent = found.provider_spec || found.provider_title || 'Specialty not recorded';
                if (statusBadge) {
                    var st = found.status || found.appt_status || 'Status unavailable';
                    statusBadge.textContent = st;
                    statusBadge.className = 'dior-st ' + (st === 'Confirmed' || st === 'Completed' ? 'ok' : (st === 'Cancelled' ? 'error' : 'pending'));
                }
                if (dtEl) dtEl.textContent = (found.date || found.appt_date || '') + ' ' + (found.time || found.appt_time || '');
                if (modeEl) modeEl.textContent = found.type || found.visit_type || 'Visit type not recorded';
                if (condEl) condEl.textContent = found.condition || found.condition_name || 'Condition not recorded';
                if (notesEl) notesEl.textContent = found.notes || 'No appointment notes recorded.';

                var joinUrl = found.join_url || '';
                var isLive = !!joinUrl && ['Confirmed', 'Scheduled', 'In-Queue', 'In Progress'].indexOf(found.status) !== -1;
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
            var docId = (document.getElementById('doc-preview-id') && document.getElementById('doc-preview-id').textContent) || '';
            var cat = (document.getElementById('doc-preview-category') && document.getElementById('doc-preview-category').textContent) || 'Official Document';
            var author = (document.getElementById('doc-preview-author') && document.getElementById('doc-preview-author').textContent) || 'Author not recorded';
            var date = (document.getElementById('doc-preview-date') && document.getElementById('doc-preview-date').textContent) || '';
            var patientName = (document.getElementById('doc-preview-patient') && document.getElementById('doc-preview-patient').textContent) || 'Patient';

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
            var service = (document.getElementById('rec-service') && document.getElementById('rec-service').textContent) || 'Service not recorded';
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
            if (!rxId) return;

            var cleanId = String(rxId).trim();
            var baseUrl = window.location.origin;
            if (window.location.pathname.indexOf('/diormedical') !== -1) {
                baseUrl += '/diormedical';
            }
            if (window.dior_ajax_object && window.dior_ajax_object.site_url) {
                baseUrl = window.dior_ajax_object.site_url;
            }
            var pdfUrl = baseUrl + '/?dior_action=view_rx_pdf&rx_id=' + encodeURIComponent(cleanId);

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Generating Prescription PDF',
                    html: '<div class="py-2"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted small">Loading high-resolution e-prescription...</div></div>',
                    showConfirmButton: false,
                    allowOutsideClick: false
                });
            }

            var iframe = document.createElement('iframe');
            iframe.style.position = 'fixed';
            iframe.style.left = '-9999px';
            iframe.style.top = '-9999px';
            iframe.style.width = '794px';
            iframe.style.height = '1123px';
            iframe.style.border = 'none';
            iframe.style.visibility = 'hidden';

            var fallbackDownload = function () {
                if (typeof Swal !== 'undefined') Swal.close();
                if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
                window.open(pdfUrl + '&download=1', '_blank');
            };

            var timer = setTimeout(fallbackDownload, 10000);

            iframe.onload = function () {
                try {
                    setTimeout(function () {
                        try {
                            clearTimeout(timer);
                            if (iframe.contentWindow && typeof iframe.contentWindow.downloadAsPdf === 'function') {
                                iframe.contentWindow.downloadAsPdf().then(function () {
                                    if (typeof Swal !== 'undefined') Swal.close();
                                    setTimeout(function () { if (iframe.parentNode) iframe.parentNode.removeChild(iframe); }, 1500);
                                }).catch(function (err) {
                                    console.error('PDF error in iframe:', err);
                                    fallbackDownload();
                                });
                            } else {
                                fallbackDownload();
                            }
                        } catch (ex) {
                            console.error('Iframe access failed:', ex);
                            fallbackDownload();
                        }
                    }, 800);
                } catch (e) {
                    clearTimeout(timer);
                    fallbackDownload();
                }
            };

            iframe.src = pdfUrl;
            document.body.appendChild(iframe);
        };

        window.diorDownloadInvoicePdf = function (invId) {
            var cleanId = invId ? String(invId).trim() : 'INV-2026-001';
            var baseUrl = window.location.origin;
            if (window.location.pathname.indexOf('/diormedical') !== -1) {
                baseUrl += '/diormedical';
            }
            if (window.dior_ajax_object && window.dior_ajax_object.site_url) {
                baseUrl = window.dior_ajax_object.site_url;
            }
            var pdfUrl = baseUrl + '/?dior_action=view_invoice_pdf&inv_id=' + encodeURIComponent(cleanId);

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Generating Invoice PDF',
                    html: '<div class="py-2"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted small">Loading invoice and official watermark...</div></div>',
                    showConfirmButton: false,
                    allowOutsideClick: false
                });
            }

            var iframe = document.createElement('iframe');
            iframe.style.position = 'fixed';
            iframe.style.left = '-9999px';
            iframe.style.top = '-9999px';
            iframe.style.width = '1060px';
            iframe.style.height = '1120px';
            iframe.style.border = 'none';
            iframe.style.visibility = 'hidden';

            var fallbackDownload = function () {
                if (typeof Swal !== 'undefined') Swal.close();
                if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
                window.open(pdfUrl + '&download=1', '_blank');
            };

            var timer = setTimeout(fallbackDownload, 10000);

            iframe.onload = function () {
                try {
                    setTimeout(function () {
                        try {
                            clearTimeout(timer);
                            if (iframe.contentWindow && typeof iframe.contentWindow.downloadAsPdf === 'function') {
                                iframe.contentWindow.downloadAsPdf().then(function () {
                                    if (typeof Swal !== 'undefined') Swal.close();
                                    setTimeout(function () { if (iframe.parentNode) iframe.parentNode.removeChild(iframe); }, 1500);
                                }).catch(function (err) {
                                    console.error('Invoice PDF error in iframe:', err);
                                    fallbackDownload();
                                });
                            } else {
                                fallbackDownload();
                            }
                        } catch (ex) {
                            console.error('Iframe access failed:', ex);
                            fallbackDownload();
                        }
                    }, 800);
                } catch (e) {
                    clearTimeout(timer);
                    fallbackDownload();
                }
            };

            iframe.src = pdfUrl;
            document.body.appendChild(iframe);
        };

        window.diorViewInvoicePdf = function (invId) {
            var cleanId = invId ? String(invId).trim() : 'INV-2026-001';
            var baseUrl = window.location.origin;
            if (window.location.pathname.indexOf('/diormedical') !== -1) {
                baseUrl += '/diormedical';
            }
            if (window.dior_ajax_object && window.dior_ajax_object.site_url) {
                baseUrl = window.dior_ajax_object.site_url;
            }
            window.open(baseUrl + '/?dior_action=view_invoice_pdf&inv_id=' + encodeURIComponent(cleanId), '_blank');
        };

        window.diorPrintInvoicePdf = function (invId) {
            window.diorDownloadInvoicePdf(invId);
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
        if (typeof window.diorSafeSwitchPatientTab === 'function') {
            return window.diorSafeSwitchPatientTab(tabId, updateHash);
        }
        var panel = document.getElementById('tab-' + tabId);
        if (!panel) {
            panel = document.getElementById(tabId);
        }
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
            if (item === panel) {
                item.classList.add('active');
                item.removeAttribute('hidden');
                item.style.setProperty('display', 'block', 'important');
                item.style.setProperty('visibility', 'visible', 'important');
                item.style.setProperty('opacity', '1', 'important');
            } else {
                item.classList.remove('active');
                item.setAttribute('hidden', 'hidden');
                item.style.setProperty('display', 'none', 'important');
                item.style.setProperty('visibility', 'hidden', 'important');
                item.style.setProperty('opacity', '0', 'important');
            }
        });

        // Sync subtab pills in in-page navigation bars
        document.querySelectorAll('.dior-subtab-pill[data-patient-tab]').forEach(function(pill) {
            if (pill.getAttribute('data-patient-tab') === tabId) {
                pill.classList.add('active');
            } else {
                pill.classList.remove('active');
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

        if (tabId !== 'appointments') {
            var w = document.getElementById('dior-appt-dropdown-wrap');
            if (w) {
                w.classList.remove('open');
                var ch = w.querySelector('.fa-chevron-down');
                if (ch) ch.style.transform = 'rotate(0deg)';
            }
        }

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

                var tabId = btn.getAttribute('data-tab');
                if (!tabId) return;

                event.preventDefault();
                event.stopImmediatePropagation();

                if (tabId === 'appointments') {
                    var now = Date.now();
                    if (now - (window._diorLastApptToggleTime || 0) < 300) {
                        return;
                    }
                    if (typeof window.diorToggleApptDropdown === 'function') {
                        window.diorToggleApptDropdown(event);
                    } else {
                        window._diorLastApptToggleTime = now;
                        var w = document.getElementById('dior-appt-dropdown-wrap');
                        if (w) {
                            var isOpen = w.classList.contains('open');
                            if (isOpen) {
                                w.classList.remove('open');
                                var ch = w.querySelector('.fa-chevron-down');
                                if (ch) ch.style.transform = 'rotate(0deg)';
                            } else {
                                w.classList.add('open');
                                var ch = w.querySelector('.fa-chevron-down');
                                if (ch) ch.style.transform = 'rotate(180deg)';
                                switchPatientTab('appointments');
                                if (typeof window.diorSwitchApptSubTab === 'function') {
                                    window.diorSwitchApptSubTab('today');
                                }
                            }
                        }
                    }
                } else {
                    switchPatientTab(tabId);
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
            var raw = hash.substring(5);
            var requested = raw;
            var requestedSub = null;
            if (raw.indexOf('&subtab=') !== -1) {
                var p = raw.split('&subtab=');
                requested = decodeURIComponent(p[0]);
                requestedSub = decodeURIComponent(p[1]);
            } else {
                requested = decodeURIComponent(raw);
            }
            if (document.getElementById('tab-' + requested)) {
                switchPatientTab(requested, false);
                if (requested === 'appointments' && typeof window.diorSwitchApptSubTab === 'function') {
                    var s = requestedSub || (function(){
                        try { return sessionStorage.getItem('dior_active_appt_subtab'); } catch(e){ return null; }
                    })() || 'today';
                    window.diorSwitchApptSubTab(s);
                }
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


/* v17.2.1: prescription details use a clean native Bootstrap modal. */
(function () {
  'use strict';
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch];
    });
  }
  window.diorViewRxDetails = function (rxId, fallbackMed, fallbackInst) {
    var list = Array.isArray(window.dior_patient_rxs_data) ? window.dior_patient_rxs_data : [];
    var found = null;
    list.some(function (rx) {
      var ids = [rx.id].concat(Array.isArray(rx.raw_ids) ? rx.raw_ids : []).map(String);
      if (ids.some(function (id) { return id.toLowerCase() === String(rxId).toLowerCase(); })) { found = rx; return true; }
      return false;
    });
    var rx = found || {};
    var title = rx.title || rx.name || rx.medication || fallbackMed || 'Prescription';
    var instruction = rx.instructions || rx.sig || fallbackInst || 'No additional instructions available.';
    var prescribedBy = rx.prescribed_by || rx.doctor_name || '—';
    var date = rx.date_prescribed || rx.date || rx.created_at || '—';
    var status = rx.status || rx.routing_status || '—';
    var modal = document.getElementById('dior-bootstrap-rx-details-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'dior-bootstrap-rx-details-modal';
      modal.className = 'modal fade';
      modal.tabIndex = -1;
      modal.setAttribute('aria-labelledby', 'dior-bootstrap-rx-details-title');
      modal.setAttribute('aria-hidden', 'true');
      modal.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><div class="modal-header"><div><h5 class="modal-title" id="dior-bootstrap-rx-details-title">Prescription Details</h5><div class="small text-muted">Review the medication information below</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-4">Prescription ID</dt><dd class="col-sm-8" data-rx-field="id"></dd><dt class="col-sm-4">Medication</dt><dd class="col-sm-8 fw-semibold" data-rx-field="title"></dd><dt class="col-sm-4">Prescribed by</dt><dd class="col-sm-8" data-rx-field="doctor"></dd><dt class="col-sm-4">Date prescribed</dt><dd class="col-sm-8" data-rx-field="date"></dd><dt class="col-sm-4">Status</dt><dd class="col-sm-8" data-rx-field="status"></dd><dt class="col-sm-4">Instructions</dt><dd class="col-sm-8" data-rx-field="instructions"></dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
      document.body.appendChild(modal);
    }
    modal.querySelector('[data-rx-field="id"]').textContent = rx.id || rxId || '—';
    modal.querySelector('[data-rx-field="title"]').textContent = title;
    modal.querySelector('[data-rx-field="doctor"]').textContent = prescribedBy;
    modal.querySelector('[data-rx-field="date"]').textContent = date;
    modal.querySelector('[data-rx-field="status"]').textContent = status;
    modal.querySelector('[data-rx-field="instructions"]').textContent = instruction;
    if (window.bootstrap && window.bootstrap.Modal) window.bootstrap.Modal.getOrCreateInstance(modal).show();
    else { modal.classList.add('show'); modal.style.display = 'block'; modal.removeAttribute('aria-hidden'); }
  };
})();

/* Health Goals Modal & Dynamic Actions (100% Bootstrap 5) */
(function () {
  'use strict';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch];
    });
  }

  function getAjaxUrl() {
    return (window.dior_patient_dashboard && window.dior_patient_dashboard.ajax_url) || (window.dior_vars && window.dior_vars.ajax_url) || '/wp-admin/admin-ajax.php';
  }

  function getNonce() {
    return (window.dior_patient_dashboard && window.dior_patient_dashboard.portal_nonce) || (window.dior_vars && window.dior_vars.nonce) || '';
  }

  function updateModalGoalsList(goals) {
    var modalList = document.getElementById('dior-modal-goals-list');
    if (!modalList) return;
    if (!goals || goals.length === 0) {
      modalList.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted small">No goals recorded yet.</td></tr>';
      return;
    }
    var html = '';
    goals.forEach(function (g, idx) {
      var pct = Math.min(100, Math.max(0, parseInt(g.pct || 0, 10)));
      html += '<tr>';
      html += '  <td class="ps-3 py-2 fw-semibold text-dark small">' + esc(g.name) + '</td>';
      html += '  <td class="py-2 text-secondary small">' + esc(g.val) + '</td>';
      html += '  <td class="py-2 text-center text-secondary small">' + pct + '%</td>';
      html += '  <td class="pe-3 py-2 text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0 border-0 text-decoration-none dior-modal-del-goal-btn fw-semibold small" data-goal-index="' + idx + '">Delete</button></td>';
      html += '</tr>';
    });
    modalList.innerHTML = html;
  }

  function renderGoalsList(goals) {
    var container = document.getElementById('dior-goals-card-body');
    if (!container) return;
    if (!goals || goals.length === 0) {
      container.innerHTML = '<div class="text-center py-4 text-muted small" id="dior-no-goals-msg">No health goals set yet. Click the <strong>+</strong> button above to add your goals.</div>';
      updateModalGoalsList(goals);
      return;
    }

    var html = '';
    goals.forEach(function (goal, idx) {
      var pct = Math.min(100, Math.max(0, parseInt(goal.pct || 0, 10)));
      html += '<div class="goal mb-2" data-goal-index="' + idx + '">';
      html += '  <div class="d-flex justify-content-between align-items-center mb-1">';
      html += '    <span class="fw-bold text-dark small">' + esc(goal.name) + '</span>';
      html += '    <span class="text-secondary small">' + esc(goal.val) + '</span>';
      html += '  </div>';
      html += '  <div class="progress dior-progress-sm rounded-pill bg-light">';
      html += '    <div class="progress-bar rounded-pill dior-progress-orange" role="progressbar" style="width: ' + pct + '%;" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100"></div>';
      html += '  </div>';
      html += '</div>';
    });
    container.innerHTML = html;
    updateModalGoalsList(goals);
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Quick goal pills in modal
    document.querySelectorAll('.dior-quick-goal').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var name = this.getAttribute('data-name');
        var val = this.getAttribute('data-val');
        var pct = this.getAttribute('data-pct');
        var nameInput = document.getElementById('goal_name');
        var valInput = document.getElementById('goal_val');
        var pctInput = document.getElementById('goal_pct');
        if (nameInput && name) nameInput.value = name;
        if (valInput && val) valInput.value = val;
        if (pctInput && pct) pctInput.value = pct;
      });
    });

    // Form submission
    var form = document.getElementById('dior-form-add-health-goal');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var alertBox = document.getElementById('dior-goal-form-alert');
        if (alertBox) { alertBox.classList.add('d-none'); alertBox.textContent = ''; }

        var submitBtn = document.getElementById('dior-btn-save-goal');
        var origText = submitBtn ? submitBtn.innerHTML : 'Save Vitals & Goal';
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
        }

        var heightInput = document.getElementById('goal_height');
        var weightInput = document.getElementById('goal_weight');
        var goalNameInput = document.getElementById('goal_name');
        var goalValInput = document.getElementById('goal_val');
        var goalPctInput = document.getElementById('goal_pct');

        var height = (heightInput ? heightInput.value : '').trim();
        var weight = (weightInput ? weightInput.value : '').trim();
        var goalName = (goalNameInput ? goalNameInput.value : '').trim();
        var goalVal = (goalValInput ? goalValInput.value : '').trim();
        var goalPct = parseInt(goalPctInput ? goalPctInput.value : '50', 10);

        var body = new URLSearchParams({
          action: 'dior_patient_add_health_goal',
          nonce: getNonce(),
          height: height,
          weight: weight,
          goal_name: goalName,
          goal_val: goalVal,
          goal_pct: isNaN(goalPct) ? 50 : Math.min(100, Math.max(0, goalPct))
        });

        fetch(getAjaxUrl(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString()
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
          }
          if (data && data.success) {
            // Clear goal specific fields
            if (goalNameInput) goalNameInput.value = '';
            if (goalValInput) goalValInput.value = '';
            if (goalPctInput) goalPctInput.value = '50';

            var modalEl = document.getElementById('diorHealthGoalsModal');
            if (modalEl && window.bootstrap && window.bootstrap.Modal) {
              var inst = window.bootstrap.Modal.getInstance(modalEl);
              if (inst) inst.hide();
            }

            // Update patient card metrics dynamically
            if (data.data && typeof data.data.height_display !== 'undefined') {
              var hElem = document.getElementById('dior-patient-metric-height');
              if (hElem) hElem.textContent = data.data.height_display;
            }
            if (data.data && typeof data.data.weight_display !== 'undefined') {
              var wElem = document.getElementById('dior-patient-metric-weight');
              if (wElem) wElem.textContent = data.data.weight_display;
            }
            if (heightInput && data.data && typeof data.data.height !== 'undefined') {
              heightInput.value = data.data.height;
            }
            if (weightInput && data.data && typeof data.data.weight !== 'undefined') {
              weightInput.value = data.data.weight;
            }

            renderGoalsList(data.data.goals || []);

            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'success',
                title: 'Saved Successfully!',
                text: (data.data && data.data.message) ? data.data.message : 'Your vitals and health goals have been updated.',
                timer: 1800,
                showConfirmButton: false
              });
            }
          } else {
            var msg = (data && data.data && data.data.message) ? data.data.message : 'Failed to save health goal.';
            if (alertBox) {
              alertBox.textContent = msg;
              alertBox.classList.remove('d-none');
            } else if (typeof Swal !== 'undefined') {
              Swal.fire({ icon: 'error', title: 'Error', text: msg });
            } else {
              alert(msg);
            }
          }
        })
        .catch(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
          }
          if (alertBox) {
            alertBox.textContent = 'Network or server error. Please try again.';
            alertBox.classList.remove('d-none');
          }
        });
      });
    }

    // Delete goal event delegation (handles modal delete buttons & card delete buttons)
    document.addEventListener('click', function (e) {
      var delBtn = e.target.closest('.dior-delete-goal-btn, .dior-modal-del-goal-btn');
      if (!delBtn) return;
      e.preventDefault();
      var goalIndex = delBtn.getAttribute('data-goal-index');
      if (goalIndex === null || goalIndex === undefined) return;

      var doDelete = function () {
        var body = new URLSearchParams({
          action: 'dior_patient_delete_health_goal',
          nonce: getNonce(),
          goal_index: goalIndex
        });

        fetch(getAjaxUrl(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString()
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.success) {
            renderGoalsList(data.data.goals || []);
          }
        });
      };

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Remove Goal?',
          text: 'Are you sure you want to remove this health goal?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc3545',
          confirmButtonText: 'Yes, remove',
          cancelButtonText: 'Cancel'
        }).then(function (result) {
          if (result.isConfirmed) {
            doDelete();
          }
        });
      } else if (confirm('Are you sure you want to remove this health goal?')) {
        doDelete();
      }
    });
  });
})();
