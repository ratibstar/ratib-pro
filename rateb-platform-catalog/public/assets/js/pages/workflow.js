(function (document, api, ui) {
  'use strict';

  var productUuid = '';

  async function refresh() {
    if (!productUuid) {
      return;
    }
    try {
      var history = await api.get('/catalog/products/' + encodeURIComponent(productUuid) + '/workflow/history');
      var comments = await api.get('/catalog/products/' + encodeURIComponent(productUuid) + '/workflow/comments');
      var historyItems = Array.isArray(history.data) ? history.data : [];
      var commentItems = Array.isArray(comments.data) ? comments.data : [];
      if (historyItems.length) {
        ui.renderTable(document.getElementById('workflowHistory'), [
          { key: 'status', label: ui.t('field_status', 'Status'), render: function (r) { return ui.statusBadge(r.status || r.to_status || '—'); } },
          { key: 'created_at', label: ui.t('field_created_at', 'Created'), render: function (r) { return ui.escapeHtml(r.created_at || r.at || '—'); } },
          { key: 'actor', label: ui.t('field_name', 'Name'), render: function (r) { return ui.escapeHtml(r.actor || r.user || r.action || '—'); } }
        ], historyItems);
        document.getElementById('workflowHistory').innerHTML += ui.rawJsonDetails(history.data);
      } else {
        document.getElementById('workflowHistory').innerHTML = ui.entityDetail(history.data || {});
      }
      if (commentItems.length) {
        ui.renderTable(document.getElementById('workflowComments'), [
          { key: 'body', label: ui.t('field_description', 'Description'), render: function (r) { return ui.escapeHtml(r.body || r.comment || '—'); } },
          { key: 'created_at', label: ui.t('field_created_at', 'Created'), render: function (r) { return ui.escapeHtml(r.created_at || '—'); } }
        ], commentItems);
        document.getElementById('workflowComments').innerHTML += ui.rawJsonDetails(comments.data);
      } else {
        document.getElementById('workflowComments').innerHTML = ui.entityDetail(comments.data || {});
      }
      document.getElementById('workflowActions').hidden = false;
      document.getElementById('workflowCommentForm').hidden = false;
    } catch (error) {
      ui.handleError(error);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    ui.bindForm(document.getElementById('workflowLoadForm'), async function (data) {
      productUuid = data.product_uuid;
      await refresh();
    });

    document.querySelectorAll('[data-wf]').forEach(function (btn) {
      btn.addEventListener('click', async function () {
        if (!productUuid) {
          return;
        }
        try {
          await api.post('/catalog/products/' + encodeURIComponent(productUuid) + '/workflow/' + btn.getAttribute('data-wf'), {});
          ui.flash(ui.t('success', 'Success'), 'success');
          refresh();
        } catch (error) {
          ui.handleError(error);
        }
      });
    });

    ui.bindForm(document.getElementById('workflowCommentForm'), async function (data) {
      try {
        await api.post('/catalog/products/' + encodeURIComponent(productUuid) + '/workflow/comments', { body: data.body });
        ui.flash(ui.t('success', 'Success'), 'success');
        refresh();
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
