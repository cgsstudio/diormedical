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
            event.stopPropagation();
            activate(tabId, true);

            if (tabId === 'appointments' && typeof window.diorSwitchApptSubTab === 'function') {
                window.diorSwitchApptSubTab('today');
            }

            // Close the mobile drawer after a successful selection.
            if (window.innerWidth <= 1024 && typeof window.diorCloseMobileDrawer === 'function') {
                window.diorCloseMobileDrawer();
            }
        }, true);

        // Restore a valid tab from the URL, otherwise keep Overview active.
        var hash = window.location.hash || '';
        if (hash.indexOf('#tab=') === 0) {
            var requested = decodeURIComponent(hash.substring(5));
            if (!activate(requested, false)) activate('overview', false);
        } else {
            var active = app.querySelector('.dior-nav-btn.active[data-tab]');
            activate(active ? active.getAttribute('data-tab') : 'overview', false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
