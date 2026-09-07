(function () {
  'use strict';

  function boot() {
    var table = document.querySelector('[data-journal-lines-table]');
    if (!table || table.getAttribute('data-jl-bound') === '1') {
      return;
    }
    table.setAttribute('data-jl-bound', '1');
    var form = table.closest('form');
    var addBtn = document.querySelector('[data-journal-lines-add]');
    var tbody = table.querySelector('tbody');
    var bar = document.querySelector('[data-journal-balance]');
    var debitEl = document.querySelector('[data-journal-total-debit]');
    var creditEl = document.querySelector('[data-journal-total-credit]');
    var diffEl = document.querySelector('[data-journal-diff]');
    var alertEl = document.querySelector('[data-journal-unbalanced-alert]');
    var okEl = document.querySelector('[data-journal-balance-ok]');

    function num(el) {
      var n = parseFloat((el && el.value) || '0');
      return isNaN(n) ? 0 : n;
    }

    function fmt(n) {
      return n.toFixed(2);
    }

    function totals() {
      var debit = 0;
      var credit = 0;
      tbody.querySelectorAll('[data-journal-lines-row]').forEach(function (row) {
        debit += num(row.querySelector('input[name="line_debit[]"]'));
        credit += num(row.querySelector('input[name="line_credit[]"]'));
      });
      return { debit: debit, credit: credit, diff: Math.abs(debit - credit) };
    }

    function refresh() {
      var t = totals();
      if (debitEl) debitEl.textContent = fmt(t.debit);
      if (creditEl) creditEl.textContent = fmt(t.credit);
      if (diffEl) diffEl.textContent = fmt(t.diff);
      var unbalanced = t.diff > 0.009 || (t.debit <= 0 && t.credit <= 0);
      if (bar) {
        bar.classList.toggle('is-unbalanced', unbalanced);
        bar.classList.toggle('is-balanced', !unbalanced);
      }
      if (alertEl) {
        alertEl.classList.toggle('d-none', !unbalanced);
      }
      if (okEl) {
        okEl.classList.toggle('d-none', unbalanced);
      }
      return !unbalanced;
    }

    addBtn && addBtn.addEventListener('click', function () {
      var row = tbody.querySelector('[data-journal-lines-row]');
      if (!row) return;
      tbody.appendChild(row.cloneNode(true));
      var last = tbody.lastElementChild;
      last.querySelectorAll('input').forEach(function (el) { el.value = ''; });
      last.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
      refresh();
    });
    tbody.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-journal-lines-remove]');
      if (!btn) return;
      if (tbody.querySelectorAll('[data-journal-lines-row]').length <= 1) return;
      btn.closest('tr').remove();
      refresh();
    });
    tbody.addEventListener('input', refresh);
    tbody.addEventListener('change', refresh);
    if (form) {
      form.addEventListener('submit', function (e) {
        if (!refresh()) {
          e.preventDefault();
          if (alertEl) {
            alertEl.classList.remove('d-none');
            try { alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e1) { /* ignore */ }
          }
        }
      });
    }
    refresh();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  document.addEventListener('rateb:nav:afterEnter', boot);
})();
