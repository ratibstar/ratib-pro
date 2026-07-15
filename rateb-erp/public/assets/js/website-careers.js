/**
 * Phase WEBSITE-06 — Career portal interactions (lazy load placeholder).
 */
(function () {
  'use strict';
  var grids = document.querySelectorAll('[data-career-lazy-grid]');
  grids.forEach(function (grid) {
    grid.setAttribute('data-career-lazy-ready', '1');
  });
  var searchForms = document.querySelectorAll('.rateb-career-search');
  searchForms.forEach(function (form) {
    var input = form.querySelector('input[type="search"]');
    if (!input) return;
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && input.value.trim() === '') {
        e.preventDefault();
      }
    });
  });
})();
