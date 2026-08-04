(function (document, api, ui) {
  'use strict';

  async function loadQueue() {
    var el = document.getElementById('queueStatus');
    ui.setLoading(el, true);
    try {
      var res = await api.get('/catalog/admin/queue/status');
      el.innerHTML = ui.renderQueuePanel(res.data, { includeRaw: true });
    } catch (error) {
      ui.handleError(error);
      el.innerHTML = '<div class="admin-muted">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadQueue();
    document.addEventListener('admin:page-refresh', loadQueue);

    ui.bindForm(document.getElementById('jobLoadForm'), async function (data) {
      try {
        var job = await api.get('/catalog/jobs/' + encodeURIComponent(data.job_id));
        var items = await api.get('/catalog/jobs/' + encodeURIComponent(data.job_id) + '/items');
        document.getElementById('jobDetail').innerHTML = ui.entityDetail(job.data || {});
        var itemRows = Array.isArray(items.data) ? items.data : [];
        if (itemRows.length) {
          ui.renderTable(document.getElementById('jobItems'), [
            { key: 'status', label: ui.t('field_status', 'Status'), render: function (r) { return ui.statusBadge(r.status || '—'); } },
            { key: 'uuid', label: ui.t('field_uuid', 'UUID'), render: function (r) { return ui.codeCell(r.uuid || r.id || '—'); } }
          ], itemRows);
          document.getElementById('jobItems').innerHTML += ui.rawJsonDetails(items.data);
        } else {
          document.getElementById('jobItems').innerHTML = ui.entityDetail(items.data || {});
        }
      } catch (error) {
        ui.handleError(error);
      }
    });

    document.getElementById('jobReplayBtn').addEventListener('click', async function () {
      var jobId = document.getElementById('jobId').value;
      if (!jobId) {
        return;
      }
      try {
        var res = await api.post('/catalog/admin/jobs/' + encodeURIComponent(jobId) + '/replay', {});
        document.getElementById('jobDetail').innerHTML = ui.entityDetail(res.data || {});
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });

    document.getElementById('jobCancelBtn').addEventListener('click', async function () {
      var jobId = document.getElementById('jobId').value;
      if (!jobId || !window.confirm(ui.t('confirm', 'Are you sure?'))) {
        return;
      }
      try {
        var res = await api.del('/catalog/jobs/' + encodeURIComponent(jobId));
        document.getElementById('jobDetail').innerHTML = ui.entityDetail(res.data || {});
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
