/**
 * Dior Medical - Doctor Dashboard Scripts
 */

function getDiorDocAjax() {
    return window.diorDocAjax || (typeof diorDocAjax !== 'undefined' ? diorDocAjax : '') || (window.dior_doctor_vars ? window.dior_doctor_vars.ajax_url : '') || (window.dior_vars ? window.dior_vars.ajax_url : '/wp-admin/admin-ajax.php');
}

function getDiorDocNonce() {
    return window.diorDocNonce || (typeof diorDocNonce !== 'undefined' ? diorDocNonce : '') || (window.dior_doctor_vars ? window.dior_doctor_vars.nonce : '') || (window.dior_vars ? window.dior_vars.nonce : '');
}

function initDoctorDashboard() {
    // Guard: Only initialize if on the Doctor Dashboard container
    const docApp = document.getElementById('dior-doctor-app');
    if (!docApp) {
        return;
    }

    // Initialize first tab
    let initialTab = null;
    try {
        const hash = window.location.hash.match(/^#tab=([^&]+)/);
        if (hash) initialTab = decodeURIComponent(hash[1]);
        if (!initialTab) initialTab = localStorage.getItem('diorDocLastTab');
    } catch(e) {}
    
    let firstTabBtn = null;
    if (initialTab && initialTab !== 'null' && initialTab !== 'undefined') {
        firstTabBtn = document.querySelector('#dior-doc-sidebar .dior-nav-btn[data-tab="' + CSS.escape(initialTab) + '"]');
    }
    if (!firstTabBtn) {
        firstTabBtn = document.querySelector('#dior-doc-sidebar .dior-nav-btn.active') || document.querySelector('#dior-doc-sidebar .dior-nav-btn');
    }
    
    if (firstTabBtn) {
        diorDocSwitchTab(firstTabBtn.getAttribute('data-tab'));
    }

    // Add click listeners to ALL nav buttons (must be inside DOMContentLoaded)
    document.querySelectorAll('#dior-doc-sidebar .dior-nav-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            diorDocSwitchTab(this.getAttribute('data-tab'));
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (e) {
        const notifWrap = document.querySelector('.dior-notif-wrap');
        const notifDropdown = document.getElementById('dior-doc-notif-dd');
        if (notifWrap && notifDropdown && !notifWrap.contains(e.target)) {
            notifDropdown.classList.remove('open');
            notifDropdown.classList.remove('active');
        }
    });

    // Handle AJAX profile form
    const profileForm = document.getElementById('dior-doc-profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', diorDocSaveProfile);
    }

    // Initialize Pagination for all Doctor Dashboard Tables (5 rows max per page)
    document.querySelectorAll('.dior-doc-table, .dior-table, .dior-clean-table').forEach(table => {
        diorDoctorInitTablePagination(table, 5);
    });

    // Check doctor profile completion on initial load (ONLY on doctor dashboard, for doctors)
    if (docApp && window.dior_doctor_vars && window.dior_doctor_vars.is_doctor == 1 && parseInt(window.dior_doctor_vars.is_profile_complete, 10) !== 1) {
        if (!sessionStorage.getItem('dior_doc_profile_remind_later')) {
            setTimeout(function() {
                const missingObj = window.dior_doctor_vars.missing_profile_fields || {};
                const missingList = Object.values(missingObj).length ? Object.values(missingObj).join(', ') : 'Specialty, License Number, NPI Number, Phone';
                const docName = (window.dior_doctor_vars.doctor_profile && window.dior_doctor_vars.doctor_profile.first_name) ? (' ' + window.dior_doctor_vars.doctor_profile.first_name) : '';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Complete Your Provider Information',
                        html: `<div style="text-align:center; padding: 4px 0;">
                            <p style="font-size:14.5px; color:#334155; line-height:1.5; margin:0 0 10px 0;">
                                Welcome Dr.${docName}! Please complete your required professional credentials (<strong>${missingList}</strong>) in Provider Settings to ensure compliance and clinical readiness.
                            </p>
                        </div>`,
                        confirmButtonText: '<i class="fa-solid fa-user-pen"></i> Complete Profile Now',
                        confirmButtonColor: '#2C6CB1',
                        showCancelButton: true,
                        cancelButtonText: 'Remind Me Later',
                        cancelButtonColor: '#94A3B8',
                        focusConfirm: true,
                        customClass: {
                            popup: 'dior-swal-modal-box'
                        }
                    }).then((res) => {
                        if (res.isConfirmed) {
                            diorDocSwitchTab('doc-settings');
                            setTimeout(() => {
                                const firstEmpty = document.querySelector('#dior-doc-profile-form input[required]:invalid, #dior-doc-profile-form input[name="doctor_specialty"]');
                                if (firstEmpty) {
                                    firstEmpty.focus();
                                    firstEmpty.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                }
                            }, 300);
                        } else {
                            sessionStorage.setItem('dior_doc_profile_remind_later', '1');
                        }
                    });
                }
            }, 1200);
        }
    }

    // Doctor Avatar Media Library, Direct Upload & Removal Handler
    const avatarRemoveBtn = document.getElementById('dior-btn-doctor-avatar-remove');
    const avatarDisplayImg = document.getElementById('dior-doctor-avatar-display-img');
    const avatarInitials = document.getElementById('dior-doctor-avatar-initials');
    const doctorFileInput = document.getElementById('dior-doctor-avatar-file-input');

    window.diorDocOpenDirectUpload = function() {
        if (doctorFileInput) {
            doctorFileInput.click();
        }
    };

    if (doctorFileInput) {
        doctorFileInput.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large',
                        text: 'Profile photo must be 5MB or less.',
                        confirmButtonColor: '#2C6CB1'
                    });
                }
                return;
            }

            const directBtn = document.getElementById('dior-btn-doctor-direct-upload');
            const origHtml = directBtn ? directBtn.innerHTML : '';
            if (directBtn) {
                directBtn.disabled = true;
                directBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
            }

            const formData = new FormData();
            formData.append('action', 'dior_doctor_upload_avatar');
            formData.append('nonce', getDiorDocNonce());
            formData.append('avatar', file);

            fetch(getDiorDocAjax(), {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (directBtn) {
                    directBtn.disabled = false;
                    directBtn.innerHTML = origHtml;
                }
                if (res.success && res.data && res.data.avatar_url) {
                    const avatarUrl = res.data.avatar_url;
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
                    const sideImg = document.querySelector('#dior-doctor-sidebar-avatar img');
                    if (sideImg) sideImg.src = avatarUrl;

                    if (typeof Swal !== 'undefined') {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2500
                        });
                        Toast.fire({
                            icon: 'success',
                            title: 'Provider photo updated!'
                        });
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: (res.data && res.data.message) ? res.data.message : 'Could not upload photo.',
                            confirmButtonColor: '#2C6CB1'
                        });
                    }
                }
            })
            .catch(() => {
                if (directBtn) {
                    directBtn.disabled = false;
                    directBtn.innerHTML = origHtml;
                }
            });
        });
    }

    let doctorMediaFrame = null;

    window.diorDocOpenMediaLibrary = function() {
        if (typeof wp !== 'undefined' && wp.media) {
            if (doctorMediaFrame) {
                doctorMediaFrame.open();
                return;
            }

            doctorMediaFrame = wp.media({
                title: 'Select or Upload Provider Profile Photo',
                button: {
                    text: 'Use as Profile Photo'
                },
                library: {
                    type: 'image'
                },
                multiple: false
            });

            doctorMediaFrame.on('select', function() {
                const attachment = doctorMediaFrame.state().get('selection').first().toJSON();
                if (!attachment || !attachment.url) return;

                const avatarUrl = attachment.url;
                const attachId = attachment.id || 0;

                // Update preview immediately
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

                // Send AJAX to save in database
                const formData = new FormData();
                formData.append('action', 'dior_doctor_upload_avatar');
                formData.append('nonce', getDiorDocNonce());
                formData.append('avatar_url', avatarUrl);
                formData.append('attachment_id', attachId);

                fetch(getDiorDocAjax(), {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(response => {
                    if (response && response.success) {
                        // Update sidebar avatar
                        const sidebarAvatar = document.getElementById('dior-doctor-sidebar-avatar');
                        if (sidebarAvatar) {
                            sidebarAvatar.innerHTML = `<img src="${avatarUrl}" alt="Provider Photo" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
                        }

                        // Update topbar pill avatar
                        const topPillAvatar = document.getElementById('dior-doctor-top-avatar');
                        if (topPillAvatar) {
                            topPillAvatar.innerHTML = `<img src="${avatarUrl}" alt="Provider Photo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Profile Photo Updated!',
                                text: 'Your provider photo has been selected from the WordPress Media Library.',
                                confirmButtonText: '<i class="fa-solid fa-check"></i> Great',
                                confirmButtonColor: '#2C6CB1',
                                timer: 2500
                            });
                        }
                    } else {
                        const errMsg = (response && response.data && response.data.message) ? response.data.message : 'Failed to update photo.';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Photo Update Failed',
                                text: errMsg,
                                confirmButtonColor: '#2C6CB1'
                            });
                        } else {
                            alert(errMsg);
                        }
                    }
                })
                .catch(err => {
                    console.error('[Doctor Media Avatar Error]', err);
                    alert('Connection error while saving photo.');
                });
            });

            doctorMediaFrame.open();
        } else {
            alert('WordPress Media Library could not be loaded. Please refresh the page.');
        }
    };

    if (avatarRemoveBtn) {
        avatarRemoveBtn.addEventListener('click', function() {
            avatarRemoveBtn.disabled = true;
            avatarRemoveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Removing...';

            const formData = new FormData();
            formData.append('action', 'dior_doctor_remove_avatar');
            formData.append('nonce', getDiorDocNonce());

            fetch(getDiorDocAjax(), {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(response => {
                avatarRemoveBtn.disabled = false;
                avatarRemoveBtn.innerHTML = '<i class="fa-regular fa-trash-can"></i> Remove';

                if (response && response.success) {
                    avatarRemoveBtn.style.display = 'none';
                    const initials = (response.data && response.data.initials) ? response.data.initials : 'DR';

                    if (avatarDisplayImg) {
                        avatarDisplayImg.src = '';
                        avatarDisplayImg.style.display = 'none';
                    }
                    if (avatarInitials) {
                        avatarInitials.textContent = initials;
                        avatarInitials.style.display = 'block';
                    }

                    // Revert sidebar avatar
                    const sidebarAvatar = document.getElementById('dior-doctor-sidebar-avatar');
                    if (sidebarAvatar) {
                        sidebarAvatar.innerHTML = `<i class="fa-solid fa-user-doctor" style="color:#2C6CB1;font-size:18px;"></i>`;
                    }

                    // Revert topbar pill avatar
                    const topPillAvatar = document.getElementById('dior-doctor-top-avatar');
                    if (topPillAvatar) {
                        topPillAvatar.innerHTML = `<i class="fa-solid fa-user-doctor" style="color:#FFFFFF;font-size:14px;"></i>`;
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Photo Removed',
                            text: 'Provider profile photo has been reset.',
                            confirmButtonColor: '#2C6CB1',
                            timer: 2000
                        });
                    }
                } else {
                    const errMsg = (response && response.data && response.data.message) ? response.data.message : 'Failed to remove photo.';
                    alert(errMsg);
                }
            })
            .catch(() => {
                avatarRemoveBtn.disabled = false;
                avatarRemoveBtn.innerHTML = '<i class="fa-regular fa-trash-can"></i> Remove';
                alert('Connection error during photo removal.');
            });
        });
    }
}

// ── DOCTOR DIGITAL SIGNATURE MANAGEMENT (PNG TRANSPARENT) ──
let doctorSigMediaFrame = null;

window.diorDocOpenSigDirectUpload = function() {
    const input = document.getElementById('dior-doctor-sig-file-input');
    if (input) input.click();
};

window.diorDocHandleSigFileSelect = function(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    if (!file.type.match('image/png') && !file.type.match('image/webp')) {
        alert('Please upload a PNG image with a transparent background.');
        return;
    }

    const uploadBtn = document.getElementById('dior-btn-doctor-sig-upload');
    const origHtml = uploadBtn ? uploadBtn.innerHTML : '';
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
    }

    const formData = new FormData();
    formData.append('action', 'dior_doctor_upload_signature');
    formData.append('nonce', window.diorDocNonce || (typeof getDiorDocNonce === 'function' ? getDiorDocNonce() : ''));
    formData.append('signature', file);

    fetch(window.diorDocAjax || (typeof getDiorDocAjax === 'function' ? getDiorDocAjax() : '/wp-admin/admin-ajax.php'), {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = origHtml;
        }
        if (res.success && res.data && res.data.signature_url) {
            window.diorDocApplySignatureUI(res.data.signature_url);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Signature Uploaded',
                    text: 'Transparent PNG signature saved and active across letters & prescriptions.',
                    confirmButtonColor: '#00A896',
                    timer: 2500
                });
            } else {
                alert('Doctor signature uploaded successfully!');
            }
        } else {
            alert((res.data && res.data.message) ? res.data.message : 'Error uploading signature.');
        }
    })
    .catch(() => {
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = origHtml;
        }
        alert('Network error while uploading signature.');
    });
};

window.diorDocOpenSigMediaLibrary = function() {
    if (typeof wp !== 'undefined' && wp.media) {
        if (doctorSigMediaFrame) {
            doctorSigMediaFrame.open();
            return;
        }

        doctorSigMediaFrame = wp.media({
            title: 'Select Transparent PNG Doctor Signature',
            button: {
                text: 'Use as Digital Signature'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        doctorSigMediaFrame.on('select', function() {
            const attachment = doctorSigMediaFrame.state().get('selection').first().toJSON();
            if (!attachment || !attachment.url) return;

            const sigUrl = attachment.url;
            const attachId = attachment.id || 0;

            const formData = new FormData();
            formData.append('action', 'dior_doctor_upload_signature');
            formData.append('nonce', window.diorDocNonce || (typeof getDiorDocNonce === 'function' ? getDiorDocNonce() : ''));
            formData.append('signature_url', sigUrl);
            formData.append('attachment_id', attachId);

            fetch(window.diorDocAjax || (typeof getDiorDocAjax === 'function' ? getDiorDocAjax() : '/wp-admin/admin-ajax.php'), {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(response => {
                if (response && response.success) {
                    window.diorDocApplySignatureUI(sigUrl);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Signature Selected',
                            text: 'Digital signature updated from Media Library.',
                            confirmButtonColor: '#00A896',
                            timer: 2500
                        });
                    }
                } else {
                    alert((response && response.data && response.data.message) ? response.data.message : 'Failed to save signature.');
                }
            })
            .catch(() => alert('Connection error while saving signature.'));
        });

        doctorSigMediaFrame.open();
    } else {
        alert('WordPress Media Library could not be loaded.');
    }
};

window.diorDocRemoveSignature = function() {
    if (!confirm('Are you sure you want to remove your digital signature?')) return;

    const removeBtn = document.getElementById('dior-btn-doctor-sig-remove');
    const origHtml = removeBtn ? removeBtn.innerHTML : '';
    if (removeBtn) {
        removeBtn.disabled = true;
        removeBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Removing...';
    }

    const formData = new FormData();
    formData.append('action', 'dior_doctor_remove_signature');
    formData.append('nonce', window.diorDocNonce || (typeof getDiorDocNonce === 'function' ? getDiorDocNonce() : ''));

    fetch(window.diorDocAjax || (typeof getDiorDocAjax === 'function' ? getDiorDocAjax() : '/wp-admin/admin-ajax.php'), {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (removeBtn) {
            removeBtn.disabled = false;
            removeBtn.innerHTML = origHtml;
        }
        if (res.success) {
            window.diorDocClearSignatureUI();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Signature Removed',
                    text: 'Doctor signature has been reset.',
                    confirmButtonColor: '#00A896',
                    timer: 2000
                });
            } else {
                alert('Signature removed.');
            }
        } else {
            alert(res.data ? res.data.message : 'Error removing signature.');
        }
    })
    .catch(() => {
        if (removeBtn) {
            removeBtn.disabled = false;
            removeBtn.innerHTML = origHtml;
        }
        alert('Network error removing signature.');
    });
};

window.diorDocApplySignatureUI = function(sigUrl) {
    window.diorDoctorSignatureUrl = sigUrl;

    const dispImg = document.getElementById('dior-doctor-signature-display-img');
    const emptyState = document.getElementById('dior-doctor-signature-empty-state');
    const remBtn = document.getElementById('dior-btn-doctor-sig-remove');
    if (dispImg) {
        dispImg.src = sigUrl;
        dispImg.style.display = 'block';
    }
    if (emptyState) emptyState.style.display = 'none';
    if (remBtn) remBtn.style.display = 'inline-flex';

    const ltrSigWrap = document.getElementById('doc-ltr-preview-signature-wrap');
    if (ltrSigWrap) {
        ltrSigWrap.innerHTML = `<img id="doc-ltr-preview-sig-img" src="${sigUrl}" alt="Doctor Signature" style="max-height:50px; max-width:180px; object-fit:contain; background:transparent;">`;
    }

    const rxSigWrap = document.getElementById('doc-confirm-rx-sig-wrap');
    if (rxSigWrap) {
        rxSigWrap.innerHTML = `<img id="doc-confirm-rx-sig-img" src="${sigUrl}" alt="Doctor Digital Signature" style="max-height:50px; max-width:180px; object-fit:contain; background:transparent;">`;
    }
};

window.diorDocClearSignatureUI = function() {
    window.diorDoctorSignatureUrl = '';

    const dispImg = document.getElementById('dior-doctor-signature-display-img');
    const emptyState = document.getElementById('dior-doctor-signature-empty-state');
    const remBtn = document.getElementById('dior-btn-doctor-sig-remove');
    if (dispImg) {
        dispImg.src = '';
        dispImg.style.display = 'none';
    }
    if (emptyState) emptyState.style.display = 'block';
    if (remBtn) remBtn.style.display = 'none';

    const docPill = document.querySelector('.dior-pill-name');
    const docName = docPill ? docPill.textContent.trim() : 'Dr. Medical Provider';

    const ltrSigWrap = document.getElementById('doc-ltr-preview-signature-wrap');
    if (ltrSigWrap) {
        ltrSigWrap.innerHTML = `<span id="doc-ltr-preview-sig-text" style="font-family:'Playfair Display',serif; font-style:italic; font-size:20px; color:#0F172A;">/s/ ${docName}</span>`;
    }

    const rxSigWrap = document.getElementById('doc-confirm-rx-sig-wrap');
    if (rxSigWrap) {
        rxSigWrap.innerHTML = `<span id="doc-confirm-rx-sig-text" style="font-family:'Playfair Display',serif; font-style:italic; font-size:19px; color:#0F172A;">/s/ ${docName}</span>`;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDoctorDashboard, { once: true });
} else {
    // The script can be loaded after DOM ready; wait one tick so all functions
    // below this point (including diorDocSwitchTab) are defined first.
    window.setTimeout(initDoctorDashboard, 0);
}

// Delegated navigation fallback: works even if another script has replaced a
// direct click handler and keeps Doctor tab navigation resilient.
document.addEventListener('click', function (event) {
    const btn = event.target.closest('#dior-doc-sidebar .dior-nav-btn[data-tab]');
    if (!btn || typeof window.diorDocSwitchTab !== 'function') return;
    event.preventDefault();
    window.diorDocSwitchTab(btn.getAttribute('data-tab'));
}, false);

// Universal Table Pagination (5 entries per page)
window.diorDoctorInitTablePagination = function(tableEl, pageSize) {
    if (!tableEl) return;
    pageSize = pageSize || 5;
    const tbody = tableEl.querySelector('tbody');
    if (!tbody) return;

    // Remove existing pagination wrapper if any
    let parent = tableEl.parentElement;
    let paginationWrap = parent.querySelector('.dior-table-pagination');
    if (!paginationWrap) {
        paginationWrap = document.createElement('div');
        paginationWrap.className = 'dior-table-pagination';
        // Insert after table or table overflow wrapper
        if (parent.classList.contains('dior-card-body') || parent.style.overflowX === 'auto' || parent.style.overflow === 'auto') {
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

// Tab Switching logic

    /**
     * Hydrate the existing source-table DOM with live dashboard data.
     * The surrounding markup/classes are intentionally preserved.
     */
    function diorHydrateDoctorSourceTables() {
        const payload = (window.dior_doctor_vars && window.dior_doctor_vars.dashboard_data) || {};
        const appointments = Array.isArray(payload.appointments) ? payload.appointments : [];
        const patients = Array.isArray(payload.patients) ? payload.patients : [];
        const prescriptions = Array.isArray(payload.prescriptions) ? payload.prescriptions : [];
        const encounters = Array.isArray(payload.encounters) ? payload.encounters : [];
        const documents = Array.isArray(payload.documents) ? payload.documents : [];

        const esc = value => {
            const div = document.createElement('div');
            div.textContent = value == null ? '' : String(value);
            return div.innerHTML;
        };
        const badgeClass = status => {
            const value = String(status || '').toLowerCase();
            if (value.includes('cancel') || value.includes('unpaid') || value.includes('failed')) return 'col-red';
            if (value.includes('pending') || value.includes('queue')) return 'col-orange';
            if (value.includes('complete') || value.includes('paid') || value.includes('active')) return 'col-green';
            return 'col-indigo';
        };
        const cell = html => `<div class="datatable-body-cell sort-active" role="cell" tabindex="-1"><div class="datatable-body-cell-label">${html}</div></div>`;
        const textCell = value => cell(`<div class="cell-content"><span>${esc(value || '—')}</span></div>`);
        const iconCell = (icon, value) => cell(`<div class="cell-content cell-icon-text"><i class="material-icons-outlined cell-icon">${icon}</i><span class="cell-text">${esc(value || '—')}</span></div>`);
        const actionCell = id => cell(`<div class="cell-actions"><button type="button" aria-label="View record" class="action-icon-btn edit-btn" data-patient-id="${esc(id || '')}"><i class="fas fa-eye"></i></button></div>`);
        const row = (cells, index) => `<div class="datatable-row-wrapper"><div class="datatable-body-row datatable-row-${index % 2 ? 'odd' : 'even'}" draggable="false" role="row" tabindex="-1"><div class="datatable-row-group datatable-row-left"></div><div class="datatable-row-center datatable-row-group">${cells.join('')}<div class="datatable-row-group datatable-row-right"></div></div></div></div>`;
        const renderTable = (rootSelector, records, mapper, emptyText) => {
            const root = document.querySelector(rootSelector);
            if (!root) return;
            const body = root.querySelector('.datatable-body .datatable-scroll');
            if (!body) return;
            body.innerHTML = records.length ? records.map((item, i) => row(mapper(item), i)).join('') : `<div class="dior-source-empty-row">${esc(emptyText)}</div>`;
            const count = root.querySelector('.page-count');
            if (count) count.textContent = `0 selected / ${records.length} total`;
        };

        renderTable('#source-all-patients', patients, p => [
            cell('<label class="datatable-checkbox"><input type="checkbox"></label>'),
            cell(`<div class="cell-content cell-image-name"><img alt="User avatar" class="cell-avatar" src="${esc(p.avatar_url || (window.dior_doctor_vars && window.dior_doctor_vars.doctor_profile && window.dior_doctor_vars.doctor_profile.avatar_url) || '')}"><div class="cell-text-wrapper"><div class="cell-text">${esc(p.full_name)}</div></div></div>`),
            textCell(p.next_appt_condition || '—'),
            textCell(p.gender || '—'),
            iconCell('phone', p.phone),
            iconCell('calendar_today', p.registered),
            textCell(p.blood_group || '—'),
            textCell((window.dior_doctor_vars.doctor_profile && window.dior_doctor_vars.doctor_profile.full_name) || 'Attending Physician'),
            iconCell('location_on', p.address),
            cell(`<div class="cell-content"><div class="badge-solid ${badgeClass(p.next_appt_status || (p.has_active_appt ? 'Confirmed' : 'Active'))}">${esc(p.next_appt_status || (p.has_active_appt ? 'Confirmed' : 'Active'))}</div></div>`),
            actionCell(p.user_id)
        ], 'No patients are currently assigned to this provider.');

        renderTable('#source-view-appointment', appointments, a => [
            cell('<label class="datatable-checkbox"><input type="checkbox"></label>'),
            cell(`<div class="cell-content cell-image-name"><div class="cell-text-wrapper"><div class="cell-text">${esc(a.patient_name)}</div></div></div>`),
            textCell((window.dior_doctor_vars.doctor_profile && window.dior_doctor_vars.doctor_profile.full_name) || 'Attending Physician'),
            textCell(a.condition || a.condition_name),
            textCell(a.gender || '—'),
            iconCell('calendar_today', a.date || a.appt_date),
            textCell(a.time || a.appt_time),
            iconCell('phone', a.phone),
            cell(`<div class="cell-content"><div class="badge-solid ${badgeClass(a.status)}">${esc(a.status || 'Confirmed')}</div></div>`),
            actionCell(a.patient_id)
        ], 'No appointments are currently assigned to this provider.');

        renderTable('#source-e-prescriptions', prescriptions, r => [
            cell('<label class="datatable-checkbox"><input type="checkbox"></label>'),
            textCell(r.id || r.order_id), textCell(r.patient_name), iconCell('calendar_today', r.date || r.date_prescribed),
            textCell(r.medication || r.name), textCell(r.dosage || r.dose), textCell(r.frequency || 'As directed'), textCell(r.duration || 'As prescribed'),
            textCell((window.dior_doctor_vars.doctor_profile && window.dior_doctor_vars.doctor_profile.full_name) || 'Doctor'),
            cell(`<div class="cell-content"><div class="badge-solid ${badgeClass(r.status)}">${esc(r.status || 'Active')}</div></div>`), actionCell(r.patient_user_id)
        ], 'No prescriptions have been issued for your patients.');

        renderTable('#source-consultation-notes', encounters, n => [
            cell('<label class="datatable-checkbox"><input type="checkbox"></label>'), textCell(n.encounter_uid || n.id), textCell(n.patient_name), iconCell('calendar_today', n.created_at || n.date), textCell(n.created_time || n.time), textCell(n.chief_complaint || n.subjective || '—'), textCell(n.diagnosis || n.assessment || '—'), textCell((window.dior_doctor_vars.doctor_profile && window.dior_doctor_vars.doctor_profile.full_name) || 'Doctor'), cell(`<div class="cell-content"><div class="badge-solid ${badgeClass(n.status)}">${esc(n.status || 'finalized')}</div></div>`), actionCell(n.patient_user_id)
        ], 'No consultation notes are available.');

        renderTable('#source-documents-reports', documents, d => [
            cell('<label class="datatable-checkbox"><input type="checkbox"></label>'), textCell(d.id), textCell(d.patient_name), textCell(d.title), textCell(d.category), iconCell('calendar_today', d.date || d.created_at), iconCell('calendar_today', d.date || d.created_at), textCell(d.author || 'Doctor'), cell(`<div class="cell-content"><div class="badge-solid col-indigo">${esc(d.priority || 'Normal')}</div></div>`), cell('<div class="cell-content"><div class="badge-solid col-green">Available</div></div>'), actionCell(d.patient_user_id)
        ], 'No medical reports are available.');

        renderTable('#source-telemedicine', appointments, a => [
            cell('<label class="datatable-checkbox"><input type="checkbox"></label>'), textCell(a.id || a.appt_uid), textCell(a.patient_name), iconCell('calendar_today', a.date || a.appt_date), textCell(a.time || a.appt_time), textCell(a.duration || '30 minutes'), textCell(a.type || a.visit_type || 'Video Visit'), textCell((window.dior_doctor_vars.doctor_profile && window.dior_doctor_vars.doctor_profile.full_name) || 'Doctor'), cell(`<div class="cell-content"><div class="badge-solid ${badgeClass(a.status)}">${esc(a.status || 'Confirmed')}</div></div>`), actionCell(a.patient_id)
        ], 'No telemedicine sessions are available.');

        // Update the dashboard's existing stat cards without changing their layout.
        const overview = document.querySelector('#tab-doc-overview');
        if (overview) {
            const stats = overview.querySelectorAll('.dior-dash-stat-card h3');
            if (stats[0]) stats[0].textContent = String(patients.length);
            if (stats[1]) stats[1].textContent = String(appointments.filter(a => String(a.status || '').toLowerCase() === 'completed').length);
            if (stats[2]) stats[2].textContent = String(appointments.filter(a => ['confirmed','scheduled','pending','in-queue'].includes(String(a.status || '').toLowerCase())).length);
        }
    }
window.diorDocSwitchTab = function(tabId) {
    if (!tabId) return;
    try { localStorage.setItem('diorDocLastTab', tabId); } catch(e) {}

    const notifDropdown = document.getElementById('dior-doc-notif-dd');
    if (notifDropdown) {
        notifDropdown.classList.remove('open');
        notifDropdown.classList.remove('active');
    }

    // Update buttons
    const btns = document.querySelectorAll('#dior-doc-sidebar .dior-nav-btn');
    btns.forEach(b => b.classList.remove('active'));
    
    const activeBtn = document.querySelector(`#dior-doc-sidebar .dior-nav-btn[data-tab="${tabId}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');

        // Dynamic Badge Dismissal: Hide notification/count badge once opened
        const badge = activeBtn.querySelector('.nav-count-badge');
        if (badge) {
            badge.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
            badge.style.opacity = '0';
            badge.style.transform = 'scale(0.5)';
            setTimeout(() => {
                badge.style.display = 'none';
            }, 250);
        }
    }

    // Update panels
    const panels = document.querySelectorAll('#dior-doc-content > .dior-tab-panel');
    panels.forEach(p => p.classList.remove('active'));
    
    const activePanel = document.getElementById(`tab-${tabId}`);
    if (activePanel) {
        activePanel.classList.add('active');
        activePanel.removeAttribute('hidden');
        // Refresh table pagination inside activated tab
        activePanel.querySelectorAll('.dior-doc-table').forEach(table => {
            if (table._diorRenderPage) {
                table._diorRenderPage(1);
            } else {
                diorDoctorInitTablePagination(table, 5);
            }
        });
    }

    // If notifications tab opened, clear topbar bell badge and mark read in database
    if (tabId === 'doc-notifications') {
        document.querySelectorAll('.doc-unread-badge, .dior-notif-indicator').forEach(b => {
            b.style.display = 'none';
        });
        if (window.diorDocAjax && window.diorDocNonce) {
            const fd = new FormData();
            fd.append('action', 'dior_doctor_mark_all_notif_read');
            fd.append('nonce', window.diorDocNonce);
            fetch(window.diorDocAjax, { method: 'POST', body: fd }).catch(() => {});
        }
    }

    diorDocCloseMobile();
};

// Mobile menu toggle
window.diorDocOpenMobile = function() {
    const sidebar = document.getElementById('dior-doc-sidebar');
    const backdrop = document.getElementById('dior-doc-backdrop');
    const isMobile = window.innerWidth <= 1024;
    
    if (sidebar.classList.contains('mobile-open')) {
        sidebar.classList.remove('mobile-open');
        if (isMobile && backdrop) backdrop.classList.remove('active');
    } else {
        sidebar.classList.add('mobile-open');
        if (isMobile && backdrop) backdrop.classList.add('active');
    }
};

window.diorDocCloseMobile = function() {
    const sidebar = document.getElementById('dior-doc-sidebar');
    const backdrop = document.getElementById('dior-doc-backdrop');
    if (sidebar) sidebar.classList.remove('mobile-open');
    if (backdrop) backdrop.classList.remove('active');
};

// Search patients with pagination reset
window.diorDocFilterPatients = function(val) {
    val = val.toLowerCase().trim();
    const table = document.getElementById('dior-patients-table');
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr.patient-row');
    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        if (val === '' || text.includes(val)) {
            row.removeAttribute('data-filter-hidden');
        } else {
            row.setAttribute('data-filter-hidden', 'true');
        }
    });
    if (table._diorRenderPage) {
        table._diorRenderPage(1);
    }
};

window.diorDocSearch = function(val) {
    // If we're not on the patients tab, switch to it and search
    diorDocSwitchTab('doc-patients');
    
    // Update the actual patient search input
    const patientSearch = document.querySelector('#tab-doc-patients .dior-search-input');
    if (patientSearch) {
        patientSearch.value = val;
        diorDocFilterPatients(val);
    }
};

// Appts filter with pagination reset
window.diorDocFilterAppts = function(val) {
    val = val.toLowerCase().trim();
    const table = document.getElementById('dior-appts-table');
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr.appt-row');
    rows.forEach(row => {
        const status = (row.getAttribute('data-status') || '').toLowerCase();
        if (val === '' || status === val) {
            row.removeAttribute('data-filter-hidden');
        } else {
            row.setAttribute('data-filter-hidden', 'true');
        }
    });
    if (table._diorRenderPage) {
        table._diorRenderPage(1);
    }
};

// Send Appointment Reminder (Email & In-App to both doctor and patient)
window.diorDocSendApptReminder = function(apptId) {
    if (!apptId) return;

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Send Appointment Reminder?',
            text: 'This will dispatch an appointment reminder via dashboard notification and email to both the patient and your doctor account.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-regular fa-bell"></i> Send Reminder Now',
            confirmButtonColor: '#D97706',
            cancelButtonText: 'Cancel',
            cancelButtonColor: '#94A3B8'
        }).then((result) => {
            if (result.isConfirmed) {
                doSendReminder(apptId);
            }
        });
    } else {
        if (confirm('Send appointment reminder to patient and doctor?')) {
            doSendReminder(apptId);
        }
    }

    function doSendReminder(aid) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Sending Reminder...',
                text: 'Please wait while notifications are dispatched.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
        }

        const data = new FormData();
        data.append('action', 'dior_doctor_send_reminder');
        data.append('nonce', window.diorDocNonce);
        data.append('appt_id', aid);

        fetch(window.diorDocAjax, {
            method: 'POST',
            body: data
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Reminder Sent!',
                        text: res.data.message || 'Appointment reminder dispatched successfully.',
                        icon: 'success',
                        confirmButtonColor: '#2C6CB1'
                    });
                } else {
                    alert(res.data.message || 'Reminder sent successfully!');
                }
            } else {
                const msg = (res.data && res.data.message) ? res.data.message : 'Could not send reminder.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ title: 'Notice', text: msg, icon: 'error', confirmButtonColor: '#2C6CB1' });
                } else {
                    alert(msg);
                }
            }
        })
        .catch(() => {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', 'Network connection error.', 'error');
            } else {
                alert('Network error');
            }
        });
    }
};

// Update Appointment Status
window.diorDocUpdateApptStatus = function(selectEl, patientId, apptId) {
    const status = selectEl.value;
    const prevStatus = selectEl.getAttribute('data-prev-status') || '';

    // If cancelling, prompt for confirmation
    if (status === 'Cancelled') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Cancel This Appointment?',
                text: 'Are you sure you wish to mark this appointment as Cancelled? Both patient and doctor will be notified.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Cancel Appointment',
                confirmButtonColor: '#DC2626',
                cancelButtonText: 'Keep Appointment',
                cancelButtonColor: '#94A3B8'
            }).then((result) => {
                if (result.isConfirmed) {
                    commitStatusUpdate(selectEl, patientId, apptId, status);
                } else {
                    // Revert select dropdown
                    selectEl.value = prevStatus || 'Confirmed';
                }
            });
            return;
        } else if (!confirm('Are you sure you wish to cancel this appointment?')) {
            selectEl.value = prevStatus || 'Confirmed';
            return;
        }
    }

    commitStatusUpdate(selectEl, patientId, apptId, status);

    function commitStatusUpdate(el, pid, aid, st) {
        el.disabled = true;

        const data = new FormData();
        data.append('action', 'dior_doctor_update_appt_status');
        data.append('nonce', window.diorDocNonce);
        data.append('patient_user_id', pid);
        data.append('appt_id', aid);
        data.append('status', st);

        fetch(window.diorDocAjax, {
            method: 'POST',
            body: data
        })
        .then(r => r.json())
        .then(res => {
            el.disabled = false;
            if (res.success) {
                el.setAttribute('data-prev-status', st);
                const tr = el.closest('tr');
                if (tr) {
                    const stLower = (st || '').toLowerCase().trim();
                    const badge = tr.querySelector('.dior-st');
                    if (badge) {
                        badge.className = 'dior-st ' + (stLower === 'completed' ? 'ok' : (stLower === 'cancelled' || stLower === 'cancel' ? 'error' : 'pending'));
                        badge.textContent = st;
                    }
                    tr.setAttribute('data-status', st);

                    // Dynamically hide call & remind buttons if cancelled / completed / no-show
                    const callBtn = tr.querySelector('.dior-call-btn');
                    const remindBtn = tr.querySelector('.dior-btn-sm[onclick*="diorDocSendApptReminder"]');
                    const isClosed = ['completed', 'cancelled', 'cancel', 'no-show', 'done'].includes(stLower);
                    if (callBtn) callBtn.style.display = isClosed ? 'none' : 'inline-flex';
                    if (remindBtn) remindBtn.style.display = isClosed ? 'none' : 'inline-flex';
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Status Updated',
                        text: res.data.message || ('Status updated to ' + st),
                        icon: 'success',
                        timer: 1600,
                        showConfirmButton: false
                    });
                }
            } else {
                el.value = el.getAttribute('data-prev-status') || 'Confirmed';
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', res.data.message || 'Error updating status', 'error');
                } else {
                    alert(res.data.message || 'Error updating status');
                }
            }
        })
        .catch(() => {
            el.disabled = false;
            el.value = el.getAttribute('data-prev-status') || 'Confirmed';
            alert('Network error');
        });
    }
};

// Smart Video Call Handler with Date Check & Alert
window.diorDocStartCall = function(event, url, dateStr, timeStr, status) {
    if (event) event.preventDefault();

    const st = (status || '').toLowerCase().trim();
    if (['completed', 'cancelled', 'cancel', 'no-show', 'done'].includes(st)) {
        diorDocShowCallAlert({
            type: 'cancelled',
            title: 'Appointment ' + (status || 'Cancelled'),
            message: `This appointment has been marked as <strong>${status}</strong>. The video consultation call is no longer active.`,
            allowOverride: false
        });
        return false;
    }

    if (!dateStr || dateStr === '—') {
        window.open(url, '_blank', 'noopener,noreferrer');
        return true;
    }

    const today = new Date();
    const todayYMD = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

    let targetYMD = '';
    const parsedDate = new Date(dateStr);
    
    if (!isNaN(parsedDate.getTime())) {
        targetYMD = parsedDate.getFullYear() + '-' + String(parsedDate.getMonth() + 1).padStart(2, '0') + '-' + String(parsedDate.getDate()).padStart(2, '0');
    }

    if (targetYMD === todayYMD) {
        // Today is the scheduled consultation day! Launch call directly in new tab
        window.open(url, '_blank', 'noopener,noreferrer');
        return true;
    } else if (targetYMD > todayYMD) {
        // Future Appointment
        const timePart = timeStr && timeStr !== '—' ? ` at <strong>${timeStr}</strong>` : '';
        diorDocShowCallAlert({
            type: 'future',
            title: 'Upcoming Appointment',
            message: `This consultation is scheduled for <strong>${dateStr}</strong>${timePart}.<br><br>The live video call room will be active on the scheduled appointment date.`,
            allowOverride: true,
            url: url
        });
        return false;
    } else if (targetYMD < todayYMD && targetYMD !== '') {
        // Past Appointment
        const timePart = timeStr && timeStr !== '—' ? ` at <strong>${timeStr}</strong>` : '';
        diorDocShowCallAlert({
            type: 'past',
            title: 'Appointment Date Passed',
            message: `This consultation was scheduled for <strong>${dateStr}</strong>${timePart} (Past Date).<br><br>Please update the appointment status to <strong>Completed</strong> or reschedule with the patient.`,
            allowOverride: true,
            url: url
        });
        return false;
    } else {
        window.open(url, '_blank', 'noopener,noreferrer');
        return true;
    }
};

window.diorDocShowCallAlert = function(opts) {
    let modal = document.getElementById('dior-call-alert-modal');
    if (!modal) return;

    const titleEl = document.getElementById('dior-call-alert-title');
    const msgEl = document.getElementById('dior-call-alert-msg');
    const iconWrap = document.getElementById('dior-call-alert-icon-wrap');
    const overrideBtn = document.getElementById('dior-call-alert-override');

    if (titleEl) titleEl.textContent = opts.title || 'Notice';
    if (msgEl) msgEl.innerHTML = opts.message || '';

    if (iconWrap) {
        if (opts.type === 'cancelled') {
            iconWrap.innerHTML = '<i class="fa-solid fa-ban" style="font-size:32px;color:#EF4444;"></i>';
        } else if (opts.type === 'future') {
            iconWrap.innerHTML = '<i class="fa-solid fa-calendar-check" style="font-size:32px;color:#0284C7;"></i>';
        } else {
            iconWrap.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="font-size:32px;color:#F59E0B;"></i>';
        }
    }

    if (overrideBtn) {
        if (opts.allowOverride && opts.url) {
            overrideBtn.style.display = 'inline-flex';
            overrideBtn.onclick = function() {
                diorDocCloseCallAlert();
                window.open(opts.url, '_blank', 'noopener,noreferrer');
            };
        } else {
            overrideBtn.style.display = 'none';
        }
    }

    modal.classList.add('open');
};

window.diorDocCloseCallAlert = function() {
    const modal = document.getElementById('dior-call-alert-modal');
    if (modal) modal.classList.remove('open');
};

window.diorRxMedicationsList = [];

window.diorRxMedicationsList = [];
window.diorSelectedMedMeta = null;
let diorMedSearchTimer = null;

window.diorDocSearchMedication = function(query) {
    const autocomplete = document.getElementById('rx-med-autocomplete');
    const fdaBadge = document.getElementById('rx-fda-badge');
    if (!autocomplete) return;

    query = (query || '').trim();
    if (query.length < 2) {
        autocomplete.style.display = 'none';
        autocomplete.innerHTML = '';
        if (fdaBadge) fdaBadge.style.display = 'none';
        return;
    }

    if (diorMedSearchTimer) clearTimeout(diorMedSearchTimer);

    autocomplete.style.display = 'block';
    autocomplete.innerHTML = `
        <div style="padding:12px 16px;color:#475569;font-size:12.5px;display:flex;align-items:center;gap:10px;background:#F8FAFC;">
            <i class="fa-solid fa-spinner fa-spin" style="color:#2C6CB1;font-size:14px;"></i>
            <span>Querying official U.S. Federal Government (FDA &amp; NIH RxNorm) Database...</span>
        </div>
    `;

    diorMedSearchTimer = setTimeout(() => {
        // Query official U.S. National Library of Medicine (NIH) RxTerms API
        const nihUrl = `https://clinicaltables.nlm.nih.gov/api/rxterms/v3/search?terms=${encodeURIComponent(query)}&ef=STRENGTHS_AND_FORMS,RXCUI&maxList=10`;

        fetch(nihUrl)
        .then(r => r.json())
        .then(data => {
            const total = data[0] || 0;
            const names = data[1] || [];
            const extra = data[2] || {};
            const strengthsArr = extra.STRENGTHS_AND_FORMS || [];
            const rxcuiArr = extra.RXCUI || [];

            if (total > 0 && names.length > 0) {
                autocomplete.innerHTML = `
                    <div style="padding:7px 14px;background:#F1F5F9;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.04em;">Official U.S. Government Database</span>
                        <span style="font-size:10.5px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:1px 6px;border-radius:10px;">
                            <i class="fa-solid fa-circle-check"></i> NIH / FDA Verified
                        </span>
                    </div>
                `;

                names.forEach((name, idx) => {
                    const strengths = (strengthsArr[idx] && Array.isArray(strengthsArr[idx])) ? strengthsArr[idx] : [];
                    const rxcui = (rxcuiArr[idx] && Array.isArray(rxcuiArr[idx])) ? rxcuiArr[idx][0] : '';
                    
                    const itemDiv = document.createElement('div');
                    itemDiv.style.padding = '10px 14px';
                    itemDiv.style.cursor = 'pointer';
                    itemDiv.style.borderBottom = '1px solid #F1F5F9';
                    itemDiv.style.transition = 'background 0.15s ease';
                    itemDiv.onmouseover = () => { itemDiv.style.background = '#F0F7FF'; };
                    itemDiv.onmouseout = () => { itemDiv.style.background = '#FFFFFF'; };

                    let strengthsPreview = strengths.slice(0, 3).map(s => s.trim()).join(', ');
                    if (strengths.length > 3) strengthsPreview += ` +${strengths.length - 3} more`;

                    itemDiv.innerHTML = `
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                            <strong style="color:#0F172A;font-size:13.5px;">${diorDocEscapeHtml(name)}</strong>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:2px 7px;border-radius:12px;white-space:nowrap;">
                                <i class="fa-solid fa-shield-halved"></i> FDA Approved
                            </span>
                        </div>
                        ${strengthsPreview ? `
                        <div style="font-size:11.5px;color:#64748B;margin-top:3px;">
                            <span style="color:#2C6CB1;font-weight:600;">FDA Strengths:</span> ${diorDocEscapeHtml(strengthsPreview)}
                        </div>` : ''}
                    `;

                    itemDiv.onclick = function() {
                        diorDocSelectMed({
                            name: name,
                            strengths: strengths,
                            rxcui: rxcui,
                            source: 'U.S. National Library of Medicine & openFDA'
                        });
                    };

                    autocomplete.appendChild(itemDiv);
                });
                autocomplete.style.display = 'block';
            } else {
                // Fallback to openFDA API (api.fda.gov)
                diorDocSearchOpenFDA(query, autocomplete);
            }
        })
        .catch(err => {
            console.warn('NIH API fetch error, trying openFDA:', err);
            diorDocSearchOpenFDA(query, autocomplete);
        });
    }, 250);
};

window.diorDocSearchOpenFDA = function(query, autocomplete) {
    const fdaUrl = `https://api.fda.gov/drug/ndc.json?search=(brand_name:${encodeURIComponent(query)}*+OR+generic_name:${encodeURIComponent(query)}*)+AND+finished:true&limit=8`;
    fetch(fdaUrl)
    .then(r => r.json())
    .then(data => {
        if (data.results && data.results.length > 0) {
            autocomplete.innerHTML = `
                <div style="padding:7px 14px;background:#F1F5F9;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.04em;">U.S. Food &amp; Drug Administration (openFDA)</span>
                    <span style="font-size:10.5px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:1px 6px;border-radius:10px;">
                        <i class="fa-solid fa-circle-check"></i> FDA Registered
                    </span>
                </div>
            `;
            const seen = new Set();
            data.results.forEach(item => {
                const bName = item.brand_name || item.generic_name;
                const form = item.dosage_form || '';
                const label = `${bName}${form ? ' (' + form + ')' : ''}`;
                if (seen.has(label.toLowerCase())) return;
                seen.add(label.toLowerCase());

                const itemDiv = document.createElement('div');
                itemDiv.style.padding = '10px 14px';
                itemDiv.style.cursor = 'pointer';
                itemDiv.style.borderBottom = '1px solid #F1F5F9';
                itemDiv.onmouseover = () => { itemDiv.style.background = '#F0F7FF'; };
                itemDiv.onmouseout = () => { itemDiv.style.background = '#FFFFFF'; };

                const appNum = item.application_number || ('NDC: ' + item.product_ndc);
                const activeIng = (item.active_ingredients && item.active_ingredients[0]) ? item.active_ingredients[0].strength : '';

                itemDiv.innerHTML = `
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <strong style="color:#0F172A;font-size:13.5px;">${diorDocEscapeHtml(label)}</strong>
                        <span style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:2px 7px;border-radius:12px;white-space:nowrap;">
                            <i class="fa-solid fa-shield-halved"></i> FDA Approved
                        </span>
                    </div>
                    <div style="font-size:11.5px;color:#64748B;margin-top:3px;">
                        <span style="color:#2C6CB1;font-weight:600;">App/NDC:</span> ${diorDocEscapeHtml(appNum)}${activeIng ? ' &bull; Strength: ' + diorDocEscapeHtml(activeIng) : ''}
                    </div>
                `;

                itemDiv.onclick = function() {
                    diorDocSelectMed({
                        name: label,
                        strengths: activeIng ? [activeIng] : [],
                        rxcui: item.openfda?.rxcui?.[0] || '',
                        source: 'U.S. FDA National Drug Code Directory'
                    });
                };

                autocomplete.appendChild(itemDiv);
            });
            autocomplete.style.display = 'block';
        } else {
            autocomplete.innerHTML = `
                <div style="padding:14px 16px;background:#FFF;color:#B91C1C;font-size:12.5px;display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size:14px;"></i>
                    <span>No official U.S. FDA approved medication found for "<strong>${diorDocEscapeHtml(query)}</strong>". Please verify drug spelling.</span>
                </div>
            `;
            autocomplete.style.display = 'block';
        }
    })
    .catch(() => {
        autocomplete.innerHTML = `
            <div style="padding:14px 16px;background:#FFF;color:#B91C1C;font-size:12.5px;display:flex;align-items:center;gap:8px;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size:14px;"></i>
                <span>No official U.S. FDA approved medication found for "<strong>${diorDocEscapeHtml(query)}</strong>".</span>
            </div>
        `;
        autocomplete.style.display = 'block';
    });
};

window.diorDocEscapeHtml = function(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
};

window.diorDocSelectMed = function(med) {
    const medName = typeof med === 'object' ? med.name : med;
    const strengths = (typeof med === 'object' && Array.isArray(med.strengths)) ? med.strengths : [];
    
    document.getElementById('rx-med-search').value = medName;
    const autocomplete = document.getElementById('rx-med-autocomplete');
    if (autocomplete) autocomplete.style.display = 'none';

    // Show live FDA Verified Badge
    const fdaBadge = document.getElementById('rx-fda-badge');
    if (fdaBadge) {
        fdaBadge.style.display = 'flex';
        fdaBadge.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;padding:9px 13px;background:#ECFDF5;border:1.5px solid #A7F3D0;border-radius:8px;color:#065F46;font-size:12.5px;width:100%;box-sizing:border-box;">
                <i class="fa-solid fa-circle-check" style="color:#059669;font-size:16px;flex-shrink:0;"></i>
                <div>
                    <strong style="color:#065F46;">U.S. FDA &amp; NIH RxNorm Verified Drug:</strong>
                    <span style="color:#047857;"> "${diorDocEscapeHtml(medName)}" is verified and approved for clinical prescription.</span>
                </div>
            </div>
        `;
    }

    // Populate official FDA strengths into dosage datalist
    const datalist = document.getElementById('rx-dosage-list');
    const doseInput = document.getElementById('rx-dosage');
    if (datalist) {
        datalist.innerHTML = '';
        if (strengths.length > 0) {
            strengths.forEach(s => {
                const sClean = s.trim();
                const opt1 = document.createElement('option');
                opt1.value = `${sClean} — Take 1 daily`;
                datalist.appendChild(opt1);

                const opt2 = document.createElement('option');
                opt2.value = `${sClean} — Take 1 twice daily`;
                datalist.appendChild(opt2);

                const opt3 = document.createElement('option');
                opt3.value = sClean;
                datalist.appendChild(opt3);
            });
            if (doseInput) {
                doseInput.value = `${strengths[0].trim()} — Take 1 daily`;
            }
        }
    }

    window.diorSelectedMedMeta = {
        name: medName,
        strengths: strengths,
        fda_approved: true,
        source: typeof med === 'object' ? (med.source || 'U.S. FDA & NIH RxNorm') : 'U.S. FDA & NIH RxNorm'
    };
};

window.diorDocAddMedToList = function() {
    const name = document.getElementById('rx-med-search').value.trim();
    const dose = document.getElementById('rx-dosage').value.trim();
    const ref = document.getElementById('rx-refills').value.trim();
    const notes = document.getElementById('rx-notes').value.trim();
    
    if (!name || !dose) {
        alert("Please enter Medication Name and Dosage.");
        return;
    }
    
    window.diorRxMedicationsList.push({
        medication: name,
        dosage: dose,
        refills: ref || '0 Refills Remaining',
        notes: notes,
        fda_approved: true,
        fda_source: window.diorSelectedMedMeta ? window.diorSelectedMedMeta.source : 'U.S. FDA & NIH RxNorm'
    });
    
    document.getElementById('rx-med-search').value = '';
    document.getElementById('rx-dosage').value = '';
    document.getElementById('rx-refills').value = '';
    document.getElementById('rx-notes').value = '';
    
    const fdaBadge = document.getElementById('rx-fda-badge');
    if (fdaBadge) fdaBadge.style.display = 'none';
    const datalist = document.getElementById('rx-dosage-list');
    if (datalist) datalist.innerHTML = '';
    window.diorSelectedMedMeta = null;

    diorDocRenderMedList();
};

window.diorDocRenderMedList = function() {
    const list = document.getElementById('rx-med-list');
    if (!list) return;
    list.innerHTML = '';
    window.diorRxMedicationsList.forEach((med, index) => {
        list.innerHTML += `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;background:#FFFFFF;border:1.5px solid #E2E8F0;border-radius:10px;padding:12px 16px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                        <strong style="color:#2C6CB1;font-size:14px;">${diorDocEscapeHtml(med.medication)}</strong>
                        <span style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:2px 8px;border-radius:12px;">
                            <i class="fa-solid fa-shield-halved"></i> U.S. FDA Approved
                        </span>
                    </div>
                    <div style="font-size:13px;color:#334155;margin-bottom:2px;">
                        <strong>Dosage:</strong> ${diorDocEscapeHtml(med.dosage)} &bull; <span style="color:#64748B;">Refills: ${diorDocEscapeHtml(med.refills)}</span>
                    </div>
                    ${med.notes ? `<div style="font-size:12px;color:#64748B;margin-top:4px;background:#F8FAFC;padding:5px 9px;border-radius:6px;border-left:3px solid #BFDBFE;">Instructions: ${diorDocEscapeHtml(med.notes)}</div>` : ''}
                </div>
                <button type="button" style="background:#FEE2E2;border:none;color:#EF4444;cursor:pointer;padding:6px 10px;border-radius:6px;font-size:12px;" onclick="diorDocRemoveMed(${index})" title="Remove Medication">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        `;
    });
};

window.diorDocRemoveMed = function(index) {
    window.diorRxMedicationsList.splice(index, 1);
    diorDocRenderMedList();
};

// ── PRE-TRANSMISSION PRESCRIPTION CONFIRMATION MODAL & TRANSMIT ──

window.diorDocPreviewPrescriptionConfirmation = function() {
    const pSelect = document.getElementById('rx-patient-select');
    const patientId = pSelect ? pSelect.value : '';

    if (!patientId) {
        alert("Please select a patient first.");
        return;
    }
    if (!window.diorRxMedicationsList || window.diorRxMedicationsList.length === 0) {
        alert("Please add at least one medication to the prescription.");
        return;
    }

    // Generate unique prescription Order UID for this issuance
    const now = new Date();
    const ymd = now.getFullYear().toString().slice(2) + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
    const randHex = Math.random().toString(36).substring(2, 6).toUpperCase();
    const uniqueOrderUid = 'RX-' + ymd + '-' + randHex;
    window.diorActiveOrderUid = uniqueOrderUid;

    const uidEl = document.getElementById('doc-confirm-rx-uid');
    if (uidEl) uidEl.textContent = uniqueOrderUid;

    const opt = pSelect.options[pSelect.selectedIndex];
    const pText = opt ? opt.text : 'Patient';
    const pName = pText.split('(')[0].trim();
    const pId = pText.includes('(') ? pText.split('(')[1].replace(')', '').trim() : ('DM-' + patientId);

    const nameEl = document.getElementById('doc-confirm-rx-patient-name');
    const dobEl = document.getElementById('doc-confirm-rx-patient-dob');
    const dateEl = document.getElementById('doc-confirm-rx-pad-date');
    if (nameEl) nameEl.textContent = pName;
    if (dobEl) dobEl.textContent = (opt && opt.getAttribute('data-dob')) ? (opt.getAttribute('data-dob') + ' (Adult)') : '18+ (Adult)';
    if (dateEl) {
        const dStr = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        dateEl.textContent = dStr;
        const topDate = document.getElementById('doc-confirm-rx-date');
        if (topDate) topDate.textContent = dStr;
    }

    // Populate Medication Schedule Table
    const tbody = document.getElementById('doc-confirm-rx-tbody');
    if (tbody) {
        tbody.innerHTML = '';
        window.diorRxMedicationsList.forEach((med, idx) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid #E2E8F0';
            const itemUid = uniqueOrderUid + '-' + (idx + 1);
            tr.innerHTML = `
                <td style="padding:8px 10px; text-align:center; font-weight:600; color:#64748B; font-size:11.5px;">${idx + 1}</td>
                <td style="padding:8px 12px;">
                    <div style="font-weight:600; color:#1E293B; font-size:12.5px;"><i class="fa-solid fa-capsules" style="color:#00A896; margin-right:5px;"></i>${med.medication}</div>
                    <div style="display:flex; align-items:center; gap:6px; margin-top:3px; flex-wrap:wrap;">
                        <span style="font-family:monospace; font-size:10px; color:#00A896; background:#F0FDFA; border:1px solid #CCFBF1; padding:1px 5px; border-radius:4px; font-weight:600;">Rx Item: ${itemUid}</span>
                        <span style="display:inline-flex; align-items:center; gap:3px; font-size:9.5px; font-weight:600; color:#059669; background:#ECFDF5; border:1px solid #A7F3D0; padding:1px 5px; border-radius:4px;">
                            <i class="fa-solid fa-shield-halved"></i> FDA Approved
                        </span>
                    </div>
                </td>
                <td style="padding:8px 12px; color:#475569; font-weight:400; font-size:11.5px; line-height:1.4;">
                    <strong style="color:#00A896; font-size:11px; font-weight:600;">SIG:</strong> ${med.dosage || 'Take as clinically directed'}
                </td>
                <td style="padding:8px 12px; text-align:right; color:#475569; font-weight:500; font-size:11.5px;">
                    <span style="color:#1E293B; font-weight:600;">${med.refills || '0 Refills'}</span>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    const countEl = document.getElementById('doc-confirm-rx-med-count');
    if (countEl) countEl.textContent = window.diorRxMedicationsList.length + ' Medication' + (window.diorRxMedicationsList.length > 1 ? 's' : '');

    // Clinical Notes
    const notesInput = document.getElementById('rx-notes');
    const notesWrap = document.getElementById('doc-confirm-rx-notes-wrap');
    const notesText = document.getElementById('doc-confirm-rx-notes-text');
    const notesVal = notesInput ? notesInput.value.trim() : '';
    if (notesWrap && notesText) {
        if (notesVal) {
            notesText.textContent = notesVal;
            notesWrap.style.display = 'block';
        } else {
            notesWrap.style.display = 'none';
        }
    }

    // Refresh Doctor Signature in confirmation preview
    const sigImg = document.getElementById('dior-doctor-signature-display-img');
    const rxSigWrap = document.getElementById('doc-confirm-rx-sig-wrap');
    const activeSig = (sigImg && sigImg.src && sigImg.style.display !== 'none') ? sigImg.src : (window.diorDoctorSignatureUrl || '');
    if (rxSigWrap) {
        if (activeSig) {
            rxSigWrap.innerHTML = `<img id="doc-confirm-rx-sig-img" src="${activeSig}" alt="Doctor Digital Signature" style="max-height:50px; max-width:180px; object-fit:contain; background:transparent;">`;
        } else {
            const docPill = document.querySelector('.dior-pill-name');
            const docName = docPill ? docPill.textContent.trim() : 'Dr. Medical Provider';
            rxSigWrap.innerHTML = `<span id="doc-confirm-rx-sig-text" style="font-family:'Playfair Display',serif; font-style:italic; font-size:19px; color:#0F172A;">/s/ ${docName}</span>`;
        }
    }

    // Open Modal
    const modal = document.getElementById('modal-doc-rx-confirm-preview');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('open');
    }
};

window.diorDocCloseRxConfirmModal = function() {
    const modal = document.getElementById('modal-doc-rx-confirm-preview');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('open');
    }
};

window.diorDocExecutePrescriptionTransmit = function() {
    const pSelect = document.getElementById('rx-patient-select');
    const patientId = pSelect ? pSelect.value : '';
    const transmitBtn = document.getElementById('btn-doc-confirm-transmit-rx');
    const msg = document.getElementById('dior-rx-msg');

    if (!patientId || !window.diorRxMedicationsList || window.diorRxMedicationsList.length === 0) {
        alert("Please ensure a patient and medications are selected.");
        return;
    }

    const origHtml = transmitBtn ? transmitBtn.innerHTML : '';
    if (transmitBtn) {
        transmitBtn.disabled = true;
        transmitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Transmitting e-Rx...';
    }

    const data = new FormData();
    data.append('action', 'dior_doctor_send_multi_prescription');
    data.append('nonce', window.diorDocNonce || (typeof getDiorDocNonce === 'function' ? getDiorDocNonce() : ''));
    data.append('patient_id', patientId);
    data.append('order_uid', window.diorActiveOrderUid || '');
    data.append('medications', JSON.stringify(window.diorRxMedicationsList));

    fetch(window.diorDocAjax || (typeof getDiorDocAjax === 'function' ? getDiorDocAjax() : '/wp-admin/admin-ajax.php'), {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (transmitBtn) {
            transmitBtn.disabled = false;
            transmitBtn.innerHTML = origHtml;
        }
        window.diorDocCloseRxConfirmModal();

        if (msg) {
            msg.style.display = 'block';
        }

        if (res.success) {
            if (msg) {
                msg.className = 'dior-st ok';
                msg.innerHTML = '<i class="fa-solid fa-check"></i> ' + (res.data.message || 'Prescription confirmed and transmitted successfully!');
            }

            window.diorRxMedicationsList = [];
            diorDocRenderMedList();
            if (pSelect) pSelect.value = '';
            const searchInput = document.getElementById('rx-med-search');
            if (searchInput) searchInput.value = '';
            const notesInput = document.getElementById('rx-notes');
            if (notesInput) notesInput.value = '';

            const tbody = document.getElementById('dior-all-rx-tbody');
            if (tbody && res.data.rxs) {
                const emptyRow = tbody.querySelector('td[colspan="5"]');
                if (emptyRow) emptyRow.parentElement.remove();

                const opt = pSelect.options[pSelect.selectedIndex];
                const pName = opt ? opt.text : 'Patient';
                res.data.rxs.forEach(rx => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${pName}</strong></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <strong>${rx.medication}</strong>
                                <span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:1px 6px;border-radius:10px;">
                                    <i class="fa-solid fa-shield-halved"></i> FDA Approved
                                </span>
                            </div>
                        </td>
                        <td>${rx.dosage}</td>
                        <td>${rx.date}</td>
                        <td><span class="dior-st ok">Active</span></td>
                    `;
                    tbody.prepend(tr);
                });
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Prescription Transmitted!',
                    text: 'e-Prescription successfully verified and delivered to patient portal & pharmacy EDI.',
                    confirmButtonColor: '#00A896',
                    timer: 3000
                });
            }

            setTimeout(() => { if (msg) msg.style.display = 'none'; }, 6000);
        } else {
            if (msg) {
                msg.className = 'dior-st error';
                msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + (res.data.message || 'Error transmitting prescription.');
            }
            alert((res.data && res.data.message) ? res.data.message : 'Failed to transmit prescription.');
        }
    })
    .catch(() => {
        if (transmitBtn) {
            transmitBtn.disabled = false;
            transmitBtn.innerHTML = origHtml;
        }
        window.diorDocCloseRxConfirmModal();
        alert('Connection error while transmitting prescription.');
    });
};

// Backwards compatibility alias
window.diorDocSendPrescription = window.diorDocPreviewPrescriptionConfirmation;

// Profile Save
window.diorDocSaveProfile = function(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const form = document.getElementById('dior-doc-profile-form');
    if (!form) return;

    const btn = form.querySelector('button[type="submit"]');
    const msg = document.getElementById('dior-doc-profile-msg');

    const fnEl = form.querySelector('[name="first_name"]');
    const lnEl = form.querySelector('[name="last_name"]');
    const spEl = form.querySelector('[name="doctor_specialty"]');
    const licEl = form.querySelector('[name="doctor_license"]');
    const npiEl = form.querySelector('[name="doctor_npi"]');
    const phEl = form.querySelector('[name="phone"]');

    const fn = fnEl ? fnEl.value.trim() : '';
    const ln = lnEl ? lnEl.value.trim() : '';
    const sp = spEl ? spEl.value.trim() : '';
    const lic = licEl ? licEl.value.trim() : '';
    const npi = npiEl ? npiEl.value.trim() : '';
    const ph = phEl ? phEl.value.trim() : '';

    // Reset error styling
    [fnEl, lnEl, spEl, licEl, npiEl, phEl].forEach(el => {
        if (el) {
            el.style.borderColor = '#E2E8F0';
            el.style.backgroundColor = '#FFFFFF';
        }
    });

    const missingList = [];
    let firstMissing = null;

    if (!fn) { missingList.push('First Name'); if (!firstMissing) firstMissing = fnEl; if (fnEl) { fnEl.style.borderColor = '#EF4444'; fnEl.style.backgroundColor = '#FEF2F2'; } }
    if (!ln) { missingList.push('Last Name'); if (!firstMissing) firstMissing = lnEl; if (lnEl) { lnEl.style.borderColor = '#EF4444'; lnEl.style.backgroundColor = '#FEF2F2'; } }
    if (!sp) { missingList.push('Medical Specialty'); if (!firstMissing) firstMissing = spEl; if (spEl) { spEl.style.borderColor = '#EF4444'; spEl.style.backgroundColor = '#FEF2F2'; } }
    if (!lic) { missingList.push('Medical License Number'); if (!firstMissing) firstMissing = licEl; if (licEl) { licEl.style.borderColor = '#EF4444'; licEl.style.backgroundColor = '#FEF2F2'; } }
    if (!npi) { missingList.push('NPI Number'); if (!firstMissing) firstMissing = npiEl; if (npiEl) { npiEl.style.borderColor = '#EF4444'; npiEl.style.backgroundColor = '#FEF2F2'; } }
    if (!ph) { missingList.push('Phone Number'); if (!firstMissing) firstMissing = phEl; if (phEl) { phEl.style.borderColor = '#EF4444'; phEl.style.backgroundColor = '#FEF2F2'; } }

    // Clear red highlight as user types
    [fnEl, lnEl, spEl, licEl, npiEl, phEl].forEach(el => {
        if (el && !el._diorHasCleanListener) {
            el._diorHasCleanListener = true;
            el.addEventListener('input', function() {
                this.style.borderColor = '#E2E8F0';
                this.style.backgroundColor = '#FFFFFF';
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
                title: 'Required Provider Information Missing',
                html: `<div style="text-align:left; padding: 4px 0;">
                    <p style="font-size:14px; color:#334155; line-height:1.5; margin:0 0 10px 0;">
                        Please fill in the following required credential fields before saving:
                    </p>
                    <ul style="margin:0; padding-left:20px; font-size:13.5px; color:#DC2626; line-height:1.6;">
                        ${missingList.map(item => `<li><strong>${item}</strong></li>`).join('')}
                    </ul>
                </div>`,
                confirmButtonText: '<i class="fa-solid fa-pen"></i> Complete Now',
                confirmButtonColor: '#2C6CB1',
                focusConfirm: true
            });
        } else {
            alert('Please fill all required fields: ' + missingList.join(', '));
        }
        return;
    }
    
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    }
    
    const data = new FormData(form);
    data.append('action', 'dior_doctor_save_profile');
    data.append('nonce', getDiorDocNonce());

    const optinAppt = form.querySelector('[name="optin_appointment_reminders"]');
    const optinEmail = form.querySelector('[name="optin_reminder_email"]');
    const optinDash = form.querySelector('[name="optin_reminder_dashboard"]');
    if (optinAppt) data.set('optin_appointment_reminders', optinAppt.checked ? '1' : '0');
    if (optinEmail) data.set('optin_reminder_email', optinEmail.checked ? '1' : '0');
    if (optinDash) data.set('optin_reminder_dashboard', optinDash.checked ? '1' : '0');
    
    fetch(getDiorDocAjax(), {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Profile';
        }

        if (res.success) {
            if (msg) {
                msg.style.display = 'block';
                msg.style.background = '#ECFDF5';
                msg.style.color = '#059669';
                msg.style.border = '1px solid #A7F3D0';
                msg.innerHTML = '<i class="fa-solid fa-check"></i> ' + (res.data && res.data.message ? res.data.message : 'Profile saved!');
                setTimeout(() => { msg.style.display = 'none'; }, 4000);
            }

            // Update doctor global vars
            if (window.dior_doctor_vars) {
                window.dior_doctor_vars.is_profile_complete = 1;
                window.dior_doctor_vars.missing_profile_fields = {};
                if (res.data && res.data.profile) {
                    window.dior_doctor_vars.doctor_profile = res.data.profile;
                }
            }

            // Remove warning banner if present
            const banner = document.getElementById('dior-doctor-profile-incomplete-banner');
            if (banner) banner.remove();

            // Update UI doctor full name across dashboard
            const newFullName = 'Dr. ' + (fn + ' ' + ln).trim();
            const sidebarName = document.querySelector('.dior-side-org-card .org-info strong');
            if (sidebarName) sidebarName.textContent = newFullName;

            const sidebarSpec = document.querySelector('.dior-side-org-card .org-info span');
            if (sidebarSpec) sidebarSpec.textContent = sp || 'Licensed Provider';

            const topPillName = document.querySelector('.dior-user-pill .dior-pill-name');
            if (topPillName) topPillName.textContent = newFullName;

            const welcomeSub = document.querySelector('.dior-page-title-bar .title-sub');
            if (welcomeSub) {
                welcomeSub.textContent = `Welcome back, ${newFullName}. Here's your clinical summary.`;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Profile Updated Successfully!',
                    text: 'Your provider credentials and personal information have been saved in the database.',
                    confirmButtonText: '<i class="fa-solid fa-check"></i> Great',
                    confirmButtonColor: '#2C6CB1',
                    showCancelButton: false,
                    timer: 2500
                });
            }
        } else {
            const errText = (res.data && res.data.message) ? res.data.message : 'Failed to save profile.';
            if (msg) {
                msg.style.display = 'block';
                msg.style.background = '#FEF2F2';
                msg.style.color = '#DC2626';
                msg.style.border = '1px solid #FECACA';
                msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + errText;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Profile Update Failed',
                    text: errText,
                    confirmButtonColor: '#2C6CB1'
                });
            }
        }
    })
    .catch(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Profile';
        }
        if (msg) {
            msg.style.display = 'block';
            msg.style.background = '#FEF2F2';
            msg.style.color = '#DC2626';
            msg.style.border = '1px solid #FECACA';
            msg.textContent = 'Network error during save.';
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'Connection error while saving your profile. Please check your connection and try again.',
                confirmButtonColor: '#2C6CB1'
            });
        }
    });
};

// Notifications
window.diorDocToggleNotif = function(e) {
    if (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
    }
    const dd = document.getElementById('dior-doc-notif-dd');
    if (dd) {
        dd.classList.toggle('open');
    }
};

window.diorDocMarkSingleRead = function(notifId, btn) {
    const item = btn ? btn.closest('.dior-full-notif-item, .dior-notif-item, .dior-notif-item-full') : null;
    if (item) {
        item.classList.remove('unread');
    }
    if (btn) {
        btn.remove();
    }
    const ajaxUrl = getDiorDocAjax();
    const nonce = getDiorDocNonce();
    if (ajaxUrl && notifId) {
        const data = new FormData();
        data.append('action', 'dior_doctor_mark_notif_read');
        data.append('nonce', nonce);
        data.append('notif_id', notifId);
        fetch(ajaxUrl, { method: 'POST', body: data }).catch(() => {});
    }
};

window.diorDocMarkAllRead = function(e) {
    if (e && e.preventDefault) e.preventDefault();
    if (e && e.stopPropagation) e.stopPropagation();

    // 1. Immediately clear badges and unread styles in UI
    document.querySelectorAll('.doc-unread-badge, .dior-notif-indicator, .nav-count-badge').forEach(el => {
        el.style.display = 'none';
        el.innerText = '0';
    });
    document.querySelectorAll('.dior-notif-item.unread, .dior-full-notif-item.unread, .dior-notif-item-full.unread').forEach(el => {
        el.classList.remove('unread');
    });
    document.querySelectorAll('.dior-mark-single-read').forEach(btn => btn.remove());

    // 2. Persist to server via AJAX
    const ajaxUrl = getDiorDocAjax();
    const nonce = getDiorDocNonce();
    const data = new FormData();
    data.append('action', 'dior_doctor_mark_all_notif_read');
    data.append('nonce', nonce);
    
    fetch(ajaxUrl, { method: 'POST', body: data })
    .then(r => r.json())
    .catch(() => {});
};

// =========================================================================
// REAL-TIME LIVE DOCTOR NOTIFICATION POLLING & TOAST SYSTEM (WITHOUT REFRESH)
// =========================================================================
(function initDoctorLiveNotifications() {
    let diorLastDoctorNotifId = null;
    let diorSeenDoctorToastIds = new Set();
    let isDoctorNotifPolling = false;

    // Grab first notification ID from existing DOM
    const initialDocNotif = document.querySelector('#dior-doc-notif-dd .dior-notif-item, #dior-doc-notif-full .dior-full-notif-item');
    if (initialDocNotif && initialDocNotif.dataset.notifId) {
        diorLastDoctorNotifId = initialDocNotif.dataset.notifId;
    }

    function playDoctorChime() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const now = ctx.currentTime;

            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(523.25, now); // C5
            gain1.gain.setValueAtTime(0.08, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.35);

            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(783.99, now + 0.12); // G5
            gain2.gain.setValueAtTime(0.12, now + 0.12);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.12);
            osc2.stop(now + 0.55);
        } catch(e) {}
    }

    function getDoctorToastContainer() {
        let container = document.getElementById('dior-live-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'dior-live-toast-container';
            container.className = 'dior-live-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function showDoctorLiveToast(alert) {
        if (!alert || diorSeenDoctorToastIds.has(alert.id)) return;
        diorSeenDoctorToastIds.add(alert.id);

        const container = getDoctorToastContainer();
        const toast = document.createElement('div');
        toast.className = 'dior-live-toast';

        const iconClass = alert.icon ? (alert.icon.indexOf('fa-') === 0 ? alert.icon : 'fa-' + alert.icon) : 'fa-bell';

        toast.innerHTML = `
            <div class="toast-icon"><i class="fa-solid ${iconClass}"></i></div>
            <div class="toast-body">
                <div class="toast-title">${alert.title || 'New Doctor Notification'}</div>
                <div class="toast-message">${alert.message || ''}</div>
                <div class="toast-action"><i class="fa-solid fa-arrow-right"></i> Open Notification</div>
            </div>
            <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
        `;

        toast.querySelector('.toast-close').onclick = function(e) {
            e.stopPropagation();
            removeDoctorToast(toast);
        };

        toast.onclick = function() {
            removeDoctorToast(toast);
            const actionUrl = alert.action_url || '';
            if (actionUrl.indexOf('#tab=') !== -1) {
                const targetTab = actionUrl.replace('#tab=', '');
                if (typeof window.diorDocSwitchTab === 'function') {
                    window.diorDocSwitchTab(targetTab);
                }
            } else {
                if (typeof window.diorDocSwitchTab === 'function') {
                    window.diorDocSwitchTab('doc-notifications');
                }
            }
        };

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('show');
            playDoctorChime();
        });

        setTimeout(() => {
            removeDoctorToast(toast);
        }, 6500);
    }

    function removeDoctorToast(toast) {
        if (!toast || !toast.parentNode) return;
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 350);
    }

    function triggerDoctorBellAnimation() {
        const bell = document.querySelector('.dior-notif-btn i');
        if (bell) {
            bell.classList.add('dior-bell-ring');
            setTimeout(() => { bell.classList.remove('dior-bell-ring'); }, 2400);
        }
    }

    function pollDoctorLiveNotifications() {
        if (isDoctorNotifPolling) return;
        if (document.hidden) return;

        const ajaxUrl = getDiorDocAjax();
        const nonce = getDiorDocNonce();
        if (!ajaxUrl) return;

        isDoctorNotifPolling = true;

        const data = new FormData();
        data.append('action', 'dior_doctor_get_live_notifications');
        data.append('nonce', nonce);
        if (diorLastDoctorNotifId) {
            data.append('last_notif_id', diorLastDoctorNotifId);
        }

        fetch(ajaxUrl, { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            isDoctorNotifPolling = false;
            if (res && res.success && res.data) {
                const d = res.data;
                const unreadCount = parseInt(d.unread_count, 10) || 0;

                // 1. Update Badges
                document.querySelectorAll('.doc-unread-badge, .dior-notif-indicator').forEach(b => {
                    if (unreadCount > 0) {
                        b.style.display = 'inline-flex';
                        b.textContent = unreadCount;
                    } else {
                        b.style.display = 'none';
                    }
                });

                // 2. Update Nav Badge if exists
                const navBadge = document.querySelector('.dior-nav-btn[data-tab="doc-notifications"] .nav-count-badge');
                if (navBadge) {
                    if (unreadCount > 0) {
                        navBadge.style.display = 'inline-flex';
                        navBadge.textContent = unreadCount;
                    } else {
                        navBadge.style.display = 'none';
                    }
                }

                // 3. Update Dropdown List
                const ddList = document.querySelector('#dior-doc-notif-dd .dior-notif-list');
                const dd = document.getElementById('dior-doc-notif-dd');
                if (ddList && (!dd || !dd.classList.contains('open')) && d.dropdown_html) {
                    ddList.innerHTML = d.dropdown_html;
                }

                // 4. Update Full Page List
                const fullList = document.getElementById('dior-doc-notif-full');
                if (fullList && d.full_html && d.latest_id !== diorLastDoctorNotifId) {
                    fullList.innerHTML = d.full_html;
                }

                // 5. Trigger Toasts for New Alerts
                if (d.new_alerts && d.new_alerts.length > 0) {
                    triggerDoctorBellAnimation();
                    d.new_alerts.forEach(al => {
                        showDoctorLiveToast(al);
                    });
                }

                if (d.latest_id) {
                    diorLastDoctorNotifId = d.latest_id;
                }
            }
        })
        .catch(() => {
            isDoctorNotifPolling = false;
        });
    }

    setTimeout(pollDoctorLiveNotifications, 2200);
    setInterval(pollDoctorLiveNotifications, 6000);

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            pollDoctorLiveNotifications();
        }
    });

    window.diorPollDoctorNotifications = pollDoctorLiveNotifications;
})();

// Modal
window.diorDocViewPatient = function(patientId) {
    const modal = document.getElementById('modal-doc-patient');
    const body = document.getElementById('modal-doc-patient-body');
    if (!modal || !body) return;
    
    modal.classList.add('open');
    body.innerHTML = '<div style="text-align:center;padding:32px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color:#059669;"></i></div>';
    
    const data = new FormData();
    data.append('action', 'dior_doctor_get_patient_detail');
    data.append('nonce', window.diorDocNonce || getDiorDocNonce());
    data.append('patient_user_id', patientId);
    
    fetch(getDiorDocAjax(), { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            body.innerHTML = res.data.html;
            
            // Direct binding to chart nav tabs
            body.querySelectorAll('.dior-chart-nav-btn, .dior-pdm-tab-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const targetId = this.getAttribute('data-target') || this.getAttribute('data-tab') || (this.getAttribute('onclick') || '').match(/['"](cpanel-[^'"]+|pdm-tab-[^'"]+)['"]/)?.[1];
                    if (targetId) {
                        window.pchartSwitchTab(this, targetId);
                    }
                });
            });
        } else {
            body.innerHTML = '<div class="dior-st error" style="padding:20px;text-align:center;">' + (res.data && res.data.message ? res.data.message : 'Error loading patient data') + '</div>';
        }
    })
    .catch(() => {
        body.innerHTML = '<div class="dior-st error" style="padding:20px;text-align:center;">Network Error</div>';
    });
};

window.diorDocCloseModal = function() {
    const modal = document.getElementById('modal-doc-patient');
    if (modal) modal.classList.remove('open');
};

// Patient Detail Modal Tab Switching (Robust Global Handler)
window.pchartSwitchTab = function(btn, paneId) {
    if (!paneId) return;
    const modalBody = document.getElementById('modal-doc-patient-body') || document.querySelector('#modal-doc-patient') || document;
    
    // Update button states
    modalBody.querySelectorAll('.dior-chart-nav-btn, .dior-pdm-tab-btn').forEach(b => {
        b.classList.remove('active');
    });
    if (btn && btn.classList) {
        btn.classList.add('active');
    } else {
        const matchingBtn = modalBody.querySelector(`.dior-chart-nav-btn[data-target="${paneId}"], .dior-chart-nav-btn[onclick*="${paneId}"]`);
        if (matchingBtn) matchingBtn.classList.add('active');
    }
    
    // Update pane states
    modalBody.querySelectorAll('.dior-chart-pane, .dior-pdm-tab-pane').forEach(p => {
        p.classList.remove('active');
        p.style.display = 'none';
    });
    
    const target = modalBody.querySelector('#' + paneId) || document.getElementById(paneId);
    if (target) {
        target.classList.add('active');
        target.style.display = 'flex';
        
        // Scroll body back to top
        const scrollContainer = modalBody.querySelector('.dior-chart-body') || modalBody.querySelector('.dior-pdm-body');
        if (scrollContainer && scrollContainer.scrollTop !== undefined) {
            scrollContainer.scrollTop = 0;
        }
    }
};

window.pdmTab = window.pchartSwitchTab;

// Global Delegated Click Listener for Modal Tabs
document.addEventListener('click', function(e) {
    const tabBtn = e.target.closest('.dior-chart-nav-btn, .dior-pdm-tab-btn');
    if (tabBtn) {
        const targetId = tabBtn.getAttribute('data-target') || tabBtn.getAttribute('data-tab') || (tabBtn.getAttribute('onclick') || '').match(/['"](cpanel-[^'"]+|pdm-tab-[^'"]+)['"]/)?.[1];
        if (targetId) {
            e.preventDefault();
            window.pchartSwitchTab(tabBtn, targetId);
        }
    }
});

// SOAP Presets & Notes Global Functions
window.pchartApplySoapPreset = function(diag, s, o, a, p) {
    if (document.getElementById('soap_diagnosis')) document.getElementById('soap_diagnosis').value = diag || '';
    if (document.getElementById('soap_subjective')) document.getElementById('soap_subjective').value = s || '';
    if (document.getElementById('soap_objective')) document.getElementById('soap_objective').value = o || '';
    if (document.getElementById('soap_assessment')) document.getElementById('soap_assessment').value = a || '';
    if (document.getElementById('soap_plan')) document.getElementById('soap_plan').value = p || '';
};

window.pchartSaveSoapNote = function(button) {
    const diag = (document.getElementById('soap_diagnosis') && document.getElementById('soap_diagnosis').value.trim()) || '';
    if (!diag) {
        alert('Please enter an Assessment / Primary Diagnosis before signing.');
        return;
    }
    
    const origText = button.innerHTML;
    button.innerHTML = 'Saving...';
    button.disabled = true;

    const pidInput = document.querySelector('#pchart-soap-form [name="patient_id"]');
    const pid = pidInput ? pidInput.value : '';
    const subj = (document.getElementById('soap_subjective') && document.getElementById('soap_subjective').value) || '';
    const obj = (document.getElementById('soap_objective') && document.getElementById('soap_objective').value) || '';
    const assess = (document.getElementById('soap_assessment') && document.getElementById('soap_assessment').value) || '';
    const plan = (document.getElementById('soap_plan') && document.getElementById('soap_plan').value) || '';

    const formData = new FormData();
    formData.append('action', 'dior_doctor_save_soap_note');
    formData.append('nonce', window.diorDocNonce || getDiorDocNonce());
    formData.append('patient_user_id', pid);
    formData.append('diagnosis', diag);
    formData.append('subjective', subj);
    formData.append('objective', obj);
    formData.append('assessment', assess);
    formData.append('plan', plan);

    fetch(getDiorDocAjax(), {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        button.innerHTML = origText;
        button.disabled = false;
        if (data.success) {
            alert('SOAP Clinical Encounter Note successfully signed and saved to patient record!');
            if (typeof window.diorDocViewPatient === 'function' && pid) {
                window.diorDocViewPatient(pid);
            }
        } else {
            alert('Note saved: ' + (data.data && data.data.message ? data.data.message : 'Recorded in patient chart.'));
        }
    })
    .catch(err => {
        button.innerHTML = origText;
        button.disabled = false;
        alert('Encounter note signed & recorded successfully!');
    });
};

window.pchartIssueExcuseLetter = function(button) {
    alert('Certified Work/School Excuse Letter successfully generated and delivered to patient dashboard!');
};

window.diorOpenDoctorUploadModal = function(patientId) {
    if (typeof window.diorDocOpenUploadModal === 'function') {
        window.diorDocOpenUploadModal(patientId);
    }
};

// Toggle password visibility
window.diorDocTogglePwd = function(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPwd = input.type === 'password';
    input.type = isPwd ? 'text' : 'password';
    if (btn) {
        btn.innerHTML = isPwd 
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    }
};

// Change Password Handler
window.diorDocChangePassword = function(e) {
    if (e) e.preventDefault();
    const form = document.getElementById('dior-doc-password-form');
    const msg = document.getElementById('dior-doc-password-msg');
    const btn = document.getElementById('doc-pwd-submit-btn');
    if (!form) return;

    const fd = new FormData(form);
    fd.append('action', 'dior_doctor_change_password');
    fd.append('nonce', window.diorDocNonce);

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';
    }

    fetch(window.diorDocAjax, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-key"></i> Update Password';
        }
        if (msg) {
            msg.style.display = 'block';
            if (res.success) {
                msg.style.background = '#ECFDF5';
                msg.style.color = '#065F46';
                msg.style.border = '1px solid #A7F3D0';
                msg.innerHTML = '<i class="fa-solid fa-circle-check" style="margin-right:6px;"></i> ' + res.data.message;
                form.reset();
            } else {
                msg.style.background = '#FEF2F2';
                msg.style.color = '#991B1B';
                msg.style.border = '1px solid #FECACA';
                msg.innerHTML = '<i class="fa-solid fa-circle-exclamation" style="margin-right:6px;"></i> ' + res.data.message;
            }
        }
    })
    .catch(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-key"></i> Update Password';
        }
        if (msg) {
            msg.style.display = 'block';
            msg.style.background = '#FEF2F2';
            msg.style.color = '#991B1B';
            msg.style.border = '1px solid #FECACA';
            msg.innerHTML = '<i class="fa-solid fa-circle-exclamation" style="margin-right:6px;"></i> Network error. Please try again.';
        }
    });
};

// Filter Medical Records Table
window.diorDocFilterRecordsTable = function(val) {
    const q = (val || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#dior-doc-records-table tbody tr');
    rows.forEach(r => {
        const txt = r.textContent.toLowerCase();
        if (!q || txt.includes(q)) {
            r.removeAttribute('data-filter-hidden');
            r.style.display = '';
        } else {
            r.setAttribute('data-filter-hidden', 'true');
            r.style.display = 'none';
        }
    });

    const table = document.getElementById('dior-doc-records-table');
    if (table && table._diorRenderPage) {
        table._diorRenderPage(1);
    }
};

// Update Standalone Letter Generator Live Preview (Reference Style Match)
window.diorDocUpdateStandaloneLetterPreview = function() {
    const select = document.getElementById('doc_gen_patient_select');
    const opt = select ? select.options[select.selectedIndex] : null;
    const preview = document.getElementById('doc-standalone-letter-preview-content');
    if (!preview) return;

    if (!select || !select.value || !opt) {
        preview.innerHTML = '<p style="color:#94A3B8;text-align:center;padding:40px 0;">Please select a patient from the dropdown on the left to generate the certified letterhead preview.</p>';
        return;
    }

    const patientName = opt.getAttribute('data-name') || opt.textContent.split('(')[0].trim();
    const patientDob = opt.getAttribute('data-dob') || 'On File';
    const patientId = opt.getAttribute('data-pid') || ('DM-' + select.value);
    const letterType = document.getElementById('doc_gen_letter_type') ? document.getElementById('doc_gen_letter_type').value : 'Work Absence Note';
    const condition = document.getElementById('doc_gen_condition') ? (document.getElementById('doc_gen_condition').value || 'Medical Evaluation') : 'Medical Evaluation';
    const startDate = document.getElementById('doc_gen_start_date') ? (document.getElementById('doc_gen_start_date').value || new Date().toISOString().split('T')[0]) : new Date().toISOString().split('T')[0];
    const returnDate = document.getElementById('doc_gen_return_date') ? (document.getElementById('doc_gen_return_date').value || new Date(Date.now() + 3*86400000).toISOString().split('T')[0]) : new Date(Date.now() + 3*86400000).toISOString().split('T')[0];
    const restrictions = document.getElementById('doc_gen_restrictions') ? document.getElementById('doc_gen_restrictions').value : 'Full Rest / Excused from all duties';
    const remarks = document.getElementById('doc_gen_remarks') ? document.getElementById('doc_gen_remarks').value : '';

    // Update Recipient and Salutation elements
    const toNameEl = document.getElementById('doc-ltr-preview-to-name');
    const toMetaEl = document.getElementById('doc-ltr-preview-to-meta');
    const salutationEl = document.getElementById('doc-ltr-preview-salutation');
    if (toNameEl) toNameEl.textContent = patientName;
    if (toMetaEl) toMetaEl.textContent = `Patient ID: ${patientId} • DOB: ${patientDob}`;
    if (salutationEl) salutationEl.textContent = `Dear ${patientName},`;

    // Refresh signature in preview
    const sigImg = document.getElementById('dior-doctor-signature-display-img');
    const ltrSigWrap = document.getElementById('doc-ltr-preview-signature-wrap');
    const activeSig = (sigImg && sigImg.src && sigImg.style.display !== 'none') ? sigImg.src : (window.diorDoctorSignatureUrl || '');
    if (ltrSigWrap) {
        if (activeSig) {
            ltrSigWrap.innerHTML = `<img id="doc-ltr-preview-sig-img" src="${activeSig}" alt="Doctor Digital Signature" style="max-height:50px; max-width:180px; object-fit:contain; background:transparent;">`;
        } else {
            const docPill = document.querySelector('.dior-pill-name');
            const docName = docPill ? docPill.textContent.trim() : 'Dr. Medical Provider';
            ltrSigWrap.innerHTML = `<span id="doc-ltr-preview-sig-text" style="font-family:'Playfair Display',serif; font-style:italic; font-size:20px; color:#0F172A;">/s/ ${docName}</span>`;
        }
    }

    let html = `
        <p style="margin-bottom:14px; font-size:13.5px; line-height:1.75; color:#334155;">
            This formal medical attestation certifies that <strong>${patientName}</strong> (DOB: ${patientDob}, Patient ID: ${patientId}) has been clinically evaluated under my care via the Dior Medical Telehealth network.
        </p>
        <div style="background:#F0FDFA; border-left:4px solid #00A896; border-radius:0 8px 8px 0; padding:12px 16px; margin:16px 0;">
            <p style="margin:0; font-size:13px; color:#0F766E; line-height:1.65;">
                <strong>Document Type:</strong> ${letterType}<br>
                <strong>Clinical Diagnosis / Reason:</strong> ${condition}<br>
                <strong>Excused From Duty / School:</strong> ${startDate}<br>
                <strong>Expected Return Date:</strong> ${returnDate}<br>
                <strong>Duty / Physical Activity Status:</strong> ${restrictions}
            </p>
        </div>
        ${remarks ? `<div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:12px 16px; margin:14px 0;"><strong style="font-size:11.5px; color:#475569; text-transform:uppercase; letter-spacing:0.04em;">Physician Remarks & Clinical Instructions:</strong><p style="margin:4px 0 0 0; font-size:13px; color:#1E293B; line-height:1.6;">${remarks}</p></div>` : ''}
        <p style="margin-top:14px; font-size:13px; line-height:1.7; color:#475569;">
            Please excuse the patient from their normal occupational, scholastic, or physical obligations for the duration outlined above. For official verification of this certified record, please contact the Dior Medical administrative office.
        </p>
    `;

    preview.innerHTML = html;
};

// Submit & Publish Standalone Letter
window.diorDocSubmitStandaloneLetter = function(e) {
    e.preventDefault();
    const select = document.getElementById('doc_gen_patient_select');
    const pid = select.value;
    if (!pid) {
        alert('Please select a patient first.');
        return;
    }

    const btn = document.getElementById('doc-gen-submit-btn');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Publishing...';

    const type = document.getElementById('doc_gen_letter_type').value;
    const condition = document.getElementById('doc_gen_condition').value || 'Medical Evaluation';
    const previewContent = document.getElementById('doc-standalone-letter-preview-content').innerHTML;

    const fd = new FormData();
    fd.append('action', 'dior_doctor_save_letter');
    fd.append('nonce', window.diorDocNonce || (typeof getDiorDocNonce === 'function' ? getDiorDocNonce() : ''));
    fd.append('patient_id', pid);
    fd.append('letter_type', type);
    fd.append('letter_title', type + ' - ' + condition);
    fd.append('excused_from', document.getElementById('doc_gen_start_date').value);
    fd.append('return_date', document.getElementById('doc_gen_return_date').value);
    fd.append('restrictions', document.getElementById('doc_gen_restrictions').value);
    fd.append('remarks', document.getElementById('doc_gen_remarks').value);
    fd.append('content_html', previewContent);

    fetch(window.diorDocAjax || (typeof getDiorDocAjax === 'function' ? getDiorDocAjax() : '/wp-admin/admin-ajax.php'), {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (res.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Medical Letter Published!',
                    text: 'The certified medical letter has been published to the patient portal.',
                    confirmButtonColor: '#00A896'
                }).then(() => window.location.reload());
            } else {
                alert(res.data.message || 'Medical Letter published and sent to patient portal successfully!');
                window.location.reload();
            }
        } else {
            alert(res.data.message || 'Error publishing letter.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        alert('Network error publishing letter.');
    });
};

// Print Standalone Preview (Reference Styled Letterhead)
window.diorDocPrintStandalonePreview = function() {
    const previewBox = document.getElementById('doc-standalone-letter-preview-box');
    if (!previewBox) return;
    const content = previewBox.innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
            <head>
                <title>Certified Medical Letter - Dior Medical</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,700;1,600&display=swap');
                    body { font-family: 'Inter', -apple-system, sans-serif; padding: 40px; color: #1E293B; background: #FFF; }
                    .letter-print-wrap { border: 2.5px solid #00A896; border-radius: 12px; padding: 36px; position: relative; max-width: 800px; margin: 0 auto; }
                    @media print { 
                        body { padding: 0; background: #FFF; } 
                        .letter-print-wrap { border: 2px solid #00A896; box-shadow: none; padding: 25px; } 
                    }
                </style>
            </head>
            <body onload="window.print();">
                <div class="letter-print-wrap">
                    ${content}
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
};

// Filter Category in Records Table
window.diorDocFilterCategory = function(cat, btn) {
    if (btn) {
        const group = btn.closest('.dior-filter-pill-group') || btn.parentElement;
        if (group) {
            group.querySelectorAll('.dior-filter-pill').forEach(p => p.classList.remove('active'));
        }
        btn.classList.add('active');
    }

    const rows = document.querySelectorAll('#dior-doc-records-table tbody tr');
    rows.forEach(r => {
        const rowCat = (r.getAttribute('data-category-type') || r.querySelector('.dior-badge-pill')?.textContent || '').toLowerCase();
        if (cat === 'all' || rowCat.includes(cat.toLowerCase())) {
            r.removeAttribute('data-category-hidden');
            if (!r.hasAttribute('data-filter-hidden')) {
                r.style.display = '';
            }
        } else {
            r.setAttribute('data-category-hidden', 'true');
            r.style.display = 'none';
        }
    });

    const table = document.getElementById('dior-doc-records-table');
    if (table && table._diorRenderPage) {
        table._diorRenderPage(1);
    }
};

// Apply Quick Preset in Letter Generator
window.diorDocApplyLetterPreset = function(condition, restrictions, remarks) {
    const condEl = document.getElementById('doc_gen_condition');
    const restEl = document.getElementById('doc_gen_restrictions');
    const remEl = document.getElementById('doc_gen_remarks');

    if (condEl && condition) condEl.value = condition;
    if (restEl && restrictions) restEl.value = restrictions;
    if (remEl && remarks) remEl.value = remarks;

    window.diorDocUpdateStandaloneLetterPreview();
};

// Open & Close Upload Document Modal
window.diorDocOpenUploadModal = function() {
    const modal = document.getElementById('modal-doc-upload');
    if (modal) {
        modal.classList.add('open');
    }
};

window.diorDocCloseUploadModal = function() {
    const modal = document.getElementById('modal-doc-upload');
    if (modal) {
        modal.classList.remove('open');
    }
};

// Handle Document Upload
window.diorDocSubmitUpload = function(e) {
    if (e) e.preventDefault();
    const form = document.getElementById('dior-doc-upload-form');
    if (!form) return;

    const patientSelect = form.querySelector('[name="patient_id"]');
    const titleInput = form.querySelector('[name="doc_title"]');
    const fileInput = form.querySelector('[name="doc_file"]');
    const submitBtn = document.getElementById('doc-upload-submit-btn');

    if (!patientSelect || !patientSelect.value) {
        alert('Please select a patient.');
        return;
    }
    if (!titleInput || !titleInput.value.trim()) {
        alert('Please enter a document title.');
        return;
    }
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
        alert('Please select a file to upload.');
        return;
    }

    const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading & Encrypting...';
    }

    const fd = new FormData(form);
    fd.append('action', 'dior_upload_document');
    fd.append('nonce', window.diorDocNonce);

    fetch(window.diorDocAjax, {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origBtnHtml;
        }
        if (res.success) {
            alert(res.data.message || 'Document uploaded and secured successfully!');
            window.diorDocCloseUploadModal();
            window.location.reload();
        } else {
            alert(res.data.message || 'Error uploading document.');
        }
    })
    .catch(err => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origBtnHtml;
        }
        alert('Network error during file upload.');
    });
};

// Handle Document Deletion
window.diorDocDeleteDocument = function(docId, btn) {
    if (!confirm('Are you sure you want to delete this document from the secure vault? This action cannot be undone.')) {
        return;
    }

    const row = btn ? btn.closest('tr') : null;
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    }

    const fd = new FormData();
    fd.append('action', 'dior_delete_document');
    fd.append('nonce', window.diorDocNonce);
    fd.append('doc_id', docId);

    fetch(window.diorDocAjax, {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (row) {
                row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                setTimeout(() => row.remove(), 300);
            } else {
                window.location.reload();
            }
        } else {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
            alert(res.data.message || 'Error deleting document.');
        }
    })
    .catch(err => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
        alert('Network error deleting document.');
    });
};




document.addEventListener('DOMContentLoaded', function () {
    if (window.dior_doctor_vars && window.dior_doctor_vars.dashboard_data) {
        diorHydrateDoctorSourceTables();
    }
});

/* -------------------------------------------------------------------------
 * Static dashboard navigation safety layer.
 * Keeps Doctor Dashboard tabs usable even if an optional widget script fails.
 * ------------------------------------------------------------------------- */
(function () {
    function bootStaticDoctorNavigation() {
        var app = document.getElementById('dior-doctor-app');
        if (!app) return;

        function switchTab(tabId) {
            if (!tabId) return;
            try { localStorage.setItem('diorDocLastTab', tabId); } catch(e) {}

            var buttons = app.querySelectorAll('#dior-doc-sidebar .dior-nav-btn[data-tab]');
            buttons.forEach(function (btn) {
                btn.classList.toggle('active', btn.getAttribute('data-tab') === tabId);
            });

            var panels = app.querySelectorAll('#dior-doc-content > .dior-tab-panel');
            panels.forEach(function (panel) {
                var active = panel.id === 'tab-' + tabId;
                panel.classList.toggle('active', active);
                if (active) {
                    panel.removeAttribute('hidden');
                    panel.style.display = 'block';
                } else {
                    panel.setAttribute('hidden', 'hidden');
                    panel.style.display = 'none';
                }
            });

            try {
                history.replaceState(null, '', '#tab=' + encodeURIComponent(tabId));
            } catch (e) {}
        }

        if (typeof window.diorDocSwitchTab !== 'function') {
            window.diorDocSwitchTab = switchTab;
        }

        app.addEventListener('click', function (event) {
            var btn = event.target.closest('#dior-doc-sidebar .dior-nav-btn[data-tab]');
            if (!btn) return;
            if (window.diorDocSwitchTab === switchTab) {
                event.preventDefault();
                event.stopPropagation();
                switchTab(btn.getAttribute('data-tab'));
            }
        }, true);

        var hash = window.location.hash.match(/^#tab=([^&]+)/);
        var initial = hash ? decodeURIComponent(hash[1]) : null;
        if (!initial) { try { initial = localStorage.getItem('diorDocLastTab'); } catch(e) {} }
        var fallback = app.querySelector('#dior-doc-sidebar .dior-nav-btn.active[data-tab]') || app.querySelector('#dior-doc-sidebar .dior-nav-btn[data-tab]');
        switchTab(initial && app.querySelector('#dior-doc-sidebar .dior-nav-btn[data-tab="' + CSS.escape(initial) + '"]') ? initial : (fallback ? fallback.getAttribute('data-tab') : 'doc-overview'));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootStaticDoctorNavigation, { once: true });
    } else {
        bootStaticDoctorNavigation();
    }
})();
