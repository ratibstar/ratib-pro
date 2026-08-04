(function (document, api, ui) {
  'use strict';

  var productUuid = '';

  document.addEventListener('DOMContentLoaded', function () {
    ui.bindForm(document.getElementById('versionsLoadForm'), async function (data) {
      productUuid = data.product_uuid;
      try {
        var res = await api.get('/catalog/products/' + encodeURIComponent(productUuid) + '/versions');
        var items = Array.isArray(res.data) ? res.data : [];
        document.getElementById('versionsCompareForm').hidden = false;
        ui.renderTable(document.getElementById('versionsList'), [
          { key: 'version', label: ui.t('field_version', 'Version'), render: function (r) { return ui.escapeHtml(String(r.version_number || r.version || '—')); } },
          { key: 'created_at', label: ui.t('field_created_at', 'Created'), render: function (r) { return ui.escapeHtml(r.created_at || '—'); } },
          { key: 'actions', label: ui.t('actions', 'Actions'), render: function (r) {
            var v = r.version_number || r.version;
            return '<button type="button" class="btn btn-sm btn-outline-secondary" data-ver="' + ui.escapeHtml(String(v)) + '">' + ui.escapeHtml(ui.t('details', 'View')) + '</button> ' +
              '<button type="button" class="btn btn-sm btn-outline-primary" data-restore="' + ui.escapeHtml(String(v)) + '">' + ui.escapeHtml(ui.t('refresh', 'Restore')) + '</button>';
          } }
        ], items);

        document.querySelectorAll('[data-ver]').forEach(function (btn) {
          btn.addEventListener('click', async function () {
            try {
              var detail = await api.get('/catalog/products/' + encodeURIComponent(productUuid) + '/versions/' + encodeURIComponent(btn.getAttribute('data-ver')));
              document.getElementById('versionsDetail').innerHTML = ui.entityDetail(detail.data || {});
            } catch (error) {
              ui.handleError(error);
            }
          });
        });
        document.querySelectorAll('[data-restore]').forEach(function (btn) {
          btn.addEventListener('click', async function () {
            if (!window.confirm(ui.t('confirm', 'Are you sure?'))) {
              return;
            }
            try {
              await api.post('/catalog/products/' + encodeURIComponent(productUuid) + '/versions/' + encodeURIComponent(btn.getAttribute('data-restore')) + '/restore', {});
              ui.flash(ui.t('success', 'Success'), 'success');
            } catch (error) {
              ui.handleError(error);
            }
          });
        });
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('versionsCompareForm'), async function (data) {
      try {
        var res = await api.get('/catalog/products/' + encodeURIComponent(productUuid) + '/versions/compare', {
          left: data.left,
          right: data.right
        });
        document.getElementById('versionsDetail').innerHTML = ui.entityDetail(res.data || {});
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
