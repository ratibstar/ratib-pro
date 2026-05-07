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
            try { console.warn(message); } catch (_) {}
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

    function showModernAlert(message, kind) {
        if (!message) return;
        var host = document.getElementById('ccModernAlertHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'ccModernAlertHost';
            host.style.position = 'fixed';
            host.style.right = '16px';
            host.style.bottom = '16px';
            host.style.zIndex = '2000';
            host.style.maxWidth = '420px';
            host.style.display = 'flex';
            host.style.flexDirection = 'column';
            host.style.gap = '8px';
            document.body.appendChild(host);
        }
        var card = document.createElement('div');
        card.style.background = '#0f172a';
        card.style.border = '1px solid #334155';
        card.style.color = '#e5e7eb';
        card.style.borderRadius = '12px';
        card.style.padding = '12px 14px';
        card.style.boxShadow = '0 10px 28px rgba(0,0,0,.35)';
        card.style.display = 'flex';
        card.style.alignItems = 'start';
        card.style.gap = '10px';
        if (kind === 'safe') {
            card.style.borderColor = '#166534';
        } else if (kind === 'warning') {
            card.style.borderColor = '#92400e';
        } else if (kind === 'danger') {
            card.style.borderColor = '#991b1b';
        }
        var msg = document.createElement('div');
        msg.style.flex = '1';
        msg.textContent = String(message);
        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.textContent = 'x';
        closeBtn.style.background = 'transparent';
        closeBtn.style.border = '0';
        closeBtn.style.color = '#94a3b8';
        closeBtn.style.cursor = 'pointer';
        closeBtn.addEventListener('click', function () { card.remove(); });
        card.appendChild(msg);
        card.appendChild(closeBtn);
        host.appendChild(card);
        window.setTimeout(function () {
            if (card && card.parentNode) card.remove();
        }, 6000);
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
                (active.tagName === 'INPUT' ||
                 active.tagName === 'TEXTAREA' ||
                 active.tagName === 'SELECT' ||
                 active.tagName === 'IFRAME' ||
                 active.isContentEditable);
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

    var editPanel = document.getElementById('ccEditTenantPanel');
    var closeEditPanel = document.getElementById('ccCloseEditPanel');
    var idEl = document.getElementById('editTenantId');
    var nameEl = document.getElementById('editTenantName');
    var domainEl = document.getElementById('editTenantDomain');
    var dbNameEl = document.getElementById('editTenantDbName');
    var dbHostEl = document.getElementById('editTenantDbHost');
    var dbUserEl = document.getElementById('editTenantDbUser');
    var statusEl = document.getElementById('editTenantStatus');
    var configDbPanel = document.getElementById('ccDbConfigPanel');
    var closeConfigDbPanel = document.getElementById('ccCloseDbPanel');
    var configDbForm = document.getElementById('ccDbConfigForm');
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
                if (!editPanel) return;
                idEl.value = btn.getAttribute('data-id') || '';
                nameEl.value = btn.getAttribute('data-name') || '';
                domainEl.value = btn.getAttribute('data-domain') || '';
                dbNameEl.value = btn.getAttribute('data-db-name') || '';
                dbHostEl.value = btn.getAttribute('data-db-host') || '';
                dbUserEl.value = btn.getAttribute('data-db-user') || '';
                statusEl.value = btn.getAttribute('data-status') || 'provisioning';
                editPanel.classList.remove('hidden');
            });
        });
    }
    bindEditButtons();
    if (closeEditPanel && editPanel) {
        closeEditPanel.addEventListener('click', function () { editPanel.classList.add('hidden'); });
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
                var promptText = form.getAttribute('data-prompt') || '';
                var requiredText = '';
                var m = promptText.match(/Type\s+([A-Z_]+)\s+to\s+continue/i);
                if (m && m[1]) requiredText = m[1].toUpperCase();
                if (!requiredText) {
                    showToast('Missing confirmation keyword', 'danger');
                    return;
                }
                var input = form.querySelector('input[name="confirm_text"]');
                if (!input) {
                    showToast('Missing confirm_text field', 'danger');
                    return;
                }
                // Inline confirm UI (no modal)
                var wrap = form.querySelector('.cc-confirm-inline');
                if (!wrap) {
                    wrap = document.createElement('span');
                    wrap.className = 'cc-confirm-inline';
                    var hint = document.createElement('span');
                    hint.className = 'cc-muted';
                    hint.textContent = 'Type ' + requiredText + ' then press again';
                    var txt = document.createElement('input');
                    txt.type = 'text';
                    txt.autocomplete = 'off';
                    txt.placeholder = requiredText;
                    txt.addEventListener('input', function () {
                        input.value = String(txt.value || '');
                    });
                    wrap.appendChild(hint);
                    wrap.appendChild(txt);
                    form.appendChild(wrap);
                    window.setTimeout(function () { try { txt.focus(); } catch (_) {} }, 0);
                    return;
                }
                var typed = String(input.value || '').trim().toUpperCase();
                if (typed !== requiredText) {
                    showToast('Confirmation text must be: ' + requiredText, 'warning');
                    return;
                }
                form.dataset.confirmed = '1';
                form.submit();
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
            var i1 = form.querySelector('input[name="confirm_text"]');
            var i2 = form.querySelector('input[name="confirm_text_second"]');
            if (!i1 || !i2) {
                showToast('Emergency confirmation inputs missing', 'danger');
                return;
            }
            var wrap = form.querySelector('.cc-confirm-inline');
            if (!wrap) {
                wrap = document.createElement('span');
                wrap.className = 'cc-confirm-inline';
                var hint = document.createElement('span');
                hint.className = 'cc-muted';
                hint.textContent = 'Type CONFIRM then press again';
                var txt = document.createElement('input');
                txt.type = 'text';
                txt.autocomplete = 'off';
                txt.placeholder = 'CONFIRM';
                txt.addEventListener('input', function () {
                    i1.value = String(txt.value || '');
                    i2.value = String(txt.value || '');
                });
                wrap.appendChild(hint);
                wrap.appendChild(txt);
                form.appendChild(wrap);
                window.setTimeout(function () { try { txt.focus(); } catch (_) {} }, 0);
                return;
            }
            var typed = String(i1.value || '').trim().toUpperCase();
            if (typed !== 'CONFIRM') {
                showToast('Confirmation text must be: CONFIRM', 'warning');
                return;
            }
            form.dataset.confirmed = '1';
            form.submit();
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

    // Sidebar tabs: section switching (no page scroll)
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
            window.history.replaceState(null, '', '#' + targetId);
            setActiveSection(targetId);
        });
    });

    function setActiveLink(targetId) {
        sectionLinks.forEach(function (lnk) {
            var hrefId = (lnk.getAttribute('href') || '').replace('#', '');
            if (hrefId === targetId) lnk.classList.add('active');
            else lnk.classList.remove('active');
        });
    }

    function setActiveSection(targetId) {
        if (!targetId) return;
        sections.forEach(function (s) {
            if (s.id === targetId) {
                s.node.classList.remove('cc-section-hidden');
                s.node.classList.add('cc-section-active');
            } else {
                s.node.classList.add('cc-section-hidden');
                s.node.classList.remove('cc-section-active');
            }
        });
        setActiveLink(targetId);
    }

    function initSectionVisibility() {
        if (!sections.length) return;
        sections.forEach(function (s) {
            s.node.classList.add('cc-section-hidden');
            s.node.classList.remove('cc-section-active');
        });
        var initialHash = (window.location.hash || '').replace('#', '');
        var pick = initialHash && document.getElementById(initialHash) ? initialHash : sections[0].id;
        setActiveSection(pick);
    }

    window.addEventListener('hashchange', function () {
        var h = (window.location.hash || '').replace('#', '');
        if (h) setActiveSection(h);
    });

    initSectionVisibility();

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = (text === null || text === undefined) ? '' : String(text);
        return div.innerHTML;
    }

    function latinDigits(input) {
        var s = String(input === null || input === undefined ? '' : input);
        return s.replace(/[\u0660-\u0669]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); })
            .replace(/[\u06F0-\u06F9]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); });
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
        if (!configDbPanel) return;
        cfgTenantId.value = String(tenantId);
        cfgDbName.value = String(tenant.database_name || '');
        cfgDbHost.value = String(tenant.db_host || '');
        cfgDbUser.value = String(tenant.db_user || '');
        cfgDbPassword.value = '';
        configDbPanel.classList.remove('hidden');
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

    function bindConfigButtons() {
        document.querySelectorAll('.cfg-db-btn').forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                var tenantId = Number(btn.getAttribute('data-tenant-id') || 0);
                var tenant = tenantIndex[tenantId] || {
                    id: tenantId,
                    database_name: btn.getAttribute('data-db-name') || '',
                    db_host: btn.getAttribute('data-db-host') || '',
                    db_user: btn.getAttribute('data-db-user') || ''
                };
                if (!tenant || !tenant.id) return;
                configureDbForTenant(tenant);
            });
        });
    }
    // Delegated fallback for any late-rendered Configure DB buttons.
    document.addEventListener('click', function (evt) {
        var btn = evt.target && evt.target.closest ? evt.target.closest('.cfg-db-btn') : null;
        if (!btn) return;
        var tenantId = Number(btn.getAttribute('data-tenant-id') || 0);
        var tenant = tenantIndex[tenantId] || {
            id: tenantId,
            database_name: btn.getAttribute('data-db-name') || '',
            db_host: btn.getAttribute('data-db-host') || '',
            db_user: btn.getAttribute('data-db-user') || ''
        };
        if (!tenant || !tenant.id) return;
        configureDbForTenant(tenant);
    });

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
                '<td>' + escapeHtml(latinDigits(t.created_at || '')) + '</td>' +
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
        bindConfigButtons();
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
            var existingConfirm = queryForm.querySelector('.cc-confirm-inline');
            if (!existingConfirm) {
                var wrap = document.createElement('div');
                wrap.className = 'cc-confirm-inline';
                var hint = document.createElement('span');
                hint.className = 'cc-muted';
                hint.textContent = 'Type EXECUTE then submit again';
                var txt = document.createElement('input');
                txt.type = 'text';
                txt.autocomplete = 'off';
                txt.placeholder = 'EXECUTE';
                wrap.appendChild(hint);
                wrap.appendChild(txt);
                queryForm.appendChild(wrap);
                window.setTimeout(function () { try { txt.focus(); } catch (_) {} }, 0);
                return;
            }
            var typedInput = existingConfirm.querySelector('input');
            var typed = String((typedInput && typedInput.value) || '').trim().toUpperCase();
            if (typed !== 'EXECUTE') {
                showToast('Confirmation text must be: EXECUTE', 'warning');
                return;
            }
            if (confirmHidden) confirmHidden.value = '1';
            runQueryRequest(fd, true);
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

    if (closeConfigDbPanel && configDbPanel) {
        closeConfigDbPanel.addEventListener('click', function () {
            configDbPanel.classList.add('hidden');
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
                if (configDbPanel) configDbPanel.classList.add('hidden');
                showToast('Tenant DB configuration updated', 'safe');
                window.location.reload();
            }).catch(function (err) {
                showToast(err && err.message ? err.message : 'Configure DB request failed', 'danger');
            });
        });
    }

    bindTestConnectionForms();
    bindConfigButtons();
    var inactiveIssuesBtn = document.getElementById('ccInactiveIssuesBtn');
    var tenantIssuesPanel = document.getElementById('ccTenantIssuesPanel');
    function setTenantIssuesOpen(open) {
        if (!inactiveIssuesBtn || !tenantIssuesPanel) return;
        tenantIssuesPanel.classList.toggle('hidden', !open);
        inactiveIssuesBtn.classList.toggle('is-open', !!open);
        inactiveIssuesBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (inactiveIssuesBtn && tenantIssuesPanel) {
        inactiveIssuesBtn.addEventListener('click', function () {
            setTenantIssuesOpen(tenantIssuesPanel.classList.contains('hidden'));
        });
        inactiveIssuesBtn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                setTenantIssuesOpen(tenantIssuesPanel.classList.contains('hidden'));
            }
        });
    }
    var tenantIssueSinglePanel = document.getElementById('ccTenantIssueSinglePanel');
    var tenantIssueSingleTitle = document.getElementById('ccTenantIssueSingleTitle');
    var tenantIssueSingleText = document.getElementById('ccTenantIssueSingleText');
    document.addEventListener('click', function (e) {
        var badge = e.target && e.target.closest ? e.target.closest('.cc-issue-badge-btn') : null;
        if (!badge) return;
        e.preventDefault();
        var issue = String(badge.getAttribute('data-issue') || '').trim();
        var tenantId = String(badge.getAttribute('data-tenant-id') || '').trim();
        if (tenantIssueSinglePanel && tenantIssueSingleText) {
            if (tenantIssueSingleTitle) {
                tenantIssueSingleTitle.textContent = tenantId ? ('Tenant #' + tenantId + ' issue') : 'Selected tenant issue';
            }
            tenantIssueSingleText.textContent = issue || 'No issue details found for this tenant';
            tenantIssueSinglePanel.classList.remove('hidden');
            tenantIssueSinglePanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
    var dbIssuesBtn = document.getElementById('ccDbIssuesBtn');
    var dbIssuesPanel = document.getElementById('ccDbIssuesPanel');
    function setDbIssuesOpen(open) {
        if (!dbIssuesBtn || !dbIssuesPanel) return;
        dbIssuesPanel.classList.toggle('hidden', !open);
        dbIssuesBtn.classList.toggle('is-open', !!open);
        dbIssuesBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (dbIssuesBtn && dbIssuesPanel) {
        dbIssuesBtn.addEventListener('click', function () {
            setDbIssuesOpen(dbIssuesPanel.classList.contains('hidden'));
        });
        dbIssuesBtn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                setDbIssuesOpen(dbIssuesPanel.classList.contains('hidden'));
            }
        });
    }
    var serverPaging = false;
    try {
        var tenantSection = document.getElementById('tenant-control');
        serverPaging = !!(tenantSection && tenantSection.getAttribute('data-cc-server-paging') === '1');
    } catch (_) {
        serverPaging = false;
    }
    if (!serverPaging) {
        loadTenants();
    }

    // Release Wizard
    var rwForm = document.getElementById('rwForm');
    var rwScopeType = document.getElementById('rwScopeType');
    var rwOperation = document.getElementById('rwOperation');
    var rwCountry = document.getElementById('rwCountryId');
    var rwTenant = document.getElementById('rwTenantId');
    var rwOverride = document.getElementById('rwOverrideValue');
    function syncRwVisibility() {
        if (!rwScopeType) return;
        var scope = String(rwScopeType.value || 'global');
        var op = rwOperation ? String(rwOperation.value || 'apply') : 'apply';
        var isCountry = scope === 'country';
        var isTenant = scope === 'tenant';
        document.querySelectorAll('.rw-country-field').forEach(function (el) {
            el.classList.toggle('hidden', !isCountry);
        });
        document.querySelectorAll('.rw-tenant-field').forEach(function (el) {
            el.classList.toggle('hidden', !isTenant);
        });
        document.querySelectorAll('.rw-override-field').forEach(function (el) {
            el.classList.toggle('hidden', !(isCountry || isTenant) || op === 'rollback_scope');
        });
        if (rwCountry) rwCountry.required = isCountry;
        if (rwTenant) rwTenant.required = isTenant;
    }
    if (rwScopeType) rwScopeType.addEventListener('change', syncRwVisibility);
    if (rwOperation) rwOperation.addEventListener('change', syncRwVisibility);
    syncRwVisibility();

    document.querySelectorAll('.rw-example-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var flag = document.getElementById('rwFlagKey');
            var stage = document.getElementById('rwStage');
            var percent = document.getElementById('rwPercent');
            var def = document.getElementById('rwDefaultValue');
            var ov = document.getElementById('rwOverrideValue');
            if (flag) flag.value = btn.getAttribute('data-flag') || '';
            if (rwScopeType) rwScopeType.value = btn.getAttribute('data-scope') || 'global';
            if (stage) stage.value = btn.getAttribute('data-stage') || 'full';
            if (percent) percent.value = btn.getAttribute('data-percent') || '100';
            if (def) def.value = btn.getAttribute('data-default') || '0';
            if (rwOperation) rwOperation.value = btn.getAttribute('data-operation') || 'apply';
            if (ov) ov.value = btn.getAttribute('data-override') || '1';
            syncRwVisibility();
            if (rwForm) rwForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    // Bulk: select all + count + confirm word based on action
    var selectAll = document.getElementById('ccSelectAllTenants');
    var bulkForm = document.getElementById('ccTenantBulkForm');
    var bulkAction = document.getElementById('ccBulkAction');
    var bulkCount = document.getElementById('ccBulkCount');
    var bulkRunBtn = document.getElementById('ccBulkRunBtn');
    function updateBulkCount() {
        if (!bulkCount) return;
        var checked = document.querySelectorAll('.cc-tenant-check:checked').length;
        bulkCount.textContent = String(checked) + ' selected';
    }
    function updateBulkRunState() {
        if (!bulkRunBtn) return;
        var hasAction = !!(bulkAction && String(bulkAction.value || '').trim() !== '');
        var checked = document.querySelectorAll('.cc-tenant-check:checked').length;
        bulkRunBtn.disabled = !(hasAction && checked > 0);
    }
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var on = !!selectAll.checked;
            document.querySelectorAll('.cc-tenant-check').forEach(function (cb) {
                if (cb && !cb.disabled) cb.checked = on;
            });
            updateBulkCount();
            updateBulkRunState();
        });
    }
    document.querySelectorAll('.cc-tenant-check').forEach(function (cb) {
        cb.addEventListener('change', function () {
            updateBulkCount();
            updateBulkRunState();
        });
    });
    if (bulkAction) {
        bulkAction.addEventListener('change', function () {
            // Reset previous typed confirmation when action changes.
            if (bulkForm) {
                var input = bulkForm.querySelector('input[name="confirm_text"]');
                if (input) input.value = '';
                var wrap = bulkForm.querySelector('.cc-confirm-inline');
                if (wrap) wrap.remove();
                bulkForm.dataset.confirmed = '0';
            }
            updateBulkRunState();
        });
    }
    updateBulkCount();
    updateBulkRunState();
    if (bulkForm && bulkAction) {
        bulkForm.addEventListener('submit', function (e) {
            if (bulkForm.dataset.confirmed === '1') {
                bulkForm.dataset.confirmed = '0';
                return;
            }
            e.preventDefault();
            var act = String(bulkAction.value || '').toLowerCase();
            var required = act === 'suspend' ? 'SUSPEND' : (act === 'activate' ? 'ACTIVATE' : (act === 'delete' ? 'DELETE' : ''));
            if (!required) {
                showToast('Choose a bulk action first', 'danger');
                return;
            }
            var anyChecked = document.querySelectorAll('.cc-tenant-check:checked').length > 0;
            if (!anyChecked) {
                showToast('Select at least 1 tenant', 'danger');
                return;
            }
            var input = bulkForm.querySelector('input[name="confirm_text"]');
            if (!input) {
                showToast('Missing confirm field', 'danger');
                return;
            }
            var wrap = bulkForm.querySelector('.cc-confirm-inline');
            if (!wrap) {
                wrap = document.createElement('span');
                wrap.className = 'cc-confirm-inline';
                var hint = document.createElement('span');
                hint.className = 'cc-muted';
                hint.textContent = 'Type ' + required + ' then press Run again';
                var txt = document.createElement('input');
                txt.type = 'text';
                txt.autocomplete = 'off';
                txt.placeholder = required;
                txt.addEventListener('input', function () {
                    input.value = String(txt.value || '');
                });
                wrap.appendChild(hint);
                wrap.appendChild(txt);
                bulkForm.appendChild(wrap);
                window.setTimeout(function () { try { txt.focus(); } catch (_) {} }, 0);
                return;
            }
            var typed = String(input.value || '').trim().toUpperCase();
            if (typed !== required) {
                showToast('Confirmation text must be: ' + required, 'warning');
                return;
            }
            bulkForm.dataset.confirmed = '1';
            bulkForm.submit();
        });
    }

    // After bulk success (set by server), show popup and reset bulk controls.
    var bulkPopup = (typeof window !== 'undefined' && window.__ccBulkPopupMessage) ? String(window.__ccBulkPopupMessage) : '';
    if (bulkPopup) {
        showModernAlert(bulkPopup, 'safe');
        if (selectAll) selectAll.checked = false;
        document.querySelectorAll('.cc-tenant-check').forEach(function (cb) { cb.checked = false; });
        if (bulkAction) bulkAction.value = '';
        if (bulkForm) {
            var cInput = bulkForm.querySelector('input[name="confirm_text"]');
            if (cInput) cInput.value = '';
            var cWrap = bulkForm.querySelector('.cc-confirm-inline');
            if (cWrap) cWrap.remove();
            bulkForm.dataset.confirmed = '0';
        }
        updateBulkCount();
        updateBulkRunState();
    }
})();

