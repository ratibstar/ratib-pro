(function (document, api, ui) {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    ui.bindForm(document.getElementById('erpSyncForm'), async function (data) {
      try {
        var res = await api.get('/catalog/sync/' + encodeURIComponent(data.company_id), {
          since: data.since || undefined,
          limit: data.limit || undefined
        });
        document.getElementById('erpSyncResult').innerHTML = ui.jsonBlock(res.data);
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
