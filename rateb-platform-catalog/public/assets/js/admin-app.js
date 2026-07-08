(function (document) {
  'use strict';

  var STORAGE_OPEN_GROUP = 'rateb-catalog-admin-nav-open-group';
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

    function isMobileNav() {
      return window.matchMedia('(max-width: 991.98px)').matches;
    }

    function readOpenGroup() {
      try {
        return localStorage.getItem(STORAGE_OPEN_GROUP) || '';
      } catch (e) {
        return '';
      }
    }

    function writeOpenGroup(key) {
      try {
        if (key) {
          localStorage.setItem(STORAGE_OPEN_GROUP, key);
        } else {
          localStorage.removeItem(STORAGE_OPEN_GROUP);
        }
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

    function closeAllGroupsExcept(exceptEl) {
      document.querySelectorAll('[data-nav-group]').forEach(function (groupEl) {
        if (groupEl !== exceptEl) {
          setGroupOpen(groupEl, false);
        }
      });
    }

    function initNavGroups() {
      var groups = document.querySelectorAll('[data-nav-group]');
      var savedKey = readOpenGroup();
      var activeGroup = null;

      groups.forEach(function (groupEl) {
        if (groupEl.querySelector('.admin-nav-link.is-active')) {
          activeGroup = groupEl;
        }
      });

      groups.forEach(function (groupEl) {
        var key = groupEl.getAttribute('data-nav-group') || '';
        var open = activeGroup
          ? groupEl === activeGroup
          : (savedKey !== '' && key === savedKey);
        setGroupOpen(groupEl, open);

        var btn = groupEl.querySelector('[data-nav-group-toggle]');
        if (!btn) {
          return;
        }
        btn.addEventListener('click', function () {
          if (shell && shell.classList.contains('is-sidebar-collapsed') && !isMobileNav()) {
            return;
          }
          var willOpen = !groupEl.classList.contains('is-open');
          closeAllGroupsExcept(willOpen ? groupEl : null);
          setGroupOpen(groupEl, willOpen);
          writeOpenGroup(willOpen ? key : '');
        });
      });
    }

    function closeMobileSidebar() {
      if (!sidebar) {
        return;
      }
      sidebar.classList.remove('is-open');
      overlay.classList.remove('is-open');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
      }
    }

    function openMobileSidebar() {
      if (!sidebar) {
        return;
      }
      sidebar.classList.add('is-open');
      overlay.classList.add('is-open');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'true');
      }
    }

    function setSidebarCollapsed(collapsed) {
      if (!shell) {
        return;
      }
      if (isMobileNav()) {
        shell.classList.remove('is-sidebar-collapsed');
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
        if (isMobileNav()) {
          if (sidebar && sidebar.classList.contains('is-open')) {
            closeMobileSidebar();
          } else {
            openMobileSidebar();
          }
          return;
        }
        setSidebarCollapsed(!(shell && shell.classList.contains('is-sidebar-collapsed')));
      });
    }

    if (collapseBtn) {
      collapseBtn.addEventListener('click', function () {
        setSidebarCollapsed(!(shell && shell.classList.contains('is-sidebar-collapsed')));
      });
    }

    overlay.addEventListener('click', closeMobileSidebar);

    window.addEventListener('resize', function () {
      if (!isMobileNav()) {
        closeMobileSidebar();
      }
      try {
        setSidebarCollapsed(localStorage.getItem(STORAGE_COLLAPSED) === '1');
      } catch (e) {
        setSidebarCollapsed(false);
      }
    });

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
