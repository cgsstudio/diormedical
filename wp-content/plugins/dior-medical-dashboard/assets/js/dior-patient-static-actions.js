/* Dior Medical — Patient non-overview actions; submitted feedback is stored through authenticated AJAX. */
(function () {
    'use strict';

    function esc(value) {
        return String(value ?? '').replace(/[&<>'"]/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c];
        });
    }

    window.diorOpenStaticModal = function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('is-open');
    };
    window.diorCloseStaticModal = function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('is-open');
    };

    window.diorOpenFeedbackModal = function () {
        var modal = document.getElementById('dior-feedback-modal');
        if (!modal) return;
        document.getElementById('dior-feedback-modal-title').textContent = 'Give Feedback';
        document.getElementById('dior-feedback-subject').value = '';
        document.getElementById('dior-feedback-rating-5').checked = true;
        document.getElementById('dior-feedback-message').value = '';
        diorOpenStaticModal('dior-feedback-modal');
    };

    function appendFeedbackRow(review) {
        var table = document.getElementById('dior-feedback-table');
        if (!table || !table.tBodies[0]) return;
        var tbody = table.tBodies[0];
        var emptyRow = tbody.querySelector('.dior-empty-state');
        if (emptyRow) emptyRow.closest('tr').remove();

        var row = document.createElement('tr');
        row.dataset.feedbackId = String(review.id || '');

        function cellWithText(value, className) {
            var cell = document.createElement('td');
            var text = document.createElement('span');
            text.className = 'cell-text';
            text.textContent = value || '';
            cell.appendChild(text);
            if (className) cell.classList.add(className);
            return cell;
        }

        row.appendChild(cellWithText(review.subject, 'feedback-subject-cell'));
        row.appendChild(cellWithText(review.doctor, 'feedback-doctor-cell'));

        var ratingCell = document.createElement('td');
        var ratingText = document.createElement('span');
        ratingText.className = 'cell-text';
        var star = document.createElement('i');
        star.className = 'fa-solid fa-star';
        star.style.color = '#F59E0B';
        ratingText.appendChild(star);
        ratingText.appendChild(document.createTextNode(' ' + review.rating + '/5'));
        ratingCell.appendChild(ratingText);
        row.appendChild(ratingCell);

        row.appendChild(cellWithText(review.message, 'feedback-message-cell'));

        var dateCell = document.createElement('td');
        var dateContent = document.createElement('div');
        dateContent.className = 'cell-content cell-icon-text';
        var dateIcon = document.createElement('i');
        dateIcon.className = 'fa-regular fa-calendar cell-icon';
        dateContent.appendChild(dateIcon);
        var dateText = document.createElement('span');
        dateText.className = 'cell-text';
        dateText.textContent = review.date || 'Today';
        dateContent.appendChild(dateText);
        dateCell.appendChild(dateContent);
        row.appendChild(dateCell);

        var statusCell = document.createElement('td');
        var statusContent = document.createElement('div');
        statusContent.className = 'cell-content';
        var statusBadge = document.createElement('div');
        statusBadge.className = 'badge-solid col-amber';
        statusBadge.textContent = review.status || 'Pending';
        statusContent.appendChild(statusBadge);
        statusCell.appendChild(statusContent);
        row.appendChild(statusCell);

        var actionCell = document.createElement('td');
        var viewButton = document.createElement('button');
        viewButton.type = 'button';
        viewButton.className = 'dior-feedback-view-action';
        viewButton.setAttribute('aria-label', 'View feedback');
        viewButton.title = 'View feedback';
        viewButton.dataset.feedback = JSON.stringify(review);
        viewButton.innerHTML = '<i class="fa-regular fa-eye"></i>';
        viewButton.addEventListener('click', function () { window.diorViewPatientFeedback(viewButton); });
        actionCell.appendChild(viewButton);
        row.appendChild(actionCell);

        tbody.prepend(row);
        var totalLabel = document.getElementById('dior-feedback-total-count');
        if (totalLabel) totalLabel.textContent = tbody.querySelectorAll('tr[data-feedback-id]').length + ' total feedback records';

        var searchInput = document.querySelector('#tab-feedback .search-input');
        if (searchInput && searchInput.value && typeof window.diorFilterStaticTable === 'function') {
            window.diorFilterStaticTable(searchInput, 'dior-feedback-table');
        }
    }

    window.diorViewPatientFeedback = function (button) {
        var feedback;
        try {
            feedback = JSON.parse(button.dataset.feedback || '{}');
        } catch (error) {
            return;
        }
        var details = [
            'Doctor: ' + (feedback.doctor || 'Care team'),
            'Rating: ' + (feedback.rating || '—') + '/5',
            'Status: ' + (feedback.status || 'Pending'),
            '',
            feedback.message || ''
        ].join('\n');
        if (window.Swal) {
            Swal.fire({
                title: feedback.subject || 'Patient Feedback',
                text: details,
                icon: 'info',
                confirmButtonColor: '#2C6CB1'
            });
        } else {
            window.alert((feedback.subject || 'Patient Feedback') + '\n\n' + details);
        }
    };

    window.diorSaveFeedback = function (event) {
        event.preventDefault();
        var form = document.getElementById('dior-feedback-form');
        var button = form && form.querySelector('button[type="submit"]');
        var cfg = window.dior_vars || window.dior_patient_dashboard || {};
        var ajaxUrl = cfg.ajax_url || '/wp-admin/admin-ajax.php';
        var nonce = cfg.nonce || cfg.portal_nonce || '';
        if (!form || !nonce) {
            var configError = 'Your secure session is missing. Refresh the page and try again.';
            if (window.Swal) Swal.fire({ icon: 'error', title: 'Feedback Not Sent', text: configError });
            else window.alert(configError);
            return false;
        }

        var formData = new FormData(form);
        formData.append('action', 'dior_patient_submit_feedback');
        formData.append('nonce', nonce);
        var originalButton = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
        }

        fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
            .then(function (response) {
                return response.text().then(function (body) {
                    var result;
                    try {
                        result = JSON.parse(body);
                    } catch (error) {
                        throw new Error(response.redirected ? 'Your session expired. Sign in and try again.' : 'The server did not return a valid response. Please try again.');
                    }
                    if (!result.success) throw new Error((result.data && result.data.message) || 'Unable to send feedback.');
                    return result;
                });
            })
            .then(function (result) {
                appendFeedbackRow(result.data.review || {});
                diorCloseStaticModal('dior-feedback-modal');
                form.reset();
                document.getElementById('dior-feedback-rating-5').checked = true;
                if (window.Swal) {
                    Swal.fire({ icon: 'success', title: 'Feedback Sent', text: result.data.message, confirmButtonColor: '#2C6CB1' });
                } else {
                    window.alert(result.data.message);
                }
            })
            .catch(function (error) {
                if (window.Swal) Swal.fire({ icon: 'error', title: 'Feedback Not Sent', text: error.message });
                else window.alert(error.message);
            })
            .finally(function () {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = originalButton;
                }
            });
        return false;
    };

    window.diorDeleteStaticRow = function (button, type) {
        var row = button.closest('tr');
        if (!row) return;
        var label = type === 'document' ? 'document' : (type === 'telemedicine' ? 'telemedicine session' : (type === 'emergency contact' ? 'emergency contact' : 'feedback'));
        if (window.confirm('Delete this ' + label + ' from the design preview?')) row.remove();
    };

    window.diorEditDocument = function (button) {
        var row = button.closest('tr');
        if (!row) return;
        var cells = row.querySelectorAll('td');
        if (cells.length < 3) return;
        var title = window.prompt('Edit document title:', cells[1].innerText.trim());
        if (title !== null && title.trim()) cells[1].querySelector('.cell-text').textContent = title.trim();
    };

    window.diorViewTelemedicine = function (button) {
        var row = button.closest('tr');
        if (!row) return;
        var cells = row.querySelectorAll('td');
        var doctor = cells[2] ? cells[2].innerText.trim() : 'Attending Physician';
        var date = cells[4] ? cells[4].innerText.trim() : '—';
        var time = cells[5] ? cells[5].innerText.trim() : '—';
        window.alert('Telemedicine Session\n\nDoctor: ' + doctor + '\nDate: ' + date + '\nTime: ' + time);
    };

    window.diorFilterStaticTable = function (input, tableId) {
        var query = (input.value || '').toLowerCase();
        var table = document.getElementById(tableId);
        if (!table || !table.tBodies[0]) return;
        Array.prototype.forEach.call(table.tBodies[0].rows, function (row) {
            row.style.display = row.innerText.toLowerCase().indexOf(query) !== -1 ? '' : 'none';
        });
    };

    document.addEventListener('click', function (event) {
        if (event.target.classList.contains('dior-static-modal-backdrop')) event.target.classList.remove('is-open');
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.dior-static-modal-backdrop.is-open').forEach(function (modal) { modal.classList.remove('is-open'); });
    });
}());
