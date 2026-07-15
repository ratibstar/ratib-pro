(function () {
  'use strict';
  function boot() {
    var root = document.getElementById('websiteMenusRoot');
    if (!root) return;
    var csrf = root.getAttribute('data-csrf') || '';
    var menuId = root.getAttribute('data-menu-id') || '0';

    var addBtn = document.getElementById('wbMenuAdd');
    var list = document.getElementById('wbMenuItems');
    if (addBtn && list) {
      addBtn.addEventListener('click', function () {
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2 wb-menu-row';
        row.setAttribute('data-key', 'n' + Date.now());
        row.innerHTML = '<div class="col-md-3"><input class="form-control form-control-sm" data-field="label_en" placeholder="Label EN"></div>' +
          '<div class="col-md-3"><input class="form-control form-control-sm" data-field="label_ar" placeholder="Label AR"></div>' +
          '<div class="col-md-3"><input class="form-control form-control-sm" data-field="url" placeholder="URL"></div>' +
          '<div class="col-md-2"><input class="form-control form-control-sm" data-field="parent_key" placeholder="Parent key"></div>' +
          '<div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger wb-menu-remove">×</button></div>';
        list.appendChild(row);
      });
      list.addEventListener('click', function (e) {
        if (e.target.classList.contains('wb-menu-remove')) {
          var row = e.target.closest('.wb-menu-row');
          if (row) row.remove();
        }
      });
    }

    var save = document.getElementById('wbMenuSave');
    if (save) {
      save.addEventListener('click', function () {
        var items = [];
        Array.prototype.forEach.call(document.querySelectorAll('.wb-menu-row'), function (row, i) {
          items.push({
            _key: row.getAttribute('data-key') || ('k' + i),
            label_en: (row.querySelector('[data-field="label_en"]') || {}).value || '',
            label_ar: (row.querySelector('[data-field="label_ar"]') || {}).value || '',
            url: (row.querySelector('[data-field="url"]') || {}).value || '#',
            parent_key: (row.querySelector('[data-field="parent_key"]') || {}).value || '',
            sort_order: i
          });
        });
        var fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('menu_id', menuId);
        fd.append('items', JSON.stringify(items));
        fetch(root.getAttribute('data-save-url'), { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function () { location.reload(); });
      });
    }

    var footerSave = document.getElementById('wbFooterSave');
    if (footerSave) {
      footerSave.addEventListener('click', function () {
        var columns = [];
        Array.prototype.forEach.call(document.querySelectorAll('.wb-footer-row'), function (row, i) {
          var linksRaw = (row.querySelector('[data-field="links_json"]') || {}).value || '[]';
          var links;
          try { links = JSON.parse(linksRaw); } catch (err) { links = []; }
          columns.push({
            title_en: (row.querySelector('[data-field="title_en"]') || {}).value || '',
            title_ar: (row.querySelector('[data-field="title_ar"]') || {}).value || '',
            links: links,
            sort_order: i
          });
        });
        var fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('columns', JSON.stringify(columns));
        fetch(root.getAttribute('data-footer-url'), { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function () { location.reload(); });
      });
    }
  }
  document.addEventListener('DOMContentLoaded', boot);
})();
