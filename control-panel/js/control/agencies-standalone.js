/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/agencies-standalone.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/agencies-standalone.js`.
 */
(function() {
    // EN: Initialize core DOM references and API endpoint resolution.
    // AR: تهيئة مراجع العناصر الأساسية وتحديد مسار API المناسب.
    var body = document.body;
    var tableCard = document.getElementById('tableCard');
    var appConfig = document.getElementById('app-config');
    var API_BASE = (tableCard && tableCard.getAttribute('data-api-base')) || (body && body.getAttribute('data-api-base')) || (appConfig && appConfig.getAttribute('data-control-api-path')) || '';
    var countryId = parseInt((tableCard && tableCard.getAttribute('data-country-id')) || (body && body.getAttribute('data-country-id')) || '0', 10);
    var tableBody = document.getElementById('tableBody');

    if (!API_BASE) API_BASE = (window.location.origin + (document.location.pathname.replace(/\/pages\/.*$/, '') || '')) + '/api/control';

    // Move modals to body to avoid overlay/stacking blocking clicks (run even on country-cards view)
    ['viewModal', 'editModal', 'alertModal', 'confirmModal'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el.parentNode !== document.body) document.body.appendChild(el);
    });

    if (!tableBody) return;

    // EN: Utility helpers (number normalization, modal alerts, confirmations, slug sanitizer).
    // AR: دوال مساعدة (توحيد الأرقام، التنبيه، التأكيد، وتنظيف slug).
    function toWesternNum(s) {
        var map = {'٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'};
        return String(s).replace(/[٠-٩۰-۹]/g, function(d) { return map[d] || d; });
    }
    function showAlert(msg) {
        var el = document.getElementById('alertMessage');
        var modalEl = document.getElementById('alertModal');
        if (el) el.textContent = msg;
        if (modalEl && typeof bootstrap !== 'undefined') new bootstrap.Modal(modalEl).show();
    }
    function showConfirm(msg) {
        return new Promise(function(resolve) {
            var confirmMessage = document.getElementById('confirmMessage');
            var modalEl = document.getElementById('confirmModal');
            if (confirmMessage) confirmMessage.textContent = msg;
            if (!modalEl || typeof bootstrap === 'undefined') { resolve(false); return; }
            var modal = new bootstrap.Modal(modalEl);
            var done = false;
            var finish = function(ok) { if (done) return; done = true; modal.hide(); resolve(ok); };
            modalEl.querySelector('#confirmOk').onclick = function() { finish(true); };
            modalEl.querySelector('#confirmCancel').onclick = function() { finish(false); };
            modalEl.addEventListener('hidden.bs.modal', function onHide() { finish(false); modalEl.removeEventListener('hidden.bs.modal', onHide); }, { once: true });
            modal.show();
        });
    }
    function normalizeSlug(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[_\s]+/g, '-')
            .replace(/[^a-z0-9-]+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    var editDbPort = document.getElementById('editDbPort');
    if (editDbPort) editDbPort.addEventListener('input', function() {
        this.value = toWesternNum(this.value).replace(/\D/g, '').slice(0, 5);
    });
    var slugManuallyEdited = false;
    var editName = document.getElementById('editName');
    var editSlug = document.getElementById('editSlug');
    if (editSlug) editSlug.addEventListener('input', function() {
        slugManuallyEdited = true;
        this.value = normalizeSlug(this.value);
    });
    if (editName) editName.addEventListener('input', function() {
        if (!editSlug) return;
        if (!slugManuallyEdited || !editSlug.value.trim()) {
            editSlug.value = normalizeSlug(this.value);
        }
    });

    var selectAll = document.getElementById('selectAll');
    var btnBulkDelete = document.getElementById('btnBulkDelete');
    var btnBulkActivate = document.getElementById('btnBulkActivate');
    var btnBulkDeactivate = document.getElementById('btnBulkDeactivate');
    var btnBulkSuspend = document.getElementById('btnBulkSuspend');
    var btnBulkUnsuspend = document.getElementById('btnBulkUnsuspend');
    var btnBulkSync = document.getElementById('btnBulkSync');
    var btnBulkRebuildDb = document.getElementById('btnBulkRebuildDb');
    var btnBulkRunMigration = document.getElementById('btnBulkRunMigration');
    var btnBulkTestDbConnection = document.getElementById('btnBulkTestDbConnection');
    var btnRepairTenantLinks = document.getElementById('btnRepairTenantLinks');
    var bulkOverrideSuspended = document.getElementById('bulkOverrideSuspended');
    var bulkProgressBox = document.getElementById('bulkProgressBox');
    var bulkProgressText = document.getElementById('bulkProgressText');
    var bulkAuditBody = document.getElementById('bulkAuditBody');
    function parseEventMeta(row) {
        if (!row || !row.metadata) return {};
        if (typeof row.metadata === 'object') return row.metadata;
        try { return JSON.parse(row.metadata); } catch (e) { return {}; }
    }

    function addBulkAuditRow(row) {
        if (!bulkAuditBody || !row || !row.event_type) return;
        if (!/^BULK_OPERATION_/.test(row.event_type)) return;
        var meta = parseEventMeta(row);
        if (bulkAuditBody.children.length === 1 && /No bulk events yet\./.test(bulkAuditBody.children[0].textContent || '')) {
            bulkAuditBody.innerHTML = '';
        }
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + (row.created_at || new Date().toISOString().slice(0, 19).replace('T', ' ')) + '</td>' +
            '<td>' + (row.event_type || '-') + '</td>' +
            '<td>' + (meta.action || '-') + '</td>' +
            '<td>' + (meta.total != null ? meta.total : '-') + '</td>' +
            '<td>' + (meta.success != null ? meta.success : '-') + '</td>' +
            '<td>' + (meta.failed != null ? meta.failed : '-') + '</td>' +
            '<td>' + (meta.duration_ms != null ? (meta.duration_ms + ' ms') : '-') + '</td>' +
            '<td><code>' + (meta.request_id || row.request_id || '-') + '</code></td>';
        bulkAuditBody.insertBefore(tr, bulkAuditBody.firstChild);
        while (bulkAuditBody.children.length > 20) {
            bulkAuditBody.removeChild(bulkAuditBody.lastChild);
        }
    }


    function checkApiBase() {
        if (!API_BASE) { showAlert('Configuration error: API base not set. Please refresh the page.'); return false; }
        return true;
    }

    // EN: Standard agencies API client with JSON/content-type safety checks.
    // AR: عميل API موحد للوكالات مع تحقق من نوع المحتوى وسلامة JSON.
    function apiCall(method, body) {
        if (!checkApiBase()) return Promise.reject(new Error('API base not set'));
        var url = API_BASE + '/agencies.php?control=1';
        var opts = { method: method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
        if (body && (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE')) opts.body = JSON.stringify(body);
        return fetch(url, opts).then(function(r) {
            var ct = r.headers.get('content-type');
            if (!ct || ct.indexOf('application/json') === -1) return r.text().then(function(t) { throw new Error('API error: ' + (t || r.status)); });
            return r.json();
        });
    }

    // EN: Central toggle for all bulk action controls during long-running actions.
    // AR: تحكم مركزي لتعطيل/تفعيل أزرار العمليات الجماعية أثناء العمليات الطويلة.
    function setBulkButtonsDisabled(disabled) {
        var btns = [btnBulkDelete, btnBulkActivate, btnBulkDeactivate, btnBulkSuspend, btnBulkUnsuspend, btnBulkSync, btnBulkRebuildDb, btnBulkRunMigration, btnBulkTestDbConnection];
        btns.forEach(function(b) { if (b) b.disabled = disabled; });
    }

    // EN: Recompute selection-driven button state from current checked rows.
    // AR: إعادة حساب حالة الأزرار بناءً على الصفوف المحددة حالياً.
    function updateBulkState(forceDisabled) {
        var checked = document.querySelectorAll('.row-check:checked');
        var n = forceDisabled ? 0 : checked.length;
        if (btnBulkDelete) btnBulkDelete.disabled = !n;
        if (btnBulkActivate) btnBulkActivate.disabled = !n;
        if (btnBulkDeactivate) btnBulkDeactivate.disabled = !n;
        if (btnBulkSuspend) btnBulkSuspend.disabled = !n;
        if (btnBulkUnsuspend) btnBulkUnsuspend.disabled = !n;
        if (btnBulkSync) btnBulkSync.disabled = !n;
        if (btnBulkRebuildDb) btnBulkRebuildDb.disabled = !n;
        if (btnBulkRunMigration) btnBulkRunMigration.disabled = !n;
        if (btnBulkTestDbConnection) btnBulkTestDbConnection.disabled = !n;
    }

    // Delegate change/click to document so events reach even with overlay issues
    document.addEventListener('change', function(e) {
        if (e.target && e.target.matches && e.target.matches('.row-check')) updateBulkState();
    });
    if (selectAll) selectAll.addEventListener('change', function() { document.querySelectorAll('.row-check').forEach(function(c) { c.checked = selectAll.checked; }); updateBulkState(); });
    var countrySelect = document.getElementById('agenciesCountrySelect') || document.getElementById('agencyCountrySelectLegacy') || document.querySelector('select[name="country_id"]');
    if (countrySelect && countrySelect.form) {
        countrySelect.addEventListener('change', function() { countrySelect.form.submit(); });
    }
    var agenciesPageLimitSelect = document.getElementById('agenciesPageLimitSelect') || document.getElementById('pageLimitSelect');
    if (agenciesPageLimitSelect && agenciesPageLimitSelect.form) {
        agenciesPageLimitSelect.addEventListener('change', function() { agenciesPageLimitSelect.form.submit(); });
    }
    // Initialize bulk buttons state on load (also covers browser-restored checkbox state).
    updateBulkState();
    setTimeout(updateBulkState, 0);

    var modalEl = document.getElementById('editModal');
    var modal = (modalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(modalEl) : null;
    var viewModalEl = document.getElementById('viewModal');
    var viewModal = (viewModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(viewModalEl) : null;
    var viewModalRowData = null;

    // EN: Initialize add-agency modal with safe defaults for fresh records.
    // AR: تهيئة نافذة إضافة وكالة بقيم افتراضية آمنة للسجلات الجديدة.
    var btnAdd = document.getElementById('btnAdd');
    if (btnAdd) btnAdd.onclick = function() {
        slugManuallyEdited = false;
        document.getElementById('editId').value = '';
        var editCountryId = document.getElementById('editCountryId');
        document.getElementById('editCountryId').value = countryId || (editCountryId && editCountryId.options[1] ? editCountryId.options[1].value : '');
        document.getElementById('editName').value = '';
        document.getElementById('editSlug').value = '';
        document.getElementById('editSiteUrl').value = '';
        document.getElementById('editDbHost').value = 'localhost';
        document.getElementById('editDbPort').value = 3306;
        document.getElementById('editDbUser').value = '';
        document.getElementById('editDbPass').value = '';
        document.getElementById('editDbName').value = '';
        document.getElementById('editIsActive').value = '1';
        var editRenewalDate = document.getElementById('editRenewalDate');
        if (editRenewalDate) editRenewalDate.value = '';
        var editIsSuspended = document.getElementById('editIsSuspended');
        if (editIsSuspended) editIsSuspended.value = '0';
        document.getElementById('modalTitle').textContent = 'Add Agency';
        document.getElementById('editDbPass').placeholder = '';
        if (modal) modal.show();
    };

    // Document-level delegation so Edit/Delete/Bulk buttons work (capture phase)
    document.addEventListener('click', function(e) {
        if (!e.target || typeof e.target.closest !== 'function') return;

        var bulkBtn = e.target.closest('#btnBulkDelete, #btnBulkActivate, #btnBulkDeactivate, #btnBulkSuspend, #btnBulkUnsuspend, #btnBulkSync, #btnBulkRebuildDb, #btnBulkRunMigration, #btnBulkTestDbConnection, #btnRepairTenantLinks');
        if (bulkBtn) {
            e.preventDefault();
            e.stopPropagation();
            // Re-evaluate selection state right before action click.
            updateBulkState();
            if (bulkBtn.disabled) { showAlert('Please select one or more agencies (check the boxes).'); return; }
            var id = bulkBtn.id;
            if (id === 'btnBulkDelete') handleBulkAction('Type DELETE in the next prompt to confirm bulk delete.', 'PATCH', function(ids) { return { agency_ids: ids, ids: ids, action: 'delete' }; }, true);
            else if (id === 'btnBulkActivate') handleBulkAction('Bulk activate selected agencies?', 'PATCH', function(ids) { return { agency_ids: ids, ids: ids, action: 'activate', is_active: 1 }; });
            else if (id === 'btnBulkDeactivate') handleBulkAction('Bulk mark selected agencies as inactive?', 'PATCH', function(ids) { return { agency_ids: ids, ids: ids, action: 'deactivate', is_active: 0, is_suspended: 0 }; });
            else if (id === 'btnBulkSuspend') handleBulkAction('Bulk suspend selected agencies?', 'PATCH', function(ids) { return { agency_ids: ids, ids: ids, action: 'suspend', is_suspended: 1 }; });
            else if (id === 'btnBulkUnsuspend') handleBulkAction('Bulk unsuspend selected agencies?', 'PATCH', function(ids) { return { agency_ids: ids, ids: ids, action: 'activate', is_suspended: 0 }; });
            else if (id === 'btnBulkSync') handleBulkAction('Bulk sync selected agencies?', 'PATCH', function(ids) { return { agency_ids: ids, ids: ids, action: 'sync' }; });
            else if (id === 'btnBulkRebuildDb') handleBulkAction('Bulk rebuild DB for selected agencies? (SUPER_ADMIN)', 'PATCH', function(ids) { return { agency_ids: ids, ids: ids, action: 'rebuild_db' }; });
            else if (id === 'btnBulkRunMigration') handleBulkAction('Bulk run migration for selected agencies?', 'PATCH', function(ids) { return { agency_ids: ids, ids: ids, action: 'run_migration' }; });
            else if (id === 'btnBulkTestDbConnection') handleBulkAction('Bulk test DB connection for selected agencies?', 'PATCH', function(ids) { return { agency_ids: ids, ids: ids, action: 'test_db_connection' }; });
            else if (id === 'btnRepairTenantLinks') handleBulkAction('Repair tenant link for selected agencies without tenant_id? (SUPER_ADMIN)', 'PATCH', function(ids) { return { agency_ids: ids, ids: ids, action: 'repair_tenant_link' }; });
            return;
        }
        if (e.target.closest('.btn-agency-control-link')) {
            var controlLink = e.target.closest('.btn-agency-control-link');
            var action = controlLink.getAttribute('data-action');
            var agencyId = parseInt(controlLink.getAttribute('data-agency-id') || '0', 10);
            if (action && agencyId) {
                apiCall('PATCH', { agency_ids: [agencyId], action: action }).catch(function() {});
            }
        }

        if (e.target.closest('.btn-view')) {
            // EN: View action hydrates read-only modal from encoded row payload.
            // AR: إجراء العرض يملأ نافذة القراءة من بيانات الصف المشفرة.
            e.preventDefault();
            e.stopPropagation();
            var raw = e.target.closest('.btn-view').dataset.row || '';
            var r = raw ? JSON.parse(atob(raw)) : {};
            viewModalRowData = r;
            var cid = r.country_id;
            var cname = (r.country_name || '').trim() || (r.country || '').trim() || '-';
            function setView(id, val) { var el = document.getElementById(id); if (el) el.textContent = val != null && val !== '' ? String(val) : '-'; }
            setView('viewCountry', cname);
            setView('viewName', r.name || r.agency_name);
            setView('viewSlug', r.slug);
            setView('viewSiteUrl', r.site_url);
            setView('viewDbHost', r.db_host || 'localhost');
            setView('viewDbPort', r.db_port || '3306');
            setView('viewDbUser', r.db_user);
            setView('viewDbName', r.db_name);
            setView('viewCreated', r.created_at ? String(r.created_at).slice(0, 10) : '');
            setView('viewRenewalDate', r.renewal_date ? String(r.renewal_date).slice(0, 10) : '');
            var status = (r.is_active === 0 || r.is_active === '0') ? 'Inactive' : (r.is_suspended ? 'Suspended' : 'Active');
            setView('viewStatus', status);
            var viewSuspended = document.getElementById('viewSuspended');
            if (viewSuspended) viewSuspended.textContent = r.is_suspended ? 'Yes (non-payment)' : 'No';
            if (viewModal) viewModal.show();
        } else if (e.target.closest('.btn-edit')) {
            // EN: Edit action hydrates form modal for update workflow.
            // AR: إجراء التعديل يملأ نموذج النافذة لعملية التحديث.
            e.preventDefault();
            e.stopPropagation();
            var raw = e.target.closest('.btn-edit').dataset.row || '';
            var r = raw ? JSON.parse(atob(raw)) : {};
            document.getElementById('editId').value = r.id || '';
            document.getElementById('editCountryId').value = r.country_id || '';
            document.getElementById('editName').value = r.name || '';
            document.getElementById('editSlug').value = r.slug || '';
            slugManuallyEdited = true;
            document.getElementById('editSiteUrl').value = r.site_url || '';
            document.getElementById('editDbHost').value = r.db_host || 'localhost';
            document.getElementById('editDbPort').value = r.db_port || 3306;
            document.getElementById('editDbUser').value = r.db_user || '';
            document.getElementById('editDbPass').value = '';
            document.getElementById('editDbPass').placeholder = '(leave blank to keep)';
            document.getElementById('editDbName').value = r.db_name || '';
            document.getElementById('editIsActive').value = r.is_active !== undefined && r.is_active !== null ? r.is_active : '1';
            var editRenewalDate = document.getElementById('editRenewalDate');
            if (editRenewalDate && r.renewal_date) editRenewalDate.value = String(r.renewal_date).slice(0, 10);
            else if (editRenewalDate) editRenewalDate.value = '';
            var editIsSuspended = document.getElementById('editIsSuspended');
            if (editIsSuspended) editIsSuspended.value = (r.is_suspended ? '1' : '0');
            document.getElementById('modalTitle').textContent = 'Edit Agency';
            if (modal) modal.show();
        } else if (e.target.closest('.btn-delete')) {
            // EN: Delete action uses confirmation then calls API.
            // AR: إجراء الحذف يطلب تأكيداً ثم يستدعي API.
            e.preventDefault();
            e.stopPropagation();
            var id = e.target.closest('.btn-delete').dataset.id;
            showConfirm('Delete this agency?').then(function(ok) {
                if (ok) apiCall('DELETE', { ids: [id] }).then(function(r) { if (r.success) location.reload(); else showAlert(r.message); }).catch(function(err) { showAlert('Request failed: ' + (err.message || err)); });
            });
        } else if (e.target.closest('.btn-mark-paid')) {
            // EN: Mark-paid operation unsuspends agency and syncs latest registration payment state.
            // AR: عملية "تم الدفع" تفك الإيقاف وتزامن حالة آخر طلب تسجيل مرتبط.
            e.preventDefault();
            e.stopPropagation();
            var btnPaid = e.target.closest('.btn-mark-paid');
            var aid = parseInt(btnPaid.dataset.id || '0', 10);
            var aname = (btnPaid.dataset.name || 'this agency').trim();
            if (!aid) { showAlert('Invalid agency ID'); return; }
            showConfirm('Mark paid for ' + aname + '? This will unsuspend the agency and mark its latest linked registration as Paid.').then(function(ok) {
                if (!ok) return;
                btnPaid.disabled = true;
                apiCall('PATCH', { ids: [aid], action: 'mark_paid' }).then(function(r) {
                    if (r && r.success) {
                        showAlert('Marked paid successfully.');
                        location.reload();
                    } else {
                        btnPaid.disabled = false;
                        showAlert((r && r.message) ? r.message : 'Mark paid failed');
                    }
                }).catch(function(err) {
                    btnPaid.disabled = false;
                    showAlert('Request failed: ' + (err.message || err));
                });
            });
        }
    }, true);

    var btnEditFromView = document.getElementById('btnEditFromView');
    if (btnEditFromView) btnEditFromView.onclick = function() {
        if (!viewModalRowData) return;
        var r = viewModalRowData;
        if (viewModal) viewModal.hide();
        slugManuallyEdited = true;
        document.getElementById('editId').value = r.id || '';
        document.getElementById('editCountryId').value = r.country_id || '';
        document.getElementById('editName').value = r.name || '';
        document.getElementById('editSlug').value = r.slug || '';
        document.getElementById('editSiteUrl').value = r.site_url || '';
        document.getElementById('editDbHost').value = r.db_host || 'localhost';
        document.getElementById('editDbPort').value = r.db_port || 3306;
        document.getElementById('editDbUser').value = r.db_user || '';
        document.getElementById('editDbPass').value = '';
        document.getElementById('editDbPass').placeholder = '(leave blank to keep)';
        document.getElementById('editDbName').value = r.db_name || '';
        document.getElementById('editIsActive').value = r.is_active !== undefined && r.is_active !== null ? r.is_active : '1';
        var editRenewalDate = document.getElementById('editRenewalDate');
        if (editRenewalDate && r.renewal_date) editRenewalDate.value = String(r.renewal_date).slice(0, 10);
        else if (editRenewalDate) editRenewalDate.value = '';
        var editIsSuspended = document.getElementById('editIsSuspended');
        if (editIsSuspended) editIsSuspended.value = (r.is_suspended ? '1' : '0');
        document.getElementById('modalTitle').textContent = 'Edit Agency';
        if (modal) modal.show();
    };

    var btnSave = document.getElementById('btnSave');
    if (btnSave) btnSave.onclick = function() {
        if (!checkApiBase()) return;
        var id = document.getElementById('editId').value;
        var countryIdVal = document.getElementById('editCountryId').value;
        var name = document.getElementById('editName').value.trim();
        var dbUser = document.getElementById('editDbUser').value.trim();
        var dbPass = document.getElementById('editDbPass').value;
        var dbName = document.getElementById('editDbName').value.trim();
        var dbPortNum = parseInt(toWesternNum(document.getElementById('editDbPort').value), 10) || 3306;

        var missing = [];
        if (!name) missing.push('Name');
        if (!(countryIdVal ? parseInt(countryIdVal, 10) : 0)) missing.push('Country');
        if (!dbUser) missing.push('DB User');
        if (!dbName) missing.push('DB Name');
        if (!id && !dbPass) missing.push('DB Password');
        if (missing.length) { showAlert('Missing required: ' + missing.join(', ')); return; }
        if (dbPortNum < 1 || dbPortNum > 65535) { showAlert('DB Port must be between 1 and 65535'); return; }
        var slugVal = normalizeSlug(document.getElementById('editSlug').value.trim());
        document.getElementById('editSlug').value = slugVal;
        if (slugVal && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slugVal)) { showAlert('Slug must be lowercase letters, numbers and hyphens only (e.g. bangladesh-dhaka)'); return; }
        var siteUrlVal = document.getElementById('editSiteUrl').value.trim();
        if (siteUrlVal && !/^https?:\/\/.+/.test(siteUrlVal)) { showAlert('Site URL must start with http:// or https://'); return; }

        var payload = {
            country_id: countryIdVal ? parseInt(countryIdVal, 10) : 0,
            name: name,
            slug: slugVal || null,
            site_url: siteUrlVal,
            db_host: document.getElementById('editDbHost').value.trim() || 'localhost',
            db_port: dbPortNum,
            db_user: dbUser,
            db_pass: dbPass || (id ? 'KEEP' : ''),
            db_name: dbName,
            is_active: parseInt(document.getElementById('editIsActive').value, 10) || 0
        };
        var editRenewalDateEl = document.getElementById('editRenewalDate');
        if (editRenewalDateEl && editRenewalDateEl.value.trim()) payload.renewal_date = editRenewalDateEl.value.trim();
        var editIsSuspendedEl = document.getElementById('editIsSuspended');
        if (editIsSuspendedEl) payload.is_suspended = parseInt(editIsSuspendedEl.value, 10) || 0;
        if (payload.db_pass === 'KEEP') delete payload.db_pass;
        var method = id ? 'PUT' : 'POST';
        if (id) payload.id = id;
        btnSave.disabled = true;
        apiCall(method, payload).then(function(r) {
            if (r.success) { if (modal) modal.hide(); location.reload(); }
            else showAlert(r.message);
        }).catch(function(err) { showAlert('Request failed: ' + (err.message || err)); }).finally(function() { btnSave.disabled = false; });
    };

    function getCheckedIds() {
        var nodes = document.querySelectorAll('.row-check:checked');
        var ids = [];
        for (var i = 0; i < nodes.length; i++) ids.push(nodes[i].getAttribute('data-id'));
        return ids;
    }

    function handleBulkAction(msg, method, buildPayload, requireDeletePrompt) {
        var ids = getCheckedIds();
        if (!ids.length) { showAlert('Please select one or more agencies (check the boxes).'); return; }
        if (!checkApiBase()) return;
            showConfirm(msg).then(function(ok) {
            if (!ok) return;
            setBulkButtonsDisabled(true);
            var body = buildPayload(ids);
                if (bulkOverrideSuspended && bulkOverrideSuspended.checked) {
                    body.override_suspended = true;
                }
                if (requireDeletePrompt) {
                    var typed = window.prompt('Type DELETE to confirm bulk delete', '');
                    if ((typed || '').trim().toUpperCase() !== 'DELETE') {
                        showAlert('Bulk delete cancelled. You must type DELETE.');
                        updateBulkState();
                        return;
                    }
                    body.confirm = 'DELETE';
                }
            if (bulkProgressBox && bulkProgressText) {
                bulkProgressBox.style.display = '';
                bulkProgressText.textContent = 'Running on ' + ids.length + ' agencies...';
            }
            apiCall(method || 'PATCH', body).then(function(r) {
                if (r.success) location.reload();
                else {
                    var details = r.first_error || (r.errors && r.errors[0] && r.errors[0].error) || r.message || 'Request failed';
                    showAlert(details + (r.request_id ? (' (request: ' + r.request_id + ')') : ''));
                    if (bulkProgressText) {
                        bulkProgressText.textContent = 'Completed with failures. Success: ' + (r.success_count || 0) + ', Failed: ' + (r.failed_count || 0);
                    }
                    updateBulkState();
                }
            }).catch(function(err) { showAlert('Request failed: ' + (err.message || err)); updateBulkState(); });
        });
    }

    (function initBulkSseProgress() {
        if (!window.EventSource || !bulkProgressText) return;
        // Temporary safe mode: disable automatic SSE stream connection here
        // because some hosting stacks return 500 on stream endpoints and flood console.
        // Bulk actions still work; progress updates fall back to request result + reload.
        return;
        var streamCandidates = [
            API_BASE + '/events-stream.php',
            window.location.origin + '/admin/events-stream.php'
        ];
        var activeIndex = 0;
        var es = null;
        var fallbackTimer = null;

        function bindStream(streamUrl) {
            try {
                es = new EventSource(streamUrl);
                es.onmessage = function(evt) {
                    var row = null;
                    try { row = JSON.parse(evt.data || '{}'); } catch (e) { return; }
                    if (!row || !row.event_type) return;
                    if (row.event_type === 'BULK_OPERATION_STARTED') {
                        if (bulkProgressBox) bulkProgressBox.style.display = '';
                        bulkProgressText.textContent = 'Bulk started...';
                    } else if (row.event_type === 'BULK_OPERATION_ITEM_SUCCESS' || row.event_type === 'BULK_OPERATION_ITEM_FAILED') {
                        bulkProgressText.textContent = 'Processing: ' + row.event_type.replace('BULK_OPERATION_ITEM_', '').toLowerCase();
                    } else if (row.event_type === 'BULK_OPERATION_COMPLETED') {
                        var meta = {};
                        try { meta = row.metadata ? JSON.parse(row.metadata) : {}; } catch (e2) { meta = {}; }
                        bulkProgressText.textContent = 'Done. Total: ' + (meta.total || 0) + ', Success: ' + (meta.success || 0) + ', Failed: ' + (meta.failed || 0);
                    }
                    addBulkAuditRow(row);
                };
                es.onerror = function() {
                    if (fallbackTimer) return;
                    fallbackTimer = window.setTimeout(function() {
                        fallbackTimer = null;
                        if (es) { try { es.close(); } catch (e3) {} }
                        activeIndex = (activeIndex + 1) % streamCandidates.length;
                        bindStream(streamCandidates[activeIndex]);
                    }, 1200);
                };
            } catch (e) {}
        }

        bindStream(streamCandidates[activeIndex]);
    })();

})();
