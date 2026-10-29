/* Dior Medical: mobile card labels for existing data tables; preserve original DOM/actions. */
(function () {
  'use strict';
  function clean(text) { return (text || '').replace(/\s+/g, ' ').replace(/[↕↑↓⇅]/g, '').trim(); }
  function prepare(table) {
    if (!table || table.closest('.modal')) return;
    if (table.id === 'dior-doc-cal-grid-table' || table.querySelector('#dior-doc-cal-grid-body')) return;
    var headerRow = table.querySelector('thead tr:last-child');
    if (!headerRow) return;
    var headers = Array.prototype.slice.call(headerRow.querySelectorAll('th')).map(function (th) { return clean(th.textContent); });
    if (!headers.length || !table.querySelector('tbody tr')) return;
    table.dataset.diorMobileCards = '1';
    table.classList.add('dior-mobile-card-table');
    table.querySelectorAll('tbody tr').forEach(function (row) {
      var cells = Array.prototype.slice.call(row.children).filter(function (cell) { return cell.tagName === 'TD'; });
      if (cells.length === 1 && (cells[0].hasAttribute('colspan') || /no .*found|no .*available|no records|no appointments|no prescriptions/i.test(cells[0].textContent))) {
        row.classList.add('dior-mobile-empty-row');
        return;
      }
      cells.forEach(function (cell, index) {
        var label = headers[index] || (index === cells.length - 1 ? 'Actions' : 'Details');
        if (index === cells.length - 1 && !label) label = 'Actions';
        cell.setAttribute('data-mobile-label', label);
      });
    });
  }
  function init() { document.querySelectorAll('.dior-tab-panel table').forEach(prepare); }
  function boot() {
    init();
    var observer = new MutationObserver(function () { init(); });
    observer.observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('click', function (event) {
      if (event.target.closest('.dior-subtab-pill,[data-patient-tab],[data-doc-tab],[data-tab]')) window.setTimeout(init, 100);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();