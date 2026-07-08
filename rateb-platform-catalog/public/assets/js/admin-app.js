(function (document) {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.getElementById('adminSidebar');
    var toggle = document.getElementById('adminSidebarToggle');
    var overlay = document.createElement('div');
    overlay.className = 'admin-overlay';
    overlay.id = 'adminSidebarOverlay';
    document.body.appendChild(overlay);

    function closeSidebar() {
      if (!sidebar) {
        return;
      }
      sidebar.classList.remove('is-open');
      overlay.classList.remove('is-open');
    }

    function openSidebar() {
      if (!sidebar) {
        return;
      }
      sidebar.classList.add('is-open');
      overlay.classList.add('is-open');
    }

    if (toggle) {
      toggle.addEventListener('click', function () {
        if (sidebar && sidebar.classList.contains('is-open')) {
          closeSidebar();
        } else {
          openSidebar();
        }
      });
    }

    overlay.addEventListener('click', closeSidebar);

    document.querySelectorAll('[data-admin-refresh]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var eventName = btn.getAttribute('data-admin-refresh');
        document.dispatchEvent(new CustomEvent(eventName || 'admin:refresh'));
      });
    });
  });
})(document);
