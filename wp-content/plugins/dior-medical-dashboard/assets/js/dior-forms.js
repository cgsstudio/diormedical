/**
 * Dior Medical - Luxury Telehealth Frontend Forms & Modals JS
 * Controls Multi-Step Booking Wizard, Global Button Click Interceptions, and Patient Portal Lookup
 */

(function($) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const bookingModal = document.getElementById('dior-booking-modal-overlay');
        const portalModal = document.getElementById('dior-portal-modal-overlay');

        // Helper: Open Modal
        window.diorOpenBookingModal = function(preselectedService) {
            if (!bookingModal) return;
            
            // If a specific condition was clicked, pre-select it
            if (preselectedService) {
                const conditionSelect = document.getElementById('dior-select-condition');
                if (conditionSelect) {
                    let found = false;
                    for (let i = 0; i < conditionSelect.options.length; i++) {
                        if (conditionSelect.options[i].text.toLowerCase().includes(preselectedService.toLowerCase()) ||
                            preselectedService.toLowerCase().includes(conditionSelect.options[i].text.toLowerCase())) {
                            conditionSelect.selectedIndex = i;
                            found = true;
                            break;
                        }
                    }
                    if (!found) {
                        // Create and append if not matching exactly
                        const opt = new Option(preselectedService, preselectedService, true, true);
                        conditionSelect.add(opt);
                    }
                }
            }

            bookingModal.classList.add('open');
            document.body.style.overflow = 'hidden';
        };

        window.diorCloseBookingModal = function() {
            if (bookingModal) {
                bookingModal.classList.remove('open');
                document.body.style.overflow = '';
            }
        };

        window.diorOpenPortalModal = function() {
            if (!portalModal) return;
            portalModal.classList.add('open');
            document.body.style.overflow = 'hidden';
        };

        window.diorClosePortalModal = function() {
            if (portalModal) {
                portalModal.classList.remove('open');
                document.body.style.overflow = '';
            }
        };

        // Close button handlers
        const bookingCloseBtn = document.getElementById('dior-booking-modal-close');
        if (bookingCloseBtn) bookingCloseBtn.addEventListener('click', diorCloseBookingModal);

        const finishBtn = document.getElementById('dior-finish-btn');
        if (finishBtn) finishBtn.addEventListener('click', function() {
            diorCloseBookingModal();
            location.reload();
        });

        const portalCloseBtn = document.getElementById('dior-portal-modal-close');
        if (portalCloseBtn) portalCloseBtn.addEventListener('click', diorClosePortalModal);

        // Backdrop click to close
        if (bookingModal) {
            bookingModal.addEventListener('click', function(e) {
                if (e.target === bookingModal) diorCloseBookingModal();
            });
        }
        if (portalModal) {
            portalModal.addEventListener('click', function(e) {
                if (e.target === portalModal) diorClosePortalModal();
            });
        }

        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                diorCloseBookingModal();
                diorClosePortalModal();
            }
        });

        // -------------------------------------------------------------
        // AUTOMATICALLY HOOK ALL BUTTONS ACROSS THE WEBSITE
        // -------------------------------------------------------------
        const conditionsList = [
            'Cold & Flu', 'COVID-19', 'Bronchitis', 'Sinus', 'URI', 'Cough',
            'Sore Throat', 'Tonsillitis', 'Rashes', 'Hives', 'Cold Sores',
            'Scabies', 'Insect Bites', 'Allergies', 'Back & Joint Pain',
            'Knee Pain', 'Neck Pain', 'Tennis Elbow', 'Sprains & Strains',
            'GERD', 'Constipation', 'Diarrhea', 'UTI', 'Yeast Infection',
            'Bacterial Vaginosis', 'Birth Control', 'Erectile Dysfunction',
            'Mastitis', 'Ear Pain', 'Pink Eye', 'Dizziness', 'Gout',
            'Thrush', 'Sleep Issues', 'Medication Refills', 'Lab Interpretation',
            'Asthma'
        ];

        document.querySelectorAll('a, button, .elementor-button').forEach(el => {
            const text = (el.innerText || el.textContent || '').trim();
            const href = el.getAttribute('href') || '';

            // 1. "Book an appointment" buttons
            if (/book an appointment|book appointment|schedule consultation|book now/i.test(text) || href === '#book-appointment') {
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.diorOpenBookingModal();
                });
            }

            // 2. "Access Patient Portal" buttons
            else if (/access patient portal|patient portal|patient login/i.test(text) || href === '#patient-portal') {
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.diorOpenPortalModal();
                });
            }

            // 3. Condition cards / buttons
            else {
                for (let cond of conditionsList) {
                    if (text.toLowerCase() === cond.toLowerCase()) {
                        el.addEventListener('click', function(e) {
                            e.preventDefault();
                            window.diorOpenBookingModal(cond);
                        });
                        break;
                    }
                }
            }
        });

        // Consultation type card selection
        document.querySelectorAll('.dior-type-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.dior-type-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Provider card selection
        document.querySelectorAll('.dior-provider-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.dior-provider-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
            });
        });

        // -------------------------------------------------------------
        // MULTI-STEP WIZARD NAVIGATION
        // -------------------------------------------------------------
        const step1 = document.getElementById('dior-step-1');
        const step2 = document.getElementById('dior-step-2');
        const step3 = document.getElementById('dior-step-3');
        const step4 = document.getElementById('dior-step-4');

        function setStep(stepNum) {
            [step1, step2, step3, step4].forEach((s, idx) => {
                if (s) s.style.display = (idx + 1 === stepNum) ? 'block' : 'none';
            });

            document.querySelectorAll('.dior-stepper .dior-step').forEach(st => {
                const num = parseInt(st.getAttribute('data-step'), 10);
                st.classList.remove('active', 'completed');
                if (num === stepNum) {
                    st.classList.add('active');
                } else if (num < stepNum) {
                    st.classList.add('completed');
                }
            });
        }

        // Next to Step 2
        const nextTo2 = document.getElementById('dior-next-to-step-2');
        if (nextTo2) {
            nextTo2.addEventListener('click', function() {
                setStep(2);
            });
        }

        // Back to Step 1
        const backTo1 = document.getElementById('dior-back-to-step-1');
        if (backTo1) {
            backTo1.addEventListener('click', function() {
                setStep(1);
            });
        }

        // Next to Step 3
        const nextTo3 = document.getElementById('dior-next-to-step-3');
        if (nextTo3) {
            nextTo3.addEventListener('click', function() {
                const dateVal = document.getElementById('dior-appt-date').value;
                if (!dateVal) {
                    alert('Please select your preferred appointment date.');
                    return;
                }
                setStep(3);
            });
        }

        // Back to Step 2
        const backTo2 = document.getElementById('dior-back-to-step-2');
        if (backTo2) {
            backTo2.addEventListener('click', function() {
                setStep(2);
            });
        }

        // -------------------------------------------------------------
        // STEP 3: SUBMIT APPOINTMENT BOOKING VIA AJAX
        // -------------------------------------------------------------
        const bookingForm = document.getElementById('dior-patient-booking-form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const submitBtn = document.getElementById('dior-submit-booking');
                const origText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Scheduling Appointment...';

                const formData = new FormData(this);
                formData.append('action', 'dior_patient_book');
                formData.append('nonce', dior_forms_vars.nonce);

                fetch(dior_forms_vars.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;

                    if (res.success && res.data && res.data.appointment) {
                        const appt = res.data.appointment;
                        
                        // Populate Receipt in Step 4
                        const elId = document.getElementById('res-appt-id');
                        const elProv = document.getElementById('res-provider-name');
                        const elServ = document.getElementById('res-service');
                        const elDt = document.getElementById('res-datetime');
                        const elRoom = document.getElementById('res-room-link');
                        const elIntake = document.getElementById('dior-proceed-intake-btn');

                        if (elId) elId.textContent = appt.id;
                        if (elProv) elProv.textContent = appt.provider_name;
                        if (elServ) elServ.textContent = appt.service + ' (' + appt.type + ')';
                        if (elDt) elDt.textContent = appt.date + ' at ' + appt.time;
                        if (elRoom) {
                            elRoom.href = appt.room_url;
                            elRoom.textContent = 'Enter ' + appt.provider_name.split(',')[0] + ' Room';
                        }
                        if (elIntake && res.data.intake_url) {
                            elIntake.href = res.data.intake_url;
                        }

                        // Switch to Step 4 receipt and redirect to Patient Dashboard
                        setStep(4);
                        if (res.data && res.data.redirect_url) {
                            setTimeout(() => {
                                window.location.href = res.data.redirect_url;
                            }, 2000);
                        }
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Error scheduling appointment.');
                    }
                })
                .catch(err => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                    alert('Network error. Please try again.');
                });
            });
        }

        // -------------------------------------------------------------
        // PATIENT PORTAL LOOKUP AJAX
        // -------------------------------------------------------------
        function handlePortalLookup(formId, resultsId) {
            const form = document.getElementById(formId);
            const results = document.getElementById(resultsId);
            if (!form || !results) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const queryInput = form.querySelector('input[name="search_query"]');
                const query = (queryInput.value || '').trim();

                if (!query) {
                    alert('Please enter your Appointment ID or Email.');
                    return;
                }

                results.style.display = 'block';
                results.innerHTML = '<div style="text-align:center; padding:20px; color:#64748B;">Searching clinical records...</div>';

                const formData = new FormData();
                formData.append('action', 'dior_portal_lookup');
                formData.append('nonce', dior_forms_vars.nonce);
                formData.append('search_query', query);

                fetch(dior_forms_vars.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.data && res.data.appointment) {
                        const appt = res.data.appointment;
                        const rxs = res.data.prescriptions || [];
                        const statusClass = (appt.status || '').replace(/\s+/g, '-');

                        let rxHtml = '';
                        if (rxs.length > 0) {
                            rxHtml = '<div style="margin-top:16px; padding-top:12px; border-top:1px solid #EEF2F6;">'
                                   + '<h5 style="margin:0 0 8px; color:#0B1030; font-size:13px; font-weight:700;">Prescriptions on Record:</h5>';
                            rxs.forEach(rx => {
                                rxHtml += '<div style="background:#F8FAFC; padding:8px 12px; border-radius:8px; margin-bottom:6px; font-size:12.5px;">'
                                        + '<strong>' + rx.medication + ' (' + rx.dosage + ')</strong> - ' + rx.status + ' (' + rx.pharmacy + ')'
                                        + '</div>';
                            });
                            rxHtml += '</div>';
                        }

                        results.innerHTML = `
                            <div class="dior-portal-record-card">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                    <span style="font-size:13px; color:#64748B;">Appointment ID: <strong>${appt.id}</strong></span>
                                    <span class="portal-badge-status ${statusClass}">${appt.status}</span>
                                </div>
                                <h3 style="font-size:18px; font-weight:700; color:#0B1030; margin:0 0 6px;">${appt.service}</h3>
                                <p style="font-size:13.5px; color:#475569; margin:0 0 14px;">Attending: <strong>${appt.provider_name}</strong> | ${appt.date} at ${appt.time}</p>
                                
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    ${appt.room_url ? `<a href="${appt.room_url}" target="_blank" rel="noopener" class="dior-btn-primary" style="padding:8px 20px; font-size:13px;">Join Telehealth Room &rarr;</a>` : ''}
                                    <a href="${dior_forms_vars.intake_url}?appt_id=${appt.id}&patient_name=${encodeURIComponent(appt.patient_name)}&condition=${encodeURIComponent(appt.service)}" class="dior-btn-secondary" style="padding:8px 18px; font-size:13px;">HIPAA Intake Form</a>
                                </div>
                                ${rxHtml}
                            </div>
                        `;
                    } else {
                        results.innerHTML = `<div style="background:#FEF2F2; color:#991B1B; padding:14px 18px; border-radius:10px; font-size:13.5px; text-align:center;">
                            ${res.data && res.data.message ? res.data.message : 'No matching records found.'}
                        </div>`;
                    }
                })
                .catch(() => {
                    results.innerHTML = '<div style="color:#B3382C; text-align:center; padding:14px;">Error communicating with server.</div>';
                });
            });
        }

        handlePortalLookup('dior-patient-portal-lookup-form', 'dior-portal-results');
        handlePortalLookup('dior-patient-portal-page-form', 'dior-page-portal-results');

        // -------------------------------------------------------------
        // HIPAA INTAKE FORM AJAX SUBMISSION
        // -------------------------------------------------------------
        const hipaaForm = document.getElementById('dior-hipaa-intake-form');
        if (hipaaForm) {
            hipaaForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const submitBtn = document.getElementById('dior-submit-hipaa-btn');
                const resultMsg = document.getElementById('dior-intake-result-msg');
                const origText = submitBtn.innerHTML;

                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Submitting Encrypted Intake...';

                const formData = new FormData(this);
                formData.append('action', 'dior_submit_intake');
                formData.append('nonce', dior_forms_vars.nonce);

                fetch(dior_forms_vars.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;

                    if (res.success) {
                        resultMsg.style.display = 'block';
                        resultMsg.innerHTML = `
                            <div style="background:#EAF6F0; border:1px solid #1E7A5F; color:#1E7A5F; padding:20px; border-radius:12px; text-align:center;">
                                <h4 style="margin:0 0 6px; font-size:18px;">&#10004; Clinical Intake Submitted</h4>
                                <p style="margin:0; font-size:14px;">${res.data.message}</p>
                                <p style="margin-top:10px; font-size:12.5px; color:#475569;">Intake Reference: <strong>${res.data.intake_id}</strong></p>
                            </div>
                        `;
                        hipaaForm.reset();
                    } else {
                        resultMsg.style.display = 'block';
                        resultMsg.innerHTML = `<div style="background:#FEF2F2; color:#991B1B; padding:14px; border-radius:10px;">
                            ${res.data && res.data.message ? res.data.message : 'Error submitting intake.'}
                        </div>`;
                    }
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                    resultMsg.style.display = 'block';
                    resultMsg.innerHTML = '<div style="color:#B3382C;">Network error. Please try again.</div>';
                });
            });
        }

        // -------------------------------------------------------------
        // PATIENT DASHBOARD TAB SWITCHER
        // -------------------------------------------------------------
        document.querySelectorAll('.dior-pt-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-pt-tab');
                
                document.querySelectorAll('.dior-pt-tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                document.querySelectorAll('.dior-pt-tab-content').forEach(content => {
                    content.style.display = 'none';
                    content.classList.remove('active');
                });

                const targetContent = document.getElementById('pt-tab-' + tabId);
                if (targetContent) {
                    targetContent.style.display = 'block';
                    targetContent.classList.add('active');
                }
            });
        });

        // -------------------------------------------------------------
        // LUXURY PATIENT AUTH & REGISTRATION PORTAL ([dior_auth])
        // -------------------------------------------------------------
        const authPortal = document.getElementById('dior-auth-portal');
        if (authPortal) {
            const alertBox = document.getElementById('dior-auth-alert-box');
            const tabButtons = authPortal.querySelectorAll('.dior-auth-tab-btn');
            const tabPanes = authPortal.querySelectorAll('.dior-auth-tab-pane');
            const tabsNav = authPortal.querySelector('.dior-auth-tabs-nav');
            const forgotPane = document.getElementById('tab-forgot');
            const loginPane = document.getElementById('tab-login');
            const openForgotLink = document.getElementById('dior-open-forgot-link');
            const backToLoginBtn = document.getElementById('dior-back-to-login');

            // Alert Helper
            function showAuthAlert(type, message) {
                if (!alertBox) return;
                alertBox.className = 'dior-auth-alert ' + type;
                const iconSvg = (type === 'success') 
                    ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                    : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
                alertBox.innerHTML = iconSvg + '<span>' + message + '</span>';
                alertBox.style.display = 'flex';
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            function hideAuthAlert() {
                if (alertBox) alertBox.style.display = 'none';
            }

            // Tab Switching
            tabButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    hideAuthAlert();

                    tabButtons.forEach(b => {
                        b.classList.remove('active');
                        b.setAttribute('aria-selected', 'false');
                    });
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');

                    tabPanes.forEach(pane => {
                        pane.classList.remove('active');
                        pane.style.display = 'none';
                    });

                    const targetPane = document.getElementById(targetId);
                    if (targetPane) {
                        targetPane.classList.add('active');
                        targetPane.style.display = 'block';
                    }
                });
            });

            // Forgot Password Slide Navigation
            if (openForgotLink && forgotPane) {
                openForgotLink.addEventListener('click', function() {
                    hideAuthAlert();
                    tabPanes.forEach(pane => {
                        pane.classList.remove('active');
                        pane.style.display = 'none';
                    });
                    if (tabsNav) tabsNav.style.display = 'none';
                    forgotPane.classList.add('active');
                    forgotPane.style.display = 'block';
                });
            }

            if (backToLoginBtn && forgotPane && loginPane) {
                backToLoginBtn.addEventListener('click', function() {
                    hideAuthAlert();
                    forgotPane.classList.remove('active');
                    forgotPane.style.display = 'none';
                    if (tabsNav) tabsNav.style.display = 'flex';
                    
                    tabButtons.forEach(b => {
                        if (b.getAttribute('data-target') === 'tab-login') {
                            b.classList.add('active');
                            b.setAttribute('aria-selected', 'true');
                        } else {
                            b.classList.remove('active');
                            b.setAttribute('aria-selected', 'false');
                        }
                    });

                    loginPane.classList.add('active');
                    loginPane.style.display = 'block';
                });
            }

            // Password Show/Hide Toggle
            authPortal.querySelectorAll('.dior-toggle-pwd').forEach(toggleBtn => {
                toggleBtn.addEventListener('click', function() {
                    const pwdInput = this.parentElement.querySelector('input');
                    if (!pwdInput) return;
                    const eyeOpen = this.querySelector('.eye-open');
                    const eyeClosed = this.querySelector('.eye-closed');

                    if (pwdInput.type === 'password') {
                        pwdInput.type = 'text';
                        if (eyeOpen) eyeOpen.style.display = 'none';
                        if (eyeClosed) eyeClosed.style.display = 'block';
                    } else {
                        pwdInput.type = 'password';
                        if (eyeOpen) eyeOpen.style.display = 'block';
                        if (eyeClosed) eyeClosed.style.display = 'none';
                    }
                });
            });

            // 1. AJAX LOGIN SUBMISSION
            const loginForm = document.getElementById('dior-login-form');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    hideAuthAlert();

                    const submitBtn = loginForm.querySelector('.dior-auth-submit-btn');
                    const btnTxt = submitBtn ? submitBtn.querySelector('.btn-txt') : null;
                    const btnSpinner = submitBtn ? submitBtn.querySelector('.btn-spinner') : null;

                    if (submitBtn) submitBtn.disabled = true;
                    if (btnTxt) btnTxt.style.display = 'none';
                    if (btnSpinner) btnSpinner.style.display = 'inline-block';

                    const formData = new FormData(loginForm);
                    formData.append('action', 'dior_patient_login');
                    formData.append('nonce', dior_forms_vars.nonce);

                    fetch(dior_forms_vars.ajax_url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            showAuthAlert('success', res.data.message || 'Login successful!');
                            setTimeout(() => {
                                window.location.href = res.data.redirect_to || window.location.href;
                            }, 1000);
                        } else {
                            if (submitBtn) submitBtn.disabled = false;
                            if (btnTxt) btnTxt.style.display = 'inline-block';
                            if (btnSpinner) btnSpinner.style.display = 'none';
                            showAuthAlert('error', res.data && res.data.message ? res.data.message : 'Login failed. Please check your credentials.');
                        }
                    })
                    .catch(() => {
                        if (submitBtn) submitBtn.disabled = false;
                        if (btnTxt) btnTxt.style.display = 'inline-block';
                        if (btnSpinner) btnSpinner.style.display = 'none';
                        showAuthAlert('error', 'Network error while attempting to sign in. Please try again.');
                    });
                });
            }

            // 2. AJAX REGISTRATION SUBMISSION
            const regForm = document.getElementById('dior-register-form');
            if (regForm) {
                regForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    hideAuthAlert();

                    const submitBtn = regForm.querySelector('.dior-auth-submit-btn');
                    const btnTxt = submitBtn ? submitBtn.querySelector('.btn-txt') : null;
                    const btnSpinner = submitBtn ? submitBtn.querySelector('.btn-spinner') : null;

                    if (submitBtn) submitBtn.disabled = true;
                    if (btnTxt) btnTxt.style.display = 'none';
                    if (btnSpinner) btnSpinner.style.display = 'inline-block';

                    const formData = new FormData(regForm);
                    formData.append('action', 'dior_patient_register');
                    formData.append('nonce', dior_forms_vars.nonce);

                    fetch(dior_forms_vars.ajax_url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            showAuthAlert('success', res.data.message || 'Account created successfully!');
                            setTimeout(() => {
                                window.location.href = res.data.redirect_to || window.location.href;
                            }, 1200);
                        } else {
                            if (submitBtn) submitBtn.disabled = false;
                            if (btnTxt) btnTxt.style.display = 'inline-block';
                            if (btnSpinner) btnSpinner.style.display = 'none';
                            showAuthAlert('error', res.data && res.data.message ? res.data.message : 'Registration failed. Please check the entered data.');
                        }
                    })
                    .catch(() => {
                        if (submitBtn) submitBtn.disabled = false;
                        if (btnTxt) btnTxt.style.display = 'inline-block';
                        if (btnSpinner) btnSpinner.style.display = 'none';
                        showAuthAlert('error', 'Network error during registration. Please try again.');
                    });
                });
            }

            // 3. AJAX FORGOT PASSWORD SUBMISSION
            const forgotForm = document.getElementById('dior-forgot-form');
            if (forgotForm) {
                forgotForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    hideAuthAlert();

                    const submitBtn = forgotForm.querySelector('.dior-auth-submit-btn');
                    const btnTxt = submitBtn ? submitBtn.querySelector('.btn-txt') : null;
                    const btnSpinner = submitBtn ? submitBtn.querySelector('.btn-spinner') : null;

                    if (submitBtn) submitBtn.disabled = true;
                    if (btnTxt) btnTxt.style.display = 'none';
                    if (btnSpinner) btnSpinner.style.display = 'inline-block';

                    const formData = new FormData(forgotForm);
                    formData.append('action', 'dior_patient_forgot_password');
                    formData.append('nonce', dior_forms_vars.nonce);

                    fetch(dior_forms_vars.ajax_url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (submitBtn) submitBtn.disabled = false;
                        if (btnTxt) btnTxt.style.display = 'inline-block';
                        if (btnSpinner) btnSpinner.style.display = 'none';

                        if (res.success) {
                            showAuthAlert('success', res.data.message || 'Reset link dispatched.');
                            forgotForm.reset();
                        } else {
                            showAuthAlert('error', res.data && res.data.message ? res.data.message : 'Error processing request.');
                        }
                    })
                    .catch(() => {
                        if (submitBtn) submitBtn.disabled = false;
                        if (btnTxt) btnTxt.style.display = 'inline-block';
                        if (btnSpinner) btnSpinner.style.display = 'none';
                        showAuthAlert('error', 'Network error. Please try again.');
                    });
                });
            }
        }
    });

})(jQuery);

