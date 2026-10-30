/**
 * Dior Medical - Patient Dashboard navigation safety controller.
 * Keeps sidebar navigation independent from other dashboard widgets.
 */
(function () {
    'use strict';

    // Fallbacks for mobile drawer
    window.diorCloseMobileDrawer = window.diorCloseMobileDrawer || function () {
        var sidebar = document.getElementById('dior-sidebar');
        var backdrop = document.getElementById('dior-drawer-backdrop');
        if (sidebar) {
            sidebar.classList.remove('open');
            sidebar.classList.remove('mobile-open');
        }
        if (backdrop) backdrop.classList.remove('active');
        document.body.style.overflow = '';
    };

    window.diorOpenMobileDrawer = window.diorOpenMobileDrawer || function () {
        var sidebar = document.getElementById('dior-sidebar');
        var backdrop = document.getElementById('dior-drawer-backdrop');
        if (sidebar) {
            sidebar.classList.add('open');
            sidebar.classList.add('mobile-open');
        }
        if (backdrop) backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

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
            settings: 'Settings',
            questionnaire: 'Clinical Intake Questionnaires'
        };

        function normalizeTabId(tabId) {
            if (!tabId) return 'overview';
            var t = String(tabId).trim();
            if (t.indexOf('#tab=') === 0) t = t.substring(5);
            if (t.indexOf('#') === 0) t = t.substring(1);
            if (t.indexOf('&') !== -1) t = t.split('&')[0];
            if (t === 'dashboard') return 'overview';
            if (t === 'prescriptions') return 'docs_meds';
            if (t === 'billing') return 'payments';
            return t;
        }

        function activate(rawTabId, updateHash) {
            var tabId = normalizeTabId(rawTabId);
            if (!tabId) return false;

            var panel = document.getElementById('tab-' + tabId);
            if (!panel && app) {
                panel = document.getElementById(tabId);
            }
            if (!panel || !app.contains(panel)) return false;

            // Nav buttons
            app.querySelectorAll('.dior-nav-btn[data-tab]').forEach(function (button) {
                var bTab = normalizeTabId(button.getAttribute('data-tab'));
                button.classList.toggle('active', bTab === tabId);
            });

            // Tab panels - toggle active class AND inline styles
            app.querySelectorAll('.dior-tab-panel').forEach(function (item) {
                var isActive = (item === panel);
                item.classList.toggle('active', isActive);
                if (isActive) {
                    item.removeAttribute('hidden');
                    item.style.setProperty('display', 'block', 'important');
                    item.style.setProperty('visibility', 'visible', 'important');
                    item.style.setProperty('opacity', '1', 'important');
                } else {
                    item.setAttribute('hidden', 'hidden');
                    item.style.setProperty('display', 'none', 'important');
                    item.style.setProperty('visibility', 'hidden', 'important');
                    item.style.setProperty('opacity', '0', 'important');
                }
            });

            // Sync subtab pills in in-page navigation bars
            app.querySelectorAll('.dior-subtab-pill[data-patient-tab]').forEach(function (pill) {
                var pTab = normalizeTabId(pill.getAttribute('data-patient-tab'));
                pill.classList.toggle('active', pTab === tabId);
            });

            // Sync mobile bottom navigation active state
            app.querySelectorAll('.dior-mobile-bottom-nav .dior-mobile-nav-item[data-switch-tab]').forEach(function (item) {
                var mTab = normalizeTabId(item.getAttribute('data-switch-tab'));
                var isMatch = (mTab === tabId);
                item.classList.toggle('is-active', isMatch);
                if (isMatch) {
                    item.setAttribute('aria-current', 'page');
                } else {
                    item.removeAttribute('aria-current');
                }
            });

            // Close mobile drawer and any open topbar dropdowns on tab switch
            if (typeof window.diorCloseMobileDrawer === 'function') {
                window.diorCloseMobileDrawer();
            }
            var notifDropdown = document.getElementById('dior-notif-dropdown');
            if (notifDropdown) notifDropdown.classList.remove('open');
            var profileDropdown = document.getElementById('dior-profile-dropdown');
            if (profileDropdown) profileDropdown.classList.remove('open');

            var title = document.getElementById('dior-current-page-title');
            if (title && titles[tabId]) title.textContent = titles[tabId];

            if (tabId !== 'appointments') {
                var w = document.getElementById('dior-appt-dropdown-wrap');
                if (w) {
                    w.classList.remove('open');
                    var trigger = w.querySelector(':scope > .dior-nav-btn');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                    var ch = w.querySelector('.fa-chevron-down');
                    if (ch) ch.style.transform = 'rotate(0deg)';
                }
            }

            if (tabId === 'notifications') {
                app.querySelectorAll('.unread-badge-count, .dior-notif-indicator').forEach(function (b) {
                    b.style.display = 'none';
                });
            }

            // Refresh table pagination in activated panel
            if (typeof window.diorInitTablePagination === 'function') {
                panel.querySelectorAll('.dior-table, .dior-clean-table, .dior-doc-table').forEach(function (table) {
                    if (table._diorRenderPage) {
                        table._diorRenderPage(1);
                    } else {
                        window.diorInitTablePagination(table, 5);
                    }
                });
            }

            if (updateHash !== false && window.history && window.history.replaceState) {
                try {
                    window.history.replaceState(null, '', '#tab=' + encodeURIComponent(tabId));
                } catch (e) { }
            }

            return true;
        }

        // Public APIs
        window.diorSafeSwitchPatientTab = activate;
        window.diorSwitchTab = activate;

        // Toggle appointments dropdown helper
        window._diorLastApptToggleTime = window._diorLastApptToggleTime || 0;
        function toggleApptDropdown(e) {
            if (e && e.preventDefault) e.preventDefault();
            if (e && e.stopPropagation) e.stopPropagation();

            var w = document.getElementById('dior-appt-dropdown-wrap');
            if (!w) return;

            var isMobile = window.matchMedia && window.matchMedia('(max-width: 1024px)').matches;
            var isOpen = w.classList.contains('open');
            var trigger = w.querySelector(':scope > .dior-nav-btn');
            var chevron = w.querySelector(':scope > .dior-nav-btn .fa-chevron-down');

            // Mobile: parent item is an accordion trigger. Keep the drawer open
            // so users can select one of its child links.
            if (isMobile) {
                w.classList.toggle('open', !isOpen);
                if (trigger) trigger.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                if (chevron) chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                return;
            }

            // Desktop retains the original tab + dropdown interaction.
            if (isOpen) {
                w.classList.remove('open');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            } else {
                w.classList.add('open');
                if (trigger) trigger.setAttribute('aria-expanded', 'true');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
                activate('appointments', true);
                if (typeof window.diorSwitchApptSubTab === 'function') {
                    var activeSub = w.querySelector('.dior-nav-dropdown-item.active');
                    window.diorSwitchApptSubTab(activeSub ? activeSub.getAttribute('data-appt-subtab') : 'today');
                }
            }
        }
        window.diorToggleApptDropdown = toggleApptDropdown;

        // Unified capture listener on app container
        app.addEventListener('click', function (event) {
            // 1. Sidebar nav buttons
            var button = event.target.closest('.dior-nav-btn[data-tab]');
            if (button && app.contains(button)) {
                var tabId = button.getAttribute('data-tab');
                if (!tabId) return;

                event.preventDefault();
                event.stopImmediatePropagation();

                if (tabId === 'appointments') {
                    toggleApptDropdown(event);
                } else {
                    activate(tabId, true);
                }

                if (window.innerWidth <= 1024 && typeof window.diorCloseMobileDrawer === 'function') {
                    window.diorCloseMobileDrawer();
                }
                return;
            }

            // 2. Profile dropdown / quick switcher buttons [data-switch-tab]
            var switchBtn = event.target.closest('[data-switch-tab]');
            if (switchBtn && app.contains(switchBtn)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                var sTab = switchBtn.getAttribute('data-switch-tab');
                if (sTab) activate(sTab, true);
                var notifDropdown = document.getElementById('dior-notif-dropdown');
                if (notifDropdown) notifDropdown.classList.remove('open');
                return;
            }

            // 3. Subtab navigation pills [data-patient-tab]
            var pill = event.target.closest('.dior-subtab-pill[data-patient-tab]');
            if (pill && app.contains(pill)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                var pTab = pill.getAttribute('data-patient-tab');
                if (pTab) activate(pTab, true);
                return;
            }

            // 4. Appointments dropdown subtabs [data-appt-subtab]
            var apptSub = event.target.closest('[data-appt-subtab]');
            if (apptSub && app.contains(apptSub)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                activate('appointments', true);
                var subName = apptSub.getAttribute('data-appt-subtab');
                if (typeof window.diorSwitchApptSubTab === 'function') {
                    window.diorSwitchApptSubTab(subName);
                }
                if (window.innerWidth <= 1024 && typeof window.diorCloseMobileDrawer === 'function') {
                    window.diorCloseMobileDrawer();
                }
                return;
            }
        }, true);

        // Listen for browser back / forward navigation and deep-link hash updates
        window.addEventListener('hashchange', function () {
            var hash = window.location.hash || '';
            if (hash.indexOf('#tab=') === 0) {
                var raw = hash.substring(5);
                var requestedTab = raw;
                var requestedSubTab = null;
                if (raw.indexOf('&subtab=') !== -1) {
                    var parts = raw.split('&subtab=');
                    requestedTab = decodeURIComponent(parts[0]);
                    requestedSubTab = decodeURIComponent(parts[1]);
                } else {
                    requestedTab = decodeURIComponent(raw);
                }
                activate(requestedTab, false);
                if (requestedTab === 'appointments' && requestedSubTab && typeof window.diorSwitchApptSubTab === 'function') {
                    window.diorSwitchApptSubTab(requestedSubTab);
                }
            }
        });

        // Initial tab restoration from URL hash or default
        var hash = window.location.hash || '';
        var initialTab = 'overview';
        var initialSubTab = null;

        if (hash.indexOf('#tab=') === 0) {
            var raw = hash.substring(5);
            if (raw.indexOf('&subtab=') !== -1) {
                var parts = raw.split('&subtab=');
                initialTab = decodeURIComponent(parts[0]);
                initialSubTab = decodeURIComponent(parts[1]);
            } else {
                initialTab = decodeURIComponent(raw);
            }
        } else {
            var active = app.querySelector('.dior-nav-btn.active[data-tab]');
            initialTab = active ? active.getAttribute('data-tab') : 'overview';
        }

        if (initialTab === 'appointments') {
            activate('appointments', false);
            var sub = initialSubTab || (function () {
                try { return sessionStorage.getItem('dior_active_appt_subtab'); } catch (e) { return null; }
            })() || 'today';
            if (typeof window.diorSwitchApptSubTab === 'function') {
                window.diorSwitchApptSubTab(sub);
            }
        } else {
            if (!activate(initialTab, false)) activate('overview', false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
