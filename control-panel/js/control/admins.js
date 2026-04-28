/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/admins.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/admins.js`.
 */
(function() {
    const config = document.getElementById('control-config');
    const API_BASE = (config && config.getAttribute('data-api-base')) || '';
    const tableBody = document.getElementById('tableBody');
    if (!tableBody) return;

    var alertModalInstance = null;
    function showAlert(msg) {
        const el = document.getElementById('alertMessage');
        const modalEl = document.getElementById('alertModal');
        if (el) el.textContent = msg;
        if (modalEl && typeof bootstrap !== 'undefined') {
            if (!alertModalInstance) alertModalInstance = new bootstrap.Modal(modalEl);
            alertModalInstance.show();
        }
    }

    function showConfirm(msg) {
        return Promise.resolve(window.confirm(msg));
    }
    function cleanupBackdrops() {
        if (document.querySelector('.modal.show')) return;
        document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    }

    const selectAll = document.getElementById('selectAll');
    const btnBulkDelete = document.getElementById('btnBulkDelete');

    function apiCall(method, body) {
        const url = API_BASE + '/admins-api.php';
        const opts = { method: method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
        if (body && (method === 'POST' || method === 'PUT' || method === 'DELETE')) opts.body = JSON.stringify(body);
        return fetch(url, opts).then(function(r) {
            const ct = r.headers.get('content-type');
            if (!ct || !ct.includes('application/json')) return r.text().then(function(t) { throw new Error('API error: ' + (t || r.status)); });
            return r.json();
        });
    }

    function updateBulkState() {
        const checked = document.querySelectorAll('.row-check:checked');
        if (btnBulkDelete) btnBulkDelete.disabled = !checked.length;
    }

    tableBody.addEventListener('change', function(e) { if (e.target.matches('.row-check')) updateBulkState(); });
    document.addEventListener('change', function(e) { if (e.target && e.target.matches && e.target.matches('.row-check')) updateBulkState(); });
    if (selectAll) selectAll.addEventListener('change', function() { document.querySelectorAll('.row-check').forEach(function(c) { c.checked = selectAll.checked; }); updateBulkState(); });

    var pageLimitSelect = document.getElementById('pageLimitSelect');
    if (pageLimitSelect) pageLimitSelect.addEventListener('change', function() { if (this.form) this.form.submit(); });

    const modalEl = document.getElementById('editModal');
    const modal = (modalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(modalEl) : null;

    var btnAdd = document.getElementById('btnAdd');
    if (btnAdd) btnAdd.onclick = function() {
        document.getElementById('editId').value = '';
        document.getElementById('editUsername').value = '';
        document.getElementById('editPassword').value = '';
        document.getElementById('editFullName').value = '';
        var countrySel = document.getElementById('editCountryId');
        if (countrySel) countrySel.value = '';
        document.getElementById('editIsActive').value = '1';
        document.getElementById('modalTitle').textContent = 'Add Admin';
        document.getElementById('passwordLabel').textContent = 'Password *';
        document.getElementById('passwordHint').textContent = 'Required. Min 4 characters.';
        document.getElementById('editPassword').required = true;
        if (modal) modal.show();
    };

    tableBody.addEventListener('click', function(e) {
        if (e.target.closest('.btn-delete')) return;
        if (e.target.closest('.btn-edit')) {
            var b = e.target.closest('.btn-edit');
            document.getElementById('editId').value = b.dataset.id || '';
            document.getElementById('editUsername').value = b.dataset.username || '';
            document.getElementById('editPassword').value = '';
            document.getElementById('editFullName').value = b.dataset.fullname || '';
            var countrySelEdit = document.getElementById('editCountryId');
            if (countrySelEdit) {
                var cid = b.getAttribute('data-country-id') || '';
                countrySelEdit.value = cid && parseInt(cid, 10) > 0 ? String(parseInt(cid, 10)) : '';
            }
            document.getElementById('editIsActive').value = String(b.dataset.active !== undefined ? b.dataset.active : '1');
            document.getElementById('modalTitle').textContent = 'Edit Admin';
            document.getElementById('passwordLabel').textContent = 'Password (leave blank to keep current)';
            document.getElementById('passwordHint').textContent = 'Optional. Min 4 characters if changing.';
            document.getElementById('editPassword').required = false;
            if (modal) modal.show();
        }
    });

    var btnSave = document.getElementById('btnSave');
    if (btnSave) btnSave.onclick = function() {
        var id = document.getElementById('editId').value;
        var username = document.getElementById('editUsername').value.trim();
        var password = document.getElementById('editPassword').value;
        var fullName = document.getElementById('editFullName').value.trim();
        var isActive = parseInt(document.getElementById('editIsActive').value, 10) || 0;

        if (!username) { showAlert('Username is required'); return; }
        if (!id && password.length < 4) { showAlert('Password must be at least 4 characters'); return; }
        if (id && password && password.length < 4) { showAlert('Password must be at least 4 characters'); return; }

        var payload = { username: username, full_name: fullName, is_active: isActive };
        var countryEl = document.getElementById('editCountryId');
        if (countryEl) {
            var cv = countryEl.value;
            payload.country_id = cv && parseInt(cv, 10) > 0 ? parseInt(cv, 10) : null;
        }
        if (id) {
            payload.action = 'update';
            payload.id = parseInt(id, 10);
            if (password) payload.password = password;
        } else {
            payload.password = password;
        }

        btnSave.disabled = true;
        apiCall('POST', payload).then(function(r) {
            btnSave.disabled = false;
            if (r.success) { if (modal) modal.hide(); location.reload(); }
            else showAlert(r.message);
        }).catch(function(err) { btnSave.disabled = false; showAlert('Request failed: ' + (err.message || err)); });
    };

    function deleteAdmins(ids) {
        var validIds = (ids || []).map(function(v) { return parseInt(v, 10); }).filter(function(v) { return v > 0; });
        if (!validIds.length) { showAlert('No valid admins selected for delete.'); return; }
        fetch(API_BASE + '/admins-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'delete', ids: validIds })
        })
            .then(function(r) {
                var ct = r.headers.get('content-type');
                if (!ct || !ct.includes('application/json')) return r.text().then(function(t) { throw new Error('API error: ' + (t || r.status)); });
                return r.json();
            })
            .then(function(r) {
                if (r.success) location.reload();
                else showAlert(r.message || 'Delete failed');
            })
            .catch(function(err) { showAlert('Request failed: ' + (err.message || err)); });
    }
    if (btnBulkDelete) btnBulkDelete.onclick = function() {
        var checked = Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) { return parseInt(c.dataset.id, 10); });
        if (!checked.length) { showAlert('Please select one or more admins.'); return; }
        showConfirm('Delete ' + checked.length + ' selected admin(s)? They will no longer be able to log in.').then(function(ok) {
            if (ok) deleteAdmins(checked);
        });
    };

    // Move all modals to body so they appear above Bootstrap backdrop (avoids stacking context trap)
    ['editModal', 'alertModal', 'confirmModal', 'permissionsModal'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el.parentNode && el.parentNode !== document.body) document.body.appendChild(el);
        if (el) el.addEventListener('hidden.bs.modal', cleanupBackdrops);
    });
    cleanupBackdrops();
    var alertModalEl = document.getElementById('alertModal');
    var alertModalOkBtn = document.getElementById('alertModalOk');
    var alertModalCancelBtn = document.getElementById('alertModalCancel');
    var alertModalCloseBtn = document.getElementById('alertModalClose');
    function hideAlertModal() { if (alertModalInstance) alertModalInstance.hide(); }
    if (alertModalEl && typeof bootstrap !== 'undefined') {
        alertModalInstance = new bootstrap.Modal(alertModalEl);
        if (alertModalOkBtn) alertModalOkBtn.addEventListener('click', hideAlertModal);
        if (alertModalCancelBtn) alertModalCancelBtn.addEventListener('click', hideAlertModal);
        if (alertModalCloseBtn) alertModalCloseBtn.addEventListener('click', hideAlertModal);
    }
    var permModalEl = document.getElementById('permissionsModal');
    var permModal = (permModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(permModalEl) : null;
    var permGroupsContainer = document.getElementById('permGroupsContainer');
    var permUserId = document.getElementById('permUserId');
    var permUserName = document.getElementById('permUserName');

    // Document-level delegation so clicks always work (capture phase = run first)
    document.addEventListener('click', function(e) {
        var modal = document.getElementById('permissionsModal');
        if (!modal || !modal.classList.contains('show')) return;
        var chip = e.target.closest('.perm-chip');
        if (chip) {
            e.preventDefault();
            e.stopPropagation();
            chip.classList.toggle('perm-on');
            chip.classList.toggle('perm-off');
            return;
        }
        var btnSelect = e.target.closest('.perm-grp-select-all');
        if (btnSelect) {
            e.preventDefault();
            e.stopPropagation();
            var group = btnSelect.closest('.perm-group');
            if (group) group.querySelectorAll('.perm-chip').forEach(function(c) { c.classList.add('perm-on'); c.classList.remove('perm-off'); });
            return;
        }
        var btnCancel = e.target.closest('.perm-grp-cancel-all');
        if (btnCancel) {
            e.preventDefault();
            e.stopPropagation();
            var group = btnCancel.closest('.perm-group');
            if (group) group.querySelectorAll('.perm-chip').forEach(function(c) { c.classList.remove('perm-on'); c.classList.add('perm-off'); });
        }
    }, true);
    document.addEventListener('keydown', function(e) {
        var modal = document.getElementById('permissionsModal');
        if (!modal || !modal.classList.contains('show')) return;
        var chip = e.target.closest('.perm-chip');
        if (chip && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            chip.classList.toggle('perm-on');
            chip.classList.toggle('perm-off');
        }
    }, true);

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function renderPermGroups(data, container) {
        var el = container || permGroupsContainer;
        if (!el) return;
        var groups = data.groups || [];
        var totalPerms = 0;
        var html = '';
        groups.forEach(function(grp) {
            var perms = grp.permissions || [];
            totalPerms += perms.length;
            html += '<div class="perm-group" data-group-id="' + escapeHtml(grp.id || '') + '">';
            html += '<div class="perm-group-title">' + escapeHtml(grp.name) + ' (' + perms.length + ' permissions)</div>';
            html += '<div class="perm-group-actions">';
            html += '<button type="button" class="btn btn-primary btn-sm perm-grp-select-all"><i class="far fa-check-square me-1"></i> Select All</button> ';
            html += '<button type="button" class="btn btn-danger btn-sm perm-grp-cancel-all"><i class="fas fa-times me-1"></i> Cancel All</button>';
            html += '</div>';
            html += '<div class="perm-group-perms">';
            perms.forEach(function(p) {
                var on = p.granted ? ' perm-on' : ' perm-off';
                html += '<span class="perm-chip' + on + '" role="button" tabindex="0" data-id="' + escapeHtml(String(p.id)) + '" title="' + escapeHtml(p.name) + '">' + escapeHtml(p.name) + '</span>';
            });
            html += '</div></div>';
        });
        el.innerHTML = html || '<p class="text-muted">No permission groups.</p>';

        var totalEl = document.getElementById('permTotalText');
        if (totalEl) totalEl.textContent = 'Total: ' + totalPerms + ' permissions across ' + groups.length + ' groups';
    }

    function getSelectedPermIds() {
        var el = document.getElementById('permGroupsContainer');
        if (!el) return [];
        var chips = el.querySelectorAll('.perm-chip.perm-on');
        return Array.from(chips).map(function(c) { return c.getAttribute('data-id'); });
    }

    tableBody.addEventListener('click', function(e) {
        if (e.target.closest('.btn-permissions')) {
            e.preventDefault();
            e.stopPropagation();
            var b = e.target.closest('.btn-permissions');
            var id = b.dataset.id;
            var username = b.dataset.username || 'Admin';
            var container = document.getElementById('permGroupsContainer');
            if (permUserId) permUserId.value = id;
            if (permUserName) permUserName.textContent = '— ' + username;
            if (container) container.innerHTML = '<div class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i> Loading...</div>';
            var totalEl = document.getElementById('permTotalText');
            if (totalEl) totalEl.textContent = 'Total: 0 permissions across 0 groups';
            if (permModal) permModal.show();
            fetch(API_BASE + '/user_permissions.php?user_id=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.groups) {
                        if (container) container.innerHTML = '<p class="text-danger">' + (data.message || 'Failed to load permissions') + '</p>';
                        return;
                    }
                    renderPermGroups(data, container);
                })
                .catch(function(err) {
                    if (container) container.innerHTML = '<p class="text-danger">Request failed: ' + escapeHtml(err.message || err) + '</p>';
                });
        }
    });

    var permUseRoleOnly = document.getElementById('permUseRoleOnly');
    var permClear = document.getElementById('permClear');
    var permSave = document.getElementById('permSave');
    if (permUseRoleOnly) permUseRoleOnly.onclick = function() {
        var el = document.getElementById('permGroupsContainer');
        if (el) el.querySelectorAll('.perm-chip').forEach(function(c) { c.classList.remove('perm-on'); c.classList.add('perm-off'); });
    };
    if (permClear) permClear.onclick = function() {
        var el = document.getElementById('permGroupsContainer');
        if (el) el.querySelectorAll('.perm-chip').forEach(function(c) { c.classList.remove('perm-on'); c.classList.add('perm-off'); });
    };
    if (permSave) permSave.onclick = function() {
        var uidEl = document.getElementById('permUserId');
        var ids = getSelectedPermIds();
        var btn = document.getElementById('permSave');
        if (!uidEl || !uidEl.value) { showAlert('No user selected.'); return; }
        btn.disabled = true;
        fetch(API_BASE + '/user_permissions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ user_id: parseInt(uidEl.value, 10), permissions: ids })
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                if (data.success) {
                    if (permModal) {
                        permModalEl.addEventListener('hidden.bs.modal', function onHidden() {
                            permModalEl.removeEventListener('hidden.bs.modal', onHidden);
                            showAlert('Permissions saved.');
                        }, { once: true });
                        permModal.hide();
                    } else {
                        showAlert('Permissions saved.');
                    }
                } else {
                    showAlert(data.message || 'Save failed');
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                showAlert('Request failed: ' + (err.message || err));
            });
    };

    // Single delegated delete handler (capture): avoid duplicate requests/alerts
    document.addEventListener('click', function(e) {
        var rowDeleteBtn = e.target && e.target.closest ? e.target.closest('.btn-delete') : null;
        if (!rowDeleteBtn) return;
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        var id = parseInt(rowDeleteBtn.dataset.id, 10);
        if (!id) { showAlert('Invalid admin id.'); return; }
        showConfirm('Delete this admin? They will no longer be able to log in.').then(function(ok) {
            if (ok) deleteAdmins([id]);
        });
    }, true);

    /** Open add or edit modal when linked from HR dashboard (?open=add | ?edit=id). */
    (function applyQueryActions() {
        try {
            var q = new URLSearchParams(window.location.search);
            if ((q.get('open') === 'add' || q.get('add') === '1') && btnAdd) {
                setTimeout(function() { btnAdd.click(); }, 120);
                return;
            }
            var editId = q.get('edit');
            if (editId && tableBody) {
                var safeId = String(editId).replace(/[^0-9]/g, '');
                if (!safeId) return;
                var editBtn = tableBody.querySelector('.btn-edit[data-id="' + safeId + '"]');
                if (editBtn) {
                    setTimeout(function() { editBtn.click(); }, 120);
                }
            }
        } catch (e) { /* ignore */ }
    })();
})();
