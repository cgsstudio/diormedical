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

        var cbCell = document.createElement('td');
        cbCell.className = 'text-center';
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.className = 'va-checkbox dior-feedback-row-cb';
        cb.value = String(review.id || '');
        if (typeof window.diorUpdateFeedbackCheckState === 'function') {
            cb.addEventListener('change', window.diorUpdateFeedbackCheckState);
        }
        cbCell.appendChild(cb);
        row.appendChild(cbCell);

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
        statusBadge.className = 'badge-solid ' + (review.status === 'Published' ? 'col-green' : 'col-amber');
        statusBadge.textContent = review.status || 'Pending';
        statusContent.appendChild(statusBadge);
        statusCell.appendChild(statusContent);
        row.appendChild(statusCell);

        var actionCell = document.createElement('td');
        var actionsWrapper = document.createElement('div');
        actionsWrapper.className = 'cell-actions d-flex align-items-center justify-content-center gap-1';

        var viewButton = document.createElement('button');
        viewButton.type = 'button';
        viewButton.className = 'action-icon-btn edit-btn dior-feedback-view-action btn';
        viewButton.setAttribute('aria-label', 'View feedback');
        viewButton.title = 'View feedback';
        viewButton.dataset.feedback = JSON.stringify(review);
        viewButton.innerHTML = '<i class="fas fa-eye"></i>';
        viewButton.addEventListener('click', function () { window.diorViewPatientFeedback(viewButton); });
        actionsWrapper.appendChild(viewButton);

        var editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'action-icon-btn edit-btn dior-feedback-edit-action btn';
        editButton.setAttribute('aria-label', 'Edit feedback');
        editButton.title = 'Edit feedback';
        editButton.dataset.feedbackId = String(review.id || '');
        editButton.dataset.feedback = JSON.stringify(review);
        editButton.innerHTML = '<i class="fas fa-pen"></i>';
        editButton.addEventListener('click', function () { window.diorEditPatientFeedback(editButton); });
        actionsWrapper.appendChild(editButton);

        var delButton = document.createElement('button');
        delButton.type = 'button';
        delButton.className = 'action-icon-btn delete-btn dior-feedback-delete-action btn';
        delButton.setAttribute('aria-label', 'Delete feedback');
        delButton.title = 'Delete feedback';
        delButton.dataset.feedbackId = String(review.id || '');
        delButton.innerHTML = '<i class="fas fa-trash"></i>';
        delButton.addEventListener('click', function () { window.diorDeletePatientFeedback(delButton); });
        actionsWrapper.appendChild(delButton);

        actionCell.appendChild(actionsWrapper);

        row.appendChild(actionCell);

        tbody.prepend(row);
        var totalLabel = document.getElementById('dior-feedback-total-count');
        if (totalLabel) totalLabel.textContent = tbody.querySelectorAll('tr[data-feedback-id]').length + ' total feedback records';
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
        Swal.fire({
            title: feedback.subject || 'Patient Feedback',
            text: details,
            icon: 'info',
            confirmButtonColor: '#2C6CB1'
        });
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
        }
    };

    function diorConfirmDeleteFeedback(feedbackId, row) {
        var cfg = window.dior_vars || window.dior_patient_dashboard || {};
        var ajaxUrl = cfg.ajax_url || '/wp-admin/admin-ajax.php';
        var nonce = cfg.nonce || cfg.portal_nonce || '';

        if (!nonce) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Session expired. Please refresh the page.' });
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
                        if (count === 0 && tbody && !tbody.querySelector('.dior-empty-state')) {
                            tbody.innerHTML = '<tr><td colspan="8" class="dior-empty-state text-center py-5 text-secondary">You have not submitted feedback yet.</td></tr>';
                        }
                    }
                    Swal.fire({
                        position: "top-end",
                        icon: "success",
                        title: "Feedback Deleted",
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: result.data.message || 'Failed to delete feedback.' });
                }
            })
            .catch(function (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
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
            Swal.fire({ icon: 'error', title: 'Feedback Not Sent', text: configError });
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
                        var subjCell = row.querySelector('.feedback-subject-cell .cell-text') || (cells.length > 7 ? cells[1].querySelector('.cell-text') : (cells[0] ? cells[0].querySelector('.cell-text') : null));
                        if (subjCell) subjCell.textContent = result.data.review.subject || 'Patient Feedback';
                        
                        var ratingCell = row.querySelector('.feedback-rating-cell .cell-text') || (cells.length > 7 ? cells[3].querySelector('.cell-text') : (cells[2] ? cells[2].querySelector('.cell-text') : null));
                        if (ratingCell) ratingCell.innerHTML = '<i class="fa-solid fa-star" style="color:#F59E0B;"></i> ' + (result.data.review.rating || 5) + '/5';
                        
                        var msgCell = row.querySelector('.feedback-message-cell .cell-text') || (cells.length > 7 ? cells[4].querySelector('.cell-text') : (cells[3] ? cells[3].querySelector('.cell-text') : null));
                        if (msgCell) msgCell.textContent = result.data.review.message || '';

                        var updatedData = JSON.stringify({
                            subject: result.data.review.subject,
                            doctor: result.data.review.doctor,
                            rating: result.data.review.rating,
                            message: result.data.review.message,
                            status: result.data.review.status
                        });
                        var viewButton = row.querySelector('.dior-feedback-view-action');
                        if (viewButton) viewButton.dataset.feedback = updatedData;
                        var editButton = row.querySelector('.dior-feedback-edit-action');
                        if (editButton) editButton.dataset.feedback = updatedData;
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
                        toast: true,
                        position: "top-end",
                        icon: "success",
                        title: isEdit ? "Feedback Updated" : "Feedback Sent",
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: result.data.message || 'Could not save feedback.' });
                }
            })
            .catch(function (error) {
                Swal.fire({ icon: 'error', title: 'Feedback Not Sent', text: error.message });
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
            Swal.fire({
                title: 'Delete ' + label + '?',
                text: 'Are you sure you want to delete this ' + label + '?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Delete'
            }).then(function(r) {
                if (r.isConfirmed) row.remove();
            });
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
                    Swal.fire({position:'top-end',icon:'success',title:'Document Deleted',showConfirmButton:false,timer:1400});
                }).catch(function(err){
                    Swal.fire({icon:'error',title:'Delete Failed',text:err.message});
                });
        };
        Swal.fire({
            title: 'Delete Document?',
            text: 'This document will be removed from your medical records.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Delete'
        }).then(function(r) {
            if (r.isConfirmed) doDelete();
        });
    };

    window.diorOpenDocumentUploadModal = function(){
        var modalEl = document.getElementById('dior-document-modal');
        if (!modalEl) return;
        var form = document.getElementById('dior-document-form');
        if (form) form.reset();
        var idEl = document.getElementById('dior-document-id');
        if (idEl) idEl.value = '';
        var titleEl = document.getElementById('dior-document-modal-title');
        if (titleEl) titleEl.innerHTML = '<i class="fas fa-file-medical text-primary me-2"></i> Add Medical Document';
        var saveBtn = document.getElementById('dior-document-save-btn');
        if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-cloud-arrow-up me-1"></i> Upload Document';
        var fileWrap = document.getElementById('dior-document-file-wrap');
        if (fileWrap) fileWrap.style.display = 'block';
        var fileInput = document.getElementById('dior-document-file');
        if (fileInput) fileInput.required = true;
        var dropzoneLabel = document.getElementById('dior-doc-file-label');
        if (dropzoneLabel) dropzoneLabel.textContent = '';
        
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEl.classList.add('show', 'is-open');
            modalEl.style.display = 'block';
            modalEl.setAttribute('aria-hidden', 'false');
        }
    };

    window.diorEditDocument = function(button){
        var row = button.closest('tr');
        if (!row) return;
        var modalEl = document.getElementById('dior-document-modal');
        if (!modalEl) return;
        var id = row.getAttribute('data-doc-id') || '';
        var title = button.getAttribute('data-doc-title') || (row.cells[1] ? row.cells[1].innerText.trim() : 'Medical Document');
        var category = button.getAttribute('data-doc-category') || (row.cells[2] ? row.cells[2].innerText.trim() : 'Clinical Record');
        
        var idEl = document.getElementById('dior-document-id');
        if (idEl) idEl.value = id;
        var titleInput = document.getElementById('dior-document-title');
        if (titleInput) titleInput.value = title;
        var catSelect = document.getElementById('dior-document-category');
        if (catSelect) catSelect.value = category;
        var modalTitle = document.getElementById('dior-document-modal-title');
        if (modalTitle) modalTitle.innerHTML = '<i class="fas fa-pen-to-square text-primary me-2"></i> Update Medical Document';
        var saveBtn = document.getElementById('dior-document-save-btn');
        if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save Changes';
        var fileWrap = document.getElementById('dior-document-file-wrap');
        if (fileWrap) fileWrap.style.display = 'none';
        var fileInput = document.getElementById('dior-document-file');
        if (fileInput) fileInput.required = false;

        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEl.classList.add('show', 'is-open');
            modalEl.style.display = 'block';
            modalEl.setAttribute('aria-hidden', 'false');
        }
    };

    window.diorCloseDocumentModal = function(){
        var modalEl = document.getElementById('dior-document-modal');
        if (!modalEl) return;
        if (window.bootstrap && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
        }
        modalEl.classList.remove('show', 'is-open');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        var backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(function(b) { b.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
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
        var patIdInput = document.getElementById('dior-document-patient-id');
        var patId = (patIdInput && patIdInput.value) || cfg.current_user_id || '';
        fd.set('patient_id', patId);
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
                    var doc = result.data.document;
                    var tbody = document.getElementById('dior-docs-tbody');
                    if(tbody){
                        var emptyRow = tbody.querySelector('.dior-empty-state');
                        if(emptyRow) emptyRow.closest('tr').remove();
                        var tr = document.createElement('tr');
                        tr.setAttribute('data-doc-id', doc.id || '');
                        tr.setAttribute('data-doc-author-id', doc.author_id || '');
                        var docTitle = doc.title || 'Medical Document';
                        var docCategory = doc.category || 'Clinical Record';
                        var docType = doc.file_type || 'PDF';
                        var docDate = doc.date || 'Just now';
                        var docSize = doc.size || '—';
                        var downloadUrl = (cfg.home_url || '/') + '?dior_action=download_doc&doc_id=' + encodeURIComponent(doc.id || '') + '&patient_id=' + encodeURIComponent(cfg.current_user_id || '');
                        tr.innerHTML = '<td><input type="checkbox" class="dior-ic-52ff4d551f"></td>' +
                            '<td><span class="cell-text dior-ic-794116ec9b">' + docTitle + '</span></td>' +
                            '<td><span class="cell-text">' + docCategory + '</span></td>' +
                            '<td><span class="cell-text">' + docType + '</span></td>' +
                            '<td><div class="cell-content cell-icon-text"><i class="far fa-calendar cell-icon"></i><span class="cell-text">' + docDate + '</span></div></td>' +
                            '<td><span class="cell-text">' + docSize + '</span></td>' +
                            '<td><div class="cell-content"><div class="badge-solid col-green">Available</div></div></td>' +
                            '<td><div class="cell-actions">' +
                            '<a href="' + downloadUrl + '" target="_blank" class="action-icon-btn btn" title="Download Document" download><i class="fas fa-file-arrow-down text-primary"></i></a>' +
                            '<button type="button" class="action-icon-btn edit-btn" title="Edit Document" data-doc-title="' + docTitle + '" data-doc-category="' + docCategory + '" onclick="diorEditDocument(this)"><i class="fas fa-pen"></i></button>' +
                            '<button type="button" class="action-icon-btn delete-btn" title="Delete Document" onclick="diorDeleteStaticRow(this, \'document\')"><i class="fas fa-trash"></i></button>' +
                            '</div></td>';
                        tbody.insertBefore(tr, tbody.firstChild);

                        var pageCount = document.getElementById('dior-docs-page-count');
                        if (pageCount) {
                            var totalRows = tbody.querySelectorAll('tr[data-doc-id]').length;
                            pageCount.textContent = '0 selected / ' + totalRows + ' total';
                        }
                    }
                }
                diorCloseDocumentModal();
                if(window.Swal) Swal.fire({position:'top-end',icon:'success',title:id?'Document Updated':'Document Uploaded',showConfirmButton:false,timer:1500});
            })
            .catch(function(err){Swal.fire({icon:'error',title:'Document Not Saved',text:err.message});})
            .finally(function(){btn.disabled=false;});
    });

    window.diorCloseTelemedicineModal = function () {
        var modalEl = document.getElementById('diorTelemedicineModal');
        if (!modalEl) return;
        if (window.bootstrap && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
        }
        modalEl.classList.remove('show', 'is-open');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        var backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(function (b) { b.remove(); });
    };

    window.diorViewTelemedicine = function (button) {
        var row = button.closest('tr');
        if (!row) return;

        var cells = row.querySelectorAll('td');
        var sessionId = row.getAttribute('data-tele-id') || (cells[1] ? cells[1].innerText.trim() : '—');
        var doctor = row.getAttribute('data-doctor') || (cells[2] ? cells[2].innerText.trim() : 'Attending Physician');
        var specialty = row.getAttribute('data-specialty') || (cells[3] ? cells[3].innerText.trim() : 'Telehealth Physician');
        var date = row.getAttribute('data-date') || (cells[4] ? cells[4].innerText.trim() : '—');
        var time = row.getAttribute('data-time') || (cells[5] ? cells[5].innerText.trim() : '—');
        var duration = row.getAttribute('data-duration') || (cells[6] ? cells[6].innerText.trim() : '30 minutes');
        var type = row.getAttribute('data-type') || (cells[7] ? cells[7].innerText.trim() : 'Video Visit (HD)');
        var status = row.getAttribute('data-status') || (cells[8] ? cells[8].innerText.trim() : 'Confirmed');
        var joinUrl = row.getAttribute('data-join-url') || '';

        var modalEl = document.getElementById('diorTelemedicineModal');
        if (!modalEl) {
            if (window.Swal) {
                Swal.fire({
                    title: 'Telemedicine Session',
                    html: '<div class="text-start"><strong>Doctor:</strong> ' + doctor + '<br><strong>Date:</strong> ' + date + '<br><strong>Time:</strong> ' + time + '</div>',
                    icon: 'info',
                    confirmButtonColor: '#2C6CB1'
                });
            }
            return;
        }

        var sessEl = document.getElementById('dior-tele-modal-session-id');
        var docEl = document.getElementById('dior-tele-modal-doctor');
        var specEl = document.getElementById('dior-tele-modal-specialty');
        var dateEl = document.getElementById('dior-tele-modal-date');
        var timeEl = document.getElementById('dior-tele-modal-time');
        var durEl = document.getElementById('dior-tele-modal-duration');
        var typeEl = document.getElementById('dior-tele-modal-type');
        var statusEl = document.getElementById('dior-tele-modal-status');
        var joinBtn = document.getElementById('dior-tele-modal-join-btn');

        if (sessEl) sessEl.textContent = sessionId;
        if (docEl) docEl.textContent = doctor;
        if (specEl) specEl.textContent = specialty;
        if (dateEl) dateEl.textContent = date;
        if (timeEl) timeEl.textContent = time;
        if (durEl) durEl.textContent = '(' + duration + ')';
        if (typeEl) typeEl.textContent = type;

        if (statusEl) {
            var stLower = status.toLowerCase();
            var badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
            if (stLower === 'confirmed' || stLower === 'scheduled') {
                badgeClass = 'bg-success-subtle text-success border border-success-subtle';
            } else if (stLower === 'completed') {
                badgeClass = 'bg-info-subtle text-info border border-info-subtle';
            } else if (stLower === 'cancelled') {
                badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
            }
            statusEl.innerHTML = '<span class="badge rounded-pill px-2.5 py-1 small fw-semibold ' + badgeClass + '">' + status + '</span>';
        }

        if (joinBtn) {
            var isInactive = ['completed', 'cancelled', 'no show'].indexOf(status.toLowerCase()) !== -1;
            if (isInactive) {
                joinBtn.classList.add('disabled');
                joinBtn.setAttribute('aria-disabled', 'true');
                joinBtn.style.pointerEvents = 'none';
                joinBtn.innerHTML = '<i class="fas fa-video-slash me-1"></i> Call Unavailable';
            } else {
                joinBtn.classList.remove('disabled');
                joinBtn.removeAttribute('aria-disabled');
                joinBtn.style.pointerEvents = '';
                joinBtn.href = joinUrl || 'javascript:void(0);';
                joinBtn.innerHTML = '<i class="fas fa-video me-1"></i> Join Call';
                if (!joinUrl) {
                    joinBtn.onclick = function (e) {
                        e.preventDefault();
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Consultation Room',
                                text: 'The secure video room for session ' + sessionId + ' with ' + doctor + ' will open 5 minutes prior to appointment time.',
                                confirmButtonColor: '#2C6CB1'
                            });
                        }
                    };
                } else {
                    joinBtn.onclick = null;
                }
            }
        }

        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEl.classList.add('show', 'is-open');
            modalEl.style.display = 'block';
            modalEl.setAttribute('aria-hidden', 'false');
        }
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

    window.diorBulkDeleteStaticRows = function (btn) {
        var wrapper = btn.closest('.docs-card, .va-table-wrapper, .card, .medidash-card, .dior-tab-panel');
        if (!wrapper) return;
        var table = wrapper.querySelector('table');
        if (!table) return;
        var checkedBoxes = table.querySelectorAll('tbody input[type="checkbox"]:checked');
        if (checkedBoxes.length === 0) {
            Swal.fire({ icon: 'info', title: 'No Rows Selected', text: 'Please select at least one row to delete.' });
            return;
        }

        var performDelete = function() {
            checkedBoxes.forEach(function(cb) {
                var row = cb.closest('tr');
                if (row) row.remove();
            });
            var masterCb = table.querySelector('thead input[type="checkbox"]');
            if (masterCb) masterCb.checked = false;
            
            // Try updating count if exists
            var countEl = wrapper.querySelector('[id*="-page-count"]');
            if (countEl) {
                var total = table.querySelectorAll('tbody tr:not(.dior-empty-state)').length;
                countEl.innerText = '0 selected / ' + total + ' total';
            }
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You are about to delete ' + checkedBoxes.length + ' selected record(s).',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete them!'
            }).then(function(result) {
                if (result.isConfirmed) {
                    performDelete();
                    Swal.fire({ position: 'top-end', icon: 'success', title: 'Records Deleted', showConfirmButton: false, timer: 1500 });
                }
            });
        }
    };

    // =========================================================================
    // EMERGENCY CONTACTS MODAL & ACTIONS (Strict Bootstrap 5 Standard)
    // =========================================================================
    window.diorOpenEmergencyContactModal = function() {
        var modalEl = document.getElementById('dior-emergency-contact-modal');
        if (!modalEl) return;
        var form = document.getElementById('dior-emerg-contact-form');
        if (form) form.reset();
        var indexEl = document.getElementById('dior-emerg-contact-index');
        if (indexEl) indexEl.value = '';
        var titleEl = document.getElementById('diorEmergModalTitle');
        if (titleEl) titleEl.innerHTML = '<i class="fas fa-truck-medical text-danger me-2"></i> Add Emergency Contact';
        var saveBtn = document.getElementById('dior-emerg-save-btn');
        if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-check me-1"></i> Save Contact';

        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
            modalEl.setAttribute('aria-hidden', 'false');
        }
    };

    window.diorEditEmergencyContact = function(button) {
        var row = button.closest('tr');
        if (!row) return;
        var modalEl = document.getElementById('dior-emergency-contact-modal');
        if (!modalEl) return;

        var index = row.getAttribute('data-contact-index') || '';
        var name = (row.cells[1] ? row.cells[1].innerText.trim() : '');
        var relation = (row.cells[2] ? row.cells[2].innerText.trim() : '');
        var phone = (row.cells[3] ? row.cells[3].innerText.trim() : '');
        var priority = (row.cells[4] ? row.cells[4].innerText.trim() : 'Emergency');

        var indexEl = document.getElementById('dior-emerg-contact-index');
        if (indexEl) indexEl.value = index;
        var nameInput = document.getElementById('dior-emerg-name');
        if (nameInput) nameInput.value = name;
        var relSelect = document.getElementById('dior-emerg-relation');
        if (relSelect) relSelect.value = relation || 'Spouse';
        var phoneInput = document.getElementById('dior-emerg-phone');
        if (phoneInput) phoneInput.value = phone;
        var prioSelect = document.getElementById('dior-emerg-priority');
        if (prioSelect) prioSelect.value = priority || 'Emergency';

        var titleEl = document.getElementById('diorEmergModalTitle');
        if (titleEl) titleEl.innerHTML = '<i class="fas fa-pen-to-square text-primary me-2"></i> Edit Emergency Contact';
        var saveBtn = document.getElementById('dior-emerg-save-btn');
        if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> Update Contact';

        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
            modalEl.setAttribute('aria-hidden', 'false');
        }
    };

    window.diorCloseEmergencyContactModal = function() {
        var modalEl = document.getElementById('dior-emergency-contact-modal');
        if (!modalEl) return;
        if (window.bootstrap && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
        } else {
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
        }
    };

    window.diorDeleteEmergencyContact = function(button) {
        var row = button.closest('tr');
        if (!row) return;
        var index = row.getAttribute('data-contact-index') || '';
        var name = row.cells[1] ? row.cells[1].innerText.trim() : 'this contact';

        var doDelete = function() {
            var cfg = window.dior_vars || window.dior_patient_dashboard || {};
            var fd = new FormData();
            fd.append('action', 'dior_patient_delete_emergency_contact');
            fd.append('nonce', cfg.nonce || cfg.portal_nonce || '');
            fd.append('contact_index', index);

            fetch(cfg.ajax_url || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            }).then(function(r) { return r.json(); })
            .then(function(res) {
                row.remove();
                var tbody = document.getElementById('dior-emerg-tbody');
                if (tbody) {
                    var remaining = tbody.querySelectorAll('tr:not(.dior-emerg-empty-row)');
                    if (remaining.length === 0) {
                        tbody.innerHTML = '<tr class="dior-emerg-empty-row"><td colspan="6" class="dior-empty-state text-center py-4 text-muted">No emergency contact has been added.</td></tr>';
                    } else {
                        remaining.forEach(function(r, i) {
                            r.setAttribute('data-contact-index', i);
                        });
                    }
                    var countEl = document.getElementById('dior-emerg-page-count');
                    if (countEl) countEl.textContent = '0 selected / ' + remaining.length + ' total';
                }
                if (window.Swal) {
                    Swal.fire({ position: 'top-end', icon: 'success', title: 'Emergency contact removed.', showConfirmButton: false, timer: 1500 });
                }
            }).catch(function(err) {
                row.remove();
                if (window.Swal) {
                    Swal.fire({ position: 'top-end', icon: 'success', title: 'Contact removed.', showConfirmButton: false, timer: 1500 });
                }
            });
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Delete ' + name + '?',
                text: 'Are you sure you want to remove this emergency contact?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, remove'
            }).then(function(r) {
                if (r.isConfirmed) doDelete();
            });
        } else if (confirm('Are you sure you want to delete ' + name + '?')) {
            doDelete();
        }
    };

    document.addEventListener('submit', function(event) {
        if (event.target.id !== 'dior-emerg-contact-form') return;
        event.preventDefault();

        var form = event.target;
        var cfg = window.dior_vars || window.dior_patient_dashboard || {};
        var btn = document.getElementById('dior-emerg-save-btn');
        var index = document.getElementById('dior-emerg-contact-index').value;

        var name = (document.getElementById('dior-emerg-name').value || '').trim();
        var relation = (document.getElementById('dior-emerg-relation').value || '').trim();
        var phone = (document.getElementById('dior-emerg-phone').value || '').trim();
        var priority = (document.getElementById('dior-emerg-priority').value || 'Emergency').trim();

        if (!name || !phone) {
            if (window.Swal) Swal.fire({ icon: 'error', title: 'Missing Information', text: 'Full name and phone number are required.' });
            return;
        }

        btn.disabled = true;

        var fd = new FormData(form);
        fd.append('action', 'dior_patient_save_emergency_contact');
        fd.append('nonce', cfg.nonce || cfg.portal_nonce || '');

        var badgeClass = (priority === 'Primary') 
            ? 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 small' 
            : ((priority === 'Secondary') 
                ? 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 small' 
                : 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 small');

        var phoneClean = phone.replace(/[^\d+]/g, '');

        var rowInner = '<td><input type="checkbox" class="dior-ic-52ff4d551f form-check-input"></td>' +
            '<td><span class="cell-text fw-semibold text-dark">' + name + '</span></td>' +
            '<td><span class="cell-text text-secondary">' + (relation || '—') + '</span></td>' +
            '<td><div class="cell-content cell-icon-text d-flex align-items-center"><i class="fas fa-phone cell-icon text-primary me-2" style="font-size: 13px;"></i><a href="tel:' + phoneClean + '" class="cell-text text-decoration-none text-dark">' + phone + '</a></div></td>' +
            '<td><div class="cell-content"><span class="' + badgeClass + '">' + priority + '</span></div></td>' +
            '<td><div class="cell-actions d-flex align-items-center gap-1">' +
                '<button type="button" class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center p-0 shadow-sm" style="width: 32px; height: 32px;" title="Edit Contact" onclick="diorEditEmergencyContact(this)"><i class="fas fa-pen" style="font-size: 11px;"></i></button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger rounded-circle d-inline-flex align-items-center justify-content-center p-0 shadow-sm" style="width: 32px; height: 32px;" title="Delete Contact" onclick="diorDeleteEmergencyContact(this)"><i class="fas fa-trash" style="font-size: 11px;"></i></button>' +
            '</div></td>';

        fetch(cfg.ajax_url || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            if (!result.success) throw new Error((result.data && result.data.message) || 'Unable to save emergency contact.');

            var tbody = document.getElementById('dior-emerg-tbody');
            if (tbody) {
                var emptyRow = tbody.querySelector('.dior-emerg-empty-row, .dior-empty-state');
                if (emptyRow) emptyRow.closest('tr').remove();

                if (index !== '' && index >= 0) {
                    var existingRow = tbody.querySelector('tr[data-contact-index="' + index + '"]');
                    if (existingRow) {
                        existingRow.innerHTML = rowInner;
                    }
                } else {
                    var newIdx = tbody.querySelectorAll('tr:not(.dior-emerg-empty-row)').length;
                    var tr = document.createElement('tr');
                    tr.setAttribute('data-contact-index', newIdx);
                    tr.innerHTML = rowInner;
                    tbody.appendChild(tr);
                }

                var countEl = document.getElementById('dior-emerg-page-count');
                if (countEl) {
                    var total = tbody.querySelectorAll('tr:not(.dior-emerg-empty-row)').length;
                    countEl.textContent = '0 selected / ' + total + ' total';
                }
            }

            diorCloseEmergencyContactModal();
            if (window.Swal) {
                Swal.fire({
                    position: 'top-end',
                    icon: 'success',
                    title: (index !== '' && index >= 0) ? 'Contact Updated' : 'Contact Added',
                    showConfirmButton: false,
                    timer: 1600
                });
            }
        })
        .catch(function(err) {
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Error Saving Contact', text: err.message });
            } else {
                alert('Error: ' + err.message);
            }
        })
        .finally(function() {
            btn.disabled = false;
        });
    });
}());
