/* Dior Medical: independent dashboard tab navigation fallback. */
(function () {
    'use strict';
    function init(root, prefix) {
        if (!root || root.getAttribute('data-tabs-bound') === '1') return;
        root.setAttribute('data-tabs-bound', '1');
        function show(id) {
            var panel = document.getElementById('tab-' + id);
            if (!panel || !root.contains(panel)) return;
            root.querySelectorAll('.dior-nav-btn[data-tab]').forEach(function (button) {
                button.classList.toggle('active', button.getAttribute('data-tab') === id);
            });
            root.querySelectorAll('.dior-tab-panel').forEach(function (item) {
                var active = item === panel;
                item.classList.toggle('active', active);
                item.hidden = !active;
            });
            try { window.history.replaceState(null, '', '#tab=' + encodeURIComponent(id)); } catch (ignore) {}
        }
        if (root.id === 'dior-patient-portal-app') window.diorSwitchTab = show;
        if (root.id === 'dior-doctor-app') window.diorDocSwitchTab = show;
        root.addEventListener('click', function (event) {
            var sublink = event.target.closest('[data-appt-subtab]');
            if (sublink && root.contains(sublink)) {
                event.preventDefault();
                show('appointments');
                root.querySelectorAll('.dior-subtab-panel').forEach(function (item) {
                    item.style.display = item.id === 'dior-subtab-' + sublink.getAttribute('data-appt-subtab') ? 'block' : 'none';
                });
                return;
            }
            var button = event.target.closest('.dior-nav-btn[data-tab]');
            if (!button || !root.contains(button)) return;
            // Appointment parent has its own submenu handler; regular buttons use this handler.
            if (button.closest('.dior-nav-item-has-children')) return;
            event.preventDefault();
            show(button.getAttribute('data-tab'));
        });
        var hash = window.location.hash.match(/^#tab=([^&]+)/);
        show(hash ? decodeURIComponent(hash[1]) : prefix);
    }
    function boot() {
        init(document.getElementById('dior-patient-portal-app'), 'overview');
        init(document.getElementById('dior-doctor-app'), 'doc-overview');
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
