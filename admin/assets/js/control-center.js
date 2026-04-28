/**
 * EN: Implements system administration/observability module behavior in `admin/assets/js/control-center.js`.
 * AR: ينفذ سلوك وحدة إدارة النظام والمراقبة في `admin/assets/js/control-center.js`.
 */
(function () {
    function getCcRole() {
        return (document.body && document.body.getAttribute('data-cc-role')) || 'VIEWER';
    }

    var liveKey = 'cc_live_mode';
    var live = document.getElementById('liveMode');
    var liveStatusBadge = document.getElementById('liveStatusBadge');
    var timer = null;
    var pausedForTyping = false;
    var toastHost = document.getElementById('ccToastHost');

    function showToast(message, kind) {
        if (!toastHost) {
            window.alert(message);
            return;
        }
        var toast = document.createElement('div');
        toast.className = 'cc-toast ' + (kind || 'warning');
        toast.textContent = (message === null || message === undefined) ? '' : String(message);
        toastHost.appendChild(toast);
        window.setTimeout(function () {
            toast.remove();
        }, 4200);
    }

    function ensureConfirmModal() {
        var existing = document.getElementById('ccConfirmOverlay');
        if (existing) return existing;
        var overlay = document.createElement('div');
        overlay.id = 'ccConfirmOverlay';
        overlay.className = 'modal cc-confirm-overlay hidden';
        overlay.innerHTML =
            '<div class="modal-content cc-confirm-card">' +
            '<h3 id="ccConfirmTitle">Confirm Action</h3>' +
            '<form id="ccConfirmForm" class="cc-form-grid">' +
            '<input id="ccConfirmActionField" class="cc-confirm-meta" type="text" placeholder="Action" />' +
            '<input id="ccConfirmTenantField" class="cc-confirm-meta" type="text" placeholder="Tenant ID" />' +
            '<input id="ccConfirmRequiredField" class="cc-confirm-meta" type="text" placeholder="Required Text" />' +
            '<input id="ccConfirmInput" class="cc-confirm-input" type="text" autocomplete="off" placeholder="Type confirmation text" />' +
            '<div class="modal-actions cc-confirm-actions">' +
            '<button type="submit" id="ccConfirmOk">Confirm</button>' +
            '<button type="button" id="ccConfirmCancel">Cancel</button>' +
            '</div>' +
            '</form>' +
            '</div>';
        document.body.appendChild(overlay);
        return overlay;
    }

    function showConfirmModal(options) {
        options = options || {};
        var overlay = ensureConfirmModal();
        var titleEl = document.getElementById('ccConfirmTitle');
        var formEl = document.getElementById('ccConfirmForm');
        var actionEl = document.getElementById('ccConfirmActionField');
        var tenantEl = document.getElementById('ccConfirmTenantField');
        var requiredEl = document.getElementById('ccConfirmRequiredField');
        var inputEl = document.getElementById('ccConfirmInput');
        var cancelBtn = document.getElementById('ccConfirmCancel');
        var okBtn = document.getElementById('ccConfirmOk');
        var requiredText = options.requireText ? String(options.requireText) : '';
        var confirmLabel = options.confirmLabel ? String(options.confirmLabel) : 'Confirm';
        var resolveFn = null;

        titleEl.textContent = options.title || 'Confirm Action';
        okBtn.textContent = confirmLabel;
        inputEl.value = '';
        actionEl.value = String(options.actionName || '');
        tenantEl.value = options.tenantId ? String(options.tenantId) : '';
        requiredEl.value = requiredText;
        inputEl.disabled = requiredText === '';
        inputEl.placeholder = requiredText !== '' ? ('type ' + requiredText + ' here') : 'no typed confirmation required';

        function close(result) {
            overlay.classList.add('hidden');
            cancelBtn.removeEventListener('click', onCancel);
            formEl.removeEventListener('submit', onSubmit);
            overlay.removeEventListener('click', onBackdrop);
            document.removeEventListener('keydown', onEsc);
            if (resolveFn) resolveFn(result);
        }

        function onCancel() {
            close({ confirmed: false, value: '' });
        }

        function onSubmit(e) {
            e.preventDefault();
            var v = inputEl.value || '';
            if (requiredText && v !== requiredText) {
                showToast('Please type exactly: ' + requiredText, 'warning');
                inputEl.focus();
                return;
            }
            close({ confirmed: true, value: v });
        }

        function onBackdrop(e) {
            if (e.target === overlay) {
                close({ confirmed: false, value: '' });
            }
        }

        function onEsc(e) {
            if (e.key === 'Escape') {
                close({ confirmed: false, value: '' });
            }
        }

        cancelBtn.addEventListener('click', onCancel);
        formEl.addEventListener('submit', onSubmit);
        overlay.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onEsc);
        overlay.classList.remove('hidden');
        if (requiredText) inputEl.focus();
        else okBtn.focus();

        return new Promise(function (resolve) {
            resolveFn = resolve;
        });
    }

    function setPausedTypingState(isPaused) {
        pausedForTyping = !!isPaused;
        if (!liveStatusBadge) return;
        if (pausedForTyping) liveStatusBadge.classList.remove('hidden');
        else liveStatusBadge.classList.add('hidden');
    }

    function startLive() {
        if (timer !== null) return;
        timer = window.setInterval(function () {
            var active = document.activeElement;
            var isTyping =
                active &&
                (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable);
            if (isTyping) {
                setPausedTypingState(true);
                return;
            }
            setPausedTypingState(false);
            window.location.reload();
        }, 5000);
    }

    function stopLive() {
        if (timer === null) return;
        window.clearInterval(timer);
        timer = null;
        setPausedTypingState(false);
    }

    if (live) {
        if (window.localStorage.getItem(liveKey) === '1') {
            live.checked = true;
            startLive();
        }
        live.addEventListener('change', function () {
            if (live.checked) {
                window.localStorage.setItem(liveKey, '1');
                startLive();
            } else {
                window.localStorage.setItem(liveKey, '0');
                stopLive();
            }
        });
    }

    var queryForm = document.getElementById('queryForm');
    var queryAction = document.getElementById('queryAction');
    var clearSql = document.getElementById('clearSql');
    var sqlEditor = document.getElementById('sqlEditor');
    if (queryForm && queryAction) {
        queryForm.querySelectorAll('button[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                queryAction.value = btn.getAttribute('data-action') || 'query_execute';
            });
        });
    }
    if (clearSql && sqlEditor) {
        clearSql.addEventListener('click', function () { sqlEditor.value = ''; });
    }

    var modal = document.getElementById('editTenantModal');
    var closeModal = document.getElementById('closeEditModal');
    var idEl = document.getElementById('editTenantId');
    var nameEl = document.getElementById('editTenantName');
    var domainEl = document.getElementById('editTenantDomain');
    var dbNameEl = document.getElementById('editTenantDbName');
    var dbHostEl = document.getElementById('editTenantDbHost');
    var dbUserEl = document.getElementById('editTenantDbUser');
    var statusEl = document.getElementById('editTenantStatus');
    var configDbModal = document.getElementById('configDbModal');
    var closeConfigDbModal = document.getElementById('closeConfigDbModal');
    var configDbForm = document.getElementById('configDbForm');
    var cfgTenantId = document.getElementById('cfgTenantId');
    var cfgDbName = document.getElementById('cfgDbName');
    var cfgDbHost = document.getElementById('cfgDbHost');
    var cfgDbUser = document.getElementById('cfgDbUser');
    var cfgDbPassword = document.getElementById('cfgDbPassword');
    var tenantIndex = {};
    function bindEditButtons() {
        document.querySelectorAll('.edit-btn').forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                if (!modal) return;
                idEl.value = btn.getAttribute('data-id') || '';
                nameEl.value = btn.getAttribute('data-name') || '';
                domainEl.value = btn.getAttribute('data-domain') || '';
                dbNameEl.value = btn.getAttribute('data-db-name') || '';
                dbHostEl.value = btn.getAttribute('data-db-host') || '';
                dbUserEl.value = btn.getAttribute('data-db-user') || '';
                statusEl.value = btn.getAttribute('data-status') || 'provisioning';
                modal.classList.remove('hidden');
            });
        });
    }
    bindEditButtons();
    if (closeModal && modal) {
        closeModal.addEventListener('click', function () { modal.classList.add('hidden'); });
    }

    function bindDangerForms() {
        document.querySelectorAll('.danger-form').forEach(function (form) {
            if (form.dataset.bound === '1') return;
            form.dataset.bound = '1';
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === '1') {
                    form.dataset.confirmed = '0';
                    return;
                }
                e.preventDefault();
                var confirmText = form.getAttribute('data-confirm') || '';
                var promptText = form.getAttribute('data-prompt') || '';
                var requiredText = '';
                var m = promptText.match(/Type\s+([A-Z_]+)\s+to\s+continue/i);
                if (m && m[1]) requiredText = m[1].toUpperCase();
                showConfirmModal({
                    title: 'Confirm Dangerous Action',
                    message: confirmText || promptText || 'Please confirm this action.',
                    actionName: (form.querySelector('input[name="action"]') || {}).value || '',
                    tenantId: (form.querySelector('input[name="tenant_id"]') || {}).value || '',
                    requireText: requiredText,
                    confirmLabel: 'Confirm',
                    danger: true
                }).then(function (result) {
                    if (!result || !result.confirmed) return;
                    var input = form.querySelector('input[name="confirm_text"]');
                    if (input && requiredText) input.value = requiredText;
                    form.dataset.confirmed = '1';
                    form.submit();
                });
            });
        });
    }
    bindDangerForms();

    document.querySelectorAll('.emergency-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === '1') {
                form.dataset.confirmed = '0';
                return;
            }
            e.preventDefault();
            var label = form.getAttribute('data-label') || 'emergency action';
            showConfirmModal({
                title: 'Emergency Action',
                message: 'You are about to run: ' + label,
                actionName: (form.querySelector('input[name="emergency_code"]') || {}).value || 'emergency_action',
                requireText: 'CONFIRM',
                confirmLabel: 'Continue',
                danger: true
            }).then(function (step1) {
                if (!step1 || !step1.confirmed) return;
                showConfirmModal({
                    title: 'Final Confirmation',
                    message: 'Type CONFIRM again to execute this emergency action.',
                    actionName: (form.querySelector('input[name="emergency_code"]') || {}).value || 'emergency_action',
                    requireText: 'CONFIRM',
                    confirmLabel: 'Execute',
                    danger: true
                }).then(function (step2) {
                    if (!step2 || !step2.confirmed) return;
                    var i1 = form.querySelector('input[name="confirm_text"]');
                    var i2 = form.querySelector('input[name="confirm_text_second"]');
                    if (i1) i1.value = 'CONFIRM';
                    if (i2) i2.value = 'CONFIRM';
                    form.dataset.confirmed = '1';
                    form.submit();
                });
            });
        });
    });

    var gatewaySearch = document.getElementById('gatewaySearch');
    var gatewayStatus = document.getElementById('gatewayStatusFilter');
    var gatewayTenant = document.getElementById('gatewayTenantFilter');
    var gatewayTable = document.getElementById('gatewayTable');
    function applyGatewayFilters() {
        if (!gatewayTable) return;
        var q = (gatewaySearch ? gatewaySearch.value : '').toLowerCase().trim();
        var s = gatewayStatus ? gatewayStatus.value : 'all';
        var t = gatewayTenant ? gatewayTenant.value.trim() : '';
        gatewayTable.querySelectorAll('tbody tr').forEach(function (row) {
            var text = row.textContent.toLowerCase();
            var status = row.getAttribute('data-status') || '';
            var tenant = row.getAttribute('data-tenant') || '';
            var okQ = q === '' || text.indexOf(q) !== -1;
            var okS = s === 'all' || status === s;
            var okT = t === '' || tenant === t;
            row.style.display = (okQ && okS && okT) ? '' : 'none';
        });
    }
    if (gatewaySearch) gatewaySearch.addEventListener('input', applyGatewayFilters);
    if (gatewayStatus) gatewayStatus.addEventListener('change', applyGatewayFilters);
    if (gatewayTenant) gatewayTenant.addEventListener('input', applyGatewayFilters);

    // Sidebar tabs: smooth scroll + active state + hash sync
    var sectionLinks = document.querySelectorAll('.cc-sidebar a[href^="#"]');
    var sections = [];
    sectionLinks.forEach(function (link) {
        var targetId = (link.getAttribute('href') || '').replace('#', '');
        if (!targetId) return;
        var section = document.getElementById(targetId);
        if (!section) return;
        sections.push({ id: targetId, link: link, node: section });

        link.addEventListener('click', function (e) {
            e.preventDefault();
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            window.history.replaceState(null, '', '#' + targetId);
            setActiveLink(targetId);
        });
    });

    function setActiveLink(targetId) {
        sectionLinks.forEach(function (lnk) {
            var hrefId = (lnk.getAttribute('href') || '').replace('#', '');
            if (hrefId === targetId) lnk.classList.add('active');
            else lnk.classList.remove('active');
        });
    }

    if (sections.length > 0) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    setActiveLink(entry.target.id);
                }
            });
        }, { rootMargin: '-25% 0px -60% 0px', threshold: [0.1, 0.25, 0.5] });

        sections.forEach(function (s) {
            observer.observe(s.node);
        });

        var initialHash = (window.location.hash || '').replace('#', '');
        if (initialHash) {
            var target = document.getElementById(initialHash);
            if (target) {
                setActiveLink(initialHash);
            }
        } else {
            setActiveLink(sections[0].id);
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = (text === null || text === undefined) ? '' : String(text);
        return div.innerHTML;
    }

    function numericFromMixedId(value) {
        if (value === null || value === undefined) return 0;
        var s = String(value).trim();
        if (!s) return 0;
        if (/^\d+$/.test(s)) return Number(s) || 0;
        var m = s.match(/(\d+)/);
        return m ? (Number(m[1]) || 0) : 0;
    }

    function agencyDisplayId(row) {
        if (row && row.display_id) return String(row.display_id);
        var agencyId = numericFromMixedId(row && row.agency_id);
        if (agencyId > 0) return 'AG' + String(agencyId).padStart(4, '0');
        var rawId = numericFromMixedId(row && row.id);
        return rawId > 0 ? String(rawId) : '0';
    }

    function apiPost(payload) {
        return fetch(window.location.pathname, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = null;
                try {
                    data = JSON.parse(text);
                } catch (_) {
                    data = null;
                }
                if (!res.ok) {
                    var msg = (data && data.message) ? data.message : ('HTTP ' + res.status);
                    throw new Error(msg);
                }
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid JSON response');
                }
                return data;
            });
        });
    }

    function bindTenantActionButtons() {
        // Intentionally no JS interception for Test Connection:
        // fallback form submit is the source of truth for reliability.
    }

    function setConnStatus(tenantId, text, kind) {
        var el = document.querySelector('.conn-status[data-tenant-id="' + tenantId + '"]');
        if (!el) return;
        var full = (text || '').toString();
        var shortText = full;
        if (kind === 'err') {
            var lower = full.toLowerCase();
            if (lower.indexOf('credentials are incomplete') !== -1) {
                shortText = 'DB config incomplete';
            } else if (full.length > 60) {
                shortText = full.slice(0, 57) + '...';
            }
        }
        el.textContent = shortText;
        el.title = full;
        el.classList.remove('ok', 'err', 'pending');
        if (kind) el.classList.add(kind);
    }

    function configureDbForTenant(tenant) {
        var tenantId = Number(tenant.id || 0);
        if (!tenantId) return;
        if (!configDbModal) return;
        cfgTenantId.value = String(tenantId);
        cfgDbName.value = String(tenant.database_name || '');
        cfgDbHost.value = String(tenant.db_host || '');
        cfgDbUser.value = String(tenant.db_user || '');
        cfgDbPassword.value = '';
        configDbModal.classList.remove('hidden');
    }

    function bindTestConnectionForms() {
        document.querySelectorAll('form.test-conn-form').forEach(function (form) {
            if (form.dataset.bound === '1') return;
            form.dataset.bound = '1';
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var tenantInput = form.querySelector('input[name="tenant_id"]');
                var tenantId = parseInt((tenantInput && tenantInput.value) ? tenantInput.value : '0', 10);
                if (!tenantId) return;
                setConnStatus(tenantId, 'Testing...', 'pending');
                apiPost({ action: 'test_connection', tenant_id: tenantId })
                    .then(function (data) {
                        if (!data.success) {
                            var failMsg = data.message || 'Failed';
                            setConnStatus(tenantId, failMsg, 'err');
                            showToast('Tenant #' + tenantId + ' connection failed: ' + failMsg, 'danger');
                            return;
                        }
                        setConnStatus(tenantId, 'Connected', 'ok');
                        showToast('Tenant #' + tenantId + ' connection successful', 'safe');
                    })
                    .catch(function (err) {
                        var errMsg = (err && err.message) ? err.message : 'Request failed';
                        setConnStatus(tenantId, errMsg, 'err');
                        showToast('Tenant #' + tenantId + ' connection failed: ' + errMsg, 'danger');
                    });
            });
        });
    }

    function renderTenants(rows) {
        var tbody = document.querySelector('#tenant-control table tbody');
        var csrf = document.body ? (document.body.getAttribute('data-cc-csrf') || '') : '';
        var role = getCcRole();
        var isSuper = role === 'SUPER_ADMIN';
        var isAdminPlus = isSuper || role === 'ADMIN';
        if (!tbody) return;
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7">No tenants found.</td></tr>';
            return;
        }
        tenantIndex = {};
        rows.forEach(function (t) {
            var id = Number(t.id || 0);
            if (id > 0) tenantIndex[id] = t;
        });

        tbody.innerHTML = rows.map(function (t) {
            var id = Number(t.id || 0);
            var shownId = agencyDisplayId(t);
            var status = String(t.status || '');
            var hasDbConfig = !!t.has_db_config;
            var btnTitle = hasDbConfig ? 'Test tenant database connection' : 'DB config missing: click to see exact error';
            var editBtn = isAdminPlus
                ? ('<button type="button" class="edit-btn" ' +
                'data-id="' + id + '" ' +
                'data-name="' + escapeHtml(t.name || '') + '" ' +
                'data-domain="' + escapeHtml(t.domain || '') + '" ' +
                'data-db-name="' + escapeHtml(t.database_name || '') + '" ' +
                'data-db-host="' + escapeHtml(t.db_host || '') + '" ' +
                'data-db-user="' + escapeHtml(t.db_user || '') + '" ' +
                'data-status="' + escapeHtml(status) + '">Edit</button>')
                : '';
            var toggleForm = '';
            if (status === 'active' && isSuper) {
                toggleForm = '<form method="post" class="inline danger-form" data-prompt="Type SUSPEND to continue">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '">' +
                '<input type="hidden" name="action" value="tenant_toggle">' +
                '<input type="hidden" name="tenant_id" value="' + id + '">' +
                '<input type="hidden" name="status" value="' + escapeHtml(status) + '">' +
                '<input type="hidden" name="confirm_text" value="">' +
                '<button type="submit">Suspend</button></form>';
            } else if (status !== 'active' && isAdminPlus) {
                toggleForm = '<form method="post" class="inline danger-form" data-prompt="Type ACTIVATE to continue">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '">' +
                '<input type="hidden" name="action" value="tenant_toggle">' +
                '<input type="hidden" name="tenant_id" value="' + id + '">' +
                '<input type="hidden" name="status" value="' + escapeHtml(status) + '">' +
                '<input type="hidden" name="confirm_text" value="">' +
                '<button type="submit">Activate</button></form>';
            }
            var deleteForm = isSuper
                ? ('<form method="post" class="inline danger-form" data-confirm="Delete tenant ' + id + '?" data-prompt="Type DELETE to continue">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '">' +
                '<input type="hidden" name="action" value="tenant_delete">' +
                '<input type="hidden" name="tenant_id" value="' + id + '">' +
                '<input type="hidden" name="confirm_text" value="">' +
                '<button type="submit">Delete</button></form>')
                : '';
            var cfgBtn = isAdminPlus ? ('<button type="button" class="cfg-db-btn" data-tenant-id="' + id + '">Configure DB</button>') : '';
            return '<tr>' +
                '<td>' + escapeHtml(shownId) + '</td>' +
                '<td>' + escapeHtml(t.name || '') + '</td>' +
                '<td>' + escapeHtml(t.domain || '') + '</td>' +
                '<td><span class="badge ' + escapeHtml(status) + '">' + escapeHtml(status) + '</span></td>' +
                '<td><span class="db-badge ' + (hasDbConfig ? 'ok' : 'missing') + '">' + (hasDbConfig ? 'configured' : 'missing') + '</span></td>' +
                '<td>' + escapeHtml(t.created_at || '') + '</td>' +
                '<td class="row-actions">' +
                editBtn +
                toggleForm +
                deleteForm +
                cfgBtn +
                '<form method="post" class="inline test-conn-form">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '">' +
                '<input type="hidden" name="action" value="db_test">' +
                '<input type="hidden" name="tenant_id" value="' + id + '">' +
                '<button type="submit" class="test-conn-btn" data-tenant-id="' + id + '" title="' + escapeHtml(btnTitle) + '">Test Connection</button>' +
                '</form>' +
                '<span class="conn-status" data-tenant-id="' + id + '"></span>' +
                '</td>' +
                '</tr>';
        }).join('');
        document.querySelectorAll('.cfg-db-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tenantId = Number(btn.getAttribute('data-tenant-id') || 0);
                var tenant = tenantIndex[tenantId] || null;
                if (!tenant) return;
                configureDbForTenant(tenant);
            });
        });
        bindTenantActionButtons();
        bindTestConnectionForms();
        bindEditButtons();
        bindDangerForms();
    }

    function renderDbControl(rows) {
        var tbody = document.querySelector('#db-control table tbody');
        var csrf = document.body ? (document.body.getAttribute('data-cc-csrf') || '') : '';
        var role = getCcRole();
        var isSuper = role === 'SUPER_ADMIN';
        var isAdminPlus = isSuper || role === 'ADMIN';
        if (!tbody) return;
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3">No tenants available.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (t) {
            var id = Number(t.id || 0);
            var shownId = agencyDisplayId(t);
            var dbHost = String(t.db_host || 'localhost');
            var dbName = String(t.database_name || '-');
            var hasDbConfig = !!t.has_db_config;
            var mig = isAdminPlus
                ? ('<form method="post" class="inline">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '">' +
                '<input type="hidden" name="action" value="run_migration">' +
                '<input type="hidden" name="tenant_id" value="' + id + '">' +
                '<button type="submit">Run Migration</button></form>')
                : '';
            var rebuild = isSuper
                ? ('<form method="post" class="inline danger-form" data-prompt="Type REBUILD to continue">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '">' +
                '<input type="hidden" name="action" value="rebuild_schema">' +
                '<input type="hidden" name="tenant_id" value="' + id + '">' +
                '<input type="hidden" name="confirm_text" value="">' +
                '<button type="submit">Rebuild Schema</button></form>')
                : '';
            var backup = isSuper
                ? ('<form method="post" class="inline danger-form" data-prompt="Type BACKUP to continue">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '">' +
                '<input type="hidden" name="action" value="backup_tenant_sync">' +
                '<input type="hidden" name="tenant_id" value="' + id + '">' +
                '<input type="hidden" name="confirm_text" value="">' +
                '<button type="submit" title="Requires server backup config">Backup DB</button></form>' +
                '<form method="post" class="inline danger-form" data-prompt="Type RESTORE to continue">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '">' +
                '<input type="hidden" name="action" value="restore_tenant_sync">' +
                '<input type="hidden" name="tenant_id" value="' + id + '">' +
                '<input type="text" name="backup_file" placeholder="file.sql" required style="max-width:120px">' +
                '<input type="hidden" name="confirm_text" value="">' +
                '<button type="submit">Restore</button></form>')
                : '';
            return '<tr>' +
                '<td>#' + escapeHtml(shownId) + ' ' + escapeHtml(t.domain || '') + '</td>' +
                '<td>' + escapeHtml(dbHost) + ' / ' + escapeHtml(dbName) + ' / **** <span class="db-badge ' + (hasDbConfig ? 'ok' : 'missing') + '">' + (hasDbConfig ? 'configured' : 'missing') + '</span></td>' +
                '<td class="row-actions">' +
                '<form method="post" class="inline">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '">' +
                '<input type="hidden" name="action" value="db_test">' +
                '<input type="hidden" name="tenant_id" value="' + id + '">' +
                '<button type="submit">Test Connection</button>' +
                '</form>' +
                mig + rebuild + backup +
                '</td>' +
                '</tr>';
        }).join('');
        bindDangerForms();
    }

    function loadTenants() {
        apiPost({ action: 'get_tenants' })
            .then(function (data) {
                if (data && data.success) {
                    if (data.role && document.body) {
                        document.body.setAttribute('data-cc-role', String(data.role));
                    }
                    var rows = data.tenants || [];
                    renderTenants(rows);
                    renderDbControl(rows);
                }
            })
            .catch(function (err) {
                console.warn('loadTenants failed:', err && err.message ? err.message : err);
                // Keep server-rendered table usable even if fetch fails.
            });
    }

    var createForm = document.querySelector('#tenant-control form.cc-form-grid');
    if (createForm) {
        createForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(createForm);
            var payload = {
                action: 'create_tenant',
                name: String(fd.get('name') || ''),
                domain: String(fd.get('domain') || ''),
                database_name: String(fd.get('database_name') || ''),
                db_host: String(fd.get('db_host') || ''),
                db_user: String(fd.get('db_user') || ''),
                db_password: String(fd.get('db_password') || ''),
                status: String(fd.get('status') || 'active')
            };
            apiPost(payload)
                .then(function (data) {
                    if (!data.success) {
                        showToast(data.message || 'Create tenant failed', 'danger');
                        return;
                    }
                    createForm.reset();
                    showToast('Tenant created successfully', 'safe');
                    loadTenants();
                })
                .catch(function (err) {
                    showToast((err && err.message) ? err.message : 'Create tenant request failed', 'danger');
                });
        });
    }

    function sqlFirstToken(sql) {
        var s = String(sql || '').trim().replace(/^\(+/, '');
        return (s.split(/\s+/)[0] || '').toLowerCase();
    }
    function sqlIsReadOnly(sql) {
        return ['select', 'show', 'describe', 'explain'].indexOf(sqlFirstToken(sql)) !== -1;
    }

    if (queryForm) {
        queryForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(queryForm);
            var sqlText = String(fd.get('sql') || '').trim();
            var mode = String(fd.get('execution_mode') || 'SAFE').toUpperCase();
            var role = getCcRole();
            var confirmHidden = document.getElementById('queryConfirmWrite');
            if (confirmHidden) confirmHidden.value = '0';
            if (mode === 'SYSTEM' && role !== 'SUPER_ADMIN') {
                showToast('SYSTEM mode requires SUPER_ADMIN.', 'warning');
                return;
            }
            var isReadOnly = sqlIsReadOnly(sqlText);
            if (isReadOnly) {
                runQueryRequest(fd, false);
                return;
            }
            if (mode === 'SAFE') {
                showToast('SAFE mode is read-only. Switch to STRICT (scoped writes) or SYSTEM (super-admin).', 'warning');
                return;
            }
            showConfirmModal({
                title: mode === 'STRICT' ? 'Confirm STRICT write' : 'Confirm SYSTEM write',
                message: 'Irreversible data changes may occur. Ensure SQL is correct and tenant scope is intentional.',
                actionName: 'run_query',
                tenantId: parseInt(String(fd.get('query_tenant_id') || '0'), 10) || 0,
                requireText: 'EXECUTE',
                confirmLabel: 'Run write query',
                danger: true
            }).then(function (decision) {
                if (!decision || !decision.confirmed) return;
                if (confirmHidden) confirmHidden.value = '1';
                runQueryRequest(fd, true);
            });
        });
    }

    function runQueryRequest(fd, confirmWrite) {
        var payload = {
                action: 'run_query',
                query: String(fd.get('sql') || '').trim(),
                tenant_id: parseInt(String(fd.get('query_tenant_id') || '0'), 10) || 0,
                mode: String(fd.get('execution_mode') || 'SAFE'),
                confirm_write: confirmWrite ? '1' : '0'
        };
        apiPost(payload)
                .then(function (data) {
                    var qcw = document.getElementById('queryConfirmWrite');
                    if (qcw) qcw.value = '0';
                    if (!data.success) {
                        showToast(data.message || 'Query execution failed', 'danger');
                        return;
                    }
                    var meta = document.querySelector('.cc-result-meta');
                    if (meta) {
                        meta.innerHTML = '<span>Executed</span><span>Execution: ' + (data.execution_ms || 0) + ' ms</span><span>Rows affected: ' + (data.rows_affected || 0) + '</span>';
                    }

                    var wrap = queryForm.parentElement ? queryForm.parentElement.querySelector('.cc-table-wrap') : null;
                    if (!wrap) return;
                    var resultRows = Array.isArray(data.result) ? data.result : [];
                    if (resultRows.length === 0) {
                        wrap.innerHTML = '<div class="cc-alert safe">No result rows.</div>';
                        showToast('Query executed successfully', 'safe');
                        return;
                    }
                    var headers = Object.keys(resultRows[0]);
                    var thead = '<thead><tr>' + headers.map(function (h) { return '<th>' + escapeHtml(h) + '</th>'; }).join('') + '</tr></thead>';
                    var tbody = '<tbody>' + resultRows.map(function (row) {
                        return '<tr>' + headers.map(function (h) { return '<td>' + escapeHtml(row[h]) + '</td>'; }).join('') + '</tr>';
                    }).join('') + '</tbody>';
                    wrap.innerHTML = '<table>' + thead + tbody + '</table>';
                    showToast('Query executed successfully', 'safe');
                })
                .catch(function (err) {
                    showToast((err && err.message) ? err.message : 'Query request failed', 'danger');
                });
    }

    if (closeConfigDbModal && configDbModal) {
        closeConfigDbModal.addEventListener('click', function () {
            configDbModal.classList.add('hidden');
        });
    }

    if (configDbForm) {
        configDbForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var tenantId = parseInt(cfgTenantId.value || '0', 10);
            var dbName = (cfgDbName.value || '').trim();
            var dbHost = (cfgDbHost.value || '').trim();
            var dbUser = (cfgDbUser.value || '').trim();
            var dbPassword = cfgDbPassword.value || '';
            if (!tenantId || dbName === '' || dbUser === '') {
                showToast('tenant_id, database_name and db_user are required', 'warning');
                return;
            }
            apiPost({
                action: 'configure_db',
                tenant_id: tenantId,
                database_name: dbName,
                db_host: dbHost,
                db_user: dbUser,
                db_password: dbPassword
            }).then(function (data) {
                if (!data.success) {
                    showToast(data.message || 'Failed to update DB configuration', 'danger');
                    return;
                }
                configDbModal.classList.add('hidden');
                showToast('Tenant DB configuration updated', 'safe');
                loadTenants();
            }).catch(function (err) {
                showToast(err && err.message ? err.message : 'Configure DB request failed', 'danger');
            });
        });
    }

    bindTestConnectionForms();
    loadTenants();
})();

