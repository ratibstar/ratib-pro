/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/dashboard.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/dashboard.js`.
 */
// EN: Dashboard widget bootstrap for users-per-country cards and shortcuts.
// AR: تهيئة ويدجت لوحة التحكم لبطاقات المستخدمين حسب الدولة والروابط المختصرة.
(function() {
    var config = document.getElementById('control-config');
    var apiBase = (config && config.getAttribute('data-api-base')) || '';
    var agenciesUrlBase = (config && config.getAttribute('data-agencies-url-base')) || '';
    var countryUsersUrlBase = (config && config.getAttribute('data-country-users-url-base')) || '';
    var ratibBase = (config && config.getAttribute('data-ratib-base')) || '';
    var tenantSelfTestUrl = (config && config.getAttribute('data-tenant-self-test-url')) || '';
    var tenantAllIntervalMs = Number((config && config.getAttribute('data-tenant-all-self-test-interval-ms')) || 0) || 300000;
    var grid = document.getElementById('usersPerCountryGrid');
    var runTenantSelfTestBtn = document.getElementById('runTenantSelfTestBtn');
    var tenantSelfTestResult = document.getElementById('tenantSelfTestResult');
    var runTenantAllSelfTestBtn = document.getElementById('runTenantAllSelfTestBtn');
    var tenantAllSelfTestResult = document.getElementById('tenantAllSelfTestResult');
    var tenantIsolationGlobalAlert = document.getElementById('tenantIsolationGlobalAlert');
    var tenantIsolationGlobalAlertText = document.getElementById('tenantIsolationGlobalAlertText');
    if (!grid || !apiBase) return;
    apiBase = apiBase.replace(/\/$/, '');
    ratibBase = ratibBase.replace(/\/$/, '') || (window.location.origin || '');
    /** If PHP pageUrl() base disagrees with this deployment, derive panel root from api path (same as dashboard.php $baseUrl). */
    if (!agenciesUrlBase && apiBase) {
        agenciesUrlBase = apiBase.replace(/\/?api\/control$/i, '') + '/pages/control/agencies.php?control=1';
    }
    if (!countryUsersUrlBase && apiBase) {
        countryUsersUrlBase = apiBase.replace(/\/?api\/control$/i, '') + '/pages/control/country-users.php?control=1';
    }

    // EN: Load country/user summary from control API using same-origin session cookies.
    // AR: جلب ملخص الدول/المستخدمين من API لوحة التحكم باستخدام كوكيز الجلسة.
    fetch(apiBase + '/get-users-per-country.php', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && Array.isArray(data.countries) && data.countries.length > 0) {
                grid.innerHTML = data.countries.map(function(c) {
                    var cid = encodeURIComponent(String(c.id != null ? c.id : ''));
                    var agenciesUrl = agenciesUrlBase ? (agenciesUrlBase + (agenciesUrlBase.indexOf('?') >= 0 ? '&' : '?') + 'country_id=' + cid) : '#';
                    var usersUrl = c.agency_id && countryUsersUrlBase ? (countryUsersUrlBase + '&agency_id=' + encodeURIComponent(String(c.agency_id))) : null;
                    var slug = (c.slug || '').trim();
                    var loginUrl = (ratibBase && slug) ? (ratibBase + '/' + slug + '/login') : null;
                    var linksHtml = '<div class="users-per-country-links">' +
                        (usersUrl ? '<a href="' + usersUrl + '" target="_blank" rel="noopener noreferrer">View Users &rarr;</a>' : '') +
                        '<a href="' + agenciesUrl + '">View Agencies &rarr;</a>' +
                        '</div>';
                    var cardClass = 'users-per-country-card' + (loginUrl ? ' users-per-country-card-clickable' : '');
                    var loginAttr = loginUrl ? (' data-login-url="' + loginUrl.replace(/"/g, '&quot;') + '"') : '';
                    return '<div class="' + cardClass + '"' + loginAttr + '>' +
                        '<div class="country-name">' + (c.name || 'Unknown') + '</div>' +
                        '<div class="users-count">' + (c.users_count || 0) + '</div>' +
                        '<div class="users-label">Users</div>' +
                        linksHtml +
                        '</div>';
                }).join('');
                // EN: Make card body open tenant login in new tab while preserving inner action links.
                // AR: جعل البطاقة تفتح تسجيل دخول المستأجر في تبويب جديد مع إبقاء الروابط الداخلية مستقلة.
                grid.querySelectorAll('.users-per-country-card-clickable').forEach(function(card) {
                    card.addEventListener('click', function(evt) {
                        if (evt.target && evt.target.closest('.users-per-country-links')) return;
                        var loginUrl = card.getAttribute('data-login-url') || '';
                        if (!loginUrl) return;
                        window.open(loginUrl, '_blank', 'noopener,noreferrer');
                    });
                });
            } else {
                grid.innerHTML = '<div class="text-muted control-empty-state">No countries configured.</div>';
            }
        })
        .catch(function() {
            grid.innerHTML = '<div class="text-muted control-empty-state">Failed to load users per country.</div>';
        });

    function setSelfTestResult(status, text) {
        if (!tenantSelfTestResult) return;
        tenantSelfTestResult.classList.remove('tenant-self-test-idle', 'tenant-self-test-running', 'tenant-self-test-pass', 'tenant-self-test-fail');
        tenantSelfTestResult.classList.add('tenant-self-test-' + status);
        tenantSelfTestResult.innerHTML = '<span class="tenant-self-test-badge">' + status.toUpperCase() + '</span>' +
            '<span class="tenant-self-test-text">' + text + '</span>';
    }

    function buildTenantSelfTestUrl() {
        // Prefer control-panel API namespace because this dashboard is routed there.
        var base = apiBase ? (apiBase + '/tenant-isolation-self-test.php') : '';
        if (!base) base = tenantSelfTestUrl;
        if (!base) {
            base = apiBase.replace(/\/?api\/control$/i, '') + '/api/diagnostics/tenant-isolation-self-test.php';
        }
        try {
            var current = new URL(window.location.href);
            var url = new URL(base, window.location.origin);
            if (current.searchParams.get('control') === '1') {
                url.searchParams.set('control', '1');
            }
            var agencyId = current.searchParams.get('agency_id');
            if (agencyId) {
                url.searchParams.set('agency_id', agencyId);
            }
            return url.toString();
        } catch (_) {
            return base;
        }
    }

    if (runTenantSelfTestBtn && tenantSelfTestResult) {
        if (runTenantSelfTestBtn.getAttribute('data-tenant-self-test-bound') === '1') {
            return;
        }
        runTenantSelfTestBtn.setAttribute('data-tenant-self-test-bound', '1');
        runTenantSelfTestBtn.addEventListener('click', function() {
            var endpoint = buildTenantSelfTestUrl();
            runTenantSelfTestBtn.disabled = true;
            setSelfTestResult('running', 'Running isolation checks...');
            fetch(endpoint, { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || data.success !== true) {
                        setSelfTestResult('fail', 'Test failed to run.');
                        return;
                    }
                    if (data.isolation_ok === true) {
                        var dbName = (data.runtime_context && data.runtime_context.db_name_active) ? data.runtime_context.db_name_active : 'N/A';
                        setSelfTestResult('pass', 'PASS - isolation is healthy. Active DB: ' + dbName);
                    } else {
                        var failed = Array.isArray(data.failed_strict_checks) ? data.failed_strict_checks : [];
                        var shortMsg = failed.length > 0
                            ? ('FAIL - ' + failed.slice(0, 2).join(', ') + (failed.length > 2 ? ' ...' : ''))
                            : 'FAIL - one or more checks failed.';
                        setSelfTestResult('fail', shortMsg);
                    }
                })
                .catch(function() {
                    setSelfTestResult('fail', 'Request error while running test.');
                })
                .finally(function() {
                    runTenantSelfTestBtn.disabled = false;
                });
        });
    }

    function setAllResult(status, text) {
        if (!tenantAllSelfTestResult) return;
        tenantAllSelfTestResult.classList.remove('tenant-self-test-idle', 'tenant-self-test-running', 'tenant-self-test-pass', 'tenant-self-test-fail');
        tenantAllSelfTestResult.classList.add('tenant-self-test-' + status);
        tenantAllSelfTestResult.innerHTML = '<span class="tenant-self-test-badge">' + status.toUpperCase() + '</span>' +
            '<span class="tenant-self-test-text">' + text + '</span>';
    }

    function setGlobalIsolationAlert(show, text) {
        if (!tenantIsolationGlobalAlert || !tenantIsolationGlobalAlertText) return;
        if (show) {
            tenantIsolationGlobalAlertText.textContent = text || 'Tenant isolation issue detected.';
            tenantIsolationGlobalAlert.classList.remove('is-hidden');
        } else {
            tenantIsolationGlobalAlert.classList.add('is-hidden');
        }
    }

    function runAllAgenciesAudit(triggeredByUser) {
        // If feature is disabled, the "Run All Agencies" button is not rendered with this id.
        if (!runTenantAllSelfTestBtn) {
            return;
        }
        if (!apiBase) return;
        if (runTenantAllSelfTestBtn) {
            runTenantAllSelfTestBtn.disabled = true;
        }
        setAllResult('running', triggeredByUser ? 'Running all agencies audit...' : 'Auto-checking all agencies...');
        fetch(apiBase + '/agencies-audit.php', { credentials: 'same-origin' })
            .then(function(r) {
                return r.text().then(function(text) {
                    var parsed = null;
                    try { parsed = JSON.parse(text); } catch (_) {}
                    return { ok: r.ok, status: r.status, data: parsed, raw: text };
                });
            })
            .then(function(resp) {
                var data = resp.data;
                if (!resp.ok) {
                    var msg = (data && data.message) ? data.message : ('HTTP ' + resp.status);
                    setAllResult('fail', 'All agencies test error: ' + msg);
                    setGlobalIsolationAlert(true, 'Tenant isolation auto-check failed: ' + msg);
                    return;
                }
                if (!data || data.success !== true) {
                    setAllResult('fail', 'All agencies test failed to run.');
                    setGlobalIsolationAlert(true, 'Tenant isolation auto-check failed to run.');
                    return;
                }
                var summary = data.summary || {};
                var total = Number(summary.agencies_total || 0);
                var ok = Number(summary.db_connect_ok || 0);
                var failed = Number(summary.db_connect_failed || 0);
                var isolationReady = Number(summary.isolation_ready || 0);
                var isolationFailed = Number(summary.isolation_failed || 0);
                var fullReady = Number(summary.full_ready || 0);
                if (failed === 0 && isolationFailed === 0 && total > 0 && isolationReady === total) {
                    setAllResult('pass', 'PASS - all agencies isolated. Total: ' + total + ', DB ok: ' + ok);
                    setGlobalIsolationAlert(false, '');
                } else {
                    setAllResult('fail', 'FAIL - total: ' + total + ', db ok: ' + ok + ', db failed: ' + failed + ', isolation ready: ' + isolationReady + ', full ready: ' + fullReady);
                    setGlobalIsolationAlert(true, 'Isolation alert: ' + failed + ' DB failures / ' + isolationFailed + ' isolation failures detected.');
                }
            })
            .catch(function() {
                setAllResult('fail', 'Request error while auditing all agencies.');
                setGlobalIsolationAlert(true, 'Tenant isolation auto-check request error.');
            })
            .finally(function() {
                if (runTenantAllSelfTestBtn) {
                    runTenantAllSelfTestBtn.disabled = false;
                }
            });
    }

    if (runTenantAllSelfTestBtn && tenantAllSelfTestResult) {
        runTenantAllSelfTestBtn.addEventListener('click', function() {
            runAllAgenciesAudit(true);
        });
    }

    // Start periodic auto-check only when all-agencies audit is enabled.
    if (runTenantAllSelfTestBtn) {
        setTimeout(function() { runAllAgenciesAudit(false); }, 2000);
        if (tenantAllIntervalMs >= 60000) {
            setInterval(function() { runAllAgenciesAudit(false); }, tenantAllIntervalMs);
        }
    }
})();
