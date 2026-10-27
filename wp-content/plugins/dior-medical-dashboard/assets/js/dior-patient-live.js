/**
 * Dior Medical - stable patient dashboard controller.
 * Keeps main tab navigation and profile persistence independent of legacy widgets.
 */
(function () {
    'use strict';

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
        settings: 'Settings'
    };

    function switchTab(tabId, updateHash) {
        var app = document.getElementById('dior-patient-portal-app');
        if (!app || !tabId) return false;
        var panel = document.getElementById('tab-' + tabId);
        if (!panel || !app.contains(panel)) return false;

        app.querySelectorAll('.dior-nav-btn[data-tab]').forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-tab') === tabId);
        });

        app.querySelectorAll('.dior-tab-panel').forEach(function (item) {
            var active = item === panel;
            item.classList.toggle('active', active);
            item.style.display = active ? 'block' : 'none';
            item.style.visibility = active ? 'visible' : 'hidden';
            item.style.opacity = active ? '1' : '0';
        });

        var title = document.getElementById('dior-current-page-title');
        if (title && titles[tabId]) title.textContent = titles[tabId];

        if (tabId !== 'appointments') {
            var w = document.getElementById('dior-appt-dropdown-wrap');
            if (w) {
                w.classList.remove('open');
                var ch = w.querySelector('.fa-chevron-down');
                if (ch) ch.style.transform = 'rotate(0deg)';
            }
        }

        if (updateHash !== false) {
            var hash = '#tab=' + encodeURIComponent(tabId);
            if (window.history && window.history.replaceState) window.history.replaceState(null, '', hash);
            else window.location.hash = hash;
        }
        return true;
    }

    window._diorLastApptToggleTime = window._diorLastApptToggleTime || 0;

    function toggleApptDropdown(e) {
        if (e) {
            if (typeof e.preventDefault === 'function') e.preventDefault();
            if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        }
        var now = Date.now();
        if (now - (window._diorLastApptToggleTime || 0) < 300) {
            return;
        }
        window._diorLastApptToggleTime = now;

        var w = document.getElementById('dior-appt-dropdown-wrap');
        if (!w) return;
        var isOpen = w.classList.contains('open');
        if (isOpen) {
            w.classList.remove('open');
            var ch = w.querySelector('.fa-chevron-down');
            if (ch) ch.style.transform = 'rotate(0deg)';
        } else {
            w.classList.add('open');
            var ch = w.querySelector('.fa-chevron-down');
            if (ch) ch.style.transform = 'rotate(180deg)';
            switchTab('appointments', true);
            if (typeof window.diorSwitchApptSubTab === 'function') {
                var activeSub = w.querySelector('.dior-nav-dropdown-item.active');
                var sub = activeSub ? activeSub.getAttribute('data-appt-subtab') : 'today';
                window.diorSwitchApptSubTab(sub);
            }
        }
    }
    window.diorToggleApptDropdown = toggleApptDropdown;

    window.diorSwitchTab = switchTab;
    window.diorSafeSwitchPatientTab = switchTab;

    function bindNavigation() {
        var app = document.getElementById('dior-patient-portal-app');
        if (!app || app.__diorLiveBound) return;
        app.__diorLiveBound = true;

        app.addEventListener('click', function (event) {
            var btn = event.target.closest('.dior-nav-btn[data-tab]');
            if (!btn || !app.contains(btn)) return;
            var tab = btn.getAttribute('data-tab');
            if (!tab) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            if (tab === 'appointments') {
                toggleApptDropdown(event);
            } else {
                switchTab(tab, true);
            }
        }, true);

        app.addEventListener('click', function (event) {
            var link = event.target.closest('[data-appt-subtab]');
            if (!link || !app.contains(link)) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            switchTab('appointments', true);
            if (typeof window.diorSwitchApptSubTab === 'function') {
                window.diorSwitchApptSubTab(link.getAttribute('data-appt-subtab'));
            }
        }, true);

        var hash = window.location.hash || '';
        var requestedTab = 'overview';
        var requestedSub = null;
        if (hash.indexOf('#tab=') === 0) {
            var raw = hash.substring(5);
            if (raw.indexOf('&subtab=') !== -1) {
                var p = raw.split('&subtab=');
                requestedTab = decodeURIComponent(p[0]);
                requestedSub = decodeURIComponent(p[1]);
            } else {
                requestedTab = decodeURIComponent(raw);
            }
        }
        if (requestedTab === 'appointments') {
            switchTab('appointments', false);
            var sub = requestedSub || (function(){
                try { return sessionStorage.getItem('dior_active_appt_subtab'); } catch(e){ return null; }
            })() || 'today';
            if (typeof window.diorSwitchApptSubTab === 'function') {
                window.diorSwitchApptSubTab(sub);
            }
        } else {
            if (!switchTab(requestedTab, false)) switchTab('overview', false);
        }
    }

    function bindProfileSave() {
        var form = document.getElementById('dior-profile-settings-form');
        if (!form || form.__diorProfileBound) return;
        form.__diorProfileBound = true;

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var cfg = window.dior_patient_live_vars || window.dior_vars || window.dior_patient_dashboard || {};
            var ajaxUrl = cfg.ajax_url || form.getAttribute('data-ajax-url');
            var nonce = cfg.nonce || cfg.portal_nonce || form.getAttribute('data-nonce');
            if (!ajaxUrl || !nonce) {
                var configError = 'Unable to start the profile update. Refresh the page and try again.';
                Swal.fire({ icon: 'error', title: 'Update Failed', text: configError });
                return;
            }

            var data = new FormData(form);
            data.append('action', 'dior_patient_save_profile');
            data.append('nonce', nonce);

            var button = form.querySelector('button[type="submit"]');
            var original = button ? button.innerHTML : '';
            if (button) { button.disabled = true; button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...'; }

            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
                .then(function (response) {
                    return response.text().then(function (body) {
                        var result;
                        try {
                            result = JSON.parse(body);
                        } catch (parseError) {
                            var message = response.redirected
                                ? 'Your session may have expired. Sign in again, then retry.'
                                : 'The server returned a page instead of profile data (HTTP ' + response.status + '). Refresh and try again.';
                            throw new Error(message);
                        }
                        if (!response.ok && !result.success) {
                            throw new Error((result.data && result.data.message) || 'Unable to save profile.');
                        }
                        return result;
                    });
                })
                .then(function (result) {
                    if (!result.success) throw new Error((result.data && result.data.message) || 'Unable to save profile.');
                    var profile = result.data.profile || {};
                    document.querySelectorAll('.dior-sidebar-user-name').forEach(function (el) { el.textContent = profile.full_name || 'Patient'; });
                    var settingsName = document.querySelector('#tab-settings .dior-settings-profile-name');
                    if (settingsName && profile.full_name) settingsName.textContent = profile.full_name;
                    var settingsEmail = document.querySelector('#tab-settings .dior-settings-profile-email');
                    if (settingsEmail && profile.email) settingsEmail.textContent = profile.email;

                    // Update Overview Card dynamically
                    var overviewName = document.querySelector('#tab-overview .patient-info h2');
                    if (overviewName && profile.full_name) overviewName.textContent = profile.full_name;

                    if (typeof profile.height !== 'undefined') {
                        var hElem = document.getElementById('dior-patient-metric-height');
                        if (hElem) hElem.textContent = profile.height ? (!isNaN(profile.height) ? profile.height + ' cm' : profile.height) : '—';
                        var goalH = document.getElementById('goal_height');
                        if (goalH) goalH.value = profile.height || '';
                    }
                    if (typeof profile.weight !== 'undefined') {
                        var wElem = document.getElementById('dior-patient-metric-weight');
                        if (wElem) wElem.textContent = profile.weight ? (!isNaN(profile.weight) ? profile.weight + ' kg' : profile.weight) : '—';
                        var goalW = document.getElementById('goal_weight');
                        if (goalW) goalW.value = profile.weight || '';
                    }

                    Swal.fire({ icon: 'success', title: 'Profile Updated', text: 'Your profile details have been saved.', timer: 1800, showConfirmButton: false });
                })
                .catch(function (error) {
                    Swal.fire({ icon: 'error', title: 'Update Failed', text: error.message });
                })
                .finally(function () {
                    if (button) { button.disabled = false; button.innerHTML = original; }
                });
        });
    }

    function boot() {
        bindNavigation();
        bindProfileSave();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
})();
