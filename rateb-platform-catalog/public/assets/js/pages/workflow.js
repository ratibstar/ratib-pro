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
      document.getElementById('workflowHistory').innerHTML = ui.jsonBlock(history.data);
      document.getElementById('workflowComments').innerHTML = ui.jsonBlock(comments.data);
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
