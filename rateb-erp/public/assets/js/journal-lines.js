(function () {
  'use strict';

  if (window.__RATEB_JOURNAL_LINES__) {
    try { window.ratebJournalLinesRefresh && window.ratebJournalLinesRefresh(); } catch (e0) { /* ignore */ }
    return;
  }
  window.__RATEB_JOURNAL_LINES__ = 1;

  var DIGITS = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9' };

  function num(el) {
    var raw = el ? String(el.value || '') : '';
    raw = raw.replace(/[٠-٩]/g, function (d) { return DIGITS[d] || d; }).replace(/,/g, '').trim();
    var n = parseFloat(raw);
    return isNaN(n) ? 0 : n;
  }

  function fmt(n) {
    return (Math.round(n * 100) / 100).toFixed(2);
  }

  function table() {
    return document.querySelector('[data-journal-lines-table]');
  }

  function refresh() {
    var tbl = table();
    var debitEl = document.querySelector('[data-journal-total-debit]');
    var creditEl = document.querySelector('[data-journal-total-credit]');
    var diffEl = document.querySelector('[data-journal-diff]');
    var alertEl = document.querySelector('[data-journal-unbalanced-alert]');
    var okEl = document.querySelector('[data-journal-balance-ok]');
    var bar = document.querySelector('[data-journal-balance]');
    var debit = 0;
    var credit = 0;
    if (tbl) {
      tbl.querySelectorAll('[data-journal-lines-row]').forEach(function (row) {
        debit += num(row.querySelector('input[name="line_debit[]"]'));
        credit += num(row.querySelector('input[name="line_credit[]"]'));
      });
    }
    var diff = Math.abs(debit - credit);
    if (debitEl) debitEl.textContent = fmt(debit);
    if (creditEl) creditEl.textContent = fmt(credit);
    if (diffEl) diffEl.textContent = fmt(diff);
    var unbalanced = diff > 0.009;
    if (bar) {
      bar.classList.toggle('is-unbalanced', unbalanced);
      bar.classList.toggle('is-balanced', !unbalanced && (debit > 0 || credit > 0));
    }
    if (alertEl) {
      if (unbalanced) {
        alertEl.hidden = false;
        alertEl.classList.remove('d-none');
      } else {
        alertEl.hidden = true;
        alertEl.classList.add('d-none');
      }
    }
    if (okEl) {
      okEl.classList.toggle('d-none', unbalanced);
    }
    return !unbalanced && (debit > 0 || credit > 0);
  }

  window.ratebJournalLinesRefresh = refresh;

  function addRow() {
    var tbl = table();
    if (!tbl) return;
    var tbody = tbl.querySelector('tbody');
    var row = tbody && tbody.querySelector('[data-journal-lines-row]');
    if (!row) return;
    var clone = row.cloneNode(true);
    clone.querySelectorAll('input').forEach(function (el) { el.value = ''; });
    clone.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
    tbody.appendChild(clone);
    refresh();
  }

  document.addEventListener('click', function (e) {
    var addBtn = e.target.closest('[data-journal-lines-add]');
    if (addBtn) {
      e.preventDefault();
      e.stopPropagation();
      addRow();
      return;
    }
    var rm = e.target.closest('[data-journal-lines-remove]');
    if (!rm) return;
    var tbl = table();
    if (!tbl) return;
    var rows = tbl.querySelectorAll('[data-journal-lines-row]');
    if (rows.length <= 1) return;
    var tr = rm.closest('[data-journal-lines-row]');
    if (tr) tr.remove();
    refresh();
  }, true);

  document.addEventListener('input', function (e) {
    if (e.target && e.target.closest && e.target.closest('[data-journal-lines-table]')) {
      refresh();
    }
  }, true);

  document.addEventListener('change', function (e) {
    if (e.target && e.target.closest && e.target.closest('[data-journal-lines-table]')) {
      refresh();
    }
  }, true);

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.querySelector || !form.querySelector('[data-journal-lines-table]')) {
      return;
    }
    if (!refresh()) {
      e.preventDefault();
      var alertEl = document.querySelector('[data-journal-unbalanced-alert]');
      if (alertEl) {
        alertEl.hidden = false;
        alertEl.classList.remove('d-none');
        try { alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e1) { /* ignore */ }
      }
    }
  }, true);

  function kick() {
    refresh();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', kick);
  } else {
    kick();
  }
  document.addEventListener('rateb:nav:afterEnter', kick);
})();
