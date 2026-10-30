/**
 * Patient Appointment mobile cards.
 * Cards are rendered from existing live table rows so backend data, actions and filters remain authoritative.
 */
(function () {
  'use strict';

  function initPatientAppointmentMobile() {
    var root = document.getElementById('tab-appointments');
    if (!root || root.dataset.mobileCardsReady === '1') return;
    root.dataset.mobileCardsReady = '1';

    var configs = [
      { id: 'dior-upcoming-appointments-table', labels: ['Doctor', 'Date', 'Time', 'Condition', 'Status', 'Notes'], indexes: [1, 2, 3, 4, 5, 6], statusIndex: 5, actionIndex: 7, checkboxIndex: 0 },
      { id: 'dior-past-appointments-table', labels: ['Doctor', 'Date', 'Time', 'Email', 'Mobile', 'Condition', 'Format', 'Follow-up'], indexes: [1, 2, 3, 4, 5, 6, 7, 8], statusIndex: -1, actionIndex: 9, checkboxIndex: 0 }
    ];

    function cellText(cell) {
      return cell ? String(cell.textContent || '').replace(/\s+/g, ' ').trim() : '';
    }

    function createField(label, source, isStatus) {
      var field = document.createElement('div');
      field.className = 'dior-appt-mobile-field';
      var heading = document.createElement('span');
      heading.className = 'dior-appt-mobile-label';
      heading.textContent = label;
      var value = document.createElement('span');
      value.className = isStatus ? 'dior-appt-mobile-status' : 'dior-appt-mobile-value';
      value.textContent = cellText(source) || '—';
      field.appendChild(heading);
      field.appendChild(value);
      return field;
    }

    function render(config) {
      var table = document.getElementById(config.id);
      if (!table) return;
      var wrapper = table.closest('.table-responsive');
      var tbody = table.tBodies && table.tBodies[0];
      if (!wrapper || !tbody) return;

      var list = wrapper.parentNode.querySelector('.dior-appt-mobile-cards');
      if (!list) {
        list = document.createElement('div');
        list.className = 'dior-appt-mobile-cards d-md-none';
        list.setAttribute('aria-live', 'polite');
        wrapper.insertAdjacentElement('afterend', list);
      }
      list.replaceChildren();

      Array.prototype.forEach.call(tbody.rows, function (row) {
        if (row.classList.contains('dior-empty-state')) {
          var empty = document.createElement('div');
          empty.className = 'dior-appt-mobile-empty';
          empty.textContent = cellText(row.cells[0]) || 'No appointments found.';
          list.appendChild(empty);
          return;
        }
        if (row.hidden || row.style.display === 'none') return;

        var cells = row.cells;
        var card = document.createElement('article');
        card.className = 'dior-appt-mobile-card';

        var head = document.createElement('div');
        head.className = 'dior-appt-mobile-card-head';
        var doctor = document.createElement('div');
        doctor.className = 'dior-appt-mobile-doctor';
        doctor.textContent = cellText(cells[1]) || 'Appointment';
        head.appendChild(doctor);

        var originalCheckbox = cells[config.checkboxIndex] && cells[config.checkboxIndex].querySelector('input[type="checkbox"]');
        if (originalCheckbox) {
          var checkbox = originalCheckbox.cloneNode(true);
          checkbox.classList.remove('upcoming-row-checkbox');
          checkbox.removeAttribute('onclick');
          checkbox.classList.add('dior-appt-mobile-select');
          checkbox.checked = originalCheckbox.checked;
          checkbox.addEventListener('change', function () {
            originalCheckbox.checked = checkbox.checked;
            if (typeof window.diorUpdateUpcomingSelectedCount === 'function') window.diorUpdateUpcomingSelectedCount();
          });
          head.appendChild(checkbox);
        }
        card.appendChild(head);

        var fields = document.createElement('div');
        fields.className = 'dior-appt-mobile-fields';
        config.indexes.forEach(function (index) {
          var cell = cells[index];
          if (!cell) return;
          fields.appendChild(createField(config.labels[config.indexes.indexOf(index)], cell, index === config.statusIndex));
        });
        card.appendChild(fields);

        var actionsCell = cells[config.actionIndex];
        if (actionsCell) {
          var sourceActions = actionsCell.querySelector('.cell-actions') || actionsCell;
          var actions = sourceActions.cloneNode(true);
          actions.classList.add('dior-appt-mobile-actions');
          card.appendChild(actions);
        }
        list.appendChild(card);
      });
    }

    configs.forEach(function (config) {
      var table = document.getElementById(config.id);
      if (!table || !table.tBodies.length) return;
      var tbody = table.tBodies[0];
      var update = function () { render(config); };
      update();

      // Rebuild cards when existing filters, pagination, or row actions update the source table.
      if (window.MutationObserver) {
        var observer = new MutationObserver(update);
        observer.observe(tbody, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
      }
      var panel = table.closest('.dior-subtab-panel');
      if (panel) {
        panel.addEventListener('input', function (event) {
          if (event.target.matches('input[type="search"], input[type="text"]')) window.setTimeout(update, 0);
        });
        panel.addEventListener('click', function () { window.setTimeout(update, 60); });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPatientAppointmentMobile);
  } else {
    initPatientAppointmentMobile();
  }
})();
