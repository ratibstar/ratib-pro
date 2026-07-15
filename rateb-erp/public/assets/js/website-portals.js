/**
 * Phase WEBSITE-07/08/09 — Portal interactions + online services.
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
  var services = document.querySelector('[data-online-services]');
  if (services) {
    services.setAttribute('data-services-ready', '1');
  }
  var wizard = document.querySelector('[data-require-agreement]');
  if (wizard) {
    wizard.addEventListener('submit', function (e) {
      var box = wizard.querySelector('[data-agreement-check]');
      if (box && !box.checked) {
        e.preventDefault();
        window.alert('Please accept the digital agreement.');
      }
    });
  }
  var calendar = document.querySelector('[data-appointment-calendar]');
  if (calendar) {
    calendar.setAttribute('data-calendar-ready', '1');
  }
})();
