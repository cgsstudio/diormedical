/**
 * Dior Medical — Doctor Dashboard Navigation Safety Controller
 * Provides isolated, rock-solid sidebar navigation with zero dependencies.
 * Ensures Doctor Dashboard tabs ALWAYS switch smoothly even if other widgets fail.
 */
(function () {
    'use strict';

    // Normalize tab ID aliases
    function normalizeTabId(tabId) {
        if (!tabId) return 'doc-overview';
        var t = String(tabId).trim();
        if (t.indexOf('#tab=') === 0) t = t.substring(5);
        else if (t.indexOf('#') === 0) t = t.substring(1);
        if (t.indexOf('tab-') === 0) t = t.substring(4);

        // Overview
        if (t === 'overview' || t === 'dashboard' || t === 'doc-dashboard') return 'doc-overview';

        // Appointments
        if (t === 'appointments-today' || t === 'today-appointments' || t === 'today' || t === 'doc-appointments-today' || t === 'doc-today-appointments') {
            setTimeout(function () {
                var df = document.getElementById('va-date-filter');
                if (df) {
                    df.value = 'today';
                    df.dispatchEvent(new Event('change'));
                }
            }, 80);
            return 'doc-appointments-view';
        }
        if (t === 'appointments' || t === 'calendar' || t === 'appointment-calendar') return 'doc-appointments';
        if (t === 'appointments-view' || t === 'view-appointment' || t === 'view-appointments') return 'doc-appointments-view';
        if (t === 'appointments-book' || t === 'book-appointment' || t === 'book-appointments') return 'doc-appointments-book';
        if (t === 'appointments-edit' || t === 'edit-appointment' || t === 'edit-appointments') return 'doc-appointments-edit';

        // Patients
        if (t === 'patients' || t === 'doc-patients' || t === 'patients-all' || t === 'all-patients') return 'doc-patients-all';
        if (t === 'patients-add' || t === 'add-patient' || t === 'doc-add-patient') return 'doc-patients-add';
        if (t === 'patients-edit' || t === 'edit-patient' || t === 'doc-edit-patient') return 'doc-patients-edit';
        if (t === 'patients-records' || t === 'patient-records' || t === 'records' || t === 'doc-records') return 'doc-patients-records';
        if (t === 'patients-profile' || t === 'patient-profile' || t === 'profile' || t === 'doc-profile') return 'doc-patients-profile';

        // Accounts & Billing
        if (t === 'accounts' || t === 'doc-accounts' || t === 'billing' || t === 'doc-billing' || t === 'bill-list' || t === 'accounts-bill-list') return 'doc-accounts-bill-list';
        if (t === 'accounts-add-bill' || t === 'add-bill') return 'doc-accounts-add-bill';
        if (t === 'accounts-income' || t === 'income' || t === 'doc-income') return 'doc-accounts-income';
        if (t === 'accounts-expenses' || t === 'expenses' || t === 'doc-expenses') return 'doc-accounts-expenses';
        if (t === 'accounts-income-report' || t === 'income-report' || t === 'doc-income-report') return 'doc-accounts-income-report';
        if (t === 'accounts-invoice' || t === 'invoice' || t === 'doc-invoice') return 'doc-accounts-invoice';

        // Consultation notes & Prescriptions
        if (t === 'consultation-notes' || t === 'notes' || t === 'doc-notes') return 'doc-consultation-notes';
        if (t === 'prescriptions' || t === 'doc-prescriptions' || t === 'e-prescriptions' || t === 'doc-e-prescriptions' || t === 'digital-prescriptions') return 'doc-e-prescriptions';

        // Pharmacy
        if (t === 'pharmacy' || t === 'doc-pharmacy' || t === 'pharmacy-list' || t === 'medicines' || t === 'doc-medicines') return 'doc-pharmacy-list';
        if (t === 'pharmacy-add' || t === 'add-medicine' || t === 'doc-medicine-add') return 'doc-pharmacy-add';

        // Documents
        if (t === 'documents' || t === 'doc-documents' || t === 'documents-upload' || t === 'upload-documents') return 'doc-documents-upload';
        if (t === 'documents-templates' || t === 'consent-templates' || t === 'templates') return 'doc-documents-templates';
        if (t === 'documents-signed' || t === 'signed-consent' || t === 'signed') return 'doc-documents-signed';

        // Telemedicine
        if (t === 'telemed' || t === 'telemedicine' || t === 'doc-telemedicine' || t === 'doc-telemed-video' || t === 'telemed-video' || t === 'video-consultation') return 'doc-telemed-video';
        if (t === 'telemed-records' || t === 'doc-telemed-records' || t === 'virtual-visits' || t === 'virtual-visit-records') return 'doc-telemed-records';

        // Feedback & Reviews
        if (t === 'feedback' || t === 'doc-feedback' || t === 'patient-review' || t === 'reviews' || t === 'doc-reviews') return 'doc-patient-review';

        // Notifications
        if (t === 'notifications' || t === 'doc-notifications' || t === 'notifications-system' || t === 'doc-notifications-system') return 'doc-notifications-system';
        if (t === 'notifications-alerts' || t === 'doc-notifications-alerts') return 'doc-notifications-alerts';

        // Consultation / Telemedicine fallback
        if (t === 'consultation' || t === 'consultation-room' || t === 'doc-consultation' || t === 'doc-consultation-room') return 'doc-telemed-video';

        // Settings
        if (t === 'settings' || t === 'profile-settings') return 'doc-settings';

        return t;
    }
    window.normalizeDoctorTabId = normalizeTabId;

    // Global submenu toggle function
    window.diorToggleSubmenu = function (e, menuId) {
        if (typeof e === 'string' && !menuId) {
            menuId = e;
            e = null;
        }
        if (e) {
            if (e.preventDefault) e.preventDefault();
            if (e.stopPropagation) e.stopPropagation();
        }
        var targetMenu = document.getElementById(menuId);
        if (!targetMenu) return;

        var isAlreadyOpen = targetMenu.classList.contains('active') && targetMenu.classList.contains('open');

        // Strict Accordion: Close ALL other submenus
        document.querySelectorAll('#dior-doc-sidebar .dior-nav-item.has-submenu').forEach(function (item) {
            if (item !== targetMenu) {
                item.classList.remove('active', 'open');
                var subEl = item.querySelector('.dior-submenu');
                if (subEl) subEl.style.setProperty('display', 'none', 'important');
                var icon = item.querySelector('.submenu-icon');
                if (icon) {
                    icon.classList.remove('fa-minus');
                    icon.classList.add('fa-plus');
                }
            }
        });

        // Toggle target
        if (isAlreadyOpen) {
            targetMenu.classList.remove('active', 'open');
            var subEl = targetMenu.querySelector('.dior-submenu');
            if (subEl) subEl.style.setProperty('display', 'none', 'important');
            var icon = targetMenu.querySelector('.submenu-icon');
            if (icon) {
                icon.classList.remove('fa-minus');
                icon.classList.add('fa-plus');
            }
        } else {
            targetMenu.classList.add('active', 'open');
            var subEl = targetMenu.querySelector('.dior-submenu');
            if (subEl) subEl.style.setProperty('display', 'flex', 'important');
            var icon = targetMenu.querySelector('.submenu-icon');
            if (icon) {
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-minus');
            }
        }
    };

    // Mobile drawer close helper
    window.diorDocCloseMobile = function () {
        var sidebar = document.getElementById('dior-doc-sidebar');
        var backdrop = document.getElementById('dior-doc-backdrop');
        if (sidebar) sidebar.classList.remove('mobile-open');
        if (backdrop) backdrop.classList.remove('active');
    };

    // Mobile drawer open helper
    window.diorDocOpenMobile = function () {
        var sidebar = document.getElementById('dior-doc-sidebar');
        var backdrop = document.getElementById('dior-doc-backdrop');
        if (!sidebar) return;
        var isOpen = sidebar.classList.contains('mobile-open');
        if (isOpen) {
            sidebar.classList.remove('mobile-open');
            if (backdrop) backdrop.classList.remove('active');
        } else {
            sidebar.classList.add('mobile-open');
            if (backdrop) backdrop.classList.add('active');
        }
    };

    // Main tab switcher
    window.diorDocSwitchTab = function (tabId, updateUrl) {
        var app = document.getElementById('dior-doctor-app');
        if (!tabId) return false;
        var normalizedId = normalizeTabId(tabId);

        // Find the matching panel inside Doctor Dashboard only; avoid collisions with other dashboard markup.
        var contentRoot = app ? app.querySelector('#dior-doc-content') : document.getElementById('dior-doc-content');
        var activePanel = contentRoot ? contentRoot.querySelector('#tab-' + normalizedId) : null;
        if (!activePanel && contentRoot) {
            activePanel = contentRoot.querySelector('#' + normalizedId);
        }
        if (!activePanel && contentRoot && normalizedId !== 'doc-overview') {
            // Keep the desktop dashboard usable if a requested panel is missing.
            activePanel = contentRoot.querySelector('#tab-doc-overview');
            normalizedId = 'doc-overview';
        }
        if (!activePanel) {
            console.warn('[Dior Navigation] Panel not found for tab:', tabId, '(normalized: ' + normalizedId + ')');
            return false;
        }

        // Close notification dropdown if open
        var notifDropdown = document.getElementById('dior-doc-notif-dd');
        if (notifDropdown) {
            notifDropdown.classList.remove('open', 'active');
        }

        // Update nav buttons
        var sidebar = app ? app.querySelector('#dior-doc-sidebar') : document.getElementById('dior-doc-sidebar');
        var buttons = sidebar ? sidebar.querySelectorAll('.dior-nav-btn') : [];
        buttons.forEach(function (b) {
            b.classList.remove('active');
        });

        var activeBtn = sidebar ? sidebar.querySelector('.dior-nav-btn[data-tab="' + normalizedId + '"]') : null;
        if (activeBtn) {
            activeBtn.classList.add('active');

            // Strict Accordion: Expand parent submenu if inside one, close all others
            var parentSubmenu = activeBtn.closest('.dior-nav-item.has-submenu');
            document.querySelectorAll('#dior-doc-sidebar .dior-nav-item.has-submenu').forEach(function(sub) {
                if (parentSubmenu && sub === parentSubmenu) {
                    sub.classList.add('active', 'open');
                    var subEl = sub.querySelector('.dior-submenu');
                    if (subEl) subEl.style.setProperty('display', 'flex', 'important');
                    var subIcon = sub.querySelector('.submenu-icon');
                    if (subIcon) {
                        subIcon.classList.remove('fa-plus');
                        subIcon.classList.add('fa-minus');
                    }
                } else {
                    sub.classList.remove('active', 'open');
                    var subEl = sub.querySelector('.dior-submenu');
                    if (subEl) subEl.style.setProperty('display', 'none', 'important');
                    var subIcon = sub.querySelector('.submenu-icon');
                    if (subIcon) {
                        subIcon.classList.remove('fa-minus');
                        subIcon.classList.add('fa-plus');
                    }
                }
            });

            // Dismiss badge with transition if present
            var badge = activeBtn.querySelector('.nav-count-badge');
            if (badge) {
                badge.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                badge.style.opacity = '0';
                badge.style.transform = 'scale(0.5)';
                setTimeout(function () {
                    badge.style.display = 'none';
                }, 250);
            }
        }

        // Sync subtab pills in in-page navigation bars
        document.querySelectorAll('.dior-subtab-pill[data-doc-tab], .dior-subtab-pill[data-tab]').forEach(function (pill) {
            var pillTab = pill.getAttribute('data-doc-tab') || pill.getAttribute('data-tab');
            if (normalizeTabId(pillTab) === normalizedId) {
                pill.classList.add('active');
            } else {
                pill.classList.remove('active');
            }
        });

        // Update tab panels
        var panels = contentRoot ? contentRoot.querySelectorAll(':scope > .dior-tab-panel, .dior-tab-panel') : document.querySelectorAll('#dior-doc-content > .dior-tab-panel');
        panels.forEach(function (panel) {
            if (panel === activePanel) {
                panel.classList.add('active');
                panel.removeAttribute('hidden');
                panel.style.setProperty('display', 'block', 'important');
            } else {
                panel.classList.remove('active');
                panel.setAttribute('hidden', 'hidden');
                panel.style.setProperty('display', 'none', 'important');
            }
        });

        // Update URL hash
        if (updateUrl !== false && window.history && window.history.replaceState) {
            try {
                window.history.replaceState(null, '', '#tab=' + encodeURIComponent(normalizedId));
            } catch (e) { }
        }

        // Refresh table pagination if table exists in active panel
        if (window.diorDoctorInitTablePagination) {
            activePanel.querySelectorAll('.dior-doc-table, .dior-table, .dior-clean-table').forEach(function (table) {
                if (table._diorRenderPage) {
                    table._diorRenderPage(1);
                } else {
                    window.diorDoctorInitTablePagination(table, 5);
                }
            });
        }

        // Trigger doctor appointment calendar re-render if switching to calendar tab
        if (normalizedId === 'doc-appointments' && typeof window.diorRenderDoctorCalendar === 'function') {
            setTimeout(window.diorRenderDoctorCalendar, 40);
        }

        // Trigger income report chart if opened
        if (normalizedId === 'doc-accounts-income-report' && typeof window.diorRenderIncomeChart === 'function') {
            setTimeout(window.diorRenderIncomeChart, 50);
        }

        // If notifications tab opened, clear topbar bell badge and mark read in database
        if (normalizedId === 'doc-notifications-system') {
            document.querySelectorAll('.doc-unread-badge, .dior-notif-indicator').forEach(function (b) {
                b.style.display = 'none';
            });
            if (window.diorDocAjax && window.diorDocNonce) {
                try {
                    var fd = new FormData();
                    fd.append('action', 'dior_doctor_mark_all_notif_read');
                    fd.append('nonce', window.diorDocNonce);
                    fetch(window.diorDocAjax, { method: 'POST', body: fd }).catch(function () { });
                } catch (e) { }
            }
        }

        // Close mobile drawer on selection
        window.diorDocCloseMobile();

        return true;
    };

    // Global navigation aliases for doctor templates
    window.diorSwitchTab = function (tabId, updateUrl) {
        if (document.getElementById('dior-doctor-app')) {
            return window.diorDocSwitchTab(tabId, updateUrl);
        }
        if (typeof window.diorSafeSwitchPatientTab === 'function') {
            return window.diorSafeSwitchPatientTab(tabId, updateUrl);
        }
    };
    window.diorAdminSwitchTab = function (tabId, updateUrl) {
        return window.diorDocSwitchTab(tabId, updateUrl);
    };

    function boot() {
        var app = document.getElementById('dior-doctor-app');
        if (!app || app.__diorDoctorNavigationReady) return;
        app.__diorDoctorNavigationReady = true;

        // Capture-phase event delegation for rock-solid click response
        app.addEventListener('click', function (event) {
            // Check if clicking a tab switch button
            var tabBtn = event.target.closest('#dior-doctor-app #dior-doc-sidebar .dior-nav-btn[data-tab]');
            if (tabBtn) {
                var tab = tabBtn.getAttribute('data-tab');
                if (tab) {
                    // Do not stop propagation here: existing tab handlers may load dynamic data.
                    event.preventDefault();
                    window.diorDocSwitchTab(tab, true);
                    return;
                }
            }

            // Note: Parent submenu toggle buttons are handled directly by their onclick/native event handlers.
        }, true);

        // Sync tab from URL hash on load
        function syncTabFromUrl(updateUrl) {
            var hashMatch = window.location.hash.match(/^#tab=([^&]+)/);
            var requestedTab = 'doc-overview';
            // Malformed percent-encoding in the URL must not stop initial dashboard rendering.
            if (hashMatch) {
                try {
                    requestedTab = decodeURIComponent(hashMatch[1]);
                } catch (error) {
                    requestedTab = 'doc-overview';
                }
            }
            requestedTab = normalizeTabId(requestedTab);

            var app = document.getElementById('dior-doctor-app');
            var contentRoot = app ? app.querySelector('#dior-doc-content') : document.getElementById('dior-doc-content');
            var panel = contentRoot ? contentRoot.querySelector('#tab-' + requestedTab) : null;
            var tabToLoad = panel ? requestedTab : 'doc-overview';
            window.diorDocSwitchTab(tabToLoad, updateUrl);
        }

        window.addEventListener('hashchange', function () {
            syncTabFromUrl(false);
        });

        // Initialize immediately
        syncTabFromUrl(false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
