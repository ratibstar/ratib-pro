/**
 * Phase WEBSITE-07 — Portal interactions.
 */
(function () {
  'use strict';
  document.querySelectorAll('[data-portal-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!window.confirm(el.getAttribute('data-portal-confirm') || 'Confirm?')) {
        e.preventDefault();
      }
    });
  });
})();
