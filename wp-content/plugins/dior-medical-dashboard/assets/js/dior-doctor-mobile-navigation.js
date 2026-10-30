/* DIOR Doctor Dashboard — isolated mobile drawer and accordion controller.
 * Uses the existing sidebar markup and existing tab handlers; desktop behavior is untouched.
 */
(function () {
    'use strict';

    var mobileQuery = window.matchMedia('(max-width: 1024px)');
    // Preserve the original desktop submenu implementation before installing mobile handlers.
    var desktopToggleSubmenu = window.diorToggleSubmenu;
    var desktopOpenMobile = window.diorDocOpenMobile;
    var desktopCloseMobile = window.diorDocCloseMobile;

    function getElements() {
        return {
            app: document.getElementById('dior-doctor-app'),
            sidebar: document.getElementById('dior-doc-sidebar'),
            backdrop: document.getElementById('dior-doc-backdrop'),
            toggle: document.querySelector('#dior-doctor-app .dior-mobile-menu-toggle')
        };
    }

    function setDrawerState(open) {
        var el = getElements();
        if (!el.sidebar || !mobileQuery.matches) {
            if (!mobileQuery.matches) {
                if (el.sidebar) el.sidebar.classList.remove('mobile-open');
                if (el.backdrop) el.backdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
            return;
        }

        el.sidebar.classList.toggle('mobile-open', !!open);
        if (el.backdrop) el.backdrop.classList.toggle('active', !!open);
        document.body.style.overflow = open ? 'hidden' : '';

        if (el.toggle) el.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        el.sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    // Override conflicting legacy handlers after both legacy scripts are loaded.
    window.diorDocOpenMobile = function (event) {
        if (!mobileQuery.matches) {
            if (typeof desktopOpenMobile === 'function') return desktopOpenMobile(event);
            return;
        }
        if (event && event.preventDefault) event.preventDefault();
        var sidebar = getElements().sidebar;
        setDrawerState(!(sidebar && sidebar.classList.contains('mobile-open')));
    };

    window.diorDocCloseMobile = function () {
        if (!mobileQuery.matches) {
            if (typeof desktopCloseMobile === 'function') return desktopCloseMobile();
            return;
        }
        var el = getElements();
        if (el.sidebar) el.sidebar.classList.remove('mobile-open');
        if (el.backdrop) el.backdrop.classList.remove('active');
        document.body.style.overflow = '';
        if (el.toggle) el.toggle.setAttribute('aria-expanded', 'false');
        if (el.sidebar) el.sidebar.setAttribute('aria-hidden', 'true');
    };

    // Keep one submenu expanded at a time and synchronize its icon/accessibility state.
    window.diorToggleSubmenu = function (event, menuId) {
        // Desktop must continue using the pre-existing desktop navigation logic unchanged.
        if (!mobileQuery.matches) {
            if (typeof desktopToggleSubmenu === 'function') {
                return desktopToggleSubmenu(event, menuId);
            }
            return;
        }

        if (event && event.preventDefault) event.preventDefault();
        if (event && event.stopPropagation) event.stopPropagation();

        var sidebar = getElements().sidebar;
        var target = sidebar && sidebar.querySelector('#' + CSS.escape(menuId));
        if (!target) return;

        var shouldOpen = !(target.classList.contains('active') || target.classList.contains('open'));
        sidebar.querySelectorAll('.dior-nav-item.has-submenu').forEach(function (item) {
            var active = item === target && shouldOpen;
            item.classList.toggle('active', active);
            item.classList.toggle('open', active);
            var trigger = item.querySelector(':scope > .dior-nav-btn');
            var icon = item.querySelector(':scope > .dior-nav-btn .submenu-icon');
            if (trigger) trigger.setAttribute('aria-expanded', active ? 'true' : 'false');
            if (icon) {
                icon.classList.toggle('fa-minus', active);
                icon.classList.toggle('fa-plus', !active);
            }
        });
    };

    // Guard against a stale drawer backdrop capturing clicks across the dashboard.
    function enforceBackdropState() {
        var el = getElements();
        if (!el.backdrop) return;
        var drawerOpen = !!(mobileQuery.matches && el.sidebar && el.sidebar.classList.contains('mobile-open'));
        el.backdrop.classList.toggle('active', drawerOpen);
        el.backdrop.style.pointerEvents = drawerOpen ? 'auto' : 'none';
        if (!mobileQuery.matches) {
            el.backdrop.style.display = 'none';
            document.body.style.overflow = '';
        } else {
            el.backdrop.style.display = drawerOpen ? 'block' : 'none';
        }
    }

    function bindMobileNavigation() {
        var el = getElements();
        if (!el.app || !el.sidebar) return;

        // Make all accordion triggers keyboard-accessible without changing their HTML IDs.
        el.sidebar.querySelectorAll('.dior-nav-item.has-submenu').forEach(function (item) {
            var trigger = item.querySelector(':scope > .dior-nav-btn');
            var submenu = item.querySelector(':scope > .dior-submenu');
            if (trigger) {
                trigger.setAttribute('aria-haspopup', 'true');
                trigger.setAttribute('aria-expanded', item.classList.contains('active') ? 'true' : 'false');
            }
            if (submenu && !submenu.id) submenu.id = item.id + '-submenu';
            if (trigger && submenu) trigger.setAttribute('aria-controls', submenu.id);
        });

        // A selected navigation item closes the drawer after the existing tab handler runs.
        el.sidebar.addEventListener('click', function (event) {
            var navButton = event.target.closest('.dior-nav-btn[data-tab]');
            if (navButton && el.sidebar.contains(navButton)) {
                window.setTimeout(window.diorDocCloseMobile, 0);
            }
        });

        if (el.backdrop) {
            el.backdrop.addEventListener('click', window.diorDocCloseMobile);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && el.sidebar.classList.contains('mobile-open')) {
                window.diorDocCloseMobile();
                if (el.toggle) el.toggle.focus();
            }
        });

        // Ensure the drawer never remains stuck open after rotating to desktop.
        if (mobileQuery.addEventListener) {
            mobileQuery.addEventListener('change', function (event) {
                if (!event.matches) window.diorDocCloseMobile();
            });
        } else if (mobileQuery.addListener) {
            mobileQuery.addListener(function (event) {
                if (!event.matches) window.diorDocCloseMobile();
            });
        }

        if (mobileQuery.matches) {
            el.sidebar.setAttribute('aria-hidden', el.sidebar.classList.contains('mobile-open') ? 'false' : 'true');
        } else {
            el.sidebar.removeAttribute('aria-hidden');
        }

        // Clear stale overlay state and keep it synchronized with the actual drawer.
        enforceBackdropState();
        if (el.backdrop && window.MutationObserver) {
            var backdropObserver = new MutationObserver(enforceBackdropState);
            backdropObserver.observe(el.backdrop, { attributes: true, attributeFilter: ['class'] });
        }
        window.addEventListener('resize', enforceBackdropState, { passive: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindMobileNavigation);
    } else {
        bindMobileNavigation();
    }
})();