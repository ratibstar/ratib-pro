(function () {
  'use strict';

  function boot() {
    var root = document.getElementById('websiteThemeMarketplace');
    if (!root) return;
    var csrf = root.getAttribute('data-csrf') || '';

    function post(url, data) {
      var fd = new FormData();
      fd.append('_csrf', csrf);
      Object.keys(data || {}).forEach(function (k) {
        var v = data[k];
        if (v === undefined || v === null) return;
        fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v);
      });
      return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
        return r.json();
      });
    }

    function bind(sel, urlAttr, extra) {
      Array.prototype.forEach.call(document.querySelectorAll(sel), function (btn) {
        btn.addEventListener('click', function () {
          var data = extra ? extra(btn) : { id: btn.getAttribute('data-id') };
          post(root.getAttribute(urlAttr), data).then(function (res) {
            if (!res || !res.ok) {
              alert((res && (res.message || (res.errors && res.errors.join(', ')))) || 'Failed');
              return;
            }
            if (res.url) window.open(res.url, '_blank', 'noopener');
            if (res.package) {
              var blob = new Blob([JSON.stringify(res.package, null, 2)], { type: 'application/json' });
              var a = document.createElement('a');
              a.href = URL.createObjectURL(blob);
              a.download = 'theme-export.json';
              a.click();
              URL.revokeObjectURL(a.href);
              return;
            }
            location.reload();
          });
        });
      });
    }

    bind('.wb-theme-install', 'data-install-url', function (btn) {
      return { slug: btn.getAttribute('data-slug') };
    });
    bind('.wb-theme-activate', 'data-activate-url');
    bind('.wb-theme-preview', 'data-preview-url');
    bind('.wb-theme-demo', 'data-demo-url');
    bind('.wb-theme-duplicate', 'data-duplicate-url');
    bind('.wb-theme-reset', 'data-reset-url');
    bind('.wb-theme-delete', 'data-delete-url');
    bind('.wb-theme-export', 'data-export-url');
    bind('.wb-theme-backup', 'data-backup-url');

    Array.prototype.forEach.call(document.querySelectorAll('.wb-theme-restore'), function (btn) {
      btn.addEventListener('click', function () {
        post(root.getAttribute('data-restore-url'), { version_id: btn.getAttribute('data-version-id') }).then(function (res) {
          if (!res || !res.ok) alert((res && res.message) || 'Restore failed');
          else location.reload();
        });
      });
    });

    var clearBtn = document.getElementById('wbThemeClearPreview');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        post(root.getAttribute('data-clear-preview-url'), {}).then(function () { location.reload(); });
      });
    }

    var importBtn = document.getElementById('wbThemeImportBtn');
    var importFile = document.getElementById('wbThemeImportFile');
    if (importBtn && importFile) {
      importBtn.addEventListener('click', function () {
        var file = importFile.files && importFile.files[0];
        if (!file) {
          alert('Choose a JSON package');
          return;
        }
        var reader = new FileReader();
        reader.onload = function () {
          post(root.getAttribute('data-import-url'), { package: String(reader.result || '') }).then(function (res) {
            if (!res || !res.ok) alert((res && (res.message || (res.errors && res.errors.join(', ')))) || 'Import failed');
            else location.reload();
          });
        };
        reader.readAsText(file);
      });
    }
  }

  document.addEventListener('DOMContentLoaded', boot);
})();
