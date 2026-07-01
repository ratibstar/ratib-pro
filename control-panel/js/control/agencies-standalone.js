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
    ['viewModal', 'editModal', 'alertModal', 'confirmModal', 'erpProvisionModal'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el.parentNode !== document.body) document.body.appendChild(el);
    });

    if (!tableBody) return;

    function closeAgencyActionDropdowns() {
        document.querySelectorAll('.ag-actions-dropdown').forEach(function (wrap) {
            var toggle = wrap.querySelector('.ag-actions-toggle');
            var menu = wrap.querySelector('.ag-actions-menu');
            if (menu) {
                menu.classList.remove('show');
            }
            if (toggle) {
                toggle.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            }
            wrap.classList.remove('show');
            if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown && toggle) {
                var inst = bootstrap.Dropdown.getInstance(toggle);
                if (inst) {
                    inst.hide();
                }
            }
        });
    }

    function initAgencyActionDropdowns() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) {
            return;
        }
        document.querySelectorAll('.ag-actions-dropdown .ag-actions-toggle').forEach(function (toggle) {
            if (toggle._agDdBound) {
                return;
            }
            toggle._agDdBound = true;
            try {
                bootstrap.Dropdown.getOrCreateInstance(toggle, {
                    popperConfig: {
                        strategy: 'fixed',
                        modifiers: [{ name: 'offset', options: { offset: [0, 6] } }],
                    },
                });
            } catch (e) {
                /* ignore */
            }
        });
    }
    initAgencyActionDropdowns();

    function getBootstrapModal(el) {
        if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return null;
        }
        if (typeof bootstrap.Modal.getOrCreateInstance === 'function') {
            return bootstrap.Modal.getOrCreateInstance(el);
        }
        return new bootstrap.Modal(el);
    }

    // Stale Bootstrap backdrops block clicks after repeated confirm/alert dialogs.
    function cleanupStaleModalBackdrops() {
        document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
        if (!document.querySelector('.modal.show')) {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }
    }
    cleanupStaleModalBackdrops();

    function showAlert(msg) {
        var el = document.getElementById('alertMessage');
        var modalEl = document.getElementById('alertModal');
        if (el) el.textContent = msg;
        var modal = getBootstrapModal(modalEl);
        if (modal) {
            modal.show();
            return;
        }
        window.alert(msg);
    }

    function showConfirm(msg) {
        return new Promise(function(resolve) {
            var confirmMessage = document.getElementById('confirmMessage');
            var modalEl = document.getElementById('confirmModal');
            if (confirmMessage) confirmMessage.textContent = msg;
            var modal = getBootstrapModal(modalEl);
            if (!modal) {
                resolve(window.confirm(msg));
                return;
            }
            var done = false;
            var confirmedOk = false;
            var finish = function(ok) {
                if (done) return;
                done = true;
                if (ok) confirmedOk = true;
                modal.hide();
                resolve(ok);
            };
            var okBtn = modalEl.querySelector('#confirmOk');
            var cancelBtn = modalEl.querySelector('#confirmCancel');
            if (okBtn) okBtn.onclick = function() { finish(true); };
            if (cancelBtn) cancelBtn.onclick = function() { finish(false); };
            modalEl.addEventListener('hidden.bs.modal', function onHide() {
                modalEl.removeEventListener('hidden.bs.modal', onHide);
                if (!confirmedOk) finish(false);
            });
            modal.show();
        });
    }

    function runProvisionPro(proBtn, proAgencyId) {
        if (!window.confirm('Provision RATEB Pro for this agency?\n\nCreates/updates admin user:\nUsername: admin\nPassword: 123456')) return;
        proBtn.disabled = true;
        fetch(API_BASE + '/agencies-provision-pro.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ agency_id: proAgencyId })
        }).then(function(res) {
            var ct = (res.headers.get('content-type') || '').toLowerCase();
            if (!ct.includes('application/json')) throw new Error('Session expired or server error');
            return res.json();
        }).then(function(data) {
            proBtn.disabled = false;
            if (!data || !data.success) {
                window.alert((data && data.message) ? data.message : 'Pro provisioning failed');
                return;
            }
            var seed = data.data || {};
            var msg = 'RATEB Pro ready on ' + (seed.db_name || 'database');
            if (seed.admin_password) msg += '\nUsername: ' + (seed.admin_username || 'admin') + '\nPassword: ' + seed.admin_password;
            window.alert(msg);
            window.location.reload();
        }).catch(function() {
            proBtn.disabled = false;
            window.alert('Pro provisioning request failed');
        });
    }

    function openErpProvisionModal(erpProvBtn, agencyId, erpStatus) {
        var planSelect = document.getElementById('erpProvisionPlanSelect');
        var agencyInput = document.getElementById('erpProvisionAgencyId');
        var modalEl = document.getElementById('erpProvisionModal');
        if (!planSelect || !agencyInput || !modalEl) {
            window.alert('ERP plan dialog is unavailable on this page.');
            return;
        }
        agencyInput.value = String(agencyId);
        agencyInput.setAttribute('data-force', erpStatus === 'ready' ? '1' : '0');
        var currentPlan = (erpProvBtn.getAttribute('data-erp-plan') || 'professional').toLowerCase();
        planSelect.value = ['starter', 'professional', 'enterprise'].indexOf(currentPlan) >= 0 ? currentPlan : 'professional';
        window.setTimeout(function() {
            cleanupStaleModalBackdrops();
            var erpModal = getBootstrapModal(modalEl);
            if (erpModal) erpModal.show();
            else window.alert('ERP plan dialog is unavailable on this page.');
        }, 50);
    }

    // EN: Utility helpers (number normalization, modal alerts, confirmations, slug sanitizer).
    // AR: دوال مساعدة (توحيد الأرقام، التنبيه، التأكيد، وتنظيف slug).
    function toWesternNum(s) {
        var map = {'٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'};
        return String(s).replace(/[٠-٩۰-۹]/g, function(d) { return map[d] || d; });
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

    function apiDeleteIds(ids, confirmToken) {
        var body = { action: 'delete', ids: ids, agency_ids: ids };
        if (confirmToken) body.confirm = confirmToken;
        return apiCall('POST', body).then(function(r) {
            if (r && r.success) return r;
            return apiCall('DELETE', body);
        }).then(function(r) {
            if (r && r.success) return r;
            var patchBody = { action: 'delete', ids: ids, agency_ids: ids };
            if (confirmToken) patchBody.confirm = confirmToken;
            return apiCall('PATCH', patchBody);
        });
    }

    function syncRowPickVisual(chk) {
        if (!chk) return;
        var row = chk.closest('tr');
        if (row && chk.classList.contains('agency-row-check')) {
            row.classList.toggle('agency-row-picked', !!chk.checked);
        }
    }

    function syncAllRowPickVisuals() {
        document.querySelectorAll('.agency-row-check').forEach(syncRowPickVisual);
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
        if (e.target && e.target.matches && e.target.matches('.agency-row-check, .row-check')) {
            syncRowPickVisual(e.target);
            updateBulkState();
        }
    });
    if (selectAll) selectAll.addEventListener('change', function() {
        document.querySelectorAll('.agency-row-check, .row-check').forEach(function(c) { c.checked = selectAll.checked; syncRowPickVisual(c); });
        updateBulkState();
    });
    syncAllRowPickVisuals();
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

    function clickClosest(e, selector) {
        var el = e.target;
        if (el && el.nodeType === 3) el = el.parentElement;
        if (!el || typeof el.closest !== 'function') return null;
        return el.closest(selector);
    }

    function openViewFromBtn(viewBtn) {
        var raw = viewBtn.dataset.row || '';
        var r = raw ? JSON.parse(atob(raw)) : {};
        viewModalRowData = r;
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
        cleanupStaleModalBackdrops();
        window.setTimeout(function() {
            if (viewModal) viewModal.show();
        }, 0);
    }

    function openEditFromBtn(editBtn) {
        var raw = editBtn.dataset.row || '';
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
        var editErpCompanyId = document.getElementById('editErpCompanyId');
        if (editErpCompanyId) editErpCompanyId.value = r.erp_company_id ? String(r.erp_company_id) : '';
        document.getElementById('modalTitle').textContent = 'Edit Agency';
        cleanupStaleModalBackdrops();
        window.setTimeout(function() {
            if (modal) modal.show();
        }, 0);
    }

    // mousedown (capture) — runs before Bootstrap closes the dropdown on click
    document.addEventListener('mousedown', function(e) {
        var item = clickClosest(e, '.ag-actions-menu .dropdown-item');
        if (!item || item.classList.contains('disabled') || item.classList.contains('permission-denied')) return;
        if (item.tagName === 'A' && item.getAttribute('href')) return;

        e.preventDefault();
        e.stopPropagation();
        closeAgencyActionDropdowns();

        if (item.classList.contains('btn-provision-pro')) {
            var proAgencyId = parseInt(item.getAttribute('data-agency-id') || '0', 10);
            if (proAgencyId) runProvisionPro(item, proAgencyId);
            return;
        }
        if (item.classList.contains('btn-provision-erp')) {
            var agencyId = parseInt(item.getAttribute('data-agency-id') || '0', 10);
            if (!agencyId) return;
            var erpStatus = (item.getAttribute('data-erp-status') || 'none').toLowerCase();
            if (erpStatus === 'ready' && !window.confirm('Re-provision ERP? This resets the ERP database and creates a fresh company with admin / 123456.')) return;
            openErpProvisionModal(item, agencyId, erpStatus);
            return;
        }
        if (item.classList.contains('ag-btn-erp-blocked')) {
            window.alert(item.getAttribute('data-blocked-reason') || 'ERP is not ready for this agency yet.');
            return;
        }
        if (item.classList.contains('btn-view')) {
            openViewFromBtn(item);
            return;
        }
        if (item.classList.contains('btn-edit')) {
            openEditFromBtn(item);
            return;
        }
        if (item.classList.contains('btn-delete')) {
            var delId = item.dataset.id;
            showConfirm('Delete this agency?').then(function(ok) {
                if (ok) apiDeleteIds([parseInt(delId, 10)]).then(function(r) { if (r.success) location.reload(); else showAlert(r.message || 'Delete failed'); }).catch(function(err) { showAlert('Request failed: ' + (err.message || err)); });
            });
            return;
        }
        if (item.classList.contains('btn-mark-paid')) {
            var aid = parseInt(item.dataset.id || '0', 10);
            var aname = (item.dataset.name || 'this agency').trim();
            if (!aid) { showAlert('Invalid agency ID'); return; }
            showConfirm('Mark paid for ' + aname + '? This will unsuspend the agency and mark its latest linked registration as Paid.').then(function(ok) {
                if (!ok) return;
                item.disabled = true;
                apiCall('PATCH', { ids: [aid], action: 'mark_paid' }).then(function(r) {
                    if (r && r.success) {
                        showAlert('Marked paid successfully.');
                        location.reload();
                    } else {
                        item.disabled = false;
                        showAlert((r && r.message) ? r.message : 'Mark paid failed');
                    }
                }).catch(function(err) {
                    item.disabled = false;
                    showAlert('Request failed: ' + (err.message || err));
                });
            });
        }
    }, true);

    // Bulk / non-dropdown actions (capture — before other document handlers)
    document.addEventListener('click', function(e) {
        var bulkBtn = clickClosest(e, '#btnBulkDelete, #btnBulkActivate, #btnBulkDeactivate, #btnBulkSuspend, #btnBulkUnsuspend, #btnBulkSync, #btnBulkRebuildDb, #btnBulkRunMigration, #btnBulkTestDbConnection, #btnRepairTenantLinks');
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
        if (clickClosest(e, '.btn-agency-control-link')) {
            var controlLink = clickClosest(e, '.btn-agency-control-link');
            var action = controlLink.getAttribute('data-action');
            var agencyId = parseInt(controlLink.getAttribute('data-agency-id') || '0', 10);
            if (action && agencyId) {
                apiCall('PATCH', { agency_ids: [agencyId], action: action }).catch(function() {});
            }
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
        var editErpCompanyIdFromView = document.getElementById('editErpCompanyId');
        if (editErpCompanyIdFromView) editErpCompanyIdFromView.value = r.erp_company_id ? String(r.erp_company_id) : '';
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
        var editErpCompanyIdEl = document.getElementById('editErpCompanyId');
        if (editErpCompanyIdEl) payload.erp_company_id = editErpCompanyIdEl.value ? parseInt(editErpCompanyIdEl.value, 10) : 0;
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

    var erpProvisionConfirmBtn = document.getElementById('erpProvisionConfirmBtn');
    if (erpProvisionConfirmBtn) {
        erpProvisionConfirmBtn.addEventListener('click', function() {
            var agencyId = parseInt((document.getElementById('erpProvisionAgencyId') || {}).value || '0', 10);
            var agencyInput = document.getElementById('erpProvisionAgencyId');
            var force = agencyInput && agencyInput.getAttribute('data-force') === '1';
            var planSelect = document.getElementById('erpProvisionPlanSelect');
            var planSlug = planSelect ? String(planSelect.value || 'professional') : 'professional';
            var modalEl = document.getElementById('erpProvisionModal');
            if (!agencyId) return;
            erpProvisionConfirmBtn.disabled = true;
            fetch(API_BASE + '/agencies-erp-plan.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ agency_id: agencyId, plan_slug: planSlug })
            }).then(function() {
                return fetch(API_BASE + '/agencies-provision-erp.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ agency_id: agencyId, plan_slug: planSlug, force: force ? 1 : 0 })
                });
            }).then(function(res) {
                var ct = (res.headers.get('content-type') || '').toLowerCase();
                if (!ct.includes('application/json')) {
                    throw new Error('Session expired or server error — please log in again and retry.');
                }
                return res.json();
            }).then(function(data) {
                erpProvisionConfirmBtn.disabled = false;
                var inst = getBootstrapModal(modalEl);
                if (inst) inst.hide();
                cleanupStaleModalBackdrops();
                if (!data || !data.success) {
                    showAlert((data && data.message) ? data.message : 'ERP provisioning failed');
                    return;
                }
                var seed = data.data && data.data.seed ? data.data.seed : null;
                var plan = (data.data && data.data.erp_plan_slug) ? data.data.erp_plan_slug : planSlug;
                var msg = 'ERP ready (' + plan + ') on ' + ((data.data && data.data.erp_db_name) ? data.data.erp_db_name : 'database');
                if (seed && seed.admin_password) {
                    var login = seed.admin_username || seed.admin_email || 'admin';
                    msg += '\nUsername: ' + login + '\nPassword: ' + seed.admin_password;
                }
                showAlert(msg);
                window.location.reload();
            }).catch(function() {
                erpProvisionConfirmBtn.disabled = false;
                showAlert('ERP provisioning request failed');
            });
        });
    }

})();
