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
        document.getElementById('dior-feedback-id').value = '';
        document.getElementById('dior-feedback-subject').value = '';
        document.getElementById('dior-feedback-rating-5').checked = true;
        document.getElementById('dior-feedback-message').value = '';
        document.getElementById('dior-feedback-submit-btn').innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Feedback';
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

    window.diorEditPatientFeedback = function (button) {
        var feedbackId = button.dataset.feedbackId;
        var feedback;
        try {
            feedback = JSON.parse(button.dataset.feedback || '{}');
        } catch (error) {
            return;
        }

        var modal = document.getElementById('dior-feedback-modal');
        if (!modal) return;

        document.getElementById('dior-feedback-modal-title').textContent = 'Edit Feedback';
        document.getElementById('dior-feedback-id').value = feedbackId || '';
        document.getElementById('dior-feedback-subject').value = feedback.subject || '';
        var rating = parseInt(feedback.rating) || 5;
        var ratingInput = document.getElementById('dior-feedback-rating-' + rating);
        if (ratingInput) ratingInput.checked = true;
        document.getElementById('dior-feedback-message').value = feedback.message || '';
        document.getElementById('dior-feedback-submit-btn').innerHTML = '<i class="fa-solid fa-save"></i> Update Feedback';

        diorOpenStaticModal('dior-feedback-modal');
    };

    window.diorDeletePatientFeedback = function (button) {
        var feedbackId = button.dataset.feedbackId;
        if (!feedbackId) return;

        var row = button.closest('tr');
        if (!row) return;

        if (window.Swal) {
            Swal.fire({
                title: 'Delete Feedback?',
                text: 'Are you sure you want to delete this feedback? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#94A3B8',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    diorConfirmDeleteFeedback(feedbackId, row);
                }
            });
        } else if (window.confirm('Are you sure you want to delete this feedback?')) {
            diorConfirmDeleteFeedback(feedbackId, row);
        }
    };

    function diorConfirmDeleteFeedback(feedbackId, row) {
        var cfg = window.dior_vars || window.dior_patient_dashboard || {};
        var ajaxUrl = cfg.ajax_url || '/wp-admin/admin-ajax.php';
        var nonce = cfg.nonce || cfg.portal_nonce || '';

        if (!nonce) {
            if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: 'Session expired. Please refresh the page.' });
            else window.alert('Session expired. Please refresh the page.');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'dior_patient_delete_feedback');
        formData.append('nonce', nonce);
        formData.append('feedback_id', feedbackId);

        fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (result.success) {
                    row.remove();
                    var totalLabel = document.getElementById('dior-feedback-total-count');
                    if (totalLabel) {
                        var tbody = document.querySelector('#dior-feedback-table tbody');
                        var count = tbody ? tbody.querySelectorAll('tr[data-feedback-id]').length : 0;
                        totalLabel.textContent = count + ' total feedback records';
                    }
                    if (window.Swal) {
                        Swal.fire({
                            position: "top-end",
                            icon: "success",
                            title: "Feedback Deleted",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        window.alert('Feedback deleted successfully.');
                    }
                } else {
                    if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: result.data.message || 'Failed to delete feedback.' });
                    else window.alert(result.data.message || 'Failed to delete feedback.');
                }
            })
            .catch(function (error) {
                if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                else window.alert('Network error. Please try again.');
            });
    }

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

        var feedbackId = document.getElementById('dior-feedback-id').value;
        var isEdit = feedbackId && feedbackId !== '';

        var formData = new FormData(form);
        formData.append('action', isEdit ? 'dior_patient_update_feedback' : 'dior_patient_submit_feedback');
        formData.append('nonce', nonce);
        var originalButton = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + (isEdit ? 'Updating...' : 'Sending...');
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
                if (isEdit) {
                    var row = document.querySelector('tr[data-feedback-id="' + feedbackId + '"]');
                    if (row) {
                        var cells = row.querySelectorAll('td');
                        if (cells.length >= 5) {
                            cells[0].querySelector('.cell-text').textContent = result.data.review.subject || 'Patient Feedback';
                            cells[2].querySelector('.cell-text').innerHTML = '<i class="fa-solid fa-star" style="color:#F59E0B;"></i> ' + (result.data.review.rating || 5) + '/5';
                            cells[3].querySelector('.cell-text').textContent = result.data.review.message || '';
                            var viewButton = row.querySelector('.dior-feedback-view-action');
                            if (viewButton) {
                                viewButton.dataset.feedback = JSON.stringify({
                                    subject: result.data.review.subject,
                                    doctor: result.data.review.doctor,
                                    rating: result.data.review.rating,
                                    message: result.data.review.message,
                                    status: result.data.review.status
                                });
                            }
                        }
                    }
                } else {
                    appendFeedbackRow(result.data.review || {});
                }
                diorCloseStaticModal('dior-feedback-modal');
                form.reset();
                document.getElementById('dior-feedback-id').value = '';
                document.getElementById('dior-feedback-rating-5').checked = true;
                document.getElementById('dior-feedback-submit-btn').innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Feedback';
                document.getElementById('dior-feedback-modal-title').textContent = 'Give Feedback';
                if (window.Swal) {
                    Swal.fire({
                        position: "top-end",
                        icon: "success",
                        title: isEdit ? "Feedback Updated" : "Feedback Sent",
                        showConfirmButton: false,
                        timer: 1500
                    });
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
        if (type !== 'document') {
            var label = type === 'telemedicine' ? 'telemedicine session' : (type === 'emergency contact' ? 'emergency contact' : 'feedback');
            if (window.confirm('Delete this ' + label + '?')) row.remove();
            return;
        }
        var docId = row.getAttribute('data-doc-id') || '';
        if (!docId) return;
        var cfg = window.dior_vars || window.dior_patient_dashboard || {};
        var fd = new FormData();
        fd.append('action', 'dior_delete_document');
        fd.append('nonce', cfg.nonce || cfg.portal_nonce || '');
        fd.append('doc_id', docId);
        fd.append('patient_id', cfg.current_user_id || (cfg.patient && cfg.patient.id) || '');
        var doDelete = function(){
            fetch(cfg.ajax_url || '/wp-admin/admin-ajax.php', {method:'POST', credentials:'same-origin', body:fd})
                .then(function(r){return r.json();})
                .then(function(result){
                    if(!result.success) throw new Error((result.data && result.data.message) || 'Could not delete document.');
                    row.remove();
                    if(window.Swal) Swal.fire({position:'top-end',icon:'success',title:'Document Deleted',showConfirmButton:false,timer:1400});
                }).catch(function(err){
                    if(window.Swal) Swal.fire({icon:'error',title:'Delete Failed',text:err.message}); else window.alert(err.message);
                });
        };
        if(window.Swal){
            Swal.fire({title:'Delete Document?',text:'This document will be removed from your medical records.',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Delete'}).then(function(r){if(r.isConfirmed) doDelete();});
        } else if(window.confirm('Delete this document?')) doDelete();
    };

    window.diorOpenDocumentUploadModal = function(){
        var modal=document.getElementById('dior-document-modal'); if(!modal) return;
        var form=document.getElementById('dior-document-form'); if(form) form.reset();
        document.getElementById('dior-document-id').value='';
        document.getElementById('dior-document-modal-title').textContent='Add Medical Document';
        document.getElementById('dior-document-save-btn').innerHTML='<i class="fa-solid fa-cloud-arrow-up"></i> Upload Document';
        document.getElementById('dior-document-file-wrap').style.display='block';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden','false');
    };

    window.diorEditDocument = function(button){
        var row=button.closest('tr'); if(!row) return;
        var modal=document.getElementById('dior-document-modal'); if(!modal) return;
        var id=row.getAttribute('data-doc-id') || '';
        var title=button.getAttribute('data-doc-title') || (row.cells[1] ? row.cells[1].innerText.trim() : 'Medical Document');
        var category=button.getAttribute('data-doc-category') || (row.cells[2] ? row.cells[2].innerText.trim() : 'Clinical Record');
        document.getElementById('dior-document-id').value=id;
        document.getElementById('dior-document-title').value=title;
        document.getElementById('dior-document-category').value=category;
        document.getElementById('dior-document-modal-title').textContent='Update Medical Document';
        document.getElementById('dior-document-save-btn').innerHTML='<i class="fa-solid fa-save"></i> Save Changes';
        document.getElementById('dior-document-file-wrap').style.display='none';
        modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false');
    };

    window.diorCloseDocumentModal = function(){
        var modal=document.getElementById('dior-document-modal'); if(!modal) return;
        modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true');
    };

    document.addEventListener('submit', function(event){
        if(event.target.id !== 'dior-document-form') return;
        event.preventDefault();
        var form=event.target, cfg=window.dior_vars || window.dior_patient_dashboard || {};
        var id=document.getElementById('dior-document-id').value || '';
        var btn=document.getElementById('dior-document-save-btn');
        var fd=new FormData(form);
        fd.append('action', id ? 'dior_update_document' : 'dior_upload_document');
        fd.append('nonce', cfg.nonce || cfg.portal_nonce || '');
        fd.append('patient_id', cfg.current_user_id || '');
        btn.disabled=true;
        fetch(cfg.ajax_url || '/wp-admin/admin-ajax.php',{method:'POST',credentials:'same-origin',body:fd})
            .then(function(r){return r.json();})
            .then(function(result){
                if(!result.success) throw new Error((result.data && result.data.message) || 'Unable to save document.');
                if(id){
                    var row=document.querySelector('#dior-docs-tbody tr[data-doc-id="'+CSS.escape(id)+'"]');
                    if(row){
                        var title=result.data.title || fd.get('doc_title');
                        var category=result.data.category || fd.get('doc_category');
                        if(row.cells[1] && row.cells[1].querySelector('.cell-text')) row.cells[1].querySelector('.cell-text').textContent=title;
                        if(row.cells[2] && row.cells[2].querySelector('.cell-text')) row.cells[2].querySelector('.cell-text').textContent=category;
                        var edit=row.querySelector('.edit-btn'); if(edit){edit.setAttribute('data-doc-title',title);edit.setAttribute('data-doc-category',category);}
                    }
                } else if(result.data && result.data.document){
                    window.location.reload();
                }
                diorCloseDocumentModal();
                if(window.Swal) Swal.fire({position:'top-end',icon:'success',title:id?'Document Updated':'Document Uploaded',showConfirmButton:false,timer:1500});
            })
            .catch(function(err){if(window.Swal) Swal.fire({icon:'error',title:'Document Not Saved',text:err.message});else window.alert(err.message);})
            .finally(function(){btn.disabled=false;});
    });

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
