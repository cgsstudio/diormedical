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
        if (t === 'feedback' || t === 'doc-feedback' || t === 'patient-review' || t === 'reviews') {
            return 'doc-patient-review';
        }
        if (t === 'notifications' || t === 'doc-notifications') {
            return 'doc-notifications-system';
        }
        if (t === 'documents' || t === 'doc-documents') {
            return 'doc-documents-upload';
        }
        if (t === 'patients' || t === 'doc-patients') {
            return 'doc-patients-all';
        }
        if (t === 'appointments') {
            return 'doc-appointments';
        }
        if (t === 'settings') {
            return 'doc-settings';
        }
        return t;
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
        var contentRoot = app && app.querySelector('#dior-doc-content');
        if (!app || !contentRoot || !tabId) return false;

        var normalizedId = normalizeTabId(tabId);
        var panelId = 'tab-' + normalizedId;
        var activePanel = Array.prototype.find.call(contentRoot.children, function (child) {
            return child.id === panelId || child.id === normalizedId;
        });

        // Fallback for panels rendered inside a wrapper by a particular PHP template.
        if (!activePanel) {
            activePanel = contentRoot.querySelector('[id="' + panelId + '"], [id="' + normalizedId + '"]');
        }
        if (!activePanel || !activePanel.classList.contains('dior-tab-panel')) {
            console.error('[Dior Navigation] Dashboard panel not found:', tabId, 'expected:', panelId);
            return false;
        }

        // Hide every other dashboard panel without relying on :scope selector support.
        Array.prototype.forEach.call(contentRoot.querySelectorAll('.dior-tab-panel'), function (panel) {
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

            // Check if clicking a parent submenu toggle button
            var parentMenuBtn = event.target.closest('#dior-doctor-app #dior-doc-sidebar .dior-nav-item.has-submenu > .dior-nav-btn');
            if (parentMenuBtn && !parentMenuBtn.getAttribute('data-tab')) {
                var parentItem = parentMenuBtn.closest('.dior-nav-item.has-submenu');
                if (parentItem && parentItem.id) {
                    event.preventDefault();
                    window.diorToggleSubmenu(event, parentItem.id);
                    return;
                }
            }
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
