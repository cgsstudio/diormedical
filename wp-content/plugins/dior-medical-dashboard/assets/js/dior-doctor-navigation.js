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
        var buttons = document.querySelectorAll('#dior-doc-sidebar .dior-nav-btn');
        buttons.forEach(function (b) {
            b.classList.remove('active');
        });

        var activeBtn = document.querySelector('#dior-doc-sidebar .dior-nav-btn[data-tab="' + normalizedId + '"]');
        if (activeBtn) {
            activeBtn.classList.add('active');

            // Strict Accordion: Expand parent submenu if inside one, close all others
            var parentSubmenu = activeBtn.closest('.dior-nav-item.has-submenu');
            document.querySelectorAll('#dior-doc-sidebar .dior-nav-item.has-submenu').forEach(function(sub) {
                if (parentSubmenu && sub === parentSubmenu) {
                    sub.classList.add('active', 'open');
                    var subIcon = sub.querySelector('.submenu-icon');
                    if (subIcon) {
                        subIcon.classList.remove('fa-plus');
                        subIcon.classList.add('fa-minus');
                    }
                } else {
                    sub.classList.remove('active', 'open');
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

        // Update tab panels
        var panels = document.querySelectorAll('#dior-doc-content > .dior-tab-panel');
        panels.forEach(function (panel) {
            if (panel === activePanel) {
                panel.classList.add('active');
                panel.removeAttribute('hidden');
                panel.style.removeProperty('display');
                panel.style.display = 'block';
            } else {
                panel.classList.remove('active');
                panel.setAttribute('hidden', 'hidden');
                panel.style.display = 'none';
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

        // Close mobile drawer on selection
        window.diorDocCloseMobile();

        return true;
    };

    function boot() {
        var app = document.getElementById('dior-doctor-app');
        if (!app || app.__diorDoctorNavigationReady) return;
        app.__diorDoctorNavigationReady = true;

        // Capture-phase event delegation for rock-solid click response
        app.addEventListener('click', function (event) {
            // Check if clicking a tab switch button
            var tabBtn = event.target.closest('#dior-doc-sidebar .dior-nav-btn[data-tab]');
            if (tabBtn) {
                var tab = tabBtn.getAttribute('data-tab');
                if (tab) {
                    event.preventDefault();
                    event.stopPropagation();
                    window.diorDocSwitchTab(tab, true);
                    return;
                }
            }

            // Check if clicking a parent submenu toggle button
            var parentMenuBtn = event.target.closest('#dior-doc-sidebar .dior-nav-item.has-submenu > .dior-nav-btn');
            if (parentMenuBtn && !parentMenuBtn.getAttribute('data-tab')) {
                var parentItem = parentMenuBtn.closest('.dior-nav-item.has-submenu');
                if (parentItem && parentItem.id) {
                    event.preventDefault();
                    event.stopPropagation();
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
