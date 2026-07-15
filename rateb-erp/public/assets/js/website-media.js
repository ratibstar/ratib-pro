(function () {
  'use strict';
  function boot() {
    var root = document.getElementById('websiteMediaRoot');
    if (!root) return;
    var csrf = root.getAttribute('data-csrf') || '';

    var folderForm = document.getElementById('wbFolderForm');
    if (folderForm) {
      folderForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(folderForm);
        fd.append('_csrf', csrf);
        fetch(root.getAttribute('data-folder-url'), { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function () { location.reload(); });
      });
    }

    var uploadForm = document.getElementById('wbUploadForm');
    if (uploadForm) {
      uploadForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(uploadForm);
        fd.append('_csrf', csrf);
        var params = new URLSearchParams(location.search);
        if (params.get('folder')) fd.append('folder_id', params.get('folder'));
        fetch(root.getAttribute('data-upload-url'), { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (!res || !res.ok) alert((res && res.error) || 'Upload failed');
            else location.reload();
          });
      });
    }
  }
  document.addEventListener('DOMContentLoaded', boot);
})();
