/**
 * Phase WEBSITE-07/08 — Portal interactions + lazy workspace hints.
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
  var pipeline = document.querySelector('[data-lazy-pipeline]');
  if (pipeline) {
    pipeline.setAttribute('data-pipeline-ready', '1');
  }
  var kpis = document.querySelector('[data-workspace-kpis]');
  if (kpis) {
    kpis.setAttribute('data-kpis-ready', '1');
  }
})();
