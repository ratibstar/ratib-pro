(function (document, api, ui) {
  'use strict';

  async function loadRoles() {
    var el = document.getElementById('rolesList');
    ui.setLoading(el, true);
    try {
      var res = await api.get('/catalog/admin/roles');
      ui.renderTable(el, [
        { key: 'name', label: 'Name', render: function (r) { return ui.escapeHtml(r.name || r.code || '—'); } },
        { key: 'uuid', label: 'UUID', render: function (r) { return ui.codeCell(r.uuid); } },
        { key: 'is_active', label: 'Active', render: function (r) { return ui.escapeHtml(String(r.is_active != null ? r.is_active : '—')); } }
      ], Array.isArray(res.data) ? res.data : [], {
        onRowClick: function (row) {
          document.getElementById('completenessForm').hidden = true;
          document.getElementById('userRolesResult').innerHTML = ui.jsonBlock(row);
        }
      });
    } catch (error) {
      el.innerHTML = '<div class="admin-muted">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  async function loadCompleteness() {
    var el = document.getElementById('completenessRules');
    ui.setLoading(el, true);
    try {
      var res = await api.get('/catalog/admin/completeness-rules');
      var items = Array.isArray(res.data) ? res.data : [];
      ui.renderTable(el, [
        { key: 'code', label: 'Code', render: function (r) { return ui.escapeHtml(r.code); } },
        { key: 'weight', label: 'Weight', render: function (r) { return ui.escapeHtml(String(r.weight != null ? r.weight : '—')); } },
        { key: 'is_active', label: 'Active', render: function (r) { return ui.escapeHtml(String(r.is_active != null ? r.is_active : '—')); } }
      ], items, {
        onRowClick: function (row) {
          document.getElementById('completenessForm').hidden = false;
          document.getElementById('crCode').value = row.code || '';
          document.getElementById('crWeight').value = row.weight != null ? row.weight : '';
          document.getElementById('crActive').value = row.is_active ? '1' : '0';
        }
      });
    } catch (error) {
      el.innerHTML = '<div class="admin-muted">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadRoles();
    loadCompleteness();
    document.addEventListener('admin:page-refresh', function () {
      loadRoles();
      loadCompleteness();
    });

    document.getElementById('loadUserRolesBtn').addEventListener('click', async function () {
      var uuid = document.getElementById('settingsUserUuid').value;
      if (!uuid) {
        return;
      }
      try {
        var res = await api.get('/catalog/admin/users/' + encodeURIComponent(uuid) + '/roles');
        document.getElementById('userRolesResult').innerHTML = ui.jsonBlock(res.data);
        var roles = (res.data && Array.isArray(res.data.roles)) ? res.data.roles : (Array.isArray(res.data) ? res.data : []);
        document.getElementById('settingsRoleUuids').value = roles.map(function (r) { return r.uuid || r.role_uuid; }).filter(Boolean).join(',');
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('userRolesForm'), async function (data) {
      try {
        var roleUuids = String(data.role_uuids || '').split(',').map(function (v) { return v.trim(); }).filter(Boolean);
        await api.put('/catalog/admin/users/' + encodeURIComponent(data.user_uuid) + '/roles', { role_uuids: roleUuids });
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('completenessForm'), async function (data) {
      try {
        await api.put('/catalog/admin/completeness-rules/' + encodeURIComponent(data.code), {
          weight: Number(data.weight),
          is_active: data.is_active === '1'
        });
        ui.flash(ui.t('success', 'Success'), 'success');
        loadCompleteness();
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
