(function (document) {
  'use strict';

  var STORAGE_GROUPS = 'rateb-catalog-admin-nav-groups';
  var STORAGE_COLLAPSED = 'rateb-catalog-admin-sidebar-collapsed';

  document.addEventListener('DOMContentLoaded', function () {
    var shell = document.querySelector('.admin-shell');
    var sidebar = document.getElementById('adminSidebar');
    var toggle = document.getElementById('adminSidebarToggle');
    var collapseBtn = document.getElementById('adminSidebarCollapse');
    var overlay = document.createElement('div');
    overlay.className = 'admin-overlay';
    overlay.id = 'adminSidebarOverlay';
    document.body.appendChild(overlay);

    function readGroupsState() {
      try {
        return JSON.parse(localStorage.getItem(STORAGE_GROUPS) || '{}');
      } catch (e) {
        return {};
      }
    }

    function writeGroupsState(state) {
      try {
        localStorage.setItem(STORAGE_GROUPS, JSON.stringify(state));
      } catch (e) {
        /* ignore */
      }
    }

    function setGroupOpen(groupEl, open) {
      if (!groupEl) {
        return;
      }
      groupEl.classList.toggle('is-open', open);
      var btn = groupEl.querySelector('[data-nav-group-toggle]');
      if (btn) {
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
    }

    function initNavGroups() {
      var saved = readGroupsState();
      document.querySelectorAll('[data-nav-group]').forEach(function (groupEl) {
        var key = groupEl.getAttribute('data-nav-group') || '';
        var hasActive = !!groupEl.querySelector('.admin-nav-link.is-active');
        var open = hasActive || saved[key] === true;
        if (saved[key] === false && !hasActive) {
          open = false;
        }
        setGroupOpen(groupEl, open);

        var btn = groupEl.querySelector('[data-nav-group-toggle]');
        if (!btn) {
          return;
        }
        btn.addEventListener('click', function () {
          if (shell && shell.classList.contains('is-sidebar-collapsed')) {
            return;
          }
          var next = !groupEl.classList.contains('is-open');
          setGroupOpen(groupEl, next);
          saved[key] = next;
          writeGroupsState(saved);
        });
      });
    }

    function closeMobileSidebar() {
      if (!sidebar) {
        return;
      }
      sidebar.classList.remove('is-open');
      overlay.classList.remove('is-open');
    }

    function openMobileSidebar() {
      if (!sidebar) {
        return;
      }
      sidebar.classList.add('is-open');
      overlay.classList.add('is-open');
    }

    function setSidebarCollapsed(collapsed) {
      if (!shell) {
        return;
      }
      shell.classList.toggle('is-sidebar-collapsed', collapsed);
      try {
        localStorage.setItem(STORAGE_COLLAPSED, collapsed ? '1' : '0');
      } catch (e) {
        /* ignore */
      }
      if (collapseBtn) {
        collapseBtn.innerHTML = collapsed
          ? '<i class="bi bi-layout-sidebar"></i>'
          : '<i class="bi bi-layout-sidebar-inset"></i>';
        collapseBtn.title = collapsed
          ? (window.RatebAdminConfig && RatebAdminConfig.i18n && RatebAdminConfig.i18n.expand_sidebar) || 'Expand sidebar'
          : (window.RatebAdminConfig && RatebAdminConfig.i18n && RatebAdminConfig.i18n.collapse_sidebar) || 'Collapse sidebar';
      }
    }

    if (toggle) {
      toggle.addEventListener('click', function () {
        if (sidebar && sidebar.classList.contains('is-open')) {
          closeMobileSidebar();
        } else {
          openMobileSidebar();
        }
      });
    }

    if (collapseBtn) {
      collapseBtn.addEventListener('click', function () {
        setSidebarCollapsed(!(shell && shell.classList.contains('is-sidebar-collapsed')));
      });
    }

    overlay.addEventListener('click', closeMobileSidebar);

    try {
      setSidebarCollapsed(localStorage.getItem(STORAGE_COLLAPSED) === '1');
    } catch (e) {
      setSidebarCollapsed(false);
    }

    initNavGroups();

    document.querySelectorAll('[data-admin-refresh]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var eventName = btn.getAttribute('data-admin-refresh');
        document.dispatchEvent(new CustomEvent(eventName || 'admin:refresh'));
      });
    });
  });
})(document);
