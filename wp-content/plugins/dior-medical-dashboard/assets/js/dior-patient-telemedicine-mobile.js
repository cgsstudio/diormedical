/* Separate mobile Telemedicine renderer. Reads existing PHP table data and delegates actions to existing handlers. */
(function () {
    'use strict';

    function initTelemedicineMobile() {
        var root = document.getElementById('tab-telemedicine');
        var sourceBody = document.getElementById('dior-tele-tbody');
        var list = document.getElementById('dior-tele-mobile-list');
        var pager = document.getElementById('dior-tele-mobile-pagination');
        var search = document.getElementById('dior-tele-search-input');

        if (!root || !sourceBody || !list || !pager || root.__diorTeleMobileReady) return;
        root.__diorTeleMobileReady = true;

        var currentPage = 1;
        var pageSize = 5;

        function esc(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
            });
        }

        function getRows() {
            return Array.prototype.slice.call(sourceBody.querySelectorAll('tr')).filter(function (row) {
                return row.cells.length >= 10 && !row.querySelector('.dior-empty-state');
            });
        }

        function rowData(row) {
            var cells = row.cells;
            var attr = function (name, fallback) {
                return row.getAttribute(name) || fallback || '—';
            };
            var status = attr('data-status', cells[8].textContent.trim());
            var statusClass = status.toLowerCase() === 'completed' ? 'completed' :
                (status.toLowerCase() === 'cancelled' || status.toLowerCase() === 'no show' ? 'cancelled' : '');
            return {
                id: attr('data-tele-id', cells[1].textContent.trim()),
                doctor: attr('data-doctor', cells[2].textContent.trim()),
                specialty: attr('data-specialty', cells[3].textContent.trim()),
                date: attr('data-date', cells[4].textContent.trim()),
                time: attr('data-time', cells[5].textContent.trim()),
                duration: attr('data-duration', cells[6].textContent.trim()),
                type: attr('data-type', cells[7].textContent.trim()),
                status: status,
                statusClass: statusClass,
                joinUrl: row.getAttribute('data-join-url') || '',
                row: row
            };
        }

        function field(label, value) {
            return '<div class="dior-tele-card-field"><span class="dior-tele-card-label">' +
                esc(label) + '</span><span class="dior-tele-card-value">' + esc(value) + '</span></div>';
        }

        function card(data) {
            var actions = '<button type="button" data-tele-action="view" data-tele-id="' + esc(data.id) +
                '"><i class="fas fa-eye"></i> View Details</button>';
            if (data.joinUrl && !data.statusClass) {
                actions += '<a class="join" href="' + esc(data.joinUrl) +
                    '" target="_blank" rel="noopener"><i class="fas fa-video"></i> Join Call</a>';
            } else if (!data.statusClass) {
                actions += '<button type="button" class="join" data-tele-action="view" data-tele-id="' +
                    esc(data.id) + '"><i class="fas fa-video"></i> Join Call</button>';
            } else {
                actions += '<button type="button" disabled aria-disabled="true"><i class="fas fa-video"></i> Call Unavailable</button>';
            }
            if (!data.statusClass) {
                actions += '<button type="button" class="cancel" data-tele-action="cancel" data-tele-id="' +
                    esc(data.id) + '"><i class="fas fa-xmark"></i> Cancel</button>';
            }
            return '<article class="dior-tele-mobile-card">' +
                '<div class="dior-tele-card-head"><div><div class="dior-tele-card-id">Session ' + esc(data.id) +
                '</div><div class="dior-tele-card-doctor">' + esc(data.doctor) +
                '</div><div class="dior-tele-card-specialty">' + esc(data.specialty) +
                '</div></div><span class="dior-tele-status ' + esc(data.statusClass) + '">' + esc(data.status) +
                '</span></div><div class="dior-tele-card-grid">' +
                field('Date', data.date) + field('Time', data.time) + field('Duration', data.duration) +
                field('Consultation Type', data.type) +
                '</div><div class="dior-tele-card-actions">' + actions + '</div></article>';
        }

        function render() {
            var query = search ? search.value.trim().toLowerCase() : '';
            var all = getRows().map(rowData);
            var filtered = all.filter(function (item) {
                return !query || [item.id, item.doctor, item.specialty, item.date, item.time,
                    item.duration, item.type, item.status].join(' ').toLowerCase().indexOf(query) !== -1;
            });
            var pages = Math.max(1, Math.ceil(filtered.length / pageSize));
            currentPage = Math.min(Math.max(1, currentPage), pages);
            var start = (currentPage - 1) * pageSize;
            var visible = filtered.slice(start, start + pageSize);

            list.innerHTML = visible.length ? visible.map(card).join('') :
                '<div class="dior-tele-mobile-empty"><i class="fas fa-video mb-2"></i><br>' +
                (all.length ? 'No matching telemedicine sessions found.' : 'No telemedicine sessions available.') +
                '</div>';

            pager.innerHTML = filtered.length > pageSize
                ? '<span>Showing ' + (start + 1) + '–' + Math.min(start + pageSize, filtered.length) +
                  ' of ' + filtered.length + ' sessions</span><div class="d-flex align-items-center gap-2">' +
                  '<button type="button" data-page="' + (currentPage - 1) + '" ' +
                  (currentPage === 1 ? 'disabled' : '') + ' aria-label="Previous page"><i class="fas fa-chevron-left"></i></button>' +
                  '<span>' + currentPage + ' / ' + pages + '</span>' +
                  '<button type="button" data-page="' + (currentPage + 1) + '" ' +
                  (currentPage === pages ? 'disabled' : '') + ' aria-label="Next page"><i class="fas fa-chevron-right"></i></button></div>'
                : (filtered.length ? '<span>Showing ' + filtered.length + ' of ' + filtered.length + ' sessions</span>' : '');
        }

        if (search) {
            search.addEventListener('input', function () { currentPage = 1; render(); });
        }

        pager.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-page]');
            if (!button || button.disabled) return;
            currentPage = parseInt(button.getAttribute('data-page'), 10) || 1;
            render();
        });

        list.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-tele-action]');
            if (!button) return;
            var id = button.getAttribute('data-tele-id');
            var row = getRows().find(function (item) {
                return String(item.getAttribute('data-tele-id') || '') === String(id);
            });
            if (!row) return;

            if (button.getAttribute('data-tele-action') === 'view') {
                var viewButton = row.querySelector('.cell-actions .edit-btn');
                if (viewButton) viewButton.click();
            } else if (button.getAttribute('data-tele-action') === 'cancel') {
                var cancelButton = row.querySelector('.cell-actions .delete-btn');
                if (cancelButton) cancelButton.click();
            }
        });

        // Refresh mobile cards when the existing table data changes.
        if (window.MutationObserver) {
            var observer = new MutationObserver(function () {
                if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches) render();
            });
            observer.observe(sourceBody, { childList: true, subtree: true, characterData: true });
        }

        render();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTelemedicineMobile);
    } else {
        initTelemedicineMobile();
    }
    document.addEventListener('dior:patient-tab-changed', initTelemedicineMobile);
})();
