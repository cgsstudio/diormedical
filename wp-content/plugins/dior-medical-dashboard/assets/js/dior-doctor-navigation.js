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
        var t = String(tabId).trim().toLowerCase();
        var aliases = {
            'overview': 'doc-overview',
            'dashboard': 'doc-overview',
            'feedback': 'doc-patient-review',
            'doc-feedback': 'doc-patient-review',
            'patient-review': 'doc-patient-review',
            'reviews': 'doc-patient-review',
            'notifications': 'doc-notifications-system',
            'doc-notifications': 'doc-notifications-system',
            'documents': 'doc-documents-upload',
            'doc-documents': 'doc-documents-upload',
            'patients': 'doc-patients-all',
            'all-patients': 'doc-patients-all',
            'patient-records': 'doc-patients-records',
            'records': 'doc-patients-records',
            'patient-profile': 'doc-patients-profile',
            'add-patient': 'doc-patients-add',
            'edit-patient': 'doc-patients-edit',
            'appointments': 'doc-appointments',
            'appointment-calendar': 'doc-appointments',
            'view-appointment': 'doc-appointments-view',
            'book-appointment': 'doc-appointments-book',
            'edit-appointment': 'doc-appointments-edit',
            'settings': 'doc-settings',
            'prescriptions': 'doc-e-prescriptions',
            'digital-prescriptions': 'doc-e-prescriptions',
            'consultation': 'doc-consultation',
            'consultation-notes': 'doc-consultation-notes',
            'telemedicine': 'doc-telemed-video',
            'telemedicine-video': 'doc-telemed-video',
            'telemedicine-records': 'doc-telemed-records',
            'pharmacy': 'doc-pharmacy-list',
            'income': 'doc-accounts-income',
            'expenses': 'doc-accounts-expenses',
            'bill-list': 'doc-accounts-bill-list',
            'add-bill': 'doc-accounts-add-bill',
            'invoice': 'doc-accounts-invoice',
            'income-report': 'doc-accounts-income-report'
        };
        if (aliases[t]) return aliases[t];
        return t.indexOf('tab-') === 0 ? t.substring(4) : t;
    }

    // Global submenu toggle
    window.diorToggleSubmenu = function (e, menuId) {
        if (e && e.preventDefault) e.preventDefault();
        var targetMenu = document.getElementById(menuId);
        if (!targetMenu) return;

        var isAlreadyOpen = targetMenu.classList.contains('active') || targetMenu.classList.contains('open');

        document.querySelectorAll('#dior-doc-sidebar .dior-nav-item.has-submenu').forEach(function (item) {
            if (item.id !== menuId) {
                item.classList.remove('active', 'open');
                var icon = item.querySelector('.submenu-icon');
                if (icon) {
                    icon.classList.remove('fa-minus');
                    icon.classList.add('fa-plus');
                }
            }
        });

        if (isAlreadyOpen) {
            targetMenu.classList.remove('active', 'open');
            var icon = targetMenu.querySelector('.submenu-icon');
            if (icon) {
                icon.classList.remove('fa-minus');
                icon.classList.add('fa-plus');
            }
        } else {
            targetMenu.classList.add('active', 'open');
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

    // Main tab switcher — robustly resolves every real panel within the Doctor Dashboard.
    window.diorDocSwitchTab = function (tabId, updateUrl) {
        var app = document.getElementById('dior-doctor-app');
        // The dashboard's canonical content wrapper is an ID, not a generic
        // class selector. Resolve it first so a nested tab-content element can
        // never be mistaken for the main panel container.
        var contentRoot = document.getElementById('dior-doc-content')
            || document.getElementById('dior-tab-content')
            || (app && app.querySelector('.dior-tab-content'));
        if (!app || !contentRoot || !tabId) return false;

        var normalizedId = normalizeTabId(tabId);
        var panelId = 'tab-' + normalizedId;
        // Resolve the known panel by its DOM ID, then ensure it belongs to this
        // dashboard's content wrapper. This avoids failures caused by wrappers,
        // selector escaping, or panels nested by template markup.
        var activePanel = document.getElementById(panelId)
            || document.getElementById(normalizedId);
        if (activePanel && activePanel.parentElement !== contentRoot) activePanel = null;
        // An invalid URL hash or an outdated menu entry must never leave the
        // dashboard with every panel hidden. Fall back to the overview panel.
        if (!activePanel || !activePanel.classList.contains('dior-tab-panel')) {
            if (normalizedId !== 'doc-overview') {
                return window.diorDocSwitchTab('doc-overview', updateUrl);
            }
        }
        if (!activePanel || !activePanel.classList.contains('dior-tab-panel')) {
            console.error('[Dior Navigation] Overview panel is missing; dashboard markup may not have loaded.');
            return false;
        }

        // The tab template marks the active panel with display:none inline;
        // remove that inline style so it cannot override the active state.
        activePanel.style.display = 'block';

        // Hide every other dashboard panel without relying on :scope selector support.
        Array.prototype.forEach.call(contentRoot.children, function (panel) {
            if (!panel.classList || !panel.classList.contains('dior-tab-panel')) return;
            var isActive = panel === activePanel;
            panel.classList.toggle('active', isActive);
            if (isActive) {
                panel.removeAttribute('hidden');
                panel.style.removeProperty('display');
                panel.style.display = 'block';
                panel.setAttribute('aria-hidden', 'false');
            } else {
                panel.setAttribute('hidden', 'hidden');
                panel.style.display = 'none';
                panel.setAttribute('aria-hidden', 'true');
            }
        });

        // Update sidebar active state and expand the active item's parent accordion.
        var sidebar = app.querySelector('#dior-doc-sidebar');
        if (sidebar) {
            sidebar.querySelectorAll('.dior-nav-btn').forEach(function (button) {
                button.classList.toggle('active', button.getAttribute('data-tab') === normalizedId);
            });

            var activeButton = sidebar.querySelector('.dior-nav-btn[data-tab="' + normalizedId + '"]');
            var parentSubmenu = activeButton && activeButton.closest('.dior-nav-item.has-submenu');
            sidebar.querySelectorAll('.dior-nav-item.has-submenu').forEach(function (item) {
                var open = !!parentSubmenu && item === parentSubmenu;
                item.classList.toggle('active', open);
                item.classList.toggle('open', open);
                var icon = item.querySelector('.submenu-icon');
                if (icon) {
                    icon.classList.toggle('fa-minus', open);
                    icon.classList.toggle('fa-plus', !open);
                }
                var trigger = item.querySelector(':scope > .dior-nav-btn');
                if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            if (activeButton) {
                var badge = activeButton.querySelector('.nav-count-badge');
                if (badge) badge.style.display = 'none';
            }
        }

        var notifDropdown = app.querySelector('#dior-doc-notif-dd');
        if (notifDropdown) notifDropdown.classList.remove('open', 'active');

        // Keep existing in-page subtab indicators synchronized with the sidebar.
        app.querySelectorAll('.dior-subtab-pill[data-doc-tab]').forEach(function (pill) {
            pill.classList.toggle('active', pill.getAttribute('data-doc-tab') === normalizedId);
        });

        // Preserve the existing notification read action when the notifications page is opened.
        if (normalizedId === 'doc-notifications-system' && window.diorDocAjax && window.diorDocNonce) {
            var notificationData = new FormData();
            notificationData.append('action', 'dior_doctor_mark_all_notif_read');
            notificationData.append('nonce', window.diorDocNonce);
            window.fetch(window.diorDocAjax, { method: 'POST', body: notificationData }).catch(function () {});
        }

        if (updateUrl !== false && window.history && window.history.replaceState) {
            try { window.history.replaceState(null, '', '#tab=' + encodeURIComponent(normalizedId)); }
            catch (error) { /* Keep navigation working when URL updates are blocked. */ }
        }

        if (typeof window.diorDoctorInitTablePagination === 'function') {
            activePanel.querySelectorAll('.dior-doc-table, .dior-table, .dior-clean-table').forEach(function (table) {
                if (typeof table._diorRenderPage === 'function') table._diorRenderPage(1);
                else window.diorDoctorInitTablePagination(table, 5);
            });
        }

        if (normalizedId === 'doc-appointments' && typeof window.diorRenderDoctorCalendar === 'function') {
            window.setTimeout(window.diorRenderDoctorCalendar, 40);
        }

        if (typeof window.diorDocCloseMobile === 'function') window.diorDocCloseMobile();
        app.dispatchEvent(new CustomEvent('dior:doctor-tab-changed', { detail: { tab: normalizedId } }));
        return true;
    };

    function boot() {
        var app = document.getElementById('dior-doctor-app');
        if (!app || app.__diorDoctorNavigationReady) return;
        app.__diorDoctorNavigationReady = true;

        // Capture-phase document delegation survives dashboard content wrappers
        // being replaced by WordPress/Elementor or another dashboard script.
        document.addEventListener('click', function (event) {
            // Check if clicking a tab switch button
            var clickTarget = event.target && event.target.nodeType === 1 ? event.target : event.target && event.target.parentElement;
            if (!clickTarget || !clickTarget.closest) return;
            var tabBtn = clickTarget.closest('#dior-doctor-app #dior-doc-sidebar .dior-nav-btn[data-tab]');
            if (tabBtn) {
                var tab = tabBtn.getAttribute('data-tab');
                if (tab) {
                    // Do not stop propagation here: existing tab handlers may load dynamic data.
                    event.preventDefault();
                    // This controller is the sole owner of sidebar navigation.
                    // Stop legacy delegated/inline handlers from switching the same tab again.
                    var switched = window.diorDocSwitchTab(tab, true);
                    if (switched) {
                        event.stopPropagation();
                        if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
                    }
                    return;
                }
            }

            // Check if clicking a parent submenu toggle button
            var parentMenuBtn = clickTarget.closest('#dior-doctor-app #dior-doc-sidebar .dior-nav-item.has-submenu > .dior-nav-btn');
            if (parentMenuBtn && !parentMenuBtn.getAttribute('data-tab')) {
                var parentItem = parentMenuBtn.closest('.dior-nav-item.has-submenu');
                if (parentItem && parentItem.id) {
                    event.preventDefault();
                    window.diorToggleSubmenu(event, parentItem.id);
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
                    return;
                }
            }
        }, true);

        // Sync tab from URL hash on load
        function syncTabFromUrl(updateUrl) {
            var requestedTab = '';
            // First support the current hash-based dashboard links.
            var hashMatch = window.location.hash.match(/^#(?:tab=)?([^&]+)/i);
            if (hashMatch) {
                try { requestedTab = decodeURIComponent(hashMatch[1]); }
                catch (error) { requestedTab = ''; }
            }

            // Also support legacy/direct WordPress URLs after a full page reload.
            // These are client-side tab routes because all authorized tab templates
            // are rendered inside the dashboard shortcode; no AJAX page renderer exists.
            if (!requestedTab && window.URLSearchParams) {
                var params = new URLSearchParams(window.location.search);
                requestedTab = params.get('tab') || params.get('page_tab') || params.get('view') || '';
            }
            requestedTab = normalizeTabId(requestedTab || 'doc-overview');

            var app = document.getElementById('dior-doctor-app');
            var contentRoot = document.getElementById('dior-doc-content')
                || document.getElementById('dior-tab-content')
                || (app && app.querySelector('.dior-tab-content'));
            var panel = document.getElementById('tab-' + requestedTab)
                || document.getElementById(requestedTab);
            if (panel && (!contentRoot || panel.parentElement !== contentRoot)) panel = null;
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
