/**
 * Dior Medical — Patient Dashboard navigation safety controller.
 * Keeps sidebar navigation independent from other dashboard widgets.
 */
(function () {
    'use strict';

    function boot() {
        var app = document.getElementById('dior-patient-portal-app');
        if (!app || app.__diorSafeNavigationReady) return;

        app.__diorSafeNavigationReady = true;

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

        function activate(tabId, updateHash) {
            if (!tabId) return false;

            var panel = document.getElementById('tab-' + tabId);
            if (!panel || !app.contains(panel)) return false;

            app.querySelectorAll('.dior-nav-btn[data-tab]').forEach(function (button) {
                button.classList.toggle('active', button.getAttribute('data-tab') === tabId);
            });

            app.querySelectorAll('.dior-tab-panel').forEach(function (item) {
                item.classList.toggle('active', item === panel);
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

            if (updateHash !== false && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#tab=' + encodeURIComponent(tabId));
            }

            return true;
        }

        // Public API used by existing dashboard controls.
        window.diorSafeSwitchPatientTab = activate;

        // Capture before other handlers so a broken widget cannot block navigation.
        app.addEventListener('click', function (event) {
            var button = event.target.closest('.dior-nav-btn[data-tab]');
            if (!button || !app.contains(button)) return;

            var tabId = button.getAttribute('data-tab');
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
                            activate('appointments', true);
                            if (typeof window.diorSwitchApptSubTab === 'function') {
                                window.diorSwitchApptSubTab('today');
                            }
                        }
                    }
                }
            } else {
                activate(tabId, true);
            }

            // Close the mobile drawer after a successful selection.
            if (window.innerWidth <= 1024 && typeof window.diorCloseMobileDrawer === 'function') {
                window.diorCloseMobileDrawer();
            }
        }, true);

        // Restore a valid tab and subtab from the URL, otherwise keep Overview active.
        var hash = window.location.hash || '';
        var requestedTab = 'overview';
        var requestedSubTab = null;

        if (hash.indexOf('#tab=') === 0) {
            var raw = hash.substring(5);
            if (raw.indexOf('&subtab=') !== -1) {
                var parts = raw.split('&subtab=');
                requestedTab = decodeURIComponent(parts[0]);
                requestedSubTab = decodeURIComponent(parts[1]);
            } else {
                requestedTab = decodeURIComponent(raw);
            }
        } else {
            var active = app.querySelector('.dior-nav-btn.active[data-tab]');
            requestedTab = active ? active.getAttribute('data-tab') : 'overview';
        }

        if (requestedTab === 'appointments') {
            activate('appointments', false);
            var sub = requestedSubTab || (function(){
                try { return sessionStorage.getItem('dior_active_appt_subtab'); } catch(e){ return null; }
            })() || 'today';
            if (typeof window.diorSwitchApptSubTab === 'function') {
                window.diorSwitchApptSubTab(sub);
            }
        } else {
            if (!activate(requestedTab, false)) activate('overview', false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
