/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/countries.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/countries.js`.
 */
(function() {
    // EN: Initialize API base + key DOM handles for countries page behavior.
    // AR: تهيئة مسار API والعناصر الأساسية لسلوك صفحة إدارة الدول.
    var config = document.getElementById('control-config');
    var appConfig = document.getElementById('app-config');
    var API_BASE = (config && config.getAttribute('data-api-base')) || (appConfig && appConfig.getAttribute('data-control-api-path')) || '';
    var tableBody = document.getElementById('tableBody');
    if (!tableBody) return;

    // EN: Move modals under <body> to avoid clipping/stacking issues inside nested containers.
    // AR: نقل النوافذ إلى <body> لتفادي مشاكل القص وترتيب الطبقات داخل الحاويات.
    // Move modals to body so they escape parent overflow/stacking and remain interactive
    ['editModal', 'alertModal', 'confirmModal'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el.parentNode !== document.body) document.body.appendChild(el);
    });

    // EN: Small UI helpers for alert and confirm modals.
    // AR: دوال مساعدة لعرض التنبيه ونافذة التأكيد.
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

    var selectAll = document.getElementById('selectAll');
    var btnBulkDelete = document.getElementById('btnBulkDelete');
    var btnBulkActivate = document.getElementById('btnBulkActivate');
    var btnBulkInactivate = document.getElementById('btnBulkInactivate');
    var bulkProgressBox = document.getElementById('bulkProgressBox');
    var pageLimitSelect = document.getElementById('pageLimitSelect');
    if (pageLimitSelect && pageLimitSelect.form) {
        pageLimitSelect.addEventListener('change', function() {
            pageLimitSelect.form.submit();
        });
    }

    // EN: Standardized API request wrapper for countries CRUD/bulk operations.
    // AR: دالة موحدة لاستدعاء API لعمليات CRUD والعمليات الجماعية.
    function apiCall(method, body) {
        var url = API_BASE + '/countries.php?control=1';
        var opts = { method: method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
        if (body && (method === 'POST' || method === 'PUT' || method === 'DELETE')) opts.body = JSON.stringify(body);
        return fetch(url, opts).then(function(r) {
            var ct = r.headers.get('content-type');
            if (!ct || !ct.includes('application/json')) return r.text().then(function(t) { throw new Error('API error: ' + (t || r.status)); });
            return r.json();
        });
    }

    // EN: Keep bulk action buttons enabled only when at least one row is selected.
    // AR: تفعيل أزرار العمليات الجماعية فقط عند تحديد صف واحد على الأقل.
    function updateBulkState() {
        var checked = document.querySelectorAll('.row-check:checked');
        if (btnBulkActivate) btnBulkActivate.disabled = !checked.length;
        if (btnBulkInactivate) btnBulkInactivate.disabled = !checked.length;
        if (btnBulkDelete) btnBulkDelete.disabled = !checked.length;
    }

    function setBulkProgress(msg, kind) {
        if (!bulkProgressBox) return;
        bulkProgressBox.textContent = msg;
        bulkProgressBox.classList.remove('is-running', 'is-success', 'is-error');
        if (kind) bulkProgressBox.classList.add(kind);
    }

    // EN: Collect validated numeric IDs from selected table rows.
    // AR: جمع المعرفات الرقمية الصحيحة من الصفوف المحددة.
    function getSelectedIds() {
        return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) { return parseInt(c.dataset.id, 10) || 0; }).filter(function(id) { return id > 0; });
    }

    // EN: Runs bulk actions with safeguards (explicit DELETE confirmation for destructive action).
    // AR: تنفيذ العمليات الجماعية مع حماية إضافية (تأكيد كتابة DELETE للحذف الجماعي).
    function runBulkAction(action, title) {
        var ids = getSelectedIds();
        if (!ids.length) return;
        var confirmPromise;
        if (action === 'bulk_delete') {
            var confirmWord = (window.prompt('Type DELETE to confirm bulk delete for ' + ids.length + ' countries:') || '').trim();
            confirmPromise = Promise.resolve(confirmWord);
        } else {
            confirmPromise = showConfirm(title + ' for ' + ids.length + ' selected country(ies)?').then(function(ok) { return ok ? 'OK' : ''; });
        }
        confirmPromise.then(function(token) {
            if (!token) return;
            if (action === 'bulk_delete' && token.toUpperCase() !== 'DELETE') {
                showAlert('Bulk delete cancelled. You must type DELETE.');
                return;
            }
            setBulkProgress('Running ' + title + ' for ' + ids.length + ' countries...', 'is-running');
            return apiCall('POST', {
                action: action,
                ids: ids,
                confirm: action === 'bulk_delete' ? token : undefined
            }).then(function(r) {
                if (!r || !r.success) {
                    setBulkProgress('Bulk failed: ' + ((r && r.message) ? r.message : 'Unknown error'), 'is-error');
                    showAlert((r && r.message) ? r.message : 'Bulk request failed');
                    return;
                }
                var summary = r.summary || {};
                var done = parseInt(summary.updated != null ? summary.updated : summary.deleted, 10);
                if (isNaN(done)) done = 0;
                setBulkProgress(title + ' completed. Selected: ' + ids.length + ', affected: ' + done + '.', 'is-success');
                setTimeout(function() { location.reload(); }, 400);
            }).catch(function(err) {
                setBulkProgress('Bulk failed: ' + (err.message || err), 'is-error');
                showAlert('Request failed: ' + (err.message || err));
            });
        });
    }

    tableBody.addEventListener('change', function(e) { if (e.target.matches('.row-check')) updateBulkState(); });
    if (selectAll) selectAll.addEventListener('change', function() { document.querySelectorAll('.row-check').forEach(function(c) { c.checked = selectAll.checked; }); updateBulkState(); });

    var modalEl = document.getElementById('editModal');
    var modal = (modalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(modalEl) : null;

    // EN: Open modal in create mode with default values.
    // AR: فتح النافذة في وضع الإضافة مع القيم الافتراضية.
    var btnAdd = document.getElementById('btnAdd');
    if (btnAdd) btnAdd.onclick = function() {
        document.getElementById('editId').value = '';
        document.getElementById('editName').value = '';
        document.getElementById('editSlug').value = '';
        document.getElementById('editIsActive').value = '1';
        document.getElementById('modalTitle').textContent = 'Add Country';
        if (modal) modal.show();
    };

    // EN: Row-level actions (edit/delete) delegated to table body.
    // AR: إجراءات كل صف (تعديل/حذف) عبر تفويض الحدث داخل الجدول.
    tableBody.addEventListener('click', function(e) {
        e.stopPropagation();
        if (e.target.closest('.btn-edit')) {
            var b = e.target.closest('.btn-edit');
            document.getElementById('editId').value = b.dataset.id || '';
            document.getElementById('editName').value = b.dataset.name || '';
            document.getElementById('editSlug').value = b.dataset.slug || '';
            document.getElementById('editIsActive').value = b.dataset.active !== undefined ? b.dataset.active : '1';
            document.getElementById('modalTitle').textContent = 'Edit Country';
            if (modal) modal.show();
        } else if (e.target.closest('.btn-delete')) {
            var id = e.target.closest('.btn-delete').dataset.id;
            showConfirm('Delete this country? Agencies under it will also be deleted.').then(function(ok) {
                if (ok) apiCall('DELETE', { ids: [id] }).then(function(r) { if (r.success) location.reload(); else showAlert(r.message); }).catch(function(err) { showAlert('Request failed: ' + (err.message || err)); });
            });
        }
    });

    // EN: Save handler for add/edit modal.
    // AR: معالج الحفظ لنافذة الإضافة/التعديل.
    var btnSave = document.getElementById('btnSave');
    if (btnSave) btnSave.onclick = function() {
        if (!API_BASE) { showAlert('Configuration error: API base not set. Please refresh the page.'); return; }
        var id = document.getElementById('editId').value;
        var name = document.getElementById('editName').value.trim();
        if (!name) { showAlert('Name is required'); return; }
        var slug = document.getElementById('editSlug').value.trim();
        if (slug && !/^[a-z0-9][a-z0-9_\-]*[a-z0-9]$|^[a-z0-9]$/.test(slug)) { showAlert('Slug must be lowercase letters, numbers, hyphens or underscores (e.g. saudi-arabia)'); return; }
        var payload = { name: name, slug: slug || null, is_active: parseInt(document.getElementById('editIsActive').value, 10) || 0 };
        var method = id ? 'PUT' : 'POST';
        if (id) payload.id = id;
        btnSave.disabled = true;
        apiCall(method, payload).then(function(r) {
            btnSave.disabled = false;
            if (r.success) { if (modal) modal.hide(); location.reload(); } else { showAlert(r.message); }
        }).catch(function(err) { btnSave.disabled = false; showAlert('Request failed: ' + (err.message || err)); });
    };

    if (btnBulkActivate) btnBulkActivate.onclick = function() { runBulkAction('bulk_activate', 'Bulk Activate'); };
    if (btnBulkInactivate) btnBulkInactivate.onclick = function() { runBulkAction('bulk_inactivate', 'Bulk Inactivate'); };
    if (btnBulkDelete) btnBulkDelete.onclick = function() { runBulkAction('bulk_delete', 'Bulk Delete'); };
})();
